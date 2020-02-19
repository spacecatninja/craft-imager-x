# Imager X Changelog

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
