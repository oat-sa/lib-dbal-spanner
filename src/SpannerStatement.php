<?php

declare(strict_types=1);

namespace Oat\DbalSpanner;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use IteratorAggregate;
use PDO;

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
        $this->parameterSyntax = $this->detectParameterSyntax($sql);
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
    public function detectParameterSyntax(string $sql): string
    {
        $named = (strpos($sql, ':') !== false || strpos($sql, '@') !== false);
        $positional = (strpos($sql, '?') !== false);

        if ($named && $positional) {
            throw new InvalidArgumentException(
                sprintf('Statement "%s" can not use both named and positional parameters.', $sql)
            );
        }

        if ($named) {
            return self::PARAMETERS_NAMED;
        }

        if (
        $positional) {
            return self::PARAMETERS_POSITIONAL;
        }

        return self::PARAMETERS_NONE;
    }

    /**
     * Translates parameter placeholders to Spanner format.
     *
     * @param string $sql
     *
     * @return string
     */
    public function translateParameterPlaceHolders(string $sql): string
    {
        switch ($this->parameterSyntax) {
            case self::PARAMETERS_NAMED:
                // Replaces DBAL flavored parameters (:param) with spanner's (@param).
                return str_replace(':', '@', $sql);

            case self::PARAMETERS_POSITIONAL:
                // Replaces positional parameters (?) with spanner ordinal generated ones (@param1, @param2, ...).
                $parts = explode('?', $sql);
                for ($i = 1; $i < count($parts); $i++) {
                    $parts[$i - 1] .= '@param' . $i;
                }

                // Stores the parameter count to assert that the right number of parameters is given upon statement execution.
                $this->positionalParameterCount = count($parts) - 1;

                return implode('', $parts);

            case self::PARAMETERS_NONE:
            default:
                // No parameter at all.
                return $sql;
        }
    }

    /**
     * Translates the positional parameters into named parameters.
     *
     * @param array $params
     *
     * @return array
     * @throws InvalidArgumentException When a wrong number of parameters are provided.
     */
    public function translatePositionalParameterNames(array $params): array
    {
        if (!array_key_exists(0, $params)) {
            return $params;
        }

        // Assert number of parameters.
        if($this->positionalParameterCount !== count($params)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The statement "%s" expects exactly %d parameters, %d found.',
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

    public function execute($params = null): bool
    {
        $parameters = $this->translatePositionalParameterNames($params ?? []);

        try {
            $this->result = $this->database->execute($this->sql, ['parameters' => $parameters]);
        } catch (BadRequestException $exception) {
            return false;
        }

        return true;
    }

    public function rowCount()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
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
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
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

        return $this->result->rows($fetchMode);
    }

    public function fetchColumn($columnIndex = 0)
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function getIterator()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }
}
