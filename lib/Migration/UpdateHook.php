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

    public function run(IOutput $output){
        $users=$this->um->search('',1000);

        try {
            /** @var CalDavBackend $backend */
            $backend=\OC::$server->query(CalDavBackend::class);
            if(!method_exists($backend,"getCalendarByUri")){
                $backend=null;
            }
        } catch (QueryException $e) {
            $output->warning("appointments UpdateHook can't get CalDavBackend");
            $backend=null;
        }

        $output->info("running appointments UpdateHook for ".count($users)." users");
        foreach ($users as $user){
            if($user->getLastLogin()!==0) {
                $userId=$user->getUID();

                // convert cal_uri -> cal_id
                $cal_url = $this->c->getUserValue(
                    $userId, $this->appName,
                    'cal_url', null);
                if ($cal_url !== null) {
                    $cal_id=-1;
                    if($backend!==null) {
                        $cal = $backend->getCalendarByUri(
                            BackendManager::PRINCIPAL_PREFIX . $userId,
                            $cal_url);
                        if ($cal !== null) {
                            $cal_id=$cal['id'];
                        }
                    }

                    try {
                        $this->c->setUserValue(
                            $userId, $this->appName,
                            'cal_id', $cal_id);
                    } catch (PreConditionNotMetException $e) {
                        $output->warning($e->getMessage());
                    }
                    $this->c->deleteUserValue(
                        $userId,$this->appName, 'cal_url');
                }

                // Email Options
                $ics=$this->c->getUserValue(
                    $userId,$this->appName,
                    BackendUtils::EML_ICS,null);
                if($ics!==null){
                    $a=BackendUtils::EML_DEF;
                    $a[BackendUtils::EML_ICS]=$ics;
                    $j=json_encode($a);
                    if($j!==false){
                        try{
                            $this->c->setUserValue(
                                $userId, $this->appName,
                                BackendUtils::KEY_EML, $j);
                        } catch (PreConditionNotMetException $e) {
                            $output->warning($e->getMessage());
                        }
                    }
                    $this->c->deleteUserValue(
                        $userId,$this->appName,
                        BackendUtils::EML_ICS);
                }

                // Convert pubPageSettings
                $PPS_KEY="pubPageSettings";

                $old_pps=$this->c->getUserValue(
                    $userId,$this->appName,$PPS_KEY,null);

                if($old_pps!==null){
                    $a=PageController::PSN_DEF;
                    $a[PageController::PSN_NWEEKS]=$old_pps[0];
                    $a[PageController::PSN_EMPTY]=boolval($old_pps[1]);
                    $a[PageController::PSN_FNED]=boolval($old_pps[2]);
                    $a[PageController::PSN_WEEKEND]=boolval($old_pps[3]);
                    $a[PageController::PSN_TIME2]=boolval($old_pps[4]);

                    $a[PageController::PSN_FORM_TITLE]=$this->c->getUserValue(
                        $userId,$this->appName,PageController::PSN_FORM_TITLE);
                    $a[PageController::PSN_GDPR]=$this->c->getUserValue(
                        $userId,$this->appName,PageController::PSN_GDPR);

                    $j=json_encode($a);
                    if($j!==false){
                        try{
                            $this->c->setUserValue(
                                $userId, $this->appName,
                                PageController::KEY_PSN, $j);
                        } catch (PreConditionNotMetException $e) {
                            $output->warning($e->getMessage());
                        }
                    }

                    $this->c->deleteUserValue(
                        $userId,$this->appName,$PPS_KEY);
                    $this->c->deleteUserValue(
                        $userId,$this->appName,
                        PageController::PSN_FORM_TITLE);
                    $this->c->deleteUserValue(
                        $userId,$this->appName,
                        PageController::PSN_GDPR);
                }
            }
        }
        $output->info("appointments UpdateHook finished");
    }
}