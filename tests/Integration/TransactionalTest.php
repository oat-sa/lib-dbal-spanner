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

namespace OAT\Library\DBALSpanner\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Core\Exception\BadRequestException;
use Google\Cloud\Spanner\Transaction;
use OAT\Library\DBALSpanner\Tests\Integration\_helpers\ConfigurationTrait;
use OAT\Library\DBALSpanner\Tests\Integration\_helpers\ConnectionTrait;

class TransactionalTest
{
    use ConfigurationTrait;
    use ConnectionTrait;

    public const TABLE_NAME = 'transactional_test';

    public function prepare()
    {
        $expected = [];
        $connection = $this->getConnection();
        for ($i = 1; $i <= 6; $i++) {
            $row = ['id' => $i, 'consumed' => false, 'value' => 'value' . $i];
            // Insert test lines returns the number of modified rows.
            $connection->insert(self::TABLE_NAME, $row);
            $expected[] = $row;
        }

        $sql = 'SELECT * FROM ' . self::TABLE_NAME;
        $this->assertEquals($expected, $this->fetchAllResults($connection, $sql));
    }

    public function run($process)
    {
        $connection = $this->getConnection();

        $this->operation($connection, $process);
    }

    public function transactional($process)
    {
        $connection = $this->getConnection();

        $connection->transactional(
            function ($transaction) use ($process) {
                $this->operation($transaction, $process);
            }
        );
    }

    private function operation($connection, $process)
    {
        $sql = 'SELECT id FROM ' . self::TABLE_NAME . ' WHERE consumed = false LIMIT 2';
        $ids = array_column($this->fetchAllResults($connection, $sql), 'id');
        echo $process, ' => ', print_r($ids, true);

        $sql = 'UPDATE ' . self::TABLE_NAME . ' SET consumed = true WHERE id IN (' . implode(',', $ids) . ')';
        if ($connection instanceof Transaction) {
            $connection->executeUpdate($sql);
            $connection->commit();
        } else {
            $connection->query($sql);
        }
        
        sleep(3);
    }

    public function checkFinalRun()
    {
        // Checking final result.
        $sql = 'SELECT * FROM ' . self::TABLE_NAME;
        $this->assertEquals(
            [
                ['id' => 1, 'consumed' => true, 'value' => 'value1'],
                ['id' => 2, 'consumed' => true, 'value' => 'value2'],
                ['id' => 3, 'consumed' => false, 'value' => 'value3'],
                ['id' => 4, 'consumed' => false, 'value' => 'value4'],
                ['id' => 5, 'consumed' => false, 'value' => 'value5'],
                ['id' => 6, 'consumed' => false, 'value' => 'value6'],
            ],
            $this->fetchAllResults($this->getConnection(), $sql)
        );
    }

    public function checkFinalTransactional()
    {
        // Checking final result.
        $sql = 'SELECT * FROM ' . self::TABLE_NAME;
        $this->assertEquals(
            [
                ['id' => 1, 'consumed' => true, 'value' => 'value1'],
                ['id' => 2, 'consumed' => true, 'value' => 'value2'],
                ['id' => 3, 'consumed' => true, 'value' => 'value3'],
                ['id' => 4, 'consumed' => true, 'value' => 'value4'],
                ['id' => 5, 'consumed' => true, 'value' => 'value5'],
                ['id' => 6, 'consumed' => true, 'value' => 'value6'],
            ],
            $this->fetchAllResults($this->getConnection(), $sql)
        );
    }

    public function finish()
    {
        $this->getConnection()->query('DELETE FROM ' . self::TABLE_NAME . ' WHERE true');
    }

    /**
     * Returns all the results of the statement as an array
     *
     * @param Connection $connection
     * @param string     $sql
     * @param array      $parameters
     *
     * @return array
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     */
    public function fetchAllResults($connection, string $sql): array
    {
        // Uses two different methods for the sake of testing although all could be done with prepare and execute.
        if ($connection instanceof Transaction) {
            $statement = $connection->execute($sql);
            $rows = $statement->rows();
        } else {
            $statement = $connection->query($sql);
            $rows = $statement->fetchAll();
        }

        $results = [];
        if ($rows) {
            foreach ($rows as $row) {
                $results[] = $row;
            }
        }

        return $results;
    }

    private function assertEquals($expected, $actual)
    {
        if ($expected !== $actual) {
            echo 'F';
            $this->failures[] = print_r($actual, true) . ' doesn\'t match expected ' . print_r($expected, true) . "\n";
        } else {
            echo '.';
        }
    }
}
