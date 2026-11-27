<?php

/**
 * Advanced BBCode & Editor Manager: remove all data (only when the admin chooses to).
 *
 * @package BBCodeManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $settings;

db_extend('packages');

foreach (array('bbm_tags', 'bbm_profiles', 'bbm_rules', 'bbm_revisions') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'bbm\_%')
);

// Uploaded icons
$dir = $settings['default_theme_dir'] . '/images/bbm';
if (is_dir($dir))
{
	foreach (glob($dir . '/*') ?: array() as $file)
		if (is_file($file))
			@unlink($file);
	@rmdir($dir);
}

cache_put_data('bbm_config', null, 0);
updateSettings(array('settings_updated' => time()));
