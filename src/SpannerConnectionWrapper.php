<?php

declare(strict_types=1);

namespace OAT\Library\DBALSpanner;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\ParameterType;
use Google\Cloud\Spanner\Database;
use Google\Cloud\Spanner\Transaction;

class SpannerConnectionWrapper extends Connection
{
    /** @var Database */
    protected $database;

    /** @var array */
    protected $cachedStatements = [];

    public function prepare($prepareString)
    {
        return $this->_conn->prepare($prepareString);
    }

    public function query()
    {
        $args = func_get_args();

        return $this->_conn->query($args[0]);
    }

    public function delete($tableExpression, array $identifier, array $types = [])
    {
        return $this->database->delete($tableExpression, $identifier);
    }

    public function update($tableExpression, array $data, array $identifier, array $types = [])
    {
        return $this->database->update($tableExpression, $data);
    }

    public function insert($tableExpression, array $data, array $types = [])
    {
        return $this->database->insert($tableExpression, $data);
    }

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
