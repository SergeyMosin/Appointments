<?php
/** @noinspection PhpMissingParamTypeInspection */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace OCA\Appointments\Controller;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\BbbIntegration;
use OCA\Appointments\Backend\IBackendConnector;
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

    public function __construct($AppName, $UserId,
                                IRequest $request,
                                IConfig $config,
                                IL10N $l,
                                BackendUtils $utils,
                                BackendManager $backendManager,
                                LoggerInterface $logger)
    {
        parent::__construct($AppName, $request);

        $this->userId = $UserId;
        $this->config = $config;
        $this->l = $l;
        $this->utils = $utils;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
        $this->logger = $logger;
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
        return str_replace('<?', '&lt;?', preg_replace('#<script(.*?)>(.*?)</script>#is', '', $text));
    }

    private function updateSettings(string $pageId): array
    {
        $key = $this->request->getParam("k");
        $value = $this->request->getParam("v");

        if ($key === "__ckey") {
            return $this->setUserKey($value);
        }

        $settings = $this->utils->getUserSettings();

        if (empty($key) || !isset($settings[$key])
            || gettype($settings[$key]) !== gettype($value)) {
            $this->logger->warning("setUserSettingsV2: invalid key or value - " . $key);
            return [Http::STATUS_BAD_REQUEST, ''];
        }

        if (is_string($value)) {
            $value = $this->regexRemoveScriptTag($value);
        }

        if ($this->utils->isDir($pageId)) {
            return $this->updateDirSettings($pageId, $key, $value);
        }

        $validator = $this->getValidator($key);
        if ($validator !== null) {
            /** @var array{ int, string }|null $validationResult [code,data] */
            $validationResult = $validator($value, $pageId, $key);
            if ($validationResult[0] !== Http::STATUS_OK) {
                return $validationResult;
            }
        }

        return $this->utils->setUserSettingsV2($this->userId, $pageId, $key, $value);
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

    private function getValidator(string $prop): ?\Closure
    {
        return match ($prop) {

            BackendUtils::PAGE_ENABLED => function ($value, $pageId, $key): array {
                if ($value === false) {
                    return [Http::STATUS_OK, ''];
                }
                $settings = $this->utils->getUserSettings();
                // check BackendUtils::ORG_EMAIL
                $orgEmail = $settings[BackendUtils::ORG_EMAIL];
                if (empty($orgEmail) || !filter_var($orgEmail, FILTER_VALIDATE_EMAIL)) {
                    // try to set BackendUtils::ORG_EMAIL to user's email
                    /** @var IUserManager $userManager */
                    $userManager = \OC::$server->get(IUserManager::class);
                    $orgEmail = $userManager->get($this->userId)->getEMailAddress();
                    if (empty($orgEmail) || !filter_var($orgEmail, FILTER_VALIDATE_EMAIL)) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 3,
                            "message" => $this->l->t("A valid email address is required.")
                        ])];
                    }
                    $res = $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::ORG_EMAIL, $orgEmail);
                    if ($res[0] !== Http::STATUS_OK) {
                        $res[1] = 'email configuration error';
                        return $res;
                    }
                }
                // BackendUtils::ORG_NAME
                if (empty($settings[BackendUtils::ORG_NAME])) {
                    /** @var IUserManager $userManager */
                    $userManager = \OC::$server->get(IUserManager::class);
                    $displayName = $userManager->getDisplayName($this->userId);
                    if (empty($displayName)) {
                        $displayName = substr($orgEmail, 0, strpos($orgEmail, '@'));
                    }
                    if (empty($displayName) || $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::ORG_NAME, $displayName)[0] !== Http::STATUS_OK) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 3,
                            "message" => $this->l->t("Can not determine or set organizer name")
                        ])];
                    }
                }

                // check if we at least have the main calendar
                if ($this->utils->getMainCalId($this->userId, $this->bc) === '-1') {
                    return [Http::STATUS_ACCEPTED, json_encode([
                        "type" => 3,
                        "message" => $this->l->t("Main calendar not found, check settings.")
                    ])];
                };
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_TMM_DST_ID => function ($calId, $pageId, $key): array {
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_TEMPLATE) {
                    // if CLS_TMM_DST_ID is in CLS_TMM_MORE_CALS we need to remove it
                    $conflictCalsIds = $settings[BackendUtils::CLS_TMM_MORE_CALS];
                    if (in_array($calId, $conflictCalsIds)) {
                        return $this->utils->setUserSettingsV2($this->userId, $pageId,
                            BackendUtils::CLS_TMM_MORE_CALS,
                            array_values(array_diff($conflictCalsIds, [$calId]))
                        );
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_TMM_MORE_CALS, BackendUtils::CLS_TMM_SUBSCRIPTIONS => function ($value, $pageId, $key): array {
                $d = $this->config->getUserValue($this->userId, $this->appName, "cnk");
                if ($d === "" || ((hexdec(substr($d, 0, 4)) >> 15) & 1) !== ((hexdec(substr($d, 4, 4)) >> 12) & 1)) {

                    $calCount = count($value);
                    $settings = $this->utils->getUserSettings();
                    if ($key === BackendUtils::CLS_TMM_MORE_CALS) {
                        $calCount += count($settings[BackendUtils::CLS_TMM_SUBSCRIPTIONS]);
                    } else {
                        $calCount += count($settings[BackendUtils::CLS_TMM_MORE_CALS]);
                    }
                    if ($calCount > 2) {
                        return [Http::STATUS_ACCEPTED, json_encode([
                            "type" => 2,
                            "message" => $this->l->t("More than 2 additional calendars.")
                        ])];
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::CLS_BUFFER_BEFORE, BackendUtils::CLS_BUFFER_AFTER => function ($value, $pageId, $key): array {
                if ($key === BackendUtils::CLS_BUFFER_BEFORE) {
                    $buf2 = BackendUtils::CLS_BUFFER_AFTER;
                } else {
                    $buf2 = BackendUtils::CLS_BUFFER_BEFORE;
                }
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE && $value !== 0) {
                    return [Http::STATUS_BAD_REQUEST, $this->makeErrorJson('in simple mode buffers must be 0')];
                }
                if ($value < 0 || $value > 120) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }
                // for now the "after" buffer must be the same as the "before" buffer because there is really no good way to deal with buffer overlap when bufferBefore != afterBuffer
                return $this->utils->setUserSettingsV2($this->userId, $pageId, $buf2, $value);
            },

            BackendUtils::CLS_DEST_ID => function (&$value, $pageId, $key): array {
                $settings = $this->utils->getUserSettings();
                if ($settings[BackendUtils::CLS_MAIN_ID] === $value) {
                    // '-1' = use mail calendar
                    $value = '-1';
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_TMPL_DATA => function (&$value, $page, $key): array {
                if (!is_array($value) || count($value) !== 7) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }

                $maxDurCount = empty($this->config->getUserValue($this->userId, $this->appName, "cnk")) ? 2 : 8;

                for ($i = 0; $i < 7; ++$i) {
                    $day = $value[$i];
                    if (!is_array($day)) {
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                    $spotsCount = count($day);
                    for ($j = 0; $j < $spotsCount; ++$j) {
                        $spot = $day[$j];
                        if (!is_array($spot)
                            || !array_key_exists('start', $spot)
                            || !array_key_exists('dur', $spot)
                            || !array_key_exists('title', $spot)) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                        if (count($spot['dur']) > $maxDurCount) {
                            array_splice($value[$i][$j]['dur'], $maxDurCount);
                        }
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_REMINDERS => function (&$value, $pageId, $key): array {

                $valid = $this->utils->getDefaultSettingsData()[BackendUtils::KEY_REMINDERS];
                foreach ($valid as $k => $v) {
                    if (!isset($value[$k]) || gettype($value[$k]) !== gettype($v)) {
                        $this->logger->error('bad/missing "' . $k . '" key in reminders data');
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                }
                if (count($valid) !== count($value) || count($valid['data']) !== count($value['data'])) {
                    $this->logger->error('bad count reminders data');
                    return [Http::STATUS_BAD_REQUEST, ''];
                }

                $allowed_values = ["0", "3600", "7200", "14400", "28800", "86400"];
                $k = $this->config->getUserValue($this->userId, $this->appName, "cn" . "k");
                if ($k !== "" && ((hexdec(substr($k, 0, 4)) >> 15) & 1) === ((hexdec(substr($k, 4, 4)) >> 12) & 1)) {
                    array_push($allowed_values, "172800", "259200", "345600", "432000", "518400", "604800");
                }

                foreach ($value[BackendUtils::REMINDER_DATA] as $index => &$item) {
                    if (!in_array($item[BackendUtils::REMINDER_DATA_TIME], $allowed_values, true)
                        || ($k === "" && $index > 0)
                    ) {
                        $item[BackendUtils::REMINDER_DATA_TIME] = "0";
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::KEY_FORM_INPUTS_JSON => function (&$value, $pageId, $key): array {
                $inputsHtml = $this->makeFormComponent($value);
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::KEY_FORM_INPUTS_HTML, $inputsHtml);
            },

            BackendUtils::TALK_ENABLED => function (&$value, $pageId, $key) {
                if ($value === false) {
                    // nothing to do
                    return [Http::STATUS_OK, ''];
                }
                if (!TalkIntegration::canTalk()) {
                    $value = false;
                    return [Http::STATUS_OK, ''];
                }
                // ensure that BBB is unset
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::BBB_ENABLED, false);
            },

            BackendUtils::BBB_ENABLED => function (&$value, $pageId, $key) {
                if ($value === false) {
                    // nothing to do
                    return [Http::STATUS_OK, ''];
                }
                if (!\OC::$server->get(BbbIntegration::class)->canBBB()) {
                    $value = false;
                    return [Http::STATUS_OK, ''];
                }
                // ensure that TALK is unset
                return $this->utils->setUserSettingsV2($this->userId, $pageId, BackendUtils::TALK_ENABLED, false);
            },

            BackendUtils::SEC_EMAIL_BLACKLIST => function ($value, $pageId, $key) {
                if (!is_array($value)) {
                    return [Http::STATUS_BAD_REQUEST, ''];
                }
                foreach ($value as $item) {
                    if (strlen($item) < 4) {
                        return [Http::STATUS_BAD_REQUEST, ''];
                    }
                    if (str_starts_with($item, '*@')) {
                        // should be a valid domain after the '*@'
                        if (filter_var(substr($item, 2), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                    } else {
                        // this should be a valid email
                        if (filter_var($item, FILTER_VALIDATE_EMAIL) === false) {
                            return [Http::STATUS_BAD_REQUEST, ''];
                        }
                    }
                }
                return [Http::STATUS_OK, ''];
            },

            BackendUtils::PSN_FORM_FINISH_TEXT => function (&$value, $pageId, $key) {
                if (!empty($value)) {
                    $value = strip_tags($value, '<div><p><span><br>');
                }
                return [Http::STATUS_OK, ''];
            },

            default => null,
        };
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
        return '{"error":"' . $error . '"}';
    }

    private function logException(\Throwable $e)
    {
        $this->logger->error($e->getMessage(), ['app' => $this->appName, 'exception' => $e]);
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

}
