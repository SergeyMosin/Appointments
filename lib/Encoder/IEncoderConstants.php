<?php
// https://stackoverflow.com/questions/24357985/php-traits-defining-generic-constants
namespace OCA\Appointments\Encoder;

interface IEncoderConstants
{
    const VERSION_001 = "\x81";
    const VERSION_002 = "\x82";
    // T_STRING and T_INT are also references to starting point
    // to encode lengths, ex:
    //  char(185): means that the value of an int is packed in the next 5 bytes
    //  char(173): means that 3 bytes follow with packed the length of string
    //  char(192): means a negative value is packed in the next 2 bytes
    const STRING_EMPTY = "\xA0"; //160
    const INT_ZERO = "\xD0"; //208
    const T_STRING_START = 170; // 171 - 178 inclusive
    const T_INT_START = 178; // 179 - 186 inclusive
    const T_INT_NEG_START = 186; // 187 - 194 inclusive
    // The range from T_STRING_START > $x < T_LEN_END, is used for validation
    const T_LEN_END = 195;
    const T_BOOL_FALSE = "\xC8"; // 200
    const T_BOOL_TRUE = "\xC9"; // 201
//    const T_STRUCT = "";
//    const T_ARRAY = "";
//    const T_ARRAY_FIXED = "";
}