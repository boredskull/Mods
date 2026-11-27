# Enhanced Reactions v1.0.10

A **complete, standalone SMF 2.1.x modification** from BoredSkull, released under the ISC Licence.

## Fresh installation

1. Back up forum files and database.
2. Upload `EnhancedReactions_1.0.10.zip` in **Admin > Packages > Download Packages**.
3. Choose **Install Mod**. This package includes all files and creates the four necessary reaction tables.
4. Visit **Admin > Configuration > Modification Settings > Enhanced Reactions**. Confirm that the mod is enabled.
5. **Admin > Members > Permissions > General Permissions**: set **React to posts** for the desired groups. New installations grant this to the regular-member group (but not guests).
6. Test selecting, changing, and removing reactions. Native SMF Likes may stay disabled.

## Upgrading older Enhanced Reactions

If any version from 1.0.6 to 1.0.9 is installed, **do not uninstall first**. Package Manager should offer the **upgrade** action for this same mod ID. This full package replaces the installed source, JS, CSS, and language files and migrates old reaction records into the independent table without deleting native Likes. Old Like permission grants and explicit denies are copied only if React to posts hasn't been set up yet, so groups you have changed since are left alone. Back up first.

## Behaviour

- Independent reaction storage (`bs_reaction_items`), no dependency on native `enable_likes`.
- Like, Love, Laugh, Wow, Sad, Angry, Helpful, Thanks, configurable by admin.
- Rate limit applies to new reactions on *different* posts, not changes to the same post.
- Native Like buttons are suppressed by the SMF display hook when Enhanced Reactions is enabled, avoiding a UI flash.
- Only logged-in members with **React to posts** permission can react; no self-reactions.
- Viewing reactions checks board and approval permissions. Unapproved/locked/private content is guarded.
- Reactions are plain text and emoji, never trusted HTML. SMF CSRF session validated on POST.
- Other BoredSkull mods may register validated external targets through `integrate_bs_reactions_targets`; integrations are optional.
- Uninstall removes code/hooks **but preserves the four reaction tables**, for safe recovery.

## Important staging notes

No live SMF/MySQL/bsmodern installation is present here. PHP/JS syntax, manifest, archive, and isolated behaviour checks are not a production guarantee. In particular, verify native Like visibility on any custom themes, permissions, and the exact HTTP 500 from the SMF error log if it persists.


## Version 1.0.9

- Fix SMF 2.1 board visibility checks when submitting reactions. The endpoint now uses SMF's `query_see_board` restrictions instead of treating `view_board` as a board permission.
- Allow Global Moderators with `moderate_forum` to react on locked topics when otherwise permitted.
- Apply the same approval, locked-topic, ownership and permissions checks when rendering the picker and accepting a reaction, using a batched message lookup.
- Preserve all optional BoredSkull ecosystem hooks and existing reaction data.

## Version 1.0.10

- Upgrading from 1.0.9 is now supported. Upgrades also register every hook added since 1.0.6 (Activity Stream, Community Insights, credits), which earlier upgrade paths skipped.
- The installer now uses SMF's database functions, so it works on PostgreSQL as well as MySQL/MariaDB, and no longer stops with "invalid database table prefix" on servers where SMF uses a database-qualified prefix.
- The 1.0.6 migration runs once and in batches, so large Likes tables can't exhaust memory.
- Upgrading no longer re-grants **React to posts** to groups an administrator has removed it from.
- Member names with apostrophes or ampersands now show correctly in the "See reactions" list.
- Deleting a member now removes their reactions, so counts match the list of who reacted.
- The CSS and JavaScript now load with a version seed, so browsers fetch the new files after an upgrade.
- Added a credits line, and the readme and licence now show in the Package Manager.

