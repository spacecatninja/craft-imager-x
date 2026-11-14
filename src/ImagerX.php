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

use Craft;
use craft\base\Element;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\Asset\Elements\Asset;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\ElementEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterGqlDirectivesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\ReplaceAssetEvent;
use craft\gql\TypeManager;
use craft\models\ImageTransform;
use craft\services\Assets;
use craft\services\Elements;
use craft\services\Gql;
use craft\web\twig\variables\CraftVariable;
use GraphQL\Type\Definition\Type;
use CraftCms\Cms\Route\Routes;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;

use SpaceCatNinja\ImagerX\Console\Commands\CleanCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearCachesCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearRuntimeCacheCommand;
use SpaceCatNinja\ImagerX\Console\Commands\ClearTransformsCacheCommand;
use SpaceCatNinja\ImagerX\Console\Commands\GenerateCommand;

use SpaceCatNinja\ImagerX\ElementActions\ClearTransformsElementAction;
use SpaceCatNinja\ImagerX\ElementActions\GenerateTransformsAction;
use SpaceCatNinja\ImagerX\Exceptions\ImagerException;
use SpaceCatNinja\ImagerX\Gql\Directives\ImagerSrcset;
use SpaceCatNinja\ImagerX\Gql\Directives\ImagerTransform;
use SpaceCatNinja\ImagerX\Gql\Interfaces\ImagerTransformedImageInterface;
use SpaceCatNinja\ImagerX\Gql\Queries\ImagerQuery;
use SpaceCatNinja\ImagerX\Gql\Resolvers\ImagerResolver;
use SpaceCatNinja\ImagerX\Support\ImagerHelpers;
use SpaceCatNinja\ImagerX\Support\VersionHelpers;
use SpaceCatNinja\ImagerX\Data\GenerateSettings;
use SpaceCatNinja\ImagerX\Data\TransformedImageInterface;

use SpaceCatNinja\ImagerX\Services\GenerateService;
use SpaceCatNinja\ImagerX\Services\ImagerService;

use SpaceCatNinja\ImagerX\Twig\ImagerTwigExtension;
use SpaceCatNinja\ImagerX\Utilities\ImagerUtility;
use SpaceCatNinja\ImagerX\Twig\ImagerVariable;

use yii\base\Event;

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

        // The cache clearing action URLs are anonymous and authenticated with the `clearKey` setting
        RateLimiter::for(self::CLEAR_CACHE_RATE_LIMITER, static fn(Request $request) => Limit::perMinute(10)->by($request->ip()));
    }

    public function boot(Routes $routes): void
    {
        // The `clearKey` authenticates the cache clearing action URLs, not a session, so they don't need CSRF protection
        PreventRequestForgery::except([
            $routes->actionTriggerUriPrefix() . "/$this->handle/cache/clear-transforms",
            $routes->actionTriggerUriPrefix() . "/$this->handle/cache/clear-remote-images",
        ]);

        // Load additional config files
        $this->loadAdditionalConfigs();

        // Create config model
        $config = ImagerService::getConfig();

        // Add our Twig extensions
        Craft::$app->view->registerTwigExtension(new ImagerTwigExtension());

        // Register our variables
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('imager', ImagerVariable::class);
                $variable->set('imagerx', ImagerVariable::class);
            }
        );

        // Event listener for clearing caches when an asset is replaced
        Event::on(Assets::class, Assets::EVENT_AFTER_REPLACE_ASSET,
            static function(ReplaceAssetEvent $event) {
                // Check if the asset is an image before proceeding
                if ($event->asset->kind === 'image') {
                    app(ImagerService::class)->removeTransformsForAsset($event->asset);
                }
            }
        );

        // Register element actions
        $this->registerElementActions();

        // Register overrides for native transforms and thumbs
        $this->registerNativeOverrides();

        // Register GraphQL functionality
        $this->registerGraphQL();

        // Register generate listeners
        $this->registerGenerateListeners();

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
     * Load additional config files
     */
    private function loadAdditionalConfigs(): void
    {
        // Named transforms
        $transformsConfig = Config::get('craft.imager-x-transforms', []);

        foreach ($transformsConfig as $transformname => $transformConfig) {
            ImagerService::registerNamedTransform($transformname, $transformConfig);
        }

        // Generate setup
        $generateConfig = Config::get('craft.imager-x-generate', []);
        ImagerService::$generateConfig = new GenerateSettings($generateConfig);
    }

    /**
     * Register element actions
     */
    private function registerElementActions(): void
    {
        // Register element action to assets for clearing transforms
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS,
            static function(RegisterElementActionsEvent $event) {
                $config = ImagerService::getConfig();

                $identity = Craft::$app->getUser()->getIdentity();

                if ($identity && count($config->hideClearCachesForUserGroups) > 0) {
                    foreach ($config->hideClearCachesForUserGroups as $userGroup) {
                        if ($identity->isInGroup($userGroup)) {
                            return;
                        }
                    }
                }

                $event->actions[] = ClearTransformsElementAction::class;

                if (app(self::class)->isPro()) {
                    // If any volume transforms were configured, add generate transforms element action
                    $generateVolumeConfig = ImagerService::$generateConfig['volumes'] ?? null;

                    if ($generateVolumeConfig) {
                        $event->actions[] = GenerateTransformsAction::class;
                    }
                }
            }
        );
    }

    /**
     * Register events for overriding native functionality
     */
    private function registerNativeOverrides(): void
    {
        $config = ImagerService::getConfig();

        // Event listener for overriding Craft's internal transform functionality
        if ($config->useForNativeTransforms) {
            // Imager does native transforms right away, so Craft's srcset field has to count each size
            // towards the GraphQL complexity, the same as Craft counts the url field with a transform.
            Event::on(TypeManager::class, TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
                static function(DefineGqlTypeFieldsEvent $event) {
                    if ($event->typeName !== 'AssetInterface' || !isset($event->fields['srcset'])) {
                        return;
                    }

                    $event->fields['srcset']['complexity'] = static fn($childrenComplexity, $args) => $childrenComplexity + Gql::GRAPHQL_COMPLEXITY_CPU_HEAVY * max(1, \count((array)($args['sizes'] ?? [])));
                }
            );

            Event::on(Asset::class, Asset::EVENT_BEFORE_DEFINE_URL,
                static function(DefineAssetUrlEvent $event) {
                    if ($event->transform !== null && $event->sender->kind === 'image' && \in_array(strtolower($event->sender->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                        // In the control panel, leave transforms of assets without public transform URLs to Craft.
                        // Craft then uses its own transforms that require a logged in user, for thumbnails and previews.
                        if (Craft::$app->getRequest()->getIsCpRequest() && !self::hasPublicTransformUrls($event->sender)) {
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
                                $assetTransform = Craft::$app->getImageTransforms()->getTransformByHandle($transform);

                                if ($assetTransform) {
                                    $transform = ImagerHelpers::normalizeAssetTransformToObject($assetTransform);
                                    $isNativeTransform = true;
                                } else {
                                    Craft::error('Unknown asset transform handle supplied to native transform', __METHOD__);
                                    $transform = [];
                                }
                            }

                            // Like Craft, let the asset's focal point take precedence over a native transform's position when cropping
                            if ($isNativeTransform && ($transform['mode'] ?? null) === 'crop' && $event->sender->getHasFocalPoint()) {
                                unset($transform['position']);
                            }

                            if (is_array($transform)) {
                                // We need to reset the transform here to avoid an infinite loop
                                $event->sender->setTransform(null);

                                // Do the transform
                                $transformedImage = app(ImagerService::class)->transformImage($event->sender, $transform);

                                if ($transformedImage !== null) {
                                    $event->url = $transformedImage->getUrl();
                                    $event->handled = true;
                                }
                            }
                        } catch (ImagerException $e) {
                            return null;
                        }
                    }
                }
            );
        }

        // Event listener for overriding Craft's internal thumb url
        if ($config->useForCpThumbs) {
            Event::on(Assets::class, Assets::EVENT_DEFINE_THUMB_URL,
                static function(DefineAssetThumbUrlEvent $event) {
                    if ($event->asset->kind === 'image' && \in_array(strtolower($event->asset->getExtension()), ImagerService::getConfig()->safeFileFormats, true)) {
                        // Leave thumbnails of assets without public transform URLs to Craft, which uses its own
                        // transforms that require a logged in user.
                        if (!self::hasPublicTransformUrls($event->asset)) {
                            return;
                        }

                        try {
                            /** @var TransformedImageInterface $transformedImage */
                            $transformedImage = app(ImagerService::class)->transformImage($event->asset, ['width' => $event->width, 'height' => $event->height, 'mode' => 'fit']);

                            if ($transformedImage !== null) {
                                $event->url = $transformedImage->getUrl();
                            }
                        } catch (ImagerException) {
                            // just ignore
                        }
                    }
                }
            );
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
     * Register GraphQL event listeners
     */
    private function registerGraphQL(): void
    {
        if ($this->isPro()) {
            // Register types
            Event::on(
                Gql::class,
                Gql::EVENT_REGISTER_GQL_TYPES,
                static function(RegisterGqlTypesEvent $event) {
                    Craft::debug(
                        'Gql::EVENT_REGISTER_GQL_TYPES',
                        __METHOD__
                    );
                    $event->types[] = ImagerTransformedImageInterface::class;
                }
            );

            // Register query
            Event::on(Gql::class,
                Gql::EVENT_REGISTER_GQL_QUERIES,
                static function(RegisterGqlQueriesEvent $event) {
                    $queries = ImagerQuery::getQueries();
                    foreach ($queries as $key => $value) {
                        $event->queries[$key] = $value;
                    }
                }
            );

            // Register directives
            Event::on(Gql::class,
                Gql::EVENT_REGISTER_GQL_DIRECTIVES,
                static function(RegisterGqlDirectivesEvent $event) {
                    $event->directives[] = ImagerTransform::class;
                    $event->directives[] = ImagerSrcset::class;
                }
            );

            if (VersionHelpers::craftIs('3.4')) {
                /*
                 * Adds queries to AssetInterface, see https://github.com/spacecatninja/craft-imager-x/pull/111
                 */
                Event::on(TypeManager::class,
                    TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
                    static function(DefineGqlTypeFieldsEvent $event) {
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
                    }
                );
            }
        }
    }

    /**
     * Register event listeners for generate functionality
     */
    private function registerGenerateListeners(): void
    {
        if ($this->isPro()) {
            if (ImagerService::$generateConfig === null) {
                return;
            }

            Event::on(Elements::class,
                Elements::EVENT_AFTER_SAVE_ELEMENT,
                static function(ElementEvent $event) {
                    /** @var GenerateSettings $config */
                    $config = ImagerService::$generateConfig;

                    /** @var Element $element */
                    $element = $event->element;

                    if ($element instanceof Asset && $element->getScenario() === Asset::SCENARIO_INDEX) {
                        return;
                    }

                    // Volume based generation is site agnostic, so skip propagation saves in multi-site installs (see issue #307)
                    if (!$element->propagating && app(GenerateService::class)->shouldGenerateByVolumes($element)) {
                        app(GenerateService::class)->processAssetByVolumes($element);
                    }

                    if ($element->getIsRevision()) {
                        return;
                    }

                    if (!$config->generateForDrafts && $element->getIsDraft()) {
                        return;
                    }

                    if (app(GenerateService::class)->shouldGenerateByElements($element)) {
                        app(GenerateService::class)->processElementByElements($element);
                    }

                    if (app(GenerateService::class)->shouldGenerateByFields($element)) {
                        app(GenerateService::class)->processElementByFields($element);
                    }
                });
        }
    }

    /**
     * Register event listeners for removing transforms automatically.
     */
    private function registerRemoveTransformsListeners(): void
    {
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT,
            static function(ElementEvent $event) {
                if ($event->element instanceof Asset) {
                    /** @var Asset $asset */
                    $asset = $event->element;

                    app(ImagerService::class)->removeTransformsForAsset($asset);
                }
            }
        );

        Event::on(Elements::class, Elements::EVENT_BEFORE_SAVE_ELEMENT,
            static function(ElementEvent $event) {
                /** @var Element $element */
                $element = $event->element;

                if (!$element instanceof Asset || $event->isNew) {
                    return;
                }

                // Craft only uses the file operations scenario when the filename changes, a plain move uses the move scenario
                $isRelocation = \in_array($element->getScenario(), [Asset::SCENARIO_FILEOPS, Asset::SCENARIO_MOVE], true)
                    || ($element->newFolderId !== null && $element->newFolderId !== (int)$element->folderId)
                    || ($element->newFilename !== null && $element->newFilename !== $element->getFilename())
                    || $element->newLocation !== null;

                if ($isRelocation) {
                    app(ImagerService::class)->removeTransformsForAsset($element);
                }
            }
        );
    }
}
