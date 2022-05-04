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

use spacecatninja\imagerx\ImagerX as Plugin;

/**
 * Class CacheController
 *
 * @package spacecatninja\imager\controllers
 */
class CacheController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var int|bool|array
     */
    protected int|bool|array $allowAnonymous = ['actionClearTransforms', 'actionClearRemoteImages'];

    // Public Methods
    // =========================================================================

    /**
     * Controller action to clear image transforms
     *
     * @throws \Throwable
     */
    public function actionClearTransforms(): bool
    {
        $config = Plugin::$plugin->getSettings();
        $request = Craft::$app->getRequest();

        $key = $request->getParam('key', '');
        $setKey = $config->clearKey ?? '';

        if ($setKey === '' || $key !== $setKey) {
            throw new \RuntimeException('Unautorized key');
        }

        Plugin::$plugin->imagerx->deleteImageTransformCaches();

        return true;
    }

    /**
     * Controller action to clear remote images
     *
     * @throws \Throwable
     */
    public function actionClearRemoteImages(): bool
    {
        $config = Plugin::$plugin->getSettings();
        $request = Craft::$app->getRequest();

        $key = $request->getParam('key', '');
        $setKey = $config->clearKey ?? '';

        if ($setKey === '' || $key !== $setKey) {
            throw new \RuntimeException('Unautorized key');
        }

        Plugin::$plugin->imagerx->deleteRemoteImageCaches();

        return true;
    }
}
