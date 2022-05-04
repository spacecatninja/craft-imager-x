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
use Imagine\Imagick\Imagick;
use spacecatninja\imagerx\services\ImagerService;

class OpacityEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage $imageInstance
     * @param array|string|int|float|null $params
     * @throws \ImagickException
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();
            
            if (\is_array($params)) {
                if (\count($params) > 1) {
                    self::opacity($imagickInstance, $params[0], $params[1]);
                } else {
                    self::opacity($imagickInstance, $params[0]);
                }
            } else {
                self::opacity($imagickInstance, $params);
            }
        }
    }

    /**
     * Opacity
     *
     * If 'transparent' is passed as the background color, the effect doesn't produce any
     * visible effect on a non-transparent image.
     *
     * @param Imagick|\Imagick $imagickInstance
     * @param int|float $alpha
     * @param string $color
     * @throws \ImagickException
     */
    private static function opacity(Imagick|\Imagick $imagickInstance, float|int $alpha = 1, $color = '#fff')
    {
        $width = $imagickInstance->getImageWidth();
        $height = $imagickInstance->getImageHeight();

        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel($color));
        $draw->rectangle(0, 0, $width, $height);

        $temporary = new \Imagick();
        $temporary->setBackgroundColor(new \ImagickPixel('transparent'));
        $temporary->newImage($width, $height, new \ImagickPixel('transparent'));
        $temporary->setImageFormat('png32');
        $temporary->drawImage($draw);
        
        $clone = clone $imagickInstance;
        
        if (method_exists($clone, 'setImageAlpha')) { // ImageMagick >= 7
            $clone->setImageAlpha($alpha);
        } else {
            $clone->setImageOpacity($alpha);
        }
        
        if (defined('\Imagick::ALPHACHANNEL_OFF')) { // ImageMagick >= 7
            $imagickInstance->setImageAlphaChannel(Imagick::ALPHACHANNEL_OFF);
        } else {
            $imagickInstance->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        }
        
        $imagickInstance->compositeImage($temporary, Imagick::COMPOSITE_REPLACE, 0, 0);
        $imagickInstance->compositeImage($clone, Imagick::COMPOSITE_DEFAULT, 0, 0);
    }
}
