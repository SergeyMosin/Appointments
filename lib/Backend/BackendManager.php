<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */


namespace OCA\Appointments\Backend;


use OCA\Appointments\AppInfo\Application;
use OCP\AppFramework\QueryException;

class BackendManager
{
    const PRINCIPAL_PREFIX = 'principals/users/';

    const BC_SABRE = "0";
    const BC_DB_DIRECT = "1";

    private $appName;

    /** @var IBackendConnector */
    private $connector=null;

    public function __construct(){
        $this->appName=Application::APP_ID;
    }

    /**
     * @return IBackendConnector
     * @throws \Exception
     */
    function getConnector() {
        if ($this->connector !== null) return $this->connector;

        $connectorId = $this->getConnectorId();

        $cname = "";
        if ($connectorId === self::BC_SABRE) {
            if (!BCSabreImpl::checkCompatibility()) {
                throw new \Exception("Backend Connector " . BCSabreImpl::class . " not compatible");
            }
            $cname = BCSabreImpl::class;
        }

        try {
            $c = \OC::$server->query($cname);
        } catch (QueryException $e) {
            \OC::$server->getLogger()->error($e);
            throw new \Exception("Can't get Backend Connector");
        }

        $this->connector = $c;
        return $c;
    }

    private function getConnectorId() {
        // TODO: get from user prefs
        return self::BC_SABRE;
    }
}