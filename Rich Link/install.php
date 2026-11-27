<?php

/**
 * Rich Link Previews: database install.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

// Metadata for each external URL, shared by every post that links to it.
$smcFunc['db_create_table']('{db_prefix}rich_links_cache',
	array(
		array('name' => 'url_hash', 'type' => 'char', 'size' => 40, 'null' => false, 'default' => ''),
		array('name' => 'url', 'type' => 'text', 'null' => false),
		array('name' => 'title', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'description', 'type' => 'text', 'null' => false),
		array('name' => 'image', 'type' => 'text', 'null' => false),
		array('name' => 'site_name', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'status', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'fetched', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('url_hash')),
		array('name' => 'idx_fetched', 'type' => 'index', 'columns' => array('fetched')),
	),
	array(),
	'ignore'
);

// The previews attached to each post, in order.
$smcFunc['db_create_table']('{db_prefix}rich_links_msg',
	array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'position', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'link_type', 'type' => 'varchar', 'size' => 8, 'null' => false, 'default' => 'web'),
		array('name' => 'ref_id', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'url_hash', 'type' => 'char', 'size' => 40, 'null' => false, 'default' => ''),
		array('name' => 'url', 'type' => 'text', 'null' => false),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_msg', 'position')),
		array('name' => 'idx_url_hash', 'type' => 'index', 'columns' => array('url_hash')),
	),
	array(),
	'ignore'
);

// Default settings (existing values are kept on upgrade).
$defaults = array(
	'richlinks_enabled' => 1,
	'richlinks_background' => 1,
	'richlinks_max' => 3,
	'richlinks_internal' => 1,
	'richlinks_images' => 1,
	'richlinks_exclude' => '',
	'richlinks_timeout' => 5,
	'richlinks_max_kb' => 512,
	'richlinks_cache_days' => 30,
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;

if (!empty($new))
	updateSettings($new);

if (SMF == 'SSI')
	echo 'Rich Link Previews database changes are complete.';
