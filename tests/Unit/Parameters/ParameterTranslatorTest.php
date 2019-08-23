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
use Google\Cloud\Spanner\Database;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use OAT\Library\DBALSpanner\SpannerStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParameterTranslatorTest extends TestCase
{
    /** @var SpannerStatement */
    protected $subject;

    /** @var Database|MockObject */
    protected $database;

    public function setUp(): void
    {
        $this->database = $this->createConfiguredMock(Database::class, []);
    }

    /**
     * @dataProvider placeHoldersToTest
     *
     * @param $sql
     * @param $expected
     */
    public function testTranslatePlaceHolders($sql, $expected)
    {
        $subject = new ParameterTranslator();
        $this->assertEquals($expected, $subject->translatePlaceHolders($sql));
    }

    public function placeHoldersToTest()
    {
        $expectedNamed = 'SELECT * FROM statements WHERE modelid = @model AND subject = @subject';
        $expectedPositional = 'SELECT * FROM statements WHERE modelid = @param1 AND subject = @param2';

        return [
            ['', ''],
            ['SELECT * FROM statements WHERE modelid = :model AND subject = :subject', $expectedNamed],
            ['SELECT * FROM statements WHERE modelid = @model AND subject = @subject', $expectedNamed],
            ['SELECT * FROM statements WHERE modelid = @model AND subject = :subject', $expectedNamed],
            ['SELECT * FROM statements WHERE modelid = ? AND subject = ?', $expectedPositional],
            ['SELECT * FROM statements WHERE modelid = ? AND subject = ?;', $expectedPositional . ';'],
        ];
    }

    /**
     * @dataProvider mixedParameterSyntaxesToTest
     */
    public function testTranslatePlaceHoldersWithMixedTypesThrowsException(string $sql)
    {
        // Creates a statement with a blank sql string to avoid detection on constructor.
        $subject = new ParameterTranslator();
        $this->expectException(InvalidArgumentException::class);
        $subject->translatePlaceHolders($sql);
    }

    public function mixedParameterSyntaxesToTest()
    {
        return [['?@'], ['?:'], ['?:@']];
    }
}
