# Slow Mode 1.0.4 (TEST)

Standalone SMF 2.1.x BoredSkull modification. ISC licence. Hooks only, no core/theme edits.

## 1.0.4 fixes
- Remove invalid Subs-DbPackage.php dependency; load database package functions via SMF db_extend.
- Use SMF column nullability option.

## 1.0.3 fixes
- Board cooldowns apply to new topics and poll submissions as well as replies.
- Active cooldowns use the current rule duration and last successful posting timestamp.
- Explicit posting validation errors release their own in-flight claim; interrupted requests retain the safety lease.
- English language fallback and translated settings, notices and template labels.
- Database package API for table creation and portable orphan cleanup queries. Existing tables remain intact.

## 1.0.2 fixes
- Register the Slow Mode subsection and its translated label before SMF builds the admin menu.
- Redirect to Slow Mode settings after successful installation.
- Remove the new admin menu hook during uninstall.

## 1.0.1 fixes
- Guest cooldowns use a keyed HMAC of the IP address (not raw IP storage); users sharing an IP share the cooldown. No identity is exposed in the UI.
- The full cooldown starts at `integrate_post2_end` after successful publication. An in-flight lease prevents near-simultaneous posts; failed or interrupted requests may keep a short lease (up to 90 seconds), not a full cooldown. This avoids falsely releasing a claim after a topic was already saved but before the completion hook runs.
- A visible topic notice shows rules and approximate remaining time, using escaped text.
- Audits changes to board/topic rules, including member ID, old/new minutes, and UTC timestamp. Admin page shows latest 30 changes.
- Daily SMF maintenance prunes orphaned rules and old expired claims.

## Configuration
Admin > Configuration > Modification Settings > Slow Mode. Per-topic controls appear on permitted topics. Existing settings and rules are retained on upgrade/uninstall.

## Integration API
`integrate_bsm_exemption(&$exempt, $id_member, $id_board, $id_topic)` is supported. Trust Levels can set `$exempt = true` when installed; standalone otherwise.

## Important caveats
The 1.0.0 `bsm_cooldowns` table is retained but no longer used. New `bsm_slots` table handles members and guests. Existing cooldown reservations from 1.0.0 cannot be safely migrated, but previous posts still enforce limits for registered members. Guest cooldown history starts after installing this release. A guest posting from a new IP can bypass a prior IP's slot; IPs behind a shared NAT share a cooldown. In low-traffic forums daily cleanup may run later. Uninstall retains records; database maintenance and deletion should be separately backed up.

**Staging only.** Live SMF integration, database concurrency and guest posting workflows have not been tested on an actual forum. Test before live use.
