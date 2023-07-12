<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\models;

use craft\helpers\FileHelper;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\Box;
use kornrunner\Blurhash\Blurhash;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\CacheHelpers;
use spacecatninja\imagerx\helpers\ImagerHelpers;

use spacecatninja\imagerx\services\ImagerService;

use yii\base\InvalidConfigException;
use yii\caching\TagDependency;

class LocalTransformedImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{
    /**
     * Constructor
     *
     * @param LocalTargetImageModel $targetModel
     * @param LocalSourceImageModel $sourceModel
     * @param array                 $transform
     *
     * @throws ImagerException
     */
    public function __construct(LocalTargetImageModel $targetModel, LocalSourceImageModel $sourceModel, array $transform)
    {
        $this->source = $sourceModel;
        $this->path = $targetModel->getFilePath();
        $this->filename = $targetModel->filename;
        $this->url = $targetModel->url;
        $this->isNew = $targetModel->isNew;

        $this->extension = $targetModel->extension;
        $this->size = @filesize($targetModel->getFilePath());

        try {
            $this->mimeType = FileHelper::getMimeType($targetModel->getFilePath()) ?? '';
        } catch (InvalidConfigException $invalidConfigException) {
            // just ignore
        }

        $imageInfo = @getimagesize($targetModel->getFilePath());

        if (\is_array($imageInfo) && $imageInfo[0] !== '' && $imageInfo[1] !== '') {
            $this->width = $imageInfo[0];
            $this->height = $imageInfo[1];
        }
        
        if ($this->width === 0 || $this->height === 0) { 
            $config = ImagerService::getConfig();

            $sourceImageSize = ImagerHelpers::getSourceImageSize($sourceModel);
            
            try {
                $sourceSize = new Box($sourceImageSize[0], $sourceImageSize[1]);
                $targetCrop = ImagerHelpers::getCropSize($sourceSize, $transform, $config->getSetting('allowUpscale', $transform));
                $this->width = $targetCrop->getWidth();
                $this->height = $targetCrop->getHeight();
            } catch (InvalidArgumentException $invalidArgumentException) {
                throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
            }
        }
    }
    
    public function getSize(string $unit = 'b', int $precision = 2): float|int
    {
        $unit = strtolower($unit);

        return match ($unit) {
            'g', 'gb' => round(((int)$this->size) / 1024 / 1024 / 1024, $precision),
            'm', 'mb' => round(((int)$this->size) / 1024 / 1024, $precision),
            'k', 'kb' => round(((int)$this->size) / 1024, $precision),
            default => $this->size,
        };
    }

    public function getDataUri(): string
    {
        $imageData = $this->getBase64Encoded();
        return sprintf('data:image/%s;base64,%s', $this->extension, $imageData);
    }

    public function getBase64Encoded(): string
    {
        $image = @file_get_contents($this->path);
        return base64_encode($image);
    }

    public function getBlurhash(): string
    {
        $config = ImagerService::getConfig();
        
        $blurhashFile = $this->getPath();
        $key = 'imager-x-local-blurhash-' . base64_encode($blurhashFile);
        $cache = \Craft::$app->getCache();
        $dep = null;
        
        if (!$cache) {
            \Craft::error('Cache component not found when trying to create blurhash in transformed image model');
            return '';
        }
        
        if ($this->source && $this->source->asset) {
            $dep = new TagDependency(['tags' => CacheHelpers::getElementCacheTags($this->source->asset)]);
        }
        
        $blurhashData = $cache->getOrSet($key, static function() use ($blurhashFile, $config) {
            $image = imagecreatefromstring(file_get_contents($blurhashFile));
            $width = imagesx($image);
            $height = imagesy($image);
            $ratio = $height/$width;
            $factor = 1;
            
            if ($width > 100) {
                $factor = $width/100;
            }
            
            $pixels = [];
            for ($y = 0; $y < $height; $y+=($factor*$ratio)) {
                $row = [];
                for ($x = 0; $x < $width; $x+=$factor) {
                    $index = imagecolorat($image, floor($x), floor($y));
                    $colors = imagecolorsforindex($image, $index);

                    $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                }

                $pixels[] = $row;
            }
            
            $components_x = max(1, min((int)$config->blurhashComponents[0], 9));
            $components_y = max(1, min((int)$config->blurhashComponents[1], 9));
            return Blurhash::encode($pixels, $components_x, $components_y);
        }, null, $dep);
        
        if (!$blurhashData) {
            \Craft::error('An error occured when trying to create blurhash from local file. The file path was: ' . $blurhashFile);
            return '';
        }
        
        return (string)$blurhashData;
    }
}
