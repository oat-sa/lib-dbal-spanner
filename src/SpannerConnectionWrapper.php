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

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use Google\Cloud\Spanner\Timestamp;

/**
 * Class SpannerConnectionWrapper
 * @package OAT\Library\DBALSpanner
 *
 * @property SpannerConnection $_conn
 */
class SpannerConnectionWrapper extends Connection
{

    /**
     * @param string $prepareString
     * @return SpannerStatement
     */
    public function prepare($prepareString)
    {
        return $this->_conn->prepare($prepareString);
    }

    /**
     * @return SpannerStatement
     */
    public function query()
    {
        $args = func_get_args();

        return $this->_conn->query($args[0]);
    }

    /**
     * @param string $tableExpression
     * @param array $identifier
     * @param array $types
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    public function delete($tableExpression, array $identifier, array $types = [])
    {
        return $this->_conn->delete($tableExpression, $identifier);
    }

    /**
     * @param string $tableExpression
     * @param array $data
     * @param array $identifier
     * @param array $types
     * @return int|mixed
     * @throws InvalidArgumentException
     */
    public function update($tableExpression, array $data, array $identifier, array $types = [])
    {
        return $this->_conn->update($tableExpression, $data, $identifier);
    }

    /**
     * @param string $tableExpression
     * @param array $data
     * @param array $types
     * @return Timestamp|int
     */
    public function insert($tableExpression, array $data, array $types = [])
    {
        return $this->_conn->insert($tableExpression, $data);
    }

    /**
     * @param string $statement
     * @return int|mixed
     */
    public function exec($statement)
    {
        return $this->_conn->exec($statement);
    }

    public function lastInsertId($name = null)
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function beginTransaction()
    {
        //        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function commit()
    {
        //        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function rollBack()
    {
        //        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function errorCode()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }

    public function errorInfo()
    {
        throw new \Exception("\e[31m\e[1m" . __METHOD__ . "\e[21m\e[0m" . ' not implemented.');
    }
}
