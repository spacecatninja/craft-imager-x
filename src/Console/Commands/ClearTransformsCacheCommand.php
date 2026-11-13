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

class ClearTransformsCacheCommand extends Command
{
    use CraftCommand;

    #[\Override]
    protected $signature = 'imager-x:clear-caches:transforms-cache';

    #[\Override]
    protected $description = 'Clears the Imager X transforms cache';

    #[\Override]
    protected $aliases = ['imager-x/clear-caches/transforms-cache', 'craft:imager-x:clear-caches:transforms-cache'];

    public function handle(ImagerService $imager): int
    {
        $imager->deleteImageTransformCaches();

        $this->info('> Imager X transforms cache has been cleared.');

        return self::SUCCESS;
    }
}
