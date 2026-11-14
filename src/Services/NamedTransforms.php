<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX\Services;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Config;

/**
 * The named transforms, from `config/craft/imager-x-transforms.php` and any registered by other plugins.
 */
#[Singleton]
class NamedTransforms
{
    /**
     * @var array<string, array>
     */
    private array $transforms;

    public function __construct()
    {
        $this->transforms = Config::get('craft.imager-x-transforms', []);
    }

    public function register(string $name, array $transform): void
    {
        $this->transforms[$name] = $transform;
    }

    public function has(string $name): bool
    {
        return isset($this->transforms[$name]);
    }

    public function get(string $name): ?array
    {
        return $this->transforms[$name] ?? null;
    }

    /**
     * @return array<string, array>
     */
    public function all(): array
    {
        return $this->transforms;
    }
}
