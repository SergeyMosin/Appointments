<?php
namespace OCA\Appointments;

use OCA\Appointments\Controller\PageController;
use Sabre\VObject\Component\VEvent;

class UtilsAppt{
    /**
     * @param VEvent $vevent
     * @return bool
     */
    public static function isAppointment($vevent){
        return !isset($vevent->CATEGORIES) || $vevent->CATEGORIES->getValue() !== PageController::APP_CAT ? false : true;
    }
}