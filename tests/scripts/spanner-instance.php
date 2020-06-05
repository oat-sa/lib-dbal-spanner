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

require_once __DIR__ . '/../../vendor/autoload.php';

use OAT\Library\DBALSpanner\Tests\_helpers\Configuration;
use OAT\Library\DBALSpanner\Tests\_helpers\EchoLogger;
use OAT\Library\DBALSpanner\Tests\_helpers\SpannerDatabaseInstanceManager;

$configuration = new Configuration();
$spanner = new SpannerDatabaseInstanceManager();
$spanner->setLogger(new EchoLogger());

$option = $argv[1] ?? 'help';

switch ($option) {
    case 'create':
        $spanner->createInstance($configuration->get(Configuration::CONFIG_INSTANCE_NAME));
        $spanner->createDatabase(
            $configuration->get(Configuration::CONFIG_INSTANCE_NAME),
            $configuration->get(Configuration::CONFIG_DATABASE_NAME),
            include __DIR__ . '/../_resources/database_statements.php'
        );

        break;
    case 'delete':
        $spanner->deleteInstance($configuration->get(Configuration::CONFIG_INSTANCE_NAME));

        break;
    case 'migrate':
        $spanner->createTables(
            $configuration->get(Configuration::CONFIG_INSTANCE_NAME),
            $configuration->get(Configuration::CONFIG_DATABASE_NAME),
            include __DIR__ . '/../_resources/database_statements.php'
        );

        break;
    case 'status':
        echo $spanner->getResourcesStatus($configuration->get(Configuration::CONFIG_INSTANCE_NAME));

        break;
    default:
        echo PHP_EOL;
        echo 'options:';
        echo PHP_EOL;
        echo '    create: Create a new instance';
        echo PHP_EOL;
        echo '    delete: Delete instance';
        echo PHP_EOL;
        echo '    migrate: Create database tables';
        echo PHP_EOL;
        echo '    status: Get current instances status';
        echo PHP_EOL;
        echo PHP_EOL;
        break;
}
