<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\models;

use craft\base\Model;

use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\helpers\FileHelper;
use spacecatninja\imagerx\services\ImagerService;

class ConfigModel extends Settings
{
    /**
     * @var string
     */
    private string $configOverrideString = '';

    /**
     * TransformSettings constructor.
     *
     * @param Settings|Model $settings
     * @param array|null     $overrides
     */
    public function __construct($settings, array $overrides = null, array $config = [])
    {
        parent::__construct($config);

        // Reset model to get overrides from config file
        foreach ($settings as $key => $value) {
            $this->$key = $value;
        }

        // Apply transform overrides
        $excludedConfigOverrideProperties = ['fillTransforms', 'fillInterval', 'fillAttribute', 'filenamePattern', 'transformer', 'optimizeType', 'safeFileFormats'];

        if ($overrides !== null) {
            foreach ($overrides as $key => $value) {
                $this->$key = $value;
                if (!\in_array($key, $excludedConfigOverrideProperties, true)) {
                    $this->addToOverrideFilestring($key, $value);
                }
            }
        }

        // Prep position value
        if (isset(ImagerService::$craftPositionTranslate[(string)$this->position])) {
            $this->position = ImagerService::$craftPositionTranslate[(string)$this->position];
        }

        $this->position = str_replace('%', '', $this->position);

        // Replace localized settings
        $localizables = ['imagerUrl'];
        
        foreach ($localizables as $localizable) {
            $this->{$localizable} = ConfigHelper::localizedValue($this->{$localizable});
        }
        
        // Replace aliases
        $parseables = ['imagerSystemPath', 'imagerUrl'];

        foreach ($parseables as $parseable) {
            $this->{$parseable} = App::parseEnv($this->{$parseable});
        }
        
        // Normalize imager system path
        $this->imagerSystemPath = FileHelper::normalizePath($this->imagerSystemPath);
    }

    /**
     * Get setting by key. If there is an override in transform, that is returned instead of the value in the model.
     *
     * @param array|null $transform
     *
     */
    public function getSetting(string $key, array $transform = null): mixed
    {
        return $transform[$key] ?? $this[$key];
    }

    /**
     * Returns config override string for this config model
     */
    public function getConfigOverrideString(): string
    {
        return $this->configOverrideString;
    }

    /**
     * Creates additional file string based on config overrides that is appended to filename
     */
    private function addToOverrideFilestring(string $key, mixed $value): void
    {
        $r = (ImagerService::$transformKeyTranslate[$key] ?? $key) . (\is_array($value) ? md5(implode('-', $value)) : $value);
        $this->configOverrideString .= '_' . str_replace('%', '', str_replace([' ', '.'], '-', $r));
    }
}
