<?php


declare(strict_types=1);

namespace OCA\Appointments\Migration;

use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020008Date20240424T001 extends SimpleMigrationStep
{
    /**
     * @param IOutput $output
     * @param \Closure $schemaClosure
     * @param array $options
     * @return ISchemaWrapper|null
     */
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options)
    {

        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable(BackendUtils::HASH_TABLE_NAME)) {
            $table = $schema->getTable(BackendUtils::HASH_TABLE_NAME);

            if(!$table->hasColumn('appt_doc')){
                $table->addColumn('appt_doc', 'binary',[
                    'notnull' => false,
                    'length' => 32768
                ]);
            }
        }

        return $schema;
    }
}
