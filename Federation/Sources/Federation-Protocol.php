<?php
/** Activity delivery, follow lifecycle, and incoming moderation. */
if (!defined('SMF')) die('No direct access');

function federation_net()
{
    global $sourcedir;
    require_once($sourcedir . '/Federation-Net.php');
}
function federation_event($kind, $details)
{
    global $smcFunc;
    $smcFunc['db_insert']('insert', '{db_prefix}federation_log',
        array('logged' => 'int', 'event_type' => 'string-32', 'details' => 'string-255'),
        array(time(), substr($kind, 0, 32), federation_truncate_text(strip_tags((string) $details), 255)), array('id_log'));
}
function federation_enqueue($actor, $inbox, $payload)
{
    global $smcFunc;
    federation_net();
    if (!federation_safe_target($inbox) || !is_array($payload)) return false;
    $serialized = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!$serialized || strlen($serialized) > 262144) return false;
    $smcFunc['db_insert']('insert', '{db_prefix}federation_delivery',
        array('id_actor' => 'int', 'inbox' => 'string-2048', 'payload' => 'string', 'attempts' => 'int', 'next_try' => 'int', 'status' => 'string-16', 'last_error' => 'string-255'),
        array((int) $actor['id_actor'], $inbox, $serialized, 0, time(), 'queued', ''), array('id_delivery'));
    return true;
}
function federation_public_fanout($actor, $activity)
{
    $followers = federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local']);
    $public = 'https://www.w3.org/ns/activitystreams#Public';
    $type = $activity['type'] ?? '';
    if (!in_array($type, array('Create','Update','Delete'), true) || !in_array($public, (array)($activity['to'] ?? array()), true) || !in_array($followers, (array)($activity['cc'] ?? array()), true)) return false;
    return $type === 'Delete' || (in_array($public, (array)($activity['object']['to'] ?? array()), true) && in_array($followers, (array)($activity['object']['cc'] ?? array()), true));
}
function federation_follower_destinations($rows, $shared_allowed)
{
    $destinations = array();
    foreach ($rows as $row) {
        $personal = $row['inbox'];
        if (!federation_safe_target($personal)) continue;
        $shared = $shared_allowed ? federation_shared_inbox($row['remote_actor'], $personal, $row['shared_inbox'] ?? '') : '';
        $destinations[$shared ?: $personal] = true;
    }
    return array_keys($destinations);
}
function federation_queue_followers($actor, $activity)
{
    global $smcFunc;
    federation_net();
    $q = $smcFunc['db_query']('', 'SELECT remote_actor, inbox, shared_inbox FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND status = {string:active}',
        array('id' => (int) $actor['id_actor'], 'active' => 'active'));
    $rows = array(); while ($row = $smcFunc['db_fetch_assoc']($q)) $rows[] = $row;
    $smcFunc['db_free_result']($q);
    foreach (federation_follower_destinations($rows, federation_public_fanout($actor, $activity)) as $inbox) federation_enqueue($actor, $inbox, $activity);
}
/**
 * Return the local object ID for content that must remain public until delivery.
 * A false return rejects malformed/non-local outgoing Create/Update activities.
 * Delete is intentionally permitted after removal (and returns null).
 */
function federation_outbound_object_id($payload)
{
    if (!is_array($payload) || !isset($payload['type']) || !in_array($payload['type'], array('Create', 'Update'), true)) return null;
    if (empty($payload['object']['id']) || !is_string($payload['object']['id'])) return false;
    $object_url = $payload['object']['id'];
    $prefix = federation_url('object', ';id=');
    if (strpos($object_url, $prefix) !== 0) return false;
    $id = substr($object_url, strlen($prefix));
    if (!preg_match('/^[1-9][0-9]*$/D', $id)) return false;
    return (int) $id;
}

/** Refuse queued publication if content has since become private or withdrawn. */
function federation_delivery_content_public($actor_id, $payload)
{
    global $smcFunc;
    $object_id = federation_outbound_object_id($payload);
    if ($object_id === null) return true;
    if ($object_id === false) return false;
    $q = $smcFunc['db_query']('', 'SELECT o.id_topic, o.visible, o.object_json, m.body, m.subject, m.approved FROM {db_prefix}federation_objects AS o JOIN {db_prefix}messages AS m ON (m.id_msg = o.id_message) WHERE o.id_object = {int:object} AND o.id_actor = {int:actor} LIMIT 1',
        array('object' => $object_id, 'actor' => (int) $actor_id));
    $row = $smcFunc['db_fetch_assoc']($q);
    $smcFunc['db_free_result']($q);
    if (!$row || empty($row['visible']) || empty($row['approved']) || !federation_topic_is_public((int)$row['id_topic'], (int)$actor_id)) return false;
    $current=json_decode($row['object_json'],true);
    return is_array($current) && ($current['content'] ?? null) === federation_content($row['subject'], $row['body']) && ($current['content'] ?? null) === ($payload['object']['content'] ?? null) && ($current['name'] ?? null) === ($payload['object']['name'] ?? null);
}

/** Run from administrator queue screen or SMF scheduled background job. */
function federation_process_queue($limit = 10, $budget = 0)
{
    global $smcFunc, $modSettings;
    if (empty($modSettings['federation_enabled'])) return 0;
    federation_net();
    $started = microtime(true);
    if (PHP_SAPI === 'cli') { federation_reconcile_topics(); federation_prune(); }
    $limit = max(1, min(25, (int) $limit));
    // Recover deliveries left claimed by a crashed worker (the HTTP timeout is 15 seconds).
    $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:queued} WHERE status = {string:working} AND next_try <= {int:now}',
        array('queued' => 'queued', 'working' => 'working', 'now' => time()));
    $q = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}federation_delivery WHERE status = {string:queued} AND next_try <= {int:now} ORDER BY next_try, id_delivery LIMIT {int:limit}',
        array('queued' => 'queued', 'now' => time(), 'limit' => $limit));
    $jobs = array(); while ($row = $smcFunc['db_fetch_assoc']($q)) $jobs[] = $row;
    $smcFunc['db_free_result']($q);
    $handled = 0;
    foreach ($jobs as $job) {
        if ($budget && microtime(true) - $started >= $budget - 5) break;
        $id = (int) $job['id_delivery']; $attempt = (int) $job['attempts'] + 1;
        // Claim by ID and status so parallel runners cannot send the same delivery.
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:working}, next_try = {int:recover_at} WHERE id_delivery = {int:id} AND status = {string:queued}',
            array('working' => 'working', 'queued' => 'queued', 'id' => $id, 'recover_at' => time() + 300));
        if ((int) $smcFunc['db_affected_rows']() !== 1) continue;
        $payload = json_decode($job['payload'], true);
        if (is_array($payload) && ($payload['type'] ?? '') === 'Create') {
            $object_id = federation_outbound_object_id($payload);
            if ($object_id) {
                $q = $smcFunc['db_query']('', 'SELECT object_json FROM {db_prefix}federation_objects WHERE id_object = {int:id} AND id_actor = {int:actor} AND visible = 1', array('id'=>$object_id,'actor'=>(int)$job['id_actor']));
                $r = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
                if ($r && ($current = json_decode($r['object_json'], true))) { $payload['object'] = $current; $job['payload'] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
            }
        }
        $actor = federation_actor_by_id((int) $job['id_actor'], ($payload['type'] ?? '') === 'Delete');
        // Fail closed if a scheduled Create/Update is no longer public.
        if (!$actor || !is_array($payload) || !federation_delivery_content_public((int) $job['id_actor'], $payload) || !federation_delivery_recipient_active($job, $payload)) {
            $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:failed}, attempts = {int:attempts}, last_error = {string:error} WHERE id_delivery = {int:id}',
                array('failed' => 'failed', 'attempts' => $attempt, 'error' => federation_message('federation_error_delivery_unavailable', 'Local actor, public object or active recipient no longer available'), 'id' => $id));
            $handled++;
            continue;
        }
        $headers = federation_signature_headers($actor, $job['inbox'], $job['payload']);
        $status = 0; $error = federation_message('federation_error_actor_no_longer_available', 'Actor no longer available');
        if ($headers) list($status, $unused, $error) = federation_http($job['inbox'], 'POST', $job['payload'], $headers, 262144, $budget ? max(1, min(5, (int)($budget - (microtime(true)-$started)))) : 15);
        if ($status >= 200 && $status < 300) {
            $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:done}, attempts = {int:attempts}, last_error = {string:empty}, next_try = {int:now} WHERE id_delivery = {int:id}',
                array('done' => 'done', 'attempts' => $attempt, 'empty' => '', 'now'=>time(), 'id' => $id));
        } else {
            $retry = $attempt < 6 && ($status === 0 || $status === 408 || $status === 429 || $status >= 500);
            $state = $retry ? 'queued' : 'failed';
            $delay = min(86400, (int) pow(2, min($attempt, 10)) * 60);
            $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_delivery SET status = {string:state}, attempts = {int:attempts}, next_try = {int:next}, last_error = {string:error} WHERE id_delivery = {int:id}',
                array('state' => $state, 'attempts' => $attempt, 'next' => time() + $delay, 'error' => federation_truncate_text('HTTP ' . $status . ': ' . $error, 255), 'id' => $id));
            federation_event('delivery_failed', 'Delivery ' . $id . ': HTTP ' . $status . ' (' . $state . ')');
        }
        $handled++;
    }
    return $handled;
}
function federation_follow_activity($local, $remote, $undo = false)
{
    $self = federation_actor_url($local['actor_type'], (int) $local['id_local']);
    $id = $self . '#follow-' . hash('sha256', $self . '|' . $remote);
    $follow = array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $id, 'type' => 'Follow', 'actor' => $self, 'object' => $remote);
    return $undo ? array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $self . '#undo-' . bin2hex(random_bytes(12)), 'type' => 'Undo', 'actor' => $self, 'object' => $follow) : $follow;
}
function federation_follow_remote($local, $target)
{
    global $smcFunc;
    federation_net();
    $target = federation_resolve_handle($target);
    $remote = $target ? federation_remote_actor($target, $local) : false;
    if (!$remote) throw new RuntimeException(federation_message('federation_error_remote_actor_not_discoverable_or_blocked', 'Remote actor not discoverable or blocked'));
    $smcFunc['db_insert']('replace', '{db_prefix}federation_following',
        array('id_actor' => 'int', 'remote_hash' => 'string-64', 'remote_actor' => 'string', 'inbox' => 'string', 'status' => 'string-16'),
        array((int) $local['id_actor'], hash('sha256', $target), $target, $remote['inbox'], 'requested'), array('id_actor', 'remote_hash'));
    federation_enqueue($local, $remote['inbox'], federation_follow_activity($local, $target));
    federation_event('follow_requested', 'Outbound follow requested');
}
function federation_unfollow_remote($local, $target)
{
    global $smcFunc;
    $q = $smcFunc['db_query']('', 'SELECT inbox FROM {db_prefix}federation_following WHERE id_actor = {int:id} AND remote_actor = {string:actor}',
        array('id' => (int) $local['id_actor'], 'actor' => $target));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if (!$row) return;
    federation_enqueue($local, $row['inbox'], federation_follow_activity($local, $target, true));
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_following WHERE id_actor = {int:id} AND remote_actor = {string:actor}', array('id' => (int) $local['id_actor'], 'actor' => $target));
}
/** Protect the signing endpoint before performing remote discovery work. */
function federation_check_inbox_rate()
{
    global $smcFunc;
    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) federation_error(429, federation_message('federation_error_unknown_request_origin', 'Unknown request origin'));
    $key = hash('sha256', $ip);
    $q = $smcFunc['db_query']('', 'SELECT window_start, hits FROM {db_prefix}federation_rate WHERE ip_hash = {string:hash} LIMIT 1', array('hash' => $key));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    $now = time();
    if (!$row) {
        $smcFunc['db_insert']('ignore', '{db_prefix}federation_rate', array('ip_hash' => 'string-64', 'window_start' => 'int', 'hits' => 'int'),
            array($key, $now, 1), array('ip_hash'));
    } elseif ((int)$row['window_start'] <= $now - 60) {
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_rate SET window_start = {int:now}, hits = {int:hits} WHERE ip_hash = {string:hash}',
            array('now' => $now, 'hits' => 1, 'hash' => $key));
    } elseif ((int)$row['hits'] >= 30) {
        header('Retry-After: 60');
        federation_error(429, federation_message('federation_error_rate_limit_exceeded', 'Rate limit exceeded'));
    } else {
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_rate SET hits = hits + 1 WHERE ip_hash = {string:hash}', array('hash' => $key));
    }
}
function federation_inbox_endpoint()
{
    global $modSettings, $smcFunc;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') federation_error(405, federation_message('federation_error_post_required', 'POST required'));
    if (empty($modSettings['federation_inbound'])) federation_error(404, federation_message('federation_error_inbound_federation_disabled', 'Inbound federation disabled'));
    federation_check_inbox_rate();
    $local = federation_endpoint_actor();
    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($length > 262144) federation_error(413, federation_message('federation_error_activity_too_large', 'Activity too large'));
    $raw = file_get_contents('php://input', false, null, 0, 262145);
    if (!is_string($raw) || strlen($raw) > 262144) federation_error(413, federation_message('federation_error_activity_too_large', 'Activity too large'));
    $activity = json_decode($raw, true);
    if (!is_array($activity) || empty($activity['id']) || !is_string($activity['id']) || strlen($activity['id']) > 2048
        || empty($activity['type']) || !is_string($activity['type'])) federation_error(400, federation_message('federation_error_invalid_activity', 'Invalid activity'));
    federation_net();
    $remote = federation_verify_inbox($raw, $activity, $local);
    if (!$remote) federation_error(401, federation_message('federation_error_invalid_http_signature', 'Invalid HTTP signature'));
    $type = $activity['type'];
    if (!in_array($type, array('Follow', 'Undo', 'Accept', 'Reject', 'Create', 'Update', 'Delete', 'Like', 'Announce'), true)) federation_error(422, federation_message('federation_error_unsupported_activity', 'Unsupported activity'));
    // Duplicate activity IDs are ignored. The unique database key enforces this concurrently.
    $q = $smcFunc['db_query']('', 'SELECT id_inbox FROM {db_prefix}federation_inbox WHERE id_actor = {int:local} AND activity_hash = {string:id} LIMIT 1', array('local' => (int) $local['id_actor'], 'id' => hash('sha256', $activity['id'])));
    $duplicate = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if ($duplicate) federation_json(array('accepted' => true), 'application/json', 202);
    $target = federation_actor_url($local['actor_type'], (int) $local['id_local']);
    if ($type === 'Follow' && (!isset($activity['object']) || $activity['object'] !== $target)) federation_error(422, federation_message('federation_error_wrong_followed_actor', 'Wrong followed actor'));
    // Except follow-related replies, all remote activities are quarantined for manual moderation.
    $status = 'pending';
    if ($type === 'Undo' && federation_undo_follow($local, $activity)) $status = 'handled';
    if (in_array($type, array('Accept', 'Reject'), true)) {
        $object = isset($activity['object']) && is_array($activity['object']) ? $activity['object'] : array();
        if (federation_matches_follow_reply($local, $activity)) {
            $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_following SET status = {string:status} WHERE id_actor = {int:id} AND remote_actor = {string:actor} AND status = {string:requested}',
                array('requested'=>'requested','status' => $type === 'Accept' ? 'active' : 'rejected', 'id' => (int) $local['id_actor'], 'actor' => $activity['actor']));
            $status = 'handled';
        }
    }
    if ($status === 'pending') federation_domain_quota(parse_url($activity['actor'], PHP_URL_HOST));
    $smcFunc['db_insert']('ignore', '{db_prefix}federation_inbox',
        array('id_actor' => 'int', 'activity_hash' => 'string-64', 'activity_id' => 'string', 'remote_actor' => 'string', 'activity_type' => 'string-32', 'payload' => 'string', 'status' => 'string-16', 'received' => 'int'),
        array((int) $local['id_actor'], hash('sha256', $activity['id']), $activity['id'], $activity['actor'], $type, $raw, $status, time()), array('id_inbox'));
    federation_event('inbound_' . strtolower($type), 'Received signed ' . $type . ', status ' . $status);
    federation_json(array('accepted' => true), 'application/json', 202);
}
function federation_review_inbox($id, $approve)
{
    global $smcFunc;
    $q = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}federation_inbox WHERE id_inbox = {int:id} AND status = {string:pending} LIMIT 1', array('id' => (int) $id, 'pending' => 'pending'));
    $row = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if (!$row) throw new RuntimeException(federation_message('federation_error_activity_no_longer_pending', 'Activity no longer pending'));
    $actor = federation_actor_by_id((int) $row['id_actor']);
    if (!$actor) throw new RuntimeException(federation_message('federation_error_local_actor_unavailable', 'Local actor unavailable'));
    $payload = json_decode($row['payload'], true);
    if (!is_array($payload) || empty($payload['actor']) || $payload['actor'] !== $row['remote_actor']) throw new RuntimeException(federation_message('federation_error_invalid_activity_state', 'Invalid activity state'));
    if ($approve && $row['activity_type'] === 'Follow') {
        federation_net();
        $remote = federation_remote_actor($row['remote_actor'], $actor);
        if (!$remote) throw new RuntimeException(federation_message('federation_error_follower_is_no_longer_discoverable', 'Follower is no longer discoverable'));
        $q = $smcFunc['db_query']('', 'SELECT id_inbox FROM {db_prefix}federation_inbox WHERE id_inbox = {int:id} AND status = {string:pending}', array('id'=>(int)$id,'pending'=>'pending'));
        $pending = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
        if (!$pending) throw new RuntimeException(federation_message('federation_error_follow_no_longer_pending', 'Follow no longer pending'));
        $smcFunc['db_insert']('replace', '{db_prefix}federation_followers',
            array('id_actor' => 'int', 'remote_hash' => 'string-64', 'remote_actor' => 'string', 'inbox' => 'string', 'status' => 'string-16', 'follow_id'=>'string', 'shared_inbox'=>'string'),
            array((int) $actor['id_actor'], hash('sha256', $row['remote_actor']), $row['remote_actor'], $remote['inbox'], 'active', $row['activity_id'], federation_shared_inbox($row['remote_actor'], $remote['inbox'], $remote['endpoints']['sharedInbox'] ?? '')), array('id_actor', 'remote_hash'));
        $local_url = federation_actor_url($actor['actor_type'], (int) $actor['id_local']);
        $accept = array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $local_url . '#accept-' . $id,
            'type' => 'Accept', 'actor' => $local_url, 'object' => $payload);
        federation_enqueue($actor, $remote['inbox'], $accept);
    } elseif ($approve && $row['activity_type'] === 'Undo') {
        federation_undo_follow($actor, $payload);
    }
    // Deliberately no insertion of remote Create/Update/Delete into native SMF messages.
    // A third-party mod can opt into this reviewed activity via this extension hook.
    if ($approve && function_exists('call_integration_hook')) {
        $safe_event = array('id' => $row['activity_id'], 'type' => $row['activity_type'],
            'actor' => $row['remote_actor'], 'received' => (int) $row['received']);
        call_integration_hook('integrate_federation_approved_activity', array(&$safe_event));
    }
    $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_inbox SET status = {string:status} WHERE id_inbox = {int:id} AND status = {string:pending}',
        array('status' => $approve ? 'approved' : 'rejected', 'id' => (int) $id, 'pending' => 'pending'));
    federation_event('review', 'Activity #' . $id . ' ' . ($approve ? 'approved' : 'rejected'));
}
function federation_publish_topic($board, $topic, $message, $only_actor = 0)
{
    global $smcFunc, $modSettings, $user_info;
    if (empty($modSettings['federation_enabled']) || empty($modSettings['federation_outbound']) || !federation_guest_public_board($board) || !federation_topic_is_public($topic)) return false;
    $q = $smcFunc['db_query']('', '
        SELECT m.id_msg, m.id_member, m.subject, m.body, m.poster_time, m.approved, t.id_board, t.approved AS topic_approved
        FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
        WHERE m.id_msg = {int:msg} AND m.id_topic = {int:topic} AND t.id_board = {int:board} AND t.id_first_msg = m.id_msg LIMIT 1',
        array('msg' => $message, 'topic' => $topic, 'board' => $board));
    $post = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    if (!$post || empty($post['approved']) || empty($post['topic_approved'])) return false;
    $actors = array();
    $board_actor = federation_actor_row('board', $board);
    if ($board_actor) $actors[] = $board_actor;
    if (!empty($modSettings['federation_allow_members']) && !empty($modSettings['federation_member_outbound']) && !empty($post['id_member'])) {
        $member_actor = federation_actor_row('member', (int) $post['id_member']);
        if ($member_actor) $actors[] = $member_actor;
    }
    if (!$actors) return false;
    $html = federation_content($post['subject'], $post['body']);
    foreach ($actors as $actor) {
        if ($only_actor && (int)$actor['id_actor'] !== (int)$only_actor) continue;
        // Serialize publication for this actor. Never reuse an object ID after Delete.
        if (!$smcFunc['db_transaction']('begin')) continue;
        $lock = $smcFunc['db_query']('', 'SELECT id_actor FROM {db_prefix}federation_actors WHERE id_actor = {int:id} AND enabled = 1 FOR UPDATE', array('id'=>(int)$actor['id_actor']));
        $locked = $smcFunc['db_fetch_assoc']($lock); $smcFunc['db_free_result']($lock);
        if (!$locked || !federation_topic_is_public($topic, (int)$actor['id_actor'])) { $smcFunc['db_transaction']('rollback'); continue; }
        $q = $smcFunc['db_query']('', 'SELECT id_object, visible FROM {db_prefix}federation_objects WHERE id_actor = {int:id} AND id_message = {int:msg} LIMIT 1',
            array('id' => (int) $actor['id_actor'], 'msg' => $message));
        $existing = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
        if ($existing && !empty($existing['visible'])) { $smcFunc['db_transaction']('commit'); continue; }
        if ($existing) {
            // Old object URL stays unavailable; pending Deletes still refer to the old URL.
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_objects WHERE id_object = {int:id} AND visible = 0', array('id'=>(int)$existing['id_object']));
            if ((int)$smcFunc['db_affected_rows']() !== 1) { $smcFunc['db_transaction']('rollback'); continue; }
        }
        $actorurl = federation_actor_url($actor['actor_type'], (int) $actor['id_local']);
        $smcFunc['db_insert']('insert', '{db_prefix}federation_objects',
            array('id_actor' => 'int', 'id_topic' => 'int', 'id_message' => 'int', 'visible' => 'int', 'object_json' => 'string', 'activity_json' => 'string'),
            array((int) $actor['id_actor'], $topic, $message, 0, '{}', '{}'), array('id_object'));
        $objectid = (int) $smcFunc['db_insert_id']('{db_prefix}federation_objects', 'id_object');
        if ($objectid < 1 || ($existing && $objectid === (int)$existing['id_object'])) { $smcFunc['db_transaction']('rollback'); continue; }
        $objecturl = federation_url('object', ';id=' . $objectid);
        $object = array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $objecturl, 'type' => 'Note',
            'attributedTo' => $actorurl, 'content' => $html, 'name' => federation_decode($post['subject']), 'published'=>gmdate('c', (int)$post['poster_time']),
            'url' => federation_site() . '/index.php?topic=' . $topic . '.0', 'to' => array('https://www.w3.org/ns/activitystreams#Public'), 'cc' => array(federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local'])));
        $activity = array('@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $objecturl . '#create',
            'type' => 'Create', 'actor' => $actorurl, 'object' => $object, 'to' => array('https://www.w3.org/ns/activitystreams#Public'), 'cc' => array(federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local'])));
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_objects SET object_json = {string:object}, activity_json = {string:activity}, visible = {int:visible} WHERE id_object = {int:id}',
            array('object' => json_encode($object), 'activity' => json_encode($activity), 'visible' => 1, 'id' => $objectid));
        if (!$smcFunc['db_transaction']('commit')) continue;
        federation_queue_followers($actor, $activity);
        federation_event('outbound_create', 'Published public topic ' . $topic);
    }
    return true;
}

/** Broadcast edited public content only if it has already been federated. */
function federation_update_message($message)
{
    global $smcFunc;
    $q = $smcFunc['db_query']('', 'SELECT id_object, id_actor, id_topic, object_json FROM {db_prefix}federation_objects WHERE id_message = {int:msg} AND visible = {int:yes}',
        array('msg' => (int)$message, 'yes' => 1));
    $items = array(); while($r=$smcFunc['db_fetch_assoc']($q)) $items[]=$r;
    $smcFunc['db_free_result']($q);
    if (!$items) return;
    $q = $smcFunc['db_query']('', 'SELECT body, subject, approved, poster_time FROM {db_prefix}messages WHERE id_msg = {int:msg}',array('msg'=>(int)$message));
    $post = $smcFunc['db_fetch_assoc']($q); $smcFunc['db_free_result']($q);
    foreach ($items as $entry) {
        $actor = federation_actor_by_id((int)$entry['id_actor']);
        if (!$actor || !$post || empty($post['approved']) || !federation_topic_is_public((int) $entry['id_topic'], (int)$entry['id_actor'])) continue;
        $obj = json_decode($entry['object_json'],true);
        if (!is_array($obj)) continue;
        $new_content = federation_content($post['subject'], $post['body']);
        $new_name = federation_decode($post['subject']);
        if (($obj['content'] ?? '') === $new_content && ($obj['name'] ?? '') === $new_name && !empty($obj['published'])) continue;
        $obj['content'] = $new_content;
        $obj['name'] = $new_name;
        $obj['updated'] = gmdate('c');
        $obj['cc'] = array(federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local']));
        if (empty($obj['published'])) $obj['published'] = gmdate('c',(int)$post['poster_time']);
        $actorurl = federation_actor_url($actor['actor_type'],(int)$actor['id_local']);
        $activity = array('@context'=>'https://www.w3.org/ns/activitystreams',
            'id'=>$obj['id'].'#update-'.bin2hex(random_bytes(12)), 'type'=>'Update',
            'actor'=>$actorurl,'object'=>$obj, 'to'=>array('https://www.w3.org/ns/activitystreams#Public'), 'cc'=>array(federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local'])));
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_objects SET object_json = {string:object}, activity_json = {string:activity} WHERE id_object = {int:id}',
            array('object'=>json_encode($obj),'activity'=>json_encode(array_replace($activity,array('type'=>'Create','id'=>$obj['id'].'#create'))),'id'=>(int)$entry['id_object']));
        federation_queue_followers($actor,$activity);
    }
}
/** Mark local objects as invisible and send signed Delete to existing followers. */
function federation_delete_message($message, $only_actor = 0)
{
    global $smcFunc;
    $q=$smcFunc['db_query']('', 'SELECT id_object, id_actor, object_json FROM {db_prefix}federation_objects WHERE id_message = {int:msg} AND visible = {int:yes}' . ($only_actor ? ' AND id_actor = {int:actor}' : ''),
        array('msg'=>(int)$message,'yes'=>1,'actor'=>(int)$only_actor));
    $items=array(); while($r=$smcFunc['db_fetch_assoc']($q)) $items[]=$r;
    $smcFunc['db_free_result']($q);
    foreach($items as $item) {
        $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_objects SET visible = {int:off} WHERE id_object = {int:id} AND visible = 1',
            array('off'=>0,'id'=>(int)$item['id_object']));
        if ((int)$smcFunc['db_affected_rows']() !== 1) continue;
        $actor=federation_actor_by_id((int)$item['id_actor'], true);
        $obj=json_decode($item['object_json'],true);
        if (!$actor || !is_array($obj) || !isset($obj['id'])) continue;
        $actorurl=federation_actor_url($actor['actor_type'],(int)$actor['id_local']);
        $activity=array('@context'=>'https://www.w3.org/ns/activitystreams', 'id'=>$obj['id'].'#delete-'.bin2hex(random_bytes(12)),
            'type'=>'Delete','actor'=>$actorurl,'object'=>array('id'=>$obj['id'],'type'=>'Tombstone'),
            'to'=>array('https://www.w3.org/ns/activitystreams#Public'), 'cc'=>array(federation_url('followers', ';kind=' . $actor['actor_type'] . ';id=' . (int)$actor['id_local'])));
        federation_queue_followers($actor,$activity);
    }
}

function federation_matches_follow_reply($local, $activity)
{
    $expected = federation_follow_activity($local, $activity['actor']);
    $object = $activity['object'] ?? null;
    if (is_string($object)) return $object === $expected['id'];
    return is_array($object) && ($object['type'] ?? '') === 'Follow' && ($object['actor'] ?? '') === $expected['actor'] && ($object['object'] ?? '') === $expected['object'] && (!isset($object['id']) || $object['id'] === $expected['id']);
}
function federation_undo_follow($local, $activity)
{
    global $smcFunc;
    $object = $activity['object'] ?? null;
    $target = federation_actor_url($local['actor_type'], (int)$local['id_local']);
    if (is_array($object)) {
        if (($object['type'] ?? '') !== 'Follow' || ($object['actor'] ?? '') !== $activity['actor'] || ($object['object'] ?? '') !== $target) return false;
        $id = $object['id'] ?? '';
    } else $id = $object;
    if (!is_string($id) || $id === '') return false;
    $args = array('id'=>(int)$local['id_actor'], 'actor'=>$activity['actor'], 'follow'=>$id);
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND remote_actor = {string:actor} AND (follow_id = {string:follow} OR (follow_id = {string:empty} AND {int:embedded} = 1))', $args + array('empty'=>'','embedded'=>is_array($object)?1:0));
    $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_inbox SET status = {string:handled} WHERE id_actor = {int:id} AND remote_actor = {string:actor} AND activity_id = {string:follow} AND activity_type = {string:type} AND status = {string:pending}', $args + array('handled'=>'handled','type'=>'Follow','pending'=>'pending'));
    return true;
}
function federation_domain_quota($host)
{
    global $smcFunc;
    $key = hash('sha256', 'domain:' . strtolower((string)$host));
    $now = time(); $window = $now - ($now % 86400);
    $smcFunc['db_insert']('ignore', '{db_prefix}federation_rate', array('ip_hash'=>'string-64','window_start'=>'int','hits'=>'int'), array($key,$window,0), array('ip_hash'));
    $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_rate SET hits = 0, window_start = {int:window} WHERE ip_hash = {string:key} AND window_start < {int:window}', array('key'=>$key,'window'=>$window));
    $smcFunc['db_query']('', 'UPDATE {db_prefix}federation_rate SET hits = hits + 1 WHERE ip_hash = {string:key} AND window_start = {int:window} AND hits < 200', array('key'=>$key,'window'=>$window));
    if ((int)$smcFunc['db_affected_rows']() !== 1) federation_error(429, federation_message('federation_error_daily_domain_review_quota_reached', 'Daily domain review quota reached'));
}
function federation_delivery_recipient_active($job, $payload)
{
    global $smcFunc;
    if (!in_array($payload['type'] ?? '', array('Create','Update'),true)) return true;
    federation_net();
    $actor = federation_actor_by_id((int)$job['id_actor']);
    if (!$actor) return false;
    $q=$smcFunc['db_query']('', 'SELECT remote_actor, inbox, shared_inbox FROM {db_prefix}federation_followers WHERE id_actor = {int:id} AND (inbox = {string:inbox} OR shared_inbox = {string:inbox}) AND status = {string:active}', array('id'=>(int)$job['id_actor'],'inbox'=>$job['inbox'],'active'=>'active'));
    $active = false;
    while ($r=$smcFunc['db_fetch_assoc']($q)) {
        if ($r['inbox'] === $job['inbox'] || (federation_public_fanout($actor, $payload) && federation_shared_inbox($r['remote_actor'], $r['inbox'], $r['shared_inbox']) === $job['inbox'])) { $active = true; break; }
    }
    $smcFunc['db_free_result']($q); return $active;
}
function federation_withdraw_topics($topics)
{
    global $smcFunc;
    $topics=array_filter(array_map('intval',$topics)); if (!$topics) return;
    $q=$smcFunc['db_query']('', 'SELECT DISTINCT id_message FROM {db_prefix}federation_objects WHERE id_topic IN ({array_int:ids}) AND visible = 1',array('ids'=>$topics));
    $ids=array(); while($r=$smcFunc['db_fetch_assoc']($q)) $ids[]=(int)$r['id_message']; $smcFunc['db_free_result']($q);
    foreach($ids as $id) federation_delete_message($id);
}
function federation_reconcile_topics($topics = array())
{
    global $smcFunc, $modSettings;
    // Include previously withdrawn objects. Only restore the original opted-in identity.
    $qmax=$smcFunc['db_query']('', 'SELECT MAX(id_object) AS max_id FROM {db_prefix}federation_objects', array());
    $maxrow=$smcFunc['db_fetch_assoc']($qmax); $smcFunc['db_free_result']($qmax);
    $max=(int)($maxrow['max_id'] ?? 0);
    $after=0;
    do {
        $args=array('after'=>$after,'max'=>$max); if ($topics) $args['ids']=$topics;
        $q=$smcFunc['db_query']('', 'SELECT o.id_object, o.id_message, o.id_topic, o.id_actor, o.visible, t.id_board, t.id_first_msg, m.approved AS message_approved FROM {db_prefix}federation_objects AS o LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = o.id_topic) LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = o.id_message) WHERE o.id_object > {int:after} AND o.id_object <= {int:max}' . ($topics?' AND o.id_topic IN ({array_int:ids})':'') . ' ORDER BY o.id_object LIMIT 100',$args);
        $rows=array(); while($r=$smcFunc['db_fetch_assoc']($q)) $rows[]=$r; $smcFunc['db_free_result']($q);
        foreach($rows as $r) {
            $after=(int)$r['id_object'];
            $public = !empty($r['message_approved']) && (int)$r['id_first_msg'] === (int)$r['id_message'] && federation_topic_is_public((int)$r['id_topic'], (int)$r['id_actor']);
            if (!$public) {
                if (!empty($r['visible'])) federation_delete_message((int)$r['id_message'], (int)$r['id_actor']);
            } elseif (!empty($modSettings['federation_outbound'])) {
                if (empty($r['visible'])) federation_publish_topic((int)$r['id_board'], (int)$r['id_topic'], (int)$r['id_message'], (int)$r['id_actor']);
                else federation_update_message((int)$r['id_message']);
            }
        }
    } while (PHP_SAPI === 'cli' && count($rows)===100);
}
function federation_prune()
{
    global $smcFunc;
    $now=time();
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_inbox WHERE received < {int:cutoff} AND status = {string:pending}',array('cutoff'=>$now-7*86400,'pending'=>'pending'));
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_inbox WHERE received < {int:cutoff} AND status != {string:pending}',array('cutoff'=>$now-30*86400,'pending'=>'pending'));
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_delivery WHERE next_try < {int:cutoff} AND status IN ({array_string:states})',array('cutoff'=>$now-30*86400,'states'=>array('done','failed')));
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_log WHERE logged < {int:cutoff}',array('cutoff'=>$now-30*86400));
    $smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_rate WHERE window_start < {int:cutoff}',array('cutoff'=>$now-86400));
}
