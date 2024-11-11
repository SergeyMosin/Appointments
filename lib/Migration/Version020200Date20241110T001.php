<?php


declare(strict_types=1);

namespace OCA\Appointments\Migration;

use OCA\Appointments\Backend\BackendUtils;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020200Date20241110T001 extends SimpleMigrationStep
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
            $schema->dropTable(BackendUtils::PREF_TABLE_NAME);
        }
        return $schema;
    }
}
