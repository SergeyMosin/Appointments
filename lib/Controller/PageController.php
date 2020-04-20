<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */
namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use Sabre\VObject\Reader;

class PageController extends Controller {
    const RND_SPS = 'abcdefghijklmnopqrstuvwxyz1234567890';
    const RND_SPU = '1234567890ABCDEF';

    const KEY_PSN="page_options";
    const PSN_FORM_TITLE="formTitle";
    const PSN_NWEEKS="nbrWeeks";
    const PSN_EMPTY="showEmpty";
    const PSN_FNED="startFNED";
    const PSN_WEEKEND="showWeekends";
    const PSN_TIME2="time2Cols";
    const PSN_GDPR="gdpr";
    const PSN_ON_CANCEL="whenCanceled";
    const PSN_PAGE_TITLE="pageTitle";
    const PSN_PAGE_SUB_TITLE="pageSubTitle";
    const PSN_PAGE_STYLE="pageStyle";

    const PSN_DEF=array(
        self::PSN_FORM_TITLE=>"",
        self::PSN_NWEEKS=>"1",
        self::PSN_EMPTY=>true,
        self::PSN_FNED=>false, // start at first not empty day
        self::PSN_WEEKEND=>false,
        self::PSN_TIME2=>false,
        self::PSN_GDPR=>"",
        self::PSN_ON_CANCEL=>"mark",
        self::PSN_PAGE_TITLE=>"",
        self::PSN_PAGE_SUB_TITLE=>"",
        self::PSN_PAGE_STYLE=>""
    );

    private $userId;
    private $c;
    private $um;
    private $mailer;
    private $l;
    /** @var \OCA\Appointments\Backend\IBackendConnector $bc */
    private $bc;
    private $utils;

	public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                IConfig $c,
                                IUserManager $a,
                                IMailer $mailer,
                                IL10N $l,
                                BackendManager $backendManager,
                                BackendUtils $utils){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
        $this->c=$c;
        $this->um=$a;
        $this->mailer=$mailer;
        $this->l=$l;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc=$backendManager->getConnector();
        $this->utils=$utils;
	}

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        $t=new TemplateResponse($this->appName, 'index');

        $csp=$t->getContentSecurityPolicy();
        if($csp===null){
            $csp=new ContentSecurityPolicy();
            $t->setContentSecurityPolicy($csp);
        }
        $csp->addAllowedFrameDomain('\'self\'');
        return  $t;// templates/index.php
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function calgetweek(){
        // t must be d[d]-mm-yyyy
        $t = $this->request->getParam("t");
        $r=new SendDataResponse();

        if($t===null){
            $r->setStatus(400);
            return $r;
        }

        // Because of floating timezones...
        $utz=$this->utils->getUserTimezone($this->userId,$this->c);
        try {
            $t_start=\DateTime::createFromFormat(
                'j-m-Y H:i:s',$t.' 00:00:00',$utz);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error($e->getMessage().", timezone: ".$utz->getName());
            $r->setStatus(400);
            return $r;
        }

        $cal_id=$this->c->getUserValue(
            $this->userId,
            $this->appName,
            'cal_id');
        if(empty($cal_id) || $this->bc->getCalendarById($cal_id,$this->userId)===null){
            $r->setStatus(400);
            return $r;
        }

        $r->setStatus(200);

        $t_end=clone $t_start;
        $t_end->setTimestamp($t_start->getTimestamp()+(7*86400));

        $out=$this->bc->queryRange($cal_id,$t_start,$t_end,true);
        if($out!==null){
            $r->setData($out);
        }

        return $r;
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function callist(){

        $cals=$this->bc->getCalendarsForUser($this->userId);
        $out='';
        $c30=chr(30);
        $c31=chr(31);
        foreach ($cals as $c){
            $out.=
                $c['displayName'].$c30.
                $c['color'].$c30.
                $c['id'].$c31;
        }
        return substr($out,0,-1);
    }

    /**
     * @NoAdminRequired
     * @throws \OCP\PreConditionNotMetException
     * @throws \ErrorException
     */
    public function state(){
        $action = $this->request->getParam("a");
        $r=new SendDataResponse();
        $r->setStatus(400);

        if($action==="get"){

            $cal_id=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                'cal_id',
                ''
            );

            $enabled='';
            if(!empty($cal_id)){
                $cal=$this->bc->getCalendarById($cal_id,$this->userId);
                if($cal!==null){

                    $c30=chr(30);
                    $c31=chr(31);
                    $rd = $cal['displayName'].$c30.
                        $cal['color'].$c30.
                        $cal['id'].$c31;

                    $enabled=$this->c->getUserValue(
                        $this->userId,
                        $this->appName,
                        'page_enabled',
                        '0'
                    );
                    $rd.=$enabled;

                    $r->setData($rd);
                    $r->setStatus(200);
                }
            }else{
                $r->setStatus(204);
            }
            if(empty($enabled)){
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->c->setUserValue(
                    $this->userId,
                    $this->appName,
                    'page_enabled',
                    '0');
            }
        }elseif($action==="set"){
            $v=$this->request->getParam("url"); //url is actually id
            if($v!==null) {
                $cal=$this->bc->getCalendarById($v,$this->userId);
                if($cal!==null) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $this->c->setUserValue(
                        $this->userId,
                        $this->appName,
                        'cal_id',
                        $v);

                    $r->setStatus(200);
                }
            }
            // Disable automatically when changing calendars
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->c->setUserValue(
                $this->userId,
                $this->appName,
                'page_enabled',
                '0');
        }elseif($action==="enable"){
            $v=$this->request->getParam("v");

            $r->setStatus(200);
            if($v==='1'){
                $c=$this->c;
                $u=$this->userId;
                $a=$this->appName;
                if(empty($c->getUserValue($u,$a, BackendUtils::KEY_O_NAME))
                    || empty($c->getUserValue($u,$a, BackendUtils::KEY_O_ADDR))
                    || empty($c->getUserValue($u,$a, BackendUtils::KEY_O_EMAIL))){
                    $r->setStatus(412);
                    $v='0';
                }
            }else{
                $v='0';
            }
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->c->setUserValue(
                $this->userId,
                $this->appName,
                'page_enabled',
                $v);
        }elseif ($action==='get_puburi'){
            $u=$this->getPublicWebBase().'/'
            .$this->pubPrx($this->getToken($this->userId)).'form';

            $r->setData($u);
            $r->setStatus(200);
        }elseif ($action==="set_pps"){
            $value=$this->request->getParam("d");
            if($value!==null) {
                if($this->utils->setUserSettings(
                        self::KEY_PSN,
                        $value, self::PSN_DEF,
                        $this->userId,$this->appName)===true
                ){
                    $r->setStatus(200);
                }else{
                    $r->setStatus(500);
                }
            }
        }elseif ($action==="get_pps"){
            $a=$this->utils->getUserSettings(
                self::KEY_PSN,
                self::PSN_DEF,
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
                $o[$k] = $this->c->getUserValue($this->userId, $this->appName, $k);
            }
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
                        $this->c->setUserValue(
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


    /**
     * @param $token
     * @return string|false user name on success, false=not verified
     * @throws \ErrorException
     */
    private function verifyToken($token){
        if(empty($token) || strlen($token)>256) return false;
        $token=str_replace("_","/",$token);
        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($this->c->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }
        $td=$this->utils->decrypt($token,$key,$iv);
        if(strlen($td)>4 && substr($td,0,4)===hash( 'adler32', substr($td,4),true)){
            return substr($td,4);
        }else{
            return false;
        }
    }

    /**
     * @param $uid
     * @return string
     * @throws \ErrorException
     */
    private function getToken($uid){
        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($this->c->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }
        $tkn=$this->utils->encrypt(hash ( 'adler32' , $uid,true).$uid,$key,$iv);
        return urlencode(str_replace("/","_",$tkn));
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws \ErrorException
     */
    public function form(){
        $uid=$this->verifyToken($this->request->getParam("token"));
        if($uid===false){
            return new NotFoundResponse();
        }

        if($this->request->getParam("sts")!==null) {
            $tr=$this->showFinish('public',$uid);
        }else{
            $tr=$this->showForm('public',$uid);
        }
        return $tr;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws \ErrorException
     * @noinspection PhpUnused
     */
    public function formPost(){
        $uid=$this->verifyToken($this->request->getParam("token"));
        if($uid===false){
            return new NotFoundResponse();
        }
        return $this->showFormPost($uid);
    }

    private function pubPrx($token){
        return 'pub/'.$token.'/';
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @noinspection PhpUnused
     * @throws \ErrorException
     */
    public function cncf(){
        $uid=$this->verifyToken($this->request->getParam("token"));
        $pd=$this->request->getParam("d");
        if($uid===false || $pd===null || strlen($pd)>512
            || (($a=substr($pd,0,1))!=='0') && $a!=='1'){
            return new NotFoundResponse();
        }

        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));

        $uri=$this->utils->decrypt(substr($pd,1),$key).".ics";
        if(empty($uri)){
            return $this->pubErrResponse($uid);
        }

        $cal_id=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_id');
        if(empty($cal_id)) {
            return $this->pubErrResponse($uid);
        }

        $page_text='';
        if($a==='1') {
            // Confirm

            // Emails are handled by the DavListener... set the Hint
            $ses=\OC::$server->getSession();
            $ses->set(
                BackendUtils::APPT_SES_KEY_HINT,
                BackendUtils::APPT_SES_CONFIRM);

            //TODO: 'medium' date_time format is forced in BackendUtils->dataConfirmAttendee() because messages need to be rewritten.
            list($sts, $date_time) = $this->bc->confirmAttendee($uid, $cal_id, $uri);

            if ($sts === 0) { // Appointment is confirmed successfully
                // TRANSLATORS Your {{Date Time}} appointment is confirmed.
                $page_text = $this->l->t("Your %s appointment is confirmed.", [$date_time]);
            }
        }else{
            // Cancel

            // Emails are handled by the DavListener... set the Hint
            $ses=\OC::$server->getSession();
            $ses->set(
                BackendUtils::APPT_SES_KEY_HINT,
                BackendUtils::APPT_SES_CANCEL);

            $pps=$this->utils->getUserSettings(
                self::KEY_PSN,self::PSN_DEF,
                $uid,$this->appName);
            // This can be 'mark' or 'reset'
            $mr=$pps[self::PSN_ON_CANCEL];
            if($mr==='mark') {
                // Just Cancel
                //TODO: 'medium' date_time format is forced in BackendUtils->dataCancelAttendee() because messages need to be rewritten.
                list($sts, $date_time) = $this->bc->cancelAttendee($uid, $cal_id, $uri);

            }else{
                // Delete and Reset ($date_time can be an empty string here)
                list($sts, $date_time, $dt_info, $is_floating) = $this->bc->deleteCalendarObject($uid, $cal_id, $uri);

                if(empty($dt_info)){
                    \OC::$server->getLogger()->error('can not re-create appointment, no dt_info');
                }else{
                    $cr=$this->addAppointments($uid,$dt_info,$is_floating);
                    if($cr[0]!=='0'){
                        \OC::$server->getLogger()->error('addAppointments() failed '.$cr);
                    }
                }
            }

            if ($sts === 0) { // Appointment is cancelled successfully
                // TRANSLATORS Your {{Date Time}} appointment is canceled.
                $page_text = $this->l->t("Your %s appointment is canceled.", [$date_time]);
            }
        }

        if ($sts === 0) {
            // Confirm/Cancel OK.
            $tr=$this->getPublicTemplate("public/thanks",$uid);
            $tr->setParams([
                // TRANSLATORS Meaning the booking process is finished
                'appt_c_head' => $this->l->t("All done."),
                'appt_c_msg' => $page_text
            ]);
            $tr->setStatus(200);
        } else {
            // Error
            // TODO: add phone number to "contact us ..."
            $org_email = $this->c->getUserValue(
                $uid, $this->appName,
                BackendUtils::KEY_O_EMAIL);
            $tr=$this->getPublicTemplate("public/formerr",$uid);
            $tr->setParams(['appt_e_ne' => $org_email]);
            $tr->setStatus(500);
        }
        return $tr;
    }

    private function pubErrResponse($userId){
        $tr=$this->getPublicTemplate('public/formerr',$userId);
        $tr->setStatus(500);
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @noinspection PhpUnused
     */
    public function formBase(){
        if($this->request->getParam("sts")!==null) {
            $tr=$this->showFinish('base',$this->userId);
        }else{
            $tr=$this->showForm('base',$this->userId);
        }
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @throws \ErrorException
     * @noinspection PhpUnused
     */
    public function formBasePost(){
        return $this->showFormPost($this->userId);
    }

    /**
     * @param $uid
     * @return RedirectResponse
     * @throws \ErrorException
     */
    public function showFormPost($uid){

        // sts: 0=OK, 1=bad input, 2=server error
        $ok_uri="form?sts=0";
        $bad_input_url="form?sts=1";
        $server_err_url="form?sts=2";

        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        if(empty($key)){
            $rr=new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }

        $post=$this->request->getParams();

        if(strlen($post['adatetime'])>127
            || preg_match('/[^a-zA-Z0-9+\/=]/',$post['adatetime'])
            || strlen($post['name']) > 64
            || strlen($post['name']) < 3
            || strlen($post['email']) > 128
            || strlen($post['email']) < 4
            || strlen($post['phone']) > 32
            || strlen($post['phone']) < 4){
            $rr=new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $dc=$this->utils->decrypt($post['adatetime'],$key);
        if(empty($dc) || strpos($dc,'|')===false){
            $rr=new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

//        Session start(time()).'|'.object uri
        $da=explode('|',$dc);
        $ti=intval($da[0]);// session start, 15 minute max
        $ts=time();

        if($ts<$ti || $ts>$ti+900
            || strlen($da[1])>64
            || preg_match('/[^\PC ]/u',$post['name'])
            || $this->mailer->validateMailAddress($post['email'])===false
            || preg_match('/[^0-9 .()\-+,\/]/',$post['phone'])){
            $rr=new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $post['name']=htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8');

        // Input seems OK...

        $cal_id=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_id',
            ''
        );

        if(empty($cal_id) || $this->bc->getCalendarById($cal_id,$uid)===null) {
            $rr=new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }

        // cal_id is good...

        // TODO: make sure that the appointment time is within the actual range

        // Emails are handled by the DavListener...
        // ... set the Hint and Confirm/Cancel buttons info
        $ses=\OC::$server->getSession();
        $ses->set(
            BackendUtils::APPT_SES_KEY_HINT,
            BackendUtils::APPT_SES_BOOK);
        $ses->set(
            BackendUtils::APPT_SES_KEY_BURL,
            $this->getPublicWebBase().'/' .$this->pubPrx($this->getToken($uid)).'cncf?d=');
        $ses->set(
            BackendUtils::APPT_SES_KEY_BTKN,
            urlencode($this->utils->encrypt(substr($da[1],0,-4),$key))
        );

        // Update appointment data
        $r=$this->bc->setAttendee($uid,$cal_id,$da[1],$post);

        if($r>0){
            // &r=1 means there was a race and someone else has booked that slot
            $rr=new RedirectResponse($server_err_url.($r===1?"&r=1":"")."&eml=".urlencode($post['email']));
            $rr->setStatus(303);
            return $rr;
        }

        $uri=$ok_uri."&d=".urlencode(
                $this->utils->encrypt(pack('I',time()).$post['email'],$key)
            );
        $rr=new RedirectResponse($uri);
        $rr->setStatus(303);
        return $rr;
    }

    /**
     * @param string $render
     * @param string $uid
     * @return TemplateResponse
     */
    public function showFinish($render,$uid){
        // Redirect to finalize page...
        // sts: 0=OK, 1=bad input, 2=server error
        // sts=2&r=1: race condition while booking
        // d=time and email

        $tmpl='public/formerr';
        $rs=500;
        $param=[
            'appt_c_head'=>$this->l->t("Almost done..."),
        ];

        $sts=$this->request->getParam('sts');

        if($sts==='2') {
            $em=$this->request->getParam('eml');
            if($this->request->getParam('r')==='1'){
                $param['appt_e_rc']='1';
            }elseif ($em!==null && $this->mailer->validateMailAddress($em)!==false){
                $param['appt_e_ne']=$em;
            }
        }elseif($sts==='0') {
            $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
            $dd=$this->utils->decrypt($this->request->getParam('d',''),$key);
            if(strlen($dd)>7){
                $ts=unpack('Iint',substr($dd,0,4))['int'];
                $em=substr($dd,4);
                if($ts+8>=time()){
                    if($this->mailer->validateMailAddress($em)) {
                        $tmpl = 'public/thanks';
                        $param['appt_c_msg'] = $this->l->t("We have sent an email to %s, please open it and click on the confirmation link to finalize your appointment request",[$em]);
                        $rs = 200;
                    }
                }else{
                    // TODO: graceful redirect somewhere, via js perhaps??
                    $tmpl = 'public/thanks';
                    $param['appt_c_head']=$this->l->t("Info");
                    $param['appt_c_msg'] = $this->l->t("Link Expired...");
                    $rs = 409;
                }
            }
        }

//        $tr=new TemplateResponse($this->appName,$tmpl,$param,$render);
        if($render==="public"){
            $tr = $this->getPublicTemplate($tmpl,$uid);
        }else {
            $tr = new TemplateResponse($this->appName,$tmpl, [],$render);
        }
        $tr->setParams($param);
        $tr->setStatus($rs);
        return $tr;
    }

    /**
     * @param $render
     * @param $uid
     * @return TemplateResponse
     */
    public function showForm($render,$uid){
        $templateName='public/form';
        if($render==="public"){
            $tr = $this->getPublicTemplate($templateName,$uid);
        }else {
            $tr = new TemplateResponse($this->appName,$templateName, [],$render);
        }

        $pps=$this->utils->getUserSettings(
            self::KEY_PSN,self::PSN_DEF,
            $uid,$this->appName);

        $ft=$pps[self::PSN_FORM_TITLE];
        $org_name=$this->c->getUserValue(
            $uid,$this->appName, BackendUtils::KEY_O_NAME);

        $params=[
            'appt_sel_opts'=>'',
            'appt_state'=>'0',
            'appt_org_name'=>!empty($org_name)?$org_name:'Organization Name',
            'appt_org_addr'=>str_replace(array("\r\n","\n","\r"),'<br>',$this->c->getUserValue(
                $uid, $this->appName, BackendUtils::KEY_O_ADDR,
                "123 Main Street\nNew York, NY 45678")),
            'appt_form_title'=>!empty($ft)?$ft:$this->l->t('Book Your Appointment'),
            'appt_pps'=>'',
            'appt_gdpr'=>'',
            'appt_inline_style'=>$pps[self::PSN_PAGE_STYLE]
        ];

        // google recaptcha
        // 'jsfiles'=>['https://www.google.com/recaptcha/api.js']
        //        $tr->getContentSecurityPolicy()->addAllowedScriptDomain('https://www.google.com/recaptcha/')->addAllowedScriptDomain('https://www.gstatic.com/recaptcha/')->addAllowedFrameDomain('https://www.google.com/recaptcha/');

        if($this->c->getUserValue(
            $uid,
            $this->appName,
            'page_enabled',
            '0')!=='1'){

            $params['appt_state']='4';
            $tr->setParams($params);
            return $tr;
        }

        if(empty($org_name) || empty($this->c->getUserValue(
            $uid, $this->appName, BackendUtils::KEY_O_EMAIL))
        ){
            $params['appt_state']='7';
            $tr->setParams($params);
            return $tr;
        }

        $cid=null;
        $cid=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_id');

        if(empty($cid) || $this->bc->getCalendarById($cid,$uid)===null){
            $tr->setParams($params);
            return $tr;
        }

        $params['appt_state']='1';

        $hkey=$this->c->getAppValue($this->appName, 'hk');
        if(empty($hkey)){
            $tr->setParams($params);
            return $tr;
        }
        $params['appt_state']='2';

        $nw=intval($pps[self::PSN_NWEEKS]);
        $nw++; // for alignment in the form

        // Because of floating timezones...
        $utz=$this->utils->getUserTimezone($uid,$this->c);
        try {
            $t_start = new \DateTime('now', $utz);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error($e->getMessage().", timezone: ".$utz->getName());
            $params['appt_state']='6';
            $tr->setParams($params);
            return $tr;
        }

        $t_end=clone $t_start;
        $t_end->setTimestamp($t_start->getTimestamp()+(7*$nw*86400));
        $t_end->setTime(0,0);

        $out=$this->bc->queryRange($cid,$t_start,$t_end);

        if(empty($out)) {
            $params['appt_state']='5';
        }

        $params['appt_sel_opts'] = $out;

        $params['appt_pps']=self::PSN_NWEEKS.":".$pps[self::PSN_NWEEKS].'.'.
            self::PSN_EMPTY.":".($pps[self::PSN_EMPTY]?"1":"0").'.'.
            self::PSN_FNED.":".($pps[self::PSN_FNED]?"1":"0").'.'.
            self::PSN_WEEKEND.":".($pps[self::PSN_WEEKEND]?"1":"0").'.'.
            self::PSN_TIME2.":".($pps[self::PSN_TIME2]?"1":"0");

        // GDPR
        $params['appt_gdpr']=$pps[self::PSN_GDPR];

        $tr->setParams($params);

        //$tr->getContentSecurityPolicy()->addAllowedFrameAncestorDomain('\'self\'');
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @throws \Exception
     */
    public function help(){

        $f=\OC::$server->getAppManager()->getAppPath($this->appName).'/templates/help.php';

        // Include
        ob_start();
        try {
            include $f;
            $data = ob_get_contents();
        } catch (\Exception $e) {
            @ob_end_clean();
            throw $e;
        }
        @ob_end_clean();

        return $data;
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function caladd(){
        return $this->addAppointments(
            $this->userId,
            $this->request->getParam("d"),
            $this->request->getParam("tz")!=="C"
        );
    }

    /**
     * @param string $userId
     * @param string|null $ds
     *      dtsamp,dtstart,dtend [,dtstart,dtend,...] -
     *      dtsamp: 20200414T073008Z must be UTC (ends with Z),
     *      dtstart/dtend: 20200414T073008
     * @param boolean $is_floating
     * @return string
     */
    private function addAppointments($userId,$ds,$is_floating){

        if(empty($ds)) return '1:No Data';
        $data=explode(',',$ds);
        $c=count($data);
        if($c<3) return '1:Bad Data Length '.$c;

        $cal_id=$this->c->getUserValue(
            $userId,
            $this->appName,
            'cal_id');
        if(empty($cal_id)) return '1:No calendar URI';

        $cal=$this->bc->getCalendarById($cal_id,$userId);
        if($cal===null) return '1:Can not find calendar';

        $tz_id="";
        $tz_data="";
        if(!$is_floating){ // We want calendar's timezone
            if(!empty($cal['timezone'])){
                $tzo=Reader::read($cal['timezone']);
                // TODO: Error check in-case the timezone data is bad...
                $tz_id=';TZID='.$tzo->VTIMEZONE->TZID->getValue();
                $tz_data=$tzo->VTIMEZONE->serialize();
            }else{
                return '1:Can not get calendar timezone';
            }
        }

        $rn="\r\n";
        $u=$this->um->get($userId);

        $u_email=$this->c->getUserValue(
            $userId,
            $this->appName,
            BackendUtils::KEY_O_EMAIL);
        if(empty($u_email)) $u_email=$u->getEMailAddress();
        if(empty($u_email)) return '1:Cant find your email';

//        ESCAPED-CHAR = ("\\" / "\;" / "\," / "\N" / "\n")
//        \\ encodes \ \N or \n encodes newline \; encodes ; \, encodes ,
        $u_addr=$this->c->getUserValue(
            $userId,
            $this->appName,
            BackendUtils::KEY_O_ADDR);
        if(empty($u_addr)) return '1:Cant find your address';
        $u_addr=str_replace(array("\\",";",",","\r\n","\r","\n"),array('\\\\','\;','\,',' \n',' \n',' \n'),$u_addr);

//        $u_name=trim($this->c->getUserValue(
//            $this->userId,
//            $this->appName,
//            self::KEY_O_NAME));
//        if(empty($u_name)) $u_name=trim($u->getDisplayName());

        $u_name=trim($u->getDisplayName());
        if(empty($u_name)) return '1:Cant find your name';

        $cr_date_rn=$data[0].$rn;
        $pieces = [];
        $ts=time();

        $max = strlen(self::RND_SPS) - 1;

        $rtn='0';

        $max_u=strlen(self::RND_SPU)-1;
        $br_u=[9,5,5,5,12];
        $br_c=count($br_u);
        $e_url=[];

        $organizer_location=$this->chunk_split_unicode("ORGANIZER;SCHEDULE-AGENT=CLIENT;CN=".$u_name.":mailto:".$u_email,75,"\r\n ").$rn
            .$this->chunk_split_unicode("LOCATION:".$u_addr,75,"\r\n ").$rn;
        // TRANSLATORS Appointment time is "Available"
        $available=$this->l->t("Available").$rn;

        for($i=1;$i<$c;$i+=2) {
            $cc = 0;
            $p = 0;
            for ($j = 0; $j < 27; ++$j) {
                $pieces[$p] = self::RND_SPS[rand(0, $max)];
                $p++;
                if ($cc === 6) {
                    $pieces[$p] = "-";
                    $p++;
                    $cc = 0;
                }
                ++$cc;
            }

            $eo = "BEGIN:VCALENDAR\r\n" .
                "PRODID:-//IDN nextcloud.com//Appointment App | srgdev.com//EN\r\n" .
                "CALSCALE:GREGORIAN\r\n" .
                "VERSION:2.0\r\n" .
                "BEGIN:VEVENT\r\n" .
                "SUMMARY:".$available .
                "STATUS:TENTATIVE\r\n" .
                "LAST-MODIFIED:" . $cr_date_rn .
                "DTSTAMP:" . $cr_date_rn .
                "SEQUENCE:1\r\n" .
                "CATEGORIES:" . BackendUtils::APPT_CAT . $rn .
                "CREATED:" . $cr_date_rn .
                "UID:" . implode('', $pieces)                .floor($ts / (ord($pieces[1]) + ord($pieces[2]) + ord($pieces[3]))) . $rn .
                "DTSTART".$tz_id.":" . $data[$i] . $rn .
                "DTEND".$tz_id.":" . $data[$i+1] . $rn .
                $organizer_location .
                "END:VEVENT\r\n".$tz_data."END:VCALENDAR\r\n";

            // make calendar object uri
            $p=0;
            $cc=$br_u[0];
            for($j=0;$j<$br_c;){
                $e_url[$p]=self::RND_SPU[rand(0, $max_u)];
                $p++;
                if($cc===$p){
                    $j++;
                    if($j<$br_c){
                        $cc+=$br_u[$j]+1;
                        $e_url[$p]='-';
                        $p++;
                    }
                    if($j===3) {
                        $e_url[$p]='S';
                        $p++;
                    }
                }
            }

            if(!$this->bc->createObject($cal_id,
                implode('',$e_url).".ics", $eo)){
                $rtn='1:bad request';
                break;
            }
        }
        return $rtn.'|'.$i.'|'.$c;
    }

    /**
     * @param string $templateName
     * @param string $userId
     * @return PublicTemplateResponse
     */
    private function getPublicTemplate($templateName,$userId){
        $pps=$this->utils->getUserSettings(
            self::KEY_PSN,self::PSN_DEF,
            $userId,$this->appName);
        $tr = new PublicTemplateResponse($this->appName,$templateName, []);
        if(!empty($pps[self::PSN_PAGE_TITLE])) {
            $tr->setHeaderTitle($pps[self::PSN_PAGE_TITLE]);
        }else{
            $tr->setHeaderTitle("Nextcloud | Appointments");
        }
        if(!empty($pps[self::PSN_PAGE_SUB_TITLE])) {
            $tr->setHeaderDetails($pps[self::PSN_PAGE_SUB_TITLE]);
        }
        $tr->setFooterVisible(false);

//        $tr->setHeaderActions([new SimpleMenuAction('download', 'Label', '', 'link-url', 0)]);
        return $tr;
    }


    private function getPublicWebBase(){
        return \OC::$server->getURLGenerator()->getBaseUrl().'/index.php/apps/appointments';
    }

    private function chunk_split_unicode($str, $l = 76, $e = "\r\n") {
        $tmp = array_chunk(
            preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY), $l);
        $str = "";
        foreach ($tmp as $t) {
            $str .= join("", $t) . $e;
        }
        return trim($str);
    }
}
