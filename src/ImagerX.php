<?php
/**
 * Imager X plugin for Craft CMS
 *
 * Ninja powered image transforms.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2022 André Elvan
 */

namespace SpaceCatNinja\ImagerX;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Events\AssetReplaced;
use CraftCms\Cms\Asset\Events\AssetUrlResolving;
use CraftCms\Cms\Asset\Events\ThumbUrlResolving;
use CraftCms\Cms\Asset\Validation\AssetRules;
use CraftCms\Cms\Element\Events\ElementActionsResolving;
use CraftCms\Cms\Element\Events\ElementDeleted;
use CraftCms\Cms\Element\Events\ElementSaved;
use CraftCms\Cms\Element\Events\ElementSaving;
use CraftCms\Cms\Gql\Events\GqlTypeFieldsResolving;
use CraftCms\Cms\Gql\Gql;
use CraftCms\Cms\Gql\GqlDirectives;
use CraftCms\Cms\Gql\GqlQueries;
use CraftCms\Cms\Gql\GqlTypes;
use CraftCms\Cms\Image\Data\ImageTransform;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Route\Routes;
use CraftCms\Cms\Support\Facades\ImageTransforms;
use CraftCms\Cms\Support\Facades\Twig;
use CraftCms\Cms\Twig\Variables\CraftVariable;
use GraphQL\Type\Definition\Type;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use SpaceCatNinja\ImagerX\Console\Commands\CleanCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearCachesCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearRuntimeCacheCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearTransformsCacheCommand;
use SpaceCatNinja\ImagerX\Console\Commands\GenerateCommand;
use SpaceCatNinja\ImagerX\Data\ConfigModel;
use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;
use SpaceCatNinja\ImagerX\ElementActions\ClearTransformsElementAction;
use SpaceCatNinja\ImagerX\ElementActions\GenerateTransformsAction;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Gql\Directives\ImagerSrcset;
use SpaceCatNinja\ImagerX\Gql\Directives\ImagerTransform;
use SpaceCatNinja\ImagerX\Gql\Interfaces\ImagerTransformedImageInterface;
use SpaceCatNinja\ImagerX\Gql\Queries\ImagerQuery;
use SpaceCatNinja\ImagerX\Gql\Resolvers\ImagerResolver;
use SpaceCatNinja\ImagerX\Services\GenerateService;
use SpaceCatNinja\ImagerX\Services\ImagerService;
use SpaceCatNinja\ImagerX\Services\TransformState;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\Twig\ImagerTwigExtension;
use SpaceCatNinja\ImagerX\Twig\ImagerVariable;
use SpaceCatNinja\ImagerX\Utilities\ImagerUtility;

use function CraftCms\Cms\currentUserElement;

/**
 * Imager X
 *
 * @author    André Elvan
 * @package   Imager X
 * @since     2.0.0
 *
 * @method Settings getSettings()
 */
class ImagerX extends Plugin
{
    // Editions
    // =========================================================================
    /**
     * @var string
     */
    public const EDITION_LITE = 'lite';

    /**
     * @var string
     */
    public const EDITION_PRO = 'pro';

    /**
     * @var string The rate limiter for the cache clearing action URLs.
     */
    public const CLEAR_CACHE_RATE_LIMITER = 'imager-x-clear-cache';

    // Properties
    // =========================================================================

    protected array $commands = [
        CleanCommand::class,
        GenerateCommand::class,
        ClearCachesCommand::class,
        ClearTransformsCacheCommand::class,
        ClearRuntimeCacheCommand::class,
    ];

    protected array $utilities = [
        ImagerUtility::class,
    ];

    protected array $publishables = [
        __DIR__ . '/../resources/dist/imagerutility.js' => 'utility/imagerutility.js',
        __DIR__ . '/../resources/dist/imagerutility.css' => 'utility/imagerutility.css',
    ];

    // Public Methods
    // =========================================================================

    public static function editions(): array
    {
        return [
            self::EDITION_LITE,
            self::EDITION_PRO,
        ];
    }

    /**
     * Returns whether the Pro edition is active.
     */
    public function isPro(): bool
    {
        return $this->is(self::EDITION_PRO);
    }

    #[\Override]
    public function register(): void
    {
        parent::register();

        // Bound here rather than in boot(), because Craft loads the plugin's routes before boot() runs.
        $this->app->singleton(Settings::class, fn() => $this->getSettings());
        $this->app->singleton(GenerateSettings::class, static fn() => new GenerateSettings(Config::get('craft.imager-x-generate', [])));

        // The cache clearing action URLs are anonymous and authenticated with the `clearKey` setting
        RateLimiter::for(self::CLEAR_CACHE_RATE_LIMITER, static fn(Request $request) => Limit::perMinute(10)->by($request->ip()));
    }

    public function boot(Routes $routes, GqlTypes $gqlTypes, GqlQueries $gqlQueries, GqlDirectives $gqlDirectives): void
    {
        // The `clearKey` authenticates the cache clearing action URLs, not a session, so they don't need CSRF protection
        PreventRequestForgery::except([
            $routes->actionTriggerUriPrefix() . "/$this->handle/cache/clear-transforms",
            $routes->actionTriggerUriPrefix() . "/$this->handle/cache/clear-remote-images",
        ]);

        // Create config model
        $config = ImagerService::getConfig();

        // Add our Twig extension, and `craft.imager` / `craft.imagerx`
        Twig::registerExtension(new ImagerTwigExtension());
        CraftVariable::macro('imager', static fn() => app(ImagerVariable::class));
        CraftVariable::macro('imagerx', static fn() => app(ImagerVariable::class));

        // Clear transforms when an image is replaced
        Event::listen(static function(AssetReplaced $event): void {
            if ($event->asset->kind === 'image') {
                app(ImagerService::class)->removeTransformsForAsset($event->asset);
            }
        });

        // Register element actions
        $this->registerElementActions();

        // Register overrides for native transforms and thumbs
        $this->registerNativeOverrides($config);

        // Register GraphQL functionality
        if ($this->isPro()) {
            $this->registerGraphQL($gqlTypes, $gqlQueries, $gqlDirectives);
        }

        // Register generate listeners
        if ($this->isPro()) {
            $this->registerGenerateListeners();
        }

        // Register listener for clearing transforms on asset delete/move
        if ($config->removeTransformsOnAssetFileops) {
            $this->registerRemoveTransformsListeners();
        }
    }

    /**
     * @inheritdoc
     */
    protected static function createSettings(): ?PluginSettings
    {
        return new Settings();
    }

    /**
     * Register element actions
     */
    private function registerElementActions(): void
    {
        Event::listen(static function(ElementActionsResolving $event): void {
            if (!is_a($event->elementType, Asset::class, true)) {
                return;
            }

            $config = ImagerService::getConfig();
            $user = currentUserElement();

            if ($user && count($config->hideClearCachesForUserGroups) > 0) {
                foreach ($config->hideClearCachesForUserGroups as $userGroup) {
                    if ($user->isInGroup($userGroup)) {
                        return;
                    }
                }
            }

            $event->actions[] = ClearTransformsElementAction::class;

            // If any volume transforms were configured, add generate transforms element action
            if (app(self::class)->isPro() && !empty(app(GenerateSettings::class)->volumes)) {
                $event->actions[] = GenerateTransformsAction::class;
            }
        });
    }

    /**
     * Register events for overriding native functionality
     */
    private function registerNativeOverrides(ConfigModel $config): void
    {
        // Event listener for overriding Craft's internal transform functionality
        if ($config->useForNativeTransforms) {
            // Imager does native transforms right away, so Craft's srcset field has to count each size
            // towards the GraphQL complexity, the same as Craft counts the url field with a transform.
            Event::listen(static function(GqlTypeFieldsResolving $event): void {
                if ($event->typeName !== 'AssetInterface' || !isset($event->fields['srcset'])) {
                    return;
                }

                $event->fields['srcset']['complexity'] = static fn($childrenComplexity, $args) => $childrenComplexity + Gql::GRAPHQL_COMPLEXITY_CPU_HEAVY * max(1, \count((array)($args['sizes'] ?? [])));
            });

            Event::listen(static function(AssetUrlResolving $event): void {
                $asset = $event->asset;

                if ($event->transform === null || $asset->kind !== 'image' || !\in_array(strtolower($asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                    return;
                }

                // In the control panel, leave transforms of assets without public transform URLs to Craft.
                // Craft then uses its own transforms that require a logged in user, for thumbnails and previews.
                if (request()->isCpRequest() && !self::hasPublicTransformUrls($asset)) {
                    return;
                }

                try {
                    $transform = $event->transform;
                    $isNativeTransform = false;

                    // Transform is an ImageTransform
                    if ($transform instanceof ImageTransform) {
                        $transform = ImagerHelpers::normalizeAssetTransformToObject($transform);
                        $isNativeTransform = true;
                    }

                    // Transform is a named asset transform
                    if (is_string($transform)) {
                        $assetTransform = ImageTransforms::getTransformByHandle($transform);

                        if ($assetTransform) {
                            $transform = ImagerHelpers::normalizeAssetTransformToObject($assetTransform);
                            $isNativeTransform = true;
                        } else {
                            Log::error('Unknown asset transform handle supplied to native transform');
                            $transform = [];
                        }
                    }

                    // Like Craft, let the asset's focal point take precedence over a native transform's position when cropping
                    if ($isNativeTransform && ($transform['mode'] ?? null) === 'crop' && $asset->getHasFocalPoint()) {
                        unset($transform['position']);
                    }

                    // Guard against recursion, in case something asks for a native transform URL while we transform
                    $state = app(TransformState::class);

                    if (is_array($transform) && !$state->resolvingNativeTransformUrl) {
                        $state->resolvingNativeTransformUrl = true;

                        try {
                            $transformedImage = app(ImagerService::class)->transformImage($asset, $transform);
                        } finally {
                            $state->resolvingNativeTransformUrl = false;
                        }

                        if ($transformedImage !== null) {
                            $event->url = $transformedImage->getUrl();
                            $event->handled = true;
                        }
                    }
                } catch (ImagerException) {
                    // Leave it to Craft
                }
            });
        }

        // Event listener for overriding Craft's internal thumb url
        if ($config->useForCpThumbs) {
            Event::listen(static function(ThumbUrlResolving $event): void {
                $asset = $event->asset;

                if ($asset->kind !== 'image' || !\in_array(strtolower($asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                    return;
                }

                // Leave thumbnails of assets without public transform URLs to Craft, which uses its own
                // transforms that require a logged in user.
                if (!self::hasPublicTransformUrls($asset)) {
                    return;
                }

                try {
                    /** @var TransformedImageInterface|null $transformedImage */
                    $transformedImage = app(ImagerService::class)->transformImage($asset, ['width' => $event->width, 'height' => $event->height, 'mode' => $event->mode->value]);

                    if ($transformedImage !== null) {
                        $event->url = $transformedImage->getUrl();
                    }
                } catch (ImagerException) {
                    // just ignore
                }
            });
        }
    }

    /**
     * Checks if Craft would publish transforms of an asset, which it only does if the volume has public URLs.
     */
    private static function hasPublicTransformUrls(Asset $asset): bool
    {
        try {
            return $asset->getVolume()->sourceHasUrls();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Register GraphQL types, queries and directives
     */
    private function registerGraphQL(GqlTypes $gqlTypes, GqlQueries $gqlQueries, GqlDirectives $gqlDirectives): void
    {
        $gqlTypes->register(ImagerTransformedImageInterface::class);
        $gqlQueries->register(ImagerQuery::class);
        $gqlDirectives->register(ImagerTransform::class, ImagerSrcset::class);

        // Adds queries to AssetInterface, see https://github.com/spacecatninja/craft-imager-x/pull/111
        Event::listen(static function(GqlTypeFieldsResolving $event): void {
            if ($event->typeName !== 'AssetInterface') {
                return;
            }

            $event->fields['imagerTransform'] = [
                'name' => 'imagerTransform',
                'type' => Type::listOf(ImagerTransformedImageInterface::getType()),
                'args' => [
                    'transform' => [
                        'name' => 'transform',
                        'type' => Type::string(),
                        'description' => 'The handle of the named transform you want to generate.',
                    ],
                ],
                'resolve' => ImagerResolver::class.'::resolve',
                'description' => 'Returns a list of images produced from the named Imager X transform.',
            ];
        });
    }

    /**
     * Register event listeners for generate functionality
     */
    private function registerGenerateListeners(): void
    {
        Event::listen(static function(ElementSaved $event): void {
            $config = app(GenerateSettings::class);
            $element = $event->element;
            $generate = app(GenerateService::class);

            if ($element instanceof Asset && $element->ruleset->getScenario() === AssetRules::SCENARIO_INDEX) {
                return;
            }

            // Volume based generation is site agnostic, so skip propagation saves in multi-site installs (see issue #307)
            if (!$element->propagating && $generate->shouldGenerateByVolumes($element)) {
                $generate->processAssetByVolumes($element);
            }

            if ($element->getIsRevision()) {
                return;
            }

            if (!$config->generateForDrafts && $element->getIsDraft()) {
                return;
            }

            if ($generate->shouldGenerateByElements($element)) {
                $generate->processElementByElements($element);
            }

            if ($generate->shouldGenerateByFields($element)) {
                $generate->processElementByFields($element);
            }
        });
    }

    /**
     * Register event listeners for removing transforms automatically.
     */
    private function registerRemoveTransformsListeners(): void
    {
        Event::listen(static function(ElementDeleted $event): void {
            if ($event->element instanceof Asset) {
                app(ImagerService::class)->removeTransformsForAsset($event->element);
            }
        });

        Event::listen(static function(ElementSaving $event): void {
            $element = $event->element;

            if (!$element instanceof Asset || $event->isNew) {
                return;
            }

            // Craft only uses the file operations scenario when the filename changes, a plain move uses the move scenario
            $isRelocation = \in_array($element->ruleset->getScenario(), [AssetRules::SCENARIO_FILEOPS, AssetRules::SCENARIO_MOVE], true)
                || ($element->newFolderId !== null && $element->newFolderId !== (int)$element->folderId)
                || ($element->newFilename !== null && $element->newFilename !== $element->getFilename())
                || $element->newLocation !== null;

            if ($isRelocation) {
                app(ImagerService::class)->removeTransformsForAsset($element);
            }
        });
    }
}
