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

use SpaceCatNinja\ImagerX\Services\NamedTransforms;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Edition;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Images;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\Utility\Utility;
use CraftCms\Cms\View\TemplateMode;
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
use function CraftCms\Cms\template;

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
        $volumes = Volumes::getAllVolumes();
        $volumeOptions = [];

        foreach ($volumes as $volume) {
            $assetsCount = Asset::find()
                ->volumeId($volume->id)
                ->kind('image')
                ->includeSubfolders(true)
                ->limit(null)
                ->count();

            $volumeOptions[] = [
                'label' => $volume->name,
                'count' => $assetsCount,
                'value' => $volume->id,
            ];
        }

        // Get checkboxes for named transforms
        $namedTransforms = app(NamedTransforms::class)->all();
        $namedTransformsOptions = [];
        $hasTransformWithoutDisplayName = false;

        foreach ($namedTransforms as $namedTransformKey => $namedTransformValue) {
            if (!isset($namedTransformValue['displayName'])) {
                $hasTransformWithoutDisplayName = true;
            }

            $namedTransformsOptions[] = [
                'label' => $namedTransformValue['displayName'] ?? $namedTransformKey,
                'value' => $namedTransformKey,
            ];
        }

        // Compile debug info
        $debugInfo = self::debugInfo();

        // Load the utility's styles and script. Craft adds them to the page when the utility is shown.
        $plugin = app(ImagerX::class);
        $version = '?v=' . $plugin->version;
        HtmlStack::cssFile($plugin->asset('utility/imagerutility.css') . $version);
        HtmlStack::jsFile($plugin->asset('utility/imagerutility.js') . $version);

        return template('imager-x/utility/_utility', [
            'volumeOptions' => $volumeOptions,
            'namedTransformsOptions' => $namedTransformsOptions,
            'showDisplayNameInfo' => $hasTransformWithoutDisplayName,
            'queueUrl' => Url::cpUrl('utilities/queue-manager'),
            'caches' => $caches,
            'debugInfo' => $debugInfo,
            'isPro' => $plugin->isPro(),
        ], TemplateMode::Cp);
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
            'value' => Cms::version() . ' ' . mb_strtoupper(Edition::get()->name),
        ];
        $r[] = [
            'label' => 'PHP version',
            'value' => PHP::version(),
        ];

        $r[] = [
            'label' => 'Image driver & version',
            'value' => (Images::getIsGd() ? 'GD' : 'Imagick') . ' ' . Images::getVersion(),
        ];

        $r[] = [
            'label' => 'Image driver supported formats',
            'value' => implode(', ', Images::getSupportedImageFormats()),
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
