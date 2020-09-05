<?php /** @noinspection PhpMissingParamTypeInspection */

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
            $pgs = $this->utils->getUserSettings(
                BackendUtils::KEY_PAGES, $this->userId);
            $changed = false;
            foreach ($pgs as $pageId => $v) {
                // JUST IN CASE: check if calendars are set
                if ($v[BackendUtils::PAGES_ENABLED] === 1) {

                    if($pageId === 'p0'){
                        $key=BackendUtils::KEY_CLS;
                    }else{
                        $key=BackendUtils::KEY_MPS.$pageId;
                    }

                    $other_cal = "-1";
                    $main_cal = $this->utils->getMainCalId($this->userId,$pageId, $this->bc, $other_cal);

                    $ts_mode = $this->utils->getUserSettings($key, $this->userId)[BackendUtils::CLS_TS_MODE];

                    if (($ts_mode === "0" && $main_cal === "-1") ||
                        ($ts_mode === "1" && ($main_cal === "-1" || $other_cal === "-1"))
                    ) {
                        $pgs[$pageId][BackendUtils::PAGES_ENABLED] = 0;
                        $changed = true;
                    }
                }
            }
            $j = json_encode($pgs);
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
            // pageId can be:
            //  "new" - create new page
            //  "delete" - delete the page with Id in $v
            $pageId=$this->request->getParam("p");
            $v=$this->request->getParam("v");
            if(!empty($pageId) && $v!==null){
                $vo=json_decode($v,true);
                if($vo!==null) {
                    $sts=200;
                    $vo_changed=false;
                    $pgs = $this->utils->getUserSettings(
                        BackendUtils::KEY_PAGES, $this->userId);
                    if (isset($pgs[$pageId])) {
                        // updating existing

                        // JUST IN CASE: check if email & calendars are set
                        if($vo[BackendUtils::PAGES_ENABLED]===1) {

                            if ($pageId === 'p0'){
                                $key=BackendUtils::KEY_CLS;
                            }else{
                                $key=BackendUtils::KEY_MPS.$pageId;
                            }

                            $other_cal="-1";
                            $main_cal=$this->utils->getMainCalId($this->userId,$pageId,$this->bc,$other_cal);

                            $ts_mode=$this->utils->getUserSettings(
                                $key, $this->userId)[BackendUtils::CLS_TS_MODE];

                            $email=$this->utils->getUserSettings(
                                BackendUtils::KEY_ORG,
                                $this->userId)[BackendUtils::ORG_EMAIL];

                            if(($ts_mode==="0" && $main_cal==="-1")
                                || ($ts_mode==="1" && ($main_cal==="-1" || $other_cal==="-1"))
                                || empty($email)
                            ){
                                $sts=202;
                                $r->setData('{"info":"'.$this->l->t("Action failed. Select calendar(s) first.").'"}');

                                $vo[BackendUtils::PAGES_ENABLED]=0;
                                $vo_changed=true;
                            }
                        }
                    } else if($pageId==="new") {
                        // creating new
                        if(count($pgs)>2){
                            $d=$this->config->getUserValue($this->userId, $this->appName, "c"."nk");
                            if($d==="" || ((hexdec(substr($d,0,4))>>15)&1)!==((hexdec(substr($d,4,4))>>12)&1) ){
                                $r->setData('{"contrib":"'.$this->l->t("More than 2 additional pages (10 maximum)").'"}');
                                $sts=202;
                            }
                        }
                        if($sts!==202) {
                            $i = 1;
                            for (; $i < 10; $i++) {
                                $_pid = "p" . $i;
                                if (!isset($pgs[$_pid])) {
                                    $pageId = $_pid;
                                    break;
                                }
                            }
                            if ($pageId === 'new') {
                                // could not find a free spot (10 max)
                                $sts = 202;
                                $r->setData('{"info":"' . $this->l->t("Page not added: 10 pages maximum") . '"}');
                            } else {
                                // add empty so we can get_mps & set_mps
                                $this->config->setUserValue(
                                    $this->userId, $this->appName,
                                    BackendUtils::KEY_MPS . $pageId, "");
                            }
                        }
                    }else if($pageId==="delete" && $vo["page"]!=="p0"){
                        $_pid=$vo["page"];

                        // check and delete directory page link if url for this page is used
                        $uca=explode(chr(31),$this->getPubURI($_pid));
                        $a=$this->utils->getUserSettings(
                            BackendUtils::KEY_DIR, $this->userId);
                        $l=count($a);
                        for($i=0;$i<$l;$i++){
                            $lu=$a[$i]['url'];
                            if($lu===$uca[0] || $lu===$uca[1]){
                                unset($a[$i]);
                            }
                        }
                        if($l!==count($a)){
                            // array_values = reindex
                            $dpl=json_encode(array_values($a));
                            if($dpl!==false) {
                                $this->config->setUserValue(
                                    $this->userId, $this->appName,
                                    BackendUtils::KEY_DIR, $dpl);
                            }
                        }

                        // delete the page
                        unset($pgs[$_pid]);
                        $this->config->deleteUserValue(
                            $this->userId,$this->appName,
                            BackendUtils::KEY_MPS.$_pid);
                    }else{
                        \OC::$server->getLogger()->error("Bad pageId/page_action");
                        $sts=400;
                    }

                    if($sts===200 || $vo_changed===true) {
                        if($pageId!=="delete") {
                            // filter new "arrivals"
                            $sa = [];
                            foreach (BackendUtils::PAGES_VAL_DEF as $_pid => $v) {
                                if (isset($vo[$_pid]) && gettype($vo[$_pid]) === gettype($v)) {
                                    $sa[$_pid] = $vo[$_pid];
                                } else {
                                    $sa[$_pid] = $v;
                                }
                            }
                            $pgs[$pageId] = $sa;
                        }

                        if (!$this->utils->setUserSettings(
                                BackendUtils::KEY_PAGES,
                                "", $pgs,
                                $this->userId, $this->appName) === true
                        ) {
                            $sts=500;
                        }
                    }

                    $r->setStatus($sts);
                }
            }
        } elseif ($action==='get_puburi'){
            $pageId=$this->request->getParam("p",'p0');
            if(empty($pageId)) $pageId='p0';

            $pgs=$this->utils->getUserSettings(
                        BackendUtils::KEY_PAGES, $this->userId);
            if(isset($pgs[$pageId])) {
                $r->setData($this->getPubURI($pageId));
                $r->setStatus(200);
            }

        } elseif ($action==='get_diruri'){
            $gos="sub".'str';$c=$this->config->getUserValue($this->userId, $this->appName, "cn".'k');$go="hexd".'ec';
            $dir = $this->utils->getUserSettings(
                BackendUtils::KEY_DIR, $this->userId);
            if(count($dir)===0){
                $r->setData('{"info":"'.$this->l->t("Please setup the directory page first.").'"}');
                $r->setStatus(202);
            }else if(empty($c)||(($go($gos($c,0,0b100))>>15)&0b1)!==(($go($gos($c,0b100,4))>>0xC) &0b1)){
                $r->setData('{"contrib":"'.$this->l->t("Directory page").'"}');
                $r->setStatus(200+2);
            }else{
                $r->setData(
                    $this->utils->getPublicWebBase().'/pub/'
                    // "p0" will return only the encoded username
                    .$this->utils->getToken($this->userId,"p0").'/dir'.chr(31)."");
                $r->setStatus(200);
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

            $j=json_encode($this->getMoreProps($a));
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="set_cls") {
            $value=$this->request->getParam("d");
            if($value!==null) {

                if($this->setClsMps(
                        BackendUtils::KEY_CLS,
                        BackendUtils::CLS_DEF,
                        $value,'p0')===true)
                {
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }else if($action==="get_mps") {
            $pageId=$this->request->getParam("p");
            // must have a pageId for this action
            if(!empty($pageId) && null!==$this->config->getUserValue(
                    $this->userId,$this->appName,
                    BackendUtils::KEY_MPS.$pageId,null))
            {
                $a=$this->utils->getUserSettings(
                    BackendUtils::KEY_MPS.$pageId, $this->userId);

                $j=json_encode($this->getMoreProps($a));
                if($j!==false){
                    $r->setData($j);
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }

        }else if($action==="set_mps") {
            $pageId=$this->request->getParam("p");
            $value=$this->request->getParam("d");
            // must have a pageId for this action
            if(!empty($pageId) && $value!==null
                && null!==$this->config->getUserValue(
                    $this->userId,$this->appName,
                    BackendUtils::KEY_MPS.$pageId,null))
            {
                if($this->setClsMps(
                    BackendUtils::KEY_MPS.$pageId,
                    BackendUtils::MPS_DEF,
                    $value,$pageId)===true)
                {
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }else if($action==="get_dir") {
            $a=$this->utils->getUserSettings(
                BackendUtils::KEY_DIR, $this->userId);
            $j=json_encode($a);
            if($j!==false){
                $r->setData($j);
                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }

        }else if($action==="set_dir") {
            $value=$this->request->getParam("d");
            if($value!==null) {

                /** @noinspection PhpUnhandledExceptionInspection */
                $this->config->setUserValue($this->userId, $this->appName,BackendUtils::KEY_DIR,$value);

                $r->setStatus(200);
            }else{
                $r->setStatus(500);
            }
        }else if($action==="set_k") {
            $value=$this->request->getParam("d");
            $sts=500;
            if($value!==null) {
                $a=json_decode($value,true);
                if($a!==null && isset($a['k'])){
                    $k = str_replace("-", "", trim($a['k']));
                    if(strlen($k)===20&&((hexdec(substr($k,0,4))>>15)&1)===((hexdec(substr($k,4,4))>>12)&1)){
                            /** @noinspection PhpUnhandledExceptionInspection */
                        $this->config->setUserValue($this->userId, $this->appName, "cnk", $k);
                        $sts=200;
                    }
                }
            }
            $r->setStatus($sts);
        }else if($action==="get_k") {
            $r->setData($this->config->getUserValue($this->userId, $this->appName, "cnk")!==""?"_":"");
            $r->setStatus(200);
        }
        return $r;
    }

    /**
     * @param string $key BackendUtils::KEY_MPS or BackendUtils::KEY_CLS
     * @param $def
     * @param $value
     * @param $pageId
     * @return bool
     * @noinspection PhpDocMissingThrowsInspection
     */
    function setClsMps($key,$def,$value,$pageId){

        $o_cms=$this->utils->getUserSettings(
            $key,$this->userId);

        if($this->utils->setUserSettings(
                $key, $value, $def,
                $this->userId,$this->appName)===true
        ){
            // this can be cls or mps
            $cms=$this->utils->getUserSettings($key,$this->userId);

            // This is needed to get BackendUtils::CLS_XTM_AUTO_FIX
            $real_cls=$this->utils->getUserSettings(BackendUtils::KEY_CLS,$this->userId);

            // Set ExternalModeSabrePlugin::AUTO_FIX_URI
            if($real_cls[BackendUtils::CLS_XTM_AUTO_FIX]===false){
                $this->config->setUserValue($this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI,"");
            }else{
                $afu=$this->config->getUserValue(
                    $this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI);

                $o_calId=$o_cms[BackendUtils::CLS_XTM_SRC_ID];
                $calId=$cms[BackendUtils::CLS_XTM_SRC_ID];

                $o_cUri="";
                $cUri="";

                $cals=$this->bc->getCalendarsForUser($this->userId);
                $l=count($cals);
                for($i=0;$i<$l;$i++){
                    $cal=$cals[$i];
                    if($cal['id']===$o_calId){
                        $o_cUri=$pageId."/".$this->userId."/".$cal['uri']."/".chr(31);
                    }
                    if($cal['id']===$calId){
                        $cUri=$pageId."/".$this->userId."/".$cal['uri']."/".chr(31);
                    }
                }
//                af_uri="/".$this->userId."/".$ci["uri"]."/";

                if(!empty($o_cUri)){
                    $afu=str_replace($o_cUri,"",$afu);
                }

                if(!empty($cUri) && $cms[BackendUtils::CLS_TS_MODE]==="1"){
                    $afu.=$cUri;
                }

                $this->config->setUserValue($this->userId, $this->appName,
                    ExternalModeSabrePlugin::AUTO_FIX_URI,$afu);
            }


            if($o_cms[BackendUtils::CLS_TS_MODE]!==$cms[BackendUtils::CLS_TS_MODE]){
                // ts_mode changed - disable the page...
                $pgs = $this->utils->getUserSettings(
                    BackendUtils::KEY_PAGES, $this->userId);

                $pgs[$pageId][BackendUtils::PAGES_ENABLED]=0;

                $this->utils->setUserSettings(
                    BackendUtils::KEY_PAGES,
                    "", $pgs,
                    $this->userId, $this->appName);
            }
            return true;
        }else{
            return false;
        }

    }

    /**
     * @param array $a can be CLS or MPS
     * @return array
     */
    function getMoreProps($a){
        if($a[BackendUtils::CLS_TS_MODE]==="0"
            && $a[BackendUtils::CLS_MAIN_ID]!=="-1"){

            $cal=$this->bc->getCalendarById(
                $a[BackendUtils::CLS_MAIN_ID],$this->userId);
            if($cal!==null){
                $a['curCal_color']=$cal['color'];
                $a['curCal_name']=$cal['displayName'];
            }
        }
        return $a;
    }

    function getPubURI($pageId){
        $pb = $this->utils->getPublicWebBase();
        $tkn = $this->utils->getToken($this->userId,$pageId);
        return $pb . '/' . $this->utils->pubPrx($tkn, false) . 'form' . chr(31)
            . $pb . '/' . $this->utils->pubPrx($tkn, true) . 'form';
    }

}