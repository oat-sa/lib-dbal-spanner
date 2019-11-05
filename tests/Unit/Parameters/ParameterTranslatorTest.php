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

namespace Oat\DbalSpanner\Tests\Unit\Parameters;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\TestCase;

class ParameterTranslatorTest extends TestCase
{
    use NoPrivacyTrait;

    /** @var ParameterTranslator */
    protected $subject;

    public function setUp(): void
    {
        $this->subject = new ParameterTranslator();
    }

    /**
     * @dataProvider placeHoldersToTest
     */
    public function testTranslatePlaceHolders($sql, $expected, $expectedPositionalParameterCount)
    {
        $this->assertEquals($expected, $this->subject->translatePlaceHolders($sql));
        $this->assertEquals($expectedPositionalParameterCount, $this->getPrivateProperty($this->subject, 'positionalParameterCount'));
    }

    public function placeHoldersToTest()
    {
        $expectedNamed = 'SELECT * FROM statements WHERE modelid = @model AND subject = @subject';
        $expectedPositional = 'SELECT * FROM statements WHERE modelid = @param1 AND subject = @param2';

        return [
            ['', '', 0],
            ['SELECT * FROM statements WHERE modelid = :model AND subject = :subject', $expectedNamed, 0],
            ['SELECT * FROM statements WHERE modelid = @model AND subject = @subject', $expectedNamed, 0],
            ['SELECT * FROM statements WHERE modelid = @model AND subject = :subject', $expectedNamed, 0],
            ['SELECT * FROM statements WHERE modelid = ? AND subject = ?', $expectedPositional, 2],
            ['SELECT * FROM statements WHERE modelid = ? AND subject = ?;', $expectedPositional . ';', 2],
        ];
    }

    /**
     * @dataProvider mixedParameterSyntaxesToTest
     */
    public function testTranslatePlaceHoldersWithMixedTypesThrowsException(string $sql)
    {
        // Creates a statement with a blank sql string to avoid detection on constructor.
        $this->expectException(InvalidArgumentException::class);
        $this->subject->translatePlaceHolders($sql);
    }

    public function mixedParameterSyntaxesToTest()
    {
        return [['?@'], ['?:'], ['?:@']];
    }

    /**
     * @dataProvider namedParamsToTest
     */
    public function testConvertPositionalToNamedWithNamedParameters(array $expected, array $boundValues, array $params = null)
    {
        $this->assertEquals($expected, $this->subject->convertPositionalToNamed($boundValues, $params));
    }

    public function namedParamsToTest()
    {
        $numericKey0 = 0;
        $numericKey1 = 1;
        $key1 = 'key1';
        $value1 = 'value1';

        return [
            [[[$key1 => $value1],[]], [], [$key1 => $value1]],
            [[[$numericKey1 => $value1],[]], [], [$numericKey1 => $value1]],
            [[[],[]], [], null],
            [[[$numericKey0 => $value1],[]], [$numericKey0 => $value1], null],
        ];
    }

    public function testConvertPositionalToNamedWithWrongParameterCountThrowsException()
    {
        $this->setPrivateProperty($this->subject, 'positionalParameterCount', 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected exactly 2 parameter(s), 1 found.');
        $this->subject->convertPositionalToNamed([], ['value1']);
    }

    /**
     * @dataProvider positionalParamsToTest
     */
    public function testConvertPositionalToNamedWithPositionalParameters(array $expected, array $boundValues, array $params = null)
    {
        $this->setPrivateProperty($this->subject, 'positionalParameterCount', 2);

        $this->assertEquals($expected, $this->subject->convertPositionalToNamed($boundValues, $params));
    }

    public function positionalParamsToTest()
    {
        $numericKey1 = 1;
        $numericKey2 = 2;
        $value1 = 'value1';
        $value2 = 'value2';

        return [
            [[['param1' => $value1, 'param2' => $value2], []], [], [$value1, $value2]],
            [[['param1' => $value1, 'param2' => $value2], []], [$numericKey1 => $value1, $numericKey2 => $value2], null],
        ];
    }
}
