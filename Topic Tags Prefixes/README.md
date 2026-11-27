# Topic Tags & Prefixes 1.0.2

**BoredSkull** · **Simple Machines Forum 2.1.x** · **ISC licence**

Topic Tags & Prefixes adds topic-level tags and coloured prefixes to SMF 2.1, while preserving ordinary boards and topics. It uses integration hooks; no core or theme template edits are required. The small stylesheet inherits the active SMF theme's layout and works with bsmodern.

## Features

- **Topic tags:** Up to 1–12 comma-separated tags per topic (default five), with 30-character display-name limits. Topic authors can manage labels while SMF permits them to edit the original post, respecting topic locks and edit-time restrictions. Moderators with `modify_any` and permission to edit locked topics can manage labels.
- **Coloured topic prefixes:** Admin-controlled prefix names, six-digit hex colours, enabled/disabled status, sort order, and optional per-board restrictions. Starter set: Announcement, Guide, Support, Solved, Bug.
- **Posting form:** When creating a new topic, the standard SMF post form is enhanced with prefix and tag inputs using a small, optional JavaScript file. If a theme alters its posting form, the **Edit tags & prefix** topic button is the fallback, subject to the same SMF edit permissions and time limits. Replying never changes topic labels.
- **Topic and board display:** Coloured prefixes and clickable tags appear alongside topic titles in the topic view and board topic list.
- **Search and discovery:** Tag browser with name search, popular tags ranked by the number of visible approved topics, prefix discovery, topic lists, and recent/most-replied sorting with paging.
- **Follow tags:** Registered members can follow and unfollow tags, view their following page, see new-topic counts since their last check, and mark the feed as read. Counts and followed-tag names respect current SMF board visibility. **This is an on-site subscription feed; it does not send email or push notifications.**
- **Administration:** Enable/disable tags, prefixes and subscriptions; allow existing-only or member-created tags; set the per-topic limit; rename, disable or delete tags; create, edit or delete prefixes.
- **Modular integration:** `integrate_tp_topic_labels_saved` integration hook after topic metadata changes, with parameters `(int $topicId, array $tagIds, int $prefixId)` to allow other modifications to invalidate their own caches or react to updates. No companion-mod installation is required.
- **Data-preserving removal:** Uninstalling without selecting the SMF Package Manager's remove-data option retains the modification tables for later reinstallation. Selecting remove-data permanently deletes tags, assignments, prefixes, subscriptions and settings.

## Installation

1. Back up files and the SMF database.
2. Go to **Admin → Packages → Download Packages → Upload Package**, and upload `TopicTagsPrefixes_1.0.2.zip`.
3. Install for SMF 2.1.x. The installer creates five tables and initial prefix choices.
4. Open **Admin → Configuration → Topic Tags & Prefixes**. Configure member tag creation, tag limits, prefix names/colours, and per-board availability.
5. The public tag browser remains available at `?action=topiclabels` without a top-level forum menu button. Create a topic to use the labels fields; use **Edit tags & prefix** on existing topics.

## Important behaviours and limitations

- Each topic has **one prefix** but can have multiple tags. Prefixes do not change stored SMF subjects; the displayed badge is inserted by a hook.
- Tags are stored in separate tables and never rewrite topic text. Existing topics can be tagged by using their edit-label link.
- Tag-browser counts and topic lists use SMF's `{query_see_board}` condition and approved topics, so private or unapproved content isn't surfaced in public tag searches.
- New-tag creation is allowed by default. Admins can disable it to restrict members to the existing tag catalogue. Disabled tags remain stored until explicitly deleted.
- Renaming a tag changes its display name but leaves its slug/URL stable; deleting a tag removes assignments and follows.
- Disabling tags or prefixes hides both the editing controls and existing badges in topic listings, while keeping label data available if the feature is re-enabled. Tag-follow links, feeds and routes are also hidden or denied when subscriptions are switched off.
- This release is intentionally **not** a standalone search replacement, auto-tagging AI tool, email subscription service or tag synonym/merge tool. These are not represented as included features.
- The feature is a dedicated first-release SMF 2.1 mod; compatibility with other modifications which overwrite subject markup should be checked on a staging forum.

## Internal tables

- `tp_tags`: tag catalogue and slugs
- `tp_topic_tags`: many-to-many tag assignments
- `tp_prefixes`: coloured prefixes and board restrictions
- `tp_topic_prefix`: per-topic prefix choice
- `tp_follows`: member subscriptions and last-seen timestamps

## Compatibility, performance and security

- PHP 7.4+ recommended. PHP 8.x supported by syntax checks. SMF 2.1.x only.
- Uses SMF's existing sessions, post/create permissions, `modify_own`/`modify_any`, and admin permission.
- No direct SQL receives unescaped user input. Tags and colours are validated and markup is escaped for output.
- Tag catalogue is limited to 150 visible tags in this first version; admin tag listing shows the first 300 alphabetically. The tag browser and topic lists are paginated or limited to avoid unbounded output.
- No external services, tracking, generated email or file uploads.

## Test status

- PHP syntax, package XML references, JavaScript syntax, archive CRC, and isolated tests are run before release.
- **A live installation on a running SMF 2.1 forum with MySQL/MariaDB has not been performed in this environment.** Test on a staging board before public release, especially permissions and new-topic posting.

Copyright © 2026 BoredSkull. ISC license.

## 1.0.0 pre-release rebuild corrections

- Save new-topic labels via SMF's `integrate_create_topic` callback, after the topic row has actually been inserted.
- Hide stored tags and prefixes when their respective global switches are disabled, without deleting their assignments.
- Match SMF's locked-topic and author edit-time restrictions in the dedicated label editor.
- Hide following navigation when subscriptions are unavailable and reject direct requests to disabled subscription endpoints.
- Show followed-tag names only where the member can still see at least one approved topic carrying that tag.
- Apply per-board prefix restrictions to guessed direct prefix URLs, not just the prefix browser.

This rebuild intentionally retains version **1.0.0**. Its ZIP is a replacement for the earlier pre-release package, not a separately versioned upgrade.

## 1.0.1 maintenance update

- Removed the **Topic Tags & Prefixes** button from the public forum's main navigation, leaving administration and topic controls unchanged.
- The tag browser, followed-tags feed, and prefix discovery remain available through their existing URLs.
- For upgrades from 1.0.0, installation automatically unregisters the previously installed `integrate_menu_buttons` hook. The uninstall manifest also retains a legacy cleanup entry.
- No database schema or existing topic-tag data is changed.

## 1.0.2 maintenance update

- Fixed the empty button beside the topic navigation controls. SMF 2.1 looks up the `text` field of display buttons in `$txt`; `tp_edit_button` now correctly resolves to **Edit tags & prefix**.
- Kept the button's existing `?action=topiclabels;sa=edit;topic=...` link and original edit-permission checks.
- No changes to the public main navigation, forum database tables, tags or prefixes.
