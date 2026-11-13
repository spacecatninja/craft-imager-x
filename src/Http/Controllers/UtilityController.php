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

use Craft;
use craft\web\Controller;
use SpaceCatNinja\ImagerX\Support\FileHelper;
use SpaceCatNinja\ImagerX\Support\FormatHelper;
use SpaceCatNinja\ImagerX\ImagerX as Plugin;

use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Utilities\ImagerUtility;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class CacheController
 *
 * @package SpaceCatNinja\ImagerX\Http\Controllers
 */
class UtilityController extends Controller
{
    // Protected Properties
    // =========================================================================

    protected int|bool|array $allowAnonymous = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // The utility actions change state, and are only for users that have access to the utility
        $this->requirePostRequest();
        $this->requirePermission('utility:'.ImagerUtility::id());

        return true;
    }

    /**
     * Controller action to generate transforms from utility
     */
    public function actionGenerateTransforms(): Response
    {
        $request = Craft::$app->getRequest();
        $volumes = $request->getParam('volumes');
        $useConfiguredTransforms = $request->getParam('useConfiguredTransforms') === '1';
        $namedTransforms = $request->getParam('namedTransforms');

        // Only named transforms that are configured for the site can be generated from the utility
        $namedTransforms = \is_array($namedTransforms) ? array_values(array_filter($namedTransforms, static fn($handle) => \is_string($handle) && isset(ImagerService::$namedTransforms[$handle]))) : [];
        
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
                    Craft::t('imager-x', 'An error occurred when trying to generate transform jobs. Please check the logs for details.'),
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
