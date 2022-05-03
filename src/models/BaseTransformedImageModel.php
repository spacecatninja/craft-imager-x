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

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\ImagerX;

class BaseTransformedImageModel implements \Stringable
{
    /**
     * @var mixed
     */
    public mixed $source = null;

    /**
     * @var string
     */
    public string $path = '';

    /**
     * @var string
     */
    public string $filename = '';

    /**
     * @var string
     */
    public string $url = '';

    /**
     * @var string
     */
    public string $extension = '';

    /**
     * @var string
     */
    public string $mimeType = '';

    /**
     * @var int
     */
    public int $width = 0;

    /**
     * @var int
     */
    public int $height = 0;

    /**
     * @var int|float
     */
    public int|float $size = 0;

    /**
     * @var bool
     */
    public bool $isNew = false;

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getSize(string $unit = 'b', int $precision = 2): float|int
    {
        return 0;
    }

    /**
     * @throws ImagerException
     */
    public function getPlaceholder(array $settings = []): string
    {
        if ($settings !== []) {
            if (!isset($settings['width'])) {
                $settings['width'] = $this->width;
            }

            if (!isset($settings['height'])) {
                $settings['height'] = $this->height;
            }
        }

        return ImagerX::$plugin->placeholder->placeholder($settings);
    }

    public function getIsNew(): bool
    {
        return $this->isNew;
    }
    
    public function getDataUri(): string
    {
        return '';
    }

    public function getBase64Encoded(): string
    {
        return '';
    }

    public function getBlurhash(): string
    {
        return '';
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
