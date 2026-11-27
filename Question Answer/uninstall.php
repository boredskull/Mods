<?php

/**
 * Questions & Answers: remove the mod's own data (only when the admin chooses to).
 *
 * Topics and posts are SMF's own and are kept. Topics locked because they
 * were solved stay locked.
 *
 * @package QuestionsAnswers
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

foreach (array('qa_topics', 'qa_votes', 'qa_scores', 'qa_members', 'qa_adjustments') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'qa\_%')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}board_permissions
	WHERE permission IN ({array_string:perms})',
	array('perms' => array('qa_vote', 'qa_accept_own', 'qa_accept_any'))
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts
	WHERE content_type = {string:type}
		AND content_action LIKE {string:prefix}',
	array('type' => 'member', 'prefix' => 'qa\_%')
);

updateSettings(array('settings_updated' => time()));
