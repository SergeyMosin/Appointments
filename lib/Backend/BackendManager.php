<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace OCA\Appointments\Backend;

class BackendManager
{
    const PRINCIPAL_PREFIX = 'principals/users/';

    const BC_SABRE = "0";
    const BC_DB_DIRECT = "1";

    private IBackendConnector|null $connector = null;

    /**
     * @throws \Exception
     */
    function getConnector(): IBackendConnector
    {
        if ($this->connector !== null) {
            return $this->connector;
        }

        $connectorId = $this->getConnectorId();

        $cname = "";
        if ($connectorId === self::BC_SABRE) {
            if (!BCSabreImpl::checkCompatibility()) {
                throw new \Exception("Backend Connector " . BCSabreImpl::class . " not compatible");
            }
            $cname = BCSabreImpl::class;
        }

        try {
            $c = \OC::$server->get($cname);
        } catch (\Throwable $e) {
            $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
            $logger->error($e->getMessage());
            throw new \Exception("Can not get Backend Connector");
        }

        $this->connector = $c;
        return $c;
    }

    private function getConnectorId(): string
    {
        // TODO: get from user prefs
        return self::BC_SABRE;
    }
}