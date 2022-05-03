<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\gql\resolvers;

use Craft;
use craft\elements\Asset;
use craft\gql\base\Resolver;

use GraphQL\Type\Definition\ResolveInfo;

use spacecatninja\imagerx\exceptions\ImagerException;
use spacecatninja\imagerx\ImagerX;
use spacecatninja\imagerx\services\ImagerService;

class ImagerResolver extends Resolver
{
    /**
     * @inheritDoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $asset = null;
        $transform = $arguments['transform'];

        if ($source instanceof Asset) {
            // If our source is an Asset, use it directly
            $asset = $source;
        } else {
            // Otherwise query for assets based on submitted id or url argument
            $id = $arguments['id'] ?? null;
            $url = $arguments['url'] ?? null;

            if ($id && $url) {
                Craft::error('Both `id` and `url` was submitted to GraphQL query, these are mutually exclusive. `id` will be used.', __METHOD__);
            }

            if (!empty($id)) {
                $query = Asset::find()
                    ->id($id)
                    ->kind('image')
                    ->limit(null);

                $asset = $query->one();
            } elseif (!empty($url)) {
                $asset = $url;
            }
        }
        
        if ($asset instanceof Asset && ($asset->kind !== 'image' || !\in_array(strtolower($asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true))) {
            return null;
        }
        
        if ($asset !== null) {
            try {
                $transformedImages = ImagerX::$plugin->imager->transformImage($asset, $transform);
                return self::prepResults($transformedImages);
            } catch (ImagerException $imagerException) {
                Craft::error('An error occured when transforming asset in GraphQL query: ' . $imagerException->getMessage(), __METHOD__);
                return null;
            }
        }

        return null;
    }

    private static function prepResults(array $transformedImages): array
    {
        $r = [];

        foreach ($transformedImages as $transformedImage) {
            $r[] = (array)$transformedImage;
        }

        return $r;
    }
}
