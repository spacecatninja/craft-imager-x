<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */


namespace spacecatninja\imagerx\effects;

use Imagine\Gd\Image as GdImage;
use Imagine\Imagick\Image as ImagickImage;

interface ImagerEffectsInterface
{
    /**
     * @param array|string|int|float|bool|null $params
     */
    public static function apply(GdImage|ImagickImage $imageInstance, $params);
}
