Community Digest 1.1.7 for SMF 2.1
by BoredSkull - https://boredskull.net - ISC licence

A personalised daily, weekly or monthly roundup of the most useful new discussions, popular questions, club updates, polls and topics still waiting for a reply. It can go out by email or stay on the forum, and it uses SMF's own scheduled tasks, mail queue and alerts.

FEATURES

Personalised recommendations
 - Topics are ranked by followed boards (chosen in preferences, plus boards the member gets notifications for), discussions they've posted in or follow, interest keywords, and members they follow (buddies, plus Activity Stream follows).
 - Each pick shows why it was chosen, for example "New in Off-Topic, which you follow".
 - Weights can be changed in admin.

Flexible delivery
 - Members choose Off, Daily, Weekly or Monthly. Delivery can be on-forum only, email only, or both.
 - Members also choose the weekday and hour, in their own time zone.
 - The default is on-forum only, so no emails go out unless a member asks for them.
 - Every email has an unsubscribe link that works without logging in. The link opens a confirmation page, so link-scanning mail filters can't unsubscribe anyone by accident.

Sections
 - Picked for you, From people you follow, Popular questions, Waiting for a reply, Club updates, Polls to vote in, Popular discussions, Upcoming events, New members, Your profile wall, and Community at a glance.
 - Admins switch sections on or off. Members untick the ones they don't want.

Intelligent filtering
 - Board access mirrors {query_see_board}, including deny groups. It is checked per member when the digest is built and again when the dashboard is shown.
 - Unapproved topics, redirect topics, the recycle bin, ignored boards and ignored members are left out. So are topics the member has unwatched or already read to the end.
 - Spam checks: topics from banned or unactivated accounts are always skipped. There is also an optional minimum post count for topic starters.
 - Topics with open moderation reports are skipped. Locked topics can be skipped too (optional).
 - Admin can exclude whole boards. Moderators get an "Exclude from digest" button in each topic, and these exclusions are recorded in the moderation log.
 - Repeats are suppressed: a topic is never shown again unless it has new posts, and there is a cap on how often it can come back.
 - Permissions are respected: poll recommendations need poll_view and poll_vote and skip polls already voted in, events need calendar_view and the new members list needs view_mlist.

Personal digest dashboard (?action=digest)
 - Shows the latest picks as cards with excerpts, plus a sidebar with events, new members and stats.
 - Members can mark items read one at a time or all at once, refresh, preview their email, or open preferences.
 - Opening a card marks it read and jumps to the first new post.

Administrator control (Admin > Configuration > Community Digest)
 - Settings: frequencies allowed, defaults, limits per section and per digest, excerpt length, filters, weights, batch size, daily email cap and log retention.
 - Content filters: excluded boards and excluded topics.
 - Test delivery: send a digest to any member right now, or preview it in the browser. This page also shows totals for members with a digest, members on email, digests due, emails sent today and recent failures.
 - Delivery log: sent, skipped and failed entries, a "failures only" filter, and a button to clear the log.
 - Integrations: each companion mod can be switched on or off, with a Detected / Not installed status.

Alerts
 - When an on-forum digest is ready, the member gets a core SMF alert. Members can switch it off under Profile > Alerts ("Community Digest").

HOW SENDING WORKS

An hourly scheduled task called "Community Digest" picks up members whose next delivery is due, up to the batch size each run. Members it doesn't reach are handled the next hour. Emails are sent through sendmail(), so they use SMF's mail queue and throttling if you have the queue enabled. Emails are held in a separate retry queue when the daily cap is reached, without delaying on-forum delivery. Failed sends are retried with backoff, with up to eight attempts.

The digest window runs from the member's last digest to the time of generation, limited to twice the length of their period. "Waiting for a reply" looks further back, up to four periods.

Large forums: ordinary live digests locate the window start using the messages primary key. Queued email retries instead search approved messages by their original timestamp window, ensuring a topic isn't lost merely because new replies were posted after the cutoff. This historical search costs more on very large message tables. Candidate pools are cached only for exactly matching windows.

INTEGRATIONS

These are all optional. If a mod isn't installed, its section is simply left out.

 - Activity Stream: follows count as "people you follow".
 - Questions & Answers: adds a "Popular questions" section. Open questions are boosted, with Open/Solved badges.
 - Clubs: adds "Club updates" for clubs the member belongs to. Club membership and permission-verified board visibility are both required, including for private clubs.
 - Ranked Polls: ranked-choice polls get a "Ranked poll" badge.
 - Profile Wall: shows how many new comments are on the member's wall.

The table and column names used by these adapters are in CommunityDigestIntegrations::$map at the top of Sources/CommunityDigest-Integrations.php. If your builds of those mods use different names, change them there. Every adapter query uses db_error_skip, so a mismatch hides the section instead of causing an error.

HOOK FOR OTHER MODS

integrate_community_digest_sections (&$builders, &$ctx)

Add or replace a section with $builders['my_section'] = callable. The callable receives &$ctx and returns either

  array('title' => 'My section', 'items' => CommunityDigestEngine::take($ctx, $candidates))

where $candidates is id_topic => array('topic' => $row, 'score' => float, 'reason' => string, 'badge' => string), or

  array('title' => '...', 'lines' => array(array('text' => '', 'href' => '', 'meta' => '')))

Useful parts of $ctx: member, prefs, since, until, boards (visible board ids), pool (board-filtered candidate topics), pool_all, signals and used. Always access-check anything you add that isn't from 'pool'.

UNINSTALLING

Uninstalling disables the scheduled task. Tick "Remove all data associated with this modification" to drop the five digest_* tables, all cd_* settings, the digest alerts and the alert preference.

VERSION 1.1.0 FIXES

- Email send failures and daily caps use a retryable queue, independently of on-forum delivery.
- Moderators can only exclude topics in boards where they have permission.
- Club picks use the same viewer-verified board visibility as ordinary topics.
- Text and URLs in dashboard/email output are escaped and restricted to safe link schemes.
- Corrected member lookup by name in administrator test delivery.
- Unchecking every digest section now persists correctly.

UPGRADING FROM 1.0.0 OR 1.0.1

Use SMF Package Manager's Upgrade action. The upgrade adds digest_email_queue, preserves the original four tables and settings, replaces source and theme files, and unregisters the older ineffective scheduled-task include hooks. The task callable now explicitly loads its source file. Back up the forum first.

The scheduled task uses SMF's source-file-prefixed callable, so it loads correctly even when no digest page has been visited.

1.1.0 preserves the corrected 1.0.1 Questions & Answers and Ranked Polls adapters and corrects the Clubs 1.1.1 and Profile Wall 1.1.0 adapters against their actual schemas.

VERSION 1.1.1 FIXES

- Queued email retries retain their original window end, so a delayed email cannot pull in later topic posts, registrations or profile-wall comments. Later activity no longer hides a qualifying topic, although mutable topic metadata and other live-only sections are not historical snapshots.
- Admin test deliveries no longer silently create a subscriber or set an immediate next-send time; test emails omit an unsubscribe link because they are not subscriptions.
- Upgrades from 1.1.0 are supported without resetting member preferences or the existing email queue.

VERSION 1.1.2 FIXES

- Board access now uses SMF 2.1's board_permissions_view (including deny groups and board-manager access), preventing incorrect visibility when board access data and legacy board fields disagree.
- Candidate and message-window caches use exact start times, preventing different members' reporting periods from accidentally sharing cached results.
- Empty on-forum refreshes record an empty batch marker without deleting past recommendation history, so old recommendations no longer appear as the latest digest. No database migration is required.
- Queued email retries select topics from messages within the originally scheduled reporting period, even when a topic has received newer replies. Board visibility and approval are always checked at delivery time.
- Poll suggestions now require both poll_view and poll_vote and omit polls already voted in, including Ranked Polls ballots when that companion mod is enabled.

UPGRADING FROM 1.1.1

Use SMF Package Manager's Upgrade action. All existing preferences, historical recommendations and email jobs are preserved. The database install script is safe to rerun and does not require new tables or columns.

VERSION 1.1.3 INSTALLATION FIX

- Fixes "Data too long for column 'callable' at row 1" during installation.
- Replaces the 74-character scheduled task callable with a 55-character,
  source-file-qualified entry point compatible with SMF 2.1's column limit.
  The entry point invokes the same CommunityDigestEngine::scheduledTask method.
- Re-running installation after a failed 1.1.2 install is safe: existing
  digest tables and settings are preserved, and the scheduled task is inserted
  or updated by its stable task name without creating duplicates.
- Includes an explicit upgrade path from 1.1.2, preserving preferences,
  recommendation history, logs, and queued emails.

AFTER A FAILED 1.1.2 INSTALL

Upload and install 1.1.3 using Admin > Packages > Download Packages. If SMF
lists 1.1.2 as installed, choose the Upgrade action instead. You do not need
to manually modify the scheduled_tasks table or remove existing digest data.
Once installed, verify the Community Digest entry under Admin > Maintenance >
Scheduled Tasks and use Run Now once on a test forum to confirm delivery.

VERSION 1.1.4 ACTIVITY STREAM DETECTION

- Corrects the Activity Stream integration status. Previous releases treated the
  absence of one hardcoded table (activity_follows) as proof that Activity Stream
  was not installed.
- The status now checks SMF's installed-package record as well as supported
  follow-table schemas, without modifying or reinstalling Activity Stream.
- Recognises alternate Activity Stream follow-table names and verifies the
  actual follower/followed column names before making a query.
- If Activity Stream is installed but uses an unrecognised follow-table schema,
  the admin status reports "Installed; following data not recognised". The
  adapter safely remains inactive rather than issuing invalid SQL.
- Adds the direct upgrade path from 1.1.3. All prior preferences, digests,
  email queues and scheduled tasks are preserved. No schema changes.

To upgrade, use SMF Package Manager's Upgrade action for Community Digest. The
Activity Stream installation itself does not need to be changed. If the status
is "Installed; following data not recognised", the exact Activity Stream ZIP
is needed to map its follow schema; do not manually rename tables.

VERSION 1.1.5 MEMBER PREFERENCE FILTERING

- Optional companion-mod sections are no longer shown on the member preference screen unless the supporting integration is active.
- Questions & Answers, Clubs and Profile Wall are now hidden from "What to include" when those mods are not installed or their digest integration is disabled.
- Saving preferences now treats the visible member choices as the full available set, so hidden optional sections are not preserved as if they were still selectable.

VERSION 1.1.6 AVAILABILITY AND UPGRADE FIXES

- Adds explicit Package Manager upgrade paths from 1.1.4 and 1.1.5.
- Detects the actual companion-mod source files as well as their database tables, preventing leftover data after uninstallation from advertising a nonexistent integration.
- Applies section availability consistently to admin section switches, new digests, stored dashboard cards and member preferences. A removed or disabled section disappears from the dashboard immediately, without waiting for the next scheduled refresh.
- Hides Upcoming events if SMF Calendar is disabled or the member cannot view it, and New members if the member cannot view the member list.
- Hides Polls to vote in if the member lacks view and vote permissions on all accessible boards. Stock SMF buddies continue to make the Following section available even without Activity Stream.
- Existing preferences, historical recommendations and queued email jobs remain unchanged.

VERSION 1.1.7 FIXES

- Members on both on-forum and email delivery now get their picks in the email too. The on-forum copy was stored first and marked every pick as already shown, so the email arrived with only events, new members and stats, or was skipped as empty.
- The dashboard sidebar no longer adds "Undefined array key" warnings to the error log for the Community at a glance and Your profile wall blocks.
- Upcoming event titles now use the forum's word censoring.
- Admin test delivery now finds members whose names contain characters such as apostrophes or ampersands.
- The credits line shows the version number, like the other BoredSkull mods.
- New default settings take effect straight after installing, without waiting for SMF's settings cache to expire.
- One upgrade path now covers every version from 1.0.0 to 1.1.6.

To upgrade, use the Package Manager's Upgrade action. Preferences, recommendation history, logs and queued emails are kept. There are no database changes.
