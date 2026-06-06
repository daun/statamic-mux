<?php

use Daun\StatamicMux\Http\Controllers\Cp\Api\MuxVideosController as MuxVideosApiController;
use Daun\StatamicMux\Http\Controllers\Cp\AssetsController;
use Daun\StatamicMux\Http\Controllers\Cp\MuxVideosController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->group(function () {
    Route::get('/thumbnail/{id}', [AssetsController::class, 'thumbnail'])->where('id', '.*')->name('thumbnail');

    Route::get('/', [MuxVideosController::class, 'index'])->name('index');

    Route::prefix('api/videos')->name('api.videos.')->group(function () {
        Route::get('/local', [MuxVideosApiController::class, 'local'])->name('local');
        Route::get('/remote', [MuxVideosApiController::class, 'remote'])->name('remote');
        Route::post('/refresh', [MuxVideosApiController::class, 'refresh'])->name('refresh');
    });
});
