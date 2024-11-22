<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

namespace OCA\Appointments\Controller;

use OC\AppFramework\Middleware\Security\Exceptions\NotLoggedInException;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\Backend\HintVar;
use OCA\Appointments\Backend\IBackendConnector;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
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

    const TEST_TOKEN_CNF = '3b719b44-8ec9-41e9-b161-00fb1515b1ed';

    private string|null $userId;
    private IConfig $c;
    private IMailer $mailer;
    private IL10N $l;
    private IBackendConnector $bc;
    private BackendUtils $utils;
    private LoggerInterface $logger;
    private IUserSession $userSession;
    private IURLGenerator $urlGenerator;

    public function __construct(IRequest        $request,
                                IConfig         $c,
                                IMailer         $mailer,
                                IL10N           $l,
                                IUserSession    $userSession,
                                BackendManager  $backendManager,
                                BackendUtils    $utils,
                                IURLGenerator   $urlGenerator,
                                LoggerInterface $logger
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->c = $c;
        $this->mailer = $mailer;
        $this->l = $l;
        $this->userSession = $userSession;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
        $this->utils = $utils;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->userId = $this->userSession->getUser()?->getUID();
    }

    /**
     * CAUTION: the @Stuff turns off security checks; for this page no admin is
     *          required and no CSRF check. If you don't know what CSRF is, read
     *          it up in the docs, or you might create a security hole. This is
     *          basically the only required method to add this exemption, don't
     *          add it to any other method if you don't exactly know what it does
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse
    {
        $t = new TemplateResponse($this->appName, 'index');

        $disable = false;
        if (!empty($this->userId)) {
            $allowedGroups = $this->c->getAppValue($this->appName,
                BackendUtils::KEY_LIMIT_TO_GROUPS);
            if ($allowedGroups !== '') {
                $aga = json_decode($allowedGroups, true);
                if ($aga !== null) {
                    $user = $this->userSession->getUser();
                    $userGroups = \OC::$server->get(IGroupManager::class)->getUserGroups($user);
                    $disable = true;
                    foreach ($aga as $ag) {
                        if (array_key_exists($ag, $userGroups)) {
                            $disable = false;
                            break;
                        }
                    }
                }
            }
        }

        if ($disable) {
            $t->setParams(['disabled' => true]);
        } else {
            Util::addScript(Application::APP_ID, 'script');
            Util::addStyle(Application::APP_ID, 'style');
        }

        $csp = $t->getContentSecurityPolicy();
        if ($csp === null) {
            $csp = new ContentSecurityPolicy();
            $t->setContentSecurityPolicy($csp);
        }
        $csp->addAllowedFrameDomain('\'self\'');

        return $t;// templates/index.php
    }

    // ---- EMBEDDABLE -----

    /**
     * @NoAdminRequired
     * @NoSameSiteCookieRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws NotLoggedInException
     */
    public function formEmb(): Response
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"));
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
            return $tr;
        }

        $this->throwIfPrivateModeNotLoggedIn();

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
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function formPostEmb(): RedirectResponse
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"));
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
        }

        $this->throwIfPrivateModeNotLoggedIn();

        $tr = $this->showFormPost($userId, $pageId, true);
        $this->setEmbCsp($tr, $userId);
        return $tr;
    }

    /**
     * @PublicPage
     * @NoSameSiteCookieRequired
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function cncfEmb(): Response
    {
        list($userId) = $this->utils->verifyToken($this->request->getParam("token"));
        if ($userId === null) {
            $tr = new TemplateResponse($this->appName, "public/r404", [], "base");
            $tr->setStatus(404);
        }
        $tr = $this->cncf(true);
        $this->setEmbCsp($tr, $userId);
        return $tr;
    }

    private function setEmbCsp(Response $tr, string $userId): void
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
        }
    }

    // ---- END EMBEDDABLE -----


    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws NotLoggedInException
     */
    public function form(): Response
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"));
        if ($userId === null) {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn();

        if ($this->request->getParam("sts") !== null) {
            $tr = $this->showFinish('public', $userId);
        } else {
            $tr = $this->showForm('public', $userId, $pageId);
        }
        return $tr;
    }

    /**
     * @PublicPage
     * @NoCSRFRequired
     * @NoAdminRequired
     * @throws NotLoggedInException
     * @noinspection PhpUnused
     */
    public function formPost(): Response
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"));
        if ($userId === null) {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn();

        return $this->showFormPost($userId, $pageId);
    }

    private function getPageText(string $date_time, int $state): string
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
     * @throws NotLoggedInException
     */
    public function cncf(bool $embed = false): Response
    {
        list($userId, $pageId) = $this->utils->verifyToken($this->request->getParam("token"));

        $pd = $this->request->getParam("d");
        if ($userId === null || $pd === null || strlen($pd) > 512
            || (($a = substr($pd, 0, 1)) !== '0') && $a !== '1' && $a !== '2' && $a !== '3') {
            return new NotFoundResponse();
        }

        $this->throwIfPrivateModeNotLoggedIn();

        $dParam = substr($pd, 1);
        if ($dParam === self::TEST_TOKEN_CNF) {
            // shortcircut to testing
            $a = '-' . $a;
        } else {
            $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
            $uri = $this->utils->decrypt($dParam, $key) . ".ics";
            if (empty($uri)) {
                return $this->pubErrResponse($userId, $embed);
            }
        }

        $settings = $this->utils->getUserSettings();

        $otherCalId = "-1";
        $cal_id = $this->utils->getMainCalId($userId, $this->bc, $otherCalId);
        if ($cal_id === '-1') {
            return $this->pubErrResponse($userId, $embed);
        }

        $tr_params = ['appt_c_more' => ''];

        // take action automatically if "Skip email verification step" is set
        $take_action = $a === '2';
        $appt_action_url_hash = '';
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
                $appt_action_url_hash = hash('adler32', $pd, false);
            }
        }

        // TODO: check if trying to deal with a past appointment.

        $page_text = '';
        $sts = 1; // <- assume fail
        $a_base = false; // are we in preview mode (renderAs base)
        if ($a === '1' || $a === '2') {
            // Confirm or Skip email verification step ($a==='2')

            $a_ok = true;
            $skip_evs_email = '';

            if ($a === '2') {
                $a_ok = false;
                $sp = strpos(substr($uri, 4), chr(31));
                if ($sp !== false) {
                    $ts = unpack('Lint', substr($uri, 0, 4))['int'];
                    if ($ts + 8 >= time()) {
                        $em = substr($uri, 4, $sp);
                        if ($this->mailer->validateMailAddress($em)) {
                            // form is ok and skip evs is active
                            $uri = substr($uri, $sp + 1 + 4);
                            $skip_evs_email = $em;
                            $a_ok = true; // :)
                            $a_base = true;
                            $tr_params['appt_c_more'] = $settings[BackendUtils::PSN_FORM_FINISH_TEXT];
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

                    list($sts, $date_time, $attendeeName) = $this->bc->confirmAttendee($userId, $pageId, $cal_id, $uri);

                    if ($sts === 0) {
                        // Appointment is confirmed successfully
                        $page_text = $this->makeConfirmedPageText($date_time, $skip_evs_email);
                    } elseif ($otherCalId !== '-1' && $settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                        // edge case (simple mode): this could be a page reload, and we need to check DEST calendar just in-case the appointment has been confirmed already

                        //TODO: better way todo this to keep the code DRY ???
                        if (($data = $this->bc->getObjectData($otherCalId, $uri)) !== null) {
                            // this appointment is confirmed already

                            list($date_time, $state, $attendeeName) = $this->utils->dataApptGetInfo($data);

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
                    if ($data === null && $otherCalId !== '-1' && $settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE) {
                        // check DEST cal
                        $data = $this->bc->getObjectData($otherCalId, $uri);
                    }

                    list($date_time, $state, $attendeeName) = $this->utils->dataApptGetInfo($data);

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
                            $tr_params['appt_action_url_hash'] = $appt_action_url_hash;
                        }
                    }
                }

                if ($take_action && $sts === 0) {
                    // check if we have a custom redirect
                    if (($r_url = trim($settings[BackendUtils::ORG_CONFIRMED_RDR_URL])) !== "") {

                        $d = ["initialConfirm" => $initial_confirm];

                        if ($settings[BackendUtils::ORG_CONFIRMED_RDR_ID] === true) {
                            $d["id"] = hash("md5", str_replace("-", "", substr($uri, 0, -4)));
                        }
                        if ($settings[BackendUtils::ORG_CONFIRMED_RDR_DATA] === true) {
                            $d["name"] = $attendeeName;
                            $d["dateTimeString"] = $date_time;
                        }

                        $r_url .= (!str_contains($r_url, "?") ? "?" : "&") . "d=" . base64_encode(json_encode($d));

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
            if ($settings[BackendUtils::CLS_TS_MODE] === BackendUtils::CLS_TS_MODE_SIMPLE
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

                // This can be 'mark' or 'reset'
                $mr = $settings[BackendUtils::CLS_ON_CANCEL];
                if ($mr === 'mark') {
                    // Just Cancel
                    list($sts, $date_time) = $this->bc->cancelAttendee($userId, $pageId, $r_cal_id, $uri);
                } else {

                    // Delete and Reset ($date_time can be an empty string here)
                    list($sts, $date_time, $dt_info, $tz_data, $title) = $this->bc->deleteCalendarObject($userId, $r_cal_id, $uri);

                    if ($settings[BackendUtils::CLS_TS_MODE] === '0') {

                        if (empty($dt_info)) {
                            $this->logger->warning('can not re-create appointment, no dt_info or this is a repeated request');
                        } else {
                            // this is only needed in simple/manual mode
                            $cr = $this->addAppointments($userId, $dt_info, $tz_data, $title);
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
                    $this->bc->getObjectData($r_cal_id, $uri));
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
                    $tr_params['appt_action_url_hash'] = $appt_action_url_hash;
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
                if ($settings[BackendUtils::CLS_TS_MODE] === '0' && $otherCalId !== "-1") {
                    $cId = $otherCalId;

                    // try the destination calendar
                    $data = $this->bc->getObjectData($otherCalId, $uri);
                }
            }

            if ($data !== null) {

                if ($take_action) {

                    list($new_type, $new_data) = $this->utils->dataChangeApptType($data, $userId);
                    if (!empty($new_type) && !empty($new_data)) {

                        if ($this->bc->updateObject($cId, $uri, $new_data) !== false) {
                            $sts = 0;

                            $lbl = !empty($settings[BackendUtils::TALK_FORM_LABEL])
                                ? $settings[BackendUtils::TALK_FORM_LABEL]
                                : $settings[BackendUtils::TALK_FORM_DEF_LABEL];

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

                        $lbl = !empty($settings[BackendUtils::TALK_FORM_LABEL])
                            ? $settings[BackendUtils::TALK_FORM_LABEL]
                            : $settings[BackendUtils::TALK_FORM_DEF_LABEL];

                        // TRANSLATORS Ex: Would you like to change your {{meeting type}} to {{online(video/audio)}} ?
                        $page_text = $this->l->t("Would you like to change your %s to %s?", [$lbl, $new_type]);
                        // TRANSLATORS This is a button label
                        $tr_params['appt_action_url_text'] = $this->l->t("Yes, Change");
                        $tr_params['appt_action_url_hash'] = $appt_action_url_hash;
                    }
                }
            }
        } elseif ($a[0] === '-') {
            // testing
            $sts = 0;
            // -2
            $take_action = true;
            $date_time = $this->utils->getDateTimeString(
                new \DateTimeImmutable('now'),
                '_UTC'
            );
            $page_text = $this->makeConfirmedPageText($date_time, 'test.email@domain.com');
            $tr_params['appt_c_more'] = $settings[BackendUtils::PSN_FORM_FINISH_TEXT];
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
            $org_email = $settings[BackendUtils::ORG_EMAIL];

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
            $tr = $this->getPublicTemplate($tr_name);
        }

        $tr_params['appt_inline_style'] = $this->utils->getInlineStyle($userId, $settings);
        $tr_params['application'] = $this->l->t('Appointments');

        $tr->setParams($tr_params);
        $tr->setStatus($tr_sts);

        return $tr;
    }

    private function makeConfirmedPageText(string $date_time, string $skip_evs_email): string
    {
        return $this->getPageText($date_time, BackendUtils::PREF_STATUS_CONFIRMED) .
            (!empty($skip_evs_email)
                // TRANSLATORS the '%s' is an email address
                ? (" " . $this->l->t("An email with additional details is on its way to you at %s", [$skip_evs_email]))
                : '');
    }


    private function pubErrResponse(string $userId, bool $embed): Response
    {
        $tn = 'public/formerr';
        if ($embed) {
            $tr = new TemplateResponse($this->appName, $tn, [], 'base');
        } else {
            $tr = $this->getPublicTemplate($tn);
        }

        $tr->setParams([
            'appt_inline_style' => $this->utils->getInlineStyle($userId, $this->utils->getUserSettings()),
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
    public function formBase(): Response
    {
        $pageId = $this->request->getParam("p", "p0");
        if (empty($pageId)) {
            $pageId = 'p0';
        }
        if (empty($this->userId) || !$this->utils->loadSettingsForUserAndPage($this->userId, $pageId)) {
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
     * @noinspection PhpUnused
     */
    public function formBasePost(): Response
    {
        $pageId = $this->request->getParam("p", "p0");
        if (empty($pageId)) {
            $pageId = 'p0';
        }
        if (empty($this->userId) || !$this->utils->loadSettingsForUserAndPage($this->userId, $pageId)) {
            return new NotFoundResponse();
        }

        return $this->showFormPost($this->userId, $pageId, false, true);
    }

    public function showFormPost(string $userId, string $pageId, bool $embed = false, bool $base = false): RedirectResponse
    {

        $pageParam = $base ? ("&p=" . $pageId) : "";

        // sts: 0=OK, 1=bad input, 2=server error
        $ok_uri = "form?sts=0" . $pageParam;
        $bad_input_url = "form?sts=1" . $pageParam;
        $server_err_url = "form?sts=2" . $pageParam;
        $captcha_failed_url = "form?sts=3" . $pageParam;
        $captcha_server_error_url = "form?sts=4" . $pageParam;
        $blocked_error_url = "form?sts=5" . $pageParam;

        $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
        if (empty($key)) {
            $rr = new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }

        $settings = $this->utils->getUserSettings();
        $hide_phone = $settings[BackendUtils::PSN_HIDE_TEL];

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
            || strlen($post['name']) < 2
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

        if (!empty($settings[BackendUtils::SEC_EMAIL_BLACKLIST]) && is_array($settings[BackendUtils::SEC_EMAIL_BLACKLIST])) {
            $_email = $post['email'];
            foreach ($settings[BackendUtils::SEC_EMAIL_BLACKLIST] as $blocked) {
                if ($_email === $blocked ||
                    // domain block
                    (str_starts_with($blocked, '*@')
                        && str_ends_with($_email, substr($blocked, 1)))
                ) {
                    $rr = new RedirectResponse($blocked_error_url);
                    $rr->setStatus(303);
                    return $rr;

                }
            }
        }

        if ($hide_phone) {
            $post['phone'] = "";
        }
        $post['name'] = htmlspecialchars(strip_tags($post['name']), ENT_NOQUOTES);

        // Talk integration override...
        if (isset($post['talk_type']) && $post['talk_type'] === '0') {
            // possible request for 'In-person' meeting, instead of virtual,
            // a.k.a. - no need for Talk room
            $post['type_override'] = '1';
        }

        $v = '';
        $fij = $settings[BackendUtils::KEY_FORM_INPUTS_JSON];

        if (!empty($fij)) {
            $f0 = $fij;
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

        if ($settings[BackendUtils::SEC_HCAP_ENABLED] === true
            && !empty($settings[BackendUtils::SEC_HCAP_SECRET])
            && !empty($settings[BackendUtils::SEC_HCAP_SITE_KEY])
        ) {
            if (($cErr = $this->validateHCaptcha($post, $settings)) !== 0) {
                if ($cErr === 1) {
                    $rr = new RedirectResponse($captcha_failed_url);
                } else {
                    $rr = new RedirectResponse($captcha_server_error_url);
                }
                $rr->setStatus(303);
                return $rr;
            }
        }

        // Input seems OK...

        $cal_id = $this->utils->getMainCalId($userId, $this->bc);
        if ($cal_id === "-1") {
            $rr = new RedirectResponse($server_err_url);
            $rr->setStatus(303);
            return $rr;
        }
        // main cal_id is good...

        $dc = $this->utils->decrypt($post['adatetime'], $key);
        if (empty($dc) || (!str_contains($dc, '|') && $dc[0] !== "_")) {
            $rr = new RedirectResponse($bad_input_url);
            $rr->setStatus(303);
            return $rr;
        }

        $dcs = substr($dc, 0, 2);
        if ($dcs === "_2") {
            // template mode
            // $dc = '_2'.ses_time.'_'.$day(1byte)$indexInDay'_'startTs
            $pos = strpos($dc, '_', 2);
            $ti = intval(substr($dc, 2, $pos - 2));
            $pos++;
            $post['tmpl_day'] = intval(substr($dc, $pos, 1));
            $pos2 = strpos($dc, '_', $pos);
            $pos++;
            $post['tmpl_idx'] = intval(substr($dc, $pos, $pos2 - $pos));
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

        $skip_evs = $settings[BackendUtils::EML_SKIP_EVS];

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

    private function showFormCustomField(array $field, array $post, int $index = 0): bool|string
    {

        $v = '';
        if (!empty($field) && isset($post[$field['name']])) {
            $n = $post[$field['name']];
            // TODO: check "number" type
            $v = strip_tags(htmlspecialchars(preg_replace('/\s+/', ' ', trim(substr($n, 0, 512))), ENT_NOQUOTES));

            if (isset($field['required']) && $field['required'] === true && $v === '') {
                return false;
            }
            $v = "\n" . rtrim($field['label'], ':') . ": " . $v;
        }

        return $v;
    }

    private function showFinish(string $render, string $uid): Response
    {
        // Redirect to finalize page...
        // sts: 0=OK, 1=bad input, 2=server error, 3=bad captcha, 4=captcha server error, 5=blocked
        // sts=2&r=1: race condition while booking
        // d=time and email

        $tmpl = 'public/formerr';
        $rs = 500;
        $param = [
            'appt_c_head' => $this->l->t("Almost done …"),
            'application' => $this->l->t('Appointments')
        ];

        $sts = $this->request->getParam('sts');

        $settings = $this->utils->getUserSettings();

        if ($sts === '2') {
            $em = $this->request->getParam('eml');
            if ($this->request->getParam('r') === '1') {
                $param['appt_e_rc'] = '1';
            } elseif ($em === '1') {
                $param['appt_e_ne'] = $settings[BackendUtils::ORG_EMAIL];
            }
        } elseif ($sts === '0') {
            $dParam = $this->request->getParam('d', '');
            if ($dParam === self::TEST_TOKEN_CNF) {
                $dd = pack('L', time()) . 'test.email@domain.com';
            } else {
                $key = hex2bin($this->c->getAppValue($this->appName, 'hk'));
                $dd = $this->utils->decrypt($dParam, $key);
            }
            if (strlen($dd) > 7) {
                $ts = unpack('Lint', substr($dd, 0, 4))['int'];
                $em = substr($dd, 4);
                if ($ts + 8 >= time()) {
                    if ($this->mailer->validateMailAddress($em)) {
                        $tmpl = 'public/thanks';
                        $param['appt_c_msg'] = $this->l->t("We have sent an email to %s, please open it and click on the confirmation link to finalize your appointment request", [$em]);
                        $param['appt_c_more'] = $settings[BackendUtils::PSN_FORM_FINISH_TEXT];
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
        } elseif ($sts === '3') {
            $param['input_err'] = $this->l->t("Human verification failed");
        } elseif ($sts === '4') {
            $param['input_err'] = $this->l->t("Internal server error: validation request failed");
        } elseif ($sts === '5') {
            $param['input_err'] = $this->l->t('We regret to inform you that your email address has been blocked due to activity that violates our community guidelines.');
        }

        if ($render === "public") {
            $tr = $this->getPublicTemplate($tmpl);
        } else {
            $tr = new TemplateResponse($this->appName, $tmpl, [], $render);
        }

        $param['appt_inline_style'] = $this->utils->getInlineStyle($uid, $settings);

        $tr->setParams($param);
        $tr->setStatus($rs);
        return $tr;
    }

    private function showForm(string $render, string $uid, string $pageId): Response
    {
        $templateName = 'public/form';
        if ($render === "public") {
            $tr = $this->getPublicTemplate($templateName);
        } else {
            $tr = new TemplateResponse($this->appName, $templateName, [], $render);
        }

        $settings = $this->utils->getUserSettings();

        $ft = $settings[BackendUtils::PSN_FORM_TITLE];
        $org_name = $settings[BackendUtils::ORG_NAME];
        $addr = $settings[BackendUtils::ORG_ADDR];

        if (empty($org_name)) {
            $org_name = $this->l->t('Organization Name');
        }
        $addr = trim($addr);
        if (!empty($addr) and filter_var($addr, FILTER_VALIDATE_URL) !== false) {
            $addr = $this->l->t("Online Meeting");
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
            'appt_inline_style' => $this->utils->getInlineStyle($uid, $settings),
            'appt_hide_phone' => $settings[BackendUtils::PSN_HIDE_TEL],
            'more_html' => '',
            'application' => $this->l->t('Appointments'),
            'translations' => '',
            'hCapKey' => '',
            'zones_file'=>'',
        ];

        if ($settings[BackendUtils::SEC_HCAP_ENABLED] === true
            && !empty($settings[BackendUtils::SEC_HCAP_SECRET])
            && !empty($settings[BackendUtils::SEC_HCAP_SITE_KEY])
        ) {
            Util::addHeader("script", [
                'src' => 'https://www.hCaptcha.com/1/api.js',
                'async' => '',
                'nonce' => \OC::$server->get(ContentSecurityPolicyNonceManager::class)->getNonce()
            ], '');
            $csp = $tr->getContentSecurityPolicy();
            $csp->addAllowedScriptDomain('https://hcaptcha.com/');
            $csp->addAllowedScriptDomain('https://*.hcaptcha.com/');
            $csp->addAllowedFrameDomain('https://hcaptcha.com/');
            $csp->addAllowedFrameDomain('https://*.hcaptcha.com/');
            $csp->addAllowedStyleDomain('https://hcaptcha.com/');
            $csp->addAllowedStyleDomain('https://*.hcaptcha.com/');
            $csp->addAllowedConnectDomain('https://hcaptcha.com/');
            $csp->addAllowedConnectDomain('https://*.hcaptcha.com/');
            $tr->setContentSecurityPolicy($csp);
            $params['hCapKey'] = $settings[BackendUtils::SEC_HCAP_SITE_KEY];
        }

        // google recaptcha
        // 'jsfiles'=>['https://www.google.com/recaptcha/api.js']
        //        $tr->getContentSecurityPolicy()->addAllowedScriptDomain('https://www.google.com/recaptcha/')->addAllowedScriptDomain('https://www.gstatic.com/recaptcha/')->addAllowedFrameDomain('https://www.google.com/recaptcha/');

        if (!$settings[BackendUtils::PAGE_ENABLED]) {
            $params['appt_state'] = '4';
            $tr->setParams($params);
            return $tr;
        }

        if (empty($org_name) || empty($settings[BackendUtils::ORG_EMAIL])) {
            $params['appt_state'] = '7';
            $tr->setParams($params);
            return $tr;
        }

        $calId = $this->utils->getMainCalId($uid, $this->bc);
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

        $nw = intval($settings[BackendUtils::PSN_NWEEKS]);

        $utz = $this->utils->getCalendarTimezone($uid, $this->bc->getCalendarById($calId, $uid));
        try {
            $t_start = new \DateTime('now +' . $settings[BackendUtils::CLS_PREP_TIME] . "mins", $utz);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage() . ", timezone: " . $utz->getName());
            $params['appt_state'] = '6';
            $tr->setParams($params);
            return $tr;
        }

        $ts_mode = $settings[BackendUtils::CLS_TS_MODE];

        $t_end = clone $t_start;
        $t_end->setTimestamp($t_start->getTimestamp() + (7 * $nw * 86400));
        $t_end->setTime(0, 0);

        if ($ts_mode === BackendUtils::CLS_TS_MODE_EXTERNAL) {
            // @see BCSabreImpl->queryRange()
            $calId .= chr(31) . $settings[BackendUtils::CLS_XTM_SRC_ID];
        }

        if ($ts_mode === BackendUtils::CLS_TS_MODE_TEMPLATE) {
            $out = $this->bc->queryTemplate($settings, $t_start, $t_end, $uid, $pageId);
        } else {
            $out = $this->bc->queryRange($calId, $t_start, $t_end, $ts_mode . $uid, $pageId);
        }

        if (empty($out)) {
            $params['appt_state'] = '5';
        }

        $params['appt_sel_opts'] = $out;

        $params['appt_pps'] =
            BackendUtils::PSN_NWEEKS . ":" . $settings[BackendUtils::PSN_NWEEKS] . '.' .
            BackendUtils::PSN_EMPTY . ":" . ($settings[BackendUtils::PSN_EMPTY] ? "1" : "0") . '.' .
            BackendUtils::PSN_FNED . ":" . ($settings[BackendUtils::PSN_FNED] ? "1" : "0") . '.' .
            BackendUtils::PSN_WEEKEND . ":" . ($settings[BackendUtils::PSN_WEEKEND] ? "1" : "0") . '.' .
            BackendUtils::PSN_SHOW_TZ . ":" . ($settings[BackendUtils::PSN_SHOW_TZ] ? "1" : "0") . '.' .
            BackendUtils::PSN_TIME2 . ":" . ($settings[BackendUtils::PSN_TIME2] ? "1" : "0") . '.' .
            BackendUtils::PSN_END_TIME . ":" . ($settings[BackendUtils::PSN_END_TIME] ? "1" : "0") . '.' .
            BackendUtils::PSN_PREFILL_INPUTS . ":" . $settings[BackendUtils::PSN_PREFILL_INPUTS] . '.' .
            BackendUtils::PSN_PREFILLED_TYPE . ":" . $settings[BackendUtils::PSN_PREFILLED_TYPE];

        // GDPR
        $params['appt_gdpr'] = $settings[BackendUtils::PSN_GDPR];
        $params['appt_gdpr_no_chb'] = $settings[BackendUtils::PSN_GDPR_NO_CHB];

        $video_label = '';
        $video_placeholder = '';
        $video_no = '';
        $video_yes = '';

        if ($settings[BackendUtils::TALK_ENABLED] === true) {
            if ($settings[BackendUtils::TALK_FORM_ENABLED] === true
                && !empty($this->c->getUserValue($uid, $this->appName, chr(99) . "n" . 'k'))
            ) {
                $video_label = !empty($settings[BackendUtils::TALK_FORM_LABEL])
                    ? $settings[BackendUtils::TALK_FORM_LABEL]
                    : $settings[BackendUtils::TALK_FORM_DEF_LABEL];
                $video_placeholder = !empty($settings[BackendUtils::TALK_FORM_PLACEHOLDER])
                    ? $settings[BackendUtils::TALK_FORM_PLACEHOLDER]
                    : $settings[BackendUtils::TALK_FORM_DEF_PLACEHOLDER];
                $video_no = !empty($settings[BackendUtils::TALK_FORM_REAL_TXT])
                    ? $settings[BackendUtils::TALK_FORM_REAL_TXT]
                    : $settings[BackendUtils::TALK_FORM_DEF_REAL];
                $video_yes = !empty($settings[BackendUtils::TALK_FORM_VIRTUAL_TXT]) ? $settings[BackendUtils::TALK_FORM_VIRTUAL_TXT] : $settings[BackendUtils::TALK_FORM_DEF_VIRTUAL];
            }
        } elseif ($settings[BackendUtils::BBB_ENABLED] === true
            && $settings[BackendUtils::BBB_FORM_ENABLED] === true
        ) {
            $video_label = $this->l->t('Meeting Type');
            $video_placeholder = $this->l->t('Select meeting type');
            $video_no = $this->l->t('In-person meeting');
            $video_yes = $this->l->t('Online (audio/video)');
        }
        if ($video_label !== '') {
            // we have a meeting type <select>

            $params['appt_tlk_type'] = '<label for="srgdev-ncfp_talk_type" class="srgdev-ncfp-form-label">'
                . htmlspecialchars(strip_tags(($video_label)), ENT_NOQUOTES)
                . '</label>
<select name="talk_type" required id="srgdev-ncfp_talk_type" class="srgdev-ncfp-form-input srgdev-ncfp-form-select">
    <option value="" disabled selected hidden>'
                . htmlspecialchars(strip_tags(($video_placeholder)), ENT_NOQUOTES)
                . '</option>
    <option class="srgdev-ncfp-form-option" id="srgdev-ncfp_talk_type_op1" style="font-size: medium" value="0">'
                . htmlspecialchars(strip_tags(($video_no)), ENT_NOQUOTES)
                . '</option>
    <option class="srgdev-ncfp-form-option" id="srgdev-ncfp_talk_type_op2" style="font-size: medium" value="1">'
                . htmlspecialchars(strip_tags(($video_yes)), ENT_NOQUOTES)
                . '</option>
</select>';
        }

        if (!empty($settings[BackendUtils::KEY_FORM_INPUTS_HTML])) {
            $params['more_html'] = $settings[BackendUtils::KEY_FORM_INPUTS_HTML];
        }

        // translations (because we do not have window.t without vue in form.js)
        $params['translations'] =
            "name_required:" . $this->l->t('Name is required.') . "," .
            "email_required:" . $this->l->t('Email is required.') . "," .
            "phone_required:" . $this->l->t('Phone number is required.') . "," .
            "required:" . $this->l->t('Required.') . "," .
            "number_required:" . $this->l->t('Number required.');

        $params['zones_file'] = $this->urlGenerator->linkTo(Application::APP_ID, 'ajax/zones.js');

        $tr->setParams($params);

        if (($r_url = trim($settings[BackendUtils::ORG_CONFIRMED_RDR_URL])) !== "") {
            // we need to adjust CSP if redirecting to a different domain
            $parsedRdrUrl = parse_url($r_url);
            if (is_array($parsedRdrUrl) && $parsedRdrUrl['host']) {

                $urlGenerator = \OC::$server->get(\OCP\IURLGenerator::class);

                $parsedSrvUrl = parse_url($urlGenerator->getAbsoluteURL('/'));
                if (is_array($parsedSrvUrl) && $parsedSrvUrl['host']) {
                    if ($parsedRdrUrl['host'] !== $parsedSrvUrl['host']) {
                        $csp = $tr->getContentSecurityPolicy();
                        $csp->addAllowedFormActionDomain($parsedRdrUrl['host']);
                        $tr->setContentSecurityPolicy($csp);
                    }
                }
            }
        }

        //$tr->getContentSecurityPolicy()->addAllowedFrameAncestorDomain('\'self\'');
        return $tr;
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function caladd(): NotFoundResponse|string
    {
        $pageId = $this->request->getParam("p");

        if (empty($pageId) || empty($this->userId) || !$this->utils->loadSettingsForUserAndPage($this->userId, $pageId)) {
            return new NotFoundResponse();
        }

        return $this->addAppointments(
            $this->userId,
            $this->request->getParam("d"),
            $this->request->getParam("tz")
        );
    }

    /**
     * @param string|null $ds
     *      dtstamp,dtstart,dtend [,dtstart,dtend,...] -
     *      dttsamp: 20200414T073008Z must be UTC (ends with Z),
     *      dtstart/dtend: 20200414T073008
     * @param string $tz_data_str Can be VTIMEZONE data or 'UTC'
     * @param string $title title is used when the appointment is being reset
     */
    private function addAppointments(string $userId, string|null $ds, string $tz_data_str, string $title = ""): string
    {

        if (empty($ds)) {
            return '1:No Data';
        }
        $data = explode(',', $ds);
        $c = count($data);
        if ($c < 3) {
            return '1:' . $this->l->t("Please add time slots first.") . " [DL = " . $c . "]";
        }

        $cal_id = $this->utils->getMainCalId($userId, $this->bc);
        if ($cal_id === "-1") {
            return '1:' . $this->l->t("Please select a calendar first");
        }

        $cal = $this->bc->getCalendarById($cal_id, $userId);
        if ($cal === null) {
            return '1:' . $this->l->t("Selected calendar not found");
        }

        $evt_parts = $this->utils->makeAppointmentParts(
            $userId, $tz_data_str, $data[0], $title);
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

    private function getPublicTemplate(string $templateName): PublicTemplateResponse
    {
        $settings = $this->utils->getUserSettings();
        $tr = new PublicTemplateResponse($this->appName, $templateName, []);
        if (!empty($settings[BackendUtils::PSN_PAGE_TITLE])) {
            $tr->setHeaderTitle($settings[BackendUtils::PSN_PAGE_TITLE]);
        } else {
            $defaults = new \OCP\Defaults();
            $tr->setHeaderTitle($defaults->getProductName() . " | " . $this->l->t("Appointments"));
        }
        if (!empty($settings[BackendUtils::PSN_PAGE_SUB_TITLE])) {
            $tr->setHeaderDetails($settings[BackendUtils::PSN_PAGE_SUB_TITLE]);
        }
        $tr->setFooterVisible(false);

        if ($settings[BackendUtils::PSN_META_NO_INDEX] === true) {
            // https://support.google.com/webmasters/answer/93710?hl=en
            Util::addHeader("meta", ['name' => 'robots', 'content' => 'noindex']);
        }

        $tr->addHeader('X-Appointments', 'yes');

        return $tr;
    }

    /**
     * @throws NotLoggedInException
     */
    private function throwIfPrivateModeNotLoggedIn(): void
    {
        if ($this->utils->getUserSettings()[BackendUtils::CLS_PRIVATE_PAGE]
            && !$this->userSession->isLoggedIn()) {
            throw new NotLoggedInException();
        }
    }

    /**
     * @param array $post
     * @return int
     *  0 = OK,
     *  1 = captcha error,
     *  2 = internal error
     */
    private function validateHCaptcha(array $post, array $settings): int
    {
        if (empty($post['h-captcha-response'])) {
            return 1;
        }

        $clientService = \OC::$server->get(IClientService::class);
        $client = $clientService->newClient();

        try {
            $res = $client->post('https://api.hcaptcha.com/siteverify', [
                    'form_params' => [
                        'response' => $post['h-captcha-response'],
                        'secret' => $this->utils->decrypt(substr($settings[BackendUtils::SEC_HCAP_SECRET], 8), $this->utils->getLocalHash()),
                        'sitekey' => $settings[BackendUtils::SEC_HCAP_SITE_KEY]
                    ]]
            );

            $body = json_decode($res->getBody(), true);
            if ($body === null) {
                throw new \Exception("cannot parse response");
            }
        } catch (\Throwable $e) {
            $this->logger->error("hCaptcha post error: ", [
                'app' => Application::APP_ID,
                'exception' => $e
            ]);
            return 2;
        }

        if ($body['success'] === true) {
            return 0;
        }

        if (isset($body['error-codes']) && count(array_intersect([
                'missing-input-secret',
                'invalid-input-secret',
                'sitekey-secret-mismatch'
            ], $body['error-codes'])) !== 0) {

            $this->logger->error("hCaptcha internal error: " . var_export($body['error-codes'], true), [
                'app' => Application::APP_ID
            ]);
            return 2;
        }
        return 1;
    }
}
