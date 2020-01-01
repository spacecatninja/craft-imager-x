<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\transformers;

use spacecatninja\imagerx\exceptions\ImagerException;
use craft\elements\Asset;

interface TransformerInterface
{
    /**
     * @param Asset|string $image
     * @param array        $transforms
     *
     * @return array|null
     *
     * @throws ImagerException
     */
    public function transform($image, $transforms);

}
