# Imager X Changelog

## 5.1.5 - 2025-07-16

### Added
- Added config setting `disableACL` to AWS external storage (fixes #296).

### Fixed
- Fixed a type issue that could occur when setting opacity for letterbox.

## 5.1.4 - 2025-06-07

### Added
- Added support for dominantColor as a return type for `imagerTransform` GraphQL directive (fixes #290).

### Fixed
- Fixed an issue where Imgix URLs could have double slashes if `useCloudSourcePath` was `null` (fixes #289).

## 5.1.3 - 2025-03-25

### Fixed
- Fixed an issue where TransformJob was throwing an error if an asset couldn’t be found, which would result in errors when replacing assets.

## 5.1.2 - 2025-02-27

### Fixed
- Fixed an issue where GD could throw an error because the output format extension wasn’t an allowed one (addresses #184)

## 5.1.1 - 2025-01-07

### Fixed
- Fixed an issue that could occur if an assets volume didn’t have public URLs enabled (fixes #280).

## 5.1.0 - 2024-12-01

### Added
- Added support for credential less authentication for AWS external storage, the storage config now take a `useCredentialLessAuth` config setting that can be set to `true`. It should now behave the same as the AWS volume from P&T (closes #254).

### Changed
- Changed/repurposed behaviour of `hideClearCachesForUserGroups` config setting (which was no longer used), to hide clear cache element actions for user groups (closes #278).

## 5.0.3 - 2024-11-08

### Fixed
- Fixed an issue where Imager would try to remove transforms for non-image assets (fixes #273).

## 5.0.2 - 2024-08-08

### Fixed
- Fixed auto generation for fields inside matrix entry types (fixes #270).

## 5.0.1 - 2024-06-24

### Fixed
- Fixed an issue that could occur if slashes were used in config override parameters (fixes #268).

### Changed
- Changed event for registrering extensions to be more in line with what Craft recommends.

## 5.0.0 - 2024-04-10

### Added
- Added support for Craft 5
- Added support for creating queue jobs when using the generate console command.
- Added support for limit and offset when using the generate console command.
- Added support for using `{transformName}` in `filenamePattern` (resolves #257)

### Fixed
- Fixed incorrect closure check for transform params and improved error handling.
- Fixed icon method for utility
- Fixed use of deprecated method `ensureTopFolder`.

### Changed
- Changed behaviour of avif and jxl transforms, they are now processed via Imagine.
