<?php

namespace OCA\Appointments\AppInfo;


use OC_Util;
use OCA\Appointments\Backend\DavListener;
use OCA\DAV\Events\CalendarObjectMovedToTrashEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\SubscriptionDeletedEvent;
use OCA\DAV\HookManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Application extends App implements IBootstrap
{

    const APP_ID = 'appointments';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // gte NC22
        if (OC_Util::getVersion()[0] >= 22) {
            $context->registerEventListener(CalendarObjectUpdatedEvent::class, DavListener::class);
            $context->registerEventListener(CalendarObjectMovedToTrashEvent::class, DavListener::class);
            $context->registerEventListener(SubscriptionDeletedEvent::class, DavListener::class);
        }
    }

    public function boot(IBootContext $context): void {
        // NC21
        if (OC_Util::getVersion()[0] < 22) {
            $context->injectFn([$this, 'registerHooks']);
        }
    }

    public function registerHooks(HookManager              $hm,
                                  EventDispatcherInterface $dispatcher,
                                  IAppContainer            $container,
                                  IServerContainer         $serverContainer) {


        try {
            $l10n = $container->query(\OCP\IL10N::class);
            $logger = $container->query(LoggerInterface::class);
            $utils = $container->query(\OCA\Appointments\Backend\BackendUtils::class);
            $listener = new DavListener($l10n, $logger, $utils);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error("Can't Init DavListener");
            \OC::$server->getLogger()->error($e->getMessage());
            return;
        }

        $dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject', [$listener, 'handleOld']);
        $dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject', [$listener, 'handleOld']);
    }

}