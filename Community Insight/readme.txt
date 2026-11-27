Community Insights 1.1.1 by BoredSkull
SMF 2.1.x | ISC licence | https://boredskull.net

FEATURES
========
The existing hook-based Community Insights dashboard remains available. The new
navigation adds Detailed Reports, Saved Reports, Export History, and administrator
pages for Automated Reports, Alerts and Permissions.

1. Saved reports: create, edit, run or delete your own named report definitions.
2. CSV and JSON downloads from permitted reports.
3. Detailed Content & Engagement report.
4. Relative topic engagement scores (0-100 among recent candidates), with
   configurable scoring weights in Admin > Community Insights.
5. First external-reply average/median and per-day response-time data.
6. Separate on/off switches for the five built-in companion integrations.
7. An administrator permissions overview linking to native SMF membergroup permissions.
8. Automated daily, weekly, and approximate 30-day monthly email summaries.
9. Retention cohorts measuring activity at days 7, 30 and 90.
10. New-member onboarding funnels including first post, first topic, replies
    received, and five-post contributors.
11. Topic momentum based on the last 24 hours vs the preceding 24 hours.
12. UTC day/hour posting heatmap.
13. Side-by-side comparison for up to five visible boards, including period-over-
    period change, contributors and 48-hour response rates.
14. Daily report counts and time-to-last-update proxies for moderation workload.
15. Administrator email threshold alerts for posts, posting drops, new members,
    unanswered topics and report backlog.
16. Member contribution insights based on external replies and discussions helped.
17. Automatic daily per-board snapshots (two-year retention).
18. Customisable layouts with drag ordering, up/down buttons, width selection
    and dashboard presets. Existing 1.0.x widget choices are preserved.
19. Developer hooks for external data providers and additional report types.

MENU
====
The header's Community Insights button opens the original dashboard. The tabs
there link to reports, history and administration-specific features. The
community's active SMF theme provides the page chrome; the mod uses SMF's
normal catbg/titlebg/windowbg classes rather than replacing theme templates.

INSTALL / UPGRADE
=================
Upload this ZIP via Admin > Packages > Download Packages > Upload Package.
SMF 2.1.x supported. Package includes a direct upgrade definition for 1.0.x and 1.1.0.
Back up files and database before upgrading, especially on a live forum.
The installer adds six new ci_ tables without dropping or changing ci_layouts:
 ci_layout_meta, ci_reports, ci_export_history, ci_schedules,
 ci_alert_rules, ci_snapshots.
It installs a daily scheduled task named community_insights with the callable
 ci_run|$sourcedir/CI-Tasks.php
(SMF's "function|file" format) and a source file separate from any companion
modification. Upgrading from 1.1.0 repairs the callable automatically.

PERMISSIONS / PRIVACY
=====================
No public analytics dashboard is enabled by default. A member needs ci_view,
plus ci_core, ci_members, ci_moderation and/or ci_integrations as appropriate.
Downloads additionally require ci_export. All member-facing board reports use
SMF's {query_see_board} security filtering. Report ownership and permissions
are checked again when a saved report is viewed or exported. Report metadata
and export history are private to the account that created them.

Automated emails and alert rules are only configurable by administrators.
A scheduled report is mailed ONLY to its owning account while the account is
still activated and belongs to SMF's Administrator group. To avoid an unscoped
cron task leaking message content, the email includes a report-type-specific bounded numeric aggregate summary
using the saved date selection. Snapshot-derived numbers include only complete
recorded UTC days; unknown/missing days are never estimated. For reports that
cannot be safely summarised in a scheduled task, the email includes an explicit
notice and an authenticated link. It is NOT a full saved report or CSV
attachment. The full report is run in the forum with up-to-date permissions.
Uninstalling without removing data should retain personal layouts/saved
reports/snapshot data. The scheduled task is removed on uninstall. If you choose
Remove all data, the mod's new and old tables/settings are deleted.

FURTHER PRE-RELEASE AUDIT FIXES (STILL 1.1.0)
===========================================
- The dashboard's moderation workload now filters items by the selected
  creation-date period. Its pending/open figures explicitly describe CURRENT
  state of those period items, because SMF cannot reconstruct past statuses.
- Unanswered-topic alerts include starter-only follow-up conversations,
  using an approved external reply test. Alerts are limited to currently
  unlocked topics older than the configured response window, excluding the
  recycle board when enabled. This is a current backlog metric, not the
  dashboard's date-scoped unanswered list.
- Scheduled report summaries now use the saved report's date options and
  report type rather than sending the same previous-day post/topic totals
  for every schedule. The job still avoids impersonating forum members and
  only emails verified current administrators. See limitations above.

HISTORICAL REPORTING CORRECTIONS (1.1.0 PRE-RELEASE)
====================================================
- Response-time reports count only external replies on or before the
  selected report end date.
- Discussion health and board comparison response rates evaluate only topics
  that had their full response window before that end date.
- Historical unanswered lists ignore replies posted after the selected end.
- Onboarding's "received a reply" metric cannot count later replies.
- Dashboard member-engagement windows mature relative to the report end and
  cannot count posts outside the selected end date.
- Partial monthly retention cohorts stop at the selected end date.
- Topic engagement ranking replies and contributor counts stop at the
  selected end date. SMF does not retain historical per-topic view counts,
  so views are correctly identified as current totals, not reconstructed.
- Other existing live-only metrics (e.g. all-time forum totals, current
  moderation state, topic views) cannot be reconstructed as exact past states.
  Deleted messages and past approval states are likewise unavailable.

REPORT MEASUREMENT NOTES
========================
- Topic ranking: the score is relative to a bounded set of recent topics,
  NOT an absolute quality score. Adjust weights in Admin > Community Insights.
- Topic momentum: 24-hour activity change, not all-time views.
- Onboarding: up to 500 recent activated signups in the chosen window.
- Retention: up to 250 activated signups in each of six monthly cohorts; only
  posting in currently visible boards qualifies. New cohorts are not counted
  as retained until they have matured to each 7/30/90-day checkpoint.
- Response times: sample of up to 2,000 recent topics from the selected period.
- Moderation time: SMF time_updated is a proxy for last update, not the exact
  closed-at timestamp. Reports use only boards visible to the viewer.
- Snapshots are taken for completed UTC days from the day after installation.
  Historical days are NOT silently reconstructed. Deleted boards are excluded
  from member-scoped historical charts.
- Export History stores download event metadata, not copies of CSV/JSON files;
  re-run a report to get fresh data checked against current permissions.
- Historical snapshots are recorded on the forum's scheduled-task runs, so an
  inactive forum may have missing dates until tasks execute.
- Boards with restricted content are excluded from delegated member reports.

DEVELOPER HOOKS
===============
integrate_ci_providers (&$providers, $dates)
  Existing provider hook from 1.0.x, with permission-gated scalar aggregates.

integrate_ci_report_charts (&$charts, $reportType, $dates)
  Add up to four charts to a report. Each chart is an array containing a
  plain text title and a numeric label=>value points array (48 points max).
  HTML is stripped and labels/values are escaped before rendering.

integrate_ci_report_types (&$types)
  Add a report definition under an identifier matching /^[a-z][a-z0-9_]{0,31}$/.
  Example:
  $types['mymetric'] = array(
      'label' => 'My metric',
      'permission' => 'ci_core',
      'callback' => 'my_metric_callback'
  );
  The callback receives ($dates, $options) and returns a bounded scalar array.
  Never return private content if the permission would allow unauthorised users.
  The module sanitises provider output and never renders supplied raw HTML.

STAGING / VERIFICATION
======================
Before publishing, test fresh install, upgrade from 1.0.3, custom SMF themes,
membergroup permissions, various optional mods enabled/disabled, exports,
email sending, and manually running the scheduled task. Test with a database
backup. Performance-sensitive cohort/first-reply reports should be benchmarked
on a large forum. This package is not claimed to have been live-tested.

CHANGELOG
=========
1.1.1:
- Fixed the daily scheduled task never running. Its callable was stored as
  file|function, but SMF reads function|file, so snapshots, emailed reports
  and threshold alerts never happened. Upgrading repairs the stored callable.
- Fixed the dashboard ignoring the saved widget order. Drag and arrow ordering
  now takes effect.
- Fixed a chosen dashboard preset overwriting later manual widget changes.
  Changing a widget, its width or its order now switches the preset to Custom.
- Fixed board and member names with & or quotes showing double-escaped
  (e.g. Q&amp;A) in detailed report tables.
- Fixed the Trending report failing on PostgreSQL.
- Added an upgrade path from 1.1.0.
1.1.0: New reporting suite and all nineteen enhancement categories above.
1.0.3: Cache isolation and optional integrations stability fixes.

1.1.0 VERIFIED RELEASE CORRECTIONS (version number unchanged)
- Historical Trending reports now compare two equal windows within the selected date range.
- Dashboard presets, detailed reports and CSV/JSON exports share one UTC date-window calculation with inclusive boundaries and no future timestamps. Export forms carry exact on-screen start/end timestamps so downloads do not shift at the next cache boundary.
- Admin saves preserve integration preferences for companion mods that are currently unavailable.
- Cohort retention requires activity at least 24 hours after registration and respects the report end date.
- CSV/JSON export POST actions now validate the same ci_report token included in dashboard and detailed report forms, in addition to SMF session validation.
- All pre-existing database tables, settings, version identifiers and upgrade routes are retained.
