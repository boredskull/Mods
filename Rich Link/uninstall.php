<?php

/**
 * Rich Link Previews: remove all data (only when the admin chooses to).
 *
 * @package RichLinkPreviews
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

$smcFunc['db_drop_table']('{db_prefix}rich_links_cache');
$smcFunc['db_drop_table']('{db_prefix}rich_links_msg');

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'richlinks\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}background_tasks
	WHERE task_class = {string:class}',
	array('class' => 'RichLinks_Fetch_Background')
);

updateSettings(array('settings_updated' => time()));
