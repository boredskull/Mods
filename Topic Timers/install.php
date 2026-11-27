<?php
if (!defined('SMF')) die('SMF only');
global $smcFunc;
$smcFunc['db_query']('', 'CREATE TABLE IF NOT EXISTS {db_prefix}btt_timers (
 id_timer int unsigned NOT NULL AUTO_INCREMENT,
 id_topic int unsigned NOT NULL,
 id_board smallint unsigned NOT NULL,
 id_member mediumint unsigned NOT NULL,
 action varchar(12) NOT NULL,
 target_board smallint unsigned NOT NULL DEFAULT 0,
 execute_at int unsigned NOT NULL,
 created_at int unsigned NOT NULL,
 status varchar(12) NOT NULL DEFAULT \'pending\',
 attempts tinyint unsigned NOT NULL DEFAULT 0,
 retry_at int unsigned NOT NULL DEFAULT 0,
 claimed_at int unsigned NOT NULL DEFAULT 0,
 completed_at int unsigned NOT NULL DEFAULT 0,
 error_message varchar(255) NOT NULL DEFAULT \'\',
 PRIMARY KEY (id_timer), KEY due (status, execute_at), KEY topic (id_topic, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', array());
// Existing v1.0.0 installations need the new retry_at column.
$column=$smcFunc['db_query']('', 'SHOW COLUMNS FROM {db_prefix}btt_timers LIKE {string:col}',array('col'=>'retry_at'));
$found=$smcFunc['db_num_rows']($column)>0;$smcFunc['db_free_result']($column);
if (!$found) $smcFunc['db_query']('', 'ALTER TABLE {db_prefix}btt_timers ADD retry_at int unsigned NOT NULL DEFAULT 0',array());
$r=$smcFunc['db_query']('', 'SELECT id_task FROM {db_prefix}scheduled_tasks WHERE task={string:name} LIMIT 1', array('name'=>'btt_process'));
$exists=$smcFunc['db_num_rows']($r)>0;$smcFunc['db_free_result']($r);
if (!$exists) $smcFunc['db_insert']('', '{db_prefix}scheduled_tasks', array('next_time'=>'int','time_offset'=>'int','time_regularity'=>'int','time_unit'=>'string-1','disabled'=>'int','task'=>'string-24','callable'=>'string-255'), array(time()+60,0,1,'m',0,'btt_process','$sourcedir/TopicTimers.php|scheduled_btt_process'), array('id_task'));
// Preserve existing admin choices on upgrades/reinstalls.
global $modSettings;
$initial=array();
foreach(array('btt_enabled'=>1,'btt_delete_enabled'=>0,'btt_recheck_permissions'=>1) as $key=>$value) if (!isset($modSettings[$key])) $initial[$key]=$value;
if ($initial) updateSettings($initial);

// Refresh SMF's global next-task cache after registering our task.
$r=$smcFunc['db_query']('', 'SELECT MIN(next_time) AS next_time FROM {db_prefix}scheduled_tasks WHERE disabled={int:enabled}',array('enabled'=>0));
$row=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
updateSettings(array('next_task_time'=>!empty($row['next_time'])?(int)$row['next_time']:time()+86400));
