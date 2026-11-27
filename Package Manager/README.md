# Package Manager Enhancements 1.0.0

BoredSkull | SMF 2.1.x | ISC licence

Build your forum, your way!

## Features

- Additional Package Dashboard tab, with a searchable local package library, installation history and interactive status cards.
- Distinct categories for modifications awaiting installation, local updates, installed entries without local updates, and other local packages such as themes, languages and avatars. Total entries is the sum of these four groups.
- Browse Packages search and sorting, preserving native SMF package actions and groups.
- Sequential multi-file uploads with drag and drop, progress, failure feedback and explicit resume. The original SMF uploader remains available in a collapsed section.
- Enhanced Options and File Permissions presentation, and backup reminders on installation/removal test screens.
- Original SMF layout link opens native Browse Packages without enhancement assets. Use the normal Browse Packages tab to return.
- Package review links open SMF's native install-test screen where applicable; otherwise they open Browse Packages. The dashboard never installs, upgrades, removes or executes package content.

## Installation

1. Back up your forum files and database.
2. Upload this ZIP through SMF Package Manager and review the native installation test.
3. Install the package. The redirect opens Browse Packages; the Package Dashboard is available beside it.

This is the first public release. If you installed any private tester or release-candidate build, uninstall it using its original package before installing 1.0.0. Some private builds used higher provisional version numbers; SMF may treat a direct installation as a downgrade. There is no supported in-place upgrade from those builds. No core files or custom database tables are modified by this release.

## Performance and limitations

The dashboard reads the current SMF installed-package records and scans up to 250 eligible local archives or unpacked package directories, selected alphabetically by filename. A notice appears when the limit is exceeded. SMF's native list remains available for the full collection. Metadata is cached for three minutes when SMF caching is enabled, keyed by archive path and file statistics; installed state and history are always read live. Local version comparisons do not check remote update services or guarantee compatibility. The native installation test remains authoritative. History shows up to 100 latest package log records, using the removal date and removing member for removed records. A missing removal date is shown as unknown.

English strings are located in Themes/default/languages/BsPackageManager.english.php and supplied to JavaScript by SMF. Translators can create the appropriate SMF language file. Custom themes may use different markup; verify presentation on your theme. Upload confirmation is based on SMF's response and pauses on uncertain outcomes; check native Package Manager before retrying a failed upload.

## Optional ecosystem integration

Each modification works independently. No companion mod is required.

- `integrate_bspm_package_metadata(&$entry)`: extend a package entry with optional notes and declared dependency package IDs (`dependencies`, array of strings). Other fields include `id`, `name`, `installed`, `available`, `filename`, `type`, `status`, `date`, `description`, and `author`. Notes are plain text; missing dependency notes are generated only for declared IDs absent from the installed log.
- `integrate_bspm_dashboard_cards(&$cards)`: append an array containing `title` and `description` (plain text). The integration section appears only if cards are supplied.

Neither hook authorises automatic installation or removal. Existing integration hooks are retained.

## Changelog

### 1.0.0 - First public release

- Consolidated the private testing builds into the first stable version number.
- Corrected history removal dates, chronological ordering, escaped package names and actor display; distinguish upgrade records.
- Restored the original SMF layout link.
- Added the Other local packages filter and card; made status categories mutually exclusive.
- Made the archive cap deterministic and added short-lived metadata caching.
- Registered SMF credits and included the ISC licence and README in the install manifest.
- Localised dashboard, upload, browse, options and permissions text, notices and dependency messages.
- Loaded assets through Package Manager action hooks rather than CSS output timing.
- Enabled failed upload removal, explicit resume and stable drag highlighting.
- Hid Browse Packages groups when their rows do not match a search.
- Tidied template indentation and documentation; retained native install tests and existing ecosystem hooks.

## Licence

ISC. See LICENSE.txt.
