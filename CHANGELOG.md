# Imager X Changelog

## 6.0.1 - Unreleased

### Fixed
- Fixed additional SSRF bypasses in external URL validation: redirects are now followed manually and re-validated on every hop, the connection is pinned to the validated IP to mitigate DNS rebinding, all resolved A/AAAA records are checked (not just the first), and downloads are restricted to the `http`/`https` protocols. The `skipExternalUrlValidation` config setting still disables these checks.
- Fixed a path traversal issue by confining string-based local image sources to the web root / imager system path.
- Fixed `safeFileFormats` validation to URL sources in the `imagerTransform` GraphQL query.
- Fixed potential command injection in the image optimizers and custom encoder by escaping file path arguments with `escapeshellarg()`.
- Fixed the cache clear key comparison to be constant-time, and added validation of the `--volume` handle in the `imager-x/clean` console command.

## 6.0.0 - 2026-05-24

> [!WARNING]  
> This release contains breaking changes. Please review the release notes carefully before upgrading.

### Removed
- Removed Imgix support from core. Imgix is now available as a separate plugin, `spacecatninja/imager-x-imgix-transformer`. A deprecation notice is logged if old Imgix-related config settings are present. Please install the plugin and migrate your Imgix configuration to `config/imager-x-imgix-transformer.php`, see [documentation for details](https://github.com/spacecatninja/craft-imager-x-imgix-transformer).
- Removed AWS and GCS external storage drivers from core. These are now available as separate plugins, `spacecatninja/imager-x-aws-storage-driver` and `spacecatninja/imager-x-gcs-storage-driver`.
- Removed Kraken and Tinify optimizers from core. These are now available as separate plugins, `spacecatninja/imager-x-kraken-optimizer` and `spacecatninja/imager-x-tinify-optimizer`.

### Changed
- Changed `safeFileFormats` config setting, added `webp` to the default.
- Changed optimizer config key from `tinypng` to `tinify`.


## 5.2.1 - 2026-03-01

### Added
- Added skipExternalUrlValidation config setting to skip the new external URL validation check.

### Fixed
- Fixed SSRF vulnerability by blocking private/reserved IP ranges for external URL downloads.
- Fixed option escaping for shell commands.
- Fixed handling of cache clear key.

## 5.2.0 - 2026-02-28

### Added
- Added support for auto generating images in content blocks, and added support for expanded syntax to `fields` auto generate config (fixes #305 and #39).
- Added support for specifying offset and limit per field in the format `fieldHandle[offset:limit]` (fixes #301).

### Changed
- Changed from `Asset::EVENT_DEFINE_URL` to `Asset::EVENT_BEFORE_DEFINE_URL` when replacing native transforms with Imager X for performance (thanks, @johnnynotsolucky).

## 5.1.7 - 2025-11-14

### Fixed
- Fixed an issue where the width and height of Imgix transforms would be wrong if both `width` and `height` were provided, and mode was `fit`. (fixes #303, thanks @white-ruud).

## 5.1.6 - 2025-07-24

### Fixed
- Fixed asset check in all GQL related code to allow any file kind, to account for adapters (fixes #297, thanks @denisyilmaz).

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
