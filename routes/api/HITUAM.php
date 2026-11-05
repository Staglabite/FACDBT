<?php

use App\Http\Controllers\Api\HITUAM\HITUAM02\HITUAMF009;
use App\Http\Controllers\Original\HITUAM\HITUAM01\HITUAMF010;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth', 'controller' => HITUAMF009::class], function () {
    Route::post('/login', 'login');

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::post('/logout', 'logout');
    });
});

Route::group(['prefix' => 'bd'], function () {

    Route::group(['prefix' => 'configuration', 'controller' => HITUAMF010::class], function () {
        Route::get('/variable', 'showByVariable');
    });
});
