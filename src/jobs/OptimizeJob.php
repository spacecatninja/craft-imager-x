<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\jobs;

use Craft;

use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\optimizers\ImagerOptimizeInterface;
use spacecatninja\imagerx\services\ImagerService;

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
        if (isset(ImagerService::$optimizers[$this->optimizer])) {
            /** @var ImagerOptimizeInterface $optimizerClass */
            $optimizerClass = ImagerService::$optimizers[$this->optimizer];
            $optimizerClass::optimize($this->filePath, $this->optimizerSettings);
            
            // Clear stat cache to make sure old file size is not cached
            clearstatcache(true, $this->filePath);
            
            $config = ImagerService::getConfig();
    
            if (empty($config->storages)) {
                return;
            }
    
            ImagerX::$plugin->storage->store($this->filePath, true);
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
