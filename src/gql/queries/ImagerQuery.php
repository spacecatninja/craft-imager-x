<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\gql\queries;

use craft\gql\base\Query;

use GraphQL\Type\Definition\Type;

use spacecatninja\imagerx\gql\arguments\ImagerTransformQueryArguments;
use spacecatninja\imagerx\gql\interfaces\ImagerTransformedImageInterface;
use spacecatninja\imagerx\gql\resolvers\ImagerResolver;

class ImagerQuery extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        return [
            'imagerTransform' => [
                'type' => Type::listOf(ImagerTransformedImageInterface::getType()),
                'args' => ImagerTransformQueryArguments::getArguments(),
                'resolve' => ImagerResolver::class . '::resolve',
                'description' => 'This query is used to query for Imager X transforms.',
            ],
        ];
    }
}
