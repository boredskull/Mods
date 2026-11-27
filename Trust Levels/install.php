<?php

/**
 * Trust Levels: database install.
 *
 * @package TrustLevels
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings, $sourcedir;

db_extend('packages');

$smcFunc['db_create_table']('{db_prefix}trust_members',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'level', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'locked', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'last_calc', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'held_warning', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member')),
		array('name' => 'idx_level', 'type' => 'index', 'columns' => array('level')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}trust_log',
	array(
		array('name' => 'id_log', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'action', 'type' => 'varchar', 'size' => 12, 'null' => false, 'default' => 'level'),
		array('name' => 'old_level', 'type' => 'tinyint', 'size' => 4, 'null' => false, 'default' => -1),
		array('name' => 'new_level', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'logged', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_changer', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'reason', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_log')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
		array('name' => 'idx_logged', 'type' => 'index', 'columns' => array('logged')),
	),
	array(),
	'ignore'
);

// Upgrading from 1.0: new columns (ignored if they already exist).
$smcFunc['db_add_column']('{db_prefix}trust_members',
	array('name' => 'held_warning', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	array(), 'ignore'
);
$smcFunc['db_add_column']('{db_prefix}trust_log',
	array('name' => 'action', 'type' => 'varchar', 'size' => 12, 'null' => false, 'default' => 'level'),
	array(), 'ignore'
);

// Level groups that Trust Levels itself gave members (1.1). Groups not
// listed here were assigned by staff and are never removed automatically.
$smcFunc['db_create_table']('{db_prefix}trust_groups',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_group', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'added', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member', 'id_group')),
	),
	array(),
	'ignore'
);

// Defaults (existing values are kept on upgrade)
if (!class_exists('TrustLevels'))
	require_once($sourcedir . '/TrustLevels.php');

$defaults = array(
	'tl_enabled' => 1,
	'tl_levels' => json_encode(TrustLevels::defaultLevels()),
	'tl_demote' => 'warnings',
	'tl_exempt_groups' => json_encode(array(1, 2)),
	'tl_allow_internal' => 1,
	'tl_apply_pm' => 1,
	'tl_badge' => 'below_avatar',
	'tl_badge_min' => 1,
	'tl_profile_public' => 0,
	'tl_promotion_pm' => 1,
	'tl_pm_from' => 1,
	'tl_probation_days' => 0,
	'tl_protect_groups' => 1,
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;

// Upgrading from 1.0: before 1.1 nobody recorded which level groups were
// added automatically. A member holding the group of their current level
// almost certainly got it from Trust Levels (1.0 removed every other level
// group on each sync), so record those. Any other level group a member
// holds was given by staff afterwards, so it's left unrecorded and protected.
if (empty($modSettings['tl_groups_migrated']))
{
	$levels = TrustLevels::levels();
	$last = 0;
	do
	{
		$rows = array();
		$seen = 0;
		$request = $smcFunc['db_query']('', '
			SELECT tm.id_member, tm.level, mem.additional_groups
			FROM {db_prefix}trust_members AS tm
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = tm.id_member)
			WHERE tm.id_member > {int:last}
			ORDER BY tm.id_member
			LIMIT 1000',
			array('last' => $last)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$seen++;
			$last = (int) $row['id_member'];
			$group = isset($levels[(int) $row['level']]) ? (int) $levels[(int) $row['level']]['group'] : 0;
			if ($group > 0 && in_array($group, array_map('intval', explode(',', (string) $row['additional_groups'])), true))
				$rows[] = array($last, $group, time());
		}
		$smcFunc['db_free_result']($request);

		if (!empty($rows))
			$smcFunc['db_insert']('ignore',
				'{db_prefix}trust_groups',
				array('id_member' => 'int', 'id_group' => 'int', 'added' => 'int'),
				$rows,
				array('id_member', 'id_group')
			);
	}
	while ($seen === 1000);

	$new['tl_groups_migrated'] = 1;
}

if (!empty($new))
	updateSettings($new);

// Daily scheduled task (around 04:00 server time)
$request = $smcFunc['db_query']('', '
	SELECT id_task
	FROM {db_prefix}scheduled_tasks
	WHERE task = {string:task}',
	array('task' => 'tl_recalc')
);
$exists = $smcFunc['db_num_rows']($request) > 0;
$smcFunc['db_free_result']($request);

if (!$exists)
	$smcFunc['db_insert']('insert',
		'{db_prefix}scheduled_tasks',
		array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string', 'callable' => 'string'),
		array(strtotime('tomorrow') + 4 * 3600, 4 * 3600, 1, 'd', 0, 'tl_recalc', 'TrustLevels::scheduledTask'),
		array('id_task')
	);

if (SMF == 'SSI')
	echo 'Trust Levels database changes are complete.';
