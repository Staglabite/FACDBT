<?php

use App\Exceptions\ResponseException;
use App\Http\Middleware\LocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../credential/sftp.env')) {
    Dotenv::createImmutable(__DIR__ . '/../credential', 'sftp.env')->safeLoad();
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            Route::prefix('HITUAM')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web/HITUAM.php');

            Route::prefix('FACDBT')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web/FACDBT.php');

            Route::prefix('HITUAM/web-api')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web-api/HITUAM.php');

            Route::prefix('FACTWM/web-api')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web-api/HITUAM.php');

            Route::prefix('HITUAM/api')
                ->group(__DIR__ . '/../routes/api/HITUAM.php');
        },

    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(LocaleMiddleware::class);

        $middleware->alias([
            'serve' => \App\Http\Middleware\PermissionMiddleware::class,
            'privacy.check' => \App\Http\Middleware\CheckPrivacyAgreement::class,
            'check.token' => \App\Http\Middleware\CheckToken::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();
