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

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\ParameterType;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Timestamp;
use Google\Cloud\Spanner\Transaction;

/**
 * Class SpannerConnection
 */
class SpannerConnection implements Connection
{
    /** @var Database */
    protected $database;

    /** @var Driver */
    protected $driver;

    /** @var array */
    protected $cachedStatements = [];

    public function __construct(Driver $driver, Database $database)
    {
        $this->database = $database;
        $this->driver = $driver;
    }

    /**
     * @param string $prepareString
     *
     * @return SpannerStatement
     */
    public function prepare($prepareString)
    {
        if (isset($this->cachedStatements[$prepareString])) {
            return $this->cachedStatements[$prepareString];
        }

        $statement = new SpannerStatement($this->database, $prepareString);
        $this->cachedStatements[$prepareString] = $statement;

        return $statement;
    }

    /**
     * @return SpannerStatement
     */
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        // Runs schema manipulation on the DDL endpoint.
        if ($this->isDdlStatement($sql)) {
            $longRunningOperation = $this->database->updateDdl($sql);
            $longRunningOperation->pollUntilComplete();

            //stub to be complient with return type. it is used by doctrine
            return new SpannerStatement($this->database, $sql);
        }

        $statement = $this->prepare($sql);
        $statement->execute();

        return $statement;
    }

    /**
     * @param string $tableExpression
     * @param array  $identifier
     * @param array  $types
     *
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    public function delete($tableExpression, array $identifier, array $types = [])
    {
        if (empty($identifier)) {
            throw InvalidArgumentException::fromEmptyCriteria();
        }

        $statement = sprintf(
            'DELETE FROM %s WHERE %s',
            $tableExpression,
            $this->forgeWhereClause($identifier, $types)
        );

        return $this->exec($statement);
    }

    /**
     * @param string $tableExpression
     * @param array  $data
     * @param array  $identifier
     * @param array  $types
     *
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    public function update($tableExpression, array $data, array $identifier, array $types = [])
    {
        if (empty($identifier)) {
            throw InvalidArgumentException::fromEmptyCriteria();
        }

        $sets = [];

        foreach ($data as $columnName => $value) {
            $sets[] = $columnName . ' = ' . $this->quoteString($value, $types[$columnName] ?? '');
        }

        $statement = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $tableExpression,
            implode(', ', $sets),
            $this->forgeWhereClause($identifier, $types)
        );

        return $this->exec($statement);
    }

    /**
     * @param string $tableExpression
     * @param array  $data
     *
     * @return Timestamp
     */
    public function insert($tableExpression, array $data)
    {
        return $this->database->insert($tableExpression, $data);
    }

    /**
     * Gathers conditions for an update or delete call.
     *
     * @param mixed[] $identifiers Input array of columns to values
     * @param array   $types
     *
     * @return string the conditions
     */
    protected function forgeWhereClause(array $identifiers, array $types = [])
    {
        $conditions = [];

        foreach ($identifiers as $columnName => $value) {
            $conditions[] = $value === null
                ? $this->driver->getDatabasePlatform()->getIsNullExpression($columnName)
                : $columnName . ' = ' . $this->quoteString($value, $types[$columnName] ?? '');
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Quotes strings only.
     *
     * @param mixed $value
     * @param int|string   $type
     *
     * @return mixed
     */
    public function quoteString($value, $type = '')
    {
        return $this->quote($value, $this->getParameterType($value, $type));
    }

    /**
     * Tries to detect the value type if not precised.
     *
     * @param mixed $value
     * @param int|string $type
     *
     * @return int
     */
    protected function getParameterType($value, $type): int
    {
        if ($type !== '') {
            return $type;
        }
        if ($value === null) {
            return ParameterType::NULL;
        }
        if (is_bool($value)) {
            return ParameterType::BOOLEAN;
        }
        if (is_int($value)) {
            return ParameterType::INTEGER;
        }
        return ParameterType::STRING;
    }

    public function quote($input, $type = ParameterType::STRING)
    {
        return $type === ParameterType::STRING || $type === ParameterType::BINARY
            ? '"' . $input . '"'
            : $input;
    }

    /**
     * @param string $statement
     *
     * @return int|mixed
     */
    public function exec($statement)
    {
        // Runs schema manipulation on the DDL endpoint.
        if ($this->isDdlStatement($statement)) {
            $longRunningOperation = $this->database->updateDdl($statement);
            $longRunningOperation->pollUntilComplete();
            return true;
        }

        return $this->database->runTransaction(
            static function (Transaction $t) use ($statement) {
                $rowCount = $t->executeUpdate($statement);
                $t->commit();
                return $rowCount;
            }
        );
    }

    public function transactional(Closure $func)
    {
        return $this->database->runTransaction(
            static function (Transaction $t) use ($func) {
                return $func($t);
            }
        );
    }

    public function isDdlStatement($statement)
    {
        $statement = ltrim($statement);

        return stripos($statement, 'CREATE ') === 0
            || stripos($statement, 'DROP ') === 0
            || stripos($statement, 'ALTER ') === 0;
    }

    public function lastInsertId($name = null)
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function beginTransaction()
    {
    }

    public function commit()
    {
    }

    public function rollBack()
    {
    }

    public function errorCode()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function errorInfo()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }
}
