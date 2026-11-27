<?php

/**
 * Clubs: pages and actions (directory, club tabs, membership, events, admin).
 *
 * @package Clubs
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.3
 */

if (!defined('SMF'))
	die('No direct access...');

class Clubs_Pages
{
	public static function action()
	{
		global $context, $txt, $scripturl;

		if (!Clubs::enabled())
			redirectexit();

		isAllowedTo('clubs_view');

		loadLanguage('Clubs');
		loadTemplate('Clubs');
		loadJavaScriptFile('clubs.js', array('minimize' => true, 'defer' => true, 'seed' => Clubs::VERSION), 'smf_clubs');

		$context['clubs_url'] = $scripturl . '?action=clubs';
		$context['clubs_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['clubs_title'];
		$context['linktree'][] = array('url' => $context['clubs_url'], 'name' => $txt['clubs_title']);

		$context['clubs_notice'] = isset($_SESSION['clubs_notice']) ? $_SESSION['clubs_notice'] : null;
		unset($_SESSION['clubs_notice']);

		$sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'list';
		$pages = array(
			'list' => 'directory', 'create' => 'create', 'view' => 'view',
			'join' => 'join', 'request' => 'request', 'cancel' => 'cancel', 'leave' => 'leave',
			'accept' => 'accept', 'decline' => 'decline', 'member' => 'member', 'invite' => 'invite',
			'manage' => 'manage', 'addboard' => 'addBoard', 'removeboard' => 'removeBoard',
			'delete' => 'delete', 'event' => 'event', 'rsvp' => 'rsvp',
		);

		$method = isset($pages[$sa]) ? $pages[$sa] : 'directory';
		self::$method();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	protected static function notice($text, $type = 'info')
	{
		$_SESSION['clubs_notice'] = array($type, $text);
	}

	protected static function back(array $club, $tab = '')
	{
		redirectexit('action=clubs;sa=view;club=' . $club['id_club'] . ($tab !== '' ? ';tab=' . $tab : ''));
	}

	/**
	 * Load the club from the request and check the member can see it.
	 */
	protected static function requireClub()
	{
		$club = Clubs::load(isset($_REQUEST['club']) ? (int) $_REQUEST['club'] : 0);
		if (!$club || !Clubs::canSee($club))
			fatal_lang_error('clubs_error_not_found', false);

		return $club;
	}

	protected static function requireLeader(array $club)
	{
		if (!Clubs::isLeader($club))
			fatal_lang_error('clubs_error_not_leader', false);
	}

	protected static function findMembers($names)
	{
		global $smcFunc;

		$names = array_filter(array_map('trim', preg_split('~[,\n]+~', (string) $names)));
		$names = array_slice(array_unique($names), 0, 20);
		if (empty($names))
			return array();

		$escaped = array();
		foreach ($names as $n)
			$escaped[] = $smcFunc['htmlspecialchars'](mb_strtolower($n), ENT_QUOTES);

		$found = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE (LOWER(real_name) IN ({array_string:names}) OR LOWER(member_name) IN ({array_string:names}))
				AND is_activated = {int:active}',
			array('names' => $escaped, 'active' => 1)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$found[(int) $row['id_member']] = $row['real_name'];
		$smcFunc['db_free_result']($request);

		return $found;
	}

	protected static function people(array $ids)
	{
		global $memberContext;

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$out = array();
		if (empty($ids))
			return $out;

		foreach ((array) loadMemberData($ids) as $id)
			if (loadMemberContext($id))
				$out[$id] = array(
					'id' => $id,
					'name' => $memberContext[$id]['name'],
					'link' => $memberContext[$id]['link'],
					'avatar' => !empty($memberContext[$id]['avatar']['href']) ? $memberContext[$id]['avatar']['href'] : '',
				);

		return $out;
	}

	public static function card(array $row)
	{
		global $scripturl;

		return array(
			'id' => (int) $row['id_club'],
			'name' => $row['name'],
			'summary' => Clubs::plain($row['description'], 140),
			'type' => $row['type'],
			'status' => $row['status'],
			'members' => (int) $row['num_members'],
			'icon' => Clubs::iconUrl($row),
			'color' => preg_match('~^#[0-9a-f]{6}$~i', $row['color']) ? $row['color'] : '#557ea0',
			'role' => isset($row['my_role']) ? $row['my_role'] : '',
			'url' => $scripturl . '?action=clubs;sa=view;club=' . $row['id_club'],
		);
	}

	/* ------------------------------------------------------------------
	 * Directory
	 * ---------------------------------------------------------------- */

	public static function directory()
	{
		global $context, $smcFunc, $user_info, $txt;

		$me = (int) $user_info['id'];
		$admin = Clubs::isAdmin();
		$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
		$sort = isset($_GET['sort']) && in_array($_GET['sort'], array('members', 'newest', 'name'), true) ? $_GET['sort'] : 'members';
		$mine = !empty($_GET['mine']) && $me > 0;

		$where = array();
		$params = array('me' => $me, 'active' => 'active', 'private' => 'private', 'in' => array('owner', 'leader', 'member', 'invited'), 'joined' => Clubs::$in_group);

		if (!$admin)
		{
			$where[] = '(c.status = {string:active} OR c.id_owner = {int:me})';
			$where[] = '(c.type != {string:private} OR cm.role IN ({array_string:in}))';
		}
		if ($mine)
			$where[] = 'cm.role IN ({array_string:joined})';
		if ($search !== '')
		{
			$where[] = 'LOWER(c.name) LIKE {string:search}';
			$params['search'] = '%' . strtr($smcFunc['htmlspecialchars'](mb_strtolower($search), ENT_QUOTES), array('%' => '\%', '_' => '\_')) . '%';
		}

		$where_sql = empty($where) ? '1=1' : implode(' AND ', $where);
		$order = array('members' => 'c.num_members DESC, c.id_club DESC', 'newest' => 'c.created DESC', 'name' => 'c.name')[$sort];

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}clubs AS c
				LEFT JOIN {db_prefix}club_members AS cm ON (cm.id_club = c.id_club AND cm.id_member = {int:me})
			WHERE ' . $where_sql,
			$params
		);
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$per_page = 24;
		$base = $context['clubs_url'] . ($search !== '' ? ';q=' . urlencode($search) : '') . ';sort=' . $sort . ($mine ? ';mine=1' : '');
		$context['page_index'] = constructPageIndex($base, $start, (int) $total, $per_page);

		$context['clubs_list'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT c.id_club, c.name, c.description, c.type, c.status, c.num_members, c.icon, c.color, COALESCE(cm.role, {string:empty}) AS my_role
			FROM {db_prefix}clubs AS c
				LEFT JOIN {db_prefix}club_members AS cm ON (cm.id_club = c.id_club AND cm.id_member = {int:me})
			WHERE ' . $where_sql . '
			ORDER BY ' . $order . '
			LIMIT {int:start}, {int:per_page}',
			$params + array('start' => $start, 'per_page' => $per_page, 'empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['clubs_list'][] = self::card($row);
		$smcFunc['db_free_result']($request);

		$context['clubs_search'] = $search;
		$context['clubs_sort'] = $sort;
		$context['clubs_mine'] = $mine;
		$context['clubs_can_create'] = self::canCreate();
		$context['clubs_invites'] = $me ? self::myInvites($me) : array();
		$context['sub_template'] = 'clubs_directory';
	}

	protected static function myInvites($me)
	{
		global $smcFunc;

		$out = array();
		$request = $smcFunc['db_query']('', '
			SELECT c.id_club, c.name, c.description, c.type, c.status, c.num_members, c.icon, c.color, cm.role AS my_role
			FROM {db_prefix}club_members AS cm
				INNER JOIN {db_prefix}clubs AS c ON (c.id_club = cm.id_club)
			WHERE cm.id_member = {int:me}
				AND cm.role = {string:invited}
				AND c.status = {string:active}',
			array('me' => $me, 'invited' => 'invited', 'active' => 'active')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$out[] = self::card($row);
		$smcFunc['db_free_result']($request);

		return $out;
	}

	protected static function canCreate()
	{
		global $user_info;

		if ($user_info['is_guest'] || !allowedTo('clubs_create'))
			return false;
		if (Clubs::isAdmin())
			return true;

		$max = (int) Clubs::setting('max_owned', 3);

		return $max === 0 || Clubs::countOwned($user_info['id']) < $max;
	}

	/* ------------------------------------------------------------------
	 * Create
	 * ---------------------------------------------------------------- */

	protected static function readForm(array &$errors, $existing = null)
	{
		global $smcFunc, $sourcedir, $txt;

		$name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
		$name = preg_replace('~\s+~u', ' ', strip_tags($name));
		if (mb_strlen($name) < 3 || mb_strlen($name) > 60)
			$errors[] = $txt['clubs_error_name'];

		$escaped_name = $smcFunc['htmlspecialchars']($name, ENT_QUOTES);

		$request = $smcFunc['db_query']('', '
			SELECT id_club
			FROM {db_prefix}clubs
			WHERE LOWER(name) = LOWER({string:name})' . ($existing ? '
				AND id_club != {int:id}' : ''),
			array('name' => $escaped_name, 'id' => $existing ? $existing['id_club'] : 0)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
			$errors[] = $txt['clubs_error_name_taken'];
		$smcFunc['db_free_result']($request);

		$description = mb_substr(str_replace("\r", '', isset($_POST['description']) ? (string) $_POST['description'] : ''), 0, 5000);
		$description = $smcFunc['htmlspecialchars']($description, ENT_QUOTES);
		require_once($sourcedir . '/Subs-Post.php');
		preparsecode($description);

		$type = isset($_POST['type']) && in_array($_POST['type'], Clubs::allowedTypes(), true) ? $_POST['type'] : ($existing ? $existing['type'] : Clubs::allowedTypes()[0]);
		$color = isset($_POST['color']) && preg_match('~^#[0-9a-f]{6}$~i', $_POST['color']) ? strtolower($_POST['color']) : '#557ea0';

		// Files are only deleted after the club has been saved (see finishIcon()).
		$icon = $existing ? $existing['icon'] : '';
		$uploaded = '';
		if (!empty($_POST['icon_remove']))
			$icon = '';
		if (!empty($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['icon']['tmp_name']))
		{
			$error = '';
			$new = Clubs::saveIcon($_FILES['icon']['tmp_name'], $_FILES['icon']['size'], $error);
			if ($new === '')
				$errors[] = $txt[$error];
			else
				$icon = $uploaded = $new;
		}

		return array(
			'uploaded_icon' => $uploaded,
			'name' => $escaped_name,
			'description' => $description,
			'type' => $type,
			'color' => $color,
			'icon' => $icon,
			'member_events' => !empty($_POST['member_events']),
		);
	}

	/**
	 * After saving (or failing to save) a club: delete whichever icon file
	 * is no longer used.
	 *
	 * @param string $old The icon before the form was sent
	 * @param bool $saved Whether the club was saved
	 */
	protected static function finishIcon(array $data, $old, $saved)
	{
		$remove = array();
		if (!$saved)
			$remove[] = $data['uploaded_icon'];
		elseif ($old !== '' && $old !== $data['icon'])
			$remove[] = $old;

		foreach (array_filter($remove) as $file)
			@unlink(Clubs::iconDir() . '/' . basename($file));
	}

	public static function create()
	{
		global $context, $user_info, $txt, $sourcedir;

		if (!self::canCreate())
			fatal_lang_error('clubs_error_cannot_create', false);

		$context['clubs_errors'] = array();
		$context['clubs_form'] = array('name' => '', 'description' => '', 'type' => Clubs::allowedTypes()[0], 'color' => '#557ea0', 'icon' => '', 'member_events' => false);

		if (isset($_POST['save']))
		{
			checkSession();

			$errors = array();
			$data = self::readForm($errors);

			if (empty($errors))
			{
				unset($data['uploaded_icon']);
				$id = Clubs::create($data, $user_info['id']);
				$club = Clubs::load($id, true);
				self::notice($club['status'] === 'pending' ? $txt['clubs_created_pending'] : $txt['clubs_created']);
				self::back($club);
			}

			$context['clubs_errors'] = $errors;
			self::finishIcon($data, '', false);
			$data['icon'] = '';
			require_once($sourcedir . '/Subs-Post.php');
			$data['description'] = un_preparsecode($data['description']);
			$context['clubs_form'] = $data;
		}

		$context['clubs_types'] = Clubs::allowedTypes();
		$context['clubs_editing'] = false;
		$context['page_title'] = $txt['clubs_create'];
		$context['linktree'][] = array('name' => $txt['clubs_create']);
		$context['sub_template'] = 'clubs_form';
	}

	/* ------------------------------------------------------------------
	 * Club page
	 * ---------------------------------------------------------------- */

	public static function view()
	{
		global $context, $txt, $scripturl, $user_info;

		$club = self::requireClub();

		$tabs = array('overview', 'members');
		$can_content = Clubs::canSeeContent($club) && $club['status'] === 'active';
		if ($can_content)
		{
			$tabs[] = 'media';
			if (Clubs::calendarEnabled())
				$tabs[] = 'events';
		}
		if (Clubs::isLeader($club))
			$tabs[] = 'manage';

		$tab = isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true) ? $_GET['tab'] : 'overview';
		$role = Clubs::role($club['id_club']);

		$context['club'] = $club + array(
			'icon_url' => Clubs::iconUrl($club),
			'url' => $scripturl . '?action=clubs;sa=view;club=' . $club['id_club'],
			'board_url' => $club['id_board'] ? $scripturl . '?board=' . $club['id_board'] . '.0' : '',
			'description_html' => parse_bbc($club['description']),
		);
		$context['club_role'] = $role;
		$context['club_is_member'] = Clubs::isMember($club);
		$context['club_is_leader'] = Clubs::isLeader($club);
		$context['club_is_owner'] = Clubs::isOwner($club);
		$context['club_can_content'] = $can_content;
		$context['club_tabs'] = $tabs;
		$context['club_tab'] = $tab;
		$context['club_can_join'] = !$user_info['is_guest'] && allowedTo('clubs_join') && $club['status'] === 'active';

		$context['club_header'] = array(
			'club' => $club,
			'icon' => $context['club']['icon_url'],
			'url' => $context['club']['url'],
			'active' => $tab,
			'can_see_content' => $can_content,
			'is_leader' => $context['club_is_leader'],
			'events' => Clubs::calendarEnabled(),
			'tabs' => $tabs,
		);

		$context['page_title'] = strip_tags($club['name']);
		$context['linktree'][] = array('url' => $context['club']['url'], 'name' => $club['name']);

		$method = 'tab' . ucfirst($tab);
		self::$method($club);

		$context['sub_template'] = 'clubs_view';
	}

	protected static function tabOverview(array $club)
	{
		global $context, $smcFunc, $scripturl;

		$leaders = Clubs::leaders($club['id_club']);

		$recent = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND role IN ({array_string:roles})
			ORDER BY joined DESC
			LIMIT 12',
			array('club' => $club['id_club'], 'roles' => Clubs::$in_group)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$recent[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$people = self::people(array_merge($leaders, $recent));
		$context['club_leaders'] = array_values(array_intersect_key($people, array_flip($leaders)));
		$context['club_recent'] = array_values(array_intersect_key($people, array_flip($recent)));

		$context['club_topics'] = array();
		$context['club_events'] = array();
		$context['club_stats'] = array('topics' => 0, 'posts' => 0);

		$boards = Clubs::clubBoards($club['id_club']);
		if (empty($boards) || !$context['club_can_content'])
			return;

		$request = $smcFunc['db_query']('', '
			SELECT SUM(num_topics), SUM(num_posts)
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:boards})',
			array('boards' => $boards)
		);
		list ($topics, $posts) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		$context['club_stats'] = array('topics' => (int) $topics, 'posts' => (int) $posts);

		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.num_replies, fm.subject, lm.poster_time, COALESCE(lmem.real_name, lm.poster_name) AS last_poster, b.name AS board_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS lm ON (lm.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS lmem ON (lmem.id_member = lm.id_member)
			WHERE t.id_board IN ({array_int:boards})
				AND {query_see_board}
				AND t.approved = {int:yes}
			ORDER BY t.id_last_msg DESC
			LIMIT 6',
			array('boards' => $boards, 'yes' => 1)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['club_topics'][] = array(
				'url' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new',
				'subject' => $row['subject'],
				'replies' => (int) $row['num_replies'],
				'last_poster' => $row['last_poster'],
				'time' => timeformat($row['poster_time']),
				'board' => $row['board_name'],
			);
		$smcFunc['db_free_result']($request);

		if (Clubs::calendarEnabled())
			$context['club_events'] = array_slice(self::events($club, false), 0, 3);
	}

	protected static function tabMembers(array $club)
	{
		global $context, $smcFunc;

		$lists = array('leaders' => array(), 'members' => array(), 'pending' => array(), 'invited' => array(), 'banned' => array());
		$messages = array();
		$all = array();

		$request = $smcFunc['db_query']('', '
			SELECT id_member, role, joined, message
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
			ORDER BY CASE role WHEN {string:owner} THEN 0 WHEN {string:leader} THEN 1 ELSE 2 END, joined
			LIMIT 1000',
			array('club' => $club['id_club'], 'owner' => 'owner', 'leader' => 'leader')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$id = (int) $row['id_member'];
			$key = in_array($row['role'], array('owner', 'leader'), true) ? 'leaders' : ($row['role'] === 'member' ? 'members' : $row['role']);
			if (!isset($lists[$key]))
				continue;
			$lists[$key][$id] = array('role' => $row['role'], 'joined' => timeformat($row['joined']), 'message' => $row['message']);
			$all[] = $id;
		}
		$smcFunc['db_free_result']($request);

		$people = self::people($all);
		$is_leader = Clubs::isLeader($club);

		$context['club_members'] = array();
		foreach ($lists as $key => $rows)
		{
			// Requests, invitations and bans are only for leaders.
			if (!$is_leader && in_array($key, array('pending', 'invited', 'banned'), true))
				continue;

			$context['club_members'][$key] = array();
			foreach ($rows as $id => $info)
				if (isset($people[$id]))
					$context['club_members'][$key][] = $people[$id] + $info;
		}
	}

	protected static function tabMedia(array $club)
	{
		global $context, $smcFunc, $scripturl;

		$boards = Clubs::clubBoards($club['id_club']);
		$context['club_media'] = array();
		$context['page_index'] = '';
		if (empty($boards))
			return;

		$params = array('boards' => $boards, 'yes' => 1, 'normal' => 0, 'ext' => array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'));
		$from = '
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.id_board IN ({array_int:boards})
				AND {query_see_board}
				AND a.attachment_type = {int:normal}
				AND a.approved = {int:yes}
				AND m.approved = {int:yes}
				AND LOWER(a.fileext) IN ({array_string:ext})';

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) ' . $from, $params);
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['club']['url'] . ';tab=media', $start, (int) $total, 30);

		$request = $smcFunc['db_query']('', '
			SELECT a.id_attach, a.id_thumb, a.filename, m.id_topic, m.id_msg, m.subject ' . $from . '
			ORDER BY a.id_attach DESC
			LIMIT {int:start}, 30',
			$params + array('start' => $start)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$base = $scripturl . '?action=dlattach;topic=' . $row['id_topic'] . '.0;attach=';
			$context['club_media'][] = array(
				'thumb' => $base . ((int) $row['id_thumb'] > 0 ? $row['id_thumb'] : $row['id_attach']) . ';image',
				'full' => $base . $row['id_attach'] . ';image',
				'name' => $row['filename'],
				'post' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'subject' => $row['subject'],
			);
		}
		$smcFunc['db_free_result']($request);

		$context['club_can_upload'] = $club['type'] === 'readonly' ? Clubs::isLeader($club) : Clubs::isMember($club) || Clubs::isAdmin();
		$context['club_upload_url'] = $scripturl . '?action=post;board=' . $club['id_board'] . '.0';
	}

	/**
	 * Club events from SMF's calendar, with RSVP counts.
	 */
	protected static function events(array $club, $include_past = true)
	{
		global $smcFunc, $user_info, $scripturl;

		$boards = Clubs::clubBoards($club['id_club']);
		if (empty($boards))
			return array();

		$since = $include_past ? date('Y-m-d', time() - 90 * 86400) : date('Y-m-d');

		$events = array();
		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event, cal.title, cal.start_date, cal.end_date, cal.location, cal.id_topic, COALESCE(mem.real_name, {string:empty}) AS creator
			FROM {db_prefix}calendar AS cal
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)
			WHERE cal.id_board IN ({array_int:boards})
				AND {query_see_board}
				AND cal.end_date >= {date:since}
			ORDER BY cal.start_date, cal.id_event
			LIMIT 100',
			array('boards' => $boards, 'since' => $since, 'empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$events[(int) $row['id_event']] = array(
				'id' => (int) $row['id_event'],
				'title' => $row['title'],
				'start' => $row['start_date'],
				'end' => $row['end_date'],
				'location' => $row['location'],
				'creator' => $row['creator'],
				'topic_url' => (int) $row['id_topic'] > 0 ? $scripturl . '?topic=' . $row['id_topic'] . '.0' : '',
				'past' => $row['end_date'] < date('Y-m-d'),
				'counts' => array('going' => 0, 'maybe' => 0, 'no' => 0),
				'mine' => '',
			);
		$smcFunc['db_free_result']($request);

		if (!empty($events))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_event, id_member, status
				FROM {db_prefix}club_rsvp
				WHERE id_event IN ({array_int:events})',
				array('events' => array_keys($events))
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$e = (int) $row['id_event'];
				if (isset($events[$e]['counts'][$row['status']]))
					$events[$e]['counts'][$row['status']]++;
				if ((int) $row['id_member'] === (int) $user_info['id'])
					$events[$e]['mine'] = $row['status'];
			}
			$smcFunc['db_free_result']($request);
		}

		return array_values($events);
	}

	protected static function canCreateEvent(array $club)
	{
		if (!Clubs::calendarEnabled() || $club['status'] !== 'active' || $club['id_board'] <= 0)
			return false;

		if (Clubs::isLeader($club))
			return true;

		// Each event starts a discussion topic, so in read-only clubs (where
		// only leaders post) only leaders can add events.
		return $club['type'] !== 'readonly' && $club['member_events'] && Clubs::isMember($club);
	}

	protected static function tabEvents(array $club)
	{
		global $context;

		$events = self::events($club, true);
		$context['club_events_upcoming'] = array_values(array_filter($events, function ($e) { return !$e['past']; }));
		$context['club_events_past'] = array_reverse(array_values(array_filter($events, function ($e) { return $e['past']; })));
		$context['club_can_event'] = self::canCreateEvent($club);
		$context['club_can_rsvp'] = Clubs::isMember($club) || Clubs::isAdmin();
	}

	protected static function tabManage(array $club)
	{
		global $context, $smcFunc, $sourcedir;

		require_once($sourcedir . '/Subs-Post.php');
		$context['clubs_form'] = $club;
		$context['clubs_form']['description'] = un_preparsecode($club['description']);
		$context['clubs_types'] = array_values(array_unique(array_merge(Clubs::allowedTypes(), array($club['type']))));
		$context['clubs_editing'] = true;
		$context['clubs_errors'] = isset($_SESSION['clubs_form_errors']) ? $_SESSION['clubs_form_errors'] : array();
		unset($_SESSION['clubs_form_errors']);

		$context['club_boards'] = array();
		$boards = Clubs::clubBoards($club['id_club']);
		if (!empty($boards))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_board, name, description, num_topics, num_posts
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:boards})
				ORDER BY board_order',
				array('boards' => $boards)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$context['club_boards'][] = $row + array('main' => (int) $row['id_board'] === $club['id_board']);
			$smcFunc['db_free_result']($request);
		}

		$context['club_max_boards'] = (int) Clubs::setting('max_boards', 3);

		$context['club_transfer_to'] = array();
		if (Clubs::isOwner($club))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}club_members
				WHERE id_club = {int:club}
					AND role IN ({array_string:roles})
				LIMIT 200',
				array('club' => $club['id_club'], 'roles' => array('leader', 'member'))
			);
			$ids = array();
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);
			$context['club_transfer_to'] = self::people($ids);
		}
	}

	/* ------------------------------------------------------------------
	 * Membership actions
	 * ---------------------------------------------------------------- */

	public static function join()
	{
		global $user_info, $txt;

		checkSession('get');
		is_not_guest();
		isAllowedTo('clubs_join');

		$club = self::requireClub();
		$role = Clubs::role($club['id_club']);

		if ($role === 'banned')
			fatal_lang_error('clubs_error_banned', false);
		if (in_array($role, Clubs::$in_group, true))
			self::back($club);
		if ($role === 'invited')
		{
			Clubs::setRole($club['id_club'], $user_info['id'], 'member');
			self::notice($txt['clubs_joined']);
			self::back($club);
		}
		if ($club['type'] !== 'open' || $club['status'] !== 'active')
			fatal_lang_error($club['type'] === 'closed' ? 'clubs_error_request_needed' : 'clubs_error_invite_only', false);

		Clubs::setRole($club['id_club'], $user_info['id'], 'member');
		self::notice($txt['clubs_joined']);
		self::back($club);
	}

	public static function request()
	{
		global $user_info, $txt, $smcFunc;

		checkSession();
		is_not_guest();
		isAllowedTo('clubs_join');

		$club = self::requireClub();
		$role = Clubs::role($club['id_club']);

		if ($role === 'banned')
			fatal_lang_error('clubs_error_banned', false);
		if ($club['type'] !== 'closed' || $club['status'] !== 'active' || $role !== null)
			self::back($club);

		$message = mb_substr(trim(strip_tags(isset($_POST['message']) ? (string) $_POST['message'] : '')), 0, 250);
		Clubs::setRole($club['id_club'], $user_info['id'], 'pending', $smcFunc['htmlspecialchars']($message, ENT_QUOTES));
		Clubs::alert(Clubs::leaders($club['id_club']), 'request', $user_info['id'], $user_info['name'], $club);

		self::notice($txt['clubs_requested']);
		self::back($club);
	}

	public static function cancel()
	{
		global $user_info, $txt;

		checkSession('get');
		$club = self::requireClub();
		if (Clubs::role($club['id_club']) === 'pending')
			Clubs::setRole($club['id_club'], $user_info['id'], null);

		self::notice($txt['clubs_request_cancelled']);
		self::back($club);
	}

	public static function leave()
	{
		global $user_info, $txt;

		checkSession('get');
		$club = self::requireClub();
		$role = Clubs::role($club['id_club']);

		if ($role === 'owner')
			fatal_lang_error('clubs_error_owner_leave', false);
		if (in_array($role, array('leader', 'member'), true))
			Clubs::setRole($club['id_club'], $user_info['id'], null);

		self::notice($txt['clubs_left']);
		if ($club['type'] === 'private')
			redirectexit('action=clubs');
		self::back($club);
	}

	public static function accept()
	{
		global $user_info, $txt;

		checkSession('get');
		$club = self::requireClub();
		if (Clubs::role($club['id_club']) === 'invited')
		{
			Clubs::setRole($club['id_club'], $user_info['id'], 'member');
			self::notice($txt['clubs_joined']);
		}
		self::back($club);
	}

	public static function decline()
	{
		global $user_info, $txt;

		checkSession('get');
		$club = self::requireClub();
		if (Clubs::role($club['id_club']) === 'invited')
			Clubs::setRole($club['id_club'], $user_info['id'], null);

		self::notice($txt['clubs_invite_declined']);
		redirectexit('action=clubs');
	}

	/**
	 * Leader actions on a member: approve, deny, promote, demote, remove,
	 * ban, unban, cancel invitation, make owner.
	 */
	public static function member()
	{
		global $user_info, $txt, $smcFunc;

		checkSession('get');
		$club = self::requireClub();
		self::requireLeader($club);

		$target = isset($_GET['u']) ? (int) $_GET['u'] : 0;
		$do = isset($_GET['do']) ? (string) $_GET['do'] : '';
		$role = Clubs::role($club['id_club'], $target);
		$i_am_owner = Clubs::isOwner($club);

		if ($target <= 0 || $role === null && $do !== 'ban')
			self::back($club, 'members');

		// Only the owner (or an admin) can act on leaders; nobody acts on the owner.
		if ($role === 'owner' || ($role === 'leader' && !$i_am_owner))
			fatal_lang_error('clubs_error_not_allowed', false);

		$name = '';
		$request = $smcFunc['db_query']('', 'SELECT real_name FROM {db_prefix}members WHERE id_member = {int:id}', array('id' => $target));
		if ($row = $smcFunc['db_fetch_row']($request))
			$name = $row[0];
		$smcFunc['db_free_result']($request);

		switch ($do)
		{
			case 'approve':
				if ($role === 'pending')
				{
					Clubs::setRole($club['id_club'], $target, 'member');
					Clubs::alert(array($target), 'approved', $user_info['id'], $user_info['name'], $club);
				}
				break;

			case 'deny':
				if ($role === 'pending')
				{
					Clubs::setRole($club['id_club'], $target, null);
					Clubs::alert(array($target), 'denied', $user_info['id'], $user_info['name'], $club);
				}
				break;

			case 'promote':
				if ($role === 'member' && $i_am_owner)
					Clubs::setRole($club['id_club'], $target, 'leader');
				elseif ($role === 'member')
					fatal_lang_error('clubs_error_not_allowed', false);
				break;

			case 'demote':
				if ($role === 'leader')
					Clubs::setRole($club['id_club'], $target, 'member');
				break;

			case 'remove':
			case 'cancelinvite':
				if (in_array($role, array('leader', 'member', 'invited', 'pending'), true))
					Clubs::setRole($club['id_club'], $target, null);
				break;

			case 'ban':
				if ($target !== (int) $user_info['id'])
					Clubs::setRole($club['id_club'], $target, 'banned');
				break;

			case 'unban':
				if ($role === 'banned')
					Clubs::setRole($club['id_club'], $target, null);
				break;

			case 'transfer':
				if ($i_am_owner && in_array($role, array('leader', 'member'), true))
				{
					$old_owner = $club['id_owner'];
					Clubs::setRole($club['id_club'], $target, 'owner');
					if ($old_owner > 0 && $old_owner !== $target)
						Clubs::setRole($club['id_club'], $old_owner, 'leader');
					$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_owner = {int:o} WHERE id_club = {int:c}', array('o' => $target, 'c' => $club['id_club']));
					Clubs::forget($club['id_club']);
				}
				break;
		}

		self::notice(sprintf($txt['clubs_member_updated'], $name));
		self::back($club, $do === 'transfer' ? 'manage' : 'members');
	}

	public static function invite()
	{
		global $user_info, $txt;

		checkSession();
		$club = self::requireClub();
		self::requireLeader($club);

		$found = self::findMembers(isset($_POST['names']) ? $_POST['names'] : '');
		$invited = array();

		foreach ($found as $id => $name)
		{
			$role = Clubs::role($club['id_club'], $id);
			if ($role === null || $role === 'pending')
			{
				// A pending request becomes membership straight away.
				if ($role === 'pending')
					Clubs::setRole($club['id_club'], $id, 'member');
				else
				{
					Clubs::setRole($club['id_club'], $id, 'invited', '', $user_info['id']);
					$invited[] = $id;
				}
			}
		}

		Clubs::alert($invited, 'invited', $user_info['id'], $user_info['name'], $club);

		self::notice(empty($found) ? $txt['clubs_invite_none'] : sprintf($txt['clubs_invited_n'], count($invited)), empty($found) ? 'error' : 'info');
		self::back($club, 'members');
	}

	/* ------------------------------------------------------------------
	 * Managing
	 * ---------------------------------------------------------------- */

	public static function manage()
	{
		global $smcFunc, $txt;

		checkSession();
		$club = self::requireClub();
		self::requireLeader($club);

		$errors = array();
		$data = self::readForm($errors, $club);

		// Only the owner (or an admin) can change the type.
		if (!Clubs::isOwner($club))
			$data['type'] = $club['type'];

		if (!empty($errors))
		{
			self::finishIcon($data, $club['icon'], false);
			$_SESSION['clubs_form_errors'] = $errors;
			self::back($club, 'manage');
		}

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}clubs
			SET name = {string:name}, description = {string:description}, type = {string:type}, color = {string:color},
				icon = {string:icon}, member_events = {int:member_events}
			WHERE id_club = {int:id}',
			array(
				'name' => $data['name'], 'description' => $data['description'], 'type' => $data['type'], 'color' => $data['color'],
				'icon' => $data['icon'], 'member_events' => $data['member_events'] ? 1 : 0, 'id' => $club['id_club'],
			)
		);

		if ($club['id_board'] > 0)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}boards
				SET name = {string:name}, description = {string:description}
				WHERE id_board = {int:board}',
				array('name' => $data['name'], 'description' => Clubs::plain($data['description'], 250), 'board' => $club['id_board'])
			);

		self::finishIcon($data, $club['icon'], true);

		Clubs::forget($club['id_club']);
		if ($data['type'] !== $club['type'] || $data['name'] !== $club['name'])
			Clubs::syncAccess($club['id_club']);

		// The profile name follows the club name.
		if ($data['name'] !== $club['name'] && $club['id_profile'] > 0)
			$smcFunc['db_query']('', 'UPDATE {db_prefix}permission_profiles SET profile_name = {string:n} WHERE id_profile = {int:p}', array('n' => mb_substr('Club: ' . un_htmlspecialchars($data['name']), 0, 255), 'p' => $club['id_profile']));

		// The group name follows the club name.
		if ($data['name'] !== $club['name'] && $club['id_group'] > 0)
		{
			loadLanguage('Clubs');
			$smcFunc['db_query']('', 'UPDATE {db_prefix}membergroups SET group_name = {string:n} WHERE id_group = {int:g}', array('n' => mb_substr(sprintf($txt['clubs_group_name'], $data['name']), 0, 80), 'g' => $club['id_group']));
			if ($club['id_ban_group'] > 0)
				$smcFunc['db_query']('', 'UPDATE {db_prefix}membergroups SET group_name = {string:n} WHERE id_group = {int:g}', array('n' => mb_substr(sprintf($txt['clubs_ban_group_name'], un_htmlspecialchars($data['name'])), 0, 80), 'g' => $club['id_ban_group']));
		}

		self::notice($txt['clubs_saved']);
		self::back($club, 'manage');
	}

	public static function addBoard()
	{
		global $smcFunc, $txt;

		checkSession();
		$club = self::requireClub();
		self::requireLeader($club);

		$extra = count(Clubs::clubBoards($club['id_club'])) - 1;
		if ($extra >= (int) Clubs::setting('max_boards', 3) || $club['id_board'] <= 0)
			fatal_lang_error('clubs_error_board_limit', false);

		$name = mb_substr(trim(strip_tags(isset($_POST['board_name']) ? (string) $_POST['board_name'] : '')), 0, 60);
		$desc = mb_substr(trim(strip_tags(isset($_POST['board_description']) ? (string) $_POST['board_description'] : '')), 0, 250);
		if ($name === '')
			self::back($club, 'manage');

		Clubs::createBoard($club, $smcFunc['htmlspecialchars']($name, ENT_QUOTES), $smcFunc['htmlspecialchars']($desc, ENT_QUOTES), $club['id_board']);
		Clubs::syncAccess($club['id_club']);

		self::notice($txt['clubs_board_added']);
		self::back($club, 'manage');
	}

	public static function removeBoard()
	{
		global $txt;

		checkSession('get');
		$club = self::requireClub();
		self::requireLeader($club);

		Clubs::removeBoard($club, isset($_GET['board']) ? (int) $_GET['board'] : 0);

		self::notice($txt['clubs_board_removed']);
		self::back($club, 'manage');
	}

	public static function delete()
	{
		global $txt;

		checkSession();
		$club = self::requireClub();
		if (!Clubs::isOwner($club))
			fatal_lang_error('clubs_error_not_allowed', false);

		if (trim((string) (isset($_POST['confirm_name']) ? $_POST['confirm_name'] : '')) !== un_htmlspecialchars($club['name']))
		{
			$_SESSION['clubs_form_errors'] = array($txt['clubs_error_confirm_name']);
			self::back($club, 'manage');
		}

		Clubs::delete($club['id_club']);
		self::notice($txt['clubs_deleted']);
		redirectexit('action=clubs');
	}

	/* ------------------------------------------------------------------
	 * Events
	 * ---------------------------------------------------------------- */

	public static function event()
	{
		global $smcFunc, $sourcedir, $user_info, $txt;

		checkSession();
		$club = self::requireClub();
		if (!self::canCreateEvent($club))
			fatal_lang_error('clubs_error_not_allowed', false);

		$title = mb_substr(trim(strip_tags(isset($_POST['title']) ? (string) $_POST['title'] : '')), 0, 60);
		$start = isset($_POST['start']) ? (string) $_POST['start'] : '';
		$end = isset($_POST['end']) && $_POST['end'] !== '' ? (string) $_POST['end'] : $start;
		$location = mb_substr(trim(strip_tags(isset($_POST['location']) ? (string) $_POST['location'] : '')), 0, 200);
		$description = mb_substr(trim(isset($_POST['description']) ? (string) $_POST['description'] : ''), 0, 10000);

		$valid_date = function ($d)
		{
			$parts = explode('-', $d);
			return preg_match('~^\d{4}-\d{2}-\d{2}$~', $d) && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
		};

		if ($title === '' || !$valid_date($start) || !$valid_date($end) || $end < $start || strtotime($end) - strtotime($start) > 31 * 86400)
		{
			self::notice($txt['clubs_error_event'], 'error');
			self::back($club, 'events');
		}

		// Each event gets a discussion topic in the club's main board.
		require_once($sourcedir . '/Subs-Post.php');

		$subject = $smcFunc['htmlspecialchars']($title, ENT_QUOTES);
		$body = $smcFunc['htmlspecialchars']($description !== '' ? $description : $title, ENT_QUOTES);
		preparsecode($body);

		$msgOptions = array('id' => 0, 'subject' => $subject, 'body' => $body, 'icon' => 'xx', 'smileys_enabled' => true, 'attachments' => array(), 'approved' => true);
		$topicOptions = array('id' => 0, 'board' => $club['id_board'], 'poll' => null, 'lock_mode' => null, 'sticky_mode' => null, 'mark_as_read' => true, 'is_approved' => true);
		$posterOptions = array('id' => $user_info['id'], 'name' => $user_info['name'], 'email' => $user_info['email'], 'update_post_count' => true);
		createPost($msgOptions, $topicOptions, $posterOptions);

		$smcFunc['db_insert']('insert',
			'{db_prefix}calendar',
			array('id_board' => 'int', 'id_topic' => 'int', 'title' => 'string-60', 'id_member' => 'int', 'start_date' => 'date', 'end_date' => 'date', 'location' => 'string-255'),
			array($club['id_board'], (int) $topicOptions['id'], $subject, $user_info['id'], $start, $end, $smcFunc['htmlspecialchars']($location, ENT_QUOTES)),
			array('id_event')
		);

		updateSettings(array('calendar_updated' => time()));

		self::notice($txt['clubs_event_added']);
		self::back($club, 'events');
	}

	public static function rsvp()
	{
		global $smcFunc, $user_info;

		checkSession('get');
		is_not_guest();
		$club = self::requireClub();
		if (!Clubs::isMember($club) && !Clubs::isAdmin())
			fatal_lang_error('clubs_error_not_member', false);

		$id_event = isset($_GET['event']) ? (int) $_GET['event'] : 0;
		$status = isset($_GET['status']) ? (string) $_GET['status'] : '';

		// The event must belong to this club.
		$boards = Clubs::clubBoards($club['id_club']);
		$request = $smcFunc['db_query']('', '
			SELECT id_event
			FROM {db_prefix}calendar
			WHERE id_event = {int:e}
				AND id_board IN ({array_int:boards})',
			array('e' => $id_event, 'boards' => $boards ?: array(0))
		);
		$ok = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		if ($ok)
		{
			if (in_array($status, array('going', 'maybe', 'no'), true))
				$smcFunc['db_insert']('replace',
					'{db_prefix}club_rsvp',
					array('id_event' => 'int', 'id_member' => 'int', 'status' => 'string-6', 'updated' => 'int'),
					array($id_event, $user_info['id'], $status, time()),
					array('id_event', 'id_member')
				);
			else
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_rsvp WHERE id_event = {int:e} AND id_member = {int:m}', array('e' => $id_event, 'm' => $user_info['id']));
		}

		self::back($club, 'events');
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ---------------------------------------------------------------- */

	public static function admin()
	{
		global $context, $txt, $scripturl, $sourcedir, $smcFunc;

		isAllowedTo('admin_forum');
		loadLanguage('Clubs');
		loadTemplate('Clubs');

		$context['page_title'] = $txt['clubs_title'];
		$context['clubs_admin_url'] = $scripturl . '?action=admin;area=clubs';
		$context['clubs_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['clubs_title'],
			'description' => $txt['clubs_admin_desc'],
			'tabs' => array('settings' => array(), 'list' => array()),
		);
		$context['clubs_notice'] = isset($_SESSION['clubs_notice']) ? $_SESSION['clubs_notice'] : null;
		unset($_SESSION['clubs_notice']);

		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'list')
		{
			self::adminList();
			return;
		}

		require_once($sourcedir . '/ManageServer.php');

		$types = array();
		foreach (Clubs::$types as $t)
			$types[$t] = $txt['clubs_type_' . $t];

		$config_vars = array(
			array('check', 'clubs_enabled'),
			array('check', 'clubs_show_menu'),
			array('check', 'clubs_hide_index', 'subtext' => $txt['clubs_hide_index_desc']),
			'',
			array('select', 'clubs_types', $types, 'multiple' => true, 'subtext' => $txt['clubs_types_desc']),
			array('check', 'clubs_approval', 'subtext' => $txt['clubs_approval_desc']),
			array('int', 'clubs_max_owned', 'min' => 0, 'max' => 100, 'subtext' => $txt['clubs_max_owned_desc']),
			array('int', 'clubs_max_boards', 'min' => 0, 'max' => 20, 'subtext' => $txt['clubs_max_boards_desc']),
			'',
			array('check', 'clubs_guests', 'subtext' => $txt['clubs_guests_desc']),
			array('check', 'clubs_gmods_private', 'subtext' => $txt['clubs_gmods_private_desc']),
			array('check', 'clubs_ban_hides', 'subtext' => $txt['clubs_ban_hides_desc']),
			array('check', 'clubs_events', 'subtext' => $txt['clubs_events_desc']),
			array('check', 'clubs_alerts'),
		);

		$context['post_url'] = $context['clubs_admin_url'] . ';save';
		$context['settings_title'] = $txt['clubs_tab_settings'];

		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['clubs_max_owned'] = max(0, min(100, isset($_POST['clubs_max_owned']) ? (int) $_POST['clubs_max_owned'] : 3));
			$_POST['clubs_max_boards'] = max(0, min(20, isset($_POST['clubs_max_boards']) ? (int) $_POST['clubs_max_boards'] : 3));
			if (empty($_POST['clubs_types']))
				$_POST['clubs_types'] = array('open');

			saveDBSettings($config_vars);

			// Guest and moderator access may have changed.
			self::resyncAll();

			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=clubs');
		}

		prepareDBSettingContext($config_vars);
		$context['sub_template'] = 'show_settings';
	}

	public static function resyncAll()
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs WHERE status = {string:active}', array('active' => 'active'));
		$ids = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		foreach ($ids as $id)
			Clubs::syncAccess($id);

		return count($ids);
	}

	protected static function adminList()
	{
		global $context, $smcFunc, $txt, $scripturl;

		if (isset($_GET['approve']) || isset($_GET['remove']) || isset($_GET['resync']))
		{
			checkSession('get');

			if (isset($_GET['approve']) && ($club = Clubs::load((int) $_GET['approve'])) && $club['status'] === 'pending')
			{
				Clubs::activate($club['id_club']);
				Clubs::alert(array($club['id_owner']), 'approved', 0, '', $club);
				$_SESSION['clubs_notice'] = array('info', $txt['clubs_admin_approved']);
			}
			elseif (isset($_GET['remove']) && Clubs::load((int) $_GET['remove']))
			{
				Clubs::delete((int) $_GET['remove']);
				$_SESSION['clubs_notice'] = array('info', $txt['clubs_deleted']);
			}
			elseif (isset($_GET['resync']))
				$_SESSION['clubs_notice'] = array('info', sprintf($txt['clubs_admin_resynced'], self::resyncAll()));

			redirectexit('action=admin;area=clubs;sa=list');
		}

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}clubs', array());
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['clubs_admin_url'] . ';sa=list', $start, (int) $total, 30);

		$context['clubs_admin_list'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT c.id_club, c.name, c.type, c.status, c.num_members, c.created, c.id_owner, COALESCE(mem.real_name, {string:empty}) AS owner_name
			FROM {db_prefix}clubs AS c
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = c.id_owner)
			ORDER BY CASE WHEN c.status = {string:pending} THEN 0 ELSE 1 END, c.created DESC
			LIMIT {int:start}, 30',
			array('start' => $start, 'empty' => '', 'pending' => 'pending')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['clubs_admin_list'][] = $row + array('url' => $scripturl . '?action=clubs;sa=view;club=' . $row['id_club'], 'created_text' => timeformat($row['created']));
		$smcFunc['db_free_result']($request);

		$context['sub_template'] = 'clubs_admin_list';
	}
}
