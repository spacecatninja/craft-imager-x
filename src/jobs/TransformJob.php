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
use craft\elements\Asset;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

use spacecatninja\imagerx\exceptions\ImagerException;

use spacecatninja\imagerx\ImagerX;
use yii\queue\Queue;

class TransformJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var null|int
     */
    public ?int $assetId = null;
    
    /**
     * @var array
     */
    public array $transforms = [];
    

    // Public Methods
    // =========================================================================

    /**
     * @param QueueInterface|Queue $queue
     * @throws ImagerException
     */
    public function execute($queue): void
    {
        $criteria = [];
        if ($this->assetId === null) {
            throw new ImagerException(Craft::t('imager-x', 'Asset ID in transform job was null'));
        }
        
        $query = Asset::find();
        $criteria['id'] = $this->assetId;
        $criteria['status'] = null;
        Craft::configure($query, $criteria);
        
        $asset = $query->one();
        
        if (!$asset) {
            throw new ImagerException(Craft::t('imager-x', 'Asset with ID ' . $this->assetId . ' in transform job was not found.'));
        }
        
        ImagerX::$plugin->generate->generateTransformsForAsset($asset, $this->transforms);
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
        return Craft::t('imager-x', 'Transforming asset');
    }
}
