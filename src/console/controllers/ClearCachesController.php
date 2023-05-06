<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\console\controllers;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\helpers\FileHelper;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

class ClearCachesController extends Controller
{
    // Public Methods
    // =========================================================================
    

    /**
     * Clears all Imager X caches
     */
    public function actionAll(): int
    {
        ImagerX::$plugin->imagerx->deleteImageTransformCaches();
        ImagerX::$plugin->imagerx->deleteRemoteImageCaches();
        
        $this->success("> Imager X transforms and runtime cache has been cleared.");
        return ExitCode::OK;
    }
    
    /**
     * Clears the Imager X transforms cache
     */
    public function actionTransformsCache(): int
    {
        ImagerX::$plugin->imagerx->deleteImageTransformCaches();
        
        $this->success("> Imager X transforms cache has been cleared.");
        return ExitCode::OK;
    }
    
    /**
     * Clears the Imager X runtime cache
     */
    public function actionRuntimeCache(): int
    {
        ImagerX::$plugin->imagerx->deleteRemoteImageCaches();
        
        $this->success("> Imager X runtime cache has been cleared.");
        return ExitCode::OK;
    }
    
    
    public function success(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_GREEN);
    }

    public function message(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_GREY);
    }

    public function error(string $text = ''): void
    {
        $this->stdout($text . PHP_EOL, BaseConsole::FG_RED);
    }

    private function fileHasExpired(string $file): bool
    {
        return FileHelper::lastModifiedTime($file) + $this->duration < time();
    }
}
