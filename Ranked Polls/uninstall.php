<?php

/**
 * Ranked Polls: remove all data (only when the admin chooses to).
 * The polls themselves stay as normal SMF polls, with first-choice counts.
 *
 * @package RankedPolls
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

foreach (array('rp_polls', 'rp_ballots', 'rp_voters') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'rp\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}board_permissions
	WHERE permission = {string:perm}',
	array('perm' => 'rankedpoll_create')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts
	WHERE content_action = {string:action}',
	array('action' => 'ranked_results')
);

updateSettings(array('settings_updated' => time()));
