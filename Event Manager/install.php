<?php

/**
 * Event Manager: database install.
 *
 * @package EventManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

// Registration settings for a calendar event. Events without a row use the admin defaults.
$smcFunc['db_create_table']('{db_prefix}em_events',
	array(
		array('name' => 'id_event', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'enabled', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'capacity', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'waitlist', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'waitlist_max', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'approval', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'allow_maybe', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'max_guests', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'question', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'question_required', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'visibility', 'type' => 'varchar', 'size' => 12, 'null' => false, 'default' => 'members'),
		array('name' => 'opens', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'closes', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'cancel_hours', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'reminders', 'type' => 'varchar', 'size' => 60, 'null' => false, 'default' => ''),
		array('name' => 'cancelled', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'original_title', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_event')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}em_registrations',
	array(
		array('name' => 'id_event', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'status', 'type' => 'varchar', 'size' => 10, 'null' => false, 'default' => 'going'),
		array('name' => 'guests', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'note', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'registered', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'checked_in', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_event', 'id_member')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member', 'status')),
		array('name' => 'idx_queue', 'type' => 'index', 'columns' => array('id_event', 'status', 'registered')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}em_reminders',
	array(
		array('name' => 'id_event', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'offset_min', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'sent', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_event', 'id_member', 'offset_min')),
		array('name' => 'idx_sent', 'type' => 'index', 'columns' => array('sent')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}em_feeds',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'token', 'type' => 'varchar', 'size' => 32, 'null' => false, 'default' => ''),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

// Settings (only those not already set, so reinstalling keeps choices).
$defaults = array(
	'em_enabled' => 1,
	'em_show_menu' => 1,
	'em_topic_panel' => 1,
	'em_feeds' => 1,
	'em_default_enabled' => 1,
	'em_default_capacity' => 0,
	'em_default_waitlist' => 1,
	'em_default_maybe' => 1,
	'em_default_visibility' => 'members',
	'em_default_reminders' => '1440,60',
	'em_alerts' => 1,
	'em_reminder_email' => 1,
	'em_reminder_maybe' => 0,
);
$new = array();
foreach ($defaults as $k => $v)
	if (!isset($modSettings[$k]))
		$new[$k] = $v;

// Everyone who can post (regular members, group 0) may sign up, once.
if (empty($modSettings['em_permissions_set']))
{
	$smcFunc['db_insert']('ignore',
		'{db_prefix}permissions',
		array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
		array(array(0, 'em_register', 1)),
		array('id_group', 'permission')
	);

	// And every post-count group that can already see the calendar.
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:perm}
			AND add_deny = {int:yes}
			AND id_group > {int:guests}',
		array('perm' => 'calendar_view', 'yes' => 1, 'guests' => -1)
	);
	$rows = array();
	while ($row = $smcFunc['db_fetch_row']($request))
		$rows[] = array((int) $row[0], 'em_register', 1);
	$smcFunc['db_free_result']($request);

	if (!empty($rows))
		$smcFunc['db_insert']('ignore',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$rows,
			array('id_group', 'permission')
		);

	$new['em_permissions_set'] = 1;
}

if (!empty($new))
	updateSettings($new);

// Reminder task, every 15 minutes.
$request = $smcFunc['db_query']('', '
	SELECT id_task
	FROM {db_prefix}scheduled_tasks
	WHERE task = {string:task}',
	array('task' => 'em_reminders')
);
$exists = $smcFunc['db_num_rows']($request) > 0;
$smcFunc['db_free_result']($request);

if (!$exists)
	$smcFunc['db_insert']('insert',
		'{db_prefix}scheduled_tasks',
		array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'),
		array(time() + 300, 0, 15, 'm', 0, 'em_reminders', 'EM::scheduledTask'),
		array('id_task')
	);

// Repair the callable for existing installations as well. The class is
// always loaded by the integrate_pre_include hook (also when cron.php runs
// tasks), so the plain callable is all SMF needs. 1.2.0 stored it as
// "file|function", which SMF reads the other way round, so it never ran.
$smcFunc['db_query']('', '
	UPDATE {db_prefix}scheduled_tasks
	SET callable = {string:callable}
	WHERE task = {string:task}',
	array('callable' => 'EM::scheduledTask', 'task' => 'em_reminders')
);

if (SMF == 'SSI')
	echo 'Event Manager database changes are complete.';
