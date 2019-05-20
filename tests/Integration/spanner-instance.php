<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Oat\DbalSpanner\Tests\Integration\SpannerDatabaseInstanceManager;

$instanceName = 'php-dbal-tests';
$databaseName = 'spanner-test';

$spanner = new SpannerDatabaseInstanceManager();

if (isset($argv[1])) {
    switch ($argv[1]) {
        case 'start':
            $spanner->createInstance($instanceName);

            $spanner->createDatabase($instanceName, $databaseName, [
                'CREATE TABLE statements (
                modelid INT64 NOT NULL,
                subject STRING(255) NOT NULL,
                predicate STRING(255) NOT NULL,
                object STRING(65535),
                l_language STRING(255),
                author STRING(255),
                epoch STRING(255)
            )
            PRIMARY KEY (subject, predicate, object, l_language)',
                'CREATE INDEX k_po ON statements (predicate, object)',
            ]);

            break;

        case 'stop':
            $spanner->deleteInstance($instanceName);
            break;

        case 'list':
            break;
    }
}

echo $spanner->getResourcesStatus($instanceName);
