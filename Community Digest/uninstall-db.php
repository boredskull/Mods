<?php
/**
 * Community Digest - optional "remove all data" on uninstall.
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

foreach (array('digest_prefs', 'digest_items', 'digest_log', 'digest_excluded', 'digest_email_queue') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'cd\_%')
);

$smcFunc['db_query']('', 'DELETE FROM {db_prefix}scheduled_tasks WHERE task = {string:task}', array('task' => 'community_digest'));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}user_alerts WHERE content_type = {string:type}', array('type' => 'digest'));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}user_alerts_prefs WHERE alert_pref = {string:pref}', array('pref' => 'digest_ready'));

foreach (array_keys($modSettings) as $k)
	if (strpos($k, 'cd_') === 0)
		unset($modSettings[$k]);

updateSettings(array('settings_updated' => time()));
