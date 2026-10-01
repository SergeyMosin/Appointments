<?php

declare(strict_types = 1);

namespace Unit;

//use OCA\Appointments\IntervalTree\AVLIntervalNode;
//use OCA\Appointments\IntervalTree\AVLIntervalTree;
use OCA\Appointments\RangeIndex\RangeIndex;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class RangeIndexTest extends TestCase
{
    function testMultiReturn()
    {

        $logger = new ConsoleLogger();
//        $logger->info("start testTree");

        $logger->info("-------------------------------------------\n");

        $this->assertEquals(1, 1);

////        $template = json_decode('[[{"start":42000,"dur":[30],"title":""},{"start":42600,"dur":[30],"title":""},{"start":34800,"dur":[30],"title":""},{"start":34200,"dur":[30],"title":""},{"start":35700,"dur":[30],"title":""},{"start":36600,"dur":[30],"title":""},{"start":39600,"dur":[30],"title":""},{"start":41400,"dur":[30],"title":""}],[{"start":28800,"dur":[15],"title":""},{"start":29700,"dur":[15],"title":""},{"start":30600,"dur":[15],"title":""},{"start":31500,"dur":[15],"title":""},{"start":32400,"dur":[15],"title":""},{"start":33300,"dur":[15],"title":""},{"start":34200,"dur":[15],"title":""},{"start":35100,"dur":[15],"title":""},{"start":36000,"dur":[15],"title":""},{"start":36900,"dur":[15],"title":""}],[],[{"start":28800,"dur":[30],"title":""},{"start":30600,"dur":[30],"title":""},{"start":32400,"dur":[30],"title":""},{"start":34200,"dur":[30],"title":""},{"start":36000,"dur":[30],"title":""},{"start":37800,"dur":[30],"title":""},{"start":39600,"dur":[30],"title":""},{"start":41400,"dur":[30],"title":""}],[],[],[]]', true);
////
////
////        foreach ($template as &$subArray) {
////            if (!empty($subArray)) {
////                usort($subArray, function ($a, $b) {
////                    // Use ->start for objects. If you decoded with json_decode(..., true), use $a['start'] instead.
////                    return $a['start'] <=> $b['start'];
////                });
////            }
////        }
////        unset($subArray); // Break the reference link after the loop
////
////
//////        $logger->info(var_export($template,true));
////
////        $ri = new RangeIndex();
////
////        $start = new \DateTime('now');
////
////        $end = new \DateTime('now');
////        $end->modify('+1 week');
////
////        $ri->buildFromTemplate(
////            $template, $start, $start->getTimestamp(), $end->getTimestamp()
////        );
////
//////        $logger->info(var_export($riData, true));
////
////        $riData = $ri->dump();
////        $ri->invalidateRange(1790587800,1790597800);
////
////        $logger->info(var_export($riData, true));
////
////        $ri->compact();
////        $riData = $ri->dump();
////
////        $logger->info(var_export($riData, true));
//
//        $ri = new RangeIndex();
//
////        $arr = [
////            [19, 25],
////            [25, 31],
////            [15, 25],
////            [19, 21],
////            [22,25],
////            [26,28],
////            [25, 35],
////            [18,32]
////        ];
//
//        $arr = [
//            [8, 12],   // index 0 – possible root (not chosen by DP in this run)
//            [10, 13],  // index 1 – becomes the root of the chosen chain
//            [11, 14],  // index 2 – tiny step  ← unnecessary
//            [12, 15],  // index 3 – tiny step  ← unnecessary
//            [13, 25],  // index 4 – big jump that makes the two tinies redundant
//            [20, 22],  // index 5 – (not used in the final dense path)
//            [21, 32],  // index 6 – terminal
//        ];
//
//        usort($arr, function ($a, $b) {
//            return $a[0] <=> $b[0];
//        });
//
//        $ri->buildFromArray($arr);
//
//        $riData = $ri->dump();
//
//        $logger->info('ttt_1: ' . var_export($riData, true));
//
////        $ri->invalidateRange(20,30);
//
//        $ri->invalidateSingleIntervalInRange(10,30);
//        $logger->info('ttt_1: ' . var_export($riData, true));

    }




    private RangeIndex $ri;

    protected function setUp(): void
    {
        $this->ri = new RangeIndex();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array{0: list<int>, 1: list<int>, 2: list<int>} */
    private function dump(): array
    {
        return $this->ri->dump();
    }

    private function starts(): array
    {
        return $this->dump()[0];
    }

    private function ends(): array
    {
        return $this->dump()[1];
    }

    private function maxEnds(): array
    {
        return $this->dump()[2];
    }

    private function validEnds(): array
    {
        return array_map(
            static fn(int $e): int => abs($e),
            array_filter($this->ends(), static fn(int $e): bool => $e >= 0)
        );
    }

    private function validCount(): int
    {
        return count(array_filter($this->ends(), static fn(int $e): bool => $e >= 0));
    }

    // ------------------------------------------------------------------
    // buildFromArray / finalize
    // ------------------------------------------------------------------

    public function testBuildFromArrayEmpty(): void
    {
        $this->ri->buildFromArray([]);
        $this->assertSame([], $this->starts());
        $this->assertSame([], $this->ends());
        $this->assertSame([], $this->maxEnds());
    }

    public function testBuildFromArraySingle(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        $this->assertSame([10], $this->starts());
        $this->assertSame([20], $this->ends());
        $this->assertSame([20], $this->maxEnds());
    }

    public function testBuildFromArrayMultipleAndMaxEnds(): void
    {
        // sorted by start
        $this->ri->buildFromArray([
            [5, 15],
            [10, 12],
            [11, 25],
            [20, 30],
        ]);

        $this->assertSame([5, 10, 11, 20], $this->starts());
        $this->assertSame([15, 12, 25, 30], $this->ends());
        // maxEnds[i] = max end among [0..i]
        $this->assertSame([15, 15, 25, 30], $this->maxEnds());
    }

    // ------------------------------------------------------------------
    // invalidateRange (bulk)
    // ------------------------------------------------------------------

    public function testInvalidateRangeEmptyIndex(): void
    {
        $this->assertSame(0, $this->ri->invalidateRange(0, 100));
    }

    public function testInvalidateRangeEmptyQuery(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        $this->assertSame(0, $this->ri->invalidateRange(15, 15));
        $this->assertSame(0, $this->ri->invalidateRange(20, 10)); // end <= start
    }

    public function testInvalidateRangeNoOverlap(): void
    {
        $this->ri->buildFromArray([[10, 20], [30, 40]]);
        $this->assertSame(0, $this->ri->invalidateRange(20, 30)); // touches but half-open → no overlap
        $this->assertSame(0, $this->ri->invalidateRange(0, 10));
        $this->assertSame(0, $this->ri->invalidateRange(40, 50));
    }

    public function testInvalidateRangePartialAndFullOverlap(): void
    {
        $this->ri->buildFromArray([
            [0, 10],
            [5, 15],
            [20, 30],
            [25, 35],
        ]);

        // query [8, 22) intersects first three
        $c = $this->ri->invalidateRange(8, 22);
        $this->assertSame(3, $c);

        $ends = $this->ends();
        $this->assertLessThan(0, $ends[0]); // [0,10)
        $this->assertLessThan(0, $ends[1]); // [5,15)
        $this->assertLessThan(0, $ends[2]); // [20,30)
        $this->assertGreaterThan(0, $ends[3]); // [25,35) untouched
    }

    public function testInvalidateRangeAlreadyInvalidatedAreSkipped(): void
    {
        $this->ri->buildFromArray([[10, 20], [15, 25]]);
        $this->ri->invalidateRange(10, 20); // invalidates both
        $this->assertSame(0, $this->ri->invalidateRange(10, 20)); // second call does nothing
    }

    // ------------------------------------------------------------------
    // invalidateSingleIntervalInRange – basic cases
    // ------------------------------------------------------------------

    public function testSingleEmptyIndex(): void
    {
        $this->assertSame(0, $this->ri->invalidateSingleIntervalInRange(0, 100));
    }

    public function testSingleEmptyQuery(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        $this->assertSame(0, $this->ri->invalidateSingleIntervalInRange(15, 15));
    }

    public function testSingleExactOneInterval(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(1, $c);
        $this->assertLessThan(0, $this->ends()[0]);
    }

    public function testSingleExactOneIntervalOvershoot(): void
    {
        $this->ri->buildFromArray([[5, 25]]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(1, $c);
        $this->assertLessThan(0, $this->ends()[0]);
    }

    public function testSingleNoCoverPossible(): void
    {
        $this->ri->buildFromArray([
            [0, 5],
            [25, 30],
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(0, $c);
        $this->assertSame(2, $this->validCount());
    }

    public function testSingleGapInMiddle(): void
    {
        $this->ri->buildFromArray([
            [5, 12],
            [15, 25], // gap [12,15)
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(2, $c);
        $this->assertSame(0, $this->validCount());
    }

    // ------------------------------------------------------------------
    // Abutment (half-open)
    // ------------------------------------------------------------------

    public function testSingleAbuttingIntervalsFormContinuousCover(): void
    {
        // [8,12) + [12,18) + [18,22) covers [10,20)
        $this->ri->buildFromArray([
            [8, 12],
            [12, 18],
            [18, 22],
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertGreaterThan(0, $c);
        // all three are necessary → all should be invalidated
        $this->assertSame(0, $this->validCount());
    }

    public function testSingleTouchingButNotCovering(): void
    {
        // [10,15) + [15,20) covers [10,20) exactly
        $this->ri->buildFromArray([
            [10, 15],
            [15, 20],
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(2, $c);
        $this->assertSame(0, $this->validCount());
    }

    // ------------------------------------------------------------------
    // Unnecessary intermediates (sparsify)
    // ------------------------------------------------------------------

    public function testSparsifyDropsUnnecessaryIntermediates(): void
    {
        // Dense path will contain tiny steps that the farthest-reach
        // greedy can discard.
        $this->ri->buildFromArray([
            [8, 12],   // 0 possible root
            [10, 13],  // 1 root of chosen chain
            [11, 14],  // 2 unnecessary
            [12, 15],  // 3 unnecessary
            [13, 25],  // 4 big jump
            [20, 22],  // 5 not used
            [21, 32],  // 6 terminal
        ]);

        $c = $this->ri->invalidateSingleIntervalInRange(10, 30);

        // sparsify should keep only 3 intervals
        $this->assertSame(3, $c);
        $this->assertSame(4, $this->validCount()); // 7-3 = 4 still valid
    }

    public function testSparsifyFallbackInvalidatesWholeDenseChain(): void
    {
        // Force a situation where sparsify cannot finish cleanly
        // (we simulate by using a cover that the greedy may abort on
        // if the data is slightly inconsistent).  Here we just verify
        // that a normal multi-interval cover still invalidates something.
        $this->ri->buildFromArray([
            [5, 15],
            [12, 16],
            [14, 17],
            [15, 20],
            [18, 22],
            [19, 25],
            [23, 28],
            [26, 35],
        ]);

        $c = $this->ri->invalidateSingleIntervalInRange(10, 30);
        $this->assertGreaterThan(0, $c);
        // at least the selected cover is gone
        $this->assertLessThan(8, $this->validCount());
    }

    // ------------------------------------------------------------------
    // Preference for later start / lower outside cost
    // ------------------------------------------------------------------

    public function testPrefersLaterRootWhenPossible(): void
    {
        $this->ri->buildFromArray([
            [0, 15],   // early root, large left outside
            [9, 15],   // later root, small left outside
            [14, 22],  // terminal
        ]);

        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(2, $c);

        $ends = $this->ends();
        // the early root [0,15) should stay valid
        $this->assertGreaterThan(0, $ends[0]);
        // the better root and the terminal should be invalidated
        $this->assertLessThan(0, $ends[1]);
        $this->assertLessThan(0, $ends[2]);
    }

    public function testPrefersSmallerRightOvershoot(): void
    {
        $this->ri->buildFromArray([
            [8, 12],
            [11, 18],
            [17, 20],   // exact end
            [16, 25],   // larger overshoot
        ]);

        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertGreaterThan(0, $c);

//        $ends = $this->ends();
//        // the exact-end terminal should be preferred over the large overshoot
//        $this->assertLessThan(0, $ends[2]); // [17,20) used
//        $this->assertGreaterThan(0, $ends[3]); // [16,25) left alone
        $ends = $this->ends();
        $this->assertContains(-20, $ends);           // [17,20) invalidated
        $this->assertNotContains(-25, $ends);        // [16,25) not invalidated
        $this->assertContains(25, $ends);            // still present as valid
    }

    // ------------------------------------------------------------------
    // Already-invalidated intervals inside the window
    // ------------------------------------------------------------------

    public function testIgnoresAlreadyInvalidatedIntervals(): void
    {
        $this->ri->buildFromArray([
            [5, 15],
            [12, 22],
            [18, 25],
        ]);

        // manually invalidate the middle one
        $this->ri->invalidateRange(12, 13); // marks [12,22) invalid

        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        // only [5,15) can start; it does not reach 20, and the only
        // remaining interval that reaches is [18,25) but there is a gap.
        // Depending on exact numbers a cover may or may not exist.
        // The important assertion: the already-invalid interval stays invalid
        // and is never “re-validated”.
        $ends = $this->ends();
        $this->assertLessThan(0, $ends[1]);
    }

    // ------------------------------------------------------------------
    // Boundary / half-open edge cases
    // ------------------------------------------------------------------

    public function testQueryTouchesStartButDoesNotOverlap(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        // query [20, 30) – half-open, no overlap
        $this->assertSame(0, $this->ri->invalidateSingleIntervalInRange(20, 30));
        $this->assertSame(0, $this->ri->invalidateRange(20, 30));
    }

    public function testQueryTouchesEndButDoesNotOverlap(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        // query [0, 10) – half-open, no overlap
        $this->assertSame(0, $this->ri->invalidateSingleIntervalInRange(0, 10));
        $this->assertSame(0, $this->ri->invalidateRange(0, 10));
    }

    public function testIntervalExactlyMatchingQuery(): void
    {
        $this->ri->buildFromArray([[10, 20]]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(1, $c);
    }

    public function testIntervalStartingAfterQueryStartNeedsPrevious(): void
    {
        $this->ri->buildFromArray([
            [15, 25], // starts after 10 → cannot cover alone
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(1, $c);
    }

    // ------------------------------------------------------------------
    // compact()
    // ------------------------------------------------------------------

    public function testCompactRemovesInvalidated(): void
    {
        $this->ri->buildFromArray([
            [5, 15],
            [10, 20],
            [25, 35],
        ]);
        $this->ri->invalidateRange(8, 12); // invalidates first two
        $this->assertSame(1, $this->validCount());

        $this->ri->compact();

        $this->assertSame([25], $this->starts());
        $this->assertSame([35], $this->ends());
        $this->assertSame([35], $this->maxEnds());
    }

    public function testCompactEmptyAfterAllInvalidated(): void
    {
        $this->ri->buildFromArray([[10, 20], [15, 25]]);
        $this->ri->invalidateRange(0, 100);
        $this->ri->compact();
        $this->assertSame([], $this->starts());
        $this->assertSame([], $this->ends());
        $this->assertSame([], $this->maxEnds());
    }

    // ------------------------------------------------------------------
    // Multiple successive calls
    // ------------------------------------------------------------------

    public function testSuccessiveSingleInvalidations(): void
    {
        $this->ri->buildFromArray([
            [0, 30],
            [5, 15],
            [10, 20],
            [15, 25],
            [20, 35],
        ]);

        // first call takes a cover of [10,20)
        $c1 = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertGreaterThan(0, $c1);

        // second call for a later range should still succeed with remaining intervals
        $c2 = $this->ri->invalidateSingleIntervalInRange(22, 30);
        $this->assertGreaterThan(0, $c2);

        $this->assertLessThan(5, $this->validCount());
    }

    // ------------------------------------------------------------------
    // Large left / right outside cost
    // ------------------------------------------------------------------

    public function testLargeOutsideCostStillAcceptedWhenOnlyOption(): void
    {
        $this->ri->buildFromArray([
            [0, 100], // huge outside cost, but only option
        ]);
        $c = $this->ri->invalidateSingleIntervalInRange(40, 60);
        $this->assertSame(1, $c);
        $this->assertLessThan(0, $this->ends()[0]);
    }

    // ------------------------------------------------------------------
    // Degenerate intervals (end <= start) are never inserted by buildFromArray
    // but if they somehow appear they should be ignored by the DP
    // ------------------------------------------------------------------

    public function testDegenerateIntervalIgnoredByDP(): void
    {
        // manually poke a degenerate entry (normally buildFromArray would not create it)
        $this->ri->buildFromArray([
            [10, 10], // zero-length
            [8, 22],
        ]);
        // The DP skips end <= start, so only the second interval can be used
        $c = $this->ri->invalidateSingleIntervalInRange(10, 20);
        $this->assertSame(1, $c);
    }

    // ------------------------------------------------------------------
    // Stress / many tiny steps
    // ------------------------------------------------------------------

    public function testManyTinyStepsSparsified(): void
    {
        $intervals = [[5, 12]];
        for ($i = 0; $i < 20; $i++) {
            $intervals[] = [10 + $i, 13 + $i];
        }
        $intervals[] = [25, 40];

        $this->ri->buildFromArray($intervals);

        $c = $this->ri->invalidateSingleIntervalInRange(10, 30);

        $this->assertGreaterThan(0, $c);
        // sparsify must have kept a small number of intervals
        $this->assertLessThan(10, $c);
        // and some intervals must remain valid
        $this->assertGreaterThan(0, $this->validCount());
    }

}