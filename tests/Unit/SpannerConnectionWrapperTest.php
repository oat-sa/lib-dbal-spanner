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

use Doctrine\DBAL\Driver;
use OAT\Library\DBALSpanner\SpannerConnection;
use OAT\Library\DBALSpanner\SpannerConnectionWrapper;
use PHPUnit\Framework\TestCase;

class SpannerConnectionWrapperTest extends TestCase
{
    public function getConnectionWrapper($connection)
    {
        return new SpannerConnectionWrapper(
            [
                'pdo' => $connection,
            ],
            $this->getMockForAbstractClass(Driver::class)
        );
    }

    public function testPrepare()
    {
        $preparedString = 'SELECT * FROM anytable';

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('prepare')
            ->with($preparedString)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->prepare($preparedString));
    }

    public function testQuery()
    {
        $query = 'SELECT * FROM anytable';

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->query($query));
    }

    public function testDelete()
    {
        $tableExpression = 'anytable';
        $identifiers = ['id' => 3];

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('delete')
            ->with($tableExpression, $identifiers)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->delete($tableExpression, $identifiers));
    }

    public function testUpdate()
    {
        $tableExpression = 'anytable';
        $data = ['name' => 'toto'];
        $identifiers = ['id' => 3];

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('update')
            ->with($tableExpression, $data, $identifiers)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->update($tableExpression, $data, $identifiers));
    }

    public function testInsert()
    {
        $tableExpression = 'anytable';
        $data = ['name' => 'toto'];

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('insert')
            ->with($tableExpression, $data)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->insert($tableExpression, $data));
    }

    public function testExec()
    {
        $statement = 'statement';

        $_conn = $this->createMock(SpannerConnection::class);
        $_conn->expects($this->once())
            ->method('exec')
            ->with($statement)
            ->willReturn('success');

        $this->assertEquals('success', $this->getConnectionWrapper($_conn)->exec($statement));
    }

    public function testLastInsertId()
    {
        $mock = $this->createMock(SpannerConnection::class);
        $mock->expects($this->once())
            ->method('lastInsertId');
        $this->getConnectionWrapper($mock)->lastInsertId();
    }

    public function testBeginTransaction()
    {
        $this->expectNoException(
            function () {
                $this->getConnectionWrapper($this->createMock(SpannerConnection::class))->beginTransaction();
            }
        );
    }

    public function testCommit()
    {
        $this->expectNoException(
            function () {
                $this->getConnectionWrapper($this->createMock(SpannerConnection::class))->commit();
            }
        );
    }

    public function testRollBack()
    {
        $this->expectNoException(
            function () {
                $this->getConnectionWrapper($this->createMock(SpannerConnection::class))->rollBack();
            }
        );
    }

    public function testErrorCode()
    {
        $mock = $this->createMock(SpannerConnection::class);
        $mock->expects($this->once())
            ->method('errorCode');
        $this->getConnectionWrapper($mock)->errorCode();
    }

    public function testErrorInfo()
    {
        $mock = $this->createMock(SpannerConnection::class);
        $mock->expects($this->once())
            ->method('errorInfo');
        $this->getConnectionWrapper($mock)->errorInfo();
    }

    public function expectNoException(callable $function): void
    {
        try {
            $function();
        } catch (\Exception $e) {
            /* An exception was thrown unexpectedly, so fail the test */
            $this->fail();
        }

        /* No exception was thrown, so just make a dummy assertion to pass the test */
        $this->assertTrue(true);
    }
}
