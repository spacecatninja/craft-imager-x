<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Transformers;

use SpaceCatNinja\ImagerX\Adapters\Adapters;
use SpaceCatNinja\ImagerX\Effects\Effects;
use CraftCms\Cms\Support\Facades\Images;

use CraftCms\Cms\Asset\Elements\Asset;
use Imagine\Exception\InvalidArgumentException;

use Imagine\Gd\Image as GdImage;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\LayersInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Imagick\Image as ImagickImage;
use SpaceCatNinja\ImagerX\Adapters\ImagerAdapterInterface;
use SpaceCatNinja\ImagerX\Effects\ImagerEffectsInterface;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\Support\QueueHelpers;

use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Data\LocalSourceImageModel;
use SpaceCatNinja\ImagerX\Data\LocalTargetImageModel;
use SpaceCatNinja\ImagerX\Data\LocalTransformedImageModel;
use SpaceCatNinja\ImagerX\Data\NoopImageModel;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;
use SpaceCatNinja\ImagerX\Services\ImagerService;

use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File;
use Illuminate\Support\Facades\Log;

use function CraftCms\Cms\t;

/**
 * CraftTransformer
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class CraftTransformer implements TransformerInterface
{
    private null|Imagine|\Imagine\Imagick\Imagine $imagineInstance = null;

    private null|GdImage|ImageInterface|ImagickImage $imageInstance = null;

    public function __construct()
    {
        $this->imagineInstance = ImagerHelpers::createImagineInstance();
    }

    /**
     * Main transform method
     *
     * @throws ImagerException
     */
    public function transform(Asset|ImagerAdapterInterface|string $image, array $transforms): ?array
    {
        $config = ImagerService::getConfig();

        $sourceModel = new LocalSourceImageModel($image);

        $transformedImages = [];

        foreach ($transforms as $transform) {
            $adapterClass = app(Adapters::class)->get($sourceModel->extension);

            if ($adapterClass !== null) {
                $transformSourceModel = new LocalSourceImageModel(new $adapterClass($image, $transform['adapterParams'] ?? []));
            } else {
                $transformSourceModel = $sourceModel;
            }

            if ($config->getSetting('noop', $transform)) {
                $msg = t('Noop activated, returning “{path}”.', ['path' => $sourceModel->url], 'imager-x');
                Log::info($msg);

                $transformedImages[] = new NoopImageModel($transformSourceModel, $transform);
            } else {
                $transformedImages[] = $this->getTransformedImage($transformSourceModel, $transform);
            }
        }


        app(ImagerService::class)->postProcessTransformedImages($transformedImages);

        return $transformedImages;
    }

    // Private Methods
    // =========================================================================
    /**
     * Gets one transformed image based on source image and transform
     *
     * @throws ImagerException
     */
    private function getTransformedImage(LocalSourceImageModel $sourceModel, array $transform): ?LocalTransformedImageModel
    {
        $config = ImagerService::getConfig();

        if ($this->imagineInstance === null) {
            $msg = t('Imagine instance was not created for driver “{driver}”.', ['driver' => ImagerService::$imageDriver], 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }

        // Create target model
        $targetModel = new LocalTargetImageModel($sourceModel, $transform);

        // Set save options
        $saveOptions = $this->getSaveOptions($targetModel->extension, $transform);

        // Do transform if transform doesn't exist, cache is disabled, or cache expired
        if (ImagerHelpers::shouldCreateTransform($targetModel, $transform)) {
            // Make sure that we have a local copy.
            $sourceModel->getLocalCopy();

            // Check all the things that could go wrong(tm)
            if (!file_exists($sourceModel->getFilePath())) {
                $msg = t('Requested image “{fileName}” does not exist in path “{sourcePath}”', ['fileName' => $sourceModel->filename, 'sourcePath' => $sourceModel->path], 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }

            // PHP caches realpath() results across requests, so a folder another process deleted can still look like
            // it exists, for example after the transforms cache was cleared from the console or the utility.
            clearstatcache(true, $sourceModel->path);
            clearstatcache(true, $targetModel->path);

            if (!realpath($sourceModel->path)) {
                $msg = t('Source folder “{sourcePath}” does not exist', ['sourcePath' => $sourceModel->path], 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }

            if (!realpath($targetModel->path)) {
                try {
                    File::ensureDirectoryExists($targetModel->path);
                } catch (\Throwable) {
                    // ignore for now, trying to create
                }

                if (!realpath($targetModel->path)) {
                    $msg = t('Target folder “{targetPath}” does not exist and could not be created', ['targetPath' => $targetModel->path], 'imager-x');
                    Log::error($msg);
                    throw new ImagerException($msg);
                }
            }

            try {
                $targetPathIsWriteable = File::isWritable($targetModel->path);
            } catch (\Throwable) {
                $targetPathIsWriteable = false;
            }

            if ($targetModel->path && !$targetPathIsWriteable) {
                $msg = t('Target folder “{targetPath}” is not writeable', ['targetPath' => $targetModel->path], 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }

            if (!Images::checkMemoryForImage($sourceModel->getFilePath())) {
                $msg = t('Not enough memory available to perform this image operation.', category: 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }

            // Create the imageInstance. only once if reuse is enabled, or always
            if ($this->imageInstance === null || !$config->getSetting('instanceReuseEnabled', $transform)) {
                try {
                    $this->imageInstance = $this->imagineInstance->open($sourceModel->getFilePath());
                } catch (\Throwable $throwable) {
                    // A downloaded file that can't be opened as an image would otherwise be kept in the source cache
                    if ($sourceModel->type === 'remoteurl') {
                        @unlink($sourceModel->getFilePath());
                    }

                    $msg = t('An error occured when trying to open image: '.$throwable->getMessage(), category: 'imager-x');
                    Log::error($msg);
                    throw new ImagerException($msg);
                }
            }

            $animated = false;

            // Check if this is an animated gif, and we're using Imagick
            if ($sourceModel->extension === 'gif' && ImagerService::$imageDriver !== 'gd' && $this->imageInstance->layers()) {
                $animated = true;
            }

            // Run tranforms, either on each layer of an animated gif, or on the whole image.
            if ($animated) {
                if ($this->imageInstance->layers()) {
                    $this->imageInstance->layers()->coalesce();
                }

                // We need to create a new image instance with the target size, or letterboxing will be wrong.
                $originalSize = $this->imageInstance->getSize();
                $resizeSize = ImagerHelpers::getResizeSize($originalSize, $transform, $config->getSetting('allowUpscale', $transform));
                $this->assertWithinPixelLimit($resizeSize, $originalSize);
                $layers = $this->imageInstance->layers() ?? [];
                $gif = $this->imagineInstance->create($resizeSize);

                if ($gif->layers()) {
                    $gif->layers()->remove(0);
                }

                [$startFrame, $endFrame, $interval] = $this->getFramesVars($layers, $transform);

                for ($i = $startFrame; $i <= $endFrame; $i += $interval) {
                    if (isset($layers[$i])) {
                        $layer = $layers[$i];
                        $this->transformLayer($layer, $transform, $sourceModel->extension);
                        $gif->layers()->add($layer);
                    }
                }

                $this->imageInstance = $gif;
            } else {
                $this->transformLayer($this->imageInstance, $transform, $sourceModel->extension);
            }

            // If Image Driver is imagick and removeMetadata is true, remove meta data
            if (ImagerService::$imageDriver === 'imagick' && $config->getSetting('removeMetadata', $transform)) {
                ImagerHelpers::processMetaData($this->imageInstance, $transform);
            }

            // Convert the image to RGB before converting to webp/saving
            if ($config->getSetting('convertToRGB', $transform)) {
                $this->imageInstance->usePalette(new RGB());
            }

            $customEncoders = $config->getSetting('customEncoders', $transform);

            // Save the transform
            if (isset($customEncoders[$targetModel->extension])) {
                $this->saveWithCustomEncoder($customEncoders[$targetModel->extension], $this->imageInstance, $targetModel->getFilePath(), $sourceModel->extension, $transform);
            } else {
                $this->imageInstance->save($targetModel->getFilePath(), $saveOptions);
            }

            if (!$config->getSetting('instanceReuseEnabled', $transform)) {
                $this->imageInstance->__destruct();
            }

            $targetModel->isNew = true;
        }

        // create LocalTransformedImageModel for transformed image
        return new LocalTransformedImageModel($targetModel, $sourceModel, $transform);
    }

    /**
     * Apply transforms to an image or layer.
     *
     * @throws ImagerException
     */
    private function transformLayer(ImagickImage|ImageInterface|GdImage &$layer, array $transform, string $sourceExtension): void
    {
        $config = ImagerService::getConfig();

        // Apply any pre resize filters
        if (isset($transform['preEffects'])) {
            $this->applyEffects($layer, $transform['preEffects']);
        }

        if (isset($transform['trim'])) {
            $this->trim($layer, $transform['trim']);
        }

        try {
            // Get size and crop information
            $originalSize = $layer->getSize();
            $cropSize = ImagerHelpers::getCropSize($originalSize, $transform, $config->getSetting('allowUpscale', $transform));
            $resizeSize = ImagerHelpers::getResizeSize($originalSize, $transform, $config->getSetting('allowUpscale', $transform));
            $this->assertWithinPixelLimit($resizeSize, $originalSize);
            $this->assertWithinPixelLimit($cropSize, $originalSize);
            $filterMethod = $this->getFilterMethod($transform);

            // Do the resize
            $layer->resize($resizeSize, $filterMethod);

            // Do the crop
            if (!isset($transform['mode']) || $transform['mode'] === 'crop' || $transform['mode'] === 'croponly') {
                $cropPoint = ImagerHelpers::getCropPoint($resizeSize, $cropSize, $config->getSetting('position', $transform));
                $layer->crop($cropPoint, $cropSize);
            }
        } catch (\Throwable $throwable) {
            throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }

        // Apply post resize effects
        if (isset($transform['effects'])) {
            $this->applyEffects($layer, $transform['effects']);
        }

        // Letterbox, add padding
        if (isset($transform['mode']) && mb_strtolower($transform['mode']) === 'letterbox') {
            $this->applyLetterbox($layer, $transform, $originalSize);
        }

        // Interlace if true
        if ($config->getSetting('interlace', $transform)) {
            $interlaceVal = $config->getSetting('interlace', $transform);

            if (\is_string($interlaceVal)) {
                $layer->interlace(ImagerService::$interlaceKeyTranslate[$interlaceVal]);
            } else {
                $layer->interlace(ImagerService::$interlaceKeyTranslate['line']);
            }
        }

        // Apply watermark if enabled
        if (isset($transform['watermark'])) {
            $this->applyWatermark($layer, $transform['watermark']);
        }

        // Apply background color if enabled and applicable
        if (($sourceExtension !== 'jpg') && ($config->getSetting('bgColor', $transform) !== '')) {
            $this->applyBackgroundColor($layer, $config->getSetting('bgColor', $transform));
        }

        // Add padding
        if (isset($transform['pad'])) {
            $this->applyPadding($layer, $transform, $sourceExtension, $originalSize);
        }
    }

    /**
     * Returns the filter method for resize operations
     */
    private function getFilterMethod(array $transform): string
    {
        $config = ImagerService::getConfig();

        return ImagerService::$imageDriver === 'imagick' ? ImagerService::$filterKeyTranslate[(string)$config->getSetting('resizeFilter', $transform)] : ImageInterface::FILTER_UNDEFINED;
    }

    /**
     * @throws \Throwable
     * @throws ImagerException
     */
    private function saveWithCustomEncoder(array $encoder, ImagickImage|ImageInterface|GdImage $imageInstance, string $path, string $sourceExtension, array $transform): void
    {
        if (!empty($encoder['path']) && file_exists($encoder['path'])) {
            // Save temp file
            $tempFile = $this->saveTemporaryFile($imageInstance, $sourceExtension);

            $customEncoderOptions = array_map('escapeshellarg', $transform['customEncoderOptions'] ?? []);

            $opts = array_merge([
                '{src}' => escapeshellarg($tempFile),
                '{dest}' => escapeshellarg($path),
            ], $encoder['options'], $customEncoderOptions);

            $r = [];

            foreach ($opts as $k => $v) {
                if (!str_starts_with($k, '{')) {
                    $r['{'.$k.'}'] = $v;
                } else {
                    $r[$k] = $v;
                }
            }

            $opts = $r;

            // Note: {src}, {dest} and customEncoderOptions are individually passed through escapeshellarg()
            // above. The encoder path is escaped here, and paramsString/options come from plugin config. We
            // deliberately do not wrap the whole string in escapeshellcmd() — doing so mangles the already
            // escaped arguments (e.g. paths containing spaces or shell metacharacters).
            $command = escapeshellarg($encoder['path']).' '.strtr($encoder['paramsString'], $opts).' 2>&1';
            $r = shell_exec($command);

            if (!file_exists($path)) {
                unlink($tempFile);
                $msg = t("Custom encoder failed. Output was:\n".$r."\nThe executed command was \"{$command}\"", category: 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }

            // Delete temp file
            unlink($tempFile);
        } else {
            $msg = t("Custom encoder path is missing or invalid.", category: 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }
    }

    /**
     * Save temporary file and return filename
     *
     * @throws ImagerException
     * @throws \Throwable
     */
    private function saveTemporaryFile(ImagickImage|ImageInterface|GdImage $imageInstance, string $sourceExtension): string
    {
        $tempPath = Path::runtime().DIRECTORY_SEPARATOR.'imager'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;

        // Check if the path exists. Clear PHP's realpath cache first, in case another process deleted it.
        clearstatcache(true, $tempPath);

        if (!realpath($tempPath)) {
            try {
                File::ensureDirectoryExists($tempPath);
            } catch (\Throwable) {
                // just ignore for now, trying to create
            }

            if (!realpath($tempPath)) {
                $msg = t('Temp folder “{tempPath}” does not exist and could not be created', ['tempPath' => $tempPath], 'imager-x');
                Log::error($msg);
                throw new ImagerException($msg);
            }
        }

        // Check if file format is one that can be commonly converted, else opt for png
        if (!in_array($sourceExtension, ['jpg', 'jpeg', 'png'])) {
            $sourceExtension = 'png';
        }

        $targetFilePath = $tempPath.md5(microtime()).'.'.$sourceExtension;

        $saveOptions = [
            'jpeg_quality' => 100,
            'png_compression_level' => 1,
            'flatten' => true,
        ];

        $imageInstance->save($targetFilePath, $saveOptions);

        return $targetFilePath;
    }

    /**
     * Get the save options based on extension and transform
     */
    private function getSaveOptions(string $extension, array $transform): array
    {
        $config = ImagerService::getConfig();

        return match (mb_strtolower($extension)) {
            'jpg', 'jpeg' => ['jpeg_quality' => $config->getSetting('jpegQuality', $transform)],
            'gif' => ['flatten' => false],
            'png' => ['png_compression_level' => $config->getSetting('pngCompressionLevel', $transform)],
            'webp' => ['webp_quality' => $config->getSetting('webpQuality', $transform), 'webp_imagick_options' => $config->getSetting('webpImagickOptions', $transform)],
            'avif' => ['avif_quality' => $config->getSetting('avifQuality', $transform)],
            'jxl' => ['jxl_quality' => $config->getSetting('jxlQuality', $transform)],
            default => [],
        };
    }

    /**
     * Apply letterbox to image
     *
     * @throws ImagerException
     */
    private function applyLetterbox(ImagickImage|ImageInterface|GdImage &$imageInstance, array $transform, BoxInterface $sourceSize): void
    {
        if (isset($transform['width'], $transform['height'])) {
            $config = ImagerService::getConfig();

            $letterboxDef = $config->getSetting('letterbox', $transform);

            try {
                $padding = $transform['pad'] ?? [0, 0, 0, 0];
                $padWidth = $padding[1] + $padding[3];
                $padHeight = $padding[0] + $padding[2];

                $size = new Box($transform['width'] - $padWidth, $transform['height'] - $padHeight);

                $position = new Point(
                    (int)floor(((int)$transform['width'] - $padWidth - $imageInstance->getSize()->getWidth()) / 2),
                    (int)floor(((int)$transform['height'] - $padHeight - $imageInstance->getSize()->getHeight()) / 2)
                );
            } catch (InvalidArgumentException $invalidArgumentException) {
                Log::error($invalidArgumentException->getMessage());
                throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
            }

            $palette = new RGB();
            $color = $palette->color(
                $letterboxDef['color'] ?? '#000',
                isset($letterboxDef['opacity']) ? (int)($letterboxDef['opacity'] * 100) : 0
            );

            if ($this->imagineInstance !== null) {
                $this->assertWithinPixelLimit($size, $sourceSize);
                $backgroundImage = $this->imagineInstance->create($size, $color);

                // Set palette of created image. This is necessary to avoid colors being skewed
                // when pasting an image into one with a different color palette.
                if ($config->getSetting('convertToRGB', $transform)) {
                    $this->imageInstance->usePalette(new RGB());
                } else {
                    $backgroundImage->usePalette($imageInstance->palette());
                }

                $backgroundImage->paste($imageInstance, $position);

                $imageInstance = $backgroundImage;
            }
        }
    }

    /**
     * Apply padding to image
     *
     * @throws ImagerException
     */
    private function applyPadding(ImagickImage|ImageInterface|GdImage &$imageInstance, array $transform, string $sourceExtension, BoxInterface $sourceSize): void
    {
        if (isset($transform['pad'])) {
            $config = ImagerService::getConfig();

            $bgColor = $config->getSetting('bgColor', $transform);

            if (empty($bgColor)) {
                $bgColor = ($sourceExtension !== 'jpg' ? 'transparent' : '#000');
            }

            $imageWidth = $imageInstance->getSize()->getWidth();
            $imageHeight = $imageInstance->getSize()->getHeight();
            $padding = $transform['pad'];
            $padWidth = $padding[1] + $padding[3];
            $padHeight = $padding[0] + $padding[2];

            try {
                $size = new Box($imageWidth + $padWidth, $imageHeight + $padHeight);
                $position = new Point($padding[3], $padding[0]);
            } catch (InvalidArgumentException $invalidArgumentException) {
                Log::error($invalidArgumentException->getMessage());
                throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
            }

            $palette = new RGB();

            $color = $bgColor === 'transparent' ? $palette->color('#000', 0) : $palette->color($bgColor);

            if ($this->imagineInstance !== null) {
                $this->assertWithinPixelLimit($size, $sourceSize);
                $backgroundImage = $this->imagineInstance->create($size, $color);
                $backgroundImage->paste($imageInstance, $position);
                $imageInstance = $backgroundImage;
            }
        }
    }

    /**
     * Apply background color to image when converting from transparent to non-transparent
     *
     * @throws ImagerException
     */
    private function applyBackgroundColor(ImagickImage|ImageInterface|GdImage &$imageInstance, string $bgColor): void
    {
        $palette = new RGB();

        $color = $bgColor === 'transparent' ? $palette->color('#000', 0) : $palette->color($bgColor);

        try {
            $topLeft = new Point(0, 0);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Log::error($invalidArgumentException->getMessage());
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        if ($this->imagineInstance !== null) {
            $backgroundImage = $this->imagineInstance->create($imageInstance->getSize(), $color);
            $backgroundImage->paste($imageInstance, $topLeft);
            $imageInstance = $backgroundImage;
        }
    }

    /**
     * Apply watermark to image
     *
     * @throws ImagerException
     */
    private function applyWatermark(ImagickImage|ImageInterface|GdImage $imageInstance, array $watermark): void
    {
        if (!isset($watermark['image'])) {
            $msg = t('Watermark image property not set', category: 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }

        if (!isset($watermark['width'], $watermark['height'])) {
            $msg = t('Watermark image size is not set', category: 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }

        if ($this->imagineInstance === null) {
            $msg = t('Imagine instance was not created for driver “{driver}”.', ['driver' => ImagerService::$imageDriver], 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }

        $sourceModel = new LocalSourceImageModel($watermark['image']);
        $sourceModel->getLocalCopy();

        $watermarkInstance = $this->imagineInstance->open($sourceModel->getFilePath());

        try {
            $watermarkBox = new Box($watermark['width'], $watermark['height']);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Log::error($invalidArgumentException->getMessage());
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        $watermarkInstance->resize($watermarkBox, ImageInterface::FILTER_UNDEFINED);

        if (isset($watermark['position'])) {
            $position = $watermark['position'];

            if (isset($position['top'])) {
                $posY = (int)$position['top'];
            } elseif (isset($position['bottom'])) {
                $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - (int)$position['bottom'];
            } else {
                $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - 10;
            }

            if (isset($position['left'])) {
                $posX = (int)$position['left'];
            } elseif (isset($position['right'])) {
                $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - (int)$position['right'];
            } else {
                $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - 10;
            }
        } else {
            $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - 10;
            $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - 10;
        }

        try {
            $positionPoint = new Point($posX, $posY);
        } catch (InvalidArgumentException $invalidArgumentException) {
            Log::error($invalidArgumentException->getMessage());
            throw new ImagerException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException);
        }

        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $watermarkInstance */
            $watermarkImagick = $watermarkInstance->getImagick();

            if (isset($watermark['opacity'])) {
                try {
                    $watermarkImagick->evaluateImage(\Imagick::EVALUATE_MULTIPLY, (float)$watermark['opacity'], \Imagick::CHANNEL_ALPHA);
                } catch (\Throwable $throwable) {
                    Log::error('Could not set watermark opacity: '.$throwable->getMessage());
                }
            }

            if (isset($watermark['blendMode'], ImagerService::$compositeKeyTranslate[$watermark['blendMode']])) {
                $blendMode = ImagerService::$compositeKeyTranslate[$watermark['blendMode']];
            } else {
                $blendMode = \Imagick::COMPOSITE_ATOP;
            }

            /** @var ImagickImage $imageInstance */
            try {
                $imageInstance->getImagick()->compositeImage($watermarkImagick, $blendMode, $positionPoint->getX(), $positionPoint->getY());
            } catch (\Throwable $throwable) {
                Log::error($throwable->getMessage());
                throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        } else { // it's GD :(
            try {
                $imageInstance->paste($watermarkInstance, $positionPoint);
            } catch (\Throwable $throwable) {
                Log::error($throwable->getMessage());
                throw new ImagerException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }
        }
    }

    /**
     * Applies effects to image.
     */
    private function applyEffects(ImagickImage|GdImage $image, array $effects): void
    {
        foreach ($effects as $effect => $value) {
            $effect = mb_strtolower($effect);

            /** @var class-string<ImagerEffectsInterface>|null $effectClass */
            $effectClass = app(Effects::class)->get($effect);

            if ($effectClass !== null) {
                $effectClass::apply($image, $value);
            }
        }
    }

    /**
     * Applies trim to image.
     *
     * @throws ImagerException
     */
    private function trim(ImagickImage|GdImage $image, float $fuzz): void
    {
        if (ImagerService::$imageDriver === 'imagick') {
            try {
                $image->getImagick()->trimImage(\Imagick::getQuantum() * $fuzz);
                $image->getImagick()->setImagePage(0, 0, 0, 0);
            } catch (\Throwable $throwable) {
                $msg = 'An error occured when trying to trim image: '.$throwable->getMessage();
                Log::error($msg);
                throw new ImagerException($msg, $throwable->getCode(), $throwable);
            }
        }
    }

    /**
     * Throws if an image of the given size would be larger than the maxTransformPixels config setting.
     * Read from config only, so that it can't be changed by a transform. Images that are no larger than
     * the source image are always allowed, since the source has already been opened at that size.
     *
     * @throws ImagerException
     */
    private function assertWithinPixelLimit(BoxInterface $size, BoxInterface $sourceSize): void
    {
        $maxPixels = ImagerService::getConfig()->maxTransformPixels;
        $pixels = $size->getWidth() * $size->getHeight();

        if ($maxPixels > 0 && $pixels > $maxPixels && $pixels > $sourceSize->getWidth() * $sourceSize->getHeight()) {
            $msg = t('The transformed image would be {width}x{height} pixels, which is more than the configured maxTransformPixels.', ['width' => $size->getWidth(), 'height' => $size->getHeight()], 'imager-x');
            Log::error($msg);
            throw new ImagerException($msg);
        }
    }

    /**
     * Get vars for animated gif frames setup
     */
    private function getFramesVars(array|LayersInterface $layers, array $transform): array
    {
        $startFrame = 0;
        $endFrame = \count($layers) - 1;
        $interval = 1;

        if (isset($transform['frames'])) {
            $framesIntArr = explode('@', $transform['frames']);

            if (\count($framesIntArr) > 1) {
                $interval = (int)$framesIntArr[1];
            }

            $framesArr = explode('-', $framesIntArr[0]);
            $startFrame = (int)$framesArr[0];

            if (\count($framesArr) > 1) {
                if ($framesArr[1] !== '*') {
                    $endFrame = (int)$framesArr[1];
                }
            } else {
                $endFrame = (int)$framesArr[0];
            }

            if ($endFrame > \count($layers) - 1) {
                $endFrame = (int)\count($layers) - 1;
            }
        }

        // A step of zero or less would never reach the end frame
        return [max(0, $startFrame), $endFrame, max(1, $interval)];
    }
}
