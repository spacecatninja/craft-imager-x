<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */


namespace spacecatninja\imagerx\controllers;

use Craft;
use craft\web\Controller;
use spacecatninja\imagerx\helpers\FileHelper;
use spacecatninja\imagerx\helpers\FormatHelper;
use spacecatninja\imagerx\ImagerX as Plugin;

use spacecatninja\imagerx\services\ImagerService;
use yii\web\Response;

/**
 * Class CacheController
 *
 * @package spacecatninja\imagerx\controllers
 */
class UtilityController extends Controller
{
    // Protected Properties
    // =========================================================================

    protected int|bool|array $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * Controller action to generate transforms from utility
     */
    public function actionGenerateTransforms(): Response
    {
        $request = Craft::$app->getRequest();
        $volumes = $request->getParam('volumes');
        $useConfiguredTransforms = $request->getParam('useConfiguredTransforms') === '1';
        $namedTransforms = $request->getParam('namedTransforms');
        
        $hasErrors = false;
        $errors = [];
        if (empty($volumes) || !is_array($volumes)) {
            $hasErrors = true;
            $errors[] = Craft::t('imager-x', 'No volumes selected.');
        }
        
        if (!$useConfiguredTransforms && empty($namedTransforms)) {
            $hasErrors = true;
            $errors[] = Craft::t('imager-x', 'No transforms selected.');
        }
        
        if ($hasErrors) {
            return $this->asJson([
                'success' => false,
                'errors' => $errors,
            ]);
        }
        
        try {
            Plugin::$plugin->generate->generateByUtility($volumes, $useConfiguredTransforms, $useConfiguredTransforms ? [] : $namedTransforms);
        } catch (\Throwable $throwable) {
            Craft::error('An error occured when trying to generate transform jobs from utility: ' . $throwable->getMessage(), __METHOD__);
            
            return $this->asJson([
                'success' => false,
                'errors' => [
                    $throwable->getMessage(),
                ],
            ]);
        }

        return $this->asJson([
            'success' => true,
        ]);
    }
    
    /**
     * Controller action to clear caches from utility.
     */
    public function actionClearCache(): Response
    {
        $request = Craft::$app->getRequest();
        $cacheClearType = $request->getParam('cacheClearType', '');
        
        if (!in_array($cacheClearType, ['all', 'transforms', 'runtime'])) {
            return $this->asJson([
                'success' => false,
                'errors' => ['Unknown cache clear type.'],
            ]);
        }
        
        if ($cacheClearType === 'all' || $cacheClearType === 'transforms') {
            Plugin::$plugin->imagerx->deleteImageTransformCaches();
        }
        
        if ($cacheClearType === 'all' || $cacheClearType === 'runtime') {
            Plugin::$plugin->imagerx->deleteRemoteImageCaches();
        }
        
        $cacheInfo = [];
        $transformsCachePath = FileHelper::normalizePath(ImagerService::getConfig()->imagerSystemPath);
        $runtimeCachePath = FileHelper::normalizePath(Craft::$app->getPath()->getRuntimePath() . '/imager/');

        try {
            $transformsCacheCount = count(FileHelper::filesInPath($transformsCachePath));
            $transformsCacheSize = FormatHelper::formatBytes(FileHelper::pathSize($transformsCachePath), 'm', 1) . ' MB';
        } catch (\Throwable) {
            $transformsCacheCount = '-';
            $transformsCacheSize = '-';
        }
        
        $cacheInfo[] = [
            'handle' => 'transforms',
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
        
        $cacheInfo[] = [
            'handle' => 'runtime',
            'fileCount' => $runtimeCacheCount,
            'size' => $runtimeCacheSize,
        ];

        return $this->asJson([
            'success' => true,
            'cacheInfo' => $cacheInfo
        ]);
    }
}
