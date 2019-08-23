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

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Spanner\Database;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use OAT\Library\DBALSpanner\SpannerStatement;
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

    private function getPrivateProperty($object, $propertyName)
    {
        $property = new \ReflectionProperty(get_class($object), $propertyName);
        $property->setAccessible(true);
        $value = $property->getValue($object);
        $property->setAccessible(false);

        return $value;
    }
}
