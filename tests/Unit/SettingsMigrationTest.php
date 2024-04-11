<?php
//
//namespace Unit;
//
//use OC\DB\Connection;
//use OC\DB\SchemaWrapper;
//use OC\Migration\SimpleOutput;
//use OCA\Appointments\AppInfo\Application;
//use OCA\Appointments\Backend\BackendUtils;
//use OCA\Appointments\Migration\Version020000Date20240221T001;
//use OCP\IDBConnection;
//use OCP\IURLGenerator;
//use OCP\L10N\IFactory;
//use PHPUnit\Framework\TestCase;
//use Psr\Log\AbstractLogger;
//use Psr\Log\LogLevel;
//
//class SettingsMigrationTest extends TestCase
//{
//
//    const BACKUP_TABLE = "appt_pref_bak";
//
//    /** @var AbstractLogger */
//    private $logger;
//
//    /** @var IDBConnection */
//    private $db;
//
//    /** @var SchemaWrapper */
//    private $schema;
//
//    /** @var BackendUtils */
//    private $utils;
//
//    function setUp(): void
//    {
//        $this->logger = new ConsoleLogger();
//
//        $app = new Application();
//        $container = $app->getContainer();
//
//        $this->db = $container->get(IDBConnection::class);
//        $this->schema = new SchemaWrapper($container->get(Connection::class));
//
//        $this->utils = new BackendUtils(
//            $this->logger,
//            $this->db,
//            $container->get(IURLGenerator::class),
//            $container->get(IFactory::class)->get($app, null)
//        );
//    }
//
//
//    function testMigrationClass()
//    {
//        $migration = new Version020000Date20240221T001($this->db);
//
//        $output = new SimpleOutput($this->logger, Application::APP_ID);
//
//        $schemaClosure = function () {
//            return $this->schema;
//        };
//
//        $schema = $migration->changeSchema($output, $schemaClosure, []);
//        $this->db->migrateToSchema($schema->getWrappedSchema());
//
//        $migration->postSchemaChange($output, $schemaClosure, []);
//
//        $this->assertEquals(1, 1);
//    }
//
//    function testCreatePrefTableV2()
//    {
//        $schema = $this->schema;
//
//        if (!$schema->hasTable(BackendUtils::PREF_TABLE_V2_NAME)) {
//
//            $this->logAny("Creating " . BackendUtils::PREF_TABLE_V2_NAME . " table");
//
//            $table = $schema->createTable(BackendUtils::PREF_TABLE_V2_NAME);
//
//            $table->addColumn('id', 'bigint', [
//                'autoincrement' => true,
//                'notnull' => true,
//                'length' => 11,
//                'unsigned' => true,
//            ]);
//            $table->setPrimaryKey(['id']);
//
//            $table->addColumn(BackendUtils::KEY_TOKEN, 'string', [
//                'notnull' => true,
//                'length' => 96
//            ]);
//            $table->addColumn(BackendUtils::KEY_USER_ID, 'string', [
//                'notnull' => true,
//                'length' => 64
//            ]);
//            $table->addColumn(BackendUtils::KEY_PAGE_ID, 'string', [
//                'notnull' => true,
//                'length' => 3 // p0 -> p99
//            ]);
//            $table->addColumn(BackendUtils::KEY_DATA, 'text', [
//                'notnull' => true,
//                'length' => 65535 // MySQL TEXT vs LONGTEXT
//            ]);
//            $table->addColumn(BackendUtils::KEY_REMINDERS, 'text', [
//                'notnull' => false,
//                'length' => 65535 // MySQL TEXT vs LONGTEXT
//            ]);
//
//            $index_name = BackendUtils::PREF_TABLE_V2_NAME . '_' . BackendUtils::KEY_USER_ID . '_index';
//            if (!$table->hasIndex($index_name)) {
//                $table->addIndex([BackendUtils::KEY_USER_ID], $index_name);
//            }
//            $index_name = BackendUtils::PREF_TABLE_V2_NAME . '_' . BackendUtils::KEY_TOKEN . '_index';
//            if (!$table->hasIndex($index_name)) {
//                $table->addUniqueIndex([BackendUtils::KEY_TOKEN], $index_name);
//            }
//            $index_name = BackendUtils::PREF_TABLE_V2_NAME . '_user_page_constr';
//            if (!$table->hasIndex($index_name)) {
//                $table->addUniqueConstraint([BackendUtils::KEY_USER_ID, BackendUtils::KEY_PAGE_ID], $index_name);
//            }
//
//            $this->db->migrateToSchema($schema->getWrappedSchema());
//
//        } else {
//            $this->logAny("Table " . BackendUtils::PREF_TABLE_V2_NAME . "already exist");
//        }
//
////        return $schema;
//
//        $this->assertEquals(true, $this->db->tableExists(BackendUtils::PREF_TABLE_V2_NAME));
//
//    }
//
//    function testPrintDefault()
//    {
//        $defaults = $this->utils->getDefaultSettingsData();
//        foreach ($defaults as $k => $v) {
//            $this->logAny($k . ":" . var_export($v, true) . ",");
//        }
//        $this->assertEquals(1, 1);
//    }
//
//    function testConvertDryRun()
//    {
//        $this->convertToV2(true);
//    }
//
//    function testConvert()
//    {
//        $this->convertToV2(false);
//    }
//
//    private function convertToV2($dryRun = true)
//    {
//
//        $qb = $this->db->getQueryBuilder();
//
//        try {
//            $result = $qb->select('*')
//                ->from(BackendUtils::PREF_TABLE_NAME)
//                ->execute();
//        } catch (\Throwable $e) {
//            $this->logger->error($e->getMessage());
//            $this->fail($e->getMessage());
//        }
//        while ($row = $result->fetch()) {
//
//            $userId = $row['user_id'];
//
//            $this->logAny('user_id: ' . $userId);
//
//            if (gettype($row[BackendUtils::KEY_PAGES]) === 'NULL') {
//                $row[BackendUtils::KEY_PAGES] = json_encode([
//                    'p0' => [
//                        BackendUtils::PAGES_ENABLED => 0,
//                        BackendUtils::PAGES_LABEL => ''
//                    ]
//                ]);
//            }
//
//            $pages = json_decode($row[BackendUtils::KEY_PAGES], true);
//
//            if ($pages !== null) {
//                // first get p0 info and use it as defaults
//                $page_info = $pages['p0'];
//
//                $page0 = [
//                    BackendUtils::KEY_USER_ID => $userId,
//                    BackendUtils::KEY_TOKEN => urldecode($this->getTokenV1($userId, 'p0')),
//                    BackendUtils::KEY_PAGE_ID => 'p0',
//                ];
//
//                $templates_data = json_decode($row[BackendUtils::KEY_TMPL_DATA] ?? null, true) ?? [];
//                $templates_info = json_decode($row[BackendUtils::KEY_TMPL_INFO] ?? null, true) ?? [];
//
//                $reminders = json_decode($row[BackendUtils::KEY_REMINDERS] ?? null, true);
//
//                $data = [
//                    BackendUtils::PAGE_ENABLED => (bool)$page_info[BackendUtils::PAGES_ENABLED],
//                    BackendUtils::PAGE_LABEL => $page_info[BackendUtils::PAGES_LABEL]
//                ];
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_ORG);
//                $this->addArrayToData($temp, $data);
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_CLS);
//                $this->addArrayToData($temp, $data);
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_EML);
//                $this->addArrayToData($temp, $data);
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_PSN);
//                $this->addArrayToData($temp, $data);
//
//                if (isset($templates_data['p0'])) {
//                    $data[BackendUtils::KEY_TMPL_DATA] = $templates_data['p0'];
//                }
//                if (isset($templates_info['p0'])) {
//                    $data[BackendUtils::KEY_TMPL_INFO] = $templates_info['p0'];
//                }
//
//                if (isset($row[BackendUtils::KEY_FORM_INPUTS_HTML])) {
//                    $data[BackendUtils::KEY_FORM_INPUTS_HTML] = $row[BackendUtils::KEY_FORM_INPUTS_HTML];
//                }
//                if (isset($row[BackendUtils::KEY_FORM_INPUTS_JSON])) {
//                    $data[BackendUtils::KEY_FORM_INPUTS_JSON] = json_decode($row[BackendUtils::KEY_FORM_INPUTS_JSON]);
//                }
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_TALK);
//                $this->addArrayToData($temp, $data, 'talk_');
//
//                $temp = $this->getArrayForKey($row, BackendUtils::KEY_DEBUGGING);
//                $this->addArrayToData($temp, $data);
//
//                $page0[BackendUtils::KEY_DATA] = $this->utils->filterDefaultSettings($data);
//
//                $page0[BackendUtils::KEY_REMINDERS] = $reminders;
//
//
//                if ($dryRun) {
//                    $this->logAny($page0);
//                } else {
//                    $this->insertRowV2($page0);
//                }
//
//                // secondary pages -------------
//
//                $mps_data = json_decode($row[BackendUtils::KEY_MPS_COL] ?? null, true) ?? [];
//
//                foreach ($pages as $page_id => $page_info) {
//
//                    if ($page_id === 'p0') {
//                        continue;
//                    }
//
//                    $newPage = [];
//
//                    $newPage[BackendUtils::KEY_USER_ID] = $userId;
//                    $newPage[BackendUtils::KEY_TOKEN] = urldecode($this->getTokenV1($userId, $page_id));
//                    $newPage[BackendUtils::KEY_PAGE_ID] = $page_id;
//
//                    $newPageData = $data;
//
//                    $newPageData[BackendUtils::PAGE_ENABLED] = (bool)$page_info[BackendUtils::PAGES_ENABLED];
//                    $newPageData[BackendUtils::PAGE_LABEL] = $page_info[BackendUtils::PAGES_LABEL];
//
//                    if (isset($mps_data[$page_id])) {
//                        $mps = $mps_data[$page_id];
//                        foreach ($mps as $k => $v) {
//
//                            if ($k === BackendUtils::ORG_EMAIL) {
//                                // only one email was allowed
//                                continue;
//                            }
//
//                            if (
//                                // these keys only replace defaults if they are NOT empty
//                                $k === BackendUtils::ORG_NAME ||
//                                $k === BackendUtils::ORG_ADDR ||
//                                $k === BackendUtils::ORG_PHONE ||
//                                $k === BackendUtils::PSN_FORM_TITLE
//                            ) {
//                                if (!empty($v)) {
//                                    $newPageData[$k] = $v;
//                                } //else use default
//                                continue;
//                            }
//
//                            // override defaults
//                            $newPageData[$k] = $v;
//                        }
//                    }
//
//                    if (isset($templates_data[$page_id])) {
//                        $newPageData[BackendUtils::KEY_TMPL_DATA] = $templates_data[$page_id];
//                    }
//                    if (isset($templates_info[$page_id])) {
//                        $newPageData[BackendUtils::KEY_TMPL_INFO] = $templates_info[$page_id];
//                    }
//
//                    $newPage[BackendUtils::KEY_DATA] = $this->utils->filterDefaultSettings($newPageData);
//
//                    $newPage[BackendUtils::KEY_REMINDERS] = $reminders;
//
//
//                    if ($dryRun) {
//                        $this->logAny($newPage);
//                    } else {
//                        $this->insertRowV2($newPage);
//                    }
//                }
//
//                // dir page ----------------------
//                if (isset($row[BackendUtils::KEY_DIR])) {
//                    $dirItems = json_decode($row[BackendUtils::KEY_DIR], true);
//                    if (!empty($dirItems)) {
//
//                        $psn_data = json_decode($row[BackendUtils::KEY_PSN] ?? null, true) ?? [];
//
//                        $dirPage = [
//                            BackendUtils::KEY_USER_ID => $page0[BackendUtils::KEY_USER_ID],
//                            BackendUtils::KEY_TOKEN => $page0[BackendUtils::KEY_TOKEN] . "dir",
//                            BackendUtils::KEY_PAGE_ID => 'd0',
//                            BackendUtils::KEY_DATA => [
//                                BackendUtils::DIR_ITEMS => $dirItems,
//                                BackendUtils::PSN_PAGE_TITLE => $psn_data[BackendUtils::PSN_PAGE_TITLE] ?? "",
//                                BackendUtils::PSN_PAGE_STYLE => $psn_data[BackendUtils::PSN_PAGE_STYLE] ?? "",
//                                BackendUtils::PSN_USE_NC_THEME => $psn_data[BackendUtils::PSN_USE_NC_THEME] ?? false,
//                                BackendUtils::CLS_PRIVATE_PAGE => false,
//                            ],
//                            BackendUtils::KEY_REMINDERS => null
//                        ];
//
//                        if ($dryRun) {
//                            $this->logAny($dirPage);
//                        } else {
//                            $this->insertRowV2($dirPage);
//                        }
//                    }
//                }
//
//            } else {
//                $this->logger->error("json_decode of 'pages' var failed for user " . $userId);
//            }
//            $this->logAny("--------\n");
//        }
//
//        $result->closeCursor();
//
//        $this->assertEquals(1, 1);
//    }
//
//    private function getTokenV1($userId, $pageId): string
//    {
//        $config = \OC::$server->getConfig();
//        $key = hex2bin($config->getAppValue(Application::APP_ID, 'hk'));
//        $iv = hex2bin($config->getAppValue(Application::APP_ID, 'tiv'));
//        if (empty($key) || empty($iv)) {
//            throw new \ErrorException("Can't find key");
//        }
//        if ($pageId === "p0") {
//            $pfx = '';
//            $upi = $userId;
//        } else {
//            $pn = intval(substr($pageId, 1));
//            if ($pn < 1 || $pn > 14) {
//                throw new \ErrorException("Bad page number");
//            }
//            $pfx = ($iv[0] ^ $iv[15]) ^ $iv[$pn];
//
//            $iv = $pfx . substr($iv, 1);
//            $upi = $userId . chr($pn);
//        }
//
//        $tkn = $this->encrypt(
//            hash('adler32', $upi, true) . $upi, $key, $iv);
//
//
//        if ($pfx === '') {
//            $bd = base64_encode($tkn);
//        } else {
//            $v = base64_encode($tkn . $pfx);
//            $bd = trim($v, '=');
//            $ld = strlen($v) - strlen($bd);
//            if ($ld === 1) {
//                $bd .= "01";
//            } else {
//                $bd .= $ld;
//            }
//        }
//        return urlencode(str_replace("/", "_", $bd));
//
//    }
//
//    // same as in utils
//    private function encrypt(string $data, string $key, $iv = ''): string
//    {
//
//        $cipher = "AES-128-CFB";
//
//        if ($iv === '') {
//            $iv = $_iv = openssl_random_pseudo_bytes(
//                openssl_cipher_iv_length($cipher));
//        } else {
//            $_iv = '';
//        }
//        $ciphertext_raw = openssl_encrypt(
//            $data,
//            $cipher,
//            $key,
//            OPENSSL_RAW_DATA,
//            $iv);
//
//        return $_iv !== ''
//            ? base64_encode($_iv . $ciphertext_raw)
//            : $_iv . $ciphertext_raw;
//    }
//
//
//    private function insertRowV2(array $data)
//    {
//        $this->logAny([
//            "user" => $data[BackendUtils::KEY_USER_ID],
//            "page_id" => $data[BackendUtils::KEY_PAGE_ID],
//        ]);
//
//        $qb = $this->db->getQueryBuilder();
//        $qb->insert(BackendUtils::PREF_TABLE_V2_NAME);
//
//        $c = 0;
//        foreach ($data as $k => $v) {
//            $qb->setValue($k, '?');
//            if (gettype($v) === "array") {
//                $sv = json_encode($v);
//            } else {
//                $sv = $v;
//            }
//            $qb->setParameter($c, $sv);
//            $c++;
//        }
////        $this->logAny("ssss: " . $qb->getSQL());
////        $this->logAny($qb->getParameters());
//        $r = $qb->executeStatement();
//        $this->logAny("num rows: " . $r);
//    }
//
//
//    private function getValue($array, $key, $default)
//    {
//        return empty($array[$key]) ? $default : $array[$key];
//    }
//
//    private function getArrayForKey($row, $key): array
//    {
//
//        $default = $this->utils->getDefaultForKey($key);
//
//        $data = json_decode($row[$key] ?? null, true) ?? [];
//
//        if (empty($data)) {
//            return $default;
//        }
//
//        foreach ($default as $k => $v) {
//            if (!isset($data[$k])) {
//                $data[$k] = $v;
//            }
//        }
//
//        return $data;
//    }
//
//    private function addArrayToData($arr, &$data, $v2prefix = "")
//    {
//        foreach ($arr as $k => $v) {
//            $data[$v2prefix . $k] = $v;
//        }
//    }
//
//
//    private function logAny(mixed $data, $level = LogLevel::INFO)
//    {
//        if (is_string($data)) {
//            $this->logger->log($level, $data);
//        } else {
//            $this->logger->log($level, var_export($data, true));
//        }
//    }
//
//}