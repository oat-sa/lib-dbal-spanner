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

namespace Oat\DbalSpanner\Tests\Unit;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Doctrine\DBAL\FetchMode;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Result;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use OAT\Library\DBALSpanner\SpannerStatement;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpannerStatementTest extends TestCase
{
    /** @var Database|MockObject */
    private $database;

    /** @var ParameterTranslator|MockObject */
    private $parameterTranslator;

    public function setUp(): void
    {
        $this->database = $this->createConfiguredMock(Database::class, []);
        $this->parameterTranslator = $this->getMockBuilder(ParameterTranslator::class)
            ->disableOriginalConstructor()
            ->setMethods(['translatePlaceHolders'])
            ->getMock();
    }

    public function testConstructor()
    {
        $originalSql = 'sql string with question marks';
        $newSql = 'sql string with translated placeholders';
        $this->parameterTranslator->method('translatePlaceHolders')->with($originalSql)->willReturn($newSql);
        $subject = new SpannerStatement($this->database, $originalSql, $this->parameterTranslator);
        $this->assertEquals($newSql, $this->getPrivateProperty($subject, 'sql'));
    }

    public function testConstructorWithMixedParameterSyntaxesThrowsException()
    {
        $this->parameterTranslator->method('translatePlaceHolders')->willThrowException(
            new InvalidArgumentException("Statement '' can not use both named and positional parameters.")
        );
        $this->expectException(InvalidArgumentException::class);
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
    }

    public function mixedParameterSyntaxesToTest()
    {
        return [['?@'], ['?:'], ['?:@']];
    }

    public function testBindValue()
    {
        $key = 'key';
        $value = 'value';

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);

        // First check that the key doesn't exist in the bound values.
        $boundValues = $this->getPrivateProperty($subject, 'boundValues');
        $this->assertFalse(isset($boundValues[$key]));

        $subject->bindValue($key, $value);

        $boundValues = $this->getPrivateProperty($subject, 'boundValues');
        $this->assertEquals($value, $boundValues[$key]);
    }

    public function testBindParam()
    {
        $variable = 'variable';
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);

        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('Operation \'OAT\Library\DBALSpanner\SpannerStatement::bindParam\' is not supported by platform.');
        $subject->bindParam('column', $variable);
    }

    // TODO:
    public function testExecute()
    {
        $this->markTestIncomplete();
    }

    /**
     * @dataProvider dmlStatementToTest
     */
    public function testIsDmlStatement($statement, $expected)
    {
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);

        $this->assertEquals($expected, $subject->isDmlStatement($statement));
    }

    public function dmlStatementToTest()
    {
        return [
            [' insert blah', true],
            ['            update ', true],
            ['            update', false],
            ['delete     ', true],
            ['inserted', false],
        ];
    }

    public function testRowCountWithDmlStatementReturnsAffectedRows()
    {
        $affectedRows = 12;
        $sql = 'delete from table1 where true;';

        $this->parameterTranslator->method('translatePlaceHolders')->willReturnArgument(0);

        $subject = new SpannerStatement($this->database, $sql, $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'affectedRows', $affectedRows);

        $this->assertEquals($affectedRows, $subject->rowCount());
    }

    public function testRowCountWithSqlStatementAndNoResultReturns0()
    {
        $sql = 'select * from table1 where true;';

        $this->parameterTranslator->method('translatePlaceHolders')->willReturnArgument(0);

        $subject = new SpannerStatement($this->database, $sql, $this->parameterTranslator);

        $this->assertEquals(0, $subject->rowCount());
    }

    public function testRowCountWithSqlStatementReturnsResultRows()
    {
        $sql = 'select * from table1 where true;';
        $row1 = ["column1" => 'value1'];
        $row2 = ["column1" => 'value2'];
        $rows = [$row1, $row2];

        /** @var Result|MockObject $result */
        $result = $this->getMockBuilder(Result::class)
            ->disableOriginalConstructor()
            ->setMethods(['rows'])
            ->getMock();
        $result->method('rows')->willReturn($rows);

        $this->parameterTranslator->method('translatePlaceHolders')->willReturnArgument(0);

        $subject = new SpannerStatement($this->database, $sql, $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'result', $result);

        $this->assertEquals(count($rows), $subject->rowCount());
    }

    /**
     * @dataProvider modesToTest
     */
    public function testSetFetchMode($fetchMode, $expected)
    {
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $subject->setFetchMode($fetchMode);
        $this->assertEquals($expected, $this->getPrivateProperty($subject, 'defaultFetchMode'));
    }

    public function modesToTest()
    {
        return [
            [FetchMode::ASSOCIATIVE, Result::RETURN_ASSOCIATIVE],
            [FetchMode::NUMERIC, Result::RETURN_ZERO_INDEXED],
            ['not existing mode', Result::RETURN_ASSOCIATIVE],
        ];
    }

    public function testFetch()
    {
        $value1 = 'value1';
        $value2 = 'value2';
        $key = 'key';
        $rows = [
            [$key => $value1],
            [$key => $value2],
        ];

        /** @var Result|MockObject $result */
        $result = $this->getMockBuilder(Result::class)
            ->disableOriginalConstructor()
            ->setMethods(['rows'])
            ->getMock();
        $result->method('rows')->with(Result::RETURN_ASSOCIATIVE)->willReturn($rows);

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'result', $result);

        $this->assertEquals([$key => $value1], $subject->fetch());
        $this->assertEquals(false, $subject->fetch(null, PDO::FETCH_ORI_ABS, 3));
    }

    public function testFetchWithColumnFetchMode()
    {
        $value1 = 'value1';
        $value2 = 'value2';
        $rows = [[$value1], [$value2]];

        /** @var Result|MockObject $result */
        $result = $this->getMockBuilder(Result::class)
            ->disableOriginalConstructor()
            ->setMethods(['rows'])
            ->getMock();
        $result->method('rows')->with(Result::RETURN_ZERO_INDEXED)->willReturn($rows);

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'result', $result);

        $this->assertEquals($value1, $subject->fetch(FetchMode::COLUMN));
    }

    public function testFetchWithNoResultReturnsFalse()
    {
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->assertFalse($subject->fetch());
    }

    /**
     * @dataProvider offsetsToTest
     */
    public function testFindOffset($rowCount, $cursorOrientation, $cursorOffset, $offset, $expected)
    {
        $row = ['key' => 'value'];
        $rows = array_fill(0, $rowCount, $row);

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'rows', $rows);
        $this->setPrivateProperty($subject, 'offset', $offset);

        $this->assertEquals($expected, $subject->findOffset($cursorOrientation, $cursorOffset));
    }

    public function offsetsToTest()
    {
        return [
            [0, PDO::FETCH_ORI_NEXT, 0, -1, -1],
            [1, PDO::FETCH_ORI_NEXT, 0, -1, 0],
            [2, PDO::FETCH_ORI_NEXT, 0, 0, 1],
            [3, PDO::FETCH_ORI_NEXT, 0, 2, -1],
            [0, PDO::FETCH_ORI_PRIOR, 0, -1, -1],
            [1, PDO::FETCH_ORI_PRIOR, 0, -1, -1],
            [2, PDO::FETCH_ORI_PRIOR, 0, 0, -1],
            [2, PDO::FETCH_ORI_PRIOR, 0, 1, 0],
            [0, PDO::FETCH_ORI_FIRST, 0, 0, -1],
            [1, PDO::FETCH_ORI_FIRST, 0, 0, 0],
            [0, PDO::FETCH_ORI_LAST, 0, 0, -1],
            [1, PDO::FETCH_ORI_LAST, 0, 0, 0],
            [12, PDO::FETCH_ORI_LAST, 0, 0, 11],
            [0, PDO::FETCH_ORI_ABS, 12, 0, -1],
            [1, PDO::FETCH_ORI_ABS, 0, 0, 0],
            [1, PDO::FETCH_ORI_ABS, 1, 0, -1],
            [1, PDO::FETCH_ORI_ABS, -12, 0, -1],
            [12, PDO::FETCH_ORI_ABS, 5, 0, 5],
            [0, PDO::FETCH_ORI_REL, 12, 0, -1],
            [1, PDO::FETCH_ORI_REL, 0, 0, 0],
            [1, PDO::FETCH_ORI_REL, 1, 0, -1],
            [1, PDO::FETCH_ORI_REL, -12, 0, -1],
            [12, PDO::FETCH_ORI_REL, 5, 0, 5],
            [0, PDO::FETCH_ORI_REL, 2, 1, -1],
            [1, PDO::FETCH_ORI_REL, 0, 1, -1],
            [1, PDO::FETCH_ORI_REL, 0, -2, -1],
            [1, PDO::FETCH_ORI_REL, 1, 1, -1],
        ];
    }

    public function testFindOffsetWithUnknownCursorOrientationThrowsException()
    {
        // Unknown orientation
        $cursorOrientation = 1012;

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'rows', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown cursorOrientation ' . $cursorOrientation . ' parameter.');
        $subject->findOffset($cursorOrientation);
    }

    /**
     * @dataProvider fetchAllToTest
     */
    public function testFetchAll($fetchMode, $rows, $expectedRows)
    {
        /** @var Result|MockObject $result */
        $result = $this->getMockBuilder(Result::class)
            ->disableOriginalConstructor()
            ->setMethods(['rows'])
            ->getMock();
        $result->method('rows')->with(Result::RETURN_ASSOCIATIVE)->willReturn($rows);

        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $this->setPrivateProperty($subject, 'result', $result);

        $this->assertEquals($expectedRows, $subject->fetchAll($fetchMode));
    }

    public function fetchAllToTest()
    {
        $value1 = 'value1';
        $value2 = 'value2';
        $key = 'key';
        $rows = [
            [$key => $value1],
            [$key => $value2],
        ];
        $object1 = new \StdClass();
        $object1->$key = $value1;
        $object2 = new \StdClass();
        $object2->$key = $value2;
        $objects = [$object1, $object2];

        return [
            [Result::RETURN_ASSOCIATIVE, $rows, $rows],
            [PDO::FETCH_OBJ, $rows, $objects],
        ];
    }

    /**
     * @dataProvider realModesToTest
     */
    public function testGetRealMode($fetchMode, $defaultFetchMode, $expectedMode, $fetchObjects)
    {
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);
        $subject->setFetchMode($defaultFetchMode);
        $this->assertEquals($expectedMode, $subject->getRealMode($fetchMode));
        $this->assertEquals($fetchObjects, $this->getPrivateProperty($subject, 'fetchObjects'));
    }

    public function realModesToTest()
    {
        $randomMode = 'random mode';
        return [
            [$randomMode, '', $randomMode, false],
            [PDO::FETCH_OBJ, '', Result::RETURN_ASSOCIATIVE, true],
            [null, FetchMode::ASSOCIATIVE, Result::RETURN_ASSOCIATIVE, false],
            [null, FetchMode::NUMERIC, Result::RETURN_ZERO_INDEXED, false],
            [null, $randomMode, Result::RETURN_ASSOCIATIVE, false],
            [null, PDO::FETCH_OBJ, Result::RETURN_ASSOCIATIVE, false],
        ];
    }

    public function testFetchAllWithNoResultReturnsFalse()
    {
        $subject = new SpannerStatement($this->database, '', $this->parameterTranslator);

        $this->assertFalse($subject->fetchAll());
    }

    // TODO:
    public function testFetchColumn()
    {
        $this->markTestIncomplete();
    }

    private function getPrivateProperty($object, $propertyName)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);
        $value = $property->getValue($object);
        $property->setAccessible(false);

        return $value;
    }

    private function setPrivateProperty($object, $propertyName, $value)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
        $property->setAccessible(false);
    }
}
