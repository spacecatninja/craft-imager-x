<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Optimizers;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Support\HandleRegistry;

/**
 * Registry of the optimizers that can be enabled with the `optimizers` config setting, keyed by handle.
 *
 * @extends HandleRegistry<ImagerOptimizeInterface>
 */
#[Singleton]
class Optimizers extends HandleRegistry
{
    protected const ?string CONTRACT = ImagerOptimizeInterface::class;

    protected const array DEFAULT_TYPES = [
        'jpegoptim' => JpegoptimOptimizer::class,
        'jpegtran' => JpegtranOptimizer::class,
        'mozjpeg' => MozjpegOptimizer::class,
        'optipng' => OptipngOptimizer::class,
        'pngquant' => PngquantOptimizer::class,
        'gifsicle' => GifsicleOptimizer::class,
    ];
}
