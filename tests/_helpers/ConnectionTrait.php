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

namespace OAT\Library\DBALSpanner\Tests\Integration\_helpers;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use OAT\Library\DBALSpanner\SpannerConnectionWrapper;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerPlatform;

trait ConnectionTrait
{
    /** @var Connection */
    private $connection;

    /**
     * @throws DBALException
     */
    public function getConnection(): Connection
    {
        if ($this->connection) {
            return $this->connection;
        }

        $connectionParams = [
            'dbname' => $this->getConfiguration(Configuration::CONFIG_DATABASE_NAME),
            'instance' => $this->getConfiguration(Configuration::CONFIG_INSTANCE_NAME),
            'wrapperClass' => SpannerConnectionWrapper::class,
            'driverClass' => SpannerDriver::class,
            'platform' => new SpannerPlatform(),
        ];

        $connection = DriverManager::getConnection($connectionParams);
        $connection->connect();

        return $this->connection = $connection;
    }

    abstract protected function getConfiguration(string $config);
}
