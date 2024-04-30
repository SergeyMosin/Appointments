<?php

namespace OCA\Appointments\Backend;

use OCA\BigBlueButton\Db\Restriction;
use OCA\BigBlueButton\Db\Room;
use OCA\BigBlueButton\Service\RestrictionService;
use OCA\BigBlueButton\Service\RoomService;
use OCA\BigBlueButton\UrlHelper;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Property;
use Sabre\VObject\Property\ICalendar\DateTime;

class BbbIntegration
{
    /**
     * TODO: Interface...
     * We need to have these public methods:
     *      canBBB
     *      createRoomForEvent
     *      renameRoom
     *      getRoomURL
     *      deleteRoom
     */

    private const BBB = 'bbb';

    private IAppManager $appManager;
    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private BackendUtils $utils;
    private IL10N $l10n;
    private LoggerInterface $logger;

    private Restriction|null $restriction = null;

    public function __construct(IAppManager     $appManager,
                                IUserSession    $userSession,
                                IGroupManager   $groupManager,
                                BackendUtils    $utils,
                                IL10N           $l10n,
                                LoggerInterface $logger)
    {
        $this->appManager = $appManager;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->utils = $utils;
        $this->l10n = $l10n;
        $this->logger = $logger;
    }


    public function createRoomForEvent(string            $attendeeName,
                                       DateTime|Property $dateTime,
                                       string            $userId,
                                       bool              $generatePassword = false): array
    {
        $ret = [
            'token' => '',
            'password' => '',
            'error' => 1, // assume failed
        ];

        if ($dateTime->isFloating() === true) {
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ": floating timezones are not supported");
            return $ret;
        }

        $rs = \OC::$server->get(RoomService::class);
        if (!$rs) {
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ":  BBB RoomService not available");
            return $ret;
        }

        $user = \OC::$server->get(IUserManager::class)->get($userId);
        if (!$user) {
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ":  invalid userId");
            return $ret;
        }

        $restriction = $this->getUserRestriction($user);

        $maxRooms = $restriction->getMaxRooms();
        $numberOfCreatedRooms = count($rs->findAll($userId, [], []));
        if ($maxRooms > -1 && $maxRooms >= $numberOfCreatedRooms) {
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ":  max rooms limit reached for user " . $userId);
            return $ret;
        }

        $dateTimeString = $this->formatDateTime($dateTime, $userId);
        /** @var Room $room */
        $room = $rs->create(
            $attendeeName . ' ' . $dateTimeString,
            $dateTimeString,
            $restriction->getMaxParticipants(),
            false,
            $generatePassword ? Room::ACCESS_PASSWORD : Room::ACCESS_WAITING_ROOM_ALL,
            $userId
        );

        $ret['error'] = 0;
        $ret['token'] = $room->getUid();
        $ret['password'] = $generatePassword ? $room->getPassword() : '';
        return $ret;
    }

    function renameRoom(string            $token,
                        string            $attendeeName,
                        DateTime|Property $dateTime,
                        string            $userId): bool
    {

        $room = $this->getRoomForUser($userId, $token, __FUNCTION__);
        if (!$room) {
            return false;
        }

        $dateTimeString = $this->formatDateTime($dateTime, $userId);
        $room->setName($attendeeName . ' ' . $dateTimeString);
        $room->setWelcome($dateTimeString);

        return true;
    }

    public function deleteRoom(string $token, string $userId): bool
    {
        $rs = \OC::$server->get(RoomService::class);
        if (!$rs) {
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ": BBB RoomService not available");
            return false;
        }

        /** @var Room $room */
        $room = $rs->findByUid($token);
        if (!$room) {
            return false;
        }

        if ($room->getUserId() !== $userId) {
            // something fishy is going on
            $this->logger->error("BbbIntegration: " . __FUNCTION__ . ": room user Id " . $room->getUserId() . " does not match requester userId " . $userId . " for room with token " . $token);
            return false;
        }

        $rs->delete($room->getId());

        return true;
    }

    public function getRoomUrl(string $token, string $userId): string|null
    {
        $urlHelper = \OC::$server->get(UrlHelper::class);
        if (!$urlHelper) {
            return null;
        }

        $room = $this->getRoomForUser($userId, $token, __FUNCTION__);
        if (!$room) {
            return null;
        }

        return $urlHelper->linkToInvitationAbsolute($room);
    }

    public function canBBB(): bool
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return false;
        }
        if (!$this->appManager->isEnabledForUser(self::BBB, $user)) {
            return false;
        }

        $restriction = $this->getUserRestriction($user);
        return $restriction->getMaxRooms() !== 0
            && $restriction->getMaxParticipants() !== 0;
    }

    public static function hasBBB(): bool
    {
        return \OC::$server->get(IAppManager::class)->isInstalled(self::BBB);
    }

    private function getRoomForUser(string $userId, string $token, string $fn): Room|null
    {
        $rs = \OC::$server->get(RoomService::class);
        if (!$rs) {
            $this->logger->error("BbbIntegration: " . $fn . ": BBB RoomService not available");
            return null;
        }

        /** @var Room $room */
        $room = $rs->findByUid($token);
        if (!$room) {
            $this->logger->error("BbbIntegration: " . $fn . ": can not find room with token" . $token);
            return null;
        }

        if ($room->getUserId() !== $userId) {
            // something fishy is going on
            $this->logger->error("BbbIntegration: " . $fn . ": room user Id " . $room->getUserId() . " does not match requester userId " . $userId . " for room with token " . $token);
            return null;
        }

        return $room;
    }


    private function formatDateTime(DateTime $dateTime, string $userId): string
    {
        $tz = $this->utils->getUserTimezone($userId);
        $_dt = new \DateTime('now', $tz);
        $tz_str = ' ' . $_dt->format('T');

        $_dt->setTimestamp($dateTime->getDateTime()->getTimestamp());

        return $this->l10n->l('date', $_dt, ['width' => 'short']) . ' '
            . str_replace([' AM', ' PM'], ['AM', 'PM'],
                $this->l10n->l('time', $_dt, ['width' => 'short']))
            . $tz_str;
    }

    private function getUserRestriction(IUser $user): Restriction
    {
        if (!$this->restriction) {
            $restrictionService = \OC::$server->get(RestrictionService::class);
            $this->restriction = $restrictionService->findByGroupIds($this->groupManager->getUserGroupIds($user));
        }
        return $this->restriction;
    }
}