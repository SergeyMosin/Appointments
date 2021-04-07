<?php

namespace OCA\Appointments\IntervalTree;


class AVLIntervalNode{
    public $l; // low - also key
    public $h; // high
    public $m; // max
    /** @var AVLIntervalNode[] */
    public $next;
    public $longer;

    /**
     * @param int $l low
     * @param int $h high
     */
    public function __construct($l, $h)
    {
        $this->l = $l;
        $this->h = $h;
        $this->m = $h;
        $this->next = [null,null];
        $this->longer = -1;
    }
}
