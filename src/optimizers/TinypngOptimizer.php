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

use Tinify\Exception;

class TinypngOptimizer implements ImagerOptimizeInterface
{

    public static function optimize(string $file, array $settings)
    {
        try {
            \Tinify\setKey(Craft::parseEnv($settings['apiKey']));
            \Tinify\validate();
            \Tinify\fromFile($file)->toFile($file);
        } catch (Exception $e) {
            Craft::error('Could not validate connection to TinyPNG, image was not optimized.', __METHOD__);
        }
    }
}
