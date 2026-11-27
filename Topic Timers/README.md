# Topic Timers 1.0.6

BoredSkull • SMF 2.1.x • ISC licence

Schedules topic locking, unlocking, moving to another board, pinning, unpinning, or removal. Runs on the native SMF scheduled task system at one-minute intervals. No core or theme source edits. Requires MySQL/MariaDB; PostgreSQL is not supported by this build.

## Installation

1. Back up the database and test on a staging installation.
2. Upload ZIP in SMF Package Manager.
3. Configure under **Admin → Configuration → Modification Settings → Topic Timers**.
4. Open a topic, then use the **Topic timer** button.

## Safety

- Guests cannot schedule actions. Non-admins require `lock_any`, `make_sticky` or `move_any` as appropriate. Only forum administrators may schedule removal.
- Removal is disabled by default; enabling it is a conscious administrative action. It uses SMF's standard `removeTopics()` routine, which uses the recycle bin if configured.
- Jobs execute as scheduled moderator actions; the creator's current membergroup and board permissions are rechecked when a timer runs by default. Admins can turn this off in settings, explicitly accepting the risk of previously authorized timers executing after a member loses permissions.
- Timers use UTC and are executed when SMF scheduled tasks run, typically within a minute after the intended time. Low-traffic forums may run tasks later without external cron.
- A claim/lease protects against concurrent execution; non-destructive failed tasks retry with increasing delays up to five times; move/removal failures require manual review; permission changes require manual review. Administrators can retry failed timers from the topic timer page. Move, remove, lock, unlock pin and unpin have state checks to reduce repeated side effects after interrupted execution.
- Timer history is intentionally retained on uninstall for recovery. To permanently erase it, an administrator can drop `smf_btt_timers` after checking the database prefix.
- Crash recovery uses a 15-minute lease expiry and manual review of interrupted jobs; external modifications during execution can still cause race conditions. Live tests are required.

## Hooks-based ecosystem API

- `integrate_btt_created($id_topic, $action, $execute_at)` after a timer is queued.
- `integrate_btt_before_execute(&$timer, &$permit)` to veto a pending action. Set `$permit=false` to reject.
- `integrate_btt_executed($timer)` on successful execution.

Scheduled Topics may register its own timers using the public queue semantics; Event Manager can schedule lock/unpin/remove actions for event discussion topics. Activity Stream, Insights and Community Digest can listen to execution hooks. No integration-specific UI or references appear without the relevant mod installed. Topic Timers works on its own.

## Validation and deployment

PHP lint, ZIP integrity, XML parsing and package consistency checks have been performed. The maintainer reported successful live testing of 1.0.5; the additional 1.0.6 safeguards have not been independently verified on a live SMF database. Test on a staging forum before production deployment.

## 1.0.4 changes

- Show server UTC time at page load, scheduler enabled/missing/disabled/due state, last logged run and next scheduled check on both screens.
- Link administrators to native scheduled task controls for manual testing.
- Display overdue pending timers without changing stored status; show retry delay and colour-coded labels, alternating history rows and aligned options.
- Refresh SMF's global next-task cache after installation so a newly registered task is not hidden behind a stale scheduler timestamp.
- Native scheduling still depends on SMF task execution; no guarantee of exact-minute execution. Last logged run is not proof that each timer succeeded.
- Uninstall 1.0.3 before installing 1.0.4. Settings and timer rows remain intact.

## 1.0.3 changes

- Load scheduled-task labels using the native management hook, with English fallbacks.
- Retain the verified native installation redirect.
- Enforce board visibility on source and destination actions; correct group-0 handling and include local moderator groups on revalidation.
- Serialize scheduled workers with a MySQL/MariaDB advisory lock and defer later jobs while earlier active jobs for the same topic remain pending.
- Require manual review after interrupted execution or move/removal exceptions.
- Uninstall 1.0.2 before installing 1.0.3; existing records and settings are preserved.

## 1.0.2 changes

- Register the Topic Timers subsection before SMF constructs its admin menu, with a readable language label.
- Add scheduled task language strings.
- Redirect after successful installation to Modification Settings → Topic Timers using the native package redirect.
- To update from 1.0.1, uninstall it then install 1.0.2; existing timer history and settings are retained.

## 1.0.1 changes

- Configurable author-permission revalidation (default enabled), including destination-board move permissions.
- Recovery of abandoned claims with a failed state after five tries and state-aware retries.
- Failed-timer manual retry for administrators, including resetting attempts and error state.
- Exponential retry backoff, and prevention of same-topic timers sharing a timestamp.
- Installer migration for retry_at and preservation of existing settings and timer history.
- Execution hooks run after marking actions complete, so observer failures do not trigger duplicate retries.

## Updating from 1.0.0

To upgrade from any earlier version, uninstall the existing package using SMF Package Manager, then install 1.0.6. Uninstallation deliberately leaves the timer database table and administrative settings in place. The 1.0.1 installer adds the `retry_at` column if it is missing, reuses the existing queue and avoids registering a second scheduled task.

The administrators' **Modification Settings → Topic Timers** page shows the latest 100 failed timers and allows an explicit retry. Inspect a destructive timer's topic state before retrying. When multiple timers share a topic but have different timestamps, SMF executes them in due-time and timer-ID order; note that a long-running task may delay following actions.

No full independent live SMF integration test of 1.0.6 has been performed. SMF's native scheduled task can execute late on low-traffic forums; a dedicated cron setup is recommended where time accuracy matters. Do not use delete timers with important data until end-to-end testing is complete.

## 1.0.5 changes
- Supply a numeric guest member context for native move/removal during cron; restore the original context afterwards.
- Add pin (make sticky), with the same permission as unpin. Show only the applicable sticky and lock action; validate again on submission.
- Check move destination after native move returns.
- Uninstall 1.0.4 before installing 1.0.5. Retain existing timer records.
- A move interrupted by the earlier cron error may already have changed database rows. Inspect the topic and run SMF maintenance recount/repair as appropriate before retrying; this release does not repair prior partial moves automatically.

## 1.0.6 changes

- Recognise both locked states; verify native removal including recycle board.
- Revalidate cancellation against current board.
- Serialize timer creation and retries with worker lock; reject same-minute conflicts.
- Update pinning description. Uninstall 1.0.5 before installing 1.0.6.
