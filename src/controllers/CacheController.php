<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
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
     * @var array
     */
    protected $allowAnonymous = ['actionClearTransforms', 'actionClearRemoteImages'];

    // Public Methods
    // =========================================================================

    /**
     * Controller action to clear image transforms
     *
     * @throws \yii\base\ErrorException
     */
    public function actionClearTransforms()
    {
        $config = Plugin::$plugin->getSettings();
        $request = Craft::$app->getRequest();

        $key = $request->getParam('key', '');
        $setKey = $config->clearKey;

        if ($setKey === '' || $key != $setKey) {
            throw new \Exception('Unautorized key');
        }

        Plugin::$plugin->imagerx->deleteImageTransformCaches();

        return true;
    }

    /**
     * Controller action to clear remote images
     *
     * @throws \yii\base\ErrorException
     */
    public function actionClearRemoteImages()
    {
        $config = Plugin::$plugin->getSettings();
        $request = Craft::$app->getRequest();

        $key = $request->getParam('key', '');
        $setKey = $config->clearKey;

        if ($setKey === '' || $key != $setKey) {
            throw new \Exception('Unautorized key');
        }

        Plugin::$plugin->imagerx->deleteRemoteImageCaches();

        return true;
    }
}
