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

class GenerateSettings extends Model
{
    public bool $generateOnlyForLiveElements = false;

    public bool $generateForDrafts = false;

    public array $volumes = [];

    public array $elements = [];

    public array $fields = [];
}
