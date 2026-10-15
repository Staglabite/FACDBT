<?php

use App\Http\Controllers\HITUAM\SsoController;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF001;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF002;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF003; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF004; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF005;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD002;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD003;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD004;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD005;
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
        Route::post('/export', 'export');
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
        Route::post('/export', 'export')->name('facdbtf002.export');
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
        Route::post('/export', 'export')->name('facdbtf003.export');
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
        Route::post('/export', 'export')->name('facdbtf005.export');
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
        Route::get('/variance-trend', 'getVarianceTrend')->name('main-dashboard.variance-trend');
    });

    // Dashboard Progress by Group Location & Detail Location (FACDBTD002)
    Route::group(['prefix' => 'progress-dashboard', 'controller' => FACDBTD002::class], function () {
        Route::get('/', 'index')->name('progress-dashboard.index');
        Route::get('/groups', 'getGroups')->name('progress-dashboard.groups');
        Route::get('/time-slots', 'getTimeSlots')->name('progress-dashboard.time-slots');
        Route::get('/data', 'getData')->name('progress-dashboard.data');
    });

    // Dashboard Variance by Area (FACDBTD003)
    Route::group(['prefix' => 'variance-dashboard', 'controller' => FACDBTD003::class], function () {
        Route::get('/', 'index')->name('variance-dashboard.index');
        Route::get('/time-slots', 'getTimeSlots')->name('variance-dashboard.time-slots');
        Route::get('/data', 'getData')->name('variance-dashboard.data');
    });

    // Nett Variance Dashboard (FACDBTD004)
    Route::group(['prefix' => 'nett-variance-dashboard', 'controller' => FACDBTD004::class], function () {
        Route::get('/', 'index')->name('nett-variance-dashboard.index');
        Route::get('/filters', 'getFilters')->name('nett-variance-dashboard.filters');
        Route::get('/data', 'getData')->name('nett-variance-dashboard.data');
        Route::get('/positive-table', 'positiveTable')->name('nett-variance-dashboard.positive-table');
        Route::get('/negative-table', 'negativeTable')->name('nett-variance-dashboard.negative-table');
    });

    // Absolute Variance Dashboard (FACDBTD005)
    Route::group(['prefix' => 'absolute-variance-dashboard', 'controller' => FACDBTD005::class], function () {
        Route::get('/', 'index')->name('absolute-variance-dashboard.index');
        Route::get('/filters', 'getFilters')->name('absolute-variance-dashboard.filters');
        Route::get('/data', 'getData')->name('absolute-variance-dashboard.data');
        Route::get('/positive-table', 'positiveTable')->name('absolute-variance-dashboard.positive-table');
        Route::get('/negative-table', 'negativeTable')->name('absolute-variance-dashboard.negative-table');
    });
});