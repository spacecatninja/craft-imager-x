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
    public const BASENAME_MAX_LENGTH = 200;

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

    public static function truncateBasename(string $basename): string
    {
        if (strlen($basename) <= self::BASENAME_MAX_LENGTH) {
            return $basename;
        }

        $keep = substr($basename, 0, self::BASENAME_MAX_LENGTH - 11);
        $hash = md5($basename);

        return $keep.'_'.substr($hash, 0, 10);
    }
}
