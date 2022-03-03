<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use Craft;

use craft\helpers\App;
use craft\models\Volume;
use craft\elements\Asset;
use craft\helpers\FileHelper;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\models\ImgixSettings;
use Imgix\UrlBuilder;

use yii\base\InvalidConfigException;

class ImgixHelpers
{
    /**
     * @param string|Asset  $image
     * @param ImgixSettings $config
     *
     * @return string
     * @throws ImagerException
     */
    public static function getImgixFilePath(Asset|string $image, ImgixSettings $config): string
    {
        if (\is_string($image)) { // if $image is a string, just pass it to builder, we have to assume the user knows what he's doing (sry) :)
            return $image;
        } 
        
        if ($config->sourceIsWebProxy === true) {
            return $image->url ?? '';
        } 
            
        try {
            $volume = $image->getVolume();
            $fs = $image->getVolume()->getFs();
        } catch (InvalidConfigException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        if (($config->useCloudSourcePath === true) && isset($fs->subfolder) && \get_class($fs) !== 'craft\fs\Local') {
            $path = implode('/', [App::parseEnv($fs->subfolder), $image->getPath()]);
        } else {
            $path = $image->getPath();
        }
        
        if ($config->addPath) {
            if (\is_string($config->addPath) && $config->addPath !== '') {
                $path = implode('/', [$config->addPath, $path]);
            } else if (is_array($config->addPath)) {
                if (isset($config->addPath[$volume->handle])) {
                    $path = implode('/', [$config->addPath[$volume->handle], $path]);
                }
            }
        }
        
        $path = FileHelper::normalizePath($path);

        //always use forward slashes for imgix
        $path = str_replace('\\', '/', $path);

        return $path;
    }

    /**
     * @param ImgixSettings $config
     * @return UrlBuilder
     */
    public static function getBuilder(ImgixSettings $config): UrlBuilder
    {
        return new UrlBuilder($config->domain,
            $config->useHttps,
            $config->signKey,
            false);
    }
    
}
