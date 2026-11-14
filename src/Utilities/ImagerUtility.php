<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Utilities;

use Craft;

use CraftCms\Cms\Asset\Elements\Asset;
use craft\helpers\Template;
use CraftCms\Cms\Utility\Utility;
use SpaceCatNinja\ImagerX\AssetBundles\ImagerUtilityAssets;
use SpaceCatNinja\ImagerX\Support\FileHelper;
use SpaceCatNinja\ImagerX\Support\FormatHelper;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Settings;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\PHP;
use CraftCms\Cms\Support\Url;

use function CraftCms\Cms\t;

/**
 * @author    SPACECATNINJA
 * @package   Imager X
 * @since     4.0.0
 */
class ImagerUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return t('Imager X', category: 'imager-x');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'imager-x-utility';
    }


    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return dirname(__DIR__, 2) . '/resources/icon-generate-utility.svg';
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $config = ImagerService::getConfig();

        // Get cache paths
        $caches = [];
        $transformsCachePath = File::normalizePath($config->imagerSystemPath);
        $runtimeCachePath = File::normalizePath(Path::runtime() . '/imager/');

        try {
            $transformsCacheCount = count(FileHelper::filesInPath($transformsCachePath));
            $transformsCacheSize = FormatHelper::formatBytes(FileHelper::pathSize($transformsCachePath), 'm', 1) . ' MB';
        } catch (\Throwable) {
            $transformsCacheCount = '-';
            $transformsCacheSize = '-';
        }

        $caches[] = [
            'handle' => 'transforms',
            'name' => 'Transforms Cache',
            'path' => $transformsCachePath,
            'fileCount' => $transformsCacheCount,
            'size' => $transformsCacheSize,
        ];

        try {
            $runtimeCacheCount = count(FileHelper::filesInPath($runtimeCachePath));
            $runtimeCacheSize = FormatHelper::formatBytes(FileHelper::pathSize($runtimeCachePath), 'm', 1) . ' MB';
        } catch (\Throwable) {
            $runtimeCacheCount = '-';
            $runtimeCacheSize = '-';
        }

        $caches[] = [
            'handle' => 'runtime',
            'name' => 'Runtime Cache',
            'path' => $runtimeCachePath,
            'fileCount' => $runtimeCacheCount,
            'size' => $runtimeCacheSize,
        ];

        // Get checkboxes for volumes
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $volumeOptions = [];

        foreach ($volumes as $volume) {
            $assetsCount = Asset::find()
                ->volumeId($volume->id)
                ->kind('image')
                ->includeSubfolders(true)
                ->limit(null)
                ->count();

            $volumeOptions[] = [
                'label' => Template::raw(e($volume->name) . sprintf(' <span class="imager-utility__muted">(%s images)</span>', $assetsCount)),
                'value' => $volume->id,
            ];
        }

        // Get checkboxes for named transforms
        $namedTransforms = ImagerService::$namedTransforms;
        $namedTransformsOptions = [];
        $hasTransformWithoutDisplayName = false;

        foreach ($namedTransforms as $namedTransformKey => $namedTransformValue) {
            if (!isset($namedTransformValue['displayName'])) {
                $hasTransformWithoutDisplayName = true;
            }

            $namedTransformsOptions[] = [
                'label' => e($namedTransformValue['displayName'] ?? $namedTransformKey),
                'value' => $namedTransformKey,
            ];
        }

        // Compile debug info
        $debugInfo = self::debugInfo();

        // Register asset bundle
        try {
            Craft::$app->getView()->registerAssetBundle(ImagerUtilityAssets::class);
        } catch (\Throwable) {
            return t('Could not load asset bundle', category: 'imager-x');
        }

        // Render template
        return Craft::$app->getView()->renderTemplate(
            'imager-x/utility/_utility',
            [
                'volumeOptions' => $volumeOptions,
                'namedTransformsOptions' => $namedTransformsOptions,
                'showDisplayNameInfo' => $hasTransformWithoutDisplayName,
                'queueUrl' => Url::cpUrl('utilities/queue-manager'),
                'caches' => $caches,
                'debugInfo' => $debugInfo,
                'isPro' => app(ImagerX::class)->isPro(),
            ]
        );
    }

    private static function debugInfo(): array
    {
        $config = ImagerService::getConfig();
        $r = [];

        $r[] = [
            'label' => 'Imager version & edition',
            'value' => app(ImagerX::class)->version . ' ' . mb_strtoupper(app(ImagerX::class)->edition),
        ];
        $r[] = [
            'label' => 'Imager transformer',
            'value' => $config->transformer,
        ];
        $r[] = [
            'label' => 'Craft version & edition',
            'value' => Craft::$app->getVersion() . ' ' . mb_strtoupper(Craft::$app->getEditionName()),
        ];
        $r[] = [
            'label' => 'PHP version',
            'value' => PHP::version(),
        ];

        $imagesService = Craft::$app->getImages();

        $r[] = [
            'label' => 'Image driver & version',
            'value' => ($imagesService->getIsGd() ? 'GD' : 'Imagick') . ' ' . $imagesService->getVersion(),
        ];

        $r[] = [
            'label' => 'Image driver supported formats',
            'value' => implode(', ', $imagesService->getSupportedImageFormats()),
        ];

        $customEncoders = implode(', ', array_keys($config->customEncoders));

        $r[] = [
            'label' => 'Custom encoders',
            'value' => !empty($customEncoders) ? $customEncoders : 'No <a href="https://imager-x.spacecat.ninja/usage/webp-avif-jpegxl.html">custom encoders</a> configured.',
        ];

        $externalStorages = implode(', ', $config->storages);

        $r[] = [
            'label' => 'Enabled external storages',
            'value' => !empty($externalStorages) ? $externalStorages : 'No <a href="https://imager-x.spacecat.ninja/configuration.html#storages-array">external storage</a> configured.',
        ];

        $optimizers = implode(', ', $config->optimizers);

        $r[] = [
            'label' => 'Enabled optimizers',
            'value' => !empty($optimizers) ? $optimizers : 'No <a href="https://imager-x.spacecat.ninja/usage/optimizers.html">optimizers</a> configured.',
        ];

        return $r;
    }


}
