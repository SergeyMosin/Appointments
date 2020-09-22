<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use OCA\Talk\Participant;
use OCA\Talk\Webinary;

class TalkIntegration{

    private $appName=Application::APP_ID;
    private $tlk;
    private $utils;
    private $config;
    private static $tmClass=\OCA\Talk\Manager::class;

    /**
     * @param array $tlk
     * @param BackendUtils $utils
     */
    public function __construct($tlk,$utils)
    {
        $this->utils = $utils;
        $this->tlk = $tlk;
        $this->config = \OC::$server->getConfig();
    }


    /** @return \OCA\Talk\Manager|null */
    private function getTalkManager(){
        try {
            /** @type \OCA\Talk\Manager $tm */
            $tm = \OC::$server->getRegisteredAppContainer($this->appName)->query(self::$tmClass);
            return $tm;
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error("Talk Manager not found");
            \OC::$server->getLogger()->error($e);
            return null;
        }
    }

    /**
     * @param string $attendeeName
     * @param \Sabre\VObject\Property\ICalendar\DateTime | \Sabre\VObject\Property $dateTime
     * @param string $userId
     * @return string room token[chr(31)password], "" = error, "-" = error (should inform user via description)
     */
    function createRoomForEvent($attendeeName,$dateTime,$userId){
        if($dateTime->isFloating()===true){
            \OC::$server->getLogger()->error("Talk room error: TalkIntegration - floating timezones are not supported");
            return "-";
        }

        $tm=$this->getTalkManager();
        $roomToken="";

        if($tm!==null){
            $roomName=$this->formatRoomName($attendeeName,$dateTime,$userId);
            try {
                $room = $tm->createPublicRoom($roomName);
                $room->addUsers([
                    'userId' => $userId,
                    'participantType' => Participant::OWNER,
                ]);
                $roomToken=$room->getToken();
            }catch (\Exception $e){
                \OC::$server->getLogger()->error("TalkIntegration: can not create public room");
                \OC::$server->getLogger()->error($e);
            }

            $n="getUs"."erValue";$hd='he'."xdec";
            $c=$this->config->$n($userId, $this->appName, 'c'."nk");
            $sss="su".'bstr';
            if(!empty($roomToken) && $c!=='' && (($hd($sss($c,0,0b100))>>14)& 1)===(($hd($sss($c,4,4))>>  6) & 1) && isset($c[5])){
                if($this->tlk[BackendUtils::TALK_LOBBY]===true){
                    $this->setLobby($room,null);
                }else if($this->tlk[BackendUtils::TALK_PASSWORD]===true){
                    $p=$this->setPassword($room);
                    if($p==='-'){
                        // error
                        $roomToken="-";
                    }else{
                        // ok
                        $roomToken.=chr(31).$p;
                    }
                }
            }
        }
        return $roomToken;
    }

    /**
     * @param \OCA\Talk\Room $room
     * @param \DateTime $dateTime
     */
    function setLobby($room,$dateTime){
        // $room->setLobby wants utc timezone ?!?
        // @see OCA\Talk\Controller\WebinarController->setLobby
//        $_dt=new \DateTime(null,new \DateTimeZone('UTC'));
//        $_dt->setTimestamp($dateTime->getTimestamp());
        // Lets not do timer for now..., davListener needs update if this is implemented

        $room->setLobby(Webinary::LOBBY_NON_MODERATORS,null);
    }

    /**
     * @param \OCA\Talk\Room $room
     * @return string
     */
    function setPassword($room){
        $p=substr(str_replace(['+', '/', '='], '', base64_encode(md5(rand(), true))),0,9);

        if($room->setPassword($p)===true){
            // So that davListener can attach pass to the email
            return $p;
        }else{
            \OC::$server->getLogger()->error("Talk room error: TalkIntegration - can not set password");
            return '-';
        }
    }

    /**
     * @param $token
     * @param string $guestName
     * @param \Sabre\VObject\Property\ICalendar\DateTime | \Sabre\VObject\Property $dateTime
     * @param string $userId
     * @param string $pref optional prefix
     * @return bool
     */
    function renameRoom($token,$guestName,$dateTime,$userId,$pref=""){
        $tm=$this->getTalkManager();
        $r=false;
        if($tm!==null){
            if(!empty($pref)) $pref=trim($pref).' ';
            try {
                $room = $tm->getRoomByToken($token);
                $r=$room->setName($pref.
                    $this->formatRoomName($guestName,$dateTime,$userId)
                );
            }catch (\Exception $e){
                \OC::$server->getLogger()->error("Room not found, token: ".$token);
                \OC::$server->getLogger()->error($e);
            }
        }
        return $r;
    }

    /**
     * @param string $guestName
     * @param \Sabre\VObject\Property\ICalendar\DateTime $dateTime
     * @param string $userId
     * @return string
     */
    private function formatRoomName($guestName,$dateTime,$userId){
        $f=$this->tlk[BackendUtils::TALK_NAME_FORMAT];
        if($this->config->getUserValue($userId, $this->appName, 'cnk')==='') $f=0;
        if($f<2){
            $dt=$this->formatDateTime($dateTime,$userId);
            if($f===0){
                $r=$guestName.' '.$dt;
            }else{
                $r=$dt.' '.$guestName;
            }
        }else{
            $r=$guestName;
        }
        return $r;
    }

    /**
     * @param string $roomToken
     * @return string
     */
    function getRoomURL($roomToken){
        return \OC::$server->getURLGenerator()->getAbsoluteURL("index.php/call/".$roomToken);
    }

    /**
     * @param \Sabre\VObject\Property\ICalendar\DateTime $dateTime
     * @param string $userId
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function formatDateTime($dateTime,$userId){
        if($dateTime->isFloating()){
            return "";
        }else{
            // convert from DateTimeImmutable + set user's timezone
            $tz=$this->utils->getUserTimezone($userId,$this->config);
            /** @noinspection PhpUnhandledExceptionInspection */
            $_dt = new \DateTime('now',$tz);
            $tz_str=' '.$_dt->format('T');
        }
        $_dt->setTimestamp($dateTime->getDateTime()->getTimestamp());

        $l10N=\OC::$server->getL10N($this->appName);
        return $l10N->l('date', $_dt, ['width' => 'short']).' '
            .str_replace([' AM',' PM'],['AM','PM'],$l10N->l('time', $_dt, ['width' => 'short'])).$tz_str;
    }

    /**
     * @param string $token
     */
    function deleteRoom($token){
        $tm=$this->getTalkManager();
        if($tm!==null) {
            try {
                $room = $tm->getRoomByToken($token);
                $room->deleteRoom();
            }catch (\Exception $e){
                \OC::$server->getLogger()->error("Room not found, token: ".$token);
                \OC::$server->getLogger()->error($e);
            }
        }
    }

    static public function canTalk(){
        try {
            /** @type \OCA\Talk\Manager $tm */
            $tm = \OC::$server->getRegisteredAppContainer(Application::APP_ID)->query(self::$tmClass);
            $r=true;
            if(!method_exists($tm,'createPublicRoom')){
                $r=false;
            }elseif(!method_exists($tm,'getRoomByToken')){
                $r=false;
            }
            return $r;
        } catch (\Exception $e) {
            return false;
        }
    }
}