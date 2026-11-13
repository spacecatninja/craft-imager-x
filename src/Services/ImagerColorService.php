<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Services;

use ColorThief\ColorThief;
use ColorThief\Image\ImageLoader;
use craft\base\Component;
use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\ImagerX\Support\CacheHelpers;
use SpaceCatNinja\ImagerX\Support\ColorHelpers;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\Support\WhiteInclusiveColorThiefAdapter;
use SpaceCatNinja\ImagerX\Data\LocalSourceImageModel;

use yii\caching\TagDependency;
use function SSNepenthe\ColorUtils\brightness;
use function SSNepenthe\ColorUtils\brightness_difference;
use function SSNepenthe\ColorUtils\color;
use function SSNepenthe\ColorUtils\color_difference;
use function SSNepenthe\ColorUtils\contrast_ratio;
use function SSNepenthe\ColorUtils\hue;
use function SSNepenthe\ColorUtils\is_bright;
use function SSNepenthe\ColorUtils\is_light;
use function SSNepenthe\ColorUtils\lightness;
use function SSNepenthe\ColorUtils\looks_bright;
use function SSNepenthe\ColorUtils\perceived_brightness;
use function SSNepenthe\ColorUtils\relative_luminance;
use function SSNepenthe\ColorUtils\saturation;

/**
 * ImagerColorService Service
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class ImagerColorService extends Component
{
    // Const Properties
    // =========================================================================

    /**
     * Part of every color cache key. Bump it when the color analysis changes, so that
     * values cached by an earlier version aren't served.
     */
    private const CACHE_KEY_VERSION = 'v2';

    // Public Methods
    // =========================================================================

    /**
     * Gets the dominant color of an image, which is the most common color in it, white included.
     * See ColorHelpers::getDominantColor() for how it's calculated.
     *
     * @param Asset|string $image      The image, as an asset or a path/URL.
     * @param int          $quality    Sampling step. 1 looks at every pixel, 10 at every tenth.
     * @param string       $colorValue `hex` for a hex string, anything else for an `[r, g, b]` array.
     * @param array|null   $area       Optional area to sample, with the keys `x`, `y`, `w` and `h`, in pixels.
     *
     * @return bool|array|string|null The color, or null if the image couldn't be analyzed or is fully transparent.
     *
     * @throws \InvalidArgumentException If `$quality` is less than 1, or `$area` is outside the image.
     */
    public function getDominantColor(Asset|string $image, int $quality = 10, string $colorValue = 'hex', ?array $area = null): bool|array|string|null
    {
        $imageIdString = is_string($image) ? base64_encode($image) : ('asset-'.$image->id);
        $key = 'imager-x-dominant-color-'.self::CACHE_KEY_VERSION."-$imageIdString-$quality";
        
        if ($area !== null) {
            $key .= '-' . ImagerHelpers::createTransformFilestring($area);
        }
        
        $cache = \Craft::$app->getCache();
        $dep = null;
        
        if (!$cache) {
            \Craft::error('Cache component not found when trying to get dominant color');
            return null;
        }
        
        if ($image instanceof Asset) {
            $dep = new TagDependency(['tags' => CacheHelpers::getElementCacheTags($image)]);
        } 

        $dominantColor = $cache->getOrSet($key, static function() use ($image, $quality, $area) {
            try {
                $source = new LocalSourceImageModel($image);
                $source->getLocalCopy();
            } catch (\Throwable) {
                return null;
            }

            try {
                $dominantColor = ColorHelpers::getDominantColor($source->getFilePath(), $quality, $area);
            } catch (\RuntimeException $runtimeException) {
                \Craft::error('Couldn\'t get dominant color for "'.$source->getFilePath().'". Error was: '.$runtimeException->getMessage());

                return null;
            }

            ImagerService::cleanSession();

            return $dominantColor;
        }, null, $dep);


        if (!\is_array($dominantColor)) {
            return null;
        }

        return $colorValue === 'hex' ? self::rgb2hex($dominantColor) : $dominantColor;
    }

    /**
     * Gets a palette of the most prominent colors in an image, using Color Thief. White pixels are
     * counted, see WhiteInclusiveColorThiefAdapter.
     *
     * @param Asset|string $image      The image, as an asset or a path/URL.
     * @param int          $colorCount Number of colors in the palette.
     * @param int          $quality    Sampling step. 1 looks at every pixel, 10 at every tenth.
     * @param string       $colorValue `hex` for hex strings, anything else for `[r, g, b]` arrays.
     * @param array|null   $area       Optional area to sample, with the keys `x`, `y`, `w` and `h`, in pixels.
     *
     * @return array|null The palette, or null if the image couldn't be analyzed or is fully transparent.
     *
     * @throws \InvalidArgumentException If `$colorCount` is out of range, `$quality` is less than 1, or `$area` is outside the image.
     */
    public function getColorPalette(Asset|string $image, int $colorCount = 8, int $quality = 10, string $colorValue = 'hex', ?array $area = null): ?array
    {
        $imageIdString = is_string($image) ? base64_encode($image) : ('asset-'.$image->id);
        $key = 'imager-x-palette-'.self::CACHE_KEY_VERSION."-$imageIdString-$colorCount-$quality";
        
        if ($area !== null) {
            $key .= '-' . ImagerHelpers::createTransformFilestring($area);
        }
        
        $cache = \Craft::$app->getCache();
        $dep = null;
        
        if (!$cache) {
            \Craft::error('Cache component not found when trying to get palette');
            return null;
        }
        
        if ($image instanceof Asset) {
            $dep = new TagDependency(['tags' => CacheHelpers::getElementCacheTags($image)]);
        } 
        
        $palette = $cache->getOrSet($key, static function() use ($image, $colorCount, $quality, $area) {
            try {
                $source = new LocalSourceImageModel($image);
                $source->getLocalCopy();
            } catch (\Throwable) {
                return null;
            }
    
            try {
                // Hack for count error in ColorThief
                // See: https://github.com/lokesh/color-thief/issues/19 and https://github.com/lokesh/color-thief/pull/84
                $adjustedColorCount = $colorCount > 7 ? $colorCount + 1 : $colorCount;
                $palette = ColorThief::getPalette(
                    sourceImage: $source->getFilePath(),
                    colorCount: $adjustedColorCount,
                    quality: $quality,
                    area: $area,
                    adapter: new WhiteInclusiveColorThiefAdapter((new ImageLoader())->createAdapter()),
                );
            } catch (\RuntimeException $runtimeException) {
                \Craft::error('Couldn\'t get palette for "'.$source->getFilePath().'". Error was: '.$runtimeException->getMessage());

                return null;
            }

            ImagerService::cleanSession();

            return \is_array($palette) ? array_slice($palette, 0, $colorCount) : null;
        }, null, $dep);

        if (!\is_array($palette)) {
            return null;
        }

        return $colorValue === 'hex' ? $this->paletteToHex($palette) : $palette;
    }

    /**
     * Calculates color brightness (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 (black) to 255 (white).
     *
     *
     */
    public function getBrightness(array|string $color): float
    {
        $c = color($color);

        return brightness($c);
    }

    /**
     * Get the hue channel of a color.
     *
     *
     */
    public function getHue(array|string $color): float
    {
        $c = color($color);

        return hue($c);
    }

    /**
     * Get the lightness channel of a color
     *
     *
     */
    public function getLightness(array|string $color): float
    {
        $c = color($color);

        return lightness($c);
    }

    /**
     * Checks brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     *
     */
    public function isBright(array|string $color, float $threshold = 127.5): bool
    {
        $c = color($color);

        return is_bright($c, $threshold);
    }

    /**
     * Checks lightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 50.0.
     *
     *
     */
    public function isLight(array|string $color, int $threshold = 50): bool
    {
        $c = color($color);

        return is_light($c, $threshold);
    }

    /**
     * Checks perceived_brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     *
     */
    public function looksBright(array|string $color, float $threshold = 127.5): bool
    {
        $c = color($color);

        return looks_bright($c, $threshold);
    }

    /**
     * Calculates the perceived brightness (http://alienryderflex.com/hsp.html) of a color on a scale from 0 (black) to 255 (white).
     *
     *
     */
    public function getPercievedBrightness(array|string $color): float
    {
        $c = color($color);

        return perceived_brightness($c);
    }

    /**
     * Calculates the relative luminance (https://www.w3.org/TR/WCAG20/#relativeluminancedef) of a color on a scale from 0 (black) to 1 (white).
     *
     *
     */
    public function getRelativeLuminance(array|string $color): float
    {
        $c = color($color);

        return relative_luminance($c);
    }

    /**
     * Get the saturation channel of a color.
     *
     *
     */
    public function getSaturation(array|string $color): float
    {
        $c = color($color);

        return saturation($c);
    }

    /**
     * Calculates brightness difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 255.
     *
     *
     */
    public function getBrightnessDifference(array|string $color1, array|string $color2): float
    {
        $c1 = color($color1);
        $c2 = color($color2);

        return brightness_difference($c1, $c2);
    }

    /**
     * Calculates color difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 765.
     *
     *
     */
    public function getColorDifference(array|string $color1, array|string $color2): int
    {
        $c1 = color($color1);
        $c2 = color($color2);

        return color_difference($c1, $c2);
    }

    /**
     * Calculates the contrast ratio (https://www.w3.org/TR/WCAG20/#contrast-ratiodef) between two colors on a scale from 1 to 21.
     *
     *
     */
    public function getContrastRatio(array|string $color1, array|string $color2): float
    {
        $c1 = color($color1);
        $c2 = color($color2);

        return contrast_ratio($c1, $c2);
    }

    /**
     * Convert rgb color to hex
     *
     *
     */
    public static function rgb2hex(array $rgb): string
    {
        return '#'.sprintf('%02x', $rgb[0]).sprintf('%02x', $rgb[1]).sprintf('%02x', $rgb[2]);
    }

    /**
     * Convert hex color to rgb
     *
     *
     */
    public static function hex2rgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);

        if (\strlen($hex) === 3) {
            $r = hexdec($hex[0].$hex[0]);
            $g = hexdec($hex[1].$hex[1]);
            $b = hexdec($hex[2].$hex[2]);
        } else {
            $r = hexdec($hex[0].$hex[1]);
            $g = hexdec($hex[2].$hex[3]);
            $b = hexdec($hex[4].$hex[5]);
        }

        return [$r, $g, $b];
    }

    // Private Methods
    // =========================================================================

    /**
     * Convert palette to array of hex colors
     *
     *
     */
    private function paletteToHex(array $palette): array
    {
        $r = [];
        foreach ($palette as $paletteColor) {
            $r[] = self::rgb2hex($paletteColor);
        }

        return $r;
    }
}
