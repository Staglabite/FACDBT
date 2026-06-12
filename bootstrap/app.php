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

            // Route::prefix('FACTWM')
            //     ->middleware(['web'])
            //     ->group(__DIR__ . '/../routes/web/FACTWM.php');

            Route::prefix('HITUAM/web-api')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web-api/HITUAM.php');

            Route::prefix('FACTWM/web-api')
                ->middleware(['web'])
                ->group(__DIR__ . '/../routes/web-api/HITUAM.php');

            Route::prefix('HITUAM/api')
                ->group(__DIR__ . '/../routes/api/HITUAM.php');

            // Route::prefix('FACTWM/api')
            //     ->group(__DIR__ . '/../routes/api/FACTWM.php');
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
    // ->booted(function () {
    //     // Load SFTP environment file
    //     if (file_exists(base_path('credential/sftp.env'))) {
    //         Dotenv::createImmutable(base_path('credential'), 'sftp.env')->safeLoad();
    //     }
    // })
    ->withExceptions(function (Exceptions $exceptions) {
        // $exceptions->render(function (Throwable $th, Request $request) {
        //     $thCode = intval($th->getCode());

        //     if ($request->expectsJson() || $request->ajax()) {
        //         $response = [
        //             'success' => false,
        //             'message' => $th->getMessage(),
        //         ];

        //         $statusCode = match (true) {
        //             $th instanceof \Illuminate\Validation\ValidationException => 422,
        //             $th instanceof Illuminate\Auth\AuthenticationException => 401,
        //             $th instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => 404,
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 405,
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException => 403,
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 429,
        //             default => $thCode < 500 && $thCode >= 100 ? $thCode : 500,
        //         };

        //         $response['message'] = match (true) {
        //             $th instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 'Resource not found',
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException => $th->getMessage() ?? 'Route not found',
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException => 'Method not allowed',
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException => 'Access denied',
        //             $th instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 'Too many requests',
        //             default => $th->getMessage(),
        //         };

        //         if ($th instanceof \Illuminate\Validation\ValidationException) {
        //             $response['errors'] = $th->errors();
        //         }

        //         return response()->json($response, $statusCode);
        //     }

        //     // Let Laravel handle non-JSON requests normally
        //     return null;
        // });

        // $exceptions->renderable(function (ResponseException $e, $request) {
        //     $e->render($request);
        // });
    })->create();
