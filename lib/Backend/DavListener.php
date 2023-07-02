<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;


use OC\Mail\EMailTemplate;
use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Email\EMailTemplateNC20;
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
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class DavListener implements IEventListener
{

    private $appName;
    private $l10N;
    private $logger;
    private $utils;

    /** @type IMailer */
    private $mailer;

    /** @type IConfig */
    private $config;

    private $linkify;

    public function __construct(\OCP\IL10N      $l10N,
                                LoggerInterface $logger,
                                BackendUtils    $utils)
    {
        $this->appName = Application::APP_ID;
        $this->l10N = $l10N;
        $this->logger = $logger;
        $this->utils = $utils;

        $this->mailer = \OC::$server->get(IMailer::class);
        $this->config = \OC::$server->get(IConfig::class);

        $this->linkify = new Linkify();
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
     * @param int $lastStart timestamp set by IJonList->setLastRun() producto of time() func
     */
    public function handleReminders(int $lastStart, IDBConnection $db, IBackendConnector $bc): void
    {

        // we need to pull all pending appointments between now + 42 min( 1 hour [min delta] - 18 min [time between jobs]) and now + 7 days(max delta)
        $now = time();
        $qb = $db->getQueryBuilder();
        try {
            $result = $qb->select('hash.*', 'pref.reminders')
                ->from(BackendUtils::HASH_TABLE_NAME, 'hash')
                ->leftJoin('hash', BackendUtils::PREF_TABLE_NAME, 'pref', $qb->expr()->eq('hash.user_id', 'pref.user_id'))
                ->where($qb->expr()->isNotNull('pref.reminders'))
                ->andWhere($qb->expr()->eq('hash.status', $qb->createNamedParameter(BackendUtils::PREF_STATUS_CONFIRMED, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->gte('hash.start', $qb->createNamedParameter($now + 2520, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->lte('hash.start', $qb->createNamedParameter($now + 604800, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->isNotNull('hash.user_id'))
                ->andWhere($qb->expr()->isNotNull('hash.page_id'))
                ->andWhere($qb->expr()->isNotNull('hash.uri'))
                ->orderBy('hash.user_id')
                ->execute();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }

        $config = $this->config;
        $mailer = $this->mailer;

        $utils = $this->utils;
        $utz = new \DateTimeZone('utc');

        $userId = '';
        $extNotifyFilePath = '';
        while ($row = $result->fetch()) {

            $remObj = json_decode($row['reminders'], true);
            if ($remObj === false) {
                $this->logger->error("json_decode failed. string: " . $row['reminders']);
                continue;
            }

            if ($userId !== $row['user_id']) {
                $userId = $row['user_id'];
                $utils->clearSettingsCache();

                $extNotifyFilePath = $config->getAppValue($this->appName, 'ext_notify_' . $userId);
            }

            $pageId = $row['page_id'];
            $otherCalId = '-1';
            $calId = $utils->getMainCalId($userId, $pageId, null, $otherCalId);
            if ($calId === '-1') {
                $this->logger->error("can not find main calendar, userId: " . $userId . ", pageId: " . $pageId);
                continue;
            }
            if ($otherCalId !== '-1' && $utils->getUserSettings(
                    $pageId === 'p0'
                        ? BackendUtils::KEY_CLS
                        : BackendUtils::KEY_MPS . $pageId,
                    $userId)[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                // if we have a dst calendar in simple mode than it will hold confirmed appointments, so we should check it first and then check the src calendar just in-case settings have been changed after the appointment was booked
                $temp = $calId;
                $calId = $otherCalId;
                $otherCalId = $temp;
            } else {
                // dst calendar is only valid in simple mode
                $otherCalId = '-1';
            }

            $remDataArray = $remObj[BackendUtils::REMINDER_DATA];
            foreach ($remDataArray as $remData) {
                $remindAt = $row['start'] - $remData[BackendUtils::REMINDER_DATA_TIME];
                if ($remindAt >= $lastStart && $remindAt < $now) {
                    // send reminder

                    $data = $bc->getObjectData($calId, $row['uri']);

                    if ($data === null && $otherCalId !== '-1') {
                        $data = $bc->getObjectData($otherCalId, $row['uri']);
                    }

                    if ($data === null) {
                        $this->logger->error("can not get object data, uri: " . $row['uri'] . ", calId: " . $calId);
                        break;
                    }

                    if (strpos($data, "\r\nATTENDEE;") === false
                        || strpos($data, "\r\n" . BackendUtils::TZI_PROP . ":") === false) {
                        $this->logger->error('bad event data');
                        break;
                    }

                    $vObject = Reader::read($data);
                    if (!isset($vObject->VEVENT)) {
                        $this->logger->error("not a event, uri: " . $row['uri'] . ", calId: " . $calId);
                        break;
                    }

                    /** @var \Sabre\VObject\Component\VEvent $evt */
                    $evt = $vObject->VEVENT;
                    if (!isset($evt->UID)
                        || !isset($evt->ATTENDEE)
                        || !isset($evt->STATUS)
                        || !isset($evt->DTEND)
                        || !isset($evt->ORGANIZER)
                        || $evt->STATUS->getValue() !== 'CONFIRMED'
                        || !isset($evt->{BackendUtils::XAD_PROP})
                    ) {
                        $this->logger->error('bad event object');
                        break;
                    }

                    $att = $utils->getAttendee($evt);
                    if ($att === null || $att->parameters['PARTSTAT']->getValue() === 'DECLINED') {
                        $this->logger->error('bad attendee data');
                        break;
                    }
                    $to_name = $att->parameters['CN']->getValue();
                    if (empty($to_name) || preg_match('/[^\PC ]/u', $to_name)) {
                        $this->logger->error("invalid attendee name");
                        break;
                    }
                    $att_v = $att->getValue();
                    $to_email = substr($att_v, strpos($att_v, ":") + 1);
                    if ($mailer->validateMailAddress($to_email) === false) {
                        $this->logger->error("invalid attendee email");
                        break;
                    }

                    // event data looks ok...

                    $date_time = $utils->getDateTimeString(
                        $evt->DTSTART->getDateTime(),
                        $evt->{BackendUtils::TZI_PROP}->getValue()
                    );

                    list($org_email, $org_name, $org_phone) = $this->getOrgInfo($userId, $pageId);
                    $tmpl = $this->getEmailTemplate();


                    // TRANSLATORS Subject for email, Ex: {{Organization Name}} appointment reminder
                    $tmpl->setSubject($this->l10N->t("%s appointment reminder", [$org_name]));
                    // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
                    $tmpl->addBodyText($this->l10N->t("Dear %s,", $to_name));

                    // TRANSLATORS Main part of email, Ex: This is a reminder from {{Organization Name}} about your upcoming appointment on {{Date And Time}}. If you need to reschedule, please call {{Organization Phone}}.
                    $tmpl->addBodyText($this->l10N->t('This is a reminder from %1$s about your upcoming appointment on %2$s. If you need to reschedule, please call %3$s.', [$org_name, $date_time, $org_phone]));

                    $cnl_lnk_url = '';

                    // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
                    $xad = explode(chr(31), $utils->decrypt(
                        $evt->{BackendUtils::XAD_PROP}->getValue(),
                        $evt->UID->getValue()));
                    if (count($xad) > 2) {
                        $embed = $xad[3] === "1";
                    } else {
                        $embed = false;
                    }

                    // we want links and buttons
                    if ($remData[BackendUtils::REMINDER_DATA_ACTIONS]) {

                        // overwrite.cli.url must be set if $embed is not used
                        if ($embed || $config->getSystemValue('overwrite.cli.url') !== '') {

                            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                                $userId, $pageId, $embed,
                                $row['uri'],
                                $config);
                            $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

                            if (count($xad) > 4) {

                                $has_link = strlen($xad[4]) > 1 ? 1 : 0;
                                $tlk = $utils->getUserSettings(BackendUtils::KEY_TALK, $userId);
                                if ($tlk[BackendUtils::TALK_ENABLED]) {
                                    if ($tlk[BackendUtils::TALK_FORM_ENABLED] === true) {
                                        if ($has_link === 1) {
                                            $ti = new TalkIntegration($tlk, $utils);
                                            // add talk link info
                                            $this->addTalkInfo(
                                                $tmpl, $xad, $ti, $tlk,
                                                $config->getUserValue($userId, $this->appName, "c" . "nk"));
                                        }
                                        $this->addTypeChangeLink($tmpl, $tlk, $btn_url . "3" . $btn_tkn, $has_link);
                                    }
                                }
                            }
                        } else {
                            $this->logger->error('can not add actions to reminder, missing overwrite.cli.url');
                        }
                    }
                    if (!empty($remObj[BackendUtils::REMINDER_MORE_TEXT])) {
                        list($remHtml, $remPlainText) = $this->prepHtmlEmailText($remObj[BackendUtils::REMINDER_MORE_TEXT]);
                        if ($remHtml === null) {
                            $tmpl->addBodyText($remPlainText);
                        } else {
                            $tmpl->addBodyText($remHtml, $remPlainText);
                        }
                    }

                    // everything is ready, send email...
                    $this->finalizeEmailText($tmpl, $cnl_lnk_url);

                    ///-------------------

                    $msg = $mailer->createMessage();

                    $this->setFromAddress($msg, $userId, $org_email, $org_name);

                    $msg->setTo(array($to_email));
                    $msg->useTemplate($tmpl);

                    $description = '';

                    try {
                        $mailer->send($msg);

                        $utz = $this->utils->getCalendarTimezone($userId, $config, $bc->getCalendarById($calId, $userId));

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
                        if ($bc->updateObject($calId, $row['uri'], $vObject->serialize()) === false) {
                            $this->logger->error("Can not update object uid: " . $row['uid']);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("Can not send email to " . $to_email . ", event uid: " . $row['uid']);
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

                    usleep(500000);
                    break;
                }
            }
        }
        $result->closeCursor();
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

        if (strpos($cd, "\r\nATTENDEE;") === false
            || strpos($cd, "\r\nCATEGORIES:" . BackendUtils::APPT_CAT . "\r\n") === false
            || strpos($cd, "\r\n" . BackendUtils::TZI_PROP . ":") === false
            || strpos($cd, "\r\nORGANIZER;") === false
            || strpos($cd, "\r\nUID:") === false) {
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

        if (isset($evt->{BackendUtils::XAD_PROP})) {
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

        $other_cal = '-1';
        $cal_id = $utils->getMainCalId($userId, $pageId, null, $other_cal);

        if ($other_cal !== '-1') {
            // only allowed in simple
            if ($utils->getUserSettings(
                    $pageId === 'p0'
                        ? BackendUtils::KEY_CLS
                        : BackendUtils::KEY_MPS . $pageId,
                    $userId)[BackendUtils::CLS_TS_MODE] !== '0') {
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

        $utz = $utils->getCalendarTimezone($userId, $config, $utils->transformCalInfo($calendarData));
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

        $eml_settings = $utils->getUserSettings(
            BackendUtils::KEY_EML, $userId);

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

        $date_time = $utils->getDateTimeString(
            $evt->DTSTART->getDateTime(),
            $evt->{BackendUtils::TZI_PROP}->getValue()
        );

        list($org_email, $org_name, $org_phone) = $this->getOrgInfo($userId, $pageId);

        $is_cancelled = false;

//        $tmpl=$mailer->createEMailTemplate("ID_".time());
        $tmpl = $this->getEmailTemplate();

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
            // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
            $tmpl->addBodyText($this->l10N->t("Dear %s,", $to_name));

            // TRANSLATORS Main part of email, Ex: The {{Organization Name}} appointment scheduled for {{Date Time}} is awaiting your confirmation.
            $tmpl->addBodyText($this->l10N->t('The %1$s appointment scheduled for %2$s is awaiting your confirmation.', [$org_name, $date_time]));

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

            if (!empty($eml_settings[BackendUtils::EML_VLD_TXT])) {
                $this->addMoreEmailText($tmpl, $eml_settings[BackendUtils::EML_VLD_TXT]);
            }

            if ($eml_settings[BackendUtils::EML_MREQ]) {
                $om_prefix = $this->l10N->t("Appointment pending");
            }

        } elseif ($hint === HintVar::APPT_CONFIRM) {
            // Confirm link in the email is clicked ...
            // ... or the email validation step is skipped

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} Appointment is Confirmed
            $tmpl->setSubject($this->l10N->t("%s Appointment is confirmed", [$org_name]));
            $tmpl->addBodyText($to_name . ",");
            // TRANSLATORS Main body of email,Ex: Your {{Organization Name}} appointment scheduled for {{Date Time}} is now confirmed.
            $tmpl->addBodyText($this->l10N->t('Your %1$s appointment scheduled for %2$s is now confirmed.', [$org_name, $date_time]));

            // add cancellation link
            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);
            $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

            if (count($xad) > 4) {

                $has_link = strlen($xad[4]) > 1 ? 1 : 0;
                $tlk = $utils->getUserSettings(BackendUtils::KEY_TALK, $userId);
                if ($tlk[BackendUtils::TALK_ENABLED]) {
                    if ($has_link === 1) {
                        $ti = new TalkIntegration($tlk, $utils);
                        // add talk link info
                        $talk_link_txt = $this->addTalkInfo(
                            $tmpl, $xad, $ti, $tlk,
                            $config->getUserValue($userId, $this->appName, "c" . "nk"));
                    }

                    if ($tlk[BackendUtils::TALK_FORM_ENABLED] === true) {
                        if ($has_link === 0) {
                            // add in-person meeting type
                            $tmpl->addBodyText($this->makeMeetingTypeInfo($tlk, $has_link));
                        }
                        $this->addTypeChangeLink($tmpl, $tlk, $btn_url . "3" . $btn_tkn, $has_link);
                    }
                }
            }

            if (!empty($eml_settings[BackendUtils::EML_CNF_TXT])) {
                $this->addMoreEmailText($tmpl, $eml_settings[BackendUtils::EML_CNF_TXT]);
            }

            if ($eml_settings[BackendUtils::EML_MCONF]) {
                $om_prefix = $this->l10N->t("Appointment confirmed");
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

            // TRANSLATORS Main body of email,Ex: Your {{Organization Name}} appointment scheduled for {{Date Time}} is now canceled.
            $tmpl->addBodyText($to_name . ",");
            $tmpl->addBodyText($this->l10N->t('Your %1$s appointment scheduled for %2$s is now canceled.', [$org_name, $date_time]));
            $is_cancelled = true;

            if ($eml_settings[BackendUtils::EML_MCNCL] && $hint !== HintVar::APPT_NONE) {
                $om_prefix = $this->l10N->t("Appointment canceled");
            }

            if ($isDelete && count($xad) > 4 && strlen($xad[4]) > 1) {
                $tlk = $utils->getUserSettings(BackendUtils::KEY_TALK, $userId);
                if ($tlk[BackendUtils::TALK_DEL_ROOM] === true) {
                    $ti = new TalkIntegration($tlk, $utils);
                    $ti->deleteRoom($xad[4]);
                }
            }

            $ext_event_type = 1;

        } elseif ($hint === HintVar::APPT_TYPE_CHANGE) {

            $tmpl->setSubject($this->l10N->t("%s Appointment update", [$org_name]));
            // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
            $tmpl->addBodyText($this->l10N->t("Dear %s,", [$to_name]));
            // TRANSLATORS Main part of email
            $tmpl->addBodyText($this->l10N->t("Your appointment details have changed. Please review information below."));

            $tlk = $utils->getUserSettings(BackendUtils::KEY_TALK, $userId);

            $has_link = strlen($xad[4]) > 1 ? 1 : 0;

            $tmpl->addBodyListItem($this->makeMeetingTypeInfo($tlk, $has_link));
            $tmpl->addBodyListItem($this->l10N->t("Date/Time: %s", [$date_time]));

            if ($has_link === 1) {
                // add talk link info
                $ti = new TalkIntegration($tlk, $utils);
                $talk_link_txt = $this->addTalkInfo(
                    $tmpl, $xad, $ti, $tlk,
                    $config->getUserValue($userId, $this->appName, "c" . "nk"));
            }

            list($btn_url, $btn_tkn) = $this->makeBtnInfo(
                $userId, $pageId, $embed,
                $objectData['uri'],
                $config);

            $this->addTypeChangeLink($tmpl, $tlk, $btn_url . "3" . $btn_tkn, $has_link);

            $cnl_lnk_url = $btn_url . "0" . $btn_tkn;

            if ($eml_settings[BackendUtils::EML_MCONF]) {
                $om_prefix = $this->l10N->t("Appointment updated");
            }

            $ext_event_type = 3;

        } elseif ($hint === HintVar::APPT_NONE) {
            // Organizer or External Action (something changed...)

            // TRANSLATORS Subject for email, Ex: {{Organization Name}} appointment status update
            $tmpl->setSubject($this->l10N->t("%s Appointment update", [$org_name]));
            // TRANSLATORS First line of email, Ex: Dear {{Customer Name}},
            $tmpl->addBodyText($this->l10N->t("Dear %s,", [$to_name]));
            // TRANSLATORS Main part of email
            $tmpl->addBodyText($this->l10N->t("Your appointment details have changed. Please review information below."));


            $pst = $att->parameters['PARTSTAT']->getValue();

            $tlk = $utils->getUserSettings(BackendUtils::KEY_TALK, $userId);
            $ti = new TalkIntegration($tlk, $utils);

            // Add changes details...
            if ($hash_ch[0] === true) { // DTSTART changed
                $tmpl->addBodyListItem($this->l10N->t("Date/Time: %s", [$date_time]));
                // if we have a Talk room we need to update the room's name (and lobby time if implemented)
                if (count($xad) > 4 && strlen($xad[4]) > 1) {
                    $ti->renameRoom(
                        $xad[4], $to_name, $evt->DTSTART, $userId
                    );
                }
            }

            $ext_event_type = 2;

            if ($hash_ch[1] === true) { //STATUS changed
                if ($evt->STATUS->getValue() === 'CANCELLED') {
                    $tmpl->addBodyListItem($this->l10N->t('Status: Canceled'));
                    $is_cancelled = true;
                    $ext_event_type = 1;
                } else {
                    // Non cancelled status is determined by the attendee's PARTSTAT
                    if ($pst === 'NEEDS-ACTION') {
                        $tmpl->addBodyListItem($this->l10N->t('Status: Pending confirmation'));
                        $ext_event_type = -1; // no extNotify when pending
                    } elseif ($pst === 'ACCEPTED') {
                        $tmpl->addBodyListItem($this->l10N->t('Status: Confirmed'));
                        $ext_event_type = 0;
                    }
                }
            }

            if ($hash_ch[2] === true && isset($evt->LOCATION)) { // LOCATION changed
                $tmpl->addBodyListItem($this->l10N->t("Location: %s", [$evt->LOCATION->getValue()]));
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

            // if there is a Talk room - add info...
            if (count($xad) > 4 && $tlk[BackendUtils::TALK_ENABLED]) {
                $has_link = strlen($xad[4]) > 1 ? 1 : 0;
                if ($has_link === 1) {
                    // add talk link info
                    $talk_link_txt = $this->addTalkInfo(
                        $tmpl, $xad, $ti, $tlk,
                        $config->getUserValue($userId, $this->appName, "c" . "nk"));
                }
                $this->addTypeChangeLink($tmpl, $tlk, $btn_url . "3" . $btn_tkn, $has_link);
            }

            if (empty($org_phone)) {
                // TRANSLATORS Additional part of email - contact information WITHOUT phone number (only email). The last argument is email address.
                $tmpl->addBodyText($this->l10N->t("If you have any questions please write to %s", [$org_email]));
            } else {
                // TRANSLATORS Additional part of email - contact information WITH email AND phone number: If you have any questions please feel free to call {123-456-7890} or write to {email@example.com}
                $tmpl->addBodyText($this->l10N->t('If you have any questions please feel free to call %1$s or write to %2$s', [$org_phone, $org_email]));
            }

            // if NOT cancelled and PARTSTAT:ACCEPTED we ADD the cancellation at the END
            if ($is_cancelled === false && $pst === 'ACCEPTED') {
                $cnl_lnk_url = $btn_url . "0" . $btn_tkn;
            }

            // Update hash
            $utils->setApptHash($evt, $userId, $pageId);

            if (($eml_settings[BackendUtils::EML_ADEL] === false && $isDelete)
                || ($eml_settings[BackendUtils::EML_AMOD] === false && $isDelete)) {
                // no need to go further if we don want to email attendees on change
                return;
            }

        } else {
            return;
        }

        $this->finalizeEmailText($tmpl, $cnl_lnk_url);

        ///-------------------


        $msg = $mailer->createMessage();

        $this->setFromAddress($msg, $userId, $org_email, $org_name);

        $msg->setTo(array($to_email));
        $msg->useTemplate($tmpl);

        $utz_info = $evt->{BackendUtils::TZI_PROP}->getValue()[0];

        // .ics attachment
        if ($hint !== HintVar::APPT_BOOK
            && $eml_settings[BackendUtils::EML_ICS] === true
            && $no_ics === false) {

            // method https://tools.ietf.org/html/rfc5546#section-3.2
            if (!$is_cancelled) {
                $method = 'PUBLISH';

                $more_ics_text = $eml_settings[BackendUtils::EML_ICS_TXT];

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
                    . $utils->getDateTimeString($evt_dt, $utz_info, 1));
                $tmpl->addHeading(" "); // spacer
                $tmpl->addBodyText($om_prefix);
                $tmpl->addBodyListItem($utils->getDateTimeString($evt_dt, $utz_info));
                $ic = 0;
                foreach ($oma as $info) {
                    if (strlen($info) > 2) {
                        $tmpl->addBodyListItem($this->linkify->process($info), '', '', strip_tags($info));
                        $ic++;
                        if ($ic > 16) {
                            break;
                        }
                    }
                }

                // Add page name
                $pages = $utils->getUserSettings(BackendUtils::KEY_PAGES, $userId);
                if (count($pages) > 1) {
                    $tmpl->addBodyListItem($pages[$pageId]['label']);
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
            $filePath = $config->getAppValue($this->appName, 'ext_notify_' . $userId);
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
        $key = hex2bin($config->getAppValue($this->appName, 'hk'));
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
                $this->appName,
                'emb_cncf_' . $userId, $btn_url);
            $pageIdParam = "&pageId=" . $pageId;
        }
        return [
            $btn_url,
            urlencode($utils->encrypt(substr($uri, 0, -4), $key)) . $pageIdParam
        ];
    }

    /**
     * @param \OCP\Mail\IEMailTemplate $tmpl
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
        $tmpl->addBodyText($talk_link_html, $talk_link_txt);
        return trim($talk_link_txt);
    }

    /**
     * @param $tmpl
     * @param $tlk
     * @param $typeChangeLink
     * @param int $newType 0=virtual, 1=real
     */
    private function addTypeChangeLink($tmpl, $tlk, $typeChangeLink, $newType)
    {
        $txt = strip_tags(trim($tlk[BackendUtils::TALK_FORM_TYPE_CHANGE_TXT]));
        if (!empty($txt)) {

            if ($newType === 0) {
                // need virtual text
                $nt = htmlspecialchars((
                !empty($tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT])
                    ? $tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT]
                    : $tlk[BackendUtils::TALK_FORM_DEF_VIRTUAL]),
                    ENT_NOQUOTES);
            } else {
                // real txt
                $nt = htmlspecialchars((
                !empty($tlk[BackendUtils::TALK_FORM_REAL_TXT])
                    ? $tlk[BackendUtils::TALK_FORM_REAL_TXT]
                    : $tlk[BackendUtils::TALK_FORM_DEF_REAL]),
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

                    $tmpl->addBodyText($h, $t);
                }
            }
        }
    }

    private function makeMeetingTypeInfo($tlk, $has_link)
    {
        $info = !empty($tlk[BackendUtils::TALK_FORM_LABEL])
            ? $tlk[BackendUtils::TALK_FORM_LABEL]
            : $tlk[BackendUtils::TALK_FORM_DEF_LABEL];
        if ($has_link) {
            $info .= ': ' . (!empty($tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT])
                    ? $tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT]
                    : $tlk[BackendUtils::TALK_FORM_DEF_VIRTUAL]);
        } else {
            $info .= ': ' . (!empty($tlk[BackendUtils::TALK_FORM_REAL_TXT])
                    ? $tlk[BackendUtils::TALK_FORM_REAL_TXT]
                    : $tlk[BackendUtils::TALK_FORM_DEF_REAL]);
        }
        return htmlspecialchars($info, ENT_NOQUOTES);
    }

    private function getEmailTemplate()
    {

        $urlGenerator = \OC::$server->get(IURLGenerator::class);

        // NC settings compliance
        $class = $this->config->getSystemValue('mail_template_class', '');
        if ($class !== '' && class_exists($class) && is_a($class, EMailTemplate::class, true)) {
            return new $class(
                new \OCP\Defaults(),
                $urlGenerator,
                \OC::$server->get(IFactory::class),
                "ID_" . time(),
                []
            );
        }

        return new EMailTemplateNC20(
            new \OCP\Defaults(),
            $urlGenerator,
            $this->l10N,
            "ID_" . time(),
            []
        );
    }

    private function getOrgInfo($userId, $pageId)
    {
        $org = $this->utils->getUserSettings(
            BackendUtils::KEY_ORG, $userId);

        $email = $org[BackendUtils::ORG_EMAIL];
        $name = $org[BackendUtils::ORG_NAME];
        $phone = $org[BackendUtils::ORG_PHONE];

        if ($pageId !== 'p0') {
            $cms = $this->utils->getUserSettings(
                BackendUtils::KEY_MPS . $pageId, $userId);
            if (!empty($cms[BackendUtils::ORG_NAME])) {
                $name = $cms[BackendUtils::ORG_NAME];
            }
            if (!empty($cms[BackendUtils::ORG_PHONE])) {
                $phone = $cms[BackendUtils::ORG_PHONE];
            }
        }

        return [$email, $name, $phone];
    }


    function finalizeEmailText(&$tmpl, $cnl_lnk_url)
    {

        $tmpl->addBodyText($this->l10N->t("Thank you"));

        // cancellation link for confirmation emails
        if (!empty($cnl_lnk_url)) {
            $tmpl->addBodyText(
                '<div style="font-size: 80%;color: #989898">' .
                // TRANSLATORS This is a part of an email message. %1$s Cancel Appointment %2$s is a link to the cancellation page (HTML format).
                $this->l10N->t('To cancel your appointment please click: %1$s Cancel Appointment %2$s', ['<a style="color: #989898" href="' . $cnl_lnk_url . '">', '</a>'])
                . "</div>",
                // TRANSLATORS This is a part of an email message. %s is a URL of the cancellation page (PLAIN TEXT format).
                $this->l10N->t('To cancel your appointment please visit: %s', $cnl_lnk_url)
            );
        }

        $theme = new \OCP\Defaults();
        $tmpl->addFooter("Booked via " . $theme->getEntity() . " Appointments");
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
        if ($this->config->getAppValue($this->appName,
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

}
