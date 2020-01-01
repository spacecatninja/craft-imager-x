<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\optimizers;

use Craft;
use ImageOptim\API;

class ImageoptimOptimizer implements ImagerOptimizeInterface
{

    public static function optimize(string $file, array $settings)
    {
        $api = new API($settings['apiUsername']);
        $image = $api->imageFromPath($file);
        $image->quality($settings['quality'] ?? 'medium');
        $imageData = $image->getBytes();
        file_put_contents($file, $imageData);
    }
}
