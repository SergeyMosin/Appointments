<?php

namespace OCA\Appointments\Cron;

use OCA\Appointments\Backend\DavListener;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class Reminders extends TimedJob
{

    private $davListener;

    public function __construct(ITimeFactory $time, DavListener $davListener) {
        parent::__construct($time);
        $this->davListener = $davListener;

        // Run once an hour
        parent::setInterval(10);
    }

    /** @inheritDoc */
    protected function run($argument) {
        \OC::$server->getLogger()->error("argument: " . var_export($argument, true));
        $this->davListener->handleReminder("TEST");
        $this->davListener->handleReminder("this->getLastRun(): " . $this->getLastRun());
    }
}