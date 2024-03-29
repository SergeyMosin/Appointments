<?php

namespace OCA\Appointments\AppInfo;

use OCA\Appointments\Backend\DavListener;
use OCA\Appointments\Backend\RemoveScriptsMiddleware;
use OCA\DAV\Events\CalendarObjectMovedToTrashEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\SubscriptionDeletedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{

    const APP_ID = 'appointments';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(CalendarObjectUpdatedEvent::class, DavListener::class);
        $context->registerEventListener(CalendarObjectMovedToTrashEvent::class, DavListener::class);
        $context->registerEventListener(SubscriptionDeletedEvent::class, DavListener::class);

        $context->registerService('ApptRemoveScriptsMiddleware', function ($c) {
            return new RemoveScriptsMiddleware();
        });
        $context->registerMiddleware('ApptRemoveScriptsMiddleware');
    }

    public function boot(IBootContext $context): void {
    }
}