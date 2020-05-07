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
            $result = $parser->parse($this->makeDavReport($start,$end));
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
            // TODO: $c['id'] can be a string or an int
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
        return $this->confirmCancel($calId,$uri,true);
    }

    /**
     * @inheritDoc
     */
    function cancelAttendee($userId, $calId, $uri){
        return $this->confirmCancel($calId,$uri,false);
    }

    private function confirmCancel($calId, $uri, $do_confirm){
        $ret=[1,null];
        $err='';
        $d=$this->getObjectData($calId,$uri);
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
                if($this->updateObject($calId,$uri,$newData)===false){
                    $err="Can not update object: ".$uri;
                }else{
                    // Object Update: SUCCESS
                    $ret=[0,$date];
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
     * @param \DateTime $start
     * @param \DateTime $end
     * @return string
     */
    public static function makeDavReport($start,$end){
        return '<C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop xmlns:D="DAV:"><C:calendar-data/></D:prop><C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT"><C:time-range start="'.$start->format(self::TIME_FORMAT).'" end="'.$end->format(self::TIME_FORMAT).'"/></C:comp-filter><C:comp-filter name="VEVENT"><C:prop-filter name="CATEGORIES"><C:text-match>'.BackendUtils::APPT_CAT.'</C:text-match></C:prop-filter><C:prop-filter name="STATUS"><C:text-match>TENTATIVE</C:text-match></C:prop-filter><C:prop-filter name="RRULE"><C:is-not-defined/></C:prop-filter></C:comp-filter></C:comp-filter></C:filter></C:calendar-query>';
    }

}