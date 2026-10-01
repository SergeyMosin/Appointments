<?php

namespace OCA\Appointments\RangeIndex;

class RangeIndex
{
    /**
     * Ranges are half-open: [start, end)
     */
    private array $starts = [];
    private array $ends = [];
    private array $maxEnds = [];
    // masked int: indexInDay,dayNumber(3bits)
    private array $data = [];

    /**
     * @param array $td
     * @param \DateTime $start
     * @param int $start_ts original start (from)
     * @param int $end_ts original end (to)
     */
    public function buildFromTemplate(array $td, \DateTime $start, int $start_ts, int $end_ts): void
    {
        $day = $start->format('N') - 1;
        $ds = $start->getTimestamp();

        while ($ds < $end_ts) {
            $perDayArray = $td[$day];

            // timeSlots($slotData) MUST be already sortes using something like:
            // $a['start'] <=> $b['start']) ?: ($a['end'] <=> $b['end']
            foreach ($perDayArray as $slotData) {

                $start_sec = $slotData['start'];

                $start->setTime(0, 0, $start_sec);
                $sts = $start->getTimestamp();

                if ($sts < $start_ts) {
                    // TODO: can this be moved to outer loop ???
                    // skip past
                    continue;
                }
                if ($sts > $end_ts) {
                    // Done :)
                    break 2;
                }

                $this->starts[] = $sts;
                $this->ends[] = ($sts + ($slotData['end'] - $start_sec));
                $this->data[] = $slotData;
            }

            $day++;
            if ($day >= 7) {
                $day = 0;
            }
            // we need to re-calculate this because of daytime satokensvings
            $start->setTime(0, 0);
            $start->modify('+1 day');
            $ds = $start->getTimestamp();
        }
        $this->finalize();
    }

    /**
     * mostly used fo testing
     * @param array $arr [[start,end],...] - must be sorted
     * @return void
     */
    public function buildFromArray(array $arr): void
    {
        usort($arr, function ($a, $b) {
            return ($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1]);
        });

        $data = [
            'tkn' => null
        ];

        foreach ($arr as $se) {
            $this->starts[] = $se[0];
            $this->ends[] = $se[1];
            $this->data[] = $data;
        }
        $this->finalize();
    }

    private function finalize(): void
    {
        /*
         * maxEnds[i] = maximum end among [0 .. i].
         *
         * It is intentionally not modified when a slot is invalidated
         * later. A stale larger max is safe: it may cause extra scanning,
         * but it cannot hide a valid range.
         */
        $this->maxEnds = [];

        $count = count($this->starts);
        $maxEnd = PHP_INT_MIN;
        for ($i = 0; $i < $count; ++$i) {
            $end = $this->ends[$i];
            if ($end > $maxEnd) {
                $maxEnd = $end;
            }
            $this->maxEnds[$i] = $maxEnd;
        }
    }

    /**
     * Check whether any valid range intersects [start, end).
     * @returns int - count of invalidated intervals
     */
    public function invalidateRange(int $start, int $end): int
    {
        if ($end <= $start || !$this->starts) {
            return 0;
        }

        /*
         * Only ranges with:
         *     range.start < query.end
         * can intersect.
         * Find first start >= queryEnd.
         */
        $right = $this->lowerBoundStart($end);
        if ($right === 0) {
            return 0;
        }

        /*
         * maxEnds allows us to skip the prefix where no range can
         * possibly reach queryStart.
         * Find first index where:
         *     maxEnds[i] > queryStart
         */
        $left = $this->firstMaxEndGreaterThan($start, $right);

        $c = 0;
        for ($i = $left; $i < $right; ++$i) {

            $endVal = $this->ends[$i];

            /*
             * Invalid slots are skipped before interpreting the end
             * timestamp because the low bit is metadata.
             */
            if ($endVal < 0) {
                continue;
            }

            /*
             * TODO: this is probably not needed...
             * Temporal intersection:
             * range.end > query.start
             * We already know:
             * range.start < query.end
             */
            if ($endVal <= $start) {
                continue;
            }

            $this->ends[$i] = -abs($endVal);

            $c++;
        }
        return $c;
    }


    /**
     * Invalidate intervals that interact with the query range [start_ts, end_ts).
     *
     * Contract
     * --------
     * 1. If a continuous cover of [start_ts, end_ts) exists:
     *    invalidate a minimum-outside-union-cost cover (token-aware as a
     *    secondary key), then sparsify it to a small cardinality.
     *
     * 2. If no continuous cover exists:
     *    invalidate a minimum number of intervals that maximise the measure
     *    of the query that is covered (classic max-coverage / min-cardinality
     *    greedy on a line).
     *
     * Ranges are half-open: [start, end).
     * When $token is not null, among covers with equal outside cost the one
     * that uses the fewest non-matching tokens is preferred.
     *
     * @param int $start_ts query start (inclusive)
     * @param int $end_ts query end   (exclusive)
     * @param mixed|null $token prefer intervals whose token equals this value
     * @return int                  number of intervals invalidated
     */
    public function invalidateSingleIntervalInRange(
        int   $start_ts,
        int   $end_ts,
        mixed $token = null
    ): int {
        if ($end_ts <= $start_ts || !$this->starts) {
            return 0;
        }

        $right = $this->lowerBoundStart($end_ts);
        if ($right === 0) {
            return 0;
        }
        $left = $this->firstMaxEndGreaterThan($start_ts, $right);
        $n = $right - $left;
        if ($n <= 0) {
            return 0;
        }

        // ------------------------------------------------------------------
        // Fast path: single intersecting candidate
        // (binary searches already guarantee intersection)
        // ------------------------------------------------------------------
        if ($n === 1) {
            $v = $this->ends[$left];
            $this->ends[$left] = -abs($v);
            return $v < 0 ? 0 : 1;
        }

        // ------------------------------------------------------------------
        // DP – look for a full min-outside-cost (token-aware) cover
        // state: [leftmost_start, reachable_end, last_index, non_match_count]
        // ------------------------------------------------------------------
        $states = [];
        $top = 0;
        $prev = array_fill(0, $n, -1);
        $bestCost = INF;
        $bestNon = INF;
        $bestLast = -1;

        for ($i = $left; $i < $right; $i++) {
            $end = $this->ends[$i];
            if ($end < 0) {
                continue;
            }
            $start = $this->starts[$i];
            $zi = $i - $left;
            $non = ($token !== null && ($this->data[$i]['tkn'] ?? null) !== $token) ? 1 : 0;

            while ($top > 0 && $states[$top - 1][1] < $start) {
                --$top;
            }

            // ---- terminal (reaches end_ts) ----
            if ($end >= $end_ts) {
                if ($start <= $start_ts) {
                    $cost = ($start_ts - $start) + ($end - $end_ts);
                    $nonTotal = $non;
                    if ($cost < $bestCost
                        || ($cost === $bestCost && $nonTotal < $bestNon)
                    ) {
                        $bestCost = $cost;
                        $bestNon = $nonTotal;
                        $bestLast = $zi;
                        $prev[$zi] = -1;
                        if ($cost === 0 && $nonTotal === 0) {
                            break;          // perfect cover
                        }
                    }
                } elseif ($top > 0) {
                    $last = $states[$top - 1];
                    $cost = ($start_ts - $last[0]) + ($end - $end_ts);
                    $nonTotal = $last[3] + $non;
                    if ($cost < $bestCost
                        || ($cost === $bestCost && $nonTotal < $bestNon)
                    ) {
                        $bestCost = $cost;
                        $bestNon = $nonTotal;
                        $bestLast = $zi;
                        $prev[$zi] = $last[2];
                        if ($cost === 0 && $nonTotal === 0) {
                            break;          // perfect cover
                        }
                    }
                }
                continue;
            }

            // ---- starter ----
            if ($start <= $start_ts) {
                while ($top > 0 && $states[$top - 1][1] <= $end) {
                    --$top;
                }
                $states[$top++] = [$start, $end, $zi, $non];
                $prev[$zi] = -1;
                continue;
            }

            // ---- extension ----
            if ($top === 0) {
                continue;
            }
            $lastIdx = $top - 1;
            if ($end > $states[$lastIdx][1]) {
                $prev[$zi] = $states[$lastIdx][2];
                $states[$lastIdx][1] = $end;
                $states[$lastIdx][2] = $zi;
                $states[$lastIdx][3] += $non;

                $updated = $states[--$top];
                while ($top > 0 && $states[$top - 1][1] <= $end) {
                    --$top;
                }
                $states[$top++] = $updated;
            }
        }

        // ------------------------------------------------------------------
        // Clause 1 – full cover found → reconstruct, sparsify, invalidate
        // ------------------------------------------------------------------
        if ($bestLast >= 0) {
            $full = [];
            $i = $bestLast;
            while ($i >= 0) {
                $full[] = $i + $left;
                $i = $prev[$i];
            }
            $fc = count($full);

            if ($fc <= 2) {
                foreach ($full as $idx) {
                    $this->ends[$idx] = -abs($this->ends[$idx]);
                }
                return $fc;
            }

            $full = array_reverse($full);

            // sparsify
            $c = 0;
            $i = 0;
            $currentEnd = $start_ts;
            $sparsifyOk = true;

            while ($currentEnd < $end_ts && $i < $fc) {
                $idx = $full[$i];
                $start = $this->starts[$idx];
                if ($start > $currentEnd) {
                    $sparsifyOk = false;
                    break;
                }

                $maxEnd = $currentEnd;
                $bestJ = -1;
                while ($i < $fc) {
                    $idx = $full[$i];
                    $start = $this->starts[$idx];
                    if ($start > $currentEnd) {
                        break;
                    }
                    $end = $this->ends[$idx];
                    if ($end > $maxEnd) {
                        $maxEnd = $end;
                        $bestJ = $i;
                    }
                    $i++;
                }

                if ($bestJ === -1 || $maxEnd <= $currentEnd) {
                    $sparsifyOk = false;
                    break;
                }

                $idx = $full[$bestJ];
                $this->ends[$idx] = -abs($this->ends[$idx]);
                $c++;
                $currentEnd = $maxEnd;
            }

            if (!$sparsifyOk || $currentEnd < $end_ts) {
                foreach ($full as $idx) {
                    $this->ends[$idx] = -abs($this->ends[$idx]);
                }
                $c = $fc;
            }
            return $c;
        }

        // ------------------------------------------------------------------
        // Clause 2 – max-coverage / min-cardinality (allows gaps)
        // ------------------------------------------------------------------
        $selected = [];
        $i = $left;
        $current = $start_ts;

        while ($current < $end_ts && $i < $right) {
            // Among intervals that cover $current (start ≤ current < end),
            // pick farthest end; token is a tie-breaker.
            $maxEnd = $current;
            $best = -1;
            $bestNon = 1;
            $j = $i;
            while ($j < $right && $this->starts[$j] <= $current) {
                $e = $this->ends[$j];
                if ($e > $current) {          // actually covers current
                    $non = ($token !== null && ($this->data[$j]['tkn'] ?? null) !== $token) ? 1 : 0;
                    if ($e > $maxEnd || ($e === $maxEnd && $non < $bestNon)) {
                        $maxEnd = $e;
                        $best = $j;
                        $bestNon = $non;
                    }
                }
                $j++;
            }

            if ($best >= 0 && $maxEnd > $current) {
                // extend continuous coverage
                $selected[] = $best;
                $current = $maxEnd;
                $i = $best + 1;
                continue;
            }

            // No interval covers $current → skip gap.
            // Jump to the earliest start of any still-valid interval
            // that intersects the remaining query (current, end_ts).
            $jump = null;
            while ($i < $right) {
                $e = $this->ends[$i];
                if ($e >= 0 && $e > $current && $this->starts[$i] < $end_ts) {
                    $jump = $this->starts[$i];
                    break;
                }
                $i++;
            }
            if ($jump === null) {
                break;                      // nothing left that can cover more
            }
            // skip the uncoverable hole; do not select yet
            $current = max($current, $jump);
        }

        foreach ($selected as $idx) {
            $this->ends[$idx] = -abs($this->ends[$idx]);
        }
        return count($selected);
    }

    /**
     * Binary search:
     * first starts[i] >= $value
     */
    private function lowerBoundStart(int $value): int
    {
        $lo = 0;
        $hi = count($this->starts);

        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;

            if ($this->starts[$mid] < $value) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $lo;
    }

    /**
     * Binary search:
     * first maxEnds[i] > $value
     * Only searches [0, $right).
     */
    private function firstMaxEndGreaterThan(int $value, int $right): int
    {
        $lo = 0;
        $hi = $right;

        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;

            if ($this->maxEnds[$mid] <= $value) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $lo;
    }

    public function compact(): void
    {
        $newStarts = [];
        $newEnds = [];
        $newData = [];

        foreach ($this->starts as $i => $start) {
            if ($this->ends[$i] >= 0) {
                $newStarts[] = $start;
                $newEnds[] = $this->ends[$i];
                $newData[] = $this->data[$i];
            }
        }

        $this->starts = $newStarts;
        $this->ends = $newEnds;
        $this->data = $newData;

        // rebuild maxEnds (same logic as build())
        $count = count($this->starts);
        $this->maxEnds = [];
        $maxEnd = PHP_INT_MIN;
        for ($i = 0; $i < $count; ++$i) {
            if ($this->ends[$i] > $maxEnd) {
                $maxEnd = $this->ends[$i];
            }
            $this->maxEnds[$i] = $maxEnd;
        }
    }

    /**
     * True iff an interval [start, end) is still present and not invalidated.
     */
    public function hasExactInterval(int $start, int $end): bool
    {
        if ($end <= $start || !$this->starts) {
            return false;
        }

        // first index with starts[i] >= $start
        $i = $this->lowerBoundStart($start);
        $n = count($this->starts);

        // scan the run of equal starts (secondary key is end)
        while ($i < $n && $this->starts[$i] === $start) {
            $e = $this->ends[$i];
            if ($e === $end) {          // exact match and still valid (positive)
                return true;
            }
            if ($e >= 0 && $e > $end) {
                // later ends only get larger → no match possible
                break;
            }
            $i++;
        }
        return false;
    }

    public function dump()
    {
        return [
            $this->starts,
            $this->ends,
            $this->maxEnds,
            $this->data
        ];
    }
}