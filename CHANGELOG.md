# Imager X Changelog

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
