<?php
/**
 * Event Manager 1.2.1 data-preserving upgrade (from 1.0.0 to 1.2.0).
 * @package EventManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('No direct access...');

global $smcFunc;

// 1.2.0 stored the callable as "file|function", which SMF reads as
// "function|file", so the reminder task never ran. The class is always
// loaded by integrate_pre_include (also for cron.php): use the plain callable.
$smcFunc['db_query']('', '
	UPDATE {db_prefix}scheduled_tasks
	SET callable = {string:callable}
	WHERE task = {string:task}',
	array('callable' => 'EM::scheduledTask', 'task' => 'em_reminders')
);
