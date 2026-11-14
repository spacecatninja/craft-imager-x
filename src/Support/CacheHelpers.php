<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Support;

use Closure;
use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Support\Config;
use CraftCms\DependencyAwareCache\Dependency\TagDependency;
use CraftCms\DependencyAwareCache\Facades\DependencyCache;

/**
 * Class CacheHelper
 *
 * @author    SPACECATNINJA
 * @since     4.2.0
 */
class CacheHelpers
{
    /**
     * Returns the cache tags that Craft invalidates when the element, or all elements, are changed.
     *
     * @return string[]
     */
    public static function getElementCacheTags(ElementInterface $element): array
    {
        $class = $element::class;

        return ['element', "element::$class", "element::$element->id"];
    }

    /**
     * Returns a cache dependency that is invalidated together with Craft's caches for the element.
     */
    public static function getElementCacheDependency(ElementInterface $element): TagDependency
    {
        return new TagDependency(self::getElementCacheTags($element));
    }

    /**
     * Gets an item from the cache, or stores the result of the callback, for the `cacheDuration` set in Craft's general config.
     */
    public static function remember(string $key, Closure $callback, ?TagDependency $dependency = null): mixed
    {
        return DependencyCache::remember($key, self::defaultDuration(), $callback, $dependency);
    }

    /**
     * Craft's default cache duration in seconds, or `null` for no expiration.
     */
    public static function defaultDuration(): ?int
    {
        return Config::durationInSeconds(Cms::config()->cacheDuration) ?: null;
    }
}
