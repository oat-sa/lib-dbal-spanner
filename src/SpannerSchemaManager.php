<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

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
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }
}
