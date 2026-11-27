<?php
// Community Digest 1.1.0 - English
// BoredSkull - ISC licence

global $txt;

$txt['cd_credits'] = 'Community Digest %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
$txt['cd_by'] = 'by';

$txt['cd_menu'] = 'Digest';
$txt['cd_whoall'] = 'Viewing their <a href="' . (isset($GLOBALS['scripturl']) ? $GLOBALS['scripturl'] : '') . '?action=digest">community digest</a>.';
$txt['cd_disabled'] = 'The community digest is currently turned off.';
$txt['cd_no_member'] = 'That member could not be found.';

// Frequencies and delivery
$txt['cd_freq_0'] = 'Off';
$txt['cd_freq_1'] = 'Daily';
$txt['cd_freq_2'] = 'Weekly';
$txt['cd_freq_3'] = 'Monthly';
$txt['cd_delivery_0'] = 'On-forum digest only (no emails)';
$txt['cd_delivery_1'] = 'Email only';
$txt['cd_delivery_2'] = 'Email and on-forum digest';

// Sections
$txt['cd_section_recommended'] = 'Picked for you';
$txt['cd_section_following'] = 'From people you follow';
$txt['cd_section_questions'] = 'Popular questions';
$txt['cd_section_unanswered'] = 'Waiting for a reply';
$txt['cd_section_clubs'] = 'Club updates';
$txt['cd_section_polls'] = 'Polls to vote in';
$txt['cd_section_popular'] = 'Popular discussions';
$txt['cd_section_events'] = 'Upcoming events';
$txt['cd_section_newmembers'] = 'New members';
$txt['cd_section_wall'] = 'Your profile wall';
$txt['cd_section_stats'] = 'Community at a glance';
$txt['cd_section_toggle'] = 'Section: %1$s';

$txt['cd_intro_recommended'] = 'Based on the boards you follow, the discussions you take part in and your interests.';
$txt['cd_intro_unanswered'] = 'These haven\'t had a reply yet. Could you help?';
$txt['cd_intro_events'] = 'Coming up in the next %1$d days.';
$txt['cd_intro_newmembers'] = '%1$s new members joined. Say hello!';
$txt['cd_stats_line'] = '%1$s new topics and %2$s posts in boards you can see.';
$txt['cd_wall_line'] = 'You have %1$s new comments on your profile wall.';

// Reasons and badges
$txt['cd_reason_participated'] = 'You took part in this discussion';
$txt['cd_reason_board'] = 'New in %1$s, which you follow';
$txt['cd_reason_following'] = 'Started by %1$s, who you follow';
$txt['cd_reason_keyword'] = 'Matches your interest: %1$s';
$txt['cd_reason_popular'] = '%1$d new posts this period';
$txt['cd_reason_unanswered'] = 'No replies yet';
$txt['cd_reason_needs_answer'] = 'Still needs an accepted answer';
$txt['cd_reason_answered'] = 'Has an accepted answer';
$txt['cd_reason_club'] = 'In your club: %1$s';
$txt['cd_badge_poll'] = 'Poll';
$txt['cd_badge_ranked'] = 'Ranked poll';
$txt['cd_badge_open_question'] = 'Open question';
$txt['cd_badge_solved'] = 'Solved';
$txt['cd_badge_club'] = 'Club';

// Dashboard
$txt['cd_dashboard_title'] = 'Your Community Digest';
$txt['cd_dashboard_summary'] = '<strong>%1$d</strong> recommendations, <strong>%2$d</strong> unread.';
$txt['cd_dashboard_updated'] = 'Updated %1$s.';
$txt['cd_dashboard_empty'] = 'Your digest is empty at the moment.';
$txt['cd_dashboard_tip'] = 'Tip: <a href="%1$s">choose the boards and topics you care about</a> to get better recommendations, and decide whether you want your digest by email.';
$txt['cd_nothing_yet'] = 'Nothing new to recommend right now. Check back later, or browse the latest posts.';
$txt['cd_browse_recent'] = 'Recent posts';
$txt['cd_mark_all_read'] = 'Mark all read';
$txt['cd_mark_read'] = 'Mark read';
$txt['cd_is_read'] = 'Read';
$txt['cd_refresh'] = 'Refresh';
$txt['cd_preview_email'] = 'Preview email';
$txt['cd_preferences'] = 'Preferences';
$txt['cd_card_meta'] = 'by %1$s &middot; %2$s replies &middot; %3$s views';

// Profile
$txt['cd_profile_area'] = 'Community Digest';
$txt['cd_profile_desc'] = 'Get a roundup of the most useful new discussions, popular questions and posts waiting for a reply. No emails are sent unless you choose email delivery.';
$txt['cd_saved'] = 'Your digest preferences have been saved.';
$txt['cd_pref_frequency'] = 'How often';
$txt['cd_pref_frequency_desc'] = 'Also sets how far back your on-forum digest looks.';
$txt['cd_pref_delivery'] = 'Delivery';
$txt['cd_pref_day'] = 'Day for weekly digests';
$txt['cd_pref_day_desc'] = 'Monthly digests arrive on the 1st.';
$txt['cd_pref_hour'] = 'Time of day';
$txt['cd_pref_hour_desc'] = 'In your own time zone.';
$txt['cd_pref_next'] = 'Next digest';
$txt['cd_pref_sections'] = 'What to include';
$txt['cd_pref_sections_desc'] = 'Untick anything you are not interested in.';
$txt['cd_pref_interests'] = 'Your interests';
$txt['cd_pref_keywords'] = 'Interest keywords';
$txt['cd_pref_keywords_desc'] = 'Comma separated, up to 10. Topics whose titles match are ranked higher.';
$txt['cd_pref_boards'] = 'Boards you follow';
$txt['cd_pref_boards_desc'] = 'New discussions in these boards are prioritised. Boards you get notifications for count too.';
$txt['cd_no_boards'] = 'No boards available.';

// Email
$txt['cd_email_subject_daily'] = 'Your daily digest from %1$s';
$txt['cd_email_subject_weekly'] = 'Your weekly digest from %1$s';
$txt['cd_email_subject_monthly'] = 'Your monthly digest from %1$s';
$txt['cd_email_heading_daily'] = 'Your daily digest';
$txt['cd_email_heading_weekly'] = 'Your weekly digest';
$txt['cd_email_heading_monthly'] = 'Your monthly digest';
$txt['cd_email_greeting'] = 'Hi %1$s,';
$txt['cd_email_intro'] = 'Here is what you may have missed between %1$s and %2$s.';
$txt['cd_meta_line'] = '%1$s &middot; started by %2$s &middot; %3$s replies';
$txt['cd_email_view_online'] = 'Open your digest on the forum';
$txt['cd_email_footer'] = 'You are getting this email because you chose digest emails on %1$s.';
$txt['cd_email_manage'] = 'Change digest settings';
$txt['cd_email_unsubscribe'] = 'Unsubscribe from digest emails';

// Unsubscribe
$txt['cd_unsub_title'] = 'Digest emails';
$txt['cd_unsub_confirm'] = 'Stop sending you community digest emails? Your on-forum digest will still be available.';
$txt['cd_unsub_button'] = 'Unsubscribe';
$txt['cd_unsub_done'] = 'You have been unsubscribed from digest emails. You can turn them back on any time in <a href="%1$s">your digest preferences</a>.';
$txt['cd_unsub_invalid'] = 'This unsubscribe link is invalid or has expired. You can manage digest emails from your profile.';

// Alerts
$txt['alert_group_digest'] = 'Community Digest';
$txt['alert_digest_ready'] = 'When my on-forum digest is ready';
$txt['cd_alert_text'] = 'Your <a href="%1$s">community digest</a> is ready with %2$d new picks.';

// Moderation
$txt['cd_exclude_topic'] = 'Exclude from digest';
$txt['cd_include_topic'] = 'Allow in digest';
$txt['modlog_ac_cd_exclude'] = 'Excluded &quot;{topic}&quot; from the community digest';
$txt['modlog_ac_cd_include'] = 'Allowed &quot;{topic}&quot; back into the community digest';

// Scheduled task
$txt['scheduled_task_community_digest'] = 'Community Digest';
$txt['scheduled_task_desc_community_digest'] = 'Builds and sends member digests that are due. Runs hourly and works through members in batches.';

// Admin
$txt['cd_admin_title'] = 'Community Digest';
$txt['cd_admin_desc'] = 'Personalised daily, weekly or monthly roundups of new discussions, delivered by email or on the forum.';
$txt['cd_admin_settings'] = 'Settings';
$txt['cd_admin_content'] = 'Content filters';
$txt['cd_admin_content_desc'] = 'Keep boards and individual topics out of every digest.';
$txt['cd_admin_test'] = 'Test delivery';
$txt['cd_admin_test_desc'] = 'Send a digest to one member right now, or preview it in your browser.';
$txt['cd_admin_log'] = 'Delivery log';
$txt['cd_admin_log_desc'] = 'Every digest sent, skipped or failed.';

$txt['cd_enabled'] = 'Enable Community Digest';
$txt['cd_menu_button'] = 'Show a "Digest" button in the main menu';
$txt['cd_hdr_delivery'] = 'Delivery';
$txt['cd_allow_daily'] = 'Allow daily digests';
$txt['cd_allow_weekly'] = 'Allow weekly digests';
$txt['cd_allow_monthly'] = 'Allow monthly digests';
$txt['cd_default_frequency'] = 'Default frequency for new members';
$txt['cd_default_delivery'] = 'Default delivery';
$txt['cd_default_delivery_sub'] = 'Keep this as on-forum only to avoid sending unwanted emails. Members opt in to email themselves.';
$txt['cd_default_hour'] = 'Default delivery hour (0-23)';
$txt['cd_alert_on_ready'] = 'Send an alert when an on-forum digest is ready';
$txt['cd_alert_on_ready_sub'] = 'Uses SMF\'s own alerts. Members can switch it off in their alert preferences.';
$txt['cd_skip_empty'] = 'Don\'t send a digest when there is nothing to recommend';
$txt['cd_dashboard_refresh_hours'] = 'Rebuild on-forum digests after this many hours';
$txt['cd_email_accent'] = 'Email accent colour';
$txt['cd_email_accent_sub'] = 'Hex colour, for example #3e6a8c.';
$txt['cd_hdr_content'] = 'Content';
$txt['cd_max_per_section'] = 'Maximum topics per section';
$txt['cd_max_total'] = 'Maximum topics per digest';
$txt['cd_excerpt_length'] = 'Excerpt length (characters)';
$txt['cd_excerpt_length_sub'] = '0 turns excerpts off.';
$txt['cd_pool_size'] = 'Candidate topics to consider';
$txt['cd_pool_size_sub'] = 'Most recently active topics scanned per digest window. Raise for very busy forums.';
$txt['cd_unanswered_min_hours'] = 'Only show unanswered topics older than (hours)';
$txt['cd_hdr_filter'] = 'Filtering';
$txt['cd_min_starter_posts'] = 'Minimum post count of topic starter';
$txt['cd_min_starter_posts_sub'] = 'Helps keep new spam accounts out. Topics from banned or unactivated accounts are always excluded.';
$txt['cd_exclude_reported'] = 'Exclude topics with open moderation reports';
$txt['cd_exclude_locked'] = 'Exclude locked topics';
$txt['cd_skip_read'] = 'Skip topics the member has already read to the end';
$txt['cd_max_repeats'] = 'Times the same topic can be recommended';
$txt['cd_max_repeats_sub'] = 'A topic is never repeated unless it has new posts. 0 = no limit.';
$txt['cd_hdr_weights'] = 'Personalisation weights';
$txt['cd_w_board'] = 'Followed board';
$txt['cd_w_participated'] = 'Member posted in or follows the topic';
$txt['cd_w_keyword'] = 'Interest keyword match (each, max 2)';
$txt['cd_w_following'] = 'Started by someone they follow';
$txt['cd_w_replies'] = 'Activity in the period';
$txt['cd_w_views'] = 'Views';
$txt['cd_hdr_sending'] = 'Sending';
$txt['cd_batch_size'] = 'Members processed per hourly run';
$txt['cd_batch_size_sub'] = 'Members not reached are picked up next hour.';
$txt['cd_daily_email_cap'] = 'Maximum digest emails per day';
$txt['cd_daily_email_cap_sub'] = '0 = no limit. Emails also go through SMF\'s mail queue if it is enabled.';
$txt['cd_log_days'] = 'Keep log entries for (days)';
$txt['cd_hdr_integrations'] = 'Integrations';
$txt['cd_int_detected'] = 'Detected';
$txt['cd_int_missing'] = 'Not installed';
$txt['cd_int_follow_unavailable'] = 'Installed; following data not recognised';

$txt['cd_excluded_boards'] = 'Excluded boards';
$txt['cd_excluded_boards_desc'] = 'Topics in ticked boards never appear in any digest. The recycle bin is always excluded.';
$txt['cd_excluded_topics'] = 'Excluded topics';
$txt['cd_excluded_topics_desc'] = 'Moderators can exclude a topic using the "Exclude from digest" button in the topic.';
$txt['cd_no_excluded_topics'] = 'No topics are excluded.';
$txt['cd_topic'] = 'Topic';
$txt['cd_topic_missing'] = '(deleted topic)';
$txt['cd_excluded_by'] = 'Excluded by';
$txt['cd_remove_selected'] = 'Allow selected topics again';

$txt['cd_stat_subscribers'] = 'Members with a digest';
$txt['cd_stat_email'] = 'Receiving email';
$txt['cd_stat_due'] = 'Due now';
$txt['cd_stat_today'] = 'Emails sent today';
$txt['cd_stat_failed'] = 'Failures (7 days)';
$txt['cd_test_member'] = 'Member';
$txt['cd_test_member_desc'] = 'Display name, username or member ID.';
$txt['cd_test_send'] = 'Send test email now';
$txt['cd_test_no_member'] = 'No member found with that name or ID.';
$txt['cd_test_sent'] = 'Test digest with %2$d topics sent to %1$s.';
$txt['cd_test_failed'] = 'Sending failed: %1$s. Check your mail settings and the error log.';

$txt['cd_log_time'] = 'Time';
$txt['cd_log_member'] = 'Member';
$txt['cd_log_channel'] = 'Channel';
$txt['cd_log_status'] = 'Status';
$txt['cd_log_items'] = 'Topics';
$txt['cd_log_message'] = 'Details';
$txt['cd_log_failed'] = 'Failed';
$txt['cd_log_sent'] = 'Sent';
$txt['cd_log_test'] = 'Test';
$txt['cd_log_skipped'] = 'Skipped';
$txt['cd_log_empty'] = 'Nothing logged yet.';
$txt['cd_log_show_failed'] = 'Show failures only';
$txt['cd_log_show_all'] = 'Show everything';
$txt['cd_log_clear'] = 'Clear log';
$txt['cd_log_clear_confirm'] = 'Clear the whole delivery log?';
$txt['cd_channel_email'] = 'Email';
$txt['cd_channel_onsite'] = 'On-forum';
