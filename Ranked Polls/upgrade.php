<?php
/** Ranked Polls upgrade (1.0.x and 1.1.0 to 1.1.1): widen ballot rankings without deleting votes,
 * and repair the scheduled task. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('No direct access...');

global $smcFunc;
db_extend('packages');

// SMF's package API safely handles both MySQL and PostgreSQL.
$smcFunc['db_change_column']('{db_prefix}rp_ballots', 'ranking', array(
	'name' => 'ranking', 'type' => 'varchar', 'size' => 2048,
	'null' => false, 'default' => '',
));


// 1.1.0 stored the callable as "file|function". SMF reads that format as
// "function|file", so it looked for a file named after the method and the
// task never ran. The class is always loaded by integrate_pre_include (also
// for cron.php), so the plain callable is reliable.
$smcFunc['db_query']('', '
    UPDATE {db_prefix}scheduled_tasks
    SET callable = {string:callable}
    WHERE task = {string:task}',
    array('callable' => 'RankedPolls::scheduledTask', 'task' => 'rp_tasks')
);
