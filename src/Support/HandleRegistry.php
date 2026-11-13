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

use InvalidArgumentException;

/**
 * Base class for the registries that map a handle to a class, like transformers and effects.
 *
 * Concrete registries are container singletons. Resolve one, and register types in your plugin's boot():
 *
 * ```php
 * public function boot(Transformers $transformers): void
 * {
 *     $transformers->register('imgix', Imgix::class);
 * }
 * ```
 *
 * @template T of object
 */
abstract class HandleRegistry
{
    /** @var class-string<T>|null The interface registered classes must implement */
    protected const ?string CONTRACT = null;

    /** @var array<string, class-string<T>> */
    protected const array DEFAULT_TYPES = [];

    /** @var array<string, class-string<T>> */
    private array $types = [];

    public function __construct()
    {
        foreach (static::DEFAULT_TYPES as $handle => $class) {
            $this->register($handle, $class);
        }
    }

    /**
     * Registers a class under a handle. Handles are case-insensitive, and registering an existing handle replaces it.
     *
     * @param class-string<T> $class
     */
    public function register(string $handle, string $class): void
    {
        if (static::CONTRACT !== null && !is_a($class, static::CONTRACT, true)) {
            throw new InvalidArgumentException(sprintf('[%s] must implement [%s].', $class, static::CONTRACT));
        }

        $this->types[mb_strtolower($handle)] = $class;
    }

    public function remove(string $handle): void
    {
        unset($this->types[mb_strtolower($handle)]);
    }

    public function has(string $handle): bool
    {
        return isset($this->types[mb_strtolower($handle)]);
    }

    /**
     * @return class-string<T>|null
     */
    public function get(string $handle): ?string
    {
        return $this->types[mb_strtolower($handle)] ?? null;
    }

    /**
     * @return array<string, class-string<T>>
     */
    public function all(): array
    {
        return $this->types;
    }
}
