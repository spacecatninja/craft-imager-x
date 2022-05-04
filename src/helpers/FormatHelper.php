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

/**
 * Class FormatHelper
 *
 * @author    SPACECATNINJA
 * @since     4.0.0
 */

class FormatHelper
{
    public static function formatBytes(int $size, string $unit = 'b', int $precision = 2): float|int
    {
        $unit = strtolower($unit);

        return match ($unit) {
            'g', 'gb' => round(($size) / 1024 / 1024 / 1024, $precision),
            'm', 'mb' => round(($size) / 1024 / 1024, $precision),
            'k', 'kb' => round(($size) / 1024, $precision),
            default => $size,
        };
    }
}
