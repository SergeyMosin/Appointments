<?php
namespace OCA\Appointments;
use OCA\Appointments\Controller\PageController;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;


class InstallHook implements IRepairStep{

    private $config;
    private $am;
    private $um;
    private $appName;
    private $userId;


    /**
     * InstallHook constructor.
     * @param $AppName
     * @param $UserId
     * @param IConfig $config
     * @param IAccountManager $am
     * @param IUserManager $um
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct($AppName,
                                $UserId,
                                IConfig $config,
                                IAccountManager $am,
                                IUserManager $um){
        $this->config=$config;
        $this->am=$am;
        $this->um=$um;
        $this->appName=$AppName;
        $this->userId=$UserId;
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
        if(empty($this->config->getAppValue($this->appName,'hk'))){
            $this->config->setAppValue($this->appName, 'hk',
                bin2hex(openssl_random_pseudo_bytes(32, $is_good)));
        }
        if(empty($this->config->getAppValue($this->appName,'tiv'))) {
            $this->config->setAppValue($this->appName, 'tiv',
                bin2hex(openssl_random_pseudo_bytes(
                    openssl_cipher_iv_length(PageController::CIPHER),
                    $is_good)));
        }


        $u=$this->um->get($this->userId);
        if($u!==null) {
            if (empty($this->config->getUserValue(
                $this->userId,
                $this->appName,
                PageController::KEY_O_PHONE))) {
                try {
                    $a = $this->am->getAccount($u);
                    $ap = $a->getProperty(IAccountManager::PROPERTY_PHONE);
                    $phone = $ap->getValue();
                } catch (PropertyDoesNotExistException $e) {
                    $phone = '';
                }
                $this->config->setUserValue(
                    $this->userId,
                    $this->appName,
                    PageController::KEY_O_PHONE,
                    $phone);
            }

            if (empty($this->config->getUserValue(
                $this->userId,
                $this->appName,
                PageController::KEY_O_EMAIL))) {
                $email = $u->getEMailAddress();
                $this->config->setUserValue(
                    $this->userId,
                    $this->appName,
                    PageController::KEY_O_EMAIL,
                    empty($email) ? '' : $email);
            }
        }


        $output->info("Appointments install hook finished");
    }
}