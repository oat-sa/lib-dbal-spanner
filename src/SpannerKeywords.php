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
        return SpannerDriver::DRIVER_NAME;
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
