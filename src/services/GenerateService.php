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
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\models\Volume;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\helpers\FieldHelpers;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\jobs\TransformJob;

use yii\base\InvalidConfigException;

/**
 * GenerateService Service
 *
 * @author    André Elvan
 * @package   Imager
 * @since     3.0.0
 */
class GenerateService extends Component
{
    /**
     * @param array|null $transforms
     */
    public function generateByUtility(array $volumeIds, bool $useConfig = true, array $transforms = null): void
    {
        $volumesConfig = ImagerService::$generateConfig->volumes ?? [];

        foreach ($volumeIds as $volumeId) {
            $volume = Craft::$app->volumes->getVolumeById($volumeId);

            if (!$volume) {
                Craft::error(sprintf('Couldn\'t find volume with ID %s.', $volumeId), __METHOD__);
                continue;
            }

            if ($useConfig && isset($volumesConfig[$volume->handle]) && !empty($volumesConfig[$volume->handle])) {
                $transforms = $volumesConfig[$volume->handle];
            }

            if (empty($transforms)) {
                Craft::error(sprintf('Couldn\'t find any transforms for volume with ID %s.', $volumeId), __METHOD__);
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
        $volumesConfig = ImagerService::$generateConfig->volumes;

        if (empty($volumesConfig)) {
            return;
        }

        /** @var Volume $volume */
        try {
            $volume = $asset->getVolume();
        } catch (InvalidConfigException) {
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
        $elementsConfig = ImagerService::$generateConfig->elements;

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
                    /** @var Query $query */
                    $query = $elementType::find();
                    $criteria['id'] = $element->getId();

                    if (!ImagerService::$generateConfig->generateOnlyForLiveElements) {
                        $criteria['status'] = null;
                    }

                    if (ImagerService::$generateConfig->generateForDrafts) {
                        $criteria['drafts'] = true;
                    }

                    if (!isset($criteria['siteId']) && !isset($criteria['site'])) {
                        $criteria['siteId'] = $element->siteId;
                    }

                    Craft::configure($query, $criteria);

                    if ($query->count() === 0) {
                        continue;
                    }
                }

                // get all the assets from the fields
                $assets = [];

                foreach ($fields as $fieldHandle) {
                    $fields = FieldHelpers::getFieldsInElementByHandle($element, $fieldHandle);

                    if (is_array($fields)) {
                        foreach ($fields as $field) {
                            if ($field instanceof ElementQuery) {
                                $query = clone($field);
                                $assets[] = $query->limit($limit)->all();
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
        $fieldsConfig = ImagerService::$generateConfig->fields;

        if (empty($fieldsConfig)) {
            return;
        }

        $fieldLayout = $element->getFieldLayout();

        foreach ($fieldsConfig as $fieldHandle => $transforms) {
            $field = FieldHelpers::getFieldInFieldLayoutByHandle($element, $fieldLayout, $fieldHandle);

            if ($field instanceof ElementQuery) {
                $assets = $field->all();

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
    public function shouldGenerateByVolumes(ElementInterface|Element $element): bool
    {
        return self::shouldTransformElement($element);
    }

    /**
     * @param Element|ElementInterface $element
     */
    public function shouldGenerateByElements(ElementInterface|Element $element): bool
    {
        $elementsConfig = ImagerService::$generateConfig->elements;

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
     */
    public function shouldGenerateByFields(ElementInterface|Element $element): bool
    {
        $elementsConfig = ImagerService::$generateConfig->fields;
        return !empty($elementsConfig);
    }

    /**
     * @param Asset|ElementInterface $asset
     */
    public function createTransformJob(ElementInterface|Asset $asset, array $transforms): void
    {
        $queue = Craft::$app->getQueue();

        $jobId = $queue->push(new TransformJob([
            'description' => Craft::t('imager-x', 'Generating transforms for asset "' . $asset->filename . '" (ID ' . $asset->id . ')'),
            'assetId' => $asset->id,
            'transforms' => $transforms,
        ]));

        Craft::info('Created transform job for asset with id ' . $asset->id . ' (job id is ' . $jobId . ')', __METHOD__);
    }

    /**
     * @param Asset|ElementInterface $asset
     */
    public function generateTransformsForAsset(ElementInterface|Asset $asset, array $transforms): void
    {
        if (self::shouldTransformElement($asset)) {
            foreach ($transforms as $transformName) {
                if (isset(ImagerService::$namedTransforms[$transformName])) {
                    try {
                        ImagerX::$plugin->imager->transformImage($asset, $transformName, null, ['optimizeType' => 'runtime']);
                    } catch (ImagerException $imagerException) {
                        $msg = Craft::t('imager-x', 'An error occured when trying to auto generate transforms for asset with id “{assetId}“ and transform “{transformName}”: {message}', ['assetId' => $asset->id, 'transformName' => $transformName, 'message' => $imagerException->getMessage()]);
                        Craft::error($msg, __METHOD__);
                    }
                } else {
                    $msg = Craft::t('imager-x', 'Named transform with handle “{transformName}” could not be found', ['transformName' => $transformName]);
                    Craft::error($msg, __METHOD__);
                }
            }
        }
    }

    /**
     * @param Asset|Element|ElementInterface $element
     */
    public static function shouldTransformElement(ElementInterface|Element|Asset $element): bool
    {
        return $element instanceof Asset && $element->kind === 'image' && \in_array(strtolower($element->getExtension()), ImagerService::getConfig()->safeFileFormats, true);
    }
}
