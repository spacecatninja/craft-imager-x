<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\ElementActions;

use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use Illuminate\Support\Facades\Gate;
use SpaceCatNinja\ImagerX\Services\GenerateService;
use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Queries\AssetQuery;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use SpaceCatNinja\ImagerX\ImagerX;

use SpaceCatNinja\ImagerX\Services\ImagerService;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 *
 * @property-read string $triggerLabel
 */
class GenerateTransformsAction extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return t('Generate transforms', category: 'imager-x');
    }

    /**
     * Generates transforms
     *
     *
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        if (!($query instanceof AssetQuery)) {
            $this->setMessage(t('Invalid query, this action only works on assets', category: 'imager-x'));
            return false;
        }
        
        $user = currentUser();

        // Only act on assets the current user can view. This is done first, so that the
        // response doesn't say anything about other assets.
        $assetsToGenerate = array_filter(
            $query->kind('image')->all(),
            static fn($asset) => $asset instanceof Asset && $user !== null && Gate::forUser($user)->check('view', $asset)
        );

        if (empty($assetsToGenerate)) {
            $this->setMessage(t('No images to transform', category: 'imager-x'));
            return true;
        }

        $generateConfig = app(GenerateSettings::class)->volumes;
        
        if (empty($generateConfig)) {
            $this->setMessage(t('No generate transforms configured', category: 'imager-x'));
            return false;
        }
        

        try {
            /** @var Asset $asset */
            foreach ($assetsToGenerate as $asset) {
                $volumeHandle = $asset->getVolume()->handle;
                $transforms = $generateConfig[$volumeHandle] ?? null;
                
                if ($transforms) {
                    app(GenerateService::class)->createTransformJob($asset, $transforms);
                }
            }
        } catch (\Throwable $throwable) {
            $this->setMessage($throwable->getMessage());
            return false;
        }

        $this->setMessage(t('Generating asset transforms...', category: 'imager-x'));
        return true;
    }
}
