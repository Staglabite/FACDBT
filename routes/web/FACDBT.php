<?php

use App\Http\Controllers\HITUAM\SsoController;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF001;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF002;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF003; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF004; 
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF005;
use App\Http\Controllers\Original\FACDBT\FACDBT01\FACDBTF006;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD001;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD002;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD003;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD004;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD005;
use App\Http\Controllers\Original\FACDBT\FACDBT04\FACDBTD006;
use App\Http\Controllers\Original\FACDBT\FACDBT04\DashboardDataVersion;
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

    // Route untuk Taking Book (FACDBT004): hanya lihat & export (data book milik sistem taking / scanner)
    Route::group(['prefix' => 'master-takingbook', 'controller' => FACDBTF004::class], function () {
        Route::get('/', 'index')->name('facdbtf004.index');
        Route::get('/export', 'export')->name('facdbtf004.export');
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

    // Route untuk Master Event (FACDBT006)
    Route::group(['prefix' => 'master-event', 'controller' => FACDBTF006::class], function () {
        Route::get('/', 'index')->name('facdbtf006.index');
        Route::post('/', 'store')->name('facdbtf006.store');

        Route::get('/periods', 'periods')->name('facdbtf006.periods');
        Route::get('/download-template', 'downloadTemplate')->name('facdbtf006.download-template');
        Route::post('/delete-multiple', 'destroyMultiple')->name('facdbtf006.delete-multiple');

        Route::get('/{id}', 'show')->name('facdbtf006.show');
        Route::put('/{event}', 'update')->name('facdbtf006.update');
        Route::delete('/{event}', 'destroy')->name('facdbtf006.destroy');
        Route::post('/{event}/parts', 'importParts')->name('facdbtf006.import-parts');
        Route::get('/{event}/parts/export', 'exportParts')->name('facdbtf006.export-parts');
    });

});

Route::group(['prefix' => 'ds', 'middleware' => 'auth'], function () {

    // Versi data taking: dashboard memuat ulang chart hanya bila ada scan / book baru
    Route::get('/data-version', DashboardDataVersion::class)->name('dashboard.data-version');
    
    Route::group(['prefix' => 'main-dashboard', 'controller' => FACDBTD001::class], function () {
        Route::get('/', 'index')->name('main-dashboard.index'); 
        Route::get('/data', 'getData')->name('main-dashboard.getdata'); 
        Route::get('/time-range', 'getTimeRange')->name('main-dashboard.time-range');
        Route::get('/progress-by-area', 'getProgressByArea')->name('main-dashboard.progress-by-area');
        Route::get('/variance', 'getVariance')->name('main-dashboard.variance');
        Route::get('/variance-trend', 'getVarianceTrend')->name('main-dashboard.variance-trend');
    });

    // Dashboard Progress by Group Location & Detail Location (FACDBTD002)
    Route::group(['prefix' => 'progress-dashboard', 'controller' => FACDBTD002::class], function () {
        Route::get('/', 'index')->name('progress-dashboard.index');
        Route::get('/time-range', 'getTimeRange')->name('progress-dashboard.time-range');
        Route::get('/data', 'getData')->name('progress-dashboard.data');
    });

    // Variance Taking by Qty & Amount (FACDBTD003)
    Route::group(['prefix' => 'taking-qty-amount', 'controller' => FACDBTD003::class], function () {
        Route::get('/', 'index')->name('taking-qty-amount.index');
        Route::get('/filters', 'getFilters')->name('taking-qty-amount.filters');
        Route::get('/data', 'getData')->name('taking-qty-amount.data');
        Route::get('/positive-table', 'positiveTable')->name('taking-qty-amount.positive-table');
        Route::get('/negative-table', 'negativeTable')->name('taking-qty-amount.negative-table');
    });

    // Variance Taking by Part No per Location No (FACDBTD004)
    Route::group(['prefix' => 'variance-part-location', 'controller' => FACDBTD004::class], function () {
        Route::get('/', 'index')->name('variance-part-location.index');
        Route::get('/filters', 'getFilters')->name('variance-part-location.filters');
        Route::get('/data', 'getData')->name('variance-part-location.data');
        Route::get('/positive-table', 'positiveTable')->name('variance-part-location.positive-table');
        Route::get('/negative-table', 'negativeTable')->name('variance-part-location.negative-table');
    });

    // Alamat lama (sebelum ganti nama menu) tetap bisa dibuka
    Route::redirect('/nett-variance-dashboard', '/FACDBT/ds/taking-qty-amount');
    Route::redirect('/absolute-variance-dashboard', '/FACDBT/ds/variance-part-location');

    // History Taking (FACDBTD005)
    Route::group(['prefix' => 'taking-history', 'controller' => FACDBTD005::class], function () {
        Route::get('/', 'index')->name('taking-history.index');
        Route::get('/filters', 'getFilters')->name('taking-history.filters');
        Route::post('/export', 'export')->name('taking-history.export');
    });

    // Taking Data Revision (FACDBTD006)
    Route::group(['prefix' => 'taking-data-revision', 'controller' => FACDBTD006::class], function () {
        Route::get('/', 'index')->name('taking-data-revision.index');
        Route::get('/data', 'getData')->name('taking-data-revision.data');
        Route::get('/time-range', 'getTimeRange')->name('taking-data-revision.time-range');
    });
});