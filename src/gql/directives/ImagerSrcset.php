<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\gql\directives;

use Craft;
use craft\gql\base\Directive;
use craft\gql\GqlEntityRegistry;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive as GqlDirective;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ResolveInfo;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\gql\arguments\ImagerSrcsetArguments;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\services\ImagerService;

/**
 * Class ImagerTransform
 * @package spacecatninja\imagerx\gql\directives
 */
class ImagerSrcset extends Directive
{
    public function __construct(array $config)
    {
        $args = &$config['args'];

        foreach ($args as &$argument) {
            $argument = new FieldArgument($argument);
        }

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public static function create(): GqlDirective
    {
        if ($type = GqlEntityRegistry::getEntity(self::class)) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(static::name(), new self([
            'name' => static::name(),
            'locations' => [
                DirectiveLocation::FIELD,
            ],
            'args' => ImagerSrcsetArguments::getArguments(),
            'description' => 'This directive is used to return a srcset from a named transform in Imager X.',
        ]));
    }

    /**
     * @inheritdoc
     */
    public static function name(): string
    {
        return 'imagerSrcset';
    }

    /**
     * @inheritdoc
     */
    public static function apply(mixed $source, mixed $value, array $arguments, ResolveInfo $resolveInfo): mixed
    {
        if ($resolveInfo->fieldName !== 'url') {
            return $value;
        }

        if (empty($arguments['handle'])) {
            return null;
        }
        
        if ($source->kind !== 'image' || !\in_array(strtolower($source->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
            return null;
        }
        
        try {
            $transformedImages = ImagerX::$plugin->imagerx->transformImage($source, $arguments['handle']);
        } catch (ImagerException $imagerException) {
            Craft::error('An error occured when trying to generate srcset in GraphQL directive: ' . $imagerException->getMessage(), __METHOD__);
            return null;
        }
        
        if ($transformedImages === null) {
            return null;
        } 
        
        return ImagerX::$plugin->imagerx->srcset($transformedImages);
    }
}
