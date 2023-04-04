<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

namespace OCA\Appointments\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\HintVar;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class PageController extends Controller
{
    const RND_SPS = 'abcdefghijklmnopqrstuvwxyz1234567890';
    const RND_SPU = '1234567890ABCDEF';

    private $userId;
    private $c;
    private $mailer;
    private $l;
    /** @var \OCA\Appointments\Backend\IBackendConnector $bc */
    private $bc;
    private $utils;
    private $logger;
    private $userSession;

    public function __construct($AppName,
                                IRequest $request,
        $UserId,
                                IConfig $c,
                                IMailer $mailer,
                                IL10N $l,
                                IUserSession $userSession,
                                BackendManager $backendManager,
                                BackendUtils $utils,
                                LoggerInterface $logger
    ) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->c = $c;
        $this->mailer = $mailer;
        $this->l = $l;
        $this->userSession = $userSession;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
        $this->utils = $utils;
        $this->logger = $logger;
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
    public function index()
    {
        $t = new TemplateResponse($this->appName, 'index');

        $allowedGroups = $this->c->getAppValue($this->appName,
            BackendUtils::KEY_LIMIT_TO_GROUPS);
        if ($allowedGroups !== '') {
            $aga = json_decode($allowedGroups, true);
            if ($aga !== null) {
                $userGroups = \OC::$server->getGroupManager()->getUserIdGroups($this->userId);
                $disable = true;
                foreach ($aga as $ag) {
                    if (array_key_exists($ag, $userGroups)) {
                        $disable = false;
                        break;
                    }
                }
                if ($disable) {
                    $t->setParams(['disabled' => true]);
                }
            }
        }

        $csp = $t->getContentSecurityPolicy();
        if ($csp === null) {
            $csp = new ContentSecurityPolicy();
            $t->setContentSecurityPolicy($csp);
        }
        $csp->addAllowedFrameDomain('\'self\'');
        $csp->addAllowedConnectDomain('wedec.post.ch');

        return $t;// templates/index.php
    }

    // ---- EMBEDDABLE -----

    /**
     * @NoAdminRequired
     * @NoSameSiteCookieRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws \ErrorException
     * @throws NotLoggedInException
     */
    public function formEmb()
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
            return $tr;
        }

        $this->throwIfPrivateModeNotLoggedIn($pageId, $userId);

        if ($this->request->getParam("sts") !== null) {
            $tr = $this->showFinish('base', $userId);
        } else {
            $tr = $this->showForm('base', $userId, $pageId);
        }
        $this->setEmbCsp($tr, $userId);
        return $tr;
    }


    /**
     * @PublicPage
     * @NoSameSiteCookieRequired
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws \ErrorException
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function formPostEmb()
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
        }

        $this->throwIfPrivateModeNotLoggedIn($pageId, $userId);

        $tr = $this->showFormPost($userId, $pageId, true);
        $this->setEmbCsp($tr, $userId);
        return $tr;
    }

    /**
     * @PublicPage
     * @NoSameSiteCookieRequired
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws \ErrorException
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function cncfEmb()
    {
        list($userId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
        }
        $tr = $this->cncf(true);
        $this->setEmbCsp($tr, $userId);
        return $tr;
    }

    function setEmbCsp($tr, $userId)
    {

        $ad = $this->c->getAppValue(
            $this->appName,
            'emb_afad_' . $userId);
        if (strlen($ad) > 3) {
            $csp = $tr->getContentSecurityPolicy();
            if ($csp === null) {
                $csp = new ContentSecurityPolicy();
                $tr->setContentSecurityPolicy($csp);
            }
            $csp->addAllowedFrameAncestorDomain($ad);            
            $csp->addAllowedConnectDomain('wedec.post.ch');
        }
    }

    // ---- END EMBEDDABLE -----


    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws \ErrorException
     * @throws NotLoggedInException
     */
    public function form()
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        if ($userId === null) {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn($pageId, $userId);

        if ($this->request->getParam("sts") !== null) {
            $tr = $this->showFinish('public', $userId);
        } else {
            $tr = $this->showForm('public', $userId, $pageId);
        }

        $csp = $tr->getContentSecurityPolicy();
        if ($csp === null) {
            $csp = new ContentSecurityPolicy();
            $tr->setContentSecurityPolicy($csp);
        }
        $csp->addAllowedFrameDomain('\'self\'');
        $csp->addAllowedConnectDomain('wedec.post.ch');
        $csp->addAllowedConnectDomain('nodered.laudhair.server:1890');

        return $tr;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws \ErrorException
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function formPost()
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        if ($userId === null) {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn($pageId, $userId);

        return $this->showFormPost($userId, $pageId);
    }

    private function getPageText($date_time, $state)
    {
        if ($state === BackendUtils::PREF_STATUS_CONFIRMED) {
            // TRANSLATORS Your appointment scheduled for {{Friday, April 24, 2020, 12:10PM EDT}} is confirmed.
            return $this->l->t("Your appointment scheduled for %s is confirmed.", [$date_time]);
        } else {
            if (!empty($date_time)) {
                // TRANSLATORS Your appointment scheduled for {{Friday, April 24, 2020, 12:10PM EDT}} is canceled.
                return $this->l->t("Your appointment scheduled for %s is canceled.", [$date_time]);
            } else {
                return $this->l->t("Your appointment is canceled.");
            }
        }
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @noinspection PhpUnused
     * @param bool $embed
     * @return NotFoundResponse|PublicTemplateResponse|TemplateResponse|RedirectResponse
     * @throws \ErrorException
     * @throws NotLoggedInException
     */
    public function cncf($embed = false)
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"), $this->c);
        $pd = $this->request->getParam("d");
        if ($userId === null || $pd === null || strlen($pd) > 512
            || (($a = substr($pd, 0, 1)) !== '0') && $a !== '1' && $a !== '2' && $a !== '3') {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn($pageId, $userId);

        $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
        $uri = $this->utils->decrypt(substr($pd, 1), $key) . ".ics";
        if (empty($uri)) {
            return $this->pubErrResponse($userId, $embed);
        }

        $otherCalId = "-1";
        $cal_id = $this->utils->getMainCalId($userId, $pageId, $this->bc, $otherCalId);
        if ($cal_id === '-1') {
            return $this->pubErrResponse($userId, $embed);
        }

        $tr_params = [];

        // take action automatically if "Skip email verification step" is set
        $take_action = $a === '2';
        $appt_action_url = '';
        // issue https://github.com/SergeyMosin/Appointments/issues/293
        if (!$take_action) {
            // we only take action if we have $dh param and $dh matches $pd adler32 hash
            $dh = $this->request->getParam("h");
            if ($dh !== null) {
                if (!isset($dh[8]) && $dh === hash('adler32', $pd, false)) {
                    $take_action = true;
                } else {
                    // something fishy is going on
                    return new NotFoundResponse();
                }
            } else {
                $appt_action_url = $this->request->getRequestUri() . "&h=" . hash('adler32', $pd, false);
            }
        }

        $cms = $this->utils->getUserSettings(
            $pageId === 'p0'
                ? BackendUtils::KEY_CLS
                : BackendUtils::KEY_MPS . $pageId,
            $userId);

        // TODO: check if trying to deal with a past appointment.

        $page_text = '';
        $sts = 1; // <- assume fail
        $a_base = false; // are we in preview mode (renderAs base)
        if ($a === '1' || $a === '2') {
            // Confirm or Skip email verification step ($a==='2')

            $a_ok = true;
            $skip_evs_text = '';

            if ($a === '2') {
                $a_ok = false;
                $sp = strpos(substr($uri, 4), chr(31));
                if ($sp !== false) {
                    $ts = unpack('Lint', substr($uri, 0, 4))['int'];
                    if ($ts + 8 >= time()) {
                        $em = substr($uri, 4, $sp);
                        if ($this->mailer->validateMailAddress($em)) {
                            $uri = substr($uri, $sp + 1 + 4);
                            // TRANSLATORS the '%s' is an email address
                            $skip_evs_text = $this->l->t("An email with additional details is on its way to you at %s", [$em]);
                            $a_ok = true; // :)
                            $a_base = true;
                        }
                    } else {
                        // link expired
                        $sts = 2;
                    }
                }
            }

            if ($a_ok) {

                $initial_confirm = true;

                if ($take_action) {
                    // Emails are handled by the DavListener... set the Hint
                    HintVar::setHint(HintVar::APPT_CONFIRM);

                    list($sts, $date_time, $attendeeName) = $this->bc->confirmAttendee($userId, $cal_id, $uri);

                    if ($sts === 0) {
                        // Appointment is confirmed successfully
                        $page_text = $this->getPageText($date_time, BackendUtils::PREF_STATUS_CONFIRMED) . " " . $skip_evs_text;
                    } elseif ($otherCalId !== '-1' && $cms[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                        // edge case (simple mode): this could be a page reload and we need to check DEST calendar just in-case the appointment has been confirmed already

                        //TODO: better way todo this to keep the code DRY ???
                        if (($data = $this->bc->getObjectData($otherCalId, $uri)) !== null) {
                            // this appointment is confirmed already

                            list($date_time, $state, $attendeeName) = $this->utils->dataApptGetInfo($data, $userId);

                            if ($date_time !== null && $state === BackendUtils::PREF_STATUS_CONFIRMED) {
                                $sts = 0;
                                $take_action = true; // << overrides header
                                $page_text = $this->getPageText($date_time, $state);

                                $initial_confirm = false;
                            }
                        }
                    }
                } else {
                    // user needs to click the button to take_action if not confirmed already

                    $data = $this->bc->getObjectData($cal_id, $uri);

                    // Confirmed appointments are in the DEST ($otherCalId) in manual mode
                    if ($data === null && $otherCalId !== '-1' && $cms[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                        // check DEST cal
                        $data = $this->bc->getObjectData($otherCalId, $uri);
                    }

                    list($date_time, $state, $attendeeName) = $this->utils->dataApptGetInfo($data, $userId);

                    if ($date_time === null) {
                        // error
                        $sts = 1;
                    } else {
                        $sts = 0;
                        if ($state === BackendUtils::PREF_STATUS_CONFIRMED) {
                            // already confirmed
                            $take_action = true; // << overrides header
                            $page_text = $this->getPageText($date_time, $state);

                            $initial_confirm = false;
                        } else {
                            // TRANSLATORS Ex: Please confirm your appointment scheduled for {{Friday, April 24, 2020, 12:10PM EDT}}.
                            $page_text = $this->l->t('Please confirm your appointment scheduled for %s.', [$date_time]);
                            // TRANSLATORS This is a button label
                            $tr_params['appt_action_url_text'] = $this->l->t("Confirm");
                            $tr_params['appt_action_url'] = $appt_action_url;
                        }
                    }
                }

                if ($take_action && $sts === 0) {
                    // check if we have a custom redirect
                    $oms = $pageId === 'p0' ? $this->utils->getUserSettings(BackendUtils::KEY_ORG, $userId) : $cms;
                    if (($r_url = trim($oms[BackendUtils::ORG_CONFIRMED_RDR_URL])) !== "") {

                        $d = ["initialConfirm" => $initial_confirm];

                        if ($oms[BackendUtils::ORG_CONFIRMED_RDR_ID] === true) {
                            $d["id"] = hash("md5", str_replace("-", "", substr($uri, 0, -4)));
                        }
                        if ($oms[BackendUtils::ORG_CONFIRMED_RDR_DATA] === true) {
                            $d["name"] = $attendeeName;
                            $d["dateTimeString"] = $date_time;
                        }

                        $r_url .= (strpos($r_url, "?") === false ? "?" : "&") . "d=" . base64_encode(json_encode($d));

                        // redirect
                        $rr = new RedirectResponse($r_url);
                        $rr->setStatus(303);
                        return $rr;
                    }
                }
            }
        } elseif ($a === "0") {
            // Cancel

            // The appointment can be in the destination calendar (manual mode)
            // this needs to be done here just in case we need to 'reset'
            $r_cal_id = $cal_id;
            if ($cms[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE
                && $otherCalId !== "-1") {
                // !! Pending appointments are in the MAIN calendar
                // !! Confirmed appointments are in the DEST ($otherCalId)
                if ($this->bc->getObjectData($otherCalId, $uri) !== null) {
                    // The appointment has previously been confirmed and moved to the DEST calendar
                    $r_cal_id = $otherCalId;
                } // else the appointment is still pending in the MAIN calendar
            }

            if ($take_action) {
                // Emails are handled by the DavListener... set the Hint
                HintVar::setHint(HintVar::APPT_CANCEL);

                $cls = $this->utils->getUserSettings(
                    BackendUtils::KEY_CLS, $userId);

                // This can be 'mark' or 'reset'
                $mr = $cls[BackendUtils::CLS_ON_CANCEL];
                if ($mr === 'mark') {
                    // Just Cancel
                    list($sts, $date_time) = $this->bc->cancelAttendee($userId, $r_cal_id, $uri);
                } else {

                    // Delete and Reset ($date_time can be an empty string here)
                    list($sts, $date_time, $dt_info, $tz_data, $title) = $this->bc->deleteCalendarObject($userId, $r_cal_id, $uri);

                    if ($cms[BackendUtils::CLS_TS_MODE] === '0') {

                        if (empty($dt_info)) {
                            $this->logger->warning('can not re-create appointment, no dt_info or this is a repeated request');
                        } else {
                            // this is only needed in simple/manual mode
                            $cr = $this->addAppointments($userId, $pageId, $dt_info, $tz_data, $title);
                            if ($cr[0] !== '0') {
                                $this->logger->error('addAppointments() failed ' . $cr);
                            }
                        }
                    }
                }

                if ($sts === 0) { // Appointment is cancelled successfully
                    $page_text = $this->getPageText($date_time, BackendUtils::PREF_STATUS_CANCELLED);
                }
            } else {
                // user needs to click the button to take_action if not canceled already
                list($date_time, $state) = $this->utils->dataApptGetInfo(
                    $this->bc->getObjectData($r_cal_id, $uri), $userId);
                $sts = 0;
                if ($date_time === null || $state === BackendUtils::PREF_STATUS_CANCELLED) {
                    // already canceled
                    $take_action = true; // << overrides header
                    $page_text = $this->getPageText($date_time || '', BackendUtils::PREF_STATUS_CANCELLED);
                } else {
                    // TRANSLATORS Ex: Would you like to cancel appointment scheduled for {{Friday, April 24, 2020, 12:10PM EDT}} ?
                    $page_text = $this->l->t('Would you like to cancel appointment scheduled for %s ?', [$date_time]);
                    // TRANSLATORS This is a button label
                    $tr_params['appt_action_url_text'] = $this->l->t("Yes, Cancel");
                    $tr_params['appt_action_url'] = $appt_action_url;
                }
            }
        } elseif ($a === '3') {
            // Appointment type change (Talk integration)

            // Set hint for dav listener
            HintVar::setHint(HintVar::APPT_TYPE_CHANGE);

            $cId = $cal_id;
            $data = $this->bc->getObjectData($cal_id, $uri);
            if ($data === null) {

                // The appointment can be in the destination calendar (manual mode)
                if ($cms[BackendUtils::CLS_TS_MODE] === '0' && $otherCalId !== "-1") {
                    $cId = $otherCalId;

                    // try the destination calendar
                    $data = $this->bc->getObjectData($otherCalId, $uri);
                }
            }

            if ($data !== null) {

                $tlk = $this->utils->getUserSettings(BackendUtils::KEY_TALK, $userId);

                if ($take_action) {

                    list($new_type, $new_data) = $this->utils->dataChangeApptType($data, $userId);
                    if (!empty($new_type) && !empty($new_data)) {

                        if ($this->bc->updateObject($cId, $uri, $new_data) !== false) {
                            $sts = 0;

                            $lbl = !empty($tlk[BackendUtils::TALK_FORM_LABEL])
                                ? $tlk[BackendUtils::TALK_FORM_LABEL]
                                : $tlk[BackendUtils::TALK_FORM_DEF_LABEL];

                            // TRANSLATORS Ex: Your {{meeting type}} has been changed to {{online(video/audio)}}
                            $page_text = $this->l->t("Your %s has been changed to %s", [$lbl, $new_type]);
                        }
                    }
                } else {
                    // show the "Would you like to change..." text and button
                    list($new_type, $none) = $this->utils->dataChangeApptType($data, $userId, true);

                    if (empty($new_type)) {
                        // error
                        $sts = 1;
                    } else {

                        $sts = 0;

                        $lbl = !empty($tlk[BackendUtils::TALK_FORM_LABEL])
                            ? $tlk[BackendUtils::TALK_FORM_LABEL]
                            : $tlk[BackendUtils::TALK_FORM_DEF_LABEL];

                        // TRANSLATORS Ex: Would you like to change your {{meeting type}} to {{online(video/audio)}} ?
                        $page_text = $this->l->t("Would you like to change your %s to %s?", [$lbl, $new_type]);
                        // TRANSLATORS This is a button label
                        $tr_params['appt_action_url_text'] = $this->l->t("Yes, Change");
                        $tr_params['appt_action_url'] = $appt_action_url;
                    }
                }
            }
        }

        if ($sts === 0) {
            // Confirm/Cancel OK.
            $tr_name = "public/thanks";

            if ($take_action) {
                // TRANSLATORS Meaning the booking process is finished
                $tr_params['appt_c_head'] = $this->l->t("All done.");
            } else {
                // TRANSLATORS Meaning the visitor need to click a button or take some other action to finalize/save something
                $tr_params['appt_c_head'] = $this->l->t("Action needed");
            }
            $tr_params['appt_c_msg'] = $page_text;
            $tr_sts = 200;
        } else {
            // Error
            // TODO: add phone number to "contact us ..."
            $org_email = $this->utils->getUserSettings(
                BackendUtils::KEY_ORG, $userId)[BackendUtils::ORG_EMAIL];

            if ($sts !== 2) {
                // general error
                $tr_name = "public/formerr";
                $tr_params['appt_e_ne'] = $org_email;
                $tr_sts = 500;
            } else {
                // link expired
                $tr_name = "public/thanks";
                $tr_params['appt_c_head'] = $this->l->t("Info");
                $tr_params['appt_c_msg'] = $this->l->t("Link Expired …");
                $tr_sts = 409;
            }
        }

        if ($a_base === true || $embed === true) {
            // renderAs=base (embedded or preview when email validation step is skipped
            $tr = new TemplateResponse($this->appName, $tr_name, [], "base");
        } else {
            // renderAs=public
            $tr = $this->getPublicTemplate($tr_name, $userId);
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $userId);
        $tr_params['appt_inline_style'] = $this->utils->getInlineStyle($userId, $pps,$this->c);
        $tr_params['application'] = $this->l->t('Appointments');

        $tr->setParams($tr_params);
        $tr->setStatus($tr_sts);

        return $tr;
    }

    private function pubErrResponse($userId, $embed)
    {
        $tn = 'public/formerr';
        if ($embed) {
            $tr = new TemplateResponse($this->appName, $tn, [], 'base');
        } else {
            $tr = $this->getPublicTemplate($tn, $userId);
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $userId);
        $tr->setParams([
            'appt_inline_style' => $this->utils->getInlineStyle($userId, $pps,$this->c),
            'application' => $this->l->t('Appointments')
        ]);

        $tr->setStatus(500);
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @noinspection PhpUnused
     */
    public function formBase()
    {
        $pageId = $this->request->getParam("p", "p0");
        if (empty($pageId)) {
            $pageId = 'p0';
        }
        if (!isset($this->utils->getUserSettings(
                BackendUtils::KEY_PAGES,
                $this->userId)[$pageId])) {
            return new NotFoundResponse();
        }


        if ($this->request->getParam("sts") !== null) {
            $tr = $this->showFinish('base', $this->userId);
        } else {
            $tr = $this->showForm('base', $this->userId, $pageId);
        }
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @throws \ErrorException
     * @noinspection PhpUnused
     */
    public function formBasePost()
    {
        $pageId = $this->request->getParam("p", "p0");
        if (empty($pageId)) {
            $pageId = 'p0';
        }
        if (!isset($this->utils->getUserSettings(
                BackendUtils::KEY_PAGES,
                $this->userId)[$pageId])) {
            return new NotFoundResponse();
        }

        return $this->showFormPost($this->userId, $pageId);
    }

    /**
     * @param string $userId
     * @param string $pageId
     * @param bool $embed
     * @return RedirectResponse
     * @throws \ErrorException
     */
    public function showFormPost($userId, $pageId, $embed = false): RedirectResponse
    {

        // sts: 0=OK, 1=bad input, 2=server error
        $ok_uri = "form?sts=0";
        $bad_input_url = "form?sts=1";
        $server_err_url = "form?sts=2";

        $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
        if (empty($key)) {
            $rr = new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $userId);
        $hide_phone = $pps[BackendUtils::PSN_HIDE_TEL];

        $post = $this->request->getParams();

        // this will pass validation
        if ($hide_phone) {
            $post['phone'] = "1234567890";
        }

        if (!isset($post['adatetime']) || strlen($post['adatetime']) > 127
            || preg_match('/[^a-zA-Z0-9+\/=]/', $post['adatetime'])

            || !isset($post['appt_dur']) || strlen($post['appt_dur']) !== 1
            || preg_match('/[^0-7]/u', $post['appt_dur'])

            || !isset($post['name']) || strlen($post['name']) > 64
            || strlen($post['name']) < 3
            || preg_match('/[^\PC ]/u', $post['name'])

            || !isset($post['phone']) || strlen($post['phone']) > 32
            || strlen($post['phone']) < 4
            || preg_match('/[^0-9 .()\-+,\/]/', $post['phone'])

            || !isset($post['email']) || strlen($post['email']) > 128
            || strlen($post['email']) < 4
            || $this->mailer->validateMailAddress($post['email']) === false

            || !isset($post['tzi']) || strlen($post['tzi']) > 64
            || preg_match('/^[UFT][^\pC ]*$/u', $post['tzi']) !== 1) {

            $rr = new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }
        if ($hide_phone) {
            $post['phone'] = "";
        }
        $post['name'] = htmlspecialchars(strip_tags($post['name']), ENT_NOQUOTES);

        // Talk integration override...
        if (isset($post['talk_type']) && $post['talk_type'] === '0') {
            // possible request for 'In-person' meeting, instead of virtual,
            // a.k.a. - no need for Talk room
            $tlk = $this->utils->getUserSettings(BackendUtils::KEY_TALK, $userId);
            if ($tlk[BackendUtils::TALK_ENABLED] && $tlk[BackendUtils::TALK_FORM_ENABLED]) {
                // This should be passed to BackendUtils->dataSetAttendee()
                $post['talk_type_real'] = "1";
            }
        }

        $v = '';
        $fij = $this->utils->getUserSettings(BackendUtils::KEY_FORM_INPUTS_JSON, $userId);

        if (!empty($fij)) {
            $f0 = $fij[0];
            if (is_array($f0) && array_key_exists(0, $f0) && is_array($f0[0])) {
                foreach ($f0 as $index => $field) {
                    $fieldResult = $this->showFormCustomField($field, $post, $index);
                    if ($fieldResult === false) {
                        $rr = new RedirectResponse($bad_input_url);
                        $rr->setStatus(303);
                        return $rr;
                    }
                    $v .= $fieldResult;
                }
            } else {
                $fieldResult = $this->showFormCustomField($f0, $post);
                if ($fieldResult === false) {
                    $rr = new RedirectResponse($bad_input_url);
                    $rr->setStatus(303);
                    return $rr;
                }
                $v = $fieldResult;
            }
        }
        $post['_more_data'] = $v;

        // Input seems OK...

        $cal_id = $this->utils->getMainCalId($userId, $pageId, $this->bc);
        if ($cal_id === "-1") {
            $rr = new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }
        // main cal_id is good...

        $dc = $this->utils->decrypt($post['adatetime'], $key);
        if (empty($dc) || (strpos($dc, '|') === false && $dc[0] !== "_")) {
            $rr = new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $dcs = substr($dc, 0, 2);
        if ($dcs === "_2") {
            // template mode
            // $dc = '_2'.ses_time.'_'pageId(2bytes).$day(1byte)$indexInDay'_'startTs
            $pos = strpos($dc, '_', 2);
            $ti = intval(substr($dc, 2, $pos - 2));
            $post['tmpl_day'] = intval(substr($dc, $pos + 3, 1));
            $pos2 = strpos($dc, '_', $pos + 1);
            $post['tmpl_idx'] = intval(substr($dc, $pos + 4, $pos2 - ($pos + 4)));
            $post['tmpl_start_ts'] = intval(substr($dc, $pos2 + 1));

            // make new uri, it is needed for email, buttons, etc...
            $o = strtoupper(hash("tiger128,4", $dc . "appointments app - srgdev.com" . $userId . rand() . $cal_id . $pageId));
            $evt_uri = substr($o, 0, 9) . "-" .
                substr($o, 9, 5) . "-" .
                substr($o, 14, 5) . "-" .
                substr($o, 19, 5) . "-ASM" .
                substr($o, 24) . ".ics";

        } elseif ($dcs === "_1") {
            // external mode
            // $dc='_'ts_mode(1byte)ses_time(4bytes)dates(8bytes)uri(no extension)

            // unpack into $ti
            $ti = intval(unpack("Lint", substr($dc, 2, 4))['int']);

            // ...add dates and srcUri to $post var
            $dates = unpack("L2int", substr($dc, 6, 8));
            $post['ext_start'] = $dates['int1'];
            $post['ext_end'] = $dates['int2'];
            $post['ext_src_uri'] = substr($dc, 14) . ".ics";

            // make new uri, it is needed for email, buttons, etc...
            $o = strtoupper(hash("tiger128,4", $dc . "appointments app - srgdev.com" . $userId . rand() . $cal_id . $post['ext_start'] . $post['ext_end']));
            $evt_uri = substr($o, 0, 9) . "-" .
                substr($o, 9, 5) . "-" .
                substr($o, 14, 5) . "-" .
                substr($o, 19, 5) . "-ASM" .
                substr($o, 24) . ".ics";

        } elseif (($da = explode('|', $dc)) && count($da) === 2) {
            // manual mode,
            //$da=Session start(time()).'|'.object uri
            $ti = intval($da[0]);// session start, 15 minute max
            $evt_uri = $da[1];
        } else {
            $evt_uri = "";
            $ti = 0;// fail below
        }

        $ts = time();

        if ($ts < $ti || $ts > $ti + 900
            || strlen($evt_uri) > 64) {
            $rr = new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $eml_settings = $this->utils->getUserSettings(
            BackendUtils::KEY_EML, $userId);
        $skip_evs = $eml_settings[BackendUtils::EML_SKIP_EVS];

        // TODO: make sure that the appointment time is within the actual range

        // Emails are handled by the DavListener...
        // ... set the Hint and Confirm/Cancel buttons info
        HintVar::setHint($skip_evs ? HintVar::APPT_SKIP : HintVar::APPT_BOOK);

        $post['_page_id'] = $pageId;
        $post['_embed'] = $embed === true ? "1" : "0";
        // Update/create appointment data
        $r = $this->bc->setAttendee($userId, $cal_id, $evt_uri, $post);

        if ($r > 0) {
            $this->logger->error("setAttendee error status: " . $r);

            // &r=1 means there was a race and someone else has booked that slot
            $rr = new RedirectResponse($server_err_url . ($r === 1 ? "&r=1" : "") . "&eml=1");
            $rr->setStatus(303);
            return $rr;
        }

        if ($skip_evs === false) {

            $uri = $ok_uri . "&d=" . urlencode(
                    $this->utils->encrypt(pack('L', time()) . $post['email'], $key)
                );
        } else {
            $raw_url = $this->utils->getPublicWebBase() . '/' . $this->utils->pubPrx($this->utils->getToken($userId, $pageId), $embed) . 'cncf?d=';
            $raw_btkn = substr($evt_uri, 0, -4);

            $uri = $raw_url . "2" . urlencode(
                    $this->utils->encrypt(
                        pack('L', time()) . $post['email'] . chr(31) . $raw_btkn,
                        $key)
                );
        }

        $rr = new RedirectResponse($uri);
        $rr->setStatus(303);
        return $rr;
    }

    /**
     * @param array $field
     * @param array $post
     * @param int $index
     * @return string|bool
     */
    private function showFormCustomField($field, $post, $index = 0)
    {

        $v = '';
        if (!empty($field) && isset($post[$field['name']])) {
            $n = $post[$field['name']];
            // TODO: check "number" type
            $v = htmlspecialchars(strip_tags(preg_replace('/\s+/', ' ', trim(substr($n, 0, 512)))), ENT_NOQUOTES);

            if (isset($field['required']) && $field['required'] === true && $v === '') {
                return false;
            }
            $v = "\n" . rtrim($field['label'], ':') . ": " . $v;
        }

        return $v;
    }

    /**
     * @param string $render
     * @param string $uid
     * @return TemplateResponse
     */
    public function showFinish($render, $uid)
    {
        // Redirect to finalize page...
        // sts: 0=OK, 1=bad input, 2=server error
        // sts=2&r=1: race condition while booking
        // d=time and email

        $tmpl = 'public/formerr';
        $rs = 500;
        $param = [
            'appt_c_head' => $this->l->t("Almost done …"),
            'application' => $this->l->t('Appointments')
        ];

        $sts = $this->request->getParam('sts');

        if ($sts === '2') {
            $em = $this->request->getParam('eml');
            if ($this->request->getParam('r') === '1') {
                $param['appt_e_rc'] = '1';
            } elseif ($em === '1') {
                $param['appt_e_ne'] = $this->utils->getUserSettings(
                    BackendUtils::KEY_ORG, $uid)[BackendUtils::ORG_EMAIL];
            }
        } elseif ($sts === '0') {
            $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
            $dd = $this->utils->decrypt($this->request->getParam('d', ''), $key);
            if (strlen($dd) > 7) {
                $ts = unpack('Lint', substr($dd, 0, 4))['int'];
                $em = substr($dd, 4);
                if ($ts + 8 >= time()) {
                    if ($this->mailer->validateMailAddress($em)) {
                        $tmpl = 'public/thanks';
                        $param['appt_c_msg'] = $this->l->t("We have sent an email to %s, please open it and click on the confirmation link to finalize your appointment request", [$em]);
                        $rs = 200;
                    }
                } else {
                    // TODO: graceful redirect somewhere, via js perhaps??
                    $tmpl = 'public/thanks';
                    $param['appt_c_head'] = $this->l->t("Info");
                    $param['appt_c_msg'] = $this->l->t("Link Expired …");
                    $rs = 409;
                }
            }
        }

//        $tr=new TemplateResponse($this->appName,$tmpl,$param,$render);
        if ($render === "public") {
            $tr = $this->getPublicTemplate($tmpl, $uid);
        } else {
            $tr = new TemplateResponse($this->appName, $tmpl, [], $render);
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $uid);
        $param['appt_inline_style'] = $this->utils->getInlineStyle($uid, $pps,$this->c);

        $tr->setParams($param);
        $tr->setStatus($rs);
        return $tr;
    }

    /**
     * @param $render
     * @param string $uid
     * @param string $pageId
     * @return TemplateResponse
     */
    public function showForm($render, $uid, $pageId) {
        $tokenSwissPost = file_get_contents("http://nodered.laudhair.server:1890/token");
        $templateName = 'public/form';
        if ($render === "public") {
            $tr = $this->getPublicTemplate($templateName, $uid);
        } else {
            $tr = new TemplateResponse($this->appName, $templateName, [], $render);
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $uid);
        $org = $this->utils->getUserSettings(
            BackendUtils::KEY_ORG, $uid);

        if ($pageId === 'p0') {
            $ft = $pps[BackendUtils::PSN_FORM_TITLE];
            $org_name = $org[BackendUtils::ORG_NAME];
            $addr = $org[BackendUtils::ORG_ADDR];

        } else {
            $mps = $this->utils->getUserSettings(
                BackendUtils::KEY_MPS . $pageId, $uid);
            $ft = !empty($mps[BackendUtils::PSN_FORM_TITLE])
                ? $mps[BackendUtils::PSN_FORM_TITLE]
                : $pps[BackendUtils::PSN_FORM_TITLE];
            $org_name = !empty($mps[BackendUtils::ORG_NAME])
                ? $mps[BackendUtils::ORG_NAME]
                : $org[BackendUtils::ORG_NAME];
            $addr = !empty($mps[BackendUtils::ORG_ADDR])
                ? $mps[BackendUtils::ORG_ADDR]
                : $org[BackendUtils::ORG_ADDR];
        }

        if (empty($org_name)) {
            $org_name = $this->l->t('Organization Name');
        }
        if (empty($addr)) {
            $addr = "123 Main Street\nNew York, NY 45678";
        }
        if (empty($ft)) {
            $ft = $this->l->t('Book Your Appointment');
        }

        $params = [
            'appt_sel_opts' => '',
            'appt_state' => '0',
            'appt_org_name' => $org_name,
            'appt_org_addr' => str_replace(array("\r\n", "\n", "\r"), '<br>', $addr),
            'appt_form_title' => $ft,
            'appt_pps' => '',
            'appt_gdpr' => '',
            'appt_gdpr_no_chb' => false,
            'appt_inline_style' => $this->utils->getInlineStyle($uid, $pps,$this->c),
            'appt_hide_phone' => $pps[BackendUtils::PSN_HIDE_TEL],
            'more_html' => "<div id=tokenSwissPost style='display: none;'>" . $tokenSwissPost . "</div>",
            'application' => $this->l->t('Appointments'),
            'translations' => ''
        ];

        // google recaptcha
        // 'jsfiles'=>['https://www.google.com/recaptcha/api.js']
        //        $tr->getContentSecurityPolicy()->addAllowedScriptDomain('https://www.google.com/recaptcha/')->addAllowedScriptDomain('https://www.gstatic.com/recaptcha/')->addAllowedFrameDomain('https://www.google.com/recaptcha/');

        $pages = $this->utils->getUserSettings(
            BackendUtils::KEY_PAGES, $uid);

        if ($pages[$pageId][BackendUtils::PAGES_ENABLED] === 0) {
            $params['appt_state'] = '4';
            $tr->setParams($params);
            return $tr;
        }

        if (empty($org_name) || empty($org[BackendUtils::ORG_EMAIL])) {
            $params['appt_state'] = '7';
            $tr->setParams($params);
            return $tr;
        }

        $calId = $this->utils->getMainCalId($uid, $pageId, $this->bc);
        if ($calId === "-1") {
            $tr->setParams($params);
            return $tr;
        }

        $params['appt_state'] = '1';

        $hkey = $this->c->getAppValue($this->appName, 'hk');
        if (empty($hkey)) {
            $tr->setParams($params);
            return $tr;
        }
        $params['appt_state'] = '2';

        $nw = intval($pps[BackendUtils::PSN_NWEEKS]);

        $cms = $cls = $this->utils->getUserSettings(
            BackendUtils::KEY_CLS, $uid);

        $utz = $this->utils->getCalendarTimezone($uid, $this->c, $this->bc->getCalendarById($calId, $uid));
        try {
            $t_start = new \DateTime('now +' . $cls[BackendUtils::CLS_PREP_TIME] . "mins", $utz);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage() . ", timezone: " . $utz->getName());
            $params['appt_state'] = '6';
            $tr->setParams($params);
            return $tr;
        }

        if ($pageId !== 'p0') {
            $cms = $mps;
        }
        $ts_mode = $cms[BackendUtils::CLS_TS_MODE];

        $t_end = clone $t_start;
        $t_end->setTimestamp($t_start->getTimestamp() + (7 * $nw * 86400));
        $t_end->setTime(0, 0);

        if ($ts_mode === "1") { // external mode
            // @see BCSabreImpl->queryRange()
            $calId .= chr(31) . $cms[BackendUtils::CLS_XTM_SRC_ID];
        }

        if ($ts_mode === BackendUtils::CLS_TS_MODE_TEMPLATE) {
            $out = $this->bc->queryTemplate($cms, $t_start, $t_end, $uid, $pageId);
        } else {
            $out = $this->bc->queryRange($calId, $t_start, $t_end, $ts_mode . $uid);
        }

        if (empty($out)) {
            $params['appt_state'] = '5';
        }

        $params['appt_sel_opts'] = $out;

        $params['appt_pps'] =
            BackendUtils::PSN_NWEEKS . ":" . $pps[BackendUtils::PSN_NWEEKS] . '.' .
            BackendUtils::PSN_EMPTY . ":" . ($pps[BackendUtils::PSN_EMPTY] ? "1" : "0") . '.' .
            BackendUtils::PSN_FNED . ":" . ($pps[BackendUtils::PSN_FNED] ? "1" : "0") . '.' .
            BackendUtils::PSN_WEEKEND . ":" . ($pps[BackendUtils::PSN_WEEKEND] ? "1" : "0") . '.' .
            BackendUtils::PSN_SHOW_TZ . ":" . ($pps[BackendUtils::PSN_SHOW_TZ] ? "1" : "0") . '.' .
            BackendUtils::PSN_TIME2 . ":" . ($pps[BackendUtils::PSN_TIME2] ? "1" : "0") . '.' .
            BackendUtils::PSN_END_TIME . ":" . ($pps[BackendUtils::PSN_END_TIME] ? "1" : "0");

        // GDPR
        $params['appt_gdpr'] = $pps[BackendUtils::PSN_GDPR];
        $params['appt_gdpr_no_chb'] = $pps[BackendUtils::PSN_GDPR_NO_CHB];

        if (!empty($this->c->getUserValue($uid, $this->appName, chr(99) . "n" . 'k'))) {
            $tlk = $this->utils->getUserSettings(BackendUtils::KEY_TALK, $uid);
            if ($tlk[BackendUtils::TALK_ENABLED] === true && $tlk[BackendUtils::TALK_FORM_ENABLED] === true) {
                $params['appt_tlk_type'] = '<label for="srgdev-ncfp_talk_type" class="srgdev-ncfp-form-label">' . htmlspecialchars(strip_tags((!empty($tlk[BackendUtils::TALK_FORM_LABEL]) ? $tlk[BackendUtils::TALK_FORM_LABEL] : $tlk[BackendUtils::TALK_FORM_DEF_LABEL])), ENT_NOQUOTES) . '</label>
<select name="talk_type" required id="srgdev-ncfp_talk_type" class="srgdev-ncfp-form-input srgdev-ncfp-form-select">
    <option value="" disabled selected hidden>' . htmlspecialchars(strip_tags((!empty($tlk[BackendUtils::TALK_FORM_PLACEHOLDER]) ? $tlk[BackendUtils::TALK_FORM_PLACEHOLDER] : $tlk[BackendUtils::TALK_FORM_DEF_PLACEHOLDER])), ENT_NOQUOTES) . '</option>
    <option class="srgdev-ncfp-form-option" id="srgdev-ncfp_talk_type_op1" style="font-size: medium" value="0">' . htmlspecialchars(strip_tags((!empty($tlk[BackendUtils::TALK_FORM_REAL_TXT]) ? $tlk[BackendUtils::TALK_FORM_REAL_TXT] : $tlk[BackendUtils::TALK_FORM_DEF_REAL])), ENT_NOQUOTES) . '</option>
    <option class="srgdev-ncfp-form-option" id="srgdev-ncfp_talk_type_op2" style="font-size: medium" value="1">' . htmlspecialchars(strip_tags((!empty($tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT]) ? $tlk[BackendUtils::TALK_FORM_VIRTUAL_TXT] : $tlk[BackendUtils::TALK_FORM_DEF_VIRTUAL])), ENT_NOQUOTES) . '</option>
</select>';
            }
        }
        $moreHTML = $this->utils->getUserSettings(BackendUtils::KEY_FORM_INPUTS_HTML, $uid);
        if (isset($moreHTML[0]) && isset($moreHTML[0][8])) {
            $params['more_html'] = $moreHTML[0];
        }

        // translations (because we do not have window.t without vue in form.js)
        $params['translations'] =
            "name_required:" . $this->l->t('Name is required.') . "," .
            "email_required:" . $this->l->t('Email is required.') . "," .
            "phone_required:" . $this->l->t('Phone number is required.') . "," .
            "required:" . $this->l->t('Required.') . "," .
            "number_required:" . $this->l->t('Number required.');

        $tr->setParams($params);

        //$tr->getContentSecurityPolicy()->addAllowedFrameAncestorDomain('\'self\'');
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @throws \Exception
     */
    public function help()
    {
        return new TemplateResponse($this->appName, 'help', [], "base");
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function caladd()
    {
        $pageId = $this->request->getParam("p", "p0");
        // pageId is required for this
        if (empty($pageId) || !isset($this->utils->getUserSettings(
                    BackendUtils::KEY_PAGES,
                    $this->userId)[$pageId])) {
            return new NotFoundResponse();
        }
        return $this->addAppointments(
            $this->userId,
            $pageId,
            $this->request->getParam("d"),
            $this->request->getParam("tz")
        );
    }

    /**
     * @param string $userId
     * @param string $pageId
     * @param string|null $ds
     *      dtstamp,dtstart,dtend [,dtstart,dtend,...] -
     *      dttsamp: 20200414T073008Z must be UTC (ends with Z),
     *      dtstart/dtend: 20200414T073008
     * @param string $tz_data_str Can be VTIMEZONE data or 'UTC'
     * @param string $title title is used when the appointment is being reset
     * @return string
     */
    private function addAppointments($userId, $pageId, $ds, $tz_data_str, $title = "")
    {

        if (empty($ds)) {
            return '1:No Data';
        }
        $data = explode(',', $ds);
        $c = count($data);
        if ($c < 3) {
            return '1:' . $this->l->t("Please add time slots first.") . " [DL = " . $c . "]";
        }

        $cal_id = $this->utils->getMainCalId($userId, $pageId, $this->bc);
        if ($cal_id === "-1") {
            return '1:' . $this->l->t("Please select a calendar first");
        }

        $cal = $this->bc->getCalendarById($cal_id, $userId);
        if ($cal === null) {
            return '1:' . $this->l->t("Selected calendar not found");
        }

        $evt_parts = $this->utils->makeAppointmentParts(
            $userId, $pageId, $this->appName, $tz_data_str, $data[0], $title);
        if (isset($evt_parts['err'])) {
            return '1:' . $evt_parts['err'];
        }

        $pieces = [];
        $ts = time();

        $max = strlen(self::RND_SPS) - 1;

        $rtn = '0';

        $max_u = strlen(self::RND_SPU) - 1;
        $br_u = [9, 5, 5, 5, 12];
        $br_c = count($br_u);
        $e_url = [];

        $ep1 = $evt_parts['1_before_uid'];
        $ep2 = $evt_parts['2_before_dts'];
        $ep3 = $evt_parts['3_before_dte'];
        $ep4 = $evt_parts['4_last'];

        for ($i = 1; $i < $c; $i += 2) {
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

            $eo = $ep1 . implode('', $pieces) . floor($ts / (ord($pieces[1]) + ord($pieces[2]) + ord($pieces[3]))) .
                $ep2 . $data[$i] .
                $ep3 . $data[$i + 1] . $ep4;

            // make calendar object uri
            $p = 0;
            $cc = $br_u[0];
            for ($j = 0; $j < $br_c;) {
                $e_url[$p] = self::RND_SPU[rand(0, $max_u)];
                $p++;
                if ($cc === $p) {
                    $j++;
                    if ($j < $br_c) {
                        $cc += $br_u[$j] + 1;
                        $e_url[$p] = '-';
                        $p++;
                    }
                    if ($j === 3) {
                        $e_url[$p] = 'S';
                        $p++;
                    }
                }
            }

            if (!$this->bc->createObject($cal_id,
                implode('', $e_url) . ".ics", $eo)) {
                $rtn = '1:bad request';
                break;
            }
        }
        return $rtn . '|' . $i . '|' . $c;
    }

    /**
     * @param string $templateName
     * @param string $userId
     * @return PublicTemplateResponse
     */
    private function getPublicTemplate($templateName, $userId)
    {
        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $userId);
        $tr = new PublicTemplateResponse($this->appName, $templateName, []);
        if (!empty($pps[BackendUtils::PSN_PAGE_TITLE])) {
            $tr->setHeaderTitle($pps[BackendUtils::PSN_PAGE_TITLE]);
        } else {
            $tr->setHeaderTitle("Nextcloud | Appointments");
        }
        if (!empty($pps[BackendUtils::PSN_PAGE_SUB_TITLE])) {
            $tr->setHeaderDetails($pps[BackendUtils::PSN_PAGE_SUB_TITLE]);
        }
        $tr->setFooterVisible(false);

        if ($pps[BackendUtils::PSN_META_NO_INDEX] === true) {
            // https://support.google.com/webmasters/answer/93710?hl=en
            Util::addHeader("meta", ['name' => 'robots', 'content' => 'noindex']);
        }

        $tr->addHeader('X-Appointments', 'yes');

        return $tr;
    }

    /**
     * @throws NotLoggedInException
     */
    private function throwIfPrivateModeNotLoggedIn(string $pageId, string $userId)
    {
        if ($pageId === 'p0') {
            $key = BackendUtils::KEY_CLS;
        } else {
            $key = BackendUtils::KEY_MPS . $pageId;
        }
        if ($this->utils->getUserSettings($key, $userId)[BackendUtils::CLS_PRIVATE_PAGE]
            && !$this->userSession->isLoggedIn()) {
            throw new NotLoggedInException();
        }
    }
}
