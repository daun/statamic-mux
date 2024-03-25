<?php

use Daun\StatamicMux\Controllers\ApiController;
use Daun\StatamicMux\Controllers\AssetActionController;
use Daun\StatamicMux\Controllers\AssetController;
use Daun\StatamicMux\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('mux')->name('mux.')->middleware('statamic.cp.authenticated')->group(function () {
    Route::get('', [DashboardController::class, 'index'])->name('index');

    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets/actions', [AssetActionController::class, 'run'])->name('assets.actions.run');
    Route::post('assets/actions/list', [AssetActionController::class, 'bulkActions'])->name('assets.actions.bulk');
    // Route::get('assets/image/thumbnail/{asset}/{size?}/{time?}', [AssetImageController::class, 'thumbnail'])->name('assets.image.thumbnail');
    // Route::get('assets/image/gif/{asset}/{size?}/{start?}/{end?}/{fps?}', [AssetImageController::class, 'gif'])->name('assets.image.gif');

    Route::get('api/assets', [ApiController::class, 'assets'])->name('api.assets');
});
