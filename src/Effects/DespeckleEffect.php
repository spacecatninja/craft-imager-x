<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Effects;

use Imagine\Gd\Image as GdImage;
use Imagine\Imagick\Image as ImagickImage;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class DespeckleEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage        $imageInstance
     * @param array|string|int|float|null $params
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();
            $imagickInstance->despeckleImage();
        }
    }
}
