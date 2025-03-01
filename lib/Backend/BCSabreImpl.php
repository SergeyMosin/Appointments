<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;


use OC\OCS\Exception;
use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\IntervalTree\AVLIntervalNode;
use OCA\Appointments\IntervalTree\AVLIntervalTree;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\WebcalCaching\RefreshWebcalService;
use OCP\IConfig;
use OCP\IDBConnection;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Recur\NoInstancesException;
use Sabre\VObject\Reader;
use Psr\Log\LoggerInterface;

class BCSabreImpl implements IBackendConnector
{

    const TIME_FORMAT = "Ymd\THis\Z";
    const TIME_FORMAT_NO_Z = "Ymd\THis";

    private CalDavBackend $backend;
    private IConfig $config;
    private BackendUtils $utils;
    private LoggerInterface $logger;
    private IDBConnection $db;

    public function __construct(CalDavBackend   $backend,
                                IConfig         $config,
                                BackendUtils    $utils,
                                IDBConnection   $db,
                                LoggerInterface $logger)
    {
        $this->backend = $backend;
        $this->config = $config;
        $this->utils = $utils;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    function queryRangePast($calIds, $end, $only_empty, $delete, $delete_test = false)
    {

        if (empty($calIds)) {
            return "0";
        }

        $ots = $end->getTimestamp();
        $end->setTimestamp($ots + 50400);

        $utz = $end->getTimezone();
        $cnt = 0;

        if ($delete) {

            if ($delete_test === false) {
                // let's make easier for the DavListener...
                HintVar::setHint(HintVar::APPT_SKIP);
            } else {
                HintVar::setHint(HintVar::APPT_CANCEL);
            }
            // Cleanup hash table
            if ($only_empty === false && $delete_test === false) {
                $cutoff_str = $end->modify('-35 days')->format(BackendUtils::FLOAT_TIME_FORMAT);
                $query = $this->db->getQueryBuilder();

                $query->delete(BackendUtils::HASH_TABLE_NAME)
                    ->where($query->expr()->lt('hash',
                        $query->createNamedParameter($cutoff_str)))
                    ->execute();
            }
        }

        $iter = $this->fastQuery($calIds, -1, $end->getTimestamp(), [], ['uri', 'calendarid']);
        foreach ($iter as $row) {
            $vo = Reader::read($row['calendardata']);
            if ($only_empty &&
                (!isset($vo->VEVENT->STATUS)
                    || $vo->VEVENT->STATUS->getValue() !== 'TENTATIVE'
                    || $vo->VEVENT->ATTENDEE
                )
            ) {
                continue;
            }
            $ts = $vo->VEVENT->DTEND->getDateTime($utz)->getTimestamp();
            if ($ts <= $ots) {
                if ($delete) {
                    $this->backend->deleteCalendarObject($row['calendarid'], $row['uri']);
                }
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * @param int $start_ts UTC
     * @param int $end_ts UTC
     * @param \DateTimeZone $utz user's timezone
     * @return int 0=no events, 1=at least 1
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function checkRangeTR(int $start_ts, int $end_ts, string $calId, \DateTimeZone $utz, array $settings): int
    {
        $start = new \DateTime('@' . $start_ts);
        $start->setTimezone($utz);

        // TODO: finalize floating TZ support removal
        $queryConfig = [
            // Because of floating timezones...
            // 50400 = 14 hours
            'start' => $start_ts - 50400,
            'end' => $end_ts + 50400,
            'props' => $settings[BackendUtils::CLS_XTM_REQ_CAT]
                ? ['CATEGORIES:' . BackendUtils::APPT_CAT] : []
        ];

        // we need to adjust/modify $start and $end by 1 sec because of "<=" and ">=" comparisons(instead of just "<" and ">" ) in the buildBusyTree function
        $start->modify('+1 second');
        $start_ts = $start->getTimestamp();

        $end_ts--; // -1 second

        $booked_tree = $this->buildBusyTree([$calId], $queryConfig, $start, $start_ts, $end_ts, true);

        // if $booked_tree is NOT null then there was a match(intersection)
        return $booked_tree === null ? 0 : 1;

    }

    /**
     * @param string $calIds dstCal(main)+chr(31)+srcCal(free spots)
     */
    private function queryRangeTR(string $calIds, \DateTime $start, \DateTime $end, string $key): string|null
    {
        // user's timezone
        $utz = $start->getTimezone();

        $start_ts = $start->getTimestamp();
        $end_ts = $end->getTimestamp();

        // parse calIds
        $sp = strpos($calIds, chr(31));
        if ($sp === false) {
            return null;
        }
        $srcId = substr($calIds, $sp + 1);
        $dstId = substr($calIds, 0, $sp);

        $settings = $this->utils->getUserSettings();

        $queryConfig = [
            // Because of floating timezones...
            // 50400 = 14 hours
            'start' => $start_ts - 50400,
            'end' => $end_ts + 50400,
            'props' => $settings[BackendUtils::CLS_XTM_REQ_CAT]
                ? ['CATEGORIES:' . BackendUtils::APPT_CAT] : []
        ];

        $booked_tree = $this->buildBusyTree([$dstId], $queryConfig, $start, $start_ts, $end_ts, false);

        // Get free/available spots
        $str_out = '';
        // '_'ts_mode(1byte)ses_time(4bytes)dates(8bytes)uri(no extension)
        $ses_info = '_1' . pack("L", time());

        $showET = $settings[BackendUtils::PSN_END_TIME];

        $iter = $this->fastQuery(
            [$srcId],
            $queryConfig['start'],
            $queryConfig['end'],
            $queryConfig['props'],
            ['uri']
        );
        foreach ($iter as $row) {

            $cd = $row['calendardata'];

            if (strpos($cd, "\r\nTRANSP:TRANSPARENT\r\n", 22) === false) {
                // must be "Free" aka TRANSPARENT
                continue;
            }

            /** @var \Sabre\VObject\Component\VCalendar $vo */
            $vo = Reader::read($cd);

            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;

            if (!$evt->DTSTART->hasTime()
                || (isset($evt->CLASS) && $evt->CLASS->getValue() !== 'PUBLIC')) {
                $vo->destroy();
                continue;
            }

            $ts_pref = 'U';
            if ($evt->DTSTART->isFloating()) {
                $vo->destroy();
                continue;
            }

            $atl = ':';
            if (isset($evt->SUMMARY)) {
                $s = $evt->SUMMARY->getValue();
                if ($s[0] === "_") {
                    $atl .= str_replace(',', ' ', $s);
                }
            }

            if (isset($evt->RRULE)) {

                try {
                    $it = new EventIterator($vo->getByUID($evt->UID->getValue()), null, $utz);
                } catch (NoInstancesException $e) {
                    // This event is recurring, but it doesn't have a single instance. We are skipping this event from the output entirely.
                    continue;
                }

                $it->fastForward($start);
                $skip_count = $it->key();
            } else {
                if (isset($evt->STATUS)
                    && $evt->STATUS->getValue() === 'CANCELLED') {
                    // check if CANCELLED early
                    $vo->destroy();
                    continue;
                }
                // TODO: reuse FakeIterator
                $it = new FakeIterator($evt, $utz);
                $skip_count = 0;
            }

            $c = 0;
            while ($it->valid()) {

                $c++;
                if ($c > 384) {
                    break;
                }

                $_evt = $it->getEventObject();
                if (isset($_evt->STATUS)
                    && $_evt->STATUS->getValue() === 'CANCELLED') {
                    $it->next();
                    continue;
                }

                $s_ts = $it->getDtStart()->getTimestamp();

                if ($s_ts >= $end_ts) {
                    $it->next();
                    break;
                }
                if ($s_ts > $start_ts) {
                    $e_ts = $it->getDtEnd()->getTimestamp();

                    if (AVLIntervalTree::lookUp($booked_tree,
                            $s_ts, $e_ts) === null) {

                        $str_out .= $ts_pref . $s_ts
                            . ($showET ? ":" . $e_ts : "")
                            . ':' . $this->utils->encrypt($ses_info . pack("LL", $s_ts, $e_ts) . substr($row['uri'], 0, -4), $key) . $atl . ',';
                    }
                }
                $it->next();
            }

            if ($skip_count > 14 && $settings[BackendUtils::CLS_XTM_PUSH_REC] === true) {
                // Optimize recurrence
                $it->rewind();
                $skip_until = $skip_count - 7;
                while ($it->valid() && $it->key() < $skip_until) {
                    $it->next();
                }
                $this->utils->optimizeRecurrence($it->getDtStart(), $it->getDtEnd(), $skip_until, $vo);
                $this->updateObject($srcId, $row['uri'], $vo->serialize());
            }
            $vo->destroy();
        }
        return $str_out !== '' ? substr($str_out, 0, -1) : null;
    }

    /**
     * @return int 0=ok, -1=error, 1=taken
     */
    function checkRangeTemplate(array $settings, \DateTime $start, \DateTime $end, string $userId): int
    {

        $queryConfig = [
            'start' => $start->getTimestamp() - 86400, //24h
            'end' => $end->getTimestamp() + 50400, //14h
        ];

        $cals = $this->getCalsForConflictCheck($settings, $userId);

        // we need to adjust/modify $start and $end by 1 sec because of "<=" and ">=" comparisons(instead of just "<" and ">" ) in the buildBusyTree function
        $start->modify('+1 second');
        $end->modify('-1 second');

        $start_ts = $start->getTimestamp();
        $end_ts = $end->getTimestamp();

        $booked_tree = $this->buildBusyTree($cals, $queryConfig, $start, $start_ts, $end_ts, true);

        // if $booked_tree is NOT null then there was a match(intersection)
        return $booked_tree === null ? 0 : 1;
    }


    /**
     * @inheritDoc
     */
    function queryTemplate($cms, $start, $end, $userId, $pageId): ?string
    {

        $key = hex2bin($this->config->getAppValue(Application::APP_ID, 'hk'));
        if (empty($key)) {
            $this->logger->error("Can't find hkey");
            return null;
        }

        $utz = $start->getTimezone();

        $start_ts = $start->getTimestamp();
        $end_ts = $end->getTimestamp();

        $queryConfig = [
            // Because of floating timezones...
            // We need to adjust for UTC and filter
            'start' => $start_ts - 86400, //24h
            'end' => $end_ts + 64800, //18h
        ];

        $settings = $this->utils->getUserSettings();

        $cals = $this->getCalsForConflictCheck($settings, $userId);

        $booked_tree = $this->buildBusyTree($cals, $queryConfig, $start, $start_ts, $end_ts, false);

        $ti = $settings[BackendUtils::KEY_TMPL_INFO];

        if ($start->getTimezone()->getName() !== $ti[BackendUtils::TMPL_TZ_NAME]) {
            try {
                $start->setTimezone(new \DateTimeZone($ti[BackendUtils::TMPL_TZ_NAME]));
            } catch (\Exception $e) {
                $this->logger->warning('Can not set template timezone');
            }
        }

        $td = $settings[BackendUtils::KEY_TMPL_DATA];
        if (count($td) !== 7) {
            $td[] = [];
        }
        $start->modify("today");
        // 0=Monday
        $day = $start->format('N') - 1;
        $ds = $start->getTimestamp();
        $out = "";
        $ses_start = '_2' . time() . '_';
        while ($ds < $end_ts) {
            $dia = $td[$day];
            $tc = 0;

            foreach ($dia as $di) {
                //TODO: there are better ways to sent this to the front end, instead of calculating it here
                $start->setTime(0, 0, $di['start']);
                $sts = $start->getTimestamp();

                if ($sts < $start_ts) {
                    continue;
                } // skip past
                if ($sts > $end_ts) {
                    break 2;
                } // Done :)
                $cc = 0;
                foreach ($di['dur'] as $dur) {
                    $ets = $sts + $dur * 60;
                    if (AVLIntervalTree::lookUp($booked_tree, $sts, $ets) !== null) {
                        // this spot is taken
                        break;
                    }
                    ++$cc;
                }
                if ($cc !== 0) {
                    $data = $ses_start . $day . $tc . '_' . $sts;
                    $out .= 'T' . $sts . ":" . implode(';', array_slice($di['dur'], 0, $cc)) . ":" . $this->utils->encrypt($data, $key) . ":_" . $di['title'] . ',';
                }
                $tc++;
            }

            $day++;
            if ($day >= 7) {
                $day = 0;
            }
            // we need to re-calculate this because of daytime savings
            $start->setTime(0, 0);
            $start->modify('+1 day');
            $ds = $start->getTimestamp();
        }
        return $out !== '' ? substr($out, 0, -1) : null;
    }

    private function buildBusyTree(array     $calIds,
                                   array     $queryConfig,
                                   \DateTime $start,
                                   int       $start_ts,
                                   int       $end_ts,
                                   bool      $returnAfterFirstMatch = false): AVLIntervalNode|null
    {
        $utz = $start->getTimezone();

        $busy_tree = null;
        $itc = new AVLIntervalTree();

        $settings = $this->utils->getUserSettings();
        $all_day_block = $settings[BackendUtils::CLS_ALL_DAY_BLOCK];
//        $log_remote_blockers = $settings[BackendUtils::DEBUGGING_MODE] === BackendUtils::DEBUGGING_LOG_REM_BLOCKER;

        $beforeBufferSec = $settings[BackendUtils::CLS_BUFFER_BEFORE] * 60;
        $afterBufferSec = $settings[BackendUtils::CLS_BUFFER_AFTER] * 60;

        $iter = $this->fastQuery($calIds,
            $queryConfig['start'],
            $queryConfig['end'],
            $queryConfig['props'] ?? []
        );
        foreach ($iter as $data) {

            /** @var \Sabre\VObject\Component\VCalendar $vo */
            $vo = Reader::read($data);
            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;
            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            if (!$all_day_block && !$evt->DTSTART->hasTime()) {
                $vo->destroy();
                continue;
            }

            if (isset($evt->RRULE)) {

                try {
                    $it = new EventIterator($vo->getByUID($evt->UID->getValue()), null, $utz);
                } catch (NoInstancesException $e) {
                    // This event is recurring, but it doesn't have a single instance. We are skipping this event from the output entirely.
                    continue;
                }
                $it->fastForward($start);
            } else {
                // TODO: reuse FakeIterator
                $it = new FakeIterator($evt, $utz);
            }

            $c = 0;
            while ($it->valid() && $c < 384) {
                $c++;
                $_evt = $it->getEventObject();
                if ((isset($_evt->STATUS) && $_evt->STATUS->getValue() === 'CANCELLED') || (isset($_evt->TRANSP) && $_evt->TRANSP->getValue() === 'TRANSPARENT')) {
                    $it->next();
                    continue;
                }

                if ($_evt->DTSTART && !$_evt->DTSTART->hasTime()
                    && (!$_evt->DURATION &&
                        // Specs prohibit time in DTEND when there is no time in DTSTART
                        ($_evt->DTEND && $_evt->DTEND->hasTime()))
                ) {
                    // an all-day event
                    $s_ts = $it->getDtStart()->getTimestamp();
                    $e_ts = $s_ts + 86400;
                } else {
                    // an event with end-time or multi-day duration
                    $s_ts = $it->getDtStart()->getTimestamp() - $beforeBufferSec;
                    $e_ts = $it->getDtEnd()->getTimestamp() + $afterBufferSec;
                }
                // start1 <= end2 && start2 <= end1
                if ($start_ts <= $e_ts && $s_ts <= $end_ts) {

//                    if ($log_remote_blockers && $cal['type'] === CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION) {
//                        $this->logErr("debug: " . var_export([
//                                'blocker_uid' => $_evt->UID->getValue(),
//                                'start_timestamp' => $s_ts,
//                                'end_timestamp' => $e_ts,
//                                'start_value' => $_evt->DTSTART->getValue(),
//                                'time_zone' => $it->getDtStart()->getTimezone()->getName(),
//                            ], true));
//                    }

                    $itc->insert($busy_tree, $s_ts, $e_ts);

                    if ($returnAfterFirstMatch) {
                        // we short circuit when this is called from checkRangeXxx() functions
                        return $busy_tree;
                    }
                }
                $it->next();
            }
            $vo->destroy();
        }

        return $busy_tree;
    }


    /**
     * @return int[]
     */
    private function getCalsForConflictCheck(array $settings, string $userId, bool $forceSync = false): array
    {

        // stars with destination cal
        $ret = [$settings[BackendUtils::CLS_TMM_DST_ID]];

        $currentCalIds = $settings[BackendUtils::CLS_TMM_MORE_CALS];
        if (count($currentCalIds) > 0) {
            $filteredIds = $this->utils->filterCalsAndSubs(
                $currentCalIds,
                $this->getCalendarsForUser($userId, false));
            foreach ($filteredIds as $id) {
                $ret[] = $id;
            }
        }

        $currentSubIds = $settings[BackendUtils::CLS_TMM_SUBSCRIPTIONS];
        if (count($currentSubIds) > 0) {

            $ids = []; // convert to array with ids as keys for fast look up
            for ($i = 0, $l = count($currentSubIds); $i < $l; $i++) {
                $ids[$currentSubIds[$i]] = true;
            }

            $hadSync = false; // only one sync per-request

            $syncInterval = intval($this->utils->getUserSettings()[BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC]);

            // we need to add real(not transformed) subscription objects here
            $allSubs = $this->backend->getSubscriptionsForUser(BackendManager::PRINCIPAL_PREFIX . $userId);
            for ($i = 0, $l = count($allSubs); $i < $l; $i++) {
                $sub = $allSubs[$i];
                if (isset($ids[hash("crc32", $sub['principaluri'] . $sub['source'], false)])) {

                    if ($syncInterval > 59) { // << 1 hour minimum

                        $qb = $this->db->getQueryBuilder();
                        try {
                            $c = $qb->select('*')
                                ->from(BackendUtils::SYNC_TABLE_NAME)
                                ->where($qb->expr()->eq('id', $qb->createNamedParameter($sub['id'])))
                                ->execute();
                            $sd = $c->fetch();
                            $c->closeCursor();
                        } catch (\OCP\DB\Exception $e) {
                            $this->logErr("can not get sync data: " . $e->getMessage());
                            continue;
                        }

                        if ($sd === false) {
                            $lastSync = 0;
                        } else {
                            $lastSync = intval($sd['lastsync']);
                        }

                        $now = time();

                        if ($forceSync || (!$hadSync && ($now - $lastSync) > ($syncInterval * 60))) {

                            $hadSync = true;

                            // update sync timestamp and token
                            try {
                                $qb = $this->db->getQueryBuilder();
                                $rows = $qb->update(BackendUtils::SYNC_TABLE_NAME)
                                    ->set('lastsync', $qb->createNamedParameter($now))
                                    ->set('synctoken', $qb->createNamedParameter($sub['{http://sabredav.org/ns}sync-token']))
                                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($sub['id'])))
                                    ->execute();
                                if ($rows === 0) {
                                    // first run: insert
                                    $qb = $this->db->getQueryBuilder();
                                    $qb->insert(BackendUtils::SYNC_TABLE_NAME)
                                        ->values([
                                            'id' => $qb->createNamedParameter($sub['id']),
                                            'lastsync' => $qb->createNamedParameter($now),
                                            'synctoken' => $qb->createNamedParameter($sub['{http://sabredav.org/ns}sync-token'])
                                        ])
                                        ->execute();
                                }

                            } catch (\OCP\DB\Exception $e) {
                                $this->logErr("can not update sync table " . $e->getMessage());
                                continue;
                            }

                            // lets sync
                            try {
                                /** @var RefreshWebcalService $rws */
                                $rws = \OC::$server->get(RefreshWebcalService::class);
                                $rws->refreshSubscription(
                                    (string)$sub['principaluri'],
                                    (string)$sub['uri']
                                );
                            } catch (Exception $e) {
                                $this->logErr("can not sync subscription " . $sub['id']);
                                $this->logErr($e->getMessage());
                            }
                        }
                    }

                    $ret[] = $sub['id'];
                }
            }
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function queryRange($calId, $start, $end, $mode, $pageId = null): ?string
    {

        $no_uri = ($mode === 'no_url');
        if ($no_uri) {
            $key = ''; // add end_time instead of uri
        } else {
            $key = hex2bin($this->config->getAppValue(Application::APP_ID, 'hk'));
            if (empty($key)) {
                $this->logger->error("Can't find hkey");
                return null;
            }
        }

        if ($mode[0] === BackendUtils::CLS_TS_MODE_EXTERNAL) {
            // $calId = dstCal(main)+chr(31)+srcCal(free spots) @see PageController->showForm()
            return $this->queryRangeTR($calId, $start, $end, $key);
        }

        // Simple Mode...
        $o_start = $start->getTimestamp();
        $o_end = $end->getTimestamp();

        $ses_start = time() . '|';
        $ret = '';

        $showET = $this->utils->getUserSettings()[BackendUtils::PSN_END_TIME];

        $ts_pref = 'U';

        $propFilters = ['CATEGORIES:' . BackendUtils::APPT_CAT];
        if ($no_uri === false) {
            $propFilters[] = 'STATUS:TENTATIVE';
        }
        $iter = $this->fastQuery([$calId],
            // We need to adjust for UTC timezones and filter
            $o_start - 50400,
            $o_end + 50400,
            $propFilters,
            ['uri']
        );
        foreach ($iter as $row) {
            $vo = Reader::read($row['calendardata']);

            /** @var  \Sabre\VObject\Property\ICalendar\DateTime $dt_start */
            $dt_start = $vo->VEVENT->DTSTART;
            if ($dt_start->isFloating()) {
                $vo->destroy();
                continue;
            }

            if ($vo->VEVENT->ATTENDEE) {
                // this is most likely a pending appointment that is booked
                // in the same cal via "Use Main Calendar" setting
                $vo->destroy();
                continue;
            }

            $s_ts = $dt_start->getDateTime()->getTimestamp();
            if ($s_ts > $o_start) {
                $e_ts = $vo->VEVENT->DTEND->getDateTime()->getTimestamp();
                if ($e_ts <= $o_end) {

                    if ($key !== "") {
                        $atl = ':';
                        if (isset($vo->VEVENT->SUMMARY)) {
                            $s = $vo->VEVENT->SUMMARY->getValue();
                            if ($s[0] === "_") {
                                $atl .= str_replace(',', ' ', $s);
                            }
                        }

                        $ret .= $ts_pref . $s_ts
                            . ($showET ? ":" . $e_ts : "")
                            . ':' . $this->utils->encrypt($ses_start . $row['uri'], $key)
                            . $atl . ',';
                    } else {
                        // add end_time instead of uri
                        $ret .= $ts_pref . $s_ts . ':' . $ts_pref . $e_ts . ',';
                    }
                }
            }
            $vo->destroy();
        }

        return $ret !== '' ? substr($ret, 0, -1) : null;
    }


    function getRawCalData(array $calInfo, string $userId)
    {

        $r = [];

        $fake_cms = [
            BackendUtils::CLS_TMM_DST_ID => '0'
        ];

        if ($calInfo["isSubscription"] === '1') {
            // linked
            $fake_cms[BackendUtils::CLS_TMM_SUBSCRIPTIONS] = [$calInfo["id"]];
            $fake_cms[BackendUtils::CLS_TMM_MORE_CALS] = [];
        } else {
            // local
            $fake_cms[BackendUtils::CLS_TMM_MORE_CALS] = [$calInfo["id"]];
            $fake_cms[BackendUtils::CLS_TMM_SUBSCRIPTIONS] = [];
        }

        $cals = $this->getCalsForConflictCheck(
            $fake_cms, $userId, isset($calInfo['syncRemoteNow_call']));
        if (count($cals) !== 2) {
            return 'error: calendar ' . $calInfo['id'] . ' not found';
        }

        if (isset($calInfo['syncRemoteNow_call'])) {
            return $cals[1];
        }

        $cal = $cals[1];
        $r['cal'] = $cal;

        $start = new \DateTime('now - 1 day');
        $end = new \DateTime('now + 21 days');

        $start_str = $start->format(self::TIME_FORMAT);
        $end_str = $end->format(self::TIME_FORMAT);

        $r['range'] = [
            'start' => $start_str,
            'end' => $end_str
        ];

        $endTs = $end->getTimestamp();

        $events = [];

        $iter = $this->fastQuery([$cal], $start->getTimestamp(), $end->getTimestamp());
        foreach ($iter as $data) {
            $vo = Reader::read($data);
            $e_ts = $vo->VEVENT->DTEND->getDateTime()->getTimestamp();
            if ($e_ts <= $endTs) {
                $events[] = $data;
            }
            $vo->destroy();
        }

        $r['events'] = $events;
        return $r;
    }

    /**
     * @inheritDoc
     * @noinspection PhpRedundantCatchClauseInspection
     */
    function updateObject($calId, $uri, $data): bool
    {
        try {
            $this->backend->updateCalendarObject($calId, $uri, $data);
        } catch (BadRequest $e) {
            $this->logger->error($e);
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * @noinspection PhpRedundantCatchClauseInspection
     */
    function createObject($calId, $uri, $data): bool
    {
        try {
            $this->backend->createCalendarObject($calId, $uri, $data);
        } catch (BadRequest $e) {
            $this->logger->error($e);
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    function getCalendarsForUser($userId, $skipReadOnly = true): array
    {
        $ca = $this->backend->getCalendarsForUser(BackendManager::PRINCIPAL_PREFIX . $userId);
        $ret = [];
        foreach ($ca as $c) {
            $ci = $this->utils->transformCalInfo($c, $skipReadOnly);
            if ($ci !== null) {
                $ret[] = $ci;
            }
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function getSubscriptionsForUser($userId): array
    {

        $ret = [];

        $sa = $this->backend->getSubscriptionsForUser(BackendManager::PRINCIPAL_PREFIX . $userId);
        foreach ($sa as $s) {
            $ret[] = [
                'id' => hash("crc32", $s['principaluri'] . $s['source'], false),
                'displayName' => $s['{DAV:}displayname'],
            ];
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function getCalendarById($calId, $userId): ?array
    {
        $ca = $this->backend->getCalendarsForUser(BackendManager::PRINCIPAL_PREFIX . $userId);
        foreach ($ca as $c) {
            // $c['id'] can be a string or an int
            if ($calId == $c['id']) {
                return $this->utils->transformCalInfo($c);
            }
        }
        return null;
    }


    /**
     * @inheritDoc
     */
    function getObjectData($calId, $uri)
    {
        $d = $this->backend->getCalendarObject($calId, $uri);
        if ($d !== null) {
            return $d['calendardata'];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    function setAttendee($userId, $calId, $uri, $info)
    {

        $pageId = $info['_page_id'];

        $settings = $this->utils->getUserSettings();

        $ts_mode = $settings[BackendUtils::CLS_TS_MODE];

        if ($ts_mode === BackendUtils::CLS_TS_MODE_TEMPLATE) {
            // weekly template
            $td = $settings[BackendUtils::KEY_TMPL_DATA];
            if (!isset($td[$info['tmpl_day']])
                || !isset($td[$info['tmpl_day']][$info['tmpl_idx']])
                || !isset($td[$info['tmpl_day']][$info['tmpl_idx']]['dur'])
                || !isset($td[$info['tmpl_day']][$info['tmpl_idx']]['dur'][intval($info['appt_dur'])])) {

                $this->logErr("Can't find template dur: " . $info['tmpl_day'] . ", " . $info['tmpl_idx']);

                if ($settings[BackendUtils::DEBUGGING_MODE] === BackendUtils::DEBUGGING_LOG_TEMPLATE_DUR) {
                    $this->logErr("template debug: template_data: " . var_export($td, true));
                    $this->logErr("template debug: post_info: " . var_export($info, true));
                }

                return 1;
            }

            $tza = $settings[BackendUtils::KEY_TMPL_INFO];
            if (!isset($tza[BackendUtils::TMPL_TZ_DATA])) {
                $this->logErr("Can't find timezone data, tza: " . var_export($tza, true));
                return 2;
            }

            $parts = $this->utils->makeAppointmentParts(
                $userId, $tza[BackendUtils::TMPL_TZ_DATA],
                (new \DateTime('now', new \DateTimeZone('UTC')))->format(self::TIME_FORMAT),
                isset($td[$info['tmpl_day']][$info['tmpl_idx']]['title'])
                    ? '_' . $td[$info['tmpl_day']][$info['tmpl_idx']]['title']
                    : ''
            );
            if (isset($parts['err'])) {
                $this->logErr($parts['err'] . " - template mode");
                return 3;
            }

            $end_ts = $info['tmpl_start_ts'] + $td[$info['tmpl_day']][$info['tmpl_idx']]['dur'][intval($info['appt_dur'])] * 60;

            // make UID
            $h = hash("tiger128,4", $uri . rand() . $userId . $pageId . time() . $info['tmpl_start_ts'] . $end_ts);
            $uid = substr($h, 0, 7) . "-" .
                substr($h, 7, 6) . "-" .
                substr($h, 13, 6) . "-" .
                substr($h, 19, 6) . "-tm" .
                substr($h, 25);

            try {
                $ctz = new \DateTimeZone($tza[BackendUtils::TMPL_TZ_NAME]);
            } catch (\Exception $e) {
                $this->logger->warning('Can not set timezone from template, using default...');
                $ctz = $this->utils->getCalendarTimezone($userId, $this->getCalendarById($calId, $userId));
            }

            $dt = new \DateTime('now', $ctz);

            // Insert the UID, start and end
            $d = $parts['1_before_uid'] . $uid .
                $parts['2_before_dts'] . $dt->setTimestamp($info['tmpl_start_ts'])->format(self::TIME_FORMAT_NO_Z) .
                $parts['3_before_dte'] . $dt->setTimestamp($end_ts)->format(self::TIME_FORMAT_NO_Z) .
                $parts['4_last'];

            // Special "lock" uid
            $lock_uid = "LOCK_" . hash("tiger128,4", $info['tmpl_start_ts'] . $pageId . $userId . $settings[BackendUtils::CLS_TMM_DST_ID]);

            $start_ts = $info['tmpl_start_ts'];

        } elseif ($ts_mode === BackendUtils::CLS_TS_MODE_EXTERNAL) {
            // external mode...
            // ... query source cal for source uri
            $srcId = $settings[BackendUtils::CLS_XTM_SRC_ID];
            $srcUri = $info['ext_src_uri'];
            $src_data = $this->getObjectData($srcId, $srcUri);
            if ($src_data === null) {
                $this->logErr("Source object does not exist - mode: " . $ts_mode . ", cal: " . $srcId . ", uri: " . $srcUri);
                return 1;
            }

            /** @var \Sabre\VObject\Component\VCalendar $vo */
            $vo = Reader::read($src_data);
            if (!isset($vo->VEVENT) || !$vo->VEVENT->DTSTART->hasTime()) {
                $vo->destroy();
                $this->logErr("Bad source data - calId: " . $srcId . ", uri: " . $srcUri);
                return 2;
            }

            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;

            /** @var \Sabre\VObject\Property\ICalendar\DateTime $dt_start */
            $dt_start = $evt->DTSTART;

            if (isset($dt_start->parameters['TZID']) && isset($vo->VTIMEZONE)) {
                $tzi = $vo->VTIMEZONE->serialize();
            } elseif (str_contains($dt_start->getValue(), 'Z')) {
                $tzi = 'UTC';
            } else {
                if ($dt_start->isFloating()) {
                    $this->logErr("floating timezones are not supported - calId: " . $srcId . ", uri: " . $srcUri);
                } else {
                    $this->logErr("bad timezone info - calId: " . $srcId . ", uri: " . $srcUri);
                }
                return 3;
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $parts = $this->utils->makeAppointmentParts(
                $userId, $tzi,
                (new \DateTime('now', new \DateTimeZone('UTC')))->format(self::TIME_FORMAT),
                isset($evt->SUMMARY) ? '_' . $evt->SUMMARY->getValue() : ''
            );
            if (isset($parts['err'])) {
                $this->logErr($parts['err'] . " - calId: " . $srcId . ", uri: " . $srcUri);
                return 4;
            }

            // make UID
            $h = hash("tiger128,4", $uri . rand() . $tzi . $srcUri . $srcId);
            $uid = substr($h, 0, 7) . "-" .
                substr($h, 7, 6) . "-" .
                substr($h, 13, 6) . "-" .
                substr($h, 19, 6) . "-aa" .
                substr($h, 25);

            $utz = $this->utils->getCalendarTimezone($userId, $this->getCalendarById($calId, $userId));

            $dt = $dt_start->getDateTime($utz);
            // Insert the UID, start and end
            $d = $parts['1_before_uid'] . $uid .
                $parts['2_before_dts'] . $dt->setTimestamp($info['ext_start'])->format(self::TIME_FORMAT_NO_Z) .
                $parts['3_before_dte'] . $dt->setTimestamp($info['ext_end'])->format(self::TIME_FORMAT_NO_Z) .
                $parts['4_last'];

            // Special "lock" uid
            $lock_uid = "LOCK_" . hash("tiger128,4", $info['ext_start'] . $info['ext_end'] . $info['ext_src_uri']);

            $start_ts = $info['ext_start'];

        } else {
            // manual mode
            $d = $this->getObjectData($calId, $uri);
            if ($d === null) {
                $this->logErr("Object does not exist - mode: 0, cal: " . $calId . ", uri: " . $uri);
                return 1;
            }

            // We need uid and start_ts
            $vo = Reader::read($d);
            if ($vo === null || !isset($vo->VEVENT)) {
                $this->logger->error("Bad Data: not an event");
                return 1;
            }
            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;
            if (!isset($evt->DTSTART)) {
                $this->logger->error("Bad Data: no DTSTART");
                return 1;
            }

            $lock_uid = $uid = $evt->UID->getValue();
            $start_ts = $evt->DTSTART->getDateTime()->getTimestamp();
        }

        $err = '';
        // Ugly locking to avoid a race condition and booking the same appointment twice...
        $ec = 0;
        $query = $this->db->getQueryBuilder();
        try {
            $query->insert(BackendUtils::HASH_TABLE_NAME)
                ->values([
                    'uid' => $query->createNamedParameter($lock_uid),
                    'hash' => $query->createNamedParameter('99999999.0000000000000000000000'),
                    'user_id' => $query->createNamedParameter($userId),
                    'start' => $query->createNamedParameter($start_ts)
                ])->execute();
        } catch (\Exception $e) {
            // uid already exists
            $this->logger->warning("Lock uid already exists");
            $ec = 1;
        }

        if ($ec === 0) {
            // It is SAFE (for manual made) to take this time slot

            if ($ts_mode !== BackendUtils::CLS_TS_MODE_SIMPLE) {
                // for external and template modes we need to re-check the time range and update the lock_uid to "real" uid or delete the lock_uid if the time range is "taken"

                if ($ts_mode === BackendUtils::CLS_TS_MODE_EXTERNAL) {
                    $trc = $this->checkRangeTR($info['ext_start'], $info['ext_end'], $calId, $utz, $settings);
                } else {
                    // template mode
                    $dt->setTimestamp($info['tmpl_start_ts']);
                    $dt_end = clone($dt);
                    $dt_end->setTimestamp($end_ts);
                    $trc = $this->checkRangeTemplate($settings, $dt, $dt_end, $userId);
                }
                if ($trc === 0) {
                    // the time range is good, create new object...
                    if ($this->createObject($calId, $uri, $d) !== false) {
                        // new object OK, set real uid hash
                        $query = $this->db->getQueryBuilder();
                        $query->insert(BackendUtils::HASH_TABLE_NAME)
                            ->values([
                                'uid' => $query->createNamedParameter($uid),
                                'hash' => $query->createNamedParameter('99999999.0000000000000000000000'),
                                'user_id' => $query->createNamedParameter($userId),
                                'start' => $query->createNamedParameter($start_ts)
                            ])->execute();
                    } else {
                        $err = "Can not create object - mode: " . $ts_mode . ", cal: " . $calId . ", uri: " . $uri;
                        $ec = 6;
                    }

                } else {
                    // spot busy or error occurred
                    $this->logger->warning("BusySpot/error detected in setAttendee func, mode: " . $ts_mode . ", trc_code: " . $trc);
                    $ec = 1;
                }

                // "release" the "lock"
                $this->utils->deleteApptHashByUID($this->db, $lock_uid);
            }

            if ($ec === 0) {

                $info["_more_ics_text"] = $settings[BackendUtils::EML_ICS_TXT];

                $newData = $this->utils->dataSetAttendee($d, $info, $userId, $uri);
                if ($newData === "1") {
                    $ec = 1;
                    $err = "Bad appointment status, [select different time]";
                } elseif ($newData === "2") {
                    $err = "Can not set attendee data";
                    $ec = 7;
                } else {
                    if ($this->updateObject($calId, $uri, $newData) === false) {
                        $err = "Can not update object: " . $uri;
                        $ec = 8;
                    } else {
                        // Object Update: SUCCESS
                        $ec = 0;
                    }
                }
            }

            if ($ec !== 0) {
                $this->utils->deleteApptHashByUID($this->db, $uid);
                if (!empty($err)) {
                    $this->logErr($err);
                }
            }

        } else {
            // uid is already in the hash table
            $this->logErr("Select different time");
            // $ec=1
        }

        return $ec;
    }

    private function logErr(string $err): void
    {
        $this->logger->error($err);
    }

    /**
     * @inheritDoc
     */
    function confirmAttendee($userId, $pageId, $calId, $uri): array
    {
        return $this->confirmCancel($userId, $pageId, $calId, $uri, true);
    }

    /**
     * @inheritDoc
     */
    function cancelAttendee($userId, $pageId, $calId, $uri): array
    {
        return $this->confirmCancel($userId, $pageId, $calId, $uri, false);
    }

    private function confirmCancel(string $userId, string $pageId, string $calId, string $uri, bool $do_confirm): array
    {
        $ret = [1, null, ""];
        $err = '';

        // for manual mode:
        //  if confirming:
        //      pending appointments are always in the main calendar
        //      might need to be moved to BackendUtils::CLS_DEST_ID if set
        //  if cancelling:
        //      calId is "pre-calculated" in the PageController
        //
        // for external mode:
        //  pending appointments are always in the main calendar
        $d = $this->getObjectData($calId, $uri);

        if ($d === null) {
            $err = "Object does not exist: " . $uri . ", calId: " . $calId;
        } else {
            $attendeeName = "";
            if ($do_confirm) {
                list($newData, $date, $attendeeName) = $this->utils->dataConfirmAttendee($d, $userId, $pageId);
            } else {
                list($newData, $date) = $this->utils->dataCancelAttendee($d, $userId, $pageId);
            }
            if ($newData === null) {
                $err = "Can not set attendee data";
            } elseif (empty($newData)) {
                // Already confirmed
                $ret = [0, $date, $attendeeName];
            } else {

                $settings = $this->utils->getUserSettings();

                if ($do_confirm && $settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE
                    && $settings[BackendUtils::CLS_DEST_ID] !== "-1") {
                    // confirming in regular mode into different calendar
                    $dcl_id = $settings[BackendUtils::CLS_DEST_ID];

                    // 1. delete from original calendar
                    $ra = $this->deleteCalendarObject($userId, $calId, $uri);
                    if ($ra[0] !== 0) {
                        $err = "Can not delete object: " . $uri . ", dcl=" . $dcl_id;
                    } else {
                        // 2. create new calendar object
                        if ($this->createObject($dcl_id, $uri, $newData) === false) {
                            $err = "Can not create object: " . $uri . ", dcl=" . $dcl_id;
                        } else {
                            // 3. update calendar object - this is bad, but as of now only updateObject() triggers a DavEvent that send emails
                            if ($this->updateObject($dcl_id, $uri, $newData) === false) {
                                $err = "Can not update object: " . $uri . ", dcl=" . $dcl_id;
                            } else {
                                // Object Update: SUCCESS
                                $ret = [0, $date, $attendeeName];
                            }
                        }
                    }
                } else {
                    // same calendar
                    if ($this->updateObject($calId, $uri, $newData) === false) {
                        $err = "Can not update object: " . $uri;
                    } else {
                        // Object Update: SUCCESS
                        $ret = [0, $date, $attendeeName];
                    }
                }
            }
        }
        if ($err !== '') {
            $this->logger->error($err);
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function deleteCalendarObject($userId, $calId, $uri): array
    {
        $ret = [0, '', '', 'UTC', ''];
        $d = $this->getObjectData($calId, $uri);
        if ($d !== null) {
            $ra = $this->utils->dataDeleteAppt($d);
            $ret[1] = $ra[0]; // $date_time
            $ret[2] = $ra[1]; // $dt_info
            $ret[3] = $ra[2]; // $tz_data
            $ret[4] = $ra[3]; // $title
            $this->backend->deleteCalendarObject($calId, $uri);
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    static function checkCompatibility(): bool
    {

        $className = 'OCA\DAV\CalDAV\CalDavBackend';
        $interfaceName = 'Sabre\CalDAV\Backend\SubscriptionSupport';

        if (class_exists($className, false) && defined($className . '::CALENDAR_TYPE_SUBSCRIPTION')) {
            $interfaces = class_implements($className, false);
            foreach ($interfaces as $i) {
                if ($i === $interfaceName) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Why? Because CalDavBackend->calendarQuery() expands recurrence when
     * both Start and End are provided. Additionally, even though the results
     * are cached, getCalendarObject still needs to be called.
     * Other CalDavBackend->search*** function are not quite right for this use case.
     *
     * Events with CalDavBackend::CLASSIFICATION_PRIVATE ("When shared hide this event")
     * are ignored automatically
     * Classes in NC:
     *   PRIVATE = when shared hide this event
     *   CONFIDENTIAL = when shared show when busy
     *   PUBLIC = when shared show full event
     *
     * @param numeric[] $calIds
     * @param int $startTs
     * @param int $endTs
     * @param string[] $propFilters - Ex: ['STATUS:CONFIRMED','LOCATION'] will only get events with STATUS:CONFIRMED and non-empty LOCATION prop
     * @return void
     *
     * @see CalDavBackend::INDEXED_PROPERTIES For the list of available propFilters
     */
    private function fastQuery(array $calIds, int $startTs, int $endTs, array $propFilters = [], array $additionalColumns = [])
    {
        $query = $this->db->getQueryBuilder();

        $calendarsOrExpr = $query->expr()->orX();
        foreach ($calIds as $calId) {
            $calendarsOrExpr->add($query->expr()->eq(
                'c.calendarid', $query->createNamedParameter((int)$calId)
            ));
        }

        $query->select(['c.calendardata'])
            ->from('calendarobjects', 'c')
            ->where($calendarsOrExpr)
            ->andWhere($query->expr()->neq('c.classification', $query->createNamedParameter(CalDavBackend::CLASSIFICATION_PRIVATE)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter('VEVENT')))
            ->andWhere($query->expr()->isNull('c.deleted_at'))
            ->setMaxResults(1024 * count($calIds));
        foreach ($additionalColumns as $column) {
            $query->addSelect('c.' . $column);
        }

        if ($startTs > 0) {
            $query->andWhere($query->expr()->gt('lastoccurence', $query->createNamedParameter($startTs)));
        }
        if ($endTs > 0) {
            $query->andWhere($query->expr()->lt('firstoccurence', $query->createNamedParameter($endTs)));
        }

        if (($cnt = count($propFilters)) > 0) {
            for ($i = 0; $i < $cnt; $i++) {
                $jn = 'j' . $i;
                $nameValue = explode(':', $propFilters[$i]);
                $query->leftJoin('c', 'calendarobjects_props', $jn,
                    $query->expr()->eq('c.id', $jn . '.objectid')
                )->andWhere(
                    count($nameValue) === 1
                        ? $query->expr()->eq($jn . '.name',
                        $query->createNamedParameter($nameValue[0]))
                        : $query->expr()->andX(
                        $query->expr()->eq($jn . '.name',
                            $query->createNamedParameter($nameValue[0])),
                        $query->expr()->eq($jn . '.value',
                            $query->createNamedParameter($nameValue[1]))
                    )
                );
            }
        }
        try {
            $result = $query->executeQuery();
        } catch (\Throwable $e) {
            $this->logger->error('fastQuery: ' . $e->getMessage(), [
                'app' => Application::APP_ID,
                'exception' => $e
            ]);
            return;
        }

        if (empty($additionalColumns)) {
            while ($row = $result->fetch()) {
                $cdata = $row['calendardata'];
                yield is_resource($cdata) ? stream_get_contents($cdata) : $cdata;
            }
        } else {
            while ($row = $result->fetch()) {
                $cdata = $row['calendardata'];
                if (is_resource($cdata)) {
                    $row['calendardata'] = stream_get_contents($cdata);
                }
                yield $row;
            }
        }
        $result->closeCursor();
    }
}