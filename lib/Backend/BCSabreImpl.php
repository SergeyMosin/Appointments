<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IConfig;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Xml\Service as XmlService;
use Sabre\Xml\ParseException;
use Sabre\VObject\Reader;

class BCSabreImpl implements IBackendConnector{

    const TIME_FORMAT="Ymd\THis\Z";

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
            $result = $parser->parse($this->makeDavReport(null,$end,$only_empty===true?"TENTATIVE":null));
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
     * @inheritDoc
     */
    function queryRange($calId, $start, $end,$no_uri=false){

        if($no_uri){
            $key=''; // @see BackendUtils->encodeCalendarData
        }else {
            $key = hex2bin($this->config->getAppValue($this->appName, 'hk'));
            if (empty($key)) {
                \OC::$server->getLogger()->error("Can't find hkey");
                return null;
            }
        }

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
            $result = $parser->parse($this->makeDavReport($start,$end,$no_uri===false?"TENTATIVE":null));
        } catch (ParseException $e) {
            \OC::$server->getLogger()->error($e);
            return null;
        }

        $urls=$this->backend->calendarQuery($calId,$result->filters);
        $objs=$this->backend->getMultipleCalendarObjects($calId,$urls);

        $ses_start=time().'|';
        $ret='';

        $offset=$start->getOffset();

        $use_my_float=false;
        foreach ($objs as $obj){

            $vo=Reader::read($obj['calendardata']);

            list($ts,$out) = $this->utils->encodeCalendarData(
                $vo,$ses_start.$obj['uri'],$key,$offset,$use_my_float);

            if($ts>$f_start && $ts<$f_end) {
                $ret.=$out;
            }
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
        $err='';
        $ec=1;
        $d=$this->getObjectData($calId,$uri);
        if($d===null){
            $err="Object does not exist: ".$uri;
            $ec=2;
        }else{
            $es=strpos($d,"BEGIN:VEVENT")+14;
            $us=strpos($d,"\r\nUID:",$es);
            if($us!==false){
                $us+=6;
                $uid=substr($d,$us,strpos($d,"\r\n",$us)-$us);

                $db=\OC::$server->getDatabaseConnection();
                // Ugly locking to avoid booking the same appointment twice...
                $db->lockTable(BackendUtils::HASH_TABLE_NAME);

                $hash=$this->utils->getApptHash($uid);
                if($hash===null){
                    // It is safe to take this time slot
                    $query = $db->getQueryBuilder();
                    $query->insert(BackendUtils::HASH_TABLE_NAME)
                        ->values([
                            'uid' => $query->createNamedParameter($uid),
                            'hash' => $query->createNamedParameter('00000000.0000000000000000000000')
                        ])->execute();
                    $db->unlockTable();

                    $newData=$this->utils->dataSetAttendee($d,$info,$userId);
                    if($newData==="1"){
                        // $ec=1;
                        $err="Bad appointment status, [select different time]";
                    }elseif($newData==="2"){
                        $err="Can not set attendee data";
                        $ec=3;
                    }else{
                        if($this->updateObject($calId,$uri,$newData)===false){
                            $err="Can not update object: ".$uri;
                            $ec=4;
                        }else{
                            // Object Update: SUCCESS
                            $ec=0;
                        }
                    }
                }else{
                    $db->unlockTable();
                    // $ec=1;
                    $err="Bad appointment status, [select different time]";
                }
            }else{
                $err="Bad object data for ".$uri;
                $ec=5;
            }
        }

        if($err!==''){
            \OC::$server->getLogger()->error($err);
        }

        return $ec;
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

        // check if we have destination calendar
        $cls = $this->utils->getUserSettings(
            BackendUtils::KEY_CLS, BackendUtils::CLS_DEF,
            $userId, $this->appName);
        $dcl_id = $cls[BackendUtils::CLS_DEST_ID];

        if ($dcl_id != "-1" && $this->getCalendarById($dcl_id, $userId) === null) {
            \OC::$server->getLogger()->error("WARNING: bad CLS_DEST_ID calendar with ID " . $dcl_id . " not found");
            $dcl_id = "-1";
        }

        //correct cal_id for cancellations should be "calculated" in the page controller
        $d=$this->getObjectData($calId,$uri);

        if($d===null && $do_confirm && $dcl_id!=="-1"){
            // check dest calendar
            $d=$this->getObjectData($dcl_id,$uri);
            // if d!==null then appointment is in the dest calendar and it is probably already confirmed, but we still need the date.
        }

        if($d===null){
            $err="Object does not exist: ".$uri;
        }else{
            if($do_confirm) {
                list($newData, $date) = $this->utils->dataConfirmAttendee($d);
            }else{
                list($newData, $date) = $this->utils->dataCancelAttendee($d);
            }
            if($newData===null){
                $err="Can not set attendee data";
            }elseif(empty($newData)){
                // Already confirmed
                $ret=[0,$date];
            }else{

                if($do_confirm && $dcl_id!="-1"){
                    // different destination calendar
                    // ONLY for confirmations (cal_id for cancellations should be "calculated" in the page controller)

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
        $ret=[0,'','','L'];
        $d=$this->getObjectData($calId,$uri);
        if($d!==null){
            $ra=$this->utils->dataDeleteAppt($d);
            $ret[1]=$ra[0];
            $ret[2]=$ra[1];
            $ret[3]=$ra[2];
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
        $a['id']=$c["id"];
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
//        return '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop xmlns:D="DAV:"><C:calendar-data/></D:prop><C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:time-range '.($start!==null?('start="'.$start->format(self::TIME_FORMAT).'"' ):'').' end="'.$end->format(self::TIME_FORMAT).'"/></C:comp-filter><C:comp-filter name="VEVENT"><C:prop-filter name="CATEGORIES"><C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match></C:prop-filter>'
//            .($status!==null?'<C:prop-filter name="STATUS"><C:text-match>'.$status.'</C:text-match></C:prop-filter>':'').
//            '<C:prop-filter name="RRULE"><C:is-not-defined/></C:prop-filter></C:comp-filter></C:comp-filter></C:filter></C:calendar-query>';

        return '
<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav">
    <D:prop xmlns:D="DAV:">
        <C:calendar-data/>
    </D:prop>
    <C:filter>
        <C:comp-filter name="VCALENDAR">
            <C:comp-filter name="VEVENT">
                <C:prop-filter name="CATEGORIES">
                    <C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match>
                </C:prop-filter>
            </C:comp-filter>'
                .($status!==null?'
            <C:comp-filter name="VEVENT">
                <C:prop-filter name="STATUS">
                    <C:text-match>'.$status.'</C:text-match>
                </C:prop-filter>
            </C:comp-filter>':'').
            '<C:comp-filter name="VEVENT">
                <C:prop-filter name="RRULE">
                    <C:is-not-defined/>
                </C:prop-filter>
            </C:comp-filter>
            <C:comp-filter name="VEVENT">    
                <C:time-range '.($start!==null?('start="'.$start->format(self::TIME_FORMAT).'"' ):'').' end="'.$end->format(self::TIME_FORMAT).'"/>
            </C:comp-filter>    
        </C:comp-filter>
    </C:filter>
</C:calendar-query>';
    }

}