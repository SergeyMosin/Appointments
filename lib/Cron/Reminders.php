<?php

namespace OCA\Appointments\Cron;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\DavListener;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class Reminders extends TimedJob
{

    private $davListener;
    private $db;
    private $manager;
    private $logger;

    public function __construct(ITimeFactory    $time,
                                DavListener     $davListener,
                                IDBConnection   $db,
                                BackendManager  $manager,
                                LoggerInterface $logger) {
        parent::__construct($time);
        $this->davListener = $davListener;
        $this->db = $db;
        $this->manager = $manager;
        $this->logger = $logger;

        // Run every 15 minutes
        parent::setInterval(900);
    }

    /** @inheritDoc */
    protected function run($argument) {
        if ($this->lastRun === 0) {
            // skip the first
            return;
        }
        try {
            $bc = $this->manager->getConnector();
        } catch (\Exception $e) {
            $this->logger->error("failed to run appointment reminders job: " . $e->getMessage());
            return;
        }
        $this->davListener->handleReminders($this->getLastRun(), $this->db, $bc);
    }
}