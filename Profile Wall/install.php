<?php
/**
 * Profile Wall - database installer for SMF 2.1.x
 * Creates tables, default settings and sensible default permissions.
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

// Comments (top-level comments and one level of replies).
$smcFunc['db_create_table']('{db_prefix}pw_comments',
	array(
		array('name' => 'id_comment', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_profile', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_poster', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'poster_name', 'type' => 'varchar', 'size' => 80, 'default' => ''),
		array('name' => 'id_parent', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'body', 'type' => 'text'),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'modified', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'modified_name', 'type' => 'varchar', 'size' => 80, 'default' => ''),
		array('name' => 'approved', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 1),
		array('name' => 'pinned', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'likes', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'poster_ip', 'type' => 'inet', 'size' => 16, 'null' => true),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_comment')),
		array('name' => 'idx_wall', 'type' => 'index', 'columns' => array('id_profile', 'id_parent', 'approved')),
		array('name' => 'idx_poster', 'type' => 'index', 'columns' => array('id_poster')),
		array('name' => 'idx_parent', 'type' => 'index', 'columns' => array('id_parent')),
	),
	array(),
	'ignore'
);

// Likes on comments.
$smcFunc['db_create_table']('{db_prefix}pw_likes',
	array(
		array('name' => 'id_comment', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'like_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_comment', 'id_member')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

// Reports to moderators.
$smcFunc['db_create_table']('{db_prefix}pw_reports',
	array(
		array('name' => 'id_report', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_comment', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'reason', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		array('name' => 'time_reported', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		array('name' => 'closed', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_report')),
		array('name' => 'idx_comment', 'type' => 'index', 'columns' => array('id_comment')),
		array('name' => 'idx_closed', 'type' => 'index', 'columns' => array('closed')),
	),
	array(),
	'ignore'
);

// Per-member wall settings.
$smcFunc['db_create_table']('{db_prefix}pw_walls',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
		array('name' => 'privacy', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
		array('name' => 'owner_approval', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

// Default settings (only those not already set, so upgrades keep admin choices).
$defaults = array(
	'pw_enabled' => 1,
	'pw_per_page' => 10,
	'pw_max_length' => 2000,
	'pw_flood_seconds' => 30,
	'pw_edit_window' => 0,
	'pw_replies' => 1,
	'pw_likes' => 1,
	'pw_reports' => 1,
	'pw_owner_delete' => 1,
	'pw_owner_privacy' => 1,
	'pw_summary_count' => 3,
	'pw_pm_notify' => 0,
);
$new_settings = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new_settings[$key] = $value;
if (!empty($new_settings))
	updateSettings($new_settings);

// Default permissions - only on a fresh install.
$request = $smcFunc['db_query']('', '
	SELECT COUNT(*)
	FROM {db_prefix}permissions
	WHERE permission LIKE {string:perm}',
	array(
		'perm' => 'profile_wall_%',
	)
);
list ($existing) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

if (empty($existing))
{
	$rows = array(
		// Guests can read.
		array(-1, 'profile_wall_view', 1),
		// Regular members.
		array(0, 'profile_wall_view', 1),
		array(0, 'profile_wall_post', 1),
		array(0, 'profile_wall_autoapprove', 1),
		array(0, 'profile_wall_edit_own', 1),
		array(0, 'profile_wall_delete_own', 1),
		// Global moderators.
		array(2, 'profile_wall_view', 1),
		array(2, 'profile_wall_post', 1),
		array(2, 'profile_wall_autoapprove', 1),
		array(2, 'profile_wall_edit_any', 1),
		array(2, 'profile_wall_delete_any', 1),
		array(2, 'profile_wall_moderate', 1),
	);

	$smcFunc['db_insert']('ignore',
		'{db_prefix}permissions',
		array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
		$rows,
		array('id_group', 'permission')
	);
}

if (SMF == 'SSI')
	echo 'Profile Wall database changes complete.';
