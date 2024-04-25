<?php

namespace OCA\Appointments\Backend;

interface IBackendConnector
{


    /**
     * @param string[] $calIds
     * @param \DateTime $end
     * @param bool $only_empty
     * @param bool $delete if false just count
     * @return mixed
     */
    function queryRangePast($calIds, $end, $only_empty, $delete);

    /**
     * @param string $calId
     * @param \DateTime $start should have user's timezone
     * @param \DateTime $end should have user's timezone
     * @param string $mode 1char(mode)+userId or 'no_url'
     * @param string|null $pageId
     * @return string|null
     */
    function queryRange($calId, $start, $end, $mode, $pageId);

    /**
     * @param array $cms
     * @param \DateTime $start should have user's timezone
     * @param \DateTime $end should have user's timezone
     * @param string $userId
     * @param string $pageId
     * @return string|null
     */
    function queryTemplate($cms, $start, $end, $userId, $pageId);

    /**
     * @param string $calId
     * @param string $uri
     * @param string $data
     * @return bool
     */
    function updateObject($calId, $uri, $data);

    /**
     * @param string $calId
     * @param string $uri
     * @param string $data
     * @return bool
     */
    function createObject($calId, $uri, $data);

//    /**
//     * @param string $calId
//     * @param string $userId
//     * @return bool
//     */
//    function verifyCalId($calId,$userId);

    /**
     * @param $userId
     * @param bool $skipReadOnly
     * @return array[[
     *          'id'=>string,
     *          'color'=>string,
     *          'displayName'=>string,
     *          'uri'=>string,
     *          'timezone'=>string,
     *          'isReadOnly'=>string]]
     */
    function getCalendarsForUser($userId, $skipReadOnly = true);

    /**
     * @param string $userId
     * @return array[[
     *          'id'=>string,
     *          'displayName'=>string]
     */
    function getSubscriptionsForUser($userId);

    /**
     * @param string $calId
     * @param string $userId
     * @return array|null [
     *          'id'=>string,
     *          'color'=>string,
     *          'displayName'=>string,
     *          'uri'=>string,
     *          'timezone'=string]|null
     */
    function getCalendarById($calId, $userId);

    function getRawCalData(array $calInfo, string $userId);

    /**
     * @param $calId
     * @param $uri
     * @return string|null
     */
    function getObjectData($calId, $uri);

    /**
     * This function should be have some kind of 'locking' to avoid two people booking the same appointments. return int 0=OK, 1=User Should Try again (no lock, or time slot has been booked), (err>1)=Other error
     *
     * @param $userId
     * @param $calId
     * @param $uri
     * @param array $info ['name'=>string,'email'=>string,'phone'=>string]
     * @return int 0=OK,
     *             1=User Should Try again (no Lock, or time has been booked)
     *             err>1=Other error
     */
    function setAttendee($userId, $calId, $uri, $info);

    /**
     * Returns array [int,string|null]
     *
     * @param $userId
     * @param $pageId
     * @param $calId
     * @param $uri
     * @return array [int, string|null, string]
     *                  Status: 0=OK,1=Error,
     *                  Localized DateTime string or null,
     *                  attendee name: string
     */
    function confirmAttendee($userId, $pageId, $calId, $uri);

    /**
     * Returns array [int,string|null]
     *      Status: 0=OK,1=Error, Localized DateTime string or null.
     *
     * @param $userId
     * @param $pageId
     * @param $calId
     * @param $uri
     * @return array
     */
    function cancelAttendee($userId, $pageId, $calId, $uri);

    /**
     * Returns array [int, string, string|null, string, string]
     *              Status: 0=OK,1=Error
     *              Localized DateTime string, can be empt see PageController:addAppointments
     *              $ds param, can be empty
     *              $tz_data for new appointment can be one of: VTIMEZONE data, 'L' = floating or 'UTC'
     *              $title might be needed when the appointment is reset
     *
     * @param $userId
     * @param $calId
     * @param $uri
     * @return array [int, string|null, string|null, string]
     */
    function deleteCalendarObject($userId, $calId, $uri);

    /**
     * @return bool
     */
    static function checkCompatibility();

}


