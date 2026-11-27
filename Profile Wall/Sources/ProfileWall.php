<?php
/**
 * Profile Wall for SMF 2.1.x
 * Main logic: the wall itself, actions, summary preview and admin pages.
 *
 * @license ISC
 */

if (!defined('SMF'))
	die('No direct access...');

/* --------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------ */

function pw_is_owner($memID)
{
	global $user_info;

	return !$user_info['is_guest'] && (int) $memID === (int) $user_info['id'];
}

function pw_can_moderate()
{
	return allowedTo('profile_wall_moderate') || allowedTo('moderate_forum');
}

function pw_wall_url($memID)
{
	global $scripturl;

	return $scripturl . '?action=profile;u=' . (int) $memID . ';area=wall';
}

function pw_load_assets()
{
	loadTemplate('ProfileWall');
	loadCSSFile('profilewall.css', array('minimize' => true), 'smf_profilewall');
	loadJavaScriptFile('profilewall.js', array('defer' => true, 'minimize' => true), 'smf_profilewall');
}

/**
 * Owner's wall settings plus buddy / ignore lists.
 */
function pw_wall_settings($memID)
{
	global $smcFunc;
	static $cache = array();

	$memID = (int) $memID;
	if (isset($cache[$memID]))
		return $cache[$memID];

	$wall = array(
		'privacy' => 0,
		'owner_approval' => 0,
		'buddies' => array(),
		'ignored' => array(),
		'name' => '',
		'exists' => false,
	);

	$request = $smcFunc['db_query']('', '
		SELECT mem.real_name, mem.buddy_list, mem.pm_ignore_list, w.privacy, w.owner_approval
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}pw_walls AS w ON (w.id_member = mem.id_member)
		WHERE mem.id_member = {int:member}
		LIMIT 1',
		array('member' => $memID)
	);
	if ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$wall['exists'] = true;
		$wall['name'] = $row['real_name'];
		$wall['privacy'] = (int) $row['privacy'];
		$wall['owner_approval'] = (int) $row['owner_approval'];
		$wall['buddies'] = array_filter(array_map('intval', explode(',', (string) $row['buddy_list'])));
		$wall['ignored'] = array_filter(array_map('intval', explode(',', (string) $row['pm_ignore_list'])));
	}
	$smcFunc['db_free_result']($request);

	return $cache[$memID] = $wall;
}

/**
 * Can the current user post on this wall? Returns true or a reason key.
 */
function pw_post_status($memID, $wall)
{
	global $user_info, $modSettings;

	if ($user_info['is_guest'] || !allowedTo('profile_wall_post'))
		return 'no_permission';
	if (pw_is_owner($memID) || pw_can_moderate())
		return true;
	if (in_array((int) $user_info['id'], $wall['ignored'], true))
		return 'ignored';
	// Disabling this feature globally must also disable previously saved wall restrictions.
	if (!empty($modSettings['pw_owner_privacy']))
	{
		if ($wall['privacy'] === 2)
			return 'closed';
		if ($wall['privacy'] === 1 && !in_array((int) $user_info['id'], $wall['buddies'], true))
			return 'buddies';
	}

	return true;
}

function pw_can_edit($comment, $memID)
{
	global $user_info, $modSettings;

	if (allowedTo('profile_wall_edit_any') || pw_can_moderate())
		return true;

	$own = !$user_info['is_guest'] && (int) $comment['id_poster'] === (int) $user_info['id'];
	if (!$own || !allowedTo('profile_wall_edit_own'))
		return false;

	if (!empty($modSettings['pw_edit_window']) && $comment['created'] + $modSettings['pw_edit_window'] * 60 < time())
		return false;

	return true;
}

function pw_can_delete($comment, $memID)
{
	global $user_info, $modSettings;

	if (allowedTo('profile_wall_delete_any') || pw_can_moderate())
		return true;
	if (pw_is_owner($memID) && !empty($modSettings['pw_owner_delete']))
		return true;

	$own = !$user_info['is_guest'] && (int) $comment['id_poster'] === (int) $user_info['id'];

	return $own && allowedTo('profile_wall_delete_own');
}

function pw_load_comment($id_comment, $memID = null)
{
	global $smcFunc;

	$request = $smcFunc['db_query']('', '
		SELECT *
		FROM {db_prefix}pw_comments
		WHERE id_comment = {int:id}' . ($memID !== null ? '
			AND id_profile = {int:member}' : '') . '
		LIMIT 1',
		array('id' => (int) $id_comment, 'member' => (int) $memID)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	return $row ?: false;
}

/**
 * Read, convert and validate a submitted message body. Returns the stored form.
 */
function pw_get_body($field)
{
	global $sourcedir, $smcFunc, $modSettings;

	require_once($sourcedir . '/Subs-Post.php');

	// WYSIWYG mode sends HTML; turn it back into BBC.
	if (!empty($_REQUEST[$field . '_mode']) && isset($_REQUEST[$field]))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$_REQUEST[$field] = un_htmlspecialchars(html_to_bbc($_REQUEST[$field]));
		$_POST[$field] = $_REQUEST[$field];
	}

	$body = isset($_POST[$field]) ? (string) $_POST[$field] : '';
	$body = $smcFunc['htmlspecialchars']($body, ENT_QUOTES);
	preparsecode($body);

	if ($smcFunc['htmltrim'](strip_tags(parse_bbc($body, false), '<img>')) === '')
		fatal_lang_error('pw_err_empty', false);

	if (!empty($modSettings['pw_max_length']) && $smcFunc['strlen']($body) > $modSettings['pw_max_length'])
		fatal_lang_error('pw_err_too_long', false, array((int) $modSettings['pw_max_length']));

	return $body;
}

/**
 * Send a native SMF alert (respecting the member's alert preferences).
 */
function pw_notify($to, $action, $id_comment, $memID, $actor = null)
{
	global $smcFunc, $user_info, $sourcedir, $modSettings, $txt, $scripturl;

	// Delayed approval alerts must identify the original comment author, not the moderator.
	$actor = $actor === null ? array(
		'id' => $user_info['id'], 'name' => $user_info['name'], 'username' => $user_info['username'],
	) : $actor;
	$actor_id = (int) ($actor['id'] ?? 0);
	$actor_name = (string) ($actor['name'] ?? '');
	$to = (int) $to;
	if ($to <= 0 || $to === $actor_id)
		return;

	// "pending" alerts always go out; the owner asked to approve comments.
	if ($action !== 'pending')
	{
		require_once($sourcedir . '/Subs-Notify.php');
		$pref = 'pw_' . $action;
		$prefs = getNotifyPrefs($to, $pref, true);
		$enabled = isset($prefs[$to][$pref]) ? (int) $prefs[$to][$pref] : 1;
		if (($enabled & 0x01) === 0)
			return;
	}

	$wall = pw_wall_settings($memID);

	$smcFunc['db_insert']('insert',
		'{db_prefix}user_alerts',
		array(
			'alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
			'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string',
		),
		array(
				time(), $to, $actor_id, $actor_name,
				'pwall', (int) $id_comment, $action, 0,
			$smcFunc['json_encode'](array(
				'wall_link' => pw_wall_url($memID) . '#pw' . (int) $id_comment,
					'wall_name' => $smcFunc['htmlspecialchars']($wall['name'], ENT_QUOTES),
			)),
		),
		array('id_alert')
	);
	updateMemberData($to, array('alerts' => '+'));

	// Optional old-school PM for brand new comments.
	// Only send PMs on immediate posts. A moderator cannot send a PM impersonating the author.
	if ($action === 'new' && $actor_id > 0 && $actor_id === (int) $user_info['id'] && !empty($modSettings['pw_pm_notify']))
	{
		require_once($sourcedir . '/Subs-Post.php');
		sendpm(
			array('to' => array($to), 'bcc' => array()),
			$txt['pw_pm_subject'],
			sprintf($txt['pw_pm_body'], $actor_name, $scripturl . '?action=profile;u=' . (int) $memID . ';area=wall#pw' . (int) $id_comment),
			false,
			array('id' => $user_info['id'], 'name' => $user_info['name'], 'username' => $user_info['username'])
		);
	}
}

/**
 * Delete comments, their replies, likes and reports.
 */
function pw_delete_comments(array $ids)
{
	global $smcFunc;

	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if (empty($ids))
		return;

	$request = $smcFunc['db_query']('', '
		SELECT id_comment
		FROM {db_prefix}pw_comments
		WHERE id_parent IN ({array_int:ids})',
		array('ids' => $ids)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ids[] = (int) $row['id_comment'];
	$smcFunc['db_free_result']($request);
	$ids = array_values(array_unique($ids));

	foreach (array('pw_likes', 'pw_reports', 'pw_comments') as $table)
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}' . $table . '
			WHERE id_comment IN ({array_int:ids})',
			array('ids' => $ids)
		);
}

function pw_recount_likes(array $ids)
{
	global $smcFunc;

	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if (empty($ids))
		return;

	$counts = array_fill_keys($ids, 0);
	$request = $smcFunc['db_query']('', '
		SELECT id_comment, COUNT(*) AS total
		FROM {db_prefix}pw_likes
		WHERE id_comment IN ({array_int:ids})
		GROUP BY id_comment',
		array('ids' => $ids)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$counts[(int) $row['id_comment']] = (int) $row['total'];
	$smcFunc['db_free_result']($request);

	foreach ($counts as $id => $total)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pw_comments
			SET likes = {int:total}
			WHERE id_comment = {int:id}',
			array('total' => $total, 'id' => $id)
		);
}

/* --------------------------------------------------------------------------
 * Profile area: router
 * ------------------------------------------------------------------------ */

function ProfileWallMain($memID)
{
	global $context, $txt;

	pw_load_assets();

	$actions = array(
		'view' => 'pw_view',
		'post' => 'pw_action_post',
		'edit' => 'pw_action_edit',
		'delete' => 'pw_action_delete',
		'like' => 'pw_action_like',
		'pin' => 'pw_action_pin',
		'approve' => 'pw_action_approve',
		'report' => 'pw_action_report',
		'settings' => 'pw_action_settings',
	);

	$sa = isset($_REQUEST['sa'], $actions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'view';
	$context['page_title'] = sprintf($txt['pw_title'], htmlspecialchars(pw_wall_settings($memID)['name'], ENT_QUOTES, 'UTF-8'));

	call_user_func($actions[$sa], (int) $memID);
}

/* --------------------------------------------------------------------------
 * The wall
 * ------------------------------------------------------------------------ */

function pw_view($memID)
{
	global $context, $txt, $modSettings, $smcFunc, $sourcedir, $user_info, $memberContext;

	$wall = pw_wall_settings($memID);
	$is_owner = pw_is_owner($memID);
	$can_moderate = pw_can_moderate();
	$per_page = max(1, (int) (!empty($modSettings['pw_per_page']) ? $modSettings['pw_per_page'] : 10));

	$params = array('member' => $memID, 'me' => $user_info['is_guest'] ? -1 : (int) $user_info['id']);
	$visible = ($is_owner || $can_moderate) ? '1=1' : '(c.approved = 1 OR c.id_poster = {int:me})';

	$context['pw'] = array(
		'url' => pw_wall_url($memID),
		'member_id' => $memID,
		'title' => sprintf($txt['pw_title'], htmlspecialchars($wall['name'], ENT_QUOTES, 'UTF-8')),
		'is_owner' => $is_owner,
		'post_status' => pw_post_status($memID, $wall),
		'show_wall_settings' => $is_owner && !empty($modSettings['pw_owner_privacy']),
		'wall' => $wall,
		'comments' => array(),
		'pending_count' => 0,
		'max_length' => (int) (!empty($modSettings['pw_max_length']) ? $modSettings['pw_max_length'] : 0),
		'replies_enabled' => !empty($modSettings['pw_replies']),
		'likes_enabled' => !empty($modSettings['pw_likes']),
	);

	// Pending count for the people who can act on it.
	if ($is_owner || $can_moderate)
	{
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}pw_comments
			WHERE id_profile = {int:member}
				AND approved = {int:no}',
			array('member' => $memID, 'no' => 0)
		);
		list ($context['pw']['pending_count']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// Count top-level comments for the page index.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}pw_comments AS c
		WHERE c.id_profile = {int:member}
			AND c.id_parent = 0
			AND ' . $visible,
		$params
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$start = isset($_REQUEST['start']) ? max(0, (int) $_REQUEST['start']) : 0;
	$context['pw']['page_index'] = constructPageIndex($context['pw']['url'], $start, (int) $total, $per_page);
	$context['pw']['total'] = (int) $total;

	// Top-level comments for this page; pinned first.
	$rows = array();
	$parents = array();
	$request = $smcFunc['db_query']('', '
		SELECT c.*
		FROM {db_prefix}pw_comments AS c
		WHERE c.id_profile = {int:member}
			AND c.id_parent = 0
			AND ' . $visible . '
		ORDER BY c.pinned DESC, c.id_comment DESC
		LIMIT {int:start}, {int:limit}',
		$params + array('start' => $start, 'limit' => $per_page)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$rows[(int) $row['id_comment']] = $row;
		$parents[] = (int) $row['id_comment'];
	}
	$smcFunc['db_free_result']($request);

	// Replies for those comments.
	$replies = array();
	if (!empty($parents) && $context['pw']['replies_enabled'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT c.*
			FROM {db_prefix}pw_comments AS c
			WHERE c.id_parent IN ({array_int:parents})
				AND ' . $visible . '
			ORDER BY c.id_comment ASC',
			$params + array('parents' => $parents)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$replies[(int) $row['id_comment']] = $row;
		$smcFunc['db_free_result']($request);
	}

	$all = $rows + $replies;

	// Which of these have I liked?
	$my_likes = array();
	if (!empty($all) && !$user_info['is_guest'] && $context['pw']['likes_enabled'])
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_comment
			FROM {db_prefix}pw_likes
			WHERE id_member = {int:me}
				AND id_comment IN ({array_int:ids})',
			array('me' => (int) $user_info['id'], 'ids' => array_keys($all))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$my_likes[(int) $row['id_comment']] = true;
		$smcFunc['db_free_result']($request);
	}

	// Poster info boxes.
	$posters = array();
	foreach ($all as $row)
		if (!empty($row['id_poster']))
			$posters[] = (int) $row['id_poster'];
	$loaded = !empty($posters) ? loadMemberData(array_unique($posters)) : array();
	if (!empty($loaded))
		foreach ($loaded as $id)
			loadMemberContext($id);

	foreach ($rows as $id => $row)
	{
		$comment = pw_format_comment($row, $memID, $my_likes, $memberContext);
		$comment['replies'] = array();
		foreach ($replies as $reply)
			if ((int) $reply['id_parent'] === $id)
				$comment['replies'][] = pw_format_comment($reply, $memID, $my_likes, $memberContext);
		$comment['can_reply'] = $context['pw']['replies_enabled'] && $context['pw']['post_status'] === true && !$comment['is_pending'];
		$context['pw']['comments'][] = $comment;
	}

	// Rich editor for new top-level comments.
	if ($context['pw']['post_status'] === true)
	{
		require_once($sourcedir . '/Subs-Editor.php');
		create_control_richedit(array(
			'id' => 'pw_body',
			'value' => '',
			'labels' => array('post_button' => $txt['pw_post']),
			'height' => '140px',
			'width' => '100%',
			'preview_type' => 0,
			'required' => true,
		));
		$context['pw']['editor_id'] = 'pw_body';
	}

	$context['sub_template'] = 'pw_wall';
}

function pw_format_comment($row, $memID, $my_likes, $memberContext)
{
	global $context, $user_info, $txt, $modSettings;

	$id = (int) $row['id_comment'];
	$poster_id = (int) $row['id_poster'];
	$own = !$user_info['is_guest'] && $poster_id === (int) $user_info['id'];
	$is_owner = pw_is_owner($memID);
	$session = $context['session_var'] . '=' . $context['session_id'];
	$base = pw_wall_url($memID);

	if ($poster_id && isset($memberContext[$poster_id]))
	{
		$m = $memberContext[$poster_id];
		$poster = array(
			'id' => $poster_id,
			'name' => $m['name'],
			'link' => $m['link'],
			'avatar' => !empty($m['avatar']['image']) ? $m['avatar']['image'] : '',
			'group' => !empty($m['group']) ? $m['group'] : $m['post_group'],
			'posts' => $m['posts'],
			'is_online' => !empty($m['online']['is_online']),
			'is_wall_owner' => $poster_id === (int) $memID,
		);
	}
	else
		$poster = array(
			'id' => 0,
			'name' => htmlspecialchars($row['poster_name'], ENT_QUOTES, 'UTF-8'),
			'link' => htmlspecialchars($row['poster_name'], ENT_QUOTES, 'UTF-8'),
			'avatar' => '',
			'group' => $txt['guest_title'],
			'posts' => '',
			'is_online' => false,
			'is_wall_owner' => false,
		);

	$body = $row['body'];
	censorText($body);

	$is_top = (int) $row['id_parent'] === 0;
	$is_pending = empty($row['approved']);

	return array(
		'id' => $id,
		'href' => $base . '#pw' . $id,
		'poster' => $poster,
		'body' => parse_bbc($body, true, 'pw' . $id),
		'time' => timeformat($row['created']),
		'timestamp' => (int) $row['created'],
		'modified' => !empty($row['modified']) ? array('time' => timeformat($row['modified']), 'name' => htmlspecialchars($row['modified_name'], ENT_QUOTES, 'UTF-8')) : false,
		'is_pending' => $is_pending,
		'is_pinned' => !empty($row['pinned']),
		'likes' => (int) $row['likes'],
		'liked' => isset($my_likes[$id]),
		'can_like' => !empty($modSettings['pw_likes']) && !$user_info['is_guest'] && !$own && !$is_pending,
		'can_edit' => pw_can_edit($row, $memID),
		'can_delete' => pw_can_delete($row, $memID),
		'can_pin' => $is_top && !$is_pending && ($is_owner || pw_can_moderate()),
		'can_approve' => $is_pending && ($is_owner || pw_can_moderate()),
		'can_report' => !empty($modSettings['pw_reports']) && !$user_info['is_guest'] && !$own && !$is_pending,
		'urls' => array(
			'like' => $base . ';sa=like;cid=' . $id . ';' . $session,
			'edit' => $base . ';sa=edit;cid=' . $id,
			'delete' => $base . ';sa=delete;cid=' . $id . ';' . $session,
			'pin' => $base . ';sa=pin;cid=' . $id . ';' . $session,
			'approve' => $base . ';sa=approve;cid=' . $id . ';' . $session,
			'report' => $base . ';sa=report;cid=' . $id,
		),
	);
}

/* --------------------------------------------------------------------------
 * Actions
 * ------------------------------------------------------------------------ */

function pw_action_post($memID)
{
	global $smcFunc, $user_info, $modSettings;

	checkSession();

	$wall = pw_wall_settings($memID);
	if (!$wall['exists'])
		fatal_lang_error('pw_err_not_found', false);

	$status = pw_post_status($memID, $wall);
	if ($status !== true)
		fatal_lang_error('pw_status_' . $status, false);

	// Reply?
	$parent = isset($_POST['parent']) ? (int) $_POST['parent'] : 0;
	$parent_row = false;
	if ($parent > 0)
	{
		if (empty($modSettings['pw_replies']))
			fatal_lang_error('pw_err_no_permission', false);

		$parent_row = pw_load_comment($parent, $memID);
		if (!$parent_row || (int) $parent_row['id_parent'] !== 0 || empty($parent_row['approved']))
			fatal_lang_error('pw_err_not_found', false);
	}

	// Flood control.
	if (!empty($modSettings['pw_flood_seconds']) && !pw_can_moderate())
	{
		$request = $smcFunc['db_query']('', '
			SELECT MAX(created)
			FROM {db_prefix}pw_comments
			WHERE id_poster = {int:me}',
			array('me' => (int) $user_info['id'])
		);
		list ($last) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$wait = (int) $last + (int) $modSettings['pw_flood_seconds'] - time();
		if (!empty($last) && $wait > 0)
			fatal_lang_error('pw_err_flood', false, array($wait));
	}

	$body = pw_get_body($parent > 0 ? 'pw_reply_body' : 'pw_body');

	// Moderation: global permission first, then the owner's own choice.
	$is_owner = pw_is_owner($memID);
	$approved = 1;
	$owner_must_approve = false;
	if (!pw_can_moderate() && !$is_owner)
	{
		if (!allowedTo('profile_wall_autoapprove'))
			$approved = 0;
			if (!empty($modSettings['pw_owner_privacy']) && !empty($wall['owner_approval']))
			{
			$approved = 0;
			$owner_must_approve = true;
		}
	}

	$smcFunc['db_insert']('',
		'{db_prefix}pw_comments',
		array(
			'id_profile' => 'int', 'id_poster' => 'int', 'poster_name' => 'string-80', 'id_parent' => 'int',
			'body' => 'string', 'created' => 'int', 'approved' => 'int', 'poster_ip' => 'inet',
		),
		array(
			$memID, (int) $user_info['id'], $user_info['name'], $parent,
			$body, time(), $approved, $user_info['ip'],
		),
		array('id_comment')
	);
	$id = (int) $smcFunc['db_insert_id']('{db_prefix}pw_comments', 'id_comment');

	if ($approved)
	{
		pw_notify($memID, 'new', $id, $memID);
		if ($parent_row && (int) $parent_row['id_poster'] !== $memID)
			pw_notify($parent_row['id_poster'], 'reply', $id, $memID);
	}
	elseif ($owner_must_approve)
		pw_notify($memID, 'pending', $id, $memID);

	redirectexit('action=profile;u=' . $memID . ';area=wall' . ($approved ? '' : ';pending') . '#pw' . $id);
}

function pw_action_edit($memID)
{
	global $context, $txt, $smcFunc, $sourcedir, $user_info;

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	$comment = pw_load_comment($cid, $memID);
	if (!$comment)
		fatal_lang_error('pw_err_not_found', false);
	if (!pw_can_edit($comment, $memID))
		fatal_lang_error('pw_err_no_permission', false);

	$is_reply = (int) $comment['id_parent'] !== 0;
	$field = $is_reply ? 'pw_reply_body' : 'pw_body';

	if (isset($_POST['save']))
	{
		checkSession();
		$body = pw_get_body($field);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pw_comments
			SET body = {string:body}, modified = {int:now}, modified_name = {string:name}
			WHERE id_comment = {int:id}',
			array('body' => $body, 'now' => time(), 'name' => $user_info['name'], 'id' => $cid)
		);
		cache_put_data('parse:pw' . $cid, null);

		redirectexit('action=profile;u=' . $memID . ';area=wall#pw' . $cid);
	}

	require_once($sourcedir . '/Subs-Post.php');
	$context['pw'] = array(
		'url' => pw_wall_url($memID),
		'title' => $txt['pw_edit_comment'],
		'cid' => $cid,
		'is_reply' => $is_reply,
		'field' => $field,
		'value' => un_preparsecode($comment['body']),
		'max_length' => (int) (!empty($GLOBALS['modSettings']['pw_max_length']) ? $GLOBALS['modSettings']['pw_max_length'] : 0),
	);

	if (!$is_reply)
	{
		require_once($sourcedir . '/Subs-Editor.php');
		create_control_richedit(array(
			'id' => 'pw_body',
			'value' => $context['pw']['value'],
			'labels' => array('post_button' => $txt['save']),
			'height' => '180px',
			'width' => '100%',
			'preview_type' => 0,
			'required' => true,
		));
	}

	$context['sub_template'] = 'pw_edit';
}

function pw_action_delete($memID)
{
	checkSession('get');

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	$comment = pw_load_comment($cid, $memID);
	if (!$comment)
		fatal_lang_error('pw_err_not_found', false);
	if (!pw_can_delete($comment, $memID))
		fatal_lang_error('pw_err_no_permission', false);

	pw_delete_comments(array($cid));

	redirectexit('action=profile;u=' . $memID . ';area=wall' . (!empty($comment['id_parent']) ? '#pw' . (int) $comment['id_parent'] : ''));
}

function pw_action_like($memID)
{
	global $smcFunc, $user_info, $modSettings;

	checkSession('get');
	is_not_guest();

	if (empty($modSettings['pw_likes']))
		fatal_lang_error('pw_err_no_permission', false);

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	$comment = pw_load_comment($cid, $memID);
	if (!$comment || empty($comment['approved']))
		fatal_lang_error('pw_err_not_found', false);
	if ((int) $comment['id_poster'] === (int) $user_info['id'])
		fatal_lang_error('pw_err_like_own', false);

	$request = $smcFunc['db_query']('', '
		SELECT 1
		FROM {db_prefix}pw_likes
		WHERE id_comment = {int:id}
			AND id_member = {int:me}',
		array('id' => $cid, 'me' => (int) $user_info['id'])
	);
	$already = $smcFunc['db_num_rows']($request) > 0;
	$smcFunc['db_free_result']($request);

	if ($already)
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}pw_likes
			WHERE id_comment = {int:id}
				AND id_member = {int:me}',
			array('id' => $cid, 'me' => (int) $user_info['id'])
		);
	else
	{
		$smcFunc['db_insert']('ignore',
			'{db_prefix}pw_likes',
			array('id_comment' => 'int', 'id_member' => 'int', 'like_time' => 'int'),
			array($cid, (int) $user_info['id'], time()),
			array('id_comment', 'id_member')
		);
		if ($smcFunc['db_affected_rows']() > 0)
			pw_notify($comment['id_poster'], 'like', $cid, $memID);
	}

	pw_recount_likes(array($cid));

	redirectexit('action=profile;u=' . $memID . ';area=wall#pw' . $cid);
}

function pw_action_pin($memID)
{
	global $smcFunc;

	checkSession('get');

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	$comment = pw_load_comment($cid, $memID);
	if (!$comment || (int) $comment['id_parent'] !== 0 || empty($comment['approved']))
		fatal_lang_error('pw_err_not_found', false);
	if (!pw_is_owner($memID) && !pw_can_moderate())
		fatal_lang_error('pw_err_no_permission', false);

	// One featured comment per wall.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pw_comments
		SET pinned = {int:no}
		WHERE id_profile = {int:member}',
		array('no' => 0, 'member' => $memID)
	);
	if (empty($comment['pinned']))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}pw_comments
			SET pinned = {int:yes}
			WHERE id_comment = {int:id}',
			array('yes' => 1, 'id' => $cid)
		);

	redirectexit('action=profile;u=' . $memID . ';area=wall#pw' . $cid);
}

function pw_action_approve($memID)
{
	checkSession('get');

	if (!pw_is_owner($memID) && !pw_can_moderate())
		fatal_lang_error('pw_err_no_permission', false);

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	pw_approve_comments(array($cid), $memID);

	redirectexit('action=profile;u=' . $memID . ';area=wall#pw' . $cid);
}

/**
 * Approve comments (optionally limited to one wall) and send the delayed alerts.
 */
function pw_approve_comments(array $ids, $memID = null)
{
	global $smcFunc, $user_info;

	$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
	if (empty($ids))
		return;

	$smcFunc['db_transaction']('begin');
	$rows = array();
	$request = $smcFunc['db_query']('', '
		SELECT c.id_comment, c.id_profile, c.id_poster, c.poster_name, c.id_parent, p.member_name AS poster_username
		FROM {db_prefix}pw_comments AS c
			LEFT JOIN {db_prefix}members AS p ON (p.id_member = c.id_poster)
		WHERE c.id_comment IN ({array_int:ids})
				AND c.approved = {int:no}' . ($memID !== null ? '
				AND c.id_profile = {int:member}' : '') . '
		FOR UPDATE',
		array('ids' => $ids, 'no' => 0, 'member' => (int) $memID)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$rows[] = $row;
	$smcFunc['db_free_result']($request);

	if (empty($rows))
	{
		$smcFunc['db_transaction']('commit');
		return;
	}

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pw_comments
		SET approved = {int:yes}
		WHERE id_comment IN ({array_int:ids})
			AND approved = {int:no}',
		array('yes' => 1, 'no' => 0, 'ids' => array_map(function ($r) { return (int) $r['id_comment']; }, $rows))
	);
	$smcFunc['db_transaction']('commit');

	// Notifications identify the original author, not whoever approved the comment.
	// The owner already saw the approval request if they are approving on their own wall.
	foreach ($rows as $row)
	{
		$profile_id = (int) $row['id_profile'];
		$actor = array(
			'id' => (int) $row['id_poster'],
			'name' => $row['poster_name'],
			'username' => $row['poster_username'] ?? '',
		);
		if ($profile_id !== (int) $user_info['id'])
			pw_notify($profile_id, 'new', $row['id_comment'], $profile_id, $actor);

		// Replies held for approval must also notify the original comment's author.
		if (!empty($row['id_parent']))
		{
			$parent = pw_load_comment((int) $row['id_parent'], $profile_id);
			if ($parent && !empty($parent['approved']) && (int) $parent['id_poster'] !== $profile_id)
				pw_notify($parent['id_poster'], 'reply', $row['id_comment'], $profile_id, $actor);
		}
	}
}

function pw_action_report($memID)
{
	global $context, $txt, $smcFunc, $user_info, $modSettings;

	is_not_guest();
	if (empty($modSettings['pw_reports']))
		fatal_lang_error('pw_err_no_permission', false);

	$cid = isset($_REQUEST['cid']) ? (int) $_REQUEST['cid'] : 0;
	$comment = pw_load_comment($cid, $memID);
	if (!$comment || empty($comment['approved']))
		fatal_lang_error('pw_err_not_found', false);

	if (isset($_POST['save']))
	{
		checkSession();

		$reason = isset($_POST['reason']) ? $smcFunc['htmlspecialchars'](trim((string) $_POST['reason']), ENT_QUOTES) : '';
		if ($reason === '')
			fatal_lang_error('pw_err_report_reason', false);
		$reason = $smcFunc['substr']($reason, 0, 250);

		// One open report per member per comment.
		$request = $smcFunc['db_query']('', '
			SELECT 1
			FROM {db_prefix}pw_reports
			WHERE id_comment = {int:id}
				AND id_member = {int:me}
				AND closed = {int:no}',
			array('id' => $cid, 'me' => (int) $user_info['id'], 'no' => 0)
		);
		$exists = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		if (!$exists)
			$smcFunc['db_insert']('',
				'{db_prefix}pw_reports',
				array('id_comment' => 'int', 'id_member' => 'int', 'reason' => 'string-255', 'time_reported' => 'int', 'closed' => 'int'),
				array($cid, (int) $user_info['id'], $reason, time(), 0),
				array('id_report')
			);

		redirectexit('action=profile;u=' . $memID . ';area=wall;reported#pw' . $cid);
	}

	$context['pw'] = array(
		'url' => pw_wall_url($memID),
		'title' => $txt['pw_report_comment'],
		'cid' => $cid,
	);
	$context['sub_template'] = 'pw_report';
}

function pw_action_settings($memID)
{
	global $smcFunc, $modSettings;

	checkSession();

	if (!pw_is_owner($memID) || empty($modSettings['pw_owner_privacy']))
		fatal_lang_error('pw_err_no_permission', false);

	$privacy = isset($_POST['privacy']) ? (int) $_POST['privacy'] : 0;
	if (!in_array($privacy, array(0, 1, 2), true))
		$privacy = 0;
	$approval = !empty($_POST['owner_approval']) ? 1 : 0;

	$smcFunc['db_insert']('replace',
		'{db_prefix}pw_walls',
		array('id_member' => 'int', 'privacy' => 'int', 'owner_approval' => 'int'),
		array($memID, $privacy, $approval),
		array('id_member')
	);

	redirectexit('action=profile;u=' . $memID . ';area=wall;saved');
}

/* --------------------------------------------------------------------------
 * Summary page preview
 * ------------------------------------------------------------------------ */

function ProfileWallSummary($memID)
{
	global $sourcedir, $context, $modSettings, $smcFunc, $memberContext, $txt;

	require_once($sourcedir . '/Profile-View.php');
	summary($memID);

	// Render with the stock summary template (not 'ProfileWallSummary').
	$context['sub_template'] = 'summary';

	if (empty($modSettings['pw_enabled']) || empty($modSettings['pw_summary_count']) || !allowedTo('profile_wall_view'))
		return;

	$rows = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_comment, id_poster, poster_name, body, created
		FROM {db_prefix}pw_comments
		WHERE id_profile = {int:member}
			AND approved = {int:yes}
		ORDER BY id_comment DESC
		LIMIT {int:limit}',
		array('member' => (int) $memID, 'yes' => 1, 'limit' => min(20, (int) $modSettings['pw_summary_count']))
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$rows[] = $row;
	$smcFunc['db_free_result']($request);

	$posters = array();
	foreach ($rows as $row)
		if (!empty($row['id_poster']))
			$posters[] = (int) $row['id_poster'];
	$loaded = !empty($posters) ? loadMemberData(array_unique($posters)) : array();
	if (!empty($loaded))
		foreach ($loaded as $id)
			loadMemberContext($id);

	$context['pw_summary'] = array(
		'url' => pw_wall_url($memID),
		'items' => array(),
	);
	foreach ($rows as $row)
	{
		$body = $row['body'];
		censorText($body);
		$pid = (int) $row['id_poster'];
		$context['pw_summary']['items'][] = array(
			'href' => pw_wall_url($memID) . '#pw' . (int) $row['id_comment'],
				'poster' => $pid && isset($memberContext[$pid]) ? $memberContext[$pid]['link'] : htmlspecialchars((string) $row['poster_name'], ENT_QUOTES, 'UTF-8'),
			'avatar' => $pid && isset($memberContext[$pid]['avatar']['image']) ? $memberContext[$pid]['avatar']['image'] : '',
			'excerpt' => shorten_subject(trim(strip_tags(parse_bbc($body, false))), 140),
			'time' => timeformat($row['created']),
		);
	}

	pw_load_assets();
	$context['template_layers'][] = 'pw_summary';
}

/* --------------------------------------------------------------------------
 * Admin
 * ------------------------------------------------------------------------ */

function ProfileWallAdmin()
{
	global $context, $txt;

	pw_load_assets();

	$subActions = array(
		'settings' => 'pw_admin_settings',
		'queue' => 'pw_admin_queue',
		'reports' => 'pw_admin_reports',
	);

	$default = allowedTo('admin_forum') ? 'settings' : 'queue';
	$sa = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : $default;
	$context['sub_action'] = $sa;
	$context['page_title'] = $txt['pw_admin_title'];

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['pw_admin_title'],
		'description' => $txt['pw_admin_desc'],
		'tabs' => array(
			'settings' => array(),
			'queue' => array(),
			'reports' => array(),
		),
	);

	call_user_func($subActions[$sa]);
}

function pw_admin_settings($return_config = false)
{
	global $context, $txt, $scripturl, $sourcedir;

	$config_vars = array(
		array('check', 'pw_enabled'),
		'',
		array('int', 'pw_per_page', 'min' => 1, 'max' => 100),
		array('int', 'pw_max_length', 'subtext' => $txt['pw_zero_unlimited']),
		array('int', 'pw_flood_seconds', 'postinput' => $txt['pw_seconds'], 'subtext' => $txt['pw_zero_off']),
		array('int', 'pw_edit_window', 'postinput' => $txt['pw_minutes'], 'subtext' => $txt['pw_zero_unlimited']),
		'',
		array('check', 'pw_replies'),
		array('check', 'pw_likes'),
		array('check', 'pw_reports'),
		array('check', 'pw_owner_delete'),
		array('check', 'pw_owner_privacy', 'subtext' => $txt['pw_owner_privacy_sub']),
		'',
		array('int', 'pw_summary_count', 'subtext' => $txt['pw_zero_off'], 'min' => 0, 'max' => 20),
		array('check', 'pw_pm_notify', 'subtext' => $txt['pw_pm_notify_sub']),
		'',
		array('permissions', 'profile_wall_view'),
		array('permissions', 'profile_wall_post'),
		array('permissions', 'profile_wall_autoapprove'),
		array('permissions', 'profile_wall_moderate'),
	);

	if ($return_config)
		return $config_vars;

	isAllowedTo('admin_forum');
	require_once($sourcedir . '/ManageServer.php');

	$context['post_url'] = $scripturl . '?action=admin;area=profilewall;sa=settings;save';
	$context['settings_title'] = $txt['pw_admin_settings'];

	if (isset($_GET['save']))
	{
		checkSession();
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=profilewall;sa=settings');
	}

	prepareDBSettingContext($config_vars);
	$context['sub_template'] = 'show_settings';
}

function pw_admin_check_mod()
{
	if (!pw_can_moderate() && !allowedTo('admin_forum'))
		isAllowedTo('profile_wall_moderate');
}

function pw_admin_queue()
{
	global $context, $smcFunc, $scripturl, $txt;

	pw_admin_check_mod();

	if (isset($_POST['items']) && is_array($_POST['items']))
	{
		checkSession();
		$requested = array_values(array_unique(array_filter(array_map('intval', $_POST['items']), function ($id) { return $id > 0; })));
		if (count($requested) > 250)
			fatal_lang_error('pw_err_no_permission', false);

		// Only pending IDs actually shown in the moderation queue are valid here.
		$ids = array();
		if (!empty($requested))
		{
			$check = $smcFunc['db_query']('', '
				SELECT id_comment
				FROM {db_prefix}pw_comments
				WHERE id_comment IN ({array_int:ids})
					AND approved = {int:pending}',
				array('ids' => $requested, 'pending' => 0)
			);
			while ($row = $smcFunc['db_fetch_assoc']($check))
				$ids[] = (int) $row['id_comment'];
			$smcFunc['db_free_result']($check);
		}
		if (!empty($ids) && isset($_POST['do_approve']))
			pw_approve_comments($ids);
		elseif (!empty($ids) && isset($_POST['do_delete']))
			pw_delete_comments($ids);
		redirectexit('action=admin;area=profilewall;sa=queue');
	}

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}pw_comments
		WHERE approved = {int:no}',
		array('no' => 0)
	);
	list ($total) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	$start = isset($_REQUEST['start']) ? max(0, (int) $_REQUEST['start']) : 0;
	$context['pw_admin'] = array(
		'page_index' => constructPageIndex($scripturl . '?action=admin;area=profilewall;sa=queue', $start, (int) $total, 30),
		'items' => array(),
	);

	$request = $smcFunc['db_query']('', '
		SELECT c.id_comment, c.id_profile, c.id_poster, c.poster_name, c.body, c.created, c.id_parent,
			owner.real_name AS owner_name
		FROM {db_prefix}pw_comments AS c
			LEFT JOIN {db_prefix}members AS owner ON (owner.id_member = c.id_profile)
		WHERE c.approved = {int:no}
		ORDER BY c.id_comment ASC
		LIMIT {int:start}, {int:limit}',
		array('no' => 0, 'start' => $start, 'limit' => 30)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['pw_admin']['items'][] = pw_admin_row($row);
	$smcFunc['db_free_result']($request);

	$context['sub_template'] = 'pw_admin_queue';
}

function pw_admin_reports()
{
	global $context, $smcFunc, $scripturl;

	pw_admin_check_mod();

	if (isset($_GET['close']) || isset($_GET['remove']))
	{
		checkSession('get');
		$rid = (int) (isset($_GET['close']) ? $_GET['close'] : $_GET['remove']);

		if (isset($_GET['remove']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_comment
				FROM {db_prefix}pw_reports
				WHERE id_report = {int:id}',
				array('id' => $rid)
			);
			list ($cid) = $smcFunc['db_fetch_row']($request) ?: array(0);
			$smcFunc['db_free_result']($request);
			if (!empty($cid))
				pw_delete_comments(array($cid));
		}
		else
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}pw_reports
				SET closed = {int:yes}
				WHERE id_report = {int:id}',
				array('yes' => 1, 'id' => $rid)
			);

		redirectexit('action=admin;area=profilewall;sa=reports');
	}

	$context['pw_admin'] = array('items' => array());

	$request = $smcFunc['db_query']('', '
		SELECT r.id_report, r.reason, r.time_reported, r.id_member AS id_reporter, rep.real_name AS reporter_name,
			c.id_comment, c.id_profile, c.id_poster, c.poster_name, c.body, c.created, c.id_parent,
			owner.real_name AS owner_name
		FROM {db_prefix}pw_reports AS r
			INNER JOIN {db_prefix}pw_comments AS c ON (c.id_comment = r.id_comment)
			LEFT JOIN {db_prefix}members AS rep ON (rep.id_member = r.id_member)
			LEFT JOIN {db_prefix}members AS owner ON (owner.id_member = c.id_profile)
		WHERE r.closed = {int:no}
		ORDER BY r.id_report DESC
		LIMIT 100',
		array('no' => 0)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$item = pw_admin_row($row);
		$item['report_id'] = (int) $row['id_report'];
		$item['reason'] = $row['reason'];
		$item['reported'] = timeformat($row['time_reported']);
		$item['reporter'] = '<a href="' . $scripturl . '?action=profile;u=' . (int) $row['id_reporter'] . '">' . htmlspecialchars((string) $row['reporter_name'], ENT_QUOTES, 'UTF-8') . '</a>';
		$context['pw_admin']['items'][] = $item;
	}
	$smcFunc['db_free_result']($request);

	$context['sub_template'] = 'pw_admin_reports';
}

function pw_admin_row($row)
{
	global $scripturl;

	$body = $row['body'];
	censorText($body);

	return array(
		'id' => (int) $row['id_comment'],
		'body' => parse_bbc($body, true, 'pw' . (int) $row['id_comment']),
		'time' => timeformat($row['created']),
		'is_reply' => !empty($row['id_parent']),
		'poster' => !empty($row['id_poster'])
			? '<a href="' . $scripturl . '?action=profile;u=' . (int) $row['id_poster'] . '">' . htmlspecialchars((string) $row['poster_name'], ENT_QUOTES, 'UTF-8') . '</a>'
			: htmlspecialchars((string) $row['poster_name'], ENT_QUOTES, 'UTF-8'),
		'wall' => '<a href="' . pw_wall_url($row['id_profile']) . '#pw' . (int) $row['id_comment'] . '">' . htmlspecialchars((string) $row['owner_name'], ENT_QUOTES, 'UTF-8') . '</a>',
	);
}
