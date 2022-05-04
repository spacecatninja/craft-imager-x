<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\transformers;

use craft\elements\Asset;
use spacecatninja\imagerx\exceptions\ImagerException;

interface TransformerInterface
{
    /**
     *
     * @throws ImagerException
     */
    public function transform(Asset|string $image, array $transforms): ?array;
}
