<?php

use App\Http\Controllers\authentications\LoginBasic;
use App\Http\Controllers\authentications\RegisterBasic;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\language\LanguageController;
use App\Http\Controllers\pages\HomePage;
use App\Http\Controllers\pages\MiscError;
use App\Http\Controllers\pages\Page2;
use Illuminate\Support\Facades\Route;

// Main Page Route
Route::get('/', [HomePage::class, 'index'])->name('pages-home');
Route::get('/no-role', [HomePage::class, 'noRole'])->middleware(['auth'])->name('no-role');
Route::get('/page-2', [Page2::class, 'index'])->name('pages-page-2');

// locale
Route::get('/lang/{locale}', [LanguageController::class, 'swap']);
Route::get('/pages/misc-error', [MiscError::class, 'index'])->name('pages-misc-error');

// authentication
Route::get('/auth/login-basic', [LoginBasic::class, 'index'])->name('auth-login-basic');
Route::get('/auth/register-basic', [RegisterBasic::class, 'index'])->name('auth-register-basic');

Route::group(['prefix' => 'general', 'middleware' => 'auth', 'controller' => GeneralController::class], function () {
    Route::get('/all-roles', 'allRoles');
    Route::get('/all-menus', 'allMenus');
    Route::get('/all-suppliers', 'allSuppliers');
    Route::get('/supplier-users', 'supplierUsers');
    Route::get('/applications', 'applications');
    Route::get('/menus', 'menus');
    Route::get('/pph-list', 'pphList');
    Route::post('/store-cache', 'storeCache');
});
