<?php

namespace OCA\Appointments\Tests\Unit;

use OCA\Appointments\IntervalTree\AVLIntervalNode;
use OCA\Appointments\IntervalTree\AVLIntervalTree;
use OCA\Appointments\Tests\ConsoleLoger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class IntervalTreeTest extends TestCase
{
    function testTree() {
        $logger = new ConsoleLoger();

        $logger->info("start testTree");

        $booked = null;
        $itc = new AVLIntervalTree();

        $logger->info("73600, 77200: " . $itc->insert($booked, 73600, 77200));
        $logger->info("80800, 98800: " . $itc->insert($booked, 80800, 98800));
        $logger->info("70000, 80800: " . $itc->insert($booked, 70000, 80800));


        $logger->info("result: " . var_export($booked, true));
        $logger->info("-------------------------------------------\n");


        $low = 80800;
        $high = 84400;

        $logger->info("l: " . $low . ", h: " . $high);

        $result = AVLIntervalTree::lookUp($booked, 80800, 84400);
        $logger->info("result: " . var_export($result, true));

        $this->assertNotEquals(null, $result, "result must not be null");
    }


    function testTreeFreeWindow() {

        $logger = new ConsoleLoger();

        $logger->info("start testTreeFreeWindow");

        //  0 - 45: busy (top)
        // 45 - 55: free
        // 55 - 100: busy (bottom)

        $freeLow = 45;
        $freeHigh = 55;

        $tree = null;
        $itc = new AVLIntervalTree();


        $topMin = 100;
        $topMax = 0;

        // load top
        for ($i = 0; $i < 512; $i++) {
            $low = rand(0, $freeLow - 5); // ~0 - 40
            if ($low < $topMin) {
                $topMin = $low;
            }

            $high = rand($low + 1, $freeLow); // ~low - 45
            if ($high > $topMax) {
                $topMax = $high;
            }
            $itc->insert($tree, $low, $high);
        }
        $logger->info("top min: " . $topMin . ", top max: " . $topMax);

        $btmMin = 100;
        $btmMax = 0;

        // load bottom
        for ($i = 0; $i < 512; $i++) {
            $low = rand($freeHigh, 95); // ~55 - 95
            if ($low < $btmMin) {
                $btmMin = $low;
            }
            $high = rand($low + 1, 100); // ~low - 100
            if ($high > $btmMax) {
                $btmMax = $high;
            }

            $itc->insert($tree, $low, $high);
        }
        $logger->info("btm min: " . $btmMin . ", btm max: " . $btmMax);

        $result = AVLIntervalTree::lookUp($tree, $freeLow, $freeHigh);

        $this->assertEquals(null, $result, "result must be null");
    }

    function testUpDownIntersect() {

        $logger = new ConsoleLoger();

        $logger->info("start testUpDownIntersect");

        $itc = new AVLIntervalTree();

        $tree = null;
        $treeMin = 100;
        $treeMax = 0;

        // load random
        for ($i = 0; $i < 16; $i++) {
            $low = rand(0, 97);
            if ($low < $treeMin) {
                $treeMin = $low;
            }

            $high = rand($low + 1, 100);
            if ($high > $treeMax) {
                $treeMax = $high;
            }
//            $logger->info("itc->insert: " . $low . " - " . $high);
            $itc->insert($tree, $low, $high);
        }
        $this->pushIntervalUp($tree, $treeMin, $treeMax);
        $this->pushIntervalDown($tree, $treeMin, $treeMax);

        $tree = null;
        $treeMin = 100;
        $treeMax = 0;
        // load chunks, Ex: 0-5, 5-10, 10-15, ...
        for ($i = 0; $i < 100; $i += 5) {
            $low = $i;
            if ($low < $treeMin) {
                $treeMin = $low;
            }

            $high = $i + 5;
            if ($high > $treeMax) {
                $treeMax = $high;
            }
//            $logger->info("itc->insert: " . $low . " - " . $high);
            $itc->insert($tree, $low, $high);
        }
        $this->pushIntervalUp($tree, $treeMin, $treeMax);
        $this->pushIntervalDown($tree, $treeMin, $treeMax);
    }


    private function pushIntervalUp(AVLIntervalNode $tree, int $treeMin, int $treeMax, AbstractLogger $logger = null) {

        if ($logger != null) $logger->info("treeMin: " . $treeMin . ", treeMax: " . $treeMax);

        // push interval low ($iLow) all the way back to $treeMin
        $iLow = $treeMax - 1;
        $iHigh = $treeMax + 1;
        for (; $iLow > $treeMin; $iLow--) {
            $result = AVLIntervalTree::lookUp($tree, $iLow, $iHigh);
            $this->assertNotEquals(null, $result, "result mut not be null. iLow: " . $iLow . ", iHigh: " . $iHigh);
            if ($logger != null) $logger->info("iLow: " . $iLow . ", iHigh: " . $iHigh . ", r->l: " . $result->l . ", r->h: " . $result->h);
        }

        if ($logger != null) $logger->info("-------------------------------");

        // push interval high ($iHigh) all the way back to $treeMin
        for (; $iHigh > $treeMin; $iHigh--) {
            $result = AVLIntervalTree::lookUp($tree, $iLow, $iHigh);
            $this->assertNotEquals(null, $result, "result mut not be null. iLow: " . $iLow . ", iHigh: " . $iHigh);
            if ($logger != null) $logger->info("iLow: " . $iLow . ", iHigh: " . $iHigh . ", r->l: " . $result->l . ", r->h: " . $result->h);
        }

    }

    private function pushIntervalDown(AVLIntervalNode $tree, int $treeMin, int $treeMax, AbstractLogger $logger = null) {

        if ($logger != null) $logger->info("treeMin: " . $treeMin . ", treeMax: " . $treeMax);

        $iLow = $treeMin - 1;
        $iHigh = $treeMin + 1;

        // push interval high ($iHigh) all the way back to $treeMin
        for (; $iHigh <= $treeMax; $iHigh++) {
            $result = AVLIntervalTree::lookUp($tree, $iLow, $iHigh);
            $this->assertNotEquals(null, $result, "result mut not be null. iLow: " . $iLow . ", iHigh: " . $iHigh);
            if ($logger != null) $logger->info("iLow: " . $iLow . ", iHigh: " . $iHigh . ", r->l: " . $result->l . ", r->h: " . $result->h);
        }

        if ($logger != null) $logger->info("-------------------------------");

        // push interval low ($iLow) all the way back to $treeMin
        for (; $iLow < $treeMax; $iLow++) {
            $result = AVLIntervalTree::lookUp($tree, $iLow, $iHigh);
            $this->assertNotEquals(null, $result, "result mut not be null. iLow: " . $iLow . ", iHigh: " . $iHigh);
            if ($logger != null) $logger->info("iLow: " . $iLow . ", iHigh: " . $iHigh . ", r->l: " . $result->l . ", r->h: " . $result->h);
        }

    }


}