<?php
/**
 * Social Media Links - remove data (only runs if "Remove all data" is ticked).
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

$smcFunc['db_remove_column']('{db_prefix}members', 'social_links');

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission IN ({array_string:perms})',
	array(
		'perms' => array('social_links_edit_own', 'social_links_edit_any', 'social_links_view'),
	)
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array(
		'prefix' => 'social_links_%',
	)
);

cache_put_data('sociallinks_groups', null);
cache_put_data('modSettings', null);
