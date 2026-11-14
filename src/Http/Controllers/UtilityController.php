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

use SpaceCatNinja\ImagerX\Services\NamedTransforms;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Utility\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SpaceCatNinja\ImagerX\Services\GenerateService;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Support\FileHelper;
use SpaceCatNinja\ImagerX\Support\FormatHelper;
use SpaceCatNinja\ImagerX\Utilities\ImagerUtility;

use function CraftCms\Cms\t;
use CraftCms\Cms\Support\File;

/**
 * Actions for the Imager X utility. They change state, and are only for users that have access to the utility.
 */
readonly class UtilityController
{
    public function __construct(Utilities $utilities)
    {
        abort_unless($utilities->checkAuthorization(ImagerUtility::class), 403, 'User is not authorized to perform this action.');
    }

    /**
     * Generates transforms from the utility.
     */
    public function generateTransforms(Request $request, GenerateService $generate): JsonResponse
    {
        $volumes = $request->input('volumes');
        $useConfiguredTransforms = $request->input('useConfiguredTransforms') === '1';
        $namedTransforms = $request->input('namedTransforms');

        // Only named transforms that are configured for the site can be generated from the utility
        $namedTransforms = \is_array($namedTransforms) ? array_values(array_filter($namedTransforms, static fn($handle) => \is_string($handle) && app(NamedTransforms::class)->has($handle))) : [];

        $errors = [];

        if (empty($volumes) || !is_array($volumes)) {
            $errors[] = t('No volumes selected.', category: 'imager-x');
        }

        if (!$useConfiguredTransforms && empty($namedTransforms)) {
            $errors[] = t('No transforms selected.', category: 'imager-x');
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
            ]);
        }

        try {
            $generate->generateByUtility($volumes, $useConfiguredTransforms, $useConfiguredTransforms ? [] : $namedTransforms);
        } catch (\Throwable $throwable) {
            Log::error('An error occured when trying to generate transform jobs from utility: ' . $throwable->getMessage());

            return response()->json([
                'success' => false,
                'errors' => [
                    t('An error occurred when trying to generate transform jobs. Please check the logs for details.', category: 'imager-x'),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Clears caches from the utility.
     */
    public function clearCache(Request $request, ImagerService $imager): JsonResponse
    {
        $cacheClearType = $request->input('cacheClearType', '');

        if (!in_array($cacheClearType, ['all', 'transforms', 'runtime'], true)) {
            return response()->json([
                'success' => false,
                'errors' => ['Unknown cache clear type.'],
            ]);
        }

        if ($cacheClearType === 'all' || $cacheClearType === 'transforms') {
            $imager->deleteImageTransformCaches();
        }

        if ($cacheClearType === 'all' || $cacheClearType === 'runtime') {
            $imager->deleteRemoteImageCaches();
        }

        $transformsCachePath = File::normalizePath(ImagerService::getConfig()->imagerSystemPath);
        $runtimeCachePath = File::normalizePath(Path::runtime() . '/imager/');

        return response()->json([
            'success' => true,
            'cacheInfo' => [
                ['handle' => 'transforms', ...$this->cacheInfo($transformsCachePath)],
                ['handle' => 'runtime', ...$this->cacheInfo($runtimeCachePath)],
            ],
        ]);
    }

    /**
     * @return array{fileCount: int|string, size: string}
     */
    private function cacheInfo(string $path): array
    {
        try {
            return [
                'fileCount' => count(FileHelper::filesInPath($path)),
                'size' => FormatHelper::formatBytes(FileHelper::pathSize($path), 'm', 1) . ' MB',
            ];
        } catch (\Throwable) {
            return [
                'fileCount' => '-',
                'size' => '-',
            ];
        }
    }
}
