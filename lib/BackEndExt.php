<?php
namespace OCA\Appointments;

use Doctrine\DBAL\Driver\Statement;
use OCA\DAV\CalDAV;
use OCA\DAV\Connector\Sabre\Principal;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Sabre\VObject\Reader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BackEndExt extends CalDAV\CalDavBackend{

    private $_db;
    private $_dispatcher;

    /**
     * BackEndExt constructor.
     * @param IDBConnection $db
     * @param Principal $principalBackend
     * @param IUserManager $userManager
     * @param IGroupManager $groupManager
     * @param ISecureRandom $random
     * @param ILogger $logger
     * @param EventDispatcherInterface $dispatcher
     * @param bool $legacyEndpoint
     */
    public function __construct(IDBConnection $db,
                                Principal $principalBackend,
                                IUserManager $userManager,
                                IGroupManager $groupManager,
                                ISecureRandom $random,
                                ILogger $logger,
                                EventDispatcherInterface $dispatcher,
                                bool $legacyEndpoint = false) {
        parent::__construct($db, $principalBackend,$userManager,$groupManager,
                                $random,$logger, $dispatcher, $legacyEndpoint = false);
        $this->_db = $db;
        $this->_dispatcher=$dispatcher;
    }


    public function queryWeek($cal_id,$t_start,$t_end): Statement {
        $columns = ['id', 'uri', 'calendardata','firstoccurence','lastoccurence'];

        $query = $this->_db->getQueryBuilder();
        $query->select($columns)
            ->from('calendarobjects')
            ->where($query->expr()->eq('calendarid', $query->createNamedParameter($cal_id)))
            ->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter("VEVENT")))
            ->andWhere($query->expr()->gt('firstoccurence', $query->createNamedParameter($t_start)))
            ->andWhere($query->expr()->lt('lastoccurence', $query->createNamedParameter($t_end)))
            ->orderBy('firstoccurence', 'ASC');
        $stmt = $query->execute();
        return $stmt;
    }


    /**
     * @param $user
     * @param string $uri
     * @return string|null
     */
    public function getCalendarIDByUri($user,$uri) {
        // Making fields a comma-delimited list
        $query = $this->_db->getQueryBuilder();
        $query->select(['id'])->from('calendars')
            ->where($query->expr()->eq('uri', $query->createNamedParameter($uri)))
            ->andWhere($query->expr()->eq('principaluri', $query->createNamedParameter('principals/users/'.$user)))
            ->setMaxResults(1);
        $stmt = $query->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row === false) {
            return null;
        }
        return $row['id'];
    }

    /**
     * @param $userId
     * @param $cal_url
     * @param $id
     * @param $name
     * @param $email
     * @param $phone
     * @return int|\DateTime // DateTime=OK, 1=UNAVAILABLE, 2=ERR
     * @throws \Exception
     */
    public function updateApptEntry($userId, $cal_url, $id,
                                    $name, $email, $phone) {

        $cal_id=$this->getCalendarIDByUri($userId,$cal_url);
        if($cal_id===null) return 2;

        $this->_db->beginTransaction();

        $query = $this->_db->getQueryBuilder();
        $query->select(['uri','calendardata','firstoccurence'])
            ->from('calendarobjects')
            ->where($query->expr()->eq('calendarid', $query->createNamedParameter($cal_id)))
            ->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter("VEVENT")))
            ->andWhere($query->expr()->eq('id', $query->createNamedParameter($id)))
            ->setMaxResults(1);

        $stmt = $query->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row === false) {
            $this->_db->commit();
            return 2;
        }

        $uri=$row['uri'];
        $vo=Reader::read($row['calendardata']);

        $evt=$vo->VEVENT;

        if(!isset($evt->STATUS) || $evt->STATUS->getValue()!=='TENTATIVE'){
            $this->_db->commit();
            return 1;
        }

        if(!isset($evt->CATEGORIES) || strtolower($evt->CATEGORIES->getValue())!=="appointment"){
            $this->_db->commit();
            return 2;
        }

        $a=$evt->add('ATTENDEE',"mailto:".$email);
        $a['CN']=$name;
        $a['PARTSTAT']="NEEDS-ACTION";

        if(!isset($evt->SUMMARY)) $evt->add('SUMMARY');
        $evt->SUMMARY->setValue("⌛ ".$name);

        if(!isset($evt->DESCRIPTION)) $evt->add('DESCRIPTION');
        $evt->DESCRIPTION->setValue($name."\n".$phone."\n".$email);

        if(!isset($evt->SEQUENCE)) $evt->add('SEQUENCE',1);
        else $evt->SEQUENCE->setValue($evt->SEQUENCE+1);

        if(!isset($evt->{'LAST-MODIFIED'})) $evt->add('LAST-MODIFIED');
        $evt->{'LAST-MODIFIED'}->setValue(new \DateTime());

        if(!isset($evt->STATUS)) $evt->add('STATUS');
        $evt->STATUS->setValue("CONFIRMED");

        $data=$vo->serialize();

        $this->updateCalendarObject($cal_id,$uri,$data);

        $this->_db->commit();

        // This is bad... but we still need to check
        $query = $this->_db->getQueryBuilder();
        $query->select(['calendardata'])
            ->from('calendarobjects')
            ->where($query->expr()->eq('calendarid', $query->createNamedParameter($cal_id)))
            ->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter("VEVENT")))
            ->andWhere($query->expr()->eq('uri', $query->createNamedParameter($uri)))
            ->setMaxResults(1);

        $stmt = $query->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row === false || $data!==$row['calendardata']) {
            return 2;
        }else{
            return $evt->DTSTART->getValue();
        }
    }


    /**
     * @param $userId
     * @param $cal_url
     * @param $id
     * @param $email
     * @param $cc_sts
     * @return array [DateTime|null,int] 0=OK/Status Changed, 1=Error: bad input, 2=Server Error, 3=OK/Status NOT Changed
     */
    public function updateApptStatus($userId, $cal_url, $id, $email, $cc_sts) {

        $cal_id=$this->getCalendarIDByUri($userId,$cal_url);
        if($cal_id===null) return [null,2];

        $this->_db->beginTransaction();

        $query = $this->_db->getQueryBuilder();
        $query->select(['uri','calendardata','firstoccurence'])
            ->from('calendarobjects')
            ->where($query->expr()->eq('calendarid', $query->createNamedParameter($cal_id)))
            ->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter("VEVENT")))
            ->andWhere($query->expr()->eq('id', $query->createNamedParameter($id)))
            ->setMaxResults(1);

        $stmt = $query->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row === false || (int)$row['firstoccurence']<time()-86400) {
            $this->_db->commit();
            return [null,2];
        }

        $uri=$row['uri'];
        $vo=Reader::read($row['calendardata']);

        $evt=$vo->VEVENT;

        if($evt->ATTENDEE->count()!==1){
            $this->_db->commit();
            return [null,1];
        }

        $a=$evt->ATTENDEE[0];

        if("mailto:".$email!==$a->getValue()){
            $this->_db->commit();
            return [null,1];
        }

        $sts_changed=3; // this is backwards
        if($cc_sts==='0'){
            if($a->parameters['PARTSTAT']->getValue()!=='DECLINED'){
                $sts_changed=0;
            }
            $a->parameters['PARTSTAT']->setValue('DECLINED');
            $evt->SUMMARY->setValue($a->parameters['CN']->getValue());
            $evt->STATUS->setValue("CANCELLED");
        }else{
            if($a->parameters['PARTSTAT']->getValue()!=='ACCEPTED'){
                $sts_changed=0;
            }
            $a->parameters['PARTSTAT']->setValue('ACCEPTED');
            $evt->SUMMARY->setValue("✔️ ".$a->parameters['CN']->getValue());
            $evt->STATUS->setValue("CONFIRMED");
        }

        $data=$vo->serialize();

        $this->updateCalendarObject($cal_id,$uri,$data);

        $this->_db->commit();

        // This is bad... but we still need to check
        $query = $this->_db->getQueryBuilder();
        $query->select(['calendardata'])
            ->from('calendarobjects')
            ->where($query->expr()->eq('calendarid', $query->createNamedParameter($cal_id)))
            ->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
            ->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter("VEVENT")))
            ->andWhere($query->expr()->eq('uri', $query->createNamedParameter($uri)))
            ->setMaxResults(1);

        $stmt = $query->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();


        if ($row === false || $data!==$row['calendardata']) {
            return [null,2];
        }else{
            return [$evt->DTSTART->getValue(),$sts_changed];
        }
    }













//    /**
//     * This is needed for first/last occurrence override
//     *
//     * @param mixed $calendarId
//     * @param string $objectUri
//     * @param string $calendarData
//     * @param string $uid
//     * @param int $first
//     * @param int $last
//     * @return string
//     * @throws \Sabre\DAV\Exception\BadRequest
//     */
//    function createCalendarObjectCustom($calendarId, $objectUri, $calendarData, $uid, $first, $last) {
//        $calendarType=self::CALENDAR_TYPE_CALENDAR;
////        $extraData = $this->getDenormalizedData($calendarData);
//        $extraData=[
//            'etag' => md5($calendarData),
//			'size' => strlen($calendarData),
//			'componentType' => "VEVENT",
//			'firstOccurence' => $first,
//			'lastOccurence'  => $last,
//			'uid' => $uid,
//			'classification' => self::CLASSIFICATION_PUBLIC
//        ];
//
//        $q = $this->_db->getQueryBuilder();
//        $q->select($q->func()->count('*'))
//            ->from('calendarobjects')
//            ->where($q->expr()->eq('calendarid', $q->createNamedParameter($calendarId)))
//            ->andWhere($q->expr()->eq('uid', $q->createNamedParameter($extraData['uid'])))
//            ->andWhere($q->expr()->eq('calendartype', $q->createNamedParameter($calendarType)));
//
//        $result = $q->execute();
//        $count = (int) $result->fetchColumn();
//        $result->closeCursor();
//
//        if ($count !== 0) {
//            throw new \Sabre\DAV\Exception\BadRequest('Calendar object with uid already exists in this calendar collection.');
//        }
//
//        $query = $this->_db->getQueryBuilder();
//        $query->insert('calendarobjects')
//            ->values([
//                'calendarid' => $query->createNamedParameter($calendarId),
//                'uri' => $query->createNamedParameter($objectUri),
//                'calendardata' => $query->createNamedParameter($calendarData, IQueryBuilder::PARAM_LOB),
//                'lastmodified' => $query->createNamedParameter(time()),
//                'etag' => $query->createNamedParameter($extraData['etag']),
//                'size' => $query->createNamedParameter($extraData['size']),
//                'componenttype' => $query->createNamedParameter($extraData['componentType']),
//                'firstoccurence' => $query->createNamedParameter($extraData['firstOccurence']),
//                'lastoccurence' => $query->createNamedParameter($extraData['lastOccurence']),
//                'classification' => $query->createNamedParameter($extraData['classification']),
//                'uid' => $query->createNamedParameter($extraData['uid']),
//                'calendartype' => $query->createNamedParameter($calendarType),
//            ])
//            ->execute();
//
//        $this->updateProperties($calendarId, $objectUri, $calendarData, $calendarType);
//
////        if ($calendarType === self::CALENDAR_TYPE_CALENDAR) {
//            $this->_dispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject', new GenericEvent(
//                '\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject',
//                [
//                    'calendarId' => $calendarId,
//                    'calendarData' => $this->getCalendarById($calendarId),
//                    'shares' => $this->getShares($calendarId),
//                    'objectData' => $this->getCalendarObject($calendarId, $objectUri),
//                ]
//            ));
////        } else {
////            $this->_dispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createCachedCalendarObject', new GenericEvent(
////                '\OCA\DAV\CalDAV\CalDavBackend::createCachedCalendarObject',
////                [
////                    'subscriptionId' => $calendarId,
////                    'calendarData' => $this->getCalendarById($calendarId),
////                    'shares' => $this->getShares($calendarId),
////                    'objectData' => $this->getCalendarObject($calendarId, $objectUri),
////                ]
////            ));
////        }
//        $this->addChange($calendarId, $objectUri, 1, $calendarType);
//
//        return '"' . $extraData['etag'] . '"';
//    }



}