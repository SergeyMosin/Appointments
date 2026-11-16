<?php
/** @noinspection PhpMissingParamTypeInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace OCA\Appointments\Controller;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\BbbIntegration;
use OCA\Appointments\Backend\IBackendConnector;
use OCA\Appointments\Backend\PageIO;
use OCA\Appointments\Backend\SettingsValidator;
use OCA\Appointments\Backend\TalkIntegration;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class StateController extends Controller
{

    private string $userId;
    private IConfig $config;
    private BackendUtils $utils;
    private IBackendConnector $bc;
    private IL10N $l;
    private LoggerInterface $logger;
    private SettingsValidator $validator;
    private PageIO $pageIO;

    public function __construct($AppName, $UserId,
                                IRequest $request,
                                IConfig $config,
                                IL10N $l,
                                BackendUtils $utils,
                                BackendManager $backendManager,
                                LoggerInterface $logger,
                                SettingsValidator $validator,
                                PageIO $pageIO)
    {
        parent::__construct($AppName, $request);

        $this->userId = $UserId;
        $this->config = $config;
        $this->l = $l;
        $this->utils = $utils;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
        $this->logger = $logger;
        $this->validator = $validator;
        $this->pageIO = $pageIO;
    }


    /**
     * @NoAdminRequired
     * @throws \OCP\PreConditionNotMetException
     * @throws \ErrorException
     */
    public function index(): SendDataResponse
    {
        $action = $this->request->getParam("a");
        $r = new SendDataResponse();
        $r->setStatus(Http::STATUS_BAD_REQUEST);

        if ($action === 'get_pages') {

            try {
                $pages = $this->utils->getUserPages($this->userId, true);
            } catch (\Throwable $e) {
                $this->logException($e);
                $r->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
                return $r;
            }
            $j = json_encode($pages);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(Http::STATUS_OK);
            } else {
                $r->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
            }
        } elseif ($action === 'set_pages') {
            $pageId = $this->request->getParam("p", 'p0');
            if (!is_string($pageId) || empty($pageId)) {
                $r->setData('{"error": "bad pageId"}');
                return $r;
            }
            if ($pageId === '_') {
                // create a new page
                try {
                    $pages = $this->utils->getUserPages($this->userId);
                } catch (\Throwable $e) {
                    $this->logException($e);
                    $r->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
                    return $r;
                }

                $pageCount = count($pages);

                if ($pageCount > 2 && $this->config->getUserValue($this->userId, $this->appName, "cn" . "k") === '') {
                    $r->setStatus(Http::STATUS_ACCEPTED);
                    $r->setData(json_encode([
                        "type" => 2,
                        "message" => $this->l->t("More than 3 pages")
                    ]));
                    return $r;
                }

                // find first available page, ex 'p0', 'p1', 'p2', etc...
                $pageNumbers = [];
                for ($i = 0; $i < $pageCount; ++$i) {
                    $pageNumbers[] = intval(substr($pages[$i]['id'], 1));
                }
                sort($pageNumbers, SORT_NUMERIC);
                $n = 0;
                for ($i = 0, $c = count($pageNumbers); $i < $c; ++$i) {
                    $n = $pageNumbers[$i];
                    if ($i < $c - 1) {
                        // we have next
                        if ($n + 1 < $pageNumbers[$i + 1]) {
                            // we have a "hole" in $pageNumbers
                            ++$n;
                            break;
                        }
                    } else {
                        ++$n;
                    }
                }

                $newPageId = 'p' . $n;
                $count = $this->utils->createPage($this->userId, $newPageId, json_encode([
                    "enabled" => false,
                    "label" => $this->l->t("Public Page"),
                    BackendUtils::PSN_USE_NC_THEME => true,
                ]));
                if ($count !== 1) {
                    $this->logger->warning('createPage returned ' . $count . ' , but expected 1');
                    $r->setStatus(Http::STATUS_ACCEPTED);
                    $r->setData(json_encode([
                        "message" => $this->l->t("Create Page warning. Check logs.")
                    ]));
                } else {
                    // TODO: set unconfirmed appointments auto-cancel for new pages ???
                    $r->setStatus(Http::STATUS_OK);
                }

            } else {
                // delete the page if exists
                try {
                    $pageExist = $this->utils->loadSettingsForUserAndPage($this->userId, $pageId);
                } catch (\Throwable $e) {
                    $this->logException($e);
                    $r->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
                    return $r;
                }
                if (!$pageExist) {
                    // deleting nonexistent page
                    $r->setStatus(Http::STATUS_NOT_FOUND);
                    return $r;
                }

                $pageToken = '/' . $this->utils->getUserSettings()[BackendUtils::KEY_TOKEN];

                // check and delete directory page link if url for this page is used
                if ($this->utils->loadSettingsForUserAndPage($this->userId, 'd0')) {
                    $dirSettings = $this->utils->getUserSettings();
                    if (!empty($dirSettings[BackendUtils::DIR_ITEMS])) {
                        $data = $dirSettings[BackendUtils::DIR_ITEMS];
                        $filteredData = array_filter($data, function ($item) use ($pageToken) {
                            return !str_contains($item['url'], $pageToken);
                        });
                        if (count($filteredData) !== count($data)) {
                            $this->updateDirSettings('d0', BackendUtils::DIR_ITEMS, $filteredData);
                        }
                    }
                }

                $count = $this->utils->deletePage($this->userId, $pageId);
                if ($count !== 1) {
                    $this->logger->warning('deletePage returned ' . $count . ' , but expected 1');
                    $r->setStatus(Http::STATUS_ACCEPTED);
                    $r->setData(json_encode([
                        "message" => $this->l->t("Delete Page warning. Check logs.")
                    ]));
                } else {
                    $r->setStatus(Http::STATUS_OK);
                }
            }

        } else {
            $pageId = $this->request->getParam("p", 'p0');
            if (!is_string($pageId)) {
                $r->setData('{"error": "bad pageId"}');
                return $r;
            }
            if (empty($pageId)) {
                $pageId = 'p0';
            }

            try {
                $loaded = $this->utils->loadSettingsForUserAndPage($this->userId, $pageId);
            } catch (\Throwable $e) {
                $this->logException($e);
                $r->setStatus(Http::STATUS_INTERNAL_SERVER_ERROR);
                return $r;
            }
            if ($loaded === false) {
                return $r;
            }

            switch ($action) {
                case 'get_puburi':

                    // '{"type":2,"message":"' . $this->l->t("More than 3 pages") . '"}'
                    $data = $this->getPubURI($pageId);
                    if ($data === 'INVALID_TOKEN') {
                        $r->setStatus(Http::STATUS_ACCEPTED);
                        $r->setData(json_encode([
                            "type" => 1,
                            "message" => $this->l->t("Please add at least one directory item first.")
                        ]));
                    } else {

                        if ($this->utils->isDir($pageId) && $this->config->getUserValue($this->userId, $this->appName, 'c' . chr(110) . "k") === '') {
                            $r->setStatus(Http::STATUS_ACCEPTED);
                            $r->setData(json_encode([
                                "type" => 2,
                                "message" => $this->l->t("Directory page")
                            ]));
                        } else {
                            $r->setData($data);
                            $r->setStatus(Http::STATUS_OK);
                        }
                    }
                    break;
                case 'get_all':
                    if ($this->utils->isDir($pageId) === true) {
                        $r->setData(json_encode([
                            'settings' => $this->utils->getUserSettings()
                        ]));
                    } else {
                        $settings = $this->getSettingsAndCleanup($pageId);

                        // filter out cancellation reminders
                        foreach ($settings[BackendUtils::KEY_REMINDERS][BackendUtils::REMINDER_DATA] as $index => $remData) {
                            $remType = $remData[BackendUtils::REMINDER_DATA_TYPE];
                            if ($remType !== BackendUtils::REMINDER_TYPE_APPT || $index > 2) {
                                unset(
                                    $settings[BackendUtils::KEY_REMINDERS][BackendUtils::REMINDER_DATA][$index]
                                );
                            }
                        }

                        // add reminder related readonly props
                        $cliUrl = $this->config->getSystemValue('overwrite.cli.url');
                        $settings[BackendUtils::REMINDER_BJM] = $this->config->getAppValue("core", "backgroundjobs_mode");
                        $settings[BackendUtils::REMINDER_CLI_URL] =
                            filter_var($cliUrl, FILTER_VALIDATE_URL) === false
                                ? ''
                                : '1';
                        $settings[BackendUtils::REMINDER_LANG] = $this->config->getSystemValue('default_language', 'en');

                        // readonly Talk prop
                        $settings[BackendUtils::TALK_INTEGRATION_DISABLED] = $this->config->getAppValue(Application::APP_ID, BackendUtils::TALK_INTEGRATION_DISABLED, 'no') === 'yes' || !TalkIntegration::canTalk();

                        // readonly BBB prop
                        $settings[BackendUtils::BBB_INTEGRATION_DISABLED] = $this->config->getAppValue(Application::APP_ID, BackendUtils::BBB_INTEGRATION_DISABLED, 'no') === 'yes' || !\OC::$server->get(BbbIntegration::class)->canBBB();

                        $r->setData(json_encode([
                            'settings' => $settings,
                            'cals' => $this->getCalList($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_TEMPLATE),
                            'k' => $this->config->getUserValue($this->userId, $this->appName, "c" . "" . "nk") !== "" ? "_" : "",
                        ]));
                    }
                    $r->setStatus(Http::STATUS_OK);
                    break;
                case 'get_tz':
                    $calId = $this->request->getParam("calId", "-1");
                    $tz = $this->utils->getCalendarTimezone($this->userId, $this->bc->getCalendarById($calId, $this->userId));
                    $r->setData($tz->getName());
                    $r->setStatus(200);
                    break;
                case 'get_calweek':
                    return $this->calgetweek($pageId);
                case 'export_page':
                    if ($this->utils->isDir($pageId)) {
                        break;
                    }
                    $r->setData(json_encode($this->pageIO->exportPage($this->userId, $pageId), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $r->setStatus(Http::STATUS_OK);
                    break;
                case 'import_page':
                    if ($this->utils->isDir($pageId)) {
                        break;
                    }
                    $doc = json_decode((string)$this->request->getParam("v", ''), true);
                    try {
                        // the token is never changed from the UI
                        $report = $this->pageIO->importPage($this->userId, $pageId, is_array($doc) ? $doc : [], false, false);
                    } catch (\InvalidArgumentException $e) {
                        $r->setData($this->makeErrorJson($e->getMessage()));
                        break;
                    }
                    $r->setData(json_encode([
                        'changed' => array_keys($report['changes']),
                        'warnings' => $report['warnings'],
                        'errors' => $report['errors'],
                    ]));
                    $r->setStatus(Http::STATUS_OK);
                    break;
                case 'set_one':
                    list($status, $data) = $this->updateSettings($pageId);
                    if ($status === Http::STATUS_OK) {
                        $r->setData('{"status":"ok"}');
                    } elseif (!empty($data)) {
                        $r->setData($data);
                    }
                    $r->setStatus($status);
            }
        }
        return $r;
    }


    private function regexRemoveScriptTag(string $text): string
    {
        return SettingsValidator::regexRemoveScriptTag($text);
    }

    private function updateSettings(string $pageId): array
    {
        $key = $this->request->getParam("k");
        $value = $this->request->getParam("v");

        if ($key === "__ckey") {
            return $this->setUserKey($value);
        }

        if ($this->utils->isDir($pageId)) {
            $settings = $this->utils->getUserSettings();
            if (empty($key) || !isset($settings[$key])
                || gettype($settings[$key]) !== gettype($value)) {
                $this->logger->warning("setUserSettingsV2: invalid key or value - " . $key);
                return [Http::STATUS_BAD_REQUEST, ''];
            }
            if (is_string($value)) {
                $value = $this->regexRemoveScriptTag($value);
            }
            return $this->updateDirSettings($pageId, $key, $value);
        }

        return $this->validator->apply($this->userId, $pageId, $key, $value);
    }

    private function updateDirSettings(string $pageId, string $key, $value): array
    {

        $dirSettings = $this->utils->getUserSettings();
        if ($key === BackendUtils::DIR_ITEMS) {
            if (!is_array($value) || empty($value)) {
                // delete the dir page data
                $count = $this->utils->deletePage($this->userId, $pageId);
                if ($count !== 1) {
                    $this->logger->warning('deletePage returned ' . $count . ' , but expected 1');
                    return [Http::STATUS_ACCEPTED, json_encode([
                        "message" => $this->l->t("Delete Page warning. Check logs.")
                    ])];
                } else {
                    return [Http::STATUS_OK, ''];
                }
            }

            $props = ['title', 'subTitle', 'text', 'url'];
            $propsCount = count($props);
            $data = [];
            foreach ($value as $va) {
                if (count($va) !== $propsCount) {
                    continue;
                }
                $temp = [];
                for ($i = 0; $i < $propsCount; $i++) {
                    $prop = $props[$i];
                    if (!array_key_exists($prop, $va)) {
                        continue 2;
                    }
                    $v = $va[$prop];
                    if (strlen($v) > 1024) {
                        continue 2;
                    }
                    $temp[$prop] = $this->regexRemoveScriptTag($v);
                }
                $data[] = $temp;
            }
            $dirSettings[$key] = $data;
        } else {
            $dirSettings[$key] = $value;
        }

        // for dirs we want the token to be constant for the same user and pageId
        $tokenData = $this->userId . $this->config->getAppValue($this->appName, 'hk') . $pageId;

        $crc = hash('crc32b', $tokenData);
        $token = $crc . hash('adler32', $crc . $tokenData);
        $result = $this->utils->dbUpsert2($this->userId, $pageId, [
            BackendUtils::KEY_TOKEN => $token,
            BackendUtils::KEY_DATA => json_encode($this->utils->filterDefaultSettings($dirSettings, $this->utils->getDefaultSettingsDataForDirPage()))
        ]);

        $status = $result === true ? Http::STATUS_OK : Http::STATUS_INTERNAL_SERVER_ERROR;
        return [$status, ''];
    }


    private function setUserKey(string|null $key): array
    {
        if ($key !== null && gettype($key) === 'string') {
            $k = str_replace("-", "", trim($key));
            if (strlen($k) === 20 && ((hexdec(substr($k, 0, 4)) >> 15) & 1) === ((hexdec(substr($k, 4, 4)) >> 12) & 1)) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->config->setUserValue($this->userId, $this->appName, "cnk", $k);
                return [Http::STATUS_OK, ''];
            }
        }
        return [Http::STATUS_BAD_REQUEST, '{"error":"invalid key"}'];
    }

    private function getSettingsAndCleanup(string $pageId): array
    {

        $settings = $this->utils->getUserSettings();

        $currentCalIds = $settings[BackendUtils::CLS_TMM_MORE_CALS];
        if (count($currentCalIds) > 0) {
            // we have calendars to check/filter
            $newCalIds = $this->utils->filterCalsAndSubs(
                $currentCalIds,
                $this->bc->getCalendarsForUser($this->userId, false)
            );
            if (count($newCalIds) !== count($currentCalIds)) {
                $r = $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::CLS_TMM_MORE_CALS, $newCalIds);
                if ($r[0] !== Http::STATUS_OK) {
                    $this->logger->error('getSettingsAndCleanup failed for ' . BackendUtils::CLS_TMM_MORE_CALS);
                }
            }
        }
        $currentSubIds = $settings[BackendUtils::CLS_TMM_SUBSCRIPTIONS];
        if (count($currentSubIds) > 0) {
            // we have calendars to check/filter
            $newSubIds = $this->utils->filterCalsAndSubs(
                $currentSubIds,
                $this->bc->getSubscriptionsForUser($this->userId)
            );
            if (count($newSubIds) !== count($currentSubIds)) {
                $r = $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::CLS_TMM_SUBSCRIPTIONS, $newSubIds);
                if ($r[0] !== Http::STATUS_OK) {
                    $this->logger->error('getSettingsAndCleanup failed for ' . BackendUtils::CLS_TMM_SUBSCRIPTIONS);
                }
            }
        }
        return $this->utils->getUserSettings();
    }


    private function makeErrorJson(string $error): string
    {
        return json_encode(["error" => $error]);
    }

    private function logException(\Throwable $e)
    {
        $this->logger->error($e->getMessage(), ['app' => $this->appName, 'exception' => $e]);
    }


    private function getPubURI(string $pageId): string
    {
        $pb = $this->utils->getPublicWebBase();
        $tkn = urlencode($this->utils->getUserSettings()[BackendUtils::KEY_TOKEN]);
        if (empty($tkn)) {
            return 'INVALID_TOKEN';
        }
        if ($this->utils->isDir($pageId) === false) {
            return $pb . '/' . $this->utils->pubPrx($tkn, false) . 'form' . chr(31)
                . $pb . '/' . $this->utils->pubPrx($tkn, true) . 'form';
        } else {
            return $pb . '/dir/' . $tkn . chr(31);
        }
    }

    private function getCalList(bool $isTemplateMode)
    {
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
        if ($isTemplateMode) {
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

    private function calgetweek($pageId)
    {
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
            $this->logger->error($e->getMessage() . ", timezone: " . $utz->getName());
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
     * @param string $t JSON string {
     *      "type": "empty|both" ,
     *      "before": 1|7,
     *      ["delete":boolean]
     * }
     * @param string $pageId
     * @return SendDataResponse
     */
    private function calGetOld($t, $pageId)
    {

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
            $this->logger->error($e->getMessage() . ", timezone: " . $utz->getName());
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

}
