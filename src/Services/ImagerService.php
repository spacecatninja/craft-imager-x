<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Services;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\ImagerX\Settings;
use Craft;

use craft\base\Component;
use CraftCms\Cms\Asset\Elements\Asset;
use Imagine\Image\ImageInterface;
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Events\TransformImageEvent;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Support\FileHelper;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\Support\QueueHelpers;
use SpaceCatNinja\ImagerX\Support\TransformHelpers;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\ImagerX as Plugin;
use SpaceCatNinja\ImagerX\Data\ConfigModel;
use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use SpaceCatNinja\ImagerX\Data\LocalSourceImageModel;
use SpaceCatNinja\ImagerX\Data\LocalTargetImageModel;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;
use SpaceCatNinja\ImagerX\Transformers\Transformers;
use SpaceCatNinja\ImagerX\Transformers\TransformerInterface;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File;
use Illuminate\Support\Facades\Log;

use function CraftCms\Cms\t;

/**
 * ImagerService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
#[Singleton]
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

    /**
     * @var string
     */
    public static string $processingNamedTransform = '';


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
        return self::$transformConfig ?? new ConfigModel(app(Settings::class), null);
    }

    /**
     * Detects which image driver to use
     */
    public static function detectImageDriver(): void
    {
        $extension = mb_strtolower(Cms::config()->imageDriver);

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
    public function transformImage($image, array|string $transforms, ?array $transformDefaults = null, ?array $configOverrides = null, bool $force = false): TransformedImageInterface|array|null
    {

        if (!$image) {
            return null;
        }

        // Let's handle named transforms here
        if (is_string($transforms)) {
            self::$processingNamedTransform = $transforms;

            $processedTransforms = [];

            while (is_string($transforms)) {
                if (!isset(self::$namedTransforms[$transforms])) {
                    $msg = t('There is no named transform with handle “{transformName}”.', ['transformName' => $transforms], 'imager-x');
                    Log::error($msg);

                    if (self::getConfig()->suppressExceptions) {
                        return null;
                    }

                    throw new ImagerException($msg);
                }

                if (in_array($transforms, $processedTransforms, true)) {
                    $msg = t('There was a cyclic reference to named transform with handle “{transformName}”.', ['transformName' => $transforms], 'imager-x');
                    Log::error($msg);

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
        self::$transformConfig = new ConfigModel(app(Settings::class), $configOverrides);
        
        // If we're forcing a transform, set cacheDuration to 1
        if ($force) {
            self::$transformConfig->cacheDuration = 1;
        }

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
                if (!app(ImagerX::class)->isPro()) {
                    $msg = t('Overriding transformed images is only available when using the Pro edition of Imager, you need to upgrade to use this feature.', category: 'imager-x');

                    Log::error($msg);
                    throw new ImagerException($msg);
                }

                $transformedImages = $event->transformedImages;
            }
        }

        if ($transformedImages === null) {
            // Create transformer

            $transformerHandle = self::$transformConfig->transformer;
            $isCustomTransformer = mb_strtolower($transformerHandle) !== 'craft';
            $transformerClass = !$isCustomTransformer || app(ImagerX::class)->isPro() ? app(Transformers::class)->get($transformerHandle) : null;

            if ($transformerClass === null) {
                $msg = 'Invalid transformer "'.$transformerHandle.'".';

                if ($isCustomTransformer && !app(ImagerX::class)->isPro()) {
                    $msg .= ' Custom transformers are only available when using the Pro edition of Imager, you need to upgrade to use this feature.';
                }

                Log::error($msg);
                throw new ImagerException($msg);
            }

            /** @var TransformerInterface $transformer */
            $transformer = new $transformerClass();

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

        return $returnType === 'object' ? Arr::first($transformedImages) : $transformedImages;
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
                $isFinalVersion = app(OptimizerService::class)->optimize($transformedImage);
                app(StorageService::class)->store($transformedImage->getPath(), $isFinalVersion);

                if (!$isFinalVersion) {
                    $taskCreated = true;
                }
            }
        }

        // If ajax request, trigger jobs immediately
        if ($taskCreated && $config->runJobsImmediatelyOnAjaxRequests && !Craft::$app->getRequest()->isConsoleRequest && Craft::$app->getRequest()->getIsAjax() && Cms::config()->runQueueAutomatically) {
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
            Log::error($e->getMessage());

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

            // Prefix check on normalized paths (not a substring match) so a traversed path can't
            // pass by merely containing the base path somewhere in the middle.
            if (FileHelper::isWithinBase($targetModel->path, $config->imagerSystemPath)) {
                if (is_dir($targetModel->path)) {
                    try {
                        File::cleanDirectory(File::normalizePath($targetModel->path));
                        File::deleteDirectory(File::normalizePath($targetModel->path));
                    } catch (\Throwable $throwable) {
                        Log::error('Could not clear directory "'.$targetModel->path.'" ('.$throwable->getMessage().')');
                    }
                }

                Craft::$app->elements->invalidateCachesForElement($asset);

                if ($sourceModel->type !== 'local' && file_exists($sourceModel->getFilePath())) {
                    File::delete($sourceModel->getFilePath());
                }
            }
        } catch (ImagerException $imagerException) {
            Log::error($imagerException->getMessage());
        }
    }

    /**
     * Clear all image transforms caches
     */
    public function deleteImageTransformCaches(): void
    {
        $path = self::getConfig()->imagerSystemPath;

        try {
            $dir = File::normalizePath($path);
            if (is_dir($dir)) {
                File::cleanDirectory($dir);
            }
        } catch (\Throwable $throwable) {
            Log::error('Could not clear directory "'.$path.'" ('.$throwable->getMessage().')');
        }
    }

    /**
     * Clear all remote image caches
     */
    public function deleteRemoteImageCaches(): void
    {
        try {
            $path = Path::runtime().'/imager/';
        } catch (\Throwable $exception) {
            Log::error('Could not get runtime path ('.$exception->getMessage().')');

            return;
        }

        try {
            $dir = File::normalizePath($path);
            if (is_dir($dir)) {
                File::cleanDirectory($dir);
            }
        } catch (\Throwable $throwable) {
            Log::error('Could not clear directory "'.$path.'" ('.$throwable->getMessage().')');
        }
    }

    /**
     * Clears any remote images downloaded during session if `cacheRemoteFiles` is `false`
     */
    public static function cleanSession(): void
    {
        $config = self::getConfig();

        // Reset memoized named transform value
        self::$processingNamedTransform = '';

        // Clears any remote images downloaded during session if `cacheRemoteFiles` is `false`
        if (!$config->cacheRemoteFiles && self::$remoteImageSessionCache !== []) {
            foreach (self::$remoteImageSessionCache as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

}
