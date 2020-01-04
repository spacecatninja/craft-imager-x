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
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Volume;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\db\ElementQuery;
use craft\helpers\Image;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\helpers\FieldHelpers;
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
     * @param ElementInterface|Asset $asset
     */
    public function processAssetByVolumes($asset)
    {
        $volumesConfig = ImagerService::$generateConfig->volumes;

        if (empty($volumesConfig)) {
            return;
        }

        /** @var Volume $volume */
        try {
            $volume = $asset->getVolume();
        } catch (InvalidConfigException $e) {
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

        if (!is_array($volumeConfig) || count($volumeConfig) === 0) {
            return;
        }

        $this->createTransformJob($asset, $volumeConfig);
    }

    /**
     * @param ElementInterface|Element $element
     */
    public function processElementByElements($element)
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

            if ($element instanceof $config['elementType'] && is_array($fields) && is_array($transforms) && count($fields) > 0 && count($transforms) > 0) {
                // Check if criteria matches
                if ($criteria && is_array($criteria)) {
                    /** @var Query $query */
                    $query = $elementType::find();
                    $criteria['id'] = $element->id;

                    if (!ImagerService::$generateConfig->generateOnlyForLiveElements) {
                        $criteria['status'] = null;
                    }

                    if (ImagerService::$generateConfig->generateForDrafts) {
                        $criteria['drafts'] = true;
                    }

                    Craft::configure($query, $criteria);

                    if ($query->count() === 0) {
                        return;
                    }
                }

                // get all the assets from the fields
                $assets = [];

                foreach ($fields as $fieldHandle) {
                    $fields = FieldHelpers::getFieldsInElementByHandle($element, $fieldHandle);
                    
                    if (is_array($fields)) {
                        foreach ($fields as $field) {
                            if ($field instanceof ElementQuery) {
                                $assets[] = $field->all();
                            }
                        }
                    }
                }
                
                $assets = array_merge(...$assets);

                // transform assets
                foreach ($assets as $asset) {
                    if ($this->shouldTransformElement($asset)) {
                        $this->createTransformJob($asset, $transforms);
                    }
                }
            }
        }
    }

    /**
     * @param ElementInterface|Element $element
     */
    public function processElementByFields($element)
    {
        $fieldsConfig = ImagerService::$generateConfig->fields;

        if (empty($fieldsConfig)) {
            return;
        }
        
        $fieldLayout = $element->getFieldLayout();
        
        foreach ($fieldsConfig as $fieldHandle => $transforms) {
            $field = FieldHelpers::getFieldInFieldLayoutByHandle($element, $fieldLayout, $fieldHandle);
            
            if ($field && $field instanceof ElementQuery) {
                $assets = $field->all();
                
                foreach ($assets as $asset) {
                    if ($this->shouldTransformElement($asset)) {
                        $this->createTransformJob($asset, $transforms);
                    }
                }
            }
        }
    }   
    
    /**
     * @param ElementInterface|Element $element
     * @return bool
     */
    public function shouldGenerateByVolumes($element): bool
    {
        return $this->shouldTransformElement($element);
    }

    /**
     * @param ElementInterface|Element $element
     * @return bool
     */
    public function shouldGenerateByElements($element): bool
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
     * @param ElementInterface|Element $element
     * @return bool
     */
    public function shouldGenerateByFields($element): bool
    {
        $elementsConfig = ImagerService::$generateConfig->fields;

        if (empty($elementsConfig)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @param ElementInterface|Asset $asset
     * @param array $transforms
     */
    public function createTransformJob($asset, $transforms)
    {
        $queue = Craft::$app->getQueue();

        $jobId = $queue->push(new TransformJob([
            'description' => Craft::t('imager-x', 'Generating transforms for asset with id ' . $asset->id),
            'assetId' => $asset->id,
            'transforms' => $transforms,
        ]));

        Craft::info('Created transform job for asset with id ' . $asset->id . ' (job id is ' . $jobId . ')', __METHOD__);
    }

    /**
     * @param ElementInterface|Asset $asset
     * @param array $transforms
     */
    public function generateTransformsForAsset($asset, $transforms)
    {
        foreach ($transforms as $transformName) {
            if (isset(ImagerService::$namedTransforms[$transformName])) {
                try {
                    ImagerX::$plugin->imager->transformImage($asset, $transformName, null, ['optimizeType' => 'runtime']);
                } catch (ImagerException $exception) {
                    $msg = Craft::t('imager-x', 'An error occured when trying to auto generate transforms for asset with id “{assetId}“ and transform “{transformName}”: {message}', ['assetId' => $asset->id, 'transformName' => $transformName, 'message' => $exception->getMessage()]);
                    Craft::error($msg, __METHOD__);
                }
            } else {
                $msg = Craft::t('imager-x', 'Named transform with handle “{transformName}” could not be found', ['transformName' => $transformName]);
                Craft::error($msg, __METHOD__);
            }
        }
    }

    /**
     * --- Protected functions -----------------------------------------------------
     */

    /**
     * @param ElementInterface|Element|Asset $element
     * @return bool
     */
    protected function shouldTransformElement($element): bool
    {
        return $element instanceof Asset && $element->kind === 'image' && \in_array(strtolower($element->getExtension()), Image::webSafeFormats(), true);
    }
    
}
