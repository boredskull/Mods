<?php
/** Only run when the administrator explicitly requests permanent data removal. */
if (!defined('SMF'))
	die('No direct access...');
global $smcFunc;
db_extend('packages');
$smcFunc['db_drop_table']('{db_prefix}mentions_expanded_recipients');
$smcFunc['db_drop_table']('{db_prefix}mentions_expanded_log');
