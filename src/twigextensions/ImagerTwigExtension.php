<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace spacecatninja\imagerx\twigextensions;

use spacecatninja\imagerx\ImagerX as Plugin;
use Twig\Extension\AbstractExtension;

use Twig\TwigFilter;

/**
 * @author    André Elvan
 * @package   Imager
 * @since     2.0.0
 */
class ImagerTwigExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    /**
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'Imager';
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('srcset', function(?array $images, string $descriptor = 'w'): string {
                return $this->srcsetFilter($images, $descriptor);
            }),
        ];
    }

    /**
     * Twig filter interface for srcset
     *
     *
     */
    public function srcsetFilter(?array $images, string $descriptor = 'w'): string
    {
        return Plugin::$plugin->imagerx->srcset($images, $descriptor);
    }
}
