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

namespace OAT\Library\DBALSpanner\Parameters;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\ParameterType;
use Google\Cloud\Spanner\Database;
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
     * Takes care about types mapp
     *
     * @param array $boundValues
     * @param array|null $params
     *
     * @param array|null $boundTypes
     * @return array
     * @throws InvalidArgumentException when a wrong number of parameters is provided.
     */
    public function convertPositionalToNamed(array $boundValues, array $params = null, array $boundTypes = null): array
    {
        // Positional parameters first index.
        $offset = 0;
        if ($params === null) {
            $params = $boundValues;
            $offset = 1;
        }

        // Named parameters don't have numeric keys.
        if (!array_key_exists($offset, $params)) {
            return [$params, []];
        }

        // Assert number of parameters.
        if ($this->positionalParameterCount !== count($params)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected exactly %d parameter(s), %d found',
                    $this->positionalParameterCount,
                    count($params)
                )
            );
        }

        // Generates 1-based sequenced parameter names.
        $namedParameters = [];
        $namedTypes = [];
        for ($i = 0; $i < count($params); $i++) {
            $namedParameters ['param' . ($i + 1)] = $params[$i + $offset];
            $namedTypes ['param' . ($i + 1)] = $boundTypes ? $boundTypes[$i + $offset] : null;
        }

        return [$namedParameters, array_filter($namedTypes)];
    }

    /**
     * @param $type
     * @return int|mixed
     */
    public function convertPDOtoSpannerTypes($type)
    {
        $typesMap = [
            ParameterType::BOOLEAN => Database::TYPE_BOOL,
            ParameterType::STRING => Database::TYPE_STRING,
            ParameterType::INTEGER => Database::TYPE_INT64,
        ];

        return array_key_exists($type, $typesMap) ? $typesMap[$type] : Database::TYPE_STRING;
    }

}
