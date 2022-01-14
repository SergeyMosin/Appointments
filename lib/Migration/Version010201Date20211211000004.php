<?php


declare(strict_types=1);

namespace OCA\Appointments\Migration;

use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010201Date20211211000004 extends SimpleMigrationStep
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

        if (!$schema->hasTable(BackendUtils::SYNC_TABLE_NAME)) {
            $table = $schema->createTable(BackendUtils::SYNC_TABLE_NAME);

            $table->addColumn('id', 'bigint', [
                 'notnull' => true,
                'length' => 11,
                'unsigned' => true,
            ]);
            $table->addColumn('lastsync', 'integer', [
                'notnull' => true,
                'default' => 0,
                'unsigned' => true,
            ]);
            $table->addColumn('synctoken', 'integer', [
                'notnull' => true,
                'default' => 1,
                'length' => 10,
                'unsigned' => true,
            ]);

            $table->setPrimaryKey(['id']);
        }

        return $schema;
    }

}
