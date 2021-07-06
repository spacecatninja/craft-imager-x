# Imager X Changelog


## 3.4.1 - 2021-07-05

### Added
- Added trim transform parameter (closes #99).

### Fixed
- Fixed an issue that could cause errors when trying to clear transforms in environments without an accessible file system (Thanks, @boboldehampsink).
- Fixed use of deprecated method `deleteCachesByElementId` when removing transformed assets (fixes #107).


## 3.4.0 - 2021-03-31

> {warning} Imager X now requires PHP 7.2.5 or newer.

> {warning} If you're using the ImageOptim optimizer, it have now been removed from core due to lack of support for PHP 8.0 in a required library. It is instead available as [a separate plugin](https://github.com/spacecatninja/craft-imager-x-imageoptim-optimizer). All you need to do is install it, no code or config changes needed.

### Changed
- Imager X now requires PHP 7.2.5 or higher.
- ImageOptim optimizer was removed to ensure compability with PHP 8.0 (fixes #94). The optimizer has been split out into a separate package, `spacecatninja/imager-x-imageoptim-optimizer`, which can be installed to make the optimizer continue working.

### Added 
- Added support for blurhash encoding both for local transforms (adresses #67), and through Imgix (adresses #86).
- Added support for returning a transform in blurhash "format" when using the GraphQL transformImage directive.


## 3.3.1 - 2021-03-30

### Fixed
- Fixed an issue that would create double slashes if `addVolumeToPath` was set to `false`.

## 3.3.0 - 2021-01-31

> {warning} If you're using the Imgix transformer, and have added an API key to enable purging, you need to create a new one since [Imgix is deprecating their old API in March](https://blog.imgix.com/2020/10/16/api-deprecation). This version of Imager X supports both the old and the new version, but you will get a deprecation error if you use an old API key.

### Added 
- Added support for the new Imgix API for purging. Deprecation notices are shown if the old API key appear to be for the old API (fixes #64).

### Changed
- Changed Imgix purging to remove the dependency on Guzzle.

### Fixed
- Fixed a typo that made it impossible to override CacheControl request headers for AWS external storage (fixes #82).

## 3.2.6 - 2020-11-22

### Added
- Added detection of extension from mime type for files without an extension, when no format is given.
- Added support for targeting assets fields inside SuperTable fields in auto generate config, ie `'superTableField:*.myAssetsField'` (closes #75).
- Added support for wildcards in type parameter when targeting matrix fields in auto generate config, ie `'matrixField:*.myAssetsField'`. 
- Added `hideClearCachesForUserGroups` config setting which can be used to disable the clear cache paths for Imager for certain user groups (closes #68).

### Fixed
- Fixed an issue that would occur if no file extension and no transform format was set (fixes #74).
- Fixed an issue where `getColorPalette` would return an incorrect number of colors. This hack mitigates an error in the underlying ColorThief library (fixes #69).

## 3.2.5 - 2020-11-13

### Added
- Added default values for optional parameters in `getDominantColor` and `getColorPalette`.

### Fixed
- Fixed an issue where unused/irrelevant parameters wasn't unset before generating the Imgix transform string.

## 3.2.4 - 2020-10-15

### Added
- Added `source` to transformed images, which can be used to inspect the source model used to generate the transform (closes #58).

### Fixed
- Fixed top margins on fieldset's in generate utility (fixes #59).

### Changed
- Changed default value of registered transformers, the default `craft` transformer is now added statically to alleviate issues that could occur if an error occured, and the necessary events didn't fire (adresses #56).


## 3.2.3 - 2020-10-11

### Added
- Added support for localizing `imagerUrl` config setting (closes #55).
- Added `useRawExternalUrl` config setting which can be used to opt out of the default external URL encoding (fixes #57).
- Added `transformerConfig` that can be used to pass config settings directly to a custom transformer.

### Fixed
- Fixed issue where `checkMemoryForImage` could throw an exception (fixes #54).

## 3.2.2 - 2020-09-22

### Fixed
- Fixed an issue where uploads to AWS storage would be placed in the wrong path if no subfolder was present due to an initial `/` (Thanks, @JoshCoady).
- Fixed an issue that would result in a division by zero exception if an external source image could not be downloaded and/or read (fixes #52).
- Fixed an issue where an exception was thrown if ColorThief could not analyse an image.
- Fixed how asset URL is retrieved to avoid `Assets::EVENT_GET_ASSET_URL` being called unnecessarily.

## 3.2.1 - 2020-09-12

### Added
- Added `hasNamedTransform` and `getNamedTransform` template variables (closes #51).
- Added new `clientSupports` template variable.

### Fixed
- Fixed an issue where `useForNativeTransforms` could cause an infinite loop (fixes #50).

## 3.2.0 - 2020-09-03

### Added
- Added support for [AVIF encoding](https://imager-x.spacecat.ninja/usage/avif.html) (closes #42).

## 3.1.7 - 2020-09-02

### Fixed
- Fixed an error that would occur when using `NoopImageModel` because it didn't extend `BaseTransformedImageModel` (Thanks, @boboldehampsink).

## 3.1.6 - 2020-08-27

### Fixed
- Fixed an issue where using `'transparent'` as background color would throw an error.

## 3.1.5 - 2020-08-26

### Added
- Added support for providing the key file contents to the `keyFile` config setting in Google Cloud external storage, with support for environment variables (closes #38).
- Added support for passing objects as parameters to effects.

## 3.1.4 - 2020-08-19

### Fixed
- Fixed issues with radial blur and opacity filters when Imagick was compiled with ImageMagick 7+. 

## 3.1.3 - 2020-08-05

### Added
- Added brightness effect.

### Fixed
- Fixed an issue that could occur if a local or external image didn't have an extension (related to #41).
- Fixed an issue where `'transparent'` wasn't parsed correctly when used in `pad` transform parameter.

## 3.1.2 - 2020-07-22

### Fixed
- Fixed an issue where checkbox labels in the generate utility would be HTML-encoded due to a breaking change in Craft 3.5 (fixes #37).

## 3.1.1 - 2020-07-05

### Fixed
- Fixed unnecessary exceptions that could be thrown when trying to delete files that had already been deleted (fixes #35).
- Fixed an issue where only the first selected volume from the generate utility would be used to generate transforms (fixes #29). 

## 3.1.0.1 - 2020-07-01

### Fixed
- Fixed an issue where the new generate transforms utility would be available when using the Lite edition, even though the generate functionality isn't.

## 3.1.0 - 2020-07-01

### Added
- Added generate transforms utility (addresses #29).
- Added `getPalette()` to `ImgixTransformedImageModel` which adds the ability to request color palette information directly from Imgix (adresses #33).
- Added support for deleting transforms when assets are deleted or moved, hidden behind config setting `removeTransformsOnAssetFileops` (adresses #21).

## 3.0.11 - 2020-06-17

### Added
- Added option to toggle public visibility to transforms uploaded to S3 (fixes #31).

## 3.0.10 - 2020-06-02

### Added
- Added `getPlaceholder` method to TransformedImageInterface (fixes #25).

### Changed
- Changed GraphQL directives and queries to use the `safeFileFormats` setting to avoid trying to transform file formats that aren't transformable (adresses #15 again).

## 3.0.9 - 2020-05-07

### Fixed
- Fixed issue where ImgixTransformedImageModel would return the wrong width and/or height if crop mode `fit` was used, and both width and height was set (fixes #24).

## 3.0.8 - 2020-05-06

### Added
- Added a `return` parameter to the `imagerTransform` GQL directive, to make it possible to return base64-encoded and dataUri's in addition to URL's (adresses #23).

### Changed
- Changed requirement from `google/cloud` to `google/cloud-storage` to avoid excessive amounts of packages being included and to stay in line with the relevant flysystem driver (adresses #22).

## 3.0.7 - 2020-04-11

### Changed
- Imager X no longer generates transforms when reindexing asset volumes. Use console commands to mass generate instead (adresses #19).

## 3.0.6 - 2020-03-22

### Added
- Added `safeFileFormats` setting which is be used to avoid trying to transform file formats that isn't transformable, notably when doing auto generation (adresses #15).

### Fixed
- Fixed deprecated default values in example imgix config (fixes #18).

## 3.0.5 - 2020-03-13

### Added
- Added parsing of callables prior to running `fillTransforms`, so that callables can be used also for properties that is used as `fillAttribute` (addresses #13).

## 3.0.4 - 2020-03-02

### Fixed
- Fixed an issue where an elements field layout was `null` (fixes #12).

## 3.0.3 - 2020-02-21

### Fixed
- Fixed an issue that would cause an exception if the old `domains` config setting for Imgix was set to a string accidentally. 

## 3.0.2 - 2020-02-20

### Added
- Added the ability to use named transforms in named transforms, with safeguards for cyclic references (see issue #7). X-)

## 3.0.1 - 2020-02-18

### Fixed
- Fixed an issue with generate config where only the first config element would be processed (fixes #2). 
- Fixed some issues where Imager would throw an exception if an invalid field was targeted by generate config, and an element was saved.

## 3.0.0 - 2020-02-11

### Changed
- Imager morphed into Imager X. Namespace is now `spacecatninja\imagerx`, plugin handle is `imager-x`, and we have editions.
- Changed how extensions (external storages, effects, transformers and optimizers) are registered, we now use events.

### Added
- Added support for custom transformers.
- Added support for named transforms.
- Added support for auto generating transforms on asset upload or element save.
- Added console commands for generating transforms.
- Added element action for generating transforms.
- Added basic support for GraphQL.
- Added parsing of craft style environment variables ($MY_ENV_VARIABLE) for `imagerSystemPath` and `imagerUrl`.
- Added opacity, gaussian blur, motion blur, radial blur, oil paint, adaptive blur, adaptive sharpen, despeckle, enhance and equalize effects.
- Added support for `fallbackImage` (used if an image cannot be found, or an error occurs).
- Added support for `mockImage` (used for every transform no matter what's passed in).
- Added support for `pad` transform parameter.
- Added `preserveColorProfiles` config setting for preserving color profiles even if meta data is being stripped.

### Fixed 
- Fixed issues that would occur if external downloads are interrupted and the error can't be caught. Downloads are now saved to a temporary file first.
- Fixed issues that would occur if a file upload to an external storage fails. The transformed file is now deleted so that Imager can try again. 
- Fixed issue with silhouette placeholder method, it now supports gif and png source files in addition to jpg.
- Fixed redundant encoding of URLs for the Imgix URL builder after bumping imgix/imgix-php to 3.x.

### Deprecated
- Deprecated `domains` and `shardStrategy` Imgix config settings and added `domain`, due to changes in imgix/imgix-php 3.x.
