<?php

namespace OCA\Appointments\Backend;

class PropEncoderBase
{
    private const VERSION_001 = "\x81";
    // T_STRING and T_INT are also references to starting point
    // to encode lengths, ex:
    //  char(185): means that the value of an int is packed in the next 5 bytes
    //  char(173): means that 3 bytes follow with packed the length of string
    //  char(192): means a negative value is packed in the next 2 bytes
    private const STRING_EMPTY = "\xA0"; //160
    private const INT_ZERO = "\xD0"; //208
    private const T_STRING_START = 170; // 171 - 178 inclusive
    private const T_INT_START = 178; // 179 - 186 inclusive
    private const T_INT_NEG_START = 186; // 187 - 194 inclusive
    // The range from T_STRING_START > $x < T_LEN_END, is used for validation
    private const T_LEN_END = 195;
    private const T_BOOL_FALSE = "\xC8"; // 200
    private const T_BOOL_TRUE = "\xC9"; // 201

    protected int $codec_version = 1;
    private array $_props = [];

    public function __construct()
    {
        foreach ($this as $propName => $val) {
            // no prop names starting with '_'
            if ($propName[0] !== '_' && $propName !== 'codec_version') {
                $type = gettype($val);
                if ($type === "boolean"
                    || $type === "integer"
                    || $type === "string") {
                    $this->_props[$propName] = $type;
                }
            }
        }
    }

    protected function encode(): string
    {
        $r = self::VERSION_001;

        foreach ($this->_props as $propName => $type) {
            // propName length . propName
            $r .= $this->packStream(strlen($propName)) . $propName;
            $val = $this->{$propName};
            switch ($type) {
                case 'string':
                    if ($val === '') {
                        $r .= self::STRING_EMPTY;
                        break;
                    }
                    $pvl = $this->packBytes(strlen($val));
                    // length type . packed length . value
                    $r .= chr(self::T_STRING_START + strlen($pvl)) . $pvl . $val;
                    break;
                case 'integer':
                    if ($val === 0) {
                        $r .= self::INT_ZERO;
                        break;
                    }
                    $packedInt = $this->packBytes($val);
                    // type . packed value
                    $r .= chr(
                            ($val > 0
                                ? self::T_INT_START
                                : self::T_INT_NEG_START)
                            + strlen($packedInt)
                        ) . $packedInt;
                    break;
                case 'boolean':
                    $r .= ($val === true ? self::T_BOOL_TRUE : self::T_BOOL_FALSE);
                    break;
                default:
                    throw new \Exception('not implemented for type: ' . gettype($val));
            }
        }
        return $r;
    }

    protected function decode(string $data): bool
    {
        if (isset($data[0]) && $data[0] === self::VERSION_001) {
            $this->codec_version = 1;
        } else {
            throw new \Exception('unknown version');
        }
        $l = strlen($data);
        $i = 1;
        while ($i < $l) {
            $propNameLen = $this->unpackStream($data, $i);
            $propName = substr($data, $i, $propNameLen);
            $i += $propNameLen;
            $dataType = $data[$i];
            $propType = $this->_props[$propName] ?? '';
            switch ($dataType) {
                case self::T_BOOL_FALSE:
                    if ($propType === "boolean") {
                        $this->{$propName} = false;
                    }
                    break;
                case self::T_BOOL_TRUE:
                    if ($propType === "boolean") {
                        $this->{$propName} = true;
                    }
                    break;
                case self::INT_ZERO:
                    if ($propType === "integer") {
                        $this->{$propName} = 0;
                    }
                    break;
                case self::STRING_EMPTY:
                    if ($propType === "string") {
                        $this->{$propName} = '';
                    }
                    break;
                default:
                    $typeCode = ord($dataType);
                    if ($typeCode > self::T_STRING_START && $typeCode <= self::T_LEN_END) {
                        if ($typeCode > self::T_INT_START) {
                            // int (positive or negative)
                            $intType = $typeCode > self::T_INT_NEG_START
                                ? self::T_INT_NEG_START
                                : self::T_INT_START;
                            $start = $i + 1;
                            $i += ($typeCode - $intType);
                            if ($propType === 'integer') {
                                $this->{$propName} = $this->unpackBytes(
                                    $data, $start, $i + 1,
                                    $intType === self::T_INT_NEG_START);
                            }
                        } else {
                            // string
                            $strLen = $this->unpackBytes(
                                $data,
                                ++$i,
                                ($i += ($typeCode - self::T_STRING_START)),
                            );
                            if ($propType === 'string') {
                                $this->{$propName} = substr($data, $i, $strLen);
                            }
                            $i += ($strLen - 1);
                        }
                    } else {
                        throw new \Exception('not implemented for type: ' . $dataType . ' (' . ord($dataType) . ')');
                    }
            }
            $i++;
        }
        return true;
    }

    private function packStream(int $n): string
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

    private function unpackStream(string $str, int &$pos = 0): int
    {
        $n = 0;
        for ($i = 0; $i < 9; $i++) {
            $bits = (ord($str[$i + $pos]) & 255);
            $n |= (($bits & 127) << (7 * $i));
            $i |= ($bits & 128);
            $pos++;
        }
        return $n;
    }

    private function packBytes(int $n): string
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

    private function unpackBytes(string $str, int $start, int $end, bool $isNegative = false): int
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