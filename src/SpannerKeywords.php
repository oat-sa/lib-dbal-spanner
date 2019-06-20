<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Platforms\Keywords\KeywordList;

/**
 * Spanner Keywordlist.
 */
class SpannerKeywords extends KeywordList
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'gcp-spanner';
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeywords()
    {
        return [
            'ALL',
            'AND',
            'ANY',
            'ARRAY',
            'AS',
            'ASC',
            'ASSERT_ROWS_MODIFIED',
            'AT',
            'BETWEEN',
            'BY',
            'CASE',
            'CAST',
            'COLLATE',
            'CONTAINS',
            'CREATE',
            'CROSS',
            'CUBE',
            'CURRENT',
            'DEFAULT',
            'DEFINE',
            'DESC',
            'DISTINCT',
            'ELSE',
            'END',
            'ENUM',
            'ESCAPE',
            'EXCEPT',
            'EXCLUDE',
            'EXISTS',
            'EXTRACT',
            'FALSE',
            'FETCH',
            'FOLLOWING',
            'FOR',
            'FROM',
            'FULL',
            'GROUP',
            'GROUPING',
            'GROUPS',
            'HASH',
            'HAVING',
            'IF',
            'IGNORE',
            'IN',
            'INNER',
            'INTERSECT',
            'INTERVAL',
            'INTO',
            'IS',
            'JOIN',
            'LATERAL',
            'LEFT',
            'LIKE',
            'LIMIT',
            'LOOKUP',
            'MERGE',
            'NATURAL',
            'NEW',
            'NO',
            'NOT',
            'NULL',
            'NULLS',
            'OF',
            'ON',
            'OR',
            'ORDER',
            'OUTER',
            'OVER',
            'PARTITION',
            'PRECEDING',
            'PROTO',
            'RANGE',
            'RECURSIVE',
            'RESPECT',
            'RIGHT',
            'ROLLUP',
            'ROWS',
            'SELECT',
            'SET',
            'SOME',
            'STRUCT',
            'TABLESAMPLE',
            'THEN',
            'TO',
            'TREAT',
            'TRUE',
            'UNBOUNDED',
            'UNION',
            'UNNEST',
            'USING',
            'WHEN',
            'WHERE',
            'WINDOW',
            'WITH',
            'WITHIN',
        ];
    }
}
