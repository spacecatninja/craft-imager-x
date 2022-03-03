<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\elementactions;

use spacecatninja\imagerx\ImagerX;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\base\ElementAction;

use spacecatninja\imagerx\ImagerX as Plugin;

/**
 *
 * @property-read string $triggerLabel
 */
class ImgixPurgeElementAction extends ElementAction
{

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('imager-x', 'Purge from Imgix');
    }

    /**
     * Purges selected image Assets from Imgix
     *
     * @param ElementQueryInterface $query
     *
     * @return bool
     */
    public function performAction(ElementQueryInterface $query): bool
    {

        $imagesToPurge = $query->kind('image')->all();

        if (empty($imagesToPurge)) {
            $this->setMessage(Craft::t('imager-x', 'No images to purge'));
            return true;
        }

        $imagerPlugin = Plugin::$plugin;

        try {
            foreach ($imagesToPurge as $imageToPurge) {
                $imagerPlugin->imgix->purgeAssetFromImgix($imageToPurge);
            }
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage());
            return false;
        }

        $numImagesToPurge = \count($imagesToPurge);
        if ($numImagesToPurge > 1) {
            $this->setMessage(Craft::t('imager-x', 'Purging {count} images from Imgix...', [
                'count' => $numImagesToPurge,
            ]));
            return true;
        }

        $this->setMessage(Craft::t('imager-x', 'Purging image from Imgix...'));
        return true;
    }
}
