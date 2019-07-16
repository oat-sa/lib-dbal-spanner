<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\SpannerClient;
use LogicException;

class SpannerDriver implements Driver
{
    public const DRIVER_NAME = 'gcp-spanner';

    /** @var Instance */
    private $instance;

    /** @var string */
    private $instanceName;

    /** @var string */
    private $databaseName;

    /**
     * @inheritdoc
     *
     * @throws LogicException When a parameter is missing or ext/grpc is missing or the instance does not exist.
     * @throws NotFoundException when database does not exist.
     * @throws \Doctrine\DBAL\DBALException
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        [$this->instanceName, $this->databaseName] = $this->parseParameters($params);

        $this->instance = $this->getInstance($this->instanceName);

        $connection = new SpannerConnection($params, $this);
        $connection->setDatabase($this->selectDatabase($this->databaseName));

        return $connection;
    }

    public function getDatabasePlatform()
    {
        return new SpannerPlatform();
    }

    public function getSchemaManager(Connection $conn)
    {
        return new SpannerSchemaManager($conn, new SpannerPlatform());
    }

    public function getName()
    {
        return self::DRIVER_NAME;
    }

    public function getDatabase(Connection $conn)
    {
        return $this->databaseName;
    }

    /**
     * Selects a database if it exists.
     *
     * @param string $databaseName
     *
     * @return Database
     * @throws NotFoundException when database does not exist.
     */
    public function selectDatabase(string $databaseName): Database
    {
        if (!in_array($databaseName, $this->listDatabases())) {
            throw new NotFoundException(
                sprintf("Database '%s' does not exist on instance '%s'.", $databaseName, $this->instance->name())
            );
        }

        return $this->instance->database($databaseName);
    }


    /**
     * Ensures that necessary parameters are provided.
     *
     * @param array $params
     *
     * @return array
     * @throws LogicException When a parameter is missing.
     */
    public function parseParameters(array $params): array
    {
        if (!isset($params['instance'])) {
            throw new LogicException("Missing parameter 'instance' to connect to Spanner instance.");
        }
        if (!isset($params['dbname'])) {
            throw new LogicException("Missing parameter 'dbname' to connect to Spanner database.");
        }

        return [$params['instance'], $params['dbname']];
    }

    /**
     * Returns a Spanner instance.
     *
     * @param string $instanceName
     *
     * @return Instance
     * @throws LogicException When ext/grpc is missing or the instance does not exist.
     */
    public function getInstance(string $instanceName): Instance
    {
        if ($this->instance === null) {
            try {
                $spanner = new SpannerClient();
            } catch (GoogleException $exception) {
                throw new LogicException('gRPC extension is not installed or enabled.');
            }

            $instance = $spanner->instance($instanceName);
            if (!$instance->exists()) {
                throw new LogicException(sprintf("Instance '%s' does not exist.", $instanceName));
            }

            $this->instance = $instance;
        }

        return $this->instance;
    }

    /**
     * Returns a list of database names existing on a Spanner instance.
     *
     * @return array|string[]
     */
    public function listDatabases(): array
    {
        $databaseList = [];
        foreach ($this->instance->databases() as $database) {
            if ($database instanceof Database) {
                $databaseList[] = basename($database->name());
            }
        }

        return $databaseList;
    }
}
