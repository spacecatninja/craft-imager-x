<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\lib;

use ColorThief\ColorThief;
use ColorThief\Image\Adapter\AdapterInterface;

/**
 * Wraps a Color Thief image adapter so that white pixels are counted.
 *
 * Color Thief skips every pixel where all three channels are above `ColorThief::THRESHOLD_WHITE`
 * (250), which leaves white out of palettes entirely. This adapter caps each channel at that
 * threshold before Color Thief sees it. Color Thief only uses a pixel to pick a histogram
 * bucket, and 250 to 255 all fall in the same bucket (248 to 255), so a capped pixel is
 * counted exactly where it would have been if Color Thief didn't skip it. Images without
 * white pixels give the same result as before.
 *
 * @author    André Elvan
 * @since     6.2.0
 */
class WhiteInclusiveColorThiefAdapter implements AdapterInterface
{
    // Private Properties
    // =========================================================================

    /**
     * @var AdapterInterface The adapter that loads and reads the image.
     */
    private AdapterInterface $_adapter;

    // Public Methods
    // =========================================================================

    /**
     * @param AdapterInterface $adapter The adapter that loads and reads the image.
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function __construct(AdapterInterface $adapter)
    {
        $this->_adapter = $adapter;
    }

    /**
     * The wrapped adapter was already created, so it's available.
     *
     * @return bool
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function loadFromPath(string $file): AdapterInterface
    {
        $this->_adapter->loadFromPath($file);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function loadFromUrl(string $url): AdapterInterface
    {
        $this->_adapter->loadFromUrl($url);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function loadFromBinary(string $data): AdapterInterface
    {
        $this->_adapter->loadFromBinary($data);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function load($resource): AdapterInterface
    {
        $this->_adapter->load($resource);

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function destroy(): void
    {
        $this->_adapter->destroy();
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function getHeight(): int
    {
        return $this->_adapter->getHeight();
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function getWidth(): int
    {
        return $this->_adapter->getWidth();
    }

    /**
     * Returns the color of the pixel, with each channel capped at Color Thief's white threshold.
     *
     * @param int $x
     * @param int $y
     *
     * @return \stdClass
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function getPixelColor(int $x, int $y): \stdClass
    {
        $color = $this->_adapter->getPixelColor($x, $y);

        $color->red = min($color->red, ColorThief::THRESHOLD_WHITE);
        $color->green = min($color->green, ColorThief::THRESHOLD_WHITE);
        $color->blue = min($color->blue, ColorThief::THRESHOLD_WHITE);

        return $color;
    }

    /**
     * @inheritdoc
     *
     * @author    André Elvan
     * @since     6.2.0
     */
    public function getResource()
    {
        return $this->_adapter->getResource();
    }
}
