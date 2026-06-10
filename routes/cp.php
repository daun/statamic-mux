<?php

use Daun\StatamicMux\Http\Controllers\Cp\ActionsController;
use Daun\StatamicMux\Http\Controllers\Cp\CommandController;
use Daun\StatamicMux\Http\Controllers\Cp\ListingController;
use Daun\StatamicMux\Http\Controllers\Cp\ThumbnailController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->group(function () {
    Route::get('/', [ListingController::class, 'index'])->name('index');
    Route::get('/mirrored', [ListingController::class, 'mirrored'])->name('mirrored');
    Route::get('/library', [ListingController::class, 'library'])->name('library');

    Route::prefix('listing')->name('listing.')->group(function () {
        Route::get('/local', [ListingController::class, 'local'])->name('local');
        Route::get('/remote', [ListingController::class, 'remote'])->name('remote');
        Route::post('/refresh', [ListingController::class, 'refresh'])->name('refresh');
    });

    Route::get('/thumbnail/{id}', [ThumbnailController::class, 'thumbnail'])->where('id', '.*')->name('thumbnail');
    Route::post('/command', [CommandController::class, 'run'])->name('command');

    Route::prefix('actions')->name('actions.')->group(function () {
        Route::post('/', [ActionsController::class, 'run'])->name('run');
        Route::post('/list', [ActionsController::class, 'bulkActions'])->name('bulk');
        Route::post('/remote', [ActionsController::class, 'run'])->name('remote.run');
        Route::post('/remote/list', [ActionsController::class, 'bulkActions'])->name('remote.bulk');
    });
});
