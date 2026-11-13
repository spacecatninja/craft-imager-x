<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use SpaceCatNinja\ImagerX\Services\ImagerService;

class ClearRuntimeCacheCommand extends Command
{
    use CraftCommand;

    #[\Override]
    protected $signature = 'imager-x:clear-caches:runtime-cache';

    #[\Override]
    protected $description = 'Clears the Imager X runtime cache';

    #[\Override]
    protected $aliases = ['imager-x/clear-caches/runtime-cache', 'craft:imager-x:clear-caches:runtime-cache'];

    public function handle(ImagerService $imager): int
    {
        $imager->deleteRemoteImageCaches();

        $this->info('> Imager X runtime cache has been cleared.');

        return self::SUCCESS;
    }
}
