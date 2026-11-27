<?php

/**
 * Event Manager: remove the mod's own data (only when the admin chooses to).
 *
 * Calendar events are SMF's own and are NOT deleted. Event titles marked
 * "Cancelled:" by this mod keep that prefix.
 *
 * @package EventManager
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

foreach (array('em_events', 'em_registrations', 'em_reminders', 'em_feeds') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'em\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission IN ({array_string:perms})',
	array('perms' => array('em_register', 'em_manage_any'))
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts
	WHERE content_type = {string:type}
		AND content_action LIKE {string:prefix}',
	array('type' => 'member', 'prefix' => 'em\_%')
);

updateSettings(array('settings_updated' => time()));
