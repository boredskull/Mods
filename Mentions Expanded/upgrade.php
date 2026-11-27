<?php
/** Mentions Expanded v1.1.1 non-destructive schema upgrade. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('No direct access...');

// Preserve existing settings and the v1.0.1 target log.
global $smcFunc;
db_extend('packages');
$smcFunc['db_create_table']('{db_prefix}mentions_expanded_recipients',
	array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'not_null' => true, 'default' => 0),
		array('name' => 'id_member', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'not_null' => true, 'default' => 0),
		array('name' => 'processed_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'not_null' => true, 'default' => 0),
	),
	array(array('type' => 'primary', 'columns' => array('id_msg', 'id_member'))),
	array(),
	'ignore'
);
