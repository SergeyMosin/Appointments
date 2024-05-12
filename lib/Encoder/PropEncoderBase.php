<?php

namespace OCA\Appointments\Encoder;

class PropEncoderBase extends Base implements IEncoderConstants
{

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
            $r .= $this->pack7(strlen($propName)) . $propName;
            $val = $this->{$propName};
            switch ($type) {
                case 'string':
                    if ($val === '') {
                        $r .= self::STRING_EMPTY;
                        break;
                    }
                    $pvl = $this->pack8(strlen($val));
                    // length type . packed length . value
                    $r .= chr(self::T_STRING_START + strlen($pvl)) . $pvl . $val;
                    break;
                case 'integer':
                    if ($val === 0) {
                        $r .= self::INT_ZERO;
                        break;
                    }
                    $packedInt = $this->pack8($val);
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
            $propNameLen = $this->unpack7($data, $i);
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
                                $this->{$propName} = $this->unpack8(
                                    $data, $start, $i + 1,
                                    $intType === self::T_INT_NEG_START);
                            }
                        } else {
                            // string
                            $strLen = $this->unpack8(
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
}