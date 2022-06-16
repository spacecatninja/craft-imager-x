<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\adapters;

interface ImagerAdapterInterface
{
    /**
     * Returns the full path, with filename, to a rasterized image that Imager can transform  
     *
     * @return string 
     */
    public function getPath(): string;

    /**
     * Returns the path, without filename, to where Imager should put the file inside the imagerSystemPath.
     * Make sure this is a unique path that will not result in collisions.
     * When using LocalSourceImageModel to do local file handling, $localSourceModel->transformPath can be
     * used to automatically tap into Imager's path handling, enabling cache breaking and other built-in
     * features.
     *
     * @return string 
     */
    public function getTransformPath(): string;
}
