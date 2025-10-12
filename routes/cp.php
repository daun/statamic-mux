<?php

use Daun\StatamicMux\Http\Controllers\Cp\AssetsController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->group(function () {
    Route::get('/thumbnail/{id}', [AssetsController::class, 'thumbnail'])->where('id', '.*')->name('thumbnail');
});
