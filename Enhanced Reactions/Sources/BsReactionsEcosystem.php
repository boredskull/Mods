<?php
/** Optional Enhanced Reactions extension API, 1.0.10. ISC. */
if (!defined('SMF')) die('No direct access');
/**
 * Companion mods may register a target with an access callback.
 * Callback signature: function ($contentId, $writing): bool.
 * Never register a target that the caller cannot permission-check.
 */
function bsr_target_registry()
{
    $targets=array();
    call_integration_hook('integrate_bs_reactions_targets',array(&$targets));
    foreach ($targets as $key=>$target) {
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,23}$/D',$key) || $key==='msg'
            || !is_array($target) || !isset($target['can_view'],$target['can_react'],$target['owner'],$target['url'],$target['title'])
            || !is_callable($target['can_view']) || !is_callable($target['can_react']) || !is_callable($target['owner'])
            || !is_callable($target['url']) || !is_callable($target['title'])) unset($targets[$key]);
    }
    return $targets;
}
function bsr_target_access($type,$id,$write=false,$viewer=null)
{
    global $user_info;
    $id=(int)$id;
    if ($id<1 || !is_string($type) || empty($GLOBALS['modSettings']['bsr_enabled'])) return false;
    $viewer=$viewer===null?(int)$user_info['id']:(int)$viewer;
    if ($type==='msg') {
        if ($viewer!==(int)$user_info['id']) return false;
        return bsr_access(bsr_fetch_message($id),$write);
    }
    $targets=bsr_target_registry();
    if (!isset($targets[$type])) return false;
    $t=$targets[$type];
    try {
        if (!call_user_func($t['can_view'],$id,$viewer)) return false;
        if (!$write) return true;
        if ($viewer<=0 || !allowedTo('bsr_react') || (int)call_user_func($t['owner'],$id)===$viewer) return false;
        return (bool)call_user_func($t['can_react'],$id,$viewer);
    } catch (Throwable $e) {error_log('Enhanced Reactions target check failed: '.$e->getMessage());return false;}
}
function bsr_reaction_counts($type,$id)
{
    global $smcFunc;
    if (!bsr_target_access($type,$id)) return array();
    if ($type==='msg') return bsr_count($id);
    $q=$smcFunc['db_query']('', 'SELECT reaction,COUNT(*) AS amount FROM {db_prefix}bs_reaction_items
        WHERE content_type={string:type} AND content_id={int:id} GROUP BY reaction',array('type'=>$type,'id'=>(int)$id));
    $counts=array();
    while ($r=$smcFunc['db_fetch_assoc']($q)) $counts[$r['reaction']]=(int)$r['amount'];
    $smcFunc['db_free_result']($q);
    return $counts;
}
function bsr_react_to_target($type,$id,$reaction,$actor)
{
    global $smcFunc,$user_info,$modSettings;
    $actor=(int)$actor; $id=(int)$id;
    if ($actor<=0 || $actor!==(int)$user_info['id'] || !is_string($type) || !is_string($reaction)
        || $type==='msg' || !bsr_target_access($type,$id,true)) return false;
    $catalog=bsr_enabled();
    if ($reaction!=='' && !isset($catalog[$reaction])) return false;
    $q=$smcFunc['db_query']('', 'SELECT reaction FROM {db_prefix}bs_reaction_items
        WHERE content_type={string:type} AND content_id={int:id} AND id_member={int:actor} LIMIT 1',
        array('type'=>$type,'id'=>$id,'actor'=>$actor));
    $record=$smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    $old=$record ? $record['reaction'] : '';
    if ($old===$reaction) return true;
    if ($reaction==='') $smcFunc['db_query']('', 'DELETE FROM {db_prefix}bs_reaction_items WHERE content_type={string:type}
        AND content_id={int:id} AND id_member={int:actor}',array('type'=>$type,'id'=>$id,'actor'=>$actor));
    else $smcFunc['db_insert']('replace','{db_prefix}bs_reaction_items',
        array('content_type'=>'string-24','content_id'=>'int','id_member'=>'int','reaction'=>'string-24','changed_at'=>'int'),
        array($type,$id,$actor,$reaction,time()),array('content_type','content_id','id_member'));
    try {
        $smcFunc['db_insert']('insert','{db_prefix}bs_reaction_events',
            array('content_type'=>'string-24','content_id'=>'int','id_member'=>'int','old_reaction'=>'string-24','new_reaction'=>'string-24','changed_at'=>'int'),
            array($type,$id,$actor,$old,$reaction,time()),array('id_event'));
        call_integration_hook('integrate_bs_reactions_content_changed',array($type,$id,$actor,$old,$reaction));
    } catch (Throwable $e) {error_log('Enhanced Reactions ecosystem audit failed: '.$e->getMessage());}
    return true;
}
/** Permission-filtered most-reacted post IDs, used by Skull Portal and Insights. */
function bsr_insights_top_posts($limit=10,$since=0)
{
    global $smcFunc;
    $limit=max(1,min(50,(int)$limit));$since=max(0,(int)$since);
    $q=$smcFunc['db_query']('', 'SELECT content_id,COUNT(*) AS total FROM {db_prefix}bs_reaction_items
        WHERE content_type={string:type} AND changed_at>={int:since}
        GROUP BY content_id ORDER BY total DESC LIMIT 250',array('type'=>'msg','since'=>$since));
    $results=array();
    while($row=$smcFunc['db_fetch_assoc']($q)) {
        $id=(int)$row['content_id'];
        if (bsr_target_access('msg',$id)) $results[]=array('type'=>'msg','id'=>$id,'total'=>(int)$row['total']);
        if(count($results)>=$limit) break;
    }
    $smcFunc['db_free_result']($q);
    return $results;
}
function bsr_activity_groups($since,$limit=20)
{
    global $smcFunc;
    $limit=max(1,min(50,(int)$limit));$since=max(0,(int)$since);
    $q=$smcFunc['db_query']('', 'SELECT content_id,COUNT(*) AS changes,MAX(changed_at) AS latest
        FROM {db_prefix}bs_reaction_audit WHERE changed_at>={int:since}
        GROUP BY content_id ORDER BY latest DESC LIMIT 250',array('since'=>$since));
    $rows=array();
    while($row=$smcFunc['db_fetch_assoc']($q)) {
        $id=(int)$row['content_id'];
        if (!bsr_target_access('msg',$id)) continue;
        $rows[]=array('type'=>'msg','id'=>$id,'recent_changes'=>(int)$row['changes'],
            'latest'=>(int)$row['latest'],'current_total'=>array_sum(bsr_count($id)));
        if(count($rows)>=$limit) break;
    }
    $smcFunc['db_free_result']($q);
    return $rows;
}
function bsr_portal_top_posts($limit=5,$since=0)
{
    global $scripturl;
    $html='<ul class="bsr-portal-top">';
    foreach(bsr_insights_top_posts($limit,$since) as $row) {
        $id=(int)$row['id'];
        $html.='<li><a href="'.htmlspecialchars($scripturl.'?msg='.$id,ENT_QUOTES,'UTF-8').'">Post #'.$id.'</a> ('.(int)$row['total'].' reactions)</li>';
    }
    return $html.'</ul>';
}
function bsr_content_summary_html($type,$id)
{
    if (!bsr_target_access($type,$id)) return '';
    return '<span class="bsr-content-summary">'.bsr_summary(bsr_reaction_counts($type,$id),bsr_catalog()).'</span>';
}
function bsr_stream_types(&$types)
{
    if (empty($GLOBALS['modSettings']['bsr_enabled'])) return;
    $types['bsr_reactions']='Reaction activity';
}
function bsr_stream_fetch(&$items,$config,$before,$limit)
{
    global $scripturl;
    if (empty($GLOBALS['modSettings']['bsr_enabled']) || empty($config['types']) || !in_array('bsr_reactions',$config['types'],true)) return;
    if (isset($config['members']) && $config['members']!=='all') return;
    if (isset($config['own']) && !$config['own']) return;
    $since=max(0,(int)$before-30*86400);
    foreach(bsr_activity_groups($since,min(20,(int)$limit)) as $g) {
        if ((int)$g['latest']>=(int)$before) continue;
        $id=(int)$g['id'];$row=bsr_fetch_message($id);
        if (!$row) continue;
        if (!empty($config['boards']) && !in_array((int)$row['id_board'],$config['boards'],true)) continue;
        if (class_exists('ActivityStream',false) && method_exists('ActivityStream','excludedBoards') &&
            in_array((int)$row['id_board'],ActivityStream::excludedBoards(),true)) continue;
        $html='<a href="'.htmlspecialchars($scripturl.'?msg='.$id,ENT_QUOTES,'UTF-8').'">Post #'.$id.'</a> has '.(int)$g['current_total'].' reactions';
        $items[]=array('type'=>'bsr_reactions','time'=>(int)$g['latest'],'key'=>'bsr-'.$id.'-'.(int)$g['latest'],
            'actor'=>0,'html'=>$html,'snippet'=>'');
    }
}
function bsr_insight_provider(&$providers,$dates)
{
    global $smcFunc;
    if (!allowedTo('admin_forum') || empty($GLOBALS['modSettings']['bsr_enabled'])) return;
    $q=$smcFunc['db_query']('', 'SELECT COUNT(*) AS total FROM {db_prefix}bs_reaction_audit',array());
    $a=$smcFunc['db_fetch_assoc']($q);$smcFunc['db_free_result']($q);
    $q=$smcFunc['db_query']('', 'SELECT COUNT(*) AS total FROM {db_prefix}bs_reaction_items',array());
    $b=$smcFunc['db_fetch_assoc']($q);$smcFunc['db_free_result']($q);
    $providers[]=array('id'=>'bsr_engagement','title'=>'Enhanced Reactions','permission'=>'admin_forum','items'=>array(
        'Post reaction changes (all time)'=>(int)$a['total'],
        'Active reactions on registered content'=>(int)$b['total']));
}
function bsr_target_picker_html($type,$id)
{
    global $context,$scripturl,$user_info,$smcFunc;
    $id=(int)$id;
    if (!is_string($type) || $type==='msg' || !bsr_target_access($type,$id)) return '';
    $counts=bsr_reaction_counts($type,$id);$catalog=bsr_enabled();
    $can=bsr_target_access($type,$id,true) && !empty($catalog);
    $mine='';
    if (!empty($user_info['id'])) {
        $q=$smcFunc['db_query']('', 'SELECT reaction FROM {db_prefix}bs_reaction_items WHERE content_type={string:type}
            AND content_id={int:id} AND id_member={int:member}',array('type'=>$type,'id'=>$id,'member'=>(int)$user_info['id']));
        $row=$smcFunc['db_fetch_assoc']($q);$smcFunc['db_free_result']($q);
        if ($row) $mine=$row['reaction'];
    }
    bsr_assets();
    $escape=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
    $html='<div class="bsr-target-reactions" data-type="'.$escape($type).'" data-target-id="'.$id.'" data-endpoint="'.
        $escape($scripturl.'?action=bsreactions;sa=react_target').'" data-session-var="'.$escape($context['session_var']).
        '" data-session-id="'.$escape($context['session_id']).'">';
    $html.='<span class="bsr-summary" aria-live="polite">'.bsr_summary($counts,$catalog).'</span>';
    if ($can) {
        $html.='<fieldset><legend>React</legend>';
        foreach($catalog as $key=>$info)
            $html.='<button type="button" data-reaction="'.$escape($key).'" aria-pressed="'.($key===$mine?'true':'false').'">'.$escape($info['emoji'].' '.$info['label']).'</button> ';
        $html.='<button type="button" data-reaction="">Remove</button></fieldset><span role="status" class="bsr-status" aria-live="polite"></span>';
    }
    return $html.'</div>';
}
