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
        list($userId, $pageId) = $this->utils->verifyToken(
            $this->request->getParam("token"), $this->config);
        if ($userId === null) {
            return new NotFoundResponse();
        }
        return $this->showIndex($userId, $pageId, true);
    }

    /**
     * @NoAdminRequired
     * @PublicPage
     * @NoCSRFRequired
     * @throws \ErrorException
     */
    function indexV1()
    {
        list($userId, $pageId) = $this->utils->verifyToken(
            $this->request->getParam("token") . "dir", $this->config);
        if ($userId === null) {
            return new NotFoundResponse();
        }
        return $this->showIndex($userId, $pageId, true);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @throws \ErrorException
     */
    function indexBase()
    {
        $pageId = $this->request->getParam("p", "d0");
        if (empty($pageId)) {
            $pageId = 'd0';
        }
        return $this->showIndex($this->userId, $pageId, false);
    }


    private function showIndex(string $userId, string $pageId, bool $isPublic)
    {

        if (!$this->utils->loadSettingsForUserAndPage($userId, $pageId)) {
            return new NotFoundResponse();
        }

        $settings = $this->utils->getUserSettings();
        if ($isPublic) {
//            Util::addStyle($this->appName, "form-xl-screen");
            $s = $this->config->getUserValue($userId, $this->appName, "cn" . "k");
            $f = "hex" . "dec";
            $tr = new PublicTemplateResponse($this->appName, 'public/directory' . (($s === "" || (($f(substr($s, 0, 0b100)) >> 0xf) & 1) !== (($f(substr($s, 0b100, 4)) >> 12) & 1)) ? "_" : ""), []);
            if (!empty($settings[BackendUtils::PSN_PAGE_TITLE])) {
                $tr->setHeaderTitle($settings[BackendUtils::PSN_PAGE_TITLE]);
            } else {
                $theme = new \OCP\Defaults();
                // TRANSLATORS %s is the server name. Example: Private Cloud Appointments Directory
                $tr->setHeaderTitle($this->l->t("%s Appointments Directory", [$theme->getEntity()]));
            }
            $tr->setFooterVisible(false);
        } else {
            $tr = new TemplateResponse($this->appName, 'public/directory', [], 'base');
        }

        $tr->setParams([
            'links' => $settings[BackendUtils::DIR_ITEMS],
            'application' => $this->l->t('Appointments'),
            'appt_inline_style' => $this->utils->getInlineStyle($userId, $settings)
        ]);

        return $tr;
    }
}