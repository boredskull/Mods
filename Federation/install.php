<?php
/** Federation package data install, safe to re-run on upgrades. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF')) require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF')) die('Install through SMF Package Manager.');
global $smcFunc, $modSettings;
db_extend('packages');
$tables = array(
 'federation_actors' => array(
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'actor_type','type'=>'varchar','size'=>12,'null'=>false),
  array('name'=>'id_local','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'enabled','type'=>'tinyint','size'=>1,'default'=>1),
  array('name'=>'public_key','type'=>'text','null'=>false),
  array('name'=>'private_key','type'=>'text','null'=>false),
 ),
 'federation_followers' => array(
  array('name'=>'id_follower','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'remote_hash','type'=>'varchar','size'=>64,'null'=>false),
  array('name'=>'remote_actor','type'=>'text','null'=>false),
  array('name'=>'inbox','type'=>'text','null'=>false),
  array('name'=>'status','type'=>'varchar','size'=>16,'null'=>false,'default'=>'active'),
 ),
 'federation_following' => array(
  array('name'=>'id_following','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'remote_hash','type'=>'varchar','size'=>64,'null'=>false),
  array('name'=>'remote_actor','type'=>'text','null'=>false),
  array('name'=>'inbox','type'=>'text','null'=>false),
  array('name'=>'status','type'=>'varchar','size'=>16,'null'=>false,'default'=>'requested'),
 ),
 'federation_inbox' => array(
  array('name'=>'id_inbox','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'activity_hash','type'=>'varchar','size'=>64,'null'=>false),
  array('name'=>'activity_id','type'=>'text','null'=>false),
  array('name'=>'remote_actor','type'=>'text','null'=>false),
  array('name'=>'activity_type','type'=>'varchar','size'=>32,'null'=>false),
  array('name'=>'payload','type'=>'mediumtext','null'=>false),
  array('name'=>'status','type'=>'varchar','size'=>16,'null'=>false,'default'=>'pending'),
  array('name'=>'received','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
 ),
 'federation_delivery' => array(
  array('name'=>'id_delivery','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'inbox','type'=>'text','null'=>false),
  array('name'=>'payload','type'=>'mediumtext','null'=>false),
  array('name'=>'attempts','type'=>'tinyint','size'=>3,'unsigned'=>true,'null'=>false,'default'=>0),
  array('name'=>'next_try','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'status','type'=>'varchar','size'=>16,'null'=>false,'default'=>'queued'),
  array('name'=>'last_error','type'=>'varchar','size'=>255,'null'=>false,'default'=>''),
 ),
 'federation_objects' => array(
  array('name'=>'id_object','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'id_actor','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'id_topic','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'id_message','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'visible','type'=>'tinyint','size'=>1,'null'=>false,'default'=>0),
  array('name'=>'object_json','type'=>'mediumtext','null'=>false),
  array('name'=>'activity_json','type'=>'mediumtext','null'=>false),
 ),
 'federation_blocks' => array(
  array('name'=>'id_block','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'domain','type'=>'varchar','size'=>190,'null'=>false),
 ),
 'federation_rate' => array(
  array('name'=>'id_rate','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'ip_hash','type'=>'varchar','size'=>64,'null'=>false),
  array('name'=>'window_start','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'hits','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false,'default'=>0),
 ),
 'federation_log' => array(
  array('name'=>'id_log','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
  array('name'=>'logged','type'=>'int','size'=>10,'unsigned'=>true,'null'=>false),
  array('name'=>'event_type','type'=>'varchar','size'=>32,'null'=>false),
  array('name'=>'details','type'=>'varchar','size'=>255,'null'=>false),
 ),
);
foreach ($tables as $name => $columns) {
 $primary = array('type'=>'primary','columns'=>array($columns[0]['name']));
 $indexes = array($primary);
 if ($name === 'federation_actors') $indexes[] = array('name'=>'actor_local','type'=>'unique','columns'=>array('actor_type','id_local'));
 if ($name === 'federation_followers' || $name === 'federation_following') $indexes[] = array('name'=>'actor_remote','type'=>'unique','columns'=>array('id_actor','remote_hash'));
 if ($name === 'federation_inbox') $indexes[] = array('name'=>'local_activity','type'=>'unique','columns'=>array('id_actor','activity_hash'));
 if ($name === 'federation_objects') $indexes[] = array('name'=>'actor_message','type'=>'unique','columns'=>array('id_actor','id_message'));
 if ($name === 'federation_rate') $indexes[] = array('name'=>'ip_hash','type'=>'unique','columns'=>array('ip_hash'));
 if ($name === 'federation_blocks') $indexes[] = array('name'=>'domain','type'=>'unique','columns'=>array('domain'));
 $smcFunc['db_create_table']('{db_prefix}' . $name, $columns, $indexes, array(), 'ignore');
}
$smcFunc['db_add_column']('{db_prefix}federation_followers', array('name'=>'follow_id','type'=>'varchar','size'=>2048,'null'=>false,'default'=>''), array(), 'ignore');
$smcFunc['db_add_column']('{db_prefix}federation_followers', array('name'=>'shared_inbox','type'=>'varchar','size'=>2048,'null'=>false,'default'=>''), array(), 'ignore');
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable = {string:key}', array('key'=>'federation_auto_queue'));
$defaults = array('federation_enabled'=>0, 'federation_inbound'=>0, 'federation_outbound'=>0,
 'federation_allow_members'=>0, 'federation_member_outbound'=>0);
$updates = array();
foreach ($defaults as $k=>$v) if (!isset($modSettings[$k])) $updates[$k]=$v;
if ($updates) updateSettings($updates);
if (SMF == 'SSI') echo 'Federation tables installed.';
