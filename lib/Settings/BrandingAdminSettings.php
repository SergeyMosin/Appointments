<?php

namespace OCA\Appointments\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class BrandingAdminSettings implements ISettings
{

    public function getForm(): TemplateResponse
    {
        return new TemplateResponse('appointments', 'admin_branding', [], 'blank');
    }

    public function getSection(): string
    {
        return 'additional';
    }

    public function getPriority(): int
    {
        return 50;
    }
}
