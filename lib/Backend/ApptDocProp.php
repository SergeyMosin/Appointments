<?php

namespace OCA\Appointments\Backend;

use OCA\Appointments\AppInfo\Application;
use OCA\Appointments\Encoder\PropEncoderBase;
use Psr\Log\LoggerInterface;

class ApptDocProp extends PropEncoderBase
{
    public const PROP_NAME = 'X-APPT-DOC';
    private const VERSION = 1;

    public int $version = self::VERSION;
    public string $title = '';
    public bool $embed = false;
    public string $talkToken = '';
    public string $talkPass = '';
    public string $bbbToken = '';
    public string $bbbPass = '';
    public bool $inPersonType = false;
    public string $attendeeTimezone = 'UTC';
    public string $description = '';

    public string $_evtUid = '';
    private array $_defaults = [];
    private LoggerInterface $_logger;

    public function __construct()
    {
        foreach ($this as $key => $value) {
            if ($key[0] !== '_') {
                $this->_defaults[$key] = $value;
            }
        }
        $this->_defaults['_evtUid'] = '';

        parent::__construct();
        $this->_logger = \OC::$server->get(LoggerInterface::class);
    }

    public function toString(): string
    {
        try {
            return parent::encode();
        } catch (\Throwable $e) {
            $this->_logger->error($e->getMessage(), [
                'app' => Application::APP_ID,
                'exception' => $e
            ]);
            $this->reset();
            return '';
        }
    }

    public function setFromString(string $data, string $evtUId): bool
    {
        $this->reset();
        $res = false;
        try {
            $res = parent::decode($data);
            $this->_evtUid = $evtUId;
        } catch (\Throwable $e) {
            $this->_logger->error($e->getMessage(), [
                'app' => Application::APP_ID,
                'exception' => $e
            ]);
        }
        return $res;
    }

    public function reset(): void
    {
        foreach ($this->_defaults as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
