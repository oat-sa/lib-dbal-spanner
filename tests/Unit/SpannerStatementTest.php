<?php

namespace Oat\DbalSpanner\Tests\Unit;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Spanner\Database;
use OAT\Library\DBALSpanner\Parameters\ParameterTranslator;
use OAT\Library\DBALSpanner\SpannerStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SpannerStatementTest extends TestCase
{
    /** @var SpannerStatement */
    private $subject;

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
