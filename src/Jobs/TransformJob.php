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

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Queue\Job;
use CraftCms\Cms\Support\Facades\I18N;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Services\GenerateService;

/**
 * Generates transforms for an asset.
 */
class TransformJob extends Job
{
    public function __construct(
        public ?int $assetId,
        public array $transforms = [],
        public bool $force = false,
        protected ?string $description = null,
    ) {
        parent::__construct();
    }

    /**
     * @throws ImagerException
     */
    public function handle(GenerateService $generate): void
    {
        if ($this->assetId === null) {
            throw new ImagerException('Asset ID in transform job was null');
        }

        $asset = Asset::find()->id($this->assetId)->status(null)->one();

        if (!$asset instanceof Asset) {
            return;
        }

        $generate->generateTransformsForAsset($asset, $this->transforms, $this->force);
    }

    #[\Override]
    protected function defaultDescription(): string
    {
        return I18N::prep('Transforming asset', category: 'imager-x');
    }
}
