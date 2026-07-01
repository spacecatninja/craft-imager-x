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
use CraftCms\Cms\Filesystem\Filesystems\Local;
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
                        $fs = $this->asset->getVolume();
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
            $volume = $image->getVolume();

            /** @var Local $fs */
            $fs = $image->getVolume()->getFs();

            $this->transformPath = ImagerHelpers::getTransformPathForAsset($image);
            $this->path = FileHelper::normalizePath($fs->getRootPath().'/'.$volume->getSubpath().'/'.$image->folderPath);
            $this->url = $image->getUrl() ?? '';
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
            $this->url = AssetsHelper::generateUrl($image);
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
     * @throws ImagerException
     */
    private function getPathsForLocalImagerFile($image): void
    {
        $config = ImagerService::getConfig();

        $imageString = '/'.str_replace($config->getSetting('imagerUrl'), '', $image);

        $pathParts = pathinfo($imageString);

        $base = $config->getSetting('imagerSystemPath');
        $this->transformPath = $pathParts['dirname'];
        $this->path = FileHelper::normalizePath($base.'/'.$pathParts['dirname']);
        $this->assertPathWithinBase($this->path, $base);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
    }

    /**
     * Get paths for a local file that's not in the imager path
     *
     * @throws ImagerException
     */
    private function getPathsForLocalFile(string $image): void
    {
        $webroot = (string)Yii::getAlias('@webroot');
        $pathParts = pathinfo($image);

        $this->transformPath = ImagerHelpers::getTransformPathForPath($image);
        $this->path = FileHelper::normalizePath($webroot.'/'.$pathParts['dirname']);
        $this->assertPathWithinBase($this->path, $webroot);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
    }

    /**
     * Asserts that a resolved directory stays within an allowed base directory.
     *
     * Guards against path traversal (e.g. "../") in externally supplied image references.
     * Both paths are normalized first, which resolves "." and ".." segments lexically, so the
     * comparison catches attempts to escape the base regardless of the file existing on disk.
     *
     * @throws ImagerException
     */
    private function assertPathWithinBase(string $path, string $base): void
    {
        $normalizedBase = FileHelper::normalizePath($base);
        $normalizedPath = FileHelper::normalizePath($path);

        if ($normalizedBase === '' || !str_starts_with($normalizedPath.'/', rtrim($normalizedBase, '/').'/')) {
            $msg = Craft::t('imager-x', 'Refusing to resolve an image path outside of the allowed base directory.');
            Craft::error($msg.' Path: "'.$normalizedPath.'", base: "'.$normalizedBase.'".', __METHOD__);
            throw new ImagerException($msg);
        }
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
     * Validates that a URL uses an allowed scheme and that *every* IP address its host
     * resolves to is a public, non-reserved address.
     *
     * Returns the validated IP addresses so the caller can pin the connection to them and
     * avoid a DNS-rebinding window between validation and the actual request. All A and AAAA
     * records are checked (not just the first), since cURL may connect to any of them.
     *
     * @return string[]
     * @throws ImagerException
     */
    private function validateExternalUrl(string $image): array
    {
        $scheme = strtolower((string)parse_url($image, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new ImagerException(Craft::t('imager-x', 'URL "{url}" uses a disallowed scheme; only http and https are supported', ['url' => $image]));
        }

        $host = parse_url($image, PHP_URL_HOST);

        if (empty($host)) {
            throw new ImagerException(Craft::t('imager-x', 'Could not parse host from URL "{url}"', ['url' => $image]));
        }

        $ips = $this->resolveHostIps($host);

        if ($ips === []) {
            throw new ImagerException(Craft::t('imager-x', 'Could not resolve host "{host}" to a valid IP address', ['host' => $host]));
        }

        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                $msg = Craft::t('imager-x', 'URL "{url}" resolves to a private or reserved IP address and cannot be used as an image source', ['url' => $image]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }

        return $ips;
    }

    /**
     * Resolves a hostname to all of its A and AAAA records. If the host is already an IP
     * literal it is returned as-is. Falls back to gethostbyname() when no DNS records are
     * returned (e.g. hosts defined in the system hosts file).
     *
     * @return string[]
     */
    private function resolveHostIps(string $host): array
    {
        $literal = trim($host, '[]');

        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            return [$literal];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }

                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $ip = gethostbyname($host);

            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Checks whether an IP address is safe to connect to (a public, non-reserved address).
     */
    private function isPublicIp(string $ip): bool
    {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Builds a CURLOPT_RESOLVE entry that pins the URL's host (on its scheme's port) to the
     * already-validated IP addresses, so cURL connects to exactly what we validated.
     *
     * @param string[] $ips
     * @return string[]
     */
    private function buildResolveList(string $url, array $ips): array
    {
        if ($ips === []) {
            return [];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (empty($host)) {
            return [];
        }

        $host = trim($host, '[]');
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        return [sprintf('%s:%d:%s', $host, $port, implode(',', $ips))];
    }

    /**
     * Get paths for an external file (really external, or on an external source type)
     *
     * @param string $image
     * @throws ImagerException
     */
    private function getPathsForUrl(string $image): void
    {
        $config = ImagerService::getConfig();

        if (!$config->skipExternalUrlValidation) {
            $this->validateExternalUrl($image);
        }

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
            $this->downloadFileWithCurl($imageUrl, $config);
        } elseif (ini_get('allow_url_fopen')) {
            $this->downloadFileWithFopen($imageUrl, $config);
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

    /**
     * Downloads a file with cURL.
     *
     * Redirects are followed manually (CURLOPT_FOLLOWLOCATION is off) so that every hop is
     * re-validated with validateExternalUrl() and the connection is pinned to the validated
     * IP addresses via CURLOPT_RESOLVE. This closes SSRF via redirects and DNS rebinding that
     * a single up-front check would miss. Protocols are restricted to http/https, and these
     * safety-critical options cannot be overridden by the curlOptions config setting.
     *
     * @throws ImagerException
     */
    private function downloadFileWithCurl(string $imageUrl, ConfigModel $config): void
    {
        $maxRedirects = 10;
        $currentUrl = $imageUrl;
        $httpStatus = 0;

        for ($redirect = 0; $redirect <= $maxRedirects; ++$redirect) {
            $resolve = [];

            if (!$config->skipExternalUrlValidation) {
                $resolve = $this->buildResolveList($currentUrl, $this->validateExternalUrl($currentUrl));
            }

            $fp = fopen($this->getTemporaryFilePath(), 'wb');

            if ($fp === false) {
                $msg = Craft::t('imager-x', 'Could not open a temporary file for writing while attempting to download “{imageUrl}”', ['imageUrl' => $currentUrl]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            $ch = curl_init($currentUrl);

            $defaultOptions = [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => 0,
                CURLOPT_TIMEOUT => 30,
            ];

            // Merge configured options (config overrides defaults), then re-assert the
            // safety-critical options so config can't re-enable auto-following or widen protocols.
            $options = $config->getSetting('curlOptions') + $defaultOptions;
            $options[CURLOPT_FILE] = $fp;
            $options[CURLOPT_FOLLOWLOCATION] = 0;
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;

            if ($resolve !== []) {
                $options[CURLOPT_RESOLVE] = $resolve;
            }

            curl_setopt_array($ch, $options);
            curl_exec($ch);
            $curlErrorNo = curl_errno($ch);
            $curlError = curl_error($ch);
            $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectUrl = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            fclose($fp);

            if ($curlErrorNo !== 0) {
                @unlink($this->getTemporaryFilePath());
                $msg = Craft::t('imager-x', 'cURL error “{curlErrorNo}” encountered while attempting to download “{imageUrl}”. The error was: “{curlError}”', ['imageUrl' => $currentUrl, 'curlErrorNo' => $curlErrorNo, 'curlError' => $curlError]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            if ($httpStatus >= 300 && $httpStatus < 400 && $redirectUrl !== '') {
                $currentUrl = $redirectUrl;
                continue;
            }

            break;
        }

        if ($httpStatus !== 200 && !($httpStatus === 404 && strrpos(mime_content_type($this->getTemporaryFilePath()), 'image') !== false)) {
            // remote server returned a 404, but the contents was a valid image file
            @unlink($this->getTemporaryFilePath());
            $msg = Craft::t('imager-x', 'HTTP status “{httpStatus}” encountered while attempting to download “{imageUrl}”', ['imageUrl' => $currentUrl, 'httpStatus' => $httpStatus]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }
    }

    /**
     * Downloads a file using PHP's stream wrapper, as a fallback when cURL is unavailable.
     *
     * The URL is validated up front, and redirects are not followed here since they cannot be
     * re-validated on this code path.
     *
     * @throws ImagerException
     */
    private function downloadFileWithFopen(string $imageUrl, ConfigModel $config): void
    {
        if (!$config->skipExternalUrlValidation) {
            $this->validateExternalUrl($imageUrl);
        }

        $context = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'max_redirects' => 1,
            ],
        ]);

        if (!@copy($imageUrl, $this->getTemporaryFilePath(), $context)) {
            @unlink($this->getTemporaryFilePath());
            $errors = error_get_last();
            $msg = Craft::t('imager-x', 'Error “{errorType}” encountered while attempting to download “{imageUrl}”: {errorMessage}', ['imageUrl' => $imageUrl, 'errorType' => $errors['type'], 'errorMessage' => $errors['message']]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }
    }
}
