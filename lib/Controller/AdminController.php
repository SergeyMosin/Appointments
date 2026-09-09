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
    private IUserSession $userSession;
    private IGroupManager $groupManager;

    public function __construct(string       $AppName,
                                IRequest     $request,
                                BackendUtils $utils,
                                IUserSession $userSession,
                                IGroupManager $groupManager)
    {
        parent::__construct($AppName, $request);
        $this->utils = $utils;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    private function isAdmin(): bool
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }
        return $this->groupManager->isAdmin($user->getUID());
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getBrands(): DataResponse
    {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }
        return new DataResponse($this->utils->getBrands());
    }

    /**
     * @NoAdminRequired
     */
    public function saveBrand(): DataResponse
    {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

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

    /**
     * @NoAdminRequired
     */
    public function deleteBrand(): DataResponse
    {
        if (!$this->isAdmin()) {
            return new DataResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN);
        }

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
