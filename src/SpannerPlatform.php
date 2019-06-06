<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * The SpannerPlatform provides the behavior, features and SQL dialect of the Spanner database platform.
 */
class SpannerPlatform extends AbstractPlatform
{
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
        if (! empty($columnDef['autoincrement'])) {
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
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function getBlobTypeDeclarationSQL(array $field)
    {
        return 'BYTES';
    }

    public function getNowExpression()
    {
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        return ($dateTime)->format('U');
    }

    public function getName()
    {
        return 'spanner';
    }
}
