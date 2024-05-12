<?php

namespace Unit;

use OCA\Appointments\Encoder\PropEncoderBase;
use PHPUnit\Framework\TestCase;

class Jdoc
{
    public int $num1 = 0;
    public bool $bool1 = false;
    public string $str1 = "";
    public int $num2 = 0;
    public bool $bool2 = false;
    public string $str2 = "";
    public int $num3 = 0;
}

class Doc extends PropEncoderBase
{
    public int $num1 = 0;
    public bool $bool1 = false;
    public string $str1 = "";
    public int $num2 = 0;
    public bool $bool2 = false;
    public string $str2 = "";
    public int $num3 = 0;

    function encode(): string
    {
        return parent::encode();
    }

    function decode(string $data): bool
    {
        return parent::decode($data);
    }
}


class PropTest extends TestCase
{

//    private ConsoleLogger $logger;
//
//    public static function setUpBeforeClass(): void
//    {
//        $this->logger = new ConsoleLogger();
//
//        $app = new Application();
//        self::$container = $app->getContainer();
//    }

    function testProp()
    {
        echo "date_default_timezone_get: ".date_default_timezone_get().PHP_EOL;

        $logger = new ConsoleLogger();
        $doc = new Doc();

        $doc->str1 = "abcde: simplified Chinese: 汉语; traditional Chinese: 漢語";
        $doc->str2 = "";
        $doc->num1 = PHP_INT_MIN;
        $doc->num2 = -1;
        $doc->num3 = PHP_INT_MIN;
        $doc->bool1 = true;
        $doc->bool2 = false;

        $data = $doc->encode();

        $logger->info("Doc:");
        foreach ($doc as $key => $value) {
            $logger->info($key . ': ' . var_export($value, true));
        }
        $logger->info("");

        $logger->info("data raw: " . $data);
        $logger->info("data b64: " . base64_encode($data));
        $logger->info("data url: " . urlencode($data));
        $logger->info("");

        $doc2 = new Doc();
        $ok = $doc2->decode($data);
        $this->assertEquals(true, $ok, "decode should be true");

        $logger->info("Doc2:");
        foreach ($doc2 as $key => $value) {
            $logger->info($key . ': ' . var_export($value, true));
            $this->assertEquals($doc->{$key}, $value, $key . ' values should be equal');
        }

    }

    function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"\'';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function testEncodingTime()
    {
        $logger = new ConsoleLogger();
        $doc = new Doc();
        $jdoc = new Jdoc();

        $num1 = [];
        $num2 = [];
        $num3 = [];
        $str1 = [];
        $str2 = [];
        $bool1 = [];
        $bool2 = [];

        $iter = 10000;
        for ($i = 0; $i < $iter; $i++) {
            $num1[] = random_int(PHP_INT_MIN, PHP_INT_MAX);
            $num2[] = random_int(-15, 15);
            $num3[] = random_int(PHP_INT_MIN, PHP_INT_MAX);
            $str1[] = $this->generateRandomString(random_int(0, 15));
            $str2[] = $this->generateRandomString(random_int(1000, 6000));
            $bool1[] = random_int(0, 1) > 0;
            $bool2[] = random_int(0, 1) > 0;
        }

        $out = false;
        $start = microtime(true);
        for ($i = 0; $i < $iter; $i++) {

            $jdoc->num1 = $num1[$i];
            $jdoc->num2 = $num2[$i];
            $jdoc->num3 = $num3[$i];
            $jdoc->str1 = $str1[$i];
            $jdoc->str2 = $str2[$i];
            $jdoc->bool1 = $bool1[$i];
            $jdoc->bool2 = $bool2[$i];

            $s = json_encode($jdoc);

            $jdoc->num1 = 0;
            $jdoc->num2 = 0;
            $jdoc->num3 = 0;
            $jdoc->str1 = "";
            $jdoc->str2 = "";
            $jdoc->bool1 = false;
            $jdoc->bool2 = false;

            $jdoc = json_decode($s);

            if ($jdoc === null) {
                var_export([
                    $num1[$i],
                    $num2[$i],
                    $num3[$i],
                    $str1[$i],
                    $str2[$i],
                    $bool1[$i],
                    $bool2[$i],
                ]);
                die('json_decode failed');
            } elseif ($jdoc->num1 !== $num1[$i]
                || $jdoc->num2 !== $num2[$i]
                || $jdoc->num3 !== $num3[$i]
                || $jdoc->str1 !== $str1[$i]
                || $jdoc->str2 !== $str2[$i]
                || $jdoc->bool1 !== $bool1[$i]
                || $jdoc->bool2 !== $bool2[$i]
            ) {
                die('bad json_decode');
            }
            $out = true;
        }
        $time_elapsed_secs = microtime(true) - $start;
        echo 'Json time: ' . $time_elapsed_secs . PHP_EOL;
        echo var_export($out, true) . PHP_EOL;

        $out = false;
        $start = microtime(true);
        for ($i = 0; $i < $iter; $i++) {

            $doc->num1 = $num1[$i];
            $doc->num2 = $num2[$i];
            $doc->num3 = $num3[$i];
            $doc->str1 = $str1[$i];
            $doc->str2 = $str2[$i];
            $doc->bool1 = $bool1[$i];
            $doc->bool2 = $bool2[$i];

            $s = $doc->encode();

            $doc->num1 = 0;
            $doc->num2 = 0;
            $doc->num3 = 0;
            $doc->str1 = "";
            $doc->str2 = "";
            $doc->bool1 = false;
            $doc->bool2 = false;

            try {
                $out = $doc->decode($s);
                if ($out === false) {
                    die('doc->decode failed');
                } elseif ($doc->num1 !== $num1[$i]
                    || $doc->num2 !== $num2[$i]
                    || $doc->num3 !== $num3[$i]
                    || $doc->str1 !== $str1[$i]
                    || $doc->str2 !== $str2[$i]
                    || $doc->bool1 !== $bool1[$i]
                    || $doc->bool2 !== $bool2[$i]
                ) {
                    throw new \Exception('bad doc->decode');
                }
            } catch (\Throwable $e) {
                echo "error: " . $e->getMessage();
                var_export([
                    $num1[$i],
                    $num2[$i],
                    $num3[$i],
                    $str1[$i],
                    $str2[$i],
                    $bool1[$i],
                    $bool2[$i],
                ]);
                var_export([
                    $doc->num1,
                    $doc->num2,
                    $doc->num3,
                    $doc->str1,
                    $doc->str2,
                    $doc->bool1,
                    $doc->bool2,
                ]);
                echo PHP_EOL;
                exit(1);
            }
        }
        $time_elapsed_secs = microtime(true) - $start;
        echo 'Doc time: ' . $time_elapsed_secs . PHP_EOL;
        echo var_export($out, true) . PHP_EOL;

        $this->assertNotEquals(0, 1, "test");
    }
}
