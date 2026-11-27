<?php
/** Federation protocol, storage and safety functions. */
if (!defined('SMF')) die('No direct access');
// The CLI worker can load this file before Federation.php.
if (!defined('FEDERATION_VERSION')) define('FEDERATION_VERSION', '1.0.2');

/** Avoid fatal errors on servers without mbstring; the deployment guide still recommends it. */
function federation_truncate_text($text, $length)
{
    return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
}
function federation_site()
{
    global $boardurl;
    return rtrim($boardurl, '/');
}
function federation_origin()
{
    $u = parse_url(federation_site());
    return isset($u['scheme'], $u['host']) ? strtolower($u['host']) : '';
}
function federation_url($sa, $extra = '')
{
    global $scripturl;
    return $scripturl . '?action=federation;sa=' . rawurlencode($sa) . $extra;
}
function federation_message($key, $fallback)
{
    global $txt;
    if (function_exists('loadLanguage')) loadLanguage('Federation');
    return isset($txt[$key]) ? $txt[$key] : $fallback;
}
/** Drop SMF's output buffers so ob_sessrewrite cannot add PHPSESSID to JSON URLs. */
function federation_clean_output()
{
    while (ob_get_level() > 0) @ob_end_clean();
}
function federation_error($status, $reason)
{
    federation_clean_output();
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(array('error' => $reason));
    exit;
}
function federation_json($data, $type = 'application/activity+json', $status = 200)
{
    federation_clean_output();
    http_response_code($status);
    header('Content-Type: ' . $type . '; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}
function federation_https_ready()
{
    $u = parse_url(federation_site());
    return !empty($u['scheme']) && strtolower($u['scheme']) === 'https';
}
function federation_guest_public_board($id)
{
    global $smcFunc, $modSettings;
    if ($id < 1) return false;
    // With guest access switched off, no board is publicly readable.
    if (isset($modSettings['allow_guestAccess']) && empty($modSettings['allow_guestAccess'])) return false;
    $q = $smcFunc['db_query']('', '
        SELECT id_board, member_groups, deny_member_groups, redirect, id_parent
        FROM {db_prefix}boards WHERE id_board = {int:id} LIMIT 1', array('id' => (int) $id));
    $b = $smcFunc['db_fetch_assoc']($q);
    $smcFunc['db_free_result']($q);
    if (!$b || !empty($b['redirect'])) return false;
    $groups = array_map('intval', explode(',', (string) $b['member_groups']));
    $denied = array_map('intval', explode(',', (string) $b['deny_member_groups']));
    if (!in_array(-1, $groups, true) || in_array(-1, $denied, true)) return false;
    // A parent hidden from guests makes a child unsuitable for publication.
    if (!empty($b['id_parent'])) return federation_guest_public_board((int) $b['id_parent']);
    return true;
}
/** Re-evaluate each published object's topic access on every read. */
function federation_topic_is_public($topic, $actor_id = 0)
{
    global $smcFunc, $modSettings;
    if ((int) $topic < 1) return false;
    $q = $smcFunc['db_query']('', 'SELECT id_board, approved FROM {db_prefix}topics WHERE id_topic = {int:id} LIMIT 1', array('id' => (int) $topic));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if ($actor_id && $row) {
        $actor = federation_actor_by_id($actor_id);
        if (!$actor || ($actor['actor_type'] === 'board' && (int)$actor['id_local'] !== (int)$row['id_board'])) return false;
    }
    return !!($row && !empty($row['approved']) && (empty($modSettings['recycle_enable']) || (int)$row['id_board'] !== (int)$modSettings['recycle_board']) && federation_guest_public_board((int) $row['id_board']));
}
function federation_actor_url($kind, $id)
{
    return federation_url('actor', ';kind=' . rawurlencode($kind) . ';id=' . (int) $id);
}
function federation_actor_row($kind, $id)
{
    global $smcFunc;
    if (!in_array($kind, array('board', 'member'), true) || $id < 1) return null;
    $q = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}federation_actors WHERE actor_type = {string:kind} AND id_local = {int:id} AND enabled = {int:yes} LIMIT 1', array('kind' => $kind, 'id' => $id, 'yes' => 1));
    $row = $smcFunc['db_fetch_assoc']($q);
    $smcFunc['db_free_result']($q);
    if (!$row) return null;
    if ($kind === 'board' && !federation_guest_public_board($id)) return null;
    return $row;
}
function federation_actor_by_id($actor_id, $withdrawal = false)
{
    global $smcFunc;
    $q = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}federation_actors WHERE id_actor = {int:id}' . ($withdrawal ? '' : ' AND enabled = 1') . ' LIMIT 1', array('id' => (int) $actor_id));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    return $row && ($withdrawal || ($row['actor_type'] !== 'board' || federation_guest_public_board((int) $row['id_local']))) ? $row : null;
}
function federation_private_key($stored)
{
    global $auth_secret;
    if (empty($auth_secret) || !function_exists('openssl_decrypt')) return false;
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 29) return false;
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $auth_secret, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain !== false ? $plain : false;
}
function federation_encrypt_key($pem)
{
    global $auth_secret;
    if (empty($auth_secret) || !function_exists('openssl_encrypt')) throw new RuntimeException(federation_message('federation_error_smf_auth_secret_and_openssl_are_required', 'SMF auth_secret and OpenSSL are required'));
    $nonce = random_bytes(12); $tag = '';
    $value = openssl_encrypt($pem, 'aes-256-gcm', hash('sha256', $auth_secret, true), OPENSSL_RAW_DATA, $nonce, $tag);
    if ($value === false) throw new RuntimeException(federation_message('federation_error_could_not_encrypt_actor_key', 'Could not encrypt actor key'));
    return base64_encode($nonce . $tag . $value);
}
function federation_enable_actor($kind, $id)
{
    global $smcFunc, $modSettings;
    if (!federation_https_ready()) throw new RuntimeException(federation_message('federation_error_a_https_forum_url_is_required', 'A HTTPS forum URL is required'));
    if ($kind === 'board' && !federation_guest_public_board($id)) throw new RuntimeException(federation_message('federation_error_board_is_not_guest_public', 'Board is not guest-public'));
    if ($kind === 'member' && empty($modSettings['federation_allow_members'])) throw new RuntimeException(federation_message('federation_error_member_actors_disabled', 'Member actors disabled'));
    $existing = federation_actor_row($kind, $id);
    if ($existing) return $existing;
    // Re-enabling must retain actor ID and signing key, preserving remote followers.
    $q = $smcFunc['db_query']('', 'SELECT id_actor FROM {db_prefix}federation_actors WHERE actor_type = {string:kind} AND id_local = {int:id} LIMIT 1',
        array('kind' => $kind, 'id' => $id));
    $disabled = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if ($disabled) {
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_actors SET enabled = {int:enabled} WHERE id_actor = {int:id}',
            array('enabled' => 1, 'id' => (int) $disabled['id_actor']));
        return federation_actor_row($kind, $id);
    }
    $key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072));
    if (!$key || !openssl_pkey_export($key, $private)) throw new RuntimeException(federation_message('federation_error_unable_to_generate_actor_key', 'Unable to generate actor key'));
    $public = openssl_pkey_get_details($key);
    $smcFunc['db_insert']('replace', '{db_prefix}federation_actors',
        array('actor_type' => 'string-12','id_local' => 'int','enabled' => 'int','public_key' => 'string','private_key' => 'string'),
        array($kind, $id, 1, $public['key'], federation_encrypt_key($private)), array('actor_type', 'id_local'));
    return federation_actor_row($kind, $id);
}
function federation_actor_name($row)
{
    global $smcFunc;
    $id = (int) $row['id_local'];
    if ($row['actor_type'] === 'board') {
        $q = $smcFunc['db_query']('', 'SELECT name FROM {db_prefix}boards WHERE id_board = {int:id}', array('id' => $id));
    } else {
        $q = $smcFunc['db_query']('', 'SELECT real_name AS name FROM {db_prefix}members WHERE id_member = {int:id}', array('id' => $id));
    }
    $item = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    return $item ? federation_decode($item['name']) : ('Actor ' . $id);
}
function federation_actor_document($row)
{
    $kind = $row['actor_type']; $id = (int) $row['id_local'];
    $url = federation_actor_url($kind, $id);
    $handle = ($kind === 'board' ? 'board-' : 'member-') . $id;
    return array(
        '@context' => array('https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1', array('manuallyApprovesFollowers'=>'https://www.w3.org/ns/activitystreams#manuallyApprovesFollowers')),
        'id' => $url, 'type' => $kind === 'board' ? 'Group' : 'Person',
        'manuallyApprovesFollowers' => true, 'preferredUsername' => $handle, 'name' => federation_actor_name($row),
        'inbox' => federation_url('inbox', ';kind=' . $kind . ';id=' . $id),
        'outbox' => federation_url('outbox', ';kind=' . $kind . ';id=' . $id),
        'followers' => federation_url('followers', ';kind=' . $kind . ';id=' . $id),
        'url' => $kind === 'board' ? federation_site() . '/index.php?board=' . $id . '.0' : federation_site() . '/index.php?action=profile;u=' . $id,
        'publicKey' => array('id' => $url . '#main-key', 'owner' => $url, 'publicKeyPem' => $row['public_key']),
    );
}
function federation_endpoint_actor()
{
    $kind = isset($_GET['kind']) && is_string($_GET['kind']) ? $_GET['kind'] : '';
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $row = federation_actor_row($kind, $id);
    if (!$row) federation_error(404, federation_message('federation_error_unknown_actor', 'Unknown actor'));
    return $row;
}
function federation_actor_endpoint() { federation_json(federation_actor_document(federation_endpoint_actor())); }
function federation_webfinger()
{
    $resource = isset($_GET['resource']) && is_string($_GET['resource']) ? $_GET['resource'] : '';
    $domain = preg_quote(federation_origin(), '~');
    if (!preg_match('~^acct:(board|member)-([1-9][0-9]*)@' . $domain . '$~iD', $resource, $m)) federation_error(404, federation_message('federation_error_unknown_account', 'Unknown account'));
    $row = federation_actor_row(strtolower($m[1]), (int) $m[2]);
    if (!$row) federation_error(404, federation_message('federation_error_unknown_account', 'Unknown account'));
    federation_json(array('subject' => 'acct:' . strtolower($m[1]) . '-' . (int) $m[2] . '@' . federation_origin(),
        'links' => array(array('rel' => 'self', 'type' => 'application/activity+json', 'href' => federation_actor_url(strtolower($m[1]), (int) $m[2])))), 'application/jrd+json');
}
function federation_nodeinfo($document)
{
    global $smcFunc;
    if (!$document) federation_json(array('links' => array(array('rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.1', 'href' => federation_url('nodeinfo_document')))), 'application/json');
    $q = $smcFunc['db_query']('', 'SELECT COUNT(*) AS c FROM {db_prefix}federation_actors WHERE enabled = {int:yes} AND actor_type = {string:kind}', array('yes' => 1, 'kind' => 'member'));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    federation_json(array('version' => '2.1', 'software' => array('name' => 'federation', 'version' => FEDERATION_VERSION),
        'protocols' => array('activitypub'), 'services' => array('inbound' => array(), 'outbound' => array()),
        'openRegistrations' => false, 'usage' => array('users' => array('total' => (int) $row['c'])),
        'metadata' => array('smf' => '2.1')), 'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.1#"');
}
function federation_outbox_endpoint()
{
    global $smcFunc;
    $row = federation_endpoint_actor();
    $q = $smcFunc['db_query']('', 'SELECT o.activity_json, o.id_topic, o.id_message, m.body, m.subject, m.approved FROM {db_prefix}federation_objects AS o JOIN {db_prefix}messages AS m ON (m.id_msg = o.id_message) WHERE o.id_actor = {int:id} AND o.visible = {int:visible} ORDER BY o.id_object DESC LIMIT 20',
        array('id' => (int) $row['id_actor'], 'visible' => 1));
    $items = array(); while ($x = $smcFunc['db_fetch_assoc']($q)) { if (!federation_topic_is_public((int) $x['id_topic'], (int)$row['id_actor'])) continue; $a = json_decode($x['activity_json'], true); if (is_array($a) && !empty($x['approved']) && isset($a['object']['content']) && $a['object']['content'] === federation_content($x['subject'], $x['body'])) $items[] = $a; }
    $smcFunc['db_free_result']($q);
    federation_json(array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => federation_url('outbox', ';kind=' . $row['actor_type'] . ';id=' . $row['id_local']), 'type' => 'OrderedCollection', 'totalItems' => count($items), 'orderedItems' => $items));
}
function federation_followers_endpoint()
{
    global $smcFunc;
    $row = federation_endpoint_actor();
    $q = $smcFunc['db_query']('', 'SELECT COUNT(*) AS c FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND status = {string:status}', array('id' => (int) $row['id_actor'], 'status' => 'active'));
    $x = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    federation_json(array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => federation_url('followers', ';kind=' . $row['actor_type'] . ';id=' . $row['id_local']), 'type' => 'Collection', 'totalItems' => (int) $x['c']));
}
function federation_object_endpoint()
{
    global $smcFunc;
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id < 1) federation_error(404, federation_message('federation_error_unknown_object', 'Unknown object'));
    $q = $smcFunc['db_query']('', 'SELECT o.object_json, o.id_actor, o.id_topic, m.body, m.subject, m.approved FROM {db_prefix}federation_objects AS o JOIN {db_prefix}messages AS m ON (m.id_msg = o.id_message) WHERE o.id_object = {int:id} AND o.visible = {int:visible} LIMIT 1', array('id' => $id, 'visible' => 1));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if (!$row || !federation_actor_by_id((int) $row['id_actor']) || !federation_topic_is_public((int) $row['id_topic'], (int)$row['id_actor'])) federation_error(404, federation_message('federation_error_unknown_object', 'Unknown object'));
    $obj = json_decode($row['object_json'], true);
    if (!is_array($obj) || empty($row['approved']) || ($obj['content'] ?? null) !== federation_content($row['subject'], $row['body'])) federation_error(404, federation_message('federation_error_invalid_or_outdated_object', 'Invalid or outdated object'));
    federation_json($obj);
}

/** A private, read-only view of reviewed remote activity delivered to this member. */
function federation_feed_endpoint()
{
    global $user_info, $modSettings, $context, $smcFunc, $txt;
    if (empty($user_info['id']) || empty($modSettings['federation_allow_members'])) fatal_lang_error('no_access', false);
    $actor = federation_actor_row('member', (int) $user_info['id']);
    if (!$actor) fatal_lang_error('no_access', false);
    loadLanguage('Federation'); loadTemplate('Federation');
    loadCSSFile('federation.css', array('default_theme' => true, 'minimize' => true, 'seed' => FEDERATION_VERSION . '-ui2'), 'federation_css');
    $q = $smcFunc['db_query']('', 'SELECT activity_type, remote_actor, payload, received FROM {db_prefix}federation_inbox WHERE id_actor = {int:id} AND status = {string:status} ORDER BY id_inbox DESC LIMIT 50',
        array('id' => (int) $actor['id_actor'], 'status' => 'approved'));
    $context['federation_feed'] = array();
    while ($row = $smcFunc['db_fetch_assoc']($q)) {
        $activity = json_decode($row['payload'], true);
        $object = is_array($activity) && isset($activity['object']) && is_array($activity['object']) ? $activity['object'] : array();
        $content = isset($object['content']) && is_string($object['content']) ? $object['content'] : '';
        $context['federation_feed'][] = array('type' => $row['activity_type'], 'actor' => $row['remote_actor'],
            'received' => (int) $row['received'], 'content' => (function_exists('mb_substr') ? mb_substr(strip_tags($content), 0, 2000, 'UTF-8') : substr(strip_tags($content), 0, 2000)));
    }
    $smcFunc['db_free_result']($q);
    $context['page_title'] = $txt['federation_feed'];
    $context['sub_template'] = 'federation_feed';
}

function federation_decode($text)
{
    return html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function federation_content($subject, $body)
{
    $body = federation_decode(preg_replace('~<br\s*/?>~i', "\n", (string)$body));
    $body = preg_replace_callback('~\[(url|iurl)=([^\]\r\n]+)\](.*?)\[/\1\]~is', function($m) {
        return $m[3] . ' (' . trim($m[2], "\"'") . ')';
    }, $body);
    $body = preg_replace('~\[/?(?:b|i|u|s|url|iurl|img|quote|code|list|li|size|color|font|center|left|right|pre|nobbc|spoiler|table|tr|td|sup|sub|tt)(?:=[^\]\r\n]*)?\]~i', '', $body);
    $subject = federation_decode($subject);
    if (function_exists('censorText')) { censorText($subject, true); censorText($body, true); }
    $body = federation_truncate_text($body, 10000);
    return '<p><strong>' . htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p><p>' . nl2br(htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
}
