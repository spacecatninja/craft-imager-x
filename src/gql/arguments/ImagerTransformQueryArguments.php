<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\gql\arguments;

use craft\gql\base\Arguments;
use GraphQL\Type\Definition\Type;

class ImagerTransformQueryArguments extends Arguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The asset id to transform.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'The asset url to transform.',
            ],
            'transform' => [
                'name' => 'transform',
                'type' => Type::string(),
                'description' => 'The handle of the named transform you want to generate.',
            ],
        ];
    }
}
