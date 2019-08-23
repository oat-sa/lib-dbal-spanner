<?php

namespace OAT\Library\DBALSpanner\Tests\Unit;

use OAT\Library\DBALSpanner\SpannerKeywords;
use PHPUnit\Framework\TestCase;

class SpannerKeywordsTest extends TestCase
{
    public function testGetName(): void
    {
        $this->assertEquals('gcp-spanner', (new SpannerKeywords())->getName());
    }

    public function testGetKeywords(): void
    {
        $keywords = [
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

        $class = new \ReflectionClass(SpannerKeywords::class);
        $method = $class->getMethod('getKeywords');
        $method->setAccessible(true);

        $this->assertEquals(
            $keywords,
            $method->invokeArgs(new SpannerKeywords(), [])
        );
    }
}