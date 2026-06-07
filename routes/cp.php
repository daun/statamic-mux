<?php

use Daun\StatamicMux\Http\Controllers\Cp\ListingController;
use Daun\StatamicMux\Http\Controllers\Cp\ThumbnailController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->group(function () {
    Route::get('/thumbnail/{id}', [ThumbnailController::class, 'thumbnail'])->where('id', '.*')->name('thumbnail');

    Route::get('/', [ListingController::class, 'index'])->name('index');

    Route::prefix('api/videos')->name('api.videos.')->group(function () {
        Route::get('/local', [ListingController::class, 'local'])->name('local');
        Route::get('/remote', [ListingController::class, 'remote'])->name('remote');
        Route::post('/refresh', [ListingController::class, 'refresh'])->name('refresh');
    });
});
