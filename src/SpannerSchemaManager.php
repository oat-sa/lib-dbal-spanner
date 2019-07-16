<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class SpannerSchemaManager extends AbstractSchemaManager
{
    /** @var SpannerDriver */
    protected $driver;

    /**
     * Constructor. Accepts the Connection instance to manage the schema for.
     */
    public function __construct(Connection $conn, ?AbstractPlatform $platform = null)
    {
        parent::__construct($conn, $platform);
        $this->driver = $conn->getDriver();
    }

    public function listDatabases()
    {
        return $this->driver->listDatabases();
    }

    public function createDatabase($databaseName): Void
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');
        $length = strtok('(), ');

        $type = $this->_platform->getDoctrineTypeMapping($dbType);

        $options = [
            'length' => $length,
            'notnull' => $tableColumn['null'] === 'NO',
        ];

        return new Column($tableColumn['field'], Type::getType($type), $options);
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['table_name'];
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $convertedIndexes = [];
        foreach ($tableIndexes as $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            $v['primary'] = ($v['key_name'] === 'PRIMARY_KEY');
            $v['length'] = null;
            $convertedIndexes[] = $v;
        }

        return parent::_getPortableTableIndexesList($convertedIndexes, $tableName);
    }
}
