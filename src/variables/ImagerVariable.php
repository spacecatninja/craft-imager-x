<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\variables;

use Craft;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use spacecatninja\imagerx\helpers\NamedTransformHelpers;
use spacecatninja\imagerx\ImagerX as Plugin;
use spacecatninja\imagerx\models\TransformedImageInterface;
use spacecatninja\imagerx\services\ImagerColorService;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\exceptions\ImagerException;
use craft\elements\Asset;

class ImagerVariable
{
    /**
     * Transforms an image
     *
     * @param string|Asset $file
     * @param array|string $transforms
     * @param array|null   $transformDefaults
     * @param array|null   $configOverrides
     *
     * @return array|TransformedImageInterface|null
     *
     * @throws ImagerException
     */
    public function transformImage(Asset|string $file, array|string $transforms, array $transformDefaults = null, array $configOverrides = null): array|TransformedImageInterface|null
    {
        return Plugin::$plugin->imagerx->transformImage($file, $transforms, $transformDefaults, $configOverrides);
    }

    /**
     * Takes an array of models that supports getUrl() and getWidth(), and returns a srcset
     * and returns a srcset string
     *
     * @param array  $images
     * @param string $descriptor
     *
     * @return string
     */
    public function srcset(array $images, string $descriptor = 'w'): string
    {
        return Plugin::$plugin->imagerx->srcset($images, $descriptor);
    }

    /**
     * Returns a base64 encoded transparent pixel.
     *
     * @param int $width
     * @param int $height
     * @param string $color
     *
     * @return string
     */
    public function base64Pixel(int $width = 1, int $height = 1, string $color = 'transparent'): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height' style='background:$color'/>");
    }

    /**
     * Returns an image placeholder.
     *
     * @param array|null $config
     *
     * @return string
     * @throws ImagerException
     */
    public function placeholder(array $config = null): string
    {
        return Plugin::$plugin->placeholder->placeholder($config);
    }

    /**
     * Gets the dominant color of an image
     *
     * @param string|Asset $image
     * @param int          $quality
     * @param string       $colorValue
     *
     * @return string|array|bool|null
     */
    public function getDominantColor(Asset|string $image, int $quality = 10, string $colorValue = 'hex'): string|array|bool|null
    {
        return Plugin::$plugin->color->getDominantColor($image, $quality, $colorValue);
    }

    /**
     * Gets a palette of colors from an image
     *
     * @param string|Asset $image
     * @param int          $colorCount
     * @param int          $quality
     * @param string       $colorValue
     *
     * @return array|null
     */
    public function getColorPalette(Asset|string $image, int $colorCount = 8, int $quality = 10, string $colorValue = 'hex'): ?array
    {
        return Plugin::$plugin->color->getColorPalette($image, $colorCount, $quality, $colorValue);
    }

    /**
     * Converts a hex color value to rgb
     *
     * @param string $color
     *
     * @return array
     */
    public function hex2rgb(string $color): array
    {
        return ImagerColorService::hex2rgb($color);
    }

    /**
     * Converts a rgb color value to hex
     *
     * @param array $color
     *
     * @return string
     */
    #[Pure] public function rgb2hex(array $color): string
    {
        return ImagerColorService::rgb2hex($color);
    }

    /**
     * Calculates color brightness (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 (black) to 255 (white).
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getBrightness(array|string $color): float
    {
        return Plugin::$plugin->color->getBrightness($color);
    }

    /**
     * Get the hue channel of a color.
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getHue(array|string $color): float
    {
        return Plugin::$plugin->color->getHue($color);
    }

    /**
     * Get the lightness channel of a color
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getLightness(array|string $color): float
    {
        return Plugin::$plugin->color->getLightness($color);
    }

    /**
     * Checks brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     * @param array|string $color
     * @param float        $threshold
     *
     * @return bool
     */
    public function isBright(array|string $color, float $threshold = 127.5): bool
    {
        return Plugin::$plugin->color->isBright($color, $threshold);
    }

    /**
     * Checks lightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 50.0.
     *
     * @param array|string $color
     * @param int          $threshold
     *
     * @return bool
     */
    public function isLight(array|string $color, int $threshold = 50): bool
    {
        return Plugin::$plugin->color->isLight($color, $threshold);
    }

    /**
     * Checks perceived_brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     * @param array|string $color
     * @param float        $threshold
     *
     * @return bool
     */
    public function looksBright(array|string $color, float $threshold = 127.5): bool
    {
        return Plugin::$plugin->color->looksBright($color, $threshold);
    }

    /**
     * Calculates the perceived brightness (http://alienryderflex.com/hsp.html) of a color on a scale from 0 (black) to 255 (white).
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getPercievedBrightness(array|string $color): float
    {
        return Plugin::$plugin->color->getPercievedBrightness($color);
    }

    /**
     * Calculates the relative luminance (https://www.w3.org/TR/WCAG20/#relativeluminancedef) of a color on a scale from 0 (black) to 1 (white).
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getRelativeLuminance(array|string $color): float
    {
        return Plugin::$plugin->color->getRelativeLuminance($color);
    }

    /**
     * Get the saturation channel of a color.
     *
     * @param array|string $color
     *
     * @return float
     */
    public function getSaturation(array|string $color): float
    {
        return Plugin::$plugin->color->getSaturation($color);
    }

    /**
     * Calculates brightness difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 255.
     *
     * @param array|string $color1
     * @param array|string $color2
     *
     * @return float
     */
    public function getBrightnessDifference(array|string $color1, array|string $color2): float
    {
        return Plugin::$plugin->color->getBrightnessDifference($color1, $color2);
    }

    /**
     * Calculates color difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 765.
     *
     * @param array|string $color1
     * @param array|string $color2
     *
     * @return int
     */
    public function getColorDifference(array|string $color1, array|string $color2): int
    {
        return Plugin::$plugin->color->getColorDifference($color1, $color2);
    }

    /**
     * Calculates the contrast ratio (https://www.w3.org/TR/WCAG20/#contrast-ratiodef) between two colors on a scale from 1 to 21.
     *
     * @param array|string $color1
     * @param array|string $color2
     *
     * @return float
     */
    public function getContrastRatio(array|string $color1, array|string $color2): float
    {
        return Plugin::$plugin->color->getContrastRatio($color1, $color2);
    }

    /**
     * Checks for server webp support
     *
     * @return bool
     */
    public function serverSupportsWebp(): bool
    {
        return ImagerService::hasSupportForWebP();
    }

    /**
     * Checks for server avif support
     *
     * @return bool
     */
    public function serverSupportsAvif(): bool
    {
        return ImagerService::hasSupportForAvif();
    }

    /**
     * Checks for server jxl support
     *
     * @return bool
     */
    public function serverSupportsJxl(): bool
    {
        return ImagerService::hasSupportForJxl();
    }

    /**
     * Checks for webp support in browser
     *
     * @return bool
     */
    public function clientSupportsWebp(): bool
    {
        return Craft::$app->getRequest()->accepts('image/webp');
    }

    /**
     * Checks if the browser accepts a given format.
     *
     * @param string $format
     * @return bool
     */
    public function clientSupports(string $format): bool
    {
        if (!str_contains($format, 'image/')) {
            $format = "image/$format";
        }
        
        return Craft::$app->getRequest()->accepts($format);
    }

    /**
     * Checks if asset is animated (only gif support atm)
     *
     * @param string|Asset $asset
     *
     * @return bool
     *
     * @throws ImagerException
     */
    public function isAnimated(Asset|string $asset): bool
    {
        return Plugin::$plugin->imagerx->isAnimated($asset);
    }

    /**
     * Checks if Imgix is enabled
     *
     * @return bool
     */
    public function imgixEnabled(): bool
    {
        return Plugin::$plugin->getSettings()->transformer === 'imgix';
    }
    
    /**
     * Returns transformer handle
     *
     * @return bool
     */
    public function transformer(): bool
    {
        return Plugin::$plugin->getSettings()->transformer;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasNamedTransform(string $name): bool
    {
        return NamedTransformHelpers::getNamedTransform($name) !== null;
    }
    
    /**
     * @param string $name
     * @return array|null
     */
    public function getNamedTransform(string $name): ?array
    {
        return NamedTransformHelpers::getNamedTransform($name);
    }
}
