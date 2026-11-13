<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Twig;

use SpaceCatNinja\ImagerX\Services\PlaceholderService;
use SpaceCatNinja\ImagerX\Settings;
use Craft;

use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Support\NamedTransformHelpers;
use SpaceCatNinja\ImagerX\ImagerX as Plugin;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;
use SpaceCatNinja\ImagerX\Services\ImagerColorService;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class ImagerVariable
{
    /**
     * Transforms an image
     *
     * @throws ImagerException
     */
    public function transformImage(Asset|ImagerAdapterInterface|string|null $image, array|string $transforms, ?array $transformDefaults = null, ?array $configOverrides = null): array|TransformedImageInterface|null
    {
        return app(ImagerService::class)->transformImage($image, $transforms, $transformDefaults, $configOverrides);
    }

    /**
     * Takes an array of models that supports getUrl() and getWidth(),
     * and returns a srcset string
     */
    public function srcset(?array $images, string $descriptor = 'w'): string
    {
        return app(ImagerService::class)->srcset($images, $descriptor);
    }

    /**
     * Returns a base64 encoded transparent pixel.
     */
    public function base64Pixel(int $width = 1, int $height = 1, string $color = 'transparent'): string
    {
        return 'data:image/svg+xml;charset=utf-8,' . rawurlencode(sprintf('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'%d\' height=\'%d\' style=\'background:%s\'/>', $width, $height, $color));
    }

    /**
     * Returns an image placeholder.
     *
     * @param array|null $config
     *
     * @throws ImagerException
     */
    public function placeholder(?array $config = null): string
    {
        return app(PlaceholderService::class)->placeholder($config);
    }

    /**
     * Gets the dominant color of an image
     *
     *
     */
    public function getDominantColor(Asset|string $image, int $quality = 10, string $colorValue = 'hex', ?array $area = null): string|array|bool|null
    {
        return app(ImagerColorService::class)->getDominantColor($image, $quality, $colorValue, $area);
    }

    /**
     * Gets a palette of colors from an image
     *
     *
     */
    public function getColorPalette(Asset|string $image, int $colorCount = 8, int $quality = 10, string $colorValue = 'hex', ?array $area = null): ?array
    {
        return app(ImagerColorService::class)->getColorPalette($image, $colorCount, $quality, $colorValue, $area);
    }

    /**
     * Converts a hex color value to rgb
     *
     *
     */
    public function hex2rgb(string $color): array
    {
        return ImagerColorService::hex2rgb($color);
    }

    /**
     * Converts a rgb color value to hex
     *
     *
     */
    public function rgb2hex(array $color): string
    {
        return ImagerColorService::rgb2hex($color);
    }

    /**
     * Calculates color brightness (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 (black) to 255 (white).
     *
     *
     */
    public function getBrightness(array|string $color): float
    {
        return app(ImagerColorService::class)->getBrightness($color);
    }

    /**
     * Get the hue channel of a color.
     *
     *
     */
    public function getHue(array|string $color): float
    {
        return app(ImagerColorService::class)->getHue($color);
    }

    /**
     * Get the lightness channel of a color
     *
     *
     */
    public function getLightness(array|string $color): float
    {
        return app(ImagerColorService::class)->getLightness($color);
    }

    /**
     * Checks brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     *
     */
    public function isBright(array|string $color, float $threshold = 127.5): bool
    {
        return app(ImagerColorService::class)->isBright($color, $threshold);
    }

    /**
     * Checks lightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 50.0.
     *
     *
     */
    public function isLight(array|string $color, int $threshold = 50): bool
    {
        return app(ImagerColorService::class)->isLight($color, $threshold);
    }

    /**
     * Checks perceived_brightness($color) >= $threshold. Accepts an optional $threshold float as the last parameter with a default of 127.5.
     *
     *
     */
    public function looksBright(array|string $color, float $threshold = 127.5): bool
    {
        return app(ImagerColorService::class)->looksBright($color, $threshold);
    }

    /**
     * Calculates the perceived brightness (http://alienryderflex.com/hsp.html) of a color on a scale from 0 (black) to 255 (white).
     *
     *
     */
    public function getPercievedBrightness(array|string $color): float
    {
        return app(ImagerColorService::class)->getPercievedBrightness($color);
    }

    /**
     * Calculates the relative luminance (https://www.w3.org/TR/WCAG20/#relativeluminancedef) of a color on a scale from 0 (black) to 1 (white).
     *
     *
     */
    public function getRelativeLuminance(array|string $color): float
    {
        return app(ImagerColorService::class)->getRelativeLuminance($color);
    }

    /**
     * Get the saturation channel of a color.
     *
     *
     */
    public function getSaturation(array|string $color): float
    {
        return app(ImagerColorService::class)->getSaturation($color);
    }

    /**
     * Calculates brightness difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 255.
     *
     *
     */
    public function getBrightnessDifference(array|string $color1, array|string $color2): float
    {
        return app(ImagerColorService::class)->getBrightnessDifference($color1, $color2);
    }

    /**
     * Calculates color difference (https://www.w3.org/TR/AERT#color-contrast) on a scale from 0 to 765.
     *
     *
     */
    public function getColorDifference(array|string $color1, array|string $color2): int
    {
        return app(ImagerColorService::class)->getColorDifference($color1, $color2);
    }

    /**
     * Calculates the contrast ratio (https://www.w3.org/TR/WCAG20/#contrast-ratiodef) between two colors on a scale from 1 to 21.
     *
     *
     */
    public function getContrastRatio(array|string $color1, array|string $color2): float
    {
        return app(ImagerColorService::class)->getContrastRatio($color1, $color2);
    }

    /**
     * Checks for server webp support
     */
    public function serverSupportsWebp(): bool
    {
        return ImagerService::hasSupportForWebP();
    }

    /**
     * Checks for server avif support
     */
    public function serverSupportsAvif(): bool
    {
        return ImagerService::hasSupportForAvif();
    }

    /**
     * Checks for server jxl support
     */
    public function serverSupportsJxl(): bool
    {
        return ImagerService::hasSupportForJxl();
    }

    /**
     * Checks for webp support in browser
     */
    public function clientSupportsWebp(): bool
    {
        return Craft::$app->getRequest()->accepts('image/webp');
    }

    /**
     * Checks if the browser accepts a given format.
     */
    public function clientSupports(string $format): bool
    {
        if (!str_contains($format, 'image/')) {
            $format = sprintf('image/%s', $format);
        }

        return Craft::$app->getRequest()->accepts($format);
    }

    /**
     * Checks if asset is animated (only gif support atm)
     *
     *
     *
     * @throws ImagerException
     */
    public function isAnimated(Asset|string $asset): bool
    {
        return app(ImagerService::class)->isAnimated($asset);
    }

    /**
     * Checks if Imgix is enabled
     */
    public function imgixEnabled(): bool
    {
        return app(Settings::class)->transformer === 'imgix';
    }

    /**
     * Returns transformer handle
     */
    public function transformer(): string
    {
        return app(Settings::class)->transformer;
    }

    public function hasNamedTransform(string $name): bool
    {
        return NamedTransformHelpers::getNamedTransform($name) !== null;
    }

    public function getNamedTransform(string $name): ?array
    {
        return NamedTransformHelpers::getNamedTransform($name);
    }
}
