<?php
/**
 * YouTube Video Card - database install script (SMF 2.1)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as SMF\'s index.php and SSI.php files.');

global $smcFunc, $modSettings;

db_extend('packages');

$columns = array(
	array('name' => 'id_video', 'type' => 'varchar', 'size' => 16, 'default' => '', 'null' => false),
	array('name' => 'fetched', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0, 'null' => false),
	array('name' => 'is_error', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'default' => 0, 'null' => false),
	array('name' => 'data', 'type' => 'mediumtext', 'null' => false),
);

$indexes = array(
	array('type' => 'primary', 'columns' => array('id_video')),
	array('type' => 'index', 'columns' => array('fetched')),
);

$smcFunc['db_create_table']('{db_prefix}ytcard_cache', $columns, $indexes, array(), 'ignore');

// Default settings, only where nothing is set yet.
$defaults = array(
	'ytcard_enabled' => 1,
	'ytcard_show_description' => 1,
	'ytcard_show_comments' => 1,
	'ytcard_show_avatars' => 1,
	'ytcard_comment_count' => 20,
	'ytcard_comment_order' => 'relevance',
	'ytcard_cache_hours' => 6,
	'ytcard_secret' => bin2hex(function_exists('random_bytes') ? random_bytes(16) : md5(uniqid(mt_rand(), true), true)),
);

$updates = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$updates[$key] = $value;

if (!empty($updates))
	updateSettings($updates);

if (SMF == 'SSI')
	echo 'Database changes are complete!';
