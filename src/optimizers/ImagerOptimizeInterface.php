<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\optimizers;

interface ImagerOptimizeInterface
{
    /**
     * @param string     $file
     * @param array|null $settings
     */
    public static function optimize(string $file, ?array $settings);
}
