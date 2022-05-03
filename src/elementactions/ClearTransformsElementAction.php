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
        try {
            foreach ($query->all() as $asset) {
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
