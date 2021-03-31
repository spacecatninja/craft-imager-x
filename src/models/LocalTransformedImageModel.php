<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\models;

use craft\helpers\FileHelper;

use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\exceptions\ImagerException;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\Box;

use kornrunner\Blurhash\Blurhash;

use yii\base\InvalidConfigException;

class LocalTransformedImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{
    /**
     * Constructor
     *
     * @param LocalTargetImageModel $targetModel
     * @param LocalSourceImageModel $sourceModel
     * @param array $transform
     *
     * @throws ImagerException
     */
    public function __construct($targetModel, $sourceModel, $transform)
    {
        $this->source = $sourceModel;
        $this->path = $targetModel->getFilePath();
        $this->filename = $targetModel->filename;
        $this->url = $targetModel->url;
        $this->isNew = $targetModel->isNew;

        $this->extension = $targetModel->extension;
        $this->size = @filesize($targetModel->getFilePath());

        try {
            $this->mimeType = FileHelper::getMimeType($targetModel->getFilePath());
        } catch (InvalidConfigException $e) {
            // just ignore
        }

        $imageInfo = @getimagesize($targetModel->getFilePath());

        if (\is_array($imageInfo) && $imageInfo[0] !== '' && $imageInfo[1] !== '') {
            $this->width = $imageInfo[0];
            $this->height = $imageInfo[1];
        } else { // Couldn't get size. Calculate size based on source image and transform.
            /** @var ConfigModel $settings */
            $config = ImagerService::getConfig();

            $sourceImageInfo = @getimagesize($sourceModel->getFilePath());

            try {
                $sourceSize = new Box($sourceImageInfo[0], $sourceImageInfo[1]);
                $targetCrop = ImagerHelpers::getCropSize($sourceSize, $transform, $config->getSetting('allowUpscale', $transform));
                $this->width = $targetCrop->getWidth();
                $this->height = $targetCrop->getHeight();
            } catch (InvalidArgumentException $e) {
                throw new ImagerException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
    
    /**
     * @param string $unit
     * @param int $precision
     *
     * @return float|int
     */
    public function getSize($unit = 'b', $precision = 2)
    {
        $unit = strtolower($unit);

        switch ($unit) {
            case 'g':
            case 'gb':
                return round(((int)$this->size) / 1024 / 1024 / 1024, $precision);
            case 'm':
            case 'mb':
                return round(((int)$this->size) / 1024 / 1024, $precision);
            case 'k':
            case 'kb':
                return round(((int)$this->size) / 1024, $precision);
        }

        return $this->size;
    }

    /**
     * @return string
     */
    public function getDataUri(): string
    {
        $imageData = $this->getBase64Encoded();
        return sprintf('data:image/%s;base64,%s', $this->extension, $imageData);
    }

    /**
     * @return string
     */
    public function getBase64Encoded(): string
    {
        $image = @file_get_contents($this->path);
        return base64_encode($image);
    }

    /**
     * @return string
     */
    public function getBlurhash()
    {
        $blurhashFile = $this->getPath();
        $key = 'imager-x-local-blurhash-' . base64_encode($blurhashFile);
        $cache = \Craft::$app->getCache();
        
        $blurhashData = $cache->getOrSet($key, static function () use ($blurhashFile) {
            $image = imagecreatefromstring(file_get_contents($blurhashFile));
            $width = imagesx($image);
            $height = imagesy($image);
            
            $pixels = [];
            for ($y = 0; $y < $height; ++$y) {
                $row = [];
                for ($x = 0; $x < $width; ++$x) {
                    $index = imagecolorat($image, $x, $y);
                    $colors = imagecolorsforindex($image, $index);
            
                    $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                }
                $pixels[] = $row;
            }
            
            $components_x = 4;
            $components_y = 3;
            return Blurhash::encode($pixels, $components_x, $components_y);
        });
        
        if (!$blurhashData) {
            \Craft::error('An error occured when trying to create blurhash from local file. The file path was: ' . $blurhashFile);
            return '';
        }
        
        return (string)$blurhashData;
    }
    
}
