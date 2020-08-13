<?php
/** @noinspection PhpComposerExtensionStubsInspection */
namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\ExternalModeSabrePlugin;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class StateController extends Controller{
    
    private $userId;
    private $config;
    private $utils;
    private $bc;
    private $l;

    public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                IConfig $config,
                                IL10N $l,
                                BackendUtils $utils,
                                BackendManager $backendManager){
        parent::__construct($AppName, $request);
        
        $this->userId=$UserId;
        $this->config=$config;
        $this->l=$l;
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

        if ($action==='get_pages') {
            $a = $this->utils->getUserSettings(
                BackendUtils::KEY_PAGES, $this->userId);
            $changed = false;
            foreach ($a as $page => $v) {
                // JUST IN CASE: check if calendars are set
                if ($v[BackendUtils::PAGES_ENABLED] === 1) {
                    if ($page === 'p0') {
                        // main page
                        $other_cal = "-1";
                        $main_cal = $this->utils->getMainCalId($this->userId,'', $this->bc, $other_cal);

                        $cls = $this->utils->getUserSettings(
                            BackendUtils::KEY_CLS, $this->userId);
                        $ts_mode = $cls[BackendUtils::CLS_TS_MODE];

                        if (($ts_mode === "0" && $main_cal === "-1") ||
                            ($ts_mode === "1" && ($main_cal === "-1" || $other_cal === "-1"))
                        ) {
                            $a[$page][BackendUtils::PAGES_ENABLED] = 0;
                            $changed = true;
                        }
                    } else {
                        // additional pages
                        // TODO...
                    }
                }
            }
            $j = json_encode($a);
            if ($j !== false) {
                if ($changed === true) {
                    $this->config->setUserValue(
                        $this->userId, $this->appName,
                        BackendUtils::KEY_PAGES, $j);
                }
                $r->setData($j);
                $r->setStatus(200);
            } else {
                $r->setStatus(500);
            }

        } elseif ($action==='set_pages'){
            $p=$this->request->getParam("p");
            $v=$this->request->getParam("v");
            if($p!==null && $v!==null){
                $vo=json_decode($v,true);
                if($vo!==null) {
                    $sts=200;
                    $cur = $this->utils->getUserSettings(
                        BackendUtils::KEY_PAGES, $this->userId);
                    if (isset($cur[$p])) {
                        // updating existing

                        // JUST IN CASE: check if email & calendars are set
                        if($vo[BackendUtils::PAGES_ENABLED]===1) {
                            if ($p === 'p0') {
                                // main page
                                $other_cal="-1";
                                $main_cal=$this->utils->getMainCalId($this->userId,'',$this->bc,$other_cal);

                                $cls=$this->utils->getUserSettings(
                                    BackendUtils::KEY_CLS, $this->userId);
                                $ts_mode=$cls[BackendUtils::CLS_TS_MODE];

                                $org=$this->utils->getUserSettings(
                                    BackendUtils::KEY_ORG, $this->userId);

                                if(($ts_mode==="0" && $main_cal==="-1") ||
                                    ($ts_mode==="1" && ($main_cal==="-1" || $other_cal==="-1"))
                                    || empty($org[BackendUtils::ORG_EMAIL])
                                ){
                                    $sts=412;
                                    $vo[BackendUtils::PAGES_ENABLED]=0;
                                }
                            } else {
                                // additional pages
                                // TODO:...
                            }
                        }
                    } else if($p==="new") {
                        // creating new
//                        $r->setData('{"contrib":"'.$this->l->t("More than 2 additional pages (10 maximum)").'"}');
//                        $sts=202;

                        $i=1;
                        for(;$i<10;$i++){
                            $k="p".$i;
                            if(!isset($cur[$k])){
                                $p=$k;
                                break;
                            }
                        }
                        // TODO: check for contributors $i>2
                        if($p==='new'){
                            // more than 10 spots
                            $sts=202;
                            $r->setData('{"info":"'.$this->l->t("Page not added: 10 pages maximum").'"}');
                        }
                    }else if($p==="delete" && $vo["page"]!=="p0"){
                        $page=$vo["page"];
                        unset($cur[$page]);
                        $this->config->deleteUserValue(
                            $this->userId,$this->appName,
                            BackendUtils::KEY_MPS.$page);
                    }

                    if($sts===200) {
                        // filter
                        if($p!=="delete") {
                            $sa = [];
                            foreach (BackendUtils::PAGES_VAL_DEF as $k => $v) {
                                if (isset($vo[$k]) && gettype($vo[$k]) === gettype($v)) {
                                    $sa[$k] = $vo[$k];
                                } else {
                                    $sa[$k] = $v;
                                }
                            }
                            $cur[$p] = $sa;
                        }

                        if (!$this->utils->setUserSettings(
                                BackendUtils::KEY_PAGES,
                                "", $cur,
                                $this->userId, $this->appName) === true
                        ) {
                            $sts=500;
                        }
                    }

                    $r->setStatus($sts);
                }
            }
        } elseif ($action==='get_puburi'){
            $p=$this->request->getParam("p");
            if($p!==null) {
                $pgs=$this->utils->getUserSettings(
                            BackendUtils::KEY_PAGES, $this->userId);
                if($p==='p0' || isset($pgs[$p])) {

                    $pb = $this->utils->getPublicWebBase();
                    $tkn = $this->utils->getToken(
                        $this->userId,($p==="p0"?"":$p));

                    $u = $pb . '/' . $this->utils->pubPrx($tkn, false) . 'form' . chr(31)
                        . $pb . '/' . $this->utils->pubPrx($tkn, true) . 'form';

                    $r->setData($u);
                    $r->setStatus(200);
                }
            }
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
                BackendUtils::KEY_PSN, $this->userId);
            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="get_uci") {
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_ORG, $this->userId);
            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="set_uci"){
            $d=$this->request->getParam("d");
            if($d!==null && strlen($d)<512) {
                if($this->utils->setUserSettings(
                        BackendUtils::KEY_ORG,
                        $d, BackendUtils::ORG_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }else if($action==="get_eml") {
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_EML, $this->userId);
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
                BackendUtils::KEY_CLS, $this->userId);

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
                    BackendUtils::KEY_CLS,$this->userId)[BackendUtils::CLS_TS_MODE];

                if($this->utils->setUserSettings(
                        BackendUtils::KEY_CLS,
                        $value, BackendUtils::CLS_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $cls=$this->utils->getUserSettings(
                        BackendUtils::KEY_CLS,$this->userId);

                    // TODO: autofix for additional calendars...
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
                        // ts_mode changed - disable all pages...

                        $a = $this->utils->getUserSettings(
                            BackendUtils::KEY_PAGES, $this->userId);
                        foreach ($a as $page => $v) {
                            if ($v[BackendUtils::PAGES_ENABLED] === 1) {
                                $a[$page][BackendUtils::PAGES_ENABLED]=0;
                            }
                        }

                        $this->utils->setUserSettings(
                            BackendUtils::KEY_PAGES,
                            "", $a,
                            $this->userId, $this->appName);
                    }

                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }
        return $r;
    }
}