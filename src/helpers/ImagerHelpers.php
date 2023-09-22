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

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;
use craft\helpers\FileHelper;
use craft\models\ImageTransform;
use craft\models\Volume;
use Imagine\Exception\InvalidArgumentException;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Image as ImagickImage;
use spacecatninja\imagerx\exceptions\ImagerException;

use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\services\ImagerService;

use yii\base\InvalidConfigException;

class ImagerHelpers
{

    /**
     * Creates the Imagine instance depending on the chosen image driver.
     */
    public static function createImagineInstance(): \Imagine\Imagick\Imagine|Imagine|null
    {
        try {
            if (ImagerService::$imageDriver === 'gd') {
                return new Imagine();
            }

            if (ImagerService::$imageDriver === 'imagick') {
                return new \Imagine\Imagick\Imagine();
            }
        } catch (\Throwable) {
            // just ignore for now
        }

        return null;
    }

    public static function shouldCreateTransform(LocalTargetImageModel $targetModel, array $transform): bool
    {
        $config = ImagerService::getConfig();

        return !$config->getSetting('cacheEnabled', $transform) ||
            !file_exists($targetModel->getFilePath()) ||
            (($config->getSetting('cacheDuration', $transform) !== false) && (FileHelper::lastModifiedTime($targetModel->getFilePath()) + $config->getSetting('cacheDuration', $transform) < time()));
    }

    /**
     * Creates the destination crop size box
     *
     * @throws InvalidArgumentException
     */
    public static function getCropSize(BoxInterface $originalSize, array $transform, bool $allowUpscale, bool $usePadding = true): Box
    {
        $width = $originalSize->getWidth();
        $height = $originalSize->getHeight();
        $padding = $transform['pad'] ?? [0, 0, 0, 0];

        if ($usePadding) {
            $padWidth = $padding[1] + $padding[3];
            $padHeight = $padding[0] + $padding[2];
        } else {
            $padWidth = 0;
            $padHeight = 0;
        }

        $aspect = $width / $height;

        if (isset($transform['width'], $transform['height'])) {
            $width = (int)$transform['width'];
            $height = (int)$transform['height'];
        } elseif (isset($transform['width'])) {
            $width = (int)$transform['width'];
            $height = (int)floor((int)$transform['width'] / $aspect);
        } elseif (isset($transform['height'])) {
            $width = (int)floor((int)$transform['height'] * $aspect);
            $height = (int)$transform['height'];
        }

        // check if we want to upscale. If not, adjust the transform here
        if (!$allowUpscale) {
            [$width, $height] = self::enforceMaxSize($width, $height, $originalSize, true);
        }

        $width -= $padWidth;
        $height -= $padHeight;

        // ensure that size is larger than 0
        if ($width <= 0) {
            $width = 1;
        }

        if ($height <= 0) {
            $height = 1;
        }

        return new Box((int)$width, (int)$height);
    }

    /**
     * Creates the resize size box
     *
     * @throws ImagerException
     */
    public static function getResizeSize(BoxInterface $originalSize, array $transform, bool $allowUpscale, bool $usePadding = true): Box
    {
        $width = $originalSize->getWidth();
        $height = $originalSize->getHeight();
        $padding = $transform['pad'] ?? [0, 0, 0, 0];
        $aspect = $width / $height;

        if ($usePadding) {
            $padWidth = $padding[1] + $padding[3];
            $padHeight = $padding[0] + $padding[2];
        } else {
            $padWidth = 0;
            $padHeight = 0;
        }

        $mode = $transform['mode'] ?? 'crop';

        if ($mode === 'crop' || $mode === 'fit' || $mode === 'letterbox') {
            if (isset($transform['width'], $transform['height'])) {
                $transformAspect = (int)$transform['width'] / (int)$transform['height'];
                if ($mode === 'crop') {
                    $cropZoomFactor = self::getCropZoomFactor($transform);
                    if ($transformAspect < $aspect) { // use height as guide
                        $height = (int)$transform['height'] * $cropZoomFactor;
                        $width = ceil($originalSize->getWidth() * ($height / $originalSize->getHeight()));
                    } else { // use width
                        $width = (int)$transform['width'] * $cropZoomFactor;
                        $height = ceil($originalSize->getHeight() * ($width / $originalSize->getWidth()));
                    }
                } elseif ($transformAspect === $aspect) {
                    // exactly the same, use original just to make sure no rounding errors happen
                    $height = (int)$transform['height'];
                    $width = (int)$transform['width'];
                } elseif ($transformAspect > $aspect) {
                    // use height as guide
                    $height = (int)$transform['height'];
                    $width = ceil($originalSize->getWidth() * ($height / $originalSize->getHeight()));
                } else { // use width
                    $width = (int)$transform['width'];
                    $height = ceil($originalSize->getHeight() * ($width / $originalSize->getWidth()));
                }
            } elseif (isset($transform['width'])) {
                $width = (int)$transform['width'];
                $height = ceil($width / $aspect);
            } elseif (isset($transform['height'])) {
                $height = (int)$transform['height'];
                $width = ceil($height * $aspect);
            }
        } elseif ($mode === 'croponly') {
            $width = $originalSize->getWidth();
            $height = $originalSize->getHeight();
        } elseif ($mode === 'stretch') {
            $width = (int)$transform['width'];
            $height = (int)$transform['height'];
        }

        // check if we want to upscale. If not, adjust the transform here
        if (!$allowUpscale) {
            [$width, $height] = self::enforceMaxSize((int)$width, (int)$height, $originalSize, false, self::getCropZoomFactor($transform));
        }

        $width -= $padWidth;
        $height -= $padHeight;

        try {
            $box = new Box((int)$width, (int)$height);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Craft::error($invalidArgumentException->getMessage(), __METHOD__);
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        return $box;
    }

    /**
     * @param LocalSourceImageModel $source
     *
     * @return array
     */
    public static function getSourceImageSize(LocalSourceImageModel $source): array
    {
        // Try getimagesize first
        $sourceImageInfo = @getimagesize($source->getFilePath());

        if ($sourceImageInfo && $sourceImageInfo[0] !== 0 && $sourceImageInfo[1] !== 0) {
            return array_slice($sourceImageInfo, 0, 2);
        }

        // Check if we can get it from the source asset, if it is one
        if ($source->asset !== null) {
            return [$source->asset->width, $source->asset->height];
        }

        // We need to open it (oh no). Let's cache the result.
        $cache = Craft::$app->getCache();
        $key = 'imagerx-sourceimage-size-'.md5($source->getFilePath());

        $cachedSizeData = $cache?->getOrSet($key, static function() use ($source) {
            $imagineInstance = self::createImagineInstance();

            if ($imagineInstance) {
                $imageInstance = $imagineInstance->open($source->getFilePath());
                $size = $imageInstance->getSize();

                return [$size->getWidth(), $size->getHeight()];
            }

            return null;
        });

        return $cachedSizeData ?? [0, 0];
    }


    /**
     * Enforces a max size if allowUpscale is false
     */
    public static function enforceMaxSize(int $width, int $height, BoxInterface $originalSize, bool $maintainAspect, float $zoomFactor = 1.0): array
    {
        $adjustedWidth = $width;
        $adjustedHeight = $height;

        if ($adjustedWidth > $originalSize->getWidth() * $zoomFactor) {
            $adjustedWidth = floor($originalSize->getWidth() * $zoomFactor);

            if ($maintainAspect) {
                $adjustedHeight = floor($height * ($adjustedWidth / $width));
            }
        }

        if ($adjustedHeight > $originalSize->getHeight() * $zoomFactor) {
            $adjustedHeight = floor($originalSize->getHeight() * $zoomFactor);

            if ($maintainAspect) {
                $adjustedWidth = floor($width * ($adjustedHeight / $height));
            }
        }

        return [$adjustedWidth, $adjustedHeight];
    }

    /**
     * Get the crop zoom factor
     */
    public static function getCropZoomFactor(array $transform): float
    {
        if (isset($transform['cropZoom'])) {
            return (float)$transform['cropZoom'];
        }

        return 1.0;
    }

    /**
     * Gets crop point
     *
     * @throws ImagerException
     */
    public static function getCropPoint(Box $resizeSize, Box $cropSize, string $position): Point
    {
        // Get the offsets, left and top, now as an int, representing the % offset
        [$leftOffset, $topOffset] = explode(' ', $position);

        // Get position that crop should center around
        $leftPos = floor($resizeSize->getWidth() * ($leftOffset / 100)) - floor($cropSize->getWidth() / 2);
        $topPos = floor($resizeSize->getHeight() * ($topOffset / 100)) - floor($cropSize->getHeight() / 2);

        // Make sure the point is within the boundaries and return the point
        try {
            $point = new Point(
                min(max($leftPos, 0), $resizeSize->getWidth() - $cropSize->getWidth()),
                min(max($topPos, 0), $resizeSize->getHeight() - $cropSize->getHeight())
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            Craft::error($invalidArgumentException->getMessage(), __METHOD__);
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        return $point;
    }

    /**
     * Returns the transform path for a given asset.
     *
     * @throws ImagerException
     */
    public static function getTransformPathForAsset(Asset $asset): string
    {
        /** @var Volume $volume */
        try {
            $volume = $asset->getVolume();
        } catch (InvalidConfigException $invalidConfigException) {
            Craft::error($invalidConfigException->getMessage(), __METHOD__);
            throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
        }

        $config = ImagerService::getConfig();

        $hashPath = $config->hashPath;
        $addVolumeToPath = $config->addVolumeToPath;

        if ($hashPath) {
            return FileHelper::normalizePath('/'.md5('/'.($addVolumeToPath ? mb_strtolower($volume->handle).'/' : '').$asset->folderPath.'/').'/'.$asset->getId().'/');
        }

        $path = FileHelper::normalizePath('/'.($addVolumeToPath ? mb_strtolower($volume->handle).'/' : '').$asset->folderPath.'/'.$asset->getId().'/');

        if (str_starts_with($path, '//')) {
            $path = substr($path, 1);
        }

        return $path;
    }

    /**
     * Returns the transform path for a given local path.
     */
    public static function getTransformPathForPath(string $path): string
    {
        $config = ImagerService::getConfig();

        $hashPath = $config->hashPath;
        $pathParts = pathinfo($path);

        if ($hashPath) {
            return FileHelper::normalizePath('/'.md5($pathParts['dirname']));
        }

        return FileHelper::normalizePath($pathParts['dirname']);
    }

    /**
     * Returns the transform path for a given url.
     *
     *
     */
    public static function getTransformPathForUrl(string $url): string
    {
        $config = ImagerService::getConfig();

        $urlParts = parse_url($url);
        $pathParts = pathinfo($urlParts['path']);
        $hashRemoteUrl = $config->getSetting('hashRemoteUrl');
        $hashPath = $config->getSetting('hashPath');
        $shortHashLength = $config->getSetting('shortHashLength');
        $transformPath = $pathParts['dirname'];

        if ($hashPath) {
            $transformPath = '/'.md5($pathParts['dirname']);
        }

        if ($hashRemoteUrl) {
            if ($hashRemoteUrl === 'host') {
                $transformPath = '/'.substr(md5($urlParts['host']), 0, $shortHashLength).$transformPath;
            } else {
                $transformPath = '/'.md5($urlParts['host'].$pathParts['dirname']);
            }
        } else {
            $transformPath = '/'.str_replace('.', '_', $urlParts['host']).$transformPath;
        }

        return FileHelper::normalizePath($transformPath);
    }

    /**
     * Creates additional file string that is appended to filename
     */
    public static function createTransformFilestring(array $transform): string
    {
        $r = '';

        foreach ($transform as $k => $v) {
            if ($k === 'effects' || $k === 'preEffects' || $k === 'transformerParams') {
                $effectString = '';
                foreach ($v as $eff => $param) {
                    if (ArrayHelper::isAssociative($param)) {
                        $effectString .= '_'.$eff.self::encodeTransformObject($param);
                    } elseif (\is_array($param)) {
                        if (\is_array($param[0])) {
                            $effectString .= '_'.$eff;
                            foreach ($param as $paramArr) {
                                $effectString .= '-'.implode('-', $paramArr);
                            }
                        } else {
                            $effectString .= '_'.$eff.'-'.implode('-', $param);
                        }
                    } else {
                        $effectString .= '_'.$eff.'-'.$param;
                    }
                }

                $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).$effectString;
            } elseif ($k === 'watermark') {
                $watermarkString = '';
                foreach ($v as $eff => $param) {
                    if ($eff === 'image') {
                        if ($param instanceof Asset) {
                            $watermarkString .= '-i-'.$param->getId();
                        } else {
                            $watermarkString .= '-i-'.$param;
                        }
                    } elseif ($eff === 'position') {
                        $watermarkString .= '-pos';
                        foreach ($param as $posKey => $posVal) {
                            $watermarkString .= '-'.$posKey.'-'.$posVal;
                        }
                    } else {
                        $watermarkString .= '-'.$eff.'-'.(\is_array($param) ? implode('-', $param) : $param);
                    }
                }

                $watermarkString = substr($watermarkString, 1);
                $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).'_'.mb_substr(md5($watermarkString), 0, 10);
            } elseif ($k === 'webpImagickOptions') {
                $optString = '';

                foreach ($v as $optK => $optV) {
                    $optString .= ($optK.'-'.$optV.'-');
                }

                $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).'_'.mb_substr($optString, 0, -1);
            } else {
                $r .= '_'.(ImagerService::$transformKeyTranslate[$k] ?? $k).(\is_array($v) ? implode('-', $v) : $v);
            }
        }

        return str_replace([' ', '.', ',', '#', '(', ')', '%'], ['-', '-', '-', '', '', '', 'p'], $r);
    }

    /**
     * Converts a native asset transform object into an Imager transform.
     *
     * @param ImageTransform $assetTransform
     *
     * @return array
     */
    public static function normalizeAssetTransformToObject(ImageTransform $assetTransform): array
    {
        $transformArray = $assetTransform->toArray();
        $validParams = ['width', 'height', 'format', 'mode', 'position', 'interlace', 'quality'];

        $r = [];

        foreach ($validParams as $param) {
            if (isset($transformArray[$param])) {
                $r[$param] = $transformArray[$param];
            }
        }

        return $r;
    }

    /**
     * Returns something that can be used as a fallback image for the transform method.
     */
    public static function getTransformableFromConfigSetting(Asset|int|string|null $configValue): Asset|string|null
    {
        $criteria = [];
        if (is_string($configValue) || $configValue instanceof Asset) {
            return $configValue;
        }

        if (is_int($configValue)) {
            /** @var Query $query */
            $query = Asset::find();
            $criteria['id'] = $configValue;
            Craft::configure($query, $criteria);

            return $query->one();
        }

        return null;
    }

    public static function processMetaData(ImagickImage|ImageInterface &$imageInstance, array $transform): void
    {
        if ($imageInstance instanceof ImagickImage) {
            $config = ImagerService::getConfig();
            $imagick = $imageInstance->getImagick();
            $supportsImageProfiles = method_exists($imagick, 'getimageprofiles');
            $iccProfiles = null;

            try {
                if ($config->preserveColorProfiles && $supportsImageProfiles) {
                    $iccProfiles = $imagick->getImageProfiles('icc', true);
                }

                $imagick->stripImage();

                if (!empty($iccProfiles)) {
                    $imagick->profileImage('icc', $iccProfiles['icc'] ?? '');
                }
            } catch (\Throwable $throwable) {
                Craft::error('An error occured when trying to process image meta data: ' . $throwable->getMessage());
            }
        }
    }

    /**
     * Moves a named key in an associative array to a given position
     */
    public static function moveArrayKeyToPos(string $key, int $pos, array $arr): array
    {
        if (!isset($arr[$key])) {
            return $arr;
        }

        $tempValue = $arr[$key];
        unset($arr[$key]);

        if ($pos === 0) {
            return [$key => $tempValue] + $arr;
        }

        if ($pos > \count($arr)) {
            return $arr + [$key => $tempValue];
        }

        $new_arr = [];
        $i = 1;

        foreach ($arr as $arr_key => $arr_value) {
            if ($i === $pos) {
                $new_arr[$key] = $tempValue;
            }

            $new_arr[$arr_key] = $arr_value;
            ++$i;
        }

        return $new_arr;
    }


    /**
     * Fixes slashes in path
     */
    public static function fixSlashes(string $str, bool $removeInitial, bool $removeTrailing): array|string
    {
        $r = str_replace('//', '/', $str);

        if ($r !== '') {
            if ($removeInitial && ($r[0] === '/')) {
                $r = substr($r, 1);
            }

            if ($removeTrailing && ($r[\strlen($r) - 1] === '/')) {
                $r = substr($r, 0, -1);
            }
        }

        return $r;
    }

    /**
     * Strip trailing slash
     *
     * @param string $str
     *
     * @return string
     */
    public static function stripTrailingSlash(string $str): string
    {
        return rtrim($str, '/');
    }

    /**
     * @param array $obj
     *
     * @return string
     */
    public static function encodeTransformObject(array $obj): string
    {
        ksort($obj);
        try {
            $json = json_encode($obj, JSON_THROW_ON_ERROR);
            return mb_strtolower(str_replace(['{', '}', '/', '"', ':', ','], ['-', '', '', '', '-', '_'], $json));
        } catch (\Throwable) {
            
        }
        return '--error--';
    }
}
