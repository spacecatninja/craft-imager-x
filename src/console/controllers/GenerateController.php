<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\console\controllers;

use Craft;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\base\Volume;
use craft\base\VolumeInterface;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\services\GenerateService;
use spacecatninja\imagerx\services\ImagerService;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class GenerateController extends Controller
{
    /**
     * @var string Handle of volume to generate transforms for
     */
    public $volume;

    /**
     * @var int Folder ID to generate transforms for
     */
    public $folderId;

    /**
     * @var bool Enable or disable recursive handling of folders
     */
    public $recursive;

    /**
     * @var string Field to generate transforms for
     */
    public $field;

    /**
     * @var string Which transforms to generate
     */
    public $transforms;

    /**
     * @var array|VolumeInterface[] 
     */
    private $volumes = [];
    
    /**
     * @var array|FieldInterface[] 
     */
    private $fields = [];
    
    // Public Methods
    // =========================================================================

    /**
     * @param string $actionsID
     * @return array|string[]
     */
    public function options($actionsID): array
    {
        $options = parent::options($actionsID);
        
        return array_merge($options, [
            'volume',
            'folderId',
            'recursive',
            'field',
            'transforms',
        ]);
    }

    /**
     * @return array
     */
    public function optionAliases(): array
    {
        return [
            'v' => 'volume',
            'fid' => 'folderId',
            'r' => 'recursive',
            'f' => 'field',
            't' => 'transforms',
        ];
    }

    /**
     * Generates image transforms by volume/folder or fields.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        if (!ImagerX::getInstance()->is(ImagerX::EDITION_PRO)) {
            $this->error('Console commands are only available in Imager X Pro. You need to upgrade to use this awesome feature (it\'s so worth it!).');
            return ExitCode::UNAVAILABLE;
        }

        $this->transforms = trim($this->transforms);
        $this->volume = trim($this->volume);
        
        $volumeSpecified = !empty($this->volume);
        $fieldSpecified = !empty($this->field);
        
        if ($volumeSpecified && $fieldSpecified) {
            $this->error('Both volume and field is specified. That doesn\'t make sense, it\'s either or.');
            return ExitCode::NOINPUT;
        }

        if (!$volumeSpecified && !$fieldSpecified) {
            $this->error('No volume or field were specified');
            return ExitCode::NOINPUT;
        }

        if ($volumeSpecified) {
            $this->volumes = Craft::$app->getVolumes()->getAllVolumes();
            $volumeHandles = \array_map(static function ($volume) {
                /** @var Volume $volume */
                return $volume->handle;
            }, $this->volumes);
            
            if (!in_array($this->volume, $volumeHandles, true)) {
                $this->error("No volumes with handle {$this->volume} exists");
                return ExitCode::NOINPUT;
            }
        }
        
        if ($fieldSpecified) {
            $this->fields = $this->getAllFields();

            $fieldHandles = \array_map(static function ($field) {
                /** @var Field $field */
                return $field['handle'];
            }, $this->fields);
            
            if (!in_array($this->field, $fieldHandles, true)) {
                $this->error("No field with handle {$this->field} exists");
                return ExitCode::NOINPUT;
            }
        }
        
        $transforms = $this->transforms!=='' ? explode(',', $this->transforms) : [];
        
        // Get transforms from config if none were passed in
        if ($volumeSpecified && empty($transforms)) {
            $volumesTransforms = ImagerService::$generateConfig->volumes ?? null;
            
            if ($volumesTransforms && $volumesTransforms[$this->volume] && !empty($volumesTransforms[$this->volume])) {
                $transforms = $volumesTransforms[$this->volume];
            }
        }        
        
        if ($fieldSpecified && empty($transforms)) {
            $fieldTransforms = ImagerService::$generateConfig->fields ?? null;
            
            if ($fieldTransforms && $fieldTransforms[$this->field] && !empty($fieldTransforms[$this->field])) {
                $transforms = $fieldTransforms[$this->field];
            }
        }        
        
        $assets = [];
        
        if ($volumeSpecified) {
            $assets = $this->getAssetsByVolume();
        } else if ($fieldSpecified) {
            $assets = $this->getAssetsByField();
        }
        
        $assets = $this->pruneTransformableAssets($assets);
        
        if (empty($assets)) {
            $this->error("No transformable assets found");
            return ExitCode::OK;
        }
        
        $numTransforms = count($transforms);
        $total = count($assets);
        $current = 0;
        $this->stdout(sprintf('> Generating %d named transform(s) for %d image(s).', $numTransforms, $total).PHP_EOL, Console::FG_YELLOW);
        
        foreach ($assets as $asset) {
            $current++;
            $filename = $asset->filename;
            
            /*
            $pid = getmypid();
            $mem = exec("top -pid $pid -l 1 | grep $pid | awk '{print $8}'");
            */
            
            $this->stdout("    - [".($current * $numTransforms)."/".$total * $numTransforms."] ($asset->id) ".(strlen($filename) > 50 ? (substr($filename, 0, 47).'...') : $filename)." ... ");
            ImagerX::$plugin->generate->generateTransformsForAsset($asset, $transforms);
            $this->stdout('done'.PHP_EOL, Console::FG_GREEN);
        }
        
        $this->stdout("> Done.".PHP_EOL, Console::FG_YELLOW);
        return ExitCode::OK;
    }
    
    /**
     * @param string $text
     */
    public function success($text = '')
    {
        $this->stdout("$text\n", Console::FG_GREEN);
    }

    /**
     * @param string $text
     */
    public function error($text = '')
    {
        $this->stdout("$text\n", Console::FG_RED);
    }

    /**
     * @return array|Asset[]
     */
    private function getAssetsByVolume(): array
    {
        /** @var AssetQuery $query */
        $query = null;
        
        /** @var VolumeInterface|null $targetVolume */
        $targetVolume = null;
        
        foreach ($this->volumes as $volume) {
            if ($volume->handle === $this->volume) {
                $targetVolume = $volume;
                break;
            }
        }
        
        if ($targetVolume) {
            $this->success("> Volume `{$this->volume}`");
            $query = Asset::find()
                ->volume($targetVolume)
                ->kind('image')
                ->limit(null);
            
            if (!empty($this->folderId)) {
                $query->folderId($this->folderId);
            } else {
                $folderId = Craft::$app->getVolumes()->ensureTopFolder($targetVolume);
                $query->folderId($folderId);
            }
            
            $this->success($this->recursive ? '> Recursive' : '> Not recursive');
            $query->includeSubfolders((bool)$this->recursive);
        }
        
        return $query ? $query->all() : [];
    }

    /**
     * @return array
     */
    private function getAssetsByField(): array
    {
        /** @var AssetQuery $query */
        $query = null;
        
        /** @var FieldInterface|null $targetVolume */
        $targetFieldIds = [];
        
        foreach ($this->fields as $field) {
            if ($field['handle'] === $this->field) {
                $targetFieldIds[] = $field['id'];
            }
        }
        
        if (!empty($targetFieldIds)) {
            if (count($targetFieldIds)>1) {
                $this->success('> Processing ' . count($targetFieldIds) . " fields with handle `{$this->field}`");
            } else {
                $this->success("> Processing field with handle `{$this->field}`");
            }
            
            $relatedAssets = (new Query())
                ->select(['{{%elements}}.id as id', '{{%relations}}.targetId', 'fieldId', 'type'])
                ->from(['{{%relations}}'])
                ->where(['fieldId' => $targetFieldIds])
                ->andWhere(['type' => craft\elements\Asset::class])
                ->join('LEFT JOIN', '{{%elements}}', '{{%elements}}.id = {{%relations}}.targetId')
                ->groupBy('{{%relations}}.targetId')
                ->all();

            
            $assetIds = \array_map(static function ($asset) {
                return $asset['id'];
            }, $relatedAssets);
            
            $query = Asset::find()
                ->id($assetIds)
                ->kind('image')
                ->limit(null);
        }
        
        return $query ? $query->all() : [];
    }

    /**
     * @return array
     */
    private function getAllFields(): array 
    {
        return (new Query())
            ->select(['fields.id', 'fields.handle'])
            ->from(['{{%fields}} fields'])
            ->all();
    }

    /**
     * @param array $assets
     * @return array
     */
    private function pruneTransformableAssets($assets): array
    {
        $r = [];
        
        foreach ($assets as $asset) {
            if (GenerateService::shouldTransformElement($asset)) {
                $r[] = $asset;
            }
        }
        
        return $r;
    }
}
