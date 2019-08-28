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
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerPlatform;
use OAT\Library\DBALSpanner\SpannerSchemaManager;
use PHPUnit\Framework\TestCase;

class SpannerDriverTest extends  TestCase
{
    public function testConnect()
    {
        $instance = $this->createConfiguredMock(
            Instance::class,
            [
                'databases' => [$this->createConfiguredMock(Database::class, ['name' => 'titi'])],
                'name' => 'noname',
                'database' => $this->createMock(Database::class)
            ]
        );
        $driver = new SpannerDriver();
        $property = new \ReflectionProperty(SpannerDriver::class, 'instance');
        $property->setAccessible(true);
        $property->setValue($driver, $instance);

        $parameters = ['instance' => 'toto', 'dbname' => 'titi'];
        $connection = $driver->connect($parameters);

        $this->assertInstanceOf(SpannerConnection::class, $connection);
        $this->assertEquals('titi', $driver->getDatabase($this->createMock(Connection::class)));
        $databaseNameProperty = new \ReflectionProperty(SpannerDriver::class, 'instanceName');
        $databaseNameProperty->setAccessible(true);
        $this->assertEquals('toto', $databaseNameProperty->getValue($driver));
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
                'database' => $this->createMock(Database::class)
            ]
        );
        $driver = new SpannerDriver();
        $property = new \ReflectionProperty(SpannerDriver::class, 'instance');
        $property->setAccessible(true);
        $property->setValue($driver, $instance);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(sprintf("Database '%s' does not exist on instance '%s'.", 'not-existing', 'instance-name'));
        $driver->selectDatabase('not-existing');
    }

    public function testGetDatabasePlatform()
    {
        $this->assertInstanceOf(SpannerPlatform::class, (new SpannerDriver())->getDatabasePlatform());
    }

    public function testGetSchemaManager()
    {
        $this->assertInstanceOf(SpannerSchemaManager::class, (new SpannerDriver())->getSchemaManager($this->createMock(Connection::class)));
    }

    public function testGetName()
    {
        $this->assertSame(SpannerDriver::DRIVER_NAME, (new SpannerDriver())->getName());
    }

    public function testGetDatabase()
    {
        $driver = new SpannerDriver();
        $databaseNameProperty = new \ReflectionProperty(SpannerDriver::class, 'databaseName');
        $databaseNameProperty->setAccessible(true);
        $databaseNameProperty->setValue($driver, 'fixture');
        $this->assertEquals('fixture', $driver->getDatabase($this->createMock(Connection::class)));
    }

    public function failedParseParametersProvider()
    {
        return [
            [['toto', 'test']],
            [['instance' => 'toto', 'test' => 'dbname']],
            [[]],
            [['dbname' => 'toto']],
            [['instance']],
            [['dbname']]
        ];
    }

    /**
     * @dataProvider failedParseParametersProvider
     * @param $parameters
     */
    public function testFailedParseParameters($parameters)
    {
        $this->expectException(\LogicException::class);
        (new SpannerDriver())->parseParameters($parameters);
    }

    public function testParseParameters()
    {
        $parameters = ['instance' => 'toto', 'dbname' => 'titi'];
        $this->assertEquals(['toto', 'titi'], (new SpannerDriver())->parseParameters($parameters));
    }

    public function testListDatabases()
    {
        $instance = $this->createConfiguredMock(
            Instance::class,
            [
                'databases' => [
                    'database1',
                    $this->createConfiguredMock(Database::class, ['name' => 'salut']),
                    $this->createConfiguredMock(Database::class, ['name' => '195.168.1.2/test.db'])]
            ]
        );
        $driver = new SpannerDriver();
        $property = new \ReflectionProperty(SpannerDriver::class, 'instance');
        $property->setAccessible(true);
        $property->setValue($driver, $instance);

        $this->assertEquals(['salut', 'test.db'], $driver->listDatabases('test'));
    }

    public function testListDatabasesWithNullInstanceName()
    {
        $instance = $this->createMock(Instance::class);
        $instance->expects($this->once())
            ->method('databases')
            ->willReturn([$this->createConfiguredMock(Database::class, ['name' => 'salut'])]);

        $driver = new SpannerDriver();
        $instanceProperty = new \ReflectionProperty(SpannerDriver::class, 'instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue($driver, $instance);

        $nameProperty = new \ReflectionProperty(SpannerDriver::class, 'instanceName');
        $nameProperty->setAccessible(true);
        $nameProperty->setValue($driver, 'fixture');

        $this->assertEquals(['salut'], $driver->listDatabases(''));
    }
}