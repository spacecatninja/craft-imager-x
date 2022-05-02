<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class FileHelper
 *
 * @author    SPACECATNINJA
 * @since     4.0.0
 */
class FileHelper extends \craft\helpers\FileHelper
{

    public static function filesInPath(string $path): array
    {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }
}
