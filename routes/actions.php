<?php

use Illuminate\Support\Facades\Route;
use SpaceCatNinja\ImagerX\Http\Controllers\CacheController;
use SpaceCatNinja\ImagerX\Http\Controllers\UtilityController;

/*
 * Imager X action routes, reachable at `actions/imager-x/...` and `{cpTrigger}/actions/imager-x/...`.
 *
 * The site action prefix is also reachable by guests, so every route requires a login itself.
 */

Route::middleware('auth')->group(function() {
    // Clearing caches with the `clearKey` config setting
    Route::match(['get', 'post'], 'cache/clear-transforms', [CacheController::class, 'clearTransforms']);
    Route::match(['get', 'post'], 'cache/clear-remote-images', [CacheController::class, 'clearRemoteImages']);

    // The Imager X utility
    Route::post('utility/generate-transforms', [UtilityController::class, 'generateTransforms']);
    Route::post('utility/clear-cache', [UtilityController::class, 'clearCache']);
});
