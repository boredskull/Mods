<?php

/**
 * Clubs: database install.
 *
 * @package Clubs
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

$smcFunc['db_create_table']('{db_prefix}clubs',
	array(
		array('name' => 'id_club', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'name', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'description', 'type' => 'text', 'null' => false),
		array('name' => 'type', 'type' => 'varchar', 'size' => 10, 'null' => false, 'default' => 'open'),
		array('name' => 'status', 'type' => 'varchar', 'size' => 10, 'null' => false, 'default' => 'active'),
		array('name' => 'id_owner', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_group', 'type' => 'smallint', 'size' => 5, 'null' => false, 'default' => 0),
		array('name' => 'id_board', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'icon', 'type' => 'varchar', 'size' => 80, 'null' => false, 'default' => ''),
		array('name' => 'color', 'type' => 'varchar', 'size' => 7, 'null' => false, 'default' => '#557ea0'),
		array('name' => 'member_events', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'num_members', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_profile', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_ban_group', 'type' => 'smallint', 'size' => 5, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_club')),
		array('name' => 'idx_owner', 'type' => 'index', 'columns' => array('id_owner')),
		array('name' => 'idx_status', 'type' => 'index', 'columns' => array('status', 'type')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}club_members',
	array(
		array('name' => 'id_club', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'role', 'type' => 'varchar', 'size' => 10, 'null' => false, 'default' => 'member'),
		array('name' => 'joined', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'message', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'invited_by', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_club', 'id_member')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member', 'role')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}club_boards',
	array(
		array('name' => 'id_club', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_board', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_club', 'id_board')),
		array('name' => 'idx_board', 'type' => 'unique', 'columns' => array('id_board')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}club_rsvp',
	array(
		array('name' => 'id_event', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'status', 'type' => 'varchar', 'size' => 6, 'null' => false, 'default' => 'going'),
		array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_event', 'id_member')),
	),
	array(),
	'ignore'
);

// Upgrading from 1.0 (ignored if the columns exist).
$smcFunc['db_add_column']('{db_prefix}clubs', array('name' => 'id_profile', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0), array(), 'ignore');
$smcFunc['db_add_column']('{db_prefix}clubs', array('name' => 'id_ban_group', 'type' => 'smallint', 'size' => 5, 'null' => false, 'default' => 0), array(), 'ignore');

// Moderator entries Clubs added (others are left alone).
$smcFunc['db_create_table']('{db_prefix}club_moderators',
	array(
		array('name' => 'id_board', 'type' => 'smallint', 'size' => 5, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_board', 'id_member')),
	),
	array(),
	'ignore'
);

$defaults = array(
	'clubs_enabled' => 1,
	'clubs_show_menu' => 1,
	'clubs_hide_index' => 1,
	'clubs_types' => json_encode(array('open', 'closed', 'private', 'readonly')),
	'clubs_approval' => 0,
	'clubs_max_owned' => 3,
	'clubs_max_boards' => 3,
	'clubs_guests' => 1,
	'clubs_gmods_private' => 1,
	'clubs_events' => 1,
	'clubs_alerts' => 1,
	'clubs_ban_hides' => 1,
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;
if (!empty($new))
	updateSettings($new);

// The Clubs category.
if (!class_exists('Clubs'))
	require_once($sourcedir . '/Clubs.php');
loadLanguage('Clubs');
Clubs::category();

// Upgrading from 1.0.
if (empty($modSettings['clubs_v11_migrated']))
{
	// Moderators on club boards who are club leaders were added by 1.0
	// (it removed everyone else on each sync), so record those as ours.
	$rows = array();
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT mods.id_board, mods.id_member
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}club_boards AS cb ON (cb.id_board = mods.id_board)
			INNER JOIN {db_prefix}club_members AS cm ON (cm.id_club = cb.id_club AND cm.id_member = mods.id_member)
		WHERE cm.role IN ({array_string:roles})',
		array('roles' => array('owner', 'leader'))
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$rows[] = array((int) $row[0], (int) $row[1]);
	$smcFunc['db_free_result']($request);
	if (!empty($rows))
		$smcFunc['db_insert']('ignore', '{db_prefix}club_moderators', array('id_board' => 'int', 'id_member' => 'int'), $rows, array('id_board', 'id_member'));

	// Members already banned from clubs go into the new ban groups.
	$banned = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_club, id_member
		FROM {db_prefix}club_members
		WHERE role = {string:banned}',
		array('banned' => 'banned')
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$banned[(int) $row[0]][] = (int) $row[1];
	$smcFunc['db_free_result']($request);

	require_once($sourcedir . '/Subs-Membergroups.php');
	foreach ($banned as $id_club => $members)
		if ($club = Clubs::load($id_club, true))
			addMembersToGroup($members, Clubs::banGroup($club), 'only_additional', true);

	// Every club gets its own permission profile.
	Clubs::migrateSharedProfile();

	updateSettings(array('clubs_v11_migrated' => 1));
}

// Default permissions (first install only).
if (empty($modSettings['clubs_permissions_set']))
{
	$groups = array(0);
	$request = $smcFunc['db_query']('', '
		SELECT id_group
		FROM {db_prefix}membergroups
		WHERE id_group NOT IN ({array_int:skip})',
		array('skip' => array(1, 3))
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		$groups[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	$rows = array(array(-1, 'clubs_view', 1));
	foreach (array_unique($groups) as $g)
		foreach (array('clubs_view', 'clubs_join', 'clubs_create') as $p)
			$rows[] = array($g, $p, 1);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}permissions',
		array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
		$rows,
		array('id_group', 'permission')
	);

	updateSettings(array('clubs_permissions_set' => 1));
}

// 1.1.1: board visibility lives in board_permissions_view in SMF 2.1.
// Earlier versions only updated boards.member_groups after a club was
// created (type changes, bans, guest setting), so rewrite every club's
// visibility once. Also runs on 1.0 upgrades, after the migration above.
if (empty($modSettings['clubs_view_synced']))
{
	$ids = array();
	$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs WHERE status = {string:active}', array('active' => 'active'));
	while ($row = $smcFunc['db_fetch_row']($request))
		$ids[] = (int) $row[0];
	$smcFunc['db_free_result']($request);

	foreach ($ids as $id)
		Clubs::syncAccess($id);

	updateSettings(array('clubs_view_synced' => 1));
}

// 1.1.2: repair any active club memberships whose SMF group assignment was
// not applied, and clear the cached group lists for existing club members.
// This is idempotent and avoids deleting any user groups or club content.
if (empty($modSettings['clubs_v112_members_repaired']))
{
	$needs_group = array();
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT cm.id_member, c.id_group
		FROM {db_prefix}club_members AS cm
			INNER JOIN {db_prefix}clubs AS c ON (c.id_club = cm.id_club)
			INNER JOIN {db_prefix}members AS m ON (m.id_member = cm.id_member)
		WHERE c.status = {string:active}
			AND c.id_group > {int:zero}
			AND cm.role IN ({array_string:roles})
			AND m.id_group != c.id_group
			AND FIND_IN_SET(c.id_group, m.additional_groups) = 0',
		array('active' => 'active', 'zero' => 0, 'roles' => Clubs::$in_group)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$needs_group[(int) $row['id_group']][] = (int) $row['id_member'];
	$smcFunc['db_free_result']($request);

	if (!empty($needs_group))
	{
		require_once($sourcedir . '/Subs-Membergroups.php');
		foreach ($needs_group as $group => $members)
			addMembersToGroup($members, $group, 'only_additional', true);
	}

	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT cm.id_member
		FROM {db_prefix}club_members AS cm
			INNER JOIN {db_prefix}clubs AS c ON (c.id_club = cm.id_club)
		WHERE c.status = {string:active}
			AND cm.role IN ({array_string:roles})',
		array('active' => 'active', 'roles' => Clubs::$in_group)
	);
	while ($row = $smcFunc['db_fetch_row']($request))
		Clubs::refreshMemberCache((int) $row[0]);
	$smcFunc['db_free_result']($request);

	updateSettings(array('clubs_v112_members_repaired' => 1));
}

// 1.1.3: SMF's board creation used to abort for non-admin members after
// inserting the club record, leaving active clubs with no main board.
// Repair those rows on upgrade, without deleting or guessing at orphan boards.
$request = $smcFunc['db_query']('', '
	SELECT id_club
	FROM {db_prefix}clubs
	WHERE status = {string:active}
		AND id_board = {int:zero}',
	array('active' => 'active', 'zero' => 0)
);
$missing_boards = array();
while ($row = $smcFunc['db_fetch_row']($request))
	$missing_boards[] = (int) $row[0];
$smcFunc['db_free_result']($request);
foreach ($missing_boards as $club_id)
	Clubs::activate($club_id);

if (SMF == 'SSI')
	echo 'Clubs database changes are complete.';
