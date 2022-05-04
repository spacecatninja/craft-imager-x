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

class FloodFillPaintEffect implements ImagerEffectsInterface
{
    /**
     * @param GdImage|ImagickImage $imageInstance
     * @param array                $params
     */
    public static function apply($imageInstance, $params)
    {
        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $imageInstance */
            $imagickInstance = $imageInstance->getImagick();

            if (\is_array($params) && \count($params) === 3) {
                try {
                    $searchColor = $params[2];

                    if ($searchColor === 'auto') {
                        $searchColor = $imagickInstance->getImagePixelColor(0, 0);
                    }

                    $imagickInstance->floodFillPaintImage($params[0], Imagick::getQuantum() * $params[1], $searchColor, 0, 0, false);
                } catch (\Throwable $throwable) {
                    \Craft::error('An error occured when trying to apply floodfillpaint effect: ' . $throwable->getMessage(), __METHOD__);
                }
            } else {
                \Craft::error('An incorrect number of parameters were passed to floodfillpaint effect.', __METHOD__);
            }
        }
    }
}
