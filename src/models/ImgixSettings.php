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

use craft\base\Model;

class ImgixSettings extends Model
{
    public $domains = [];
    public $domain = '';
    public $useHttps = true;
    public $signKey = '';
    public $sourceIsWebProxy = false;
    public $useCloudSourcePath = true;
    public $addPath = null;
    public $shardStrategy = null;
    public $getExternalImageDimensions = true;
    public $defaultParams = [];
    public $excludeFromPurge = false;
    public $apiKey = '';
    
    public function __construct($config = [])
    {
        if (!empty($config)) {
            \Yii::configure($this, $config);
        }
        
        if ($this->shardStrategy !== null) {
            \Craft::$app->deprecator->log(__METHOD__, 'The `shardStrategy` config setting for Imgix has been deprecated and should be removed.');
        }
    
        if (is_array($this->domains) && count($this->domains) > 0) {
            \Craft::$app->deprecator->log(__METHOD__, 'The `domains` config setting for Imgix has been deprecated, use `domain` (single string value) instead.');
            
            if ($this->domain === '') {
                $this->domain = $this->domains[0];
            }
        }
        
        if ($this->apiKey !== '' && strlen($this->apiKey)<50) {
            \Craft::$app->deprecator->log(__METHOD__, 'You appear to be using an API key for the old version of the Imgix API. You need to acquire a new one, with permissions to purge, and replace the old one in your imager-x.php config file with it. See https://blog.imgix.com/2020/10/16/api-deprecation for more information.');
        }
    }
}
