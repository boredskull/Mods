<?php

/**
 * Activity Stream for SMF 2.1
 *
 * A forum-wide timeline: new topics, replies, likes and new members, with
 * member following, ready-made tabs (All, Following, Popular, My activity)
 * and custom streams members can build and save. Everything respects board
 * access, approval, ignored boards and ignored members.
 *
 * Other mods can add their own item types with these hooks:
 *   integrate_activity_stream_types (&$types)   key => label
 *   integrate_activity_stream_fetch (&$items, $config, $before, $limit)
 *     Append items as: array('type' => key, 'time' => int, 'key' => unique string,
 *     'actor' => id_member, 'html' => safe HTML line, 'snippet' => safe HTML (optional))
 *
 * @package ActivityStream
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.2
 */

if (!defined('SMF'))
	die('No direct access...');

class ActivityStream
{
	const VERSION = '1.1.2';
	const MAX_FOLLOWS = 500;

	public static $core_types = array('topics', 'replies', 'likes', 'members');
	public static $builtins = array('all', 'following', 'popular', 'mine');

	protected static $types = null;

	/* ------------------------------------------------------------------
	 * Settings and access
	 * ---------------------------------------------------------------- */

	public static function enabled()
	{
		global $modSettings;

		return !empty($modSettings['as_enabled']);
	}

	public static function canView()
	{
		return self::enabled() && allowedTo('as_view');
	}

	public static function canCustomise()
	{
		global $user_info;

		return self::enabled() && empty($user_info['is_guest']) && allowedTo('as_follow');
	}

	/**
	 * Item types turned on by the admin, plus types added by other mods.
	 *
	 * @return array key => label
	 */
	public static function types()
	{
		global $modSettings, $txt;

		if (self::$types !== null)
			return self::$types;

		$types = array();
		foreach (self::$core_types as $type)
			if (!isset($modSettings['as_type_' . $type]) || !empty($modSettings['as_type_' . $type]))
				$types[$type] = $txt['as_type_' . $type];

		if (isset($types['likes']) && empty($modSettings['enable_likes']))
			unset($types['likes']);
		if (isset($types['members']) && !allowedTo('view_mlist'))
			unset($types['members']);

		call_integration_hook('integrate_activity_stream_types', array(&$types));

		return self::$types = $types;
	}

	public static function perPage()
	{
		global $modSettings;

		return empty($modSettings['as_per_page']) ? 25 : max(5, min(100, (int) $modSettings['as_per_page']));
	}

	/**
	 * Board IDs from a stored setting, whatever form it was saved in:
	 * a JSON array (what this mod writes), a JSON object, a comma list,
	 * or an array.
	 */
	public static function idList($raw)
	{
		if (is_array($raw))
			$list = $raw;
		elseif (!is_string($raw) || trim($raw) === '')
			$list = array();
		else
		{
			$list = json_decode($raw, true);
			if (!is_array($list))
				$list = preg_split('~\s*,\s*~', trim($raw, " \t\n\r[]{}"));
		}

		$out = array();
		foreach ($list as $v)
			if (is_scalar($v) && (int) $v > 0)
				$out[(int) $v] = (int) $v;

		return array_values($out);
	}

	/**
	 * Boards the admin excluded (not counting the recycle bin).
	 */
	public static function adminExcludedBoards()
	{
		global $modSettings;

		return self::idList(isset($modSettings['as_exclude_boards']) ? $modSettings['as_exclude_boards'] : '');
	}

	public static function excludedBoards()
	{
		global $modSettings;

		$boards = self::adminExcludedBoards();

		if (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']))
			$boards[] = (int) $modSettings['recycle_board'];

		return array_values(array_unique(array_filter($boards)));
	}

	/* ------------------------------------------------------------------
	 * Following
	 * ---------------------------------------------------------------- */

	public static function following($id_member)
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_followed
			FROM {db_prefix}as_follows
			WHERE id_member = {int:me}',
			array('me' => (int) $id_member)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		return $ids;
	}

	public static function followCounts($id_member)
	{
		global $smcFunc;

		$out = array('following' => 0, 'followers' => 0);
		foreach (array('following' => 'id_member', 'followers' => 'id_followed') as $key => $col)
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}as_follows
				WHERE ' . $col . ' = {int:id}',
				array('id' => (int) $id_member)
			);
			list ($out[$key]) = $smcFunc['db_fetch_row']($request);
			$out[$key] = (int) $out[$key];
			$smcFunc['db_free_result']($request);
		}

		return $out;
	}

	public static function isFollowing($me, $them)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT 1
			FROM {db_prefix}as_follows
			WHERE id_member = {int:me}
				AND id_followed = {int:them}',
			array('me' => (int) $me, 'them' => (int) $them)
		);
		$yes = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		return $yes;
	}

	/**
	 * @return string|true true on success, or an error key
	 */
	public static function follow($me, $them)
	{
		global $smcFunc;

		$me = (int) $me;
		$them = (int) $them;

		if ($me <= 0 || $them <= 0 || $me === $them)
			return 'as_error_follow_self';

		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member = {int:id}',
			array('id' => $them)
		);
		$exists = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);
		if (!$exists)
			return 'as_error_no_member';

		if (self::followCounts($me)['following'] >= self::MAX_FOLLOWS)
			return 'as_error_follow_limit';

		$smcFunc['db_insert']('ignore',
			'{db_prefix}as_follows',
			array('id_member' => 'int', 'id_followed' => 'int', 'created' => 'int'),
			array($me, $them, time()),
			array('id_member', 'id_followed')
		);

		return true;
	}

	public static function unfollow($me, $them)
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}as_follows
			WHERE id_member = {int:me}
				AND id_followed = {int:them}',
			array('me' => (int) $me, 'them' => (int) $them)
		);
	}

	/* ------------------------------------------------------------------
	 * Streams
	 * ---------------------------------------------------------------- */

	/**
	 * Fill in and check a stream's filters.
	 */
	public static function normaliseConfig($c)
	{
		$c = is_array($c) ? $c : array();
		$types = array_keys(self::types());

		$wanted = isset($c['types']) && is_array($c['types']) ? array_values(array_intersect($types, $c['types'])) : $types;

		return array(
			'types' => empty($wanted) ? $types : $wanted,
			'members' => isset($c['members']) && in_array($c['members'], array('all', 'followed', 'me', 'user'), true) ? $c['members'] : 'all',
			'user' => isset($c['user']) ? (int) $c['user'] : 0,
			'boards' => isset($c['boards']) && is_array($c['boards']) ? array_values(array_unique(array_filter(array_map('intval', $c['boards'])))) : array(),
			'own' => !isset($c['own']) || !empty($c['own']),
			'days' => isset($c['days']) ? max(0, min(3650, (int) $c['days'])) : 0,
		);
	}

	/**
	 * @param bool $active_only Only the streams within the admin's limit
	 *  (none when custom streams are turned off). Saved streams beyond it
	 *  are kept, just not shown, so raising the limit brings them back.
	 */
	public static function customStreams($id_member, $active_only = true)
	{
		global $smcFunc;

		$max = self::maxStreams();
		if ($active_only && $max === 0)
			return array();

		$streams = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_stream, name, config
			FROM {db_prefix}as_streams
			WHERE id_member = {int:me}
			ORDER BY sort_order, id_stream',
			array('me' => (int) $id_member)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$active = count($streams) < $max;
			if (!$active && $active_only)
				continue;
			$streams['s' . $row['id_stream']] = array(
				'id' => (int) $row['id_stream'],
				'name' => $row['name'],
				'config' => self::normaliseConfig(json_decode($row['config'], true)),
				'active' => $active,
			);
		}
		$smcFunc['db_free_result']($request);

		return $streams;
	}

	public static function defaultTab($id_member)
	{
		global $smcFunc;

		if ($id_member <= 0)
			return 'all';

		$request = $smcFunc['db_query']('', '
			SELECT default_tab
			FROM {db_prefix}as_prefs
			WHERE id_member = {int:me}',
			array('me' => (int) $id_member)
		);
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return empty($row) ? 'all' : (string) $row[0];
	}

	/**
	 * All tabs the current member can see.
	 */
	public static function tabs()
	{
		global $txt, $user_info;

		$tabs = array(
			'all' => array('label' => $txt['as_tab_all'], 'config' => self::normaliseConfig(array())),
		);

		if (self::canCustomise())
			$tabs['following'] = array('label' => $txt['as_tab_following'], 'config' => self::normaliseConfig(array('members' => 'followed')));

		$tabs['popular'] = array('label' => $txt['as_tab_popular'], 'config' => null);

		if (empty($user_info['is_guest']))
			$tabs['mine'] = array('label' => $txt['as_tab_mine'], 'config' => self::normaliseConfig(array('members' => 'me')));

		if (self::canCustomise())
			foreach (self::customStreams($user_info['id']) as $key => $s)
				$tabs[$key] = array('label' => $s['name'], 'config' => $s['config'], 'custom' => true);

		return $tabs;
	}

	/* ------------------------------------------------------------------
	 * Fetching
	 * ---------------------------------------------------------------- */

	/* ------------------------------------------------------------------
	 * Paging
	 *
	 * Items are ordered by time, then by source (posts, likes, new
	 * members, other mods), then by ID, all newest first. The cursor is the
	 * last item's position in that order, so items sharing a timestamp are
	 * never skipped or repeated.
	 * ---------------------------------------------------------------- */

	const RANK_MESSAGE = 3;
	const RANK_LIKE = 2;
	const RANK_MEMBER = 1;
	const RANK_OTHER = 0;

	/**
	 * @return array array(time, rank, a, b)
	 */
	public static function position(array $item)
	{
		switch ($item['type'])
		{
			case 'topics':
			case 'replies':
				return array((int) $item['time'], self::RANK_MESSAGE, (int) $item['id_msg'], 0);
			case 'likes':
				return array((int) $item['time'], self::RANK_LIKE, (int) $item['id_msg'], (int) $item['actor']);
			case 'members':
				return array((int) $item['time'], self::RANK_MEMBER, (int) $item['actor'], 0);
			default:
				return array((int) $item['time'], self::RANK_OTHER, (int) sprintf('%u', crc32((string) $item['key'])), 0);
		}
	}

	/**
	 * Newest first: negative when $a comes before $b.
	 */
	public static function comparePositions(array $a, array $b)
	{
		for ($i = 0; $i < 4; $i++)
			if ($a[$i] !== $b[$i])
				return $a[$i] > $b[$i] ? -1 : 1;

		return 0;
	}

	public static function encodeCursor(array $pos)
	{
		return implode('.', array_map('intval', $pos));
	}

	/**
	 * Read a cursor from the URL. Old "before=timestamp" links still work.
	 *
	 * @return array|null
	 */
	public static function cursorFromRequest()
	{
		if (isset($_GET['cursor']) && preg_match('~^(\d{1,10})\.(\d)\.(\d{1,10})\.(\d{1,10})$~', (string) $_GET['cursor'], $m))
			return array((int) $m[1], min(9, (int) $m[2]), (int) $m[3], (int) $m[4]);

		// Everything strictly older than the timestamp, as in 1.0.
		if (!empty($_GET['before']) && (int) $_GET['before'] > 0)
			return array((int) $_GET['before'], 9, 0, 0);

		return null;
	}

	/**
	 * SQL condition: items after the cursor for one source.
	 */
	protected static function cursorWhere($cursor, $rank, $time_col, $a_col, $b_col, array &$params)
	{
		if ($cursor === null)
			return '1=1';

		$params['cur_t'] = $cursor[0];
		if ($rank < $cursor[1])
			return $time_col . ' <= {int:cur_t}';
		if ($rank > $cursor[1])
			return $time_col . ' < {int:cur_t}';

		$params['cur_a'] = $cursor[2];
		$same_a = $a_col . ' < {int:cur_a}';
		if ($b_col !== null)
		{
			$params['cur_b'] = $cursor[3];
			$same_a = '(' . $a_col . ' < {int:cur_a} OR (' . $a_col . ' = {int:cur_a} AND ' . $b_col . ' < {int:cur_b}))';
		}

		return '(' . $time_col . ' < {int:cur_t} OR (' . $time_col . ' = {int:cur_t} AND ' . $same_a . '))';
	}

	/**
	 * Shared WHERE parts: board access, time window, boards, members, ignores.
	 *
	 * @return array|false Conditions, or false when the stream can't have results.
	 */
	protected static function baseWhere(array $c, $member_col, $time_col, array &$params)
	{
		global $user_info;

		// Ignored boards are skipped unless the member picked boards themselves.
		$where = array(empty($c['boards']) && $c['members'] !== 'user' ? '{query_wanna_see_board}' : '{query_see_board}');

		if ($c['days'] > 0)
		{
			$where[] = $time_col . ' >= {int:since}';
			$params['since'] = time() - $c['days'] * 86400;
		}

		$excluded = self::excludedBoards();
		if (!empty($excluded))
		{
			$where[] = 'b.id_board NOT IN ({array_int:excluded})';
			$params['excluded'] = $excluded;
		}

		if (!empty($c['boards']))
		{
			$where[] = 'b.id_board IN ({array_int:boards})';
			$params['boards'] = $c['boards'];
		}

		$me = empty($user_info['id']) ? 0 : (int) $user_info['id'];
		$params['me'] = $me;

		if ($c['members'] === 'followed')
		{
			$ids = $me ? self::following($me) : array();
			if (empty($ids))
				return false;
			$where[] = $member_col . ' IN ({array_int:followed})';
			$params['followed'] = $ids;
		}
		elseif ($c['members'] === 'me')
		{
			if (!$me)
				return false;
			$where[] = $member_col . ' = {int:me}';
		}
		elseif ($c['members'] === 'user')
		{
			$where[] = $member_col . ' = {int:user}';
			$params['user'] = (int) $c['user'];
		}
		elseif (!$c['own'] && $me)
			$where[] = $member_col . ' != {int:me}';

		if (!empty($user_info['ignoreusers']) && $c['members'] !== 'user')
		{
			$where[] = $member_col . ' NOT IN ({array_int:ignored})';
			$params['ignored'] = array_map('intval', (array) $user_info['ignoreusers']);
		}

		return $where;
	}

	/**
	 * Fetch one page of a stream, newest first.
	 *
	 * @param array $c Normalised stream config
	 * @param array|int|null $cursor Position of the last item already shown
	 *  (from cursorFromRequest()), or a timestamp as in 1.0, or null/0 for the start
	 * @return array array('items' => [...], 'next' => cursor string or '')
	 */
	public static function fetch(array $c, $cursor, $limit)
	{
		if (is_string($cursor) && preg_match('~^(\d{1,10})\.(\d)\.(\d{1,10})\.(\d{1,10})$~', $cursor, $m))
			$cursor = array((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]);

		if (is_int($cursor) || (is_string($cursor) && ctype_digit($cursor)))
			$cursor = (int) $cursor > 0 ? array((int) $cursor, 9, 0, 0) : null;
		elseif (!is_array($cursor) || count($cursor) !== 4)
			$cursor = null;
		else
			$cursor = array_map('intval', array_values($cursor));

		$fetch = $limit + 1;
		$items = array();
		foreach (array('fetchMessages', 'fetchLikes', 'fetchMembers') as $method)
			$items = array_merge($items, self::$method($c, $cursor, $fetch));

		// Other mods' items (the hook still gets a timestamp, as in 1.0).
		$extra = array();
		$before = $cursor === null ? time() + 60 : $cursor[0] + 1;
		call_integration_hook('integrate_activity_stream_fetch', array(&$extra, $c, $before, $fetch));
		foreach ($extra as $item)
			if (is_array($item) && isset($item['type'], $item['time'], $item['key']) && in_array($item['type'], $c['types'], true))
			{
				$item += array('actor' => 0, 'html' => '', 'snippet' => '');
				if ($cursor === null || self::comparePositions(self::position($item), $cursor) > 0)
					$items[] = $item;
			}

		usort($items, function ($a, $b)
		{
			return ActivityStream::comparePositions(ActivityStream::position($a), ActivityStream::position($b));
		});

		// Cut the page before grouping likes, so the cursor is a real item.
		$more = count($items) > $limit;
		$items = array_slice($items, 0, $limit);
		$last = end($items);
		$next = $more && $last ? self::encodeCursor(self::position($last)) : '';

		return array(
			'items' => self::groupLikes($items),
			'next' => $next,
		);
	}

	protected static function fetchMessages(array $c, $cursor, $limit)
	{
		global $smcFunc;

		$topics = in_array('topics', $c['types'], true);
		$replies = in_array('replies', $c['types'], true);
		if (!$topics && !$replies)
			return array();

		$params = array('limit' => $limit, 'yes' => 1);
		$where = self::baseWhere($c, 'm.id_member', 'm.poster_time', $params);
		if ($where === false)
			return array();
		$where[] = self::cursorWhere($cursor, self::RANK_MESSAGE, 'm.poster_time', 'm.id_msg', null, $params);

		$where[] = 'm.approved = {int:yes}';
		$where[] = 't.approved = {int:yes}';
		if ($topics && !$replies)
			$where[] = 'm.id_msg = t.id_first_msg';
		elseif ($replies && !$topics)
			$where[] = 'm.id_msg != t.id_first_msg';

		$items = array();
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_topic, m.id_member, m.poster_time, m.subject, m.body, m.smileys_enabled, m.likes,
				COALESCE(mem.real_name, m.poster_name) AS poster_name, t.id_first_msg, t.num_replies,
				b.id_board, b.name AS board_name, fm.subject AS topic_subject
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' . implode('
				AND ', $where) . '
			ORDER BY m.poster_time DESC, m.id_msg DESC
			LIMIT {int:limit}',
			$params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$first = (int) $row['id_msg'] === (int) $row['id_first_msg'];
			$items[] = array(
				'type' => $first ? 'topics' : 'replies',
				'key' => 'm' . $row['id_msg'],
				'time' => (int) $row['poster_time'],
				'actor' => (int) $row['id_member'],
				'actor_name' => $row['poster_name'],
				'id_msg' => (int) $row['id_msg'],
				'id_topic' => (int) $row['id_topic'],
				'id_board' => (int) $row['id_board'],
				'board_name' => $row['board_name'],
				'subject' => $row['topic_subject'],
				'body' => $row['body'],
				'smileys' => !empty($row['smileys_enabled']),
				'likes' => (int) $row['likes'],
				'replies' => (int) $row['num_replies'],
			);
		}
		$smcFunc['db_free_result']($request);

		return $items;
	}

	protected static function fetchLikes(array $c, $cursor, $limit)
	{
		global $smcFunc, $user_info;

		if (!in_array('likes', $c['types'], true))
			return array();

		$params = array('limit' => $limit, 'yes' => 1, 'msg' => 'msg');
		$where = self::baseWhere($c, 'ul.id_member', 'ul.like_time', $params);
		if ($where === false)
			return array();
		$where[] = self::cursorWhere($cursor, self::RANK_LIKE, 'ul.like_time', 'ul.content_id', 'ul.id_member', $params);

		$where[] = 'ul.content_type = {string:msg}';
		$where[] = 'm.approved = {int:yes}';
		$where[] = 't.approved = {int:yes}';
		if (!empty($user_info['ignoreusers']))
		{
			// Set here too: baseWhere() leaves it out on a member's profile page.
			$where[] = 'm.id_member NOT IN ({array_int:ignored})';
			$params['ignored'] = array_map('intval', (array) $user_info['ignoreusers']);
		}

		$items = array();
		$request = $smcFunc['db_query']('', '
			SELECT ul.id_member AS liker, ul.like_time, m.id_msg, m.id_topic, m.id_member AS author,
				COALESCE(lm.real_name, {string:empty}) AS liker_name, COALESCE(am.real_name, m.poster_name) AS author_name,
				b.id_board, b.name AS board_name, fm.subject AS topic_subject
			FROM {db_prefix}user_likes AS ul
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = ul.content_id)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS lm ON (lm.id_member = ul.id_member)
				LEFT JOIN {db_prefix}members AS am ON (am.id_member = m.id_member)
			WHERE ' . implode('
				AND ', $where) . '
			ORDER BY ul.like_time DESC, ul.content_id DESC, ul.id_member DESC
			LIMIT {int:limit}',
			$params + array('empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$items[] = array(
				'type' => 'likes',
				'key' => 'l' . $row['id_msg'] . '_' . $row['liker'],
				'time' => (int) $row['like_time'],
				'actor' => (int) $row['liker'],
				'actor_name' => $row['liker_name'],
				'likers' => array((int) $row['liker'] => $row['liker_name']),
				'id_msg' => (int) $row['id_msg'],
				'id_topic' => (int) $row['id_topic'],
				'id_board' => (int) $row['id_board'],
				'board_name' => $row['board_name'],
				'subject' => $row['topic_subject'],
				'author' => (int) $row['author'],
				'author_name' => $row['author_name'],
			);
		$smcFunc['db_free_result']($request);

		return $items;
	}

	protected static function fetchMembers(array $c, $cursor, $limit)
	{
		global $smcFunc;

		// New members belong to the whole forum, not to boards or followed members.
		if (!in_array('members', $c['types'], true) || $c['members'] !== 'all' || !empty($c['boards']))
			return array();

		global $user_info;

		$params = array('limit' => $limit, 'active' => 1, 'me' => (int) $user_info['id']);
		$where = array('is_activated = {int:active}');
		$where[] = self::cursorWhere($cursor, self::RANK_MEMBER, 'date_registered', 'id_member', null, $params);
		if (!$c['own'] && !empty($user_info['id']))
			$where[] = 'id_member != {int:me}';
		if (!empty($user_info['ignoreusers']))
		{
			$where[] = 'id_member NOT IN ({array_int:ignored})';
			$params['ignored'] = array_map('intval', (array) $user_info['ignoreusers']);
		}
		if ($c['days'] > 0)
		{
			$where[] = 'date_registered >= {int:since}';
			$params['since'] = time() - $c['days'] * 86400;
		}

		$items = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, date_registered
			FROM {db_prefix}members
			WHERE ' . implode(' AND ', $where) . '
			ORDER BY date_registered DESC, id_member DESC
			LIMIT {int:limit}',
			$params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$items[] = array(
				'type' => 'members',
				'key' => 'n' . $row['id_member'],
				'time' => (int) $row['date_registered'],
				'actor' => (int) $row['id_member'],
				'actor_name' => $row['real_name'],
			);
		$smcFunc['db_free_result']($request);

		return $items;
	}

	/**
	 * Merge likes of the same post on one page: "A, B and 3 others liked…".
	 */
	public static function groupLikes(array $items)
	{
		$out = array();
		$index = array();

		foreach ($items as $item)
		{
			if ($item['type'] !== 'likes')
			{
				$out[] = $item;
				continue;
			}

			$key = $item['id_msg'];
			if (isset($index[$key]))
			{
				$out[$index[$key]]['likers'] += $item['likers'];
				continue;
			}

			$index[$key] = count($out);
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Topics with the most replies and likes in the last few days.
	 */
	public static function popular($days, $limit = 20)
	{
		global $smcFunc, $user_info;

		$days = max(1, min(90, (int) $days));
		$count_likes = isset(self::types()['likes']);
		$cache_key = 'as_popular_' . md5($user_info['query_wanna_see_board'] . '|' . $days . '|' . implode(',', self::excludedBoards()) . '|' . (int) $count_likes);
		if (($cached = cache_get_data($cache_key, 600)) !== null)
			return $cached;

		$since = time() - $days * 86400;
		$excluded = self::excludedBoards();
		$exclude_sql = empty($excluded) ? '' : ' AND b.id_board NOT IN ({array_int:excluded})';
		$params = array('since' => $since, 'yes' => 1, 'msg' => 'msg', 'excluded' => $excluded);

		$scores = array();

		$request = $smcFunc['db_query']('', '
			SELECT m.id_topic, COUNT(*) AS n
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE {query_wanna_see_board}
				AND m.approved = {int:yes}
				AND m.poster_time >= {int:since}' . $exclude_sql . '
			GROUP BY m.id_topic
			ORDER BY n DESC
			LIMIT 100',
			$params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$scores[(int) $row['id_topic']] = array('posts' => (int) $row['n'], 'likes' => 0);
		$smcFunc['db_free_result']($request);

		// Likes only count when they're turned on (in SMF and in this mod).
		$request = !$count_likes ? false : $smcFunc['db_query']('', '
			SELECT m.id_topic, COUNT(*) AS n
			FROM {db_prefix}user_likes AS ul
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = ul.content_id)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE ul.content_type = {string:msg}
				AND ul.like_time >= {int:since}
				AND m.approved = {int:yes}
				AND {query_wanna_see_board}' . $exclude_sql . '
			GROUP BY m.id_topic
			ORDER BY n DESC
			LIMIT 100',
			$params
		);
		while ($request !== false && $row = $smcFunc['db_fetch_assoc']($request))
		{
			$id = (int) $row['id_topic'];
			if (!isset($scores[$id]))
				$scores[$id] = array('posts' => 0, 'likes' => 0);
			$scores[$id]['likes'] = (int) $row['n'];
		}
		if ($request !== false)
			$smcFunc['db_free_result']($request);

		foreach ($scores as $id => $s)
			$scores[$id]['score'] = $s['posts'] * 2 + $s['likes'];

		uasort($scores, function ($a, $b)
		{
			return $b['score'] - $a['score'];
		});
		$scores = array_slice($scores, 0, $limit, true);

		$topics = array();
		if (!empty($scores))
		{
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.num_replies, t.num_views, fm.subject, fm.id_member, COALESCE(fmem.real_name, fm.poster_name) AS starter,
					lm.poster_time AS last_time, COALESCE(lmem.real_name, lm.poster_name) AS last_poster, b.id_board, b.name AS board_name
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
					INNER JOIN {db_prefix}messages AS lm ON (lm.id_msg = t.id_last_msg)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}members AS fmem ON (fmem.id_member = fm.id_member)
					LEFT JOIN {db_prefix}members AS lmem ON (lmem.id_member = lm.id_member)
				WHERE t.id_topic IN ({array_int:topics})
					AND t.approved = {int:yes}
					AND {query_see_board}',
				array('topics' => array_keys($scores), 'yes' => 1)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Apply word censoring, as SMF does everywhere else.
				censorText($row['subject']);
				$topics[(int) $row['id_topic']] = $row + $scores[(int) $row['id_topic']];
			}
			$smcFunc['db_free_result']($request);

			// Keep the score order.
			$ordered = array();
			foreach (array_keys($scores) as $id)
				if (isset($topics[$id]))
					$ordered[$id] = $topics[$id];
			$topics = $ordered;
		}

		cache_put_data($cache_key, $topics, 600);

		return $topics;
	}

	/* ------------------------------------------------------------------
	 * Preparing items for display
	 * ---------------------------------------------------------------- */

	public static function snippet($body, $smileys, $id_msg, $length = 240)
	{
		for ($i = 0; $i < 10; $i++)
		{
			$new = preg_replace('~\[quote(?:[\s=][^\]]*)?\](?:(?!\[quote[\s=\]]).)*?\[/quote\]~is', ' ', $body);
			if ($new === null || $new === $body)
				break;
			$body = $new;
		}

		censorText($body);
		$html = parse_bbc($body, $smileys, $id_msg);
		$text = trim(preg_replace('~\s+~u', ' ', strip_tags(str_replace(array('<br>', '<br />', '</p>', '</div>', '</li>'), ' ', $html))));

		return shorten_subject($text, $length);
	}

	public static function relativeTime($time)
	{
		global $txt;

		$diff = time() - $time;
		if ($diff < 60)
			return $txt['as_just_now'];
		if ($diff < 3600)
			return sprintf($diff < 120 ? $txt['as_minute_ago'] : $txt['as_minutes_ago'], floor($diff / 60));
		if ($diff < 86400)
			return sprintf($diff < 7200 ? $txt['as_hour_ago'] : $txt['as_hours_ago'], floor($diff / 3600));

		return timeformat($time);
	}

	public static function dayLabel($time)
	{
		global $txt, $user_info;

		try
		{
			$tz = new DateTimeZone(!empty($user_info['timezone']) ? $user_info['timezone'] : date_default_timezone_get());
		}
		catch (\Exception $e)
		{
			$tz = new DateTimeZone('UTC');
		}

		$day = (new DateTime('@' . (int) $time))->setTimezone($tz);
		$today = (new DateTime('now', $tz))->format('Y-m-d');
		$yesterday = (new DateTime('yesterday', $tz))->format('Y-m-d');
		$key = $day->format('Y-m-d');

		if ($key === $today)
			return array($key, $txt['as_today']);
		if ($key === $yesterday)
			return array($key, $txt['as_yesterday']);

		$months = isset($txt['months']) && is_array($txt['months']) ? $txt['months'] : null;
		$days = isset($txt['days']) && is_array($txt['days']) ? $txt['days'] : null;
		$label = ($days ? $days[(int) $day->format('w')] : $day->format('l')) . ' ' . $day->format('j') . ' ' . ($months ? $months[(int) $day->format('n')] : $day->format('F')) . ' ' . $day->format('Y');

		return array($key, $label);
	}

	/**
	 * Add avatars, links, sentences and snippets to fetched items.
	 */
	public static function prepare(array $items)
	{
		global $scripturl, $memberContext, $txt, $smcFunc;

		$ids = array();
		foreach ($items as $item)
		{
			if (!empty($item['actor']))
				$ids[] = (int) $item['actor'];
			if (!empty($item['likers']))
				$ids = array_merge($ids, array_slice(array_keys($item['likers']), 0, 3));
		}
		$ids = array_values(array_unique(array_filter($ids)));

		$loaded = !empty($ids) ? loadMemberData($ids) : array();
		$people = array();
		foreach ((array) $loaded as $id)
			if (loadMemberContext($id))
				$people[$id] = array(
					'name' => $memberContext[$id]['name'],
					'link' => $memberContext[$id]['link'],
					'avatar' => !empty($memberContext[$id]['avatar']['href']) ? $memberContext[$id]['avatar']['href'] : '',
				);

		$person = function ($id, $fallback) use ($people, $smcFunc)
		{
			return isset($people[$id]) ? $people[$id] : array('name' => $fallback, 'link' => $fallback !== '' ? $fallback : $GLOBALS['txt']['guest_title'], 'avatar' => '');
		};

		$out = array();
		foreach ($items as $item)
		{
			if (isset($item['subject']))
				censorText($item['subject']);

			$actor = $person($item['actor'], isset($item['actor_name']) ? $item['actor_name'] : '');
			$topic_link = isset($item['id_msg']) ? '<a href="' . $scripturl . '?topic=' . $item['id_topic'] . '.msg' . $item['id_msg'] . '#msg' . $item['id_msg'] . '">' . $item['subject'] . '</a>' : '';
			$board_link = isset($item['id_board']) ? '<a href="' . $scripturl . '?board=' . $item['id_board'] . '.0">' . $item['board_name'] . '</a>' : '';

			$view = array(
				'type' => $item['type'],
				'key' => $item['key'],
				'time' => $item['time'],
				'when' => self::relativeTime($item['time']),
				'timestamp' => timeformat($item['time']),
				'avatar' => $actor['avatar'],
				'actor' => $actor['link'],
				'line' => '',
				'snippet' => '',
				'meta' => array(),
				'url' => '',
			);

			switch ($item['type'])
			{
				case 'topics':
				case 'replies':
					$view['line'] = sprintf($txt[$item['type'] === 'topics' ? 'as_line_topic' : 'as_line_reply'], $actor['link'], $topic_link, $board_link);
					$view['snippet'] = self::snippet($item['body'], $item['smileys'], $item['id_msg']);
					$view['url'] = $scripturl . '?topic=' . $item['id_topic'] . '.msg' . $item['id_msg'] . '#msg' . $item['id_msg'];
					$view['meta'][] = sprintf($txt['as_n_replies'], comma_format($item['replies']));
					if ($item['likes'] > 0)
						$view['meta'][] = sprintf($txt['as_n_likes'], comma_format($item['likes']));
					break;

				case 'likes':
					$names = array();
					foreach (array_slice($item['likers'], 0, 2, true) as $id => $name)
						$names[] = $person($id, $name)['link'];
					$others = count($item['likers']) - count($names);

					if ($others === 1)
						$who = sprintf($txt['as_likers_other_one'], implode(', ', $names));
					elseif ($others > 1)
						$who = sprintf($txt['as_likers_others'], implode(', ', $names), comma_format($others));
					elseif (count($names) === 2)
						$who = sprintf($txt['as_likers_two'], $names[0], $names[1]);
					else
						$who = $names[0];

					$author = $item['author'] > 0 ? '<a href="' . $scripturl . '?action=profile;u=' . $item['author'] . '">' . $item['author_name'] . '</a>' : $item['author_name'];
					$view['line'] = sprintf($txt['as_line_like'], $who, $author, $topic_link, $board_link);
					$view['url'] = $scripturl . '?topic=' . $item['id_topic'] . '.msg' . $item['id_msg'] . '#msg' . $item['id_msg'];
					break;

				case 'members':
					$view['line'] = sprintf($txt['as_line_member'], $actor['link']);
					$view['url'] = $scripturl . '?action=profile;u=' . $item['actor'];
					break;

				default:
					// Added by another mod (already safe HTML).
					$view['line'] = $item['html'];
					$view['snippet'] = isset($item['snippet']) ? $item['snippet'] : '';
					$view['url'] = isset($item['url']) ? $item['url'] : '';
			}

			list ($view['day'], $view['day_label']) = self::dayLabel($item['time']);
			$out[] = $view;
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Pages
	 * ---------------------------------------------------------------- */

	public static function action()
	{
		global $context, $txt, $scripturl;

		if (!self::enabled())
			redirectexit();

		// Load our permission-denied text before SMF checks guest access.
		loadLanguage('ActivityStream');
		isAllowedTo('as_view');
		loadTemplate('ActivityStream');
		loadCSSFile('activitystream.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_activitystream');
		loadJavaScriptFile('activitystream.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_activitystream');

		$context['as_url'] = $scripturl . '?action=activity';
		$context['as_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['as_title'];
		$context['linktree'][] = array('url' => $context['as_url'], 'name' => $txt['as_title']);

		$context['as_notice'] = isset($_SESSION['as_notice']) ? $_SESSION['as_notice'] : null;
		unset($_SESSION['as_notice']);

		$sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'view';
		$pages = array(
			'view' => 'view',
			'follow' => 'followAction',
			'following' => 'followingPage',
			'streams' => 'streamsPage',
			'editstream' => 'editStream',
		);

		$method = isset($pages[$sa]) ? $pages[$sa] : 'view';
		self::$method();
	}

	protected static function view()
	{
		global $context, $user_info, $modSettings, $txt;

		$tabs = self::tabs();
		$default = self::defaultTab($user_info['id']);
		$key = isset($_GET['stream']) ? (string) $_GET['stream'] : $default;
		if (!isset($tabs[$key]))
			$key = isset($tabs[$default]) ? $default : 'all';

		// Make this tab the default
		if (isset($_GET['setdefault']) && self::canCustomise())
		{
			checkSession('get');
			self::saveDefault($user_info['id'], $key);
			$_SESSION['as_notice'] = array('info', sprintf($txt['as_default_set'], $tabs[$key]['label']));
			redirectexit('action=activity;stream=' . $key);
		}

		$context['as_tabs'] = $tabs;
		$context['as_current'] = $key;
		$context['as_default'] = $default;
		$context['as_can_customise'] = self::canCustomise();

		if ($key === 'popular')
		{
			$days = empty($modSettings['as_popular_days']) ? 7 : (int) $modSettings['as_popular_days'];
			$context['as_popular'] = self::popular($days);
			$context['as_popular_days'] = $days;
			$context['sub_template'] = 'as_main';
			return;
		}

		$config = $tabs[$key]['config'];
		$page = self::fetch($config, self::cursorFromRequest(), self::perPage());

		$context['as_items'] = self::prepare($page['items']);
		$context['as_next'] = $page['next'] !== '' ? $context['as_url'] . ';stream=' . $key . ';cursor=' . $page['next'] : '';
		$context['as_config'] = $config;
		$context['as_empty_following'] = $key === 'following' && empty(self::following($user_info['id']));
		$context['as_summary'] = !empty($tabs[$key]['custom']) ? self::describe($config) : '';

		// "Load more" asks for just the items.
		if (!empty($_GET['fragment']))
		{
			$context['template_layers'] = array();
			$context['sub_template'] = 'as_fragment';
			return;
		}

		$context['sub_template'] = 'as_main';
	}

	/**
	 * Short text describing a custom stream's filters.
	 */
	public static function describe(array $c)
	{
		global $txt, $smcFunc;

		$parts = array();
		$types = self::types();
		$labels = array();
		foreach ($c['types'] as $t)
			if (isset($types[$t]))
				$labels[] = $types[$t];
		$parts[] = count($labels) === count($types) ? $txt['as_all_types'] : implode(', ', $labels);

		$parts[] = $txt['as_members_' . $c['members']];

		if (!empty($c['boards']))
			$parts[] = sprintf($txt['as_n_boards'], count($c['boards']));
		if ($c['days'] > 0)
			$parts[] = sprintf($txt['as_last_n_days'], $c['days']);

		return implode(' · ', $parts);
	}

	protected static function saveDefault($id_member, $key)
	{
		global $smcFunc;

		$smcFunc['db_insert']('replace',
			'{db_prefix}as_prefs',
			array('id_member' => 'int', 'default_tab' => 'string-20'),
			array((int) $id_member, substr($key, 0, 20)),
			array('id_member')
		);
	}

	protected static function followAction()
	{
		global $user_info, $txt;

		checkSession('get');

		if (!self::canCustomise())
			fatal_lang_error('as_error_no_permission', false);

		$them = isset($_GET['u']) ? (int) $_GET['u'] : 0;
		$unfollow = !empty($_GET['unfollow']);

		if ($unfollow)
			self::unfollow($user_info['id'], $them);
		else
		{
			$result = self::follow($user_info['id'], $them);
			if ($result !== true)
			{
				if (!empty($_GET['js']))
					self::json(array('ok' => false, 'error' => sprintf($txt[$result], self::MAX_FOLLOWS)));
				fatal_lang_error($result, false, array(self::MAX_FOLLOWS));
			}
		}

		if (!empty($_GET['js']))
			self::json(array('ok' => true, 'following' => !$unfollow, 'followers' => self::followCounts($them)['followers']));

		$back = isset($_GET['back']) && $_GET['back'] === 'following' ? 'action=activity;sa=following' : 'action=profile;area=activity;u=' . $them;
		redirectexit($back);
	}

	protected static function json(array $data)
	{
		while (ob_get_level() > 0)
			@ob_end_clean();

		header('Content-Type: application/json; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data);
		die;
	}

	protected static function followingPage()
	{
		global $context, $smcFunc, $user_info, $txt, $memberContext;

		if (!self::canCustomise())
			fatal_lang_error('as_error_no_permission', false);

		if (isset($_POST['add']))
		{
			checkSession();

			$name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (LOWER(real_name) = LOWER({string:name}) OR LOWER(member_name) = LOWER({string:name}))
					AND is_activated = {int:active}
				LIMIT 1',
				array('name' => $smcFunc['htmlspecialchars']($name, ENT_QUOTES), 'active' => 1)
			);
			$row = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if (empty($row))
				$_SESSION['as_notice'] = array('error', $txt['as_error_no_member']);
			else
			{
				$result = self::follow($user_info['id'], (int) $row[0]);
				$_SESSION['as_notice'] = $result === true ? array('info', $txt['as_followed']) : array('error', sprintf($txt[$result], self::MAX_FOLLOWS));
			}

			redirectexit('action=activity;sa=following');
		}

		$lists = array('following' => self::following($user_info['id']), 'followers' => array());
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}as_follows
			WHERE id_followed = {int:me}
			ORDER BY created DESC
			LIMIT 200',
			array('me' => $user_info['id'])
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$lists['followers'][] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$all = array_values(array_unique(array_merge($lists['following'], $lists['followers'])));
		$loaded = !empty($all) ? (array) loadMemberData($all) : array();
		$people = array();
		foreach ($loaded as $id)
			if (loadMemberContext($id))
				$people[$id] = array(
					'id' => $id,
					'name' => $memberContext[$id]['name'],
					'link' => $memberContext[$id]['link'],
					'avatar' => !empty($memberContext[$id]['avatar']['href']) ? $memberContext[$id]['avatar']['href'] : '',
				);

		$context['as_people'] = array();
		foreach ($lists as $list => $ids)
		{
			$context['as_people'][$list] = array();
			foreach ($ids as $id)
				if (isset($people[$id]))
					$context['as_people'][$list][] = $people[$id] + array('i_follow' => in_array($id, $lists['following'], true));
		}

		$context['as_tabs'] = self::tabs();
		$context['as_current'] = '';
		$context['page_title'] = $txt['as_following_title'];
		$context['sub_template'] = 'as_following';
	}

	protected static function streamsPage()
	{
		global $context, $smcFunc, $user_info, $txt;

		if (!self::canCustomise())
			fatal_lang_error('as_error_no_permission', false);

		if (isset($_GET['delete']))
		{
			checkSession('get');
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}as_streams
				WHERE id_stream = {int:id}
					AND id_member = {int:me}',
				array('id' => (int) $_GET['delete'], 'me' => $user_info['id'])
			);
			if (self::defaultTab($user_info['id']) === 's' . (int) $_GET['delete'])
				self::saveDefault($user_info['id'], 'all');
			$_SESSION['as_notice'] = array('info', $txt['as_stream_deleted']);
			redirectexit('action=activity;sa=streams');
		}

		$context['as_streams'] = array();
		foreach (self::customStreams($user_info['id'], false) as $key => $s)
			$context['as_streams'][$key] = $s + array('summary' => self::describe($s['config']));

		$context['as_tabs'] = self::tabs();
		$context['as_current'] = '';
		$context['as_default'] = self::defaultTab($user_info['id']);
		$context['as_max_streams'] = self::maxStreams();
		$context['page_title'] = $txt['as_streams_title'];
		$context['sub_template'] = 'as_streams';
	}

	public static function maxStreams()
	{
		global $modSettings;

		return isset($modSettings['as_max_streams']) ? max(0, min(50, (int) $modSettings['as_max_streams'])) : 10;
	}

	protected static function editStream()
	{
		global $context, $smcFunc, $user_info, $txt;

		if (!self::canCustomise())
			fatal_lang_error('as_error_no_permission', false);

		if (self::maxStreams() === 0)
			fatal_lang_error('as_error_streams_off', false);

		$all = self::customStreams($user_info['id'], false);
		$streams = self::customStreams($user_info['id']);
		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
		if ($id && !isset($all['s' . $id]))
			fatal_lang_error('as_error_no_stream', false);
		if ($id && !isset($streams['s' . $id]))
			fatal_lang_error('as_error_stream_hidden', false, array(self::maxStreams()));
		if (!$id && count($all) >= self::maxStreams())
			fatal_lang_error('as_error_stream_limit', false, array(self::maxStreams()));

		$stream = $id ? $streams['s' . $id] : array('id' => 0, 'name' => '', 'config' => self::normaliseConfig(array()));
		$context['as_errors'] = array();

		if (isset($_POST['save']))
		{
			checkSession();

			$name = $smcFunc['substr'](trim(strip_tags(isset($_POST['name']) ? (string) $_POST['name'] : '')), 0, 40);
			$config = self::normaliseConfig(array(
				'types' => isset($_POST['types']) ? (array) $_POST['types'] : array(),
				'members' => isset($_POST['members']) ? (string) $_POST['members'] : 'all',
				'boards' => !empty($_POST['all_boards']) ? array() : (isset($_POST['boards']) ? (array) $_POST['boards'] : array()),
				'own' => !empty($_POST['own']),
				'days' => isset($_POST['days']) ? (int) $_POST['days'] : 0,
			));

			// Only boards the member can see.
			$visible = array_keys(self::visibleBoards());
			$config['boards'] = array_values(array_intersect($config['boards'], $visible));
			if ($config['members'] === 'user')
				$config['members'] = 'all';

			if ($name === '')
				$context['as_errors'][] = $txt['as_error_stream_name'];
			if (empty($_POST['types']))
				$context['as_errors'][] = $txt['as_error_stream_types'];
			if (empty($_POST['all_boards']) && empty($config['boards']))
				$context['as_errors'][] = $txt['as_error_stream_boards'];

			if (empty($context['as_errors']))
			{
				$value = $smcFunc['htmlspecialchars']($name, ENT_QUOTES);

				if ($id)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}as_streams
						SET name = {string:name}, config = {string:config}
						WHERE id_stream = {int:id}
							AND id_member = {int:me}',
						array('name' => $value, 'config' => json_encode($config), 'id' => $id, 'me' => $user_info['id'])
					);
				else
					$id = $smcFunc['db_insert']('insert',
						'{db_prefix}as_streams',
						// The name is stored escaped, so it can be longer than 40 characters
						// ("&" becomes "&amp;"). 255 is the column size: nothing gets cut mid-entity.
						array('id_member' => 'int', 'name' => 'string-255', 'config' => 'string', 'sort_order' => 'int', 'created' => 'int'),
						array($user_info['id'], $value, json_encode($config), count($all), time()),
						array('id_stream'),
						1
					);

				if (!empty($_POST['make_default']))
					self::saveDefault($user_info['id'], 's' . $id);
				// The box is ticked when editing the default stream, so unticking it means "not any more".
				elseif (self::defaultTab($user_info['id']) === 's' . $id)
					self::saveDefault($user_info['id'], 'all');

				$_SESSION['as_notice'] = array('info', $txt['as_stream_saved']);
				redirectexit('action=activity;stream=s' . $id);
			}

			// Shown again after an error: same (escaped) form as saved names.
			$stream = array('id' => $id, 'name' => $smcFunc['htmlspecialchars']($name, ENT_QUOTES), 'config' => $config);
		}

		$context['as_stream'] = $stream;
		$context['as_is_default_stream'] = $id && self::defaultTab($user_info['id']) === 's' . $id;
		$context['as_types'] = self::types();
		$context['as_boards'] = self::visibleBoards();
		$context['as_tabs'] = self::tabs();
		$context['as_current'] = $id ? 's' . $id : '';
		$context['page_title'] = $id ? $txt['as_edit_stream'] : $txt['as_new_stream'];
		$context['sub_template'] = 'as_edit_stream';
	}

	public static function visibleBoards()
	{
		global $smcFunc;

		$excluded = self::excludedBoards();
		$boards = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, b.child_level, c.name AS cat_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (!in_array((int) $row['id_board'], $excluded, true))
				$boards[(int) $row['id_board']] = $row;
		$smcFunc['db_free_result']($request);

		return $boards;
	}

	/* ------------------------------------------------------------------
	 * Profile
	 * ---------------------------------------------------------------- */

	public static function profileAreas(&$profile_areas)
	{
		global $txt;

		if (!self::enabled())
			return;

		loadLanguage('ActivityStream');

		$profile_areas['info']['areas']['activity'] = array(
			'label' => $txt['as_profile_label'],
			'function' => 'ActivityStream::profileArea',
			'icon' => 'members',
			'permission' => array(
				'own' => array('as_view'),
				'any' => array('as_view'),
			),
		);
	}

	public static function profileArea($memID = 0)
	{
		global $context, $txt, $user_info, $scripturl, $memberContext;

		if (empty($memID))
			$memID = isset($context['id_member']) ? (int) $context['id_member'] : (int) $user_info['id'];
		$memID = (int) $memID;

		loadLanguage('ActivityStream');
		loadTemplate('ActivityStream');
		loadCSSFile('activitystream.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_activitystream');
		loadJavaScriptFile('activitystream.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_activitystream');

		$types = array_values(array_diff(array_keys(self::types()), array('members')));
		$config = self::normaliseConfig(array('members' => 'user', 'user' => $memID, 'types' => $types));
		$page = self::fetch($config, self::cursorFromRequest(), self::perPage());

		$base = $scripturl . '?action=profile;area=activity;u=' . $memID;

		$context['as_url'] = $scripturl . '?action=activity';
		$context['as_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['as_items'] = self::prepare($page['items']);
		$context['as_next'] = $page['next'] !== '' ? $base . ';cursor=' . $page['next'] : '';
		$context['as_member'] = array(
			'id' => $memID,
			'own' => $memID === (int) $user_info['id'],
			'counts' => self::followCounts($memID),
			'following' => !$user_info['is_guest'] && self::isFollowing($user_info['id'], $memID),
			'can_follow' => self::canCustomise() && $memID !== (int) $user_info['id'],
			'name' => isset($context['member']['name']) ? $context['member']['name'] : '',
		);

		if (!empty($_GET['fragment']))
		{
			$context['template_layers'] = array();
			$context['sub_template'] = 'as_fragment';
			return;
		}

		$context['page_title'] = $txt['as_profile_label'];
		$context['sub_template'] = 'as_profile';
	}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ---------------------------------------------------------------- */

	public static function actions(&$actionArray)
	{
		$actionArray['activity'] = array('ActivityStream.php', 'ActivityStream::action');
	}

	public static function menuButtons(&$buttons)
	{
		global $txt, $scripturl, $modSettings, $smcFunc;

		if (!self::enabled() || empty($modSettings['as_show_menu']))
			return;

		loadLanguage('ActivityStream');

		$button = array(
			'activity' => array(
				'title' => !empty($modSettings['as_menu_label']) ? $smcFunc['htmlspecialchars']($modSettings['as_menu_label'], ENT_QUOTES) : $txt['as_menu'],
				'href' => $scripturl . '?action=activity',
				'show' => allowedTo('as_view'),
				'icon' => 'posts',
			),
		);

		$pos = array_search('home', array_keys($buttons), true);
		$buttons = $pos === false
			? $button + $buttons
			: array_slice($buttons, 0, $pos + 1, true) + $button + array_slice($buttons, $pos + 1, null, true);
	}

	public static function deleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}as_follows WHERE id_member IN ({array_int:u}) OR id_followed IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}as_streams WHERE id_member IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}as_prefs WHERE id_member IN ({array_int:u})', array('u' => $users));
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('ActivityStream');
		$permissionList['membergroup']['as_view'] = array(false, 'general');
		$permissionList['membergroup']['as_follow'] = array(false, 'general');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'as_follow';
	}

	/**
	 * Admin > Configuration > Modification Settings > Activity Stream
	 */
	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('ActivityStream');

		$admin_areas['config']['areas']['modsettings']['subsections']['activitystream'] = array($txt['as_title']);

		// The old page (1.1.1 and earlier): hidden, only sends old links on.
		$admin_areas['config']['areas']['activitystream'] = array(
			'label' => $txt['as_title'],
			'function' => 'ActivityStream::redirectOldAdmin',
			'permission' => array('admin_forum'),
			'hidden' => true,
		);
	}

	public static function modifyModifications(&$subActions)
	{
		$subActions['activitystream'] = 'ActivityStream::adminSettings';
	}

	/**
	 * Lets the admin search find these settings.
	 */
	public static function adminSearch(&$language_files, &$include_files, &$settings_search)
	{
		$language_files[] = 'ActivityStream';
		$settings_search[] = array('ActivityStream::adminSettings', 'area=modsettings;sa=activitystream');
	}

	/**
	 * Old bookmarks to action=admin;area=activitystream go to the new place.
	 */
	public static function redirectOldAdmin()
	{
		redirectexit('action=admin;area=modsettings;sa=activitystream');
	}

	protected static function configVars($boards = array())
	{
		global $txt;

		return array(
			array('check', 'as_enabled'),
			array('check', 'as_show_menu'),
			array('text', 'as_menu_label', 'subtext' => $txt['as_menu_label_desc']),
			'',
			array('check', 'as_type_topics'),
			array('check', 'as_type_replies'),
			array('check', 'as_type_likes', 'subtext' => $txt['as_type_likes_desc']),
			array('check', 'as_type_members', 'subtext' => $txt['as_type_members_desc']),
			'',
			array('int', 'as_per_page', 'min' => 5, 'max' => 100),
			array('int', 'as_popular_days', 'min' => 1, 'max' => 90, 'postinput' => $txt['as_days']),
			array('int', 'as_max_streams', 'min' => 0, 'max' => 50, 'subtext' => $txt['as_max_streams_desc']),
			array('select', 'as_exclude_boards', $boards, 'multiple' => true, 'subtext' => $txt['as_exclude_boards_desc']),
		);
	}

	public static function adminSettings($return_config = false)
	{
		global $context, $txt, $sourcedir, $scripturl, $smcFunc;

		loadLanguage('ActivityStream');

		// The admin search only needs the list of settings.
		if ($return_config)
			return self::configVars();

		isAllowedTo('admin_forum');
		require_once($sourcedir . '/ManageServer.php');

		$boards = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, c.name AS cat_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[(int) $row['id_board']] = $row['cat_name'] . ' › ' . $row['name'];
		$smcFunc['db_free_result']($request);

		$config_vars = self::configVars($boards);

		$context['page_title'] = $txt['as_title'];
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;sa=activitystream;save';
		$context['settings_title'] = $txt['as_title'];

		// The description under the Modification Settings tabs.
		if (!empty($context['admin_menu_name']))
			$context[$context['admin_menu_name']]['tab_data']['tabs']['activitystream'] = array('description' => $txt['as_admin_desc']);

		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['as_per_page'] = max(5, min(100, isset($_POST['as_per_page']) ? (int) $_POST['as_per_page'] : 25));
			$_POST['as_popular_days'] = max(1, min(90, isset($_POST['as_popular_days']) ? (int) $_POST['as_popular_days'] : 7));
			$_POST['as_max_streams'] = max(0, min(50, isset($_POST['as_max_streams']) ? (int) $_POST['as_max_streams'] : 10));
			$_POST['as_menu_label'] = $smcFunc['substr'](trim(strip_tags(isset($_POST['as_menu_label']) ? (string) $_POST['as_menu_label'] : '')), 0, 30);
			// Save the board list ourselves, as a JSON array of real board IDs,
			// rather than relying on how SMF stores multiple selects.
			$excluded = array_values(array_intersect(self::idList(isset($_POST['as_exclude_boards']) ? (array) $_POST['as_exclude_boards'] : array()), array_keys($boards)));
			unset($_POST['as_exclude_boards']);
			saveDBSettings(array_values(array_filter($config_vars, function ($v)
			{
				return !is_array($v) || !isset($v[1]) || $v[1] !== 'as_exclude_boards';
			})));
			updateSettings(array('as_exclude_boards' => json_encode($excluded)));
			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=modsettings;sa=activitystream');
		}

		prepareDBSettingContext($config_vars);
		// Show the saved selection however it was stored.
		if (isset($context['config_vars']['as_exclude_boards']))
			$context['config_vars']['as_exclude_boards']['value'] = self::adminExcludedBoards();
		$context['sub_template'] = 'show_settings';
	}

	/**
	 * Who's Online: "Viewing the Activity Stream".
	 */
	public static function whosOnline($actions)
	{
		global $txt, $scripturl;

		if (empty($actions['action']) || $actions['action'] !== 'activity' || !allowedTo('as_view'))
			return '';

		loadLanguage('ActivityStream');

		return sprintf($txt['as_whos_online'], $scripturl . '?action=activity');
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('ActivityStream');
		$context['copyrights']['mods'][] = sprintf($txt['as_credits'], self::VERSION);
	}
}
