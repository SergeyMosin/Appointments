<?php

namespace OCA\Appointments\AppInfo;


use OCA\Appointments\Backend\DavListener;
use OCP\AppFramework\App;

class Application extends App {

    const APP_ID='appointments';
    public function __construct(){
        parent::__construct(self::APP_ID);
    }

    public function registerHooks(){

        try {
            $listener=new DavListener();
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error("Can't Init DavListener");
            \OC::$server->getLogger()->error($e->getMessage());
            return;
        }

        $dispatcher = $this->getContainer()->getServer()->getEventDispatcher();

        $dispatcher->addListener(
            '\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject',
            [$listener,'handle']);
        $dispatcher->addListener(
            DavListener::DEL_EVT_NAME,
            [$listener,'handle']);
    }

}