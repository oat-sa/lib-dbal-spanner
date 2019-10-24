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
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Exception;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\Transaction;
use IteratorAggregate;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use PDO;
use Psr\Log\LoggerAwareTrait;

class SpannerStatement implements IteratorAggregate, Statement
{
    use LoggerAwareTrait;

    /** @var ParameterTranslator */
    private $parameterTranslator;

    /** @var Database */
    protected $database;

    /** @var string */
    protected $sql;

    /**
     * The result set resource to fetch.
     *
     * @var Result|null
     */
    protected $result;

    /** @var array */
    protected $rows = null;

    /** @var int */
    protected $offset;

    /** @var string */
    protected $defaultFetchMode = Result::RETURN_ASSOCIATIVE;

    /** @var array */
    protected $boundValues = [];

    /** @var array */
    protected $boundTypes  = [];

    /** @var int */
    protected $affectedRows = 0;

    /**
     * Do we return objects instead of arrays?
     *
     * @var bool
     */
    protected $fetchObjects = false;

    /**
     * SpannerStatement constructor.
     *
     * @param Database                 $database
     * @param string                   $sql
     * @param ParameterTranslator|null $parameterTranslator
     *
     * @throws InvalidArgumentException when the sql statement uses both named and positional parameters.
     */
    public function __construct(Database $database, string $sql, ?ParameterTranslator $parameterTranslator = null)
    {
        $this->database = $database;

        // Falls back to new instance of ParameterTranslator.
        if ($parameterTranslator === null) {
            $parameterTranslator = new ParameterTranslator();
        }
        $this->parameterTranslator = $parameterTranslator;

        $this->sql = $this->parameterTranslator->translatePlaceHolders($sql);
    }

    /**
     * @param mixed $param
     * @param mixed $value
     * @param int   $type
     *
     * @return bool|void
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $this->boundTypes[$param] = $this->parameterTranslator->convertPDOtoSpannerTypes($type);
        $this->boundValues[$param] = $value;
    }

    /**
     * @throws DBALException This method is not supported in Spanner
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        throw DBALException::notSupported(__METHOD__);
    }

    public function errorCode()
    {
        throw new Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function errorInfo()
    {
        throw new Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    /**
     * @param array $params
     *
     * @return bool
     * @throws Exception
     */
    public function execute($params = null): bool
    {
        try {
            [$parameters, $types] = $this->parameterTranslator->convertPositionalToNamed($this->boundValues, $params, $this->boundTypes);
        } catch (InvalidArgumentException $exception) {
            if ($this->logger) {
                $this->logger->error(
                    sprintf(
                        "%s in the statement '%s'.",
                        $exception->getMessage(),
                        preg_replace('/@param[0-9]+/', '?', $this->sql)
                    )
                );
            }
            throw $exception;
        }

        // DML statement is executed with a direct call to database execute.
        if (!$this->isDmlStatement($this->sql)) {
            $this->result = $this->database->execute($this->sql, ['parameters' => $parameters]);
            $this->rows = null;
            return true;
        }

        // DML statement is executed within a transaction.
        try {
            return $this->database->runTransaction(
                function (Transaction $t) use ($parameters, $types) {
                    $this->affectedRows = $t->executeUpdate($this->sql, ['parameters' => $parameters, 'types' => $types]);
                    $t->commit();
                    $this->result = null;
                    $this->rows = null;

                    return (bool)$this->affectedRows;
                }
            );
        } catch (Exception $exception) {
            if ($this->logger) {
                $this->logger->error($exception->getMessage());
            }
            throw $exception;
        }
    }

    /**
     * @param $statement
     *
     * @return bool
     */
    public function isDmlStatement($statement)
    {
        $statement = ltrim($statement);

        return stripos($statement, 'INSERT ') === 0
            || stripos($statement, 'UPDATE ') === 0
            || stripos($statement, 'DELETE ') === 0;
    }

    /**
     * For DML, returns the number of affected rows.
     * For SQL, returns the number of rows in the result set.
     *
     * @return int
     */
    public function rowCount()
    {
        if ($this->isDmlStatement($this->sql)) {
            return $this->affectedRows;
        }

        if (!$this->result instanceof Result) {
            return 0;
        }

        $this->loadRows();

        return count($this->rows);
    }

    public function closeCursor()
    {
        throw new Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function columnCount()
    {
        throw new Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $modes = [
            FetchMode::ASSOCIATIVE => Result::RETURN_ASSOCIATIVE,
            FetchMode::NUMERIC => Result::RETURN_ZERO_INDEXED,
        ];

        $this->defaultFetchMode = $modes[$fetchMode] ?? Result::RETURN_ASSOCIATIVE;
    }

    /**
     * @param null $fetchMode
     * @param int  $cursorOrientation
     * @param int  $cursorOffset
     *
     * @return bool|false|mixed
     * @throws InvalidArgumentException
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        if (!$this->result instanceof Result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        if ($fetchMode === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        $this->loadRows($fetchMode);

        // Find the row offset.
        $this->offset = $this->findOffset($cursorOrientation, $cursorOffset);

        return $this->offset !== -1
            ? $this->rows[$this->offset]
            : false;
    }

    /**
     * @param     $cursorOrientation
     * @param int $cursorOffset
     *
     * @return int
     * @throws InvalidArgumentException
     */
    public function findOffset($cursorOrientation, $cursorOffset = 0)
    {
        $lastRow = count($this->rows) - 1;

        switch ($cursorOrientation) {
            case PDO::FETCH_ORI_NEXT:
                return $this->offset === $lastRow
                    ? -1
                    : $this->offset + 1;

            case PDO::FETCH_ORI_PRIOR:
                return $this->offset === -1
                    ? -1
                    : $this->offset - 1;

            case PDO::FETCH_ORI_FIRST:
                return $lastRow === -1 ? -1 : 0;

            case PDO::FETCH_ORI_LAST:
                return $lastRow;

            case PDO::FETCH_ORI_ABS:
                return $cursorOffset < 0 || $cursorOffset > $lastRow
                    ? -1
                    : $cursorOffset;

            case PDO::FETCH_ORI_REL:
                $newOffset = $this->offset + $cursorOffset;

                return $newOffset < 0 || $newOffset > $lastRow
                    ? -1
                    : $newOffset;
        }

        throw new InvalidArgumentException('Unknown cursorOrientation ' . $cursorOrientation . ' parameter.');
    }

    /**
     * @param int|null     $fetchMode
     * @param int|null     $fetchArgument
     * @param mixed[]|null $ctorArgs
     *
     * @return bool||mixed[]
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if (!$this->result instanceof Result) {
            return false;
        }

        $realMode = $this->getRealMode($fetchMode);

        $this->rows = [];
        foreach ($this->result->rows($realMode) as $row) {
            $this->rows[] = $row;
        }

        // Optionally converts each line to a StdClass object.
        if ($this->fetchObjects) {
            $this->rows = array_map(
                function (array $row) {
                    return (object)$row;
                },
                $this->rows
            );
        }

        return $this->rows;
    }

    /**
     * @param int|null $fetchMode
     *
     * @return string|int
     */
    public function getRealMode($fetchMode)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        switch ($fetchMode){
            case PDO::FETCH_OBJ:
                $this->fetchObjects = true;
                return Result::RETURN_ASSOCIATIVE;
            case PDO::FETCH_ASSOC:
                $this->fetchObjects = false;
                return Result::RETURN_ASSOCIATIVE;
            default:
                $this->fetchObjects = false;
        }

        return $fetchMode;
    }

    /**
     * @param int $columnIndex
     *
     * @return bool|false|mixed
     * @throws InvalidArgumentException
     */
    public function fetchColumn($columnIndex = 0)
    {
        $nextRow = $this->fetch(Result::RETURN_ZERO_INDEXED);

        return $nextRow === false
            ? false
            : $nextRow[$columnIndex];
    }

    public function getIterator()
    {
        throw new Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    protected function loadRows($fetchMode = null)
    {
        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        // Loads all rows.
        if ($this->rows === null) {
            $this->fetchAll($fetchMode);
            $this->offset = -1;
        }
    }
}
