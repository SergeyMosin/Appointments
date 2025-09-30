<?php

namespace Unit;

class ConsoleLogger extends \Psr\Log\AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []):void
    {
        fwrite(STDOUT, (!empty($level) ? $level . ": " : '') . $message .
            (!empty($context) ? ', ' . var_export($context, true) : '') . PHP_EOL);
    }
}