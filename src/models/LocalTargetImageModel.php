<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\models;

use spacecatninja\imagerx\helpers\FileHelper;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\services\ImagerService;

/**
 * LocalTargetImageModel
 *
 * Represents the target image for a file that need to be stored locally.
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class LocalTargetImageModel
{
    public string $path = '';

    public string $url = '';

    public string $filename = '';

    public string $extension = '';

    public bool $isNew = false;

    /**
     * LocalTargetImageModel constructor
     *
     * @param LocalSourceImageModel $source
     * @param array                 $transform
     *
     * @throws ImagerException
     */
    public function __construct(LocalSourceImageModel $source, array $transform)
    {
        $config = ImagerService::getConfig();

        $this->filename = $this->createTargetFilename($source, $transform);
        $this->path = FileHelper::normalizePath($config->imagerSystemPath.'/'.$source->transformPath);
        $this->url = ImagerHelpers::stripTrailingSlash($config->imagerUrl).FileHelper::normalizePath($source->transformPath.'/'.$this->filename, '/');
    }

    /**
     * Get file path
     */
    public function getFilePath(): string
    {
        return FileHelper::normalizePath($this->path.'/'.$this->filename);
    }

    /**
     * Creates target filename base on source and transform
     *
     * @param LocalSourceImageModel $source
     * @param array                 $transform
     *
     * @return string
     * @throws ImagerException
     */
    private function createTargetFilename(LocalSourceImageModel $source, array $transform): string
    {
        $config = ImagerService::getConfig();

        $useFilenamePattern = $config->getSetting('useFilenamePattern', $transform);
        $hashFilename = $config->getSetting('hashFilename', $transform);
        $shortHashLength = $config->getSetting('shortHashLength', $transform);

        $basename = FileHelper::truncateBasename($source->basename);
        $extension = $this->getSourceExtension($source->extension);

        if (isset($transform['format'])) {
            $extension = $transform['format'];
            unset($transform['format']);
        }

        if ($extension === '') {
            $source->getLocalCopy();

            try {
                $extension = FileHelper::getExtensionByMimeType(FileHelper::getMimeType($source->path.'/'.$source->filename) ?? '') ?? '';
            } catch (\Throwable) {
                // just continue, we can handle it
            }
        }

        $this->extension = $extension;

        $transformFileString = ImagerHelpers::createTransformFilestring($transform).$config->getConfigOverrideString();

        // If $useFilenamePattern is false, use old behavior with hashFilename config setting.
        if (!$useFilenamePattern) {
            if ($hashFilename) {
                if ($hashFilename === 'postfix') {
                    return $basename.'_'.md5($transformFileString).'.'.$extension;
                }

                return md5($basename.$transformFileString).'.'.$extension;
            }

            return $basename.$transformFileString.'.'.$extension;
        }

        // New behavior, uses filenamePattern config setting. Much joy.
        $transformFileString = ltrim($transformFileString, '_');
        $fullname = $basename.'_'.$transformFileString;

        $patternFilename = $config->getSetting('filenamePattern', $transform);
        
        $patternFilename = mb_ereg_replace('{extension}', $extension, $patternFilename);
        $patternFilename = mb_ereg_replace('{basename}', $basename, $patternFilename);
        $patternFilename = mb_ereg_replace('{fullname}', $fullname, $patternFilename);
        $patternFilename = mb_ereg_replace('{transformString}', $transformFileString, $patternFilename);
        $patternFilename = mb_ereg_replace('{transformName}', ImagerService::$processingNamedTransform, $patternFilename);

        $patternFilename = mb_ereg_replace('{basename\|hash}', md5($basename), $patternFilename);
        $patternFilename = mb_ereg_replace('{fullname\|hash}', md5($fullname), $patternFilename);
        $patternFilename = mb_ereg_replace('{transformString\|hash}', md5($transformFileString), $patternFilename);

        $patternFilename = mb_ereg_replace('{basename\|shorthash}', substr(md5($basename), 0, $shortHashLength), $patternFilename);
        $patternFilename = mb_ereg_replace('{fullname\|shorthash}', substr(md5($fullname), 0, $shortHashLength), $patternFilename);
        $patternFilename = mb_ereg_replace('{transformString\|shorthash}', substr(md5($transformFileString), 0, $shortHashLength), $patternFilename);

        if (str_contains($patternFilename, '{timestamp}')) {
            $source->getLocalCopy();
            $ts = filemtime($source->getFilePath());
            $patternFilename = mb_ereg_replace('{timestamp}', $ts !== false ? $ts : '', $patternFilename);
        }

        return rtrim($patternFilename, '.');
    }
    
    private function getSourceExtension($extension): ?string
    {
        if (ImagerService::$imageDriver === 'imagick') {
            return $extension;
        }

        $supported_types = imagetypes();

        // Array of GD image types and their corresponding file extensions
        $image_types = [
            IMG_GIF => 'gif',
            IMG_JPG => 'jpg',
            IMG_PNG => 'png',
            IMG_WBMP => 'wbmp',
            IMG_XPM => 'xpm',
            IMG_WEBP => 'webp',
            IMG_BMP => 'bmp'
        ];
        
        if (version_compare(phpversion(), '8.1.0', '>')) {
            $image_types[IMG_AVIF] = 'avif';
        }

        $supportedExtensions = [];
        
        foreach ($image_types as $const => $ext) {
            if ($supported_types & $const) {
                $supportedExtensions[] = $ext;
                
                if ($ext === 'jpg') {
                    $supportedExtensions[]  = 'jpeg';
                }
            }
        }
        
        return in_array(strtolower($extension), $supportedExtensions, true) ? $extension : 'jpg';
    }

}
