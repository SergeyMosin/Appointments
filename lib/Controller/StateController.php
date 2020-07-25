<?php
/** @noinspection PhpComposerExtensionStubsInspection */
namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\ExternalModeSabrePlugin;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IRequest;

class StateController extends Controller{
    
    private $userId;
    private $config;
    private $utils;
    private $bc;

    public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                IConfig $config,
                                BackendUtils $utils,
                                BackendManager $backendManager){
        parent::__construct($AppName, $request);
        
        $this->userId=$UserId;
        $this->config=$config;
        $this->utils=$utils;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc=$backendManager->getConnector();
        
        
    }


    /**
     * @NoAdminRequired
     * @throws \OCP\PreConditionNotMetException
     * @throws \ErrorException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function index(){
        $action = $this->request->getParam("a");
        $r=new SendDataResponse();
        $r->setStatus(400);

        // TODO: multiple pages per user:
        //  get_page + set_page:
        //      index,uri,enabled,nickname,etc...

        if($action==="get"){ // should be replaced with "get_page"
            // for now just get enabled

            $enabled=$this->config->getUserValue(
                $this->userId,
                $this->appName,
                'page_enabled',
                '0');

            if($enabled==="1"){
                // JUST IN CASE: check if calendars are set
                $other_cal="-1";
                $main_cal=$this->utils->getMainCalId($this->userId,$other_cal);

                $cls=$this->utils->getUserSettings(
                    BackendUtils::KEY_CLS,BackendUtils::CLS_DEF,
                    $this->userId ,$this->appName);
                $ts_mode=$cls[BackendUtils::CLS_TS_MODE];

                if(($ts_mode==="0" && $main_cal==="-1") ||
                    ($ts_mode==="1" && ($main_cal==="-1" || $other_cal==="-1"))
                ){
                    $enabled="0";
                    $this->config->setUserValue(
                        $this->userId,
                        $this->appName,
                        'page_enabled',
                        $enabled);
                }
            }

            $r->setData($enabled);
            $r->setStatus(200);

        }elseif($action==="enable"){
            $v=$this->request->getParam("v");

            $r->setStatus(200);
            if($v==='1'){
                $c=$this->config;
                $u=$this->userId;
                $a=$this->appName;
                $other_cal="-1";
                $main_cal=$this->utils->getMainCalId($u,$other_cal);

                $cls=$this->utils->getUserSettings(
                    BackendUtils::KEY_CLS,BackendUtils::CLS_DEF,
                    $this->userId ,$this->appName);
                $ts_mode=$cls[BackendUtils::CLS_TS_MODE];

                if(($ts_mode==="0" && $main_cal==="-1") ||
                    ($ts_mode==="1" && ($main_cal==="-1" || $other_cal==="-1"))

                    || empty($c->getUserValue($u,$a, BackendUtils::KEY_O_NAME))
                    || empty($c->getUserValue($u,$a, BackendUtils::KEY_O_ADDR))
                    || empty($c->getUserValue($u,$a, BackendUtils::KEY_O_EMAIL))
                ){
                    $r->setStatus(412);
                    $v="0";
                }
            }else{
                $v='0';
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $this->config->setUserValue(
                $this->userId,
                $this->appName,
                'page_enabled',
                $v);

        }elseif ($action==='get_puburi'){ // TODO: this should be a part of "get_page"
            $pb=$this->utils->getPublicWebBase();
            $tkn=$this->utils->getToken($this->userId);

            $u=$pb.'/' .$this->utils->pubPrx($tkn,false).'form'.chr(31)
                .$pb.'/' .$this->utils->pubPrx($tkn,true).'form';

            $r->setData($u);
            $r->setStatus(200);
        }elseif ($action==="set_pps"){
            $value=$this->request->getParam("d");
            if($value!==null) {
                if($this->utils->setUserSettings(
                        BackendUtils::KEY_PSN,
                        $value, BackendUtils::PSN_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }elseif ($action==="get_pps"){
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_PSN,
                BackendUtils::PSN_DEF,
                $this->userId,$this->appName);
            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="get_uci") {
            $o = $this->getStateKeys('uci');
            foreach ($o as $k => $v) {
                $o[$k] = $this->config->getUserValue($this->userId, $this->appName, $k);
            }
            $o[BackendUtils::KEY_USE_DEF_EMAIL]=$this->config->getAppValue(
                $this->appName,BackendUtils::KEY_USE_DEF_EMAIL,'yes');
            $j = json_encode($o);
            if ($j !== false) {
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }
        }else if($action==="set_uci"){
            $d=$this->request->getParam("d");
            if($d!==null && strlen($d)<512) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                $dvo = json_decode($d);
                if ($dvo !== null) {
                    $o = $this->getStateKeys('uci');
                    foreach ($o as $k=>$v){
                        if(isset($dvo->{$k})){
                            $dv=$dvo->{$k};
                        }else{
                            $dv="";
                        }
                        $this->config->setUserValue(
                            $this->userId,$this->appName,
                            $k,$dv);
                    }
                    $r->setStatus(200);
                }
            }
        }else if($action==="get_eml") {
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_EML,
                BackendUtils::EML_DEF,
                $this->userId,$this->appName);
            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="set_eml") {
            $value=$this->request->getParam("d");
            if($value!==null) {
                if($this->utils->setUserSettings(
                        BackendUtils::KEY_EML,
                        $value, BackendUtils::EML_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }else if($action==="get_tz"){
            $tz=$this->utils->getUserTimezone($this->userId,$this->config);
            $r->setData($tz->getName());
            $r->setStatus(200);

        }else if($action==="get_cls") {
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_CLS,
                BackendUtils::CLS_DEF,
                $this->userId,$this->appName);

            if($a[BackendUtils::CLS_TS_MODE]==="0"
                && $a[BackendUtils::CLS_MAIN_ID]!=="-1"){

                $cal=$this->bc->getCalendarById(
                    $a[BackendUtils::CLS_MAIN_ID],$this->userId);
                if($cal!==null){
                    $a['curCal_color']=$cal['color'];
                    $a['curCal_name']=$cal['displayName'];
                }
            }

            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="set_cls") {
            $value=$this->request->getParam("d");
            if($value!==null) {
                $ts_mode=$this->utils->getUserSettings(
                    BackendUtils::KEY_CLS,BackendUtils::CLS_DEF,
                    $this->userId ,$this->appName)[BackendUtils::CLS_TS_MODE];

                if($this->utils->setUserSettings(
                        BackendUtils::KEY_CLS,
                        $value, BackendUtils::CLS_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $cls=$this->utils->getUserSettings(
                        BackendUtils::KEY_CLS,BackendUtils::CLS_DEF,
                        $this->userId ,$this->appName);

                    // Set ExternalModeSabrePlugin::AUTO_FIX_URI
                    $af_uri="";
                    if($cls[BackendUtils::CLS_TS_MODE]==="1" && $cls[BackendUtils::CLS_XTM_SRC_ID]!=="-1" && $cls[BackendUtils::CLS_XTM_AUTO_FIX]===true){
                        $ci=$this->bc->getCalendarById(
                            $cls[BackendUtils::CLS_XTM_SRC_ID],
                            $this->userId);
                        if($ci!==null){
                            $af_uri="/".$this->userId."/".$ci["uri"]."/";
                        }
                    }

                    $this->config->setUserValue($this->userId, $this->appName,
                        ExternalModeSabrePlugin::AUTO_FIX_URI,$af_uri);

                    if($ts_mode!==$cls[BackendUtils::CLS_TS_MODE]){
                        // ts_mode changed - disable page...
                        $this->config->setUserValue(
                            $this->userId, $this->appName,
                            'page_enabled','0');
                    }

                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }
        return $r;
    }

    function getStateKeys($s){
        $o=[];
        if($s==='uci'){
            $o = [BackendUtils::KEY_O_NAME => "",
                BackendUtils::KEY_O_EMAIL => "",
                BackendUtils::KEY_O_ADDR => "",
                BackendUtils::KEY_O_PHONE => ""];
        }
        return $o;
    }



}