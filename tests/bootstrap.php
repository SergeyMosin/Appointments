<?php

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__ . '/../../../lib/base.php';
require_once __DIR__ . '/Unit/ConsoleLogger.php';

// Fix for "Autoload path not allowed: .../tests/lib/testcase.php"
\OC::$loader->addValidRoot(OC::$SERVERROOT . '/tests');

// Fix for "Autoload path not allowed: .../appointments/tests/testcase.php"
\OC_App::loadApp(\OCA\Appointments\AppInfo\Application::APP_ID);
// we need to load Talk because we test talk integration
\OC_App::loadApp(\OCA\Talk\AppInfo\Application::APP_ID);


OC_Hook::clear();
