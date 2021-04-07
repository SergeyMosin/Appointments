<?php


namespace OCA\Appointments\Migration;

use OC\User\Manager;
use OCA\Appointments\Backend\BackendUtils;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UpdateHook implements IRepairStep {

    private $config;
    private $um;
    private $appName;
    private $utils;

    public function __construct($AppName,
                        IConfig $config,
                        Manager $userManager,
                        BackendUtils $utils){
        $this->config=$config;
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
        $nb_val=intval($this->config->getAppValue($this->appName, $nb_key,'0'));

        if ($nb_val < 4) {

            $users = $this->um->search('', 2000);
            $output->info("running appointments UpdateHook for " . count($users) . " users");
            $a=$this->appName;

            foreach ($users as $user) {
                if ($user->getLastLogin() !== 0) {

                    $u = $user->getUID();

                    if ($this->config->getUserValue($u, $a, BackendUtils::KEY_PAGES, null) !== null) {

                        $o = [];

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_ORG, null);
                        $o[BackendUtils::KEY_ORG] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_CLS, null);
                        $o[BackendUtils::KEY_CLS] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_DIR, null);
                        $o[BackendUtils::KEY_DIR] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_EML, null);
                        $o[BackendUtils::KEY_EML] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_FORM_INPUTS_HTML, null);
                        $o[BackendUtils::KEY_FORM_INPUTS_HTML] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_FORM_INPUTS_JSON, null);
                        $o[BackendUtils::KEY_FORM_INPUTS_JSON] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_PAGES, null);
                        $o[BackendUtils::KEY_PAGES] = $v;

                        $pgs = json_decode($v, true);

                        $pi = [];
                        if ($pgs !== null) {
                            foreach ($pgs as $k => $p) {
                                if ($k !== 'p0') {
                                    $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_MPS . $k, null);
                                    $pi[$k] = json_decode($v, true);
                                }
                            }
                        }
                        if (!empty($pi)) {
                            $o[BackendUtils::KEY_MPS_COL] = json_encode($pi);
                        }

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_PSN, null);
                        $o[BackendUtils::KEY_PSN] = $v;

                        $v = $this->config->getUserValue($u, $a, BackendUtils::KEY_TALK, null);
                        $o[BackendUtils::KEY_TALK] = $v;

                        $o['user_id'] = $u;

                        try {
                            // insert into BackendUtils::PREF_TABLE_NAME
                            $qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
                            $qb->insert(BackendUtils::PREF_TABLE_NAME);
                            foreach ($o as $k => $v) {
                                $qb->setValue($k, $qb->createNamedParameter($v));
                            }
                            $qb->execute();

                            // delete from 'oc_preferences'
                            //
                            // delete $mps first
                            if ($pgs !== null) {
                                foreach ($pgs as $k => $p) {
                                    if ($k !== 'p0') {
                                        $this->config->deleteUserValue($u, $a, BackendUtils::KEY_MPS . $k);
                                    }
                                }
                            }
                            // delete other keys
                            foreach ($o as $k => $v) {
                                $this->config->deleteUserValue($u, $a, $k);
                            }

                        } catch (\Exception $e) {
                            $output->warning($e->getMessage());
                        }
                    }
                }
            }
            $this->config->setAppValue($this->appName, $nb_key, "4");
        }
        $output->info("appointments UpdateHook finished");
    }
}