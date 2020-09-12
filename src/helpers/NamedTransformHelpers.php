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

use spacecatninja\imagerx\services\ImagerService;
use Craft;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;

class NamedTransformHelpers
{
    /**
     * @param string $name
     * @return array|null
     */
    public static function getNamedTransform($name)
    {
        if (!isset(ImagerService::$namedTransforms[$name])) {
            return null;
        }
        
        return ImagerService::$namedTransforms[$name];
    }
}
