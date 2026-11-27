<?php

/**
 * Activity Stream: database install.
 *
 * @package ActivityStream
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

$smcFunc['db_create_table']('{db_prefix}as_follows',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_followed', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member', 'id_followed')),
		array('name' => 'idx_followed', 'type' => 'index', 'columns' => array('id_followed')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}as_streams',
	array(
		array('name' => 'id_stream', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'name', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'config', 'type' => 'text', 'null' => false),
		array('name' => 'sort_order', 'type' => 'smallint', 'size' => 5, 'null' => false, 'default' => 0),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_stream')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}as_prefs',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'default_tab', 'type' => 'varchar', 'size' => 20, 'null' => false, 'default' => 'all'),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

$defaults = array(
	'as_enabled' => 1,
	'as_show_menu' => 1,
	'as_menu_label' => '',
	'as_type_topics' => 1,
	'as_type_replies' => 1,
	'as_type_likes' => 1,
	'as_type_members' => 1,
	'as_per_page' => 25,
	'as_popular_days' => 7,
	'as_max_streams' => 10,
	'as_exclude_boards' => '[]',
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;
if (!empty($new))
	updateSettings($new);

// Default permissions (first install only): everyone can view, members can follow.
if (empty($modSettings['as_permissions_set']))
{
	$groups = array(-1, 0);
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE id_group NOT IN ({array_int:skip})',
		array('skip' => array(1, 3))
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$groups[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	$rows = array();
	foreach (array_unique($groups) as $group)
	{
		$rows[] = array($group, 'as_view', 1);
		if ($group !== -1)
			$rows[] = array($group, 'as_follow', 1);
	}

	$smcFunc['db_insert']('ignore',
		'{db_prefix}permissions',
		array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
		$rows,
		array('id_group', 'permission')
	);

	updateSettings(array('as_permissions_set' => 1));
}

if (SMF == 'SSI')
	echo 'Activity Stream database changes are complete.';
