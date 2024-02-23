<?php


declare(strict_types=1);

namespace OCA\Appointments\Migration;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020000Date20240221T001 extends SimpleMigrationStep
{

    protected IDBConnection $connection;

    public function __construct(IDBConnection $connection)
    {
        $this->connection = $connection;
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable(BackendUtils::PREF_TABLE_NAME)
            && !$schema->hasTable(BackendUtils::PREF_TABLE_V2_NAME)) {

            // we need to add the BackendUtils::PREF_TABLE_V2_NAME
            $output->info("Creating " . BackendUtils::PREF_TABLE_V2_NAME . " table");

            $table = $schema->createTable(BackendUtils::PREF_TABLE_V2_NAME);

            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 11,
                'unsigned' => true,
            ]);
            $table->setPrimaryKey(['id']);

            $table->addColumn(BackendUtils::KEY_TOKEN, 'string', [
                'notnull' => true,
                'length' => 96
            ]);
            $table->addColumn(BackendUtils::KEY_USER_ID, 'string', [
                'notnull' => true,
                'length' => 64
            ]);
            $table->addColumn(BackendUtils::KEY_PAGE_ID, 'string', [
                'notnull' => true,
                'length' => 3 // p0 -> p99
            ]);
            $table->addColumn(BackendUtils::KEY_DATA, 'text', [
                'notnull' => true,
                'length' => 65535 // MySQL TEXT vs LONGTEXT
            ]);
            $table->addColumn(BackendUtils::KEY_REMINDERS, 'text', [
                'notnull' => false,
                'length' => 65535 // MySQL TEXT vs LONGTEXT
            ]);


            $indexPrefix = "appt_";

            $index_name = $indexPrefix . '_' . BackendUtils::KEY_USER_ID . '_index';
            if (!$table->hasIndex($index_name)) {
                $table->addIndex([BackendUtils::KEY_USER_ID], $index_name);
            }
            $index_name = $indexPrefix . '_' . BackendUtils::KEY_TOKEN . '_index';
            if (!$table->hasIndex($index_name)) {
                $table->addUniqueIndex([BackendUtils::KEY_TOKEN], $index_name);
            }
            $index_name = $indexPrefix . '_user_page_constr';
            if (!$table->hasIndex($index_name)) {
                $table->addUniqueConstraint([BackendUtils::KEY_USER_ID, BackendUtils::KEY_PAGE_ID], $index_name);
            }
        }
        return $schema;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options)
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable(BackendUtils::PREF_TABLE_NAME)
            && $schema->hasTable(BackendUtils::PREF_TABLE_V2_NAME)) {

            $qb = $this->connection->getQueryBuilder();

            try {
                $result = $qb->select('*')
                    ->from(BackendUtils::PREF_TABLE_NAME)
                    ->execute();
            } catch (\Throwable $e) {
                $output->warning('could execute select query: ' . $e->getMessage());
                return;
            }
            while ($row = $result->fetch()) {

                $userId = $row['user_id'];

                if (gettype($row[BackendUtils::KEY_PAGES]) === 'NULL') {
                    $row[BackendUtils::KEY_PAGES] = json_encode([
                        'p0' => [
                            BackendUtils::PAGES_ENABLED => 0,
                            BackendUtils::PAGES_LABEL => ''
                        ]
                    ]);
                }

                $pages = json_decode($row[BackendUtils::KEY_PAGES], true);

                if ($pages !== null) {
                    // first get p0 info and use it as defaults
                    $page_info = $pages['p0'];

                    $page0 = [
                        BackendUtils::KEY_USER_ID => $userId,
                        BackendUtils::KEY_TOKEN => urldecode($this->getTokenV1($userId, 'p0')),
                        BackendUtils::KEY_PAGE_ID => 'p0',
                    ];

                    $templates_data = json_decode($row[BackendUtils::KEY_TMPL_DATA] ?? (string)null, true) ?? [];
                    $templates_info = json_decode($row[BackendUtils::KEY_TMPL_INFO] ?? (string)null, true) ?? [];

                    $reminders = json_decode($row[BackendUtils::KEY_REMINDERS] ?? (string)null, true);

                    $data = [
                        BackendUtils::PAGE_ENABLED => (bool)$page_info[BackendUtils::PAGES_ENABLED],
                        BackendUtils::PAGE_LABEL => $page_info[BackendUtils::PAGES_LABEL]
                    ];

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_ORG);
                    $this->addArrayToData($temp, $data);

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_CLS);
                    $this->addArrayToData($temp, $data);

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_EML);
                    $this->addArrayToData($temp, $data);

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_PSN);
                    $this->addArrayToData($temp, $data);

                    if (isset($templates_data['p0'])) {
                        $data[BackendUtils::KEY_TMPL_DATA] = $templates_data['p0'];
                    }
                    if (isset($templates_info['p0'])) {
                        $data[BackendUtils::KEY_TMPL_INFO] = $templates_info['p0'];
                    }

                    if (isset($row[BackendUtils::KEY_FORM_INPUTS_HTML])) {
                        $data[BackendUtils::KEY_FORM_INPUTS_HTML] = $row[BackendUtils::KEY_FORM_INPUTS_HTML];
                    }
                    if (isset($row[BackendUtils::KEY_FORM_INPUTS_JSON])) {
                        $data[BackendUtils::KEY_FORM_INPUTS_JSON] = json_decode($row[BackendUtils::KEY_FORM_INPUTS_JSON], true);
                    }

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_TALK);
                    $this->addArrayToData($temp, $data, 'talk_');

                    $temp = $this->getArrayForKey($row, BackendUtils::KEY_DEBUGGING);
                    $this->addArrayToData($temp, $data);

                    $page0[BackendUtils::KEY_DATA] = $this->filterDefaultSettings($data);

                    $page0[BackendUtils::KEY_REMINDERS] = $reminders;


                    $this->insertRowV2($page0);

                    // secondary pages -------------

                    $mps_data = json_decode($row[BackendUtils::KEY_MPS_COL] ?? (string)null, true) ?? [];

                    foreach ($pages as $page_id => $page_info) {

                        if ($page_id === 'p0') {
                            continue;
                        }

                        $newPage = [];

                        $newPage[BackendUtils::KEY_USER_ID] = $userId;
                        $newPage[BackendUtils::KEY_TOKEN] = urldecode($this->getTokenV1($userId, $page_id));
                        $newPage[BackendUtils::KEY_PAGE_ID] = $page_id;

                        $newPageData = $data;

                        $newPageData[BackendUtils::PAGE_ENABLED] = (bool)$page_info[BackendUtils::PAGES_ENABLED];
                        $newPageData[BackendUtils::PAGE_LABEL] = $page_info[BackendUtils::PAGES_LABEL];

                        if (isset($mps_data[$page_id])) {
                            $mps = $mps_data[$page_id];
                            foreach ($mps as $k => $v) {

                                if ($k === BackendUtils::ORG_EMAIL) {
                                    // only one email was allowed
                                    continue;
                                }

                                if (
                                    // these keys only replace defaults if they are NOT empty
                                    $k === BackendUtils::ORG_NAME ||
                                    $k === BackendUtils::ORG_ADDR ||
                                    $k === BackendUtils::ORG_PHONE ||
                                    $k === BackendUtils::PSN_FORM_TITLE
                                ) {
                                    if (!empty($v)) {
                                        $newPageData[$k] = $v;
                                    } //else use default
                                    continue;
                                }

                                // override defaults
                                $newPageData[$k] = $v;
                            }
                        }

                        if (isset($templates_data[$page_id])) {
                            $newPageData[BackendUtils::KEY_TMPL_DATA] = $templates_data[$page_id];
                        }
                        if (isset($templates_info[$page_id])) {
                            $newPageData[BackendUtils::KEY_TMPL_INFO] = $templates_info[$page_id];
                        }

                        $newPage[BackendUtils::KEY_DATA] = $this->filterDefaultSettings($newPageData);

                        $newPage[BackendUtils::KEY_REMINDERS] = $reminders;


                        $this->insertRowV2($newPage);
                    }

                    // dir page ----------------------
                    if (isset($row[BackendUtils::KEY_DIR])) {
                        $dirItems = json_decode($row[BackendUtils::KEY_DIR], true);
                        if (!empty($dirItems)) {

                            $psn_data = json_decode($row[BackendUtils::KEY_PSN] ?? (string)null, true) ?? [];

                            $dirPage = [
                                BackendUtils::KEY_USER_ID => $page0[BackendUtils::KEY_USER_ID],
                                BackendUtils::KEY_TOKEN => $page0[BackendUtils::KEY_TOKEN] . "dir",
                                BackendUtils::KEY_PAGE_ID => 'd0',
                                BackendUtils::KEY_DATA => [
                                    BackendUtils::DIR_ITEMS => $dirItems,
                                    BackendUtils::PSN_PAGE_TITLE => $psn_data[BackendUtils::PSN_PAGE_TITLE] ?? "",
                                    BackendUtils::PSN_PAGE_STYLE => $psn_data[BackendUtils::PSN_PAGE_STYLE] ?? "",
                                    BackendUtils::PSN_USE_NC_THEME => $psn_data[BackendUtils::PSN_USE_NC_THEME] ?? false,
                                    BackendUtils::CLS_PRIVATE_PAGE => false,
                                ],
                                BackendUtils::KEY_REMINDERS => null
                            ];

                            $this->insertRowV2($dirPage);
                        }
                    }

                } else {
                    $output->warning("json_decode of 'pages' var failed for user " . $userId);
                }
            }
            $result->closeCursor();
        } else {
            $output->warning('Could not finish V2.0.0 migration: missing PREF_ tables');
        }
    }

    private function insertRowV2(array $data)
    {
        $qb = $this->connection->getQueryBuilder();
        $qb->insert(BackendUtils::PREF_TABLE_V2_NAME);

        $c = 0;
        foreach ($data as $k => $v) {
            $qb->setValue($k, '?');
            if (gettype($v) === "array") {
                $sv = json_encode($v);
            } else {
                $sv = $v;
            }
            $qb->setParameter($c, $sv);
            $c++;
        }
        $qb->executeStatement();
    }

    private function getArrayForKey($row, $key): array
    {

        $default = $this->getDefaultForKey($key);

        $data = json_decode($row[$key] ?? (string)null, true) ?? [];

        if (empty($data)) {
            return $default;
        }

        foreach ($default as $k => $v) {
            if (!isset($data[$k])) {
                $data[$k] = $v;
            }
        }

        return $data;
    }

    private function addArrayToData($arr, &$data, $v2prefix = ""): void
    {
        foreach ($arr as $k => $v) {
            $data[$v2prefix . $k] = $v;
        }
    }


    private function getTokenV1($userId, $pageId): string
    {
        $config = \OC::$server->getConfig();
        $key = hex2bin($config->getAppValue(Application::APP_ID, 'hk'));
        $iv = hex2bin($config->getAppValue(Application::APP_ID, 'tiv'));
        if (empty($key) || empty($iv)) {
            throw new \ErrorException("Can't find key");
        }
        if ($pageId === "p0") {
            $pfx = '';
            $upi = $userId;
        } else {
            $pn = intval(substr($pageId, 1));
            if ($pn < 1 || $pn > 14) {
                throw new \ErrorException("Bad page number");
            }
            $pfx = ($iv[0] ^ $iv[15]) ^ $iv[$pn];

            $iv = $pfx . substr($iv, 1);
            $upi = $userId . chr($pn);
        }

        $tkn = $this->encrypt(
            hash('adler32', $upi, true) . $upi, $key, $iv);

        if ($pfx === '') {
            $bd = base64_encode($tkn);
        } else {
            $v = base64_encode($tkn . $pfx);
            $bd = trim($v, '=');
            $ld = strlen($v) - strlen($bd);
            if ($ld === 1) {
                $bd .= "01";
            } else {
                $bd .= $ld;
            }
        }
        return urlencode(str_replace("/", "_", $bd));
    }

    private function filterDefaultSettings(array $data): array
    {
        $defaults = $this->getDefaultSettingsData();

        // we don't want 'reminders' here
        if (isset($defaults[BackendUtils::KEY_REMINDERS])) {
            unset($defaults[BackendUtils::KEY_REMINDERS]);
        }

        $filteredData = [];
        foreach ($data as $k => $v) {
            if (isset($defaults[$k]) && $defaults[$k] !== $v) {
                $filteredData[$k] = $v;
            }
        }
        return $filteredData;
    }


    // same as in utils
    private function encrypt(string $data, string $key, $iv = ''): string
    {
        $cipher = "AES-128-CFB";
        if ($iv === '') {
            $iv = $_iv = openssl_random_pseudo_bytes(
                openssl_cipher_iv_length($cipher));
        } else {
            $_iv = '';
        }
        $ciphertext_raw = openssl_encrypt(
            $data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv);

        return $_iv !== ''
            ? base64_encode($_iv . $ciphertext_raw)
            : $_iv . $ciphertext_raw;
    }

    // same as in utils
    private function getDefaultForKey($key): ?array
    {
        switch ($key) {
            case BackendUtils::KEY_ORG:
                $d = array(
                    BackendUtils::ORG_NAME => "",
                    BackendUtils::ORG_EMAIL => "",
                    BackendUtils::ORG_ADDR => "",
                    BackendUtils::ORG_PHONE => "",
                    BackendUtils::ORG_CONFIRMED_RDR_URL => "",
                    BackendUtils::ORG_CONFIRMED_RDR_ID => false,
                    BackendUtils::ORG_CONFIRMED_RDR_DATA => false,
                );
                break;
            case BackendUtils::KEY_EML:
                $d = array(
                    BackendUtils::EML_ICS => false,
                    BackendUtils::EML_SKIP_EVS => false,
                    BackendUtils::EML_AMOD => true,
                    BackendUtils::EML_ADEL => true,
                    BackendUtils::EML_MREQ => false,
                    BackendUtils::EML_MCONF => false,
                    BackendUtils::EML_MCNCL => false,
                    BackendUtils::EML_VLD_TXT => "",
                    BackendUtils::EML_CNF_TXT => "",
                    BackendUtils::EML_ICS_TXT => "");
                break;
            case BackendUtils::KEY_CLS:
                $d = array(
                    BackendUtils::CLS_MAIN_ID => '-1',
                    BackendUtils::CLS_DEST_ID => '-1',

                    BackendUtils::CLS_XTM_SRC_ID => '-1',
                    BackendUtils::CLS_XTM_DST_ID => '-1',
                    BackendUtils::CLS_XTM_PUSH_REC => true,
                    BackendUtils::CLS_XTM_REQ_CAT => false,
                    BackendUtils::CLS_XTM_AUTO_FIX => false,

                    BackendUtils::CLS_TMM_DST_ID => '-1',
                    BackendUtils::CLS_TMM_MORE_CALS => [],
                    BackendUtils::CLS_TMM_SUBSCRIPTIONS => [],
                    BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC => '0',

                    BackendUtils::CLS_PREP_TIME => "0",
                    BackendUtils::CLS_BUFFER_BEFORE => 0,
                    BackendUtils::CLS_BUFFER_AFTER => 0,
                    BackendUtils::CLS_ON_CANCEL => 'mark',
                    BackendUtils::CLS_ALL_DAY_BLOCK => false,
                    BackendUtils::CLS_TITLE_TEMPLATE => "",

                    BackendUtils::CLS_PRIVATE_PAGE => false,
                    BackendUtils::CLS_TS_MODE => BackendUtils::CLS_TS_MODE_TEMPLATE);
                break;
            case BackendUtils::KEY_PSN:
                $d = array(
                    BackendUtils::PSN_FORM_TITLE => "",
                    BackendUtils::PSN_NWEEKS => "2",
                    BackendUtils::PSN_EMPTY => true,
                    BackendUtils::PSN_FNED => false, // start at first not empty day
                    BackendUtils::PSN_WEEKEND => false,
                    BackendUtils::PSN_TIME2 => false,
                    BackendUtils::PSN_END_TIME => false,
                    BackendUtils::PSN_HIDE_TEL => false,
                    BackendUtils::PSN_SHOW_TZ => false,
                    BackendUtils::PSN_GDPR => "",
                    BackendUtils::PSN_GDPR_NO_CHB => false,
                    BackendUtils::PSN_PAGE_TITLE => "",
                    BackendUtils::PSN_PAGE_SUB_TITLE => "",
                    BackendUtils::PSN_META_NO_INDEX => false,
                    BackendUtils::PSN_PAGE_STYLE => "",
                    BackendUtils::PSN_USE_NC_THEME => false);
                break;
            case BackendUtils::KEY_MPS_COL:
                $d = null;
                break;
            case BackendUtils::KEY_MPS:
                $d = array(
                    BackendUtils::CLS_MAIN_ID => '-1',
                    BackendUtils::CLS_DEST_ID => '-1',
                    BackendUtils::CLS_XTM_SRC_ID => '-1',
                    BackendUtils::CLS_XTM_DST_ID => '-1',
                    BackendUtils::CLS_TMM_DST_ID => '-1',
                    BackendUtils::CLS_TMM_MORE_CALS => [],
                    BackendUtils::CLS_TMM_SUBSCRIPTIONS => [],

                    BackendUtils::CLS_BUFFER_BEFORE => 0,
                    BackendUtils::CLS_BUFFER_AFTER => 0,

                    BackendUtils::CLS_PRIVATE_PAGE => false,
                    BackendUtils::CLS_TS_MODE => BackendUtils::CLS_TS_MODE_TEMPLATE,

                    BackendUtils::ORG_NAME => "",
                    BackendUtils::ORG_EMAIL => "",
                    BackendUtils::ORG_ADDR => "",
                    BackendUtils::ORG_PHONE => "",

                    BackendUtils::ORG_CONFIRMED_RDR_URL => "",
                    BackendUtils::ORG_CONFIRMED_RDR_ID => false,
                    BackendUtils::ORG_CONFIRMED_RDR_DATA => false,

                    BackendUtils::PSN_FORM_TITLE => "");
                break;
            case BackendUtils::KEY_PAGES:
                $d = array('p0' => BackendUtils::PAGES_VAL_DEF);
                break;
            case BackendUtils::KEY_TALK:
                $d = array(
                    BackendUtils::TALK_ENABLED => false,
                    BackendUtils::TALK_DEL_ROOM => false,
                    BackendUtils::TALK_EMAIL_TXT => "",
                    BackendUtils::TALK_LOBBY => false,
                    BackendUtils::TALK_PASSWORD => false,
                    // 0=Name+DT, 1=DT+Name, 2=Name Only
                    BackendUtils::TALK_NAME_FORMAT => 0,
                    BackendUtils::TALK_FORM_ENABLED => false,
                    BackendUtils::TALK_FORM_LABEL => "",
                    BackendUtils::TALK_FORM_PLACEHOLDER => "",
                    BackendUtils::TALK_FORM_REAL_TXT => "",
                    BackendUtils::TALK_FORM_VIRTUAL_TXT => "",
                    BackendUtils::TALK_FORM_TYPE_CHANGE_TXT => "");
                break;
            case BackendUtils::KEY_DIR:
            case BackendUtils::KEY_TMPL_DATA:
                $d = array();
                break;
            case BackendUtils::KEY_TMPL_INFO:
                $d = array('p0' => array(
                    BackendUtils::TMPL_TZ_NAME => "",
                    BackendUtils::TMPL_TZ_DATA => "")
                );
                break;
            case BackendUtils::KEY_REMINDERS:
                $d = array(
                    BackendUtils::REMINDER_DATA => [
                        [
                            BackendUtils::REMINDER_DATA_TIME => "0",
                            BackendUtils::REMINDER_DATA_ACTIONS => true
                        ],
                        [
                            BackendUtils::REMINDER_DATA_TIME => "0",
                            BackendUtils::REMINDER_DATA_ACTIONS => true
                        ],
                        [
                            BackendUtils::REMINDER_DATA_TIME => "0",
                            BackendUtils::REMINDER_DATA_ACTIONS => true
                        ],
                    ],
                    BackendUtils::REMINDER_SEND_ON_FRIDAY => false,
                    BackendUtils::REMINDER_MORE_TEXT => "");
                break;
            case BackendUtils::KEY_DEBUGGING:
                $d = array(
                    BackendUtils::DEBUGGING_LOG_REM_BLOCKER => false
                );
                break;
            default:
                $d = null;
        }
        return $d;
    }

    // same as in utils
    private function getDefaultSettingsData(): array
    {
        return [
            BackendUtils::PAGE_ENABLED => false,
            BackendUtils::PAGE_LABEL => "",

            BackendUtils::ORG_NAME => "",
            BackendUtils::ORG_EMAIL => "",
            BackendUtils::ORG_ADDR => "",
            BackendUtils::ORG_PHONE => "",

            BackendUtils::ORG_CONFIRMED_RDR_URL => "",
            BackendUtils::ORG_CONFIRMED_RDR_ID => false,
            BackendUtils::ORG_CONFIRMED_RDR_DATA => false,

            BackendUtils::CLS_MAIN_ID => '-1',
            BackendUtils::CLS_DEST_ID => '-1',

            BackendUtils::CLS_XTM_SRC_ID => '-1',
            BackendUtils::CLS_XTM_DST_ID => '-1',
            BackendUtils::CLS_XTM_PUSH_REC => true,
            BackendUtils::CLS_XTM_REQ_CAT => false,
            BackendUtils::CLS_XTM_AUTO_FIX => false,

            BackendUtils::CLS_TMM_DST_ID => '-1',
            BackendUtils::CLS_TMM_MORE_CALS => [],
            BackendUtils::CLS_TMM_SUBSCRIPTIONS => [],
            BackendUtils::CLS_TMM_SUBSCRIPTIONS_SYNC => '0',

            BackendUtils::CLS_PREP_TIME => "0",
            BackendUtils::CLS_BUFFER_BEFORE => 0,
            BackendUtils::CLS_BUFFER_AFTER => 0,
            BackendUtils::CLS_ON_CANCEL => 'reset',
            BackendUtils::CLS_ALL_DAY_BLOCK => false,
            BackendUtils::CLS_TITLE_TEMPLATE => '',

            BackendUtils::CLS_PRIVATE_PAGE => false,
            BackendUtils::CLS_TS_MODE => BackendUtils::CLS_TS_MODE_TEMPLATE,

            BackendUtils::EML_ICS => false,
            BackendUtils::EML_SKIP_EVS => false,
            BackendUtils::EML_AMOD => true,
            BackendUtils::EML_ADEL => true,
            BackendUtils::EML_MREQ => false,
            BackendUtils::EML_MCONF => true,
            BackendUtils::EML_MCNCL => false,
            BackendUtils::EML_VLD_TXT => "",
            BackendUtils::EML_CNF_TXT => "",
            BackendUtils::EML_ICS_TXT => "",

            BackendUtils::PSN_FORM_TITLE => "",
            BackendUtils::PSN_NWEEKS => "2",
            BackendUtils::PSN_EMPTY => true,
            BackendUtils::PSN_FNED => false, // start at first not empty day
            BackendUtils::PSN_WEEKEND => false,
            BackendUtils::PSN_TIME2 => false,
            BackendUtils::PSN_END_TIME => false,
            BackendUtils::PSN_HIDE_TEL => false,
            BackendUtils::PSN_SHOW_TZ => false,
            BackendUtils::PSN_GDPR => "",
            BackendUtils::PSN_GDPR_NO_CHB => false,
            BackendUtils::PSN_PAGE_TITLE => "",
            BackendUtils::PSN_PAGE_SUB_TITLE => "",
            BackendUtils::PSN_META_NO_INDEX => true,
            BackendUtils::PSN_PAGE_STYLE => "",
            BackendUtils::PSN_USE_NC_THEME => false,

            BackendUtils::KEY_TMPL_DATA => [[], [], [], [], [], [], []],
            BackendUtils::KEY_TMPL_INFO => [
                BackendUtils::TMPL_TZ_NAME => "",
                BackendUtils::TMPL_TZ_DATA => ""
            ],

            BackendUtils::KEY_FORM_INPUTS_HTML => "",
            BackendUtils::KEY_FORM_INPUTS_JSON => [],

            BackendUtils::TALK_ENABLED => false,
            BackendUtils::TALK_DEL_ROOM => false,
            BackendUtils::TALK_EMAIL_TXT => "",
            BackendUtils::TALK_LOBBY => false,
            BackendUtils::TALK_PASSWORD => false,
            // 0=Name+DT, 1=DT+Name, 2=Name Only
            BackendUtils::TALK_NAME_FORMAT => 0,
            BackendUtils::TALK_FORM_ENABLED => false,
            BackendUtils::TALK_FORM_LABEL => "",
            BackendUtils::TALK_FORM_PLACEHOLDER => "",
            BackendUtils::TALK_FORM_REAL_TXT => "",
            BackendUtils::TALK_FORM_VIRTUAL_TXT => "",
            BackendUtils::TALK_FORM_TYPE_CHANGE_TXT => "",
            BackendUtils::TALK_FORM_DEF_LABEL => 'Meeting Type',
            BackendUtils::TALK_FORM_DEF_PLACEHOLDER => 'Select meeting type',
            BackendUtils::TALK_FORM_DEF_REAL => 'In-person meeting',
            BackendUtils::TALK_FORM_DEF_VIRTUAL => 'Online (audio/video)',

            BackendUtils::KEY_REMINDERS => [
                BackendUtils::REMINDER_DATA => [
                    [
                        BackendUtils::REMINDER_DATA_TIME => "0",
                        BackendUtils::REMINDER_DATA_ACTIONS => true
                    ],
                    [
                        BackendUtils::REMINDER_DATA_TIME => "0",
                        BackendUtils::REMINDER_DATA_ACTIONS => true
                    ],
                    [
                        BackendUtils::REMINDER_DATA_TIME => "0",
                        BackendUtils::REMINDER_DATA_ACTIONS => true
                    ],
                ],
                BackendUtils::REMINDER_SEND_ON_FRIDAY => false,
                BackendUtils::REMINDER_MORE_TEXT => ""
            ],

            BackendUtils::DEBUGGING_LOG_REM_BLOCKER => false,
        ];
    }


}
