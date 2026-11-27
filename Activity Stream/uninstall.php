<?php

/**
 * Activity Stream: remove all data (only when the admin chooses to).
 *
 * @package ActivityStream
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

foreach (array('as_follows', 'as_streams', 'as_prefs') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'as\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission IN ({array_string:perms})',
	array('perms' => array('as_view', 'as_follow'))
);

updateSettings(array('settings_updated' => time()));
