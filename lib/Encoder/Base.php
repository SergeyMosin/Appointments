<?php

namespace OCA\Appointments\Encoder;

class Base
{
    protected function pack7(int $n): string
    {
        if ($n === 0) {
            return "\x80"; // 128
        }
        $s = '';
        while ($n !== 0) {
            // get the first 7 bits
            $bits = ($n & 127);
            $n >>= 7;
            $bits |= (((int)(!$n)) << 7);
            $s .= chr($bits);
        }
        return $s;
    }

    protected function unpack7(string $str, int &$pos = 0): int
    {
        $n = 0;
        for ($i = 0; $i < 9; $i++) {
            $bits = (ord($str[$pos]) & 255);
            $n |= (($bits & 127) << (7 * $i));
            $i |= ($bits & 128);
            $pos++;
        }
        return $n;
    }

    protected function pack8(int $n): string
    {
        if ($n < 0) {
            $n = ~$n;
        }
        if ($n === 0) {
            return "\x0";
        }
        $s = '';
        while ($n !== 0) {
            $byte = $n & 0xFF;
            $s .= chr($byte);
            $n >>= 8;
        }
        return $s;
    }

    protected function unpack8(string $str, int $start, int $end, bool $isNegative = false): int
    {
        $n = 0;
        $sc = 0;
        for ($i = $start; $i < $end; $i++) {
            $n |= (ord($str[$i]) << $sc);
            $sc += 8;
        }
        if ($isNegative) {
            $n = ~$n;
        }
        return $n;
    }
}