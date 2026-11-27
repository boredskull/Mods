# Anonymous Posting 1.0.9

BoredSkull · SMF 2.1.x · ISC licence

Lets permitted members post new topics and replies anonymously on selected boards. Posts appear publicly as "Anonymous". The real author is kept in a private table that only authorised staff can reveal, and every reveal is audited.

## Requirements

- SMF 2.1.x
- MySQL or MariaDB with InnoDB tables (at least `messages`, `topics` and the mod's own tables). The mod checks this before every anonymous post and refuses to post if it is not met.

## Installation

1. Back up your files and database.
2. Install through Package Manager.
3. Go to **Admin > Configuration > Modification Settings > Anonymous Posting**:
   - **Allowed boards**: boards where members may choose to post anonymously.
   - **Forced anonymous boards**: boards where every permitted post is anonymous. These must also be allowed boards.
4. Under **Permissions**, grant:
   - **Post anonymously** (`bsap_use`) to the registered groups that may use it.
   - **Reveal anonymous post authors** (`bsap_reveal`) only to trusted staff.

Guests can never post anonymously or reveal authors, even if permissions are misconfigured.

## How it works

- A **Post anonymously** option appears on the full post form and in quick reply. On forced boards it is always ticked.
- The post is stored as a guest post with the name "Anonymous", no email and a zeroed IP. The member's post count and notification subscriptions are left unchanged, and the post is not marked read for them.
- The post and the private author record are saved in one database transaction. If anything stops the post from being saved anonymously, nothing is published. The post never falls back to being published under the member's name.
- **Revealing an author:** staff with the reveal permission see a **Reveal author (logged)** button on anonymous posts. They have to confirm the reveal (a POST with a session check) before the name is shown. Each reveal is recorded first, and the reveal is refused if the record can't be saved. Board visibility and approval permissions still apply.
- **Audit:** administrators can see who revealed which message, and when, under **Modification Settings > Anonymous Posting - reveal audit**. The audit page doesn't show the author.
- **Cleanup:** author and audit records are deleted when a post is permanently deleted. Daily maintenance also clears orphaned records, up to 1,000 at a time, and admins can run this cleanup from the audit page. Recycled posts keep their records.

## Not supported on anonymous posts

Attachments, polls, calendar events, announcements, and moving, locking or stickying the topic while posting. Members get an error explaining this instead. Editing an existing post never makes it anonymous.

## Privacy notice — please read

Anonymous posting hides the author from other members. It does **not** hide them from the forum's staff or the server:

- Staff with the reveal permission can see who wrote a post.
- Server, security and flood-control logs, the online log, board read tracking, caches, database administrators and other modifications may still link activity to an account.
- Approval-queue notifications, error logs from other code, and external services are outside the mod's control.

Tell members this. Don't promise them complete anonymity, and remind them not to put identifying details in their posts.

## Compatibility

- Only SMF's normal post workflow (`post2`) is covered. Other code that calls `createPost()` directly is not anonymised.
- For anonymous submissions, the known BoredSkull AI Assistant post hooks are skipped for that request only. No settings are changed. Other AI tools or integrations are not affected.
- Integrations can veto anonymous use through `integrate_bsap_can_use (&$allowed, $id_member, $id_board)`. `$GLOBALS['bsap_pending_author']` is internal and confidential. Never expose it, and don't assume `$user_info` is the public author.

## Upgrading

- **From 1.0.8:** use Package Manager's upgrade.
- **From 1.0.9 test builds or earlier previews:** uninstall *without* "Remove data", then install this package. Existing author records and reveal records are kept.

## Uninstalling

A normal uninstall keeps the private author and audit tables, so authorship isn't lost if you reinstall. Choosing **Remove data** deletes both tables, the mod's settings and its permission entries.

## Changelog

### 1.0.9
- Release build of the 1.0.9 test series.
- Fixed: quick-reply anonymous option could fail to appear when the submit buttons are nested inside the form.
- Fixed: AI Assistant hook suppression now also matches hook entries with an instance (`#`) suffix.
- Fixed: permission names and help text now show on the permissions screen.
- Anonymous posts are no longer marked as read for the real author when they're posted.
- Settings and audit pages now share admin tabs.
- Error messages are now translatable.
- Install now stops with a clear message on non-MySQL databases.
- Remove data now also clears the mod's permission entries and settings.
- Carried over from the test series: blocks polls, calendar, attachments and topic actions, and doesn't write anonymous failures to the error log. Rejects guests, commits only after final verification, and blocks nested transactions. Also includes the paginated reveal audit, cleanup of deleted posts, credits, and the upgrade from 1.0.8.
