<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\ExternalStorage;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Support\HandleRegistry;

/**
 * Registry of the external storages that can be enabled with the `storages` config setting, keyed by handle.
 *
 * External storages are only available in Imager X Pro.
 *
 * @extends HandleRegistry<ImagerStorageInterface>
 */
#[Singleton]
class ExternalStorages extends HandleRegistry
{
    protected const ?string CONTRACT = ImagerStorageInterface::class;
}
