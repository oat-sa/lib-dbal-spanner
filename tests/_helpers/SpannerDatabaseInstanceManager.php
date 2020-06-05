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

namespace OAT\Library\DBALSpanner\Tests\_helpers;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\SpannerClient;
use LogicException;
use Psr\Log\LoggerAwareTrait;

class SpannerDatabaseInstanceManager
{
    use LoggerAwareTrait;
    use ConfigurationTrait;

    /**
     * @var string
     */
    private $projectName;

    /**
     * @var SpannerClient
     */
    private $client;

    public function __construct()
    {
        $keyFile = json_decode(file_get_contents($this->getConfiguration(Configuration::CONFIG_KEY_FILE_PATH)), true);
        $this->projectName = $keyFile['project_id'];

        try {
            $this->client = new SpannerClient(['keyFile' => $keyFile]);
        } catch (GoogleException $exception) {
            throw new LogicException('gRPC extension is not installed or enabled.');
        }
    }

    public function listInstances(): array
    {
        $instances = [];

        foreach ($this->client->instances() as $instance) {
            if ($instance instanceof Instance) {
                $instances[] = basename($instance->name());
            }
        }

        return $instances;
    }

    public function createInstance(string $instanceName, string $configurationName = null): Instance
    {
        $configurationName = $configurationName ?? $this->getConfiguration(Configuration::CONFIG_INSTANCE_REGION);

        if (!in_array($instanceName, $this->listInstances())) {
            $this->logger->info(
                sprintf("Creating instance '%s' on project '%s'..." . PHP_EOL, $instanceName, $this->projectName)
            );
            $this->client->createInstance($this->client->instanceConfiguration($configurationName), $instanceName);
        }

        return $this->getInstance($instanceName);
    }

    public function deleteInstance(string $instanceName): bool
    {
        if (!in_array($instanceName, $this->listInstances())) {
            $this->logger->info(
                sprintf(
                    "Instance '%s' does not exist on project '%s'." . PHP_EOL,
                    $instanceName,
                    $this->projectName
                )
            );

            return false;
        }

        $this->logger->info(
            sprintf(
                "Deleting instance '%s' on project '%s'..." . PHP_EOL,
                $instanceName,
                $this->projectName
            )
        );

        $instance = $this->getInstance($instanceName);
        $instance->delete();

        return true;
    }

    public function getInstance(string $instanceName): Instance
    {
        return $this->client->instance($instanceName);
    }

    public function listDatabases(string $instanceName): array
    {
        $databases = [];

        foreach ($this->getInstance($instanceName)->databases() as $database) {
            if ($database instanceof Database) {
                $databases[] = basename($database->name());
            }
        }

        return $databases;
    }

    public function createDatabase(string $instanceName, string $databaseName, array $statements = []): void
    {
        $database = $this->getInstance($instanceName)->database($databaseName);

        if (!$database->exists()) {
            $this->logger->info(
                sprintf(
                    "Creating database '%s' on instance '%s'..." . PHP_EOL,
                    $databaseName,
                    $instanceName
                )
            );

            $operation = $database->create(['statements' => $statements]);
            $operation->pollUntilComplete();
        }
    }

    public function createTables(string $instanceName, string $databaseName, array $statements = []): void
    {
        $database = $this->getInstance($instanceName)->database($databaseName);

        $operation = $database->updateDdlBatch($statements);

        $operation->pollUntilComplete();
    }

    public function getResourcesStatus(string $instanceName): string
    {
        $str = sprintf("Existing instances on project '%s':" . PHP_EOL, $this->projectName)
            . implode(PHP_EOL, $this->listInstances()) . PHP_EOL;

        if (in_array($instanceName, $this->listInstances())) {
            $str .= sprintf("Existing databases on instance '%s':" . PHP_EOL, $instanceName)
                . implode(PHP_EOL, $this->listDatabases($instanceName)) . PHP_EOL;
        }

        return $str;
    }
}
