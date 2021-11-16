<?php

namespace OCA\Appointments\Tests;

class ConsoleLoger extends \Psr\Log\AbstractLogger
{
    public function log($level, $message, array $context = array()) {
        fwrite(STDOUT, $level . ": " . $message .
            (!empty($context) ? ', ' . var_export($context, true) : '') . PHP_EOL);
    }
}