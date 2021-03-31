<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\gql\directives;

use Craft;
use craft\gql\base\Directive;
use craft\gql\GqlEntityRegistry;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive as GqlDirective;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ResolveInfo;

use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\gql\arguments\ImagerTransformArguments;
use spacecatninja\imagerx\services\ImagerService;

/**
 * Class ImagerTransform
 * @package spacecatninja\imagerx\gql\directives
 */
class ImagerTransform extends Directive
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

        $type = GqlEntityRegistry::createEntity(static::name(), new self([
            'name' => static::name(),
            'locations' => [
                DirectiveLocation::FIELD,
            ],
            'args' => ImagerTransformArguments::getArguments(),
            'description' => 'This directive is used to return a URL for an using Imager X.'
        ]));

        return $type;
    }

    /**
     * @inheritdoc
     */
    public static function name(): string
    {
        return 'imagerTransform';
    }

    /**
     * @inheritdoc
     */
    public static function apply($source, $value, array $arguments, ResolveInfo $resolveInfo)
    {
        if ($resolveInfo->fieldName !== 'url') {
            return $value;
        }
        
        $returnType = 'url';
        
        if (isset($arguments['return'])) {
            $returnType = $arguments['return'];
            unset($arguments['return']);
        }

        $transform = $arguments;
        
        if ($source->kind !== 'image' || !\in_array(strtolower($source->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
            return null;
        }
        
        try {
            $transformedImage = ImagerX::$plugin->imagerx->transformImage($source, $transform);
        } catch (ImagerException $e) {
            Craft::error('An error occured when trying to transform image in GraphQL directive: ' . $e->getMessage(), __METHOD__);
            return null;
        }
        
        if ($returnType === 'base64') {
            return $transformedImage->getBase64Encoded();
        }
        
        if ($returnType === 'dataUri') {
            return $transformedImage->getDataUri();
        }
        
        if ($returnType === 'blurhash') {
            return $transformedImage->getBlurhash();
        }
        
        return $transformedImage->getUrl();
    }
}
