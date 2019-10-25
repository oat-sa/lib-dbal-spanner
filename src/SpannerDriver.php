<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2019 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use LogicException;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;

class SpannerDriver implements Driver
{
    public const DRIVER_NAME = 'gcp-spanner';

    /** @var Instance */
    private $instance;

    /** @var string */
    private $instanceName;

    /** @var string */
    private $databaseName;

    /** @var SpannerClientFactory */
    private $spannerClientFactory;

    /**
     * SpannerDriver constructor.
     *
     * @param SpannerClientFactory $spannerClientFactory
     */
    public function __construct(SpannerClientFactory $spannerClientFactory = null)
    {
        if ($spannerClientFactory === null) {
            $spannerClientFactory = new SpannerClientFactory();
        }
        $this->spannerClientFactory = $spannerClientFactory;
    }

    /**
     * @inheritdoc
     *
     * @throws LogicException When a parameter is missing or ext/grpc is missing or the instance does not exist.
     * @throws GoogleException When ext/grpc is missing.
     * @throws NotFoundException when database does not exist.
     * @throws DBALException
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        [$this->instanceName, $this->databaseName] = $this->parseParameters($params);
        $this->instance = $this->getInstance($this->instanceName);

        $cacheSessionPool = $this->spannerClientFactory->createCacheSessionPool();
        $database = $this->selectDatabase($this->databaseName, ['sessionPool' => $cacheSessionPool]);
        $cacheSessionPool->setDatabase($database);
        $cacheSessionPool->warmup();

        return new SpannerConnection($this, $database);
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
     * @param array $options connection options containing for example a sessionPool
     *
     * @return Database
     */
    public function selectDatabase(string $databaseName, array $options): Database
    {
        return $this->instance->database($databaseName, $options);
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
     * @throws GoogleException When ext/grpc is missing.
     * @throws LogicException When the instance does not exist.
     */
    public function getInstance(string $instanceName): Instance
    {
        if ($this->instance === null) {
            $spannerClient = $this->spannerClientFactory->create();
            $instance = $spannerClient->instance($instanceName);
            if (!$instance->exists()) {
                throw new LogicException(sprintf("Instance '%s' does not exist.", $instanceName));
            }

            $this->instanceName = $instanceName;
            $this->instance = $instance;
        }

        return $this->instance;
    }

    /**
     * Returns a list of database names existing on a Spanner instance.
     *
     * @param string $instanceName
     *
     * @return array|string[]
     * @throws GoogleException
     */
    public function listDatabases(string $instanceName = ''): array
    {
        if ($instanceName === '') {
            $instanceName = $this->instanceName;
        }
        $databaseList = [];
        foreach ($this->getInstance($instanceName)->databases() as $database) {
            if ($database instanceof Database) {
                $databaseList[] = basename($database->name());
            }
        }
        return $databaseList;
    }
}
