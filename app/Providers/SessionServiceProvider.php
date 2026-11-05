<?php

namespace App\Providers;

use App\Sessions\HituamDatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Session::extend('hituam_database', function ($app) {
            $connection = $app['db']->connection($app['config']['session.connection']);
            $table = $app['config']['session.table'];
            $lifetime = $app['config']['session.lifetime'];
            $application = $app['config']['app.code'];

            return new HituamDatabaseSessionHandler(
                $connection,
                $table,
                $lifetime,
                $application
            );
        });
    }
}
