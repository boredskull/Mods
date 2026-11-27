<?php
/** Enhanced Reactions 1.0.10, BoredSkull. ISC licence. SMF 2.1.x. */
if (!defined('BSR_VERSION')) define('BSR_VERSION', '1.0.10');
if (!defined('SMF')) die('No direct access');
require_once __DIR__ . '/BsReactionsEcosystem.php';
require_once __DIR__ . '/BsReactionsStandalone.php';

function bsr_catalog()
{
    $items = array(
        'like'=>array('emoji'=>'👍','label'=>'Like'), 'love'=>array('emoji'=>'❤️','label'=>'Love'),
        'laugh'=>array('emoji'=>'😂','label'=>'Laugh'), 'wow'=>array('emoji'=>'😮','label'=>'Wow'),
        'sad'=>array('emoji'=>'😢','label'=>'Sad'), 'angry'=>array('emoji'=>'😠','label'=>'Angry'),
        'helpful'=>array('emoji'=>'💡','label'=>'Helpful'), 'thanks'=>array('emoji'=>'🙏','label'=>'Thanks'),
    );
    call_integration_hook('integrate_bs_reactions_catalog', array(&$items));
    foreach ($items as $id=>$item) {
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{0,23}$/D', $id) || !is_array($item)
            || !isset($item['emoji'], $item['label']) || !is_string($item['emoji']) || !is_string($item['label'])
            || $item['label'] === '' || strlen($item['label']) > 120 || strlen($item['emoji']) > 40)
            unset($items[$id]);
    }
    return $items;
}
function bsr_enabled()
{
    global $modSettings;
    $items = bsr_catalog();
    $enabled = isset($modSettings['bsr_enabled_types']) ? explode(',', (string)$modSettings['bsr_enabled_types']) : array_keys($items);
    return array_intersect_key($items, array_flip(array_map('trim',$enabled)));
}
function bsr_board_enabled($board)
{
    global $modSettings;
    if (empty($modSettings['bsr_enabled'])) return false;
    $disabled = array_map('intval', explode(',', isset($modSettings['bsr_disabled_boards']) ? $modSettings['bsr_disabled_boards'] : ''));
    return !in_array((int)$board, $disabled, true);
}
function bsr_actions(&$actions) { $actions['bsreactions']=array('BsReactions.php','bsr_route'); }
function bsr_admin_areas(&$areas)
{
    global $txt;
    loadLanguage('BsReactions');
    if (isset($areas['config']['areas']['modsettings']))
        $areas['config']['areas']['modsettings']['subsections']['bsreactions']=array($txt['bsr_settings']);
}
function bsr_modify_modifications(&$subActions)
{
    global $context,$txt;
    loadLanguage('BsReactions');
    $subActions['bsreactions']='bsr_settings';
    if (isset($context[$context['admin_menu_name']]['tab_data']['tabs']))
        $context[$context['admin_menu_name']]['tab_data']['tabs']['bsreactions']=array('description'=>$txt['bsr_settings_desc']);
}
function bsr_settings_config()
{
    global $txt;
    loadLanguage('BsReactions');
    return array(
        array('check','bsr_enabled','subtext'=>$txt['bsr_enabled_help']),
        array('text','bsr_enabled_types','subtext'=>$txt['bsr_enabled_types_help'],'size'=>55),
        array('text','bsr_disabled_boards','subtext'=>$txt['bsr_disabled_boards_help'],'size'=>55),
        array('int','bsr_rate_limit','subtext'=>$txt['bsr_rate_limit_help'],'min'=>0,'max'=>120),
    );
}
function bsr_settings($return_config=false)
{
    global $context,$txt,$scripturl;
    isAllowedTo('admin_forum');
    $config=bsr_settings_config();
    if ($return_config) return $config;
    $context['post_url']=$scripturl.'?action=admin;area=modsettings;sa=bsreactions;save';
    $context['settings_title']=$txt['bsr_settings'];
    if (isset($_GET['save'])) {
        checkSession('post'); saveDBSettings($config); $_SESSION['adm-save']=true;
        redirectexit('action=admin;area=modsettings;sa=bsreactions');
    }
    prepareDBSettingContext($config);
}
function bsr_theme() { /* Assets load when messages are prepared. */ }
function bsr_assets()
{
    static $loaded=false;
    if ($loaded) return;
    $loaded=true;
    loadLanguage('BsReactions');
    // The version seed makes browsers fetch the new files after an upgrade.
    loadCSSFile('bs-reactions.css',array('minimize'=>true,'seed'=>BSR_VERSION),'smf_bs_reactions');
    loadJavaScriptFile('bs-reactions.js',array('defer'=>true,'minimize'=>true,'seed'=>BSR_VERSION),'smf_bs_reactions');
}
function bsr_prefetch(&$messages,&$posters) { return bsr_v107_prefetch($messages,$posters); }
function bsr_summary($counts,$catalog)
{
    $out=array();
    foreach ($catalog as $id=>$item) if (!empty($counts[$id])) $out[]=htmlspecialchars($item['emoji'],ENT_QUOTES,'UTF-8').' '.(int)$counts[$id];
    foreach ($counts as $id=>$number) if (!isset($catalog[$id])) $out[]=htmlspecialchars((string)$id,ENT_QUOTES,'UTF-8').' '.(int)$number;
    global $txt;
    return $out ? implode(' · ', $out) : (isset($txt['bsr_none']) ? $txt['bsr_none'] : '0 reactions');
}
function bsr_credits()
{
    global $context,$txt;
    loadLanguage('BsReactions');
    $context['copyrights']['mods'][]=sprintf($txt['bsr_credits'],BSR_VERSION);
}
/** Members being deleted: their reactions go too, so counts match the "See reactions" list. */
function bsr_delete_members($users)
{
    global $smcFunc;
    $users=array_values(array_filter(array_map('intval',(array)$users)));
    if (!$users) return;
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}bs_reaction_items WHERE id_member IN ({array_int:users})',array('users'=>$users));
}
function bsr_prepare_post(&$output,&$message,$counter)
{
    global $context,$board,$user_info,$scripturl,$txt;
    if (!bsr_board_enabled($board)) return;
    bsr_assets();
    $id=(int)$output['id'];
    $counts=isset($context['bsr_rows'][$id]['counts']) ? $context['bsr_rows'][$id]['counts'] : array();
    $mine=isset($context['bsr_rows'][$id]['mine']) ? $context['bsr_rows'][$id]['mine'] : '';
    $catalog=bsr_enabled();
    // Suppress SMF Like UI on the server, preventing any Like->reaction flash.
    if (isset($output['likes'])) $output['likes']=array('count'=>0,'you'=>false,'can_like'=>false);
    $isMine=(int)$message['id_member'] > 0 && (int)$message['id_member'] === (int)$user_info['id'];
    $can=!empty($user_info['id']) && empty($user_info['is_guest']) && !$isMine && !empty($catalog)
        && isset($context['bsr_access_rows'][$id]) && bsr_access($context['bsr_access_rows'][$id], true);
    $esc=function($s) {return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');};
    $html='<div class="bsr-reactions" data-msg="'.$id.'" data-endpoint="'.$esc($scripturl.'?action=bsreactions;sa=react').'" data-view-endpoint="'.$esc($scripturl.'?action=bsreactions;sa=view;msg='.$id).'" data-session-var="'.$esc($context['session_var']).'" data-session-id="'.$esc($context['session_id']).'" data-default-label="'.$esc($txt['bsr_react']).'">';
    if ($can) {
        $label=isset($catalog[$mine]) ? $catalog[$mine]['emoji'].' '.$catalog[$mine]['label'] : $txt['bsr_react'];
        $html.='<button type="button" class="bsr-open" aria-expanded="false" aria-controls="bsr-picker-'.$id.'">'.$esc($label).'</button>';
        $html.='<span class="bsr-picker" id="bsr-picker-'.$id.'" hidden role="group" aria-label="'.$esc($txt['bsr_choose']).'">';
        foreach ($catalog as $type=>$item)
            $html.='<button type="button" data-reaction="'.$esc($type).'" aria-pressed="'.($type===$mine?'true':'false').'" title="'.$esc($item['label']).'">'.$esc($item['emoji'].' '.$item['label']).'</button>';
        $html.='<button type="button" data-reaction="" aria-pressed="false">'.$esc($txt['bsr_remove']).'</button></span>';
    }
    $html.='<span class="bsr-summary" aria-live="polite">'.bsr_summary($counts,$catalog).'</span>';
    if (empty($user_info['is_guest'])) $html.='<button type="button" class="bsr-view">'.$esc($txt['bsr_view']).'</button>';
    $html.='<span class="bsr-status" role="status" aria-live="polite"></span>';
    $html.='<dialog class="bsr-dialog" aria-label="'.$esc($txt['bsr_members']).'"><h3>'.$esc($txt['bsr_members']).'</h3><div class="bsr-dialog-content"></div><button type="button" class="bsr-close">'.$esc($txt['bsr_close']).'</button></dialog></div>';
    $output['quickbuttons']['bsr']=array('content'=>$html,'show'=>true,'class'=>'bsr-list-item');
}
function bsr_fetch_message($id)
{
    global $smcFunc;
    $q=$smcFunc['db_query']('', 'SELECT m.id_msg,m.id_member,m.id_board,m.id_topic,m.approved,m.likes,t.locked,t.approved AS topic_approved
        FROM {db_prefix}messages AS m INNER JOIN {db_prefix}topics AS t ON t.id_topic=m.id_topic
        WHERE m.id_msg={int:id} LIMIT 1',array('id'=>(int)$id));
    $row=$smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    return $row ?: false;
}
function bsr_can_see_board($board)
{
    global $smcFunc;
    static $cache = array();
    $board = (int) $board;
    if ($board < 1) return false;
    if (isset($cache[$board])) return $cache[$board];
    // This token expands to SMF's membergroup, board and access restrictions.
    $request = $smcFunc['db_query']('', '
        SELECT b.id_board FROM {db_prefix}boards AS b
        WHERE b.id_board = {int:board} AND {query_see_board}
        LIMIT 1', array('board' => $board));
    $cache[$board] = (bool) $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);
    return $cache[$board];
}
function bsr_access($row,$writing=false)
{
    global $user_info;
    if (!$row || !bsr_board_enabled($row['id_board'])) return false;
    $board=(int)$row['id_board'];
    // Access to a target post is checked against the *actual* destination board.
    // SMF 2.1 board visibility is not a view_board board permission.
    // Use SMF's own query_see_board restriction, scoped to the target board.
    if (!bsr_can_see_board($board)) return false;
    if (empty($row['topic_approved']) && !allowedTo('approve_posts',$board)) return false;
    if (empty($row['approved']) && !allowedTo('approve_posts',$board) && (int)$row['id_member'] !== (int)$user_info['id']) return false;
    if (!$writing) return true;
    if (!empty($user_info['is_guest']) || !allowedTo('bsr_react') || (int)$row['id_member']===(int)$user_info['id']) return false;
    if ((int)$row['locked'] && !allowedTo('moderate_board',$board) && !allowedTo('moderate_forum')) return false;
    return true;
}
function bsr_count($id) {return bsr_v107_count($id);}
function bsr_json($data,$code=200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function bsr_route() {return bsr_v107_route();}
