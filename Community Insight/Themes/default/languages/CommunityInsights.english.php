<?php
/**
 * Community Insights for SMF 2.1.x - English language strings.
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */
if (!defined('SMF'))
	die('No direct access...');

$txt['ci_title'] = 'Community Insights';
$txt['ci_intro'] = 'Understand participation, response rates, and community trends. All reports respect your assigned permissions.';
$txt['ci_settings'] = 'Community Insights Settings';
$txt['ci_period'] = 'Reporting period';
$txt['ci_days'] = 'days';
$txt['ci_from'] = 'From';
$txt['ci_to'] = 'To';
$txt['ci_apply'] = 'Apply';
$txt['ci_save_layout'] = 'Save my dashboard';
$txt['ci_customize'] = 'Customise my dashboard';
$txt['ci_export'] = 'Download reports';
$txt['ci_export_widget'] = 'Report';
$txt['ci_download'] = 'Download CSV';
$txt['ci_visible_scope'] = 'These totals cover the boards you are permitted to see, excluding the recycle board.';
$txt['ci_posts'] = 'Posts';
$txt['ci_topics'] = 'Topics';
$txt['ci_messages'] = 'Messages (estimated)';
$txt['ci_views'] = 'Topic views';
$txt['ci_contributors'] = 'Unique contributors';
$txt['ci_previous'] = 'Previous period';
$txt['ci_eligible'] = 'Eligible topics';
$txt['ci_responded'] = 'Responded within threshold';
$txt['ci_response_rate'] = 'Response rate (%)';
$txt['ci_response_threshold'] = 'Only topics old enough for the %d-hour response window are included.';
$txt['ci_unanswered'] = 'Unanswered discussions needing attention';
$txt['ci_board'] = 'Board';
$txt['ci_registered'] = 'New activated registrations';
$txt['ci_matured'] = '30-day matured cohort';
$txt['ci_first7'] = 'Made a post in first 7 days';
$txt['ci_returned30'] = 'Contributed on days 8–30';
$txt['ci_cohort_note'] = 'Retention measurements exclude newer registrations that have not yet had a full 30 days. Counts are independent stages, not necessarily a strict funnel.';
$txt['ci_pending'] = 'Pending post approvals';
$txt['ci_reports'] = 'Open reported posts';
$txt['ci_no_advice'] = 'No rule-based recommendations for this period.';
$txt['ci_advice_concentration'] = 'Posting volume increased, but fewer unique members contributed. Consider encouraging broader participation.';
$txt['ci_advice_unanswered'] = 'Fewer than half of eligible topics received a timely reply. Review unanswered discussions.';
$txt['ci_advice_retention'] = 'Fewer than one in five matured new members contributed on days 8–30. Review your new-member welcome experience.';
$txt['ci_no_integrations'] = 'No compatible, enabled optional integrations are available to this account. Core Community Insights remains fully functional.';
$txt['ci_widget_overview'] = 'Community overview';
$txt['ci_widget_smf'] = 'SMF forum totals';
$txt['ci_widget_activity'] = 'Activity trends';
$txt['ci_widget_popular'] = 'Most viewed recent topics';
$txt['ci_activity_chart'] = 'Post activity in the selected period';
$txt['ci_replies'] = 'Replies';
$txt['ci_widget_discussion'] = 'Discussion health';
$txt['ci_widget_boards'] = 'Board performance';
$txt['ci_widget_members'] = 'Member engagement';
$txt['ci_widget_moderation'] = 'Moderation workload';
$txt['ci_widget_recommendations'] = 'Suggested actions';
$txt['ci_widget_integrations'] = 'Optional integrations';
$txt['ci_enabled_label'] = 'Enable Community Insights';
$txt['ci_integrations_label'] = 'Enable optional modification integrations';
$txt['ci_recommendations_label'] = 'Enable rule-based recommendations';
$txt['ci_response_hours'] = 'Response time threshold (hours, 1–336)';
$txt['ci_default_layout'] = 'Default dashboard widgets';
$txt['ci_save_settings'] = 'Save settings';
$txt['ci_permissions_hint'] = 'Delegate access using SMF membergroup permissions. Grant View dashboard plus the specific reports required. Only administrators can change these settings.';
$txt['ci_permissions_link'] = 'Open permissions';
$txt['ci_disabled'] = 'Community Insights has been disabled by an administrator.';
$txt['ci_bad_request'] = 'Invalid Community Insights request.';
$txt['ci_no_export'] = 'You are not allowed to export this report.';
$txt['ci_provider_qa'] = 'Questions & Answers';
$txt['ci_provider_clubs'] = 'Clubs';
$txt['ci_provider_ranked'] = 'Ranked Polls';
$txt['ci_provider_wall'] = 'Profile Wall';
$txt['ci_provider_rules'] = 'Board Rules';
$txt['ci_metric_questions'] = 'Questions asked in period';
$txt['ci_metric_solved'] = 'Of which solved';
$txt['ci_metric_clubs'] = 'Active clubs';
$txt['ci_metric_memberships'] = 'Club memberships';
$txt['ci_metric_polls'] = 'Ranked polls';
$txt['ci_metric_ballots'] = 'Ballots';
$txt['ci_metric_comments'] = 'Approved wall comments';
$txt['ci_metric_accepted'] = 'Rule acceptances';
$txt['ci_credits'] = 'Community Insights %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
$txt['ci_by'] = 'by';
$txt['ci_whoall'] = 'Viewing <a href="' . (isset($GLOBALS['scripturl']) ? $GLOBALS['scripturl'] : '') . '?action=communityinsights">Community Insights</a>.';
$txt['ci_cache_minutes'] = 'Cache report results for (minutes, 0 = off)';
$txt['permissiongroup_community_insights'] = 'Community Insights';
$txt['permissiongroup_simple_community_insights'] = 'Community Insights';
$txt['permissionname_ci_view'] = 'View Community Insights dashboard';
$txt['permissionname_ci_core'] = 'View community and board analytics';
$txt['permissionname_ci_members'] = 'View member engagement and retention';
$txt['permissionname_ci_moderation'] = 'View moderation workload';
$txt['permissionname_ci_integrations'] = 'View compatible modification analytics';
$txt['permissionname_ci_export'] = 'Export Community Insights reports';
$txt['permissionhelp_ci_view'] = 'Allows access to the Community Insights page; additional permissions are needed for each report section.';
$txt['permissionhelp_ci_core'] = 'Allows reports based on discussions and board activity that this member is entitled to see.';
$txt['permissionhelp_ci_members'] = 'Allows viewing aggregate new-member engagement and retention statistics.';
$txt['permissionhelp_ci_moderation'] = 'Allows viewing counts of outstanding moderation items, scoped to viewable boards.';
$txt['permissionhelp_ci_integrations'] = 'Allows viewing additional analytics from compatible, installed and enabled modifications. Private-data providers are administrator-only.';
$txt['permissionhelp_ci_export'] = 'Allows downloading CSV exports for reports already visible to this member.';

$txt['ci_advanced_reports'] = 'Detailed reports';
$txt['ci_saved_reports'] = 'Saved reports';
$txt['ci_export_history'] = 'Export history';
$txt['ci_automations'] = 'Automated reports';
$txt['ci_alerts'] = 'Custom alerts';
$txt['ci_access_overview'] = 'Permissions overview';
$txt['ci_report_builder'] = 'Report builder';
$txt['ci_save_report'] = 'Save report';
$txt['ci_run_report'] = 'Run report';
$txt['ci_generate_report'] = 'Generate';
$txt['ci_integrations_individual'] = 'Individual companion integrations';
$txt['ci_layout_preset'] = 'Dashboard preset';
$txt['ci_layout_width'] = 'Widget width';
