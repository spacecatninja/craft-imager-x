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

use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use SpaceCatNinja\ImagerX\Services\NamedTransforms;
use Illuminate\Container\Attributes\Scoped;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\Volumes;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Queries\ElementQuery;
use CraftCms\Cms\Asset\Data\Volume;

use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Support\FieldHelpers;
use SpaceCatNinja\ImagerX\Support\TransformHelpers;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Jobs\TransformJob;

use CraftCms\Cms\Support\Typecast;
use Illuminate\Support\Facades\Log;

use function CraftCms\Cms\t;

/**
 * GenerateService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
#[Scoped]
class GenerateService
{
    /**
     * @var array Keys for transform jobs that have already been queued during this request.
     */
    private array $queuedTransformJobs = [];

    /**
     * @param array|null $transforms
     */
    public function generateByUtility(array $volumeIds, bool $useConfig = true, ?array $transforms = null): void
    {
        $volumesConfig = app(GenerateSettings::class)->volumes ?? [];

        foreach ($volumeIds as $volumeId) {
            $volume = Volumes::getVolumeById((int)$volumeId);

            if (!$volume) {
                Log::error(sprintf('Couldn\'t find volume with ID %s.', $volumeId));
                continue;
            }

            if ($useConfig && isset($volumesConfig[$volume->handle]) && !empty($volumesConfig[$volume->handle])) {
                $transforms = $volumesConfig[$volume->handle];
            }

            if (empty($transforms)) {
                Log::error(sprintf('Couldn\'t find any transforms for volume with ID %s.', $volumeId));
                continue;
            }

            $assets = Asset::find()
                ->volumeId($volumeId)
                ->kind('image')
                ->includeSubfolders(true)
                ->limit(null)
                ->all();

            foreach ($assets as $asset) {
                if (self::shouldTransformElement($asset)) {
                    $this->createTransformJob($asset, $transforms);
                }
            }
        }
    }

    /**
     * @param Asset|ElementInterface $asset
     */
    public function processAssetByVolumes(ElementInterface|Asset $asset): void
    {
        $volumesConfig = app(GenerateSettings::class)->volumes;

        if (empty($volumesConfig)) {
            return;
        }

        /** @var Volume $volume */
        try {
            $volume = $asset->getVolume();
        } catch (\Throwable) {
            return;
        }

        $volumeHandle = $volume->handle;

        if (!isset($volumesConfig[$volumeHandle])) {
            return;
        }

        $volumeConfig = $volumesConfig[$volumeHandle];

        if (is_string($volumeConfig)) {
            $volumeConfig = [$volumeConfig];
        }

        if (!is_array($volumeConfig) || $volumeConfig === []) {
            return;
        }

        $this->createTransformJob($asset, $volumeConfig);
    }

    /**
     * @param Element|ElementInterface $element
     */
    public function processElementByElements(ElementInterface|Element $element): void
    {
        $elementsConfig = app(GenerateSettings::class)->elements;

        if (empty($elementsConfig)) {
            return;
        }

        // Check if any of the defined element configs are of this element type
        foreach ($elementsConfig as $config) {
            /** @var Element|null $elementType */
            $elementType = $config['elementType'] ?? null;
            $fields = $config['fields'] ?? null;
            $criteria = $config['criteria'] ?? null;
            $transforms = $config['transforms'] ?? null;
            $limit = $config['limit'] ?? null;

            if ($elementType && $element instanceof $elementType && is_array($fields) && is_array($transforms) && $fields !== [] && $transforms !== []) {
                // Check if criteria matches
                if ($criteria && is_array($criteria)) {
                    $query = $elementType::find();
                    $criteria['id'] = $element->getId();

                    if (!app(GenerateSettings::class)->generateOnlyForLiveElements) {
                        $criteria['status'] = null;
                    }

                    if (app(GenerateSettings::class)->generateForDrafts) {
                        $criteria['drafts'] = true;
                    }

                    if (!isset($criteria['siteId']) && !isset($criteria['site'])) {
                        $criteria['siteId'] = $element->siteId;
                    }

                    Typecast::configure($query, $criteria);

                    if ($query->count() === 0) {
                        continue;
                    }
                }

                // get all the assets from the fields
                $assets = [];

                foreach ($fields as $fieldHandle) {
                    $fields = FieldHelpers::getFieldsInElementByHandle($element, $fieldHandle);
                    [$offset, $limit] = FieldHelpers::getOffsetAndLimitFromFieldHandle($fieldHandle);

                    if (is_array($fields)) {
                        foreach ($fields as $field) {
                            if ($field instanceof ElementQuery) {
                                $query = clone($field);
                                
                                if ($offset !== 0) {
                                    $query->offset($offset);
                                }
                                
                                if ($limit !== null) {
                                    $query->limit($limit);
                                }
                        
                                $assets[] = $query->all();
                            }
                        }
                    }
                }

                if ($assets !== []) {
                    $assets = array_merge(...$assets);
                }

                // transform assets
                foreach ($assets as $asset) {
                    if (self::shouldTransformElement($asset)) {
                        $this->createTransformJob($asset, $transforms);
                    }
                }
            }
        }
    }

    /**
     * @param Element|ElementInterface $element
     */
    public function processElementByFields(ElementInterface|Element $element): void
    {
        $fieldsConfig = app(GenerateSettings::class)->fields;

        if (empty($fieldsConfig)) {
            return;
        }

        foreach ($fieldsConfig as $fieldHandle => $transforms) {
            $fields = FieldHelpers::getFieldsInElementByHandle($element, $fieldHandle);
            [$offset, $limit] = FieldHelpers::getOffsetAndLimitFromFieldHandle($fieldHandle);
            
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if ($field instanceof ElementQuery) {
                        $query = clone($field);
                        
                        if ($offset !== 0) {
                            $query->offset($offset);
                        }
                        
                        if ($limit !== null) {
                            $query->limit($limit);
                        }
                        
                        $assets = $query->all();

                        foreach ($assets as $asset) {
                            if (self::shouldTransformElement($asset)) {
                                $this->createTransformJob($asset, $transforms);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Element|ElementInterface $element
     *
     * @return bool
     */
    public function shouldGenerateByVolumes(ElementInterface|Element $element): bool
    {
        return self::shouldTransformElement($element);
    }

    /**
     * @param Element|ElementInterface $element
     *
     * @return bool
     */
    public function shouldGenerateByElements(ElementInterface|Element $element): bool
    {
        $elementsConfig = app(GenerateSettings::class)->elements;

        if (empty($elementsConfig)) {
            return false;
        }

        // Check if any of the defined element configs are of this element type
        foreach ($elementsConfig as $config) {
            if ($element instanceof $config['elementType']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Element|ElementInterface $element
     *
     * @return bool
     */
    public function shouldGenerateByFields(ElementInterface|Element $element): bool
    {
        $elementsConfig = app(GenerateSettings::class)->fields;
        return !empty($elementsConfig);
    }

    /**
     * @param Asset|ElementInterface $asset
     * @param array $transforms
     */
    public function createTransformJob(ElementInterface|Asset $asset, array $transforms, bool $force = false): void
    {
        // Skip identical jobs that have already been queued during this request, ie when element
        // save events are fired once per site in multi-site installs (see issue #307)
        $jobKey = $asset->id.':'.md5(json_encode($transforms)).':'.($force ? '1' : '0');

        if (isset($this->queuedTransformJobs[$jobKey])) {
            Log::info('Skipped duplicate transform job for asset with id '.$asset->id);
            return;
        }

        dispatch(new TransformJob(
            assetId: $asset->id,
            transforms: $transforms,
            force: $force,
            description: I18N::prep('Generating transforms for asset "{filename}" (ID {id})', ['filename' => $asset->filename, 'id' => $asset->id], 'imager-x'),
        ));

        $this->queuedTransformJobs[$jobKey] = true;

        Log::info('Created transform job for asset with id ' . $asset->id);
    }

    /**
     * @param Asset|ElementInterface $asset
     * @param array $transforms
     */
    public function generateTransformsForAsset(ElementInterface|Asset $asset, array $transforms, bool $force = false): void
    {
        if (self::shouldTransformElement($asset)) {
            foreach ($transforms as $transform) {
                if (TransformHelpers::isQuickSyntax($transform)) {
                    try {
                        $transformedImages = app(ImagerService::class)->transformImage($asset, $transform, null, ['optimizeType' => 'runtime'], $force);
                        unset($transformedImages);
                    } catch (ImagerException $imagerException) {
                        $msg = t('An error occured when trying to auto generate transforms for asset with id “{assetId}“ and quick transform “{transform}”: {message}', ['assetId' => $asset->id, 'transform' => print_r($transform, true), 'message' => $imagerException->getMessage()], 'imager-x');
                        Log::error($msg);
                    }
                } elseif (app(NamedTransforms::class)->has($transform)) {
                    $namedTransform = app(NamedTransforms::class)->get($transform);

                    try {
                        $transformedImages = app(ImagerService::class)->transformImage($asset, $transform, null, ['optimizeType' => 'runtime'], $force);

                        if ($transformedImages && isset($namedTransform['generateFlags']) && is_array($namedTransform['generateFlags'])) {
                            $this->processGenerateFlags($transformedImages, $namedTransform['generateFlags']);
                        }

                        unset($transformedImages);
                    } catch (ImagerException $imagerException) {
                        $msg = t('An error occured when trying to auto generate transforms for asset with id “{assetId}“ and transform “{transform}”: {message}', ['assetId' => $asset->id, 'transform' => print_r($transform, true), 'message' => $imagerException->getMessage()], 'imager-x');
                        Log::error($msg);
                    }
                } else {
                    $msg = t('Unknown transform type “{transform}” could not be found', ['transform' => $transform], 'imager-x');
                    Log::error($msg);
                }
            }
        }
    }

    public function processGenerateFlags($transformedImages, $flags): void
    {
        if (!is_array($transformedImages)) {
            $transformedImages = [$transformedImages];
        }

        /** @var \SpaceCatNinja\ImagerX\Data\BaseTransformedImageModel $transformedImage */
        foreach ($transformedImages as $transformedImage) {
            foreach ($flags as $flag) {
                switch ($flag) {
                    case 'blurhash':
                        $transformedImage->getBlurhash();
                        break;
                    case 'palette':
                        app(ImagerColorService::class)->getColorPalette($transformedImage);
                        break;
                    case 'dominantColor':
                        app(ImagerColorService::class)->getDominantColor($transformedImage);
                        break;
                }
            }
        }
    }

    /**
     * @param Asset|Element|ElementInterface $element
     *
     * @return bool
     */
    public static function shouldTransformElement(ElementInterface|Element|Asset $element): bool
    {
        return $element instanceof Asset && \in_array(strtolower($element->getExtension()), ImagerService::getConfig()->safeFileFormats, true);
    }
}
