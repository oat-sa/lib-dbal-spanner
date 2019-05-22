<?php

namespace Oat\DbalSpanner\Tests\Unit;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Spanner\Database;
use OAT\Library\DBALSpanner\SpannerStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpannerStatementTest extends TestCase
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
     * @dataProvider uniqueParameterSyntaxToTest
     */
    public function testDetectParameterSyntaxWithOneTypeReturnsType(string $sql, string $expected)
    {
        // Creates a statement with a blank sql string to avoid detection on constructor.
        $subject = new SpannerStatement($this->database, '');
        $this->assertEquals($expected, $subject->detectParameterSyntax($sql));
    }

    public function uniqueParameterSyntaxToTest()
    {
        return [
            ['', SpannerStatement::PARAMETERS_NONE],
            [':', SpannerStatement::PARAMETERS_NAMED],
            ['@', SpannerStatement::PARAMETERS_NAMED],
            [':@', SpannerStatement::PARAMETERS_NAMED],
            ['?', SpannerStatement::PARAMETERS_POSITIONAL],
        ];
    }

    /**
     * @dataProvider mixedParameterSyntaxesToTest
     */
    public function testDetectParameterSyntaxWithMixedTypesThrowsException(string $sql)
    {
        // Creates a statement with a blank sql string to avoid detection on constructor.
        $subject = new SpannerStatement($this->database, '');
        $this->expectException(InvalidArgumentException::class);
        $subject->detectParameterSyntax($sql);
    }

    public function mixedParameterSyntaxesToTest()
    {
        return [['?@'], ['?:'], ['?:@']];
    }

    /**
     * @dataProvider placeHoldersToTest
     *
     * @param $sql
     * @param $expected
     */
    public function testTranslateParameterPlaceHolders($sql, $expected)
    {
        $subject = new SpannerStatement($this->database, $sql);
        $this->assertEquals($expected, $subject->translateParameterPlaceHolders($sql));
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
}
