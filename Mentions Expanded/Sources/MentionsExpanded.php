<?php
/**
 * Mentions Expanded
 *
 * Adds @everyone, @here and @groupname mentions on top of SMF 2.1's core mentions.
 *
 * @package MentionsExpanded
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Is the mod (and SMF's own mentions feature) switched on?
 */
function mex_enabled()
{
	global $modSettings;

	return !empty($modSettings['mentions_expanded_enabled']) && !empty($modSettings['enable_mentions']);
}

/**
 * Groups the admin has made mentionable, as id => escaped group name. Cached.
 */
function mex_mentionable_groups()
{
	global $modSettings, $smcFunc;

	if (empty($modSettings['mentions_expanded_groups_enabled']) || empty($modSettings['mentions_expanded_groups']))
		return array();

	if (($groups = cache_get_data('mex_groups', 300)) !== null)
		return $groups;

	$ids = array_filter(array_map('intval', explode(',', $modSettings['mentions_expanded_groups'])));
	$groups = array();

	if (!empty($ids))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:groups})
				AND min_posts = {int:no_posts}
				AND id_group != {int:moderators}',
			array(
				'groups' => $ids,
				'no_posts' => -1,
				'moderators' => 3,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (trim($row['group_name']) !== '')
				$groups[(int) $row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);
	}

	// Longest names first so "@Senior Staff" wins over "@Senior".
	uasort($groups, function ($a, $b)
	{
		return strlen($b) - strlen($a);
	});

	cache_put_data('mex_groups', $groups, 300);

	return $groups;
}

/**
 * Remove quoted, code and nobbc content so quoting someone's @everyone doesn't re-fire it.
 */
function mex_clean_body($body)
{
	$body = preg_replace('~\[code(?:=[^\]]*)?\].*?\[/code\]~is', ' ', $body);
	$body = preg_replace('~\[nobbc\].*?\[/nobbc\]~is', ' ', $body);
	$body = preg_replace('~\[member=\d+\].*?\[/member\]~is', ' ', $body);

	// Strip quotes from the inside out to cope with nesting.
	$i = 0;
	do
	{
		$previous = $body;
		$body = preg_replace('~\[quote(?:[\s=][^\]]*)?\](?:(?!\[quote).)*?\[/quote\]~is', ' ', $body);
		$i++;
	}
	while ($body !== null && $body !== $previous && $i < 25);

	return (string) $body;
}

/**
 * Does the (already htmlspecialchar'd) body contain @token as a whole word?
 */
function mex_has_token($body, $token)
{
	return preg_match(mex_token_pattern($token), $body) === 1;
}

/**
 * Regex matching @token as a whole word.
 */
function mex_token_pattern($token)
{
	return '~(?:^|(?<=[\s>\]\(;,]))@' . preg_quote($token, '~') . '(?=$|[\s.,!?;:\)\]<&\'"])~iu';
}

/**
 * Work out which expanded targets a post contains that the poster may use.
 *
 * @return array('everyone' => bool, 'here' => bool, 'groups' => int[])
 */
function mex_find_targets($body, $board_id)
{
	global $modSettings;

	$targets = array('everyone' => false, 'here' => false, 'groups' => array());

	if (strpos($body, '@') === false)
		return $targets;

	$body = mex_clean_body($body);

	if (!empty($modSettings['mentions_expanded_everyone']) && allowedTo('mention_everyone', $board_id) && mex_has_token($body, 'everyone'))
		$targets['everyone'] = true;

	if (!empty($modSettings['mentions_expanded_here']) && allowedTo('mention_here', $board_id) && mex_has_token($body, 'here'))
		$targets['here'] = true;

	// @everyone already covers every group, no need to look further.
	if (!$targets['everyone'] && allowedTo('mention_groups', $board_id))
	{
		// Groups come longest name first. Blank out each match so "@Senior Staff"
		// can't also count as a mention of a group called "Senior".
		foreach (mex_mentionable_groups() as $id_group => $name)
			if (mex_has_token($body, $name))
			{
				$targets['groups'][] = $id_group;
				$body = (string) preg_replace(mex_token_pattern($name), ' ', $body);
			}
	}

	return $targets;
}

/**
 * Shared logic for new posts and edits: log new targets and queue the background task.
 */
function mex_process($msg_id, $board_id, $topic_id, $subject, $body, $poster_id, $poster_name)
{
	global $smcFunc, $modSettings;

	$msg_id = (int) $msg_id;
	$board_id = (int) $board_id;

	if (empty($msg_id) || empty($board_id) || empty($poster_id) || !mex_enabled())
		return;

	$targets = mex_find_targets($body, $board_id);

	$keys = array();
	if ($targets['everyone'])
		$keys[] = 'everyone';
	if ($targets['here'])
		$keys[] = 'here';
	foreach ($targets['groups'] as $id_group)
		$keys[] = 'g' . $id_group;

	if (empty($keys))
		return;

	// Skip targets already sent for this message (e.g. when a post is edited).
	$request = $smcFunc['db_query']('', '
		SELECT target
		FROM {db_prefix}mentions_expanded_log
		WHERE id_msg = {int:msg}',
		array('msg' => $msg_id)
	);
	$done = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$done[] = $row['target'];
	$smcFunc['db_free_result']($request);

	$keys = array_values(array_diff($keys, $done));
	if (empty($keys))
		return;

	$data = array(
		'msg' => $msg_id,
		'board' => $board_id,
		'topic' => (int) $topic_id,
		'subject' => $subject,
		'poster_id' => (int) $poster_id,
		'poster_name' => $poster_name,
		'everyone' => in_array('everyone', $keys),
		'groups' => array(),
		'here' => array(),
		'last_id' => 0,
	);

	foreach ($keys as $key)
		if ($key[0] === 'g')
			$data['groups'][] = (int) substr($key, 1);

	// Snapshot who is online right now - by the time the task runs that may have changed.
	if (in_array('here', $keys) && !$data['everyone'])
	{
		$minutes = max(1, min(1440, (int) (isset($modSettings['mentions_expanded_here_minutes']) ? $modSettings['mentions_expanded_here_minutes'] : 15)));

		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}log_online
			WHERE id_member > {int:guest}
				AND id_member != {int:poster}
				AND log_time >= {int:since}',
			array(
				'guest' => 0,
				'poster' => (int) $poster_id,
				'since' => time() - $minutes * 60,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$data['here'][] = (int) $row['id_member'];
		$smcFunc['db_free_result']($request);

		$data['here'] = array_values(array_unique($data['here']));
	}

	if (!$data['everyone'] && empty($data['groups']) && empty($data['here']))
		return;

	// Do not mark @here as delivered when there was nobody online to notify.
	$log_keys = array_values(array_filter($keys, function ($key) use ($data)
	{
		return $key !== 'here' || !empty($data['here']);
	}));
	$rows = array();
	foreach ($log_keys as $key)
		$rows[] = array($msg_id, $key, time());

	$smcFunc['db_insert']('ignore',
		'{db_prefix}mentions_expanded_log',
		array('id_msg' => 'int', 'target' => 'string', 'sent_time' => 'int'),
		$rows,
		array('id_msg', 'target')
	);

	$smcFunc['db_insert']('insert',
		'{db_prefix}background_tasks',
		array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
		array('$sourcedir/tasks/MentionsExpanded-Notify.php', 'MentionsExpanded_Notify', $smcFunc['json_encode']($data), 0),
		array('id_task')
	);
}

/**
 * integrate_after_create_post
 */
function mex_after_create_post($msgOptions, $topicOptions, $posterOptions, $message_columns = array(), $message_parameters = array())
{
	// Unapproved posts don't notify anyone.
	if (isset($msgOptions['approved']) && empty($msgOptions['approved']))
		return;

	if (empty($msgOptions['id']) || empty($msgOptions['body']) || empty($posterOptions['id']) || (isset($msgOptions['send_notifications']) && !$msgOptions['send_notifications']))
		return;

	mex_process(
		$msgOptions['id'],
		isset($topicOptions['board']) ? $topicOptions['board'] : 0,
		isset($topicOptions['id']) ? $topicOptions['id'] : 0,
		isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
		$msgOptions['body'],
		$posterOptions['id'],
		isset($posterOptions['name']) ? $posterOptions['name'] : ''
	);
}

/**
 * integrate_modify_post - notify only targets newly added in an edit.
 */
function mex_modify_post(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts)
{
	global $smcFunc, $user_info;

	if (!isset($msgOptions['body']) || empty($msgOptions['id']) || $user_info['is_guest'] || !mex_enabled() || (isset($msgOptions['send_notifications']) && !$msgOptions['send_notifications']) || (isset($msgOptions['approved']) && !$msgOptions['approved']))
		return;

	$request = $smcFunc['db_query']('', '
		SELECT id_board, id_topic, subject, approved
		FROM {db_prefix}messages
		WHERE id_msg = {int:msg}',
		array('msg' => (int) $msgOptions['id'])
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (empty($row) || empty($row['approved']))
		return;

	mex_process(
		$msgOptions['id'],
		$row['id_board'],
		$row['id_topic'],
		isset($msgOptions['subject']) ? $msgOptions['subject'] : $row['subject'],
		$msgOptions['body'],
		$user_info['id'],
		$user_info['name']
	);
}

/**
 * integrate_post_parsebbc - highlight @everyone / @here / @group in displayed posts.
 */
function mex_post_parsebbc(&$message, &$smileys, &$cache_id, &$parse_tags)
{
	global $modSettings;

	if (!mex_enabled() || empty($modSettings['mentions_expanded_highlight']) || !empty($parse_tags) || $smileys === 'print' || strpos($message, '@') === false)
		return;

	$tokens = array();
	if (!empty($modSettings['mentions_expanded_everyone']))
		$tokens[] = 'everyone';
	if (!empty($modSettings['mentions_expanded_here']))
		$tokens[] = 'here';
	foreach (mex_mentionable_groups() as $name)
		$tokens[] = $name;

	if (empty($tokens))
		return;

	usort($tokens, function ($a, $b)
	{
		return strlen($b) - strlen($a);
	});

	$alternatives = array();
	foreach ($tokens as $token)
		$alternatives[] = preg_quote($token, '~');

	$pattern = '~(^|[\s(;,])@(' . implode('|', $alternatives) . ')(?=$|[\s.,!?;:)<&\'"])~iu';

	$parts = preg_split('~(<[^>]*>)~', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
	if ($parts === false)
		return;

	// Don't touch text inside code blocks or links.
	$skip = 0;
	foreach ($parts as $k => $part)
	{
		if ($part === '')
			continue;

		if ($part[0] === '<')
		{
			if (preg_match('~^<(/?)(code|pre|a)\b~i', $part, $m))
				$skip = max(0, $skip + ($m[1] === '/' ? -1 : 1));
			continue;
		}

		if ($skip > 0)
			continue;

		$replaced = preg_replace($pattern, '$1<span class="mention_expanded">@$2</span>', $part);
		if ($replaced !== null)
			$parts[$k] = $replaced;
	}

	$message = implode('', $parts);
}

/**
 * integrate_load_theme - a little CSS for highlighted mentions.
 */
function mex_load_theme()
{
	global $modSettings;

	if (!mex_enabled() || empty($modSettings['mentions_expanded_highlight']) || !function_exists('addInlineCss'))
		return;

	addInlineCss('
	.mention_expanded { display: inline-block; padding: 0 5px; border-radius: 4px; font-weight: bold; background: rgba(250, 166, 26, 0.18); color: #b86e00; }');
}

/**
 * integrate_load_permissions
 */
function mex_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	loadLanguage('MentionsExpanded');

	$permissionList['board']['mention_everyone'] = array(false, 'post');
	$permissionList['board']['mention_here'] = array(false, 'post');
	$permissionList['board']['mention_groups'] = array(false, 'post');
}

/**
 * integrate_load_illegal_guest_permissions
 */
function mex_illegal_guest_permissions()
{
	global $context;

	if (!isset($context['non_guest_permissions']))
		$context['non_guest_permissions'] = array();

	$context['non_guest_permissions'] = array_merge($context['non_guest_permissions'], array('mention_everyone', 'mention_here', 'mention_groups'));
}

/**
 * integrate_admin_areas
 */
function mex_admin_areas(&$admin_areas)
{
	global $txt;

	loadLanguage('MentionsExpanded');

	$admin_areas['config']['areas']['modsettings']['subsections']['mentionsexpanded'] = array($txt['mentions_expanded_title']);
}

/**
 * integrate_modify_modifications
 */
function mex_modify_modifications(&$sub_actions)
{
	$sub_actions['mentionsexpanded'] = 'mex_settings';
}

/**
 * Admin settings page.
 */
function mex_settings($return_config = false)
{
	global $txt, $scripturl, $context, $modSettings, $smcFunc;

	loadLanguage('MentionsExpanded');
	loadTemplate('MentionsExpanded');

	$config_vars = array(
		array('check', 'mentions_expanded_enabled'),
		'',
		array('check', 'mentions_expanded_everyone', 'subtext' => $txt['mentions_expanded_everyone_desc']),
		array('check', 'mentions_expanded_here', 'subtext' => $txt['mentions_expanded_here_desc']),
		array('int', 'mentions_expanded_here_minutes', 'subtext' => $txt['mentions_expanded_here_minutes_desc'], 'postinput' => $txt['minutes']),
		array('check', 'mentions_expanded_groups_enabled', 'subtext' => $txt['mentions_expanded_groups_enabled_desc']),
		array('callback', 'mentions_expanded_groups'),
		'',
		array('check', 'mentions_expanded_highlight'),
		array('check', 'mentions_expanded_email', 'subtext' => $txt['mentions_expanded_email_desc']),
		array('int', 'mentions_expanded_batch', 'subtext' => $txt['mentions_expanded_batch_desc']),
	);

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['mentions_expanded_title'];
	$context['settings_title'] = $txt['mentions_expanded_title'];
	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=mentionsexpanded';

	if (empty($modSettings['enable_mentions']))
		$context['settings_message'] = array('label' => $txt['mentions_expanded_core_off'], 'tag' => 'div', 'class' => 'errorbox');

	if (isset($_GET['save']))
	{
		checkSession();

		// Only allow valid, non-post-count groups. This is not just a UI restriction.
		$groups = array();
		if (!empty($_POST['mentions_expanded_groups']) && is_array($_POST['mentions_expanded_groups']))
		{
			$requested = array_unique(array_filter(array_map('intval', $_POST['mentions_expanded_groups']), function ($id) { return $id > 0 && $id !== 3; }));
			if (!empty($requested))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_group FROM {db_prefix}membergroups
					WHERE id_group IN ({array_int:groups}) AND min_posts = {int:no_posts}',
					array('groups' => array_values($requested), 'no_posts' => -1)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$groups[] = (int) $row['id_group'];
				$smcFunc['db_free_result']($request);
			}
		}

		$_POST['mentions_expanded_here_minutes'] = max(1, min(1440, (int) (isset($_POST['mentions_expanded_here_minutes']) ? $_POST['mentions_expanded_here_minutes'] : 15)));
		$_POST['mentions_expanded_batch'] = max(50, min(2000, (int) (isset($_POST['mentions_expanded_batch']) ? $_POST['mentions_expanded_batch'] : 500)));

		saveDBSettings($config_vars);
		updateSettings(array('mentions_expanded_groups' => implode(',', array_unique($groups))));
		cache_put_data('mex_groups', null);

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=modsettings;sa=mentionsexpanded');
	}

	// Groups for the checkbox list (no post-count groups, no local moderators).
	$selected = array_filter(array_map('intval', explode(',', isset($modSettings['mentions_expanded_groups']) ? $modSettings['mentions_expanded_groups'] : '')));
	$context['mex_all_groups'] = array();

	$request = $smcFunc['db_query']('', '
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE min_posts = {int:no_posts}
			AND id_group != {int:moderators}
		ORDER BY group_name',
		array(
			'no_posts' => -1,
			'moderators' => 3,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['mex_all_groups'][] = array(
			'id' => (int) $row['id_group'],
			'name' => $row['group_name'],
			'checked' => in_array((int) $row['id_group'], $selected),
		);
	$smcFunc['db_free_result']($request);

	prepareDBSettingContext($config_vars);
}

/**
 * integrate_credits
 */
function mex_credits()
{
	global $context, $txt;

	loadLanguage('MentionsExpanded');

	$context['copyrights']['mods'][] = sprintf($txt['mentions_expanded_credits'], $txt['mentions_expanded_by']);
}
