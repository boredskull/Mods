# Board Downloads 1.3.0

Board Downloads is a free, ISC-licensed SMF 2.1.x modification by BoredSkull. Use forum topics and attachments in Board mode or manage a separate catalogue in Traditional mode.

## Setup / upgrade

- Back up your database, forum files, attachments and any private download storage. Install or upgrade through SMF Package Manager. If you already have the 1.3.0 or 1.3.1 test package installed, uninstall it with data retention before installing the final 1.3.0 package.
- Open **Admin > Configuration > Modification Settings > Board Downloads** (`?action=admin;area=modsettings;sa=boarddownloads`). The old `?action=boarddownloads;sa=settings` URL redirects to the authenticated admin area.
- Select **Board mode** (default, including existing installations) or **Traditional mode**. Switching preserves both modes' records; their URLs and catalogue content are mode-specific. No migration between modes is performed.
- In Traditional mode, create a dedicated writable storage directory **outside the web and forum document roots**, preferably permission 0700 and owned by the PHP user. Do not expose it through server aliases or symlinks. The extension rejects paths within the webroot, including symlinks resolving to the webroot. This application cannot detect every web server alias. Set its absolute path in settings. Back up the storage directory along with the database. Do not change storage paths without copying the files first.
- Configure allowed file extensions and maximum size. PHP's post_max_size and upload_max_filesize also apply. Direct executable/script extensions are rejected; uploaded archives can contain arbitrary content, so inspect submissions before approval.
- Grant **View Downloads catalogue** (`bd_view`) to the intended groups. Optionally grant **Submit traditional downloads** (`bd_submit`) to authenticated members. Guests cannot submit regardless of this setting.
- In the catalogue, administrators can create categories with explicit group IDs. Blank means administrators only; `*` means everyone with catalogue permission. `-1` is guests and `0` regular members. Category parent links organise the catalogue; ACLs are independent and not inherited.
- Create a listing, upload one or more releases, and set status to Published (or choose a future UTC time) from the author/moderation dashboard. Admin approval is required for non-admin submissions and edits. Non-admin uploads or edits of a published listing move it to Pending; drafts stay drafts until explicitly submitted. Administrators can restore recycled entries via the dashboard. Recycle bin preserves files; it does not free disk space.
- Download counters count initiated public transfers, not completed transfers. Owner/admin inspection downloads and HEAD requests do not count. SHA-256 is generated on upload; the current download path checks the stored byte size, not a repeated full hash. No automatic malware scanning is included.

## New in 1.3.0

- Switchable Board and Traditional modes; default board behaviour and existing attachment choices preserved.
- Independent nested categories (flat filter with hierarchical names), explicit group access, member-owned listings, plain-text descriptions and tags.
- Release files, version labels, alpha/beta/RC/stable channels, SMF/PHP compatibility metadata, changelogs and history.
- Admin approval, drafts, scheduled publication (visibility evaluated against UTC at request time), featured downloads and reversible soft deletion.
- Private file delivery, extension/size restrictions, random storage names, SHA-256 checksum and same-listing duplicate detection, download counters.
- Search by title, description or tags; category filters, sorting, pagination, favourites, member ratings and author/moderation dashboard.
- Conditional GitHub Integration 1.0.1 adapter uses `bsgi_repos()` and `bsgi_fetch()` for administrator-triggered latest release tag/changelog snapshots. Only repositories configured in the companion appear. No automatic downloads of GitHub assets, no tokens and no independent GitHub client. Repo links and snapshots are hidden when the companion is disabled/uninstalled.
- Existing board-mode ecosystem hooks remain enabled only in Board mode. Traditional-mode companion consumers can call `BoredBoardDownloads::traditionalInfo($entryId)` for a viewer-scoped descriptor (null when disabled, inaccessible, unpublished, without approved releases or wrong mode). No other traditional-mode companion adapters are implemented yet.
- Admin settings under Modification Settings, no edits to SMF core/theme files. Uninstall preserves all database records and traditional files.
- Searches in both modes support literal punctuation. Storage validation preserves the previous path if a replacement is invalid. The legacy settings URL redirects to SMF Modification Settings.

---

# Original Board mode documentation (1.2.1 baseline)

A standalone SMF 2.1.x modification by BoredSkull (ISC licence).

**The forum is the content-management system.** Board Downloads does not create
new products, topics, files or file storage. It reads the topics you already
publish in selected SMF boards, then makes a browsable download catalogue.

## Getting started

1. Install the package using **Admin > Packages > Download Packages > Upload Package**.
2. Open **Admin > Configuration > Modification Settings > Board Downloads**, or `?action=boarddownloads;sa=settings`.
3. Open **Admin > Members > Permissions > General Permissions** and grant **View Downloads catalogue** to the membergroups you want (including **Guests** if appropriate). Permissions are deny-by-default for non-administrators until configured.
4. Select the existing forum board or boards that contain your downloadable files, then save. **No board is selected automatically.**
5. Visit `?action=boarddownloads` to browse the catalogue. Topics with at least one eligible approved attachment appear as download listings. Their descriptions, authors and statistics come from the original forum content.
6. Open a listing to view its original description, screenshots, downloadable attachments and a link to its discussion topic.
7. Administrators can use **Choose downloads** to select which eligible attachments to offer and which file should be the primary one-click download.
8. When new files are added to an existing topic, including approved replies, the catalogue can discover them. Revisit **Choose downloads** if a custom selection needs updating.

The modification works with whichever boards the administrator explicitly selects. It has **no fixed board ID or predefined source board**.

## Included features

- Select one or more source boards; use their names as catalogue categories.
- One catalogue listing per topic; source topic remains unchanged.
- Metadata, descriptions and screenshot previews read live from the original topic, including a preview in each catalogue card.
- Approved attachments on the topic's opening post **or approved replies**.
- One-click primary file download and detail-page additional file buttons.
- Administrator selection of visible files and the primary download file.
- Unconfigured topics automatically show every eligible approved attachment,
  using the newest as the primary download.
- Default allowed extensions: ZIP, 7Z, RAR, TAR, GZ, PDF, TXT, DOCX, ODT,
  ODS, XLSX, PPTX, MP3, MP4. Change the list in administrator settings.
- JPEG, PNG, GIF and WebP attachments are shown as preview screenshots by
  default, not as downloadable release files.
- Search by topic title, filter by board, sort by activity/views/title, paging.
- Hook-based installer, no SMF core files or themes edited.
- English language strings and responsive, theme-neutral CSS.

## Security and privacy

- The separate **View Downloads catalogue** permission controls the menu, listing and detail pages, and related integration output. Assign this using SMF native membergroup permissions (Guests supported).
- SMF board visibility is checked in *every* topic/file query.
- The catalogue checks SMF's board visibility, and the native `dlattach`
  handler separately checks `view_attachments`. To let guests download files,
  grant guests the native **View attachments** permission as well as
  **View Downloads catalogue**, and select a guest-visible source board.
  Catalogue access cannot override private-board restrictions.
- Only approved topics, messages and attachments are exposed by the catalogue.
- Selected attachment IDs are always rechecked against approved files actually
  belonging to that visible topic; stale IDs are silently omitted.
- File downloads use SMF's original `?action=dlattach;attach=N` handler, which
  applies SMF's own attachment security and download accounting.
- Changes to file choices and settings require `admin_forum` plus SMF POST
  session validation.
- Source content is not copied to public tables and no attachment files are
  duplicated. The sole new table stores the primary and selected attachment
  IDs per topic. Uninstall deliberately preserves the table and preferences.

## Notes / deliberate limits

- Only actual SMF attachments are supported, not external URLs or third-party
  attachment stores.
- Topic metadata and description always come from SMF. To change them, edit
  the original forum topic. There is no parallel upload/description manager.
- Search is on **topic titles**, not full-text inside message bodies.
- A selected file must have an allowed extension. Adding a screenshot extension
  to allowed types also allows images to appear as downloadable files.
- A topic with no eligible approved attachments does not appear in the catalogue.
- No automatic version parser or inferred software compatibility: those details
  remain in the original topic description.
- Downloads are counted by SMF's native attachment counter, not a new counter.
- No dependency on other BoredSkull modifications or a separate mod website.
- Site owners should stage-test SMF 2.1 and their own theme before production.

## Upgrade / uninstall

The one new table is created with `ignore` semantics and existing preferences
are retained during reinstalls. Uninstall removes the hooks and files but keeps
stored attachment choices (and no native topics/attachments are deleted).

## Verify on staging

Test a public and a private board; guest vs member access; topic containing
screenshots plus two ZIPs; files in a reply; an unapproved reply/attachment;
file deletion after selecting it; session token validation; and a mobile theme.

## Optional BoredSkull ecosystem integration

- **Clubs:** Club boards may be selected as normal download source boards; existing SMF/Clubs board permissions and `view_attachments` remain authoritative. Do not select private club boards for public syndication.
- **Community Insights 1.1.x:** An administrator-only `integrate_ci_providers` provider counts eligible approved download topics created in the supplied reporting window.
- **Community Digest 1.1.6:** `integrate_community_digest_sections` contributes a New downloads section selected *only* from the digest's recipient-filtered candidate pool, with an extra attachment eligibility check. It honours the digest's repeat/selection process.
- **Topic Tags & Prefixes 1.0.0:** Existing topic labels are displayed in catalogue cards and detail pages when the companion mod is enabled. Labels are not duplicated in Board Downloads.
- **Activity Stream, Knowledge Base, Event Manager:** Optional adapters are provided. **BoredChat:** Read-only extensibility is available but direct chat posting is not implemented without a verified BoredChat API.

All optional hooks are safe to register without the companion package installed.

## Ecosystem integrations

- Clubs: any club board explicitly selected in admin settings is subject to the ordinary SMF board/attachment permissions. The catalogue does not create or change club permissions.
- Activity Stream: registers a `boarddownload` type and fetch adapter. Activities use approved topics, approved attachments and the viewer's existing board/attachment permissions.
- Community Insights: provides download-topic reporting with its optional provider hook.
- Community Digest: offers a new-downloads section drawn from each recipient's permitted topic pool.
- Topic Tags & Prefixes: reads labels from the installed companion without duplicating data.
- Knowledge Base: displays matching published articles originating from the same topic, after Knowledge Base's own visibility check.
- Event Manager: links SMF calendar events associated with the same topic, after Event Manager's access check.
- BoredChat and other mods: `BoredBoardDownloads::catalogueInfo($topicId)` provides a viewer-checked read-only catalogue descriptor; `integrate_boarddownloads_related` allows additional companion-mod links on the detail page. No unverified chat-message publishing API is called.

All integrations are optional. Integration switches are shown only when the corresponding companion modification is installed and enabled. Validate the integration adapters in staging with the actual companion-mod versions before enabling them on a public production forum.

### Developer hook: related content

`integrate_boarddownloads_related (&$result, $topicId)` accepts additions to `$result` as arrays with `text` and `url`. URLs must begin with the forum's `$scripturl`; the producing mod must verify the current viewer's access itself. Links are escaped by the Board Downloads theme. Do not insert private or unapproved material.

## Changes in 1.1.1

- Fixed main menu/header spacing and improved checkbox alignment on the settings screen.
- Integration settings only appear when the corresponding companion mod's source file and enabled setting are present.
- When an integration is unavailable, its previous preference is preserved instead of being silently cleared by saving the form.
- Added an SMF Package Manager upgrade path from 1.1.0. No schema changes.

## Changes in 1.2.0

- Adds native SMF membergroup permission **View Downloads catalogue** (`bd_view`), including guest eligibility.
- Guests and membergroups can be allowed or denied independently in **Admin > Members > Permissions > General Permissions**.
- Permissions enforced on menu, direct browse/topic URLs and read-only Activity Stream, Digest and catalogue integrations; admin access retained.
- Permissions are deny-by-default for non-admins until explicitly granted. This avoids accidentally publishing a catalogue after upgrading.
- No change to SMF's original attachment download handler. Original attachment links remain subject to SMF's existing board and attachment permissions, not the new catalogue permission.
- No database migration required; new permission hook is registered by package installation/upgrade.

## Changes in 1.1.2

- Removed the Downloads top-menu icon, which can crowd the adjacent Admin button in narrow or custom theme navigation.
- Avoids forcing navigation button widths or flex values; the theme controls menu spacing.
- Kept keyboard focus highlighting for the Downloads menu item.
- Includes the upgrade path from 1.1.1. No database changes.

**Testing note:** Please clear the SMF cache and browser cache after upgrading. The final menu arrangement still needs visual verification on the site's installed theme.

## Changes in 1.2.1

- Fixed an erroneous `boardsAllowedTo('view_attachments')` filter that could
  exclude every board for guest users, even when a source board was public.
- Source boards are now filtered by SMF's `{query_see_board}` visibility clause
  in catalogue queries; downloads still pass through native SMF security.
- Corrected the empty-board state when configured boards are invisible to the viewer.
- Added package upgrade support from 1.2.0 with no schema or setting changes.

### Guest access checklist

1. In Board Downloads settings, select the source board and save.
2. In membergroup permissions for **Guests**, allow **View Downloads catalogue**.
3. Ensure the source board is visible to guests under SMF board permissions.
4. Allow **View attachments** for Guests if guests should download the actual files.
5. Verify with a signed-out/private-browser session. Guests cannot see private
   boards or unapproved topics, and the regular attachment download handler
   retains its existing checks.
