<?php


namespace OCA\Appointments\Migration;


use OC\User\Manager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\ExternalModeSabrePlugin;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UpdateHook implements IRepairStep {

    private const KEY_U_NAME = 'organization';
    private const KEY_U_EMAIL = 'email';
    private const KEY_U_ADDR = 'address';
    private const KEY_U_PHONE = 'phone';

    private $c;
    private $um;
    private $appName;
    private $utils;

    public function __construct($AppName,
                        IConfig $config,
                        Manager $userManager,
                        BackendUtils $utils){
        $this->c=$config;
        $this->um=$userManager;
        $this->appName=$AppName;
        $this->utils=$utils;
    }

    public function getName(){
        return 'Update hook for Appointments app';
    }

    public function run(IOutput $output)
    {

        $nb_key = "new_backend";
        $nb_val=intval($this->c->getAppValue($this->appName, $nb_key,'0'));

        if ($nb_val < 3) {

            $users = $this->um->search('', 2000);
            $output->info("running appointments UpdateHook for " . count($users) . " users");

            foreach ($users as $user) {
                if ($user->getLastLogin() !== 0) {
                    $userId = $user->getUID();

                    if($nb_val<2) {
                        // TODO: remove soon
                        // this is previous fix (version skipped)
                        // Fix #111 regression
                        if (!empty($this->c->getUserValue($userId, $userId, BackendUtils::KEY_CLS))) {
                            $this->c->deleteUserValue($userId, $userId, BackendUtils::KEY_CLS);
                        }
                    }
                    // new update

                    $cal_id=$this->c->getUserValue($userId, $this->appName, 'cal_id');
                    if (!empty($cal_id)){

                        $this->c->deleteUserValue($userId, $this->appName, 'cal_id');

                        $cls=$this->utils->getUserSettings(
                            BackendUtils::KEY_CLS,$userId);
                        $cls[BackendUtils::CLS_MAIN_ID]=strval($cal_id);

                        $js=json_encode($cls);
                        if($js!==false){
                            /** @noinspection PhpUnhandledExceptionInspection */
                            $this->c->setUserValue($userId,$this->appName,BackendUtils::KEY_CLS,$js);
                        }
                    }

                    // org info
                    $org_name=$this->c->getUserValue($userId, $this->appName, self::KEY_U_NAME,null);
                    if($org_name!==null){

                        $a=array(
                            BackendUtils::ORG_NAME=>$org_name,
                            BackendUtils::ORG_EMAIL=>$this->c->getUserValue($userId, $this->appName, self::KEY_U_EMAIL),
                            BackendUtils::ORG_ADDR=>$this->c->getUserValue($userId, $this->appName, self::KEY_U_ADDR),
                            BackendUtils::ORG_PHONE=>$this->c->getUserValue($userId, $this->appName, self::KEY_U_PHONE)
                        );
                        $js=json_encode($a);
                        if($js!==false){
                            /** @noinspection PhpUnhandledExceptionInspection */
                            $this->c->setUserValue($userId,$this->appName,BackendUtils::KEY_ORG,$js);
                        }

                        $this->c->deleteUserValue($userId, $this->appName,self::KEY_U_NAME);
                        $this->c->deleteUserValue($userId, $this->appName,self::KEY_U_EMAIL);
                        $this->c->deleteUserValue($userId, $this->appName,self::KEY_U_ADDR);
                        $this->c->deleteUserValue($userId, $this->appName,self::KEY_U_PHONE);
                    }

                    // autofix fix for multi-page
                    $afv=$this->c->getUserValue($userId, $this->appName,
                        ExternalModeSabrePlugin::AUTO_FIX_URI,"");
                    if(!empty($afv) && $afv[0]==="/"){
                        $this->c->setUserValue($userId, $this->appName,
                            ExternalModeSabrePlugin::AUTO_FIX_URI,
                            'p0'.$afv.chr(31));
                    }
                }
            }
            $this->c->setAppValue($this->appName, $nb_key, "3");
        }
        $output->info("appointments UpdateHook finished");
    }
}