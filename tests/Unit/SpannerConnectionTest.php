<?php

namespace OAT\Library\DBALSpanner\Tests\Unit;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Google\Cloud\Core\LongRunning\LongRunningOperation;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Transaction;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerStatement;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\TestCase;

class SpannerConnectionTest extends TestCase
{
    use NoPrivacyTrait;

    protected function getSpannerConnection(Driver $driver = null, Database $database = null)
    {
        if (null === $driver) {
            $driver = $this->getMockForAbstractClass(Driver::class);
        }

        if (null === $database) {
            $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()->getMock();
        }

        return new SpannerConnection($driver, $database);
    }

    public function testPrepare()
    {
        $connection = $this->getSpannerConnection();
        $statement = $connection->prepare('sql-query');

        $this->assertInstanceOf(SpannerStatement::class, $statement);

        $this->assertEquals('sql-query', $this->getPrivateProperty($statement, 'sql'));

        $cachedStatements = $this->getPrivateProperty($connection, 'cachedStatements');

        $this->assertArrayHasKey('sql-query', $cachedStatements);
        $this->assertEquals($statement, $cachedStatements['sql-query']);
    }

    public function testPrepareFromCache()
    {
        $connection = $this->getSpannerConnection();

        $this->setPrivateProperty($connection, 'cachedStatements', ['sql-query' => 'preparedStatement']);

        $this->assertEquals('preparedStatement', $connection->prepare('sql-query'));
    }

    public function testQuery()
    {
        $connection = $this->getSpannerConnection();
        $statement = $this->createMock(SpannerStatement::class);
        $statement->expects($this->once())->method('execute');

        $this->setPrivateProperty($connection, 'cachedStatements', ['sql-query' => $statement]);

        $this->assertEquals($statement, $connection->query('sql-query'));
    }

    public function ddlQueriesProvider()
    {
        return [
            ['CREATE table'],
            ['DROP table'],
            ['ALTER table'],
        ];
    }

    /**
     * @todo         adapt this test when DDL is implemented
     * @dataProvider ddlQueriesProvider
     *
     * @param $query
     */
    public function testQueryWithDdl($query)
    {
        $longRunningOperation = $this->createMock(LongRunningOperation::class);
        $longRunningOperation->expects($this->once())->method('pollUntilComplete');

        $database = $this->createMock(Database::class);
        $database->method('updateDdl')->with($query)->willReturn($longRunningOperation);

        $connection = $this->getSpannerConnection(null, $database);

        $this->assertTrue($connection->query($query));
    }

    public function deleteDataProvider()
    {
        return [
            [
                'users',
                [
                    'identifier' => 1
                ],
                'DELETE FROM users WHERE identifier = 1'
            ],
            [
                'events',
                [
                    'event_id' => 1,
                    'id' => 4
                ],
                'DELETE FROM events WHERE event_id = 1 AND id = 4'
            ],
            [
                'test-takers',
                [
                    'firstname' => 'firstname',
                    'lastname' => 'lastname'
                ],
                'DELETE FROM test-takers WHERE firstname = "firstname" AND lastname = "lastname"'
            ],
        ];
    }

    /**
     * @dataProvider deleteDataProvider
     *
     * @param $tableName
     * @param $identifiers
     * @param $expectedQuery
     *
     * @throws InvalidArgumentException
     */
    public function testDelete($tableName, $identifiers, $expectedQuery)
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('commit');
        $transaction->expects($this->once())->method('executeUpdate')->will(
            $this->returnCallback(
                function ($arg) {
                    return $arg;
                }
            )
        );

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('runTransaction')
            ->with(
                $this->callback(
                    function ($closure) use ($transaction, $expectedQuery) {
                        return $closure($transaction) == $expectedQuery;
                    }
                )
            );

        $connection = $this->getSpannerConnection(null, $database);
        $connection->delete($tableName, $identifiers);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testDeleteWithEmptyIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty criteria was used, expected non-empty criteria');
        $this->getSpannerConnection()->delete('users', []);
    }

    public function updateDataProvider()
    {
        return [
            [
                'users',
                [
                    'lastname' => 'fixture'
                ],
                [
                    'id' => 1
                ],
                'UPDATE users SET lastname = "fixture" WHERE id = 1'
            ],
            [
                'events',
                [
                    'event_name' => 'name',
                    'event_log' => 4
                ],
                [
                    'name' => "fixture"
                ],
                'UPDATE events SET event_name = "name", event_log = 4 WHERE name = "fixture"'
            ],
        ];
    }

    /**
     * @dataProvider updateDataProvider
     *
     * @param $tableName
     * @param $data
     * @param $identifiers
     * @param $expectedQuery
     *
     * @throws InvalidArgumentException
     */
    public function testUpdate($tableName, $data, $identifiers, $expectedQuery)
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('commit');
        $transaction->expects($this->once())->method('executeUpdate')->will(
            $this->returnCallback(
                function ($arg) {
                    return $arg;
                }
            )
        );

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('runTransaction')
            ->with(
                $this->callback(
                    function ($closure) use ($transaction, $expectedQuery) {
                        return $closure($transaction) == $expectedQuery;
                    }
                )
            );

        $connection = $this->getSpannerConnection(null, $database);
        $connection->update($tableName, $data, $identifiers);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testUpdateWithEmptyIdentifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty criteria was used, expected non-empty criteria');
        $this->getSpannerConnection()->update('users', [], []);
    }

    public function testInsert()
    {
        $tableName = 'users';
        $data = ['id' => 1, 'name' => 'fixture'];
        $database = $this->createMock(Database::class);
        $database->expects($this->once())->method('insert')->with($tableName, $data);
        $this->getSpannerConnection(null, $database)->insert($tableName, $data);
    }

    /**
     * @dataProvider ddlQueriesProvider
     *
     * @param $query
     */
    public function testExecWithDdlQuery($query)
    {
        $longRunningOperation = $this->createMock(LongRunningOperation::class);
        $longRunningOperation->expects($this->once())->method('pollUntilComplete');

        $database = $this->createMock(Database::class);
        $database->method('updateDdl')->with($query)->willReturn($longRunningOperation);

        $connection = $this->getSpannerConnection(null, $database);

        $this->assertTrue($connection->exec($query));
    }

    public function updateDataProviderWithNull()
    {
        return [
            [
                'customers',
                [
                    'name' => 'test'
                ],
                [
                    'payment' => null
                ],
                'UPDATE customers SET name = "test" WHERE payment IS NULL'
            ],
        ];
    }

    /**
     * @dataProvider updateDataProviderWithNull
     *
     * @param $tableName
     * @param $data
     * @param $identifiers
     * @param $expectedQuery
     *
     * @throws InvalidArgumentException
     */
    public function testUpdateWithNullExpression($tableName, $data, $identifiers, $expectedQuery)
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->any())
            ->method('getIsNullExpression')
            ->will(
                $this->returnCallback(
                    function () {
                        return func_get_arg(0) . ' IS NULL';
                    }
                )
            );

        $driver = $this->createConfiguredMock(Driver::class, ['getDatabasePlatform' => $platform]);

        $transaction = $this->createMock(Transaction::class);
        $transaction->expects($this->once())->method('commit');
        $transaction->expects($this->once())->method('executeUpdate')->will(
            $this->returnCallback(
                function ($arg) {
                    return $arg;
                }
            )
        );

        $database = $this->createMock(Database::class);
        $database->expects($this->atLeastOnce())
            ->method('runTransaction')
            ->with(
                $this->callback(
                    function ($closure) use ($transaction, $expectedQuery) {
                        return $closure($transaction) == $expectedQuery;
                    }
                )
            );

        $connection = $this->getSpannerConnection($driver, $database);
        $connection->update($tableName, $data, $identifiers);
    }

    public function testTransactional()
    {
        $transaction = $this->createMock(Transaction::class);

        // This function just returns the trabnsaction object, so that we can easily test whether it's well passed.
        $closure = static function (Transaction $t) {
            return $t;
        };

        $database = $this->createMock(Database::class);
        $database
            ->expects($this->once())
            ->method('runTransaction')
            ->with(
                $this->callback(
                    static function (Closure $transactionContent) use ($transaction, $closure) {
                        return $transactionContent($transaction) === $closure($transaction);
                    }
                )
            )
            ->willReturn($transaction);

        $connection = $this->getSpannerConnection(null, $database);
        $this->assertEquals($transaction, $connection->transactional($closure));
    }

    public function testLastInsertId()
    {
        $this->expectException(\Exception::class);
        $this->getSpannerConnection()->lastInsertId();
    }

    public function testBeginTransaction()
    {
        $this->assertNull($this->getSpannerConnection()->beginTransaction());
    }

    public function testCommit()
    {
        $this->assertNull($this->getSpannerConnection()->commit());
    }

    public function testRollBack()
    {
        $this->assertNull($this->getSpannerConnection()->rollBack());
    }

    public function testErrorCode()
    {
        $this->expectException(\Exception::class);
        $this->getSpannerConnection()->errorCode();
    }

    public function testErrorInfo()
    {
        $this->expectException(\Exception::class);
        $this->getSpannerConnection()->errorInfo();
    }
}
