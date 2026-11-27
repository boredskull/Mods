<?php
/**
 * Community Digest - database install.
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc;

db_extend('packages');

$tables = array(
	'digest_prefs' => array(
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'frequency', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 2),
			array('name' => 'delivery', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
			array('name' => 'sections', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'boards', 'type' => 'text'),
			array('name' => 'keywords', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'weekly_day', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
			array('name' => 'send_hour', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 8),
			array('name' => 'last_sent', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'next_send', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'token', 'type' => 'varchar', 'size' => 32, 'default' => ''),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member')),
			array('name' => 'idx_next_send', 'type' => 'index', 'columns' => array('frequency', 'next_send')),
		),
	),
	'digest_items' => array(
		'columns' => array(
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'id_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'section', 'type' => 'varchar', 'size' => 20, 'default' => ''),
			array('name' => 'reason', 'type' => 'varchar', 'size' => 120, 'default' => ''),
			array('name' => 'badge', 'type' => 'varchar', 'size' => 30, 'default' => ''),
			array('name' => 'score', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'batch', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'times_shown', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			array('name' => 'last_msg_shown', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'is_read', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member', 'id_topic')),
			array('name' => 'idx_member_batch', 'type' => 'index', 'columns' => array('id_member', 'batch')),
			array('name' => 'idx_batch', 'type' => 'index', 'columns' => array('batch')),
			array('name' => 'idx_topic', 'type' => 'index', 'columns' => array('id_topic')),
		),
	),
	'digest_log' => array(
		'columns' => array(
			array('name' => 'id_log', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'channel', 'type' => 'varchar', 'size' => 10, 'default' => ''),
			array('name' => 'status', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
			array('name' => 'items', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'default' => 0),
			array('name' => 'message', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_log')),
			array('name' => 'idx_log_time', 'type' => 'index', 'columns' => array('log_time')),
			array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
		),
	),
	'digest_email_queue' => array(
		'columns' => array(
			array('name' => 'id_queue', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'since_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'schedule_slot', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'until_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'next_attempt', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'locked_until', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'attempts', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_queue')),
			array('name' => 'idx_due', 'type' => 'index', 'columns' => array('next_attempt', 'locked_until')),
			array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
			array('name' => 'uniq_member_slot', 'type' => 'unique', 'columns' => array('id_member', 'schedule_slot')),
		),
	),
	'digest_excluded' => array(
		'columns' => array(
			array('name' => 'id_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
			array('name' => 'excluded_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_topic')),
		),
	),
);

foreach ($tables as $name => $t)
	$smcFunc['db_create_table']('{db_prefix}' . $name, $t['columns'], $t['indexes'], array(), 'ignore');

// Default settings: 'ignore' keeps any values from a previous install.
$defaults = array(
	'cd_enabled' => 1, 'cd_menu_button' => 1,
	'cd_allow_daily' => 1, 'cd_allow_weekly' => 1, 'cd_allow_monthly' => 1,
	'cd_default_frequency' => 2, 'cd_default_delivery' => 0, 'cd_default_hour' => 8,
	'cd_alert_on_ready' => 1, 'cd_skip_empty' => 1, 'cd_dashboard_refresh_hours' => 12, 'cd_email_accent' => '#3e6a8c',
	'cd_max_per_section' => 5, 'cd_max_total' => 25, 'cd_excerpt_length' => 160, 'cd_pool_size' => 400, 'cd_unanswered_min_hours' => 6,
	'cd_min_starter_posts' => 0, 'cd_exclude_reported' => 1, 'cd_exclude_locked' => 0, 'cd_skip_read' => 1, 'cd_max_repeats' => 2,
	'cd_w_board' => 30, 'cd_w_participated' => 25, 'cd_w_keyword' => 20, 'cd_w_following' => 20, 'cd_w_replies' => 10, 'cd_w_views' => 4,
	'cd_batch_size' => 50, 'cd_daily_email_cap' => 500, 'cd_log_days' => 30, 'cd_excluded_boards' => '',
	'cd_int_activity' => 1, 'cd_int_qa' => 1, 'cd_int_clubs' => 1, 'cd_int_rankedpolls' => 1, 'cd_int_profilewall' => 1,
);
foreach (array('recommended', 'following', 'questions', 'unanswered', 'clubs', 'polls', 'popular', 'events', 'newmembers', 'wall', 'stats') as $s)
	$defaults['cd_section_' . $s] = 1;

$rows = array();
foreach ($defaults as $k => $v)
	$rows[] = array($k, (string) $v);

$smcFunc['db_insert']('ignore',
	'{db_prefix}settings',
	array('variable' => 'string-255', 'value' => 'string-65534'),
	$rows,
	array('variable')
);

// SMF caches $modSettings; clear it so the new defaults are used straight away.
cache_put_data('modSettings', null, 90);

// Hourly scheduled task. Keep the source-qualified callable within SMF 2.1's
// scheduled_tasks.callable column length (the old 74-character value overflowed).
$request = $smcFunc['db_query']('', '
	SELECT id_task FROM {db_prefix}scheduled_tasks WHERE task = {string:task}',
	array('task' => 'community_digest')
);
$exists = $smcFunc['db_num_rows']($request) > 0;
$smcFunc['db_free_result']($request);

if (!$exists)
	$smcFunc['db_insert']('insert',
		'{db_prefix}scheduled_tasks',
		array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'),
		array(time() + 300, 0, 1, 'h', 0, 'community_digest', '$sourcedir/CommunityDigest-Engine.php|cd_scheduled_task'),
		array('id_task')
	);
else
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}scheduled_tasks
		SET disabled = {int:enabled}, callable = {string:callable}
		WHERE task = {string:task}',
		array('enabled' => 0, 'callable' => '$sourcedir/CommunityDigest-Engine.php|cd_scheduled_task', 'task' => 'community_digest')
	);

// Default alert preference: "digest ready" alerts on (members can turn them off).
$smcFunc['db_insert']('ignore',
	'{db_prefix}user_alerts_prefs',
	array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'),
	array(0, 'digest_ready', 1),
	array('id_member', 'alert_pref')
);

if (SMF == 'SSI')
	echo 'Community Digest database changes complete.';
