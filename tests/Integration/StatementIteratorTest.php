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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\DBALSpanner\Tests\Integration;

use Doctrine\DBAL\DBALException;
use Exception;
use OAT\Library\DBALSpanner\SpannerStatement;
use OAT\Library\DBALSpanner\Tests\_helpers\ConfigurationTrait;
use OAT\Library\DBALSpanner\Tests\_helpers\ConnectionTrait;
use PHPUnit\Framework\TestCase;

class StatementIteratorTest extends TestCase
{
    use ConfigurationTrait;
    use ConnectionTrait;

    /**
     * @var int
     */
    private $now = 1234567890;

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testIterateFromPreviousResult(): void
    {
        $this->setUpDatabase();

        $statement = $this->getConnection()->prepare('SELECT * FROM statements;');
        $statement->execute();

        $this->assertIterations($statement);
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testCannotIterateNonSelectCommand(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Statement must be a SELECT to use iterator');

        $statement = $this->getConnection()->prepare('UPDATE statements SET author = `something` WHERE modelid = 1;');
        $statement->getIterator();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testCannotIterateWhenTHereIsNoResult(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('There must be a previous result to iterate');

        $statement = $this->getConnection()->prepare('SELECT * FROM statements;');
        $statement->getIterator();
    }

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function testIterateFromPreviousRows(): void
    {
        $this->setUpDatabase();

        $statement = $this->getConnection()->prepare('SELECT * FROM statements;');
        $statement->execute();
        $statement->fetchAll();

        $this->assertIterations($statement);
    }

    private function assertIterations(SpannerStatement $statement): void
    {
        $total = 0;

        foreach ($statement->getIterator() as $row) {
            $total++;
            $this->assertSame('an author', $row['author']);
        }

        $this->assertSame(3, $total);
    }

    private function setUpDatabase(): void
    {
        $this->getConnection()->exec('DELETE FROM statements WHERE modelid > 0');

        for ($i = 1; $i <= 3; $i++) {
            $this->getConnection()->insert(
                'statements',
                [
                    'modelid' => $i,
                    'subject' => 'subject' . $i,
                    'predicate' => 'predicate' . $i,
                    'object' => 'object' . $i,
                    'l_language' => 'en',
                    'author' => 'an author',
                    'epoch' => (string)$this->now,
                ]
            );
        }
    }
}
