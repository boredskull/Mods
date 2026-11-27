<?php
if (!defined('SMF')) die('SMF only');
global $smcFunc;
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}scheduled_tasks WHERE task={string:task}',array('task'=>'btt_process'));
// Retain the timer history and pending records for recovery; do not delete user data.
// Retain admin configuration for a later reinstall. Package hook removal disables all UI and task callbacks.
