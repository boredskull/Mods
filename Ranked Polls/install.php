<?php

/**
 * Ranked Polls: database install.
 *
 * @package RankedPolls
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

// Which polls are ranked
$smcFunc['db_create_table']('{db_prefix}rp_polls',
	array(
		array('name' => 'id_poll', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'anonymous', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'notified', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_poll')),
	),
	array(),
	'ignore'
);

// Ballots. Anonymous ballots have id_member = 0 and voted = 0, and a random ID.
$smcFunc['db_create_table']('{db_prefix}rp_ballots',
	array(
		array('name' => 'id_ballot', 'type' => 'char', 'size' => 32, 'null' => false, 'default' => ''),
		array('name' => 'id_poll', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'ranking', 'type' => 'varchar', 'size' => 2048, 'null' => false, 'default' => ''),
		array('name' => 'voted', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_ballot')),
		array('name' => 'idx_poll', 'type' => 'index', 'columns' => array('id_poll', 'id_member')),
	),
	array(),
	'ignore'
);

// Who has voted (stops double voting, also for anonymous polls)
$smcFunc['db_create_table']('{db_prefix}rp_voters',
	array(
		array('name' => 'id_poll', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'voted', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_poll', 'id_member')),
	),
	array(),
	'ignore'
);

$defaults = array(
	'rp_enabled' => 1,
	'rp_boards' => '[]',
	'rp_anonymous' => 'optional',
	'rp_min_ranks' => 1,
	'rp_alerts' => 1,
	'rp_owner_export' => 0,
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;
if (!empty($new))
	updateSettings($new);

// Hourly scheduled task
$request = $smcFunc['db_query']('', '
	SELECT id_task
	FROM {db_prefix}scheduled_tasks
	WHERE task = {string:task}',
	array('task' => 'rp_tasks')
);
$exists = $smcFunc['db_num_rows']($request) > 0;
$smcFunc['db_free_result']($request);

// The class is always loaded by the integrate_pre_include hook (also when
// cron.php runs tasks), so a plain callable is all SMF needs.
if (!$exists)
	$smcFunc['db_insert']('insert',
		'{db_prefix}scheduled_tasks',
		array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'),
		array(time() + 3600, 0, 1, 'h', 0, 'rp_tasks', 'RankedPolls::scheduledTask'),
		array('id_task')
	);
else
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}scheduled_tasks
		SET callable = {string:callable}
		WHERE task = {string:task}',
		array('callable' => 'RankedPolls::scheduledTask', 'task' => 'rp_tasks')
	);

if (SMF == 'SSI')
	echo 'Ranked Polls database changes are complete.';
