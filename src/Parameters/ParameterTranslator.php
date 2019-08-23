<?php
/**
 * Created by PhpStorm.
 * User: julien
 * Date: 22/08/19
 * Time: 09:46
 */

namespace OAT\Library\DBALSpanner\Parameters;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use PhpMyAdmin\SqlParser\Lexer;
use PhpMyAdmin\SqlParser\Token;

class ParameterTranslator
{
    /** @var int */
    protected $positionalParameterCount = 0;

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
    public function translatePlaceHolders(string $sql): string
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
     * @param string $sql
     * @param array $boundValues
     * @param array $params
     *
     * @return array
     * @throws InvalidArgumentException when a wrong number of parameters is provided.
     */
    public function convertPositionalToNamed(string $sql, array $boundValues, array $params = null): array
    {
        // Positional parameters first index.
        $offset = 0;
        if ($params === null) {
            $params = $boundValues;
            $offset = 1;
        }

        // Named parameters don't have numeric keys.
        if (!array_key_exists($offset, $params)) {
            return $params;
        }

        // Assert number of parameters.
        if ($this->positionalParameterCount !== count($params)) {
            throw new InvalidArgumentException(
                sprintf(
                    "The statement '%s' expects exactly %d parameters, %d found.",
                    preg_replace('/@param[0-9]+/', '?', $sql),
                    $this->positionalParameterCount,
                    count($params)
                )
            );
        }

        // Generates 1-based sequenced parameter names.
        $namedParameters = [];
        for ($i = 0; $i < count($params); $i++) {
            $namedParameters ['param' . ($i + 1)] = $params[$i + $offset];
        }

        return $namedParameters;
    }
}
