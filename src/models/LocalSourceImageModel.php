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

use CraftCms\Cms\Asset\Elements\Asset;
use craft\errors\FsException;
use CraftCms\Cms\Filesystem\Exceptions\FilesystemException;
use CraftCms\Cms\Filesystem\Filesystems\Local;
use craft\helpers\Assets;
use craft\helpers\Assets as AssetsHelper;

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
    /**
     * @var int Seconds to wait for another process that is downloading the same source file.
     */
    private const SOURCE_CACHE_LOCK_TIMEOUT = 30;

    /**
     * @var string[] Raster image types that remote sources are always allowed to contain.
     */
    private const RASTER_MIME_TYPES = [
        'image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/jxl',
        'image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence', 'image/tiff',
        'image/bmp', 'image/x-bmp', 'image/x-ms-bmp', 'image/vnd.microsoft.icon', 'image/x-icon',
        'image/jp2', 'image/jpx', 'image/jpm', 'image/x-jp2-codestream',
        // Names used by some versions of libmagic, including the one bundled with PHP's fileinfo extension
        'image/x-jxl',
    ];

    /**
     * @var string[] Major brands of the ISO base media files that are images (AVIF, HEIF and HEIC). Only the major
     * brand is checked, since that's what image libraries use to pick a decoder.
     */
    private const ISOBMFF_IMAGE_BRANDS = ['avif', 'avis', 'heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'hevm', 'hevs', 'mif1', 'msf1'];

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
        if ($this->type === 'local') {
            return;
        }

        if ($this->shouldRefreshLocalCopy()) {
            $mutex = Craft::$app->getMutex();
            $lockName = 'imagerx-sourcecache-'.md5($this->getFilePath());
            $acquiredLock = $mutex->acquire($lockName, self::SOURCE_CACHE_LOCK_TIMEOUT);

            if (!$acquiredLock) {
                Craft::warning('Timed out waiting for source cache lock for "'.$this->getFilePath().'", proceeding without lock.', __METHOD__);
            }

            try {
                // Re-check after acquiring the lock, another process may have refreshed the file while we waited
                if ($this->shouldRefreshLocalCopy()) {
                    if ($this->asset && $this->type === 'volume') {
                        try {
                            $fs = $this->asset->getVolume()->sourceDisk();
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
                        } catch (FsException|FilesystemException $fsException) {
                            throw new ImagerException($fsException->getMessage(), $fsException->getCode(), $fsException);
                        }

                        $this->publishTemporaryFile();
                    }

                    if ($this->type === 'remoteurl') {
                        $this->downloadFile();
                    }

                    if (file_exists($this->getFilePath())) {
                        ImagerService::registerCachedRemoteFile($this->getFilePath());
                    }
                }
            } finally {
                if ($acquiredLock) {
                    $mutex->release($lockName);
                }
            }
        }

        if (!file_exists($this->getFilePath())) {
            $msg = Craft::t('imager-x', 'File could not be downloaded and saved to “{path}”', ['path' => $this->path]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        // Downloads are checked before they are published to the source cache, this covers files cached by earlier versions
        if ($this->type === 'remoteurl') {
            $this->assertRemoteFileIsImage($this->getFilePath());
        }
    }

    /**
     * Checks that a downloaded file is a raster image, or the type of file that the extension of the url says it
     * is, like a PDF from a .pdf url. Image libraries like ImageMagick pick the format from the file content, so
     * other content could otherwise be opened as a format that was never intended, like SVG.
     *
     * @throws ImagerException
     */
    private function assertRemoteFileIsImage(string $file): void
    {
        $extension = strtolower($this->extension);
        $imageInfo = @getimagesize($file);
        $mimeType = \is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;

        // getimagesize() doesn't know all image types, like HEIC and JPEG XL
        if ($mimeType === null) {
            try {
                $mimeType = FileHelper::getMimeType($file, null, false);
            } catch (\Throwable) {
                $mimeType = null;
            }
        }

        if ($mimeType !== null && \in_array(strtolower($mimeType), self::RASTER_MIME_TYPES, true)) {
            return;
        }

        // The fileinfo extension is optional, and older versions of libmagic don't know all newer formats,
        // so if the type couldn't be determined, check the signature of those formats directly
        if (($mimeType === null || $mimeType === 'application/octet-stream') && self::hasNewerImageSignature($file)) {
            return;
        }

        // Other content is only allowed if it's what the url's own extension says it is, like a PDF from a .pdf
        // url or an SVG from a .svg url. Urls with an image file extension or none must contain a raster image.
        // Plain text, XML and ImageMagick's own drawing and scripting formats are never allowed, since ImageMagick
        // detects them from the content, and neither is content of an unknown type.
        $mimeType = $mimeType !== null ? strtolower($mimeType) : null;

        if ($mimeType !== null && $extension !== '' && !str_starts_with($mimeType, 'text/')
            && !\in_array($mimeType, ['application/xml', 'application/octet-stream', 'image/x-mvg', 'image/x-msl'], true)
            && \in_array($extension, FileHelper::getExtensionsByMimeType($mimeType), true)) {
            return;
        }

        // Remove it, so that it isn't used again
        @unlink($file);

        $msg = Craft::t('imager-x', 'The remote file “{imageUrl}” is not a supported image.', ['imageUrl' => $this->url]);
        Craft::error($msg.' Detected type: '.($mimeType ?? 'unknown').'.', __METHOD__);
        throw new ImagerException($msg);
    }

    /**
     * Checks if a file starts with the signature of an image format that getimagesize() and older versions of
     * libmagic don't know: AVIF and HEIF/HEIC (an ISO base media file with an image brand), or JPEG XL.
     */
    private static function hasNewerImageSignature(string $file): bool
    {
        $header = @file_get_contents($file, false, null, 0, 16);

        if (!\is_string($header) || \strlen($header) < 12) {
            return false;
        }

        // JPEG XL codestream, or JPEG XL container
        if (str_starts_with($header, "\xFF\x0A") || str_starts_with($header, "\x00\x00\x00\x0CJXL \x0D\x0A\x87\x0A")) {
            return true;
        }

        // ISO base media file, the major brand follows the ftyp box type
        return substr($header, 4, 4) === 'ftyp' && \in_array(substr($header, 8, 4), self::ISOBMFF_IMAGE_BRANDS, true);
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
     * Checks if the local copy of a remote source file is missing, invalid, or has expired.
     */
    private function shouldRefreshLocalCopy(): bool
    {
        $config = ImagerService::getConfig();

        return !$this->isValidFile($this->getFilePath()) || (($config->cacheDurationRemoteFiles !== false) && ((FileHelper::lastModifiedTime($this->getFilePath()) + $config->cacheDurationRemoteFiles) < time()));
    }

    /**
     * Publishes the downloaded temp file to the final source cache path. Same-directory rename
     * is atomic, so processes that don't hold the source cache lock never see a partial file.
     */
    private function publishTemporaryFile(): void
    {
        if (!file_exists($this->getTemporaryFilePath())) {
            return;
        }

        if (!@rename($this->getTemporaryFilePath(), $this->getFilePath())) {
            copy($this->getTemporaryFilePath(), $this->getFilePath());
            @unlink($this->getTemporaryFilePath());
        }
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

        // Backslashes are separators when the path is used on disk, so split on them too
        $imageString = '/'.str_replace($config->getSetting('imagerUrl'), '', str_replace('\\', '/', $image));

        $pathParts = pathinfo($imageString);

        $base = $config->getSetting('imagerSystemPath');
        $this->transformPath = $pathParts['dirname'];
        $this->path = FileHelper::normalizePath($base.'/'.$pathParts['dirname']);
        $this->assertPathWithinBase($this->path, $base);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
        $this->assertPathWithinBase($this->getFilePath(), $base);
    }

    /**
     * Get paths for a local file that's not in the imager path
     *
     * @throws ImagerException
     */
    private function getPathsForLocalFile(string $image): void
    {
        $webroot = (string)Yii::getAlias('@webroot');

        // Backslashes are separators when the path is used on disk, so split on them too
        $normalizedImage = str_replace('\\', '/', $image);
        $pathParts = pathinfo($normalizedImage);

        $this->transformPath = ImagerHelpers::getTransformPathForPath($normalizedImage);
        $this->path = FileHelper::normalizePath($webroot.'/'.$pathParts['dirname']);
        $this->assertPathWithinBase($this->path, $webroot);
        $this->url = $image;
        $this->filename = $pathParts['basename'];
        $this->basename = $pathParts['filename'];
        $this->extension = $pathParts['extension'] ?? '';
        $this->assertPathWithinBase($this->getFilePath(), $webroot);
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
        if (!FileHelper::isWithinBase($path, $base)) {
            $msg = Craft::t('imager-x', 'Refusing to resolve an image path outside of the allowed base directory.');
            Craft::error($msg.' Path: "'.FileHelper::normalizePath($path).'", base: "'.FileHelper::normalizePath($base).'".', __METHOD__);
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
     * Checks whether an IP address is safe to connect to (a globally routable unicast address).
     */
    private function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // 224.0.0.0/4 multicast is never a valid image host
            return ((int)explode('.', $ip)[0] & 0xF0) !== 0xE0;
        }

        $hex = bin2hex((string)inet_pton($ip));

        // NAT64 well-known prefix 64:ff9b::/96 embeds an IPv4 address, judge the embedded address
        if (str_starts_with($hex, '0064ff9b0000000000000000')) {
            return $this->isPublicIp((string)inet_ntop((string)hex2bin(substr($hex, 24, 8))));
        }

        // Checked explicitly because PHP's filter tables differ between versions (PHP 8.2 accepts
        // ::ffff:0:0/96 with NO_RES_RANGE, and 2002::/16 with GLOBAL_RANGE): IPv4-compatible ::/96,
        // IPv4-mapped ::ffff:0:0/96, IPv4-translated ::ffff:0:0:0/96, 6to4 2002::/16, local-use NAT64
        // 64:ff9b:1::/48, deprecated site-local fec0::/10, multicast ff00::/8, documentation 3fff::/20
        // and SRv6 SIDs 5f00::/16.
        if (str_starts_with($hex, '000000000000000000000000')
            || str_starts_with($hex, '00000000000000000000ffff')
            || str_starts_with($hex, '0000000000000000ffff0000')
            || str_starts_with($hex, '2002')
            || str_starts_with($hex, '0064ff9b0001')
            || (hexdec(substr($hex, 0, 3)) & 0xFFC) === 0xFEC
            || str_starts_with($hex, 'ff')
            || str_starts_with($hex, '3fff0')
            || str_starts_with($hex, '5f00')
        ) {
            return false;
        }

        return true;
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

        $convertedImageStr = ImagerHelpers::normalizeRemoteUrlString($image);
        $this->transformPath = ImagerHelpers::getTransformPathForUrl($convertedImageStr);
        $urlParts = parse_url($convertedImageStr);
        $pathParts = pathinfo(ImagerHelpers::getRemoteUrlPath($image));
        $queryString = $config->getSetting('useRemoteUrlQueryString') ? ($urlParts['query'] ?? '') : '';

        $this->path = FileHelper::normalizePath($runtimeImagerPath.$this->transformPath.'/');
        $this->assertPathWithinBase($this->path, $runtimeImagerPath);
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

        try {
            if (\function_exists('curl_init')) {
                $this->downloadFileWithCurl($imageUrl, $config);
            } elseif (ini_get('allow_url_fopen')) {
                $this->downloadFileWithFopen($imageUrl, $config);
            } else {
                $msg = Craft::t('imager-x', 'Looks like allow_url_fopen is off and cURL is not enabled. To download external files, one of these methods has to be enabled.');
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        } catch (ImagerException $imagerException) {
            // Don't leave a partial download behind
            @unlink($this->getTemporaryFilePath());
            throw $imagerException;
        }

        // Check the download before it's published to the source cache, so that only checked files are ever used
        $this->assertRemoteFileIsImage($this->getTemporaryFilePath());
        $this->publishTemporaryFile();
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
        $maxFileSize = $config->remoteMaxFileSize;
        $deadline = $config->remoteDownloadTimeout > 0 ? microtime(true) + $config->remoteDownloadTimeout : null;

        for ($redirect = 0; $redirect <= $maxRedirects; ++$redirect) {
            $resolve = [];
            $remainingTime = null;

            if ($deadline !== null) {
                $remainingTime = (int)ceil($deadline - microtime(true));

                if ($remainingTime <= 0) {
                    $msg = Craft::t('imager-x', 'Download of “{imageUrl}” took longer than the configured remoteDownloadTimeout', ['imageUrl' => $imageUrl]);
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            }

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

            // Keep each request within what remains of the total time budget. cURL treats 0 as no
            // limit, so a configured 0 is replaced by the remaining time rather than kept.
            if ($remainingTime !== null) {
                $options[CURLOPT_TIMEOUT] = empty($options[CURLOPT_TIMEOUT]) ? $remainingTime : min((int)$options[CURLOPT_TIMEOUT], $remainingTime);
                $options[CURLOPT_CONNECTTIMEOUT] = empty($options[CURLOPT_CONNECTTIMEOUT]) ? $remainingTime : min((int)$options[CURLOPT_CONNECTTIMEOUT], $remainingTime);

                // The millisecond variants replace the ones above if they're set, so they're limited the same way
                foreach ([CURLOPT_TIMEOUT_MS, CURLOPT_CONNECTTIMEOUT_MS] as $msOption) {
                    if (empty($options[$msOption])) {
                        unset($options[$msOption]);
                    } else {
                        $options[$msOption] = min((int)$options[$msOption], $remainingTime * 1000);
                    }
                }
            }

            // Limit the size of the download. MAXFILESIZE only works when the server sends a content length,
            // the progress callback aborts other downloads once they get too big. The written file is checked
            // too, since it can be larger than what was transferred if cURL decompresses the response.
            if ($maxFileSize > 0) {
                $options[CURLOPT_MAXFILESIZE] = $maxFileSize;
                $options[CURLOPT_NOPROGRESS] = false;
                $options[CURLOPT_XFERINFOFUNCTION] = static fn($curl, int $downloadTotal, int $downloaded): int => ($downloaded > $maxFileSize || ftell($fp) > $maxFileSize) ? 1 : 0;
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

        // The progress callback may not run after the last write, so check the size of the result too
        clearstatcache(true, $this->getTemporaryFilePath());

        if ($maxFileSize > 0 && (int)@filesize($this->getTemporaryFilePath()) > $maxFileSize) {
            @unlink($this->getTemporaryFilePath());
            $msg = Craft::t('imager-x', 'Download of “{imageUrl}” is larger than the configured remoteMaxFileSize', ['imageUrl' => $imageUrl]);
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

        $httpOptions = [
            'follow_location' => 0,
            'max_redirects' => 1,
        ];

        $deadline = null;

        if ($config->remoteDownloadTimeout > 0) {
            $httpOptions['timeout'] = $config->remoteDownloadTimeout;
            $deadline = microtime(true) + $config->remoteDownloadTimeout;
        }

        $context = stream_context_create(['http' => $httpOptions]);
        $maxFileSize = $config->remoteMaxFileSize;

        error_clear_last();
        $source = @fopen($imageUrl, 'rb', false, $context);
        $target = $source !== false ? @fopen($this->getTemporaryFilePath(), 'wb') : false;
        $copied = false;
        $timedOut = false;

        if ($source !== false && $target !== false) {
            $copied = 0;

            // Copy in chunks, since the stream timeout only limits how long each read can wait. Every read
            // gets what's left of the total time, so that a slow server can't keep the download going.
            while (!feof($source)) {
                if ($deadline !== null) {
                    $remainingTime = $deadline - microtime(true);

                    if ($remainingTime <= 0) {
                        $timedOut = true;
                        break;
                    }

                    stream_set_timeout($source, (int)$remainingTime, (int)(($remainingTime - floor($remainingTime)) * 1_000_000));
                }

                $chunk = @fread($source, 65536);

                if (stream_get_meta_data($source)['timed_out']) {
                    $timedOut = true;
                    break;
                }

                if ($chunk === false || @fwrite($target, $chunk) === false) {
                    $copied = false;
                    break;
                }

                $copied += \strlen($chunk);

                if ($maxFileSize > 0 && $copied > $maxFileSize) {
                    break;
                }
            }
        }

        if (\is_resource($source)) {
            fclose($source);
        }

        if (\is_resource($target)) {
            fclose($target);
        }

        if ($timedOut) {
            @unlink($this->getTemporaryFilePath());
            $msg = Craft::t('imager-x', 'Download of “{imageUrl}” took longer than the configured remoteDownloadTimeout', ['imageUrl' => $imageUrl]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if ($copied === false) {
            @unlink($this->getTemporaryFilePath());
            $errors = error_get_last();
            $msg = Craft::t('imager-x', 'Error “{errorType}” encountered while attempting to download “{imageUrl}”: {errorMessage}', ['imageUrl' => $imageUrl, 'errorType' => $errors['type'] ?? '', 'errorMessage' => $errors['message'] ?? '']);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if ($maxFileSize > 0 && $copied > $maxFileSize) {
            @unlink($this->getTemporaryFilePath());
            $msg = Craft::t('imager-x', 'Download of “{imageUrl}” is larger than the configured remoteMaxFileSize', ['imageUrl' => $imageUrl]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }
    }
}
