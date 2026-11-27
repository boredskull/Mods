<?php
/**
 * Mentions Expanded - database install
 *
 * @package MentionsExpanded
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $smcFunc, $modSettings;

db_extend('packages');

// Tracks which expanded targets have already been sent for a message, so edits don't re-notify.
$smcFunc['db_create_table']('{db_prefix}mentions_expanded_log',
	array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'not_null' => true, 'default' => 0),
		array('name' => 'target', 'type' => 'varchar', 'size' => 30, 'not_null' => true, 'default' => ''),
		array('name' => 'sent_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'not_null' => true, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_msg', 'target')),
	),
	array(),
	'ignore'
);

// Records recipients already processed by expanded mention tasks, including email-only users.
// The primary key makes retries and overlapping group mentions idempotent.
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

// Default settings - only set the ones that don't exist yet so a reinstall keeps the admin's choices.
$defaults = array(
	'mentions_expanded_enabled' => 1,
	'mentions_expanded_everyone' => 1,
	'mentions_expanded_here' => 1,
	'mentions_expanded_here_minutes' => 15,
	'mentions_expanded_groups_enabled' => 1,
	'mentions_expanded_groups' => '',
	'mentions_expanded_highlight' => 1,
	'mentions_expanded_email' => 0,
	'mentions_expanded_batch' => 500,
);

$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;

if (!empty($new))
	updateSettings($new);

if (SMF == 'SSI')
	echo 'Mentions Expanded database changes complete.';
