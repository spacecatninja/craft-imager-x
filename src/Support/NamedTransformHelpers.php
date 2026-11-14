<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Support;

use SpaceCatNinja\ImagerX\Services\NamedTransforms;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class NamedTransformHelpers
{
    public static function getNamedTransform(string $name): ?array
    {
        return app(NamedTransforms::class)->get($name);
    }
}
