<?php

namespace Hey\Lacassa;

use Cassandra;
use Hey\Lacassa\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;
use Hey\Lacassa\Migrations\DatabaseMigrationRepository;
use Hey\Lacassa\Console\Migrations\InstallCassandraCommand;
use Hey\Lacassa\Console\Migrations\MigrateCassandraCommand;
use Illuminate\Database\Connection as LaravelConnection;

class CassandraServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        LaravelConnection::resolverFor('cassandra', function ($connection, $database, $prefix, $config) {
            return new Connection($config);
        });
        // Add database driver.
        /*$this->app->resolving('cassandra', function($db) {
            $db->extend('cassandra', function($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });*/

        // $this->registerRepository();
        // $this->registerMigrator();
        // $this->registerMigrateCommand();
        // $this->registerInstallCommand();

        // $this->commands(
        //     'command.cassandra.migrate',
        //     'command.migrate.install.cassandra'
        // );
    }

    
    /**
     * Register migrate command
     *
     * @return void
     */
    protected function registerMigrateCommand()
    {
        $this->app->singleton('command.cassandra.migrate', function ($app) {
            return new MigrateCassandraCommand($app['migrator.cassandra']);
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerMigrator()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('migrator.cassandra', function ($app) {
            $repository = $app['migration.repository.cassandra'];
            return new Migrator($repository, $app['db'], $app['files']);
        });
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('migration.repository.cassandra', function ($app) {
            $table = $app['config']['database.migrations'];
            return new DatabaseMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Register the "install" migration command.
     *
     * @return void
     */
    protected function registerInstallCommand()
    {
        $this->app->singleton('command.migrate.install.cassandra', function ($app) {
            return new InstallCassandraCommand($app['migration.repository.cassandra']);
        });
    }
}
