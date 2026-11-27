<?php
/** Optional data deletion when user chooses full uninstall. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF')) require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) die('Install/uninstall through Package Manager');
global $smcFunc;
db_extend('packages');
foreach (array('actors','followers','following','inbox','delivery','objects','blocks','log','rate') as $name)
 $smcFunc['db_drop_table']('{db_prefix}federation_' . $name);
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable IN ({array_string:vars})',
 array('vars'=>array('federation_enabled','federation_inbound','federation_outbound','federation_allow_members','federation_member_outbound','federation_auto_queue')));
cache_put_data('modSettings', null, 90);
