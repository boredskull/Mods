<?php
// Runs only when "Remove all data associated with this modification" is selected.
if (!defined('SMF')) die('No direct access');
global $smcFunc;
db_extend('packages');

foreach (array('bsap_authors', 'bsap_reveals') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permissions WHERE permission IN ({array_string:perms})',
	array('perms' => array('bsap_use', 'bsap_reveal')));
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable IN ({array_string:vars})',
	array('vars' => array('bsap_enabled', 'bsap_boards', 'bsap_force_boards')));
cache_put_data('modSettings', null, 90);
