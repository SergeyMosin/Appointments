<?php

//\OC::$server->query(OCA\Appointments\AppInfo\Application::class);

use OCA\Appointments\AppInfo\Application;

$app = \OC::$server->query(Application::class);
$app->registerHooks();