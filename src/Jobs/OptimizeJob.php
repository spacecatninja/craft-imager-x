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

use SpaceCatNinja\ImagerX\Optimizers\Optimizers;
use SpaceCatNinja\ImagerX\Services\StorageService;
use Craft;

use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Optimizers\ImagerOptimizeInterface;
use SpaceCatNinja\ImagerX\Services\ImagerService;

use yii\queue\Queue;

class OptimizeJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $optimizer = '';
    
    /**
     * @var array
     */
    public array $optimizerSettings = [];
    
    /**
     * @var string
     */
    public string $filePath = '';


    // Public Methods
    // =========================================================================

    /**
     * @param QueueInterface|Queue $queue
     * @throws ImagerException
     */
    public function execute($queue): void
    {
        /** @var class-string<ImagerOptimizeInterface>|null $optimizerClass */
        $optimizerClass = app(Optimizers::class)->get($this->optimizer);

        if ($optimizerClass !== null) {
            $optimizerClass::optimize($this->filePath, $this->optimizerSettings);
            
            // Clear stat cache to make sure old file size is not cached
            clearstatcache(true, $this->filePath);
            
            $config = ImagerService::getConfig();
    
            if (empty($config->storages)) {
                return;
            }
    
            app(StorageService::class)->store($this->filePath, true);
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a default description for [[getDescription()]], if [[description]] isn’t set.
     *
     * @return string|null The default task description
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('imager-x', 'Optimizing images');
    }
}
