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

require_once __DIR__ . '/../../vendor/autoload.php';

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Core\Exception\BadRequestException;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerPlatform;
use PHPUnit\Framework\TestCase;

class StatementsCrudTest extends TestCase
{
    protected const INSTANCE_NAME = 'php-dbal-tests';
    protected const DATABASE_NAME = 'spanner-test';

    /** @var SpannerConnection */
    protected $connection;

    /** @var int */
    protected $now = 1234567890;

    public function setUp(): void
    {
        $connectionParams = [
            'dbname' => self::DATABASE_NAME,
            'instance' => self::INSTANCE_NAME,
            'driverClass' => SpannerDriver::class,
            'wrapperClass' => SpannerConnection::class,
            'platform' => new SpannerPlatform(),
        ];

        try {
            $this->connection = DriverManager::getConnection($connectionParams);
            $this->connection->connect();
        } catch (\Exception $exception) {
            echo 'Unable to connect to Spanner instance. Did you forget to start an instance and setup a database prior to running the integration tests?';
            $this->markTestSkipped();
        }
    }

    /**
     * @throws DBALException
     * @throws BadRequestException
     */
    public function testEmptyTableOnStart()
    {
        $sql = 'SELECT * FROM statements';
        $this->assertEquals([], $this->fetchAllResults($sql));
    }

    /**
     * @depends testEmptyTableOnStart
     * @throws DBALException
     * @throws BadRequestException
     */
    public function testCreateAndSimpleSelect()
    {
        for ($i = 1; $i <= 5; $i++) {
            // Insert statement returns the number of modified rows.
            $this->assertEquals(1, $this->connection->insert(
                'statements',
                $this->generateTripleRecord('subject' . $i, 'predicate' . $i, 'object' . $i, 1, $this->now)
            ));
        }

        $expected = [
            $this->generateTripleRecord('subject1', 'predicate1', 'object1', 1, $this->now),
            $this->generateTripleRecord('subject2', 'predicate2', 'object2', 1, $this->now),
            $this->generateTripleRecord('subject3', 'predicate3', 'object3', 1, $this->now),
            $this->generateTripleRecord('subject4', 'predicate4', 'object4', 1, $this->now),
            $this->generateTripleRecord('subject5', 'predicate5', 'object5', 1, $this->now),
        ];

        $sql = 'SELECT * FROM statements';
        $this->assertEquals($expected, $this->fetchAllResults($sql));
    }

    /**
     * @depends testCreateAndSimpleSelect
     * @throws DBALException
     * @throws BadRequestException
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
     * @throws DBALException
     * @throws BadRequestException
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
     * @param array  $expected
     * @param string $sql
     * @param array  $parameters
     *
     * @throws DBALException
     * @throws BadRequestException
     */
    public function testQueryWithParameters(array $expected, string $sql, array $parameters = [])
    {
        $this->assertEquals($expected, $this->fetchAllResults($sql, $parameters));
    }

    public function parameterQueriesToTest()
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
     * @param string $exceptionMessage
     * @param string $sql
     * @param array  $parameters
     *
     * @throws DBALException
     * @throws BadRequestException
     */
    public function testQueryWithWrongParametersThrowsException(string $exceptionMessage, string $sql, array $parameters = [])
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->fetchAllResults($sql, $parameters);
    }

    public function wrongParameterQueriesToTest()
    {
        return [
            [
                "An exception occurred while executing 'SELECT * FROM statements WHERE modelid = ? AND subject = ?' with params [2]:\n\nExpected exactly 2 parameter(s), 1 found.",
                'SELECT * FROM statements WHERE modelid = ? AND subject = ?',
                [2],
            ],
            [
                "An exception occurred while executing 'SELECT * FROM statements WHERE modelid = ? AND subject = ?' with params [2, \"foo\", \"bar\"]:\n\nExpected exactly 2 parameter(s), 3 found.",
                'SELECT * FROM statements WHERE modelid = ? AND subject = ?',
                [2, 'foo', 'bar'],
            ],
            [
                "An exception occurred while executing 'SELECT * FROM statements WHERE modelid = :model AND subject = ?':\n\nCan not use both named and positional parameters.",
                'SELECT * FROM statements WHERE modelid = :model AND subject = ?',
                [2],
            ],
        ];
    }

    /**
     * @depends testQueryWithParameters
     * @depends testQueryWithWrongParametersThrowsException
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    public function testDelete()
    {
        // Delete statements return the number of modified rows.
        $this->assertEquals(1, $this->connection->delete('statements', ['subject' => 'subject1']));
        $this->assertEquals(0, $this->connection->delete('statements', ['author' => 'Robert Desnos']));
        $this->assertEquals(4, $this->connection->delete('statements', ['modelid' => 1]));

        $sql = 'SELECT * FROM statements';
        $this->assertEquals([], $this->fetchAllResults($sql));
    }

    ////////////////////////////////////////////////////////////////////////////
    /// Helpers

    /**
     * Returns all the results of the statement as an array
     *
     * @param string $sql
     * @param array  $parameters
     *
     * @return array
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    public function fetchAllResults(string $sql, array $parameters = []): array
    {
        // Uses two different methods for the sake of testing although all could be done with prepare and execute.
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
    
    /**
     * Generates a convenient triple for the sake of simplification.
     *
     * @param string $s
     * @param string $p
     * @param string $o
     * @param int    $model
     * @param int    $time
     *
     * @return array
     */
    public function generateTripleRecord(string $s, string $p, string $o, int $model, int $time)
    {
        return [
            'modelid' => $model,
            'subject' => $s,
            'predicate' => $p,
            'object' => $o,
            'l_language' => 'en',
            'author' => 'an author',
            'epoch' => (string)$time,
        ];
    }
}
