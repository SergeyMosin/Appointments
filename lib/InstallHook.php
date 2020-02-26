<?php
namespace OCA\Appointments;
use OCA\Appointments\Controller\PageController;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IConfig;
use OCP\IUserManager;

class InstallHook{
    /**
     * InstallHook constructor.
     * @param $AppName
     * @param $UserId
     * @param IConfig $config
     * @param IAccountManager $am
     * @param IUserManager $um
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct($AppName,$UserId,IConfig $config,IAccountManager $am,IUserManager $um){

        if(empty($config->getAppValue($AppName,'hk'))){
            $config->setAppValue($AppName, 'hk',
                bin2hex(openssl_random_pseudo_bytes(32, $is_good)));
        }
        if(empty($config->getAppValue($AppName,'tiv'))) {
            $config->setAppValue($AppName, 'tiv',
                bin2hex(openssl_random_pseudo_bytes(
                    openssl_cipher_iv_length(PageController::CIPHER),
                    $is_good)));
        }

        $u=$um->get($UserId);

        if(empty($config->getUserValue($UserId,$AppName,PageController::KEY_O_PHONE))){
            try {
                $a = $am->getAccount($u);
                $ap = $a->getProperty(IAccountManager::PROPERTY_PHONE);
                $phone = $ap->getValue();
            } catch (PropertyDoesNotExistException $e) {
                $phone = '';
            }
            $config->setUserValue(
                $UserId,
                $AppName,
                PageController::KEY_O_PHONE,
                $phone);
        }

        if(empty($config->getUserValue($UserId,$AppName,PageController::KEY_O_EMAIL))) {
            $email = $u->getEMailAddress();
            $config->setUserValue(
                $UserId,
                $AppName,
                PageController::KEY_O_EMAIL,
                empty($email) ? '' : $email);
        }
    }
}