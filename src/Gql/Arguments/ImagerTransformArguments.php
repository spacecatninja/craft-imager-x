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
class ImagerTransformArguments extends Arguments
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
                'description' => 'Handle of named transform',
            ],
            'width' => [
                'name' => 'width',
                'type' => Type::int(),
                'description' => 'Width for the generated transform',
            ],
            'height' => [
                'name' => 'height',
                'type' => Type::int(),
                'description' => 'Height for the generated transform',
            ],
            'mode' => [
                'name' => 'mode',
                'type' => Type::string(),
                'description' => 'The mode to use for the generated transform.',
            ],
            'position' => [
                'name' => 'position',
                'type' => Type::string(),
                'description' => 'The position to use when cropping, if no focal point specified.',
            ],
            'interlace' => [
                'name' => 'interlace',
                'type' => Type::string(),
                'description' => 'The interlace mode to use for the transform',
            ],
            'quality' => [
                'name' => 'quality',
                'type' => Type::int(),
                'description' => 'The quality of the transform',
            ],
            'format' => [
                'name' => 'format',
                'type' => Type::string(),
                'description' => 'The format to use for the transform',
            ],
            'return' => [
                'name' => 'return',
                'type' => Type::string(),
                'description' => 'Data return type. Can be `url`, `base64` or `dataUri`',
            ],
        ];
    }
}
