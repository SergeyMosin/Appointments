<?php


namespace OCA\Appointments\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UpdateHook implements IRepairStep {

    public function getName(){
        return 'Update hook for Appointments app';
    }

    public function run(IOutput $output)
    {
        $output->info("appointments UpdateHook finished");
    }
}
