<?php

namespace OCA\Appointments\Controller;

use OCA\Appointments\Backend\BackendUtils;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class AdminController extends Controller
{

    private BackendUtils $utils;
    private IGroupManager $groupManager;
    private IUserSession $userSession;

    public function __construct(string        $AppName,
                                IRequest      $request,
                                BackendUtils  $utils,
                                IGroupManager $groupManager,
                                IUserSession  $userSession)
    {
        parent::__construct($AppName, $request);
        $this->utils = $utils;
        $this->groupManager = $groupManager;
        $this->userSession = $userSession;
    }

    /**
     * Admins get the full brands; other users only id and name, which is all
     * the page settings dropdown needs. Email templates and custom CSS/JS
     * stay admin-only.
     *
     * @NoAdminRequired
     */
    public function getBrands(): DataResponse
    {
        $brands = $this->utils->getBrands();
        $user = $this->userSession->getUser();
        if ($user === null || !$this->groupManager->isAdmin($user->getUID())) {
            $brands = array_map(fn($b) => [
                BackendUtils::BRAND_ID => $b[BackendUtils::BRAND_ID] ?? '',
                BackendUtils::BRAND_NAME => $b[BackendUtils::BRAND_NAME] ?? '',
            ], $brands);
        }
        return new DataResponse($brands);
    }

    public function saveBrand(): DataResponse
    {
        $id = trim($this->request->getParam('id', ''));
        $name = trim($this->request->getParam('name', ''));

        if (empty($name)) {
            return new DataResponse(['error' => 'Brand name is required'], Http::STATUS_BAD_REQUEST);
        }

        $brand = [
            BackendUtils::BRAND_ID => empty($id) ? uniqid('brand_') : $id,
            BackendUtils::BRAND_NAME => $name,
            BackendUtils::BRAND_LOGO_URL => trim($this->request->getParam('logoUrl', '')),
            BackendUtils::BRAND_FAVICON_URL => trim($this->request->getParam('faviconUrl', '')),
            BackendUtils::BRAND_BG_IMAGE => trim($this->request->getParam('bgImage', '')),
            BackendUtils::BRAND_PRIMARY_COLOR => trim($this->request->getParam('primaryColor', '')),
            BackendUtils::BRAND_EMAIL_TEMPLATE => $this->request->getParam('emailTemplate', ''),
            BackendUtils::BRAND_CUSTOM_CSS => $this->request->getParam('customCss', ''),
            BackendUtils::BRAND_CUSTOM_JS => $this->request->getParam('customJs', ''),
        ];

        if ($brand[BackendUtils::BRAND_PRIMARY_COLOR] !== ''
            && !preg_match('/^#[0-9a-fA-F]{6}$/', $brand[BackendUtils::BRAND_PRIMARY_COLOR])) {
            return new DataResponse(['error' => 'Primary color must be #rrggbb'], Http::STATUS_BAD_REQUEST);
        }
        foreach ([BackendUtils::BRAND_LOGO_URL, BackendUtils::BRAND_FAVICON_URL, BackendUtils::BRAND_BG_IMAGE] as $k) {
            if ($brand[$k] !== '' && !preg_match('#^(https?://|/)#i', $brand[$k])) {
                return new DataResponse(['error' => $k . ' must be an http(s) URL or start with /'], Http::STATUS_BAD_REQUEST);
            }
        }

        $brands = $this->utils->getBrands();
        $found = false;
        foreach ($brands as &$existing) {
            if ($existing[BackendUtils::BRAND_ID] === $brand[BackendUtils::BRAND_ID]) {
                $existing = $brand;
                $found = true;
                break;
            }
        }
        unset($existing);

        if (!$found) {
            $brands[] = $brand;
        }

        $this->utils->saveBrands($brands);
        return new DataResponse($brand);
    }

    public function deleteBrand(): DataResponse
    {
        $id = trim($this->request->getParam('id', ''));
        if (empty($id)) {
            return new DataResponse(['error' => 'Brand id is required'], Http::STATUS_BAD_REQUEST);
        }

        $brands = array_values(array_filter(
            $this->utils->getBrands(),
            fn($b) => ($b[BackendUtils::BRAND_ID] ?? '') !== $id
        ));

        $this->utils->saveBrands($brands);
        return new DataResponse(['deleted' => $id]);
    }
}
