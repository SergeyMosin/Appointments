<?php

namespace Unit;

use ErrorException;
use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\BCSabreImpl;
use OCA\Appointments\Backend\DavListener;
use OCA\Appointments\Backend\IBackendConnector;
use OCA\Appointments\Controller\PageController;
use OCA\Appointments\Controller\StateController;
use OCA\Appointments\SendDataResponse;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\PreConditionNotMetException;
use PHPUnit\Framework\TestCase;
use OCA\Appointments\Tests\TestConstants;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class RemindersTest extends TestCase
{
    const SC_ERR = 'bad StateController status';
    const PRINCIPAL_PREFIX = 'principals/users/';

    protected static LoggerInterface $logger;

    protected static IConfig $config;
    protected static IL10N $l10n;
    protected static IMailer $mailer;
    protected static IUserSession $userSession;
    protected static BackendUtils $utils;
    protected static ContainerInterface $container;
    protected static CalDavBackend $davBE;
    protected static IDBConnection $db;
    protected static BackendManager $backendManager;
    protected static IBackendConnector $backendConnector;

    private $attendeeEmail;

    private $users = [];

    public static function setUpBeforeClass(): void
    {
        self::$logger = new ConsoleLogger();

        $app = new Application();
        self::$container = $app->getContainer();
        self::$config = self::$container->get(IConfig::class);
        self::$l10n = self::$container->get(IL10N::class);
        self::$mailer = self::$container->get(IMailer::class);
        self::$utils = self::$container->get(BackendUtils::class);
        self::$userSession = self::$container->get(IUserSession::class);

        $dav = new \OCA\DAV\AppInfo\Application();
        self::$davBE = $dav->getContainer()->get(CalDavBackend::class);

        self::$backendManager = new BackendManager();

        self::$db = self::$container->get(IDBConnection::class);
        self::$backendConnector = new BCSabreImpl(
            self::$davBE,
            self::$config,
            self::$utils,
            self::$db,
            self::$logger
        );
    }

    function testLogger()
    {
        self::$logger->error('error test');
        self::$logger->notice('notice test');
        self::$logger->info('info test');
        self::$logger->log('', 'log test');
        $this->consoleLog('test consoleLog');
        $this->assertNotEquals(0, 1, "test");
    }

    function testReminders()
    {
        // NC cron trigger loop:
        //  while true; do echo "cron $(date)"; sudo runuser -u www-data -- php /path/to/nextcloud/cron.php; sleep 15; done

        $this->assertNotEquals(false, getenv('TEST_KEY'), "missing TEST_KEY environment var");
        $this->attendeeEmail = getenv('TEST_ATTENDEE_EMAIL');
        $this->assertNotEquals(false, $this->attendeeEmail, "missing TEST_ATTENDEE_EMAIL environment var");
        $this->consoleLog('attendee: ' . $this->attendeeEmail);

        $userIds = [TestConstants::USER_ID1, TestConstants::USER_ID2];
//        $userIds = [TestConstants::USER_ID1];
        $calNames = [TestConstants::TEST_CALENDAR1, TestConstants::TEST_CALENDAR2];
//        $calNames = [TestConstants::TEST_CALENDAR1];

        $this->consoleLog('--------------------------');

        $this->configure($userIds, $calNames);

        // delete old appointments
        foreach ($this->users as $userData) {
            foreach ($userData['cals'] as $cal) {
                $this->consoleLog('clean up: ' . $cal['principaluri'] . '/' . $cal['uri']);
                $cdt = new \DateTime();
                $cdt->modify("+45 days");
                try {
                    $cr = self::$backendConnector->queryRangePast([(string)$cal['id']], $cdt, false, true, true);
                } catch (\Throwable $e) {
                    $this->fail('could not delete old appointments in calendar ' . $cal['id'] . ', ' . $e->getMessage());
                }
                $this->consoleLog('cleaned up count: ' . $cr);
            }
        }

        $this->consoleLog('--------------------------');

        foreach ($this->users as $userId => $userData) {
            $this->consoleLog("Running test with userId: " . $userId);
            $this->setupTemplatesAndReminders($userId, $userData['pages']);
            $this->createAppointmentsForReminders($userId, $userData['pages']);
            $this->consoleLog('---------------------------');
        }
    }

    function testForceHandleReminders()
    {
        $davListener = new DavListener(
            self::$l10n,
            self::$logger,
            self::$utils
        );

        $davListener->handleReminders(123, self::$db, self::$backendConnector);

        $this->assertNotEquals(0, 1, "test");
    }

    private function configure(array $userIds, array $calNames): void
    {

        // make sure test users exist and have at least one calendar
        /** @var IUserManager $userManager */
        $userManager = self::$container->get(IUserManager::class);
        foreach ($userIds as $userId) {

            $this->consoleLog('user id: ' . $userId);

            $user = $userManager->get($userId);
            $pass = hash('crc32b', $userId);
            $pass .= hash('adler32', $userId . $pass);
            if ($user === null) {
                $this->assertNotEquals(null, $userManager->createUser($userId, $pass), "can not create user with  id '" . $userId . "'");
            }
            $this->consoleLog([$userId => $pass]);

            $cals = [];
            $principalUri = self::PRINCIPAL_PREFIX . $userId;
            foreach ($calNames as $calName) {
                $cal = self::$davBE->getCalendarByUri($principalUri, $calName);
                if ($cal === null) {
                    try {
                        $calId = self::$davBE->createCalendar($principalUri, $calName, [
                            '{DAV:}displayname' => $calName,
                            'components' => 'VEVENT'
                        ]);
                    } catch (\Throwable $e) {
                        $this->fail("could not create calendar '" . $calName . "',: " . $e->getMessage());
                    }
                    $cal = self::$davBE->getCalendarById($calId);
                    $this->assertNotEquals(null, $cal, "calendar '" . $calName . "' is null");
                }
                $cals[] = $cal;
                $this->consoleLog("calendar " . $calName . " OK");
            }
            $this->users[$userId] = [
                'cals' => $cals,
                'pages' => $this->configurePages($userId, $cals)
            ];

            $this->consoleLog('--------------------------');
        }
    }

    private function configurePages($userId, $cals): array
    {
        // create a page for each calendar
        $pages = [];
        $pn = 0;
        $c = true;
        foreach ($cals as $cal) {
            $pageId = 'p' . $pn++;
            if (!self::$utils->loadSettingsForUserAndPage($userId, $pageId)) {
                // no settings for this page, let's create it
                $this->assertEquals(1, self::$utils->createPage($userId, $pageId, json_encode([
                    BackendUtils::PAGE_ENABLED => false,
                ])));
                $this->assertNotEquals(false, self::$utils->loadSettingsForUserAndPage($userId, $pageId), 'could not load settings for page: ' . $pageId);
            }
            // ensure we have the key
            $r = $this->callStateController($userId, [
                'a' => 'set_one',
                'p' => $pageId,
                'k' => '__ckey',
                'v' => getenv('TEST_KEY')
            ]);
            $this->assertEquals(200, $r->getStatus(), 'ckey: ' . self::SC_ERR);

            // page exists and settings are loaded, let's configure...
            $requiredSettings = [
                BackendUtils::PAGE_LABEL => 'Test Page ' . $pageId,
                BackendUtils::CLS_TS_MODE => BackendUtils::CLS_TS_MODE_TEMPLATE,
                BackendUtils::CLS_TMM_DST_ID => (string)$cal['id'],
                BackendUtils::ORG_EMAIL => $this->attendeeEmail,
                BackendUtils::ORG_NAME => 'Organization (' . $userId . ')',
                BackendUtils::ORG_PHONE => '1234567890',
                BackendUtils::EML_SKIP_EVS => true,
                BackendUtils::EML_ICS => true,
                BackendUtils::EML_CNF_TXT => "email <em>extra</em> text, userId: " . $userId . ", pageId: " . $pageId,
                BackendUtils::EML_ICS_TXT => ".isc extra text, userId: " . $userId . ", pageId: " . $pageId,
            ];

            $this->consoleLog('c: ' . var_export($c, true));
            if ($c === true) {
                $requiredSettings[BackendUtils::BBB_ENABLED] = false;
                $requiredSettings[BackendUtils::BBB_PASSWORD] = false;
                $requiredSettings[BackendUtils::BBB_DEL_ROOM] = false;
                $requiredSettings[BackendUtils::BBB_FORM_ENABLED] = false;

                $requiredSettings[BackendUtils::TALK_ENABLED] = true;
                $requiredSettings[BackendUtils::TALK_PASSWORD] = true;
                $requiredSettings[BackendUtils::TALK_DEL_ROOM] = true;
                $requiredSettings[BackendUtils::TALK_FORM_ENABLED] = true;
            } else {
                $requiredSettings[BackendUtils::TALK_ENABLED] = false;
                $requiredSettings[BackendUtils::TALK_PASSWORD] = false;
                $requiredSettings[BackendUtils::TALK_DEL_ROOM] = false;
                $requiredSettings[BackendUtils::TALK_FORM_ENABLED] = false;

                $requiredSettings[BackendUtils::BBB_ENABLED] = true;
                $requiredSettings[BackendUtils::BBB_PASSWORD] = true;
                $requiredSettings[BackendUtils::BBB_DEL_ROOM] = true;
                $requiredSettings[BackendUtils::BBB_FORM_ENABLED] = true;
            }
            $c = !$c;

            // this must be last
            $requiredSettings[BackendUtils::PAGE_ENABLED] = true;


            foreach ($requiredSettings as $key => $value) {
                $this->assertEquals(200, self::$utils->setUserSettingsV2(
                    $userId, $pageId, $key, $value)[0],
                    "Can not set " . $key . " for pageId " . $pageId);
            }
            $pages[] = $pageId;
            $this->consoleLog("page " . $pageId . " OK");
        }
        return $pages;
    }

    private function setupTemplatesAndReminders(string $userId, array $pages): void
    {

        $allowed_seconds = ["3600", "7200", "14400", "28800", "86400", "172800", "259200", "345600", "432000", "518400", "604800"];
        // 1 hour, 1 day, 1 week
        $selected_seconds = [0, 4, 10];
//        $selected_seconds = [4];

        $reminder_time_diff = 45; // Ex. 10 = reminders will have 10 seconds lead time. -10 = reminders will appear 10 seconds after appointments, 1800 max

        foreach ($pages as $pageId) {
            $this->assertNotEquals(false, self::$utils->loadSettingsForUserAndPage($userId, $pageId), 'could not load settings for page: ' . $pageId);
            $settings = self::$utils->getUserSettings();
            $calId = $settings[BackendUtils::CLS_TMM_DST_ID];
            $this->assertNotEquals('-1', $calId, 'no calendar for page ' . $pageId);
            $this->consoleLog("calId: " . $calId);

            // prep template data
            $tz = self::$utils->getCalendarTimezone($userId, self::$backendConnector->getCalendarById($calId, $userId));

            $tzName = $tz->getName();
            if (strtolower($tzName) === 'utc') {
                // default to Europe/London (GMT+0)
                $defaultTZ = 'Europe/London';
                $this->consoleLog("WARNING: UTC timezone detected using " . $defaultTZ);
                $tz = new \DateTimeZone($defaultTZ);
                $tzName = $tz->getName();
            }
            $this->consoleLog("tz name: " . $tzName);

            $zonesData = json_decode(file_get_contents(__DIR__ . '/../../ajax/zones.js'), true);
            $this->assertNotEquals(null, $zonesData, 'can load or parse timezone data file');

            $tzDataArray = [];
            if (isset($zonesData['zones'][$tzName])) {
                $tzDataArray = $zonesData['zones'][$tzName]['ics'];
            } elseif (isset($zonesData['aliases'][$tzName])) {
                $aliasTo = $zonesData['aliases'][$tzName]['aliasTo'];
                $tzDataArray = $zonesData['zones'][$aliasTo]['ics'];
            }

            $this->assertFalse(empty($tzDataArray), 'can find data for "' . $tzName . '" timezone');

            $tzData = "BEGIN:VTIMEZONE\r\nTZID:" . $tzName . "\r\n" . trim(implode("\r\n", $tzDataArray)) . "\r\nEND:VTIMEZONE";

            $dt = new \DateTime();
            $dt->setTimezone($tz);

            // ----
            $now = time();

            // update template
            $tar = [[], [], [], [], [], [], []];
            foreach ($selected_seconds as $seconds) {
                $timestamp = (int)$allowed_seconds[$seconds] + $reminder_time_diff + $now;

                $this->consoleLog("d: " . date(DATE_RFC2822, $timestamp));

                $dt->setTimestamp($timestamp);
                $weekDay = $dt->format("N") - 1;
                $hours = +$dt->format("G");
                $minutes = (int)$dt->format("i");
                $seconds = (int)$dt->format("s");
                $tar[$weekDay][] = [
                    "start" => $hours * 3600 + $minutes * 60 + $seconds,
                    "dur" => [15, 45],
                    "title" => $userId . "_test_appointment"
                ];
            }

            $r = $this->callStateController($userId, [
                'a' => 'set_one',
                'p' => $pageId,
                'k' => BackendUtils::KEY_TMPL_DATA,
                'v' => $tar
            ]);
            $this->assertEquals(200, $r->getStatus(), BackendUtils::KEY_TMPL_DATA . ': ' . self::SC_ERR);

            $r = $this->callStateController($userId, [
                'a' => 'set_one',
                'p' => $pageId,
                'k' => BackendUtils::KEY_TMPL_INFO,
                'v' => [
                    'tzName' => $tzName,
                    'tzData' => $tzData
                ]
            ]);
            $this->assertEquals(200, $r->getStatus(), BackendUtils::KEY_TMPL_INFO . ': ' . self::SC_ERR);

            // set reminders
            $rd = [
                BackendUtils::REMINDER_DATA => [
                    [
                        BackendUtils::REMINDER_DATA_TIME => $allowed_seconds[$selected_seconds[0]],
                        BackendUtils::REMINDER_DATA_ACTIONS => true
                    ],
                ],
                BackendUtils::REMINDER_MORE_TEXT => "test reminder more text <b>BOLD MARKUP</b> userID: " . $userId . ', pageId: ' . $pageId,
                BackendUtils::REMINDER_SEND_ON_FRIDAY => false
            ];

            if (isset($selected_seconds[1])) {
                $rd[BackendUtils::REMINDER_DATA][] = [
                    BackendUtils::REMINDER_DATA_TIME => $allowed_seconds[$selected_seconds[1]],
                    BackendUtils::REMINDER_DATA_ACTIONS => true
                ];
            }
            if (isset($selected_seconds[2])) {
                $rd[BackendUtils::REMINDER_DATA][] = [
                    BackendUtils::REMINDER_DATA_TIME => $allowed_seconds[$selected_seconds[2]],
                    BackendUtils::REMINDER_DATA_ACTIONS => true
                ];
            }

            $r = $this->callStateController($userId, [
                'a' => 'set_one',
                'p' => $pageId,
                'k' => BackendUtils::KEY_REMINDERS,
                'v' => $rd
            ]);
            $this->assertEquals(200, $r->getStatus(), BackendUtils::KEY_REMINDERS . ': ' . self::SC_ERR);
        }
    }


    private function createAppointmentsForReminders(string $userId, array $pages): void
    {
        foreach ($pages as $pageId) {
            $this->assertNotEquals(false, self::$utils->loadSettingsForUserAndPage($userId, $pageId), 'could not load settings for page: ' . $pageId);
            $settings = self::$utils->getUserSettings();
            $calId = $settings[BackendUtils::CLS_TMM_DST_ID];

            $utz = self::$utils->getCalendarTimezone($userId, self::$backendConnector->getCalendarById($calId, $userId));

            $t_start = new \DateTime('now +' . $settings[BackendUtils::CLS_PREP_TIME] . "mins", $utz);

            $this->consoleLog("t_start: " . date(DATE_RFC2822, $t_start->format("U")));

            // + 7 days
            $t_end = clone $t_start;
            $t_end->setTimestamp($t_start->getTimestamp() + (8 * 86400));
            $t_end->setTime(0, 0);

            $out = self::$backendConnector->queryTemplate(
                $settings, $t_start, $t_end, $userId, $pageId);

            $this->assertNotEquals(null, $out, "no dates");

            $outArr = explode(',', $out);
//			$this->consoleLog($outArr);

            $start_after = time() + 3600;
            $this->consoleLog("start_after: " . date(DATE_RFC2822, $start_after));

            $cc = 0;
            $oac = count($outArr);
            for ($i = 0; $i < $oac; $i++) {

                $outParts = explode(':', $outArr[$i]);

                $apptTime = (int)substr($outParts[0], 1);
//            if ($apptTime < $start_after) continue;
                $this->consoleLog("apptTime: " . date(DATE_RFC2822, $apptTime));

                $pc = $this->getPageController($userId, [
                    'adatetime' => $outParts[2],
                    'appt_dur' => 0,
                    'name' => 'Attendee ' . hash('crc32b', $userId . $pageId),
                    'email' => $this->attendeeEmail,
                    'phone' => '1234567890',
                    'tzi' => 'T' . $utz->getName()
                ]);

                $rr = $pc->showFormPost($userId, $pageId);
                $this->assertEquals(303, $rr->getStatus(), self::SC_ERR);

                $rUrl = $rr->getRedirectURL();

                $this->consoleLog('Redirect URL: ' . $rUrl);

                $this->assertNotEquals(false, strpos($rUrl, 'cncf?d='), "bad redirect URL");

                $uParts = explode('/', $rUrl);
                $c = count($uParts);
                $pc = $this->getPageController($userId, [
                    'token' => urldecode($uParts[$c - 2]),
                    'd' => urldecode(explode('=', $uParts[$c - 1])[1])
                ]);
                $res = $pc->cncf();
                $this->assertEquals(200, $res->getStatus(), self::SC_ERR);

                $cc++;
                if ($cc === 5) {
                    break;
                }
            }
        }
    }

    /**
     * @throws PreConditionNotMetException
     * @throws ErrorException
     */
    private function callStateController(string $userId, array $data): SendDataResponse
    {
        $request = $this->createMock(IRequest::class);
        $request->method("getParam")->willReturnCallback(function ($key, $default = "") use ($data) {
            return $data[$key] ?? $default;
        });
        $c = new StateController(
            Application::APP_ID, $userId, $request,
            self::$config,
            self::$l10n,
            self::$utils,
            self::$backendManager,
            self::$logger
        );
        return $c->index();
    }

    private function getPageController(string $userId, array $data): PageController
    {

        $request = $this->createMock(IRequest::class);
        $request->method("getParams")->willReturnCallback(function () use ($data) {
            return $data;
        });
        $request->method("getParam")->willReturnCallback(function ($key, $default = "") use ($data) {
            return $data[$key] ?? $default;
        });

        return new PageController(
            $request,
            $userId,
            self::$config,
            self::$mailer,
            self::$l10n,
            self::$userSession,
            self::$backendManager,
            self::$utils,
            self::$logger
        );
    }

    /** @param mixed $data */
    private function consoleLog($data)
    {
        self::$logger->log('', var_export($data, true));
    }
}

