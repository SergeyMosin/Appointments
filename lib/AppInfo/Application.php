<?php

namespace OCA\Appointments\AppInfo;

use OCA\Appointments\Backend\BeforeTemplateRenderedListener;
use OCA\Appointments\Backend\DavListener;
use OCA\Appointments\Backend\RemoveScriptsMiddleware;
use OCA\DAV\Events\CalendarObjectMovedToTrashEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\SubscriptionDeletedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

class Application extends App implements IBootstrap
{

    const APP_ID = 'appointments';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerEventListener(CalendarObjectUpdatedEvent::class, DavListener::class);
        $context->registerEventListener(CalendarObjectMovedToTrashEvent::class, DavListener::class);
        $context->registerEventListener(SubscriptionDeletedEvent::class, DavListener::class);

        $context->registerService('ApptRemoveScriptsMiddleware', function ($c) {
            return new RemoveScriptsMiddleware();
        });
        $context->registerMiddleware('ApptRemoveScriptsMiddleware');

        $context->registerEventListener(BeforeTemplateRenderedEvent::class, BeforeTemplateRenderedListener::class);
    }

    public function boot(IBootContext $context): void
    {
//        $appContainer = $context->getAppContainer();
//        $serverContainer = $context->getServerContainer();
    }
}