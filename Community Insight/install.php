<?php
/**
 * Community Insights v1.1.1 - data-preserving database installer.
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('Cannot install Community Insights without SMF.');

global $smcFunc, $modSettings;
db_extend('packages');
$smcFunc['db_create_table']('{db_prefix}ci_layouts', array(
	array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'null' => false, 'default' => 0),
	array('name' => 'widgets', 'type' => 'text'),
	array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'null' => false, 'default' => 0),
), array(array('type' => 'primary', 'columns' => array('id_member'))), array(), 'ignore');

$defaults = array(
	'ci_enabled' => 1,
	'ci_integrations' => 1,
	'ci_recommendations' => 1,
	'ci_response_hours' => 48,
	'ci_cache_minutes' => 5,
	'ci_score_replies' => 2,
    'ci_score_contributors' => 4,
    'ci_score_views' => 3,
    'ci_default_widgets' => '["overview","smf","activity","popular","discussion","boards","members","recommendations","integrations"]',
);
$new = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new[$key] = $value;
if ($new)
	updateSettings($new);


// Community Insights 1.1.x: new tables are CREATE IF ABSENT, retaining all pre-existing layouts.
$ciTables = array(
    'ci_layout_meta' => array(
        'cols' => array(
            array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'config','type'=>'text'),
            array('name'=>'updated','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        ), 'indexes' => array(array('type'=>'primary','columns'=>array('id_member'))),
    ),
    'ci_reports' => array(
        'cols' => array(
            array('name'=>'id_report','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
            array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'title','type'=>'varchar','size'=>120,'default'=>''),
            array('name'=>'report_type','type'=>'varchar','size'=>32,'default'=>''),
            array('name'=>'options','type'=>'text'),
            array('name'=>'created','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'updated','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        ), 'indexes' => array(
            array('type'=>'primary','columns'=>array('id_report')),
            array('name'=>'idx_member','type'=>'index','columns'=>array('id_member','updated')),
        ),
    ),
    'ci_export_history' => array(
        'cols' => array(
            array('name'=>'id_export','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
            array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'id_report','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'report_type','type'=>'varchar','size'=>32,'default'=>''),
            array('name'=>'format','type'=>'varchar','size'=>8,'default'=>''),
            array('name'=>'range_start','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'range_end','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'exported','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        ), 'indexes' => array(array('type'=>'primary','columns'=>array('id_export')),
            array('name'=>'idx_member_export','type'=>'index','columns'=>array('id_member','exported'))),
    ),
    'ci_schedules' => array(
        'cols' => array(
            array('name'=>'id_schedule','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
            array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'id_report','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'frequency','type'=>'varchar','size'=>12,'default'=>'weekly'),
            array('name'=>'next_send','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
        ), 'indexes' => array(array('type'=>'primary','columns'=>array('id_schedule')),
            array('name'=>'idx_schedule_due','type'=>'index','columns'=>array('enabled','next_send')),
            array('name'=>'idx_schedule_member_report','type'=>'unique','columns'=>array('id_member','id_report'))),
    ),
    'ci_alert_rules' => array(
        'cols' => array(
            array('name'=>'id_rule','type'=>'int','size'=>10,'unsigned'=>true,'auto'=>true),
            array('name'=>'id_member','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'metric','type'=>'varchar','size'=>32,'default'=>''),
            array('name'=>'comparator','type'=>'varchar','size'=>2,'default'=>'gt'),
            array('name'=>'threshold','type'=>'float','default'=>0),
            array('name'=>'enabled','type'=>'tinyint','size'=>1,'unsigned'=>true,'default'=>1),
            array('name'=>'last_triggered','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        ), 'indexes' => array(array('type'=>'primary','columns'=>array('id_rule')),
            array('name'=>'idx_alert_member','type'=>'index','columns'=>array('id_member'))),
    ),
    'ci_snapshots' => array(
        'cols' => array(
            array('name'=>'day_utc','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'id_board','type'=>'mediumint','size'=>8,'unsigned'=>true,'default'=>0),
            array('name'=>'posts','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'topics','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
            array('name'=>'captured','type'=>'int','size'=>10,'unsigned'=>true,'default'=>0),
        ), 'indexes' => array(array('type'=>'primary','columns'=>array('day_utc','id_board')),
            array('name'=>'idx_snapshot_board','type'=>'index','columns'=>array('id_board'))),
    ),
);
foreach ($ciTables as $ciName => $ciSpec)
    $smcFunc['db_create_table']('{db_prefix}' . $ciName, $ciSpec['cols'], $ciSpec['indexes'], array(), 'ignore');

// SMF reads a scheduled task callable as "function|file" (see load_file() in Subs.php).
// Updating the existing row also repairs installs of 1.1.0, which stored it the wrong way round.
$taskCallable = 'ci_run|$sourcedir/CI-Tasks.php';
$request = $smcFunc['db_query']('', 'SELECT id_task FROM {db_prefix}scheduled_tasks WHERE task = {string:name}', array('name' => 'community_insights'));
$taskExists = $smcFunc['db_num_rows']($request) > 0;
$smcFunc['db_free_result']($request);
if (!$taskExists)
    $smcFunc['db_insert']('insert', '{db_prefix}scheduled_tasks',
        array('next_time'=>'int','time_offset'=>'int','time_regularity'=>'int','time_unit'=>'string','disabled'=>'int','task'=>'string','callable'=>'string'),
        array(time() + 600, 0, 1, 'd', 0, 'community_insights', $taskCallable), array('id_task'));
else
    $smcFunc['db_query']('', 'UPDATE {db_prefix}scheduled_tasks SET callable = {string:callable} WHERE task = {string:name}',
        array('callable' => $taskCallable, 'name' => 'community_insights'));
