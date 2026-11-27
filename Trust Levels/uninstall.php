<?php

/**
 * Trust Levels: remove all data (only when the admin chooses to).
 * Membergroups given by levels are left as they are.
 *
 * @package TrustLevels
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

$smcFunc['db_drop_table']('{db_prefix}trust_members');
$smcFunc['db_drop_table']('{db_prefix}trust_log');
$smcFunc['db_drop_table']('{db_prefix}trust_groups');

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'tl\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}permissions
	WHERE permission = {string:perm}',
	array('perm' => 'tl_manage')
);

updateSettings(array('settings_updated' => time()));
