<?php

namespace OCA\Appointments\Backend;

class HintVar
{
    public const APPT_NONE = 0;
    public const APPT_BOOK = 1;
    public const APPT_CONFIRM = 2;
    public const APPT_CANCEL = 3;
    public const APPT_SKIP = 4;
    public const APPT_TYPE_CHANGE = 5;

    /** @type int $hint */
    private static $hint = self::APPT_NONE;

    public static function setHint(int $hint)
    {
        self::$hint = $hint;
    }

    public static function getHint(): int
    {
        return self::$hint;
    }
}
