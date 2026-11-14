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
use Imagine\Imagick\Imagick;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use Illuminate\Support\Facades\Log;

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
                    Log::error('An error occured when trying to apply floodfillpaint effect: ' . $throwable->getMessage());
                }
            } else {
                Log::error('An incorrect number of parameters were passed to floodfillpaint effect.');
            }
        }
    }
}
