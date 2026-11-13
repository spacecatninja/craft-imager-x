<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Transformers;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Support\HandleRegistry;

/**
 * Registry of the transformers that can be selected with the `transformer` config setting, keyed by handle.
 *
 * Transformers other than `craft` are only available in Imager X Pro.
 *
 * @extends HandleRegistry<TransformerInterface>
 */
#[Singleton]
class Transformers extends HandleRegistry
{
    protected const ?string CONTRACT = TransformerInterface::class;

    protected const array DEFAULT_TYPES = [
        'craft' => CraftTransformer::class,
    ];
}
