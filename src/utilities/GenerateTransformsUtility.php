<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\utilities;

use Craft;
use craft\base\Utility;

use craft\elements\Asset;
use craft\helpers\Html;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use craft\models\Volume;
use spacecatninja\imagerx\assetbundles\GenerateTransformsUtilityAssets;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\models\Settings;
use spacecatninja\imagerx\services\ImagerService;
use yii\base\InvalidConfigException;

/**
 * @author    Værsågod
 * @package   Imager X
 * @since     3.1.0
 */
class GenerateTransformsUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('imager-x', 'Generate transforms');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'generate-transforms-utility';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath(): ?string
    {
        return Craft::getAlias('@spacecatninja/imagerx/icon-generate-utility.svg');
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        /** @var Settings $settings */
        $settings = ImagerX::$plugin->getSettings();
        
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $volumeOptions = [];

        foreach ($volumes as $volume) {
            $assetsCount = Asset::find()
                ->volumeId($volume->id)
                ->kind('image')
                ->includeSubfolders(true)
                ->limit(null)
                ->count();

            $volumeOptions[] = [
                'label' => Template::raw(Html::encode($volume->name) . sprintf(' <span class=\'light\'>(%s images)</span>', $assetsCount)),
                'value' => $volume->id,
            ];
        }

        $view = Craft::$app->getView();
        $volumesCheckboxSelectHtml = $view->renderTemplate('_includes/forms/checkboxSelect', [
            'name' => 'volumes',
            'options' => $volumeOptions,
            'showAllOption' => false,
        ]);

        $namedTransforms = ImagerService::$namedTransforms;
        $namedTransformsOptions = [];
        $hasTransformWithoutDisplayName = false;

        foreach ($namedTransforms as $namedTransformKey => $namedTransformValue) {
            if (!isset($namedTransformValue['displayName'])) {
                $hasTransformWithoutDisplayName = true;
            }
            
            $namedTransformsOptions[] = [
                'label' => Html::encode($namedTransformValue['displayName'] ?? $namedTransformKey),
                'value' => $namedTransformKey,
            ];
        }

        $view = Craft::$app->getView();
        $transformsCheckboxSelectHtml = $view->renderTemplate('_includes/forms/checkboxSelect', [
            'name' => 'namedTransforms',
            'options' => $namedTransformsOptions,
            'showAllOption' => false,
        ]);

        
        try {
            Craft::$app->getView()->registerAssetBundle(GenerateTransformsUtilityAssets::class);
        } catch (InvalidConfigException) {
            return Craft::t('imager-x', 'Could not load asset bundle');
        }
        
        return Craft::$app->getView()->renderTemplate(
            'imager-x/utility/_generateTransforms',
            [
                'settings' => $settings,
                'volumesCheckboxSelectHtml' => $volumesCheckboxSelectHtml,
                'transformsCheckboxSelectHtml' => $transformsCheckboxSelectHtml,
                'showDisplayNameInfo' => $hasTransformWithoutDisplayName,
                'queueUrl' => UrlHelper::cpUrl('utilities/queue-manager'),
            ]
        );
    }
}
