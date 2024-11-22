<?php

namespace Unit;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\BCSabreImpl;
use OCA\Appointments\Backend\IBackendConnector;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class FastQueryTest extends TestCase
{
    const PRINCIPAL_PREFIX = 'principals/users/';

    protected static LoggerInterface $logger;
    protected static IConfig $config;
    protected static BackendUtils $utils;
    protected static ContainerInterface $container;
    protected static CalDavBackend $davBE;
    protected static IDBConnection $db;
    protected static IBackendConnector $backendConnector;

    public static function setUpBeforeClass(): void
    {
        self::$logger = new ConsoleLogger();

        $app = new Application();
        self::$container = $app->getContainer();
        self::$config = self::$container->get(IConfig::class);
        self::$utils = self::$container->get(BackendUtils::class);

        $dav = new \OCA\DAV\AppInfo\Application();
        self::$davBE = $dav->getContainer()->get(CalDavBackend::class);

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

    function testFastQuery()
    {
        $calIds = [6, 14];
        $startTs = 1704807000;
        $endTs = 1735381200;
        $propFilters = [];

        $class = new \ReflectionClass(self::$backendConnector);
        $method = $class->getMethod('fastQuery');

        $iter = $method->invokeArgs(self::$backendConnector,
            [$calIds, $startTs, $endTs, $propFilters, ['id','uri','calendarid']]
        );
        foreach ($iter as $data) {
            $this->consoleLog($data);
        }
        $this->assertNotEquals(0, 1, "test");
    }

    /** @param mixed $data */
    private function consoleLog($data)
    {
        self::$logger->log('', var_export($data, true));
    }
}

