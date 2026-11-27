# Skull Portal 1.0.0

A lightweight, standalone SMF 2.1.x portal by BoredSkull.

## Installation

Back up your database and forum. Upload the ZIP in **Admin > Package Manager > Download Packages > Upload a Package**, then install. Requires SMF 2.1.x, MySQL/MariaDB, and a PHP version supported by your forum. **Do not install this alongside an existing portal which overrides the default action without checking its settings first.**

Visit `index.php?action=admin;area=skullportal` to configure, and `index.php?action=portal` to view. Leave *Use portal as homepage* off until you have verified the portal loads correctly. Use `index.php?action=forum` to access the normal forum board index after enabling the homepage.

## Built-in blocks

- BBCode (parsed using SMF)
- Plain text (escaped)
- Custom HTML (trusted administrators only)
- Welcome/member panel: automatically displays selectable member details (avatar, display name, rank, stars when available, post count, unread/new-replies and account links); no custom text input. Guests receive login and registration links when registration is open.
- Forum statistics
- Navigation links

Each block has a title, on/off toggle, column (left/centre/right/bottom), numeric sort position, title visibility, and guest/member/everyone visibility. Responsive CSS stacks columns on narrow screens. The administration area includes drag-and-drop block ordering with keyboard-friendly move controls.

## Ecosystem extension hook

`integrate_skullportal_blocks` is called with `&$types`, a mutable registry of block types. Register your callback through SMF's `add_integration_function` or package `<hook>` declarations. For example:

```php
function mymod_skullportal_register(&$types)
{
    $types['mymod_featured'] = array(
        'label' => 'Featured items',
        'render' => 'mymod_skullportal_render',
        'active' => 'mymod_skullportal_active', // required for extensions
        'visible' => 'mymod_skullportal_visible', // optional
    );
}

function mymod_skullportal_active()
{
    // Return true only when your own modification is installed AND enabled.
    return !empty($GLOBALS['modSettings']['mymod_enabled']);
}

function mymod_skullportal_visible($block)
{
    // Check the originating mod's permissions here.
    return allowedTo('view_mlist'); // example only; use the correct permission
}

function mymod_skullportal_render($block)
{
    // Escape all member-generated values and validate any URLs.
    return '<p>Dynamic content from my mod</p>';
}
```

The portal calls each `render` callback with the stored block row and expects HTML as a return value. Extension block definitions require an `active` callback returning true, otherwise the type is not offered or rendered. The optional `visible` callback can reject the block based on originating-mod permissions. If the provider is uninstalled, its registered type disappears, and old stored blocks are skipped instead of executed. Third-party content is not automatically escaped by the portal, so a provider must apply appropriate escaping/permission checks.

### Additional ecosystem output hook (1.0.0 release)

`integrate_skullportal_block_output` is called as
`(&$html, $block, $page)` after a block has passed its visibility checks and
its renderer has produced a string. `$html` is the trusted HTML output passed
by reference; `$block` is the saved block row and `$page` is `portal` or `forum`.
Other BoredSkull mods can register a callback using SMF's normal integration
hook mechanism when installed, then append permitted features to a block's
output. The callback is not called for hidden blocks or unavailable providers.
Integration authors must escape all dynamic content, validate URLs and respect
the originating mod's permissions. If the callback throws, that block is
skipped and the exception is logged. No other mods are required.

**Ecosystem hook register:** `integrate_skullportal_blocks` (register block
providers, with required `active` and optional `visible` checks);
`integrate_skullportal_block_output` (extend visible rendered block output).
Both are optional; integrations must register their own callbacks and be
absent when the provider mod is not installed. Skull Portal also uses SMF's
`integrate_pre_load`, `integrate_actions`, `integrate_menu_buttons`,
`integrate_current_action`, `integrate_theme_context` and
`integrate_admin_areas` hooks for standalone operation.

## Security and maintenance

- Admin modifications require `admin_forum`, SMF session validation, and CSRF tokens.
- Public HTML is never used as a PHP expression.
- The HTML block deliberately permits scripts and active HTML **only for a trusted forum admin**. Do not allow moderators or ordinary members to edit these blocks.
- Third-party block callbacks are trusted executable PHP. Install extensions only from trusted publishers.
- Blocks are retained if the package is uninstalled, protecting configuration on reinstall. Manual removal of `{db_prefix}skullportal_blocks` may be needed for a clean database removal.
- Homepage selection uses SMF's native `integrate_default_action` setting and attempts to restore its prior value when disabled or uninstalled. Other modifications using that same single-default-action setting may conflict.

## Limitations

This 1.0.0 package has been statically audited and syntax checked. There is no
permission-aware per-block cache, separate draft/publish workflow or custom
third-party provider settings editor. PHP testing in isolation does not
substitute for a live SMF 2.1.x installation and theme compatibility check.
MySQL/MariaDB are required by the installer.

## License

ISC. Copyright (c) 2026 BoredSkull.

## Pre-release maintenance history

Uninstall explicitly reverses all seven registered SMF hook callbacks and removes package files while preserving stored blocks. This is a static audit release; live SMF installation and full integration tests remain outstanding.

## Changes in 1.0.0

- The portal no longer adds a dedicated **Skull Portal** entry to the public main menu.
- When **Use portal as homepage** is enabled, the **Forum** button appears immediately after **Home** and points to `?action=forum`. Home continues to use the normal forum base URL, which opens the configured homepage.
- When the portal is not the homepage, the extra Forum button is not shown.
- The public portal layout excludes columns containing no currently visible/enabled blocks. Centre-only pages fill the available content width. Two-column combinations also expand automatically. Visibility is checked per visitor, so guests and members can see different layouts.
- No database migration is needed; existing blocks and the third-party integration hook API remain unchanged.

Upgrade via SMF Package Manager after backing up. Existing installations may need the old package removed before installing this release; the uninstaller preserves block data. Do not run two versions side by side. Clear the forum cache if your theme continues showing old navigation.

## 1.0.0: Blocks on forum pages

Blocks can now be displayed on the portal, on forum pages, or both. Edit a block and choose **Display on**. The forum rendering uses an SMF template layer, with side columns around the regular forum content and centre blocks above it. Existing blocks default to Both.

The installer adds a `display_on` column to pre-existing block tables without deleting data. The `integrate_theme_context` hook must be registered when upgrading. Do not replace only the PHP files: install the package through SMF Package Manager so the new hook is registered. Back up before installation.

Forum blocks are displayed on board index, boards, and topic views, not on administration and profile pages. Test custom themes on staging.

## Pre-release versioning

This package consolidates the development builds 1.0.0 through 1.0.3 into the unreleased **1.0.0** baseline. Earlier development ZIPs should not be publicly distributed as separate releases. Existing test installations may need to uninstall the earlier development package first (database retained), then install this package. Back up before testing.


## RC3 fixes (unreleased 1.0.0)
- Correct new-block DB insertion order: `display_on` and `sort_order` now map to their respective typed columns.
- Use an explicit `skullportal_forum_main` route for `?action=forum` that loads the SMF board index rather than the portal callback.
- Existing blocks/settings remain in place. No schema changes.

### RC4 convenience improvements

- After SMF reports successful installation, Package Manager redirects to **Admin > Skull Portal** (three-second delay).
- Administrators also have **Skull Portal** in the main navigation's **Admin** dropdown. No extra top-level navigation item is introduced.
- This is still pre-release **1.0.0**.


## RC5: Bottom blocks and custom appearance
Bottom blocks span the full page width, following the normal columns (and following the forum content when used on forum pages). Per-block optional hexadecimal heading/body colours, border colour and corner radius. Existing blocks retain theme styling. Installer adds nullable style_settings to existing tables without deleting data.

## RC6 adjustments (still version 1.0.0)

- On the forum board index, Bottom blocks now render outside the board index outer layer, after SMF's Info Centre. Forum pages without an Info Centre continue to show Bottom blocks after their normal content.
- The Block Appearance editor uses a collapsed disclosure panel by default. Expand **Block appearance** to change optional colours and corners.
- When the portal is configured as Home, the Forum menu item is highlighted on the board index, boards, and topic pages, while Home remains highlighted on the portal.

These changes use SMF integration hooks and template layers; they do not edit SMF core or theme files. As always, validate the layout with the active theme on a test forum before deployment.

## RC7 fix: Public homepage title

- The portal's browser page title now uses SMF's configured forum name (`$context['forum_name']`, falling back to `$mbname`) rather than the modification name.
- The Admin Centre still identifies its settings as Skull Portal.
- No database changes.


## RC8: Forum breadcrumb behaviour

When Skull Portal is configured as the homepage, the first breadcrumb on the forum index, boards and topics now links to `?action=forum` rather than the portal homepage. The portal homepage and unrelated actions keep their existing breadcrumbs. This uses a dedicated `integrate_theme_context` hook; no core or theme edits are required.

### RC9 pre-release audit fixes
- Fixed an early exit in forum-page block injection that incorrectly skipped ordinary board/topic URLs whenever Skull Portal was configured as the homepage.
- Avoided injecting duplicate forum template layers if theme context is processed again.
- Guarded template-layer handling when the theme context is incomplete.
- Verified board/topic/forum/portal/admin/empty routes with a standalone mocked SMF integration test. These tests do **not** replace live SMF 2.1.x/BSModern testing.

## RC10 additions (pre-release)
- Drag-and-drop arrangement across left, centre, right and bottom columns, plus keyboard-accessible movement controls. Save the layout to persist changes.
- Duplicate existing blocks (duplicates start disabled).
- Live portal preview link for administrators; preview maintains normal access/visibility checks.
- Quick layout presets for the built-in Welcome, Navigation and Statistics blocks; user content and third-party blocks are left intact.
- Membergroup-specific block visibility, in addition to guest/member/everyone rules.
- Optional ecosystem block types are only registered by their own active provider hooks. Missing or disabled providers are excluded from available types and the admin listing, with stored block data preserved for reinstallation. Providers must supply an `active` callable and enforce permissions using `visible`.
- No dependencies on other BoredSkull mods. No automatic HTML sanitisation of administrator HTML blocks; only trusted admins should use that type.

**Not implemented in RC10:** a separate draft/publish system, per-block server-side caching (permission-sensitive providers need a dedicated cache contract), or mobile device emulation inside the admin preview. These require further design and testing.

## RC11 audit fixes (pre-release)
- Optional integration blocks remain inaccessible through direct administrator edit URLs when their provider is unavailable. Existing saved data remains untouched.
- Membergroup labels in the block visibility selector are HTML-escaped.
- Malformed non-array style submissions are handled without PHP warnings or errors.
- Rechecked PHP syntax and package contents; live SMF compatibility testing is still required.

## Final 1.0.0 release audit

- Added `integrate_skullportal_block_output` for optional, hooks-first ecosystem extensions.
- A failing optional provider visibility callback can no longer crash a portal page; the block is hidden and the error logged.
- Block editing now rejects forged updates targeting a saved block from an unavailable provider.
- Existing `integrate_skullportal_blocks` API and database schema are retained.
- All files and hooks are packaged for SMF 2.1.x, including ISC licence.
