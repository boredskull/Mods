<?php
/**
 * Knowledge Base: remove its data. SMF only runs this when the admin ticks
 * "Remove all data associated with this modification" on uninstall, so a
 * normal uninstall (or an upgrade) keeps every article, revision and vote.
 * ISC.
 */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Uninstallation must run through SMF Package Manager.');

global $smcFunc;
db_extend('packages');

foreach (array('kb_feedback', 'kb_revisions', 'kb_articles', 'kb_categories') as $table)
    $smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable LIKE {string:prefix}', array('prefix' => 'kb\\_%'));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permissions WHERE permission IN ({array_string:perms})',
    array('perms' => array('kb_submit', 'kb_edit_own', 'kb_edit_any', 'kb_review')));

updateSettings(array('settings_updated' => time()));
