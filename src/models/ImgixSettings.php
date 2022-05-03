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

use craft\base\Model;

class ImgixSettings extends Model
{
    public string $domain = '';

    public bool $useHttps = true;

    public string $signKey = '';

    public bool $sourceIsWebProxy = false;

    public bool $useCloudSourcePath = true;

    public string|array $addPath = '';
    
    public bool $getExternalImageDimensions = true;

    public array $defaultParams = [];

    public bool $excludeFromPurge = false;

    public string $apiKey = '';

    public function __construct($config = [])
    {
        parent::__construct($config);

        if (!empty($config)) {
            \Yii::configure($this, $config);
        }
    }
}
