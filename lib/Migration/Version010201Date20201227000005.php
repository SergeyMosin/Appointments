<?php
declare(strict_types=1);

namespace OCA\Appointments\Migration;

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


        }

        if (!$schema->hasTable(BackendUtils::PREF_TABLE_NAME)) {
            $table = $schema->createTable(BackendUtils::PREF_TABLE_NAME);

            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64
            ]);


            $table->addColumn(BackendUtils::KEY_ORG, 'string',[
                'notnull' => false,
                'length' => 512
            ]);
            $table->addColumn(BackendUtils::KEY_CLS,'string',[
                'notnull' => false,
                'length' => 512
            ]);
            $table->addColumn(BackendUtils::KEY_DIR,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_EML,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_FORM_INPUTS_HTML,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_FORM_INPUTS_JSON,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_MPS_COL,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_PAGES,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_PSN,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_TMPL_DATA,'text',[
                'notnull' => false,
                'length' => 32768
            ]);
            $table->addColumn(BackendUtils::KEY_TMPL_INFO,'text',[
                'notnull' => false,
                'length' => 32768
            ]);

            $table->addColumn(BackendUtils::KEY_TALK,'text',[
                'notnull' => false,
                'length' => 32768
            ]);


            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['user_id'],BackendUtils::PREF_TABLE_NAME.'_user_index');
        }

        return $schema;
    }

}
