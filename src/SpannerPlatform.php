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

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * The SpannerPlatform provides the behavior, features and SQL dialect of the Spanner database platform.
 */
class SpannerPlatform extends AbstractPlatform
{
    public function getName()
    {
        return SpannerDriver::DRIVER_NAME;
    }

    public function getBooleanTypeDeclarationSQL(array $columnDef)
    {
        return 'BOOL';
    }

    public function getIntegerTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    public function getListTablesSQL()
    {
        return 'SELECT table_name
      FROM information_schema.tables
      WHERE table_catalog = \'\' 
      AND table_schema = \'\'';
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        return 'SELECT column_name AS Field, spanner_type AS Type, is_nullable AS `Null`, "" AS `Key`, "" AS `Default`, "" AS Extra, "" AS Comment 
      FROM information_schema.columns
      WHERE table_name = "' . $table . '"
      AND table_catalog = "" 
      AND table_schema = ""
      ORDER BY ordinal_position';
    }

    public function getListTableIndexesSQL($table, $currentDatabase = null)
    {
        return 'SELECT is_unique AS Non_Unique, i.index_name AS Key_name, column_name AS Column_Name, "" AS Sub_Part, i.index_type AS Index_Type 
      FROM information_schema.indexes i
      INNER JOIN information_schema.index_columns ic
              ON i.table_name = ic.table_name
      WHERE i.table_name = "' . $table . '"
      AND i.table_catalog = "" 
      AND i.table_schema = ""
      ORDER BY ordinal_position';
    }

    public function supportsForeignKeyConstraints()
    {
        return false;
    }

    public function getBigIntTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    public function getSmallIntTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    public function getDateTimeTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'TIMESTAMP';
    }

    public function getDateTimeFormatString()
    {
        return 'Y-m-d\TH:i:s.u\Z';
    }

    public function getDateTimeTzFormatString()
    {
        return 'Y-m-d\TH:i:s.u\Z';
    }

    public function getDateTypeDeclarationSQL(array $fieldDeclaration)
    {
        return 'DATE';
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef)
    {
        if (!empty($columnDef['autoincrement'])) {
            throw new DBALException('AUTO_INCREMENT is not supported by GCP Spanner.');
        }

        return 'INT64';
    }

    public function getFloatDeclarationSQL(array $fieldDeclaration)
    {
        return 'FLOAT64';
    }

    protected function initializeDoctrineTypeMappings()
    {
        $this->doctrineTypeMapping = [
            'array' => '',
            'bool' => 'boolean',
            'bytes' => 'blob',
            'date' => 'date',
            'float64' => 'float',
            'int64' => 'integer',
            'string' => 'string',
            'struct' => '',
            'timestamp' => 'datetime',
        ];
    }

    public function getClobTypeDeclarationSQL(array $field)
    {
        return 'BYTES';
    }

    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BYTES';
    }

    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not yet implemented.' . "\n" . 'To implement it, please follow the guidelines here: https://stackoverflow.com/questions/43266590/does-cloud-spanner-support-a-truncate-table-command');
    }

    protected function getReservedKeywordsClass()
    {
        return SpannerKeywords::class;
    }

    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return 'STRING(' . $length . ')';
    }

    /**
     * Gets the character used for identifier quoting.
     *
     * @return string
     */
    public function getIdentifierQuoteCharacter()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function _getCreateTableSQL($tableName, array $columns, array $options = [])
    {
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        $query = 'CREATE TABLE ' . $tableName . ' (' . $queryFields . ')';

        // Primary key is outside of parentheses.
        if (isset($options['primary']) && !empty($options['primary'])) {
            $keyColumns = array_unique(array_values($options['primary']));
            $query .= ' PRIMARY KEY (' . implode(', ', $keyColumns) . ')';
        }
        $sql = [$query];

        // Adds optional indexes as separated SQL statements.
        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $definition) {
                $sql[] = $this->getCreateIndexSQL($definition, $tableName);
            }
        }

        return $sql;
    }
}
