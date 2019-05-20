<?php

declare(strict_types=1);

namespace Oat\DbalSpanner\Tests\Integration;

use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\SpannerClient;
use LogicException;

class SpannerDatabaseInstanceManager
{
    private const KEY_FILE_ENV_VARIABLE = 'GOOGLE_APPLICATION_CREDENTIALS';
    private const CONFIGURATION_NAME = 'regional-europe-west1';

    /** @var string */
    private $projectName;

    /** @var SpannerClient */
    private $client;

    public function __construct()
    {
        $keyFile = json_decode(file_get_contents(getenv(self::KEY_FILE_ENV_VARIABLE)), true);
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

    public function createInstance(string $instanceName, string $configurationName = self::CONFIGURATION_NAME)
    {
        if (!in_array($instanceName, $this->listInstances())) {
            echo sprintf('Creating instance %s on project %s:...' . PHP_EOL, $instanceName, $this->projectName);
            $this->client->createInstance($this->client->instanceConfiguration($configurationName), $instanceName);
        }

        return $this->getInstance($instanceName);
    }

    public function deleteInstance(string $instanceName): bool
    {
        if (!in_array($instanceName, $this->listInstances())) {
            echo sprintf('Instance %s does not exist on project %s:...' . PHP_EOL, $instanceName, $this->projectName);
            return false;
        }

        echo sprintf('Deleting instance %s on project %s:...' . PHP_EOL, $instanceName, $this->projectName);
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

    public function createDatabase(string $instanceName, string $databaseName, array $statements = [])
    {
        $database = $this->getInstance($instanceName)->database($databaseName);
        if (!$database->exists()) {
            echo sprintf('Creating database %s on instance %s...' . PHP_EOL, $databaseName, $instanceName);
            $operation = $database->create(['statements' => $statements]);
            $operation->pollUntilComplete();
        }
    }

    public function getResourcesStatus(string $instanceName): string
    {
        $str = sprintf('Existing instances on project %s:' . PHP_EOL, $this->projectName)
            . implode(PHP_EOL, $this->listInstances()) . PHP_EOL;

        if (in_array($instanceName, $this->listInstances())) {
            $str .= sprintf('Existing databases on instance %s:' . PHP_EOL, $instanceName)
                . implode(PHP_EOL, $this->listDatabases($instanceName)) . PHP_EOL;
        }

        return $str;
    }
}
