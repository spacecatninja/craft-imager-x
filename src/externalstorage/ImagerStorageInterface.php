<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\externalstorage;

interface ImagerStorageInterface
{
    /**
     * @param string $file
     * @param string $uri
     * @param bool   $isFinal
     * @param array  $settings
     */
    public static function upload(string $file, string $uri, bool $isFinal, array $settings);
}
