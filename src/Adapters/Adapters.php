<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Adapters;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Support\HandleRegistry;

/**
 * Registry of the adapters that turn other file types into transformable images, keyed by file extension.
 *
 * @extends HandleRegistry<ImagerAdapterInterface>
 */
#[Singleton]
class Adapters extends HandleRegistry
{
    protected const ?string CONTRACT = ImagerAdapterInterface::class;
}
