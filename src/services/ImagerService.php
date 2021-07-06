<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\services;

use Craft;

use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use Imagine\Image\ImageInterface;

use spacecatninja\imagerx\helpers\VersionHelpers;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

use spacecatninja\imagerx\ImagerX as Plugin;
use spacecatninja\imagerx\models\TransformedImageInterface;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\transformers\CraftTransformer;
use spacecatninja\imagerx\transformers\TransformerInterface;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\helpers\TransformHelpers;
use spacecatninja\imagerx\models\GenerateSettings;

/**
 * ImagerService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class ImagerService extends Component
{
    /**
     * @var string
     */
    public static $imageDriver = 'gd';

    /**
     * @var null|ConfigModel
     */
    public static $transformConfig = null;

    /**
     * @var null|GenerateSettings
     */
    public static $generateConfig = null;

    /**
     * @var array
     */
    public static $transformers = [
        'craft' => CraftTransformer::class
    ];

    /**
     * @var array
     */
    public static $effects = [];

    /**
     * @var array
     */
    public static $optimizers = [];

    /**
     * @var array
     */
    public static $storage = [];

    /**
     * @var array
     */
    public static $namedTransforms = [];

    /**
     * @var array
     */
    public static $remoteImageSessionCache = [];

    /**
     * Translate dictionary for translating transform keys into filename markers
     *
     * @var array
     */
    public static $transformKeyTranslate = [
        'width' => 'W',
        'height' => 'H',
        'mode' => 'M',
        'position' => 'P',
        'format' => 'F',
        'bgColor' => 'BC',
        'cropZoom' => 'CZ',
        'effects' => 'E',
        'preEffects' => 'PE',
        'resizeFilter' => 'RF',
        'allowUpscale' => 'upscale',
        'pngCompressionLevel' => 'PNGC',
        'jpegQuality' => 'Q',
        'webpQuality' => 'WQ',
        'webpImagickOptions' => 'WIO',
        'interlace' => 'I',
        'instanceReuseEnabled' => 'REUSE',
        'watermark' => 'WM',
        'letterbox' => 'LB',
        'frames' => 'FR',
        'pad' => 'PAD',
    ];

    /**
     * Translate dictionary for resize method
     *
     * @var array
     */
    public static $filterKeyTranslate = [
        'point' => ImageInterface::FILTER_POINT,
        'box' => ImageInterface::FILTER_BOX,
        'triangle' => ImageInterface::FILTER_TRIANGLE,
        'hermite' => ImageInterface::FILTER_HERMITE,
        'hanning' => ImageInterface::FILTER_HANNING,
        'hamming' => ImageInterface::FILTER_HAMMING,
        'blackman' => ImageInterface::FILTER_BLACKMAN,
        'gaussian' => ImageInterface::FILTER_GAUSSIAN,
        'quadratic' => ImageInterface::FILTER_QUADRATIC,
        'cubic' => ImageInterface::FILTER_CUBIC,
        'catrom' => ImageInterface::FILTER_CATROM,
        'mitchell' => ImageInterface::FILTER_MITCHELL,
        'lanczos' => ImageInterface::FILTER_LANCZOS,
        'bessel' => ImageInterface::FILTER_BESSEL,
        'sinc' => ImageInterface::FILTER_SINC,
    ];

    /**
     * Translate dictionary for interlace method
     *
     * @var array
     */
    public static $interlaceKeyTranslate = [
        'none' => \Imagine\Image\ImageInterface::INTERLACE_NONE,
        'line' => \Imagine\Image\ImageInterface::INTERLACE_LINE,
        'plane' => \Imagine\Image\ImageInterface::INTERLACE_PLANE,
        'partition' => \Imagine\Image\ImageInterface::INTERLACE_PARTITION,
    ];

    /**
     * Translate dictionary for dither method
     *
     * @var array
     */
    public static $ditherKeyTranslate = [];

    /**
     * Translate dictionary for composite modes. set in constructor if driver is imagick.
     *
     * @var array
     */
    public static $compositeKeyTranslate = [];

    /**
     * Translate dictionary for translating crafts built in position constants into relative format (width/height offset)
     *
     * @var array
     */
    public static $craftPositionTranslate = [
        'top-left' => '0% 0%',
        'top-center' => '50% 0%',
        'top-right' => '100% 0%',
        'center-left' => '0% 50%',
        'center-center' => '50% 50%',
        'center-right' => '100% 50%',
        'bottom-left' => '0% 100%',
        'bottom-center' => '50% 100%',
        'bottom-right' => '100% 100%'
    ];


    // Constructor
    // =========================================================================

    public function __construct($config = [])
    {
        parent::__construct($config);

        // Detect image driver 
        self::detectImageDriver();

        // Set up imagick specific constant aliases
        if (self::$imageDriver === 'imagick') {
            self::$compositeKeyTranslate['blend'] = \Imagick::COMPOSITE_BLEND;
            self::$compositeKeyTranslate['darken'] = \Imagick::COMPOSITE_DARKEN;
            self::$compositeKeyTranslate['lighten'] = \Imagick::COMPOSITE_LIGHTEN;
            self::$compositeKeyTranslate['modulate'] = \Imagick::COMPOSITE_MODULATE;
            self::$compositeKeyTranslate['multiply'] = \Imagick::COMPOSITE_MULTIPLY;
            self::$compositeKeyTranslate['overlay'] = \Imagick::COMPOSITE_OVERLAY;
            self::$compositeKeyTranslate['screen'] = \Imagick::COMPOSITE_SCREEN;

            self::$ditherKeyTranslate['no'] = \Imagick::DITHERMETHOD_NO;
            self::$ditherKeyTranslate['riemersma'] = \Imagick::DITHERMETHOD_RIEMERSMA;
            self::$ditherKeyTranslate['floydsteinberg'] = \Imagick::DITHERMETHOD_FLOYDSTEINBERG;
        }
    }


    // Static public Methods
    // =========================================================================

    /**
     * @return ConfigModel
     */
    public static function getConfig(): ConfigModel
    {
        return self::$transformConfig ?? new ConfigModel(Plugin::$plugin->getSettings(), null);
    }

    /**
     * Detects which image driver to use
     */
    public static function detectImageDriver(): void
    {
        $extension = mb_strtolower(Craft::$app->getConfig()->getGeneral()->imageDriver);

        if ($extension === 'gd') {
            self::$imageDriver = 'gd';
        } else if ($extension === 'imagick') {
            self::$imageDriver = 'imagick';
        } else { // autodetect
            self::$imageDriver = Craft::$app->images->getIsGd() ? 'gd' : 'imagick';
        }
    }

    /**
     * @return bool
     */
    public static function hasSupportForWebP(): bool
    {
        self::detectImageDriver();

        $config = self::getConfig();

        if ($config->useCwebp && $config->cwebpPath !== '' && file_exists($config->cwebpPath)) {
            return true;
        }

        if (self::$imageDriver === 'gd' && \function_exists('imagewebp')) {
            return true;
        }

        if (self::$imageDriver === 'imagick' && (\count(\Imagick::queryFormats('WEBP')) > 0)) {
            return true;
        }


        return false;
    }

    /**
     * @return bool
     */
    public static function hasSupportForAvif(): bool
    {
        $config = self::getConfig();

        if ($config->avifEncoderPath !== '' && file_exists($config->avifEncoderPath)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $handle
     * @param string $class
     */
    public static function registerTransformer(string $handle, string $class): void
    {
        self::$transformers[mb_strtolower($handle)] = $class;
    }

    /**
     * @param string $handle
     * @param string $class
     */
    public static function registerEffect(string $handle, string $class): void
    {
        self::$effects[mb_strtolower($handle)] = $class;
    }

    /**
     * @param string $handle
     * @param string $class
     */
    public static function registerOptimizer(string $handle, string $class): void
    {
        self::$optimizers[mb_strtolower($handle)] = $class;
    }

    /**
     * @param string $handle
     * @param string $class
     */
    public static function registerExternalStorage(string $handle, string $class): void
    {
        self::$storage[mb_strtolower($handle)] = $class;
    }

    /**
     * @param string $path
     */
    public static function registerCachedRemoteFile(string $path): void
    {
        self::$remoteImageSessionCache[] = $path;
    }

    /**
     * @param string $name
     * @param array  $transform
     */
    public static function registerNamedTransform(string $name, array $transform): void
    {
        self::$namedTransforms[$name] = $transform;
    }


    // Public Methods
    // =========================================================================

    /**
     * @param Asset|string|null $image
     * @param array|string      $transforms
     * @param array|null        $transformDefaults
     * @param array|null        $configOverrides
     *
     * @return array|TransformedImageInterface|null
     * @throws ImagerException
     */
    public function transformImage($image, $transforms, array $transformDefaults = null, array $configOverrides = null)
    {
        if (!$image) {
            return null;
        }

        // Let's handle named transforms here
        if (is_string($transforms)) {
            $processedTransforms = [];

            while (is_string($transforms)) {
                if (!isset(self::$namedTransforms[$transforms])) {
                    $msg = Craft::t('imager-x', 'There is no named transform with handle “{transformName}”.', ['transformName' => $transforms]);
                    Craft::error($msg, __METHOD__);

                    if (self::getConfig()->suppressExceptions) {
                        return null;
                    }

                    throw new ImagerException($msg);
                }

                if (in_array($transforms, $processedTransforms, true)) {
                    $msg = Craft::t('imager-x', 'There was a cyclic reference to named transform with handle “{transformName}”.', ['transformName' => $transforms]);
                    Craft::error($msg, __METHOD__);

                    if (self::getConfig()->suppressExceptions) {
                        return null;
                    }

                    throw new ImagerException($msg);
                }

                $processedTransforms[] = $transforms;
                $namedTransform = self::$namedTransforms[$transforms];
                $transforms = $namedTransform['transforms'] ?? [];
                $transformDefaults = array_merge($namedTransform['defaults'] ?? [], $transformDefaults ?? []) ?? [];
                $configOverrides = array_merge($namedTransform['configOverrides'] ?? [], $configOverrides ?? []) ?? [];
            }
        }

        // Let's figure out what our return value should be.
        $returnType = 'array';

        if (!isset($transforms[0])) {
            $transforms = [$transforms];
            $returnType = 'object';
        }

        // Create config model
        self::$transformConfig = new ConfigModel(Plugin::$plugin->getSettings(), $configOverrides);

        // Resolve any callables in base transforms
        $transforms = TransformHelpers::resolveTransforms($image, $transforms);

        // Fill missing transforms if fillTransforms is enabled
        if (self::$transformConfig->fillTransforms === true && \count($transforms) > 1) {
            $transforms = TransformHelpers::fillTransforms($transforms);
        }

        // Merge in default transform parameters
        $transforms = TransformHelpers::mergeTransforms($transforms, $transformDefaults);

        // Resolve any callables in transforms after defaults were merged in
        $transforms = TransformHelpers::resolveTransforms($image, $transforms);

        // Normalize transform parameters
        $transforms = TransformHelpers::normalizeTransforms($transforms, $image);

        // Create transformer
        if (!isset(self::$transformers[self::$transformConfig->transformer])) {
            $msg = 'Invalid transformer "'.self::$transformConfig->transformer.'".';

            if (self::$transformConfig->transformer !== 'craft' && !Plugin::getInstance()->is(Plugin::EDITION_PRO)) {
                $msg .= ' Custom transformers are only available when using the Pro edition of Imager, you need to upgrade to use this feature.';
            }

            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        /** @var TransformerInterface $transformer */
        $transformer = new self::$transformers[self::$transformConfig->transformer]();
        $transformedImages = null;

        // Do we have a debug image?
        if (self::$transformConfig->mockImage !== null) {
            $image = ImagerHelpers::getTransformableFromConfigSetting(self::$transformConfig->mockImage);
        }

        try {
            $transformedImages = $transformer->transform($image, $transforms);
        } catch (ImagerException $e) {
            // If a fallback image is defined, try to transform that instead. 
            if (self::$transformConfig->fallbackImage !== null) {
                $fallbackImage = ImagerHelpers::getTransformableFromConfigSetting(self::$transformConfig->fallbackImage);

                if ($fallbackImage) {
                    try {
                        $transformedImages = $transformer->transform($fallbackImage, $transforms);
                    } catch (ImagerException $e) {
                        if (self::$transformConfig->suppressExceptions) {
                            return null;
                        }
                        throw $e;
                    }
                }
            } else {
                if (self::$transformConfig->suppressExceptions) {
                    return null;
                }
                throw $e;
            }
        }

        // Clean up after this transform session
        self::cleanSession();
        self::$transformConfig = null;

        if ($transformedImages === null) {
            return null;
        }

        return $returnType === 'object' ? $transformedImages[0] : $transformedImages;
    }

    /**
     * Creates srcset string
     *
     * @param array|mixed $images
     * @param string      $descriptor
     *
     * @return string
     */
    public function srcset($images, string $descriptor = 'w'): string
    {
        $r = '';
        $generated = [];

        if (!\is_array($images)) {
            return '';
        }

        foreach ($images as $image) {
            switch ($descriptor) {
                case 'w':
                    if (!isset($generated[$image->getWidth()])) {
                        $r .= $image->getUrl().' '.$image->getWidth().'w, ';
                        $generated[$image->getWidth()] = true;
                    }
                    break;
                case 'h':
                    if (!isset($generated[$image->getHeight()])) {
                        $r .= $image->getUrl().' '.$image->getHeight().'h, ';
                        $generated[$image->getHeight()] = true;
                    }
                    break;
                case 'w+h':
                    $key = $image->getWidth().'x'.$image->getHeight();
                    if (!isset($generated[$key])) {
                        $r .= $image->getUrl().' '.$image->getWidth().'w '.$image->getHeight().'h, ';
                        $generated[$image->getWidth().'x'.$image->getHeight()] = true;
                    }
                    break;
            }
        }

        return $r !== '' ? substr($r, 0, -2) : '';
    }

    /**
     * Checks if asset is animated.
     *
     * An animated gif contains multiple "frames", with each frame having a header made up of:
     *  - a static 4-byte sequence (\x00\x21\xF9\x04)
     *  - 4 variable bytes
     *  - a static 2-byte sequence (\x00\x2C)
     *
     * We read through the file til we reach the end of the file, or we've found at least 2 frame headers
     *
     * @param $asset
     *
     * @return bool
     * @throws ImagerException
     */
    public function isAnimated($asset): bool
    {
        $source = new LocalSourceImageModel($asset);
        $source->getLocalCopy();

        if ($source->extension !== 'gif') {
            return false;
        }

        if (!($fh = @fopen($source->getFilePath(), 'rb'))) {
            return false;
        }

        $count = 0;

        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk, $matches);
        }

        fclose($fh);

        self::cleanSession();

        return $count > 0;
    }

    /**
     * Remove transforms for a given asset
     *
     * @param Asset $asset
     */
    public function removeTransformsForAsset(Asset $asset): void
    {
        $config = self::getConfig();

        try {
            $sourceModel = new LocalSourceImageModel($asset);
            $targetModel = new LocalTargetImageModel($sourceModel, []);

            if (strpos($targetModel->path, $config->imagerSystemPath) !== false) {
                if (is_dir($targetModel->path)) {
                    try {
                        FileHelper::clearDirectory(FileHelper::normalizePath($targetModel->path));
                        FileHelper::removeDirectory(FileHelper::normalizePath($targetModel->path));
                    } catch (\Throwable $e) {
                        Craft::error('Could not clear directory "'.$targetModel->path.'" ('.$e->getMessage().')', __METHOD__);
                    }
                }

                if (VersionHelpers::craftIs('3.5')) {
                    Craft::$app->elements->invalidateCachesForElement($asset);
                } else {
                    Craft::$app->templateCaches->deleteCachesByElementId($asset->id);
                }

                if ($sourceModel->type !== 'local' && file_exists($sourceModel->getFilePath())) {
                    FileHelper::unlink($sourceModel->getFilePath());
                }
            }
        } catch (ImagerException $e) {
            Craft::error($e->getMessage(), __METHOD__);
        }
    }

    /**
     * Clear all image transforms caches
     */
    public function deleteImageTransformCaches(): void
    {
        $path = Plugin::$plugin->getSettings()->imagerSystemPath;

        try {
            $dir = FileHelper::normalizePath($path);
            if (is_dir($dir)) {
                FileHelper::clearDirectory($dir);
            }
        } catch (\Throwable $e) {
            Craft::error('Could not clear directory "'.$path.'" ('.$e->getMessage().')', __METHOD__);
        }
    }

    /**
     * Clear all remote image caches
     */
    public function deleteRemoteImageCaches(): void
    {
        try {
            $path = Craft::$app->getPath()->getRuntimePath().'/imager/';
        } catch (Exception $e) {
            Craft::error('Could not get runtime path ('.$e->getMessage().')', __METHOD__);

            return;
        }

        try {
            $dir = FileHelper::normalizePath($path);
            if (is_dir($dir)) {
                FileHelper::clearDirectory($dir);
            }
        } catch (\Throwable $e) {
            Craft::error('Could not clear directory "'.$path.'" ('.$e->getMessage().')', __METHOD__);
        }
    }

    /**
     * Clears any remote images downloaded during session if `cacheRemoteFiles` is `false`
     */
    public static function cleanSession(): void
    {
        $config = self::getConfig();

        if (!$config->cacheRemoteFiles && \count(self::$remoteImageSessionCache) > 0) {
            foreach (self::$remoteImageSessionCache as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }
}
