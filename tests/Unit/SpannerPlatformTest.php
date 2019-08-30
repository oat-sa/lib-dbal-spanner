<?php

namespace OAT\Library\DBALSpanner\Tests\Unit;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Index;
use OAT\Library\DBALSpanner\SpannerDriver;
use OAT\Library\DBALSpanner\SpannerKeywords;
use OAT\Library\DBALSpanner\SpannerPlatform;
use OAT\Library\DBALSpanner\Tests\_helpers\NoPrivacyTrait;
use PHPUnit\Framework\TestCase;

class SpannerPlatformTest extends TestCase
{
    use NoPrivacyTrait;

    /**
     * @var SpannerPlatform
     */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new SpannerPlatform();
    }

    public function testGetName(): void
    {
        $this->assertEquals(SpannerDriver::DRIVER_NAME, $this->subject->getName());
    }

    /**
     * @dataProvider constantsToTest
     */
    public function testGetImplementationConstants($method, $expected, $args = [[]])
    {
        $this->assertEquals($expected, $this->subject->$method(...$args));
    }

    public function constantsToTest()
    {
        $table = 'table1';

        return [
            ['getBooleanTypeDeclarationSQL', 'BOOL'],
            ['getIntegerTypeDeclarationSQL', 'INT64'],
            [
                'getListTablesSQL',
                'SELECT table_name
      FROM information_schema.tables
      WHERE table_catalog = \'\' 
      AND table_schema = \'\'',
            ],
            [
                'getListTableColumnsSQL',
                'SELECT column_name AS Field, spanner_type AS Type, is_nullable AS `Null`, "" AS `Key`, "" AS `Default`, "" AS Extra, "" AS Comment
      FROM information_schema.columns
      WHERE table_name = "' . $table . '"
      AND table_catalog = "" 
      AND table_schema = ""
      ORDER BY ordinal_position',
                [$table],
            ],

            [
                'getListTableColumnsSQL',
                'SELECT column_name AS Field, spanner_type AS Type, is_nullable AS `Null`, "" AS `Key`, "" AS `Default`, "" AS Extra, "" AS Comment
      FROM information_schema.columns
      WHERE table_name = "' . $table . '"
      AND table_catalog = "" 
      AND table_schema = ""
      ORDER BY ordinal_position',
                [$table],
            ],
            [
                'getListTableIndexesSQL',
                'SELECT is_unique AS Non_Unique, i.index_name AS Key_name, column_name AS Column_Name, "" AS Sub_Part, i.index_type AS Index_Type
      FROM information_schema.indexes i
      INNER JOIN information_schema.index_columns ic
              ON i.table_name = ic.table_name
      WHERE i.table_name = "' . $table . '"
      AND i.table_catalog = "" 
      AND i.table_schema = ""
      ORDER BY ordinal_position',
                [$table],
            ],
            ['supportsForeignKeyConstraints', false],
            ['getBigIntTypeDeclarationSQL', 'INT64'],
            ['getSmallIntTypeDeclarationSQL', 'INT64'],
            ['getDateTimeTypeDeclarationSQL', 'TIMESTAMP'],
            ['getDateTimeFormatString', 'Y-m-d\TH:i:s.u\Z'],
            ['getDateTimeTzFormatString', 'Y-m-d\TH:i:s.u\Z'],
            ['getDateTypeDeclarationSQL', 'DATE'],
            ['getFloatDeclarationSQL', 'FLOAT64'],
            ['getClobTypeDeclarationSQL', 'BYTES'],
            ['getBlobTypeDeclarationSQL', 'BYTES'],
            ['getIdentifierQuoteCharacter', ''],
        ];
    }

    public function testGetCommonIntegerTypeDeclarationSQL()
    {
        $this->assertEquals(
            'INT64',
            $this->invokePrivateMethod($this->subject, '_getCommonIntegerTypeDeclarationSQL', [['autoincrement' => false]])
        );
    }

    public function testGetCommonIntegerTypeDeclarationSQLWithAutoIncrementThrowsException()
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('AUTO_INCREMENT is not supported by GCP Spanner.');
        $this->invokePrivateMethod($this->subject, '_getCommonIntegerTypeDeclarationSQL', [['autoincrement' => true]]);
    }

    public function testInitializeDoctrineTypeMappings()
    {
        $this->invokePrivateMethod($this->subject, 'initializeDoctrineTypeMappings', []);
        $this->assertEquals(
            [
                'array' => '',
                'bool' => 'boolean',
                'bytes' => 'blob',
                'date' => 'date',
                'float64' => 'float',
                'int64' => 'integer',
                'string' => 'string',
                'struct' => '',
                'timestamp' => 'datetime',
            ],
            $this->getPrivateProperty($this->subject, 'doctrineTypeMapping')
        );
    }

    public function testGetTruncateTableSQL()
    {
        $this->expectException(\Exception::class);
        $this->subject->getTruncateTableSQL('');
    }

    public function testGetReservedKeywordsClass()
    {
        $this->assertEquals(
            SpannerKeywords::class,
            $this->invokePrivateMethod($this->subject, 'getReservedKeywordsClass', [])
        );
    }

    public function testGetVarcharTypeDeclarationSQLSnippet()
    {
        $length = '1012';
        $this->assertEquals(
            'STRING(' . $length . ')',
            $this->invokePrivateMethod($this->subject, 'getVarcharTypeDeclarationSQLSnippet', [$length, ''])
        );
    }

    public function testGetCreateTableSQL()
    {
        $tableName = 'table1';
        $column1 = 'column1';
        $columnDefinition = 'columnDefinition';
        $primaryvalue = 'primary field name';
        $indexName = 'index1';

        $columns = [$column1 => ['columnDefinition' => $columnDefinition]];
        $options = [
            'primary' => ['key1' => $primaryvalue, 'key2' => $primaryvalue],
            'indexes' => [new Index($indexName, [$column1])],
        ];

        $this->assertEquals(
            [
                'CREATE TABLE ' . $tableName . ' (' . $column1 . ' ' . $columnDefinition . ') PRIMARY KEY (' . $primaryvalue . ')',
                'CREATE INDEX index1 ON table1 (column1)',
            ],
            $this->invokePrivateMethod($this->subject, '_getCreateTableSQL', [$tableName, $columns, $options])
        );
    }
}
