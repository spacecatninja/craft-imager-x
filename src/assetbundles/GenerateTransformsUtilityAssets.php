<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Værsågod
 * @package   GeoMate
 * @since     3.1.0
 */
class GenerateTransformsUtilityAssets extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = '@spacecatninja/imagerx/assetbundles/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'generatetransformsutility.js',
        ];

        $this->css = [
            'generatetransformsutility.css',
        ];

        parent::init();
    }
}
