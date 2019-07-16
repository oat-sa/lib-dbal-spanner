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
