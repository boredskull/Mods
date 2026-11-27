<?php
/**
 * Slow Mode 1.0.3 for SMF 2.1.x, BoredSkull, ISC License.
 * No core or theme edits. Standalone, with optional integration hooks.
 */
if (!defined('SMF')) die('No direct access');

function BSM_lang()
{
    global $txt, $settings;
    $existing = $txt;
    if (is_file($settings['default_theme_dir'] . '/languages/SlowMode.english.php'))
        require $settings['default_theme_dir'] . '/languages/SlowMode.english.php';
    $txt = array_replace($txt, $existing);
    loadLanguage('SlowMode', '', false);
}

function BSM_actions(&$actions)
{
    $actions['bsm'] = array('SlowMode.php', 'BSM_manage');
}

function BSM_admin_areas(&$areas)
{
    global $txt;
    BSM_lang();
    $areas['config']['areas']['modsettings']['subsections']['bsm'] = array($txt['bsm'], 'admin_forum');
}

function BSM_admin(&$subActions)
{
    global $context, $txt;
    BSM_lang();
    $subActions['bsm'] = 'BSM_admin_page';
    $context[$context['admin_menu_name']]['tab_data']['tabs']['bsm'] = array('label' => $txt['bsm'], 'description' => $txt['bsm_desc']);
}

function BSM_admin_page()
{
    global $context, $smcFunc, $scripturl, $modSettings, $txt;
    isAllowedTo('admin_forum');
    BSM_lang();
    if (isset($_POST['bsm_save'])) {
        checkSession('post');
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $exempt = !empty($_POST['exempt_mod']) ? 1 : 0;
        updateSettings(array('bsm_enabled' => $enabled, 'bsm_exempt_mod' => $exempt));
        $rules = isset($_POST['minutes']) && is_array($_POST['minutes']) ? $_POST['minutes'] : array();
        foreach ($rules as $id => $minute) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $minute = max(0, min(1440, (int) $minute));
            BSM_audit('board', $id, BSM_rule('board', $id), $minute);
            $smcFunc['db_insert']('replace', '{db_prefix}bsm_rules',
                array('scope' => 'string-8', 'scope_id' => 'int', 'minutes' => 'int', 'updated_by' => 'int', 'updated_at' => 'int'),
                array('board', $id, $minute, (int) $GLOBALS['user_info']['id'], time()), array('scope', 'scope_id'));
        }
        redirectexit('action=admin;area=modsettings;sa=bsm');
    }
    $r = $smcFunc['db_query']('', 'SELECT id_board, name FROM {db_prefix}boards WHERE redirect = {string:blank} ORDER BY board_order', array('blank' => ''));
    $boards = array();
    while ($row = $smcFunc['db_fetch_assoc']($r)) $boards[(int) $row['id_board']] = $row['name'];
    $smcFunc['db_free_result']($r);
    $rules = array();
    $r = $smcFunc['db_query']('', 'SELECT scope_id, minutes FROM {db_prefix}bsm_rules WHERE scope={string:scope}', array('scope' => 'board'));
    while ($row = $smcFunc['db_fetch_assoc']($r)) $rules[(int) $row['scope_id']] = (int) $row['minutes'];
    $smcFunc['db_free_result']($r);
    $recent = array();
    $log = $smcFunc['db_query']('', 'SELECT scope, scope_id, old_minutes, new_minutes, id_member, changed_at FROM {db_prefix}bsm_history ORDER BY id DESC LIMIT 30', array());
    while ($entry = $smcFunc['db_fetch_assoc']($log)) $recent[] = $entry;
    $smcFunc['db_free_result']($log);
    $context['bsm_history'] = $recent;
    $context['bsm_boards'] = $boards;
    $context['bsm_rules'] = $rules;
    $context['bsm_enabled'] = !empty($modSettings['bsm_enabled']);
    $context['bsm_exempt_mod'] = !empty($modSettings['bsm_exempt_mod']);
    $context['post_url'] = $scripturl . '?action=admin;area=modsettings;sa=bsm';
    $context['page_title'] = $txt['bsm'];
    loadTemplate('SlowMode');
    $context['sub_template'] = 'bsm_admin';
}

function BSM_buttons(&$buttons)
{
    global $context, $scripturl, $user_info;
    if (empty($context['current_topic']) || empty($context['current_board']) || $user_info['is_guest']) return;
    if (!BSM_can_manage((int) $context['current_board'])) return;
    $buttons['bsm'] = array('text' => 'bsm_action', 'url' => $scripturl . '?action=bsm;topic=' . (int) $context['current_topic'] . '.0', 'show' => true);
    BSM_lang();
}

function BSM_can_manage($board)
{
    global $user_info;
    if ($user_info['is_guest']) return false;
    return allowedTo('admin_forum') || allowedTo('moderate_board', $board);
}

function BSM_manage()
{
    global $smcFunc, $context, $topic, $board, $scripturl, $user_info, $txt;
    BSM_lang();
    if (empty($topic) || empty($board) || !BSM_can_manage((int) $board)) fatal_lang_error('cannot_admin_forum', false);
    $r = $smcFunc['db_query']('', 'SELECT id_board FROM {db_prefix}topics WHERE id_topic={int:topic} LIMIT 1', array('topic' => (int) $topic));
    $row = $smcFunc['db_fetch_assoc']($r);
    $smcFunc['db_free_result']($r);
    if (!$row || (int) $row['id_board'] !== (int) $board) fatal_lang_error('topic_doesnt_exist', false);
    if (isset($_POST['bsm_save'])) {
        checkSession('post');
        $minutes = max(0, min(1440, (int) ($_POST['minutes'] ?? 0)));
        BSM_audit('topic', (int) $topic, BSM_rule('topic', (int) $topic), $minutes);
        $smcFunc['db_insert']('replace', '{db_prefix}bsm_rules',
            array('scope' => 'string-8', 'scope_id' => 'int', 'minutes' => 'int', 'updated_by' => 'int', 'updated_at' => 'int'),
            array('topic', (int) $topic, $minutes, (int) $user_info['id'], time()), array('scope', 'scope_id'));
        redirectexit('topic=' . (int) $topic . '.0');
    }
    $context['bsm_minutes'] = BSM_rule('topic', (int) $topic);
    $context['bsm_board_minutes'] = BSM_rule('board', (int) $board);
    $context['bsm_topic'] = (int) $topic;
    $context['page_title'] = $txt['bsm_topic_title'];
    $context['bsm_post_url'] = $scripturl . '?action=bsm;topic=' . (int) $topic . '.0';
    loadTemplate('SlowMode');
    $context['sub_template'] = 'bsm_topic';
}

function BSM_rule($scope, $id)
{
    global $smcFunc;
    if ($id <= 0) return 0;
    $r = $smcFunc['db_query']('', 'SELECT minutes FROM {db_prefix}bsm_rules WHERE scope={string:scope} AND scope_id={int:id} LIMIT 1', array('scope' => $scope, 'id' => $id));
    $row = $smcFunc['db_fetch_assoc']($r);
    $smcFunc['db_free_result']($r);
    return $row ? max(0, (int) $row['minutes']) : 0;
}

/** Extensions can set $exempt = true; only loaded integrations participate. */
function BSM_exempt($board, $topic)
{
    global $user_info, $modSettings;
    if ($user_info['is_admin']) return true;
    if (!empty($modSettings['bsm_exempt_mod']) && (allowedTo('moderate_board', $board) || allowedTo('moderate_forum'))) return true;
    $exempt = false;
    call_integration_hook('integrate_bsm_exemption', array(&$exempt, (int) $user_info['id'], (int) $board, (int) $topic));
    return $exempt === true;
}

/** Store a distinct audit event only when a rule changes. */
function BSM_audit($scope, $id, $old, $new)
{
    global $smcFunc, $user_info;
    if ((int) $old === (int) $new) return;
    $smcFunc['db_insert']('', '{db_prefix}bsm_history',
        array('scope' => 'string-8', 'scope_id' => 'int', 'old_minutes' => 'int', 'new_minutes' => 'int', 'id_member' => 'int', 'changed_at' => 'int'),
        array($scope, (int) $id, (int) $old, (int) $new, (int) $user_info['id'], time()), array('id'));
}

/** Anonymous subjects are represented by a keyed hash of IP, never a raw IP. */
function BSM_subject()
{
    global $user_info, $modSettings;
    if (!$user_info['is_guest']) return 'm:' . (int) $user_info['id'];
    if (empty($modSettings['bsm_guest_secret'])) return '';
    return 'g:' . hash_hmac('sha256', (string) $user_info['ip'], $modSettings['bsm_guest_secret']);
}

/** Determine remaining time from both rules, including claims in progress. */
function BSM_remaining($board, $topic)
{
    global $smcFunc, $user_info, $modSettings;
    if (empty($modSettings['bsm_enabled']) || BSM_exempt($board, $topic)) return 0;
    $rules = array('board' => BSM_rule('board', $board), 'topic' => BSM_rule('topic', $topic));
    if (!$rules['board'] && !$rules['topic']) return 0;
    $now = time();
    $subject = BSM_subject();
    if ($subject === '') return 0;
    $wait = 0;
    foreach ($rules as $scope => $minutes) {
        if (!$minutes) continue;
        $id = $scope === 'board' ? $board : $topic;
        $r = $smcFunc['db_query']('', 'SELECT next_allowed, pending_until, touched_at FROM {db_prefix}bsm_slots WHERE subject_key={string:subject} AND scope={string:scope} AND scope_id={int:id} LIMIT 1',
            array('subject' => $subject, 'scope' => $scope, 'id' => (int) $id));
        $row = $smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
        if ($row) $wait = max($wait, ((int) $row['next_allowed'] > 0 ? (int) $row['touched_at'] + $minutes * 60 - $now : 0), (int) $row['pending_until'] - $now);
    }
    // Preserve history for registered members when rules were just enabled.
    if (!$user_info['is_guest']) {
        foreach ($rules as $scope => $minutes) {
            if (!$minutes) continue;
            $query = 'SELECT MAX(poster_time) AS last_time FROM {db_prefix}messages WHERE id_member={int:member} AND id_board={int:board}' . ($scope === 'topic' ? ' AND id_topic={int:topic}' : '');
            $r = $smcFunc['db_query']('', $query, array('member' => (int) $user_info['id'], 'board' => (int) $board, 'topic' => (int) $topic));
            $row = $smcFunc['db_fetch_assoc']($r); $smcFunc['db_free_result']($r);
            $wait = max($wait, (int) $row['last_time'] + $minutes * 60 - $now);
        }
    }
    return max(0, $wait);
}

/** Visible information on the regular topic page, for non-exempt readers. */
function BSM_display_info(&$buttons)
{
    global $context, $modSettings, $user_info, $txt;
    $board = (int) ($context['current_board'] ?? 0);
    $topic = (int) ($context['current_topic'] ?? 0);
    if (!$board || !$topic || empty($modSettings['bsm_enabled'])) return;
    $bm = BSM_rule('board', $board); $tm = BSM_rule('topic', $topic);
    if (!$bm && !$tm) return;
    $wait = BSM_remaining($board, $topic);
    BSM_lang();
    $context['bsm_notice'] = $txt['bsm'] . ': ' . ($bm ? sprintf($txt['bsm_notice_board'], $bm) : '') . ($bm && $tm ? ', ' : '') . ($tm ? sprintf($txt['bsm_notice_topic'], $tm) : '') . '.';
    if ($wait) $context['bsm_notice'] .= ' ' . sprintf($txt['bsm_notice_wait'], (int) ceil($wait / 60));
    // Avoid exposing individual guest IPs or other user's post metadata.
    addInlineJavaScript('document.addEventListener("DOMContentLoaded", function(){var el=document.querySelector("#forumposts"); if(!el)return;var p=document.createElement("div");p.className="information";p.textContent=' . json_encode($context['bsm_notice'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';el.parentNode.insertBefore(p,el);});', true);
}

/** Reserve a short in-flight slot. The full cooldown starts only after Post2 succeeds. */
function BSM_post2_pre(&$post_errors)
{
    global $topic, $board, $user_info, $smcFunc, $modSettings;
    if (empty($modSettings['bsm_enabled']) || empty($board)) return;
    $topic = (int) $topic;
    if (!empty($post_errors) || isset($_REQUEST['msg']) || isset($_REQUEST['preview']) || isset($_POST['save_draft']) || isset($_POST['deleteevent'])) return;
    if (BSM_exempt((int) $board, (int) $topic)) return;
    $subject = BSM_subject();
    if ($subject === '') return;
    $rules = array('board' => BSM_rule('board', (int) $board), 'topic' => BSM_rule('topic', (int) $topic));
    $active = array_filter($rules, function($x) { return $x > 0; });
    if (!$active) return;
    $wait = BSM_remaining((int) $board, (int) $topic);
    if ($wait) { BSM_lang(); $post_errors[] = array('bsm_cooldown', array((int) ceil($wait / 60))); return; }
    $now = time();
    $token = bin2hex(random_bytes(16));
    $lease = $now + 90;
    $smcFunc['db_transaction']('begin');
    foreach ($active as $scope => $minutes) {
        $id = $scope === 'board' ? (int) $board : (int) $topic;
        $smcFunc['db_insert']('ignore', '{db_prefix}bsm_slots',
            array('subject_key' => 'string-80', 'scope' => 'string-8', 'scope_id' => 'int', 'next_allowed' => 'int', 'pending_until' => 'int', 'claim_token' => 'string-32', 'touched_at' => 'int'),
            array($subject, $scope, $id, 0, 0, '', $now), array('subject_key', 'scope', 'scope_id'));
        $smcFunc['db_query']('', 'UPDATE {db_prefix}bsm_slots SET pending_until={int:lease}, claim_token={string:token} WHERE subject_key={string:subject} AND scope={string:scope} AND scope_id={int:id} AND (next_allowed=0 OR touched_at<={int:cutoff}) AND pending_until<={int:now}',
            array('lease' => $lease, 'token' => $token, 'now' => $now, 'cutoff' => $now - $minutes * 60, 'subject' => $subject, 'scope' => $scope, 'id' => $id));
        if (!$smcFunc['db_affected_rows']()) {
            $smcFunc['db_transaction']('rollback');
            BSM_lang(); $post_errors[] = array('bsm_cooldown', array(1));
            return;
        }
    }
    $smcFunc['db_transaction']('commit');
    $GLOBALS['bsm_claim'] = array('subject' => $subject, 'token' => $token, 'rules' => $active, 'board' => (int) $board, 'topic' => (int) $topic);
}

/** Post2_end only runs after SMF has written the post successfully. */
function BSM_post2_end()
{
    global $smcFunc;
    if (empty($GLOBALS['bsm_claim'])) return;
    $claim = $GLOBALS['bsm_claim'];
    $now = time();
    foreach ($claim['rules'] as $scope => $minutes) {
        $id = $scope === 'board' ? $claim['board'] : $claim['topic'];
        $smcFunc['db_query']('', 'UPDATE {db_prefix}bsm_slots SET next_allowed={int:next}, pending_until={int:zero}, claim_token={string:empty}, touched_at={int:now} WHERE subject_key={string:subject} AND scope={string:scope} AND scope_id={int:id} AND claim_token={string:token}',
            array('next' => $now + $minutes * 60, 'zero' => 0, 'empty' => '', 'now' => $now, 'subject' => $claim['subject'], 'scope' => $scope, 'id' => $id, 'token' => $claim['token']));
    }
    unset($GLOBALS['bsm_claim']);
}

function BSM_errors(&$post_errors, &$minor_errors, $message, $subject)
{
    BSM_lang();
    // Only release on an explicit validation failure before publication.
    if (empty($post_errors) || empty($GLOBALS['bsm_claim'])) return;
    global $smcFunc;
    $claim = $GLOBALS['bsm_claim'];
    $smcFunc['db_query']('', 'UPDATE {db_prefix}bsm_slots SET pending_until=0, claim_token={string:empty} WHERE subject_key={string:subject} AND claim_token={string:token}', array('empty' => '', 'subject' => $claim['subject'], 'token' => $claim['token']));
    unset($GLOBALS['bsm_claim']);
}

/** Delete orphaned topic rules and expired historical posting slots. */
function BSM_prune()
{
    global $smcFunc;
    foreach (array('bsm_rules', 'bsm_slots') as $table) {
        foreach (array('topic' => array('topics', 'id_topic'), 'board' => array('boards', 'id_board')) as $scope => $target) {
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE scope={string:scope} AND scope_id NOT IN (SELECT ' . $target[1] . ' FROM {db_prefix}' . $target[0] . ')', array('scope' => $scope));
        }
    }
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}bsm_slots WHERE next_allowed<{int:old} AND pending_until<{int:now} AND touched_at<{int:old}', array('old' => time() - 172800, 'now' => time()));
}

function BSM_daily_cleanup()
{
    BSM_prune();
}
