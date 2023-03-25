<?php


namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendUtils;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\Util;

class DirController extends Controller
{
    private $userId;
    private $config;
    private $utils;
    private $l;

    public function __construct($AppName, $UserId,
                                IRequest $request,
                                IConfig $config,
                                IL10N $l,
                                BackendUtils $utils)
    {
        parent::__construct($AppName, $request);

        $this->userId = $UserId;
        $this->config = $config;
        $this->l = $l;
        $this->utils = $utils;
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws \ErrorException
     */
    function index()
    {
        list($userId) = $this->utils->verifyToken(
            $this->request->getParam("token"), $this->config);
        if ($userId === null) {
            return new NotFoundResponse();
        }
        return $this->showIndex($userId, true);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @throws \ErrorException
     */
    function indexBase()
    {
        return $this->showIndex($this->userId, false);
    }

    function showIndex($userId, $isPublic)
    {
        if ($isPublic) {
//            Util::addStyle($this->appName, "form-xl-screen");
            $pps = $this->utils->getUserSettings(BackendUtils::KEY_PSN, $userId);
            $s = $this->config->getUserValue($userId, $this->appName, "cn" . "k");
            $f = "hex" . "dec";
            $tr = new PublicTemplateResponse($this->appName, 'public/directory' . (($s === "" || (($f(substr($s, 0, 0b100)) >> 0xf) & 1) !== (($f(substr($s, 0b100, 4)) >> 12) & 1)) ? "_" : ""), []);
            if (!empty($pps[BackendUtils::PSN_PAGE_TITLE])) {
                $tr->setHeaderTitle($pps[BackendUtils::PSN_PAGE_TITLE]);
            } else {
                $tr->setHeaderTitle("Nextcloud | Appointments Directory");
            }
            if (!empty($pps[BackendUtils::PSN_PAGE_SUB_TITLE])) {
                $tr->setHeaderDetails($pps[BackendUtils::PSN_PAGE_SUB_TITLE]);
            }
            $tr->setFooterVisible(false);
        } else {
            $tr = new TemplateResponse($this->appName, 'public/directory', [], 'base');
        }

        $pps = $this->utils->getUserSettings(
            BackendUtils::KEY_PSN, $userId);

        $tr->setParams([
            'links' => $this->utils->getUserSettings(
                BackendUtils::KEY_DIR, $userId),
            'application' => $this->l->t('Appointments'),
            'appt_inline_style' => $this->utils->getInlineStyle($userId, $pps, $this->config)
        ]);

        return $tr;
    }
}