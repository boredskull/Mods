<?php
/**
 * Questions & Answers upgrade (1.0.0 - 1.2.0 to 1.2.1): create the adjustment ledger
 * and add any column an older install is missing.
 * Does not overwrite existing members, points, questions or settings.
 * @package QuestionsAnswers
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('No direct access...');

global $smcFunc;
db_extend('packages');


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

// Older installs may predate the "locked by Q&A" flag. Adding a column that
// already exists does nothing.
$smcFunc['db_add_column']('{db_prefix}qa_topics',
	array('name' => 'locked_by_qa', 'type' => 'tinyint', 'size' => 3, 'unsigned' => true, 'null' => false, 'default' => 0),
	array(), 'ignore'
);
