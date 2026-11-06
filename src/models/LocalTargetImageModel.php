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

use Craft;
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
    /**
     * File extensions a transformed image can be saved with, in addition to the keys of the
     * customEncoders config setting. Keeps active file types (php, html, svg, etc) out of the
     * public transform path, regardless of image driver and of where the extension came from.
     */
    public const SAFE_TARGET_EXTENSIONS = ['jpg', 'jpeg', 'jpe', 'jfif', 'jp2', 'png', 'gif', 'webp', 'avif', 'jxl', 'heic', 'heif', 'tif', 'tiff', 'bmp', 'wbmp', 'xpm', 'ico'];

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

        // The transform path is derived from the image reference, make sure the target never leaves the
        // imager system path. Checked before the filename is created, since that may download the source.
        $this->path = FileHelper::normalizePath($config->imagerSystemPath.'/'.$source->transformPath);
        $this->assertWithinBase($this->path, $config->imagerSystemPath);

        $this->filename = $this->createTargetFilename($source, $transform);

        if ($this->filename === '') {
            throw new ImagerException(Craft::t('imager-x', 'Could not create a filename for the transformed image.'));
        }

        $this->assertWithinBase($this->getFilePath(), $this->path);

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
     * Checks if an extension is one that transformed images can be saved with.
     */
    public static function isSafeTargetExtension(string $extension): bool
    {
        $extension = mb_strtolower($extension);

        if (\in_array($extension, self::SAFE_TARGET_EXTENSIONS, true)) {
            return true;
        }

        // Custom encoders are configured by the site, read them from config and never from a transform
        $customEncoders = array_map(static fn($key) => mb_strtolower((string)$key), array_keys(ImagerService::getConfig()->customEncoders));

        return \in_array($extension, $customEncoders, true);
    }

    /**
     * Throws if path is not inside base
     *
     * @throws ImagerException
     */
    private function assertWithinBase(string $path, string $base): void
    {
        if (!FileHelper::isWithinBase($path, $base)) {
            $msg = Craft::t('imager-x', 'Refusing to resolve an image path outside of the allowed base directory.');
            Craft::error($msg.' Path: "'.FileHelper::normalizePath($path).'", base: "'.FileHelper::normalizePath($base).'".', __METHOD__);
            throw new ImagerException($msg);
        }
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

            // The format is used as the file extension, only allow formats that we can save to. An empty
            // format is ignored, and the extension is detected from the file below.
            if ($extension !== '' && (!\is_string($extension) || !self::isSafeTargetExtension($extension))) {
                $msg = Craft::t('imager-x', 'Format “{format}” is not a supported transform format.', ['format' => \is_string($extension) ? $extension : \gettype($extension)]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }

        if ($extension === '') {
            $source->getLocalCopy();

            try {
                $extension = FileHelper::getExtensionByMimeType(FileHelper::getMimeType($source->path.'/'.$source->filename) ?? '') ?? '';
            } catch (\Throwable) {
                // just continue, we can handle it
            }
        }

        // The source extension is used verbatim with Imagick, and may come from an untrusted url.
        // Anything that isn't an image format we can save to is saved as jpg.
        if ($extension !== '' && !self::isSafeTargetExtension($extension)) {
            $extension = 'jpg';
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
            IMG_BMP => 'bmp',
            IMG_AVIF => 'avif'
        ];

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
