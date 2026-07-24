<?php

namespace OCA\Appointments\Tests\Unit;

use OCA\Appointments\Backend\ExternalSlotCapacity;
use OCA\Appointments\IntervalTree\AVLIntervalTree;
use PHPUnit\Framework\TestCase;

class ExternalSlotCapacityTest extends TestCase
{
    public function testOneClaimConsumesOnlyOneOfOneHundredDuplicateSlots(): void
    {
        $capacity = new ExternalSlotCapacity();
        for ($i = 0; $i < 100; $i++) {
            $capacity->addSource("slot-$i.ics", 1000, 2000);
        }

        $this->assertTrue($capacity->claim('slot-0.ics', 1000, 2000));

        $available = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($capacity->isAvailable("slot-$i.ics", 1000, 2000, null)) {
                $available++;
            }
        }

        $this->assertSame(99, $available);
    }

    public function testSameSourceRecurrenceInstancesAreIndependent(): void
    {
        $capacity = new ExternalSlotCapacity();
        $capacity->addSource('weekly.ics', 1000, 2000);
        $capacity->addSource('weekly.ics', 3000, 4000);

        $this->assertTrue($capacity->claim('weekly.ics', 1000, 2000));
        $this->assertFalse($capacity->isAvailable('weekly.ics', 1000, 2000, null));
        $this->assertTrue($capacity->isAvailable('weekly.ics', 3000, 4000, null));
    }

    public function testUnknownOrMovedClaimFallsBackToBlockerClassification(): void
    {
        $capacity = new ExternalSlotCapacity();
        $capacity->addSource('slot.ics', 1000, 2000);

        $this->assertFalse($capacity->claim('slot.ics', 1100, 2100));
        $this->assertFalse($capacity->claim('missing.ics', 1000, 2000));
    }

    public function testNormalBusyIntervalBlocksEveryDuplicateSlot(): void
    {
        $capacity = new ExternalSlotCapacity();
        $capacity->addSource('slot-a.ics', 1000, 2000);
        $capacity->addSource('slot-b.ics', 1000, 2000);
        $busyTree = null;
        (new AVLIntervalTree())->insert($busyTree, 900, 2100);

        $this->assertFalse($capacity->isAvailable('slot-a.ics', 1000, 2000, $busyTree));
        $this->assertFalse($capacity->isAvailable('slot-b.ics', 1000, 2000, $busyTree));
    }

    public function testDestinationClaimsOnlyItsMatchingDuplicate(): void
    {
        $capacity = new ExternalSlotCapacity();
        $capacity->addSource('slot-a.ics', 1000, 2000);
        $capacity->addSource('slot-b.ics', 1000, 2000);

        $this->assertTrue($capacity->registerDestination('slot-a.ics', 1000, 2000));
        $this->assertFalse($capacity->isAvailable('slot-a.ics', 1000, 2000, null));
        $this->assertTrue($capacity->isAvailable('slot-b.ics', 1000, 2000, null));
        $this->assertFalse($capacity->registerDestination(null, 1000, 2000));
        $this->assertFalse($capacity->registerDestination('slot-a.ics', 1100, 2100));
    }

    public function testClaimedOccurrenceIsABookingConflict(): void
    {
        $capacity = new ExternalSlotCapacity();
        $capacity->addSource('slot.ics', 1000, 2000);

        $this->assertFalse($capacity->hasBookingConflict('slot.ics', 1000, 2000, null));
        $this->assertTrue($capacity->claim('slot.ics', 1000, 2000));
        $this->assertTrue($capacity->hasBookingConflict('slot.ics', 1000, 2000, null));
        $this->assertTrue($capacity->hasBookingConflict('missing.ics', 1000, 2000, null));
    }
}
