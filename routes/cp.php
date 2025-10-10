<?php

use Daun\StatamicMux\Http\Controllers\Cp\AssetsController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->group(function () {
    Route::get('/thumbnail/{container}/{path}', [AssetsController::class, 'thumbnail'])->name('thumbnail');
});
