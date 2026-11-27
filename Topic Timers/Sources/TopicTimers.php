<?php
/**
 * BoredSkull Topic Timers 1.0.6 - ISC licence.
 * SMF 2.1.x hooks-first; scheduled actions run through SMF task system.
 */
if (!defined('SMF')) die('No direct access');

function BTT_actions(&$actions) { $actions['topictimers']=array('TopicTimers.php','BTT_dispatch'); }
function BTT_task_labels(&$subActions)
{
 global $txt;
 loadLanguage('TopicTimers','',false);
 $txt['scheduled_task_btt_process']=$txt['scheduled_task_btt_process'] ?? 'Process topic timers';
 $txt['scheduled_task_desc_btt_process']=$txt['scheduled_task_desc_btt_process'] ?? 'Executes due topic timers.';
}
function BTT_admin_areas(&$areas)
{
 global $txt;
 loadLanguage('TopicTimers','',false);
 $txt['btt_title']=$txt['btt_title'] ?? 'Topic Timers';
 $txt['btt']=$txt['btt'] ?? 'Topic Timers';
 $areas['config']['areas']['modsettings']['subsections']['btt']=array($txt['btt_title'] ?? 'Topic Timers', array('admin_forum'));
}
function BTT_admin_tabs(&$subActions)
{
 global $context,$txt;
 loadLanguage('TopicTimers','',false);
 $subActions['btt']='BTT_admin';
 $context[$context['admin_menu_name']]['tab_data']['tabs']['btt']=array('description'=>$txt['btt_description'] ?? 'Schedule automatic topic actions.');
}
function BTT_buttons(&$buttons)
{
 global $context,$scripturl,$modSettings,$txt;
 loadLanguage('TopicTimers','',false);
 if (empty($modSettings['btt_enabled']) || empty($context['current_topic']) || empty($context['current_board'])) return;
 if (!BTT_can('lock',(int)$context['current_board']) && !BTT_can('move',(int)$context['current_board']) && !BTT_can('unpin',(int)$context['current_board'])) return;
 $txt['btt_button']=$txt['btt_button'] ?? 'Topic timer';
 $buttons['btt']=array('text'=>'btt_button','url'=>$scripturl.'?action=topictimers;topic='.(int)$context['current_topic'],'show'=>true);
}
function BTT_can($action,$board)
{
 global $user_info,$modSettings;
 if (!empty($user_info['is_guest']) || empty($modSettings['btt_enabled'])) return false;
 if (!BTT_visible($board)) return false;
 if (allowedTo('admin_forum')) return true;
 if ($action==='delete') return false;
 $perm = $action==='move' ? 'move_any' : (in_array($action,array('pin','unpin'),true) ? 'make_sticky' : 'lock_any');
 return allowedTo($perm,$board);
}
function BTT_visible($board)
{
 global $smcFunc;
 $r=$smcFunc['db_query']('', 'SELECT b.id_board FROM {db_prefix}boards AS b WHERE b.id_board={int:board} AND {query_see_board}',array('board'=>(int)$board));
 $ok=$smcFunc['db_num_rows']($r)>0; $smcFunc['db_free_result']($r); return $ok;
}
function BTT_topic($id)
{
 global $smcFunc;
 $r=$smcFunc['db_query']('', 'SELECT t.id_topic,t.id_board,t.locked,t.is_sticky,b.redirect FROM {db_prefix}topics AS t INNER JOIN {db_prefix}boards AS b ON (t.id_board=b.id_board) WHERE t.id_topic={int:topic} LIMIT 1',array('topic'=>$id));
 $row=$smcFunc['db_fetch_assoc']($r);$smcFunc['db_free_result']($r);
 return $row ?: false;
}
function BTT_target($id)
{
 global $smcFunc;
 if ($id<=0) return false;
 $r=$smcFunc['db_query']('', 'SELECT id_board FROM {db_prefix}boards WHERE id_board={int:board} AND redirect={string:empty} LIMIT 1',array('board'=>$id,'empty'=>''));
 $exists=$smcFunc['db_num_rows']($r)>0;$smcFunc['db_free_result']($r);return $exists;
}
/** Recheck an author's *current* permissions against the relevant board profile.
 * Uses SMF's membergroup permissions tables without switching the scheduled task's
 * global user_info. Groups 0 and post-based groups are included as in SMF.
 */
function BTT_member_can($id_member, $action, $id_board)
{
 global $smcFunc, $modSettings;
 $id_member=(int)$id_member;
 if ($id_member<1 || $id_board<1) return false;
 $r=$smcFunc['db_query']('', 'SELECT id_group,id_post_group,additional_groups,is_activated FROM {db_prefix}members WHERE id_member={int:id} LIMIT 1',array('id'=>$id_member));
 $m=$smcFunc['db_fetch_assoc']($r);$smcFunc['db_free_result']($r);
 if (!$m || (int)$m['is_activated']!==1) return false;
 // Admin group is intrinsically privileged; however removal still requires its setting.
 if ((int)$m['id_group']===1 || in_array(1,array_map('intval',explode(',',(string)$m['additional_groups'])),true)) return true;
 if ($action==='delete') return false;
 $groups=array_unique(array_merge(array((int)$m['id_group']), (int)$m['id_post_group']>0 ? array((int)$m['id_post_group']) : array(), array_map('intval',array_filter(explode(',',(string)$m['additional_groups']),'strlen'))));
 $r=$smcFunc['db_query']('', 'SELECT id_profile,redirect,member_groups,deny_member_groups FROM {db_prefix}boards WHERE id_board={int:id} LIMIT 1',array('id'=>$id_board));
 $b=$smcFunc['db_fetch_assoc']($r);$smcFunc['db_free_result']($r);
 if (!$b || $b['redirect']!=='') return false;
 $allow=explode(',',(string)$b['member_groups']);
 $deny=explode(',',(string)$b['deny_member_groups']);
 $managers=empty($modSettings['board_manager_groups']) ? array() : explode(',', $modSettings['board_manager_groups']);
 if (!array_intersect($groups,array_merge($allow,$managers)) || array_intersect($groups,array_diff($deny,$managers))) return false;
 // Local moderator membership is specific to this board.
 $r=$smcFunc['db_query']('', 'SELECT id_member FROM {db_prefix}moderators WHERE id_board={int:board} AND id_member={int:member}',array('board'=>$id_board,'member'=>$id_member));
 $local=$smcFunc['db_num_rows']($r)>0; $smcFunc['db_free_result']($r);
 $r=$smcFunc['db_query']('', 'SELECT id_group FROM {db_prefix}moderator_groups WHERE id_board={int:board} AND id_group IN ({array_int:groups})',array('board'=>$id_board,'groups'=>$groups));
 $local=$local || $smcFunc['db_num_rows']($r)>0; $smcFunc['db_free_result']($r);
 if ($local) $groups[]=3;
 $permission=$action==='move'?'move_any':(in_array($action,array('pin','unpin'),true)?'make_sticky':'lock_any');
 $r=$smcFunc['db_query']('', 'SELECT add_deny FROM {db_prefix}board_permissions WHERE id_profile={int:profile} AND id_group IN ({array_int:groups}) AND permission={string:perm}',array('profile'=>(int)$b['id_profile'],'groups'=>$groups,'perm'=>$permission));
 $grant=false;$deny=false;
 while($v=$smcFunc['db_fetch_assoc']($r)){if (empty($v['add_deny'])) $deny=true;else $grant=true;}
 $smcFunc['db_free_result']($r);
 return $grant && (empty($modSettings['permission_enable_deny']) || !$deny);
}
function BTT_authorized_at_execution($row,$board)
{
 global $modSettings;
 if (empty($modSettings['btt_recheck_permissions'])) return true;
 $action=(string)$row['action'];
 if (!BTT_member_can((int)$row['id_member'],$action,(int)$board)) return false;
 if ($action==='move' && !BTT_member_can((int)$row['id_member'],'move',(int)$row['target_board'])) return false;
 return true;
}
/** Validate current board and action state before retrying a claimed job. */
function BTT_action_already_applied($row,$info)
{
 global $modSettings;
 if (!$info) return $row['action']==='delete';
 switch($row['action'])
 {
  case 'lock': return (int)$info['locked']!==0;
  case 'unlock': return (int)$info['locked']===0;
  case 'pin': return (int)$info['is_sticky']!==0;
  case 'unpin': return (int)$info['is_sticky']===0;
  case 'move': return (int)$info['id_board']===(int)$row['target_board'];
  case 'delete': return !empty($modSettings['recycle_enable']) && (int)$modSettings['recycle_board']>0 && (int)$info['id_board']===(int)$modSettings['recycle_board'] && (int)$row['id_board']!==(int)$info['id_board'];
 }
 return false;
}
function BTT_failure_status($tries)
{
 return $tries>=5?'failed':'pending';
}
function BTT_scheduler_info()
{
 global $smcFunc,$modSettings;
 $r=$smcFunc['db_query']('', 'SELECT id_task,next_time,disabled FROM {db_prefix}scheduled_tasks WHERE task={string:task} LIMIT 1',array('task'=>'btt_process'));
 $task=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
 $last=0;
 if ($task)
 {
  $r=$smcFunc['db_query']('', 'SELECT MAX(time_run) AS last_run FROM {db_prefix}log_scheduled_tasks WHERE id_task={int:id}',array('id'=>(int)$task['id_task']));
  $row=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r); $last=(int)($row['last_run']??0);
 }
 return array('exists'=>(bool)$task,'disabled'=>!$task || !empty($task['disabled']),'enabled'=>!empty($modSettings['btt_enabled']),'next'=>(int)($task['next_time']??0),'last'=>$last);
}
function BTT_admin()
{
 global $context,$modSettings,$scripturl,$txt;
 isAllowedTo('admin_forum');loadLanguage('TopicTimers','',false);
 if (isset($_POST['btt_save']))
 {
  checkSession('post');
  updateSettings(array('btt_enabled'=>empty($_POST['btt_enabled'])?0:1,'btt_delete_enabled'=>empty($_POST['btt_delete_enabled'])?0:1,'btt_recheck_permissions'=>empty($_POST['btt_recheck_permissions'])?0:1));
  redirectexit('action=admin;area=modsettings;sa=btt');
 }
 if (isset($_POST['btt_retry_failed']))
 {
  checkSession('post');
  global $smcFunc;
  $id=(int)($_POST['id_timer']??0);
  BTT_retry_failed($id,0);
  redirectexit('action=admin;area=modsettings;sa=btt');
 }
 global $smcFunc;
 $request=$smcFunc['db_query']('', 'SELECT id_timer,id_topic,action,execute_at,error_message FROM {db_prefix}btt_timers WHERE status={string:failed} ORDER BY id_timer DESC LIMIT 100',array('failed'=>'failed'));
 $context['btt_scheduler']=BTT_scheduler_info();
 $context['btt_failed']=array();
 while ($row=$smcFunc['db_fetch_assoc']($request)) $context['btt_failed'][]=$row;
 $smcFunc['db_free_result']($request);
 $context['page_title']='Topic Timers';
 $context['btt_settings']=array('enabled'=>!empty($modSettings['btt_enabled']),'delete'=>!empty($modSettings['btt_delete_enabled']),'recheck'=>!empty($modSettings['btt_recheck_permissions']));
 loadTemplate('TopicTimers');$context['sub_template']='btt_admin';
}
function BTT_dispatch()
{
 global $context,$smcFunc,$user_info,$scripturl,$modSettings;
 if (empty($user_info['is_guest']) && !empty($modSettings['btt_enabled'])) {} else fatal_error('Topic Timers is disabled or unavailable.',false);
 $topic=(int)($_REQUEST['topic']??0);
 $context['page_title']='Topic Timers';$context['robot_no_index']=true;
 $sa=(string)($_REQUEST['sa']??'list');
 loadTemplate('TopicTimers');
 if ($sa==='save')
 {
  checkSession('post');
  $topic=(int)($_POST['topic']??0);$action=(string)($_POST['timer_action']??'');
  if (!in_array($action,array('lock','unlock','move','pin','unpin','delete'),true)) fatal_error('Invalid timer action.',false);
  $info=BTT_topic($topic);
  if (!$info || $info['redirect']!=='') fatal_error('Topic not available.',false);
  if (!BTT_action_available($action,$info)) fatal_error('The topic state has changed. Refresh and choose an available action.',false);
  if (!BTT_can($action,(int)$info['id_board'])) fatal_lang_error('cannot_admin_forum',false);
  if ($action==='delete' && empty($modSettings['btt_delete_enabled'])) fatal_error('Timed topic removal is disabled.',false);
  $target=$action==='move'?(int)($_POST['target_board']??0):0;
  if ($action==='move' && ($target===(int)$info['id_board'] || !BTT_target($target) || !BTT_can('move',$target))) fatal_error('Choose an accessible destination board.',false);
  $raw=trim((string)($_POST['execute_at']??''));
  $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone('UTC'));
  if (!$dt || $dt->format('Y-m-d\TH:i')!==$raw || $dt->getTimestamp()<=time()+30) fatal_error('Choose a future UTC time (at least 30 seconds ahead).',false);
  $lock=BTT_acquire_lock(3);
  if (!$lock) fatal_error('Topic Timers is busy. Please try again.',false);
  try {
  // Never execute two contradictory actions on the same topic at the same timestamp.
  $conflict=$smcFunc['db_query']('', 'SELECT id_timer FROM {db_prefix}btt_timers WHERE id_topic={int:topic} AND execute_at={int:due} AND status IN ({array_string:statuses}) LIMIT 1',array('topic'=>$topic,'due'=>$dt->getTimestamp(),'statuses'=>array('pending','processing')));
  $has_conflict=$smcFunc['db_num_rows']($conflict)>0;$smcFunc['db_free_result']($conflict);
  if (!$has_conflict) {
  $smcFunc['db_insert']('', '{db_prefix}btt_timers',array('id_topic'=>'int','id_board'=>'int','id_member'=>'int','action'=>'string-12','target_board'=>'int','execute_at'=>'int','created_at'=>'int','status'=>'string-12'),array($topic,(int)$info['id_board'],(int)$user_info['id'],$action,$target,$dt->getTimestamp(),time(),'pending'),array('id_timer'));
  }
  } finally { BTT_release_lock($lock); }
  if ($has_conflict) fatal_error('Another active timer for this topic already uses that time. Choose a different minute.',false);
  call_integration_hook('integrate_btt_created',array($topic,$action,$dt->getTimestamp()));
  redirectexit('action=topictimers;topic='.$topic);
 }
 if ($sa==='retry')
 {
  checkSession('post');
  isAllowedTo('admin_forum');
  $id=(int)($_POST['id_timer']??0);
  BTT_retry_failed($id,$topic);
  redirectexit('action=topictimers;topic='.$topic);
 }
 if ($sa==='cancel')
 {
  checkSession('post');$id=(int)($_POST['id_timer']??0);
  $r=$smcFunc['db_query']('', 'SELECT id_timer,id_topic,id_board,id_member,action,status FROM {db_prefix}btt_timers WHERE id_timer={int:id} LIMIT 1',array('id'=>$id));
  $item=$smcFunc['db_fetch_assoc']($r);$smcFunc['db_free_result']($r);
  if (!$item || (int)$item['id_topic']!==$topic || ((int)$item['id_member']!==(int)$user_info['id'] && !allowedTo('admin_forum'))) fatal_error('Timer not available.',false);
  $current=BTT_topic($topic);
  if (!$current || !BTT_can((string)$item['action'],(int)$current['id_board'])) fatal_error('Permission denied.',false);
  $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:cancelled} WHERE id_timer={int:id} AND status={string:pending}',array('cancelled'=>'cancelled','id'=>$id,'pending'=>'pending'));
  redirectexit('action=topictimers;topic='.$topic);
 }
 if (!$topic) fatal_error('Choose a topic first.',false);
 $info=BTT_topic($topic);if (!$info || $info['redirect']!=='') fatal_error('Topic not found.',false);
 $board=(int)$info['id_board'];
 $context['btt_allowed']=array();
 foreach(array('lock','unlock','move','pin','unpin','delete') as $act)
  if (BTT_action_available($act,$info) && BTT_can($act,$board) && ($act!=='delete' || !empty($modSettings['btt_delete_enabled']))) $context['btt_allowed'][]=$act;
 if (empty($context['btt_allowed'])) fatal_error('You cannot manage timers for this topic.',false);
 $context['btt_topic']=$info;
 $context['btt_boards']=array();
 $request=$smcFunc['db_query']('', 'SELECT id_board,name FROM {db_prefix}boards WHERE redirect={string:empty} ORDER BY board_order',array('empty'=>''));
 while ($b=$smcFunc['db_fetch_assoc']($request))
  if ((int)$b['id_board']!==$board && BTT_can('move',(int)$b['id_board'])) $context['btt_boards'][]=$b;
 $smcFunc['db_free_result']($request);
 $request=$smcFunc['db_query']('', 'SELECT id_timer,id_topic,id_member,action,target_board,execute_at,status,error_message,retry_at FROM {db_prefix}btt_timers WHERE id_topic={int:topic}' . (!allowedTo('admin_forum') ? ' AND id_member={int:member}' : '') . ' ORDER BY id_timer DESC LIMIT 100',array('topic'=>$topic,'member'=>(int)$user_info['id']));
 $context['btt_rows']=array();while($row=$smcFunc['db_fetch_assoc']($request)) $context['btt_rows'][]=$row;
 $smcFunc['db_free_result']($request);
 $context['btt_scheduler']=BTT_scheduler_info();
 $context['sub_template']='btt_list';
}
/** Native SMF moderation helpers require a numeric current member even in cron.
 * Use a guest context, not an impersonated administrator, and restore it afterwards.
 */
function BTT_native_action($callback)
{
 global $user_info;
 $previous=$user_info;
 if (!is_array($user_info)) $user_info=array();
 if (!isset($user_info['id'])) $user_info['id']=0;
 $user_info['id']=(int)$user_info['id'];
 if (!isset($user_info['is_guest'])) $user_info['is_guest']=$user_info['id']===0;
 try { return $callback(); }
 finally { $user_info=$previous; }
}
function BTT_action_available($action,$info)
{
 switch ($action) {
  case 'lock': return empty($info['locked']);
  case 'unlock': return !empty($info['locked']);
  case 'pin': return empty($info['is_sticky']);
  case 'unpin': return !empty($info['is_sticky']);
  default: return true;
 }
}
function BTT_execute($row)
{
 global $smcFunc,$sourcedir,$modSettings;
 $topic=(int)$row['id_topic'];$info=BTT_topic($topic);
 // Only removal is considered completed when the topic no longer exists.
 if (!$info)
 {
  if ($row['action']==='delete') return true;
  throw new RuntimeException('Topic no longer exists; review required');
 }
 if ($info['redirect']!=='') throw new RuntimeException('Source topic is in a redirect board');
 $action=$row['action'];$board=(int)$info['id_board'];
 // Completed just before a crash: do not repeat destructive operations.
 if (BTT_action_already_applied($row,$info)) return true;
 if ($board!==(int)$row['id_board']) throw new RuntimeException('Topic board changed since scheduling; manual review required');
 if (!BTT_authorized_at_execution($row,$board)) throw new RuntimeException('Scheduling member no longer has required permissions');
 if ($action==='lock' || $action==='unlock')
 {
  $smcFunc['db_query']('', 'UPDATE {db_prefix}topics SET locked={int:value} WHERE id_topic={int:topic} AND id_board={int:board}',array('value'=>$action==='lock'?1:0,'topic'=>$topic,'board'=>$board));
 }
 elseif (in_array($action,array('pin','unpin'),true)) $smcFunc['db_query']('', 'UPDATE {db_prefix}topics SET is_sticky={int:value} WHERE id_topic={int:topic} AND id_board={int:board}',array('value'=>$action==='pin'?1:0,'topic'=>$topic,'board'=>$board));
 elseif ($action==='move')
 {
  $dest=(int)$row['target_board'];
  if (!BTT_target($dest)) throw new RuntimeException('Destination board is unavailable');
  require_once($sourcedir.'/MoveTopic.php');
  BTT_native_action(function() use ($topic,$dest) { moveTopics(array($topic),$dest); });
  $after=BTT_topic($topic);
  if (!$after || (int)$after['id_board']!==$dest) throw new RuntimeException('Move did not reach destination; inspect topic before retrying');
 }
 elseif ($action==='delete')
 {
  if (empty($modSettings['btt_delete_enabled'])) throw new RuntimeException('Timed deletion disabled by administrator');
  require_once($sourcedir.'/RemoveTopic.php');
  BTT_native_action(function() use ($topic) { removeTopics(array($topic),true,false,true); });
  if (!BTT_action_already_applied($row,BTT_topic($topic))) throw new RuntimeException('Removal did not complete; inspect topic before retrying');
 }
 else throw new RuntimeException('Unknown action');
 return true;
}
function BTT_acquire_lock($wait=0)
{
 global $smcFunc,$db_prefix,$db_name;
 $lock='btt_'.sha1($db_name.':'.$db_prefix);
 $r=$smcFunc['db_query']('', 'SELECT GET_LOCK({string:name}, {int:wait}) AS acquired',array('name'=>$lock,'wait'=>(int)$wait));
 $got=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
 return !empty($got['acquired']) ? $lock : false;
}
function BTT_release_lock($lock)
{
 global $smcFunc;
 $r=$smcFunc['db_query']('', 'SELECT RELEASE_LOCK({string:name})',array('name'=>$lock));
 $smcFunc['db_free_result']($r);
}
function BTT_retry_failed($id,$topic=0)
{
 global $smcFunc;
 $lock=BTT_acquire_lock(3);
 if (!$lock) fatal_error('Topic Timers is busy. Please try again.',false);
 $error='';
 try {
  $r=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}btt_timers WHERE id_timer={int:id} AND status={string:failed}',array('id'=>$id,'failed'=>'failed'));
  $row=$smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
  if (!$row || ($topic && (int)$row['id_topic']!==$topic)) $error='Failed timer not available.';
  else {
   $r=$smcFunc['db_query']('', 'SELECT id_timer FROM {db_prefix}btt_timers WHERE id_topic={int:topic} AND execute_at={int:due} AND status IN ({array_string:active}) LIMIT 1',array('topic'=>(int)$row['id_topic'],'due'=>(int)$row['execute_at'],'active'=>array('pending','processing')));
   $conflict=$smcFunc['db_num_rows']($r)>0; $smcFunc['db_free_result']($r);
   if ($conflict) $error='Another active timer already uses this time.';
   else $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:pending},attempts={int:zero},claimed_at={int:zero},retry_at={int:now},error_message={string:empty} WHERE id_timer={int:id} AND status={string:failed}',array('pending'=>'pending','zero'=>0,'now'=>time(),'empty'=>'','id'=>$id,'failed'=>'failed'));
  }
 } finally { BTT_release_lock($lock); }
 if ($error!=='') fatal_error($error,false);
}
function scheduled_btt_process()
{
 global $smcFunc,$modSettings;
 if (empty($modSettings['btt_enabled'])) return true;
 $now=time();
 $lock=BTT_acquire_lock();
 if (!$lock) return true;
 try {
 // Interrupted jobs require explicit review; their side effects may already exist.
 $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:failed},error_message={string:reason} WHERE status={string:processing} AND claimed_at < {int:expiry}',array('failed'=>'failed','processing'=>'processing','reason'=>'Interrupted execution; inspect topic before retrying','expiry'=>$now-900));
 $r=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}btt_timers WHERE status={string:pending} AND execute_at<={int:now} AND retry_at<={int:now} ORDER BY execute_at,id_timer LIMIT 20',array('pending'=>'pending','now'=>$now));
 $items=array();while($row=$smcFunc['db_fetch_assoc']($r))$items[]=$row;$smcFunc['db_free_result']($r);
 foreach($items as $row)
 {
  $id=(int)$row['id_timer'];
  $prior=$smcFunc['db_query']('', 'SELECT id_timer FROM {db_prefix}btt_timers WHERE id_topic={int:topic} AND status IN ({array_string:active}) AND (execute_at < {int:due} OR (execute_at={int:due} AND id_timer < {int:id})) LIMIT 1',array('topic'=>(int)$row['id_topic'],'active'=>array('pending','processing'),'due'=>(int)$row['execute_at'],'id'=>$id));
  $blocked=$smcFunc['db_num_rows']($prior)>0; $smcFunc['db_free_result']($prior);
  if ($blocked) continue;
  // Claim is atomic, with a status and retry timestamp guard.
  $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:processing},claimed_at={int:now},attempts=attempts+1 WHERE id_timer={int:id} AND status={string:pending} AND retry_at<={int:now}',array('processing'=>'processing','now'=>time(),'id'=>$id,'pending'=>'pending'));
  if (!$smcFunc['db_affected_rows']()) continue;
  try
  {
   $permit=true;
   call_integration_hook('integrate_btt_before_execute',array(&$row,&$permit));
   if (!$permit) throw new RuntimeException('An integration vetoed this timer');
   BTT_execute($row);
   $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:done},completed_at={int:now},error_message={string:empty} WHERE id_timer={int:id} AND status={string:processing}',array('done'=>'done','now'=>time(),'empty'=>'','id'=>$id,'processing'=>'processing'));
   // Observer failures must never make a successfully executed action retry.
   try { call_integration_hook('integrate_btt_executed',array($row)); }
   catch (Throwable $observer_error) { log_error('Topic Timers observer error: '.$observer_error->getMessage(),'general'); }
  }
  catch (Throwable $e)
  {
   $tries=(int)$row['attempts']+1;
   $status=in_array($row['action'],array('move','delete'),true) ? 'failed' : BTT_failure_status($tries);
   // Permission and changed-board failures require human intervention.
   if (strpos($e->getMessage(),'permission')!==false || strpos($e->getMessage(),'board changed')!==false || strpos($e->getMessage(),'no longer has')!==false || strpos($e->getMessage(),'no longer exists')!==false || strpos($e->getMessage(),'vetoed')!==false) $status='failed';
   $delay=min(3600,60*(1<<min($tries,5)));
   $smcFunc['db_query']('', 'UPDATE {db_prefix}btt_timers SET status={string:status},retry_at={int:next},error_message={string:error} WHERE id_timer={int:id} AND status={string:processing}',array('status'=>$status,'next'=>time()+$delay,'error'=>substr($e->getMessage(),0,250),'id'=>$id,'processing'=>'processing'));
  }
 }
 return true;
 } finally {
  BTT_release_lock($lock);
 }
}
