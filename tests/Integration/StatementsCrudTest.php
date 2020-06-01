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
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Exception;
use Google\Cloud\Spanner\Timestamp;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\Tests\_helpers\ConfigurationTrait;
use OAT\Library\DBALSpanner\Tests\_helpers\ConnectionTrait;
use PHPUnit\Framework\TestCase;

class StatementsCrudTest extends TestCase
{
    use ConfigurationTrait;
    use ConnectionTrait;

    /**
     * @var SpannerConnection
     */
    private $connection;

    /**
     * @var int
     */
    private $now = 1234567890;

    public function setUp(): void
    {
        $this->connection = $this->getConnection();
    }

    /**
     * @throws DBALException
     */
    public function testCreateAndSimpleSelect()
    {
        $this->setUpDatabase();

        for ($i = 1; $i <= 5; $i++) {
            $this->assertInstanceOf(
                Timestamp::class,
                $this->connection->insert(
                    'statements',
                    $this->generateTripleRecord(
                        'subject' . $i,
                        'predicate' . $i,
                        'object' . $i,
                        1,
                        $this->now
                    )
                )
            );
        }

        $expected = [
            $this->generateTripleRecord('subject1', 'predicate1', 'object1', 1, $this->now),
            $this->generateTripleRecord('subject2', 'predicate2', 'object2', 1, $this->now),
            $this->generateTripleRecord('subject3', 'predicate3', 'object3', 1, $this->now),
            $this->generateTripleRecord('subject4', 'predicate4', 'object4', 1, $this->now),
            $this->generateTripleRecord('subject5', 'predicate5', 'object5', 1, $this->now),
        ];

        $results = $this->fetchAllResults('SELECT * FROM statements');

        $this->assertEquals($expected, $results);
    }

    /**
     * @depends testCreateAndSimpleSelect
     *
     * @throws Exception
     */
    public function testReadWithoutParameter()
    {
        $expected = [
            $this->generateTripleRecord('subject5', 'predicate5', 'object5', 1, $this->now),
            $this->generateTripleRecord('subject4', 'predicate4', 'object4', 1, $this->now),
            $this->generateTripleRecord('subject3', 'predicate3', 'object3', 1, $this->now),
            $this->generateTripleRecord('subject2', 'predicate2', 'object2', 1, $this->now),
            $this->generateTripleRecord('subject1', 'predicate1', 'object1', 1, $this->now),
        ];

        $sql = 'SELECT * FROM statements ORDER BY subject DESC';

        $this->assertEquals($expected, $this->fetchAllResults($sql));
    }

    /**
     * @depends testReadWithoutParameter
     *
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    public function testUpdateAndQueryWithSemiColon()
    {
        $this->assertEquals(1, $this->connection->update('statements', ['modelid' => 2], ['subject' => 'subject1']));

        $expected = [
            $this->generateTripleRecord('subject1', 'predicate1', 'object1', 2, $this->now),
            $this->generateTripleRecord('subject2', 'predicate2', 'object2', 1, $this->now),
            $this->generateTripleRecord('subject3', 'predicate3', 'object3', 1, $this->now),
            $this->generateTripleRecord('subject4', 'predicate4', 'object4', 1, $this->now),
            $this->generateTripleRecord('subject5', 'predicate5', 'object5', 1, $this->now),
        ];

        $sql = 'SELECT * FROM statements;';

        $this->assertEquals($expected, $this->fetchAllResults($sql));
    }

    /**
     * @depends      testUpdateAndQueryWithSemiColon
     * @dataProvider parameterQueriesToTest
     *
     * @throws DBALException
     */
    public function testQueryWithParameters(array $expected, string $sql, array $parameters = []): void
    {
        $this->assertEquals($expected, $this->fetchAllResults($sql, $parameters));
    }

    public function parameterQueriesToTest(): array
    {
        return [
            [
                [
                    $this->generateTripleRecord('subject1', 'predicate1', 'object1', 2, $this->now),
                ],
                'SELECT * FROM statements WHERE modelid = ? AND subject = ?',
                [2, 'subject1'],
            ],
            [
                [
                    $this->generateTripleRecord('subject1', 'predicate1', 'object1', 2, $this->now),
                ],
                'SELECT * FROM statements WHERE subject = :subject',
                ['subject' => 'subject1'],
            ],
            [
                [
                    $this->generateTripleRecord('subject1', 'predicate1', 'object1', 2, $this->now),
                ],
                'SELECT * FROM statements WHERE subject = @subject',
                ['subject' => 'subject1'],
            ],
        ];
    }

    /**
     * @depends      testUpdateAndQueryWithSemiColon
     * @dataProvider wrongParameterQueriesToTest
     *
     * @throws DBALException
     */
    public function testQueryWithWrongParametersThrowsException(string $exceptionMessage, string $sql, array $parameters = []): void
    {
        // @TODO @FIXME Why are we testing Doctrine? IMO this test should be removed
        // Those error messages can vary and are third-part related

        $this->expectException(DBALException::class);
        $this->expectExceptionMessage($exceptionMessage);

        $this->fetchAllResults($sql, $parameters);
    }

    public function wrongParameterQueriesToTest(): array
    {
        return [
            [
                "Expected exactly 2 parameter(s), 1 found.",
                'SELECT * FROM statements WHERE modelid = ? AND subject = ?',
                [2],
            ],
            [
                "Expected exactly 2 parameter(s), 3 found.",
                'SELECT * FROM statements WHERE modelid = ? AND subject = ?',
                [2, 'foo', 'bar'],
            ],
            [
                "Can not use both named and positional parameters.",
                'SELECT * FROM statements WHERE modelid = :model AND subject = ?',
                [2],
            ],
        ];
    }

    /**
     * @depends testQueryWithParameters
     * @depends testQueryWithWrongParametersThrowsException
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function testDelete(): void
    {
        $this->assertEquals(1, $this->connection->delete('statements', ['subject' => 'subject1']));
        $this->assertEquals(0, $this->connection->delete('statements', ['author' => 'Robert Desnos']));
        $this->assertEquals(4, $this->connection->delete('statements', ['modelid' => 1]));

        $sql = 'SELECT * FROM statements';

        $this->assertEquals([], $this->fetchAllResults($sql));
    }

    /**
     * @throws DBALException
     */
    private function fetchAllResults(string $sql, array $parameters = []): array
    {
        if (count($parameters)) {
            $statement = $this->connection->prepare($sql);
            $statement->execute($parameters);
        } else {
            $statement = $this->connection->query($sql);
        }

        $results = [];
        $rows = $statement->fetchAll();

        if ($rows) {
            foreach ($rows as $row) {
                $results[] = $row;
            }
        }

        return $results;
    }

    private function generateTripleRecord(
        string $subject,
        string $predicate,
        string $object,
        int $model,
        int $time
    ): array {
        return [
            'modelid' => $model,
            'subject' => $subject,
            'predicate' => $predicate,
            'object' => $object,
            'l_language' => 'en',
            'author' => 'an author',
            'epoch' => (string)$time,
        ];
    }

    private function setUpDatabase(): void
    {
        $this->connection->exec('DELETE FROM statements WHERE modelid > 0');
    }
}
