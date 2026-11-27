<?php
/**
 * Community Insights - optional permanent data removal.
 * Only executed when the SMF package manager's remove data option is selected.
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('Cannot uninstall Community Insights without SMF.');

global $smcFunc;
db_extend('packages');
$smcFunc['db_drop_table']('{db_prefix}ci_layouts');
foreach (array('ci_layout_meta','ci_reports','ci_export_history','ci_schedules','ci_alert_rules','ci_snapshots') as $ciTable)
    $smcFunc['db_drop_table']('{db_prefix}' . $ciTable);
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}scheduled_tasks WHERE task = {string:name}', array('name' => 'community_insights'));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable IN ({array_string:settings})', array(
	'settings' => array('ci_enabled', 'ci_integrations', 'ci_recommendations', 'ci_response_hours', 'ci_cache_minutes', 'ci_default_widgets', 'ci_int_qa', 'ci_int_clubs', 'ci_int_ranked', 'ci_int_wall', 'ci_int_rules', 'ci_score_replies', 'ci_score_contributors', 'ci_score_views'),
));
// Remove any membergroup grants of the Community Insights permissions.
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permissions WHERE permission IN ({array_string:permissions})', array(
	'permissions' => array('ci_view', 'ci_core', 'ci_members', 'ci_moderation', 'ci_integrations', 'ci_export'),
));
