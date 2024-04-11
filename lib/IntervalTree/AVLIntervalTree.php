<?php
// https://neil.brown.name/blog/20041124101820

namespace OCA\Appointments\IntervalTree;

class AVLIntervalTree{
    /**
     * @param AVLIntervalNode $n
     * @return int
     */
    private function findNodeMax($n){
        $max=$n->h;
        $cn=$n->next[0];
        if($cn!==null && $cn->m > $max){
            $max=$cn->m;
        }
        $cn=$n->next[1];
        if($cn!==null && $cn->m > $max){
            $max=$cn->m;
        }
        return $max;
    }

    /**
     * @param AVLIntervalNode $path
     * @param int $dir
     * @return AVLIntervalNode
     */
    private function rotate_2(&$path,$dir){
        $b=$path;
        $d=$b->next[$dir];
        $c=$d->next[1-$dir];
        $e=$d->next[$dir];
        $path=$d;

        $d->next[1-$dir] = $b;
        $b->next[$dir] = $c;

        $b->longer = -1;
        $d->longer = -1;

        $b->m=$this->findNodeMax($b);
        $d->m=$this->findNodeMax($d);

        return $e;
    }

    /**
     * @param AVLIntervalNode $path
     * @param int $dir
     * @param int $third
     * @return AVLIntervalNode
     */
    private function rotate_3(&$path,$dir,$third){

        $B = $path;
        $F = $B->next[$dir];
        $D = $F->next[1-$dir];
        // node: C and E can be NULL
        $C = $D->next[1-$dir];
        $E = $D->next[$dir];
        $path = $D;
        $D->next[1-$dir] = $B;
        $D->next[$dir] = $F;
        $B->next[$dir] = $C;
        $F->next[1-$dir] = $E;
        $D->longer = -1;

        $B->m=$this->findNodeMax($B);
        $F->m=$this->findNodeMax($F);
        $D->m=$this->findNodeMax($D);

        // assume both trees are balanced
        $B->longer = $F->longer = -1;

        if ($third === -1) {
            return null;
        }

        if ($third === $dir) {
            // E holds the insertion so B is unbalanced
            $B->longer = 1-$dir;
            return $E;
        } else {
            // C holds the insertion so F is unbalanced
            $F->longer = $dir;
            return $C;
        }
    }

    /**
     * @param AVLIntervalNode $path
     * @param int $low
     */
    private function rebalance_path($path, $low){
        while ($path && $low !== $path->l) {
            $next_step = (int)($low > $path->l);
            $path->longer = $next_step;
            $path = $path->next[$next_step];
        }
    }


    /**
     * @param AVLIntervalNode $path
     * @param int $low
     */
    private function rebalance(&$path, $low){

        if($path->longer < 0){
            $this->rebalance_path($path,$low);
            return;
        }
        $first = (int)($low > $path->l);
        if($path->longer !== $first) {
            /* took the shorter path */
            $path->longer = -1;
            $this->rebalance_path($path->next[$first], $low);
            return;
        }

        $second = (int)($low > $path->next[$first]->l);
        if($first === $second){
           /* just a two-point rotate */
            $p = $this->rotate_2($path, $first);
            $this->rebalance_path($p, $low);
           return;
        }

        /* fine details of the 3 point rotate depend on the third step.
        * However there may not be a third step, if the third point of the
        * rotation is the newly inserted point.  In that case we record
        * the third step as NEITHER ( -1 )
        */
        $p = $path->next[$first]->next[$second];
        if($low === $p->l) $third = -1;
        else $third = (int)($low > $p->l);
        $p = $this->rotate_3($path, $first, $third);
        $this->rebalance_path($p, $low);
    }


    /**
     * @param AVLIntervalNode|null $tree
     * @param int $low
     * @param int $high
     * @return int
     */
    function insert(&$tree,$low,$high){
        $path_top=&$tree;

        while ($tree!==null && $tree->l!==$low){
            if($tree->longer >= 0){
                $path_top=&$tree;
            }
            if($tree->m < $high){
                $tree->m=$high;
            }
            $tree=&$tree->next[(int)($low>$tree->l)];
        }

        if($tree!==null){
            // already exists
            if($tree->h < $high){
                $tree->h=$high;
            }
            if($tree->m < $high){
                $tree->m=$high;
            }
            return 0;
        }

        $tree=new AVLIntervalNode($low,$high);

        if($path_top!==null) {
            $this->rebalance($path_top, $low);
        }

        return 1;
    }

    /**
     * @param AVLIntervalNode|null $tree
     * @param int $low
     * @param int $high
     * @return AVLIntervalNode|null null=no overlap
     */
    static function lookUp($tree, $low, $high){
        while ($tree!==null
            && ($tree->l >= $high || $low >= $tree->h)) {

            // If left child of root is present and max of left child is
            // greater than or equal to given interval, then i may
            // overlap with an interval is left subtree
            // Else interval can only overlap with right subtree

            // !($tree->next[0]!==null && $tree->next[0]->m >= $low );
            $tree = $tree->next[
                (int)($tree->next[0]===null || $tree->next[0]->m <= $low)];
        }
        return $tree;
    }
}


