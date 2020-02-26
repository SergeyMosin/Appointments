<?php
namespace OCA\Appointments\Controller;

use OCA\Appointments\BackEndExt;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use Sabre\DAV\Exception\BadRequest;
use OCP\Mail\IMailer;

class PageController extends Controller {
    const APP_CAT="Appointment";
    const TIME_FORMAT="Ymd\THis";
    const RND_SPS = 'abcdefghijklmnopqrstuvwxyz';
    const RND_SPU = '1234567890ABCDEF';
    const CIPHER="AES-128-CFB";
    const KEY_O_NAME='organization';
    const KEY_O_ADDR='address';
    const KEY_O_EMAIL='email';
    const KEY_O_PHONE='phone';

    private $userId;
	private $calBackend;
    private $c;
    private $um;
    private $mailer;
    private $l;

	public function __construct($AppName,
                                IRequest $request,
                                $UserId,
                                BackEndExt $calDavBackend,
                                IConfig $c,
                                IUserManager $a,
                                IMailer $mailer,
                                IL10N $l){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->calBackend = $calDavBackend;
        $this->c=$c;
        $this->um=$a;
        $this->mailer=$mailer;
        $this->l=$l;
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
        $t->getContentSecurityPolicy()->addAllowedFrameDomain('\'self\'');
        return  $t;// templates/index.php
    }



    /**
     * @NoAdminRequired
     */
    public function callist(){
//      '{DAV:}displayname'                                           => 'displayname',
//		'{http://apple.com/ns/ical/}refreshrate'                      => 'refreshrate',
//		'{http://apple.com/ns/ical/}calendar-order'                   => 'calendarorder',
//		'{http://apple.com/ns/ical/}calendar-color'                   => 'calendarcolor',
//		'{http://calendarserver.org/ns/}subscribed-strip-todos'       => 'striptodos',
//		'{http://calendarserver.org/ns/}subscribed-strip-alarms'      => 'stripalarms',
//		'{http://calendarserver.org/ns/}subscribed-strip-attachments' => 'stripattachments',
//        id => a unique id that will be used by other functions to modify the calendar. This can be the same as the uri or a database key.
//        uri => which the basename of the uri with which the calendar is accessed.
//        principaluri => The owner of the calendar. Almost always the same as principalUri passed to this method.
//        $fields[] = 'synctoken'; ??
//        $fields[] = 'components'; ??
//        $fields[] = 'transparent'; ??
        $cals=$this->calBackend->getCalendarsForUser('principals/users/'.$this->userId);
        $out='';
        $l=count($cals);
        $c30=chr(30);
        $c31=chr(31);
        for($i=0;$i<$l;$i++){
            $cal=$cals[$i];
            $out.=
                $cal['{DAV:}displayname'].$c30.
                $cal['{http://apple.com/ns/ical/}calendar-color'].$c30.
                $cal['uri'].$c31;
        }
        return substr($out,0,-1);
    }

    /**
     * @NoAdminRequired
     */
    public function state(){
        $action = $this->request->getParam("a");
        $r=new SendDataResponse();
        $r->setStatus(400);

        if($action==="get"){
            $cal_url=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                'cal_url',
                ''
            );
            $enabled='';
            if(!empty($cal_url)){
                $cal=$this->calBackend->getCalendarByUri('principals/users/'.$this->userId,$cal_url);
                if($cal!==null){

                    $c30=chr(30);
                    $c31=chr(31);
                    $rd = $cal['{DAV:}displayname'].$c30.
                        $cal['{http://apple.com/ns/ical/}calendar-color'].$c30.
                        $cal['uri'].$c31;

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
        }elseif($action==="get_settings") {
            // Settings
            $c30=chr(30);
            $rd="";
            $rd.=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                self::KEY_O_NAME).$c30;
            $rd.=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                self::KEY_O_ADDR).$c30;
            $rd.=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                self::KEY_O_EMAIL).$c30;
            $rd.=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                self::KEY_O_PHONE);

            $r->setData($rd);
            $r->setStatus(200);
        }elseif($action==="set"){
            $v=$this->request->getParam("url");
            if($v!==null) {
                $cal=$this->calBackend->getCalendarByUri('principals/users/'.$this->userId,$v);
                if($cal!==null) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $this->c->setUserValue(
                        $this->userId,
                        $this->appName,
                        'cal_url',
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
                if(empty($c->getUserValue($u,$a,self::KEY_O_NAME))
                    || empty($c->getUserValue($u,$a,self::KEY_O_ADDR))
                    || empty($c->getUserValue($u,$a,self::KEY_O_EMAIL))){
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
        }elseif ($action==='set_settings'){
            $sa=array("org"=>self::KEY_O_NAME,
                "addr"=>self::KEY_O_ADDR,
                "eml"=>self::KEY_O_EMAIL,
                "phn"=>self::KEY_O_PHONE);
            $n=$this->request->getParam("n");
            $v=$this->request->getParam("v");
            if($v!==null && $n!==null && array_key_exists($n,$sa)){
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->c->setUserValue(
                    $this->userId,
                    $this->appName,
                    $sa[$n],
                    $v);
                $r->setStatus(200);
            }
        }elseif ($action==='get_puburi'){
            $s=\OC::$server;
            $u=$s->getURLGenerator()->getBaseUrl().'/index.php'
            .$s->getAppManager()->getAppWebPath($this->appName).'/'
            .urldecode($this->pubPrx($this->getToken($this->userId))).'form';

            $r->setData($u);
            $r->setStatus(200);
        }

        return $r;
    }


    /**
     * @param $token
     * @return string|false user name on success, false=not verified
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function verifyToken($token){
        if(empty($token) || strlen($token)>256) return false;
        $token=str_replace("_","/",$token);
        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($this->c->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }
        $td=$this->decrypt($token,$key,$iv);
        if(strlen($td)>4 && substr($td,0,4)===hash( 'adler32', substr($td,4),true)){
            return substr($td,4);
        }else{
            return false;
        }
    }



    private function getToken($uid){
        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($this->c->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }
//        return urlencode($this->encrypt(hash ( 'adler32' , $uid,true).$uid,$key,$iv));
        $tkn=$this->encrypt(hash ( 'adler32' , $uid,true).$uid,$key,$iv);
        return urlencode(str_replace("/","_",$tkn));
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
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
     */
    public function formPost(){
        $uid=$this->verifyToken($this->request->getParam("token"));
        if($uid===false){
            return new NotFoundResponse();
        }
        return $this->showFormPost($uid);
    }

    private function pubPrx($token){
        return 'pub/'.urlencode($token).'/';
    }



    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     */
    public function cncf(){
        $uid=$this->verifyToken($this->request->getParam("token"));
        $pd=$this->request->getParam("d");
        if($uid===false || $pd===null || strlen($pd)>512
            || (($a=substr($pd,0,1))!=='0') && $a!=='1'){
            return new NotFoundResponse();
        }

        $key=hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $da=explode(chr(31),$this->decrypt(substr($pd,1),$key));

        if(count($da)!==2 || !$this->mailer->validateMailAddress($da[0])){
            return $this->pubErrResponse();
        }

        $cal_url=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_url',
            ''
        );
        if(empty($cal_url)) {
            return $this->pubErrResponse();
        }

        $ra=$this->calBackend->updateApptStatus($uid,$cal_url,$da[1],$da[0],$a);


        $org_email=$this->c->getUserValue(
            $uid, $this->appName,
            self::KEY_O_EMAIL);

        if($ra[0]===null){

            $tr=new TemplateResponse($this->appName,
                "public/formerr",
                [],"public");

            $param=[];
            if($ra[1]===2){
                $param['appt_e_ne']=$org_email;
            }

            $tr->setParams($param);
            $tr->setStatus(500);

        }else{
            if($a==='1'){
                $atx='confirmed';
            }else{
                $atx='canceled';
            }

            if($ra[1]===0) {

                $org_name = $this->c->getUserValue(
                    $uid, $this->appName,
                    self::KEY_O_NAME);

                // Send email
                $tml = $this->mailer->createEMailTemplate('ID' . time());
                $tml->setSubject($org_name . " Appointment is " . ucfirst($atx));
                $tml->addBodyText("Your " . $org_name . " appointment scheduled for " . str_replace(
                        ':00 ', ' ',
                        $this->l->l('datetime', $ra[0], ['width' => 'medium'])) . " is now " . $atx . ".");
                $tml->addBodyText("Thank You");

                $tml->addFooter("Booked via Nextcloud Appointments App");

                $msg = $this->mailer->createMessage();
                $msg->setFrom(array($org_email));
                $msg->setTo(array($da[0]));
                $msg->useTemplate($tml);

                try {
                    $this->mailer->send($msg);
                } catch (\Exception $e) {
                    \OC::$server->getLogger()->error("Can not send email to " . $da[0]);
                }
            }


            $tr=new TemplateResponse($this->appName,
                "public/thanks",
                [
                    'appt_c_head'=>"All done!",
                    'appt_c_msg'=>"Your ".str_replace(
                        ':00 ',' ',
                        $this->l->l('datetime',$ra[0],['width'=>'medium']))." appointment is ".$atx."."
                ],"public");
            $tr->setStatus(200);
        }

        return $tr;
    }



    private function pubErrResponse(){
        $tr=new TemplateResponse($this->appName,'public/formerr',[],'public');
        $tr->setStatus(500);
        return $tr;
    }




    /**
     * @NoAdminRequired
     * @NoCSRFRequired
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
     */
    public function formBasePost(){
        return $this->showFormPost($this->userId);
    }

    /**
     * @param $uid
     * @return RedirectResponse
     * @noinspection PhpDocMissingThrowsInspection
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

        if(strlen($post['adatetime'])>128
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

        $dc=$this->decrypt($post['adatetime'],$key);
        if(empty($dc) || strpos($dc,'|')===false){
            $rr=new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $da=explode('|',$dc);
        $ti=intval($da[0]);// session start, 15 minute max
        $ts=time();

        if($ts<$ti || $ts>$ti+900
            || strlen($da[1])>64
            || preg_match('/[^0-9]/u',$da[1])
            || preg_match('/[^\PC ]/u',$post['name'])
            || $this->mailer->validateMailAddress($post['email'])===false
            || preg_match('/[^0-9 .()\-+,\/]/',$post['phone'])){
            $rr=new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $post['name']=htmlspecialchars($post['name'], ENT_QUOTES, 'UTF-8');

        // Input seems OK...

        $cal_url=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_url',
            ''
        );

        if(empty($cal_url)) {
            $rr=new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }

        $s=\OC::$server;


        try {
            $r = $this->calBackend->updateApptEntry(
                $uid, $cal_url, $da[1],
                $post['name'], $post['email'], $post['phone']);
        } catch (\Exception $e) {
            $r=2;
            $s->getLogger()->error($e);
        }

        if(gettype($r)==="integer"){
            // &r=1 means there was a race and some else has booked that slot
            $rr=new RedirectResponse($server_err_url.($r===1?"&r=1":""));
            $rr->setStatus(303);
            return $rr;
        }

        // Send email to the attendee, requesting confirmation...
        $org_name=$this->c->getUserValue(
                $uid, $this->appName,
                self::KEY_O_NAME);
        $org_email=$this->c->getUserValue(
                $uid, $this->appName,
                self::KEY_O_EMAIL);

        $btn_url= $s->getURLGenerator()->getBaseUrl().'/index.php'
            .$s->getAppManager()->getAppWebPath($this->appName).'/'
            .urldecode($this->pubPrx($this->getToken($uid))).'cncf?d=';
        $btn_tkn=urlencode($this->encrypt($post['email'].chr(31).$da[1],$key));

        $tml=$this->mailer->createEMailTemplate('ID'.$ts);
        $tml->setSubject($org_name." Appointment (action needed)");
        $tml->addBodyText("Dear ".$post['name'].",",
            "Dear ".$post['name'].",");
        $tml->addBodyText("The ".$org_name." appointment scheduled for ".str_replace(
                ':00 ','',
                $this->l->l('datetime',$r,['width'=>'medium']))." is awaiting your confirmation.");
        $tml->addBodyButtonGroup(
            'Confirm',
            $btn_url.'1'.$btn_tkn,
            'Cancel',
            $btn_url.'0'.$btn_tkn
        );
        $tml->addBodyText("Thank You");

        $tml->addFooter("Booked via Nextcloud Appointments App");

        $msg=$this->mailer->createMessage();
        $msg->setFrom(array($org_email));
        $msg->setTo(array($post['email']));
        $msg->useTemplate($tml);


        try {
            $this->mailer->send($msg);
            $uri=$ok_uri."&d=".urlencode($this->encrypt(pack('I',time()).$post['email'],$key));
        } catch (\Exception $e) {
            $s->getLogger()->error("Can not send email to ".$post['email']);
            $uri=$server_err_url."&eml=".urlencode($org_email);
        }

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
        // sts=2&eml=1: could not send email
        // d=email

        $tmpl='public/formerr';
        $rs=500;
        $param=[
            'appt_c_head'=>"Almost done..."
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
            $dd=$this->decrypt($this->request->getParam('d',''),$key);
            if(strlen($dd)>7){
                $ts=unpack('Iint',substr($dd,0,4))['int'];
                $em=substr($dd,4);
                if($ts+8>=time()){
                    if($this->mailer->validateMailAddress($em)) {
                        $tmpl = 'public/thanks';
                        $param['appt_c_msg'] = "We have sent an email to " . $em . ", please open it and click on the confirmation link to finalize your appointment request.";
                        $rs = 200;
                    }
                }else{
                    $tmpl = 'public/thanks';
                    $param['appt_c_head']="Warning";
                    $param['appt_c_msg'] = "Link Expired...";
                    $rs = 409;
                }
            }
        }

        $tr=new TemplateResponse($this->appName,$tmpl,$param,$render);
        $tr->setStatus($rs);
        return $tr;
    }

    /**
     * @NoAdminRequired
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
     * @param $render
     * @param $uid
     * @return TemplateResponse
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function showForm($render,$uid){

        $tr=new TemplateResponse($this->appName,
            'public/form',
            [],
            $render);

        $params=[
            'appt_sel_opts'=>'',
            'appt_state'=>'0',
            'appt_org_name'=>$this->c->getUserValue(
                $uid,$this->appName, self::KEY_O_NAME,
                'Organization Name'),
            'appt_org_addr'=>$this->c->getUserValue(
                $uid, $this->appName, self::KEY_O_ADDR,
                '123 Main Street<br>New York, NY 45678')
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


        $cid=null;
        $cal_url=$this->c->getUserValue(
            $uid,
            $this->appName,
            'cal_url',
            ''
        );
        if(!empty($cal_url)){
            $cid=$this->calBackend->getCalendarIDByUri($uid,$cal_url);
        }

        if($cid===null){
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

        $now=new \DateTime('now',$this->getUserTimeZone($uid));

        // this is needed to get the range for floating appointments right
        $t_offset=$now->getOffset();

        $t_start=$now->getTimestamp()+$t_offset;

        $t_end=$now->setTime(0,0)->getTimestamp()+604800+$t_offset;

        $stmt=$this->calBackend->queryWeek($cid,$t_start,$t_end);
        if($stmt->rowCount()===0){
            $params['appt_state']='3';
            $tr->setParams($params);
            return $tr;
        }

        $out='';

        $last_valid_end=0;
        $ses_start=time().'|';

        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        $key=hex2bin($hkey);
        $c=0;
        $be=$this->calBackend;
        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cd=$be->readBlob($row['calendardata']);
            $first=(int)$row['firstoccurence'];
            $last=(int)$row['lastoccurence'];
            $diff=$last-$first;
            $cp=stripos($cd,"\r\ncategories:appointment\r\n",14);
            if($cp!==false
                && $cp===strpos($cd,"\r\nCATEGORIES:",14)
                && strpos($cd,"\r\nSTATUS:TENTATIVE\r\n",14)!==false
                && $first>$last_valid_end // no overlap
                && $diff<=7200 // two hours max
                && $diff>=900 // 15 minutes minimum
            ){
                //Encrypt $ses_end|uri
                $iv = openssl_random_pseudo_bytes($ivlen);
                $ciphertext_raw = openssl_encrypt(
                    $ses_start.$row['id'].'|'.substr($row['uri'],0,5),
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv);

                $out.='<option value="'.base64_encode($iv.$ciphertext_raw).'" data-ts="'.$row['firstoccurence'].'">'.$c.'</option>';
                $c++;
            }
        }

        if($out==='') {
            $params['appt_state']='5';
        }

        $params['appt_sel_opts'] = $out;
        $tr->setParams($params);

        //        $tr->getContentSecurityPolicy()->addAllowedFrameAncestorDomain('\'self\'');

        return $tr;
    }

    /**
     * @NoAdminRequired
     */
    public function caladd(){
        $ds=$this->request->getParam("d");
        if($ds===null) return '1:No Key';
        $data=explode(',',$ds);
        $c=count($data);
        if($c<3) return '1:Bad Lenght '.$c;

        $cal_url=$this->c->getUserValue(
            $this->userId,
            $this->appName,
            'cal_url',
            ''
        );
        if(empty($cal_url)) return '1:No calendar URI ';

        $be=$this->calBackend;

        $cal=$be->getCalendarByUri('principals/users/'.$this->userId,$cal_url);
        if($cal===null) return '1:Cant find calendar';

        $cid=$cal['id'];

        $rn="\r\n";
        $u=$this->um->get($this->userId);

        $u_email=$this->c->getUserValue(
                $this->userId,
                $this->appName,
                self::KEY_O_EMAIL);

        if(empty($u_email)) $u_email=$u->getEMailAddress();

        if(empty($u_email)){
            if($cal===null) return '1:Cant find your email';
        }

        $u_name=trim($u->getDisplayName());

        $cr_date_rn=$data[0].$rn;
        $pieces = [];
        $ts=time();

        $max = strlen(self::RND_SPS) - 1;

        $rtn='0';

        $max_u=strlen(self::RND_SPU)-1;
        $br_u=[9,5,5,5,12];
        $br_c=count($br_u);
        $e_url=[];

        for($i=1;$i<$c;$i+=2) {
            $cc = 0;
            $p = 0;
            for ($j = 0; $j < 18; ++$j) {
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
                "SUMMARY:Available\r\n" .
                "STATUS:TENTATIVE\r\n" .
                "LAST-MODIFIED:" . $cr_date_rn .
                "DTSTAMP:" . $cr_date_rn .
                "SEQUENCE:1\r\n" .
                "CATEGORIES:" . self::APP_CAT . $rn .
                "CREATED:" . $cr_date_rn .
                "UID:" . implode('', $pieces)."-"
                .floor($ts / (ord($pieces[1]) + ord($pieces[2]) + ord($pieces[$p - 2]))) .
                "-ncapp@srgdev.com" . $rn .
                "DTSTART:" . $data[$i] . $rn .
                "DTEND:" . $data[$i+1] . $rn .
                "ORGANIZER;CN=" . $u_name . ":acct:" . $u_email . $rn .
                "END:VEVENT\r\nEND:VCALENDAR\r\n";


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

            try {
                $be->createCalendarObject(
                    $cid,
                    implode('',$e_url).".ics",
                    $eo);
            } catch (BadRequest $e) {
                $rtn='1:bad request';
                break;
            }
        }
        return $rtn.'|'.$i.'|'.$c;
    }

    /**
     * @param string $data
     * @param string $key
     * @param string $iv special case
     * @return string
     */
    private function encrypt(string $data,string $key,$iv=''):string {
        if($iv==='') {
            $iv=$_iv = openssl_random_pseudo_bytes(
                openssl_cipher_iv_length(self::CIPHER));
        }else{
            $_iv='';
        }
        $ciphertext_raw = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv);

        return base64_encode($_iv.$ciphertext_raw);
    }


    /**
     * @param string $data
     * @param string $key
     * @param string $iv
     * @return string
     */
    private function decrypt(string $data,string $key,$iv=''):string {
        $s1=base64_decode($data);
        if($s1===false || empty($key)) return '';

        $s1=$iv.$s1;

        $ivlen = openssl_cipher_iv_length(self::CIPHER);
        $t=openssl_decrypt(
            substr($s1,$ivlen),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            substr($s1,0,$ivlen));
        return $t===false?'':$t;
    }


    private function getUserTimeZone($uid) {
        $timeZone = $this->c->getUserValue($uid, 'core', 'timezone', 'utc');
        return new \DateTimeZone($timeZone);
    }



}
