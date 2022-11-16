<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;
/**
 * TODO: Interface...
 *
 * In NC25 things have been changed and roomService is used for most operations,
 * We still need to have these public methods working regardless NC version:
 *      createRoomForEvent
 *      setLobby
 *      setPassword
 *      renameRoom
 *      getRoomURL
 *      deleteRoom
 */

use OCA\Appointments\AppInfo\Application;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Webinary;
use OCA\Talk\Room;
use OCP\HintException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class TalkIntegration
{
    private string $appName = Application::APP_ID;
    private array $tlk;
    private BackendUtils $utils;
    private \OCP\IConfig $config;

    /**
     * @param array $tlk
     * @param BackendUtils $utils
     */
    public function __construct($tlk, $utils) {
        $this->utils = $utils;
        $this->tlk = $tlk;
        $this->config = \OC::$server->get(\OCP\IConfig::class);
    }


    /** @return \OCA\Talk\Manager|null */
    private function getTalkManager() {
        try {
            /** @type \OCA\Talk\Manager $tm */
            $tm = \OC::$server->get(\OCA\Talk\Manager::class);
            return $tm;
        } catch (\Exception $e) {
            $this->logError("Talk Manager not found");
            $this->logError($e);
            return null;
        }
    }

    /**
     * @param string $attendeeName
     * @param \Sabre\VObject\Property\ICalendar\DateTime | \Sabre\VObject\Property $dateTime
     * @param string $userId
     * @return string room token[chr(31)password], "" = error, "-" = error (should inform user via description)
     */
    function createRoomForEvent(string $attendeeName, $dateTime, string $userId): string {
        if ($dateTime->isFloating() === true) {
            $this->logError("Talk room error: TalkIntegration - floating timezones are not supported");
            return "-";
        }

        $roomName = $this->formatRoomName($attendeeName, $dateTime, $userId);
        $room = null;

        # TODO: refactor after NC24 eol
        $nc25roomService = null;

        try {
            /** @type \OCA\Talk\Service\RoomService $rs */
            $rs = \OC::$server->get(\OCA\Talk\Service\RoomService::class);
            if (method_exists($rs, 'setLobby') && method_exists($rs, 'setPassword')) {
                // we are in NC25+
                $nc25roomService = $rs;
            }
        } catch (\Exception $e) {
            $this->logError($e);
            $rs = null;
        }

        if ($rs !== null) {
            try {
                /**
                 * @type IUserManager $um
                 */
                $um = \OC::$server->get(IUserManager::class);
                $user = $um->get($userId);
                if ($user !== null) {
                    $room = $rs->createConversation(Room::TYPE_PUBLIC, $roomName, $user);
                }
            } catch (\Throwable $e) {
                $this->logError($e);
            }
        }

        if ($room === null) {
            $this->logError("TalkIntegration: can not create public room");
            return '-';
        }

        $roomToken = $room->getToken();

        $n = "getUs" . "erValue";
        $hd = 'he' . "xdec";
        $c = $this->config->$n($userId, $this->appName, 'c' . "nk");
        $sss = "su" . 'bstr';
        if (!empty($roomToken) && $c !== '' && (($hd($sss($c, 0, 0b100)) >> 14) & 1) === (($hd($sss($c, 4, 4)) >> 6) & 1) && isset($c[5])) {
            if ($this->tlk[BackendUtils::TALK_LOBBY] === true) {
                $this->setLobby($room, $nc25roomService, null);
            } else if ($this->tlk[BackendUtils::TALK_PASSWORD] === true) {
                $p = $this->setPassword($room, $nc25roomService);
                if ($p === '-') {
                    // error
                    $roomToken = "-";
                } else {
                    // ok
                    $roomToken .= chr(31) . $p;
                }
            }
        }

        return $roomToken;
    }

    /**
     *  NC24 wants room
     *  NC25 wants both room and roomService
     *
     * @param Room $room
     * @param RoomService|null $roomService - non null for NC25+
     * @param \DateTime|null $dateTime
     */
    private function setLobby(Room $room, ?RoomService $roomService, ?\DateTime $dateTime) {
        // $room->setLobby wants utc timezone ?!?
        // @see OCA\Talk\Controller\WebinarController->setLobby
//        $_dt=new \DateTime(null,new \DateTimeZone('UTC'));
//        $_dt->setTimestamp($dateTime->getTimestamp());
        // Lets not do timer for now..., davListener needs update if this is implemented

        if ($roomService !== null) {
            // NC25+
            $roomService->setLobby($room, Webinary::LOBBY_NON_MODERATORS, null);
        } else {
            //NC24
            $room->setLobby(Webinary::LOBBY_NON_MODERATORS, null);
        }
    }

    /**
     * NC24 just wants room
     * NC25 wants both room and roomService
     *
     *
     * @param Room $room
     * @param RoomService|null $roomService - non null for NC25+
     * @return string
     */
    private function setPassword(Room $room, ?RoomService $roomService): string {
        $p = substr(str_replace(['+', '/', '='], '', base64_encode(md5(rand(), true))), 0, 11);

        $status = false;
        if ($roomService !== null) {
            // NC25+
            try {
                $status = $roomService->setPassword($room, $p);
            } catch (HintException $e) {
                $this->logError("error: roomService->setPassword failed: ".$e->getMessage());
            }
        } else {
            // NC24
            $status = $room->setPassword($p);
        }

        if ($status === true) {
            // So that davListener can attach pass to the email
            return $p;
        } else {
            $this->logError("Talk room error: TalkIntegration - can not set password");
            return '-';
        }
    }

    /**
     * @param string $token
     * @param string $guestName
     * @param \Sabre\VObject\Property\ICalendar\DateTime | \Sabre\VObject\Property $dateTime
     * @param string $userId
     * @param string $pref optional prefix
     * @return bool
     */
    function renameRoom(string $token, string $guestName, $dateTime, string $userId, string $pref = ""): bool {
        $tm = $this->getTalkManager();
        $r = false;
        if ($tm !== null) {
            if (!empty($pref)) $pref = trim($pref) . ' ';
            try {
                $room = $tm->getRoomByToken($token);
                $r = $room->setName($pref .
                    $this->formatRoomName($guestName, $dateTime, $userId)
                );
            } catch (\Exception $e) {
                $this->logError("Room not found, token: " . $token);
                $this->logError($e);
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
    private function formatRoomName(string $guestName, \Sabre\VObject\Property\ICalendar\DateTime $dateTime, string $userId): string {
        $f = $this->tlk[BackendUtils::TALK_NAME_FORMAT];
        if ($this->config->getUserValue($userId, $this->appName, 'cnk') === '') $f = 0;
        if ($f < 2) {
            $dt = $this->formatDateTime($dateTime, $userId);
            if ($f === 0) {
                $r = $guestName . ' ' . $dt;
            } else {
                $r = $dt . ' ' . $guestName;
            }
        } else {
            $r = $guestName;
        }
        return $r;
    }

    /**
     * @param string $roomToken
     * @return string
     */
    function getRoomURL(string $roomToken): string {
        /** @type IURLGenerator $ug */
        $ug = \OC::$server->get(IURLGenerator::class);
        return $ug->getAbsoluteURL("index.php/call/" . $roomToken);
    }

    /**
     * @param \Sabre\VObject\Property\ICalendar\DateTime $dateTime
     * @param string $userId
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function formatDateTime(\Sabre\VObject\Property\ICalendar\DateTime $dateTime, string $userId): string {
        if ($dateTime->isFloating()) {
            $this->logError("Talk room error: TalkIntegration - floating timezones are not supported");
            return "";
        } else {
            // convert from DateTimeImmutable + set user's timezone
            $tz = $this->utils->getUserTimezone($userId, $this->config);
            /** @noinspection PhpUnhandledExceptionInspection */
            $_dt = new \DateTime('now', $tz);
            $tz_str = ' ' . $_dt->format('T');
        }
        $_dt->setTimestamp($dateTime->getDateTime()->getTimestamp());

        /** @type \OCP\IL10N $l10N */
        $l10N = \OC::$server->get(IFactory::class)->get($this->appName, null);
        return $l10N->l('date', $_dt, ['width' => 'short']) . ' '
            . str_replace([' AM', ' PM'], ['AM', 'PM'], $l10N->l('time', $_dt, ['width' => 'short'])) . $tz_str;
    }

    /**
     * @param string $token
     */
    function deleteRoom(string $token) {
        $tm = $this->getTalkManager();
        if ($tm !== null) {
            try {
                $room = $tm->getRoomByToken($token);
                $room->deleteRoom();
            } catch (\Exception $e) {
                $this->logError("deleteRoom: Room not found, token: " . $token);
                if (get_class($e) !== \OCA\Talk\Exceptions\RoomNotFoundException::class) {
                    $this->logError($e);
                }
            }
        }
    }

    static public function canTalk(): bool {
        try {
            /** @type \OCA\Talk\Manager $tm */
            $tm = \OC::$server->get(\OCA\Talk\Manager::class);
            $r = true;
            if (!method_exists($tm, 'getRoomByToken')) {
                $r = false;
            }
            return $r;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function logError(string $msg) {
        /** @var \Psr\Log\LoggerInterface $logger */
        $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
        $logger->error($msg);
    }
}