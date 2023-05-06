<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\utilities;

use Craft;
use craft\base\Utility;

use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use spacecatninja\imagerx\assetbundles\ImagerUtilityAssets;
use spacecatninja\imagerx\helpers\FileHelper;
use spacecatninja\imagerx\helpers\FormatHelper;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\models\Settings;
use spacecatninja\imagerx\services\ImagerService;
use yii\base\InvalidConfigException;

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
        return Craft::t('imager-x', 'Imager X');
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
    public static function iconPath(): ?string
    {
        return Craft::getAlias('@spacecatninja/imagerx/icon-generate-utility.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        $config = ImagerService::getConfig();
        
        // Get cache paths
        $caches = [];
        $transformsCachePath = FileHelper::normalizePath($config->imagerSystemPath);
        $runtimeCachePath = FileHelper::normalizePath(Craft::$app->getPath()->getRuntimePath() . '/imager/');
        
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
                'label' => Template::raw(Html::encode($volume->name) . sprintf(' <span class=\'light\'>(%s images)</span>', $assetsCount)),
                'value' => $volume->id,
            ];
        }

        $view = Craft::$app->getView();
        $volumesCheckboxSelectHtml = $view->renderTemplate('_includes/forms/checkboxSelect', [
            'name' => 'volumes',
            'options' => $volumeOptions,
            'showAllOption' => false,
        ]);

        // Get checkboxes for named transforms 
        $namedTransforms = ImagerService::$namedTransforms;
        $namedTransformsOptions = [];
        $hasTransformWithoutDisplayName = false;

        foreach ($namedTransforms as $namedTransformKey => $namedTransformValue) {
            if (!isset($namedTransformValue['displayName'])) {
                $hasTransformWithoutDisplayName = true;
            }
            
            $namedTransformsOptions[] = [
                'label' => Html::encode($namedTransformValue['displayName'] ?? $namedTransformKey),
                'value' => $namedTransformKey,
            ];
        }

        $view = Craft::$app->getView();
        $transformsCheckboxSelectHtml = $view->renderTemplate('_includes/forms/checkboxSelect', [
            'name' => 'namedTransforms',
            'options' => $namedTransformsOptions,
            'showAllOption' => false,
        ]);

        // Compile debug info
        $debugInfo = self::debugInfo();
        
        // Register asset bundle
        try {
            Craft::$app->getView()->registerAssetBundle(ImagerUtilityAssets::class);
        } catch (InvalidConfigException) {
            return Craft::t('imager-x', 'Could not load asset bundle');
        }
        
        // Render template
        return Craft::$app->getView()->renderTemplate(
            'imager-x/utility/_utility',
            [
                'volumesCheckboxSelectHtml' => $volumesCheckboxSelectHtml,
                'transformsCheckboxSelectHtml' => $transformsCheckboxSelectHtml,
                'showDisplayNameInfo' => $hasTransformWithoutDisplayName,
                'queueUrl' => UrlHelper::cpUrl('utilities/queue-manager'),
                'caches' => $caches,
                'debugInfo' => $debugInfo,
                'isPro' => ImagerX::getInstance()?->is(ImagerX::EDITION_PRO),
            ]
        );
    }
    
    private static function debugInfo(): array 
    {
        $config = ImagerService::getConfig();
        $r = [];
        
        $r[] = [
            'label' => 'Imager version & edition',
            'value' => ImagerX::getInstance()->version . ' ' . mb_strtoupper(ImagerX::getInstance()->edition),
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
            'value' => App::phpVersion(),
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
