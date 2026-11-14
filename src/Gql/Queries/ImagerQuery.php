<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Gql\Queries;

use CraftCms\Cms\Gql\Queries\Query;

use GraphQL\Type\Definition\Type;

use SpaceCatNinja\ImagerX\Gql\Arguments\ImagerTransformQueryArguments;
use SpaceCatNinja\ImagerX\Gql\Interfaces\ImagerTransformedImageInterface;
use SpaceCatNinja\ImagerX\Gql\Resolvers\ImagerResolver;

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
