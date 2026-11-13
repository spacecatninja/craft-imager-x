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

use ColorThief\Image\ImageLoader;

/**
 * Color analysis that Color Thief doesn't do the way we need it.
 *
 * @author    André Elvan
 * @since     6.2.0
 */
class ColorHelpers
{
    // Const Properties
    // =========================================================================

    /**
     * Bits kept per channel when counting colors. Four bits gives 16 levels per channel and
     * 4096 buckets, coarse enough that noise and grain in a photo land in the same bucket.
     */
    public const DOMINANT_COLOR_BITS = 4;

    /**
     * Pixels with an alpha value above this are too transparent to count. PHP's alpha range
     * runs from 0 (opaque) to 127 (transparent). Same threshold as Color Thief uses.
     */
    public const ALPHA_THRESHOLD = 62;

    // Public Methods
    // =========================================================================

    /**
     * Returns the most common color in an image, as an `[r, g, b]` array.
     *
     * Colors are counted in buckets of 16 levels per channel, and each bucket is scored
     * together with its neighbours, so that a soft gradient (a sky, a studio backdrop) isn't
     * split into lots of small buckets that each lose to one flat patch. The returned color
     * is the actual average of the pixels in the winning bucket.
     *
     * Only transparent pixels are skipped. Unlike Color Thief, white counts like any other color.
     *
     * @param string     $path    Path to a local image file.
     * @param int        $quality Sampling step. 1 looks at every pixel, 10 at every tenth.
     * @param array|null $area    Optional area to sample, with the keys `x`, `y`, `w` and `h`, in pixels.
     *
     * @return int[]|null The color, or null if every sampled pixel is transparent.
     *
     * @throws \InvalidArgumentException If `$quality` is less than 1, or `$area` is outside the image.
     * @throws \ColorThief\Exception\NotReadableException If the image can't be read.
     * @throws \ColorThief\Exception\NotSupportedException If no image extension is available.
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public static function getDominantColor(string $path, int $quality = 10, ?array $area = null): ?array
    {
        if ($quality < 1) {
            throw new \InvalidArgumentException('The quality argument must be an integer greater than zero.');
        }

        $image = (new ImageLoader())->load($path);

        try {
            $imageWidth = $image->getWidth();
            $imageHeight = $image->getHeight();

            $startX = (int)($area['x'] ?? 0);
            $startY = (int)($area['y'] ?? 0);
            $width = (int)($area['w'] ?? $imageWidth - $startX);
            $height = (int)($area['h'] ?? $imageHeight - $startY);

            if ($startX < 0 || $startY < 0 || $width < 1 || $height < 1) {
                throw new \InvalidArgumentException('Area is out of image bounds.');
            }

            if ($startX + $width > $imageWidth || $startY + $height > $imageHeight) {
                throw new \InvalidArgumentException('Area is out of image bounds.');
            }

            $bits = self::DOMINANT_COLOR_BITS;
            $shift = 8 - $bits;

            $counts = [];
            $redSums = [];
            $greenSums = [];
            $blueSums = [];

            for ($i = 0, $pixelCount = $width * $height; $i < $pixelCount; $i += $quality) {
                $color = $image->getPixelColor($startX + ($i % $width), $startY + intdiv($i, $width));

                if ($color->alpha > self::ALPHA_THRESHOLD) {
                    continue;
                }

                $key = (($color->red >> $shift) << (2 * $bits)) | (($color->green >> $shift) << $bits) | ($color->blue >> $shift);

                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $redSums[$key] = ($redSums[$key] ?? 0) + $color->red;
                $greenSums[$key] = ($greenSums[$key] ?? 0) + $color->green;
                $blueSums[$key] = ($blueSums[$key] ?? 0) + $color->blue;
            }
        } finally {
            $image->destroy();
        }

        if ($counts === []) {
            return null;
        }

        $best = self::_getMostCommonBucket($counts);

        return [
            (int)round($redSums[$best] / $counts[$best]),
            (int)round($greenSums[$best] / $counts[$best]),
            (int)round($blueSums[$best] / $counts[$best]),
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the key of the bucket that, together with its neighbours, holds the most pixels.
     * Ties go to the bucket with the most pixels of its own.
     *
     * @param array<int, int> $counts Pixel count per bucket key.
     *
     * @return int
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    private static function _getMostCommonBucket(array $counts): int
    {
        $bits = self::DOMINANT_COLOR_BITS;
        $mask = (1 << $bits) - 1;

        $best = null;
        $bestScore = -1;

        foreach ($counts as $key => $count) {
            $red = $key >> (2 * $bits);
            $green = ($key >> $bits) & $mask;
            $blue = $key & $mask;
            $score = 0;

            for ($r = max(0, $red - 1); $r <= min($mask, $red + 1); $r++) {
                for ($g = max(0, $green - 1); $g <= min($mask, $green + 1); $g++) {
                    for ($b = max(0, $blue - 1); $b <= min($mask, $blue + 1); $b++) {
                        $score += $counts[($r << (2 * $bits)) | ($g << $bits) | $b] ?? 0;
                    }
                }
            }

            if ($score > $bestScore || ($score === $bestScore && $count > $counts[$best])) {
                $best = $key;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
