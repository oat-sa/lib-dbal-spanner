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

namespace OAT\Library\DBALSpanner\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Instance;
use Google\Cloud\Spanner\SpannerClient;
use LogicException;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerPlatform;
use OAT\Library\DBALSpanner\SpannerSchemaManager;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpannerDriverTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var SpannerDriver */
    private $subject;

    /** @var SpannerClientFactory|MockObject */
    private $spannerClientFactory;

    public function setUp(): void
    {
        $this->spannerClientFactory = $this->getMockBuilder(SpannerClientFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->subject = new SpannerDriver($this->spannerClientFactory);
    }

    public function testConstructorWithDefaultValues()
    {
        $subject = new SpannerDriver();
        
        $this->assertInstanceOf(SpannerClientFactory::class, $this->getPrivateProperty($subject, 'spannerClientFactory'));
    }
    
    public function testConnect()
    {
        $instance = $this->createConfiguredMock(
            Instance::class,
            [
                'databases' => [$this->createConfiguredMock(Database::class, ['name' => 'titi'])],
                'name' => 'noname',
                'database' => $this->createMock(Database::class),
            ]
        );
        $this->setPrivateProperty($this->subject, 'instance', $instance);

        $parameters = ['instance' => 'toto', 'dbname' => 'titi'];
        $connection = $this->subject->connect($parameters);

        $this->assertInstanceOf(SpannerConnection::class, $connection);
        $this->assertEquals('titi', $this->subject->getDatabase($this->createMock(Connection::class)));
        $this->assertEquals('toto', $this->getPrivateProperty($this->subject, 'instanceName'));
        $this->assertSame($this->subject, $this->getPrivateProperty($connection, 'driver'));
    }

    public function testGetDatabasePlatform()
    {
        $this->assertInstanceOf(SpannerPlatform::class, $this->subject->getDatabasePlatform());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf(SpannerSchemaManager::class, $this->subject->getSchemaManager($this->createMock(Connection::class)));
    }

    public function testGetName()
    {
        $this->assertSame(SpannerDriver::DRIVER_NAME, $this->subject->getName());
    }

    public function testGetDatabase()
    {
        $driver = $this->subject;
        $this->setPrivateProperty($driver, 'databaseName', 'fixture');
        $this->assertEquals('fixture', $driver->getDatabase($this->createMock(Connection::class)));
    }

    public function testTransactional()
    {
        $value = 'whatever';
        $closure = static function () use ($value) {
            return $value;
        };
        
        $connection = $this->createMock(SpannerConnection::class);
        $connection->method('transactional')->with($closure)->willReturn($closure());
        $this->setPrivateProperty($this->subject, 'connection', $connection);
        
        $this->assertEquals($value, $this->subject->transactional($closure));
    }

    /**
     * @throws DBALException
     */
    public function testTransactionalWithoutConnectionThrowsException()
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('Can not run transaction without connecting first.');
        $this->subject->transactional(function () {
        });
    }

    public function testSelectDatabaseNotFoundWithException()
    {
        $dbName = 'db-name';
        $options = ['some' => 'options'];

        $database = $this->createMock(Database::class);

        $instance = $this->createMock(Instance::class);
        $instance->method('database')->with($dbName, $options)->willReturn($database);

        $driver = $this->subject;
        $this->setPrivateProperty($driver, 'instance', $instance);

        $this->assertEquals($database, $driver->selectDatabase($dbName, $options));
    }

    public function testParseParameters()
    {
        $parameters = ['instance' => 'toto', 'dbname' => 'titi'];
        $this->assertEquals(['toto', 'titi'], $this->subject->parseParameters($parameters));
    }

    /**
     * @dataProvider failedParseParametersProvider
     *
     * @param $parameters
     */
    public function testFailedParseParameters($parameters)
    {
        $this->expectException(\LogicException::class);
        $this->subject->parseParameters($parameters);
    }

    public function failedParseParametersProvider()
    {
        return [
            [['toto', 'test']],
            [['instance' => 'toto', 'test' => 'dbname']],
            [[]],
            [['dbname' => 'toto']],
            [['instance']],
            [['dbname']],
        ];
    }

    public function testGetInstanceWithAlreadySetInstanceReturnsInstance()
    {
        $instance = $this->createMock(Instance::class);
        $this->setPrivateProperty($this->subject, 'instance', $instance);
        $this->assertEquals($instance, $this->subject->getInstance('instanceName'));
    }

    public function testGetInstanceWithExistingInstanceSetsInstanceNameAndReturnsInstance()
    {
        $instanceName = 'instance name';

        $instance = $this->createMock(Instance::class);
        $spannerClient = $this->createConfiguredMock(SpannerClient::class, ['instance' => $instance]);
        $this->spannerClientFactory->method('create')->willReturn($spannerClient);

        $this->assertEquals($instance, $this->subject->getInstance($instanceName));
        $this->assertEquals($instanceName, $this->getPrivateProperty($this->subject, 'instanceName'));
    }

    public function testListDatabases()
    {
        $instance = $this->createConfiguredMock(
            Instance::class,
            [
                'databases' => [
                    'database1',
                    $this->createConfiguredMock(Database::class, ['name' => 'salut']),
                    $this->createConfiguredMock(Database::class, ['name' => '195.168.1.2/test.db']),
                ],
            ]
        );
        $this->setPrivateProperty($this->subject, 'instance', $instance);

        $this->assertEquals(['salut', 'test.db'], $this->subject->listDatabases('test'));
    }

    public function testListDatabasesWithNullInstanceName()
    {
        $instance = $this->createMock(Instance::class);
        $instance->expects($this->once())
            ->method('databases')
            ->willReturn([$this->createConfiguredMock(Database::class, ['name' => 'salut'])]);

        $this->setPrivateProperty($this->subject, 'instance', $instance);
        $this->setPrivateProperty($this->subject, 'instanceName', 'fixture');

        $this->assertEquals(['salut'], $this->subject->listDatabases(''));
    }
}
