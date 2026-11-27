<?php
/** Topic Tags & Prefixes: idempotent database installation. ISC. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Installation must run from SMF Package Manager.');

global $smcFunc, $modSettings;
db_extend('packages');

$tables = array(
    'tp_tags' => array(
        array('name'=>'id_tag','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
        array('name'=>'name','type'=>'varchar','size'=>40,'default'=>''),
        array('name'=>'slug','type'=>'varchar','size'=>64,'default'=>''),
        array('name'=>'created_by','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
        array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
        array('name'=>'created_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
    ),
    'tp_topic_tags' => array(
        array('name'=>'id_topic','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
        array('name'=>'id_tag','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
    ),
    'tp_prefixes' => array(
        array('name'=>'id_prefix','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
        array('name'=>'name','type'=>'varchar','size'=>40,'default'=>''),
        array('name'=>'color','type'=>'varchar','size'=>7,'default'=>'#3779ad'),
        array('name'=>'board_ids','type'=>'varchar','size'=>255,'default'=>''),
        array('name'=>'sort_order','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
    ),
    'tp_topic_prefix' => array(
        array('name'=>'id_topic','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
        array('name'=>'id_prefix','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
    ),
    'tp_follows' => array(
        array('name'=>'id_tag','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
        array('name'=>'seen_at','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
    ),
);
$indexes = array(
    'tp_tags' => array(
        array('type'=>'primary','columns'=>array('id_tag')),
        array('name'=>'idx_slug','type'=>'unique','columns'=>array('slug')),
        array('name'=>'idx_enabled','type'=>'index','columns'=>array('enabled')),
    ),
    'tp_topic_tags' => array(
        array('type'=>'primary','columns'=>array('id_topic','id_tag')),
        array('name'=>'idx_tag_topic','type'=>'index','columns'=>array('id_tag','id_topic')),
    ),
    'tp_prefixes' => array(array('type'=>'primary','columns'=>array('id_prefix'))),
    'tp_topic_prefix' => array(
        array('type'=>'primary','columns'=>array('id_topic')),
        array('name'=>'idx_prefix','type'=>'index','columns'=>array('id_prefix','id_topic')),
    ),
    'tp_follows' => array(
        array('type'=>'primary','columns'=>array('id_tag','id_member')),
        array('name'=>'idx_member','type'=>'index','columns'=>array('id_member','id_tag')),
    ),
);
foreach ($tables as $name=>$cols)
    $smcFunc['db_create_table']('{db_prefix}' . $name, $cols, $indexes[$name], array(), 'ignore');

$defaults = array('tp_enabled'=>1, 'tp_tags_enabled'=>1, 'tp_prefixes_enabled'=>1, 'tp_follow_enabled'=>1, 'tp_member_tags'=>1, 'tp_max_tags'=>5);
$add = array();
foreach ($defaults as $key=>$value)
    if (!isset($modSettings[$key])) $add[$key] = $value;
if ($add) updateSettings($add);

// 1.0.1 upgrade: remove the public main-navigation hook installed by 1.0.0.
// SMF stores integration hooks in the settings table, so omitting the hook from
// the new package manifest alone would leave the old menu button in place.
// This is safe to run for both new installs and upgrades.
if (function_exists('remove_integration_function'))
    remove_integration_function('integrate_menu_buttons', 'BoredTopicTags::hookMenu', true, '$sourcedir/TopicTags.php');

// Initial prefixes for a fresh install only. Reinstallation preserves customised prefixes.
$r = $smcFunc['db_query']('', 'SELECT id_prefix FROM {db_prefix}tp_prefixes LIMIT 1', array());
$hasPrefix = (bool) $smcFunc['db_fetch_assoc']($r);
$smcFunc['db_free_result']($r);
if (!$hasPrefix)
    foreach (array(
        array('Announcement','#5743a2',10),
        array('Guide','#2467a0',20),
        array('Support','#bc681b',30),
        array('Solved','#23794a',40),
        array('Bug','#a83c3c',50),
    ) as $row)
        $smcFunc['db_insert']('insert', '{db_prefix}tp_prefixes',
            array('name'=>'string-40','color'=>'string-7','board_ids'=>'string-255','sort_order'=>'int','enabled'=>'int'),
            array($row[0],$row[1],'',$row[2],1),array('id_prefix'));
