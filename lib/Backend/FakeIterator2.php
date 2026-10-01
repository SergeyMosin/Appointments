<?php


namespace OCA\Appointments\Backend;


class FakeIterator2 implements \Iterator
{
    private bool $is_valid = false;
    private \Sabre\VObject\Component\VEvent $evt;
    private \DateTimeImmutable $s_dt;
    private \DateTimeZone $tz;

    public function __construct(\DateTimeZone $tz)
    {
        $this->tz = $tz;
    }

    public function setEvt(\Sabre\VObject\Component\VEvent $evt): void
    {
        $this->evt = $evt;
        $this->s_dt = $evt->DTSTART->getDateTime($this->tz);
        $this->is_valid = true;
    }

    function getDtStart(): \DateTimeImmutable
    {
        return $this->s_dt;
    }

    function getEventObject(): \Sabre\VObject\Component\VEvent
    {
        return $this->evt;
    }

    function getDtEnd(): \DateTimeImmutable
    {
        if (isset($this->evt->DTEND)) {
            return $this->evt->DTEND->getDateTime($this->s_dt->getTimezone());
        } elseif (isset($evt->DURATION)) {
            return $this->s_dt->add($evt->DURATION->getDateInterval());
        } else {
            // Maybe throw ???
            return $this->s_dt;
        }
    }

    public function next()
    {
        $this->is_valid = false;
    }

    public function valid()
    {
        return $this->is_valid;
    }

    public function current()
    {
        return 0;
    }

    public function key()
    {
        return 0;
    }

    public function rewind()
    {
    }
}