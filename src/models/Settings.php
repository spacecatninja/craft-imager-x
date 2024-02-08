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

use craft\base\Model;
use craft\elements\Asset;

class Settings extends Model
{
    public string $transformer = 'craft';
    public string $imagerSystemPath = '@webroot/imager/';
    public string|array $imagerUrl = '/imager/';
    public bool $cacheEnabled = true;
    public bool $cacheRemoteFiles = true;
    public string|int|bool $cacheDuration = false;
    public string|int|bool $cacheDurationRemoteFiles = 1_209_600;
    public string|int|bool $cacheDurationExternalStorage = 1_209_600;
    public string|int|bool $cacheDurationNonOptimized = 300;
    public int $jpegQuality = 80;
    public int $pngCompressionLevel = 2;
    public int $webpQuality = 80;
    public int $avifQuality = 80;
    public int $jxlQuality = 80;
    public array $webpImagickOptions = [];
    public string|bool $interlace = false;
    public bool $allowUpscale = true;
    public string $resizeFilter = 'lanczos';
    public bool $smartResizeEnabled = false;
    public bool $removeMetadata = false;
    public bool $preserveColorProfiles = false;
    public array $safeFileFormats = ['jpg', 'jpeg', 'gif', 'png'];
    public string $bgColor = '';
    public string $position = '50% 50%';
    public array $letterbox = ['color' => '#000', 'opacity' => 0];
    public array $blurhashComponents = [4, 3];
    public bool $useFilenamePattern = true;
    public string $filenamePattern = '{basename}_{transformString|hash}.{extension}';
    public int $shortHashLength = 10;
    public string|bool $hashFilename = 'postfix'; // deprecated?
    public bool $hashPath = false;
    public bool $addVolumeToPath = true;
    public string|bool $hashRemoteUrl = false;
    public bool $useRemoteUrlQueryString = false;
    public bool $useRawExternalUrl = true;
    public bool $instanceReuseEnabled = false;
    public bool $noop = false;
    public bool $suppressExceptions = false;
    public bool $convertToRGB = false;
    public string $clearKey = '';
    public bool $skipExecutableExistCheck = false;
    public bool $removeTransformsOnAssetFileops = false;
    public array $curlOptions = [];
    public bool $runJobsImmediatelyOnAjaxRequests = true;
    
    public bool|string $fillTransforms = false;
    public string $fillAttribute = 'width';
    public int|string $fillInterval = 200;
    public int|string $autoFillCount = 3;
    
    public int|string|Asset|null $fallbackImage = null;
    public int|string|Asset|null $mockImage = null;
    
    public bool $useForNativeTransforms = false;
    public bool $useForCpThumbs = false;
    public array $hideClearCachesForUserGroups = [];

    public string $imgixProfile = 'default';
    public string $imgixApiKey = '';
    public bool $imgixEnableAutoPurging = true;
    public bool $imgixEnablePurgeElementAction = true;

    public array $imgixConfig = [
        'default' => [
            'domain' => '',
            'useHttps' => true,
            'signKey' => '',
            'sourceIsWebProxy' => false,
            'useCloudSourcePath' => true,
            'getExternalImageDimensions' => true,
            'defaultParams' => [],
            'apiKey' => '',
            'excludeFromPurge' => false,
        ],
    ];

    public string $optimizeType = 'job';
    public array $optimizers = [];

    public array $optimizerConfig = [
        'jpegoptim' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/jpegoptim',
            'optionString' => '-s',
        ],
        'jpegtran' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/jpegtran',
            'optionString' => '-optimize -copy none',
        ],
        'mozjpeg' => [
            'extensions' => ['jpg'],
            'path' => '/usr/bin/mozjpeg',
            'optionString' => '-optimize -copy none',
        ],
        'optipng' => [
            'extensions' => ['png'],
            'path' => '/usr/bin/optipng',
            'optionString' => '-o2',
        ],
        'pngquant' => [
            'extensions' => ['png'],
            'path' => '/usr/bin/pngquant',
            'optionString' => '--strip --skip-if-larger',
        ],
        'gifsicle' => [
            'extensions' => ['gif'],
            'path' => '/usr/bin/gifsicle',
            'optionString' => '--optimize=3 --colors 256',
        ],
        'tinypng' => [
            'extensions' => ['png', 'jpg'],
            'apiKey' => '',
        ],
        'kraken' => [
            'extensions' => ['png', 'jpg', 'gif'],
            'apiKey' => '',
            'apiSecret' => '',
            'additionalParams' => [
                'lossy' => true,
            ],
        ],
        'imageoptim' => [
            'extensions' => ['png', 'jpg', 'gif'],
            'apiUsername' => '',
            'quality' => 'medium',
        ],
    ];

    public array $storages = [];

    public array $storageConfig = [
        'aws' => [
            'accessKey' => '',
            'secretAccessKey' => '',
            'region' => '',
            'bucket' => '',
            'folder' => '',
            'requestHeaders' => [],
            'storageType' => 'standard',
            'public' => 'true',
            'cloudfrontInvalidateEnabled' => false,
            'cloudfrontDistributionId' => '',
        ],
        'gcs' => [
            'keyFile' => '',
            'bucket' => '',
            'folder' => '',
        ],
    ];

    public array $customEncoders = [];
    public ?array $transformerConfig = null;
    

    /**
     * Settings constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if (!empty($config)) {
            \Yii::configure($this, $config);
        }

        $this->init();
    }

    public function init(): void
    {
        // Set default based on devMode. Overridable through config.
        $this->suppressExceptions = !\Craft::$app->getConfig()->getGeneral()->devMode;
    }
}
