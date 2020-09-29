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
use Google\Cloud\Spanner\Session\CacheSessionPool;
use Google\Cloud\Spanner\Session\SessionPoolInterface;
use Google\Cloud\Spanner\SpannerClient;
use OAT\Library\DBALSpanner\SpannerClient\SpannerClientFactory;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerPlatform;
use OAT\Library\DBALSpanner\SpannerSchemaManager;
use OAT\Library\DBALSpanner\Tests\_helpers\Configuration;
use OAT\Library\DBALSpanner\Tests\_helpers\ConfigurationTrait;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class SpannerDriverTest extends TestCase
{
    use NoPrivacyTrait;
    use ConfigurationTrait;

    /** @var SpannerDriver */
    private $subject;

    /** @var SpannerClientFactory|MockObject */
    private $spannerClientFactory;

    /** @var SessionPoolInterface|MockObject */
    private $sessionPool;

    public function setUp(): void
    {
        $this->spannerClientFactory = $this->createMock(SpannerClientFactory::class);
        $this->sessionPool = $this->createMock(SessionPoolInterface::class);
        $this->subject = new SpannerDriver($this->spannerClientFactory, $this->sessionPool);
    }

    public function testConstructorWithDefaultValues()
    {
        $this->assertSame(
            $this->spannerClientFactory,
            $this->getPrivateProperty($this->subject, 'spannerClientFactory')
        );
        $this->assertSame(
            $this->sessionPool,
            $this->getPrivateProperty($this->subject, 'sessionPool')
        );
    }

    public function testConnect()
    {
        $subject = new SpannerDriver();
        $authPool = $this->createMock(CacheItemPoolInterface::class);
        $sessionPool = $this->createMock(SessionPoolInterface::class);
        $keyPath = $this->getConfiguration(Configuration::CONFIG_KEY_FILE_PATH);
        $clientConfiguration = [
            'a' => '1',
            'b' => '2',
        ];

//        $instance = $this->createConfiguredMock(
//            Instance::class,
//            [
//                'databases' => [$this->createConfiguredMock(Database::class, ['name' => 'titi'])],
//                'name' => 'noname',
//                'database' => $this->createMock(Database::class),
//            ]
//        );
//        $this->setPrivateProperty($subject, 'instance', $instance);

        $parameters = ['instance' => 'toto', 'dbname' => 'titi'];

        $connection = $subject->connect(
            $parameters,
            null,
            null,
            [
                SpannerDriver::DRIVER_OPTION_AUTH_POOL => $authPool,
                SpannerDriver::DRIVER_OPTION_SESSION_POOL => $sessionPool,
                SpannerDriver::DRIVER_OPTION_CREDENTIALS_FILE_PATH => $keyPath,
                SpannerDriver::DRIVER_OPTION_CLIENT_CONFIGURATION => $clientConfiguration,
            ]
        );

        $this->assertInstanceOf(SpannerConnection::class, $connection);
        $this->assertSame('titi', $subject->getDatabase($this->createMock(Connection::class)));
        $this->assertSame('toto', $this->getPrivateProperty($subject, 'instanceName'));
        $this->assertSame($sessionPool, $this->getPrivateProperty($subject, 'sessionPool'));
        $this->assertSame($subject, $this->getPrivateProperty($connection, 'driver'));
    }

    public function testGetDatabasePlatform()
    {
        $this->assertInstanceOf(SpannerPlatform::class, $this->subject->getDatabasePlatform());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf(
            SpannerSchemaManager::class,
            $this->subject->getSchemaManager($this->createMock(Connection::class))
        );
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
        $this->subject->transactional(
            function () {
            }
        );
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
