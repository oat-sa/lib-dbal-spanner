<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use Google\Cloud\Spanner\Transaction;
use IteratorAggregate;
use PDO;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;

class SpannerStatement implements IteratorAggregate, Statement
{
    public const PARAMETERS_NONE = '';
    public const PARAMETERS_NAMED = 'named';
    public const PARAMETERS_POSITIONAL = 'positional';

    /** @var Database */
    protected $database;

    /** @var string */
    protected $sql;

    /** @var Result|null The result set resource to fetch. */
    protected $result;

    /** @var array */
    protected $rows = null;

    /** @var int */
    protected $offset;

    /** @var string */
    protected $defaultFetchMode = Result::RETURN_ASSOCIATIVE;

    /** @var array */
    protected $boundValues = [];

    /** @var string */
    protected $parameterSyntax = self::PARAMETERS_NONE;

    /** @var int */
    protected $positionalParameterCount = 0;

    /**
     * SpannerStatement constructor.
     *
     * @param Database $database
     * @param string   $sql
     *
     * @throws InvalidArgumentException when the sql statement uses both named and positional parameters.
     */
    public function __construct(Database $database, string $sql)
    {
        $this->database = $database;
        $this->sql = $this->translateParameterPlaceHolders($sql);
    }

    /**
     * Detects the optional parameter syntax.
     * DBAL and Spanner are both named so can be compatible.
     * Named and positional syntaxes are not compatible.
     *
     * @param string $sql
     *
     * @return string One of the PARAMETERS_* constants.
     * @throws InvalidArgumentException when both syntax types are used in the same statement.
     */
    public function translateParameterPlaceHolders(string $sql): string
    {
        if (strpos($sql, ':') === false && strpos($sql, '?') === false) {
            return $sql;
        }

        $named = 0;
        $tokenList = Lexer::getTokens($sql);
        $translatedSql = '';
        foreach ($tokenList->tokens as $token) {
            if ($token->type === Token::TYPE_SYMBOL) {
                if ($token->token === '?') {
                    $this->positionalParameterCount++;
                    $translatedSql .= '@param' . $this->positionalParameterCount;
                } elseif (substr($token->token, 0, 1) === ':') {
                    $translatedSql .= str_replace(':', '@', $token->token);
                    $named++;
                } elseif (substr($token->token, 0, 1) === '@') {
                    $translatedSql .= $token->token;
                    $named++;
                } else {
                    $translatedSql .= $token->token;
                }
            } else {
                $translatedSql .= $token->token;
            }
        }

        if ($named && $this->positionalParameterCount) {
            throw new InvalidArgumentException(
                sprintf("Statement '%s' can not use both named and positional parameters.", $sql)
            );
        }

        return $translatedSql;
    }

    /**
     * Translates the positional parameters into named parameters.
     *
     * @param array $params
     *
     * @return array
     * @throws InvalidArgumentException when a wrong number of parameters is provided.
     */
    public function translatePositionalParameterNames(array $params): array
    {
        if (!array_key_exists(0, $params)) {
            return $params;
        }

        // Assert number of parameters.
        if ($this->positionalParameterCount !== count($params)) {
            throw new InvalidArgumentException(
                sprintf(
                    "The statement '%s' expects exactly %d parameters, %d found.",
                    preg_replace('/@param[0-9]+/', '?', $this->sql),
                    $this->positionalParameterCount,
                    count($params)
                )
            );
        }

        // Generates 1-based sequenced parameter names.
        $namedParameters = [];
        for ($i = 0; $i < count($params); $i++) {
            $namedParameters ['param' . ($i + 1)] = $params[$i];
        }

        return $namedParameters;
    }

    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
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
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function errorInfo()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    /**
     * @param array $params
     *
     * @return bool
     * @throws InvalidArgumentException when a wrong number of parameters is provided.
     */
    public function execute($params = null): bool
    {
        try {
            $parameters = $this->translatePositionalParameterNames($params ?? []);

            if ($this->isDmlStatement($this->sql)) {
                $statement = $this->sql;
                return $this->database->runTransaction(
                    function (Transaction $t) use ($statement, $parameters) {
                        $rowCount = $t->executeUpdate($statement, ['parameters' => $parameters]);
                        $t->commit();
                        return (bool) $rowCount;
                    }
                );
            }

            $this->result = $this->database->execute($this->sql, ['parameters' => $parameters]);
            $this->rows = null;
        } catch (\Exception $exception) {
            var_dump($exception->getMessage());
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            return false;
        }

        return true;
    }

    public function isDmlStatement($statement)
    {
        $statement = ltrim($statement);

        return stripos($statement, 'INSERT ') === 0
            || stripos($statement, 'UPDATE ') === 0
            || stripos($statement, 'DELETE ') === 0;
    }

    public function rowCount()
    {
        if (!$this->result instanceof Result) {
            return 0;
        }

        $this->loadRows();

        return count($this->rows);
    }

    public function closeCursor()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function columnCount()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        $modes = [
            FetchMode::ASSOCIATIVE => Result::RETURN_ASSOCIATIVE,
            FetchMode::NUMERIC => Result::RETURN_ZERO_INDEXED,
        ];

        $this->defaultFetchMode = $modes[$fetchMode] ?? Result::RETURN_ASSOCIATIVE;
    }

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

    public function findOffset($cursorOrientation, $cursorOffset)
    {
        $lastRow = count($this->rows) - 1;

        switch ($cursorOrientation) {
            case PDO::FETCH_ORI_NEXT:
                return $this->offset === $lastRow
                    ? -1
                    : $this->offset + 1;

            case PDO::FETCH_ORI_PRIOR:
                return $this->offset - 1;

            case PDO::FETCH_ORI_FIRST:
                return 0;

            case PDO::FETCH_ORI_LAST:
                return $lastRow;

            case PDO::FETCH_ORI_ABS:
                return $cursorOffset;

            case PDO::FETCH_ORI_REL:
                $newOffset = $this->offset + $cursorOffset;

                return $newOffset < 0 || $newOffset > $lastRow
                    ? -1
                    : $newOffset;
        }
    }

    /**
     * @param null $fetchMode
     * @param null $fetchArgument
     * @param null $ctorArgs
     *
     * @return bool|\Generator|mixed[]
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        if (!$this->result instanceof Result) {
            return false;
        }

        $fetchMode = $fetchMode ?: $this->defaultFetchMode;

        $realMode = $fetchMode === PDO::FETCH_OBJ
            ? Result::RETURN_ASSOCIATIVE
            : $fetchMode;

        $this->rows = [];
        foreach($this->result->rows($realMode) as $row) {
            $this->rows[] = $row;
        }

        // Optionally converts each line to a StdClass object.
        if ($fetchMode === PDO::FETCH_OBJ) {
            $this->rows = array_map(
                function(array $row) {
                    return (object) $row;
                },
                $this->rows
            );
        }

        return $this->rows;
    }

    public function fetchColumn($columnIndex = 0)
    {
        $nextRow = $this->fetch(Result::RETURN_ZERO_INDEXED);

        return $nextRow === false
            ? false
            : $nextRow[$columnIndex];
    }

    public function getIterator()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
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
