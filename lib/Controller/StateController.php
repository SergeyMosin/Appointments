<?php
/** @noinspection PhpMissingParamTypeInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\ExternalModeSabrePlugin;
use OCA\Appointments\Backend\TalkIntegration;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class StateController extends Controller
{

    private $userId;
    private $config;
    private $utils;
    private $bc;
    private $l;

    public function __construct($AppName,
                                IRequest $request,
        $UserId,
                                IConfig $config,
                                IL10N $l,
                                BackendUtils $utils,
                                BackendManager $backendManager)
    {
        parent::__construct($AppName, $request);

        $this->userId = $UserId;
        $this->config = $config;
        $this->l = $l;
        $this->utils = $utils;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
    }


    /**
     * @NoAdminRequired
     * @throws \OCP\PreConditionNotMetException
     * @throws \ErrorException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function index()
    {
        $action = $this->request->getParam("a");
        $r = new SendDataResponse();
        $r->setStatus(400);

        if ($action === 'get_pages') {
            $pgs = $this->utils->getUserSettings(
                BackendUtils::KEY_PAGES, $this->userId);
            $changed = false;
            foreach ($pgs as $pageId => $v) {
                // JUST IN CASE: check if calendars are set
                if ($v[BackendUtils::PAGES_ENABLED] === 1) {

                    if ($pageId === 'p0') {
                        $key = BackendUtils::KEY_CLS;
                    } else {
                        $key = BackendUtils::KEY_MPS . $pageId;
                    }

                    $other_cal = "-1";
                    $main_cal = $this->utils->getMainCalId($this->userId, $pageId, $this->bc, $other_cal);

                    $cms = $this->utils->getUserSettings($key, $this->userId);

                    $pgs[$pageId][BackendUtils::CLS_PRIVATE_PAGE] = $cms[BackendUtils::CLS_PRIVATE_PAGE];

                    $ts_mode = $cms[BackendUtils::CLS_TS_MODE];
                    if ((($ts_mode === "0" || $ts_mode === "2") && $main_cal === "-1") ||
                        ($ts_mode === "1" && ($main_cal === "-1" || $other_cal === "-1"))
                    ) {
                        $pgs[$pageId][BackendUtils::PAGES_ENABLED] = 0;
                        $changed = true;
                    }
                }
            }
            $j = json_encode($pgs);
            if ($j !== false) {
                if ($changed === true) {
                    $this->utils->setDBValue($this->userId, BackendUtils::KEY_PAGES, $j);
//                    $this->config->setUserValue(
//                        $this->userId, $this->appName,
//                        BackendUtils::KEY_PAGES, $j);
                }
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }

        } elseif ($action === 'set_pages') {
            // pageId can be:
            //  "new" - create new page
            //  "delete" - delete the page with Id in $v
            $pageId = $this->request->getParam("p");
            $v = $this->request->getParam("v");
            if (!empty($pageId) && $v !== null) {
                $vo = json_decode($v, true);
                if ($vo !== null) {
                    $sts = 200;
                    $vo_changed = false;
                    $pgs = $this->utils->getUserSettings(
                        BackendUtils::KEY_PAGES, $this->userId);
                    if (isset($pgs[$pageId])) {
                        // updating existing

                        // JUST IN CASE: check if email & calendars are set
                        if ($vo[BackendUtils::PAGES_ENABLED] === 1) {

                            if ($pageId === 'p0') {
                                $key = BackendUtils::KEY_CLS;
                            } else {
                                $key = BackendUtils::KEY_MPS . $pageId;
                            }

                            $other_cal = "-1";
                            $main_cal = $this->utils->getMainCalId($this->userId, $pageId, $this->bc, $other_cal);

                            $ts_mode = $this->utils->getUserSettings(
                                $key, $this->userId)[BackendUtils::CLS_TS_MODE];

                            $email = $this->utils->getUserSettings(
                                BackendUtils::KEY_ORG,
                                $this->userId)[BackendUtils::ORG_EMAIL];

                            if ((($ts_mode === "0" || $ts_mode === "2") && $main_cal === "-1")
                                || ($ts_mode === "1" && ($main_cal === "-1" || $other_cal === "-1"))
                                || empty($email)
                            ) {
                                $sts = 202;
                                $r->setData('{"info":"' . $this->l->t("Action failed. Select calendar(s) first.") . '"}');

                                $vo[BackendUtils::PAGES_ENABLED] = 0;
                                $vo_changed = true;
                            }
                        }
                    } elseif ($pageId === "new") {
                        // creating new
                        if (count($pgs) > 2) {
                            $d = $this->config->getUserValue($this->userId, $this->appName, "c" . "nk");
                            if ($d === "" || ((hexdec(substr($d, 0, 4)) >> 15) & 1) !== ((hexdec(substr($d, 4, 4)) >> 12) & 1)) {
                                $r->setData('{"contrib":"' . $this->l->t("More than 2 additional pages (10 maximum)") . '"}');
                                $sts = 202;
                            }
                        }
                        if ($sts !== 202) {
                            $i = 1;
                            for (; $i < 10; $i++) {
                                $_pid = "p" . $i;
                                if (!isset($pgs[$_pid])) {
                                    $pageId = $_pid;
                                    break;
                                }
                            }
                            if ($pageId === 'new') {
                                // could not find a free spot (10 max)
                                $sts = 202;
                                $r->setData('{"info":"' . $this->l->t("Page not added: 10 pages maximum") . '"}');
                            } else {
                                // add empty so we can get_mps & set_mps
                                $this->utils->setDBMpsValue($this->userId, $pageId, null);
//                                $this->config->setUserValue(
//                                    $this->userId, $this->appName,
//                                    BackendUtils::KEY_MPS . $pageId, "");
                            }
                        }
                    } elseif ($pageId === "delete" && $vo["page"] !== "p0") {
                        $_pid = $vo["page"];

                        // check and delete directory page link if url for this page is used
                        $uca = explode(chr(31), $this->getPubURI($_pid));
                        $a = $this->utils->getUserSettings(
                            BackendUtils::KEY_DIR, $this->userId);
                        $l = count($a);
                        for ($i = 0; $i < $l; $i++) {
                            $lu = $a[$i]['url'];
                            if ($lu === $uca[0] || $lu === $uca[1]) {
                                unset($a[$i]);
                            }
                        }
                        if ($l !== count($a)) {
                            // array_values = reindex
                            $dpl = json_encode(array_values($a));
                            if ($dpl !== false) {
                                $this->utils->setDBValue($this->userId, BackendUtils::KEY_DIR, $dpl);
//                                $this->config->setUserValue(
//                                    $this->userId, $this->appName,
//                                    BackendUtils::KEY_DIR, $dpl);
                            }
                        }

                        // delete the page
                        unset($pgs[$_pid]);

                        $mps = $this->utils->getUserSettings(BackendUtils::KEY_MPS_COL, $this->userId);
                        unset($mps[$_pid]);
                        $this->utils->setDBValue($this->userId, BackendUtils::KEY_MPS_COL,
                            json_encode($mps) ?: null);

//                        $this->utils->setDBValue();
//                        $this->config->deleteUserValue(
//                            $this->userId,$this->appName,
//                            BackendUtils::KEY_MPS.$_pid);
                    } else {
                        \OC::$server->getLogger()->error("Bad pageId/page_action");
                        $sts = 400;
                    }

                    if ($sts === 200 || $vo_changed === true) {
                        if ($pageId !== "delete") {
                            // filter new "arrivals"
                            $sa = [];
                            foreach (BackendUtils::PAGES_VAL_DEF as $_pid => $v) {
                                if (isset($vo[$_pid]) && gettype($vo[$_pid]) === gettype($v)) {
                                    $sa[$_pid] = $vo[$_pid];
                                } else {
                                    $sa[$_pid] = $v;
                                }
                            }
                            $pgs[$pageId] = $sa;
                        }

                        if (!$this->utils->setUserSettings(
                                BackendUtils::KEY_PAGES,
                                "", $pgs,
                                $this->userId, $this->appName) === true
                        ) {
                            $sts = 500;
                        }
                    }

                    $r->setStatus($sts);
                }
            }
        } elseif ($action === 'get_puburi') {
            $pageId = $this->request->getParam("p", 'p0');
            if (empty($pageId)) {
                $pageId = 'p0';
            }

            $pgs = $this->utils->getUserSettings(
                BackendUtils::KEY_PAGES, $this->userId);
            if (isset($pgs[$pageId])) {
                $r->setData($this->getPubURI($pageId));
                $r->setStatus(200);
            }

        } elseif ($action === 'get_diruri') {
            $gos = "sub" . 'str';
            $c = $this->config->getUserValue($this->userId, $this->appName, "cn" . 'k');
            $go = "hexd" . 'ec';
            $dir = $this->utils->getUserSettings(
                BackendUtils::KEY_DIR, $this->userId);
            if (count($dir) === 0) {
                $r->setData('{"info":"' . $this->l->t("Please setup the directory page first.") . '"}');
                $r->setStatus(202);
            } elseif (empty($c) || (($go($gos($c, 0, 0b100)) >> 15) & 0b1) !== (($go($gos($c, 0b100, 4)) >> 0xC) & 0b1)) {
                $r->setData('{"contrib":"' . $this->l->t("Directory page") . '"}');
                $r->setStatus(200 + 2);
            } else {
                $r->setData(
                    $this->utils->getPublicWebBase() . '/pub/'
                    // "p0" will return only the encoded username
                    . $this->utils->getToken($this->userId, "p0") . '/dir' . chr(31) . "");
                $r->setStatus(200);
            }
        } elseif ($action === "set_pps") {
            $value = $this->request->getParam("d");
            if ($value !== null) {
                if ($this->utils->setUserSettings(
                        BackendUtils::KEY_PSN,
                        $value, $this->utils->getDefaultForKey(BackendUtils::KEY_PSN),
                        $this->userId, $this->appName) === true
                ) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_pps") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_PSN, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "get_use_nc_theme") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_PSN, $this->userId);
            $r->setData($a[BackendUtils::PSN_USE_NC_THEME] === true ? "true" : "false");
            $r->setStatus(200);
        } elseif ($action === "set_use_nc_theme") {
            $value = $this->request->getParam("d");
            if ($value !== null) {

                $a = $this->utils->getUserSettings(
                    BackendUtils::KEY_PSN, $this->userId);

                $a[BackendUtils::PSN_USE_NC_THEME] = $value === "true";

                $j = json_encode($a);
                if ($j !== false && $this->utils->setUserSettings(
                        BackendUtils::KEY_PSN,
                        $j, $this->utils->getDefaultForKey(BackendUtils::KEY_PSN),
                        $this->userId, $this->appName) === true
                ) {
                    $r->setData("rrrr: " . $j);
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_uci") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_ORG, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_uci") {
            $d = $this->request->getParam("d");
            if ($d !== null && strlen($d) < 512) {
                if ($this->utils->setUserSettings(
                        BackendUtils::KEY_ORG,
                        $d, $this->utils->getDefaultForKey(BackendUtils::KEY_ORG),
                        $this->userId, $this->appName) === true
                ) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_eml") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_EML, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_eml") {
            $value = $this->request->getParam("d");
            if ($value !== null && !isset($value[1280])) {
                if ($this->utils->setUserSettings(
                        BackendUtils::KEY_EML,
                        preg_replace("/(\\\\n){2,}/", "\\\\n\\\\n", $value), $this->utils->getDefaultForKey(BackendUtils::KEY_EML),
                        $this->userId, $this->appName) === true
                ) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_tz") {

            $calId = $this->request->getParam("p", "-1");
            $tz = $this->utils->getCalendarTimezone($this->userId, $this->config, $this->bc->getCalendarById($calId, $this->userId));
            $r->setData($tz->getName());
            $r->setStatus(200);

        } elseif ($action === "get_cls") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_CLS, $this->userId);

            // we can have stale calendars in BackendUtils::CLS_TMM_MORE_CALS we do purge here
            if ($a[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_TEMPLATE) {
                $this->purgeStaleConflictCalsAndSubs($a);
            }

            $j = json_encode($this->getMoreProps($a));
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_cls") {
            $value = $this->request->getParam("d");
            if ($value !== null) {

                if ($this->setClsMps(
                        BackendUtils::KEY_CLS,
                        $this->utils->getDefaultForKey(BackendUtils::KEY_CLS),
                        $value, 'p0') === true) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_mps") {
            $pageId = $this->request->getParam("p");
            // must have a pageId for this action
            if (!empty($pageId) && $this->MPExists($pageId)) {

//            if(!empty($pageId) && null!==$this->config->getUserValue(
//                    $this->userId,$this->appName,
//                    BackendUtils::KEY_MPS.$pageId,null))
//            {
                $a = $this->utils->getUserSettings(
                    BackendUtils::KEY_MPS . $pageId, $this->userId);

                // we can have stale calendars in BackendUtils::CLS_TMM_MORE_CALS we do purge here
                if ($a[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_TEMPLATE) {
                    $this->purgeStaleConflictCalsAndSubs($a);
                }

                $j = json_encode($this->getMoreProps($a));
                if ($j !== false) {
                    $r->setData($j);
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }

        } elseif ($action === "set_mps") {
            $pageId = $this->request->getParam("p");
            $value = $this->request->getParam("d");
            // must have a pageId for this action
            if (!empty($pageId) && $this->MPExists($pageId)) {
                if ($this->setClsMps(
                        BackendUtils::KEY_MPS . $pageId,
                        $this->utils->getDefaultForKey(BackendUtils::KEY_MPS),
                        $value, $pageId) === true) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === "get_dir") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_DIR, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }

        } elseif ($action === "set_dir") {
            $value = $this->request->getParam("d");
            if ($value !== null) {

                /** @noinspection PhpUnhandledExceptionInspection */
//                $this->config->setUserValue($this->userId, $this->appName,BackendUtils::KEY_DIR,$value);
                $this->utils->setDBValue($this->userId, BackendUtils::KEY_DIR, $value);

                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_k") {
            $value = $this->request->getParam("d");
            $sts = 500;
            if ($value !== null) {
                $a = json_decode($value, true);
                if ($a !== null && isset($a['k'])) {
                    $k = str_replace("-", "", trim($a['k']));
                    if (strlen($k) === 20 && ((hexdec(substr($k, 0, 4)) >> 15) & 1) === ((hexdec(substr($k, 4, 4)) >> 12) & 1)) {
                        /** @noinspection PhpUnhandledExceptionInspection */
                        $this->config->setUserValue($this->userId, $this->appName, "cnk", $k);
                        $sts = 200;
                    }
                }
            }
            $r->setStatus($sts);
        } elseif ($action === "get_k") {
            $r->setData($this->config->getUserValue($this->userId, $this->appName, "cnk") !== "" ? "_" : "");
            $r->setStatus(200);
        } elseif ($action === "get_talk") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_TALK, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_talk") {
            $value = $this->request->getParam("d");
            if ($value !== null) {
                // check if canTalk
                $va = json_decode($value, true);
                if ($va !== null) {
                    if ($va[BackendUtils::TALK_ENABLED] === true && TalkIntegration::canTalk() === false) {
                        // Probably Talk is NOT installed or something broken
                        $r->setData('{"info":"' . $this->l->t("Can't find Talk app. Is it installed and enabled ?") . '"}');
                        $r->setStatus(202);
                    } else {
                        if ($this->utils->setUserSettings(
                                BackendUtils::KEY_TALK,
                                $value, $this->utils->getDefaultForKey(BackendUtils::KEY_TALK),
                                $this->userId, $this->appName) === true
                        ) {
                            $r->setStatus(200);
                        } else {
                            $r->setStatus(500);
                        }
                    }
                }
            }
        } elseif ($action === 'set_fi') {
            $value = $this->request->getParam("d", '');
            if (empty($value) || $value === '[]') {
                $v = "[]";
                $h = '';
                $r->setStatus(200);
            } else {
                $a = json_decode($value, true);
                if ($a === null) {
                    $v = "[]";
                    $h = '';
                    $r->setStatus(400);
                } else {
                    $h = $this->makeFormComponent($a[0], 0);
                    $v = json_encode($a);
                    if ($v === false) {
                        $v = "[]";
                    }
                    $r->setStatus(200);
                }
            }

            $this->utils->setDBValue($this->userId, BackendUtils::KEY_FORM_INPUTS_JSON, $v);
            $this->utils->setDBValue($this->userId, BackendUtils::KEY_FORM_INPUTS_HTML, $h);

            $r->setData($h);

        } elseif ($action === 'get_fi') {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_FORM_INPUTS_JSON, $this->userId);

            // TODO: this needs to be done for all elements
            if (isset($a[0]) && isset($a[0]['name'])) {
                unset($a[0]['name']);
            }

            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === 'get_t_data') {
            $pageId = $this->request->getParam("p");
            if ($pageId !== null) {
                $a = $this->utils->getTemplateData($pageId, $this->userId);
                $j = json_encode($a);
                if ($j !== false) {
                    $r->setData($j);
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        } elseif ($action === 'set_t_data') {
            $pageId = $this->request->getParam("p");
            $value = $this->request->getParam("d");
            if ($pageId !== null && $this->utils->setTemplateData($pageId, $value, $this->userId) === true) {
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === 'get_t_tz') {
            $pageId = $this->request->getParam("p");
            if (!empty($pageId)) {
//                $a = $this->utils->getUserSettings(BackendUtils::KEY_TMPL_INFO, $this->userId);
                $a = $this->utils->getTemplateInfo($this->userId, $pageId);
                $j = json_encode($a);
                if ($j !== false) {
                    $r->setData($j);
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            } else {
                $r->setStatus(400);
            }
        } elseif ($action === 'get_reminder') {
            $a = $this->utils->getUserSettings(BackendUtils::KEY_REMINDERS, $this->userId);
            $a[BackendUtils::REMINDER_BJM] = $this->config->getAppValue("core", "backgroundjobs_mode");
            $cliUrl = $this->config->getSystemValue('overwrite.cli.url');
            $a[BackendUtils::REMINDER_CLI_URL] = $cliUrl === '' || $cliUrl === 'localhost' ? '' : '1';
            $a[BackendUtils::REMINDER_LANG] = $this->config->getSystemValue('default_language', 'en');

            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === 'set_reminder') {
            $d = $this->request->getParam("d");
            if ($d !== null) {
                $va = json_decode($d, true);
                if ($va !== null) {
                    $err = false;
                    $value = null;
                    // If there is no reminder data we set DB value to null, so we can optimize when we query data for the cronjon
                    if (isset($va[BackendUtils::REMINDER_DATA]) && count($va[BackendUtils::REMINDER_DATA]) === 3) {
                        $sa = [];
                        $defaults = $this->utils->getDefaultForKey(BackendUtils::KEY_REMINDERS);
                        foreach ($defaults as $k => $v) {
                            if (isset($va[$k]) && gettype($va[$k]) === gettype($v)) {
                                $sa[$k] = $va[$k];
                            } else {
                                $sa[$k] = $v;
                            }
                        }

                        $k = $this->config->getUserValue($this->userId, $this->appName, "cn" . "k");
                        if ($k === "" || ((hexdec(substr($k, 0, 4)) >> 15) & 1) !== ((hexdec(substr($k, 4, 4)) >> 12) & 1)) {

                            $sa[BackendUtils::REMINDER_DATA][1] = $defaults[BackendUtils::REMINDER_DATA][1];
                            $sa[BackendUtils::REMINDER_DATA][2] = $defaults[BackendUtils::REMINDER_DATA][2];
                            $allowed_values = ["3600", "7200", "14400", "28800", "86400"];
                        } else {
                            $allowed_values = ["3600", "7200", "14400", "28800", "86400", "172800", "259200", "345600", "432000", "518400", "604800"];
                        }

                        $c = count($sa[BackendUtils::REMINDER_DATA]);

                        $data_arr = $sa[BackendUtils::REMINDER_DATA];
                        $has_seconds = false;
                        for ($i = 0; $i < $c; $i++) {
                            $item = $data_arr[$i];
                            if (gettype($item[BackendUtils::REMINDER_DATA_ACTIONS]) !== "boolean") {
                                $err = true;
                                break;
                            }
                            $seconds = $item[BackendUtils::REMINDER_DATA_TIME];
                            if ($seconds === "0") {
                                continue;
                            }
                            if (!in_array($seconds, $allowed_values)) {
                                $err = true;
                                break;
                            }
                            $has_seconds = true;
                        }
                        if ($has_seconds) {
                            $t = json_encode($sa);
                            if ($t !== false) {
                                $value = $t;
                            } else {
                                $err = true;
                                $r->setStatus(500);
                            }
                        }
                    }

                    if (!$err) {
                        if ($this->utils->setUserSettings(
                                BackendUtils::KEY_REMINDERS,
                                $value, null,
                                $this->userId, $this->appName) === true
                        ) {
                            $r->setStatus(200);
                        } else {
                            $r->setStatus(500);
                        }
                    }
                }
            }
        } elseif ($action === "get_dbg") {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_DEBUGGING, $this->userId);
            $j = json_encode($a);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        } elseif ($action === "set_dbg") {
            $d = $this->request->getParam("d");
            if ($d !== null && strlen($d) < 256) {
                if ($this->utils->setUserSettings(
                        BackendUtils::KEY_DEBUGGING,
                        $d, $this->utils->getDefaultForKey(BackendUtils::KEY_DEBUGGING),
                        $this->userId, $this->appName) === true
                ) {
                    $r->setStatus(200);
                } else {
                    $r->setStatus(500);
                }
            }
        }
        return $r;
    }

    private function makeFormComponent(&$obj, $index = 0)
    {
        $r = '';
        $fields = [];
        if (is_array($obj) && array_key_exists(0, $obj) && is_array($obj[0])) {
            foreach ($obj as $ind => $field) {
                $r = $this->makeFormField($field, $ind);
                if ($r === '') {
                    return $r;
                }
                $fields[] = $r;
                $obj[$ind]['name'] = $field['name'];
                $r = '';
            }
            return implode('', $fields);
        } else {
            return $this->makeFormField($obj, $index);
        }
    }

    private function makeFormField(&$obj, $index = 0)
    {
        $r = '';
        if (!isset($obj['tag']) || !isset($obj['label'][2])) {
            return $r;
        }
        $obj['label'] = htmlspecialchars(trim($obj['label']), ENT_QUOTES, 'UTF-8');
        $tail = '';
        $ph = '';
        $class = '';
        switch ($obj['tag']) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'input':
                if (!isset($obj['type'])) {
                    $obj['type'] = 'text';
                }
                $tail = ' type="' . ($obj['type'] === 'number' ? 'number' : 'text') . '" maxlength="512"/>';
                $class = 'srgdev-ncfp-form-input';
            case 'textarea':
                if (!isset($obj['placeholder'])) {
                    return $r;
                }
                $ph = ' placeholder="' . htmlspecialchars($obj['placeholder'], ENT_QUOTES, 'UTF-8') . '"';
                if (empty($tail)) {
                    $tail = ' maxlength="512"></textarea>';
                    $class = 'srgdev-ncfp-form-textarea';
                }
                break;
            case 'select':
                if (!isset($obj['options'])
                    || gettype($obj['options']) !== 'array'
                    || count($obj['options']) === 0) {
                    return $r;
                }
                $tail = '>';
                foreach ($obj['options'] as $option) {
                    if (isset($option[1])) {
                        $o = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                        $tail .= '<option class="srgdev-ncfp-form-option" value="' . $o . '">' . $o . '</option>';
                    }
                }
                if (!isset($tail[1])) {
                    return $r;
                }
                $tail .= '</select>';
                $class = 'srgdev-ncfp-form-input srgdev-ncfp-form-select';
                break;
            default:
                return $r;
        }

        $id = 'srgdev-ncfp_' . hash('adler32', $index . $obj['tag'] . $obj['label']);
        $name = 'n' . hash('adler32', $tail . $id . $index);

        $obj['name'] = $name;

        $dmo = (isset($obj['required']) && $obj['required']) === true ? 'r1' : 'r0';

        return '<label for="' . $id . '" class="srgdev-ncfp-form-label">' . $obj['label'] . '</label><' . $obj['tag'] . ' data-more="' . $dmo . '" id="' . $id . '" name="' . $name . '" class="' . $class . '"' . $ph . $tail;

    }

    /**
     * @param string $key BackendUtils::KEY_MPS or BackendUtils::KEY_CLS
     * @param $def
     * @param $value
     * @param $pageId
     * @return bool
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function setClsMps($key, $def, $value, $pageId)
    {

        $o_cms = $this->utils->getUserSettings(
            $key, $this->userId);

        $va = json_decode($value, true);
        if ($va === null) {
            \OC::$server->getLogger()->error("can not set KEY_TMPL_INFO, json_decode failed");
            return false;
        }

        $d = $this->config->getUserValue($this->userId, $this->appName, "cnk");
        if ($d === "" || ((hexdec(substr($d, 0, 4)) >> 15) & 1) !== ((hexdec(substr($d, 4, 4)) >> 12) & 1)) {
            if (isset($va[BackendUtils::CLS_TMM_MORE_CALS]) && count($va[BackendUtils::CLS_TMM_MORE_CALS]) > 2) {
                $va[BackendUtils::CLS_TMM_MORE_CALS] = array_slice($va[BackendUtils::CLS_TMM_MORE_CALS], 0, 2);
            }
        }

        if (isset($va[BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC])) {
            // sync value must be one of the following
            if (!isset(["0" => true, "60" => true, "120" => true, "240" => true, "480" => true, "720" => true, "1440" => true][$va[BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC]])) {
                $va[BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC] = '0';
            }
        }

        //check if we have BackendUtils::KEY_TMPL_INFO
        if (strpos($value, BackendUtils::TMPL_TZ_DATA)) {

            if (!isset($va[BackendUtils::TMPL_TZ_NAME]) || !isset($va[BackendUtils::TMPL_TZ_DATA])) {
                \OC::$server->getLogger()->error("can not set KEY_TMPL_INFO, invalid TMPL_TZ data");
                return false;
            }

            if (!$this->utils->setTemplateInfo($this->userId, $pageId, array(
                BackendUtils::TMPL_TZ_NAME => $va[BackendUtils::TMPL_TZ_NAME],
                BackendUtils::TMPL_TZ_DATA => $va[BackendUtils::TMPL_TZ_DATA]))) {
                \OC::$server->getLogger()->error("can not set KEY_TMPL_INFO, setTemplateInfo failed");
                return false;
            }
        }

        // ensure positive values for buffers and for now the "after" buffer must be the same as the "before" buffer because there is really no good way to deal with buffer overlap when bufferBefore != afterBuffer
        if (isset($va[BackendUtils::CLS_BUFFER_BEFORE])) {

            if (isset($va[BackendUtils::CLS_TS_MODE]) && $va[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                // in simple mode buffers must be 0
                $va[BackendUtils::CLS_BUFFER_BEFORE] = 0;
            }
            if ($va[BackendUtils::CLS_BUFFER_BEFORE] < 0) {
                $va[BackendUtils::CLS_BUFFER_BEFORE] = 0;
            }

            $va[BackendUtils::CLS_BUFFER_AFTER] = $va[BackendUtils::CLS_BUFFER_BEFORE];
        } else {
            unset($va[BackendUtils::CLS_BUFFER_AFTER]);
        }

        $value = json_encode($va);

        if ($this->utils->setUserSettings(
                $key, $value, $def,
                $this->userId, $this->appName) === true
        ) {
            // this can be cls or mps
            $cms = $this->utils->getUserSettings($key, $this->userId);

            // This is needed to get BackendUtils::CLS_XTM_AUTO_FIX
            $real_cls = $this->utils->getUserSettings(BackendUtils::KEY_CLS, $this->userId);

            // Set ExternalModeSabrePlugin::AUTO_FIX_URI
            if ($real_cls[BackendUtils::CLS_XTM_AUTO_FIX] === false) {
                $this->config->setUserValue($this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI, "");
            } else {
                $afu = $this->config->getUserValue(
                    $this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI);

                $o_calId = $o_cms[BackendUtils::CLS_XTM_SRC_ID];
                $calId = $cms[BackendUtils::CLS_XTM_SRC_ID];

                $o_cUri = "";
                $cUri = "";

                $cals = $this->bc->getCalendarsForUser($this->userId);
                $l = count($cals);
                for ($i = 0; $i < $l; $i++) {
                    $cal = $cals[$i];
                    if ($cal['id'] === $o_calId) {
                        $o_cUri = $pageId . "/" . $this->userId . "/" . $cal['uri'] . "/" . chr(31);
                    }
                    if ($cal['id'] === $calId) {
                        $cUri = $pageId . "/" . $this->userId . "/" . $cal['uri'] . "/" . chr(31);
                    }
                }
//                af_uri="/".$this->userId."/".$ci["uri"]."/";

                if (!empty($o_cUri)) {
                    $afu = str_replace($o_cUri, "", $afu);
                }

                if (!empty($cUri) && $cms[BackendUtils::CLS_TS_MODE] === "1") {
                    $afu .= $cUri;
                }

                $this->config->setUserValue($this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI, $afu);
            }

            if ($o_cms[BackendUtils::CLS_TS_MODE] !== $cms[BackendUtils::CLS_TS_MODE]) {
                // ts_mode changed - disable the page...
                $pgs = $this->utils->getUserSettings(
                    BackendUtils::KEY_PAGES, $this->userId);

                $pgs[$pageId][BackendUtils::PAGES_ENABLED] = 0;

                $this->utils->setUserSettings(
                    BackendUtils::KEY_PAGES,
                    "", $pgs,
                    $this->userId, $this->appName);
            }
            return true;
        } else {
            return false;
        }

    }

    /**
     * @param array $a can be CLS or MPS
     * @return array
     */
    private function getMoreProps($a)
    {
        if ($a[BackendUtils::CLS_TS_MODE] === "0"
            && $a[BackendUtils::CLS_MAIN_ID] !== "-1") {

            $cal = $this->bc->getCalendarById(
                $a[BackendUtils::CLS_MAIN_ID], $this->userId);
            if ($cal !== null) {
                $a['curCal_color'] = $cal['color'];
                $a['curCal_name'] = $cal['displayName'];
            }
        }
        return $a;
    }

    private function getPubURI($pageId)
    {
        $pb = $this->utils->getPublicWebBase();
        $tkn = $this->utils->getToken($this->userId, $pageId);
        return $pb . '/' . $this->utils->pubPrx($tkn, false) . 'form' . chr(31)
            . $pb . '/' . $this->utils->pubPrx($tkn, true) . 'form';
    }

    private function MPExists($p)
    {
        $mps = $this->utils->getUserSettings(BackendUtils::KEY_MPS_COL, $this->userId);
        return $mps !== null && array_key_exists($p, $mps);
    }

    private function purgeStaleConflictCalsAndSubs(&$cls)
    {
        $currentCalIds = $cls[BackendUtils::CLS_TMM_MORE_CALS];
        if (count($currentCalIds) > 0) {
            // we have calendars to check/filter
            $cls[BackendUtils::CLS_TMM_MORE_CALS] = $this->utils->filterCalsAndSubs(
                $currentCalIds,
                $this->bc->getCalendarsForUser($this->userId, false)
            );
        }
        $currentSubIds = $cls[BackendUtils::CLS_TMM_SUBSCRIPTIONS];
        if (count($currentSubIds) > 0) {
            // we have calendars to check/filter
            $cls[BackendUtils::CLS_TMM_SUBSCRIPTIONS] = $this->utils->filterCalsAndSubs(
                $currentSubIds,
                $this->bc->getSubscriptionsForUser($this->userId)
            );
        }
    }
}
