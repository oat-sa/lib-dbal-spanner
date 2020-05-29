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

use InvalidArgumentException;

class Configuration
{
    private const INI_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'env.ini';

    public const CONFIG_INSTANCE_NAME = 'instance_name';
    public const CONFIG_DATABASE_NAME = 'database_name';
    public const CONFIG_TRANSACTIONAL_TABLE_NAME = 'transactional_table_name';

    /** @var array */
    public $config = [];

    public function __construct()
    {
        $this->config = parse_ini_file(self::INI_FILE);
    }

    public function get(string $config)
    {
        if (!array_key_exists($config, $this->config)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Config %s does not exist',
                    $config
                )
            );
        }

        return $this->config[$config];
    }
}
