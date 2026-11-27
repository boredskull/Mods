<?php
/**
 * Community Digest - selection, scoring, rendering and delivery engine.
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

class CommunityDigestEngine
{
	const FREQ_OFF = 0;
	const FREQ_DAILY = 1;
	const FREQ_WEEKLY = 2;
	const FREQ_MONTHLY = 3;

	const DELIVER_ONSITE = 0;
	const DELIVER_EMAIL = 1;
	const DELIVER_BOTH = 2;

	const LOG_FAILED = 0;
	const LOG_SENT = 1;
	const LOG_TEST = 2;
	const LOG_SKIPPED = 3;

	/** Display order of every built-in section. */
	public static $allSections = array(
		'recommended', 'following', 'questions', 'unanswered', 'clubs',
		'polls', 'popular', 'events', 'newmembers', 'wall', 'stats',
	);

	/** Sections recomputed live on the dashboard rather than stored. */
	public static $liveSections = array('events', 'newmembers', 'wall', 'stats');

	private static $boardCache = null;
	private static $msgSinceCache = array();
	private static $poolCache = array();
	private static $exclusionCache = null;
	private static $permCache = array();

	/** Encode a plain or SMF entity-encoded value for HTML text/attributes. */
	public static function escape($value)
	{
		return htmlspecialchars(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** Return only local or HTTP(S) navigation URLs from extensions/records. */
	public static function safeUrl($url)
	{
		$url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if (preg_match('~[\x00-\x20\x7f]~', $url))
			return '';
		if (preg_match('~^https?://~i', $url) || (isset($url[0]) && in_array($url[0], array('/', '?', '#')) && substr($url, 0, 2) !== '//'))
			return self::escape($url);
		return '';
	}

	/* ------------------------------------------------------------------
	 * Settings & preferences
	 * ------------------------------------------------------------------ */

	public static function setting($key, $default = 0)
	{
		global $modSettings;

		return isset($modSettings[$key]) && $modSettings[$key] !== '' ? $modSettings[$key] : $default;
	}

	public static function periodSeconds($freq)
	{
		switch ((int) $freq)
		{
			case self::FREQ_DAILY:
				return 86400;
			case self::FREQ_MONTHLY:
				return 30 * 86400;
			default:
				return 7 * 86400;
		}
	}

	public static function allowedFrequencies()
	{
		$out = array();
		if (self::setting('cd_allow_daily', 1))
			$out[] = self::FREQ_DAILY;
		if (self::setting('cd_allow_weekly', 1))
			$out[] = self::FREQ_WEEKLY;
		if (self::setting('cd_allow_monthly', 1))
			$out[] = self::FREQ_MONTHLY;

		return $out;
	}

	public static function enabledSections()
	{
		$out = array();
		foreach (self::$allSections as $s)
			if (self::setting('cd_section_' . $s, 1))
				$out[] = $s;

		return $out;
	}

	/** Can this built-in section work right now? No display toggle is checked here. */
	public static function sectionAvailable($s, $member = null)
	{
		global $modSettings;

		if ($s === 'questions')
			return CommunityDigestIntegrations::active('qa');
		if ($s === 'clubs')
			return CommunityDigestIntegrations::active('clubs');
		if ($s === 'wall')
			return CommunityDigestIntegrations::active('profilewall');
		if ($s === 'events')
			return !empty($modSettings['cal_enabled']) && ($member === null || self::generalPermission($member, 'calendar_view'));
		if ($s === 'newmembers')
			return $member === null || self::generalPermission($member, 'view_mlist');
		if ($s === 'polls' && $member !== null)
		{
			// Polls are an SMF core feature, but this member must have at
			// least one accessible board on which voting is permitted.
			$boards = self::visibleBoards($member);
			$view = self::boardPermission($member, 'poll_view', $boards);
			return !empty($view) && !empty(self::boardPermission($member, 'poll_vote', $view));
		}

		return true;
	}

	/** Effective built-in sections, including admin settings and user permissions. */
	public static function selectableSections($member = null)
	{
		$out = array();
		foreach (self::enabledSections() as $s)
			if (self::sectionAvailable($s, $member))
				$out[] = $s;

		return $out;
	}

	public static function defaultPrefs()
	{
		return array(
			'frequency' => (int) self::setting('cd_default_frequency', self::FREQ_WEEKLY),
			'delivery' => (int) self::setting('cd_default_delivery', self::DELIVER_ONSITE),
			'sections' => array(),
			'boards' => array(),
			'keywords' => array(),
			'weekly_day' => 1,
			'send_hour' => (int) self::setting('cd_default_hour', 8),
			'last_sent' => 0,
			'next_send' => 0,
			'token' => '',
			'is_default' => true,
		);
	}

	private static function csvInts($value)
	{
		return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $value)))));
	}

	private static function decodePrefsRow($row)
	{
		return array(
			'frequency' => (int) $row['frequency'],
			'delivery' => (int) $row['delivery'],
			'sections' => $row['sections'] === '__none__' ? array('__none__') : array_values(array_intersect(explode(',', $row['sections']), self::$allSections)),
			'boards' => self::csvInts($row['boards']),
			'keywords' => array_values(array_filter(array_map('trim', explode(',', $row['keywords'])), 'strlen')),
			'weekly_day' => (int) $row['weekly_day'],
			'send_hour' => (int) $row['send_hour'],
			'last_sent' => (int) $row['last_sent'],
			'next_send' => (int) $row['next_send'],
			'token' => $row['token'],
			'is_default' => false,
		);
	}

	public static function loadPrefs($id_member)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT frequency, delivery, sections, boards, keywords, weekly_day, send_hour, last_sent, next_send, token
			FROM {db_prefix}digest_prefs
			WHERE id_member = {int:member}',
			array('member' => (int) $id_member)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return $row ? self::decodePrefsRow($row) : self::defaultPrefs();
	}

	public static function savePrefs($id_member, array $prefs)
	{
		global $smcFunc;

		if (empty($prefs['token']))
			$prefs['token'] = bin2hex(random_bytes(16));

		$smcFunc['db_insert']('replace',
			'{db_prefix}digest_prefs',
			array(
				'id_member' => 'int', 'frequency' => 'int', 'delivery' => 'int', 'sections' => 'string-255',
				'boards' => 'string', 'keywords' => 'string-255', 'weekly_day' => 'int', 'send_hour' => 'int',
				'last_sent' => 'int', 'next_send' => 'int', 'token' => 'string-32',
			),
			array(
				(int) $id_member, (int) $prefs['frequency'], (int) $prefs['delivery'], implode(',', $prefs['sections']),
				implode(',', $prefs['boards']), implode(',', $prefs['keywords']), (int) $prefs['weekly_day'], (int) $prefs['send_hour'],
				(int) $prefs['last_sent'], (int) $prefs['next_send'], $prefs['token'],
			),
			array('id_member')
		);

		if ($prefs['frequency'] == self::FREQ_OFF || !in_array($prefs['delivery'], array(self::DELIVER_EMAIL, self::DELIVER_BOTH)))
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_email_queue WHERE id_member = {int:member}', array('member' => (int) $id_member));

		return $prefs;
	}

	/**
	 * Next delivery time in the member's own timezone.
	 */
	public static function computeNextSend($freq, $hour, $weekday, $tz, $now = null)
	{
		global $modSettings;

		$now = $now === null ? time() : (int) $now;
		$hour = max(0, min(23, (int) $hour));

		try
		{
			$zone = new DateTimeZone(!empty($tz) ? $tz : (!empty($modSettings['default_timezone']) ? $modSettings['default_timezone'] : 'UTC'));
		}
		catch (Exception $e)
		{
			$zone = new DateTimeZone('UTC');
		}

		$current = new DateTime('@' . $now);
		$current->setTimezone($zone);
		$c = clone $current;
		$c->setTime($hour, 0, 0);

		switch ((int) $freq)
		{
			case self::FREQ_DAILY:
				if ($c <= $current)
					$c->modify('+1 day');
				break;

			case self::FREQ_MONTHLY:
				$c->setDate((int) $c->format('Y'), (int) $c->format('n'), 1);
				if ($c <= $current)
				{
					$c->modify('first day of next month');
					$c->setTime($hour, 0, 0);
				}
				break;

			default:
				$diff = ((int) $weekday - (int) $c->format('w') + 7) % 7;
				if ($diff > 0)
					$c->modify('+' . $diff . ' days');
				if ($c <= $current)
					$c->modify('+7 days');
		}

		return $c->getTimestamp();
	}

	/* ------------------------------------------------------------------
	 * Members, boards and permissions
	 * ------------------------------------------------------------------ */

	public static function loadMembers(array $ids)
	{
		global $smcFunc;

		$ids = array_filter(array_map('intval', $ids));
		if (empty($ids))
			return array();

		$members = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, member_name, email_address, lngfile, timezone, id_group, additional_groups,
				id_post_group, ignore_boards, pm_ignore_list, buddy_list, is_activated, posts
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ids})',
			array('ids' => $ids)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['groups'] = self::memberGroups($row);
			$row['is_admin'] = in_array(1, $row['groups']);
			$row['ignore_users'] = self::csvInts($row['pm_ignore_list']);
			$row['buddies'] = self::csvInts($row['buddy_list']);
			$members[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);

		return $members;
	}

	public static function memberGroups(array $m)
	{
		$groups = array((int) $m['id_group'], (int) $m['id_post_group']);
		foreach (explode(',', (string) $m['additional_groups']) as $g)
			if (trim($g) !== '')
				$groups[] = (int) $g;

		return array_values(array_unique($groups));
	}

	public static function allBoards()
	{
		global $smcFunc;

		if (self::$boardCache !== null)
			return self::$boardCache;

		self::$boardCache = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, b.id_cat, b.child_level, b.id_profile,
				b.redirect, c.name AS cat_name
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			self::$boardCache[(int) $row['id_board']] = $row;
		}
		$smcFunc['db_free_result']($request);

		return self::$boardCache;
	}

	/**
	 * Boards this member may read, matching SMF 2.1's build_query_board().
	 * SMF uses board_permissions_view, which may differ from the legacy
	 * boards.member_groups fields when other mods manage board visibility.
	 * With $forDigest, also removes ignored, excluded, recycle and redirects.
	 */
	public static function visibleBoards(array $m, $forDigest = true)
	{
		global $smcFunc, $modSettings;

		$ignored = $forDigest ? self::csvInts($m['ignore_boards']) : array();
		$excluded = $forDigest ? self::csvInts(self::setting('cd_excluded_boards', '')) : array();
		$recycle = !empty($modSettings['recycle_enable']) ? (int) $modSettings['recycle_board'] : 0;
		$boardManagers = self::csvInts(isset($modSettings['board_manager_groups']) ? $modSettings['board_manager_groups'] : '');
		$canSeeAll = $m['is_admin'] || (!empty($boardManagers) && (bool) array_intersect($m['groups'], $boardManagers));
		$allows = array();
		$denies = array();

		if (!$canSeeAll && !empty($m['groups']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_board, deny
				FROM {db_prefix}board_permissions_view
				WHERE id_group IN ({array_int:groups})',
				array('groups' => $m['groups'])
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				if ((int) $row['deny'] === 1)
					$denies[(int) $row['id_board']] = true;
				else
					$allows[(int) $row['id_board']] = true;
			}
			$smcFunc['db_free_result']($request);
		}

		$out = array();
		foreach (self::allBoards() as $id => $b)
		{
			if (!$canSeeAll && (!isset($allows[$id]) || (!empty($modSettings['deny_boards_access']) && isset($denies[$id]))))
				continue;

			if ($forDigest && ($b['redirect'] !== '' || $id == $recycle || in_array($id, $ignored) || in_array($id, $excluded)))
				continue;

			$out[] = $id;
		}

		return $out;
	}

	public static function generalPermission(array $m, $permission)
	{
		global $smcFunc;

		if ($m['is_admin'])
			return true;

		$key = $permission . ':' . implode(',', $m['groups']);
		if (isset(self::$permCache[$key]))
			return self::$permCache[$key];

		$allow = false;
		$deny = false;
		$request = $smcFunc['db_query']('', '
			SELECT add_deny
			FROM {db_prefix}permissions
			WHERE permission = {string:perm}
				AND id_group IN ({array_int:groups})',
			array('perm' => $permission, 'groups' => $m['groups'])
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$row['add_deny'] ? $allow = true : $deny = true;
		$smcFunc['db_free_result']($request);

		return self::$permCache[$key] = ($allow && !$deny);
	}

	/**
	 * Of the given boards, return those where a board-level permission is granted.
	 */
	public static function boardPermission(array $m, $permission, array $boards)
	{
		global $smcFunc;

		if ($m['is_admin'] || empty($boards))
			return $boards;

		$all = self::allBoards();
		$profiles = array();
		foreach ($boards as $b)
			if (isset($all[$b]))
				$profiles[$all[$b]['id_profile']] = true;

		$allow = array();
		$deny = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_profile, add_deny
			FROM {db_prefix}board_permissions
			WHERE permission = {string:perm}
				AND id_group IN ({array_int:groups})
				AND id_profile IN ({array_int:profiles})',
			array('perm' => $permission, 'groups' => $m['groups'], 'profiles' => array_keys($profiles))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$row['add_deny'] ? $allow[$row['id_profile']] = true : $deny[$row['id_profile']] = true;
		$smcFunc['db_free_result']($request);

		$out = array();
		foreach ($boards as $b)
			if (isset($all[$b]) && isset($allow[$all[$b]['id_profile']]) && !isset($deny[$all[$b]['id_profile']]))
				$out[] = $b;

		return $out;
	}

	/**
	 * Board tree for preference checklists.
	 */
	public static function boardTree(array $m)
	{
		$visible = array_flip(self::visibleBoards($m, false));
		$tree = array();
		foreach (self::allBoards() as $id => $b)
		{
			if (!isset($visible[$id]) || $b['redirect'] !== '')
				continue;

			if (!isset($tree[$b['id_cat']]))
				$tree[$b['id_cat']] = array('name' => $b['cat_name'], 'boards' => array());

			$tree[$b['id_cat']]['boards'][$id] = array('id' => $id, 'name' => $b['name'], 'level' => (int) $b['child_level']);
		}

		return $tree;
	}

	/* ------------------------------------------------------------------
	 * Candidate pools
	 * ------------------------------------------------------------------ */

	/**
	 * Lowest id_msg posted at or after $time (binary search on the primary key,
	 * so it stays fast on very large message tables).
	 */
	public static function msgIdSince($time)
	{
		global $smcFunc;

		// Windows can start seconds apart; rounding can return the wrong first message.
		$key = (int) $time;
		if (isset(self::$msgSinceCache[$key]))
			return self::$msgSinceCache[$key];

		$request = $smcFunc['db_query']('', 'SELECT MAX(id_msg) FROM {db_prefix}messages', array());
		list ($high) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$high = (int) $high;
		$low = 1;
		$result = $high + 1;

		while ($low <= $high)
		{
			$mid = intdiv($low + $high, 2);
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, poster_time
				FROM {db_prefix}messages
				WHERE id_msg >= {int:mid}
				ORDER BY id_msg
				LIMIT 1',
				array('mid' => $mid)
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!$row)
				$high = $mid - 1;
			elseif ($row['poster_time'] >= $time)
			{
				$result = (int) $row['id_msg'];
				$high = $mid - 1;
			}
			else
				$low = (int) $row['id_msg'] + 1;
		}

		return self::$msgSinceCache[$key] = $result;
	}

	private static function globalExclusions()
	{
		global $smcFunc;

		if (self::$exclusionCache !== null)
			return self::$exclusionCache;

		$topics = array();
		$request = $smcFunc['db_query']('', 'SELECT id_topic FROM {db_prefix}digest_excluded', array());
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$topics[(int) $row['id_topic']] = true;
		$smcFunc['db_free_result']($request);

		if (self::setting('cd_exclude_reported', 1))
		{
			$request = $smcFunc['db_query']('', '
				SELECT DISTINCT id_topic
				FROM {db_prefix}log_reported
				WHERE closed = {int:open}
					AND ignore_all = {int:no}
					AND id_topic > {int:zero}',
				array('open' => 0, 'no' => 0, 'zero' => 0)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$topics[(int) $row['id_topic']] = true;
			$smcFunc['db_free_result']($request);
		}

		return self::$exclusionCache = $topics;
	}

	/**
	 * Topics with activity in the requested interval, after global filters.
	 * Normal digests use the fast id_msg scan; fixed-window queued emails use
	 * actual in-window messages so later bumps never hide earlier activity.
	 */
	public static function topicPool($since, $newOnly = false, $until = null)
	{
		global $smcFunc;

		$since = (int) $since;
		$until = $until === null ? null : (int) $until;
		$key = ($newOnly ? 'n' : 'a') . ':' . $since . ':' . ($until === null ? 'live' : $until);
		if (isset(self::$poolCache[$key]))
			return self::$poolCache[$key];

		$limit = max(50, min(2000, (int) self::setting('cd_pool_size', 400)));
		$minPosts = (int) self::setting('cd_min_starter_posts', 0);
		$excludeLocked = (bool) self::setting('cd_exclude_locked', 0);
		$excluded = self::globalExclusions();
		$pool = array();

		if ($until !== null)
		{
			// A topic can receive newer posts while an email sits in the queue.
			// Selecting by topics.id_last_msg would then incorrectly omit it.
			$window = array();
			$request = $smcFunc['db_query']('', '
				SELECT m.id_topic, MAX(m.id_msg) AS last_msg, COUNT(*) AS window_posts
				FROM {db_prefix}messages AS m
				WHERE m.poster_time >= {int:since}
					AND m.poster_time <= {int:until}
					AND m.approved = {int:approved}
				GROUP BY m.id_topic
				ORDER BY last_msg DESC
				LIMIT {int:limit}',
				array('since' => $since, 'until' => $until, 'approved' => 1, 'limit' => $limit)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$window[(int) $row['id_topic']] = array('last_msg' => (int) $row['last_msg'], 'window_posts' => (int) $row['window_posts']);
			$smcFunc['db_free_result']($request);

			if (empty($window))
				return self::$poolCache[$key] = array();

			$lastMessages = array_column($window, 'last_msg');
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_board, t.id_poll, t.num_replies, t.num_views, t.locked, t.is_sticky,
					t.id_member_started, t.id_first_msg, t.id_last_msg, mf.subject, mf.poster_time AS first_time,
					ml.poster_time AS last_time, COALESCE(mem.real_name, mf.poster_name) AS starter_name,
					COALESCE(mem.posts, 0) AS starter_posts, mem.is_activated
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_topic = t.id_topic AND ml.id_msg IN ({array_int:last_msgs}))
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
				WHERE t.id_topic IN ({array_int:topics})
					AND t.approved = {int:approved}
					AND t.id_redirect_topic = {int:zero}' . ($newOnly ? '
					AND mf.poster_time >= {int:since} AND mf.poster_time <= {int:until}' : '') . '
				ORDER BY ml.id_msg DESC',
				array('topics' => array_keys($window), 'last_msgs' => $lastMessages, 'approved' => 1, 'zero' => 0,
					'since' => $since, 'until' => $until)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id = (int) $row['id_topic'];
				if (isset($excluded[$id]) || ($excludeLocked && !empty($row['locked'])) ||
					(!empty($row['id_member_started']) && $row['is_activated'] !== null && (int) $row['is_activated'] !== 1) ||
					($minPosts > 0 && $row['starter_posts'] < $minPosts))
					continue;

				// Repeat/read suppression must also refer to the historical cutoff.
				$row['id_last_msg'] = $window[$id]['last_msg'];
				$row['window_posts'] = $window[$id]['window_posts'];
				$pool[$id] = $row;
			}
			$smcFunc['db_free_result']($request);
		}
		else
		{
			$minMsg = self::msgIdSince($since);
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_board, t.id_poll, t.num_replies, t.num_views, t.locked, t.is_sticky,
					t.id_member_started, t.id_first_msg, t.id_last_msg, mf.subject, mf.poster_time AS first_time,
					ml.poster_time AS last_time, COALESCE(mem.real_name, mf.poster_name) AS starter_name,
					COALESCE(mem.posts, 0) AS starter_posts, mem.is_activated
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
					INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
				WHERE ' . ($newOnly ? 't.id_first_msg' : 't.id_last_msg') . ' >= {int:min_msg}
					AND ml.poster_time >= {int:since}
					AND t.approved = {int:approved}
					AND t.id_redirect_topic = {int:zero}
				ORDER BY t.id_last_msg DESC
				LIMIT {int:limit}',
				array('min_msg' => $minMsg, 'since' => $since, 'approved' => 1, 'zero' => 0, 'limit' => $limit)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id = (int) $row['id_topic'];
				if (isset($excluded[$id]) || ($excludeLocked && !empty($row['locked'])) ||
					(!empty($row['id_member_started']) && $row['is_activated'] !== null && (int) $row['is_activated'] !== 1) ||
					($minPosts > 0 && $row['starter_posts'] < $minPosts))
					continue;

				$row['window_posts'] = 0;
				$pool[$id] = $row;
			}
			$smcFunc['db_free_result']($request);

			if (!empty($pool))
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_topic, COUNT(*) AS c
					FROM {db_prefix}messages
					WHERE id_topic IN ({array_int:topics})
						AND id_msg >= {int:min_msg}
						AND poster_time >= {int:since}
						AND approved = {int:approved}
					GROUP BY id_topic',
					array('topics' => array_keys($pool), 'min_msg' => $minMsg, 'since' => $since, 'approved' => 1)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$pool[(int) $row['id_topic']]['window_posts'] = (int) $row['c'];
				$smcFunc['db_free_result']($request);
			}
		}

		return self::$poolCache[$key] = $pool;
	}

	/* ------------------------------------------------------------------
	 * Digest building
	 * ------------------------------------------------------------------ */

	/**
	 * Build a digest for one member.
	 *
	 * @param array $m      Member row from loadMembers()
	 * @param array $prefs  Preferences from loadPrefs()
	 * @param int   $since  Window start
	 * @param array|null $only Restrict to these section ids
	 * @param int|null $until Optional upper time bound for queued email deliveries
	 * @return array ['sections' => [...], 'count' => int, 'since' => int, 'until' => int]
	 */
	public static function build(array $m, array $prefs, $since, $only = null, $until = null)
	{
		global $txt;

		$freq = $prefs['frequency'] ?: self::FREQ_WEEKLY;
		// Retryable emails must not grow their reporting window while queued.
		$upper = $until === null ? time() : min(time(), max((int) $since, (int) $until));
		$boards = self::visibleBoards($m);
		$boardSet = array_flip($boards);
		$ignoreUsers = array_flip($m['ignore_users']);

		$poolAll = self::topicPool($since, false, $until === null ? null : $upper);
		$pool = array();
		foreach ($poolAll as $id => $t)
			if (isset($boardSet[$t['id_board']]) && !isset($ignoreUsers[(int) $t['id_member_started']]))
				$pool[$id] = $t;

		$ctx = array(
			'member' => $m,
			'prefs' => $prefs,
			'since' => (int) $since,
			'until' => $upper,
			'freq' => $freq,
			'boards' => $boards,
			'board_set' => $boardSet,
			'pool' => $pool,
			'pool_all' => $poolAll,
			'used' => array(),
			'max' => max(1, (int) self::setting('cd_max_per_section', 5)),
			'remaining' => max(1, (int) self::setting('cd_max_total', 25)),
			// A queued email ignores history recorded at or after its own period end:
			// that is the on-forum copy of the same digest (dual delivery), which
			// would otherwise mark every pick as already shown.
			'signals' => self::personalSignals($m, $prefs, array_keys($pool), $until === null ? null : $upper),
		);

		// Remove topics the member has unwatched, already read, or already been shown with nothing new.
		$skipRead = (bool) self::setting('cd_skip_read', 1);
		$maxRepeats = (int) self::setting('cd_max_repeats', 2);
		foreach ($ctx['pool'] as $id => $t)
		{
			$s = $ctx['signals'];
			if (isset($s['unwatched'][$id]))
				unset($ctx['pool'][$id]);
			elseif ($skipRead && isset($s['read'][$id]) && $s['read'][$id] >= $t['id_last_msg'])
				unset($ctx['pool'][$id]);
			elseif (isset($s['history'][$id]) && ($s['history'][$id]['last_msg_shown'] >= $t['id_last_msg'] || ($maxRepeats > 0 && $s['history'][$id]['times_shown'] >= $maxRepeats)))
				unset($ctx['pool'][$id]);
		}

		$builders = array(
			'recommended' => array(__CLASS__, 'sectionRecommended'),
			'following' => array(__CLASS__, 'sectionFollowing'),
			'questions' => array('CommunityDigestIntegrations', 'sectionQuestions'),
			'unanswered' => array(__CLASS__, 'sectionUnanswered'),
			'clubs' => array('CommunityDigestIntegrations', 'sectionClubs'),
			'polls' => array(__CLASS__, 'sectionPolls'),
			'popular' => array(__CLASS__, 'sectionPopular'),
			'events' => array(__CLASS__, 'sectionEvents'),
			'newmembers' => array(__CLASS__, 'sectionNewMembers'),
			'wall' => array('CommunityDigestIntegrations', 'sectionWall'),
			'stats' => array(__CLASS__, 'sectionStats'),
		);

		// Third-party mods can add or replace sections: $builders['mysection'] = callable($ctx) returning a section array.
		call_integration_hook('integrate_community_digest_sections', array(&$builders, &$ctx));

		// Builders enforce user-specific permissions themselves; avoid a
		// second poll-permission scan for every scheduled digest recipient.
		$wanted = array_merge(self::selectableSections(), array_diff(array_keys($builders), self::$allSections));
		if (!empty($prefs['sections']))
			$wanted = array_intersect($wanted, array_merge($prefs['sections'], array_diff(array_keys($builders), self::$allSections)));
		if ($only !== null)
			$wanted = array_intersect($wanted, $only);

		$sections = array();
		$count = 0;
		foreach ($builders as $id => $callable)
		{
			if (!in_array($id, $wanted) || !is_callable($callable))
				continue;
			if ($ctx['remaining'] <= 0 && !in_array($id, self::$liveSections))
				continue;

			$section = call_user_func_array($callable, array(&$ctx));
			if (empty($section) || (empty($section['items']) && empty($section['lines'])))
				continue;

			$section['id'] = $id;
			if (!isset($section['title']))
				$section['title'] = isset($txt['cd_section_' . $id]) ? $txt['cd_section_' . $id] : $id;
			$section['items'] = isset($section['items']) ? $section['items'] : array();
			$section['lines'] = isset($section['lines']) ? $section['lines'] : array();

			$count += count($section['items']) + count($section['lines']);
			$sections[$id] = $section;
		}

		self::attachExcerpts($sections);

		return array('sections' => $sections, 'count' => $count, 'since' => (int) $since, 'until' => $ctx['until']);
	}

	private static function personalSignals(array $m, array $prefs, array $topicIds, $historyBefore = null)
	{
		global $smcFunc;

		$s = array(
			'participated' => array(), 'notify_topics' => array(), 'notify_boards' => array(),
			'read' => array(), 'unwatched' => array(), 'history' => array(),
			'follow_boards' => array_flip($prefs['boards']),
			'following' => array_flip(array_merge($m['buddies'], CommunityDigestIntegrations::followedMembers($m['id_member']))),
			'keywords' => array_map(function ($k) use ($smcFunc) { return $smcFunc['strtolower']($k); }, $prefs['keywords']),
		);

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_board
			FROM {db_prefix}log_notify
			WHERE id_member = {int:member}',
			array('member' => $m['id_member'])
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['id_topic']))
				$s['notify_topics'][(int) $row['id_topic']] = true;
			if (!empty($row['id_board']))
				$s['notify_boards'][(int) $row['id_board']] = true;
		}
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, times_shown, last_msg_shown
			FROM {db_prefix}digest_items
			WHERE id_member = {int:member}
				AND id_topic > {int:zero}' . ($historyBefore !== null ? '
				AND batch < {int:before}' : ''),
			array('member' => $m['id_member'], 'zero' => 0, 'before' => (int) $historyBefore)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$s['history'][(int) $row['id_topic']] = array('times_shown' => (int) $row['times_shown'], 'last_msg_shown' => (int) $row['last_msg_shown']);
		$smcFunc['db_free_result']($request);

		if (empty($topicIds))
			return $s;

		// Uses the (id_member, id_topic) "participation" index.
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_topic
			FROM {db_prefix}messages
			WHERE id_member = {int:member}
				AND id_topic IN ({array_int:topics})',
			array('member' => $m['id_member'], 'topics' => $topicIds)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$s['participated'][(int) $row['id_topic']] = true;
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, id_msg, unwatched
			FROM {db_prefix}log_topics
			WHERE id_member = {int:member}
				AND id_topic IN ({array_int:topics})',
			array('member' => $m['id_member'], 'topics' => $topicIds)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$s['read'][(int) $row['id_topic']] = (int) $row['id_msg'];
			if (!empty($row['unwatched']))
				$s['unwatched'][(int) $row['id_topic']] = true;
		}
		$smcFunc['db_free_result']($request);

		return $s;
	}

	/**
	 * Personal score plus the main reason it was picked.
	 */
	public static function scoreTopic(array $t, array $ctx)
	{
		global $txt, $smcFunc;

		$s = $ctx['signals'];
		$id = (int) $t['id_topic'];
		$personal = 0;
		$reason = '';
		$boards = self::allBoards();
		$boardName = isset($boards[$t['id_board']]) ? $boards[$t['id_board']]['name'] : '';

		if (isset($s['participated'][$id]) || isset($s['notify_topics'][$id]))
		{
			$personal += (int) self::setting('cd_w_participated', 25);
			$reason = $reason ?: $txt['cd_reason_participated'];
		}
		if (isset($s['follow_boards'][$t['id_board']]) || isset($s['notify_boards'][$t['id_board']]))
		{
			$personal += (int) self::setting('cd_w_board', 30);
			$reason = $reason ?: sprintf($txt['cd_reason_board'], $boardName);
		}
		if (!empty($t['id_member_started']) && isset($s['following'][(int) $t['id_member_started']]))
		{
			$personal += (int) self::setting('cd_w_following', 20);
			$reason = $reason ?: sprintf($txt['cd_reason_following'], $t['starter_name']);
		}
		if (!empty($s['keywords']))
		{
			$subject = $smcFunc['strtolower'](un_htmlspecialchars($t['subject']));
			$hits = 0;
			foreach ($s['keywords'] as $kw)
			{
				if ($kw !== '' && strpos($subject, $kw) !== false)
				{
					$hits++;
					$reason = $reason ?: sprintf($txt['cd_reason_keyword'], $smcFunc['htmlspecialchars']($kw));
				}
				if ($hits >= 2)
					break;
			}
			$personal += $hits * (int) self::setting('cd_w_keyword', 20);
		}

		$popularity = (int) self::setting('cd_w_replies', 10) * log(1 + $t['window_posts'])
			+ (int) self::setting('cd_w_views', 4) * log10(1 + $t['num_views']);

		$ageDays = max(0, (time() - (int) $t['last_time']) / 86400);
		$score = ($personal + $popularity) * pow(0.9, $ageDays);

		return array('score' => $score, 'personal' => $personal, 'reason' => $reason);
	}

	public static function makeItem(array $t, $reason = '', $score = 0, $badge = '')
	{
		global $scripturl;

		$boards = self::allBoards();

		return array(
			'id_topic' => (int) $t['id_topic'],
			'id_board' => (int) $t['id_board'],
			'id_first_msg' => (int) $t['id_first_msg'],
			'id_last_msg' => (int) $t['id_last_msg'],
			'subject' => $t['subject'],
			'href' => $scripturl . '?topic=' . $t['id_topic'] . '.0',
			'new_href' => $scripturl . '?topic=' . $t['id_topic'] . '.new#new',
			'board' => isset($boards[$t['id_board']]) ? $boards[$t['id_board']]['name'] : '',
			'board_href' => $scripturl . '?board=' . $t['id_board'] . '.0',
			'starter' => $t['starter_name'],
			'starter_id' => (int) $t['id_member_started'],
			'replies' => (int) $t['num_replies'],
			'views' => (int) $t['num_views'],
			'window_posts' => isset($t['window_posts']) ? (int) $t['window_posts'] : 0,
			'last_time' => (int) $t['last_time'],
			'reason' => $reason,
			'score' => (int) round($score),
			'badge' => $badge,
			'excerpt' => '',
			'is_read' => false,
		);
	}

	/** Take up to $limit topics from $candidates (id => [score, reason, badge]) into a section. */
	public static function take(array &$ctx, array $candidates, $limit = null)
	{
		$limit = min($limit === null ? $ctx['max'] : $limit, $ctx['remaining']);
		uasort($candidates, function ($a, $b) { return $b['score'] <=> $a['score']; });

		$items = array();
		foreach ($candidates as $id => $c)
		{
			if (count($items) >= $limit)
				break;
			if (isset($ctx['used'][$id]) || !isset($c['topic']))
				continue;

			$ctx['used'][$id] = true;
			$items[] = self::makeItem($c['topic'], isset($c['reason']) ? $c['reason'] : '', $c['score'], isset($c['badge']) ? $c['badge'] : '');
		}
		$ctx['remaining'] -= count($items);

		return $items;
	}

	public static function sectionRecommended(array &$ctx)
	{
		global $txt;

		$candidates = array();
		foreach ($ctx['pool'] as $id => $t)
		{
			if ((int) $t['id_member_started'] === (int) $ctx['member']['id_member'] && $t['window_posts'] <= 1)
				continue;

			$sc = self::scoreTopic($t, $ctx);
			if ($sc['personal'] > 0)
				$candidates[$id] = array('topic' => $t, 'score' => $sc['score'], 'reason' => $sc['reason']);
		}

		return array('items' => self::take($ctx, $candidates), 'intro' => $txt['cd_intro_recommended']);
	}

	public static function sectionFollowing(array &$ctx)
	{
		global $txt;

		$following = $ctx['signals']['following'];
		if (empty($following))
			return array();

		$candidates = array();
		foreach ($ctx['pool'] as $id => $t)
			if ($t['first_time'] >= $ctx['since'] && isset($following[(int) $t['id_member_started']]))
				$candidates[$id] = array('topic' => $t, 'score' => $t['first_time'], 'reason' => sprintf($txt['cd_reason_following'], $t['starter_name']));

		return array('items' => self::take($ctx, $candidates));
	}

	public static function sectionPopular(array &$ctx)
	{
		global $txt;

		$candidates = array();
		foreach ($ctx['pool'] as $id => $t)
		{
			if ($t['window_posts'] < 2)
				continue;
			$score = $t['window_posts'] * 3 + log10(1 + $t['num_views']) * 2;
			$candidates[$id] = array('topic' => $t, 'score' => $score, 'reason' => sprintf($txt['cd_reason_popular'], $t['window_posts']));
		}

		return array('items' => self::take($ctx, $candidates));
	}

	public static function sectionUnanswered(array &$ctx)
	{
		global $smcFunc, $txt;

		$minAge = time() - 3600 * max(0, (int) self::setting('cd_unanswered_min_hours', 6));
		// Look further back than the digest window: an older unanswered topic is still worth surfacing.
		$since = min($ctx['since'], time() - 4 * self::periodSeconds($ctx['freq']));
		$minMsg = self::msgIdSince($since);
		$excluded = self::globalExclusions();
		$ignore = array_flip($ctx['member']['ignore_users']);
		$s = $ctx['signals'];

		$candidates = array();
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.id_board, t.id_poll, t.num_replies, t.num_views, t.locked, t.is_sticky,
				t.id_member_started, t.id_first_msg, t.id_last_msg, mf.subject, mf.poster_time AS first_time,
				mf.poster_time AS last_time, COALESCE(mem.real_name, mf.poster_name) AS starter_name, mem.is_activated
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
			WHERE t.id_first_msg >= {int:min_msg}
				AND t.num_replies = {int:zero}
				AND t.approved = {int:approved}
				AND t.locked = {int:zero}
				AND t.id_redirect_topic = {int:zero}
				AND t.id_board IN ({array_int:boards})
				AND mf.poster_time <= {int:min_age}
				AND mf.poster_time <= {int:until}
				AND t.id_member_started != {int:member}
			ORDER BY t.id_first_msg DESC
			LIMIT 100',
			array(
				'min_msg' => $minMsg, 'zero' => 0, 'approved' => 1, 'boards' => empty($ctx['boards']) ? array(0) : $ctx['boards'],
				'min_age' => $minAge, 'member' => $ctx['member']['id_member'], 'until' => $ctx['until'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$id = (int) $row['id_topic'];
			if (isset($excluded[$id]) || isset($ignore[(int) $row['id_member_started']]) || isset($s['unwatched'][$id]))
				continue;
			if (!empty($row['id_member_started']) && $row['is_activated'] !== null && (int) $row['is_activated'] !== 1)
				continue;
			if (isset($s['history'][$id]) && $s['history'][$id]['times_shown'] >= max(1, (int) self::setting('cd_max_repeats', 2)))
				continue;

			$row['window_posts'] = 1;
			// Prefer unanswered topics in boards the member cares about.
			$bonus = isset($s['follow_boards'][$row['id_board']]) || isset($s['notify_boards'][$row['id_board']]) ? 1e10 : 0;
			$candidates[$id] = array('topic' => $row, 'score' => $bonus + $row['first_time'], 'reason' => $txt['cd_reason_unanswered']);
		}
		$smcFunc['db_free_result']($request);

		return array('items' => self::take($ctx, $candidates), 'intro' => $txt['cd_intro_unanswered']);
	}

	public static function sectionPolls(array &$ctx)
	{
		global $smcFunc, $txt;

		$pollTopics = array();
		foreach ($ctx['pool'] as $id => $t)
			if (!empty($t['id_poll']))
				$pollTopics[$id] = $t;

		if (empty($pollTopics))
			return array();

		// A "vote in" suggestion must be accessible and actionable.
		$boards = array_unique(array_column($pollTopics, 'id_board'));
		$viewBoards = self::boardPermission($ctx['member'], 'poll_view', $boards);
		$voteBoards = self::boardPermission($ctx['member'], 'poll_vote', $boards);
		$allowedBoards = array_flip(array_intersect($viewBoards, $voteBoards));
		$pollIds = array_unique(array_column($pollTopics, 'id_poll'));
		$ranked = CommunityDigestIntegrations::rankedPolls($pollIds);
		$rankedVoted = CommunityDigestIntegrations::rankedVotedPolls(array_keys($ranked), $ctx['member']['id_member']);

		$polls = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_poll, question, voting_locked, expire_time
			FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:polls})',
			array('polls' => $pollIds)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$polls[(int) $row['id_poll']] = $row;
		$smcFunc['db_free_result']($request);

		// Core SMF polls and Ranked Polls keep separate voting histories.
		$alreadyVoted = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_poll
			FROM {db_prefix}log_polls
			WHERE id_member = {int:member}
				AND id_poll IN ({array_int:polls})',
			array('member' => $ctx['member']['id_member'], 'polls' => $pollIds)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$alreadyVoted[(int) $row['id_poll']] = true;
		$smcFunc['db_free_result']($request);

		$candidates = array();
		foreach ($pollTopics as $id => $t)
		{
			$p = isset($polls[$t['id_poll']]) ? $polls[$t['id_poll']] : null;
			if (!$p || !isset($allowedBoards[$t['id_board']]) || !empty($p['voting_locked']) ||
				(!empty($p['expire_time']) && $p['expire_time'] < time()) ||
				isset($alreadyVoted[(int) $t['id_poll']]) || isset($rankedVoted[(int) $t['id_poll']]))
				continue;

			$candidates[$id] = array(
				'topic' => $t,
				'score' => $t['window_posts'] * 2 + log10(1 + $t['num_views']),
				'reason' => $p['question'],
				'badge' => isset($ranked[(int) $t['id_poll']]) ? $txt['cd_badge_ranked'] : $txt['cd_badge_poll'],
			);
		}

		return array('items' => self::take($ctx, $candidates));
	}

	public static function sectionEvents(array &$ctx)
	{
		global $smcFunc, $modSettings, $scripturl, $txt;

		if (empty($modSettings['cal_enabled']) || !self::generalPermission($ctx['member'], 'calendar_view'))
			return array();

		$days = max(7, (int) round(self::periodSeconds($ctx['freq']) / 86400));
		$lines = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_event, title, start_date, id_board, id_topic
			FROM {db_prefix}calendar
			WHERE start_date >= {date:today}
				AND start_date <= {date:until}
			ORDER BY start_date
			LIMIT 20',
			array('today' => gmdate('Y-m-d'), 'until' => gmdate('Y-m-d', time() + $days * 86400))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!empty($row['id_board']) && !isset($ctx['board_set'][$row['id_board']]))
				continue;
			if (count($lines) >= $ctx['max'])
				break;

			censorText($row['title']);
			$href = !empty($row['id_topic']) ? $scripturl . '?topic=' . $row['id_topic'] . '.0' : $scripturl . '?action=calendar;event=' . $row['id_event'];
			$lines[] = array('text' => $row['title'], 'href' => $href, 'meta' => self::formatDate(strtotime($row['start_date'] . ' 12:00:00 UTC'), $ctx['member']));
		}
		$smcFunc['db_free_result']($request);

		return array('lines' => $lines, 'intro' => sprintf($txt['cd_intro_events'], $days));
	}

	public static function sectionNewMembers(array &$ctx)
	{
		global $smcFunc, $scripturl, $txt;

		if (!self::generalPermission($ctx['member'], 'view_mlist'))
			return array();

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE date_registered >= {int:since}
				AND is_activated = {int:active}
				AND date_registered <= {int:until}',
			array('since' => $ctx['since'], 'active' => 1, 'until' => $ctx['until'])
		);
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($total))
			return array();

		$lines = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE date_registered >= {int:since}
				AND is_activated = {int:active}
				AND date_registered <= {int:until}
			ORDER BY date_registered DESC
			LIMIT 6',
			array('since' => $ctx['since'], 'active' => 1, 'until' => $ctx['until'])
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$lines[] = array('text' => $row['real_name'], 'href' => $scripturl . '?action=profile;u=' . $row['id_member'], 'meta' => '');
		$smcFunc['db_free_result']($request);

		return array('lines' => $lines, 'intro' => sprintf($txt['cd_intro_newmembers'], comma_format($total)), 'inline' => true);
	}

	public static function sectionStats(array &$ctx)
	{
		global $smcFunc, $txt;

		$minMsg = self::msgIdSince($ctx['since']);
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS posts, SUM(CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END) AS topics
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE m.id_msg >= {int:min_msg}
				AND m.approved = {int:approved}
				AND m.poster_time <= {int:until}
				AND m.id_board IN ({array_int:boards})',
			array('min_msg' => $minMsg, 'approved' => 1, 'until' => $ctx['until'], 'boards' => empty($ctx['boards']) ? array(0) : $ctx['boards'])
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row['posts']))
			return array();

		return array('lines' => array(
			array('text' => sprintf($txt['cd_stats_line'], comma_format((int) $row['topics']), comma_format((int) $row['posts'])), 'href' => '', 'meta' => ''),
		));
	}

	private static function attachExcerpts(array &$sections)
	{
		global $smcFunc;

		$msgs = array();
		foreach ($sections as $s)
			foreach ($s['items'] as $item)
				$msgs[] = $item['id_first_msg'];

		if (empty($msgs))
			return;

		$len = max(0, (int) self::setting('cd_excerpt_length', 160));
		$bodies = array();
		if ($len > 0)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_msg, body
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:msgs})',
				array('msgs' => $msgs)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$bodies[(int) $row['id_msg']] = self::plainExcerpt($row['body'], $len);
			$smcFunc['db_free_result']($request);
		}

		foreach ($sections as &$s)
			foreach ($s['items'] as &$item)
			{
				censorText($item['subject']);
				$item['excerpt'] = isset($bodies[$item['id_first_msg']]) ? $bodies[$item['id_first_msg']] : '';
			}
	}

	/**
	 * BBC-free, HTML-safe excerpt. Avoids parse_bbc() so it is safe in scheduled-task context.
	 */
	public static function plainExcerpt($body, $len)
	{
		global $smcFunc;

		$body = preg_replace('~\[(quote|code|spoiler|hide)[^\]]*\].*?\[/\1\]~is', ' ', $body);
		$body = preg_replace('~\[(img|attach|youtube|video|audio)[^\]]*\].*?\[/\1\]~is', ' ', $body);
		$body = preg_replace('~\[/?[a-z\*][^\]]*\]~i', '', $body);
		$body = str_replace(array('<br>', '<br />', '&nbsp;'), ' ', $body);
		$body = trim(preg_replace('~\s+~u', ' ', strip_tags($body)));
		$body = un_htmlspecialchars($body);
		censorText($body);

		if ($smcFunc['strlen']($body) > $len)
			$body = rtrim($smcFunc['substr']($body, 0, $len - 1)) . '…';

		return $smcFunc['htmlspecialchars']($body);
	}

	public static function formatDate($time, array $m)
	{
		global $modSettings;

		try
		{
			$zone = new DateTimeZone(!empty($m['timezone']) ? $m['timezone'] : (!empty($modSettings['default_timezone']) ? $modSettings['default_timezone'] : 'UTC'));
		}
		catch (Exception $e)
		{
			$zone = new DateTimeZone('UTC');
		}
		$d = new DateTime('@' . (int) $time);
		$d->setTimezone($zone);

		return $d->format('j M Y');
	}

	/* ------------------------------------------------------------------
	 * Storage for the on-forum dashboard and repeat suppression
	 * ------------------------------------------------------------------ */

	public static function storeItems($id_member, array $digest)
	{
		global $smcFunc;

		$history = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, times_shown
			FROM {db_prefix}digest_items
			WHERE id_member = {int:member}
				AND id_topic > {int:zero}',
			array('member' => (int) $id_member, 'zero' => 0)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$history[(int) $row['id_topic']] = (int) $row['times_shown'];
		$smcFunc['db_free_result']($request);

		$now = time();
		$rows = array();
		foreach ($digest['sections'] as $sid => $s)
			foreach ($s['items'] as $item)
				$rows[] = array(
					(int) $id_member, $item['id_topic'], substr($sid, 0, 20), $smcFunc['substr'](un_htmlspecialchars($item['reason']), 0, 120),
					$item['score'], $now, (isset($history[$item['id_topic']]) ? $history[$item['id_topic']] : 0) + 1,
					$item['id_last_msg'], 0, $item['badge'],
				);

		if (empty($rows))
		{
			// Topic id 0 cannot refer to a real SMF topic. A per-member marker
			// records an empty refresh while preserving historical real-topic rows.
			// It participates in MAX(batch), but never in the topic JOIN below.
			$rows[] = array((int) $id_member, 0, '__empty__', '', 0, $now, 0, 0, 1, '');
		}

		$smcFunc['db_insert']('replace',
			'{db_prefix}digest_items',
			array(
				'id_member' => 'int', 'id_topic' => 'int', 'section' => 'string-20', 'reason' => 'string-120', 'score' => 'int',
				'batch' => 'int', 'times_shown' => 'int', 'last_msg_shown' => 'int', 'is_read' => 'int', 'badge' => 'string-30',
			),
			$rows,
			array('id_member', 'id_topic')
		);

		return $now;
	}

	/**
	 * Load the latest stored batch, re-checking access, approval and exclusions.
	 */
	public static function loadStoredBatch(array $m, $prefs = null)
	{
		global $smcFunc, $txt;

		$request = $smcFunc['db_query']('', '
			SELECT MAX(batch)
			FROM {db_prefix}digest_items
			WHERE id_member = {int:member}',
			array('member' => $m['id_member'])
		);
		list ($batch) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($batch))
			return array('batch' => 0, 'sections' => array(), 'count' => 0, 'unread' => 0);

		$allowed = self::selectableSections($m);
		if ($prefs !== null && !empty($prefs['sections']))
			$allowed = array_values(array_intersect($allowed, $prefs['sections']));
		if (empty($allowed))
			return array('batch' => (int) $batch, 'sections' => array(), 'count' => 0, 'unread' => 0);
		$allowed = array_flip($allowed);

		$boards = self::visibleBoards($m);
		$excluded = self::globalExclusions();
		$rows = array();
		$request = $smcFunc['db_query']('', '
			SELECT di.id_topic, di.section, di.reason, di.score, di.is_read, di.badge,
				t.id_board, t.id_poll, t.num_replies, t.num_views, t.id_member_started, t.id_first_msg, t.id_last_msg,
				mf.subject, ml.poster_time AS last_time, COALESCE(mem.real_name, mf.poster_name) AS starter_name
			FROM {db_prefix}digest_items AS di
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = di.id_topic AND t.approved = {int:approved})
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
			WHERE di.id_member = {int:member}
				AND di.batch = {int:batch}
				AND t.id_board IN ({array_int:boards})
			ORDER BY di.score DESC',
			array('member' => $m['id_member'], 'batch' => (int) $batch, 'approved' => 1, 'boards' => empty($boards) ? array(0) : $boards)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (isset($allowed[$row['section']]) && !isset($excluded[(int) $row['id_topic']]))
				$rows[] = $row;
		$smcFunc['db_free_result']($request);

		$sections = array();
		$unread = 0;
		$order = array_flip(self::$allSections);
		foreach ($rows as $row)
		{
			$sid = $row['section'];
			if (!isset($sections[$sid]))
				$sections[$sid] = array('id' => $sid, 'title' => isset($txt['cd_section_' . $sid]) ? $txt['cd_section_' . $sid] : $sid, 'items' => array(), 'lines' => array());

			$item = self::makeItem($row, $smcFunc['htmlspecialchars']($row['reason']), $row['score'], $row['badge']);
			$item['is_read'] = !empty($row['is_read']);
			$unread += $item['is_read'] ? 0 : 1;
			$sections[$sid]['items'][] = $item;
		}
		uksort($sections, function ($a, $b) use ($order) {
			return (isset($order[$a]) ? $order[$a] : 99) <=> (isset($order[$b]) ? $order[$b] : 99);
		});
		self::attachExcerpts($sections);

		return array('batch' => (int) $batch, 'sections' => $sections, 'count' => count($rows), 'unread' => $unread);
	}

	public static function markRead($id_member, $id_topic = 0)
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}digest_items
			SET is_read = {int:read}
			WHERE id_member = {int:member}' . ($id_topic ? '
				AND id_topic = {int:topic}' : ''),
			array('read' => 1, 'member' => (int) $id_member, 'topic' => (int) $id_topic)
		);
	}

	/* ------------------------------------------------------------------
	 * Email
	 * ------------------------------------------------------------------ */

	public static function renderEmail(array $m, array $prefs, array $digest, $isTest = false)
	{
		global $txt, $mbname, $scripturl, $smcFunc;

		$freqKey = array(1 => 'daily', 2 => 'weekly', 3 => 'monthly');
		$freq = isset($freqKey[$prefs['frequency']]) ? $freqKey[$prefs['frequency']] : 'weekly';
		$subject = sprintf($txt['cd_email_subject_' . $freq], un_htmlspecialchars($mbname));

		$accent = self::setting('cd_email_accent', '#3e6a8c');
		if (!preg_match('~^#[0-9a-fA-F]{3,6}$~', $accent))
			$accent = '#3e6a8c';

		$esc = array(__CLASS__, 'escape');
		$name = $esc($m['real_name']);
		$forum = $esc($mbname);
		$unsub = $scripturl . '?action=digest;sa=unsubscribe;u=' . $m['id_member'] . ';t=' . $prefs['token'];
		$prefsUrl = $scripturl . '?action=profile;area=digest;u=' . $m['id_member'];

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>' . $esc($subject) . '</title></head>
<body style="margin:0;padding:0;background:#eef1f4;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#333;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f4;"><tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
<tr><td style="background:' . $accent . ';padding:22px 28px;color:#ffffff;">
	<div style="font-size:13px;opacity:.85;">' . $forum . '</div>
	<div style="font-size:22px;font-weight:bold;margin-top:4px;">' . $esc($txt['cd_email_heading_' . $freq]) . '</div>
</td></tr>
<tr><td style="padding:22px 28px 6px;font-size:15px;line-height:1.5;">' . sprintf($txt['cd_email_greeting'], $name) . '<br>'
			. sprintf($txt['cd_email_intro'], self::formatDate($digest['since'], $m), self::formatDate($digest['until'], $m)) . '</td></tr>';

		foreach ($digest['sections'] as $s)
		{
			$html .= '
<tr><td style="padding:18px 28px 4px;">
	<div style="font-size:12px;font-weight:bold;letter-spacing:.06em;text-transform:uppercase;color:' . $accent . ';border-bottom:2px solid #e3e8ee;padding-bottom:6px;">' . $esc($s['title']) . '</div>'
				. (!empty($s['intro']) ? '<div style="font-size:13px;color:#666;padding-top:6px;">' . $esc($s['intro']) . '</div>' : '') . '
</td></tr>';

			foreach ($s['items'] as $item)
			{
				$html .= '
<tr><td style="padding:10px 28px;">
	<a href="' . self::safeUrl($item['new_href']) . '" style="font-size:16px;font-weight:bold;color:#1f3b52;text-decoration:none;">' . $esc($item['subject']) . '</a>'
					. (!empty($item['badge']) ? ' <span style="font-size:11px;background:#fff1cc;color:#7a5a00;border-radius:3px;padding:1px 6px;">' . $esc($item['badge']) . '</span>' : '') . '
	<div style="font-size:12px;color:#888;margin-top:2px;">' . sprintf($txt['cd_meta_line'], $esc($item['board']), $esc($item['starter']), comma_format($item['replies'])) . '</div>'
					. (!empty($item['excerpt']) ? '<div style="font-size:14px;color:#444;margin-top:6px;line-height:1.45;">' . $item['excerpt'] . '</div>' : '')
					. (!empty($item['reason']) ? '<div style="font-size:12px;color:' . $accent . ';margin-top:5px;">' . $esc($item['reason']) . '</div>' : '') . '
</td></tr>';
			}

			if (!empty($s['lines']))
			{
				$html .= '<tr><td style="padding:6px 28px 4px;font-size:14px;line-height:1.7;">';
				$parts = array();
				foreach ($s['lines'] as $line)
				{
					$text = !empty($line['href']) && self::safeUrl($line['href']) !== '' ? '<a href="' . self::safeUrl($line['href']) . '" style="color:#1f3b52;">' . $esc(isset($line['text']) ? $line['text'] : '') . '</a>' : $esc(isset($line['text']) ? $line['text'] : '');
					$parts[] = $text . (!empty($line['meta']) ? ' <span style="color:#888;font-size:12px;">' . $esc($line['meta']) . '</span>' : '');
				}
				$html .= !empty($s['inline']) ? implode(', ', $parts) : implode('<br>', $parts);
				$html .= '</td></tr>';
			}
		}

		$html .= '
<tr><td style="padding:22px 28px;" align="center">
	<a href="' . self::safeUrl($scripturl) . '?action=digest" style="display:inline-block;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:bold;padding:11px 22px;border-radius:5px;">' . $txt['cd_email_view_online'] . '</a>
</td></tr>
<tr><td style="background:#f6f8fa;padding:16px 28px;font-size:12px;color:#888;line-height:1.6;">
	' . sprintf($txt['cd_email_footer'], $forum) . '<br>
	<a href="' . self::safeUrl($prefsUrl) . '" style="color:#666;">' . self::escape($txt['cd_email_manage']) . '</a>' . (!$isTest ? ' &middot;
	<a href="' . self::safeUrl($unsub) . '" style="color:#666;">' . self::escape($txt['cd_email_unsubscribe']) . '</a>' : '') . '
</td></tr>
</table></td></tr></table></body></html>';

		return array($subject, $html);
	}

	public static function log($id_member, $channel, $status, $items, $message = '')
	{
		global $smcFunc;

		$smcFunc['db_insert']('insert',
			'{db_prefix}digest_log',
			array('id_member' => 'int', 'log_time' => 'int', 'channel' => 'string-10', 'status' => 'int', 'items' => 'int', 'message' => 'string-255'),
			array((int) $id_member, time(), $channel, (int) $status, (int) $items, $smcFunc['substr']($message, 0, 255)),
			array('id_log')
		);
	}

	public static function emailsSentSince($time)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}digest_log
			WHERE log_time >= {int:since}
				AND channel = {string:email}
				AND status IN ({array_int:ok})',
			array('since' => (int) $time, 'email' => 'email', 'ok' => array(self::LOG_SENT, self::LOG_TEST))
		);
		list ($n) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $n;
	}

	/**
	 * Send one digest email now. Returns [bool ok, string message].
	 */
	public static function sendEmail(array $m, array $prefs, array $digest, $isTest = false)
	{
		global $sourcedir;

		require_once($sourcedir . '/Subs-Post.php');

		if (empty($m['email_address']))
		{
			self::log($m['id_member'], 'email', self::LOG_FAILED, $digest['count'], 'No email address');
			return array(false, 'No email address');
		}

		if (empty($prefs['token']))
		{
			if ($isTest)
				$prefs['token'] = bin2hex(random_bytes(16));
			else
				$prefs = self::savePrefs($m['id_member'], $prefs);
		}

		list ($subject, $html) = self::renderEmail($m, $prefs, $digest, $isTest);
		$ok = sendmail($m['email_address'], $subject, $html, null, null, true, 3);

		$msg = $ok ? ($isTest ? 'Test delivery' : '') : 'sendmail() returned false';
		self::log($m['id_member'], 'email', $ok ? ($isTest ? self::LOG_TEST : self::LOG_SENT) : self::LOG_FAILED, $digest['count'], $msg);

		return array($ok, $msg);
	}

	/**
	 * Raise a core SMF alert that the on-forum digest is ready, honouring the member's alert preferences.
	 */
	public static function sendAlert($id_member, $count)
	{
		global $smcFunc, $sourcedir;

		if (!self::setting('cd_alert_on_ready', 1) || $count < 1)
			return;

		require_once($sourcedir . '/Subs-Notify.php');
		$prefs = getNotifyPrefs((int) $id_member, 'digest_ready', true);
		if (empty($prefs[$id_member]['digest_ready']) || !($prefs[$id_member]['digest_ready'] & 0x01))
			return;

		// Replace any unread "digest ready" alert rather than stacking them.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}user_alerts
			WHERE id_member = {int:member}
				AND content_type = {string:type}
				AND is_read = {int:unread}',
			array('member' => (int) $id_member, 'type' => 'digest', 'unread' => 0)
		);

		$smcFunc['db_insert']('insert',
			'{db_prefix}user_alerts',
			array(
				'alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string',
				'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string',
			),
			array(time(), (int) $id_member, 0, '', 'digest', 0, 'ready', 0, $smcFunc['json_encode'](array('items' => (int) $count))),
			array('id_alert')
		);

		// Recount rather than increment, since we may have deleted an unread one.
		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) FROM {db_prefix}user_alerts WHERE id_member = {int:member} AND is_read = {int:unread}',
			array('member' => (int) $id_member, 'unread' => 0)
		);
		list ($unread) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		updateMemberData((int) $id_member, array('alerts' => (int) $unread));
	}

	/* ------------------------------------------------------------------
	 * Scheduled task (hourly)
	 * ------------------------------------------------------------------ */

	/** Queue a fixed digest period so email-only and dual-delivery members are not
	 * silently dropped when the hourly cap is reached or sendmail fails. */
	private static function queueEmail($id_member, $since, $until, $scheduleSlot)
	{
		global $smcFunc;

		$smcFunc['db_insert']('ignore',
			'{db_prefix}digest_email_queue',
			array('id_member' => 'int', 'since_time' => 'int', 'schedule_slot' => 'int', 'until_time' => 'int',
				'next_attempt' => 'int', 'locked_until' => 'int', 'attempts' => 'int'),
			array((int) $id_member, (int) $since, (int) $scheduleSlot, (int) $until, time(), 0, 0),
			array('id_queue')
		);
	}

	/** Process outstanding email jobs with a short atomic claim to prevent two
	 * scheduled-task runners from delivering the same item simultaneously. */
	private static function processEmailQueue($limit, &$sentToday, $cap, $language)
	{
		global $smcFunc;

		$remaining = $cap > 0 ? max(0, $cap - $sentToday) : $limit;
		$limit = min(max(0, (int) $limit), $remaining);
		if (!$limit)
			return;

		$now = time();
		$jobs = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_queue, id_member, since_time, until_time, attempts
			FROM {db_prefix}digest_email_queue
			WHERE next_attempt <= {int:now}
				AND locked_until <= {int:now}
			ORDER BY id_queue
			LIMIT {int:limit}',
			array('now' => $now, 'limit' => $limit)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$jobs[] = $row;
		$smcFunc['db_free_result']($request);

		$members = self::loadMembers(array_unique(array_map(function ($row) { return (int) $row['id_member']; }, $jobs)));
		foreach ($jobs as $job)
		{
			$id = (int) $job['id_member'];
			$queueId = (int) $job['id_queue'];
			// Claim with a condition, never just SELECT then send.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}digest_email_queue
				SET locked_until = {int:lock}
				WHERE id_queue = {int:queue} AND next_attempt <= {int:now}
					AND locked_until <= {int:now}',
				array('lock' => time() + 1800, 'queue' => $queueId, 'now' => time())
			);
			if (empty($smcFunc['db_affected_rows']) || $smcFunc['db_affected_rows']() !== 1)
				continue;

			$m = isset($members[$id]) ? $members[$id] : null;
			$prefs = $m ? self::loadPrefs($id) : null;
			// Honor unsubscribes and deactivated accounts even for already queued jobs.
			if (!$m || (int) $m['is_activated'] !== 1 || !$prefs || (int) $prefs['frequency'] === self::FREQ_OFF
				|| !in_array($prefs['delivery'], array(self::DELIVER_EMAIL, self::DELIVER_BOTH)))
			{
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_email_queue WHERE id_queue = {int:queue}', array('queue' => $queueId));
				continue;
			}

			loadLanguage('CommunityDigest', !empty($m['lngfile']) ? $m['lngfile'] : $language, false, true);
			// Always rebuild against current access, rather than sending a stale snapshot
			// that could contain a now-private topic.
			$digest = self::build($m, $prefs, (int) $job['since_time'], null, (int) $job['until_time']);
			if (!$digest['count'] && self::setting('cd_skip_empty', 1))
			{
				self::log($id, 'email', self::LOG_SKIPPED, 0, 'No visible items for queued digest');
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_email_queue WHERE id_queue = {int:queue}', array('queue' => $queueId));
				continue;
			}

			list ($ok) = self::sendEmail($m, $prefs, $digest);
			if ($ok)
			{
				$sentToday++;
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_email_queue WHERE id_queue = {int:queue}', array('queue' => $queueId));
			}
			else
			{
				$attempts = (int) $job['attempts'] + 1;
				if ($attempts >= 8)
				{
					self::log($id, 'email', self::LOG_FAILED, 0, 'Retry limit reached');
					$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_email_queue WHERE id_queue = {int:queue}', array('queue' => $queueId));
				}
				else
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}digest_email_queue
						SET attempts = {int:attempts}, next_attempt = {int:next}, locked_until = {int:unlocked}
						WHERE id_queue = {int:queue}',
						array('attempts' => $attempts, 'next' => time() + min(86400, 300 * (1 << min(7, $attempts))),
							'unlocked' => 0, 'queue' => $queueId)
					);
			}
		}
		loadLanguage('CommunityDigest', $language, false, true);
	}

	/** Hourly task: produce on-forum digests independently of email limits,
	 * and queue email requests for retryable, cap-aware processing. */
	public static function scheduledTask()
	{
		global $smcFunc, $sourcedir, $language, $modSettings;

		if (empty($modSettings['cd_enabled']))
			return true;

		require_once(__DIR__ . '/CommunityDigest-Integrations.php');
		require_once($sourcedir . '/Subs-Post.php');
		loadEssentialThemeData();

		$now = time();
		$batch = max(1, min(1000, (int) self::setting('cd_batch_size', 50)));
		$cap = max(0, (int) self::setting('cd_daily_email_cap', 500));
		$sentToday = $cap > 0 ? self::emailsSentSince(strtotime('today', $now)) : 0;
		$allowed = self::allowedFrequencies();
		$rows = array();

		$request = $smcFunc['db_query']('', '
			SELECT id_member, frequency, delivery, sections, boards, keywords, weekly_day, send_hour, last_sent, next_send, token
			FROM {db_prefix}digest_prefs
			WHERE frequency > {int:off}
				AND next_send <= {int:now}
			ORDER BY next_send
			LIMIT {int:batch}',
			array('off' => self::FREQ_OFF, 'now' => $now, 'batch' => $batch)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$rows[(int) $row['id_member']] = self::decodePrefsRow($row);
		$smcFunc['db_free_result']($request);

		$members = self::loadMembers(array_keys($rows));
		foreach ($rows as $id => $prefs)
		{
			$m = isset($members[$id]) ? $members[$id] : null;
			if (!$m)
			{
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_prefs WHERE id_member = {int:id}', array('id' => $id));
				continue;
			}

			$next = self::computeNextSend($prefs['frequency'], $prefs['send_hour'], $prefs['weekly_day'], $m['timezone'], $now);
			if ((int) $m['is_activated'] !== 1 || !in_array($prefs['frequency'], $allowed))
			{
				self::reschedule($id, $prefs['last_sent'], $next);
				continue;
			}

			$period = self::periodSeconds($prefs['frequency']);
			$since = $prefs['last_sent'] > 0 ? max($prefs['last_sent'], $now - 2 * $period) : $now - $period;
			$wantsEmail = in_array($prefs['delivery'], array(self::DELIVER_EMAIL, self::DELIVER_BOTH));
			$wantsOnsite = in_array($prefs['delivery'], array(self::DELIVER_ONSITE, self::DELIVER_BOTH));

			// Store the email job before advancing the schedule; the daily cap
			// must never block the on-forum channel for dual-delivery members.
			if ($wantsEmail)
				self::queueEmail($id, $since, $now, (int) $prefs['next_send']);

			if ($wantsOnsite)
			{
				loadLanguage('CommunityDigest', !empty($m['lngfile']) ? $m['lngfile'] : $language, false, true);
				$digest = self::build($m, $prefs, $since);
				// Empty digests must also replace the currently displayed batch;
				// otherwise users keep seeing the previous period's recommendations.
				self::storeItems($id, $digest);
				if ($digest['count'] || !self::setting('cd_skip_empty', 1))
				{
					self::sendAlert($id, $digest['count']);
					self::log($id, 'onsite', self::LOG_SENT, $digest['count']);
				}
				else
					self::log($id, 'onsite', self::LOG_SKIPPED, 0, 'Nothing new to recommend');
			}
			self::reschedule($id, $now, $next);
		}

		self::processEmailQueue($batch, $sentToday, $cap, $language);
		self::prune();
		loadLanguage('CommunityDigest', $language, false, true);
		return true;
	}

	private static function reschedule($id_member, $last, $next)
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}digest_prefs
			SET last_sent = {int:last}, next_send = {int:next}
			WHERE id_member = {int:member}',
			array('last' => (int) $last, 'next' => (int) $next, 'member' => (int) $id_member)
		);
	}

	public static function prune()
	{
		global $smcFunc;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}digest_items
			WHERE batch < {int:cutoff}',
			array('cutoff' => time() - 90 * 86400)
		);

		$days = (int) self::setting('cd_log_days', 30);
		if ($days > 0)
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}digest_log
				WHERE log_time < {int:cutoff}',
				array('cutoff' => time() - $days * 86400)
			);
	}
}

/**
 * SMF 2.1 scheduled_tasks.callable has limited space. This short, namespaced
 * wrapper keeps SMF's source-file-prefixed scheduled task callable compact,
 * while preserving the full engine method and its return value.
 *
 * @return bool Whether the scheduled task completed.
 */
function cd_scheduled_task()
{
	return CommunityDigestEngine::scheduledTask();
}
