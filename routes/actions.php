<?php

use Illuminate\Support\Facades\Route;
use SpaceCatNinja\ImagerX\Http\Controllers\CacheController;
use SpaceCatNinja\ImagerX\Http\Controllers\UtilityController;
use SpaceCatNinja\ImagerX\ImagerX;

/*
 * Imager X action routes, reachable at `actions/imager-x/...` and `{cpTrigger}/actions/imager-x/...`.
 *
 * The site action prefix is reachable by guests, so routes that need a login require it themselves.
 */

// Clearing caches with the `clearKey` config setting. Anonymous on purpose: the key authenticates the
// request, so these are rate limited, and exempt from CSRF protection (see ImagerX::boot()).
Route::middleware('throttle:' . ImagerX::CLEAR_CACHE_RATE_LIMITER)->group(function() {
    Route::match(['get', 'post'], 'cache/clear-transforms', [CacheController::class, 'clearTransforms']);
    Route::match(['get', 'post'], 'cache/clear-remote-images', [CacheController::class, 'clearRemoteImages']);
});

// The Imager X utility
Route::middleware('auth')->group(function() {
    Route::post('utility/generate-transforms', [UtilityController::class, 'generateTransforms']);
    Route::post('utility/clear-cache', [UtilityController::class, 'clearCache']);
});
