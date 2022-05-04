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

use Craft;

class VersionHelpers
{
    /**
     * Compares Craft version
     */
    public static function craftIs($version, $operator = '>='): bool|int
    {
        return version_compare(Craft::$app->getVersion(), $version, $operator);
    }
}
