<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2020 André Elvan
 */

namespace spacecatninja\imagerx\twigextensions;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use spacecatninja\imagerx\ImagerX as Plugin;

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

    /**
     * @return array
     */
    public function getFilters(): array 
    {
        return [
            new TwigFilter('srcset', [$this, 'srcsetFilter']),
        ];
    }
    
    /**
     * Twig filter interface for srcset
     *
     * @param array $images
     * @param string $descriptor
     *
     * @return string
     */
    public function srcsetFilter($images, $descriptor='w'): string
    {
        return Plugin::$plugin->imagerx->srcset($images, $descriptor);
    }
}
