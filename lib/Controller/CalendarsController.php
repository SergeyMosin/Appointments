<?php /** @noinspection PhpUnused */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpComposerExtensionStubsInspection */


namespace OCA\Appointments\Controller;


use OCA\Appointments\Backend\BackendManager;
use OCA\Appointments\Backend\BackendUtils;
use OCA\Appointments\SendDataResponse;
use OCP\AppFramework\Controller;
use OCP\IRequest;

class CalendarsController extends Controller
{

    private $userId;
    /** @var \OCA\Appointments\Backend\IBackendConnector $bc */
    private $bc;

    public function __construct($AppName,
                                IRequest $request,
        $UserId,
                                BackendManager $backendManager) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->bc = $backendManager->getConnector();
    }

    /**
     * @NoAdminRequired
     * @noinspection PhpUnused
     */
    public function callist() {
        $isTemplateMode = $this->request->getParam("mode", "") === BackendUtils::CLS_TS_MODE_TEMPLATE;
        $cals = $this->bc->getCalendarsForUser($this->userId, !$isTemplateMode);
        $out = '';
        $c30 = chr(30);
        $c31 = chr(31);
        foreach ($cals as $c) {
            $out .=
                $c['displayName'] . $c30 .
                $c['color'] . $c30 .
                $c['id'] . $c30 .
                $c['isReadOnly'] . $c30 .
                '0' . $c31; // isSubscription;
        }
        if($isTemplateMode) {
            // Subscriptions are only for template mode
            $sa = $this->bc->getSubscriptionsForUser($this->userId);
            foreach ($sa as $s) {
                $out .=
                    $s['displayName'] . $c30 .
                    '#000000' . $c30 .
                    $s['id'] . $c30 .
                    '1' . $c30 . // isReadOnly
                    '1' . $c31; // isSubscription
            }
        }

        return substr($out, 0, -1);
    }

}