<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\services;

use Craft;

use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\ArrayHelper;
use craft\helpers\FileHelper;
use Imagine\Image\ImageInterface;
use spacecatninja\imagerx\adapters\ImagerAdapterInterface;
use spacecatninja\imagerx\events\TransformImageEvent;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\helpers\QueueHelpers;
use spacecatninja\imagerx\helpers\TransformHelpers;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\ImagerX as Plugin;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\models\GenerateSettings;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\models\TransformedImageInterface;
use spacecatninja\imagerx\transformers\CraftTransformer;
use spacecatninja\imagerx\transformers\TransformerInterface;
use yii\base\Exception;

/**
 * ImagerService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class ImagerService extends Component
{
    // Events
    // =========================================================================

    /**
     * @var string
     */
    public const EVENT_BEFORE_TRANSFORM_IMAGE = 'imagerxBeforeTransformImage';
    public const EVENT_AFTER_TRANSFORM_IMAGE = 'imagerxAfterTransformImage';

    // Static Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $imageDriver = 'gd';

    /**
     * @var null|ConfigModel
     */
    public static ?ConfigModel $transformConfig = null;

    /**
     * @var null|GenerateSettings
     */
    public static ?GenerateSettings $generateConfig = null;

    /**
     * @var array
     */
    public static array $transformers = [
        'craft' => CraftTransformer::class,
    ];

    /**
     * @var array
     */
    public static array $effects = [];

    /**
     * @var array
     */
    public static array $optimizers = [];

    /**
     * @var array
     */
    public static array $storage = [];

    /**
     * @var array
     */
    public static array $adapters = [];

    /**
     * @var array
     */
    public static array $namedTransforms = [];

    /**
     * @var array
     */
    public static array $remoteImageSessionCache = [];

    /**
     * Translate dictionary for translating transform keys into filename markers
     *
     * @var array
     */
    public static array $transformKeyTranslate = [
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
        'customEncoderOptions' => 'CEOPTS',
        'adapterParams' => 'AP',
        'transformerParams' => 'TP',
    ];

    /**
     * Translate dictionary for resize method
     *
     * @var array
     */
    public static array $filterKeyTranslate = [
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
    public static array $interlaceKeyTranslate = [
        'none' => ImageInterface::INTERLACE_NONE,
        'line' => ImageInterface::INTERLACE_LINE,
        'plane' => ImageInterface::INTERLACE_PLANE,
        'partition' => ImageInterface::INTERLACE_PARTITION,
    ];

    /**
     * Translate dictionary for dither method
     *
     * @var array
     */
    public static array $ditherKeyTranslate = [];

    /**
     * Translate dictionary for composite modes. set in constructor if driver is imagick.
     *
     * @var array
     */
    public static array $compositeKeyTranslate = [];

    /**
     * Translate dictionary for translating crafts built in position constants into relative format (width/height offset)
     *
     * @var array
     */
    public static array $craftPositionTranslate = [
        'top-left' => '0% 0%',
        'top-center' => '50% 0%',
        'top-right' => '100% 0%',
        'center-left' => '0% 50%',
        'center-center' => '50% 50%',
        'center-right' => '100% 50%',
        'bottom-left' => '0% 100%',
        'bottom-center' => '50% 100%',
        'bottom-right' => '100% 100%',
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


    // Static Methods
    // =========================================================================

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
        } elseif ($extension === 'imagick') {
            self::$imageDriver = 'imagick';
        } else { // autodetect
            self::$imageDriver = Craft::$app->images->getIsGd() ? 'gd' : 'imagick';
        }
    }

    public static function hasSupportForWebP(): bool
    {
        self::detectImageDriver();

        $config = self::getConfig();

        if (isset($config->customEncoders['webp']['path']) && file_exists($config->customEncoders['webp']['path'])) {
            return true;
        }

        if (self::$imageDriver === 'gd' && \function_exists('imagewebp')) {
            return true;
        }

        return self::$imageDriver === 'imagick' && (\Imagick::queryFormats('WEBP') !== []);
    }

    public static function hasSupportForAvif(): bool
    {
        $config = self::getConfig();

        if (isset($config->customEncoders['avif']['path']) && file_exists($config->customEncoders['avif']['path'])) {
            return true;
        }

        if (self::$imageDriver === 'gd' && \function_exists('imageavif')) {
            return true;
        }

        return self::$imageDriver === 'imagick' && (\Imagick::queryFormats('AVIF') !== []);
    }

    public static function hasSupportForJxl(): bool
    {
        $config = self::getConfig();

        if (isset($config->customEncoders['jxl']['path']) && file_exists($config->customEncoders['jxl']['path'])) {
            return true;
        }

        if (self::$imageDriver === 'gd' && \function_exists('imagejxl')) {
            return true;
        }

        return self::$imageDriver === 'imagick' && (\Imagick::queryFormats('JXL') !== []);
    }

    public static function registerTransformer(string $handle, string $class): void
    {
        self::$transformers[mb_strtolower($handle)] = $class;
    }

    public static function registerEffect(string $handle, string $class): void
    {
        self::$effects[mb_strtolower($handle)] = $class;
    }

    public static function registerOptimizer(string $handle, string $class): void
    {
        self::$optimizers[mb_strtolower($handle)] = $class;
    }

    public static function registerExternalStorage(string $handle, string $class): void
    {
        self::$storage[mb_strtolower($handle)] = $class;
    }

    public static function registerAdapter(string $handle, string $class): void
    {
        self::$adapters[mb_strtolower($handle)] = $class;
    }

    public static function registerCachedRemoteFile(string $path): void
    {
        self::$remoteImageSessionCache[] = $path;
    }

    public static function registerNamedTransform(string $name, array $transform): void
    {
        self::$namedTransforms[$name] = $transform;
    }


    // Public Methods
    // =========================================================================
    /**
     * @param Asset|ImagerAdapterInterface|string|null $image
     * @param array|null                               $transformDefaults
     * @param array|null                               $configOverrides
     *
     * @throws ImagerException
     */
    public function transformImage($image, array|string $transforms, array $transformDefaults = null, array $configOverrides = null): TransformedImageInterface|array|null
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
        
        if (TransformHelpers::isQuickSyntax($transforms)) {
            $transforms = TransformHelpers::parseQuickTransforms($transforms);
            $configOverrides = array_merge(['fillTransforms' => 'auto'], $configOverrides ?? []); // override fillTransforms if not explicitely set
            $configOverrides = array_merge(['fillAttribute' => 'width'], $configOverrides); // override fillAttribute if not explicitely set
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
        if (self::$transformConfig->fillTransforms !== false && \count($transforms) > 1) {
            $transforms = TransformHelpers::fillTransforms($transforms);
        }
        
        // Merge in default transform parameters
        $transforms = TransformHelpers::mergeTransforms($transforms, $transformDefaults);

        // Resolve any callables in transforms after defaults were merged in
        $transforms = TransformHelpers::resolveTransforms($image, $transforms);

        // Normalize transform parameters
        $transforms = TransformHelpers::normalizeTransforms($transforms, $image);

        // Allow plugins to block transform or provide their own transformed images
        $transformedImages = null;

        if ($this->hasEventHandlers(static::EVENT_BEFORE_TRANSFORM_IMAGE)) {
            $event = new TransformImageEvent([
                'image' => $image,
                'transforms' => $transforms,
                'transformedImages' => null,
            ]);

            $this->trigger(self::EVENT_BEFORE_TRANSFORM_IMAGE, $event);

            // allow plugins to cancel the image transformation all together
            if (!$event->isValid) {
                return null;
            }

            // allow plugins to provide their own transformation results
            if ($event->transformedImages !== null) {
                // Limited to Pro edition, otherwise plugins could use this to
                // re-produce the custom transformers feature for Lite edition
                if (!Plugin::getInstance()?->is(Plugin::EDITION_PRO)) {
                    $msg = Craft::t('imager-x', 'Overriding transformed images is only available when using the Pro edition of Imager, you need to upgrade to use this feature.');

                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }

                $transformedImages = $event->transformedImages;
            }
        }

        if ($transformedImages === null) {
            // Create transformer
            if (!isset(self::$transformers[self::$transformConfig->transformer])) {
                $msg = 'Invalid transformer "'.self::$transformConfig->transformer.'".';

                if (self::$transformConfig->transformer !== 'craft' && !Plugin::getInstance()?->is(Plugin::EDITION_PRO)) {
                    $msg .= ' Custom transformers are only available when using the Pro edition of Imager, you need to upgrade to use this feature.';
                }

                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            /** @var TransformerInterface $transformer */
            $transformer = new self::$transformers[self::$transformConfig->transformer]();

            // Do we have a debug image?
            if (self::$transformConfig->mockImage !== null) {
                $image = ImagerHelpers::getTransformableFromConfigSetting(self::$transformConfig->mockImage);
            }

            try {
                $transformedImages = $transformer->transform($image, $transforms);
            } catch (\Throwable $throwable) {
                // If a fallback image is defined, try to transform that instead.
                if (self::$transformConfig->fallbackImage !== null) {
                    $fallbackImage = ImagerHelpers::getTransformableFromConfigSetting(self::$transformConfig->fallbackImage);

                    if ($fallbackImage) {
                        try {
                            $transformedImages = $transformer->transform($fallbackImage, $transforms);
                        } catch (\Throwable $throwable) {
                            if (self::$transformConfig->suppressExceptions) {
                                return null;
                            }

                            throw new ImagerException($throwable->getMessage());
                        }
                    }
                } else {
                    if (self::$transformConfig->suppressExceptions) {
                        return null;
                    }

                    throw new ImagerException($throwable->getMessage());
                }
            }
        }

        if ($this->hasEventHandlers(static::EVENT_AFTER_TRANSFORM_IMAGE)) {
            $this->trigger(static::EVENT_AFTER_TRANSFORM_IMAGE,
                new TransformImageEvent([
                    'image' => $image,
                    'transforms' => $transforms,
                    'transformedImages' => $transformedImages,
                ])
            );
        }

        // Clean up after this transform session
        self::cleanSession();
        self::$transformConfig = null;
        unset($transformer);

        if ($transformedImages === null) {
            return null;
        }

        return $returnType === 'object' ? ArrayHelper::firstValue($transformedImages) : $transformedImages;
    }

    /**
     * Do post-processing on locally transformed images; optimizers and external storage
     * 
     * @param array $transformedImages
     *
     * @return void
     * @throws ImagerException
     */
    public function postProcessTransformedImages(array $transformedImages): void
    {
        $config = self::getConfig();
        
        $taskCreated = false;

        // Loop over transformed images and do post optimizations and upload to external storage
        foreach ($transformedImages as $transformedImage) {
            /** @var TransformedImageInterface $transformedImage */
            if ($transformedImage->getIsNew()) {
                $isFinalVersion = ImagerX::getInstance()->optimizer->optimize($transformedImage);
                ImagerX::getInstance()->storage->store($transformedImage->getPath(), $isFinalVersion);

                if (!$isFinalVersion) {
                    $taskCreated = true;
                }
            }
        }

        // If ajax request, trigger jobs immediately
        if ($taskCreated && $config->runJobsImmediatelyOnAjaxRequests && !Craft::$app->getRequest()->isConsoleRequest && Craft::$app->getRequest()->getIsAjax() && Craft::$app->getConfig()->getGeneral()->runQueueAutomatically) {
            QueueHelpers::triggerQueueNow();
        }
    }

    /**
     * Creates srcset string
     */
    public function srcset(?array $images, string $descriptor = 'w'): string
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
     */
    public function isAnimated($asset): bool
    {
        try {
            $source = new LocalSourceImageModel($asset);
            $source->getLocalCopy();
        } catch (ImagerException $e) {
            Craft::error($e->getMessage(), __METHOD__);

            return false;
        }

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
     */
    public function removeTransformsForAsset(Asset $asset): void
    {
        $config = self::getConfig();

        try {
            $sourceModel = new LocalSourceImageModel($asset);
            $targetModel = new LocalTargetImageModel($sourceModel, []);

            if (str_contains($targetModel->path, $config->imagerSystemPath)) {
                if (is_dir($targetModel->path)) {
                    try {
                        FileHelper::clearDirectory(FileHelper::normalizePath($targetModel->path));
                        FileHelper::removeDirectory(FileHelper::normalizePath($targetModel->path));
                    } catch (\Throwable $throwable) {
                        Craft::error('Could not clear directory "'.$targetModel->path.'" ('.$throwable->getMessage().')', __METHOD__);
                    }
                }

                Craft::$app->elements->invalidateCachesForElement($asset);

                if ($sourceModel->type !== 'local' && file_exists($sourceModel->getFilePath())) {
                    FileHelper::unlink($sourceModel->getFilePath());
                }
            }
        } catch (ImagerException $imagerException) {
            Craft::error($imagerException->getMessage(), __METHOD__);
        }
    }

    /**
     * Clear all image transforms caches
     */
    public function deleteImageTransformCaches(): void
    {
        $path = self::getConfig()->imagerSystemPath;

        try {
            $dir = FileHelper::normalizePath($path);
            if (is_dir($dir)) {
                FileHelper::clearDirectory($dir);
            }
        } catch (\Throwable $throwable) {
            Craft::error('Could not clear directory "'.$path.'" ('.$throwable->getMessage().')', __METHOD__);
        }
    }

    /**
     * Clear all remote image caches
     */
    public function deleteRemoteImageCaches(): void
    {
        try {
            $path = Craft::$app->getPath()->getRuntimePath().'/imager/';
        } catch (Exception $exception) {
            Craft::error('Could not get runtime path ('.$exception->getMessage().')', __METHOD__);

            return;
        }

        try {
            $dir = FileHelper::normalizePath($path);
            if (is_dir($dir)) {
                FileHelper::clearDirectory($dir);
            }
        } catch (\Throwable $throwable) {
            Craft::error('Could not clear directory "'.$path.'" ('.$throwable->getMessage().')', __METHOD__);
        }
    }

    /**
     * Clears any remote images downloaded during session if `cacheRemoteFiles` is `false`
     */
    public static function cleanSession(): void
    {
        $config = self::getConfig();

        if (!$config->cacheRemoteFiles && self::$remoteImageSessionCache !== []) {
            foreach (self::$remoteImageSessionCache as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

}
