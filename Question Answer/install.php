<?php

/**
 * Questions & Answers: database install.
 *
 * @package QuestionsAnswers
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

// Topics that are questions, and their solved state.
$smcFunc['db_create_table']('{db_prefix}qa_topics',
	array(
		array('name' => 'id_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'solved', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_answer', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'solved_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'solved_by', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'locked_by_qa', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_topic')),
		array('name' => 'idx_solved', 'type' => 'index', 'columns' => array('solved')),
		array('name' => 'idx_answer', 'type' => 'index', 'columns' => array('id_answer')),
	),
	array(),
	'ignore'
);

// One row per member per answer: +1 or -1.
$smcFunc['db_create_table']('{db_prefix}qa_votes',
	array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'vote', 'type' => 'tinyint', 'size' => 4, 'null' => false, 'default' => 0),
		array('name' => 'vote_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_msg', 'id_member')),
		array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
	),
	array(),
	'ignore'
);

// Cached answer scores (only answers with votes have a row).
$smcFunc['db_create_table']('{db_prefix}qa_scores',
	array(
		array('name' => 'id_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'score', 'type' => 'int', 'size' => 10, 'null' => false, 'default' => 0),
		array('name' => 'up', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'down', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_msg')),
		array('name' => 'idx_score', 'type' => 'index', 'columns' => array('score')),
	),
	array(),
	'ignore'
);

// Cached reputation.
$smcFunc['db_create_table']('{db_prefix}qa_members',
	array(
		array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'reputation', 'type' => 'int', 'size' => 10, 'null' => false, 'default' => 0),
		array('name' => 'up', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'down', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
		array('name' => 'accepted', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
	),
	array(
		array('type' => 'primary', 'columns' => array('id_member')),
		array('name' => 'idx_reputation', 'type' => 'index', 'columns' => array('reputation')),
	),
	array(),
	'ignore'
);


// Permanent administrator adjustments. A reversal is a new opposite entry,
// not deletion of the original record. The reversed_by link prevents replays.
$smcFunc['db_create_table']('{db_prefix}qa_adjustments',
    array(
        array('name' => 'id_adjustment', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
        array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
        array('name' => 'id_admin', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
        array('name' => 'admin_name', 'type' => 'varchar', 'size' => 100, 'null' => false, 'default' => ''),
        array('name' => 'points', 'type' => 'int', 'size' => 10, 'null' => false, 'default' => 0),
        array('name' => 'reason', 'type' => 'text', 'null' => false),
        array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
        array('name' => 'reversal_of', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
        array('name' => 'reversed_by', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
    ),
    array(
        array('type' => 'primary', 'columns' => array('id_adjustment')),
        array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
        array('name' => 'idx_reversal', 'type' => 'index', 'columns' => array('reversal_of')),
    ),
    array(),
    'ignore'
);

$defaults = array(
	'qa_enabled' => 1,
	'qa_boards' => '{}',
	'qa_show_menu' => 1,
	'qa_index_badges' => 1,
	'qa_default_sort' => 'votes',
	'qa_pin_accepted' => 1,
	'qa_downvotes' => 1,
	'qa_downvote_min_rep' => 0,
	'qa_lock_solved' => 0,
	'qa_alerts' => 1,
	'qa_points_up' => 10,
	'qa_points_down' => 2,
	'qa_points_accepted' => 15,
	'qa_rep_everywhere' => 0,
);
$new = array();
foreach ($defaults as $k => $v)
	if (!isset($modSettings[$k]))
		$new[$k] = $v;

// Board permissions, once: voting for everyone who can reply, accepting
// in their own questions likewise, and accepting anywhere for moderators.
if (empty($modSettings['qa_permissions_set']))
{
	$rows = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_group, id_profile, permission
		FROM {db_prefix}board_permissions
		WHERE permission IN ({array_string:perms})
			AND add_deny = {int:yes}
			AND id_group != {int:guests}',
		array('perms' => array('post_reply_any', 'moderate_board'), 'yes' => 1, 'guests' => -1)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['permission'] === 'post_reply_any')
		{
			$rows[] = array((int) $row['id_group'], (int) $row['id_profile'], 'qa_vote', 1);
			$rows[] = array((int) $row['id_group'], (int) $row['id_profile'], 'qa_accept_own', 1);
		}
		else
			$rows[] = array((int) $row['id_group'], (int) $row['id_profile'], 'qa_accept_any', 1);
	}
	$smcFunc['db_free_result']($request);

	if (!empty($rows))
		$smcFunc['db_insert']('ignore',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$rows,
			array('id_group', 'id_profile', 'permission')
		);

	$new['qa_permissions_set'] = 1;
}

if (!empty($new))
	updateSettings($new);

if (SMF == 'SSI')
	echo 'Questions & Answers database changes are complete.';
