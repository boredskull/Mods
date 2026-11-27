<?php

/**
 * Advanced BBCode & Editor Manager: database install.
 *
 * @package BBCodeManager
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

$smcFunc['db_create_table']('{db_prefix}bbm_tags',
	array(
		array('name' => 'id_tag', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'tag', 'type' => 'varchar', 'size' => 30, 'null' => false, 'default' => ''),
		array('name' => 'title', 'type' => 'varchar', 'size' => 80, 'null' => false, 'default' => ''),
		array('name' => 'description', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'mode', 'type' => 'varchar', 'size' => 10, 'null' => false, 'default' => 'parsed'),
		array('name' => 'block_level', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'params', 'type' => 'text', 'null' => false),
		array('name' => 'template', 'type' => 'text', 'null' => false),
		array('name' => 'button', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'icon', 'type' => 'varchar', 'size' => 80, 'null' => false, 'default' => ''),
		array('name' => 'tooltip', 'type' => 'varchar', 'size' => 100, 'null' => false, 'default' => ''),
		array('name' => 'shortcut', 'type' => 'varchar', 'size' => 30, 'null' => false, 'default' => ''),
		array('name' => 'enabled', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 1),
		array('name' => 'sort_order', 'type' => 'int', 'size' => 10, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_tag')),
		array('name' => 'idx_tag', 'type' => 'unique', 'columns' => array('tag')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}bbm_profiles',
	array(
		array('name' => 'id_profile', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'name', 'type' => 'varchar', 'size' => 60, 'null' => false, 'default' => ''),
		array('name' => 'toolbar', 'type' => 'text', 'null' => false),
		array('name' => 'allowed', 'type' => 'text', 'null' => false),
		array('name' => 'is_default', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_profile')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}bbm_rules',
	array(
		array('name' => 'id_rule', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'id_profile', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'contexts', 'type' => 'varchar', 'size' => 60, 'null' => false, 'default' => ''),
		array('name' => 'boards', 'type' => 'text', 'null' => false),
		array('name' => 'id_groups', 'type' => 'text', 'null' => false),
		array('name' => 'priority', 'type' => 'int', 'size' => 10, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_rule')),
		array('name' => 'idx_profile', 'type' => 'index', 'columns' => array('id_profile')),
	),
	array(),
	'ignore'
);

$smcFunc['db_create_table']('{db_prefix}bbm_revisions',
	array(
		array('name' => 'id_revision', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
		array('name' => 'logged', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'member_name', 'type' => 'varchar', 'size' => 80, 'null' => false, 'default' => ''),
		array('name' => 'note', 'type' => 'varchar', 'size' => 255, 'null' => false, 'default' => ''),
		array('name' => 'data', 'type' => 'mediumtext', 'null' => false),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_revision')),
	),
	array(),
	'ignore'
);

$defaults = array(
	'bbm_enabled' => 1,
	'bbm_admin_bypass' => 1,
	'bbm_wysiwyg' => 1,
	'bbm_palette' => 1,
	'bbm_mobile_toolbar' => 1,
	'bbm_revisions_keep' => 50,
	'bbm_custom_css' => '',
	'bbm_tooltips' => '',
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;

if (!empty($new))
	updateSettings($new);

cache_put_data('bbm_config', null, 0);

if (SMF == 'SSI')
	echo 'Advanced BBCode & Editor Manager database changes are complete.';
