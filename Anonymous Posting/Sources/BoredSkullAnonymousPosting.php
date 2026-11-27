<?php
/**
 * BoredSkull Anonymous Posting for SMF 2.1.x
 * @version 1.0.9
 * @license ISC
 */
if (!defined('SMF')) die('No direct access');
define('BSAP_VERSION', '1.0.9');

/** Localised fatal error that never writes to the SMF error log. */
function BSAP_fatal($key, $fallback) {
    global $txt;
    fatal_error(isset($txt[$key]) ? $txt[$key] : $fallback, false);
}

function BSAP_boards($key) {
    global $modSettings;
    return array_values(array_unique(array_filter(array_map('intval', explode(',', isset($modSettings[$key]) ? (string) $modSettings[$key] : '')))));
}
function BSAP_enabled_board($board) {
    global $modSettings;
    return !empty($modSettings['bsap_enabled']) && $board > 0 && in_array((int) $board, BSAP_boards('bsap_boards'), true);
}
function BSAP_forced_board($board) { return BSAP_enabled_board((int)$board) && in_array((int)$board, BSAP_boards('bsap_force_boards'), true); }
function BSAP_can_use($member, $board) {
    global $user_info;
    if (!$member || empty($user_info['id']) || (int)$user_info['id'] !== (int)$member || !BSAP_enabled_board($board)) return false;
    if (empty($user_info['is_admin']) && !allowedTo('bsap_use')) return false;
    $allowed = true;
    try { call_integration_hook('integrate_bsap_can_use', array(&$allowed, $member, $board)); } catch (Throwable $e) { return false; }
    return $allowed === true;
}
function BSAP_requested($board) {
    return BSAP_forced_board($board) || (isset($_POST['bsap_anonymous']) && $_POST['bsap_anonymous'] === '1');
}
function BSAP_new_post_request() {
    return !empty($_REQUEST['action']) && $_REQUEST['action'] === 'post2' && empty($_REQUEST['msg'])
        && empty($_POST['save_draft']) && empty($_REQUEST['preview']) && empty($_POST['preview']);
}
/** Only treat a real file upload as an upload. SMF may leave bookkeeping
 * keys in the session or post form even when no attachment was submitted.
 * Assigned attachments are independently checked in BSAP_create(). */
function BSAP_has_actual_upload($files) {
    if (!is_array($files)) return false;
    if (array_key_exists('error', $files)) {
        foreach ((array) $files['error'] as $error)
            if (is_array($error) ? BSAP_has_actual_upload(array('error' => $error)) : ((int) $error !== UPLOAD_ERR_NO_FILE)) return true;
        return false;
    }
    foreach ($files as $value)
        if (is_array($value) && BSAP_has_actual_upload($value)) return true;
    return false;
}
/** Reject only genuine unsupported operations, not SMF's empty form/session placeholders. */
function BSAP_unsupported_request() {
    return BSAP_has_actual_upload($_FILES)
        || isset($_REQUEST['calendar']) || isset($_POST['calendar']) || isset($_REQUEST['poll']) || isset($_POST['poll'])
        || !empty($_POST['announce_topic']) || !empty($_POST['move']) || !empty($_POST['sticky']) || !empty($_POST['lock']);
}
/** Prevent anonymous operations on boards that are not explicitly enabled. */
function BSAP_active_request() {
    global $board;
    return BSAP_new_post_request() && (int)$board > 0 && BSAP_requested((int)$board);
}
/** SMF 2.1 stores IPs as VARBINARY, not printable IPv4/IPv6 strings.
 * Only accept the zero address; do not silently accept arbitrary binary IPs. */
function BSAP_is_masked_ip($stored) {
    if (!is_string($stored)) return false;
    if ($stored === '0.0.0.0' || $stored === '::') return true;
    if (strlen($stored) !== 4 && strlen($stored) !== 16) return false;
    $ip = @inet_ntop($stored);
    return $ip === '0.0.0.0' || $ip === '::';
}
/** Abort without adding a member-linked SMF error-log entry. */
function BSAP_failure($code) {
    // Never call SMF log_error here: it records the real member, IP and URL.
    BSAP_transaction_cleanup();
    BSAP_fatal('bsap_failed_safely', 'Anonymous posting failed safely. Please try again later.');
}
/** All-or-nothing persistence for the post and the confidential attribution. */
function BSAP_transactional_tables() {
    global $smcFunc, $db_type, $db_prefix;
    // This preview supports only a normal unqualified MySQL/InnoDB prefix.
    if (($db_type ?? 'mysql') !== 'mysql' || !preg_match('/^[a-zA-Z0-9_]*$/D', (string)$db_prefix)) return false;
    $names = array_map(function($t) use ($db_prefix) { return $db_prefix . $t; }, array('messages','topics','bsap_authors'));
    $q = $smcFunc['db_query']('', 'SHOW TABLE STATUS WHERE Name IN ({array_string:tables})', array('tables'=>$names));
    $found = array();
    while ($row = $smcFunc['db_fetch_assoc']($q)) if (strtolower($row['Engine'] ?? '') === 'innodb') $found[] = $row['Name'];
    $smcFunc['db_free_result']($q);
    return !array_diff($names, $found);
}
function BSAP_transaction_start() {
    global $smcFunc;
    if (!empty($GLOBALS['bsap_tx_active'])) return;
    if (empty($smcFunc['db_transaction']) || !is_callable($smcFunc['db_transaction']))
        BSAP_fatal('bsap_tx_required', 'Anonymous posting is unavailable: transactional database support is required.');
    if (!$smcFunc['db_transaction']('begin'))
        BSAP_fatal('bsap_tx_begin', 'Anonymous posting is unavailable: could not begin a transaction.');
    $GLOBALS['bsap_tx_active'] = true;
    $GLOBALS['bsap_original_transaction'] = $smcFunc['db_transaction'];
    // Guard the shared SMF transaction API while this request owns the transaction.
    $smcFunc['db_transaction'] = 'BSAP_nested_transaction';
    // PHP database drivers roll back uncommitted transactions when disconnected. A
    // shutdown handler also explicitly rolls back abnormal code paths where possible.
    register_shutdown_function('BSAP_transaction_cleanup');
}
function BSAP_nested_transaction($type = 'commit', $connection = null) {
    $GLOBALS['bsap_tx_conflict'] = true;
    throw new RuntimeException('Anonymous posting cannot share a database transaction.');
}
function BSAP_transaction_driver() {
    global $smcFunc;
    $driver = $GLOBALS['bsap_original_transaction'] ?? $smcFunc['db_transaction'];
    $smcFunc['db_transaction'] = $driver;
    unset($GLOBALS['bsap_original_transaction']);
    return $driver;
}
function BSAP_transaction_cleanup() {
    global $smcFunc;
    if (empty($GLOBALS['bsap_tx_active'])) return;
    $GLOBALS['bsap_tx_active'] = false;
    unset($GLOBALS['bsap_pending_author'], $GLOBALS['bsap_mapped_id']);
    try { $driver = BSAP_transaction_driver(); $driver('rollback'); } catch (Throwable $e) { /* driver will roll back on disconnect */ }
}
function BSAP_transaction_commit() {
    global $smcFunc;
    if (!empty($GLOBALS['bsap_tx_conflict'])) BSAP_failure('transaction_conflict');
    if (empty($GLOBALS['bsap_tx_active']) || empty($GLOBALS['bsap_mapped_id']))
        BSAP_fatal('bsap_verify_failed', 'Anonymous posting could not verify the author mapping.');
    $driver = BSAP_transaction_driver();
    if (!$driver('commit')) BSAP_failure('commit_failed');
    $GLOBALS['bsap_tx_active'] = false;
    unset($GLOBALS['bsap_mapped_id'], $GLOBALS['bsap_pending_author']);
}
function BSAP_post2_end() {
    if (empty($GLOBALS['bsap_tx_active'])) return;
    global $smcFunc;
    // after_create_post runs before the topic exists: verify the final persisted state here.
    $id = (int) ($GLOBALS['bsap_mapped_id'] ?? 0);
    $p = $GLOBALS['bsap_pending_author'] ?? array();
    $q = $smcFunc['db_query']('', 'SELECT m.id_member, m.poster_name, m.poster_email, m.poster_ip, m.id_board, t.id_first_msg, t.id_last_msg, t.id_member_started, t.id_member_updated, t.id_poll, a.id_board AS mapped_board, a.id_member AS real_author FROM {db_prefix}messages AS m INNER JOIN {db_prefix}topics AS t ON (t.id_topic=m.id_topic AND t.id_board=m.id_board) INNER JOIN {db_prefix}bsap_authors AS a ON (a.id_msg=m.id_msg) WHERE m.id_msg={int:msg}', array('msg'=>$id));
    $r = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if (!$r || (int)$r['id_member'] !== 0 || $r['poster_name'] !== 'Anonymous' || $r['poster_email'] !== '' || !BSAP_is_masked_ip($r['poster_ip']) || (int)$r['id_board'] !== (int)($p['board'] ?? 0) || (int)$r['real_author'] !== (int)($p['member'] ?? 0) || (int)$r['mapped_board'] !== (int)($p['board'] ?? 0) || ((int)$r['id_first_msg'] === $id && !empty($r['id_poll'])) || ((int)$r['id_first_msg'] === $id && (int)$r['id_member_started'] !== 0) || ((int)$r['id_last_msg'] === $id && (int)$r['id_member_updated'] !== 0)) BSAP_failure('final_verification');
    BSAP_transaction_commit();
}
function BSAP_post_end() {
    global $board, $topic, $user_info, $context, $txt;
    if (!$board || !empty($_REQUEST['msg']) || BSAP_unsupported_request() || !BSAP_can_use((int)$user_info['id'], (int)$board)) return;
    loadLanguage('BoredSkullAnonymousPosting');
    $context['posting_fields']['bsap_anonymous'] = array(
        'label' => array('html' => '<label for="bsap_anonymous">' . $txt['bsap_post_anonymously'] . '</label>'),
        'input' => array('html' => '<input type="checkbox" id="bsap_anonymous" name="bsap_anonymous" value="1"' . (BSAP_forced_board($board) || (!empty($_POST['bsap_anonymous']) && $_POST['bsap_anonymous'] === '1') ? ' checked' : '') . (BSAP_forced_board($board) ? ' disabled' : '') . '> ' . $txt['bsap_disclaimer']),
    );
}
function BSAP_display_topic(&$topic_selects, &$topic_tables, &$topic_parameters) {
    global $board, $user_info, $context, $txt;
    if (!$board || !BSAP_can_use((int)$user_info['id'], (int)$board)) return;
    loadLanguage('BoredSkullAnonymousPosting');
    $label = json_encode(' ' . $txt['bsap_post_anonymously'] . ' - ' . $txt['bsap_disclaimer'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    $checked = BSAP_forced_board($board) ? 'true' : 'false';
    $js = '(function(){function setup(){var f=document.querySelector("#quickreply form#postmodify");if(!f||document.getElementById("bsap_qr"))return;var l=document.createElement("label");l.id="bsap_qr";var c=document.createElement("input");c.type="checkbox";c.name="bsap_anonymous";c.value="1";c.checked=' . $checked . ';c.disabled=' . $checked . ';l.appendChild(c);l.appendChild(document.createTextNode(' . $label . '));var r=f.querySelector("#post_confirm_buttons");if(r&&r.parentNode)r.parentNode.insertBefore(l,r);else f.appendChild(l);}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",setup);else setup();})();';
    addInlineJavaScript($js, true);
}
function BSAP_hook_name($entry) {
    $parts = explode('|', trim((string) $entry));
    return rtrim(trim(end($parts)), '#');
}
function BSAP_prepare_hooks() {
    global $modSettings;
    // SMF reads this comma-separated list anew for each hook dispatch.
    $end = explode(',', $modSettings['integrate_post2_end'] ?? '');
    $ours = array_values(array_filter($end, function($e) { return BSAP_hook_name($e) === 'BSAP_post2_end'; }));
    if (count($ours) !== 1) return false;
    $modSettings['integrate_post2_end'] = implode(',', array_merge($ours, array_diff($end, $ours)));
    foreach (array('integrate_create_post'=>'AIAssistant::hookCreatePost','integrate_after_create_post'=>'AIAssistant::hookAfterCreatePost','integrate_post2_end'=>'AIAssistant::hookPost2End') as $hook=>$callback) {
        if (empty($modSettings[$hook])) continue;
        $modSettings[$hook] = implode(',', array_filter(explode(',', $modSettings[$hook]), function($e) use ($callback) { return BSAP_hook_name($e) !== $callback; }));
    }
    return true;
}
function BSAP_pre(&$errors) {
    global $board, $user_info, $smcFunc;
    if (!BSAP_active_request() || !empty($errors)) return;
    loadLanguage('BoredSkullAnonymousPosting');
    if (!BSAP_enabled_board((int)$board) || !BSAP_can_use((int)$user_info['id'], (int)$board)) {
        $errors[] = 'bsap_not_allowed'; return;
    }
    if (BSAP_unsupported_request()) {
        $errors[] = 'bsap_unsupported'; return;
    }
    // Suppress only the known AI Assistant screening callback for this request, before either create hook runs.
    // This neither changes persisted hook settings nor disables ordinary AI screening.
    if (!BSAP_transactional_tables() || !BSAP_prepare_hooks()) { $errors[] = 'bsap_unavailable'; return; }
    $GLOBALS['bsap_pending_author'] = array('member'=>(int)$user_info['id'], 'board'=>(int)$board, 'started'=>time());
    // Prevent posting from silently subscribing the real member to notifications.
    unset($_POST['notify'], $_POST['sticky'], $_POST['lock']);
    // Never start the transaction on a preview or draft save.
    try { BSAP_transaction_start(); }
    catch (Throwable $e) { unset($GLOBALS['bsap_pending_author']); $errors[] = 'bsap_unavailable'; }
}
function BSAP_create(&$msgOptions, &$topicOptions, &$posterOptions, &$message_columns, &$message_parameters) {
    global $user_info, $board;
    if (!BSAP_active_request()) return;
    // Fail closed: NEVER send an identified message if requested anonymity fails.
    if (empty($GLOBALS['bsap_tx_active']) || !BSAP_can_use((int)$user_info['id'], (int)$board)
        || !BSAP_enabled_board((int)$board) || !empty($msgOptions['attachments']) || !empty($topicOptions['poll'])
        || !empty($_SESSION['already_attached'])
        || BSAP_unsupported_request()
        || (int)$posterOptions['id'] !== (int)$user_info['id'] || (int)$topicOptions['board'] !== (int)$board)
        BSAP_fatal('bsap_cannot_proceed', 'Anonymous posting cannot proceed safely. No post has been published.');
    $keys = array_keys($message_columns);
    foreach (array('id_member', 'poster_name', 'poster_email', 'poster_ip') as $key)
        if (!in_array($key, $keys, true)) BSAP_fatal('bsap_bad_schema', 'Anonymous posting is unavailable: incompatible message schema.');
    if (empty($GLOBALS['bsap_pending_author'])) BSAP_failure('missing_pending_author');
    foreach (array('id_member'=>0,'poster_name'=>'Anonymous','poster_email'=>'','poster_ip'=>'0.0.0.0') as $key=>$value) {
        $pos = array_search($key, $keys, true);
        $message_parameters[$pos] = $value;
    }
    $posterOptions['id'] = 0;
    $posterOptions['name'] = 'Anonymous';
    $posterOptions['email'] = '';
    $posterOptions['ip'] = '0.0.0.0';
    $posterOptions['update_post_count'] = false;
    $msgOptions['send_notifications'] = false;
    $topicOptions['mark_as_read'] = false;
}
function BSAP_after_create($msgOptions, $topicOptions, $posterOptions, $message_columns, $message_parameters) {
    global $smcFunc;
    if (empty($GLOBALS['bsap_pending_author'])) return;
    if (empty($GLOBALS['bsap_tx_active']) || empty($msgOptions['id'])
        || (int)$posterOptions['id'] !== 0
        || (int)$topicOptions['board'] !== (int)$GLOBALS['bsap_pending_author']['board'])
        BSAP_fatal('bsap_attribution_failed', 'Anonymous post attribution could not be saved.');
    $p = $GLOBALS['bsap_pending_author'];
    try {
        $smcFunc['db_insert']('', '{db_prefix}bsap_authors',
            array('id_msg'=>'int','id_member'=>'int','id_board'=>'int','created_at'=>'int'),
            array((int)$msgOptions['id'],$p['member'],$p['board'],$p['started']), array('id_msg'), 0);
        $request = $smcFunc['db_query']('',
            'SELECT id_member, id_board FROM {db_prefix}bsap_authors WHERE id_msg={int:msg} LIMIT 1',
            array('msg'=>(int)$msgOptions['id']));
        $row = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        if (!$row || (int)$row['id_member'] !== $p['member'] || (int)$row['id_board'] !== $p['board'])
            throw new RuntimeException('mapping_not_persisted');
        // Re-read the saved public message. Another posting hook must not be able to
        // silently restore the member ID or personal metadata after anonymisation.
        $saved = $smcFunc['db_query']('',
            'SELECT id_member, poster_name, poster_email, poster_ip, id_board FROM {db_prefix}messages WHERE id_msg={int:msg} LIMIT 1',
            array('msg'=>(int)$msgOptions['id']));
        $public = $smcFunc['db_fetch_assoc']($saved);
        $smcFunc['db_free_result']($saved);
        if (!$public) throw new RuntimeException('public_message_missing');
        if ((int)$public['id_member'] !== 0) throw new RuntimeException('public_member_not_masked');
        if ($public['poster_name'] !== 'Anonymous') throw new RuntimeException('public_name_not_masked');
        if ($public['poster_email'] !== '') throw new RuntimeException('public_email_not_masked');
        if ((int)$public['id_board'] !== (int)$p['board']) throw new RuntimeException('public_board_mismatch');
        if (!BSAP_is_masked_ip($public['poster_ip'])) throw new RuntimeException('public_ip_not_masked');
        $GLOBALS['bsap_mapped_id'] = (int)$msgOptions['id'];
    } catch (Throwable $e) {
        // The uncommitted message will be rolled back by the shutdown handler.
        // Restrict diagnostic details to fixed internal codes; never log author metadata.
        $codes = array('public_message_missing', 'public_member_not_masked', 'public_name_not_masked', 'public_email_not_masked', 'public_board_mismatch', 'public_ip_not_masked', 'mapping_not_persisted');
        $code = in_array($e->getMessage(), $codes, true) ? $e->getMessage() : 'database_or_hook_failure';
        BSAP_failure($code);
    }
}
function BSAP_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions) {
    loadLanguage('BoredSkullAnonymousPosting');
    $permissionList['membergroup']['bsap_use']=array(false,'general');
    $permissionList['membergroup']['bsap_reveal']=array(false,'member_admin');
}
/** Register the entry in Configuration > Modification Settings. */
function BSAP_admin_areas(&$admin_areas) {
    global $txt;
    loadLanguage('BoredSkullAnonymousPosting');
    $admin_areas['config']['areas']['modsettings']['subsections']['bsap'] = array($txt['bsap_title']);
    $admin_areas['config']['areas']['modsettings']['subsections']['bsapaudit'] = array($txt['bsap_audit_title']);
}

/** Register the corresponding settings controller. */
function BSAP_admin_tab(&$subActions) {
    $subActions['bsap'] = 'BSAP_admin';
    $subActions['bsapaudit'] = 'BSAP_audit';
}
function BSAP_admin() {
    global $context, $txt, $scripturl, $modSettings;
    isAllowedTo('admin_forum'); loadLanguage('BoredSkullAnonymousPosting');
    require_once($GLOBALS['sourcedir'].'/ManageServer.php');
    $vars = array(
        array('check', 'bsap_enabled', 'subtext' => $txt['bsap_enabled_desc']),
        array('boards', 'bsap_boards', 'subtext' => $txt['bsap_boards_desc']),
        array('boards', 'bsap_force_boards', 'subtext' => $txt['bsap_force_boards_desc']),
    );
    if (isset($_GET['save'])) {
        checkSession('post');
        $allowed = array_map('intval', array_keys((array) ($_POST['bsap_boards'] ?? array())));
        $forced = array_map('intval', array_keys((array) ($_POST['bsap_force_boards'] ?? array())));
        if (array_diff($forced,$allowed)) fatal_lang_error('bsap_bad_boards',false);
        saveDBSettings($vars); $_SESSION['adm-save']=true; redirectexit('action=admin;area=modsettings;sa=bsap');
    }
    $context['page_title'] = $txt['bsap_title'];
    BSAP_admin_tabs();
    $context['post_url']=$scripturl.'?action=admin;area=modsettings;sa=bsap;save';
    $context['settings_title']=$txt['bsap_title'];
    prepareDBSettingContext($vars);
}
function BSAP_admin_tabs() {
    global $context, $txt;
    if (empty($context['admin_menu_name'])) return;
    $context[$context['admin_menu_name']]['tab_data']['tabs']['bsap'] = array('label' => $txt['bsap_title'], 'description' => $txt['bsap_admin_desc']);
    $context[$context['admin_menu_name']]['tab_data']['tabs']['bsapaudit'] = array('label' => $txt['bsap_audit_title'], 'description' => $txt['bsap_audit_desc']);
}
function BSAP_actions(&$actions) { $actions['bsapreveal']=array('BoredSkullAnonymousPosting.php','BSAP_reveal'); }
function BSAP_reveal() {
    global $smcFunc, $context, $scripturl, $user_info;
    // The initial GET only displays a confirmation page. Attribution is
    // NEVER disclosed or logged as a reveal until an authenticated POST.
    is_not_guest();
    isAllowedTo('bsap_reveal');
    loadLanguage('BoredSkullAnonymousPosting');
    $is_post = isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
    if ($is_post) {
        checkSession('post');
        $id = isset($_POST['msg']) && is_scalar($_POST['msg']) ? (int) $_POST['msg'] : 0;
    } else {
        $id = isset($_GET['msg']) && is_scalar($_GET['msg']) ? (int) $_GET['msg'] : 0;
    }
    if ($id < 1) fatal_lang_error('no_access', false);
    $req = $smcFunc['db_query']('',
        'SELECT a.id_member, p.id_board, p.approved, t.approved AS topic_approved, m.real_name FROM {db_prefix}bsap_authors AS a
         INNER JOIN {db_prefix}messages AS p ON (p.id_msg = a.id_msg AND p.id_member = {int:guest})
         INNER JOIN {db_prefix}topics AS t ON (t.id_topic = p.id_topic)
         LEFT JOIN {db_prefix}members AS m ON (m.id_member = a.id_member)
         WHERE a.id_msg = {int:msg} LIMIT 1',
        array('msg' => $id, 'guest' => 0));
    $row = $smcFunc['db_fetch_assoc']($req);
    $smcFunc['db_free_result']($req);
    if (!$row) fatal_lang_error('no_access', false);
    // A reveal permission never overrides the forum's board visibility rules.
    $q = $smcFunc['db_query']('',
        'SELECT b.id_board FROM {db_prefix}boards AS b WHERE b.id_board = {int:board} AND {query_see_board} LIMIT 1',
        array('board' => (int) $row['id_board']));
    $visible = $smcFunc['db_num_rows']($q) > 0;
    $smcFunc['db_free_result']($q);
    if (!$visible) fatal_lang_error('no_access', false);
    if (empty($row['approved']) || empty($row['topic_approved'])) {
        $boards = boardsAllowedTo('approve_posts');
        if (!in_array(0, array_map('intval', $boards), true) && !in_array((int)$row['id_board'], array_map('intval', $boards), true)) fatal_lang_error('no_access', false);
    }

    // No identity is inserted into the context or HTML on the GET page.
    $context['page_title'] = $is_post ? $GLOBALS['txt']['bsap_reveal_title'] : $GLOBALS['txt']['bsap_reveal_confirm_title'];
    $context['bsap_reveal_msg'] = $id;
    if (!$is_post) {
        $context['sub_template'] = 'bsap_reveal_confirm';
        loadTemplate('BoredSkullAnonymousPosting');
        return;
    }

    // Persist the audit record before showing a name, and fail closed.
    try {
        $audit = $smcFunc['db_insert']('', '{db_prefix}bsap_reveals',
            array('id_msg' => 'int', 'id_author' => 'int', 'id_viewer' => 'int', 'id_board' => 'int', 'viewed_at' => 'int'),
            array($id, (int) $row['id_member'], (int) $user_info['id'], (int) $row['id_board'], time()),
            array('id_reveal'), 1);
        if (!$audit) throw new RuntimeException('Could not save reveal audit');
    } catch (Throwable $e) {
        fatal_error($GLOBALS['txt']['bsap_reveal_audit_failed'], false);
    }
    // Do not log the revealed identity again to the general SMF moderation log.
    // The dedicated, restricted bsap_reveals record is the authoritative audit.
    $context['sub_template'] = 'bsap_reveal';
    $context['bsap_reveal_data'] = array(
        'msg' => $id,
        'member' => (int) $row['id_member'],
        'name' => $smcFunc['htmlspecialchars'](un_htmlspecialchars($row['real_name'] ?? $GLOBALS['txt']['bsap_deleted_member'])),
    );
    loadTemplate('BoredSkullAnonymousPosting');
}

function BSAP_display(&$output,&$message,$counter) {
    global $user_info, $context, $scripturl;
    if (empty($output['id']) || empty($user_info['id']) || !empty($user_info['is_guest']) || !allowedTo('bsap_reveal')) return;
    loadLanguage('BoredSkullAnonymousPosting');
    if (empty($context['bsap_page_authors'][(int)$output['id']])) return;
    // SMF Display.template.php already places messages inside quickModForm.
    // Never nest a POST form inside that form: browsers can silently submit
    // to the wrong action. Use a GET link to a separate, staff-only page.
    $link = $scripturl . '?action=bsapreveal;msg=' . (int) $output['id'];
    $value = '<a class="button" href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($GLOBALS['txt']['bsap_reveal_button'] ?? 'Reveal author (logged)', ENT_QUOTES, 'UTF-8') . '</a>';
    $output['custom_fields']['below_avatar'][] = array('col_name' => 'bsap_reveal', 'value' => $value);
}

function BSAP_illegal_guest_permissions() {
    global $context;
    $context['non_guest_permissions'] = array_merge($context['non_guest_permissions'] ?? array(), array('bsap_use','bsap_reveal'));
}
function BSAP_batch(&$messages, &$posters) {
    global $context, $smcFunc, $user_info;
    $context['bsap_page_authors'] = array();
    if (empty($user_info['id']) || !empty($user_info['is_guest']) || !allowedTo('bsap_reveal') || !$messages) return;
    $r = $smcFunc['db_query']('', 'SELECT id_msg FROM {db_prefix}bsap_authors WHERE id_msg IN ({array_int:ids})', array('ids'=>array_map('intval',$messages)));
    while ($row = $smcFunc['db_fetch_assoc']($r)) $context['bsap_page_authors'][(int)$row['id_msg']] = true;
    $smcFunc['db_free_result']($r);
}
function BSAP_purge_ids($ids) {
    global $smcFunc;
    if (!$ids) return;
    foreach (array('bsap_authors','bsap_reveals') as $table)
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_msg IN ({array_int:ids})', array('ids'=>array_map('intval',$ids)));
}
function BSAP_remove_message($msg, $row, $recycle) {
    if (!$recycle) BSAP_purge_ids(array((int)$msg));
}
function BSAP_prune() {
    global $smcFunc;
    // No dependence on the removeTopics hook's recycle implementation: only missing messages qualify.
    foreach (array('bsap_authors','bsap_reveals') as $table) {
        $r = $smcFunc['db_query']('', 'SELECT DISTINCT a.id_msg FROM {db_prefix}' . $table . ' AS a LEFT JOIN {db_prefix}messages AS m ON (m.id_msg=a.id_msg) WHERE m.id_msg IS NULL LIMIT 1000', array());
        $ids = array(); while ($row=$smcFunc['db_fetch_assoc']($r)) $ids[]=(int)$row['id_msg'];
        $smcFunc['db_free_result']($r); BSAP_purge_ids($ids);
    }
}
function BSAP_remove_topics($topics) { BSAP_prune(); }
function BSAP_audit() {
    global $context, $txt, $smcFunc, $scripturl;
    is_not_guest(); isAllowedTo('admin_forum');
    loadLanguage('BoredSkullAnonymousPosting'); loadTemplate('BoredSkullAnonymousPosting');
    if (isset($_POST['bsap_purge'])) { checkSession('post'); BSAP_prune(); redirectexit('action=admin;area=modsettings;sa=bsapaudit'); }
    $context['page_title']=$txt['bsap_audit_title']; $context['sub_template']='bsap_audit';
    BSAP_admin_tabs();
    $start=max(0, min(10000000, (int)($_GET['start'] ?? 0)));
    $r=$smcFunc['db_query']('', 'SELECT a.id_reveal, a.id_msg, a.id_viewer, a.viewed_at, m.real_name FROM {db_prefix}bsap_reveals AS a LEFT JOIN {db_prefix}members AS m ON (m.id_member=a.id_viewer) ORDER BY a.id_reveal DESC LIMIT {int:offset}, {int:limit}', array('offset'=>$start,'limit'=>101));
    $rows=array(); while ($row=$smcFunc['db_fetch_assoc']($r)) $rows[]=$row; $smcFunc['db_free_result']($r);
    $context['bsap_audit_more']=count($rows)>100; $context['bsap_audit_rows']=array_slice($rows,0,100); $context['bsap_audit_start']=$start;
}
function BSAP_credits() {
    global $context, $txt;
    loadLanguage('BoredSkullAnonymousPosting');
    $context['credits_modifications'][]=sprintf($txt['bsap_credits'], BSAP_VERSION, $txt['by'] ?? 'by');
}
