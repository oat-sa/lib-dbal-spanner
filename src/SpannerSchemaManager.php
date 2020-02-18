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
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

class SpannerSchemaManager extends AbstractSchemaManager
{
    public function listDatabases()
    {
        return $this->_conn->getDriver()->listDatabases();
    }

    public function createDatabase($databaseName): void
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');
        $length = strtok('(), ');

        $type = $this->_platform->getDoctrineTypeMapping($dbType);

        $options = [
            'length' => $length,
            'notnull' => $tableColumn['null'] === 'NO',
        ];

        return new Column($tableColumn['field'], Type::getType($type), $options);
    }

    protected function _getPortableTableDefinition($table)
    {
        return $table['table_name'];
    }

    protected function _getPortableTableIndexesList($tableIndexes, $tableName = null)
    {
        $convertedIndexes = [];
        foreach ($tableIndexes as $k => $v) {
            $v = array_change_key_case($v, CASE_LOWER);
            $v['primary'] = ($v['key_name'] === 'PRIMARY_KEY');
            $v['length'] = null;
            $convertedIndexes[] = $v;
        }

        return parent::_getPortableTableIndexesList($convertedIndexes, $tableName);
    }
}
