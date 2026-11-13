<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Events;

use yii\base\Event;
use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;

class TransformImageEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var string|ImagerAdapterInterface|Asset Image being transformed
     */
    public string|ImagerAdapterInterface|Asset $image;

    /**
     * @var array Transform params given to Imager-X (normalized)
     */
    public array $transforms = [];

    /**
     * @var bool Whether the image transform operation should be considered valid
     *  and Imager-X should proceed
     */
    public bool $isValid = true;

    /**
     * @var TransformedImageInterface|TransformedImageInterface[]|null Resulting (list of) transformed images
     */
    public array|TransformedImageInterface|null $transformedImages = null;
}
