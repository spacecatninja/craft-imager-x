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

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;

/**
 * Dispatched before an image is transformed.
 *
 * Set `$isValid` to `false` to cancel the transform, or set `$transformedImages` to provide the
 * transformed images yourself (Imager X Pro only).
 */
class TransformingImage
{
    use ValidatableEvent;

    /**
     * @param array $transforms The normalized transform parameters
     * @param TransformedImageInterface[]|null $transformedImages Transformed images to use instead of transforming the image
     */
    public function __construct(
        public readonly string|ImagerAdapterInterface|Asset $image,
        public readonly array $transforms,
        public ?array $transformedImages = null,
    ) {
    }
}
