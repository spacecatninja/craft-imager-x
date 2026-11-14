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
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;

/**
 * Dispatched after an image has been transformed.
 */
class ImageTransformed
{
    /**
     * @param array $transforms The normalized transform parameters
     * @param TransformedImageInterface[]|null $transformedImages The transformed images, or `null` if the transform failed
     */
    public function __construct(
        public readonly string|ImagerAdapterInterface|Asset $image,
        public readonly array $transforms,
        public readonly ?array $transformedImages,
    ) {
    }
}
