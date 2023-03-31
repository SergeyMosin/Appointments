<?php

namespace OCA\Appointments\Migration;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Backend\BackendUtils;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallHook implements IRepairStep
{

    private IConfig $config;

    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'Install hook for Appointments app';
    }

    /**
     * @inheritDoc
     */
    public function run(IOutput $output)
    {
        try {
            if (empty($this->config->getAppValue(Application::APP_ID, 'hk'))) {
                $this->config->setAppValue(Application::APP_ID, 'hk',
                    bin2hex(openssl_random_pseudo_bytes(32, $is_good)));
            }
            if (empty($this->config->getAppValue(Application::APP_ID, 'tiv'))) {
                $this->config->setAppValue(Application::APP_ID, 'tiv',
                    bin2hex(openssl_random_pseudo_bytes(
                        openssl_cipher_iv_length(BackendUtils::CIPHER),
                        $is_good)));
            }
        } catch (\Throwable $e) {
            $output->warning("error: " . $e->getMessage());
            throw $e;
        }
        $output->info("appointments InstallHook finished");
    }
}