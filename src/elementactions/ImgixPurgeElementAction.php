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
     *
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
        } catch (\Throwable $throwable) {
            $this->setMessage($throwable->getMessage());
            return false;
        }

        $numImagesToPurge = is_countable($imagesToPurge) ? \count($imagesToPurge) : 0;
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
