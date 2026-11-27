<?php
/** Safe, idempotent installation; existing form definitions are retained. */
if (!defined('SMF')) die('SMF only');
global $smcFunc;
db_extend('packages');
$smcFunc['db_create_table']('{db_prefix}bstf_forms', array(
    array('name'=>'id_board','type'=>'smallint','unsigned'=>true,'not_null'=>true),
    array('name'=>'title','type'=>'varchar','size'=>120,'not_null'=>true,'default'=>''),
    array('name'=>'description','type'=>'varchar','size'=>4000,'not_null'=>true,'default'=>''),
    array('name'=>'fields_json','type'=>'mediumtext','not_null'=>true),
    array('name'=>'enabled','type'=>'tinyint','unsigned'=>true,'not_null'=>true,'default'=>0),
    array('name'=>'prefix_key','type'=>'varchar','size'=>40,'not_null'=>true,'default'=>''),
), array(array('type'=>'primary','columns'=>array('id_board'))), array(), 'ignore');

// Upgrade existing definitions in place.
$smcFunc['db_add_column']('{db_prefix}bstf_forms',
    array('name'=>'form_only','type'=>'tinyint','unsigned'=>true,'not_null'=>false,'default'=>0), array(), 'ignore');
$smcFunc['db_query']('', 'UPDATE {db_prefix}bstf_forms SET form_only={int:off} WHERE form_only IS NULL', array('off'=>0));
$smcFunc['db_change_column']('{db_prefix}bstf_forms', 'description',
    array('name'=>'description','type'=>'varchar','size'=>4000,'not_null'=>true,'default'=>''));
$smcFunc['db_create_table']('{db_prefix}bstf_layouts', array(
    array('name'=>'layout_key','type'=>'varchar','size'=>64,'not_null'=>true),
    array('name'=>'name','type'=>'varchar','size'=>100,'not_null'=>true),
    array('name'=>'definition','type'=>'mediumtext','not_null'=>true),
), array(array('type'=>'primary','columns'=>array('layout_key'))), array(), 'ignore');
