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
     */
    public function __construct($AppName,$UserId,IConfig $config,IAccountManager $am,IUserManager $um){



        // TODO: check if the value already set, othervice update breaks everything ...



        $config->setAppValue($AppName, 'hk',
            bin2hex(openssl_random_pseudo_bytes(32,$is_good)));
        $config->setAppValue($AppName, 'tiv',
            bin2hex(openssl_random_pseudo_bytes(
                openssl_cipher_iv_length(PageController::CIPHER),
                $is_good)));




        $u=$um->get($UserId);
        try {
            $a=$am->getAccount($um->get($UserId));
            $ap=$a->getProperty(IAccountManager::PROPERTY_PHONE);
            $phone=$ap->getValue();
        } catch (PropertyDoesNotExistException $e) {
            $phone='';
        }

        $email=$u->getEMailAddress();


        $config->setUserValue(
            $UserId,
            $AppName,
            'phone',
            empty($phone)?'':$phone);
        $config->setUserValue(
            $UserId,
            $AppName,
            'email',
            empty($email)?'':$email);
    }
}