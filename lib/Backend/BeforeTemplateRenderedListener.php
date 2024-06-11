<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class BeforeTemplateRenderedListener implements IEventListener
{
    public function handle(Event $event): void
    {
        if (!($event instanceof BeforeTemplateRenderedEvent)) {
            return;
        }
        if ($event->isLoggedIn() && $event->getResponse()->getRenderAs() === TemplateResponse::RENDER_AS_USER) {

            try {
                $config = \OC::$server->get(IConfig::class);
                $allowedGroups = $config->getAppValue(Application::APP_ID,
                    BackendUtils::KEY_LIMIT_TO_GROUPS);

                if (!empty($allowedGroups)) {
                    $aga = json_decode($allowedGroups, true);
                    if ($aga !== null) {
                        $user = \OC::$server->get(IUserSession::class)->getUser();
                        if (!empty($user)) {
                            $userGroups = \OC::$server->get(IGroupManager::class)->getUserGroups($user);
                            $disable = true;
                            foreach ($aga as $ag) {
                                if (array_key_exists($ag, $userGroups)) {
                                    $disable = false;
                                    break;
                                }
                            }
                            if ($disable) {
                                \OC_Util::addStyle(Application::APP_ID, 'hide-app');
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \OC::$server->get(LoggerInterface::class)->error('error: cannot hide appointments app icon', [
                    'app' => Application::APP_ID,
                    'exception' => $e,
                ]);
            }
        }
    }
}