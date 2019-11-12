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

require_once __DIR__ . '/../../vendor/autoload.php';

use OAT\Library\DBALSpanner\Tests\Integration\EchoLogger;
use OAT\Library\DBALSpanner\Tests\Integration\SpannerDatabaseInstanceManager;

$instanceName = 'tao-curgen-inst';
$databaseName = 'julien-dbal-driver-tests';

$logger = new EchoLogger();
$spanner = new SpannerDatabaseInstanceManager();
$spanner->setLogger($logger);

if (isset($argv[1])) {
    switch ($argv[1]) {
        case 'start':
//            $spanner->createInstance($instanceName);

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
                'CREATE TABLE ' . TransactionalTest::TABLE_NAME . ' (
                        id INT64 NOT NULL,
                        consumed BOOL NOT NULL,
                        value STRING(255) NOT NULL
                    )
                    PRIMARY KEY (id)',
            ]);

            break;

        case 'stop':
//            $spanner->deleteInstance($instanceName);
            break;

        case 'list':
            break;
    }
}

$logger->info($spanner->getResourcesStatus($instanceName));
