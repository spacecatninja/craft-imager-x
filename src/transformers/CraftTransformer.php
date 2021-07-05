<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\transformers;

use Craft;

use craft\base\Component;
use craft\elements\Asset;
use craft\helpers\FileHelper;

use spacecatninja\imagerx\helpers\ImagerHelpers;
use spacecatninja\imagerx\models\ConfigModel;
use spacecatninja\imagerx\models\LocalTransformedImageModel;
use spacecatninja\imagerx\models\LocalSourceImageModel;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\services\ImagerService;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\effects\ImagerEffectsInterface;
use spacecatninja\imagerx\helpers\QueueHelpers;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\models\NoopImageModel;
use spacecatninja\imagerx\models\TransformedImageInterface;

use Imagine\Gd\Image as GdImage;
use Imagine\Imagick\Image as ImagickImage;
use Imagine\Exception\InvalidArgumentException;
use Imagine\Exception\OutOfBoundsException;
use Imagine\Exception\RuntimeException;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\LayersInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;

use yii\base\ErrorException;
use yii\base\Exception;

/**
 * CraftTransformer
 *
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class CraftTransformer extends Component implements TransformerInterface
{
    private $imagineInstance = null;
    private $imageInstance = null;

    /**
     * CraftTransformer constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->imagineInstance = $this->createImagineInstance();
    }

    /**
     * Main transform method
     *
     * @param Asset|string $image
     * @param array        $transforms
     *
     * @return array|null
     *
     * @throws ImagerException
     * @throws Exception
     */
    public function transform($image, $transforms): ?array
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        $sourceModel = new LocalSourceImageModel($image);

        $transformedImages = [];

        foreach ($transforms as $transform) {
            if ($config->getSetting('noop', $transform)) {
                $msg = Craft::t('imager-x', 'Noop activated, returning “{path}”.', ['path' => $sourceModel->url]);
                Craft::info($msg, __METHOD__);
                $transformedImages[] = new NoopImageModel($sourceModel, $transform);
            } else {
                $transformedImages[] = $this->getTransformedImage($sourceModel, $transform);
            }
        }

        $taskCreated = false;

        // Loop over transformed images and do post optimizations and upload to external storage 
        foreach ($transformedImages as $transformedImage) {
            /** @var TransformedImageInterface $transformedImage */
            if ($transformedImage->getIsNew()) {
                $isFinalVersion = ImagerX::$plugin->optimizer->optimize($transformedImage);
                ImagerX::$plugin->storage->store($transformedImage->getPath(), $isFinalVersion);

                if (!$isFinalVersion) {
                    $taskCreated = true;
                }
            }
        }

        // If ajax request, trigger jobs immediately
        if ($taskCreated && $config->runJobsImmediatelyOnAjaxRequests && !Craft::$app->getRequest()->isConsoleRequest && Craft::$app->getRequest()->getIsAjax()) {
            QueueHelpers::triggerQueueNow();
        }

        return $transformedImages;
    }

    // Private Methods
    // =========================================================================

    /**
     * Gets one transformed image based on source image and transform
     *
     * @param LocalSourceImageModel $sourceModel
     * @param array                 $transform
     *
     * @return LocalTransformedImageModel|null
     *
     * @throws ImagerException
     * @throws Exception
     */
    private function getTransformedImage(LocalSourceImageModel $sourceModel, array $transform): ?LocalTransformedImageModel
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        if ($this->imagineInstance === null) {
            $msg = Craft::t('imager-x', 'Imagine instance was not created for driver “{driver}”.', ['driver' => ImagerService::$imageDriver]);
            Craft::error($msg, __METHOD__);
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
                $msg = Craft::t('imager-x', 'Requested image “{fileName}” does not exist in path “{sourcePath}”', ['fileName' => $sourceModel->filename, 'sourcePath' => $sourceModel->path]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            if (!realpath($sourceModel->path)) {
                $msg = Craft::t('imager-x', 'Source folder “{sourcePath}” does not exist', ['sourcePath' => $sourceModel->path]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            if (!realpath($targetModel->path)) {
                try {
                    FileHelper::createDirectory($targetModel->path);
                } catch (Exception $e) {
                    // ignore for now, trying to create
                }

                if (!realpath($targetModel->path)) {
                    $msg = Craft::t('imager-x', 'Target folder “{targetPath}” does not exist and could not be created', ['targetPath' => $targetModel->path]);
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            }

            try {
                $targetPathIsWriteable = FileHelper::isWritable($targetModel->path);
            } catch (ErrorException $e) {
                $targetPathIsWriteable = false;
            }

            if ($targetModel->path && !$targetPathIsWriteable) {
                $msg = Craft::t('imager-x', 'Target folder “{targetPath}” is not writeable', ['targetPath' => $targetModel->path]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            try {
                if (!Craft::$app->images->checkMemoryForImage($sourceModel->getFilePath())) {
                    $msg = Craft::t('imager-x', 'Not enough memory available to perform this image operation.');
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            } catch (ErrorException $e) {
                // Do nothing, assume we have enough memory.
            }

            // Create the imageInstance. only once if reuse is enabled, or always
            if ($this->imageInstance === null || !$config->getSetting('instanceReuseEnabled', $transform)) {
                $this->imageInstance = $this->imagineInstance->open($sourceModel->getFilePath());
            }

            $animated = false;

            // Check if this is an animated gif and we're using Imagick
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

            // Save the transform
            if ($targetModel->extension === 'webp') {
                if (ImagerService::hasSupportForWebP()) {
                    $this->saveAsWebp($this->imageInstance, $targetModel->getFilePath(), $sourceModel->extension, $saveOptions);
                } else {
                    $msg = Craft::t('imager-x', 'This version of {imageDriver} does not support the webp format, and cwebp does not seem to be configured. You should use “craft.imager.serverSupportsWebp” in your templates to test for it.', ['imageDriver' => ImagerService::$imageDriver === 'gd' ? 'GD' : 'Imagick']);
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            } elseif ($targetModel->extension === 'avif') {
                if (ImagerService::hasSupportForAvif()) {
                    $this->saveAsAvif($this->imageInstance, $targetModel->getFilePath(), $sourceModel->extension);
                } else {
                    $msg = Craft::t('imager-x', 'You have not configured support for AVIF yet.');
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
            } else {
                $this->imageInstance->save($targetModel->getFilePath(), $saveOptions);
            }

            $targetModel->isNew = true;
        }

        // create LocalTransformedImageModel for transformed image
        return new LocalTransformedImageModel($targetModel, $sourceModel, $transform);
    }

    /**
     * Apply transforms to an image or layer.
     *
     * @param GdImage|ImagickImage|ImageInterface|object $layer
     * @param array                                      $transform
     * @param string                                     $sourceExtension
     *
     * @throws ImagerException
     */
    private function transformLayer(&$layer, array $transform, string $sourceExtension): void
    {
        /** @var ConfigModel $settings */
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
            $filterMethod = $this->getFilterMethod($transform);

            // Do the resize
            if (ImagerService::$imageDriver === 'imagick' && $config->getSetting('smartResizeEnabled', $transform)) {
                /** @var ImagickImage $layer */
                $layer->smartResize($resizeSize, (bool)Craft::$app->config->general->preserveImageColorProfiles, $config->getSetting('jpegQuality', $transform));
            } else {
                $layer->resize($resizeSize, $filterMethod);
            }

            // Do the crop
            if (!isset($transform['mode']) || $transform['mode'] === 'crop' || $transform['mode'] === 'croponly') {
                $cropPoint = ImagerHelpers::getCropPoint($resizeSize, $cropSize, $config->getSetting('position', $transform));
                $layer->crop($cropPoint, $cropSize);
            }
        } catch (\Throwable $e) {
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        // Apply post resize effects
        if (isset($transform['effects'])) {
            $this->applyEffects($layer, $transform['effects']);
        }

        // Letterbox, add padding
        if (isset($transform['mode']) && mb_strtolower($transform['mode']) === 'letterbox') {
            $this->applyLetterbox($layer, $transform);
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
            $this->applyPadding($layer, $transform, $sourceExtension);
        }
    }

    /**
     * Creates the Imagine instance depending on the chosen image driver.
     *
     * @return \Imagine\Gd\Imagine|\Imagine\Imagick\Imagine|null
     */
    private function createImagineInstance()
    {
        try {
            if (ImagerService::$imageDriver === 'gd') {
                return new \Imagine\Gd\Imagine();
            }

            if (ImagerService::$imageDriver === 'imagick') {
                return new \Imagine\Imagick\Imagine();
            }
        } catch (\Throwable $e) {
            // just ignore for now
        }

        return null;
    }

    /**
     * Returns the filter method for resize operations
     *
     * @param array $transform
     *
     * @return string
     */
    private function getFilterMethod(array $transform): string
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        return ImagerService::$imageDriver === 'imagick' ? ImagerService::$filterKeyTranslate[(string)$config->getSetting('resizeFilter', $transform)] : ImageInterface::FILTER_UNDEFINED;
    }


    /**
     * Saves image as webp
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param string                                     $path
     * @param string                                     $sourceExtension
     * @param array                                      $saveOptions
     *
     * @throws ImagerException
     * @throws Exception
     */
    private function saveAsWebp($imageInstance, string $path, string $sourceExtension, array $saveOptions): void
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        if ($config->getSetting('useCwebp')) {

            // Save temp file
            $tempFile = $this->saveTemporaryFile($imageInstance, $sourceExtension);

            // Convert to webp with cwebp
            $command = escapeshellcmd($config->getSetting('cwebpPath').' '.$config->getSetting('cwebpOptions').' -q '.$saveOptions['webp_quality'].' "'.$tempFile.'" -o "'.$path.'"');
            $r = shell_exec($command);

            if (!file_exists($path)) {
                $msg = Craft::t('imager-x', 'Creation of webp with cwebp failed with error "'.$r.'". The executed command was "'.$command.'"');
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }

            // Delete temp file
            unlink($tempFile);
        } else {
            if (ImagerService::$imageDriver === 'gd') {
                /** @var GdImage $imageInstance */
                $instance = $imageInstance->getGdResource();

                if (false === /** @scrutinizer ignore-call */ \imagewebp($instance, $path, $saveOptions['webp_quality'])) {
                    $msg = Craft::t('imager-x', 'GD WebP save operation failed');
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }

                // Fix for corrupt file bug (http://stackoverflow.com/questions/30078090/imagewebp-php-creates-corrupted-webp-files)
                if (filesize($path) % 2 === 1) {
                    file_put_contents($path, "\0", FILE_APPEND);
                }
            }

            if (ImagerService::$imageDriver === 'imagick') {
                /** @var ImagickImage $imageInstance */
                $instance = $imageInstance->getImagick();

                try {
                    $instance->setImageFormat('webp');
    
                    $hasTransparency = $instance->getImageAlphaChannel();
    
                    if ($hasTransparency) {
                        $instance->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                        $instance->setBackgroundColor(new \ImagickPixel('transparent'));
                    }
    
                    $instance->setImageCompressionQuality($saveOptions['webp_quality']);
                    $imagickOptions = $saveOptions['webp_imagick_options'];
    
                    if ($imagickOptions && \count($imagickOptions) > 0) {
                        foreach ($imagickOptions as $key => $val) {
                            $instance->setOption('webp:'.$key, $val);
                        }
                    }
                } catch (\Throwable $e) {
                    $msg = Craft::t('imager-x', 'An error occured when trying to set WebP options in Imagick instance.');
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                }
    
                try {
                    $instance->writeImage($path);
                } catch (\Throwable $e) {
                    $msg = Craft::t('imager-x', 'Imageick WebP save operation failed');
                    Craft::error($msg, __METHOD__);
                    throw new ImagerException($msg);
                } 
            }
        }
    }

    /**
     * Saves image as webp
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param string                                     $path
     * @param string                                     $sourceExtension
     *
     * @throws ImagerException
     * @throws Exception
     */
    private function saveAsAvif($imageInstance, string $path, string $sourceExtension): void
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        // Save temp file
        $tempFile = $this->saveTemporaryFile($imageInstance, $sourceExtension);

        $opts = array_merge([
            '{src}' => $tempFile,
            '{dest}' => $path
        ], $config->getSetting('avifEncoderOptions'));

        $r = [];

        foreach ($opts as $k => $v) {
            if (strpos($k, '{') !== 0) {
                $r['{'.$k.'}'] = $v;
            } else {
                $r[$k] = $v;
            }
        }

        $opts = $r;

        // Convert to avif
        $command = escapeshellcmd($config->getSetting('avifEncoderPath').' '.strtr($config->getSetting('avifConvertString'), $opts));
        $r = shell_exec($command);

        if (!file_exists($path)) {
            $msg = Craft::t('imager-x', "Creation of avif failed with error:\n".$r."\nThe executed command was \"$command\"");
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        // Delete temp file
        unlink($tempFile);
    }

    /**
     * Save temporary file and return filename
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param string                                     $sourceExtension
     *
     * @return string
     *
     * @throws ImagerException
     * @throws Exception
     */
    private function saveTemporaryFile($imageInstance, string $sourceExtension): string
    {
        $tempPath = Craft::$app->getPath()->getRuntimePath().DIRECTORY_SEPARATOR.'imager'.DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR;

        // Check if the path exists
        if (!realpath($tempPath)) {
            try {
                FileHelper::createDirectory($tempPath);
            } catch (Exception $e) {
                // just ignore for now, trying to create
            }

            if (!realpath($tempPath)) {
                $msg = Craft::t('imager-x', 'Temp folder “{tempPath}” does not exist and could not be created', ['tempPath' => $tempPath]);
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg);
            }
        }

        $targetFilePath = $tempPath.md5(microtime()).'.'.$sourceExtension;

        $saveOptions = [
            'jpeg_quality' => 100,
            'png_compression_level' => 1,
            'flatten' => true
        ];

        $imageInstance->save($targetFilePath, $saveOptions);

        return $targetFilePath;
    }

    /**
     * Get the save options based on extension and transform
     *
     * @param string $extension
     * @param array  $transform
     *
     * @return array
     */
    private function getSaveOptions(string $extension, array $transform): array
    {
        /** @var ConfigModel $settings */
        $config = ImagerService::getConfig();

        switch (mb_strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                return ['jpeg_quality' => $config->getSetting('jpegQuality', $transform)];
            case 'gif':
                return ['flatten' => false];
            case 'png':
                return ['png_compression_level' => $config->getSetting('pngCompressionLevel', $transform)];
            case 'webp':
                return ['webp_quality' => $config->getSetting('webpQuality', $transform), 'webp_imagick_options' => $config->getSetting('webpImagickOptions', $transform)];
        }

        return [];
    }

    /**
     * Apply letterbox to image
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param array                                      $transform
     *
     * @throws ImagerException
     */
    private function applyLetterbox(&$imageInstance, array $transform): void
    {
        if (isset($transform['width'], $transform['height'])) { // if both isn't set, there's no need for a letterbox
            /** @var ConfigModel $settings */
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
            } catch (InvalidArgumentException $e) {
                Craft::error($e->getMessage(), __METHOD__);
                throw new ImagerException($e->getMessage(), $e->getCode(), $e);
            }

            $palette = new RGB();
            $color = $palette->color(
                $letterboxDef['color'] ?? '#000',
                isset($letterboxDef['opacity']) ? (int)($letterboxDef['opacity'] * 100) : 0
            );

            if ($this->imagineInstance !== null) {
                $backgroundImage = $this->imagineInstance->create($size, $color);
                $backgroundImage->paste($imageInstance, $position);
                $imageInstance = $backgroundImage;
            }
        }
    }

    /**
     * Apply padding to image
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param array                                      $transform
     * @param string                                     $sourceExtension
     *
     * @throws ImagerException
     */
    private function applyPadding(&$imageInstance, array $transform, string $sourceExtension): void
    {
        if (isset($transform['pad'])) {
            /** @var ConfigModel $settings */
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
            } catch (InvalidArgumentException $e) {
                Craft::error($e->getMessage(), __METHOD__);
                throw new ImagerException($e->getMessage(), $e->getCode(), $e);
            }

            $palette = new RGB();

            if ($bgColor === 'transparent') {
                $color = $palette->color('#000', 0);
            } else {
                $color = $palette->color($bgColor);
            }

            if ($this->imagineInstance !== null) {
                $backgroundImage = $this->imagineInstance->create($size, $color);
                $backgroundImage->paste($imageInstance, $position);
                $imageInstance = $backgroundImage;
            }
        }
    }

    /**
     * Apply background color to image when converting from transparent to non-transparent
     *
     * @param GdImage|ImagickImage|ImageInterface|object $imageInstance
     * @param string                                     $bgColor
     *
     * @throws ImagerException
     */
    private function applyBackgroundColor(&$imageInstance, string $bgColor): void
    {
        $palette = new RGB();

        if ($bgColor === 'transparent') {
            $color = $palette->color('#000', 0);
        } else {
            $color = $palette->color($bgColor);
        }

        try {
            $topLeft = new Point(0, 0);
        } catch (InvalidArgumentException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
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
     * @param GdImage|ImagickImage|ImageInterface $imageInstance
     * @param array                               $watermark
     *
     * @throws ImagerException
     */
    private function applyWatermark($imageInstance, array $watermark): void
    {
        if (!isset($watermark['image'])) {
            $msg = Craft::t('imager-x', 'Watermark image property not set');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if (!isset($watermark['width'], $watermark['height'])) {
            $msg = Craft::t('imager-x', 'Watermark image size is not set');
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        if ($this->imagineInstance === null) {
            $msg = Craft::t('imager-x', 'Imagine instance was not created for driver “{driver}”.', ['driver' => ImagerService::$imageDriver]);
            Craft::error($msg, __METHOD__);
            throw new ImagerException($msg);
        }

        $sourceModel = new LocalSourceImageModel($watermark['image']);
        $sourceModel->getLocalCopy();
        $watermarkInstance = $this->imagineInstance->open($sourceModel->getFilePath());

        try {
            $watermarkBox = new Box($watermark['width'], $watermark['height']);
        } catch (InvalidArgumentException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        $watermarkInstance->resize($watermarkBox, ImageInterface::FILTER_UNDEFINED);

        if (isset($watermark['position'])) {
            $position = $watermark['position'];

            if (isset($position['top'])) {
                $posY = (int)$position['top'];
            } else {
                if (isset($position['bottom'])) {
                    $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - (int)$position['bottom'];
                } else {
                    $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - 10;
                }
            }

            if (isset($position['left'])) {
                $posX = (int)$position['left'];
            } else {
                if (isset($position['right'])) {
                    $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - (int)$position['right'];
                } else {
                    $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - 10;
                }
            }
        } else {
            $posY = $imageInstance->getSize()->getHeight() - (int)$watermark['height'] - 10;
            $posX = $imageInstance->getSize()->getWidth() - (int)$watermark['width'] - 10;
        }

        try {
            $positionPoint = new Point($posX, $posY);
        } catch (InvalidArgumentException $e) {
            Craft::error($e->getMessage(), __METHOD__);
            throw new ImagerException($e->getMessage(), $e->getCode(), $e);
        }

        if (ImagerService::$imageDriver === 'imagick') {
            /** @var ImagickImage $watermarkInstance */
            $watermarkImagick = $watermarkInstance->getImagick();

            if (isset($watermark['opacity'])) {
                try {
                    $watermarkImagick->evaluateImage(\Imagick::EVALUATE_MULTIPLY, (float)$watermark['opacity'], \Imagick::CHANNEL_ALPHA);
                } catch (\Throwable $e) {
                    Craft::error('Could not set watermark opacity: '.$e->getMessage(), __METHOD__);
                }
            }

            if (isset($watermark['blendMode'], ImagerService::$compositeKeyTranslate[$watermark['blendMode']])) {
                $blendMode = ImagerService::$compositeKeyTranslate[$watermark['blendMode']];
            } else {
                $blendMode = \Imagick::COMPOSITE_ATOP;
            }

            /** @var ImagickImage $imageInstance */
            $imageInstance->getImagick()->compositeImage($watermarkImagick, $blendMode, $positionPoint->getX(), $positionPoint->getY());
        } else { // it's GD :(
            try {
                $imageInstance->paste($watermarkInstance, $positionPoint);
            } catch (\Throwable $e) {
                Craft::error($e->getMessage(), __METHOD__);
                throw new ImagerException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Applies effects to image.
     *
     * @param GdImage|ImagickImage $image
     * @param array                $effects
     */
    private function applyEffects($image, array $effects): void
    {
        foreach ($effects as $effect => $value) {
            $effect = mb_strtolower($effect);

            if (isset(ImagerService::$effects[$effect])) {
                /** @var ImagerEffectsInterface $effectClass */
                $effectClass = ImagerService::$effects[$effect];
                $effectClass::apply($image, $value);
            }
        }
    }

    /**
     * Applies trim to image.
     *
     * @param GdImage|ImagickImage $image
     * @param float                $fuzz
     *
     * @throws ImagerException
     */
    private function trim($image, float $fuzz): void
    {
        if (ImagerService::$imageDriver === 'imagick') {
            try {
                $image->getImagick()->trimImage(\Imagick::getQuantum() * $fuzz);
                $image->getImagick()->setImagePage(0, 0, 0, 0);
            } catch (\Throwable $e) {
                $msg = 'An error occured when trying to trim image: '.$e->getMessage();
                Craft::error($msg, __METHOD__);
                throw new ImagerException($msg, $e->getCode(), $e);
            }
        }
    }

    /**
     * Get vars for animated gif frames setup
     *
     * @param LayersInterface|array $layers
     * @param array                 $transform
     *
     * @return array
     */
    private function getFramesVars($layers, array $transform): array
    {
        $startFrame = 0;
        $endFrame = \count($layers) - 1;
        $interval = 1;

        if (isset($transform['frames'])) {
            $framesIntArr = explode('@', $transform['frames']);

            if (\count($framesIntArr) > 1) {
                $interval = $framesIntArr[1];
            }

            $framesArr = explode('-', $framesIntArr[0]);

            if (\count($framesArr) > 1) {
                $startFrame = $framesArr[0];
                if ($framesArr[1] !== '*') {
                    $endFrame = $framesArr[1];
                }
            } else {
                $startFrame = $endFrame = $framesArr[0];
            }

            if ($endFrame > \count($layers) - 1) {
                $endFrame = \count($layers) - 1;
            }
        }

        return [$startFrame, $endFrame, $interval];
    }
}
