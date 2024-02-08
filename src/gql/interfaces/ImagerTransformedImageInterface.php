<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\gql\interfaces;

use craft\gql\base\InterfaceType as BaseInterfaceType;

use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use GraphQL\Type\Definition\InterfaceType;

use GraphQL\Type\Definition\Type;
use spacecatninja\imagerx\gql\types\generators\ImagerGenerator;

class ImagerTransformedImageInterface extends BaseInterfaceType
{
    /**
     * @inheritdoc
     */
    public static function getTypeGenerator(): string
    {
        return ImagerGenerator::class;
    }

    public static function getType($fields = null): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::class)) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::class, new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by Imager X.',
            'resolveType' => fn(array $value) => GqlEntityRegistry::getEntity(ImagerGenerator::getName()),
        ]));

        foreach (ImagerGenerator::generateTypes() as $typeName => $generatedType) {
            TypeLoader::registerType($typeName, fn() => $generatedType);
        }

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return 'ImagerTransformedImageInterface';
    }

    /**
     * @inheritdoc
     */
    public static function getFieldDefinitions(): array
    {
        return array_merge(parent::getFieldDefinitions(), [
            'path' => [
                'name' => 'path',
                'type' => Type::string(),
                'description' => 'Path to transformed image.',
            ],
            'filename' => [
                'name' => 'filename',
                'type' => Type::string(),
                'description' => 'Filename of transformed image.',
            ],
            'extension' => [
                'name' => 'extension',
                'type' => Type::string(),
                'description' => 'Extension of transformed image.',
            ],
            'url' => [
                'name' => 'url',
                'type' => Type::string(),
                'description' => 'URL for transformed image.',
            ],
            'mimeType' => [
                'name' => 'mimeType',
                'type' => Type::string(),
                'description' => 'Mime type of transformed image.',
            ],
            'width' => [
                'name' => 'width',
                'type' => Type::int(),
                'description' => 'Width of transformed image.',
            ],
            'height' => [
                'name' => 'height',
                'type' => Type::int(),
                'description' => 'Height of transformed image.',
            ],
            'size' => [
                'name' => 'size',
                'type' => Type::int(),
                'description' => 'Size of transformed image.',
            ],
            'isNew' => [
                'name' => 'isNew',
                'type' => Type::boolean(),
                'description' => 'Indicates if the transformed image is newly created.',
            ],
        ]);
    }
}
