<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;

use OC\Mail\EMailTemplate;
use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Linkify;
use OCA\DAV\Events\CalendarObjectMovedToTrashEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\SubscriptionDeletedEvent;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class DavListener implements IEventListener
{

    private const VIDEO_NONE = 0;
    private const VIDEO_TALK = 1;
    private const VIDEO_BBB = 2;

    private $appName;
    private $l10N;
    private $logger;
    private $utils;

    /** @type IMailer */
    private $mailer;

    /** @type IConfig */
    private $config;

    private $linkify;

    private $l10nFactory;

    public function __construct(\OCP\IL10N      $l10N,
                                IFactory        $l10nFactory,
                                LoggerInterface $logger,
                                BackendUtils    $utils)
    {
        $this->l10N = $l10N;
        $this->logger = $logger;
        $this->utils = $utils;

        $this->mailer = \OC::$server->get(IMailer::class);
        $this->config = \OC::$server->get(IConfig::class);

        $this->linkify = new Linkify();

        $this->l10nFactory = $l10nFactory;
    }

    function handle(Event $event): void
    {
        if ($event instanceof CalendarObjectUpdatedEvent) {
            $this->handler($event->getObjectData(), $event->getCalendarData(), false);
        } elseif ($event instanceof CalendarObjectMovedToTrashEvent) {
            $this->handler($event->getObjectData(), $event->getCalendarData(), true);
        } elseif ($event instanceof SubscriptionDeletedEvent) {
            // clean BackendUtils::SYNC_TABLE_NAME
            $this->utils->removeSubscriptionSync($event->getSubscriptionId());
        }
    }

    public function handleOld(\Symfony\Component\EventDispatcher\GenericEvent $event, string $eventName): void
    {
        $this->handler($event['objectData'], $event['calendarData'], $eventName === '\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject');
    }

    /**
     * @param int $lastStart timestamp set by IJobList->setLastRun() product of time() func
     */
    public function handleReminders(int $lastStart, IDBConnection $db, IBackendConnector $bc): void
    {
        // we need to pull all pending appointments between now + 42 min( 1 hour [min delta] - 18 min [time between jobs]) and now + 7 days(max delta)
        $now = time();
        $qb = $db->getQueryBuilder();
        try {
            $result = $qb->select('hash.*',
                'pref.' . BackendUtils::KEY_REMINDERS)
                ->from(BackendUtils::HASH_TABLE_NAME, 'hash')
                ->leftJoin('hash', BackendUtils::PREF_TABLE_V2_NAME, 'pref', $qb->expr()->andX(
                    $qb->expr()->eq(
                        'hash.' . BackendUtils::KEY_USER_ID,
                        'pref.' . BackendUtils::KEY_USER_ID),
                    $qb->expr()->eq(
                        'hash.' . BackendUtils::KEY_PAGE_ID,
                        'pref.' . BackendUtils::KEY_PAGE_ID)))
                ->where($qb->expr()->isNotNull('pref.' . BackendUtils::KEY_REMINDERS))
                ->andWhere($qb->expr()->eq('hash.status', $qb->createNamedParameter(BackendUtils::PREF_STATUS_CONFIRMED, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->gte('hash.start', $qb->createNamedParameter($now + 2520, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->lte('hash.start', $qb->createNamedParameter($now + 604800, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->isNotNull('hash.' . BackendUtils::KEY_USER_ID))
                ->andWhere($qb->expr()->isNotNull('hash.' . BackendUtils::KEY_PAGE_ID))
                ->andWhere($qb->expr()->isNotNull('hash.uri'))
                ->orderBy('hash.' . BackendUtils::KEY_USER_ID)
                ->execute();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }

        $userId = '';
        $pageId = '';
        $remDataArray = [];

        // The first loop is to collect data from DB and close the connection,
        // the second loop actually sends the emails
        $remindersToSend = [];
        while ($row = $result->fetch()) {

            $reuseSettings = true;
            if ($userId !== $row[BackendUtils::KEY_USER_ID]
                || $pageId !== $row[BackendUtils::KEY_PAGE_ID]) {

                $userId = $row[BackendUtils::KEY_USER_ID];
                $pageId = $row[BackendUtils::KEY_PAGE_ID];
                $remObj = json_decode($row[BackendUtils::KEY_REMINDERS], true);
                if ($remObj === null) {
                    $this->logger->error("json_decode failed for, userId: " . $userId . ", pageId: " . $pageId);
                    continue;
                }
                $reuseSettings = false;
                $remDataArray = $remObj[BackendUtils::REMINDER_DATA];
            }

            foreach ($remDataArray as $remData) {
                $remindAt = $row['start'] - $remData[BackendUtils::REMINDER_DATA_TIME];

//                $this->logger->error($remindAt . ', '
//                    . $row['start'] . ', '
//                    . $remData[BackendUtils::REMINDER_DATA_TIME]);

                // $lastStart is set at the START of previous job
                // for next job $lastStart is already set to basically $now
                if ($remindAt >= $lastStart && $remindAt < $now) {
                    $remindersToSend[] = [
                        'userId' => $userId,
                        'pageId' => $pageId,
                        'actions' => $remData[BackendUtils::REMINDER_DATA_ACTIONS],
                        'evtUri' => $row['uri'],
                        'evtUid' => $row['uid'],
                        'apptDoc' => is_resource($row['appt_doc'])
                            ? stream_get_contents($row['appt_doc'])
                            : $row['appt_doc'],
                        'reuseSettings' => $reuseSettings
                    ];
                }
            }

        }
        $result->closeCursor();

//        $this->logger->error('rts: ' . var_export($remindersToSend, true));

        if (count($remindersToSend) === 0) {
            // nothing to do
            return;
        }

        // just in-case
        $remindersToSend[0]['reuseSettings'] = false;

        $config = $this->config;
        $mailer = $this->mailer;

        $utils = $this->utils;
        $utz = new \DateTimeZone('utc');

        $calId = '-1';
        $otherCalId = '-1';
        $extNotifyFilePath = '';
        $settings = [];

        $doc = new ApptDocProp();

        // This loop sends out emails (there is .5sec sleep between each send)
        foreach ($remindersToSend as $remInfo) {
            if ($remInfo['reuseSettings'] === false) {
                $userId = $remInfo['userId'];
                $pageId = $remInfo['pageId'];

                if (!$utils->loadSettingsForUserAndPage($userId, $pageId)) {
                    $this->logger->error("loadSettingsForUserAndPage failed, userId: " . $userId . ", pageId: " . $pageId);
                    continue;
                }

                $settings = $utils->getUserSettings();
                if (!isset($settings[BackendUtils::KEY_REMINDERS])) {
                    $this->logger->error(" missing settings reminder data, userId: " . $userId . ", pageId: " . $pageId);
                    continue;
                }

                $extNotifyFilePath = $config->getAppValue(Application::APP_ID, 'ext_notify_' . $userId);

                $otherCalId = '-1';
                $calId = $utils->getMainCalId($userId, null, $otherCalId);
                if ($calId === '-1') {
                    $this->logger->error("can not find main calendar, userId: " . $userId . ", pageId: " . $pageId);
                    continue;
                }
                if ($otherCalId !== '-1'
                    && $settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                    // if we have a dst calendar in simple mode than it will hold confirmed appointments, so we should check it first and then check the src calendar just in-case settings have been changed after the appointment was booked
                    $temp = $calId;
                    $calId = $otherCalId;
                    $otherCalId = $temp;
                } else {
                    // dst calendar is only valid in simple mode
                    $otherCalId = '-1';
                }

                $utz = $this->utils->getCalendarTimezone($userId, $bc->getCalendarById($calId, $userId));
            }

            // settings are good at this point

            $evtUri = $remInfo['evtUri'];
            $data = $bc->getObjectData($calId, $evtUri);

            if ($data === null && $otherCalId !== '-1') {
                $data = $bc->getObjectData($otherCalId, $evtUri);
            }

            if ($data === null) {
                $this->logger->error("can not get object data, uri: " . $evtUri . ", calId: " . $calId);
                continue;
            }

            if (!str_contains($data, "\r\nATTENDEE;")
                || (!str_contains($data, "\r\n" . BackendUtils::TZI_PROP . ":")
                    && !str_contains($data, "\r\n" . ApptDocProp::PROP_NAME . ":"))
            ) {
                $this->logger->error('bad event data, uid: ' . $remInfo['evtUid']);
                continue;
            }

            $vObject = Reader::read($data);
            if (!isset($vObject->VEVENT)) {
                $this->logger->error("Reader::read failed, uid: " . $remInfo['evtUid']);
                $vObject->destroy();
                continue;
            }

            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vObject->VEVENT;
            if (!isset($evt->UID)
                || !isset($evt->ATTENDEE)
                || !isset($evt->STATUS)
                || !isset($evt->DTEND)
                || !isset($evt->ORGANIZER)
                || $evt->STATUS->getValue() !== 'CONFIRMED'
                || (!isset($evt->{BackendUtils::XAD_PROP})
                    && !isset($evt->{ApptDocProp::PROP_NAME}))
            ) {
                $this->logger->error('bad event object, uid: ' . $remInfo['evtUid']);
                $vObject->destroy();
                continue;
            }

            $att = $utils->getAttendee($evt);
            if ($att === null || $att->parameters['PARTSTAT']->getValue() === 'DECLINED') {
                $this->logger->error('bad attendee data, uid: ' . $remInfo['evtUid']);
                $vObject->destroy();
                continue;
            }

            $to_name = $att->parameters['CN']->getValue();
            if (empty($to_name) || preg_match('/[^\PC ]/u', $to_name)) {
                $this->logger->error('invalid attendee name, uid: ' . $remInfo['evtUid']);
                $vObject->destroy();
                continue;
            }
            $att_v = $att->getValue();
            $to_email = substr($att_v, strpos($att_v, ":") + 1);
            if ($mailer->validateMailAddress($to_email) === false) {
                $this->logger->error('invalid attendee email, uid: ' . $remInfo['evtUid']);
                $vObject->destroy();
                continue;
            }

            // event data looks ok...

            if (isset($evt->{ApptDocProp::PROP_NAME})) {
                if (!empty($remInfo['apptDoc']) && strlen($remInfo['apptDoc']) > 8) {
                    $doc->setFromString(substr($remInfo['apptDoc'], 8), 'dummy_evt_uid');
                } else {
                    $doc->reset();
                }
                $date_time = $utils->getDateTimeString(
                    $evt->DTSTART->getDateTime(),
                    $doc->attendeeTimezone
                );
            } else {
                $date_time = $utils->getDateTimeString(
                    $evt->DTSTART->getDateTime(),
                    $evt->{BackendUtils::TZI_PROP}->getValue()
                );
            }

            list($org_email, $org_name, $org_phone) = $this->getOrgInfo();
            $tmpl = $this->getEmailTemplate();

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} appointment reminder
            $tmpl->setSubject($this->l10N->t("%s appointment reminder", [$org_name]));
            $tmpl->addHeading(" "); // spacer
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
                $this->l10N->t("Dear %s,", [$to_name])
            ]));


            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                !empty($org_phone)
                    // TRANSLATORS Main part of email (if organization phone number is provided), Ex: This is a reminder from {{Organization Name}} about your upcoming appointment on {{Date And Time}}. If you need to reschedule, please call {{Organization Phone}}.
                    ? $this->l10N->t('This is a reminder from %1$s about your upcoming appointment on %2$s. If you need to reschedule, please call %3$s.', [$org_name, $date_time, $org_phone])
                    // TRANSLATORS Main part of email (if organization phone number is missing), Ex: This is a reminder from {{Organization Name}} about your upcoming appointment on {{Date And Time}}. If you need to reschedule, please write to {{Organization Email}}.
                    : $this->l10N->t('This is a reminder from %1$s about your upcoming appointment on %2$s. If you need to reschedule, please write to %3$s.', [$org_name, $date_time, $org_email])
            ]));

            $cnl_lnk_url = '';

            // do we want links and buttons ?
            if ($remInfo['actions']) {

                if (isset($evt->{ApptDocProp::PROP_NAME})) {
                    $embed = $doc->embed;

                    // overwrite.cli.url must be set if $embed is not used
                    if ($embed || filter_var(
                            $config->getSystemValue('overwrite.cli.url'),
                            FILTER_VALIDATE_URL) !== false
                    ) {

                        list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                            $userId, $pageId, $embed,
                            $evtUri, $config);
                        $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

                        $videoType = $this->getVideoType($settings);
                        if ($videoType !== self::VIDEO_NONE) {
                            if (($videoType === self::VIDEO_TALK
                                    && $settings[BackendUtils::TALK_FORM_ENABLED])
                                || ($videoType === self::VIDEO_BBB
                                    && $settings[BackendUtils::BBB_FORM_ENABLED])
                            ) {
                                $has_link = !empty($doc->talkToken . $doc->bbbToken);
                                if ($has_link) {
                                    $this->addVideoLinkInfo(
                                        $userId, $tmpl, $doc, $settings,
                                        $config->getUserValue($userId, Application::APP_ID, "c" . "nk")
                                    );
                                }
                                $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                            }
                        }
                    } else {
                        $this->logger->error('can not add actions to reminder, missing overwrite.cli.url');
                    }

                } else {
                    // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
                    $xad = explode(chr(31), $utils->decrypt(
                        $evt->{BackendUtils::XAD_PROP}->getValue(),
                        $evt->UID->getValue()));
                    if (count($xad) > 2) {
                        $embed = $xad[3] === "1";
                    } else {
                        $embed = false;
                    }

                    // overwrite.cli.url must be set if $embed is not used
                    if ($embed || $config->getSystemValue('overwrite.cli.url') !== '') {

                        list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                            $userId, $pageId, $embed,
                            $evtUri, $config);
                        $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

                        if (!empty($xad) && count($xad) > 4) {

                            $has_link = strlen($xad[4]) > 1;
                            if ($settings[BackendUtils::TALK_ENABLED]) {
                                if ($settings[BackendUtils::TALK_FORM_ENABLED] === true) {
                                    if ($has_link) {
                                        $ti = new TalkIntegration($settings, $utils);
                                        // add talk link info
                                        $this->addTalkInfo(
                                            $tmpl, $xad, $ti, $settings,
                                            $config->getUserValue($userId, Application::APP_ID, "c" . "nk"));
                                    }
                                    $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                                }
                            }
                        }
                    } else {
                        $this->logger->error('can not add actions to reminder, missing overwrite.cli.url');
                    }
                }
            }

            $remObj = $settings[BackendUtils::KEY_REMINDERS];
            if (!empty($remObj[BackendUtils::REMINDER_MORE_TEXT])) {
                list($remHtml, $remPlainText) = $this->prepHtmlEmailText($remObj[BackendUtils::REMINDER_MORE_TEXT]);
                if ($remHtml === null) {
                    $tmpl->addBodyText(...$this->formatEmailBodyHtml([$remPlainText]));
                } else {
                    $tmpl->addBodyText(...$this->formatEmailBodyHtml([$remHtml, $remPlainText]));
                }
            }

            // everything is ready, send email...
            $this->finalizeEmailText($tmpl, $cnl_lnk_url);

            $msg = $mailer->createMessage();

            $this->setFromAddress($msg, $userId, $org_email, $org_name);

            $msg->setTo(array($to_email));
            $msg->useTemplate($tmpl);

            $description = '';

            try {
                $mailer->send($msg);

                if (!isset($evt->DESCRIPTION)) {
                    $evt->add('DESCRIPTION');
                }
                $description = $evt->DESCRIPTION->getValue();
                // TRANSLATORS Ex: Reminder sent on {{Date and Time}},
                $description .= "\n" . $this->l10N->t("Reminder sent on %s", [$utils->getDateTimeString(
                        new \DateTimeImmutable('now', $utz),
                        "T" . $utz->getName()
                        , 1
                    )]);
                $evt->DESCRIPTION->setValue($description);
                if ($bc->updateObject($calId, $evtUri, $vObject->serialize()) === false) {
                    $this->logger->error("Can not update object uid: " . $remInfo['evtUid']);
                }
            } catch (\Exception $e) {
                $this->logger->error("Can not send email to " . $to_email . ", uid: " . $remInfo['evtUid']);
                $this->logger->error($e->getMessage());
            }

            // advanced/extensions
            if ($extNotifyFilePath !== "") {
                $data = [
                    'eventType' => 4,
                    'dateTime' => $evt->DTSTART->getDateTime(),
                    'attendeeName' => $to_name,
                    'attendeeEmail' => $to_email,
                    'attendeeTel' => $this->getPhoneFromDescription($description),
                    'pageId' => $pageId
                ];
                $this->extNotify($data, $userId, $extNotifyFilePath);
            }

            // remove all circular references, so PHP can easily clean it up.
            $vObject->destroy();

            usleep(320000);
        }
    }

    private function handler(array $objectData, array $calendarData, bool $isDelete): void
    {

//        \OC::$server->getLogger()->error('DL Debug: M0');

        // objectUri
        if (!isset($objectData['calendardata']) ||
            !isset($objectData['uri'])) {
            return;
        }
        $cd = $objectData['calendardata'];

        if (!str_contains($cd, "\r\nATTENDEE;")
            || !str_contains($cd, "\r\nCATEGORIES:" . BackendUtils::APPT_CAT . "\r\n")
            || (!str_contains($cd, "\r\n" . BackendUtils::TZI_PROP . ":")
                && !str_contains($cd, "\r\n" . ApptDocProp::PROP_NAME . ":"))
            || !str_contains($cd, "\r\nORGANIZER;")
            || !str_contains($cd, "\r\nUID:")) {
            // Not a good appointment, bail early...
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M1');

        $hint = HintVar::getHint();
        if ($hint === HintVar::APPT_SKIP
            || ($isDelete && $hint === HintVar::APPT_CONFIRM) // <-- booking in to a different calendar NOT deleting
        ) {
            // no need for email
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M2');

        $vObject = Reader::read($cd);
        if (!isset($vObject->VEVENT)) {
            // Not a VEVENT
            return;
        }
        /** @var \Sabre\VObject\Component\VEvent $evt */
        $evt = $vObject->VEVENT;
        if (!isset($evt->UID)) {
            $this->logger->error('UID not found');
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M3');

        $utils = $this->utils;
        $config = $this->config;
        $doc = null;
        if (isset($evt->{ApptDocProp::PROP_NAME})) {
            $doc = $utils->getApptDoc($evt);
            $embed = $doc->embed;
            $hashRow = $this->utils->getApptHashRow($evt->UID->getValue());
            if (!$hashRow) {
                $this->logger->error("hashRow not found");
                return;
            }
            $userId = $hashRow['user_id'];
            $pageId = $hashRow['page_id'];
        } elseif (isset($evt->{BackendUtils::XAD_PROP})) {
            // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
            $xad = explode(chr(31), $utils->decrypt(
                $evt->{BackendUtils::XAD_PROP}->getValue(),
                $evt->UID->getValue()));
            $userId = $xad[0];
            if (count($xad) > 2) {
                $pageId = $xad[2];
                $embed = $xad[3] === "1";
            } else {
                $pageId = 'p0';
                $embed = false;
            }
        } else {
            $this->logger->error("XAD_PROP not found");
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M5');

        if ($utils->loadSettingsForUserAndPage($userId, $pageId) === false) {
            return;
        }

        $other_cal = '-1';
        $cal_id = $utils->getMainCalId($userId, null, $other_cal);

        $settings = $this->utils->getUserSettings();

        if ($other_cal !== '-1') {
            // only allowed in simple
            if ($settings[BackendUtils::CLS_TS_MODE] !== '0') {
                $other_cal = '-1';
            }
        }

        // Check cal IDs.
        // $calendarData['id'] can be a string or an int
        if ($cal_id != $calendarData['id'] && $other_cal != $calendarData['id']) {
            // Not this user's calendar
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M6');

        $hash = $utils->getApptHash($evt->UID->getValue());
        if ($isDelete) {
            $utils->deleteApptHash($evt);
        }

        if ($hash === null
            || !isset($evt->ATTENDEE)
            || !isset($evt->STATUS)
            || !isset($evt->DTEND)
            || !isset($evt->ORGANIZER)
        ) {
            // Bad data
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M7');

        $utz = $utils->getCalendarTimezone($userId, $utils->transformCalInfo($calendarData));
        try {
            $now = new \DateTime('now', $utz);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage() . ", timezone: " . $utz->getName());
            return;
        }

        // TODO: this needs to be fixed @see BackendUtils->encodeCalendarData
        $now_f = (float)$now->format(BackendUtils::FLOAT_TIME_FORMAT);
        if ($now_f > (float)str_replace("T", ".", $evt->DTEND->getRawMimeDirValue())
            && $now_f > $utils->getHashDTStart($hash)
        ) {
            // Event is in the past
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M8');

        $hash_ch = $utils->getHashChanges($hash, $evt);

        $att = $utils->getAttendee($evt);
        if ($att === null
            || ($hint === HintVar::APPT_NONE
                && ($att->parameters['PARTSTAT']->getValue() === 'DECLINED'
                    || ($hash_ch === null && !$isDelete)
                    || $utils->isApptCancelled($hash, $evt) === true
                )
            )) {
            // Bad attendee value or no significant external changes
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M9');

        $to_name = $att->parameters['CN']->getValue();
        if (empty($to_name) || preg_match('/[^\PC ]/u', $to_name)) {
            $this->logger->error("invalid attendee name");
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M10');

        $mailer = $this->mailer;

        $att_v = $att->getValue();
        $to_email = substr($att_v, strpos($att_v, ":") + 1);
        if ($mailer->validateMailAddress($to_email) === false) {
            $this->logger->error("invalid attendee email");
            return;
        }

//        \OC::$server->getLogger()->error('DL Debug: M11');

        if ($doc) {
            $date_time = $utils->getDateTimeString(
                $evt->DTSTART->getDateTime(),
                $doc->attendeeTimezone
            );
        } else {
            $date_time = $utils->getDateTimeString(
                $evt->DTSTART->getDateTime(),
                $evt->{BackendUtils::TZI_PROP}->getValue()
            );
        }

        list($org_email, $org_name, $org_phone) = $this->getOrgInfo();

        $is_cancelled = false;

        $tmpl = $this->getEmailTemplate();


        if (!empty($userId) && empty($this->config->getSystemValue('force_language', false))) {
            // https://github.com/SergeyMosin/Appointments/issues/158
            // $this->l10N is initialized with attendee's lang/locale
            // which might be different from organizer's
            $userLang = $this->config->getUserValue($userId, 'core', 'lang', null);
            $userLocale = $this->config->getUserValue($userId, 'core', 'locale', null);

            $organizerL10n = $this->l10nFactory->get(Application::APP_ID, $userLang, $userLocale);
        } else {
            $organizerL10n = $this->l10N;
        }

        // Message the organizer
        $om_prefix = "";
        // Description can get overwritten when the .ics attachment is constructed, so get it here
        if (isset($evt->DESCRIPTION)) {
            $om_info = $evt->DESCRIPTION->getValue();
        } else {
            $om_info = "";
        }

        // cancellation link for confirmation emails
        $cnl_lnk_url = "";
        // this is used to stop .ics file attachment on external actions when PARTSTAT:NEEDS-ACTION
        $no_ics = false;
        $talk_link_txt = '';

//        \OC::$server->getLogger()->error('DL Debug: M12');

        $ext_event_type = -1;

        if ($hint === HintVar::APPT_BOOK) {
            // Just booked, send email to the attendee requesting confirmation...

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} Appointment (action needed)
            $tmpl->setSubject($this->l10N->t("%s appointment (action needed)", [$org_name]));
            $tmpl->addHeading(" "); // spacer
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
                $this->l10N->t("Dear %s,", [$to_name])
            ]));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS Main part of email, Ex: The {{Organization Name}} appointment scheduled for {{Date Time}} is awaiting your confirmation.
                $this->l10N->t('The %1$s appointment scheduled for %2$s is awaiting your confirmation.', [$org_name, $date_time])
            ]));

            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);

            $tmpl->addBodyButtonGroup(
                $this->l10N->t("Confirm"),
                $btn_url . '1' . $btn_tkn,
                $this->l10N->t("Cancel"),
                $btn_url . '0' . $btn_tkn
            );

            if (!empty($settings[BackendUtils::EML_VLD_TXT])) {
                $this->addMoreEmailText($tmpl, $settings[BackendUtils::EML_VLD_TXT]);
            }

            if ($settings[BackendUtils::EML_MREQ]) {
                $om_prefix = $organizerL10n->t("Appointment pending");
            }

        } elseif ($hint === HintVar::APPT_CONFIRM) {
            // Confirm link in the email is clicked ...
            // ... or the email validation step is skipped

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} Appointment is Confirmed
            $tmpl->setSubject($this->l10N->t("%s Appointment is confirmed", [$org_name]));
            $tmpl->addHeading(" "); // spacer
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([$to_name . ","]));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS Main body of email,Ex: Your {{Organization Name}} appointment scheduled for {{Date Time}} is now confirmed.
                $this->l10N->t('Your %1$s appointment scheduled for %2$s is now confirmed.', [$org_name, $date_time])
            ]));

            // add cancellation link
            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);
            $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

            if ($doc) {
                $videoType = $this->getVideoType($settings);
                if ($videoType !== self::VIDEO_NONE) {

                    $talk_link_txt = $this->addVideoLinkInfo(
                        $userId, $tmpl, $doc, $settings,
                        $config->getUserValue($userId, Application::APP_ID, "c" . "nk")
                    );

                    if (($videoType === self::VIDEO_TALK
                            && $settings[BackendUtils::TALK_FORM_ENABLED])
                        || ($videoType === self::VIDEO_BBB
                            && $settings[BackendUtils::BBB_FORM_ENABLED])
                    ) {
                        // we need 'Meeting Type' and `Type Change` info
                        $has_link = !empty($talk_link_txt);

                        $tmpl->addBodyText(...$this->formatEmailBodyHtml([$this->makeMeetingTypeInfo($settings, $has_link)]));

                        $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                    }
                }
            } else {
                if (!empty($xad) && count($xad) > 4) {

                    $has_link = strlen($xad[4]) > 1;
                    if ($settings[BackendUtils::TALK_ENABLED]) {
                        if ($has_link) {
                            $ti = new TalkIntegration($settings, $utils);
                            // add talk link info
                            $talk_link_txt = $this->addTalkInfo(
                                $tmpl, $xad, $ti, $settings,
                                $config->getUserValue($userId, Application::APP_ID, "c" . "nk"));
                        }

                        if ($settings[BackendUtils::TALK_FORM_ENABLED] === true) {
                            if (!$has_link) {
                                // add in-person meeting type
                                $tmpl->addBodyText(...$this->formatEmailBodyHtml([$this->makeMeetingTypeInfo($settings, $has_link)]));
                            }
                            $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                        }
                    }
                }
            }

            if (!empty($settings[BackendUtils::EML_CNF_TXT])) {
                $this->addMoreEmailText($tmpl, $settings[BackendUtils::EML_CNF_TXT]);
            } elseif (isset($evt->LOCATION) && filter_var($evt->LOCATION->getValue(), FILTER_VALIDATE_URL) !== false) {
                $locationUrl = $evt->LOCATION->getValue();
                $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                    $this->l10N->t('Location: ') . '<a href="' . $locationUrl . '">' . $locationUrl . '</a>',
                    $this->l10N->t('Location: ') . $locationUrl
                ]));
            }

            if ($settings[BackendUtils::EML_MCONF]) {
                $om_prefix = $organizerL10n->t("Appointment confirmed");
            }

            $ext_event_type = 0;

        } elseif ($hint === HintVar::APPT_CANCEL || $isDelete) {
            // Canceled or deleted

            if ($hint !== HintVar::APPT_NONE) {
                // Cancelled by the attendee (via the email link)

                // TRANSLATORS Subject for email, Ex: {{Organization Name}} Appointment is Canceled
                $tmpl->setSubject($this->l10N->t("%s Appointment is canceled", [$org_name]));
            } else {
                // Cancelled/deleted by the organizer

                // TRANSLATORS Subject for email, Ex: {{Organization Name}} Appointment Status Changed
                $tmpl->setSubject($this->l10N->t("%s appointment status changed", [$org_name]));
            }
            $tmpl->addHeading(" "); // spacer

            $tmpl->addBodyText(...$this->formatEmailBodyHtml([$to_name . ","]));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS Main body of email,Ex: Your {{Organization Name}} appointment scheduled for {{Date Time}} is now canceled.
                $this->l10N->t('Your %1$s appointment scheduled for %2$s is now canceled.', [$org_name, $date_time])
            ]));
            $is_cancelled = true;

            if ($settings[BackendUtils::EML_MCNCL] && $hint !== HintVar::APPT_NONE) {
                $om_prefix = $organizerL10n->t("Appointment canceled");
            }

            if ($isDelete) {
                if ($doc) {
                    if (!empty($doc->talkToken)) {
                        if ($settings[BackendUtils::TALK_DEL_ROOM] === true) {
                            $ti = new TalkIntegration($settings, $utils);
                            $ti->deleteRoom($doc->talkToken);
                        }
                    }
                    if (!empty($doc->bbbToken)) {
                        if ($settings[BackendUtils::BBB_DEL_ROOM] === true) {
                            $bi = \OC::$server->get(BbbIntegration::class);
                            $bi->deleteRoom($doc->bbbToken, $userId);
                        }
                    }
                } else {
                    if ($settings[BackendUtils::TALK_DEL_ROOM] === true) {
                        if (!empty($xad) && count($xad) > 4 && strlen($xad[4]) > 1) {
                            $ti = new TalkIntegration($settings, $utils);
                            $ti->deleteRoom($xad[4]);
                        }
                    }
                }
            }

            $ext_event_type = 1;

        } elseif ($hint === HintVar::APPT_TYPE_CHANGE) {

            $tmpl->setSubject($this->l10N->t("%s Appointment update", [$org_name]));
            $tmpl->addHeading(" "); // spacer
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
                $this->l10N->t("Dear %s,", [$to_name])
            ]));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS Main part of email
                $this->l10N->t("Your appointment details have changed. Please review information below.")
            ]));

            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);

            if ($doc) {

                $has_link = !empty($doc->talkToken . $doc->bbbToken);
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->makeMeetingTypeInfo($settings, $has_link)));
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->l10N->t("Date/Time: %s", [$date_time])));

                $talk_link_txt = $this->addVideoLinkInfo(
                    $userId, $tmpl, $doc, $settings,
                    $config->getUserValue($userId, Application::APP_ID, "c" . "nk")
                );
            } elseif (!empty($xad) && count($xad) > 4) {

                $_talkToken = $xad[4];
                $has_link = strlen($_talkToken) > 1;

                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->makeMeetingTypeInfo($settings, $has_link)));
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->l10N->t("Date/Time: %s", [$date_time])));

                $ti = new TalkIntegration($settings, $utils);
                $talk_link_txt = $this->addTalkInfo(
                    $tmpl, $xad, $ti, $settings,
                    $config->getUserValue($userId, Application::APP_ID, "c" . "nk"));
            }

            $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);

            $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

            if ($settings[BackendUtils::EML_MCONF]) {
                $om_prefix = $organizerL10n->t("Appointment updated");
            }

            $ext_event_type = 3;

        } elseif ($hint === HintVar::APPT_NONE) {
            // Organizer or External Action (something changed...)

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} appointment status update
            $tmpl->setSubject($this->l10N->t("%s Appointment update", [$org_name]));
            $tmpl->addHeading(" "); // spacer
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
                $this->l10N->t("Dear %s,", [$to_name])
            ]));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                // TRANSLATORS Main part of email
                $this->l10N->t("Your appointment details have changed. Please review information below.")
            ]));

            $pst = $att->parameters['PARTSTAT']->getValue();

            $ti = new TalkIntegration($settings, $utils);

            // Add changes details...
            if ($hash_ch[0] === true) { // DTSTART changed
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->l10N->t("Date/Time: %s", [$date_time])));
                // if we have a Talk room we need to update the room's name (and lobby time if implemented)
                if ($doc) {
                    $videoType = $this->getVideoType($settings);
                    if ($videoType !== self::VIDEO_NONE) {
                        if ($videoType === self::VIDEO_TALK && !empty($doc->talkToken)) {
                            $ti->renameRoom(
                                $doc->talkToken, $to_name, $evt->DTSTART, $userId
                            );
                        } elseif ($videoType === self::VIDEO_BBB && !empty($doc->bbbToken)) {
                            $bi = \OC::$server->get(BbbIntegration::class);
                            $bi->renameRoom(
                                $doc->bbbToken, $to_name, $evt->DTSTART, $userId);
                        }
                    }
                } elseif (!empty($xad) && count($xad) > 4 && strlen($xad[4]) > 1) {
                    $ti->renameRoom(
                        $xad[4], $to_name, $evt->DTSTART, $userId
                    );
                }

            }

            $ext_event_type = 2;

            if ($hash_ch[1] === true) { //STATUS changed
                if ($evt->STATUS->getValue() === 'CANCELLED') {
                    $tmpl->addBodyListItem(...$this->formatEmailListItem(
                        $this->l10N->t('Status: Canceled')));
                    $is_cancelled = true;
                    $ext_event_type = 1;
                } else {
                    // Non cancelled status is determined by the attendee's PARTSTAT
                    if ($pst === 'NEEDS-ACTION') {
                        $tmpl->addBodyListItem(...$this->formatEmailListItem(
                            $this->l10N->t('Status: Pending confirmation')));
                        $ext_event_type = -1; // no extNotify when pending
                    } elseif ($pst === 'ACCEPTED') {
                        $tmpl->addBodyListItem(...$this->formatEmailListItem(
                            $this->l10N->t('Status: Confirmed')));
                        $ext_event_type = 0;
                    }
                }
            }

            if ($hash_ch[2] === true && isset($evt->LOCATION)) { // LOCATION changed
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $this->l10N->t("Location: %s", [$evt->LOCATION->getValue()])));
            }

            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);

            // if NOT cancelled and PARTSTAT:NEEDS-ACTION we ADD BUTTONS before the "If you have any questions..." text
            if ($is_cancelled === false && $pst === 'NEEDS-ACTION') {
                $no_ics = true;
                $tmpl->addBodyButtonGroup(
                    $this->l10N->t("Confirm"),
                    $btn_url . '1' . $btn_tkn,
                    $this->l10N->t("Cancel"),
                    $btn_url . '0' . $btn_tkn
                );
            }

            if ($doc) {
                $videoType = $this->getVideoType($settings);
                if ($videoType !== self::VIDEO_NONE) {
                    $has_link = !empty($doc->talkToken . $doc->bbbToken);
                    if ($has_link) {
                        $talk_link_txt = $this->addVideoLinkInfo(
                            $userId, $tmpl, $doc, $settings,
                            $config->getUserValue($userId, Application::APP_ID, "c" . "nk")
                        );
                    }
                    $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                }
            } else {
                // if there is a Talk room - add info...
                if (!empty($xad) && count($xad) > 4 && $settings[BackendUtils::TALK_ENABLED]) {
                    $has_link = strlen($xad[4]) > 1;
                    if ($has_link) {
                        // add talk link info
                        $talk_link_txt = $this->addTalkInfo(
                            $tmpl, $xad, $ti, $settings,
                            $config->getUserValue($userId, Application::APP_ID, "c" . "nk"));
                    }
                    $this->addTypeChangeLink($tmpl, $settings, $btn_url . "3" . $btn_tkn, $has_link);
                }
            }
            if (empty($org_phone)) {
                $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                    // TRANSLATORS Additional part of email - contact information WITHOUT phone number (only email). The last argument is email address.
                    $this->l10N->t("If you have any questions please write to %s", [$org_email])
                ]));
            } else {
                $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                    // TRANSLATORS Additional part of email - contact information WITH email AND phone number: If you have any questions please feel free to call {123-456-7890} or write to {email@example.com}
                    $this->l10N->t('If you have any questions please feel free to call %1$s or write to %2$s', [$org_phone, $org_email])
                ]));
            }

            // if NOT cancelled and PARTSTAT:ACCEPTED we ADD the cancellation at the END
            if ($is_cancelled === false && $pst === 'ACCEPTED') {
                $cnl_lnk_url = $btn_url . "0" . $btn_tkn;
            }

            // Update hash
            $utils->setApptHash($evt, $userId, $pageId);

            if (($settings[BackendUtils::EML_ADEL] === false && $isDelete)
                || ($settings[BackendUtils::EML_AMOD] === false && $isDelete)) {
                // no need to go further if we don want to email attendees on change
                return;
            }

        } else {

            // the purposed of this block is to add strings for translation
            // start: translate for before release

            $some_org_name = "Organization Name";
            $number_of_hours = 2;
            $some_person_name = "John Smith";
            $some_date_time="Date and Time";
            $person_email="test@example.com";

            // TRANSLATORS Email subject asking an attendee to confirm a pending appointment, Ex: Reminder: please confirm your {{Organization Name}} appointment
            $future_use = $this->l10N->t("Reminder: please confirm your %s appointment", [$some_org_name]);

            // TRANSLATORS Part of email body, Ex: Important: If not confirmed within 2 hours, this appointment will be automatically cancelled.
            $future_use = $this->l10N->n('Important: If not confirmed within %n hour, this appointment will be automatically cancelled.', 'Important: If not confirmed within %n hours, this appointment will be automatically cancelled.', $number_of_hours);

            // TRANSLATORS Button text
            $future_use = $this->l10N->t("Add a guest");

            // TRANSLATORS Label for an input field
            $future_use = $this->l10N->t("Guest Email");

            // TRANSLATORS Email subject, Ex: {{Organization Name}} Appointment Guest Invitation
            $future_use = $this->l10N->t("%s Appointment Guest Invitation", [$some_org_name]);

            // TRANSLATORS Email body, Ex: {{John Smith}} has invited you to join an appointment with {{Organization Name}} on {{Date_Time}}. If you have any questions, please email {{John Smith}} directly at {{john.smith@example.com}}.
            $future_use = $this->l10N->t('%1$s has invited you to join an appointment with %2$s on %3$s. If you have any questions, please email %1$s directly at %4$s', [$some_person_name, $some_org_name, $some_date_time, $person_email]);

            // end: translate for before release
            return;
        }

        $this->finalizeEmailText($tmpl, $cnl_lnk_url);

        ///-------------------


        $msg = $mailer->createMessage();

        $this->setFromAddress($msg, $userId, $org_email, $org_name);

        $msg->setTo(array($to_email));
        $msg->useTemplate($tmpl);

        if ($doc) {
            $utz_info = $doc->attendeeTimezone[0];
        } else {
            $utz_info = $evt->{BackendUtils::TZI_PROP}->getValue()[0];
        }
        // .ics attachment
        if ($hint !== HintVar::APPT_BOOK
            && $settings[BackendUtils::EML_ICS] === true
            && $no_ics === false) {

            // method https://tools.ietf.org/html/rfc5546#section-3.2
            if (!$is_cancelled) {
                $method = 'PUBLISH';

                $more_ics_text = $settings[BackendUtils::EML_ICS_TXT];

                if (empty($org_phone) && empty($talk_link_txt) && empty($more_ics_text)) {
                    if (isset($evt->DESCRIPTION)) {
                        $evt->remove($evt->DESCRIPTION);
                    }
                } else {
                    if (!isset($evt->DESCRIPTION)) {
                        $evt->add('DESCRIPTION');
                    }

                    $evt->DESCRIPTION->setValue(
                        $org_name . "\n"
                        . (!empty($org_phone) ? $org_phone . "\n" : "")
                        . (!empty($talk_link_txt) ? "\n" . $talk_link_txt . "\n" : "")
                        . (!empty($more_ics_text) ? "\n" . $more_ics_text . "\n" : "")
                    );
                }
            } else {
                $method = 'CANCEL';
                if ($isDelete) {
                    // Set proper info, otherwise the .ics file is bad
                    if ($hint === HintVar::APPT_NONE) {
                        // Organizer deleted the appointment
                        $evt->STATUS->setValue('CANCELLED');
                    } else {
                        $utils->evtCancelAttendee($evt);
                    }
                    $utils->setSEQ($evt);
                }

                if (isset($evt->DESCRIPTION)) {
                    $evt->DESCRIPTION->setValue(
                        $this->l10N->t("Appointment is canceled")
                    );
                }
            }

            // SCHEDULE-AGENT
            // https://tools.ietf.org/html/rfc6638#section-7.1
            // Servers MUST NOT include this parameter in any scheduling messages sent as the result of a scheduling operation.
            // Clients MUST NOT include this parameter in any scheduling messages that they themselves send.

//            if(isset($evt->ORGANIZER->parameters['SCHEDULE-AGENT'])){
//                unset($evt->ORGANIZER->parameters['SCHEDULE-AGENT']);
//            }

            // Some external clients set SCHEDULE-STATUS to 3.7 because of the "acct" scheme
            if (isset($evt->ATTENDEE)) {
                foreach ($evt->ATTENDEE as $k => $v) {
                    if (isset($evt->ATTENDEE[$k]->parameters['SCHEDULE-STATUS'])
                    ) {
                        unset($evt->ATTENDEE[$k]->parameters['SCHEDULE-STATUS']);
                    }
                    if (isset($evt->ATTENDEE[$k]->parameters['SCHEDULE-AGENT'])) {
                        unset($evt->ATTENDEE[$k]->parameters['SCHEDULE-AGENT']);
                    }

                    $av = $evt->ATTENDEE[$k]->getValue();
                    if (strpos($av, "acct:") === 0) {
                        $av = 'mailto:' . substr($av, 5);
                    }
                    $evt->ATTENDEE[$k]->setValue($av);
                    $evt->ATTENDEE->offsetSet('ROLE', 'REQ-PARTICIPANT');
                }
            }

            if (!isset($vObject->METHOD)) {
                $vObject->add('METHOD');
            }
            $vObject->METHOD->setValue($method);

            if (!isset($evt->SUMMARY)) {
                $evt->add('SUMMARY');
            }
            $evt->SUMMARY->setValue($this->l10N->t("%s Appointment", [$org_name]));

            if (isset($evt->{BackendUtils::TZI_PROP})) {
                $evt->remove($evt->{BackendUtils::TZI_PROP});
            }
            if (isset($evt->{BackendUtils::XAD_PROP})) {
                $evt->remove($evt->{BackendUtils::XAD_PROP});
            }
            if (isset($evt->{BackendUtils::X_DSR})) {
                $evt->remove($evt->{BackendUtils::X_DSR});
            }
            if (isset($evt->{ApptDocProp::PROP_NAME})) {
                $evt->remove($evt->{ApptDocProp::PROP_NAME});
            }

            $msg->attach(
                $mailer->createAttachment(
                    $vObject->serialize(),
                    'appointment.ics',
                    'text/calendar; method=' . $method
                )
            );
        }

        try {
            $mailer->send($msg);
        } catch (\Exception $e) {
            $this->logger->error("Can not send email to " . $to_email);
            $this->logger->error($e->getMessage());
            return;
        }

        // Email the Organizer
        if (!empty($om_prefix) && isset($om_info)) {
            // $om_info should have attendee info separated by \n
            $oma = explode("\n", $om_info);
            // At least two parts (name and email, [phone optional])
            $omc = count($oma);
            if ($omc > 1) {

                $evt_dt = $evt->DTSTART->getDateTime();
                // Here we need organizer's timezone for getDateTimeString()
//                $utz_info .= $utils->getUserTimezone($userId, $config)->getName();
                $utz_info .= $utz->getName();

                $tmpl = $this->getEmailTemplate();

                $tmpl->setSubject($om_prefix . ": " . $to_name . ", "
                    . $utils->getDateTimeString($evt_dt, $utz_info, 1, $organizerL10n));
                $tmpl->addHeading(" "); // spacer
                $tmpl->addBodyText(...$this->formatEmailBodyHtml([$om_prefix]));
                $tmpl->addBodyListItem(...$this->formatEmailListItem(
                    $utils->getDateTimeString($evt_dt, $utz_info, 0, $organizerL10n)));
                $ic = 0;
                foreach ($oma as $info) {
                    if (strlen($info) > 2) {
                        $tmpl->addBodyListItem(...$this->formatEmailListItem(
                            $this->linkify->process($info)));
                        $ic++;
                        if ($ic > 16) {
                            break;
                        }
                    }
                }

                // Add page name
                if (count($utils->getUserPages($userId)) > 1
                    && !empty($settings[BackendUtils::PAGE_LABEL])
                ) {
                    $tmpl->addBodyListItem(...$this->formatEmailListItem(
                        $settings[BackendUtils::PAGE_LABEL]));
                }

                $msg = $mailer->createMessage();
                $msg->setFrom(array(\OCP\Util::getDefaultEmailAddress('appointments-noreply')));
                $msg->setTo(array($org_email));
                $msg->useTemplate($tmpl);

                try {
                    $mailer->send($msg);
                } catch (\Exception $e) {
                    $this->logger->error("Can not send email to " . $org_email);
                    $this->logger->error($e->getMessage());
                    return;
                }
            } else {
                $this->logger->error("Bad oma count");
            }
        }

        // advanced/extensions
        if ($ext_event_type >= 0) {
            $filePath = $config->getAppValue(Application::APP_ID, 'ext_notify_' . $userId);
            if ($filePath !== "") {
                $data = [
                    'eventType' => $ext_event_type,
                    'dateTime' => $evt->DTSTART->getDateTime(),
                    'attendeeName' => $to_name,
                    'attendeeEmail' => $to_email,
                    'attendeeTel' => $this->getPhoneFromDescription($om_info),
                    'pageId' => $pageId
                ];
                $this->extNotify($data, $userId, $filePath);
            }
        }
    }


    /**
     * @param string $userId
     * @param string $pageId
     * @param bool $embed
     * @param string $uri
     * @param \OCP\IConfig $config
     * @return string[] - [btn_url,btn_tkn]
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function makeBtnInfo($userId, $pageId, $embed, $uri, $config)
    {
        $key = hex2bin($config->getAppValue(Application::APP_ID, 'hk'));
        if (empty($key)) {
            return ["", ""];
        }

        $utils = $this->utils;

        $pageIdParam = "";

        /** @noinspection PhpUnhandledExceptionInspection */
        $btn_url = $raw_url = $utils->getPublicWebBase() . '/'
            . $utils->pubPrx($utils->getToken($userId, $pageId), $embed)
            . 'cncf?d=';
        if ($embed) {
            $btn_url = $config->getAppValue(
                Application::APP_ID,
                'emb_cncf_' . $userId, $btn_url);
            $pageIdParam = "&pageId=" . $pageId;
        }
        return [
            $btn_url,
            urlencode($utils->encrypt(substr($uri, 0, -4), $key)) . $pageIdParam
        ];
    }

    /**
     * @param IEMailTemplate $tmpl
     * @param string[] $xad
     * @param TalkIntegration $ti
     * @param array $tlk
     * @param $c
     * @return string
     */
    private function addTalkInfo($tmpl, $xad, $ti, $tlk, $c)
    {
        $url = $ti->getRoomURL($xad[4]);
        $url_html = '<a target="_blank" href="' . $url . '">' . $url . '</a>';

        $eml_txt = $tlk[BackendUtils::TALK_EMAIL_TXT];
        $s = "subs" . 'tr';
        if (!empty($eml_txt) && isset($c[3]) && ((hexdec($s($c, 0, 0b100)) >> 14) & 1) === ((hexdec($s($c, 4, 04)) >> 6) & 1)) {
            $talk_link_html = str_replace("\n", "<br>", $eml_txt);
            if (strpos($talk_link_html, "{{url}}") !== false) {
                $talk_link_html = str_replace("{{url}}", $url_html, $talk_link_html);
            } else {
                $talk_link_html .= " " . $url_html;
            }

            if ($xad[5] !== '_') {
                // we have pass
                if (strpos($talk_link_html, "{{pass}}") !== false) {
                    $talk_link_html = str_replace("{{pass}}", $xad[5], $talk_link_html);
                } else {
                    $talk_link_html .= " " . $this->l10N->t('Password') . ": " . $xad[5];
                }
            } else {
                // no pass, but {{pass}} token
                if (strpos($talk_link_html, "{{pass}}") !== false) {
                    $talk_link_html = str_replace("{{pass}}", '', $talk_link_html);
                }
            }
        } else {
            // TRANSLATORS This a link to chat/(video)call, Ex: Chat/Call link: https://my_domain.com/call/kzu6e4uv
            $talk_link_html = $this->l10N->t("Chat/Call link: %s", [$url_html]);
            if ($xad[5] !== '_') {
                // we have pass
                $talk_link_html .= '<br>' . $this->l10N->t('Password') . ": " . $xad[5];
            }
        }
        $talk_link_txt = strip_tags(str_replace("<br>", "\n", $talk_link_html));
        $tmpl->addBodyText(...$this->formatEmailBodyHtml([$talk_link_html, $talk_link_txt]));
        return trim($talk_link_txt);
    }

    private function addVideoLinkInfo(string $userId, IEMailTemplate $tmpl, ApptDocProp $doc, array $settings, string $c): string
    {
        if ($doc->inPersonType === false) {

            if ($doc->talkToken !== '') {
                // Talk
                $ti = new TalkIntegration($settings, $this->utils);
                $url = $ti->getRoomURL($doc->talkToken);
                $pass = $doc->talkPass;
            } else {
                // BBB
                $bi = \OC::$server->get(BbbIntegration::class);
                $url = $bi->getRoomUrl($doc->bbbToken, $userId);
                $pass = $doc->bbbPass;
            }
            if (empty($url)) {
                return '';
            }
            $url_html = '<a target="_blank" href="' . $url . '">' . $url . '</a>';

            $video_link_html = '';
            if ($doc->talkToken !== '') {
                // special Talk config options
                $eml_txt = $settings[BackendUtils::TALK_EMAIL_TXT];
                $s = "subs" . 'tr';
                if (!empty($eml_txt) && isset($c[3]) && ((hexdec($s($c, 0, 0b100)) >> 14) & 1) === ((hexdec($s($c, 4, 04)) >> 6) & 1)) {
                    $video_link_html = str_replace("\n", "<br>", $eml_txt);
                    if (strpos($video_link_html, "{{url}}") !== false) {
                        $video_link_html = str_replace("{{url}}", $url_html, $video_link_html);
                    } else {
                        $video_link_html .= " " . $url_html;
                    }
                }
            }

            if (empty($video_link_html)) {
                $video_link_html = $this->l10N->t('Meeting link: %s', [$url_html]);
            }
            if (!empty($pass)) {
                $video_link_html .= '<br>' . $this->l10N->t('Password: %s', [$pass]);
            }

            $video_link_txt = strip_tags(str_replace("<br>", "\n", $video_link_html));
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([$video_link_html, $video_link_txt]));
            return trim($video_link_txt);
        } else {
            return '';
        }
    }

    private function addTypeChangeLink(EMailTemplate $tmpl, array $settings, string $typeChangeLink, bool $has_link): void
    {

        if ($this->getVideoType($settings) === self::VIDEO_TALK) {

            $txt = strip_tags(trim($settings[BackendUtils::TALK_FORM_TYPE_CHANGE_TXT]));
            if (!empty($txt)) {

                if (!$has_link) {
                    // need virtual text
                    $nt = htmlspecialchars((
                    !empty($settings[BackendUtils::TALK_FORM_VIRTUAL_TXT])
                        ? $settings[BackendUtils::TALK_FORM_VIRTUAL_TXT]
                        : $settings[BackendUtils::TALK_FORM_DEF_VIRTUAL]),
                        ENT_NOQUOTES);
                } else {
                    // real txt
                    $nt = htmlspecialchars((
                    !empty($settings[BackendUtils::TALK_FORM_REAL_TXT])
                        ? $settings[BackendUtils::TALK_FORM_REAL_TXT]
                        : $settings[BackendUtils::TALK_FORM_DEF_REAL]),
                        ENT_NOQUOTES);
                }

                $txt = str_replace('{{new_type}}', $nt, $txt);

                $s = strpos($txt, '{{');
                if ($s !== false) {
                    $e = strpos($txt, '}}', $s);
                    if ($e !== false) {
                        $ltx = substr($txt, $s + 2, $e - $s - 2);

                        $p1 = substr($txt, 0, $s);
                        $p2 = substr($txt, $e + 2);

                        $h = $p1 . '<a href="' . $typeChangeLink . '">' . $ltx . '<a>' . $p2;
                        $t = $p1 . $ltx . ': ' . $typeChangeLink . ' ' . $p2;

                        $tmpl->addBodyText(...$this->formatEmailBodyHtml([$h, $t]));
                    }
                }
            }
        } else { // self::VIDEO_BBB

            if (!$has_link) {
                // need virtual
                $nt = $this->l10N->t('Online (audio/video)');
            } else {
                // need real
                $nt = $this->l10N->t('In-person meeting');
            }

            // TRANSLATORS Example: Click <a href="https://example.com">here</a> to change your appointment type to In-person meeting.
            $h = $this->l10N->t('Click %1$shere%2$s to change your appointment type to %3$s.',
                ['<a href="' . $typeChangeLink . '">', '</a>', $nt]);

            // TRANSLATORS Example: Click here: https://example.com to change your appointment type to Online (audio/video).
            $t = $this->l10N->t('Click here: %1$s to change your appointment type to %2$s.',
                [$typeChangeLink, $nt]);

            $tmpl->addBodyText(...$this->formatEmailBodyHtml([$h, $t]));
        }
    }

    private function makeMeetingTypeInfo(array $settings, bool $has_link): string
    {
        if ($settings[BackendUtils::TALK_ENABLED]) { // Talk
            $info = !empty($settings[BackendUtils::TALK_FORM_LABEL])
                ? $settings[BackendUtils::TALK_FORM_LABEL]
                : $settings[BackendUtils::TALK_FORM_DEF_LABEL];
            if ($has_link) {
                $info .= ': ' . (!empty($settings[BackendUtils::TALK_FORM_VIRTUAL_TXT])
                        ? $settings[BackendUtils::TALK_FORM_VIRTUAL_TXT]
                        : $settings[BackendUtils::TALK_FORM_DEF_VIRTUAL]);
            } else {
                $info .= ': ' . (!empty($settings[BackendUtils::TALK_FORM_REAL_TXT])
                        ? $settings[BackendUtils::TALK_FORM_REAL_TXT]
                        : $settings[BackendUtils::TALK_FORM_DEF_REAL]);
            }
        } else { //BBB
            if ($has_link) {
                $info = $this->l10N->t('Meeting type: Online (audio/video)');
            } else {
                $info = $this->l10N->t('Meeting type: In-person');
            }
        }
        return htmlspecialchars($info, ENT_NOQUOTES);
    }

    private function getEmailTemplate()
    {
        return $this->mailer->createEMailTemplate(
            'ID_' . time() . rand(10000, 99999));
    }

    private function getOrgInfo()
    {

        $settings = $this->utils->getUserSettings();

        $email = $settings[BackendUtils::ORG_EMAIL];
        $name = $settings[BackendUtils::ORG_NAME];
        $phone = $settings[BackendUtils::ORG_PHONE];

        return [$email, $name, $phone];
    }


    function finalizeEmailText(EMailTemplate $tmpl, $cnl_lnk_url)
    {

        $tmpl->addBodyText(...$this->formatEmailBodyHtml([$this->l10N->t("Thank you")]));

        // cancellation link for confirmation emails
        if (!empty($cnl_lnk_url)) {
            $tmpl->addBodyText(...$this->formatEmailBodyHtml([
                '<span style="font-size: 80%;color: #989898;">' .
                // TRANSLATORS This is a part of an email message. %1$s Cancel Appointment %2$s is a link to the cancellation page (HTML format).
                $this->l10N->t('To cancel your appointment please click: %1$s Cancel Appointment %2$s', ['<a style="color: #989898" href="' . $cnl_lnk_url . '">', '</a>'])
                . '</span>',
                // TRANSLATORS This is a part of an email message. %s is a URL of the cancellation page (PLAIN TEXT format).
                $this->l10N->t('To cancel your appointment please visit: %s', [$cnl_lnk_url])
            ]));
        }

        $theme = new \OCP\Defaults();
        // TRANSLATORS %s is the server name. Example: Booked via Private Cloud Appointments
        $tmpl->addFooter($this->l10N->t("Booked via %s Appointments", [$theme->getEntity()]));
    }

    private function formatEmailBodyHtml(array $data): array
    {
        $html = $data[0];
        if (count($data) > 1) {
            $text = $data[1];
        } else {
            $text = strip_tags($data[0]);
        }
        return [
            '</p><div style="color: #222222; text-align: left; margin: 0; padding: 0">' . $html . '</div><p style="margin: 0; padding: 0; height: 0; line-height: 0; display: block;">',
            $text
        ];
    }

    private function formatEmailListItem(string $str): array
    {
        return ['<span style="color: #222222">' . $str . '</span>', '', '', strip_tags($str)];
    }


    /**
     * @see https://github.com/SergeyMosin/Appointments/issues/26 for more info
     *
     * @param array $data
     * @param string $userId
     * @param string $filePath
     * @return void
     */
    private function extNotify(array $data, string $userId, string $filePath)
    {

        if ($filePath !== "") {

            include_once $filePath;

            if (function_exists('notificationEventListener')) {
                try {
                    notificationEventListener($data, $this->logger);
                } catch (\Exception $e) {
                    $this->logger->error("User '" . $userId . "' extension file error: " . $e);
                    return;
                }
            } else {
                $this->logger->error("User '" . $userId . "' can not find 'notificationEventListener' in " . $filePath . " or the file does not exist");
            }
        }
    }

    private function getPhoneFromDescription(string $description): string
    {
        $ret = "";
        $da = explode("\n", $description);
        if (count($da) > 2 && preg_match('/[0-9 .()\-+,\/]/', $da[1])) {
            $ret = $da[1];
        }
        return $ret;
    }

    private function addMoreEmailText(IEMailTemplate $template, string $text)
    {
        list($html, $plainText) = $this->prepHtmlEmailText($text);
        if ($html === null) {
            $template->addBodyText($plainText);
        } else {
            $template->addBodyText($html, $plainText);
        }
    }

    /**
     * @param string $text html|text
     * @return array [ html|null, plainText ]
     */
    private function prepHtmlEmailText($text)
    {
        $plainText = strip_tags($text);
        if ($text === $plainText) {
            // plain text
            $linkified = $this->linkify->process($plainText);
            if ($linkified === $plainText) {
                // no links aor emails just plain text
                return [null, $plainText];
            } else {
                // links were found and turned into html
                return [$linkified, $plainText];
            }
        } else {
            // html
            return [str_replace('<?', '&lt;?', $text), $plainText];
        }

    }

    /**
     * @param IMessage $msg
     * @param string $userId
     * @param string $org_email
     * @param string $org_name
     * @return void
     */
    private function setFromAddress($msg, $userId, $org_email, $org_name)
    {
        if ($this->config->getAppValue(Application::APP_ID,
                BackendUtils::KEY_USE_DEF_EMAIL,
                'yes') === 'no') {
            $email = $org_email;
        } else {
            $email = \OCP\Util::getDefaultEmailAddress('appointments-noreply');
            $msg->setReplyTo(array($org_email));
        }

        $name = trim($org_name);
        if (empty($name)) {
            try {
                /** @var IUserManager $userManager */
                $userManager = \OC::$server->get(IUserManager::class);
                $name = trim($userManager->getDisplayName($userId));
            } catch (\Throwable $e) {
                $this->logger->error("cannot determine user display name", [
                    'app' => Application::APP_ID,
                    'exception' => $e,
                ]);
                $name = "";
            }
        }
        if (!empty($name)) {
            $msg->setFrom([$email => $name]);
        } else {
            $msg->setFrom([$email]);
        }
    }

    private function getVideoType(array $settings): int
    {
        return $settings[BackendUtils::TALK_ENABLED] === true
            ? self::VIDEO_TALK
            : ($settings[BackendUtils::BBB_ENABLED] === true
                ? self::VIDEO_BBB
                : self::VIDEO_NONE
            );
    }

}
