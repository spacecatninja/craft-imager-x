<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Gql\Arguments;

use CraftCms\Cms\Gql\Arguments\Arguments;
use GraphQL\Type\Definition\Type;

/**
 * Class ImagerTransformArguments
 * @package SpaceCatNinja\ImagerX\Gql\Arguments
 */
class ImagerSrcsetArguments extends Arguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return [
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'Handle for named transform to use',
            ],
        ];
    }
}
