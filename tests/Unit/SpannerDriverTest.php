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
use Google\Cloud\Core\Exception\NotFoundException;
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

    public function testSelectDatabaseNotFoundWithException()
    {
        $instance = $this->createConfiguredMock(
            Instance::class,
            [
                'databases' => [
                    $this->createConfiguredMock(Database::class, ['name' => 'titi']),
                    $this->createConfiguredMock(Database::class, ['name' => 'another']),
                    $this->createConfiguredMock(Database::class, ['name' => 'still-not-exist']),
                ],
                'name' => 'instance-name',
                'database' => $this->createMock(Database::class),
            ]
        );
        $driver = $this->subject;
        $this->setPrivateProperty($driver, 'instance', $instance);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(sprintf("Database '%s' does not exist on instance '%s'.", 'not-existing', 'instance-name'));
        $driver->selectDatabase('not-existing', []);
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

    public function testGetInstanceWithNotExistingInstanceThrowsException()
    {
        $instanceName = 'instance name';

        $instance = $this->createConfiguredMock(Instance::class, ['exists' => false]);
        $spannerClient = $this->createConfiguredMock(SpannerClient::class, ['instance' => $instance]);
        $this->spannerClientFactory->method('create')->willReturn($spannerClient);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Instance \'' . $instanceName . '\' does not exist.');
        $this->subject->getInstance($instanceName);
    }

    public function testGetInstanceWithExistingInstanceSetsInstanceNameAndReturnsInstance()
    {
        $instanceName = 'instance name';

        $instance = $this->createConfiguredMock(Instance::class, ['exists' => true]);
        $spannerClient = $this->createConfiguredMock(SpannerClient::class, ['instance' => $instance]);
        $this->spannerClientFactory->method('create')->willReturn($spannerClient);

        $this->assertEquals($instance, $this->subject->getInstance($instanceName));
        $this->assertEquals($instanceName, $this->getPrivateProperty($this->subject, 'instanceName'));
    }
}
