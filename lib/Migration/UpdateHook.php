<?php


namespace OCA\Appointments\Migration;


use OC\User\Manager;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Controller\PageController;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\AppFramework\QueryException;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\PreConditionNotMetException;

class UpdateHook implements IRepairStep {

    private $c;
    private $um;
    private $appName;

    public function __construct($AppName,
                        IConfig $config,
                        Manager $userManager){
        $this->c=$config;
        $this->um=$userManager;
        $this->appName=$AppName;
    }

    public function getName(){
        return 'Update hook for Appointments app';
    }

    public function run(IOutput $output)
    {

        $nb_key = "new_backend";
        if ($this->c->getAppValue($this->appName, $nb_key) !== '2') {

            $users = $this->um->search('', 2000);
            $output->info("running appointments UpdateHook for " . count($users) . " users");

            foreach ($users as $user) {
                if ($user->getLastLogin() !== 0) {
                    $userId = $user->getUID();

                    // Fix #111 regression
                    if (!empty($this->c->getUserValue($userId, $userId, BackendUtils::KEY_CLS))) {
                        $this->c->deleteUserValue($userId, $userId, BackendUtils::KEY_CLS);
                    }

                    $this->c->setAppValue($this->appName, $nb_key, "2");
                }
            }

            $output->info("appointments UpdateHook finished");
        }
    }
}