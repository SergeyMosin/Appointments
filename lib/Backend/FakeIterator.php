<?php


namespace OCA\Appointments\Backend;


class FakeIterator implements \Iterator{


    private $is_valid=true;
    private $evt;
    /** @var \DateTimeImmutable $s_dt */
    private $s_dt;

    /**
     * FakeIterator constructor.
     * @param \Sabre\VObject\Component\VEvent $evt
     * @param \DateTimeZone $tz Reference timezone for floating dates and times.
     */
    public function __construct($evt, $tz){
        $this->evt=$evt;
        $this->s_dt=$evt->DTSTART->getDateTime($tz);
    }

    /**
     * This method returns the end date event.
     * @return \DateTimeImmutable
     */
    function getDtStart() {
        return $this->s_dt;
    }

    function getEventObject(){
        return $this->evt;
    }

    /**
     * This method returns the end date event.
     * @return \DateTimeImmutable
     */
    function getDtEnd(){
        if (isset($this->evt->DTEND)) {
            return $this->evt->DTEND->getDateTime($this->s_dt->getTimezone());
        } elseif (isset($evt->DURATION)) {
            return $this->s_dt->add($evt->DURATION->getDateInterval());
        } else {
            // Maybe throw ???
            return $this->s_dt;
        }
    }

    public function next(){$this->is_valid=false;}
    public function valid(){return $this->is_valid;}
    public function current(){return 0;}
    public function key(){return 0;}
    public function rewind(){}
}