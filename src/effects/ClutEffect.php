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

class ClutEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage $imageInstance
     * @param array|string|int|float|bool|null $params
     * @throws \ImagickException
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();
            
            if (\is_string($params)) {
                $clut = new \Imagick();
                $clut->newPseudoImage(1, 255, $params);
                $imagickInstance->clutImage($clut);
            }
        }
    }
}
