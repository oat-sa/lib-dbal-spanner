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
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\Type;
use OAT\Library\DBALSpanner\SpannerSchemaManager;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpannerSchemaManagerTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var SpannerSchemaManager */
    private $subject;

    /** @var Connection|MockObject */
    private $connection;

    /** @var AbstractPlatform|MockObject */
    private $platform;

    public function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDriver'])
            ->getMock();

        $this->platform = $this->getMockBuilder(AbstractPlatform::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDoctrineTypeMapping'])
            ->getMockForAbstractClass();

        $this->subject = new SpannerSchemaManager($this->connection, $this->platform);
    }

    public function testListDatabase()
    {
        $db1 = 'whatever';
        $databases = [$db1];

        $driver = $this->getMockBuilder(Driver::class)
            ->disableOriginalConstructor()
            ->setMethods(['listDatabases'])
            ->getMockForAbstractClass();
        $driver->method('listDatabases')->willReturn($databases);

        $this->connection->method('getDriver')->willReturn($driver);

        $this->assertEquals($databases, $this->subject->listDatabases());
    }

    public function testGetPortableTableColumnDefinition()
    {
        $fieldName = 'field1';
        $fieldType = 'STRING';
        $length = 64;
        $tableColumn = [
            'field' => $fieldName,
            'type' => $fieldType . '(' . $length . ')',
            'null' => 'NO',
        ];
        $finalType = Type::STRING;
        $finalOptions = [
            'length' => $length,
            'notnull' => true,
        ];
        $expected = new Column($fieldName, Type::getType($finalType), $finalOptions);

        $this->platform->method('getDoctrineTypeMapping')->with(strtolower($fieldType))->willReturn($finalType);

        $this->assertEquals(
            $expected,
            $this->invokePrivateMethod($this->subject, '_getPortableTableColumnDefinition', [$tableColumn])
        );
    }

    public function testGetPortableTableDefinition()
    {
        $tableName = 'a table\'s name';

        $this->assertEquals($tableName, $this->invokePrivateMethod($this->subject, '_getPortableTableDefinition', [['table_name' => $tableName]]));
    }

    public function testGetPortableTableIndexesList()
    {
        $tableName = 'a table\'s name';
        $fieldName = 'field name';
        $keyName = 'name of the key';
        $nonUnique = false;
        $isPrimary = false;
        $where = 'a where clause';
        $tableIndex = [
            'column_name' => $fieldName,
            'key_name' => $keyName,
            'primary' => $isPrimary,
            'where' => $where,
            'non_unique' => $nonUnique,
        ];
        $finalOptions = ['lengths' => [null], 'where' => $where];
        $expected = new Index($keyName, [$fieldName], !$nonUnique, $isPrimary, [], $finalOptions);
        $this->assertEquals(
            [$keyName => $expected],
            $this->invokePrivateMethod($this->subject, '_getPortableTableIndexesList', [[$tableIndex], $tableName])
        );
    }
}
