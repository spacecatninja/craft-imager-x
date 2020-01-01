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
use craft\base\Model;
use Yii;

class GenerateSettings extends Model
{
    public $generateOnlyForLiveElements = false;
    public $generateForDrafts = false;
    public $volumes = [];
    public $elements = [];
    public $fields = [];
}
