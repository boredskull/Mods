# BoredSkull Hide Banned Members 1.0.2

SMF 2.1.x · ISC · hooks only. Fix for Admin > Members List and SMF banned-status accounts.

## Behaviour

- Hide members when `members.is_activated >= 10`, or an active member-ID-specific access/login ban exists in `ban_items`/`ban_groups`.
- Both Admin > Members List and its search (`sa=query`) use the `integrate_member_list` hook to apply a filter **before counting and paging**, preserving accurate admin pagination.
- The public member list and public member search are filtered server-side after the standard SMF query. Their counts and pagination may still include hidden rows because SMF offers no equivalent public SQL-filter hook.
- Applies to administrators as requested. Banned users remain available to SMF's Ban Center, profile moderation and direct profile links.
- Does not filter third-party lists unless they call `bshbm_should_hide($id)` or `bshbm_filter_member_ids($ids)`; the public BoredSkull API remains available.
- Uses existing SMF tables. No schema changes. Uninstall removes hooks/files only, not accounts or bans.
- The removal applies to account-linked bans and SMF banned status, **not IP/email-only bans** unless SMF has marked the account banned.

## Upgrade

Uninstall 1.0.0 using Package Manager, then install 1.0.2. No database deletion or migration is needed. Clear forum cache afterwards.

## Testing

Static syntax, XML and ZIP checks included. Live MariaDB and SMF rendering require verification on staging. Test with the banned member shown in Admin > Members List, the public member list, and a newly unbanned member.


## Real settings page (v1.0.2 settings edition)

Admin → Members → Members List → **Hide Banned Members** tab. Requires forum administrator permission.

Options: enable modification; filter public member directory; filter Admin Members List; filter member search results; display forum-wide hidden-member count notice on the admin list. These are all enabled by default, including after upgrading from an installation without settings. Changing them never deletes accounts, posts, bans, or database tables. The settings tab appears only for forum administrators. The notice is informational and does not appear on the public member list.

To upgrade an already installed 1.0.2 build, uninstall the previous package (no data deletion), then install this settings edition. Ensure SMF actually executes its uninstall hooks and has removed the previous package before installing the replacement. Clear SMF cache afterward.

## v1.0.2 fix

Load SMF 2.1 `Sources/ManageServer.php` in the custom Members administration settings action before calling `prepareDBSettingContext()` or `saveDBSettings()`. Fixes fatal undefined-function error. Uninstall the prior package and install 1.0.2. Existing member and ban records are not deleted.
