<?php
/** Admin and member-facing configuration. All actions require permissions + CSRF. */
if (!defined('SMF')) die('No direct access');

function federation_admin_page()
{
    global $context, $txt, $smcFunc, $modSettings, $sourcedir, $scripturl;
    isAllowedTo('admin_forum');
    loadLanguage('Federation'); loadTemplate('Federation');
    loadCSSFile('federation.css', array('default_theme' => true, 'minimize' => true, 'seed' => FEDERATION_VERSION . '-ui2'), 'federation_css');
    require_once($sourcedir . '/Federation-Protocol.php');
    $context['page_title'] = $txt['federation_title'];
    $context['federation_base'] = $scripturl . '?action=admin;area=modsettings;sa=federation';
    $context['federation_error'] = '';
    $context['federation_message'] = '';
    $tab = isset($_REQUEST['tab']) && is_string($_REQUEST['tab']) ? $_REQUEST['tab'] : 'settings';
    if (!in_array($tab, array('settings','boards','review','deliveries','blocks','diagnostics'), true)) $tab='settings';
    $context['federation_tab'] = $tab;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['federation_action'])) {
        checkSession('post'); validateToken('federation_admin');
        $action = (string) $_POST['federation_action'];
        try {
            if ($action === 'save_settings') {
                $keys = array('enabled','inbound','outbound','allow_members','member_outbound');
                $values = array();
                foreach ($keys as $key) $values['federation_' . $key] = !empty($_POST['federation_' . $key]) ? 1 : 0;
                if (!federation_https_ready() && !empty($values['federation_enabled'])) throw new RuntimeException($txt['federation_ui_https_is_required_before_enabling_federation']);
                updateSettings($values);
                $modSettings = array_merge($modSettings, $values);
                $context['federation_message'] = $txt['federation_ui_settings_saved'];
            } elseif ($action === 'enable_board') {
                $id = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
                federation_enable_actor('board', $id);
                $context['federation_message'] = $txt['federation_ui_public_board_enabled'];
            } elseif ($action === 'disable_board') {
                $id = isset($_POST['board_id']) ? (int) $_POST['board_id'] : 0;
                $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_actors SET enabled = {int:off} WHERE actor_type = {string:kind} AND id_local = {int:id}', array('off'=>0,'kind'=>'board','id'=>$id));
                $context['federation_message'] = $txt['federation_ui_board_disabled_for_federation'];
            } elseif ($action === 'review') {
                $id = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
                federation_review_inbox($id, !empty($_POST['approve']));
                $context['federation_message'] = $txt['federation_ui_remote_activity_reviewed'];
            } elseif ($action === 'run_queue') {
                $n = federation_process_queue(3, 15);
                $context['federation_message'] = sprintf($txt['federation_processed'], $n);
            } elseif ($action === 'retry_delivery') {
                $id = isset($_POST['delivery_id']) ? (int) $_POST['delivery_id'] : 0;
                $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:queued}, next_try = {int:now} WHERE id_delivery = {int:id} AND status = {string:failed}',
                    array('queued'=>'queued','now'=>time(),'id'=>$id,'failed'=>'failed'));
                $context['federation_message'] = $txt['federation_ui_failed_delivery_requeued'];
            } elseif ($action === 'block_domain') {
                $domain = strtolower(trim(isset($_POST['domain']) ? (string) $_POST['domain'] : ''));
                if (strlen($domain) > 190 || !preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$~D', $domain)) throw new RuntimeException($txt['federation_ui_invalid_domain']);
                $smcFunc['db_insert']('ignore', '{db_prefix}federation_blocks', array('domain'=>'string-190'), array($domain), array('id_block'));
                $context['federation_message'] = $txt['federation_ui_domain_blocked'];
            } elseif ($action === 'unblock_domain') {
                $id = isset($_POST['block_id']) ? (int) $_POST['block_id'] : 0;
                $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_blocks WHERE id_block = {int:id}', array('id'=>$id));
                $context['federation_message'] = $txt['federation_ui_domain_unblocked'];
            } else throw new RuntimeException($txt['federation_ui_unknown_operation']);
        } catch (Exception $e) { $context['federation_error'] = $e->getMessage(); }
    }
    $context['federation_settings'] = array();
    foreach (array('enabled','inbound','outbound','allow_members','member_outbound') as $key)
        $context['federation_settings'][$key] = !empty($modSettings['federation_' . $key]);
    $context['federation_boards'] = array();
    $q=$smcFunc['db_query']('', 'SELECT id_board, name FROM {db_prefix}boards WHERE redirect = {string:empty} ORDER BY id_board',array('empty'=>''));
    while ($r=$smcFunc['db_fetch_assoc']($q)) {
        $r['public'] = federation_guest_public_board((int)$r['id_board']);
        $r['active'] = !!federation_actor_row('board',(int)$r['id_board']);
        $context['federation_boards'][]=$r;
    }
    $smcFunc['db_free_result']($q);
    $context['federation_inbox'] = federation_admin_rows('federation_inbox', 'id_inbox', 'status = {string:pending}', array('pending'=>'pending'), 50);
    $context['federation_delivery'] = federation_admin_rows('federation_delivery', 'id_delivery', '1=1', array(), 50);
    $context['federation_blocks'] = federation_admin_rows('federation_blocks', 'id_block', '1=1', array(), 100);
    $context['federation_log'] = federation_admin_rows('federation_log', 'id_log', '1=1', array(), 30);
    $context['federation_https'] = federation_https_ready();
    $context['federation_discovery'] = array('WebFinger' => federation_site().'/.well-known/webfinger', 'NodeInfo' => federation_site().'/.well-known/nodeinfo');
    createToken('federation_admin');
    $context['sub_template'] = 'federation_admin';
}
function federation_admin_rows($table, $order, $condition, $args, $limit)
{
    global $smcFunc;
    $q=$smcFunc['db_query']('', 'SELECT * FROM {db_prefix}' . $table . ' WHERE ' . $condition . ' ORDER BY ' . $order . ' DESC LIMIT {int:max}',array_merge($args,array('max'=>$limit)));
    $out=array(); while ($r=$smcFunc['db_fetch_assoc']($q)) $out[]=$r;
    $smcFunc['db_free_result']($q); return $out;
}
function federation_member_page($memID)
{
    global $user_info, $modSettings, $smcFunc, $context, $txt, $scripturl, $sourcedir;
    if (empty($user_info['id']) || $memID !== (int) $user_info['id'] || empty($modSettings['federation_enabled']) || empty($modSettings['federation_allow_members']))
        fatal_lang_error('no_access', false);
    loadLanguage('Federation'); loadTemplate('Federation');
    loadCSSFile('federation.css', array('default_theme' => true, 'minimize' => true, 'seed' => FEDERATION_VERSION . '-ui2'), 'federation_css');
    require_once($sourcedir . '/Federation-Protocol.php');
    $context['federation_member_error']=''; $context['federation_member_message']='';
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['federation_action'])) {
        checkSession('post'); validateToken('federation_member');
        try {
            $action = (string) $_POST['federation_action'];
            if ($action==='enable') {
                federation_enable_actor('member',$memID); $context['federation_member_message']=$txt['federation_ui_federated_identity_enabled'];
            } elseif ($action==='disable') {
                $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_actors SET enabled = {int:off} WHERE actor_type = {string:kind} AND id_local = {int:id}',
                    array('off'=>0,'kind'=>'member','id'=>$memID));
                $context['federation_member_message']=$txt['federation_ui_federated_identity_disabled'];
            } elseif ($action==='follow') {
                $local=federation_actor_row('member',$memID);
                if (!$local) throw new RuntimeException($txt['federation_ui_enable_your_federated_identity_first']);
                $target=trim(isset($_POST['remote_url'])?(string)$_POST['remote_url']:'');
                federation_follow_remote($local,$target); $context['federation_member_message']=$txt['federation_ui_follow_request_queued'];
            } elseif ($action==='remove_follower') {
                $local=federation_actor_row('member',$memID);
                $target=isset($_POST['remote_url']) && is_string($_POST['remote_url']) ? $_POST['remote_url'] : '';
                if (!$local || !$target) throw new RuntimeException($txt['federation_ui_invalid_follower']);
                $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND remote_actor = {string:actor}', array('id'=>(int)$local['id_actor'],'actor'=>$target));
                $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_inbox SET status = {string:rejected} WHERE id_actor = {int:id} AND remote_actor = {string:actor} AND activity_type = {string:type} AND status = {string:pending}', array('id'=>(int)$local['id_actor'],'actor'=>$target,'type'=>'Follow','rejected'=>'rejected','pending'=>'pending'));
                $context['federation_member_message']=$txt['federation_ui_follower_removed'];
            } elseif ($action==='unfollow') {
                $local=federation_actor_row('member',$memID);
                $target=isset($_POST['remote_url'])?(string)$_POST['remote_url']:'';
                if ($local) federation_unfollow_remote($local,$target);
                $context['federation_member_message']=$txt['federation_ui_unfollow_queued'];
            } else throw new RuntimeException($txt['federation_ui_unknown_operation']);
        } catch(Exception $e) { $context['federation_member_error']=$e->getMessage(); }
    }
    $context['federation_member_actor']=federation_actor_row('member',$memID);
    $context['federation_member_follows']=array();
    $context['federation_member_followers']=array();
    if ($context['federation_member_actor']) {
        $q=$smcFunc['db_query']('', 'SELECT remote_actor, status FROM {db_prefix}federation_following WHERE id_actor = {int:id} LIMIT 100',
            array('id'=>(int)$context['federation_member_actor']['id_actor']));
        while ($r=$smcFunc['db_fetch_assoc']($q)) $context['federation_member_follows'][]=$r;
        $smcFunc['db_free_result']($q);
        $q=$smcFunc['db_query']('', 'SELECT remote_actor FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND status = {string:active} ORDER BY id_follower LIMIT 500', array('id'=>(int)$context['federation_member_actor']['id_actor'],'active'=>'active'));
        while ($r=$smcFunc['db_fetch_assoc']($q)) $context['federation_member_followers'][]=$r;
        $smcFunc['db_free_result']($q);
    }
    $context['federation_profile_post']=$scripturl.'?action=profile;area=federation;u='.$memID;
    $context['page_title']=$txt['federation_profile'];
    createToken('federation_member');
    $context['sub_template']='federation_profile';
}
