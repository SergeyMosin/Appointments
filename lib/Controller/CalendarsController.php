<?php /** @noinspection PhpUnused */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */


namespace OCA\Appointments\Controller;


use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IRequest;

class CalendarsController extends Controller
{

    private $userId;
    private $config;
    private $utils;
    /** @var \OCA\Appointments\Backend\IBackendConnector $bc */
    private $bc;

    public function __construct($AppName,
                                IRequest $request,
        $UserId,
                                IConfig $config,
                                BackendUtils $utils,
                                BackendManager $backendManager) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->config = $config;
        $this->utils = $utils;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
    }


    /**
     * @param string $t JSON string {
     *      "type": "empty|both" ,
     *      "before": 1|7,
     *      ["delete":boolean]
     * }
     * @param string $pageId
     * @return SendDataResponse
     */
    function calGetOld($t, $pageId) {

        $r = new SendDataResponse();

        $jo = json_decode($t);
        if ($jo === null) {
            $r->setStatus(400);
            return $r;
        }

        // Because of floating timezones...
        $utz = $this->utils->getUserTimezone($this->userId);
        try {
            if ($jo->before === 1) {
                $rs = 'yesterday';
            } else {
                $rs = 'today -7 days';
            }
            $end = new \DateTime($rs, $utz);

        } catch (\Exception $e) {
            \OC::$server->getLogger()->error($e->getMessage() . ", timezone: " . $utz->getName());
            $r->setStatus(400);
            return $r;
        }

        $cals = [];

        $dst_cal_id = "-1";
        $main_cal_id = $this->utils->getMainCalId($this->userId, $this->bc, $dst_cal_id);

        if ($main_cal_id !== "-1") {
            $cals[] = $main_cal_id;
        }
        // dest calendar
        if ($jo->type === "both" && $dst_cal_id !== "-1") {
            $cals[] = $dst_cal_id;
        }

        $ots = $end->getTimestamp();

        $out = $this->bc->queryRangePast($cals, $end, $jo->type === 'empty', isset($jo->delete));

        $r = new SendDataResponse();
        if ($out !== null) {
            $r->setData($out . "|" . $ots);
            $r->setStatus(200);
        } else {
            $r->setStatus(500);
        }

        return $r;
    }


    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function calgetweek() {
        $pageId = $this->request->getParam("p", "p0");
        if (empty($pageId)) $pageId = "p0";

        $settings = $this->utils->getUserSettings();

        if ($settings[BackendUtils::CLS_TS_MODE] !== BackendUtils::CLS_TS_MODE_SIMPLE) {
            $r = new SendDataResponse();
            $r->setStatus(400);
            return $r;
        }

        // t must be d[d]-mm-yyyy
        $t = $this->request->getParam("t", "");

        //Reusing the url for deleting old appointments
        if (strpos($t, "before") !== false) {
            return $this->calGetOld($t, $pageId);
        }

        $r = new SendDataResponse();

        if (empty($t)) {
            $r->setStatus(400);
            return $r;
        }

        $dcl_id = '-1';
        $cal_id = $this->utils->getMainCalId($this->userId, $this->bc, $dcl_id);
        if ($cal_id === "-1") {
            $r->setStatus(400);
            return $r;
        }

        $utz = $this->utils->getCalendarTimezone($this->userId, $this->bc->getCalendarById($cal_id, $this->userId));
        try {
            $t_start = \DateTime::createFromFormat(
                'j-m-Y H:i:s', $t . ' 00:00:00', $utz);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error($e->getMessage() . ", timezone: " . $utz->getName());
            $r->setStatus(400);
            return $r;
        }

        $r->setStatus(200);

        $t_end = clone $t_start;
        $t_end->setTimestamp($t_start->getTimestamp() + (7 * 86400));

        $data_out = "";

        $out = $this->bc->queryRange($cal_id, $t_start, $t_end, 'no_url');
        if ($out !== null) {
            $data_out .= $out;
        }

        // check dest calendar
        if ($dcl_id !== "-1") {
            $dc = $this->bc->getCalendarById($dcl_id, $this->userId);
            $out = $this->bc->queryRange($dcl_id, $t_start, $t_end, 'no_url');
            if ($out !== null) {
                $data_out .= chr(31) . $dc['color'] . chr(30) . $out;
            }
        }

        if (!empty($data_out)) {
            $r->setData($data_out);
        }

        return $r;
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function callist() {
        $isTemplateMode = $this->request->getParam("mode", "") === BackendUtils::CLS_TS_MODE_TEMPLATE;
        $cals = $this->bc->getCalendarsForUser($this->userId, !$isTemplateMode);
        $out = '';
        $c30 = chr(30);
        $c31 = chr(31);
        foreach ($cals as $c) {
            $out .=
                $c['displayName'] . $c30 .
                $c['color'] . $c30 .
                $c['id'] . $c30 .
                $c['isReadOnly'] . $c30 .
                '0' . $c31; // isSubscription;
        }
        if($isTemplateMode) {
            // Subscriptions are only for template mode
            $sa = $this->bc->getSubscriptionsForUser($this->userId);
            foreach ($sa as $s) {
                $out .=
                    $s['displayName'] . $c30 .
                    '#000000' . $c30 .
                    $s['id'] . $c30 .
                    '1' . $c30 . // isReadOnly
                    '1' . $c31; // isSubscription
            }
        }

        return substr($out, 0, -1);
    }

}