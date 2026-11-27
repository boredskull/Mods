<?php

/**
 * Clubs: remove the mod's own data (only when the admin chooses to).
 *
 * Club boards, their topics and the club membergroups are NOT deleted:
 * they stay as normal SMF boards and groups, so no posts are lost. Delete
 * them in Admin > Boards and Admin > Membergroups if you no longer want them.
 *
 * @package Clubs
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc;

db_extend('packages');

foreach (array('clubs', 'club_members', 'club_boards', 'club_rsvp', 'club_moderators') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'clubs\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission IN ({array_string:perms})',
	array('perms' => array('clubs_view', 'clubs_join', 'clubs_create', 'clubs_manage_all'))
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts
	WHERE content_action LIKE {string:prefix}',
	array('prefix' => 'club\_%')
);

updateSettings(array('settings_updated' => time()));
