<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Transformers;

use CraftCms\Cms\Asset\Elements\Asset;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;

interface TransformerInterface
{
    /**
     *
     * @throws ImagerException
     */
    public function transform(Asset|string $image, array $transforms): ?array;
}
