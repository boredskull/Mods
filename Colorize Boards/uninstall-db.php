<?php
/**
 * Colorize Boards - uninstall: only runs when "Remove all data associated
 * with this modification" is ticked. Deletes the saved colours and setting.
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc;

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:vars})',
	array(
		'vars' => array('colorize_boards_enable', 'colorize_boards_data'),
	)
);

// Make SMF reload settings from the database.
updateSettings(array('settings_updated' => time()));
