<?php
declare(strict_types=1);

namespace OCA\Appointments\Migration;

use Doctrine\DBAL\Types\Type;
use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010201Date20201227000005 extends SimpleMigrationStep {

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

            $table->addColumn('id', Type::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 11,
                'unsigned' => true,
            ]);
            $table->addColumn('uid', Type::STRING, [
                'notnull' => true,
                'length' => 127
            ]);
            //20200413.141317
            $table->addColumn('hash', Type::STRING, [
                'notnull' => true,
                'length' => 32
            ]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uid'], BackendUtils::HASH_TABLE_NAME.'_uid_index');


        }

        if (!$schema->hasTable(BackendUtils::PREF_TABLE_NAME)) {
            $table = $schema->createTable(BackendUtils::PREF_TABLE_NAME);

            $table->addColumn('id', Type::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('user_id', Type::STRING, [
                'notnull' => true,
                'length' => 64
            ]);


            $table->addColumn(BackendUtils::KEY_ORG,Type::STRING,[
                'notnull' => false,
                'length' => 512
            ]);
            $table->addColumn(BackendUtils::KEY_CLS,Type::STRING,[
                'notnull' => false,
                'length' => 512
            ]);
            $table->addColumn(BackendUtils::KEY_DIR,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_EML,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_FORM_INPUTS_HTML,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_FORM_INPUTS_JSON,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_MPS_COL,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_PAGES,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_PSN,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_TMPL_DATA,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_TMPL_INFO,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);

            $table->addColumn(BackendUtils::KEY_TALK,Type::TEXT,[
                'notnull' => false,
                'length' => 32768
            ]);


            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'],'user_index');
        }




        return $schema;
    }

}
