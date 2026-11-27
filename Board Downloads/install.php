<?php
/** Board Downloads 1.3.0: non-destructive schema installation. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Install via the SMF Package Manager.');

global $smcFunc, $modSettings;
db_extend('packages');
$smcFunc['db_create_table']('{db_prefix}bd_topic_files', array(
    array('name' => 'id_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'id_primary', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'selected_ids', 'type' => 'text'),
    array('name' => 'updated_by', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'updated_at', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_topic')),
), array(), 'ignore');
// Empty default board selection is intentional: no unrelated forum board is exposed.
$defaults = array(
    'bd_enabled' => 1,
    'bd_show_menu' => 1,
    'bd_tags' => 1,
    'bd_digest' => 1,
    'bd_insights' => 1,
    'bd_activity' => 1,
    'bd_knowledge' => 1,
    'bd_events' => 1,
    'bd_boards' => '',
    'bd_extensions' => 'zip,7z,rar,tar,gz,pdf,txt,docx,odt,ods,xlsx,pptx,mp3,mp4',
    'bd_page_size' => 15,
);
$new = array();
foreach ($defaults as $key => $value)
    if (!isset($modSettings[$key]))
        $new[$key] = $value;
if ($new)
    updateSettings($new);

// 1.3.0: independent traditional catalogue, preserving board mode and all old data.
$int = function($name,$auto=false) { $c=array('name'=>$name,'type'=>'int','size'=>10,'unsigned'=>true,'default'=>0); if ($auto) { unset($c['default']); $c['auto']=true; } return $c; };
$str = function($name,$size=255,$default='') { return array('name'=>$name,'type'=>'varchar','size'=>$size,'default'=>$default); };
$long = function($name) { return array('name'=>$name,'type'=>'text'); };
$pk = function($name) { return array('type'=>'primary','columns'=>(array)$name); };
$idx = function($name,$cols) { return array('type'=>'index','name'=>$name,'columns'=>(array)$cols); };
$tables=array(
 'bd_categories'=>array(array($int('id_category',true),$int('id_parent'),$str('name',120),$str('groups_allowed'),$int('position')),array($pk('id_category'),$idx('parent','id_parent'))),
 'bd_entries'=>array(array($int('id_entry',true),$int('id_category'),$int('id_member'),$str('title',150),$long('description'),$str('tags'),$str('state',12,'draft'),$int('publish_at'),$int('featured'),$str('github_repo',205),$str('github_tag',80),$long('github_notes'),$int('created'),$int('updated'),$int('downloads')),array($pk('id_entry'),$idx('category_state',array('id_category','state','publish_at')),$idx('author','id_member'))),
 'bd_releases'=>array(array($int('id_release',true),$int('id_entry'),$str('version',80),$str('channel',12),$long('changelog'),$str('compat_smf',120),$str('compat_php',120),$str('filename',240),$str('storage_key',52),$int('bytes'),$str('sha256',64),$int('created'),$int('downloads'),$int('approved')),array($pk('id_release'),$idx('entry','id_entry'),$idx('hash',array('id_entry','sha256')))),
 'bd_favorites'=>array(array($int('id_entry'),$int('id_member')),array($pk(array('id_entry','id_member')),$idx('member','id_member'))),
 'bd_ratings'=>array(array($int('id_entry'),$int('id_member'),$int('rating')),array($pk(array('id_entry','id_member')))),
);
foreach ($tables as $name=>$table) $smcFunc['db_create_table']('{db_prefix}'.$name,$table[0],$table[1],array(),'ignore');
$new=array(); foreach (array('bd_mode'=>'board','bd_storage'=>'','bd_max_mb'=>100) as $key=>$value) if (!isset($modSettings[$key])) $new[$key]=$value;
if ($new) updateSettings($new);
