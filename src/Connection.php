<?php

namespace Hey\Lacassa;

use Hey\Lacassa\Query\Builder as QueryBuilder;
use Hey\Lacassa\Query\Grammar as QueryGrammar;
use Hey\Lacassa\Schema\Builder as SchemaBuilder;
use Hey\Lacassa\Schema\Grammar as SchemaGrammar;
use Hey\Lacassa\Query\Processor as QueryProcessor;
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
        $this->database = $config['keyspace'];
        $this->connection = $this->createConnection($config);

        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
        $this->useDefaultQueryGrammar();
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|string  $table
     * @param  string|null  $as
     * @return \Hey\Lacassa\Query\Builder
     */
    public function table($table, $as = null)
    {
        return $this->getDefaultQueryBuilder()->from($table);
    }

    /**
     * @return \Hey\Lacassa\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        return new SchemaBuilder($this);
    }

    /**
     * Returns the connection grammer.
     *
     * @return \Hey\Lacassa\Schema\Grammar
     */
    public function getSchemaGrammar()
    {
        return new SchemaGrammar();
    }

    /**
     * return Cassandra object.
     *
     * @return \Cassandra\Session
     */
    public function getConnection()
    {
        return $this->connection ?? null;
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Create a new Cassandra connection.
     *
     * @param array $config
     *
     * @return \Cassandra\Session
     */
    protected function createConnection(array $config)
    {
        return \Cassandra::cluster()
            ->withContactPoints($config['host'] ?? '127.0.0.1')
            ->withPort(intval($config['port'] ?? '7000'))->build()->connect('nebulose');
        /*if (array_key_exists('page_size', $config) && !empty($config['page_size'])) {
            $builder->withDefaultPageSize(intval($config['page_size'] ?? '5000'));
        }
        if (array_key_exists('consistency', $config) && in_array(strtoupper($config['consistency']), [
                'ANY', 'ONE', 'TWO', 'THREE', 'QOURUM', 'ALL', 'SERIAL',
                'LOCAL_QUORUM', 'EACH_QOURUM', 'LOCAL_SERIAL', 'LOCAL_ONE',
            ])) {
            $consistency = constant('\Cassandra::CONSISTENCY_'.strtoupper($config['consistency']));
            $builder->withDefaultConsistency($consistency);
        }
        if (array_key_exists('timeout', $config) && !empty($config['timeout'])) {
            $builder->withDefaultTimeout(intval($config['timeout']));
        }
        if (array_key_exists('connect_timeout', $config) && !empty($config['connect_timeout'])) {
            $builder->withConnectTimeout(floatval($config['connect_timeout']));
        }
        if (array_key_exists('request_timeout', $config) && !empty($config['request_timeout'])) {
            $builder->withRequestTimeout(floatval($config['request_timeout']));
        }
        if (array_key_exists('username', $config) && array_key_exists('password', $config)) {
            $builder->withCredentials($config['username'], $config['password']);
        }*/

        //return $builder->build()->connect($config['keyspace']);
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        $this->connection->close();
        unset($this->connection);
    }

    /**
     * @return string
     */
    public function getDriverName()
    {
        return 'cassandra';
    }

    /**
     * @return \Hey\Lacassa\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new QueryProcessor();
    }

    /**
     * @return \Hey\Lacassa\Query\Builder
     */
    protected function getDefaultQueryBuilder()
    {
        return new QueryBuilder($this, $this->getPostProcessor());
    }

    /**
     * @return \Hey\Lacassa\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar();
    }

    /**
     * @return \Hey\Lacassa\Schema\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return new SchemaGrammar();
    }

    public function affectingStatement($query, $bindings = [])
    {
        return $this->statement($query, $bindings);
    }

    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->connection->prepare($query);
            
            return $this->connection->execute($statement, ['arguments' => $bindings]);
        });
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->connection)) {
            $this->connection = $this->createConnection($this->config);
        }
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
