<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;


use OCA\Appointments\IntervalTree\AVLIntervalTree;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IConfig;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Xml\Service as XmlService;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Recur\NoInstancesException;
use Sabre\Xml\ParseException;
use Sabre\VObject\Reader;

class BCSabreImpl implements IBackendConnector{

    const TIME_FORMAT="Ymd\THis\Z";
    const TIME_FORMAT_NO_Z="Ymd\THis";

    private $backend;
    private $config;
    private $appName;
    private $utils;

    public function __construct(
                        $AppName,
                        CalDavBackend $backend,
                        IConfig $config,
                        BackendUtils $utils){
        /** @var BackendInterface $backend */
        $this->backend=$backend;
        $this->config=$config;
        $this->appName=$AppName;
        $this->utils=$utils;
    }

    /**
     * @inheritDoc
     */
    function queryRangePast($calIds,$end,$only_empty,$delete){

        $cc=count($calIds);
        if($cc===0){

            return "0";
        }

        $parser=new XmlService();
        $parser->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = 'Sabre\\CalDAV\\Xml\\Request\\CalendarQueryReport';

        $ots=$end->getTimestamp();
        $end->setTimestamp($ots+50400);

        try {
            $result = $parser->parse($this::makeDavReport(null,$end,$only_empty===true?"TENTATIVE":null));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return null;
        }

        $utz=$end->getTimezone();
        $cnt=0;

        if($delete) {
            // let's make easier for the DavListener...
            $ses = \OC::$server->getSession();
            $ses->set(
                BackendUtils::APPT_SES_KEY_HINT,
                BackendUtils::APPT_SES_SKIP);

            // Cleanup hash table
            if($only_empty===false) {
                $cutoff_str = $end->modify('-35 days')->format(BackendUtils::FLOAT_TIME_FORMAT);
                $db = \OC::$server->getDatabaseConnection();
                $query = $db->getQueryBuilder();

                $query->delete(BackendUtils::HASH_TABLE_NAME)
                    ->where($query->expr()->lt('hash',
                        $query->createNamedParameter($cutoff_str)))
                    ->execute();
            }
        }

        for($i=0;$i<$cc;$i++) {
            $calId=$calIds[$i];
            $urls=$this->backend->calendarQuery($calId, $result->filters);
            $objs=$this->backend->getMultipleCalendarObjects($calId,$urls);
            foreach ($objs as $obj){
                $vo=Reader::read($obj['calendardata']);
                $ts=$vo->VEVENT->DTEND->getDateTime($utz)->getTimestamp();
                if($ts<=$ots){
                    if($delete){
//                        $this->deleteCalendarObject("",$calId,$obj["uri"]);
                        $this->backend->deleteCalendarObject($calId, $obj["uri"]);
                    }
                    $cnt++;
                }
            }
        }
        return $cnt;
    }

    /**
     * @param int $start_ts UTC
     * @param int $end_ts UTC
     * @param string $calId
     * @param \DateTimeZone $utz user's timezone
     * @param bool $cat_required
     * @return int 0=no events, 1=at least 1
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function checkRangeTR($start_ts, $end_ts, $calId, $utz, $cat_required){

        // Because of floating timezones...
        // 50400 = 14 hours
        /** @noinspection PhpUnhandledExceptionInspection */
        $dt=new \DateTime('@'.($start_ts-50400),$utz);
        $r_start=$dt->format(self::TIME_FORMAT);
        $dt->setTimestamp($end_ts+50400);
        $r_end=$dt->format(self::TIME_FORMAT);

        $parser=new XmlService();
        $parser->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = 'Sabre\\CalDAV\\Xml\\Request\\CalendarQueryReport';
        try {
            $result = $parser->parse($this::makeTrDavReport($r_start,$r_end,$cat_required));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return -1;
        }

        $urls=$this->backend->calendarQuery($calId,$result->filters);
        $objs = $this->backend->getMultipleCalendarObjects($calId, $urls);

        $c=0;
        foreach ($objs as $obj) {

            $cd=$obj['calendardata'];

            if(strpos($cd,"\r\nTRANSP:TRANSPARENT\r\n",22)!==false){
                continue;
            }

            /** @var \Sabre\VObject\Component\VCalendar $vo */
            $vo = Reader::read($cd);
            if (!isset($vo->VEVENT)) {
                $vo->destroy();
                continue;
            }
            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;

            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            if(isset($evt->RRULE) || !$evt->DTSTART->hasTime()
                || !isset($evt->DTEND)
                || (isset($evt->CLASS) && $evt->CLASS->getValue()!=='PUBLIC')){
                $vo->destroy();
                continue;
            }

            /** @noinspection PhpPossiblePolymorphicInvocationInspection */
            $s_ts = $evt->DTSTART->getDateTime($utz)->getTimestamp();
            if($s_ts<$end_ts){
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $e_ts=$evt->DTEND->getDateTime($utz)->getTimestamp();
                if($e_ts>$start_ts){
                    $c=1;
                    break;
                }
            }
            $vo->destroy();
        }
        return $c;
    }


    /**
     * @param string $calIds dstCal(main)+chr(31)+srcCal(free spots)
     * @param \DateTime $start should have user's timezone
     * @param \DateTime $end should have user's timezone
     * @param $key
     * @param $userId
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection PhpPossiblePolymorphicInvocationInspection
     */
    private function queryRangeTR($calIds, $start, $end,$key,$userId){

        // user's timezone
        $utz=$start->getTimezone();
        $utz_offset=$start->getOffset();

        $start_ts=$start->getTimestamp();
        $end_ts=$end->getTimestamp();

        // We need to adjust for floating timezones and filter
        $rep_start=clone $start;
        $rep_start->modify('-14 hours');
        $rep_end=clone $end;
        $rep_end->modify('+14 hours');

        $start_str=$rep_start->format(self::TIME_FORMAT);
        $end_str=$rep_end->format(self::TIME_FORMAT);

        // parse calIds
        $sp=strpos($calIds,chr(31));
        if($sp===false){
            return null;
        }
        $srcId=substr($calIds,$sp+1);
        $dstId=substr($calIds,0,$sp);

        // $cls is used for CLS_XTM_REQ_CAT CLS_XTM_PUSH_REC
        $cls=$this->utils->getUserSettings(
            BackendUtils::KEY_CLS,$userId);

        $parser=new XmlService();
        $parser->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = 'Sabre\\CalDAV\\Xml\\Request\\CalendarQueryReport';

        try {
            $result = $parser->parse($this::makeTrDavReport($start_str,$end_str,$cls[BackendUtils::CLS_XTM_REQ_CAT]));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return null;
        }

        // Get booked spots
        $urls=$this->backend->calendarQuery($dstId,$result->filters);
        $booked_tree=null;

        if(count($urls)>0) {
            $itc=new AVLIntervalTree();
            $objs = $this->backend->getMultipleCalendarObjects($dstId, $urls);
            foreach ($objs as $obj) {

                if(strpos($obj['calendardata'],"\r\nTRANSP:TRANSPARENT\r\n",22)!==false){
                    continue;
                }

                /** @var \Sabre\VObject\Component\VCalendar $vo */
                $vo = Reader::read($obj['calendardata']);
                if (!isset($vo->VEVENT)) {
                    $vo->destroy();
                    continue;
                }
                /** @var \Sabre\VObject\Component\VEvent $evt */
                $evt = $vo->VEVENT;
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                if(isset($evt->RRULE) || !$evt->DTSTART->hasTime()
                    || (isset($evt->CLASS) && $evt->CLASS->getValue()!=='PUBLIC')){
                    $vo->destroy();
                    continue;
                }

                /** @var \Sabre\VObject\Property\ICalendar\DateTime $dt_start */
                $dt_start = $evt->DTSTART;
                $start_date_time = $dt_start->getDateTime($utz);
                $s_ts = $start_date_time->getTimestamp();


                if ($s_ts > $start_ts && $s_ts < $end_ts) {

                    // Get end_timestamp
                    if (isset($evt->DTEND)) {
                        $e_ts = $evt->DTEND->getDateTime($utz)->getTimeStamp();
                    } elseif (isset($evt->DURATION)) {
                        $e_ts = $start_date_time->add($evt->DURATION->getDateInterval())->getTimeStamp();
                    } else {
                        $vo->destroy();
                        continue;
                    }

                    $itc->insert($booked_tree, $s_ts, $e_ts);
                }
                $vo->destroy();
            }
        }

        try {
            $result = $parser->parse($this::makeTrDavReport($start_str,$end_str,$cls[BackendUtils::CLS_XTM_REQ_CAT]));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return null;
        }

        // Get free/available spots
        $urls=$this->backend->calendarQuery($srcId,$result->filters);
        $objs=$this->backend->getMultipleCalendarObjects($srcId,$urls);

        $str_out='';
        // '_'ts_mode(1byte)ses_time(4bytes)dates(8bytes)uri(no extension)
        $ses_info='_1'.pack("L",time());

        $showET=$this->utils->getUserSettings(BackendUtils::KEY_PSN,$userId)[BackendUtils::PSN_END_TIME];
        foreach ($objs as $obj) {

            $cd=$obj['calendardata'];

            if(strpos($cd,"\r\nTRANSP:TRANSPARENT\r\n",22)===false){
                // must be "Free" aka TRANSPARENT
                continue;
            }

            /** @var \Sabre\VObject\Component\VCalendar $vo */
            $vo = Reader::read($cd);
            if (!isset($vo->VEVENT)) {
                // not an event
                $vo->destroy();
                continue;
            }

            /** @var \Sabre\VObject\Component\VEvent $evt */
            $evt = $vo->VEVENT;

            if(!$evt->DTSTART->hasTime()
                || (isset($evt->CLASS) && $evt->CLASS->getValue()!=='PUBLIC')){
                $vo->destroy();
                continue;
            }

            $ts_pref = 'U';
            $ts_offset = 0;
            if ($evt->DTSTART->isFloating()) {
                $ts_pref = 'F';
                $ts_offset = $utz_offset;
            }

            $atl=':';
            if(isset($evt->SUMMARY)){
                $s=$evt->SUMMARY->getValue();
                if($s[0]==="_"){
                    $atl.=$s;
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
                if(isset($evt->STATUS)
                    && $evt->STATUS->getValue()==='CANCELLED'){
                    // check if CANCELLED early
                    $vo->destroy();
                    continue;
                }
                // TODO: reuse FakeIterator
                $it=new FakeIterator($evt,$utz);
                $skip_count=0;
            }

            $c = 0;
            while ($it->valid()) {

                $_evt=$it->getEventObject();
                if(isset($_evt->STATUS)
                    && $_evt->STATUS->getValue()==='CANCELLED'){
                    continue;
                }

                $s_ts = $it->getDTStart()->getTimestamp();

                if ($s_ts >= $end_ts || $c > 96) {
                    break;
                }
                if ($s_ts > $start_ts) {
                    $e_ts = $it->getDTEnd()->getTimestamp();

                    if (AVLIntervalTree::lookUp($booked_tree,
                            $s_ts, $e_ts) === null) {

                        $str_out.=$ts_pref.($s_ts + $ts_offset)
                            .($showET?":".($e_ts + $ts_offset):"")
                            .':'.$this->utils->encrypt($ses_info.pack("LL",$s_ts,$e_ts).substr($obj['uri'],0,-4),$key).$atl.',';
                    }
                }
                $c++;
                $it->next();
            }

            if ($skip_count > 14 && $cls[BackendUtils::CLS_XTM_PUSH_REC]===true) {
                // Optimize recurrence
                $it->rewind();
                $skip_until = $skip_count - 7;
                while ($it->valid() && $it->key() < $skip_until) {
                    $it->next();
                }
                $this->utils->optimizeRecurrence($it->getDtStart(), $it->getDtEnd(), $skip_until, $vo);
                $this->updateObject($srcId, $obj['uri'], $vo->serialize());
            }

            $vo->destroy();
        }
        return $str_out!==''?substr($str_out,0,-1):null;
    }



    /**
     * @inheritDoc
     */
    function queryRange($calId, $start, $end, $mode){

        $no_uri=($mode==='no_url');
        if($no_uri){
            $key=''; // add end_time instead of uri
        }else {
            $key = hex2bin($this->config->getAppValue($this->appName, 'hk'));
            if (empty($key)) {
                \OC::$server->getLogger()->error("Can't find hkey");
                return null;
            }
        }

        $userId=substr($mode,1);
        if($mode[0]==="1"){
            // external mode
            // $calId = dstCal(main)+chr(31)+srcCal(free spots) @see PageController->showForm()
            return $this->queryRangeTR($calId, $start, $end, $key, $userId);
        }

        // Simple Mode...

        $f_start=$start->getTimestamp();
        $f_end=$end->getTimestamp();

        // We need to adjust for floating timezones and filter
        // 50400 = 14 hours
        $start->setTimestamp($f_start-50400);
        $end->setTimestamp($f_end+50400);

        $parser=new XmlService();
        $parser->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = 'Sabre\\CalDAV\\Xml\\Request\\CalendarQueryReport';
        try {
            //$no_url(do not filter status) request is for the schedule generator @see grid.js::addPastAppts()
            $result = $parser->parse($this::makeDavReport($start,$end,$no_uri===false?"TENTATIVE":null));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return null;
        }

        $urls=$this->backend->calendarQuery($calId,$result->filters);
        $objs=$this->backend->getMultipleCalendarObjects($calId,$urls);

        $ses_start=time().'|';
        $ret='';

        $utz_offset=$start->getOffset();
        $utz=$start->getTimezone();
        $start_ts=$f_start;

        $showET=$this->utils->getUserSettings(BackendUtils::KEY_PSN,$userId)[BackendUtils::PSN_END_TIME];

        foreach ($objs as $obj){

            $vo=Reader::read($obj['calendardata']);

            /** @var  \Sabre\VObject\Property\ICalendar\DateTime $dt_start */
            $dt_start=$vo->VEVENT->DTSTART;
            $s_ts=$dt_start->getDateTime($utz)->getTimestamp();

            if($s_ts>$start_ts){

                $e_ts=$vo->VEVENT->DTEND->getDateTime($utz)->getTimestamp();

                $ts_pref = 'U';
//                $ts_offset = 0;
                if ($dt_start->isFloating()) {
                    $ts_pref = 'F';
//                    $ts_offset = $utz_offset;
                    $s_ts+=$utz_offset;
                    $e_ts+=$utz_offset;
                }

                if($key!==""){

                    $atl=':';
                    if(isset($vo->VEVENT->SUMMARY)){
                        $s=$vo->VEVENT->SUMMARY->getValue();
                        if($s[0]==="_"){
                            $atl.=$s;
                        }
                    }

                    $ret.=$ts_pref.$s_ts
                        .($showET? ":".$e_ts :"")
                        .':'.$this->utils->encrypt($ses_start.$obj['uri'],$key)
                        .$atl.',';
                }else{
                    // add end_time instead of uri
                    $ret.=$ts_pref.$s_ts.':'.$ts_pref.$e_ts.',';
                }
            }
            $vo->destroy();
        }

        return $ret!==''?substr($ret,0,-1):null;
    }

    /**
     * @inheritDoc
     * @noinspection PhpRedundantCatchClauseInspection
     */
    function updateObject($calId, $uri, $data){
        try {
            $this->backend->updateCalendarObject($calId, $uri, $data);
        } catch (BadRequest $e) {
            \OC::$server->getLogger()->error($e);
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     * @noinspection PhpRedundantCatchClauseInspection
     */
    function createObject($calId, $uri, $data)
    {
        try {
            $this->backend->createCalendarObject($calId, $uri, $data);
        } catch (BadRequest $e) {
            \OC::$server->getLogger()->error($e);
            return false;
        }
        return true;
    }

//    /**
//     * @inheritDoc
//     */
//    function verifyCalId($calId, $userId){
//        $ca=$this->backend->getCalendarsForUser(BackendManager::PRINCIPAL_PREFIX.$userId);
//        foreach ($ca as $c){
//            if($c["id"]==$calId) return true;
//        }
//        return false;
//    }

    /**
     * @inheritDoc
     */
    function getCalendarsForUser($userId){
        $ca=$this->backend->getCalendarsForUser(BackendManager::PRINCIPAL_PREFIX.$userId);
        $ret=[];
        foreach ($ca as $c){
            $ci=$this->transformCalInfo($c);
            if($ci!==null){
                $ret[]=$ci;
            }
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function getCalendarById($calId,$userId){
        $ca=$this->backend->getCalendarsForUser(BackendManager::PRINCIPAL_PREFIX.$userId);
        foreach ($ca as $c){
            // $c['id'] can be a string or an int
            if($calId==$c['id']){
                return $this->transformCalInfo($c);
            }
        }
        return null;
    }


    /**
     * @inheritDoc
     */
    function getObjectData($calId, $uri)
    {
        $d=$this->backend->getCalendarObject($calId,$uri);
        if($d!==null){
            return $d['calendardata'];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    function setAttendee($userId, $calId, $uri, $info)
    {

        $pageId=$info['_page_id'];

        $cls=$this->utils->getUserSettings(
            BackendUtils::KEY_CLS,$userId);
        $cms=$this->utils->getUserSettings(
            $pageId==='p0'
                ?BackendUtils::KEY_CLS
                :BackendUtils::KEY_MPS.$pageId,
            $userId);

        $ts_mode=$cms[BackendUtils::CLS_TS_MODE];

        if($ts_mode==='1'){
            // external mode...
            // ... query source cal for source uri
            $srcId=$cms[BackendUtils::CLS_XTM_SRC_ID];
            $srcUri=$info['ext_src_uri'];
            $src_data = $this->getObjectData($srcId, $srcUri);
            if($src_data===null){
                $this->logErr("Source object does not exist - mode: ".$ts_mode.", cal: ".$srcId.", uri: ".$srcUri);
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

            if ($dt_start->isFloating()) {
                $tzi = "L";
            }elseif(isset($dt_start->parameters['TZID']) && isset($vo->VTIMEZONE)){
                $tzi=$vo->VTIMEZONE->serialize();
            }elseif(strpos($dt_start->getValue(), 'Z') !== false){
                $tzi='UTC';
            }else{
                $this->logErr("bad timezone info - calId: " . $srcId . ", uri: " . $srcUri);
                return 3;
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $parts=$this->utils->makeAppointmentParts(
                $userId,$pageId,$this->appName,$tzi,
                (new \DateTime('now',new \DateTimeZone('UTC')))->format(self::TIME_FORMAT));
            if(isset($parts['err'])) {
                $this->logErr($parts['err']." - calId: " . $srcId . ", uri: " . $srcUri);
                return 4;
            }

            // make UID
            $h=hash("tiger128,4",$uri.rand().$tzi.$srcUri.$srcId);
            $uid=substr($h,0,7)."-".
                substr($h,7,6)."-".
                substr($h,13,6)."-".
                substr($h,19,6)."-aa".
                substr($h,25);


            $utz=$this->utils->getUserTimezone($userId,$this->config);
            $dt=$dt_start->getDateTime($utz);
            // Insert the UID, start and end
            $d= $parts['1_before_uid'].$uid.
                $parts['2_before_dts'].$dt->setTimestamp($info['ext_start'])->format(self::TIME_FORMAT_NO_Z).
                $parts['3_before_dte'].$dt->setTimestamp($info['ext_end'])->format(self::TIME_FORMAT_NO_Z).
                $parts['4_last'];

            // Special "lock" uid
            $lock_uid="LOCK_".hash("tiger128,4",$info['ext_start'].$info['ext_end'].$info['ext_src_uri']);

        }else{
            // manual mode
            $d=$this->getObjectData($calId,$uri);
            if($d===null){
                $this->logErr("Object does not exist - mode: 0, cal: ".$calId.", uri: ".$uri);
                return 1;
            }

            // extract uid
            $us = strpos($d, "\r\nUID:", strpos($d, "BEGIN:VEVENT") + 12);
            if($us === false) {
                $this->logErr("Bad object data for " . $uri);
                return 5;
            }

            $us+=6;
            $lock_uid=$uid=substr($d,$us,strpos($d,"\r\n",$us)-$us);
        }

        $err='';
        $db = \OC::$server->getDatabaseConnection();

        // Ugly locking to avoid a race condition and booking the same appointment twice...
        $ec=0;
        $query = $db->getQueryBuilder();
        try {
            $query->insert(BackendUtils::HASH_TABLE_NAME)
                ->values([
                    'uid' => $query->createNamedParameter($lock_uid),
                    'hash' => $query->createNamedParameter('99999999.0000000000000000000000')
                ])->execute();
        }catch (\Exception $e){
            // uid already exists
            $ec=1;
        }

        if ($ec === 0) {
            // It is SAFE (for manual made) to take this time slot

            if($ts_mode==="1"){
                // for external mode we need to re-check the time range and update the lock_uid to "real" uid or delete the lock_uid if the time range is "taken"


                /** @noinspection PhpUndefinedVariableInspection */
                $trc=$this->checkRangeTR($info['ext_start'],$info['ext_end'],$calId,$utz,$cls[BackendUtils::CLS_XTM_REQ_CAT]);

                if($trc===0){
                    // the time range is good, create new object...
                    if($this->createObject($calId,$uri,$d)!==false){
                        // new object OK, set real uid hash
                        $query = $db->getQueryBuilder();
                        $query->insert(BackendUtils::HASH_TABLE_NAME)
                            ->values([
                                'uid' => $query->createNamedParameter($uid),
                                'hash' => $query->createNamedParameter('99999999.0000000000000000000000')
                            ])->execute();
                    }else{
                        $err="Can not create object - mode: ".$ts_mode.", cal: ".$calId.", uri: ".$uri;
                        $ec=6;
                    }

                }else if($trc>0){
                    // spot busy
                   $ec=1;
                }

                // "release" the "lock"
                $this->utils->deleteApptHashByUID($db,$lock_uid);
            }
            if($ec===0) {
                $newData = $this->utils->dataSetAttendee($d, $info, $userId);
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

            if($ec!==0){
                $this->utils->deleteApptHashByUID($db,$uid);
                if(!empty($err)){
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

    private function logErr($err){
        \OC::$server->getLogger()->error($err);
    }

    /**
     * @inheritDoc
     */
    function confirmAttendee($userId, $calId, $uri){
        return $this->confirmCancel($userId, $calId,$uri,true);
    }

    /**
     * @inheritDoc
     */
    function cancelAttendee($userId, $calId, $uri){
        return $this->confirmCancel($userId, $calId,$uri,false);
    }

    private function confirmCancel($userId, $calId, $uri, $do_confirm){
        $ret=[1,null];
        $err='';

        // TODO: when simple mode has destination calendar confirm page reload shows error ...

        // for manual mode:
        //  if confirming:
        //      pending appointments are always in the main calendar
        //      might need to be moved to BackendUtils::CLS_DEST_ID if set
        //  if cancelling:
        //      calId is "pre-calculated" in the PageController
        //
        // for external mode:
        //  pending appointments are always in the main calendar
        $d=$this->getObjectData($calId,$uri);

        if($d===null){
            $err="Object does not exist: ".$uri;
        }else{
            if($do_confirm) {
                list($newData, $date, $pageId) = $this->utils->dataConfirmAttendee($d,$userId);
            }else{
                list($newData, $date, $pageId) = $this->utils->dataCancelAttendee($d);
            }
            if($newData===null){
                $err="Can not set attendee data";
            }elseif(empty($newData)){
                // Already confirmed
                $ret=[0,$date];
            }else{

                $cms=$this->utils->getUserSettings(
                    $pageId==='p0'
                        ?BackendUtils::KEY_CLS
                        :BackendUtils::KEY_MPS.$pageId,
                    $userId);

                if($do_confirm && $cms[BackendUtils::CLS_TS_MODE]==='0'
                    && $cms[BackendUtils::CLS_DEST_ID]!=="-1"){
                    // confirming in regular mode into different calendar
                    $dcl_id=$cms[BackendUtils::CLS_DEST_ID];

                    // 1. delete from original calendar
                    $ra=$this->deleteCalendarObject($userId,$calId,$uri);
                    if($ra[0]!==0){
                        $err = "Can not delete object: " . $uri .", dcl=".$dcl_id;
                    }else{
                        // 2. create new calendar object
                        if($this->createObject($dcl_id,$uri,$newData)===false){
                            $err = "Can not create object: " . $uri .", dcl=".$dcl_id;
                        }else{
                            // 3. update calendar object - this is bad, but as of now only updateObject() triggers a DavEvent that send emails
                            if ($this->updateObject($dcl_id, $uri, $newData) === false) {
                                $err = "Can not update object: " . $uri.", dcl=".$dcl_id;
                            } else {
                                // Object Update: SUCCESS
                                $ret = [0, $date];
                            }
                        }
                    }
                }else {
                    // same calendar
                    if ($this->updateObject($calId, $uri, $newData) === false) {
                        $err = "Can not update object: " . $uri;
                    } else {
                        // Object Update: SUCCESS
                        $ret = [0, $date];
                    }
                }
            }
        }
        if($err!==''){
            \OC::$server->getLogger()->error($err);
        }
        return $ret;
    }

    /**
     * @inheritDoc
     */
    function deleteCalendarObject($userId, $calId, $uri){
        $ret=[0,'','','L',''];
        $d=$this->getObjectData($calId,$uri);
        if($d!==null){
            $ra=$this->utils->dataDeleteAppt($d);
            $ret[1]=$ra[0];
            $ret[2]=$ra[1];
            $ret[3]=$ra[2];
            $ret[4]=$ra[3];
            $this->backend->deleteCalendarObject($calId, $uri);
        }
        return $ret;
    }

    /**
     * @inheritDoc
     * @return bool
     */
    static function checkCompatibility(){
        $c=CalDavBackend::class;
        if(class_exists($c,false)){
            $ins=class_implements($c,false);
            foreach ($ins as $i){
                if($i===BackendInterface::class){
                    return true;
                }
            }
        }
        return false;
    }

    private function transformCalInfo($c){
        // Do not use read only calendars
        if(isset($c['{http://owncloud.org/ns}read-only']) && $c['{http://owncloud.org/ns}read-only']===true){
            return null;
        }

        $a=[];
        $a['id']=(string)$c["id"];
        $a['displayName']=isset($c['{DAV:}displayname'])?$c['{DAV:}displayname']:"Calendar";
        $a['color']=isset($c['{http://apple.com/ns/ical/}calendar-color'])?$c['{http://apple.com/ns/ical/}calendar-color']:"#000000";
        $a['uri']=$c['uri'];
        $a['timezone']=isset($c['{urn:ietf:params:xml:ns:caldav}calendar-timezone'])?
            $c['{urn:ietf:params:xml:ns:caldav}calendar-timezone']:'';
        return $a;
    }

    /**
     * @param \DateTime|null $start
     * @param \DateTime $end
     * @param string|null $status
     * @return string
     */
    public static function makeDavReport($start,$end,$status){
        return '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop xmlns:D="DAV:"><C:calendar-data/></D:prop><C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:prop-filter name="CATEGORIES"><C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match></C:prop-filter></C:comp-filter>'.($status!==null?'<C:comp-filter name="VEVENT"><C:prop-filter name="STATUS"><C:text-match>'.$status.'</C:text-match></C:prop-filter></C:comp-filter>':'').'<C:comp-filter name="VEVENT"><C:prop-filter name="RRULE"><C:is-not-defined/></C:prop-filter></C:comp-filter><C:comp-filter name="VEVENT"><C:time-range '.($start!==null?('start="'.$start->format(self::TIME_FORMAT).'"' ):'').' end="'.$end->format(self::TIME_FORMAT).'"/></C:comp-filter></C:comp-filter></C:filter></C:calendar-query>';
    }

    /**
     * @param string $start
     * @param string $end
     * @param bool $cat_required
     * @return string
     */
    public static function makeTrDavReport($start, $end, $cat_required){
        return '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop xmlns:D="DAV:"><C:calendar-data/></D:prop><C:filter><C:comp-filter name="VCALENDAR">'.($cat_required?'<C:comp-filter name="VEVENT"><C:prop-filter name="CATEGORIES"><C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match></C:prop-filter></C:comp-filter>':'').'<C:comp-filter name="VEVENT"><C:time-range start="'.$start.'" end="'.$end.'"/></C:comp-filter></C:comp-filter></C:filter></C:calendar-query>';
    }


//    /**
//     * @param string $start
//     * @param string $end
//     * @return string
//     */
//    public static function makeTrBookedDavReport($start,$end){
//        return '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop xmlns:D="DAV:"><C:calendar-data/></D:prop><C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:prop-filter name="CATEGORIES"><C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match></C:prop-filter></C:comp-filter><C:comp-filter name="VEVENT"><C:prop-filter name="RRULE"><C:is-not-defined/></C:prop-filter></C:comp-filter><C:comp-filter name="VEVENT"><C:prop-filter name="ORGANIZER"/></C:comp-filter><C:comp-filter name="VEVENT"><C:prop-filter name="ATTENDEE"/></C:comp-filter><C:comp-filter name="VEVENT"><C:time-range start="'.$start.'" end="'.$end.'"/></C:comp-filter></C:comp-filter></C:filter></C:calendar-query>';
//    }

//<C:comp-filter name="VEVENT">
//<C:prop-filter name="STATUS">
//<C:text-match>CONFIRMED</C:text-match>
//</C:prop-filter>
//</C:comp-filter>
//<C:comp-filter name="VEVENT">
//<C:prop-filter name="TRANSP">
//<C:text-match>OPAQUE</C:text-match>
//</C:prop-filter>
//</C:comp-filter>

}