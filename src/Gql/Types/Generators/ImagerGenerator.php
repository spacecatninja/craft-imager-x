<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Gql\Types\Generators;

use CraftCms\Cms\Gql\Contracts\GeneratorInterface;
use CraftCms\Cms\Gql\GqlEntityRegistry;
use CraftCms\Cms\Gql\TypeLoader;

use SpaceCatNinja\ImagerX\Gql\Arguments\ImagerTransformQueryArguments;
use SpaceCatNinja\ImagerX\Gql\Interfaces\ImagerTransformedImageInterface;
use SpaceCatNinja\ImagerX\Gql\Types\ImagerType;

class ImagerGenerator implements GeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        $fields = ImagerTransformedImageInterface::getFieldDefinitions();
        $args = ImagerTransformQueryArguments::getArguments();
        $typeName = self::getName();

        $type = GqlEntityRegistry::getEntity($typeName)
            ?: GqlEntityRegistry::createEntity($typeName, new ImagerType([
                'name' => $typeName,
                'args' => fn() => $args,
                'fields' => fn() => $fields,
                'description' => 'This entity has all the Imager X transform image interface fields.',
            ]));

        TypeLoader::registerType($typeName, static fn() => $type);

        return [$type];
    }

    public static function getName($context = null): string
    {
        return 'imagerx';
    }
}
