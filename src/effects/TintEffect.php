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
use spacecatninja\imagerx\services\ImagerService;

class TintEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage        $imageInstance
     * @param array|string|int|float|null $params
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick' && \is_array($params)) {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();
            $tint = new \ImagickPixel($params[0]);
            $opacity = new \ImagickPixel($params[1]);
            $imagickInstance->tintImage($tint, $opacity);
        }
    }
}
