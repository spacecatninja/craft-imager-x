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

class ClearCachesCommand extends Command
{
    use CraftCommand;

    #[\Override]
    protected $signature = 'imager-x:clear-caches:all';

    #[\Override]
    protected $description = 'Clears all Imager X caches';

    #[\Override]
    protected $aliases = ['imager-x/clear-caches/all', 'craft:imager-x:clear-caches:all'];

    public function handle(ImagerService $imager): int
    {
        $imager->deleteImageTransformCaches();
        $imager->deleteRemoteImageCaches();

        $this->info('> Imager X transforms and runtime cache has been cleared.');

        return self::SUCCESS;
    }
}
