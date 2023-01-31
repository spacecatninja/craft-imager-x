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

use craft\elements\Asset;
use craft\errors\FsException;
use craft\fs\Local;
use craft\helpers\Assets;
use craft\helpers\Assets as AssetsHelper;
use craft\helpers\StringHelper;

use spacecatninja\imagerx\helpers\FileHelper;
use spacecatninja\imagerx\adapters\ImagerAdapterInterface;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\services\ImagerService;

use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * LocalSourceImageModel
 *
 * Represents the source image for a file that need to be stored locally.
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class LocalSourceImageModel
{
    public string $type = 'local';

    public string $path = '';

    public string $transformPath = '';

    public string $url = '';

    public string $filename = '';

    public string $basename = '';

    public string $extension = '';

    public ?Asset $asset = null;

    /**
     * LocalSourceImageModel constructor.
     *
     * @param $image
     *
     * @throws ImagerException
     */
    public function __construct($image)
    {
        $this->init($image);
    }

    /**
     * Init method
     *
     * @param $image
     *
     * @throws ImagerException
     */
    private function init($image): void
    {
        $settings = ImagerService::getConfig();

        if (\is_string($image)) {
            if (str_starts_with($image, $settings->imagerUrl)) {
                // Url to a file that is in the imager library
                $this->getPathsForLocalImagerFile($image);
            } else {
                if (str_starts_with($image, '//')) {
                    // Protocol relative url, add https
                    $image = 'https:'.$image;
                }

                if (str_starts_with($image, 'http') || str_starts_with($image, 'https')) {
                    // External url
                    $this->type = 'remoteurl';
                    $this->getPathsForUrl($image);
                } else {
                    // Relative path, assume that it's relative to document root
                    $this->getPathsForLocalFile($image);
                }
            }
        } elseif ($image instanceof LocalTransformedImageModel) {
            $this->getPathsForLocalImagerFile($image->url);
        } elseif ($image instanceof ImagerAdapterInterface) {
            $this->getPathsForAdapter($image);
        } elseif ($image instanceof Asset) {
            $this->asset = $image;
            try {
                $fileSystemClass = $image->getVolume()->getFs()::class;
            } catch (InvalidConfigException $invalidConfigException) {
                Craft::error($invalidConfigException->getMessage(), __METHOD__);
                throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
            }

            if ($fileSystemClass === Local::class) {
                $this->getPathsForLocalAsset($image);
            } else {
                $this->type = 'volume';
                $this->getPathsForVolumeAsset($image);
            }
        } else {
            throw new ImagerException(Craft::t('imager-x', 'An unknown image object was used.'));
        }
    }

    public function getFilePath(): string
    {
        return FileHelper::normalizePath($this->path.'/'.$this->filename);
    }

    public function getTemporaryFilePath(): string
    {
        return FileHelper::normalizePath($this->path.'/~'.$this->filename);
    }

    /**
     * Get a local copy of source file
     *
     * @throws ImagerException
     */
    public function getLocalCopy(): void
    {
        $config = ImagerService::getConfig();

        if ($this->type !== 'local') {
            if (!$this->isValidFile($this->getFilePath()) || (($config->cacheDurationRemoteFiles !== false) && ((FileHelper::lastModifiedTime($this->getFilePath()) + $config->cacheDurationRemoteFiles) < time()))) {
                if ($this->asset && $this->type === 'volume') {
                    try {
                        $fs = $this->asset->getVolume()->getFs();
                    } catch (InvalidConfigException $invalidConfigException) {
                        Craft::error($invalidConfigException->getMessage(), __METHOD__);
                        throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
                    }

                    // catch any AssetException and rethrow as ImagerException
                    try {
                        // If a temp file already exists, something went wrong last time, let's delete it and not assume that the Volume will handle it
                        if (file_exists($this->getTemporaryFilePath())) {
                            @unlink($this->getTemporaryFilePath());
                        }

                        Assets::downloadFile($fs, $this->asset->getPath(), $this->getTemporaryFilePath());
                    } catch (FsException $fsException) {
                        throw new ImagerException($fsException->getMessage(), $fsException->getCode(), $fsException);
                    }

                    if (file_exists($this->getTemporaryFilePath())) {
                        copy($this->getTemporaryFilePath(), $this->getFilePath());
                        @unlink($this->getTemporaryFilePath());
                    }
                }

                if ($this->type === 'remoteurl') {
                    $this->downloadFile();
                }

                if (file_exists($this->getFilePath())) {
                    ImagerService::registerCachedRemoteFile($this->getFilePath());
                }
            }

            if (!file_exists($this->getFilePath())) {
                $msg = Craft::t('imager-x', 'File could not be downloaded and saved to “{path}”', ['path' => $this->path]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }
    }

    /**
     * Checks if a file exists and is valid, or should be overwritten
     *
     * @param $file
     *
     * @return bool
     */
    private function isValidFile($file): bool
    {
        if (!file_exists($file)) {
            return false;
        }

        $size = filesize($file);

        return $size !== false && $size >= 1024;
    }

    /**
     * Get paths for a local asset
     *
     *
     * @throws ImagerException
     */
    private function getPathsForLocalAsset(Asset $image): void
    {
        try {
            /** @var Local $fs */
            $fs = $image->getVolume()->getFs();

            $this->transformPath = ImagerHelpers::getTransformPathForAsset($image);
            $this->path = FileHelper::normalizePath($fs->getRootPath().'/'.$image->folderPath);
            $this->url = $image->getUrl();
            $this->filename = $image->getFilename();
            $this->basename = $image->getFilename(false);
            $this->extension = $image->getExtension();
        } catch (InvalidConfigException $invalidConfigException) {
            Craft::error($invalidConfigException->getMessage(), __METHOD__);
            throw new ImagerException($invalidConfigException->getMessage(), $invalidConfigException->getCode(), $invalidConfigException);
        }
    }

    /**
     * Get paths for an asset on an external Craft volume.
     *
     *
     * @throws ImagerException
     */
    private function getPathsForVolumeAsset(Asset $image): void
    {
        $this->transformPath = ImagerHelpers::getTransformPathForAsset($image);

        try {
            $runtimeImagerPath = Craft::$app->getPath()->getRuntimePath().'/imager/';
        } catch (Exception $exception) {
            Craft::error($exception->getMessage(), __METHOD__);
            throw new ImagerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        try {
            $this->url = AssetsHelper::generateUrl($image->getVolume()->getFs(), $image);
            $this->path = FileHelper::normalizePath($runtimeImagerPath.$this->transformPath.'/');
            $this->filename = $image->getFilename();
            $this->basename = $image->getFilename(false);
            $this->extension = $image->getExtension();
        } catch (\Throwable $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }


        try {
            FileHelper::createDirectory($this->path);
        } catch (Exception $throwable) {
            Craft::error($throwable->getMessage(), __METHOD__);
            throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * Get paths for a local file that's in the imager path
     *
     * @param $image
     */
    private function getPathsForLocalImagerFile($image): void
    {
        $config = ImagerService::getConfig();

        $imageString = '/'.str_replace($config->getSetting('imagerUrl'), '', $image);

        $pathParts = pathinfo($imageString);

        $this->transformPath = $pathParts['dirname'];
        $this->path = FileHelper::normalizePath($config->getSetting('imagerSystemPath').'/'.$pathParts['dirname']);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
    }

    /**
     * Get paths for a local file that's not in the imager path
     */
    private function getPathsForLocalFile(string $image): void
    {
        $this->transformPath = ImagerHelpers::getTransformPathForPath($image);
        $pathParts = pathinfo($image);

        $this->path = FileHelper::normalizePath(Yii::getAlias('@webroot').'/'.$pathParts['dirname']);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
    }

    /**
     * Get paths for file from adapter
     */
    private function getPathsForAdapter(ImagerAdapterInterface $adapter): void
    {
        $this->transformPath = $adapter->getTransformPath();
        $pathParts = pathinfo($adapter->getPath());

        $this->path = $pathParts['dirname'];
        $this->url = '';
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
    }

    /**
     * Get paths for an external file (really external, or on an external source type)
     *
     * @param $image
     *
     * @throws ImagerException
     */
    private function getPathsForUrl($image): void
    {
        $config = ImagerService::getConfig();

        try {
            $runtimeImagerPath = Craft::$app->getPath()->getRuntimePath().'/imager/';
        } catch (Exception $exception) {
            Craft::error($exception->getMessage(), __METHOD__);
            throw new ImagerException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $convertedImageStr = StringHelper::toAscii(urldecode($image));
        $this->transformPath = ImagerHelpers::getTransformPathForUrl($convertedImageStr);
        $urlParts = parse_url($convertedImageStr);
        $pathParts = pathinfo($urlParts['path']);
        $queryString = $config->getSetting('useRemoteUrlQueryString') ? ($urlParts['query'] ?? '') : '';

        $this->path = FileHelper::normalizePath($runtimeImagerPath.$this->transformPath.'/');
        $this->url = $image;
        $this->basename = FileHelper::truncateBasename(str_replace(' ', '-', $pathParts['filename']).($queryString !== '' ? '_'.md5($queryString) : ''));
        $this->extension = $pathParts['extension'] ?? '';
        $this->filename = FileHelper::sanitizeFilename($this->basename.($this->extension !== '' ? '.'.$this->extension : ''));

        try {
            FileHelper::createDirectory($this->path);
        } catch (Exception $exception) {
            Craft::error($exception->getMessage(), __METHOD__);
            throw new ImagerException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Downloads an external url and places it in the source path.
     *
     * @throws ImagerException
     */
    private function downloadFile(): void
    {
        $config = ImagerService::getConfig();
        $imageUrl = $this->url;

        // url encode filename to account for non-ascii characters in filenames.
        if (!$config->useRawExternalUrl) {
            $imageUrlArr = explode('?', $this->url);

            $imageUrlArr[0] = preg_replace_callback('#://([^/]+)/([^?]+)#', fn($match) => '://'.$match[1].'/'.implode('/', array_map('rawurlencode', explode('/', $match[2]))), urldecode($imageUrlArr[0]));

            $imageUrl = implode('?', $imageUrlArr);
        }

        if (\function_exists('curl_init')) {
            $ch = curl_init($imageUrl);
            $fp = fopen($this->getTemporaryFilePath(), 'wb');

            $defaultOptions = [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_TIMEOUT => 30,
            ];

            // merge default options with config setting, config overrides default.
            $options = $config->getSetting('curlOptions') + $defaultOptions;

            curl_setopt_array($ch, $options);
            curl_exec($ch);
            $curlErrorNo = curl_errno($ch);
            $curlError = curl_error($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose(/** @scrutinizer ignore-type */ $fp);

            if ($curlErrorNo !== 0) {
                @unlink($this->getTemporaryFilePath());
                $msg = Craft::t('imager-x', 'cURL error “{curlErrorNo}” encountered while attempting to download “{imageUrl}”. The error was: “{curlError}”', ['imageUrl' => $imageUrl, 'curlErrorNo' => $curlErrorNo, 'curlError' => $curlError]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            if ($httpStatus !== 200 && !($httpStatus === 404 && strrpos(mime_content_type($this->getTemporaryFilePath()), 'image') !== false)) {
                // remote server returned a 404, but the contents was a valid image file
                @unlink($this->getTemporaryFilePath());
                $msg = Craft::t('imager-x', 'HTTP status “{httpStatus}” encountered while attempting to download “{imageUrl}”', ['imageUrl' => $imageUrl, 'httpStatus' => $httpStatus]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        } elseif (ini_get('allow_url_fopen')) {
            if (!@copy($imageUrl, $this->getTemporaryFilePath())) {
                @unlink($this->getTemporaryFilePath());
                $errors = error_get_last();
                $msg = Craft::t('imager-x', 'Error “{errorType}” encountered while attempting to download “{imageUrl}”: {errorMessage}', ['imageUrl' => $imageUrl, 'errorType' => $errors['type'], 'errorMessage' => $errors['message']]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        } else {
            $msg = Craft::t('imager-x', 'Looks like allow_url_fopen is off and cURL is not enabled. To download external files, one of these methods has to be enabled.');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if (file_exists($this->getTemporaryFilePath())) {
            copy($this->getTemporaryFilePath(), $this->getFilePath());
            @unlink($this->getTemporaryFilePath());
        }
    }
}
