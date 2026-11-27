<?php
/**
 * Enhanced Reactions 1.0.10: independent post reactions.
 * Copyright (c) 2026 BoredSkull. ISC licensed.
 */
if (!defined('SMF')) die('No direct access');

function bsr_v107_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
    global $txt;
    loadLanguage('BsReactions');
    $txt['permissionname_bsr_react'] = isset($txt['permissionname_bsr_react']) ? $txt['permissionname_bsr_react'] : 'React to posts';
    $permissionList['membergroup']['bsr_react'] = array(false, 'general');
}

function bsr_v107_prefetch(&$messages, &$posters)
{
    global $context, $smcFunc, $board, $user_info;
    if (!$messages || !bsr_board_enabled($board)) return;
    bsr_assets();
    $context['bsr_rows'] = array();
    $context['bsr_access_rows'] = array();
    $ids = array_values(array_filter(array_map('intval', (array)$messages), function($n){return $n > 0;}));
    if (!$ids) return;
    // The picker must enforce the same topic, approval and locking rules
    // as the POST endpoint, without one message-fetch query per post.
    $access_request = $smcFunc['db_query']('', '
        SELECT m.id_msg, m.id_member, m.id_board, m.id_topic, m.approved,
            t.locked, t.approved AS topic_approved
        FROM {db_prefix}messages AS m
        INNER JOIN {db_prefix}topics AS t ON t.id_topic = m.id_topic
        WHERE m.id_msg IN ({array_int:ids})', array('ids' => $ids));
    while ($access_row = $smcFunc['db_fetch_assoc']($access_request))
        $context['bsr_access_rows'][(int) $access_row['id_msg']] = $access_row;
    $smcFunc['db_free_result']($access_request);
    $request = $smcFunc['db_query']('', '
        SELECT content_id, id_member, reaction
        FROM {db_prefix}bs_reaction_items
        WHERE content_type = {string:type} AND content_id IN ({array_int:ids})',
        array('type'=>'msg', 'ids'=>$ids));
    while ($r = $smcFunc['db_fetch_assoc']($request)) {
        $id = (int)$r['content_id']; $type = (string)$r['reaction'];
        if (!isset($context['bsr_rows'][$id])) $context['bsr_rows'][$id] = array('counts'=>array());
        $context['bsr_rows'][$id]['counts'][$type] = 1 + (isset($context['bsr_rows'][$id]['counts'][$type]) ? $context['bsr_rows'][$id]['counts'][$type] : 0);
        if ((int)$r['id_member'] === (int)$user_info['id']) $context['bsr_rows'][$id]['mine'] = $type;
    }
    $smcFunc['db_free_result']($request);
}

function bsr_v107_count($id)
{
    global $smcFunc;
    $result = $smcFunc['db_query']('', '
        SELECT reaction, COUNT(*) AS amount FROM {db_prefix}bs_reaction_items
        WHERE content_type={string:type} AND content_id={int:id}
        GROUP BY reaction', array('type'=>'msg','id'=>(int)$id));
    $counts = array();
    while ($r = $smcFunc['db_fetch_assoc']($result)) $counts[$r['reaction']] = (int)$r['amount'];
    $smcFunc['db_free_result']($result);
    return $counts;
}

function bsr_v107_route()
{
    global $smcFunc, $user_info, $modSettings, $scripturl;
    loadLanguage('BsReactions');
    $action = isset($_REQUEST['sa']) && is_string($_REQUEST['sa']) ? $_REQUEST['sa'] : '';
    if ($action === 'react_target') return bsr_route_v106_target();
    $id = isset($_REQUEST['msg']) && is_scalar($_REQUEST['msg']) && preg_match('/^[1-9][0-9]*$/D',(string)$_REQUEST['msg']) ? (int)$_REQUEST['msg'] : 0;
    $row = $id > 0 ? bsr_fetch_message($id) : false;
    if ($action === 'view') {
        if (!empty($user_info['is_guest']) || !bsr_access($row)) bsr_json(array('ok'=>false,'error'=>'Not available'),403);
        $result = $smcFunc['db_query']('', '
            SELECT ri.id_member, mem.real_name, ri.reaction
            FROM {db_prefix}bs_reaction_items AS ri
            INNER JOIN {db_prefix}members AS mem ON mem.id_member=ri.id_member
            WHERE ri.content_type={string:type} AND ri.content_id={int:id}
            ORDER BY ri.changed_at DESC LIMIT 100', array('type'=>'msg','id'=>$id));
        $catalog=bsr_catalog(); $members=array();
        while ($m=$smcFunc['db_fetch_assoc']($result)) {
            $type=$m['reaction'];
            // SMF stores names HTML-escaped; the script shows them as plain text.
            $members[]=array('name'=>un_htmlspecialchars($m['real_name']), 'url'=>$scripturl.'?action=profile;u='.(int)$m['id_member'],
                'reaction_label'=>isset($catalog[$type]) ? $catalog[$type]['label'] : $type);
        }
        $smcFunc['db_free_result']($result);
        bsr_json(array('ok'=>true,'members'=>$members));
    }
    if ($action !== 'react') bsr_json(array('ok'=>false,'error'=>'Invalid request'),405);
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') bsr_json(array('ok'=>false,'error'=>'POST required'),405);
    if (checkSession('post', '', false) !== '') bsr_json(array('ok'=>false,'error'=>'Session expired; refresh the page'),403);
    if (!bsr_access($row,true)) bsr_json(array('ok'=>false,'error'=>'Not permitted'),403);
    $catalog = bsr_enabled();
    if (!isset($_POST['reaction']) || !is_string($_POST['reaction']))
        bsr_json(array('ok'=>false,'error'=>'Invalid reaction input'),400);
    $new = $_POST['reaction'];
    if ($new !== '' && !isset($catalog[$new])) bsr_json(array('ok'=>false,'error'=>'Invalid reaction'),400);
    $member = (int)$user_info['id'];
    $result = $smcFunc['db_query']('', '
        SELECT reaction FROM {db_prefix}bs_reaction_items
        WHERE content_type={string:type} AND content_id={int:id} AND id_member={int:member} LIMIT 1',
        array('type'=>'msg','id'=>$id,'member'=>$member));
    $oldrow = $smcFunc['db_fetch_assoc']($result);
    $smcFunc['db_free_result']($result);
    $old = $oldrow ? $oldrow['reaction'] : '';
    if ($old !== $new) {
        $rate=isset($modSettings['bsr_rate_limit']) ? max(0,(int)$modSettings['bsr_rate_limit']) : 2;
        if ($rate) {
            $recent=$smcFunc['db_query']('', '
                SELECT changed_at FROM {db_prefix}bs_reaction_events
                WHERE content_type={string:type} AND id_member={int:member}
                    AND content_id != {int:current_id}
                ORDER BY id_event DESC LIMIT 1',array('type'=>'msg','member'=>$member,'current_id'=>$id));
            $last=$smcFunc['db_fetch_assoc']($recent); $smcFunc['db_free_result']($recent);
            if ($last && time()-(int)$last['changed_at'] < $rate)
                bsr_json(array('ok'=>false,'error'=>'Please wait before changing your reaction'),429);
        }
        // No native SMF Like API is used. Atomic one-member/one-post key.
        if ($new === '') $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}bs_reaction_items
            WHERE content_type={string:type} AND content_id={int:id} AND id_member={int:member}',
            array('type'=>'msg','id'=>$id,'member'=>$member));
        else $smcFunc['db_insert']('replace','{db_prefix}bs_reaction_items',
            array('content_type'=>'string-24','content_id'=>'int','id_member'=>'int','reaction'=>'string-24','changed_at'=>'int'),
            array('msg',$id,$member,$new,time()),array('content_type','content_id','id_member'));
        // An audit failure must not make an already-saved reaction look unsuccessful.
        try {
            $smcFunc['db_insert']('insert','{db_prefix}bs_reaction_events',
                array('content_type'=>'string-24','content_id'=>'int','id_member'=>'int','old_reaction'=>'string-24','new_reaction'=>'string-24','changed_at'=>'int'),
                array('msg',$id,$member,$old,$new,time()),array('id_event'));
            // Maintain backwards compatibility with Activity Stream's existing audit feed.
            $smcFunc['db_insert']('insert','{db_prefix}bs_reaction_audit',
                array('content_id'=>'int','id_member'=>'int','old_reaction'=>'string-24','new_reaction'=>'string-24','changed_at'=>'int'),
                array($id,$member,$old,$new,time()),array('id_event'));
        } catch (Throwable $e) { error_log('Enhanced Reactions audit write failed: '.$e->getMessage()); }
        try {call_integration_hook('integrate_bs_reactions_changed',array($id,$member,$old,$new,(int)$row['id_board'],(int)$row['id_topic']));}
        catch (Throwable $e) {error_log('Enhanced Reactions optional integration failed: '.$e->getMessage());}
    }
    $counts=bsr_v107_count($id);
    bsr_json(array('ok'=>true,'mine'=>$new,'mine_label'=>$new !== '' ? $catalog[$new]['emoji'].' '.$catalog[$new]['label'] : '',
        'counts'=>$counts,'summary'=>bsr_summary($counts,$catalog)));
}

// Compatible with the 1.0.6 handler for non-post external content.
function bsr_route_v106_target()
{
    global $user_info;
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') bsr_json(array('ok'=>false,'error'=>'POST required'),405);
    if (checkSession('post','',false) !== '') bsr_json(array('ok'=>false,'error'=>'Session expired'),403);
    $type = isset($_POST['target_type']) && is_string($_POST['target_type']) ? $_POST['target_type'] : '';
    $id = isset($_POST['target_id']) && is_scalar($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
    $reaction = isset($_POST['reaction']) && is_string($_POST['reaction']) ? $_POST['reaction'] : '';
    if (!bsr_react_to_target($type,$id,$reaction,(int)$user_info['id']))
        bsr_json(array('ok'=>false,'error'=>'Reaction not permitted or not saved'),403);
    $counts=bsr_reaction_counts($type,$id);
    bsr_json(array('ok'=>true,'mine'=>$reaction,'counts'=>$counts,'summary'=>bsr_summary($counts,bsr_catalog())));
}
