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

class PosterizeEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage        $imageInstance
     * @param array|string|int|float|bool|null $params
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();
            
            if (\is_array($params) && \count($params) === 2) {
                $imagickInstance->posterizeImage($params[0], /** @scrutinizer ignore-type */ ImagerService::$ditherKeyTranslate[$params[1]] ?? \Imagick::DITHERMETHOD_NO);
            }
        }
    }
}
