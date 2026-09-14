<?php

use App\Http\Controllers\HITUAM\SsoController;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF001;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF002;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF003;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF004;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF005;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF006;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF007;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF008;
use App\Http\Controllers\Original\HITUAM\HITUAM02\HITUAMF009;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF010;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF011;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF012;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth', 'controller' => HITUAMF009::class], function () {
    Route::get('/login', 'index')->name('login');
    Route::get('/forgot-password', 'forgot')->name('forgot.password');
    Route::get('/reset-password/{token}', 'reset')->name('reset.password');
    Route::post('/login', 'login')->name('attempt.login');
    Route::post('/forgot-password', 'aforgot')->name('attemt.forgot');
    Route::post('/reset-password', 'postReset')->name('reset.post');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');

    Route::get('/check-privacy-agreement', 'checkPrivacyAgreement')->name('auth.check.privacy');
    Route::post('/save-privacy-agreement', 'savePrivacyAgreement')->name('auth.save.privacy');
});

Route::group(['prefix' => 'ts', 'middleware' => 'auth'], function () {

});

// Route::group(['prefix' => 'auth/sso', 'controller' => SsoController::class], function () {
//     Route::get('/send', 'send')->middleware('auth')->name('auth.sso.send');
//     Route::match(['get', 'post'], '/receive', 'receive')->name('auth.sso.receive');
// });

Route::group(['prefix' => 'bd', 'middleware' => 'auth'], function () {
    Route::group(['prefix' => 'master-application', 'controller' => HITUAMF001::class], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::post('/delete-multiple', 'destroyMultiple');
        Route::get('/{application}', 'show')->whereNumber('application');
        Route::put('/{application}', 'update')->whereNumber('application');
        Route::delete('/{application}', 'destroy')->whereNumber('application');
    });

    Route::group(['prefix' => 'master-menu', 'controller' => HITUAMF002::class], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::post('/delete-multiple', 'destroyMultiple');
        Route::get('/{menu}', 'show')->whereNumber('menu');
        Route::put('/{menu}', 'update')->whereNumber('menu');
        Route::delete('/{menu}', 'destroy')->whereNumber('menu');
    });

    Route::group(['prefix' => 'master-service', 'controller' => HITUAMF003::class], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::get('/{service}', 'show')->whereNumber('service');
        Route::put('/{service}', 'update')->whereNumber('service');
        Route::delete('/{service}', 'destroy')->whereNumber('service');
        Route::post('/delete-multiple', 'destroyMultiple');
    });

    Route::group(['prefix' => 'master-role', 'controller' => HITUAMF004::class], function () {
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::get('/{role}', 'show')->whereNumber('role');
        Route::put('/{role}', 'update')->whereNumber('role');
        Route::delete('/{role}', 'destroy')->whereNumber('role');
        Route::post('/delete-multiple', 'destroyMultiple');
    });

    Route::group(['prefix' => 'master-user', 'controller' => HITUAMF005::class], function () {
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::get('/{user}', 'show')->whereNumber('user');
        Route::put('/{user}', 'update')->whereNumber('user');
        Route::put('/password/{user}', 'changePassword')->whereNumber('user');
        Route::delete('/{user}', 'destroy')->whereNumber('user');
        Route::get('/profile/{user}', 'profile')->name('profile.show')->whereNumber('user');
        Route::post('/delete-multiple', 'destroyMultiple');
    });

    Route::group(['prefix' => 'master-userrole', 'controller' => HITUAMF006::class], function () {
        Route::get('/', 'index');
        Route::get('/users', 'users')->name('hituam.master-userrole.users');
        Route::get('/roles', 'roles')->name('hituam.master-userrole.roles');
    });

    Route::group(['prefix' => 'master-role-access', 'controller' => HITUAMF007::class], function () {
        Route::get('/', 'index');
    });

    Route::group(['prefix' => 'master-role-service', 'controller' => HITUAMF008::class], function () {
        Route::get('/', 'index');
    });

    Route::group(['prefix' => 'configuration', 'controller' => HITUAMF010::class], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{configuration}', 'show');
        Route::put('/{configuration}', 'update');
        Route::delete('/{configuration}', 'destroy');
    });

    Route::group(['prefix' => 'master-news', 'controller' => HITUAMF011::class], function () {
        Route::get('/', 'index')->name('hituam.master-news.index');
        Route::get('/form/{news}', 'storeForm');
        Route::post('/bulk-delete', 'bulkDelete');
        Route::post('/', 'store');
        Route::post('/{news}', 'update');
        Route::get('/id/{news}', 'showNewsById');
        Route::get('/slug/{news}', 'show');
        Route::delete('/{news}', 'destroy');
    });

        Route::group(['prefix' => 'news', 'controller' => HITUAMF012::class], function () {
        Route::get('/', 'index')->name('hituam.news.index');
        Route::get('/filter', 'filterNews')->name('hituam.news.filter');
        Route::get('/{idViewers}/{slug}', 'showNewsByViewer')->name('hituam.news.show');
    });
});
