<?php
/** Only invoked if the SMF Package Manager's "remove all mod data" option is selected. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Uninstallation must run from SMF Package Manager.');

global $smcFunc;
db_extend('packages');
foreach (array('tp_follows','tp_topic_prefix','tp_topic_tags','tp_prefixes','tp_tags') as $table)
    $smcFunc['db_drop_table']('{db_prefix}' . $table);
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable IN ({array_string:vars})', array(
    'vars'=>array('tp_enabled','tp_tags_enabled','tp_prefixes_enabled','tp_follow_enabled','tp_member_tags','tp_max_tags'),
));
