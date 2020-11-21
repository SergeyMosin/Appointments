<?php
/** @noinspection PhpPossiblePolymorphicInvocationInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */


namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use Sabre\VObject\Reader;

class BackendUtils{

    const APPT_CAT="Appointment";
    const TZI_PROP="X-TZI";
    const XAD_PROP="X-APPT-DATA";
    // original description
    const X_DSR="X-APPT-DSR";

    const CIPHER="AES-128-CFB";
    const HASH_TABLE_NAME="appointments_hash";
    const FLOAT_TIME_FORMAT="Ymd.His";

    public const APPT_SES_KEY_HINT = "appointment_hint";

    public const APPT_SES_BOOK = "0";
    public const APPT_SES_CONFIRM = "1";
    public const APPT_SES_CANCEL = "2";
    public const APPT_SES_SKIP = "3";
    public const APPT_SES_TYPE_CHANGE = "4";

    public const KEY_ORG = 'org_info';
    public const ORG_NAME = 'organization';
    public const ORG_EMAIL = 'email';
    public const ORG_ADDR = 'address';
    public const ORG_PHONE = 'phone';

    const ORG_DEF=array(
        self::ORG_NAME=>"",
        self::ORG_EMAIL=>"",
        self::ORG_ADDR=>"",
        self::ORG_PHONE=>"");

    public const KEY_USE_DEF_EMAIL = 'useDefaultEmail';
    public const KEY_EMAIL_FIX = 'emailFixOpt';

    // Email Settings
    public const KEY_EML = 'email_options';
    public const EML_ICS= 'icsFile';
    public const EML_SKIP_EVS = 'skipEVS';
    public const EML_AMOD = 'attMod';
    public const EML_ADEL = 'attDel';
    public const EML_MREQ = 'meReq';
    public const EML_MCONF = 'meConfirm';
    public const EML_MCNCL = 'meCancel';
    public const EML_VLD_TXT = 'vldNote';
    public const EML_CNF_TXT = 'cnfNote';

    const EML_DEF=array(
        self::EML_ICS=>false,
        self::EML_SKIP_EVS=>false,
        self::EML_AMOD=>false,
        self::EML_ADEL=>false,
        self::EML_MREQ=>false,
        self::EML_MCONF=>false,
        self::EML_MCNCL=>false,
        self::EML_VLD_TXT=>"",
        self::EML_CNF_TXT=>"");

    // Calendar Settings
    public const KEY_CLS = 'calendar_settings';
    // simple mode
    public const CLS_MAIN_ID= 'mainCalId'; // this cal_id now
    public const CLS_DEST_ID= 'destCalId';
    // external mode
    public const CLS_XTM_SRC_ID= 'nrSrcCalId';
    public const CLS_XTM_DST_ID= 'nrDstCalId';
    public const CLS_XTM_PUSH_REC= 'nrPushRec';
    public const CLS_XTM_REQ_CAT= 'nrRequireCat';
    public const CLS_XTM_AUTO_FIX= 'nrAutoFix';
    public const CLS_PREP_TIME = 'prepTime';
    public const CLS_ON_CANCEL = 'whenCanceled';
    public const CLS_TS_MODE = 'tsMode';
    const CLS_DEF=array(
        self::CLS_MAIN_ID=>'-1',
        self::CLS_DEST_ID=>'-1',
        self::CLS_XTM_SRC_ID=>'-1',
        self::CLS_XTM_DST_ID=>'-1',
        self::CLS_XTM_PUSH_REC=>true,
        self::CLS_XTM_REQ_CAT=>false,
        self::CLS_XTM_AUTO_FIX=>false,
        self::CLS_PREP_TIME=>"0",
        self::CLS_ON_CANCEL=>'mark',
        self::CLS_TS_MODE=>'0' // 0=simple/manual, 1=external/XTM, (2=template)
    );

    public const KEY_PSN = "page_options";
    public const PSN_PAGE_TITLE = "pageTitle";
    public const PSN_FNED = "startFNED";
    public const PSN_PAGE_STYLE = "pageStyle";
    public const PSN_GDPR = "gdpr";
    public const PSN_FORM_TITLE = "formTitle";
    public const PSN_META_NO_INDEX = "metaNoIndex";
    public const PSN_EMPTY = "showEmpty";
    public const PSN_WEEKEND = "showWeekends";
    public const PSN_PAGE_SUB_TITLE = "pageSubTitle";
    public const PSN_NWEEKS = "nbrWeeks";
    public const PSN_TIME2 = "time2Cols";
    public const PSN_HIDE_TEL = "hidePhone";
    public const PSN_END_TIME = "endTime";
    public const PSN_SHOW_TZ = "showTZ";

    public const PSN_DEF = array(
        self::PSN_FORM_TITLE => "",
        self::PSN_NWEEKS => "1",
        self::PSN_EMPTY => true,
        self::PSN_FNED => false, // start at first not empty day
        self::PSN_WEEKEND => false,
        self::PSN_TIME2 => false,
        self::PSN_END_TIME => false,
        self::PSN_HIDE_TEL => false,
        self::PSN_SHOW_TZ => false,
        self::PSN_GDPR => "",
        self::PSN_PAGE_TITLE => "",
        self::PSN_PAGE_SUB_TITLE => "",
        self::PSN_META_NO_INDEX => false,
        self::PSN_PAGE_STYLE => ""
    );

    public const KEY_MPS = "more_pages_";
    public const MPS_DEF=array(
        self::CLS_MAIN_ID=>'-1',
        self::CLS_DEST_ID=>'-1',
        self::CLS_XTM_SRC_ID=>'-1',
        self::CLS_XTM_DST_ID=>'-1',
        self::CLS_TS_MODE=>'0',

        self::ORG_NAME=>"",
        self::ORG_EMAIL=>"",
        self::ORG_ADDR=>"",
        self::ORG_PHONE=>"",

        self::PSN_FORM_TITLE=>"",
    );

    public const KEY_PAGES="pages";
    public const PAGES_ENABLED="enabled";
    public const PAGES_LABEL="label";

    public const PAGES_VAL_DEF=array(
        self::PAGES_ENABLED=>0,
        self::PAGES_LABEL=>""
    );

    public const PAGES_DEF=array(
        'p0'=>self::PAGES_VAL_DEF
    );

    public const KEY_DIR="dir_info";
    public const DIR_DEF=array();

    public const KEY_TALK = "appt_talk";
    public const TALK_ENABLED = "enabled";
    public const TALK_DEL_ROOM = "delete";
    public const TALK_EMAIL_TXT = "emailText";
    public const TALK_LOBBY = "lobby";
    public const TALK_PASSWORD = "password";
    public const TALK_NAME_FORMAT = "nameFormat";
    public const TALK_FORM_ENABLED = "formFieldEnable";
    public const TALK_FORM_LABEL = "formLabel";
    public const TALK_FORM_PLACEHOLDER = "formPlaceholder";
    public const TALK_FORM_REAL_TXT = "formTxtReal";
    public const TALK_FORM_VIRTUAL_TXT = "formTxtVirtual";

    public const TALK_FORM_DEF_LABEL = "formDefLabel";
    public const TALK_FORM_DEF_PLACEHOLDER = "formDefPlaceholder";
    public const TALK_FORM_DEF_REAL = "formDefReal";
    public const TALK_FORM_DEF_VIRTUAL = "formDefVirtual";

    public const TALK_FORM_TYPE_CHANGE_TXT = "formTxtTypeChange";

    public const TALK_DEF = array(
        self::TALK_ENABLED => false,
        self::TALK_DEL_ROOM => false,
        self::TALK_EMAIL_TXT => "",
        self::TALK_LOBBY => false,
        self::TALK_PASSWORD => false,
        self::TALK_NAME_FORMAT => 0, // 0=Name+DT, 1=DT+Name, 2=Name Only
        self::TALK_FORM_ENABLED => false,
        self::TALK_FORM_LABEL => "",
        self::TALK_FORM_PLACEHOLDER => "",
        self::TALK_FORM_REAL_TXT => "",
        self::TALK_FORM_VIRTUAL_TXT => "",
        self::TALK_FORM_TYPE_CHANGE_TXT => "",
    );

    public const KEY_FORM_INPUTS_JSON='fi_json';
    public const KEY_FORM_INPUTS_HTML='fi_html';

    private $appName=Application::APP_ID;

    /**
     * @param \DateTimeImmutable $new_start
     * @param \DateTimeImmutable $new_end
     * @param int $skipped number of skipped recurrences (to adjust the 'COUNT')
     * @param \Sabre\VObject\Component\VCalendar $vo
     */
    function optimizeRecurrence($new_start,$new_end,$skipped,$vo){

        /**  @var \Sabre\VObject\Component\VEvent $evt */
        $evt=$vo->VEVENT;

        $is_floating=$evt->DTSTART->isFloating();

        $evt->DTSTART->setDateTime($new_start,$is_floating);
        // there can be "DURATION" instead of "DTSTART"
        if(isset($evt->DTEND)){
            // adjust end time
            $evt->DTEND->setDateTime($new_end,$is_floating);
        }

        $this->setSEQ($evt);

        //adjust count if present
        $rra=$evt->RRULE->getParts();
        if(isset($rra['COUNT'])){
            $rra['COUNT']-=$skipped;
            $evt->RRULE->setParts($rra);
        }
    }

    /**
     * @param $data
     * @param $info
     * @param $userId
     * @return string   Event Data |
     *                  "1"=Bad Status (Most likely booked while waiting),
     *                  "2"=Other Error
     */
    function dataSetAttendee($data, $info, $userId){

        $vo = Reader::read($data);

        if ($vo === null || !isset($vo->VEVENT)) {
            \OC::$server->getLogger()->error("Bad Data: not an event");
            return "2";
        }

        /** @var \Sabre\VObject\Component\VEvent $evt */
        $evt = $vo->VEVENT;

        if (!isset($evt->STATUS) || $evt->STATUS->getValue() !== 'TENTATIVE') {
            \OC::$server->getLogger()->error("Bad Status: must be TENTATIVE");
            return "1";
        }

        if (!isset($evt->CATEGORIES) || $evt->CATEGORIES->getValue() !== BackendUtils::APPT_CAT) {
            \OC::$server->getLogger()->error("Bad Category: not an " . BackendUtils::APPT_CAT);
            return "2";
        }

        $config = \OC::$server->getConfig();
        // @see issues #120 and #116
        // Should this be documented ???
        $e_fix = $config->getUserValue($userId, $this->appName, self::KEY_EMAIL_FIX);

        if ($e_fix === 'none') {
            $a = $evt->add('ATTENDEE', "mailto:" . $info['email']);
        }elseif ($e_fix === 'scheme'){
            $a = $evt->add('ATTENDEE', "acct:" . $info['email']);
        }else{
            $a = $evt->add('ATTENDEE', "mailto:" . $info['email']);
            $a['SCHEDULE-AGENT']="CLIENT";
        }

        $a['CN']=$info['name'];
        $a['PARTSTAT']="NEEDS-ACTION";

        $title="";
        if(!isset($evt->SUMMARY)){
            $evt->add('SUMMARY');
        }else{
            $t=$evt->SUMMARY->getValue();
            if($t[0]==="_") $title=$t;
        }
        $evt->SUMMARY->setValue("⌛ ".$info['name']);

        $dsr=$info['name']."\n".(empty($info['phone'])?"":($info['phone']."\n")).$info['email'].$info['_more_data'];
        if(!isset($evt->DESCRIPTION)) $evt->add('DESCRIPTION');
        $evt->DESCRIPTION->setValue($dsr);

        if(!isset($evt->{self::X_DSR})) $evt->add(self::X_DSR);
        $evt->{self::X_DSR}->setValue($dsr);

        if(!isset($evt->STATUS)) $evt->add('STATUS');
        $evt->STATUS->setValue("CONFIRMED");

        if(!isset($evt->TRANSP)) $evt->add('TRANSP');
        $evt->TRANSP->setValue("OPAQUE");

        // Attendee's timezone info at the time of booking
        if(!isset($evt->{self::TZI_PROP})) $evt->add(self::TZI_PROP);
        $evt->{self::TZI_PROP}->setValue($info['tzi']);

        // isset($info['talk_type_real']) === No need for Talk room

        // Additional appointment info (XAD_PROP):
        //  0: userId
        //  1: _title
        //  2: pageId
        //  3: embed (uri)
        //  4: reserved for Talk link @see $this->dataConfirmAttendee()
        //      'd' === add self::TALK_FORM_REAL_TXT to description - no need for Talk room
        //      '_' === check if Talk room is needed
        //      'f' === finished
        //  5: reserved for Talk pass @see $this->dataConfirmAttendee()
        if(!isset($evt->{self::XAD_PROP})) $evt->add(self::XAD_PROP);
        $evt->{self::XAD_PROP}->setValue($this->encrypt(
            $userId.chr(31)
            .$title.chr(31)
            .$info['_page_id'].chr(31)
            .$info['_embed'].chr(31)
            // talk link, if isset($info['talk_type_real']) means no need for talk room, @see PageController->showFormPost()
            .(isset($info['talk_type_real'])?'d':'_').chr(31)
            .'_', // talk pass
            $evt->UID));

        $this->setSEQ($evt);

        $this->setApptHash($evt);

        return $vo->serialize();
    }

    /**
     * @param $uri
     * @param $userId
     * @return string[] [new meeting type, '' === error, data]
     */
    function dataChangeApptType($data,$userId){
        $r=['',''];

        $vo=$this->getAppointment($data,'CONFIRMED');
        if($vo===null) return $r;

        /** @var \Sabre\VObject\Component\VEvent $evt*/
        $evt=$vo->VEVENT;

        if(isset($evt->{BackendUtils::XAD_PROP})) {
            // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
            $xad = explode(chr(31), $this->decrypt(
                $evt->{BackendUtils::XAD_PROP}->getValue(),
                $evt->UID->getValue()));

            if(count($xad)>4) {

                $a=$this->getAttendee($evt);
                if ($a===null) {
                    return $r;
                }

                if ($xad[4] === 'f') {
                    // the appointment was previously finalized as "in-person"
                    // ... so, set $xad[4]='_' @see BackendUtils->dataSetAttendee
                    // this will add a talk room and description when a addTalkInfo is called
                    $xad[4] = '_';

                }elseif(strlen($xad[4])>1){
                    // this was a virtual appointment...
                    // ... $xad[4] is the room token.
                    // delete the room first...

                    $tlk = $this->getUserSettings(self::KEY_TALK, $userId);
                    $ti = new TalkIntegration($tlk, $this);
                    $ti->deleteRoom($xad[4]);

                    // set $xad[4]='d' which will just and description @see BackendUtils->dataSetAttendee
                    $xad[4]='d';
                }

                $new_type=$this->addEvtTalkInfo($userId,$xad,$evt,$a);
                $r[0]=$new_type;
                $r[1]=$vo->serialize();
            }
        }

        return $r;
    }

    /**
     * @param $data
     * @param string $userId
     * @return array [string|null, string|null, string|null]
     *                  null=error|""=already confirmed,
     *                  Localized DateTime string
     *                  $pageId
     */
    function dataConfirmAttendee($data,$userId){

        $vo=$this->getAppointment($data,'CONFIRMED');
        if($vo===null) return [null,null,null];

        /** @var \Sabre\VObject\Component\VEvent $evt*/
        $evt=$vo->VEVENT;

        $a=$this->getAttendee($evt);
        if ($a===null) {
            return [null,null,null];
        }

        if(isset($evt->{BackendUtils::XAD_PROP})){
            // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
            $xad=explode(chr(31),$this->decrypt(
                $evt->{BackendUtils::XAD_PROP}->getValue(),
                $evt->UID->getValue()));
            if(count($xad)>2) {
                $pageId = $xad[2];
            }else{
                $pageId = 'p0';
            }
        }else {
            return [null,null,null];
        }

        $dts=$this->getDateTimeString(
            $evt->DTSTART->getDateTime(),
            $evt->{self::TZI_PROP}->getValue()
        );

        if($a->parameters['PARTSTAT']->getValue()==='ACCEPTED'){
            return ["",$dts,$pageId];
        }

        $a->parameters['PARTSTAT']->setValue('ACCEPTED');

        if(!isset($evt->SUMMARY)) $evt->add('SUMMARY'); // ???
        $evt->SUMMARY->setValue("✔️ ".$a->parameters['CN']->getValue());

        //Talk link
        $this->addEvtTalkInfo($userId,$xad,$evt,$a);

        $this->setSEQ($evt);

        $this->setApptHash($evt);

        return [$vo->serialize(),$dts, $pageId];
    }

    /**
     * @param $userId
     * @param $xad
     * @param $evt
     * @param $a - attendee
     * @return string new appointment type virtual/in-person (from talk settings)
     */
    private function addEvtTalkInfo($userId, $xad, $evt, $a){
        $r='';

        if(count($xad)>4){
            if($xad[4]==='_') {
                $tlk = $this->getUserSettings(self::KEY_TALK, $userId);
                // check if Talk link is needed
                if ($tlk[self::TALK_ENABLED] === true) {
                    $ti = new TalkIntegration($tlk, $this);
                    $token = $ti->createRoomForEvent(
                        $a->parameters['CN']->getValue(),
                        $evt->DTSTART,
                        $userId);
                    if (!empty($token)) {

                        $l10n = \OC::$server->getL10N($this->appName);
                        if ($token !== "-") {
                            $pi = '';
                            if (strpos($token, chr(31)) === false) {
                                // just token
                                $xad[4] = $token;
                            } else {
                                // taken + pass
                                list($xad[4], $xad[5]) = explode(chr(31), $token);
                                $pi = "\n" . $l10n->t("Guest password:") . " " . $xad[5];
                                $token = $xad[4];
                            }
                            $evt->{self::XAD_PROP}->setValue($this->encrypt(
                                implode(chr(31), $xad), $evt->UID));

                            $this->updateDescription($evt,"\n\n" .
                                $ti->getRoomURL($token) . $pi);

                            $r=(!empty($tlk[self::TALK_FORM_VIRTUAL_TXT])
                                ?$tlk[self::TALK_FORM_VIRTUAL_TXT]
                                :$tlk[self::TALK_FORM_DEF_VIRTUAL]);

                        } else {

                            $this->updateDescription($evt,"\n\n" .
                                $l10n->t("Talk integration error: check logs"));
                        }
                    }
                }
            }elseif ($xad[4]==='d'){
                // meeting type is overridden by client to real,
                // set xad to 'f' and add self::TALK_FORM_REAL_TXT to description
                $xad[4]='f';
                $evt->{self::XAD_PROP}->setValue($this->encrypt(
                    implode(chr(31), $xad), $evt->UID));

                $tlk = $this->getUserSettings(self::KEY_TALK, $userId);

                $r=(!empty($tlk[self::TALK_FORM_REAL_TXT])
                    ?$tlk[self::TALK_FORM_REAL_TXT]
                    :$tlk[self::TALK_FORM_DEF_REAL]);

                $this->updateDescription($evt,"\n\n".$r);
            }
        }

        return $r;
    }

    /**
     * @param \Sabre\VObject\Component\VEvent $evt
     * @param $addString string text to be added to original description
     */
    private function updateDescription($evt,$addString){
        // just in-case
        if (!isset($evt->DESCRIPTION)) $evt->add('DESCRIPTION');

        if(isset($evt->{self::X_DSR})){
            // we have original description
            $d=$evt->{self::X_DSR}->getValue();
        }else{
            $d=$evt->DESCRIPTION->getValue();
        }

        $evt->DESCRIPTION->setValue($d.$addString);
    }

    /**
     * @param $data
     * @return array [string|null, string|null, string|null]
     *                  null=error|""=already canceled
     *                  Localized DateTime string
     */
    function dataCancelAttendee($data){

        $vo=$this->getAppointment($data,'*');
        if($vo===null) return [null,null,null];

        /** @var \Sabre\VObject\Component\VEvent $evt*/
        $evt=$vo->VEVENT;

        if($evt->STATUS->getValue()==='TENTATIVE'){
            // Can not cancel tentative appointments
            return [null,null,null];
        }

        $a=$this->getAttendee($evt);
        if ($a===null) {
            return [null,null,null];
        }

        if(isset($evt->{BackendUtils::XAD_PROP})){
            // @see BackendUtils->dataSetAttendee for BackendUtils::XAD_PROP
            $xad=explode(chr(31),$this->decrypt(
                $evt->{BackendUtils::XAD_PROP}->getValue(),
                $evt->UID->getValue()));
            if(count($xad)>2) {
                $pageId = $xad[2];
            }else{
                $pageId = 'p0';
            }
        }else {
            return [null,null,null];
        }

        $dts=$this->getDateTimeString(
            $evt->DTSTART->getDateTime(),
            $evt->{self::TZI_PROP}->getValue()
        );

        if($a->parameters['PARTSTAT']->getValue()==='DECLINED'
            || $evt->STATUS->getValue()==='CANCELLED' ){
            // Already cancelled
            return ["",$dts,$pageId];
        }

        $this->evtCancelAttendee($evt);

        $this->setSEQ($evt);

        $this->setApptHash($evt);

        return [$vo->serialize(),$dts,$pageId];
    }

    /**
     * This is also called from DavListener
     * @param \Sabre\VObject\Component\VEvent $evt
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     */
    function evtCancelAttendee(&$evt){

        $a=$this->getAttendee($evt);
        if ($a===null) {
            \OC::$server->getLogger()->error("evtCancelAttendee() bad attendee");
            return;
        }

        $a->parameters['PARTSTAT']->setValue('DECLINED');

        if(!isset($evt->SUMMARY)) $evt->add('SUMMARY'); // ???
        $evt->SUMMARY->setValue($a->parameters['CN']->getValue());

        $evt->STATUS->setValue('CANCELLED');

        if(!isset($evt->TRANSP)) $evt->add('TRANSP');
        $evt->TRANSP->setValue("TRANSPARENT");


    }



    /**
     * Returns Array [
     *          Localized DateTime string,
     *          "dtsamp,dtstart,dtend" (string)
     *          $tz_data for new appointment can be one of:
     *                  VTIMEZONE data,
     *                  'L' = floating (default)
     *                  'UTC' for UTC/GMT
     *          $title the title might need to be reset to original when the appointment is canceled (can be empty)
     * ]
     * @param string $data
     * @return string[]
     * @noinspection PhpDocMissingThrowsInspection
     */
    function dataDeleteAppt($data){
        $f="";
        $vo=$this->getAppointment($data,'CONFIRMED');
        if($vo===null) return ['','',$f,''];

        /** @var \Sabre\VObject\Component\VEvent $evt*/
        $evt=$vo->VEVENT;

        if(isset($evt->DTSTART) && isset($evt->DTEND)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $dt = (new \DateTime('now', new \DateTimeZone('utc')))->format("Ymd\THis") . "Z,".
                rtrim($evt->DTSTART->getRawMimeDirValue(),'Z').",".
                rtrim($evt->DTEND->getRawMimeDirValue(),'Z');

            if(!$evt->DTSTART->isFloating()){
                if(isset($evt->DTSTART['TZID']) && isset($vo->VTIMEZONE)){
                    $f=$vo->VTIMEZONE->serialize();
                    if(empty($f)) $f='UTC'; // <- ???
                }else{
                    $f='UTC';
                }
            }
        }else{
            $dt="";
        }

        $title="";
        $xad=explode(chr(31),$this->decrypt(
            $evt->{BackendUtils::XAD_PROP}->getValue(),
            $evt->UID->getValue()));

        // @see dataSetAttendee() $xad=...
        if(count($xad)>1 && !empty($xad[1]) && $xad[1][0]==='_'){
            $title=$xad[1];
        }

        return [$this->getDateTimeString(
            $evt->DTSTART->getDateTime(),
            $evt->{self::TZI_PROP}->getValue()
        ),$dt,$f,$title];
    }

    /**
     * @param \Sabre\VObject\Component\VEvent $evt
     * @return \Sabre\VObject\Property|null
     */
    function getAttendee($evt){
        $r=null;
        $ao=null;

        $ov=$evt->ORGANIZER->getValue();
        $ov=trim(substr($ov,strpos($ov,":")+1));

        $aa=$evt->ATTENDEE;
        $c=count($aa);
        for($i=0;$i<$c;$i++){
            $a=$aa[$i];
            $v=$a->getValue();
            if(isset($a->parameters['CN']) && isset($a->parameters['PARTSTAT'])){
                // Some external clients set SCHEDULE-STATUS to 3.7 because of the "acct" scheme
                if (isset($a->parameters['SCHEDULE-STATUS'])){
                    unset($a->parameters['SCHEDULE-STATUS']);
                }
                // Some external clients add organizer as attendee we only use if it is the only attendee (testing), otherwise we look for the one that does NOT match the organizer
                if($ov===trim(substr($v,strpos($v,":")+1))){
                    $ao=$a;
                    continue;
                }
                $r=$a;
                break;
            }
        }
        return $r!==null?$r:$ao;
    }

    /**
     * @param string $uid
     * @return string|null
     */
    function getApptHash($uid){
        $db=\OC::$server->getDatabaseConnection();

        $query = $db->getQueryBuilder();
        $query->select(['hash'])
            ->from(self::HASH_TABLE_NAME)
            ->where($query->expr()->eq('uid', $query->createNamedParameter($uid)));
        $stmt = $query->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if(!$row) {
            return null;
        }else{
            return $row['hash'];
        }
    }

    /**
     * @param \Sabre\VObject\Component\VEvent $evt
     */
    function setApptHash($evt){
        if(!isset($evt->UID)){
            \OC::$server->getLogger()->error("can't set appt_hash, no UID");
            return;
        }
        if(!isset($evt->DTSTART)){
            \OC::$server->getLogger()->error("can't set appt_hash, no DTSTART");
            return;
        }

        $uid=$evt->UID->getValue();

        $db=\OC::$server->getDatabaseConnection();
        $query = $db->getQueryBuilder();

        if($this->getApptHash($uid)===null){
            $query->insert(self::HASH_TABLE_NAME)
                ->values([
                    'uid' => $query->createNamedParameter($uid),
                    'hash' => $query->createNamedParameter(
                        $this->makeApptHash($evt))
                ])
                ->execute();
        }else{
            $query->update(self::HASH_TABLE_NAME)
                ->set('uid', $query->createNamedParameter($uid))
                ->set('hash', $query->createNamedParameter(
                    $this->makeApptHash($evt)))
                ->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
                ->execute();
        }
    }

    function deleteApptHash($evt){

        if(!isset($evt->UID)){
            \OC::$server->getLogger()->error("can't delete appt_hash, no UID");
            return;
        }

        $this->deleteApptHashByUID(
            $db=\OC::$server->getDatabaseConnection(),
            $evt->UID->getValue()
        );
    }

    /**
     * @param \OCP\IDBConnection $db
     * @param string $uid
     */
    function deleteApptHashByUID($db,$uid){
        $query = $db->getQueryBuilder();
        $query->delete(self::HASH_TABLE_NAME)
            ->where($query->expr()->eq('uid',
                $query->createNamedParameter($uid)))
            ->execute();
    }


    function makeApptHash($evt){
        // !! ORDER IS IMPORTANT - DO NOT CHANGE !! //
        $hs="";
        if(isset($evt->DTSTART)){
            $hs.=str_replace("T",".",$evt->DTSTART->getRawMimeDirValue());
        }else{
            $hs.="99999999.000000";
        }
        if(isset($evt->STATUS)){
            $hs.=hash("crc32", $evt->STATUS->getValue(), false);
        }else{
            $hs.="00000000";
        }
        if(isset($evt->LOCATION)){
            $hs.=hash("crc32", $evt->LOCATION->getValue(), false);
        }else{
            $hs.="00000000";
        }
        return $hs;
    }

    /**
     * @param string $hash
     * @param \Sabre\VObject\Component\VEvent $evt
     * @return bool
     */
    function isApptCancelled($hash,$evt){
        // 1e5189eb = hash("crc32", "CANCELLED", false)
        return $evt->STATUS->getValue()==="CANCELLED" && substr($hash,15,8)==="1e5189eb";
    }

    /**
     * @param string $hash
     * @return float
     */
    function getHashDTStart($hash){
        // TODO: this really should be the DTEND
        return (float)substr($hash,0,15);
    }

    /**
     * Returns null when there are no changes, array otherwise:
     *  [index 0 - true if DTSTART changed,
     *   index 1 - true if STATUS changed,
     *   index 2 - true if LOCATION changed]
     *
     * @param string $hash
     * @param \Sabre\VObject\Component\VEvent $evt
     * @return bool[]|null
     */
    function getHashChanges($hash,$evt){
        $evt_hash=$this->makeApptHash($evt);
        if($hash===$evt_hash) return null; // not changed

        return [
            substr($hash,0,15)!==substr($evt_hash,0,15),
            substr($hash,15,8)!==substr($evt_hash,15,8),
            substr($hash,23,8)!==substr($evt_hash,23,8)
        ];
    }

    /**
     * @param \Sabre\VObject\Component\VEvent $evt
     */
    function setSEQ($evt){
        if(!isset($evt->SEQUENCE)) $evt->add('SEQUENCE',1);
        else{
            $sv=intval($evt->SEQUENCE->getValue());
            $evt->SEQUENCE->setValue($sv+1);
        }
        if(!isset($evt->{'LAST-MODIFIED'})) $evt->add('LAST-MODIFIED');
        $evt->{'LAST-MODIFIED'}->setValue(new \DateTime());
    }

    /**
     * @param string $data
     * @param string $status fail is STATUS does not match
     * @return \Sabre\VObject\Document|null
     */
    function getAppointment($data,$status){
        $vo = Reader::read($data);

        if($vo===null || !isset($vo->VEVENT)){
            \OC::$server->getLogger()->error("Bad Data: not an event");
            return null;
        }
        /** @var \Sabre\VObject\Component\VEvent $evt*/
        $evt=$vo->VEVENT;

        if(!$evt->DTSTART->hasTime()){
            // no all-day events
            return null;
        }

        if(!isset($evt->STATUS) || ($status !== "*" && $evt->STATUS->getValue() !== $status)) {
            \OC::$server->getLogger()->error("Bad Status: must be " . $status);
            return null;
        }

        if(!isset($evt->CATEGORIES) || $evt->CATEGORIES->getValue()!==BackendUtils::APPT_CAT){
            \OC::$server->getLogger()->error("Bad Category: not an ".BackendUtils::APPT_CAT);
            return null;
        }

        if(!isset($evt->{self::TZI_PROP})){
            \OC::$server->getLogger()->error("Missing ".self::TZI_PROP." property");
            return null;
        }

        if ($this->getAttendee($evt)===null) {
            \OC::$server->getLogger()->error("Bad ATTENDEE attribute");
            return null;
        }

        return $vo;
    }


    /**
     * @param string $key
     * @param string $userId
     * @return array
     */
     function getUserSettings($key,$userId){

         $config = \OC::$server->getConfig();

         if($key===self::KEY_CLS){
             $default=self::CLS_DEF;
         }else if($key===self::KEY_PAGES){
             $default=self::PAGES_DEF;
         }else if($key===self::KEY_ORG){
             $default=self::ORG_DEF;
         }else if($key===self::KEY_PSN){
             $default=self::PSN_DEF;
         }else if(strpos($key,self::KEY_MPS)===0){
             $default=self::MPS_DEF;
         }else if($key===self::KEY_EML){
             $default=self::EML_DEF;
         }else if($key===self::KEY_DIR){
             $default=self::DIR_DEF;
         }else if($key===self::KEY_TALK){
             $default=self::TALK_DEF;
             // Translate defaults
             $l10n=\OC::$server->getL10N($this->appName);
             $default[self::TALK_FORM_DEF_LABEL]=$l10n->t('Meeting Type');
             $default[self::TALK_FORM_DEF_PLACEHOLDER]=$l10n->t('Select meeting type');
             $default[self::TALK_FORM_DEF_REAL]=$l10n->t('In-person meeting');
             $default[self::TALK_FORM_DEF_VIRTUAL]=$l10n->t('Online (audio/video)');
         }else if($key===self::KEY_FORM_INPUTS_JSON){
             // this is a special case
             $sa = json_decode(
                 $config->getUserValue($userId, $this->appName, $key,null),
                 true);
             if ($sa !== null) {
                 return $sa;
             }else{
                 return [];
             }
         }else if($key===self::KEY_FORM_INPUTS_HTML){
             // this is a special case
             return [$config->getUserValue($userId, $this->appName, $key,'')];
         }else{
             // this should never happen
             return null;
         }

         $sa = json_decode(
             $config->getUserValue($userId, $this->appName, $key),
             true);
         if ($sa === null) {
             return $default;
         }

         foreach ($default as $k => $v) {
             if (!isset($sa[$k])) {
                 $sa[$k] = $v;
             }
         }
         return $sa;
    }
    /**
     * @param string $key
     * @param string $value_str JSON String
     * @param array $default_or_pgs this is value when $key===self::KEY_PAGES
     * @param string $userId
     * @param string $appName
     * @return bool
     * @noinspection PhpDocMissingThrowsInspection
     */
    function setUserSettings($key, $value_str, $default_or_pgs, $userId, $appName){
        if($key===self::KEY_PAGES){
            // already filtered array @see StateController:set_pages
            $sa=$default_or_pgs;
        }else {
            $va = json_decode($value_str, true);
            if ($va === null) {
                return false;
            }
            $sa = [];
            foreach ($default_or_pgs as $k => $v) {
                if (isset($va[$k]) && gettype($va[$k]) === gettype($v)) {
                    $sa[$k] = $va[$k];
                } else {
                    $sa[$k] = $v;
                }
            }
        }
        $js=json_encode($sa);
        if($js===false){
            return false;
        }

        $config=\OC::$server->getConfig();
        /** @noinspection PhpUnhandledExceptionInspection */
        $config->setUserValue($userId,$appName,$key,$js);
        return true;
    }

    /**
     * For simple mode:
     *  Main = CLS_MAIN_ID
     *  Other = CLS_DEST_ID
     *
     * For external mode:
     *  Main = XTM_DST_ID (destination calendar)
     *  Other = XTM_SRC_ID (source calendar)
     *
     * @param string $userId
     * @param string $pageId
     * @param IBackendConnector|null $bc checks backend if provided
     * @param string|null $otherCal get the ID of the other calendar "-1"=not found
     * @return string calendar Id or "-1" = no main cal
     */
    function getMainCalId($userId,$pageId,$bc,&$otherCal=null){

        if($pageId==='p0'){
            // main calendar is provider
            $csProvider=$this->getUserSettings(self::KEY_CLS,$userId);
        }else{
            // more_pages_ holds $dst/$src cals
            $csProvider=$this->getUserSettings(self::KEY_MPS.$pageId,$userId);
        }

        // What mode are we in ??
        $ts_mode=$csProvider[self::CLS_TS_MODE];
        if ($ts_mode==="1"){
            $dst=$csProvider[self::CLS_XTM_DST_ID];
            $src=$csProvider[self::CLS_XTM_SRC_ID];
            // External mode - main calendar is destination calendar
            if($src === "-1" || $dst === "-1" || $src === $dst){
                if(isset($otherCal)){
                    $otherCal='-1';
                }
                return "-1";
            }else{
                if(isset($otherCal)){
                    $otherCal=($bc!==null && $bc->getCalendarById($src,$userId)===null)?'-1':$src;
                }
                return ($bc!==null && $bc->getCalendarById($dst,$userId)===null)?"-1":$dst;
            }
        }else{
            // Manual $ts_mode==="0"
            if(isset($otherCal)){
                $dst=$csProvider[self::CLS_DEST_ID];
                $otherCal=($bc!==null && $bc->getCalendarById($dst,$userId)===null)?'-1':$dst;
            }
            $src=$csProvider[self::CLS_MAIN_ID];
            return ($bc!==null && $bc->getCalendarById($src,$userId)===null)?'-1':$src;
        }
    }

    /**
     * @param string $userId
     * @param string $pageId
     * @param string $appName
     * @param string $tz_data_str Can be VTIMEZONE data, 'UTC'
     * @param string $cr_date 20200414T073008Z must be UTC (ends with Z),
     * @param string $title title is used when the appointment is being reset
     * @return string[] ['1_before_uid'=>'string...','2_before_dts'=>'string...','3_before_dte'=>'string...','4_last'=>'string...'] or ['err'=>'Error text...']
     */
    function makeAppointmentParts($userId, $pageId, $appName, $tz_data_str, $cr_date,$title=""){

        $l10n=\OC::$server->getL10N($appName);
        $iUser=\OC::$server->getUserManager()->get($userId);
        if($iUser===null){
            return ['err'=>'Bad user Id.'];
        }
        $rn="\r\n";
        $cr_date_rn=$cr_date."\r\n";

        $tz_id="";
        $tz_Z="";
        $tz_data = "";
        if($tz_data_str!=="UTC" && !empty($tz_data_str)){
            $tzo=Reader::read("BEGIN:VCALENDAR\r\nPRODID:-//IDN nextcloud.com//Appointments App//EN\r\nCALSCALE:GREGORIAN\r\nVERSION:2.0\r\n".$tz_data_str."\r\nEND:VCALENDAR");
            if(isset($tzo->VTIMEZONE) &&  isset($tzo->VTIMEZONE->TZID)){
                $tz_id=';TZID='.$tzo->VTIMEZONE->TZID->getValue();
                $tz_data=trim($tzo->VTIMEZONE->serialize())."\r\n";
            }
        }else{
            $tz_Z="Z";
        }

        $org=$this->getUserSettings(self::KEY_ORG,$userId);
        if($pageId==='p0'){
            $org_name=$org[BackendUtils::ORG_NAME];
            $addr=$org[BackendUtils::ORG_ADDR];
        }else{
            $mps=$this->getUserSettings(
                BackendUtils::KEY_MPS.$pageId,$userId);
            $org_name=!empty($mps[BackendUtils::ORG_NAME])
                ?$mps[BackendUtils::ORG_NAME]
                :$org[BackendUtils::ORG_NAME];
            $addr=!empty($mps[BackendUtils::ORG_ADDR])
                ?$mps[BackendUtils::ORG_ADDR]
                :$org[BackendUtils::ORG_ADDR];
        }

        // email is not per page
        $email=$org[self::ORG_EMAIL];

        $name=trim($iUser->getDisplayName());
        if(empty($name)){
            $name=$org_name;
        }

        if(empty($email)) $email=$iUser->getEMailAddress();
        if(empty($email)){
            return ['err'=>$l10n->t("Your email address is required for this operation.")];
        }
        if(empty($addr)){
            return ['err'=>$l10n->t("A location, address or URL is required for this operation. Check User/Organization settings.")];
        }
//        ESCAPED-CHAR = ("\\" / "\;" / "\," / "\N" / "\n")
//        \\ encodes \ \N or \n encodes newline \; encodes ; \, encodes ,
        $addr=str_replace(array("\\",";",",","\r\n","\r","\n"),array('\\\\','\;','\,',' \n',' \n',' \n'),$addr);

        if(empty($name)){
            return ['err'=>$l10n->t("Can't find your name. Check User/Organization settings.")];
        }

        if(empty($title)){
            $summary=\OC::$server->getL10N($appName)->t("Available");
        }else{
            $summary=$title;
        }


        return [
            '1_before_uid'=>"BEGIN:VCALENDAR\r\n" .
                "PRODID:-//IDN nextcloud.com//Appointment App | srgdev.com//EN\r\n" .
                "CALSCALE:GREGORIAN\r\n" .
                "VERSION:2.0\r\n" .
                "BEGIN:VEVENT\r\n" .
                "SUMMARY:".$summary.$rn.
                "STATUS:TENTATIVE\r\n" .
                "TRANSP:TRANSPARENT\r\n".
                "LAST-MODIFIED:" . $cr_date_rn .
                "DTSTAMP:" . $cr_date_rn .
                "SEQUENCE:1\r\n" .
                "CATEGORIES:" . BackendUtils::APPT_CAT . $rn .
                "CREATED:" . $cr_date_rn . "UID:", // UID goes here
            '2_before_dts' => $rn . "DTSTART".$tz_id.":", // DTSTART goes here
            '3_before_dte' => $tz_Z.$rn . "DTEND".$tz_id.":", // DTEND goes here
            '4_last' => $tz_Z.$rn .$this->chunk_split_unicode("ORGANIZER;CN=".$name.":mailto:".$email,75,"\r\n ").$rn . $this->chunk_split_unicode("LOCATION:".$addr,75,"\r\n "). $rn. "END:VEVENT\r\n".$tz_data."END:VCALENDAR\r\n"
        ];
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

    /**
     * @param $userId
     * @param \OCP\IConfig $config
     * @return \DateTimeZone
     */
    function getUserTimezone($userId,$config){
        $tz_name=$config->getUserValue($userId, 'calendar', 'timezone');
        if(empty($tz_name) || strpos($tz_name,'auto')!==false){
            // Try Nextcloud default timezone
            $tz_name = $config->getUserValue($userId, 'core', 'timezone');
            if(empty($tz_name) || strpos($tz_name,'auto')){
                return \OC::$server->getDateTimeZone()->getTimeZone();
                // Use UTC
//                \OC::$server->getLogger()->warning("no timezone for floating time found - using date_default_timezone_get(): ".date_default_timezone_get());
//                $tz_name=date_default_timezone_get();
            }
        }

        try {
            $tz=new \DateTimeZone($tz_name);
        }catch (\Exception $e){
            \OC::$server->getLogger()->error($e->getMessage());
            $tz=new \DateTimeZone('utc'); // fallback to utc
        }

        return $tz;
    }

    /**
     * @param \DateTimeImmutable $date
     * @param string $tzi Timezone info [UF][+-]\d{4} Ex: U+0300 @see dataSetAttendee() or [UF](valid timezone name) Ex: UAmerica/New_York
     * @param int $short_dt
     *      0 = long format
     *      1 = short format (for email subject)
     * @return string
     * @noinspection PhpDocMissingThrowsInspection
     */
    function getDateTimeString($date, $tzi, $short_dt=0){

        $l10N=\OC::$server->getL10N($this->appName);
        if($tzi[0]==="F"){
            $d=$date->format('Ymd\THis');
            if($short_dt===0){
                $date_time =
                    $l10N->l('date', $d, ['width' => 'full']) . ', ' .
                    $l10N->l('time', $d, ['width' => 'short']);
            }else if($short_dt===1) {
                $date_time =$l10N->l('datetime', $d, ['width' => 'short']);
            }else{
                $date_time='';
            }
        }else{
            try {
                $d = new \DateTime('now', new \DateTimeZone(substr($tzi, 1)));
            } catch (\Exception $e) {
                \OC::$server->getLogger()->error($e->getMessage());
                /** @noinspection PhpUnhandledExceptionInspection */
                $d = new \DateTime('now', $date->getTimezone());
            }
            $d->setTimestamp($date->getTimestamp());

            if($short_dt===0){
                $date_time = $l10N->l('date', $d, ['width' => 'full']).', '.
                    str_replace(':00 ', ' ',
                        $l10N->l('time', $d, ['width' => 'long']));
            }else if($short_dt===1) {
                $date_time =$l10N->l('datetime', $d, ['width' => 'short']);
            }else{
                $date_time='';
            }
        }

        return $date_time;
    }

    /**
     * @param string $data
     * @param string $key
     * @param string $iv special case
     * @return string
     */
    function encrypt(string $data,string $key,$iv=''):string {
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

        return $_iv!==''
            ?base64_encode($_iv.$ciphertext_raw)
            :$_iv.$ciphertext_raw;
    }

    /**
     * @param string $data
     * @param string $key
     * @param string $iv
     * @return string
     */
    function decrypt(string $data,string $key,$iv=''):string {
        $s1=$iv===''?base64_decode($data):$data;
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


    /**
     * @param string $token
     * @param bool $embed
     * @return string
     */
    function pubPrx($token,$embed){
        return $embed ? 'embed/'.$token.'/' : 'pub/'.$token.'/';
    }


    function getPublicWebBase(){
        return \OC::$server->getURLGenerator()->getBaseUrl().'/index.php/apps/appointments';
    }


    /**
     * @param string $token
     * @param \OCP\IConfig $config
     * @return string[]|null[] [useId,pageId] on success, [null,null]=not verified
     * @throws \ErrorException
     */
    function verifyToken($token,$config){
        if(empty($token) || strlen($token)>256) return [null,null];
        $token=str_replace("_","/",$token);
        $key=hex2bin($config->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($config->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }

        $l=strlen($token);
        if(($l&3)!==0){
            // not divisible by 4
            $m=intval($token[$l-1]);
            $token=substr($token,0,-($m===1?2:1));
            $token.=str_repeat('=',$m);

            $rc=base64_decode($token);
            if($rc===false) return [null,null];

            $iv[0]=substr($rc,-1);
            $raw=substr($rc,0,-1);
            $pageId='';
        }else{
            $raw=base64_decode($token);
            if($raw===false) return [null,null];
            $pageId="p0";
        }

        $td=$this->decrypt($raw,$key,$iv);
        if(strlen($td)>4 && substr($td,0,4)===hash( 'adler32', substr($td,4),true)){
            $u=substr($td, 4);
            if($pageId===''){
                return [substr($u,0,-1),'p'.ord(substr($u,-1))];
            }else {
                return [$u, $pageId];
            }
        }else{
            return [null,null];
        }
    }


    /**
     * @param string $userId
     * @param string $pageId (optional)
     * @return string
     * @throws \ErrorException
     */
    function getToken($userId,$pageId="p0"){
        $config=\OC::$server->getConfig();
        $key=hex2bin($config->getAppValue($this->appName, 'hk'));
        $iv=hex2bin($config->getAppValue($this->appName, 'tiv'));
        if(empty($key) || empty($iv)){
            throw new \ErrorException("Can't find key");
        }
        if($pageId==="p0"){
            $pfx='';
            $upi=$userId;
        }else{
            $pn=intval(substr($pageId,1));
            if($pn<1 || $pn>14){
                throw new \ErrorException("Bad page number");
            }
            $pfx=($iv[0]^$iv[15])^$iv[$pn];

            $iv=$pfx.substr($iv,1);
            $upi=$userId.chr($pn);
        }

        $tkn=$this->encrypt(
            hash('adler32',$upi,true).$upi, $key, $iv);


        if($pfx===''){
            $bd=base64_encode($tkn);
        }else{
            $v=base64_encode($tkn.$pfx);
            $bd=trim($v,'=');
            $ld=strlen($v)-strlen($bd);
            if($ld===1){
                $bd.="01";
            }else{
                $bd.=$ld;
            }
        }
        return urlencode(str_replace("/","_", $bd));
    }
}

