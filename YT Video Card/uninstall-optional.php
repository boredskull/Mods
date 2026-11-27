<?php
/**
 * YouTube Video Card - optional data removal on uninstall (SMF 2.1)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this file in the same place as SMF\'s index.php and SSI.php files.');

global $smcFunc;

db_extend('packages');

$smcFunc['db_drop_table']('{db_prefix}ytcard_cache');

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable IN ({array_string:variables})',
	array(
		'variables' => array(
			'ytcard_enabled',
			'ytcard_api_key',
			'ytcard_show_description',
			'ytcard_show_comments',
			'ytcard_show_avatars',
			'ytcard_comment_count',
			'ytcard_comment_order',
			'ytcard_cache_hours',
			'ytcard_secret',
		),
	)
);

// Make sure the settings cache doesn't hand the removed values back.
cache_put_data('modSettings', null, 90);

if (SMF == 'SSI')
	echo 'Database changes are complete!';
