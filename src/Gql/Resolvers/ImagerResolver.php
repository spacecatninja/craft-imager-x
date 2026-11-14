<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Gql\Resolvers;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Queries\AssetQuery;
use CraftCms\Cms\Gql\Resolvers\Elements\Asset as AssetResolver;
use craft\gql\base\Resolver;

use GraphQL\Type\Definition\ResolveInfo;

use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\ImagerX;
use SpaceCatNinja\ImagerX\Data\LocalSourceImageModel;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use Illuminate\Support\Facades\Log;

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
                Log::error('Both `id` and `url` was submitted to GraphQL query, these are mutually exclusive. `id` will be used.');
            }

            if (!empty($id)) {
                // Use Craft's asset resolver to prepare the query, so that the volume scope of the
                // active schema is applied, the same as for Craft's own asset queries.
                $query = AssetResolver::prepareQuery(null, ['id' => $id, 'kind' => 'image']);

                if (!$query instanceof AssetQuery) {
                    return null;
                }

                $asset = $query->one();
            } elseif (!empty($url)) {
                $asset = $url;
            }
        }
        
        if ($asset instanceof Asset) {
            if (!\in_array(strtolower($asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                return null;
            }
        } elseif (\is_string($asset)) {
            // A raw `url` argument is an untrusted image source. Enforce safeFileFormats here too — the
            // guard above only covered Asset sources. Only enforced when an extension is present, so
            // extension-less image URLs keep working; SSRF and path traversal are handled separately.
            // The extension is checked both as sent, and the way the transform pipeline will see it, so
            // that encoded characters, dot segments or backslashes can't hide the real extension.
            $paths = [
                parse_url($asset, PHP_URL_PATH),
                parse_url(ImagerHelpers::normalizeRemoteUrlString($asset), PHP_URL_PATH),
                ImagerHelpers::getRemoteUrlPath($asset),
            ];

            foreach ($paths as $path) {
                $extension = \is_string($path) ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

                if ($extension !== '' && !\in_array($extension, ImagerService::getConfig()->safeFileFormats, true)) {
                    return null;
                }
            }

            // Local paths and imager urls are resolved differently than remote urls, so check the source
            // the way the transform pipeline resolves it too.
            try {
                $source = new LocalSourceImageModel($asset);
            } catch (ImagerException) {
                return null;
            }

            $sourceExtension = strtolower($source->extension);

            if ($source->type === 'remoteurl') {
                if ($sourceExtension !== '' && !\in_array($sourceExtension, ImagerService::getConfig()->safeFileFormats, true)) {
                    return null;
                }
            } elseif (!\in_array($sourceExtension, ImagerService::getConfig()->safeFileFormats, true) || rtrim($source->filename, '. ') !== $source->filename) {
                // Local files aren't checked for their content like downloads are, so they need an allowed extension.
                // Windows ignores trailing dots and spaces in filenames, which could hide the real extension.
                return null;
            }
        }

        if ($asset !== null) {
            try {
                $transformedImages = app(ImagerService::class)->transformImage($asset, $transform);
                return self::prepResults($transformedImages);
            } catch (ImagerException $imagerException) {
                Log::error('An error occured when transforming asset in GraphQL query: ' . $imagerException->getMessage());
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
