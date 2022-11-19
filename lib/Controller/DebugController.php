<?php


namespace OCA\Appointments\Controller;

use OC_Util;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;

class DebugController extends Controller
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
     * @NoAdminRequired
     */
    function settingsDump() {

        $keys = [
            BackendUtils::KEY_ORG,
            BackendUtils::KEY_EML,
            BackendUtils::KEY_CLS,
            BackendUtils::KEY_PSN,
            BackendUtils::KEY_MPS_COL,
            BackendUtils::KEY_PAGES,
            BackendUtils::KEY_DIR,
            BackendUtils::KEY_REMINDERS,
            BackendUtils::KEY_TALK,
            BackendUtils::KEY_FORM_INPUTS_JSON,
            BackendUtils::KEY_FORM_INPUTS_HTML,
            BackendUtils::KEY_USE_DEF_EMAIL,
            BackendUtils::KEY_EMAIL_FIX,
            BackendUtils::KEY_TMPL_INFO,
            BackendUtils::KEY_TMPL_DATA,
        ];

        $data = '<strong>Nextcloud Version</strong>: ' . OC_Util::getVersionString() . "\n"
            . '<strong>Appointments Version</strong>: ' . $this->config->getAppValue($this->appName, 'installed_version', "N/A") . "\n"
            . '<strong>Time zone</strong>: ' . $this->utils->getUserTimezone($this->userId, $this->config)->getName() . " ("
            . "calendar: " . $this->config->getUserValue($this->userId, 'calendar', 'timezone', "N/A") . ", "
            . "core: " . $this->config->getUserValue($this->userId, 'core', 'timezone', "N/A") . ")\n"
            . '<strong>Key</strong>: ' . ($this->config->getUserValue($this->userId, $this->appName, "cnk") !== "" ? "Yes" : "No") . "\n\n";

        foreach ($keys as $k) {
            $data .= '<strong>' . $k . '</strong>: ' . var_export($this->utils->getUserSettings(
                    $k, $this->userId), true);
            $data .= "\n\n";
        }

        $data .= "<strong>ExtNotify:</strong> " . ($this->config->getAppValue($this->appName, 'ext_notify_' . $this->userId) !== "" ? "Yes" : "No") . "\n\n";

        $tr = new TemplateResponse($this->appName, 'settings_dump', [], "base");
        $params['data'] = $data;
        $tr->setParams($params);
        return $tr;
    }

    /**
     * @NoAdminRequired
     */
    function getRawCalendarData() {
        $data = "";
        $status = 400;

        $calInfoStr = $this->request->getParam("cal_info");
        if ($calInfoStr !== null) {
            $calInfo = json_decode($calInfoStr, true);
            if ($calInfo !== null && isset($calInfo["id"]) && isset($calInfo["isSubscription"])) {

                $calData = var_export($calInfo, true) . '<br>';

                $d = $this->bc->getRawCalData($calInfo, $this->userId);

                $data = $calData . '<br>' . var_export($d, true);
                $status = 200;
            }
        }

        $tr = new TemplateResponse($this->appName, 'settings_dump', [], "base");
        $tr->setParams(['data' => $data]);
        $tr->setStatus($status);
        return $tr;

    }

    /**
     * @NoAdminRequired
     */
    function syncRemoteNow() {

        $data = "";
        $status = 400;

        $calInfoStr = $this->request->getParam("cal_info");
        if ($calInfoStr !== null) {
            $calInfo = json_decode($calInfoStr, true);
            if ($calInfo !== null &&
                isset($calInfo["id"]) &&
                isset($calInfo["isSubscription"]) &&
                $calInfo["isSubscription"] === '1') {


                $syncInterval = intval($this->utils->getUserSettings(BackendUtils::KEY_CLS, $this->userId)[BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC]);

                if ($syncInterval < 60) {
                    $data = "Appointments App sync is disabled.\nSee 'Settings > Advanced Settings > Weekly Template Settings > Subscriptions Sync Interval'";
                } else {

                    $a = [
                        "name" => $calInfo["name"],
                        "syncStart" => microtime(true)
                    ];

                    $calInfo['syncRemoteNow_call'] = true;
                    $this->bc->getRawCalData($calInfo, $this->userId);

                    $a["syncEnd"] = microtime(true);
                    $a["syncDuration"] = $a["syncEnd"] - $a["syncStart"];

                    $data = var_export($a, true);
                }
                $status = 200;
            }
        }

        $tr = new TemplateResponse($this->appName, 'settings_dump', [], "base");
        $tr->setParams(['data' => $data]);
        $tr->setStatus($status);
        return $tr;

    }

}