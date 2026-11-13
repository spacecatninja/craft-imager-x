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
use GraphQL\Type\Definition\ResolveInfo;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\gql\arguments\ImagerTransformArguments;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\models\LocalTargetImageModel;
use spacecatninja\imagerx\services\ImagerService;

/**
 * Class ImagerTransform
 * @package spacecatninja\imagerx\gql\directives
 */
class ImagerTransform extends Directive
{
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
            'args' => ImagerTransformArguments::getArguments(),
            'description' => 'This directive is used to return a URL for an using Imager X.',
        ]));
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
    public static function apply(mixed $source, mixed $value, array $arguments, ResolveInfo $resolveInfo): mixed
    {
        if ($resolveInfo->fieldName !== 'url') {
            return $value;
        }
        
        $returnType = 'url';
        
        if (isset($arguments['return'])) {
            $returnType = $arguments['return'];
            unset($arguments['return']);
        }

        $transform = $arguments['handle'] ?? $arguments;

        if (\is_array($transform)) {
            $transform = self::validateTransform($transform);

            if ($transform === null) {
                return null;
            }
        }
        
        if (!\in_array(strtolower($source->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
            return null;
        }
        
        try {
            $transformedImage = ImagerX::$plugin->imagerx->transformImage($source, $transform);
        } catch (ImagerException $imagerException) {
            Craft::error('An error occured when trying to transform image in GraphQL directive: ' . $imagerException->getMessage(), __METHOD__);
            return null;
        }
        
        if ($transformedImage === null) {
            return null;
        } 
        
        if (is_array($transformedImage)) {
            $transformedImage = $transformedImage[0];
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
        
        if ($returnType === 'dominantColor') {
            return ImagerX::getInstance()->color->getDominantColor($transformedImage);
        }
        
        return $transformedImage->getUrl();
    }

    /**
     * Validates a transform built from directive arguments. These come from GraphQL clients, so only
     * values that Imager actually renders are accepted, and sizes are limited by config.
     * Returns the transform, or null if it's not allowed.
     */
    private static function validateTransform(array $transform): ?array
    {
        $config = ImagerService::getConfig();

        if (!$config->graphqlAllowCustomTransforms) {
            return null;
        }

        foreach (['width', 'height'] as $dimension) {
            if (!isset($transform[$dimension])) {
                continue;
            }

            $value = (int)$transform[$dimension];

            if ($value < 1 || ($config->graphqlMaxTransformSize > 0 && $value > $config->graphqlMaxTransformSize)) {
                return null;
            }
        }

        if (isset($transform['quality']) && ((int)$transform['quality'] < 0 || (int)$transform['quality'] > 100)) {
            return null;
        }

        if (isset($transform['mode']) && !\in_array(mb_strtolower((string)$transform['mode']), ['crop', 'croponly', 'fit', 'stretch', 'letterbox'], true)) {
            return null;
        }

        if (isset($transform['interlace']) && !isset(ImagerService::$interlaceKeyTranslate[(string)$transform['interlace']])) {
            return null;
        }

        if (isset($transform['position']) && !self::isValidPosition((string)$transform['position'])) {
            return null;
        }

        // The format becomes the file extension. It's used as given, so that existing transforms keep their filenames,
        // and an empty format is ignored like in templates.
        if (isset($transform['format']) && $transform['format'] !== '' && !LocalTargetImageModel::isSafeTargetExtension((string)$transform['format'])) {
            return null;
        }

        return $transform;
    }

    /**
     * Checks if a position is a named position, or two percentages between 0 and 100.
     */
    private static function isValidPosition(string $position): bool
    {
        if (isset(ImagerService::$craftPositionTranslate[$position])) {
            return true;
        }

        if (!preg_match('/^(\d{1,3}(?:\.\d+)?)%? (\d{1,3}(?:\.\d+)?)%?$/D', $position, $matches)) {
            return false;
        }

        return (float)$matches[1] <= 100 && (float)$matches[2] <= 100;
    }
}
