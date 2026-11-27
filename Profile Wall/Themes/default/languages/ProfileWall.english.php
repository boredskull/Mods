<?php
// Profile Wall - English

// Profile
$txt['pw_tab'] = 'Wall';
$txt['pw_title'] = '%1$s\'s Wall';
$txt['pw_comment_count'] = '%1$d comments';
$txt['pw_leave_comment'] = 'Leave a comment';
$txt['pw_post_own'] = 'Post on your wall';
$txt['pw_post'] = 'Post comment';
$txt['pw_no_comments'] = 'No comments yet. Be the first!';
$txt['pw_max_chars'] = 'Maximum %1$d characters';
$txt['pw_reply'] = 'Reply';
$txt['pw_reply_placeholder'] = 'Write a reply...';
$txt['pw_like'] = 'Like';
$txt['pw_unlike'] = 'Unlike';
$txt['pw_pin'] = 'Feature';
$txt['pw_unpin'] = 'Unfeature';
$txt['pw_approve'] = 'Approve';
$txt['pw_report'] = 'Report';
$txt['pw_cancel'] = 'Cancel';
$txt['pw_permalink'] = 'Link to this comment';
$txt['pw_edited_by'] = 'Edited by %1$s, %2$s';
$txt['pw_delete_confirm'] = 'Delete this comment and any replies to it?';
$txt['pw_edit_comment'] = 'Edit comment';
$txt['pw_badge_owner'] = 'Wall owner';
$txt['pw_badge_pinned'] = 'Featured';
$txt['pw_badge_pending'] = 'Awaiting approval';
$txt['pw_badge_reply'] = 'Reply';
$txt['pw_pending_count'] = 'There are %1$d comment(s) on this wall awaiting approval.';
$txt['pw_notice_pending'] = 'Thanks! Your comment will appear once it has been approved.';
$txt['pw_notice_reported'] = 'Thanks, the comment has been reported to the moderators.';
$txt['pw_notice_saved'] = 'Your wall settings have been saved.';

// Wall settings (owner)
$txt['pw_wall_settings'] = 'Wall settings';
$txt['pw_privacy'] = 'Who can comment on my wall';
$txt['pw_privacy_0'] = 'All members';
$txt['pw_privacy_1'] = 'Only my buddies';
$txt['pw_privacy_2'] = 'Nobody (wall closed)';
$txt['pw_owner_approval'] = 'Approve comments before they appear';
$txt['pw_owner_approval_sub'] = 'You will get an alert for each new comment waiting for you.';

// Status / errors
$txt['pw_status_ignored'] = 'This member is not accepting comments from you.';
$txt['pw_status_closed'] = 'This member has closed their wall to new comments.';
$txt['pw_status_buddies'] = 'Only this member\'s buddies can comment on their wall.';
$txt['pw_status_no_permission'] = 'You are not allowed to post wall comments.';
$txt['pw_err_no_permission'] = 'You are not allowed to do that.';
$txt['pw_err_not_found'] = 'That comment could not be found.';
$txt['pw_err_empty'] = 'Your comment was empty.';
$txt['pw_err_too_long'] = 'Your comment is too long. The maximum is %1$d characters.';
$txt['pw_err_flood'] = 'You are commenting too quickly. Please wait %1$d more second(s).';
$txt['pw_err_like_own'] = 'You cannot like your own comment.';
$txt['pw_err_report_reason'] = 'Please give a reason for the report.';

// Report
$txt['pw_report_comment'] = 'Report comment';
$txt['pw_report_intro'] = 'Tell the moderators what is wrong with this comment.';
$txt['pw_report_send'] = 'Send report';

// Summary
$txt['pw_summary_title'] = 'Latest wall comments';
$txt['pw_view_wall'] = 'View wall';

// Alerts
$txt['alert_group_profile_wall'] = 'Profile wall';
$txt['alert_opt_pw_new'] = 'When someone comments on my wall';
$txt['alert_opt_pw_reply'] = 'When someone replies to my wall comment';
$txt['alert_opt_pw_like'] = 'When someone likes my wall comment';
$txt['alert_pwall_new'] = '{member_link} left a comment on <a href="{wall_link}">your wall</a>';
$txt['alert_pwall_reply'] = '{member_link} replied to your comment on <a href="{wall_link}">{wall_name}\'s wall</a>';
$txt['alert_pwall_like'] = '{member_link} liked your comment on <a href="{wall_link}">{wall_name}\'s wall</a>';
$txt['alert_pwall_pending'] = '{member_link} left a comment on <a href="{wall_link}">your wall</a> that needs your approval';
$txt['pw_pm_subject'] = 'New comment on your wall';
$txt['pw_pm_body'] = '%1$s left a comment on your profile wall.' . "\n\n" . '[url=%2$s]Read it here[/url]';

// Permissions
$txt['permissiongroup_profile_wall'] = 'Profile Wall';
$txt['permissionname_profile_wall_view'] = 'View profile walls';
$txt['permissionhelp_profile_wall_view'] = 'Lets members see the Wall tab on profiles.';
$txt['permissionname_profile_wall_post'] = 'Post wall comments';
$txt['permissionhelp_profile_wall_post'] = 'Lets members comment on other members\' walls (subject to each member\'s privacy choice).';
$txt['permissionname_profile_wall_autoapprove'] = 'Post wall comments without approval';
$txt['permissionhelp_profile_wall_autoapprove'] = 'If not set, this group\'s comments wait in the approval queue.';
$txt['permissionname_profile_wall_edit'] = 'Edit wall comments';
$txt['permissionname_profile_wall_edit_own'] = 'Own comments';
$txt['permissionname_profile_wall_edit_any'] = 'Any comments';
$txt['permissionhelp_profile_wall_edit'] = 'Edit wall comments.';
$txt['permissionname_profile_wall_delete'] = 'Delete wall comments';
$txt['permissionname_profile_wall_delete_own'] = 'Own comments';
$txt['permissionname_profile_wall_delete_any'] = 'Any comments';
$txt['permissionhelp_profile_wall_delete'] = 'Delete wall comments.';
$txt['permissionname_profile_wall_moderate'] = 'Moderate profile walls';
$txt['permissionhelp_profile_wall_moderate'] = 'Approve, feature, edit and delete any wall comment, handle reports, and bypass wall privacy.';

// Admin
$txt['pw_admin_title'] = 'Profile Wall';
$txt['pw_admin_desc'] = 'Settings, approval queue and reports for profile wall comments.';
$txt['pw_admin_settings'] = 'Settings';
$txt['pw_admin_queue'] = 'Approval queue';
$txt['pw_admin_reports'] = 'Reports';
$txt['pw_enabled'] = 'Enable profile walls';
$txt['pw_per_page'] = 'Comments per page';
$txt['pw_max_length'] = 'Maximum comment length (characters)';
$txt['pw_flood_seconds'] = 'Minimum time between comments';
$txt['pw_edit_window'] = 'Members can edit their own comments for';
$txt['pw_replies'] = 'Allow replies to comments';
$txt['pw_likes'] = 'Allow liking comments';
$txt['pw_reports'] = 'Allow reporting comments';
$txt['pw_owner_delete'] = 'Wall owners can delete any comment on their wall';
$txt['pw_owner_privacy'] = 'Let members choose who can comment on their wall';
$txt['pw_owner_privacy_sub'] = 'Everyone / buddies only / closed, plus optional owner approval.';
$txt['pw_summary_count'] = 'Latest comments shown on the profile summary';
$txt['pw_pm_notify'] = 'Also send a personal message for new comments';
$txt['pw_pm_notify_sub'] = 'Members always get a normal alert (unless they turn it off).';
$txt['pw_zero_unlimited'] = '0 = no limit';
$txt['pw_zero_off'] = '0 = off';
$txt['pw_seconds'] = 'seconds';
$txt['pw_minutes'] = 'minutes';
$txt['pw_queue_empty'] = 'No comments are waiting for approval.';
$txt['pw_reports_empty'] = 'There are no open reports.';
$txt['pw_col_comment'] = 'Comment';
$txt['pw_col_poster'] = 'Posted by';
$txt['pw_col_wall'] = 'Wall';
$txt['pw_col_time'] = 'Date';
$txt['pw_approve_selected'] = 'Approve selected';
$txt['pw_delete_selected'] = 'Delete selected';
$txt['pw_reported_by'] = 'Reported by %1$s on %2$s';
$txt['pw_reason'] = 'Reason';
$txt['pw_dismiss'] = 'Dismiss report';
$txt['pw_delete_comment'] = 'Delete comment';

// Credits page
$txt['pw_credits'] = 'Profile Wall %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
