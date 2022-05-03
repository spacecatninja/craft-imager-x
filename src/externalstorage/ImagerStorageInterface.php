<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\externalstorage;

interface ImagerStorageInterface
{
    public static function upload(string $file, string $uri, bool $isFinal, array $settings);
}
