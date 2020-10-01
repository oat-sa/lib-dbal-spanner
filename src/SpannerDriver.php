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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use Google\Auth\Cache\SysVCacheItemPool;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Lock\SemaphoreLock;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\Session\CacheSessionPool;
use Google\Cloud\Spanner\Session\SessionPoolInterface;
use Google\Cloud\Spanner\SpannerClient;
use LogicException;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;

class SpannerDriver implements Driver
{
    public const DRIVER_NAME = 'gcp-spanner';
    public const DRIVER_OPTION_AUTH_POOL = 'driver-option-auth-pool';
    public const DRIVER_OPTION_SESSION_POOL = 'driver-option-session-pool';
    public const DRIVER_OPTION_CLIENT_CONFIGURATION = 'driver-option-client-configuration';
    public const DRIVER_OPTION_CREDENTIALS_FILE_PATH = 'driver-option-credentials-file-path';

    private const SESSIONS_MIN = 1;
    private const SESSIONS_MAX = 100;

    /** @var Instance */
    private $instance;

    /** @var string */
    private $instanceName;

    /** @var string */
    private $databaseName;

    /** @var SpannerClientFactory */
    private $spannerClientFactory;

    /** @var SpannerConnection */
    private $connection;

    /** @var SessionPoolInterface */
    private $sessionPool;

    /** @var array */
    private $driverOptions;

    /** @var SpannerClient */
    private $spannerClient;

    public function __construct(
        SpannerClientFactory $spannerClientFactory = null,
        SessionPoolInterface $sessionPool = null
    ) {
        $this->spannerClientFactory = $spannerClientFactory;
        $this->sessionPool = $sessionPool;
        $this->driverOptions = [];
    }

    /**
     * @inheritdoc
     *
     * @throws LogicException When a parameter is missing or ext/grpc is missing or the instance does not exist.
     * @throws GoogleException When ext/grpc is missing.
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
        $this->driverOptions = $driverOptions;

        [$this->instanceName, $this->databaseName] = $this->parseParameters($params);

        $this->instance = $this->getInstance($this->instanceName);

        $cacheSessionPool = $this->getSessionPool();

        $database = $this->selectDatabase(
            $this->databaseName,
            [
                'sessionPool' => $cacheSessionPool
            ]
        );

        /**
         * Unfortunately Google's default implementation does not respect the interface `SessionPoolInterface`
         *
         * @TODO Remove this as soon as the major version will be released
         */
        if ($cacheSessionPool instanceof CacheSessionPool) {
            $cacheSessionPool->warmup();
        }

        $this->connection = new SpannerConnection($this, $database);

        return $this->connection;
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

    public function transactional(Closure $closure)
    {
        if (!$this->connection instanceof SpannerConnection) {
            throw new DBALException('Can not run transaction without connecting first.');
        }

        return $this->connection->transactional($closure);
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
            $instance = $this->getSpannerClient()->instance($instanceName);

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

    private function getSessionPool(): ?SessionPoolInterface
    {
        if ($this->sessionPool !== null) {
            return $this->sessionPool;
        }

        if (array_key_exists(self::DRIVER_OPTION_SESSION_POOL, $this->driverOptions)) {
            $this->sessionPool = $this->driverOptions[self::DRIVER_OPTION_SESSION_POOL];

            return $this->sessionPool;
        }

        /**
         * @deprecated This method should be avoided and dependency should be always passed
         */
        $this->sessionPool = new CacheSessionPool(
            new SysVCacheItemPool(['proj' => 'B']),
            [
                'lock' => new SemaphoreLock(65535),
                'minSessions' => self::SESSIONS_MIN,
                'maxSessions' => self::SESSIONS_MAX,
            ]
        );

        return $this->sessionPool;
    }

    private function getSpannerClientFactory(): SpannerClientFactory
    {
        if ($this->spannerClientFactory === null) {
            $this->spannerClientFactory = new SpannerClientFactory(
                $this->driverOptions[self::DRIVER_OPTION_AUTH_POOL] ?? null,
                $this->driverOptions[self::DRIVER_OPTION_CLIENT_CONFIGURATION] ?? null,
                $this->driverOptions[self::DRIVER_OPTION_CREDENTIALS_FILE_PATH] ?? null
            );
        }

        return $this->spannerClientFactory;
    }

    /**
     * @throws GoogleException
     */
    private function getSpannerClient(): SpannerClient
    {
        if ($this->spannerClient === null) {
            $this->spannerClient = $this->getSpannerClientFactory()->create();
        }

        return $this->spannerClient;
    }
}
