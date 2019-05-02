<?php

namespace ShSo\Lacassa;

use Cassandra;
use Illuminate\Database\Connection as BaseConnection;

class Connection extends BaseConnection
{
    /**
     * The Cassandra connection handler.
     *
     * @var \Cassandra\DefaultSession
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        // Create the connection
        $this->db = $config['keyspace'];
        $this->connection = $this->createConnection($config);
        $this->useDefaultPostProcessor();
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     *
     * @return \ShSo\Lacassa\Query\Builder
     */
    public function table($table)
    {
        return $this->getDefaultQueryBuilder()->from($table);
    }

    /**
     * @return \ShSo\Lacassa\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    /**
     * Returns the connection grammer
     *
     * @return \ShSo\Lacassa\Schema\Grammar
     */
    public function getSchemaGrammar()
    {
        return new Schema\Grammar();
    }

    /**
     * return Cassandra object.
     *
     * @return \Cassandra\DefaultSession
     */
    public function getCassandraConnection()
    {
        return $this->connection;
    }

    /**
     * Create a new Cassandra connection.
     *
     * @param array $config
     *
     * @return \Cassandra\DefaultSession
     */
    protected function createConnection(array $config)
    {
        $builder = Cassandra::cluster()
            ->withContactPoints($config['host'] ?? '127.0.0.1')
            ->withPort(intval($config['port'] ?? '7000'));
        if (array_key_exists('page_size', $config) && !empty($config['page_size']))
            $builder->withDefaultPageSize(intval($config['page_size'] ?? '5000'));
        if (array_key_exists('consistency', $config) && in_array(strtoupper($config['consistency']), [
                'ANY', 'ONE', 'TWO', 'THREE', 'QOURUM', 'ALL', 'SERIAL',
                'LOCAL_QUORUM', 'EACH_QOURUM', 'LOCAL_SERIAL', 'LOCAL_ONE',
            ])) {
            $consistency = constant('\Cassandra::CONSISTENCY_'.strtoupper($config['consistency']));
            $builder->withDefaultConsistency($consistency);
        }
        if (array_key_exists('timeout', $config) && !empty($config['timeout']))
            $builder->withDefaultTimeout(intval($config['timeout']));
        if (array_key_exists('connect_timeout', $config) && !empty($config['connect_timeout']))
            $builder->withConnectTimeout(floatval($config['connect_timeout']));
        if (array_key_exists('request_timeout', $config) && !empty($config['request_timeout']))
            $builder->withRequestTimeout(floatval($config['request_timeout']));
        if (array_key_exists('username', $config) && array_key_exists('password', $config))
            $builder->withCredentials($config['username'], $config['password']);
        return $builder->build()->connect($config['keyspace']);
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        unset($this->connection);
    }

    /**
     * @return string
     */
    public function getDriverName()
    {
        return 'Cassandra';
    }

    /**
     * @return \ShSo\Lacassa\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    /**
     * @return \ShSo\Lacassa\Query\Builder
     */
    protected function getDefaultQueryBuilder()
    {
        return new Query\Builder($this, $this->getPostProcessor());
    }

    /**
     * @return \ShSo\Lacassa\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar();
    }

    /**
     * @return \ShSo\Lacassa\Schema\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar();
    }

    /**
     * Execute an CQL statement with bindings and return the result.
     *
     * @param string $query
     * @param array $bindings
     *
     * @return Cassandra\Rows
     */
    public function statement($query, $bindings = [])
    {
        foreach ($bindings as $binding) {
            $value = 'string' == strtolower(gettype($binding)) ? "'" . $binding . "'" : $binding;
            $query = preg_replace('/\?/', $value, $query, 1);
        }
        return $this->getDefaultQueryBuilder()->executeCql($query);
    }

    /**
     * Execute an CQL statement and return the result.
     *
     * @param string $query
     * @param array $bindings
     *
     * @return Cassandra\Rows
     */
    public function raw($query)
    {
        return $this->getDefaultQueryBuilder()->executeCql($query);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param string $method
     * @param array $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection, $method], $parameters);
    }
}

