<?php

/**
 * Trust Levels: English strings.
 *
 * @package TrustLevels
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

$txt['tl_title'] = 'Trust Levels';
$txt['tl_desc'] = 'Members move up levels automatically as they take part. Each level can limit links, images and daily posting, and can give a membergroup so SMF permissions follow.';
$txt['tl_credits'] = 'Trust Levels %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';

$txt['tl_tab_levels'] = 'Levels';
$txt['tl_tab_members'] = 'Members';
$txt['tl_tab_log'] = 'Log';
$txt['tl_tab_recalc'] = 'Recalculate';
$txt['tl_tab_settings'] = 'Settings';

// Common
$txt['tl_level'] = 'Trust level';
$txt['tl_level_n'] = 'Trust level %1$d';
$txt['tl_unlimited'] = 'Unlimited';
$txt['tl_none'] = 'None';
$txt['tl_any'] = 'Any';
$txt['tl_allowed'] = 'Allowed';
$txt['tl_not_allowed'] = 'Not allowed';
$txt['tl_n_per_post'] = '%1$s per post';
$txt['tl_n_per_day'] = '%1$s per day';
$txt['tl_remove'] = 'Remove';
$txt['tl_move_up'] = 'Move up';
$txt['tl_move_down'] = 'Move down';
$txt['tl_by'] = 'by %1$s';
$txt['tl_manual_short'] = 'given by staff';

// Requirements and limits
$txt['tl_req_days'] = 'Days since joining';
$txt['tl_req_posts'] = 'Posts';
$txt['tl_req_topics'] = 'Topics started';
$txt['tl_req_likes'] = 'Likes received';
$txt['tl_req_read'] = 'Topics read';
$txt['tl_req_online'] = 'Minutes online';
$txt['tl_req_warning'] = 'Warning level no higher than %1$d%%';
$txt['tl_limit_links'] = 'Links per post';
$txt['tl_limit_images'] = 'Images per post';
$txt['tl_limit_topics_day'] = 'New topics per day';
$txt['tl_limit_replies_day'] = 'Replies per day';
$txt['tl_limit_sig_links'] = 'Links in signature';

// Post errors
$txt['error_tl_no_links'] = 'Members at the %1$s trust level can\'t post links yet. Remove the links or wait until you reach the next level.';
$txt['error_tl_too_many_links'] = 'Members at the %2$s trust level can post up to %1$d links per post.';
$txt['error_tl_no_images'] = 'Members at the %1$s trust level can\'t post images yet.';
$txt['error_tl_too_many_images'] = 'Members at the %2$s trust level can post up to %1$d images per post.';
$txt['error_tl_topic_limit'] = 'Members at the %2$s trust level can start up to %1$d topics every 24 hours. Please try again later.';
$txt['error_tl_reply_limit'] = 'Members at the %2$s trust level can post up to %1$d replies every 24 hours. Please try again later.';

// Profile
$txt['tl_profile_label'] = 'Trust level';
$txt['tl_all_levels'] = 'All trust levels';
$txt['tl_exempt_note'] = 'Staff are not limited by trust levels.';
$txt['tl_locked_note'] = 'This level was set by staff and won\'t change automatically.';
$txt['tl_progress_to'] = 'Progress to %1$s';
$txt['tl_top_reached_title'] = 'Highest level reached';
$txt['tl_top_reached'] = 'This is the highest trust level. Thank you for being part of the community!';
$txt['tl_next_manual'] = 'The next level is given by the staff to trusted members. Keep taking part!';
$txt['tl_reqs_met'] = '%1$d of %2$d requirements met. Levels are checked about once an hour while you browse.';
$txt['tl_no_reqs'] = 'No requirements: this level is reached on the next check.';
$txt['tl_at_this_level'] = 'At this level';
$txt['tl_manage'] = 'Manage trust level';
$txt['tl_lock'] = 'Lock this level';
$txt['tl_lock_desc'] = 'A locked level never changes automatically. Untick to go back to automatic levels (a higher level set by hand is kept unless demotion is set to "always").';
$txt['tl_reason'] = 'Reason (optional)';
$txt['tl_save_level'] = 'Save';
$txt['tl_history'] = 'History';
$txt['tl_col_when'] = 'When';
$txt['tl_col_change'] = 'Change';
$txt['tl_col_reason'] = 'Reason';
$txt['tl_col_member'] = 'Member';
$txt['tl_col_posts'] = 'Posts';
$txt['tl_col_registered'] = 'Registered';
$txt['tl_col_last_login'] = 'Last active';
$txt['tl_col_updated'] = 'Level changed';
$txt['tl_reason_auto'] = 'Automatic';
$txt['tl_reason_initial'] = 'First calculation';
$txt['tl_reason_manual'] = 'Set by staff';

// Levels editor
$txt['tl_levels_intro'] = 'Members start at the first level and move up when they meet all of a level\'s requirements (and those of every level below). A level marked "given by staff" is never reached automatically. Leave a limit empty for unlimited, or enter 0 to block it. Give a level a membergroup to control any SMF permission (PMs, polls, attachments, post approval and so on) through that group.';
$txt['tl_add_level'] = 'Add level';
$txt['tl_save_levels'] = 'Save levels';
$txt['tl_levels_after_save'] = 'Changes apply as members are next checked. To apply them to everyone now, use Recalculate.';
$txt['tl_levels_saved'] = 'Levels saved. Use Recalculate to apply the changes to every member now.';
$txt['tl_field_name'] = 'Name';
$txt['tl_field_color'] = 'Colour';
$txt['tl_field_group'] = 'Membergroup';
$txt['tl_no_group'] = '(none)';
$txt['tl_field_manual'] = 'Only given by staff';
$txt['tl_field_description'] = 'Description';
$txt['tl_field_max_warning'] = 'Max warning %';
$txt['tl_requirements'] = 'Requirements';
$txt['tl_limits'] = 'Limits';
$txt['tl_limits_hint'] = 'Empty = unlimited. 0 = not allowed. Quoted text and links to this forum (if allowed in Settings) are not counted.';
$txt['tl_first_level_note'] = 'Every member starts at this level, so it has no requirements.';
$txt['tl_promotion_message'] = 'Promotion message';
$txt['tl_pm_subject'] = 'PM subject';
$txt['tl_pm_body'] = 'PM message (BBCode allowed)';
$txt['tl_pm_placeholders'] = 'Placeholders: {name}, {level}, {forum}, {link} (the member\'s trust level page). Leave empty to send nothing for this level.';
$txt['tl_err_name'] = 'Level %1$d needs a name.';
$txt['tl_err_group'] = 'The membergroup chosen for "%1$s" can\'t be used. Pick a regular group (not post-based, Administrator or Moderator).';
$txt['tl_err_group_dupe'] = 'The membergroup for "%1$s" is already used by another level.';
$txt['tl_err_count'] = 'You need at least two levels.';

// Members
$txt['tl_all_members'] = 'All members';
$txt['tl_locked_only'] = 'Locked';
$txt['tl_uncalculated'] = '%1$s members haven\'t been calculated yet. They\'ll be checked on their next visit, or you can <a href="%2$s">recalculate everyone now</a>.';
$txt['tl_no_members'] = 'No members to show.';

// Log
$txt['tl_no_log'] = 'No level changes yet.';

// Recalculate
$txt['tl_recalc_intro'] = 'Levels are normally checked when members post, about once an hour while they browse, and daily for recently active members. Run this after changing the levels, or when first installing, to update everyone straight away. It runs in batches, so it is safe on large forums.';
$txt['tl_recalc_run'] = 'Recalculate all members';
$txt['tl_recalc_sync'] = 'Recalculate and re-apply membergroups';
$txt['tl_recalc_sync_desc'] = 'Use the second option after changing which membergroup a level gives. Members are added to their level\'s group and removed from the other levels\' groups.';
$txt['tl_recalc_progress'] = 'Checked %1$s of %2$s members. %3$s levels changed.';
$txt['tl_continue'] = 'Continue';
$txt['tl_recalc_done'] = 'Recalculation finished.';

// Settings
$txt['tl_enabled'] = 'Enable trust levels';
$txt['tl_demote'] = 'Lowering levels';
$txt['tl_demote_desc'] = 'What happens when a member no longer meets a level\'s requirements.';
$txt['tl_demote_never'] = 'Never: levels only go up';
$txt['tl_demote_warnings'] = 'Only for warnings: drop down if the warning level is too high';
$txt['tl_demote_always'] = 'Always: recalculate from the requirements every time';
$txt['tl_exempt_groups'] = 'Exempt membergroups';
$txt['tl_exempt_groups_desc'] = 'Members of these groups get the top level with no limits. Administrators are always exempt.';
$txt['tl_allow_internal'] = 'Don\'t count links to this forum';
$txt['tl_allow_internal_desc'] = 'Links to topics, posts and pages on this forum are always allowed.';
$txt['tl_apply_pm'] = 'Apply link and image limits to personal messages';
$txt['tl_apply_pm_desc'] = 'Extra links in PMs are turned into plain text. To stop low levels sending PMs at all, give the level a membergroup without the "Send personal messages" permission.';
$txt['tl_badge'] = 'Show level badge on posts';
$txt['tl_badge_off'] = 'Don\'t show';
$txt['tl_badge_below_avatar'] = 'Below the avatar';
$txt['tl_badge_icons'] = 'With the group icons';
$txt['tl_badge_standard'] = 'With the profile fields';
$txt['tl_badge_min'] = 'Show badges from level';
$txt['tl_badge_min_desc'] = 'For example, hide the badge for brand-new members.';
$txt['tl_profile_public'] = 'Anyone can see a member\'s progress';
$txt['tl_profile_public_desc'] = 'When this is off, only the member and staff can see progress and limits. Everyone can see the level itself.';
$txt['tl_promotion_pm'] = 'Send a PM when a member reaches a new level';
$txt['tl_promotion_pm_desc'] = 'Each level\'s message is set on the Levels page.';
$txt['tl_pm_from'] = 'Send promotion PMs from member ID';
$txt['tl_pm_from_desc'] = 'Usually an administrator or a staff account. Member 1 is the forum\'s first administrator.';

// Permissions
$txt['permissionname_tl_manage'] = 'Manage members\' trust levels';
$txt['permissionhelp_tl_manage'] = 'Allows setting and locking a member\'s trust level from their profile.';

// Scheduled task
$txt['scheduled_task_tl_recalc'] = 'Update trust levels';
$txt['scheduled_task_desc_tl_recalc'] = 'Recalculates the trust levels of recently active members and removes log entries older than a year.';

// 1.1.0
$txt['tl_event_lock'] = 'Locked';
$txt['tl_event_unlock'] = 'Unlocked';
$txt['tl_event_held'] = 'Promotion held';
$txt['tl_reason_probation'] = 'Probation after a warning, until %1$s';
$txt['tl_probation_note'] = 'On probation after a warning: automatic promotion is paused until %1$s.';
$txt['tl_protected_note'] = 'Level groups assigned by staff, which trust levels won\'t remove: %1$s.';
$txt['tl_probation_days'] = 'Probation period after a warning';
$txt['tl_probation_days_desc'] = 'For this many days after a member\'s warning level goes up, they can\'t be promoted automatically, even if the warning level drops again. Staff can still change levels by hand. 0 = off.';
$txt['tl_days'] = 'days';
$txt['tl_protect_groups'] = 'Protect level groups assigned by staff';
$txt['tl_protect_groups_desc'] = 'Trust levels only remove level membergroups that it added itself. A level group a moderator gives someone by hand stays, whatever their level. Turn off to make trust levels fully control those groups.';
