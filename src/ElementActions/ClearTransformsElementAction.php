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

use Illuminate\Support\Facades\Gate;
use SpaceCatNinja\ImagerX\Services\ImagerService;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\ImagerX\ImagerX as Plugin;


use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

/**
 *
 * @property-read string $triggerLabel
 */
class ClearTransformsElementAction extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return t('Clear Imager transforms', category: 'imager-x');
    }

    /**
     * Clears transforms for selected assets
     *
     *
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $user = currentUser();

        try {
            foreach ($query->all() as $asset) {
                // Only act on assets the current user can view
                if (!$asset instanceof Asset || $user === null || !Gate::forUser($user)->check('view', $asset)) {
                    continue;
                }

                app(ImagerService::class)->removeTransformsForAsset($asset);
            }
        } catch (\Throwable $exception) {
            $this->setMessage($exception->getMessage());
            return false;
        }
        
        $this->setMessage(t('Transforms were removed', category: 'imager-x'));
        return true;
    }
}
