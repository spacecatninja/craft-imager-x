<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Data;

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Deprecator;
use CraftCms\Cms\User\Elements\User;

/**
 * The settings in `config/craft/imager-x-generate.php`.
 */
class GenerateSettings extends Component
{
    /**
     * Craft 5 element classes that may still be used as `elementType` in the `elements` setting.
     */
    private const array LEGACY_ELEMENT_TYPES = [
        'craft\\elements\\Entry' => Entry::class,
        'craft\\elements\\Asset' => Asset::class,
        'craft\\elements\\User' => User::class,
        'craft\\elements\\Address' => Address::class,
    ];

    public bool $generateOnlyForLiveElements = false;

    public bool $generateForDrafts = false;

    public array $volumes = [];

    public array $elements = [] {
        set(array $elements) {
            $this->elements = array_map($this->normalizeElementConfig(...), $elements);
        }
    }

    public array $fields = [];

    /**
     * Replaces Craft 5 element class names with the Craft 6 ones.
     */
    private function normalizeElementConfig(mixed $config): mixed
    {
        $elementType = \is_array($config) && \is_string($config['elementType'] ?? null) ? ltrim($config['elementType'], '\\') : null;

        if ($elementType !== null && isset(self::LEGACY_ELEMENT_TYPES[$elementType])) {
            Deprecator::log(
                "imager-x-generate:elementType:$elementType",
                sprintf('`%s` was used as an `elementType` in `imager-x-generate.php`. Use `%s` instead.', $elementType, self::LEGACY_ELEMENT_TYPES[$elementType]),
            );

            $config['elementType'] = self::LEGACY_ELEMENT_TYPES[$elementType];
        }

        return $config;
    }
}
