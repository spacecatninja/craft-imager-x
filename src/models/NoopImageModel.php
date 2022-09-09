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
use spacecatninja\imagerx\exceptions\ImagerException;

use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\services\ImagerService;

use yii\base\InvalidConfigException;

class NoopImageModel extends BaseTransformedImageModel implements TransformedImageInterface
{
    public string $path;

    public string $filename;

    public string $url;

    public string $extension;

    public string $mimeType;

    /**
     * @var int
     */
    public int $width;

    /**
     * @var int
     */
    public int $height;

    /**
     * @var int|float
     */
    public int|float $size;

    /**
     * @var bool
     */
    public bool $isNew = false;

    /**
     * Constructor
     *
     *
     * @throws ImagerException
     */
    public function __construct(LocalSourceImageModel $sourceModel, array $transform)
    {
        $this->source = $sourceModel;
        $this->path = $sourceModel->getFilePath();
        $this->filename = $sourceModel->filename;
        $this->url = $sourceModel->url;
        $this->isNew = false;

        $this->extension = $sourceModel->extension;
        $this->size = @filesize($sourceModel->getFilePath());

        try {
            $this->mimeType = FileHelper::getMimeType($sourceModel->getFilePath());
        } catch (InvalidConfigException $invalidConfigException) {
            // just ignore
        }

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
}
