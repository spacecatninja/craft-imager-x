<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\services;

use craft\base\Component;
use craft\elements\Asset;

use JetBrains\PhpStorm\Pure;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\exceptions\ImagerException;

use ColorThief\ColorThief;

use SSNepenthe\ColorUtils\Colors\Rgba as RGBA;
use SSNepenthe\ColorUtils\Colors\Color as Color;
use function SSNepenthe\ColorUtils\{
    alpha,
    blue,
    brightness,
    brightness_difference,
    color,
    color_difference,
    contrast_ratio,
    green,
    hsl,
    hsla,
    hue,
    is_bright,
    is_light,
    lightness,
    looks_bright,
    name,
    opacity,
    perceived_brightness,
    red,
    relative_luminance,
    rgb,
    rgba,
    saturation
};

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
    /**
     * Get dominant color of image
     *
     *
     */
    public function getDominantColor(Asset|string $image, int $quality = 10, string $colorValue = 'hex'): bool|array|string|null
    {
        try {
            $source = new LocalSourceImageModel($image);
            $source->getLocalCopy();
        } catch (ImagerException $imagerException) {
            return null;
        }

        try {
            $dominantColor = ColorThief::getColor($source->getFilePath(), $quality);
        } catch (\RuntimeException $runtimeException) {
            \Craft::error('Couldn\'t get dominant color for "' . $source->getFilePath() . '". Error was: ' . $runtimeException->getMessage());
            return null;
        }

        ImagerService::cleanSession();

        if (!\is_array($dominantColor)) {
            return null;
        }

        return $colorValue === 'hex' ? self::rgb2hex($dominantColor) : $dominantColor;
    }

    /**
     * Gets color palette for image
     *
     *
     */
    public function getColorPalette(Asset|string $image, int $colorCount = 8, int $quality = 10, string $colorValue = 'hex'): ?array
    {
        try {
            $source = new LocalSourceImageModel($image);
            $source->getLocalCopy();
        } catch (ImagerException $imagerException) {
            return null;
        }

        try {
            // Hack for count error in ColorThief
            // See: https://github.com/lokesh/color-thief/issues/19 and https://github.com/lokesh/color-thief/pull/84
            $adjustedColorCount = $colorCount > 7 ? $colorCount + 1 : $colorCount;
            
            $palette = ColorThief::getPalette($source->getFilePath(), $adjustedColorCount, $quality);
            
            $palette = array_slice($palette, 0, $colorCount);
        } catch (\RuntimeException $runtimeException) {
            \Craft::error('Couldn\'t get palette for "' . $source->getFilePath() . '". Error was: ' . $runtimeException->getMessage());
            return null;
        }
        
        ImagerService::cleanSession();

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
    public function isBright(array|string $color, float $threshold=127.5): bool
    {
        $c = color($color);
        return is_bright($c, $threshold);
    }

    /**
     * Checks lightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 50.0. 
     *
     *
     */
    public function isLight(array|string $color, int $threshold=50): bool
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
        return '#' . sprintf('%02x', $rgb[0]) . sprintf('%02x', $rgb[1]) . sprintf('%02x', $rgb[2]);
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
            $r = hexdec($hex[0] . $hex[0]);
            $g = hexdec($hex[1] . $hex[1]);
            $b = hexdec($hex[2] . $hex[2]);
        } else {
            $r = hexdec($hex[0] . $hex[1]);
            $g = hexdec($hex[2] . $hex[3]);
            $b = hexdec($hex[4] . $hex[5]);
        }

        return [$r, $g, $b];
    }

    /**
     * Convert palette to array of hex colors
     *
     *
     */
    #[Pure] private function paletteToHex(array $palette): array
    {
        $r = [];
        foreach ($palette as $paletteColor) {
            $r[] = self::rgb2hex($paletteColor);
        }

        return $r;
    }
}
