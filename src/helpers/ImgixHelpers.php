<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use Craft;
use craft\elements\Asset;

use craft\fs\Local;
use craft\helpers\App;
use craft\helpers\FileHelper;

use Imgix\UrlBuilder;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\models\ImgixSettings;

use yii\base\InvalidConfigException;

class ImgixHelpers
{
    /**
     * @throws ImagerException
     */
    public static function getImgixFilePath(Asset|string $image, ImgixSettings $config): string
    {
        if (\is_string($image)) { // if $image is a string, just pass it to builder, we have to assume the user knows what he's doing (sry) :)
            return $image;
        }
        
        if ($config->sourceIsWebProxy) {
            return $image->getUrl() ?? '';
        }
            
        try {
            $volume = $image->getVolume();
            $fs = $image->getVolume()->getFs();
        } catch (InvalidConfigException $invalidConfigException) {
            Craft::error($invalidConfigException->getMessage(), __METHOD__);
            throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
        }

        if (($config->useCloudSourcePath) && (property_exists($fs, 'subfolder') && $fs->subfolder !== null) && $fs::class !== Local::class) {
            $path = implode('/', [App::parseEnv($fs->subfolder), $image->getPath()]);
        } else {
            $path = $image->getPath();
        }
        
        if (!empty($config->addPath)) {
            if (\is_string($config->addPath) && $config->addPath !== '') {
                $path = implode('/', [$config->addPath, $path]);
            } elseif (is_array($config->addPath)) {
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

    public static function getBuilder(ImgixSettings $config): UrlBuilder
    {
        return new UrlBuilder($config->domain,
            $config->useHttps,
            $config->signKey,
            false);
    }
}
