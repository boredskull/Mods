<?php
/** Run on package removal, whether or not the database-retention option was selected. */
if (!defined('SMF')) die('No direct access...');
global $smcFunc;
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}scheduled_tasks WHERE task = {string:name}', array('name' => 'community_insights'));
