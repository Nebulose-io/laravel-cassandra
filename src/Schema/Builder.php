<?php

namespace Hey\Lacassa\Schema;

use Closure;
use Hey\Lacassa\Connection;
use Illuminate\Database\Schema\Builder as BaseBuilder;

class Builder extends BaseBuilder
{
    /**
     * @return void
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = $connection->getSchemaGrammar();
    }

    public function createType($type, Closure $callback)
    {
        $blueprint = $this->createBlueprint($type);

        $blueprint->createType();

        $callback($blueprint);

        $this->build($blueprint);
    }

    /**
     * @return \Hey\Lacassa\Schema\Builder
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        return new Blueprint($this->connection, $callback, $table);
    }

    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        $sql = $this->grammar->compileTableExists();

        $database = $this->connection->getDatabaseName();

        return ($this->connection->select($sql, [$database, $table]))->count() > 0;
    }
}
