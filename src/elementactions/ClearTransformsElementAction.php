<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\elementactions;

use Craft;

use craft\base\ElementAction;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use spacecatninja\imagerx\ImagerX as Plugin;

use yii\base\Exception;

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
        return Craft::t('imager-x', 'Clear Imager transforms');
    }

    /**
     * Clears transforms for selected assets
     *
     *
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $elementsService = Craft::$app->getElements();
        $user = Craft::$app->getUser()->getIdentity();

        try {
            foreach ($query->all() as $asset) {
                // Only act on assets the current user can view
                if (!$asset instanceof Asset || !$elementsService->canView($asset, $user)) {
                    continue;
                }

                Plugin::$plugin->imagerx->removeTransformsForAsset($asset);
            }
        } catch (Exception $exception) {
            $this->setMessage($exception->getMessage());
            return false;
        }
        
        $this->setMessage(Craft::t('imager-x', 'Transforms were removed'));
        return true;
    }
}
