<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Jobs;

use CraftCms\Cms\Queue\Job;
use CraftCms\Cms\Support\Facades\I18N;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Optimizers\ImagerOptimizeInterface;
use SpaceCatNinja\ImagerX\Optimizers\Optimizers;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Services\StorageService;

/**
 * Optimizes a transformed image, and uploads it to the configured external storages.
 */
class OptimizeJob extends Job
{
    public function __construct(
        public string $optimizer,
        public array $optimizerSettings,
        public string $filePath,
        protected ?string $description = null,
    ) {
        parent::__construct();
    }

    /**
     * @throws ImagerException
     */
    public function handle(Optimizers $optimizers, StorageService $storage): void
    {
        /** @var class-string<ImagerOptimizeInterface>|null $optimizerClass */
        $optimizerClass = $optimizers->get($this->optimizer);

        if ($optimizerClass === null) {
            return;
        }

        $optimizerClass::optimize($this->filePath, $this->optimizerSettings);

        // Clear stat cache to make sure old file size is not cached
        clearstatcache(true, $this->filePath);

        if (empty(ImagerService::getConfig()->storages)) {
            return;
        }

        $storage->store($this->filePath, true);
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        return I18N::prep('Optimizing images', category: 'imager-x');
    }
}
