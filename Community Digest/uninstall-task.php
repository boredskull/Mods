<?php
/**
 * Community Digest - always runs on uninstall: disables the scheduled task.
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

global $smcFunc;

// Disable rather than delete, so a reinstall keeps the admin's chosen schedule.
$smcFunc['db_query']('', '
	UPDATE {db_prefix}scheduled_tasks
	SET disabled = {int:disabled}
	WHERE task = {string:task}',
	array('disabled' => 1, 'task' => 'community_digest')
);
