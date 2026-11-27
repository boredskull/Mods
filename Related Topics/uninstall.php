<?php

/**
 * Related Topics - uninstall: runs only when "Remove all data" is ticked.
 *
 * @license ISC (see LICENSE)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc;

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array(
		'prefix' => 'rtopics\_%',
	)
);

// Refresh the settings cache.
updateSettings(array('settings_updated' => time()));
cache_put_data('modSettings', null, 90);
