<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;

/**
 * The SpannerPlatform provides the behavior, features and SQL dialect of the Spanner database platform.
 */
class SpannerPlatform extends AbstractPlatform
{
    public function getName()
    {
        return 'gcp-spanner';
    }

    public function getBooleanTypeDeclarationSQL(array $columnDef)
    {
        return 'BOOL';
    }

    public function getIntegerTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    public function getBigIntTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    public function getSmallIntTypeDeclarationSQL(array $columnDef)
    {
        return 'INT64';
    }

    // DateTime is not supported.
    public function getDateTimeTzTypeDeclarationSQL(array $fieldDeclaration)
    {
        throw DBALException::notSupported(__METHOD__);
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
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
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

    public function getNowExpression()
    {
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        return $dateTime->format('U');
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
            foreach ($options['indexes'] as $index => $definition) {
                $sql[] = $this->getCreateIndexSQL($index, $definition, $tableName);
            }
        }

        return $sql;
    }
}
