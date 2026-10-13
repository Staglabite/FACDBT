<?php

use App\Http\Controllers\HITUAM\SsoController;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF001;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF002;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF003; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF004; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF005;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'ts', 'middleware' => 'auth'], function () {
    // Transactional routes
});

Route::group(['prefix' => 'bd', 'middleware' => 'auth'], function () {

    // Route untuk Master Location (FACDBT001)
    Route::group(['prefix' => 'master-location', 'controller' => FACDBTF001::class], function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/download-template', 'downloadTemplate');
        Route::post('/import', 'import');
        Route::post('/delete-multiple', 'destroyMultiple');
        
        Route::get('/{location}', 'show');
        Route::put('/{location}', 'update');
        Route::delete('/{location}', 'destroy');
    });

    // Route untuk Master Part (FACDBT002)
    Route::group(['prefix' => 'master-part', 'controller' => FACDBTF002::class], function () {
        Route::get('/', 'index')->name('facdbtf002.index');
        Route::post('/', 'store')->name('facdbtf002.store');
        Route::get('/download-template', 'downloadTemplate')->name('facdbtf002.download-template');
        Route::post('/import', 'import')->name('facdbtf002.import');
        Route::post('/delete-multiple', 'destroyMultiple')->name('facdbtf002.delete-multiple');
        
        Route::get('/{id}', 'show')->name('facdbtf002.show');
        Route::put('/{part}', 'update')->name('facdbtf002.update');
        Route::delete('/{part}', 'destroy')->name('facdbtf002.destroy');
    });

    // Route untuk Master Unit Cost (FACDBT003)
    Route::group(['prefix' => 'master-unitcost', 'controller' => FACDBTF003::class], function () {
        Route::get('/', 'index')->name('facdbtf003.index');
        Route::post('/', 'store')->name('facdbtf003.store');
        
        Route::get('/download-template', 'downloadTemplate')->name('facdbtf003.download-template');
        Route::post('/import', 'import')->name('facdbtf003.import');
        Route::post('/delete-multiple', 'destroyMultiple')->name('facdbtf003.delete-multiple');
        
        Route::get('/{id}', 'show')->name('facdbtf003.show');
        Route::put('/{unitcost}', 'update')->name('facdbtf003.update');
        Route::delete('/{unitcost}', 'destroy')->name('facdbtf003.destroy');
    });

    // Route untuk Taking Book (FACDBT004)
    Route::group(['prefix' => 'master-takingbook', 'controller' => FACDBTF004::class], function () {
        Route::get('/', 'index')->name('facdbtf004.index');
        Route::post('/', 'store')->name('facdbtf004.store');
        
        Route::get('/download-template', 'downloadTemplate')->name('facdbtf004.download-template');
        Route::post('/import', 'import')->name('facdbtf004.import');
        Route::post('/delete-multiple', 'destroyMultiple')->name('facdbtf004.delete-multiple');
        
        Route::get('/{id}', 'show')->name('facdbtf004.show');
        Route::put('/{id}', 'update')->name('facdbtf004.update');
        Route::delete('/{id}', 'destroy')->name('facdbtf004.destroy');
    });

    // Route untuk Master Group Location (FACDBT005)
    Route::group(['prefix' => 'master-grouplocation', 'controller' => FACDBTF005::class], function () {
        Route::get('/', 'index')->name('facdbtf005.index');
        Route::post('/', 'store')->name('facdbtf005.store');

        Route::get('/download-template', 'downloadTemplate')->name('facdbtf005.download-template');
        Route::post('/import', 'import')->name('facdbtf005.import');
        Route::post('/delete-multiple', 'destroyMultiple')->name('facdbtf005.delete-multiple');

        Route::get('/{id}', 'show')->name('facdbtf005.show');
        Route::put('/{grouplocation}', 'update')->name('facdbtf005.update');
        Route::delete('/{grouplocation}', 'destroy')->name('facdbtf005.destroy');
    });

});

Route::group(['prefix' => 'ds', 'middleware' => 'auth'], function () {
    
    Route::group(['prefix' => 'main-dashboard', 'controller' => FACDBTD001::class], function () {
        Route::get('/', 'index')->name('main-dashboard.index'); 
        Route::get('/data', 'getData')->name('main-dashboard.getdata'); 
        Route::get('/time-slots', 'getTimeSlots')->name('main-dashboard.time-slots');
        Route::get('/progress-by-area', 'getProgressByArea')->name('main-dashboard.progress-by-area');
        Route::get('/variance', 'getVariance')->name('main-dashboard.variance');
    });
});