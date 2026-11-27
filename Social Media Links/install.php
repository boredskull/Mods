<?php
/**
 * Social Media Links - install script.
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

// One nullable TEXT column on the members table (JSON). Nullable so that
// registration never fails on strict MySQL.
$smcFunc['db_add_column'](
	'{db_prefix}members',
	array(
		'name' => 'social_links',
		'type' => 'text',
		'null' => true,
	),
	array(),
	'ignore'
);

// Sensible default permissions: regular members can add their own links and
// everyone (including guests) can see them. Change in Admin > Permissions.
$rows = array(
	array(0, 'social_links_edit_own', 1),
	array(0, 'social_links_view', 1),
	array(-1, 'social_links_view', 1),
	array(2, 'social_links_edit_own', 1),
	array(2, 'social_links_edit_any', 1),
	array(2, 'social_links_view', 1),
);

$smcFunc['db_insert']('ignore',
	'{db_prefix}permissions',
	array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
	$rows,
	array('id_group', 'permission')
);

// Default settings (only if not already set from a previous install).
$defaults = array(
	'social_links_enabled' => 1,
	'social_links_fa_url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
	'social_links_new_tab' => 1,
	'social_links_brand_colours' => 0,
	'social_links_show_labels' => 0,
);

$set = array();
foreach ($defaults as $k => $v)
	if (!isset($modSettings[$k]))
		$set[$k] = $v;

if (!empty($set))
	updateSettings($set);

cache_put_data('sociallinks_groups', null);

if (SMF == 'SSI')
	echo 'Database changes are complete!';
