<?php


declare(strict_types=1);

namespace OCA\Appointments\Migration;

use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010201Date20201227000006 extends SimpleMigrationStep
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

        if ($schema->hasTable(BackendUtils::PREF_TABLE_NAME)) {
            $table = $schema->getTable(BackendUtils::PREF_TABLE_NAME);

            if($table->hasIndex('user_index')){
                $table->dropIndex('user_index');
                $table->addUniqueIndex(['user_id'], BackendUtils::PREF_TABLE_NAME . '_user_index');
            }
        }

        return $schema;
    }

}
