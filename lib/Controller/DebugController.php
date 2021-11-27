<?php


namespace OCA\Appointments\Controller;

use OC_Util;
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

    public function __construct($AppName,
                                IRequest $request,
        $UserId,
                                IConfig $config,
                                BackendUtils $utils) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->config = $config;
        $this->utils = $utils;
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
        $tr = new TemplateResponse($this->appName, 'settings_dump', [], "base");
        $params['data'] = $data;
        $tr->setParams($params);
        return $tr;
    }

}