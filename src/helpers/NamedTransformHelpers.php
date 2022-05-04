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

use spacecatninja\imagerx\services\ImagerService;

class NamedTransformHelpers
{
    public static function getNamedTransform(string $name): ?array
    {
        return ImagerService::$namedTransforms[$name] ?? null;
    }
}
