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

use CraftCms\Cms\Cms;

class VersionHelpers
{
    /**
     * Compares Craft version
     */
    public static function craftIs($version, $operator = '>='): bool|int
    {
        return version_compare(Cms::version(), $version, $operator);
    }
}
