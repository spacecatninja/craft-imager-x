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
        $path = realpath($path);
        
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

    public static function pathSize(string $path): int
    {
        $bytesTotal = 0;
        $path = realpath($path);
        
        if ($path !== false && $path !== '' && file_exists($path)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $object) {
                try {
                    $bytesTotal += $object->getSize();
                } catch (\Throwable) {
                    // just ignore
                }
            }
        }

        return $bytesTotal;
    }
}
