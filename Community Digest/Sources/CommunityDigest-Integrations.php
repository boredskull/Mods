<?php
/**
 * Community Digest - optional integrations with companion mods.
 *
 * Every integration is optional and fails safe: if the companion mod is not
 * installed, its admin toggle is off, or its tables are not found, the section
 * is skipped and the digest keeps working on stock SMF.
 *
 * Companion mods can also register themselves instead, via the
 * integrate_community_digest_sections hook (see README.md).
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

class CommunityDigestIntegrations
{
	/**
	 * Table and column names used by each adapter. Prefix-less.
	 * Adjust here if your builds of the companion mods use different names.
	 */
	public static $map = array(
		// Verified against Questions & Answers 1.0.1.
		'qa' => array(
			'setting' => 'qa_enabled',
			'source' => 'QuestionsAnswers.php',
			'table' => 'qa_topics',
			'topic' => 'id_topic',
			'solved' => 'solved',
			'scores' => 'qa_scores',
		),
		'clubs' => array(
			'setting' => 'clubs_enabled',
			'source' => 'Clubs.php',
			'clubs' => 'clubs',
			'boards' => 'club_boards',
			'members' => 'club_members',
			'club_id' => 'id_club',
			'club_name' => 'name',
			'club_board' => 'id_board',
			'member_id' => 'id_member',
			'member_role' => 'role',
		),
		'activity' => array(
			'table' => 'activity_follows',
			'member' => 'id_member',
			'followed' => 'id_followed',
		),
		// Verified against Ranked Polls 1.0.1.
		'rankedpolls' => array(
			'setting' => 'rp_enabled',
			'source' => 'RankedPolls.php',
			'table' => 'rp_polls',
			'poll' => 'id_poll',
		),
		'profilewall' => array(
			'setting' => 'pw_enabled',
			'source' => 'ProfileWall.php',
			'table' => 'pw_comments',
			'owner' => 'id_profile',
			'poster' => 'id_poster',
			'time' => 'created',
			'approved' => 'approved',
		),
	);

	/** Human names, for the admin status display. */
	public static $names = array(
		'activity' => 'Activity Stream',
		'qa' => 'Questions & Answers',
		'clubs' => 'Clubs',
		'rankedpolls' => 'Ranked Polls',
		'profilewall' => 'Profile Wall',
	);

	private static $tableCache = null;
	private static $activitySource = false;
	private static $activityInstalled = null;

	/** Fetch prefixed table names once, without assuming a particular schema. */
	private static function tables()
	{
		global $smcFunc, $db_prefix;

		if (self::$tableCache === null)
		{
			db_extend('packages');
			$prefix = trim(preg_replace('~^.*\.~', '', $db_prefix), '`');
			$tables = $smcFunc['db_list_tables'](false, $prefix . '%');
			self::$tableCache = array();
			foreach ((array) $tables as $table)
			{
				$unqualified = trim(preg_replace('~^.*\.~', '', $table), '`');
				if (strpos($unqualified, $prefix) === 0)
					self::$tableCache[strtolower(substr($unqualified, strlen($prefix)))] = true;
			}
		}

		return self::$tableCache;
	}

	public static function tableExists($name)
	{
		$tables = self::tables();
		return isset($tables[strtolower($name)]);
	}

	/**
	 * Determine an Activity Stream follow table using its actual column names.
	 * The package has used more than one possible naming convention in the wild.
	 * Only recognized, verified identifier pairs are ever interpolated into SQL.
	 */
	private static function activityFollowSource()
	{
		global $smcFunc;

		if (self::$activitySource !== false)
			return self::$activitySource;

		self::$activitySource = null;
		$tables = self::tables();
		if (!isset($smcFunc['db_list_columns']))
			return null;
		$preferred = array('activity_follows', 'activity_stream_follows', 'activitystream_follows', 'as_follows', 'as_following', 'stream_follows');
		$candidates = array();
		foreach ($preferred as $table)
			if (isset($tables[$table]))
				$candidates[] = $table;
		foreach ($tables as $table => $exists)
			if (preg_match('~^(?:activity(?:_stream|stream)?|as|stream)_(?:member_)?(?:follows?|following|followers)$~', $table) && !in_array($table, $candidates, true))
				$candidates[] = $table;

		// These lists are deliberately conservative: never invent column names
		// for a query or silently reverse follower and followed identities.
		$fromNames = array('id_member', 'id_follower', 'member_id', 'follower_id', 'id_member_from', 'id_user');
		$toNames = array('id_followed', 'id_following', 'id_member_followed', 'followed_id', 'following_id', 'id_member_to', 'id_target', 'target_id');
		foreach ($candidates as $table)
		{
			$raw = $smcFunc['db_list_columns']('{db_prefix}' . $table);
			$columns = array();
			foreach ((array) $raw as $key => $value)
			{
				$name = is_array($value) ? (isset($value['name']) ? $value['name'] : '') : (is_string($value) ? $value : $key);
				if (is_string($name) && preg_match('~^[a-z_][a-z0-9_]*$~i', $name))
					$columns[strtolower($name)] = true;
			}
			$from = null;
			$to = null;
			foreach ($fromNames as $column)
				if (isset($columns[$column])) { $from = $column; break; }
			foreach ($toNames as $column)
				if (isset($columns[$column])) { $to = $column; break; }
			if ($from !== null && $to !== null && $from !== $to)
			{
				self::$activitySource = array('table' => $table, 'member' => $from, 'followed' => $to);
				break;
			}
		}

		return self::$activitySource;
	}

	/** SMF Package Manager registration is independent of follow-table support. */
	private static function activityPackageInstalled()
	{
		global $smcFunc;

		if (self::$activityInstalled !== null)
			return self::$activityInstalled;

		self::$activityInstalled = false;
		if (!self::tableExists('log_packages'))
			return false;

		$request = self::safeQuery('
			SELECT id_install
			FROM {db_prefix}log_packages
			WHERE install_state = {int:installed}
				AND (
					LOWER(name) LIKE {string:name}
					OR LOWER(package_id) LIKE {string:package}
					OR LOWER(filename) LIKE {string:filename}
				)
			ORDER BY id_install DESC
			LIMIT 1',
			array('installed' => 1, 'name' => '%activity stream%', 'package' => '%activitystream%', 'filename' => '%activitystream%')
		);
		if ($request)
		{
			self::$activityInstalled = (bool) $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
		}
		return self::$activityInstalled;
	}

	/** Did we recognize Activity Stream as installed? */
	public static function detected($key)
	{
		global $sourcedir;

		if (!isset(self::$map[$key]))
			return false;

		if ($key === 'activity')
			return self::activityFollowSource() !== null || self::activityPackageInstalled();

		$m = self::$map[$key];
		// SMF can retain companion-mod data on uninstall. A leftover table
		// is not proof that the mod is currently installed.
		if (!empty($m['source']) && (empty($sourcedir) || !is_file($sourcedir . '/' . $m['source'])))
			return false;
		$table = isset($m['table']) ? $m['table'] : $m['clubs'];
		if (!self::tableExists($table))
			return false;

		return $key !== 'clubs' || (self::tableExists($m['members']) && self::tableExists($m['boards']));
	}

	/** Is the Activity Stream following adapter connected, not merely installed? */
	public static function activityFollowsAvailable()
	{
		return self::activityFollowSource() !== null;
	}

	/** Detected, enabled, and able to safely query the companion mod. */
	public static function active($key)
	{
		global $modSettings;

		if (!isset(self::$map[$key]))
			return false;
		if (!empty(self::$map[$key]['setting']) && empty($modSettings[self::$map[$key]['setting']]))
			return false;
		if ($key === 'activity')
			return CommunityDigestEngine::setting('cd_int_activity', 1) && self::activityFollowsAvailable();

		return CommunityDigestEngine::setting('cd_int_' . $key, 1) && self::detected($key);
	}

	/**
	 * Run an adapter query without letting a schema mismatch raise a fatal DB error.
	 */
	private static function safeQuery($sql, array $params)
	{
		global $smcFunc;

		$params['db_error_skip'] = true;
		$request = $smcFunc['db_query']('', $sql, $params);

		return $request === false ? null : $request;
	}

	/* ------------------------------------------------------------------ */

	/** Members followed through Activity Stream (core buddies are always used too). */
	public static function followedMembers($id_member)
	{
		global $smcFunc;

		if (!self::active('activity'))
			return array();

		$c = self::activityFollowSource();
		if ($c === null)
			return array();
		$out = array();
		$request = self::safeQuery('
			SELECT ' . $c['followed'] . ' AS followed
			FROM {db_prefix}' . $c['table'] . '
			WHERE ' . $c['member'] . ' = {int:member}
			LIMIT 1000',
			array('member' => (int) $id_member)
		);
		if ($request)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$out[] = (int) $row['followed'];
			$smcFunc['db_free_result']($request);
		}

		return $out;
	}

	/** Which of these poll ids are ranked-choice? */
	public static function rankedPolls(array $polls)
	{
		global $smcFunc;

		$polls = array_filter(array_map('intval', $polls));
		if (empty($polls) || !self::active('rankedpolls'))
			return array();

		$c = self::$map['rankedpolls'];
		$out = array();
		$request = self::safeQuery('
			SELECT ' . $c['poll'] . ' AS id_poll
			FROM {db_prefix}' . $c['table'] . '
			WHERE ' . $c['poll'] . ' IN ({array_int:polls})',
			array('polls' => $polls)
		);
		if ($request)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$out[(int) $row['id_poll']] = true;
			$smcFunc['db_free_result']($request);
		}

		return $out;
	}

	/** Poll IDs for which this member has already cast a Ranked Polls ballot. */
	public static function rankedVotedPolls(array $polls, $id_member)
	{
		global $smcFunc;

		$polls = array_filter(array_map('intval', $polls));
		if (empty($polls) || !$id_member || !self::active('rankedpolls'))
			return array();

		// If the companion voting-history table is unexpectedly absent, fail
		// closed for its poll recommendations rather than presenting false picks.
		if (!self::tableExists('rp_voters'))
			return array_fill_keys($polls, true);

		$request = self::safeQuery('
			SELECT DISTINCT id_poll
			FROM {db_prefix}rp_voters
			WHERE id_member = {int:member}
				AND id_poll IN ({array_int:polls})',
			array('member' => (int) $id_member, 'polls' => $polls)
		);
		if (!$request)
			return array_fill_keys($polls, true);

		$voted = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$voted[(int) $row['id_poll']] = true;
		$smcFunc['db_free_result']($request);

		return $voted;
	}

	/** Popular and still-open questions from the Q&A mod. Access is inherited from the board-filtered pool. */
	public static function sectionQuestions(array &$ctx)
	{
		global $smcFunc, $txt;

		if (!self::active('qa') || empty($ctx['pool']))
			return array();

		$c = self::$map['qa'];
		$questions = array();
		$request = self::safeQuery('
			SELECT q.' . $c['topic'] . ' AS id_topic, q.' . $c['solved'] . ' AS solved, COALESCE(s.score, 0) AS score
			FROM {db_prefix}' . $c['table'] . ' AS q
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.' . $c['topic'] . ')
				LEFT JOIN {db_prefix}' . $c['scores'] . ' AS s ON (s.id_msg = t.id_first_msg)
			WHERE q.' . $c['topic'] . ' IN ({array_int:topics})',
			array('topics' => array_keys($ctx['pool']))
		);
		if (!$request)
			return array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$questions[(int) $row['id_topic']] = $row;
		$smcFunc['db_free_result']($request);

		$candidates = array();
		foreach ($questions as $id => $q)
		{
			$t = $ctx['pool'][$id];
			$open = empty($q['solved']);
			$sc = CommunityDigestEngine::scoreTopic($t, $ctx);
			$candidates[$id] = array(
				'topic' => $t,
				'score' => $sc['score'] + (int) $q['score'] * 2 + ($open ? 15 : 0),
				'reason' => $sc['reason'] ?: ($open ? $txt['cd_reason_needs_answer'] : $txt['cd_reason_answered']),
				'badge' => $open ? $txt['cd_badge_open_question'] : $txt['cd_badge_solved'],
			);
		}

		return array('items' => CommunityDigestEngine::take($ctx, $candidates));
	}

	/**
	 * New activity in clubs the member belongs to. Membership is an additional
	 * restriction, never a substitute for checked board visibility.
	 */
	public static function sectionClubs(array &$ctx)
	{
		global $smcFunc, $txt;

		if (!self::active('clubs'))
			return array();

		$c = self::$map['clubs'];
		$clubBoards = array();
		$request = self::safeQuery('
			SELECT cb.id_board AS id_board, c.' . $c['club_name'] . ' AS club_name
			FROM {db_prefix}' . $c['clubs'] . ' AS c
				INNER JOIN {db_prefix}' . $c['boards'] . ' AS cb ON (cb.' . $c['club_id'] . ' = c.' . $c['club_id'] . ')
				INNER JOIN {db_prefix}' . $c['members'] . ' AS cm ON (cm.' . $c['club_id'] . ' = c.' . $c['club_id'] . ')
			WHERE cm.' . $c['member_id'] . ' = {int:member}
				AND cm.' . $c['member_role'] . ' IN ({array_string:roles})
				AND c.status = {string:active}',
			array('member' => $ctx['member']['id_member'], 'roles' => array('owner', 'leader', 'member'), 'active' => 'active')
		);
		if (!$request)
			return array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (!empty($row['id_board']))
				$clubBoards[(int) $row['id_board']] = $row['club_name'];
		$smcFunc['db_free_result']($request);

		if (empty($clubBoards))
			return array();

		$candidates = array();
		$ignore = array_flip($ctx['member']['ignore_users']);
		foreach ($ctx['pool'] as $id => $t)
		{
			if (!isset($clubBoards[$t['id_board']]) || isset($ignore[(int) $t['id_member_started']]))
				continue;
			if (isset($ctx['signals']['read'][$id]) && $ctx['signals']['read'][$id] >= $t['id_last_msg'])
				continue;

			$candidates[$id] = array(
				'topic' => $t,
				'score' => $t['window_posts'] * 3 + $t['last_time'] / 1e9,
				'reason' => sprintf($txt['cd_reason_club'], $clubBoards[$t['id_board']]),
				'badge' => $txt['cd_badge_club'],
			);
		}

		return array('items' => CommunityDigestEngine::take($ctx, $candidates));
	}

	/** "You have N new comments on your profile wall." */
	public static function sectionWall(array &$ctx)
	{
		global $smcFunc, $txt, $scripturl;

		if (!self::active('profilewall'))
			return array();

		$c = self::$map['profilewall'];
		$request = self::safeQuery('
			SELECT COUNT(*) AS c
			FROM {db_prefix}' . $c['table'] . '
			WHERE ' . $c['owner'] . ' = {int:member}
				AND ' . $c['poster'] . ' != {int:member}
				AND ' . $c['time'] . ' >= {int:since}
				AND ' . $c['time'] . ' <= {int:until}
				AND ' . $c['approved'] . ' = {int:approved}',
			array('member' => $ctx['member']['id_member'], 'since' => $ctx['since'], 'until' => $ctx['until'], 'approved' => 1)
		);
		if (!$request)
			return array();
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row['c']))
			return array();

		return array('lines' => array(array(
			'text' => sprintf($txt['cd_wall_line'], comma_format((int) $row['c'])),
			'href' => $scripturl . '?action=profile;u=' . $ctx['member']['id_member'],
			'meta' => '',
		)));
	}
}
