<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\events;

use yii\base\Event;
use craft\elements\Asset;
use spacecatninja\imagerx\models\TransformedImageInterface;

class TransformImageEvent extends Event
{
    // Properties
    // =========================================================================

    /**
     * @var string|Asset Image being transformed
     */
    public string|Asset $image;

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