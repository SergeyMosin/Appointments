<?php
declare(strict_types=1);

namespace OCA\Appointments\Migration;

use Doctrine\DBAL\Types\Type;
use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010201Date20200404181716 extends SimpleMigrationStep {

    /**
     * @param IOutput $output
     * @param \Closure $schemaClosure
     * @param array $options
     * @return ISchemaWrapper|null
     */
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options){

        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable(BackendUtils::HASH_TABLE_NAME)) {
            $table = $schema->createTable(BackendUtils::HASH_TABLE_NAME);

            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 11,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', 'string', [
                'notnull' => true,
                'length' => 127
            ]);
            //20200413.141317
            $table->addColumn('hash', 'string', [
                'notnull' => true,
                'length' => 32
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid'], BackendUtils::HASH_TABLE_NAME.'_uid_index');

            return $schema;
        }
        return null;
    }

}


