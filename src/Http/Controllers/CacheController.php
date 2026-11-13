<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Settings;

/**
 * Clears the Imager X caches from a URL, using the `clearKey` config setting.
 */
readonly class CacheController
{
    public function __construct(
        private Settings $settings,
        private ImagerService $imager,
    ) {
    }

    /**
     * Clears the transforms cache.
     */
    public function clearTransforms(Request $request): JsonResponse
    {
        $this->authorizeKey($request);
        $this->imager->deleteImageTransformCaches();

        return response()->json(['success' => true]);
    }

    /**
     * Clears the runtime cache, where remote images are cached.
     */
    public function clearRemoteImages(Request $request): JsonResponse
    {
        $this->authorizeKey($request);
        $this->imager->deleteRemoteImageCaches();

        return response()->json(['success' => true]);
    }

    private function authorizeKey(Request $request): void
    {
        $key = (string)$request->input('key', '');
        $setKey = $this->settings->clearKey;

        abort_if($setKey === '', 403, 'Cache clearing is disabled: no clearKey is configured.');
        abort_unless(hash_equals($setKey, $key), 403, 'Unauthorized key.');
    }
}
