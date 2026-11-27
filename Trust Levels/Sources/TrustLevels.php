<?php

/**
 * Trust Levels for SMF 2.1
 *
 * Members move up levels automatically based on what SMF already records
 * (days registered, posts, topics, likes received, topics read, time online,
 * warnings). Each level can give a membergroup, so SMF permissions follow,
 * and can limit links, images and daily posting.
 *
 * @package TrustLevels
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

class TrustLevels
{
	const VERSION = '1.1.1';
	const MAX_LEVELS = 10;

	public static $req_keys = array('days', 'posts', 'topics', 'likes', 'read', 'online');
	public static $limit_keys = array('links', 'images', 'topics_day', 'replies_day');

	/** Links/BBCode inside these are never counted or changed (e.g. quoting someone else's links). */
	const PROTECTED_RE = '~\[(quote|code|php|nobbc|html)(?=[\s=\]])[^\]]*\](?:(?!\[\1[\s=\]]).)*?\[/\1\]~is';

	/** [img] is matched first so the URL inside it isn't counted as a link. */
	const LINK_RE = '~(\[img(?:[\s=][^\]]*)?\].*?\[/img\])|\[url=([^\]]+)\](.*?)\[/url\]|\[url\](.*?)\[/url\]|\[iurl=([^\]]+)\](.*?)\[/iurl\]|\[iurl\](.*?)\[/iurl\]|\[ftp=([^\]]+)\](.*?)\[/ftp\]|\[ftp\](.*?)\[/ftp\]|(?<![\w/=@.-])((?:https?|ftps?)://|www\.)[^\s<>\[\]"\']+~is';

	const IMG_RE = '~\[img(?:[\s=][^\]]*)?\](.*?)\[/img\]~is';

	protected static $levels = null;
	protected static $own = null;
	protected static $sending = false;
	protected static $page_levels = array();

	/* ------------------------------------------------------------------
	 * Levels
	 * ---------------------------------------------------------------- */

	public static function levels()
	{
		global $modSettings;

		if (self::$levels !== null)
			return self::$levels;

		$levels = !empty($modSettings['tl_levels']) ? json_decode($modSettings['tl_levels'], true) : null;
		if (!is_array($levels) || count($levels) < 2)
			$levels = self::defaultLevels();

		return self::$levels = array_values(array_map(array(__CLASS__, 'normaliseLevel'), array_slice($levels, 0, self::MAX_LEVELS)));
	}

	public static function resetCache()
	{
		self::$levels = null;
		self::$own = null;
	}

	public static function top()
	{
		return count(self::levels()) - 1;
	}

	protected static function limitValue($value)
	{
		return $value === '' || $value === null ? -1 : max(-1, (int) $value);
	}

	public static function normaliseLevel($l)
	{
		$l = is_array($l) ? $l : array();
		$req = isset($l['req']) && is_array($l['req']) ? $l['req'] : array();
		$lim = isset($l['limits']) && is_array($l['limits']) ? $l['limits'] : array();

		$out = array(
			'name' => isset($l['name']) && trim((string) $l['name']) !== '' ? mb_substr(trim((string) $l['name']), 0, 40) : 'Level',
			'description' => isset($l['description']) ? mb_substr((string) $l['description'], 0, 255) : '',
			'color' => isset($l['color']) && preg_match('~^#[0-9a-f]{6}$~i', $l['color']) ? strtolower($l['color']) : '#7a7a7a',
			'group' => isset($l['group']) ? max(0, (int) $l['group']) : 0,
			'manual' => !empty($l['manual']),
			'req' => array(),
			'limits' => array(),
			'pm_subject' => isset($l['pm_subject']) ? mb_substr((string) $l['pm_subject'], 0, 100) : '',
			'pm_body' => isset($l['pm_body']) ? mb_substr((string) $l['pm_body'], 0, 5000) : '',
		);

		foreach (self::$req_keys as $key)
			$out['req'][$key] = isset($req[$key]) ? max(0, (int) $req[$key]) : 0;
		$out['req']['max_warning'] = isset($req['max_warning']) && $req['max_warning'] !== '' ? max(-1, min(100, (int) $req['max_warning'])) : -1;

		foreach (self::$limit_keys as $key)
			$out['limits'][$key] = self::limitValue(isset($lim[$key]) ? $lim[$key] : -1);
		$out['limits']['sig_links'] = !empty($lim['sig_links']);

		return $out;
	}

	/**
	 * Sensible defaults, similar to Discourse's levels.
	 */
	public static function defaultLevels()
	{
		$pm = "Hi {name},\n\nCongratulations! You've reached the [b]{level}[/b] trust level on {forum}.\n\nSee what has changed: {link}";

		return array(
			array(
				'name' => 'New', 'color' => '#8a8f98', 'description' => 'Just joined. Still learning how the forum works.',
				'limits' => array('links' => 0, 'images' => 1, 'topics_day' => 3, 'replies_day' => 10, 'sig_links' => false),
			),
			array(
				'name' => 'Basic', 'color' => '#3d8fb8', 'description' => 'Has read and posted a little.',
				'req' => array('days' => 1, 'posts' => 3, 'read' => 5, 'online' => 15),
				'limits' => array('links' => 3, 'images' => 5, 'topics_day' => 10, 'replies_day' => 50, 'sig_links' => false),
				'pm_subject' => 'You are now a {level} member', 'pm_body' => $pm,
			),
			array(
				'name' => 'Member', 'color' => '#3c8f4e', 'description' => 'A regular part of the community.',
				'req' => array('days' => 14, 'posts' => 20, 'topics' => 1, 'likes' => 5, 'read' => 40, 'online' => 120, 'max_warning' => 20),
				'limits' => array('links' => -1, 'images' => -1, 'topics_day' => -1, 'replies_day' => -1, 'sig_links' => true),
				'pm_subject' => 'You are now a {level}', 'pm_body' => $pm,
			),
			array(
				'name' => 'Regular', 'color' => '#c98a12', 'description' => 'Has been around a long time and contributes a lot.',
				'req' => array('days' => 60, 'posts' => 100, 'topics' => 5, 'likes' => 30, 'read' => 200, 'online' => 600, 'max_warning' => 0),
				'limits' => array('links' => -1, 'images' => -1, 'topics_day' => -1, 'replies_day' => -1, 'sig_links' => true),
				'pm_subject' => 'You are now a {level}', 'pm_body' => $pm,
			),
			array(
				'name' => 'Leader', 'color' => '#9b4dca', 'description' => 'Chosen by the staff.', 'manual' => true,
				'req' => array('max_warning' => 0),
				'limits' => array('links' => -1, 'images' => -1, 'topics_day' => -1, 'replies_day' => -1, 'sig_links' => true),
				'pm_subject' => 'You are now a {level}', 'pm_body' => $pm,
			),
		);
	}

	/* ------------------------------------------------------------------
	 * Statistics
	 * ---------------------------------------------------------------- */

	/**
	 * Everything is read from data SMF already keeps.
	 *
	 * @return array id_member => stats
	 */
	public static function stats(array $ids)
	{
		global $smcFunc;

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$out = array();
		if (empty($ids))
			return $out;

		$now = time();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, date_registered, posts, total_time_logged_in, warning, id_group, additional_groups
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ids})',
			array('ids' => $ids)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$groups = array((int) $row['id_group']);
			foreach (explode(',', (string) $row['additional_groups']) as $g)
				if (trim($g) !== '')
					$groups[] = (int) $g;

			$out[(int) $row['id_member']] = array(
				'name' => $row['real_name'],
				'days' => (int) floor(max(0, $now - (int) $row['date_registered']) / 86400),
				'posts' => (int) $row['posts'],
				'online' => (int) floor((int) $row['total_time_logged_in'] / 60),
				'warning' => (int) $row['warning'],
				'topics' => 0,
				'likes' => 0,
				'read' => 0,
				'last_warning' => 0,
				'groups' => array_values(array_unique($groups)),
			);
		}
		$smcFunc['db_free_result']($request);

		$ids = array_keys($out);
		if (empty($ids))
			return $out;

		$counts = array(
			'topics' => '
				SELECT id_member_started AS id, COUNT(*) AS n
				FROM {db_prefix}topics
				WHERE id_member_started IN ({array_int:ids})
					AND approved = {int:yes}
				GROUP BY id_member_started',
			'likes' => '
				SELECT m.id_member AS id, COUNT(*) AS n
				FROM {db_prefix}user_likes AS ul
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = ul.content_id)
				WHERE ul.content_type = {string:msg}
					AND m.id_member IN ({array_int:ids})
					AND ul.id_member != m.id_member
				GROUP BY m.id_member',
			'read' => '
				SELECT id_member AS id, COUNT(*) AS n
				FROM {db_prefix}log_topics
				WHERE id_member IN ({array_int:ids})
				GROUP BY id_member',
			// When the warning level last went up (for the probation period).
			'last_warning' => '
				SELECT id_recipient AS id, MAX(log_time) AS n
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:warning}
					AND id_recipient IN ({array_int:ids})
					AND counter > {int:zero}
				GROUP BY id_recipient',
		);

		foreach ($counts as $key => $sql)
		{
			$request = $smcFunc['db_query']('', $sql, array('ids' => $ids, 'yes' => 1, 'msg' => 'msg', 'warning' => 'warning', 'zero' => 0));
			while ($row = $smcFunc['db_fetch_assoc']($request))
				if (isset($out[(int) $row['id']]))
					$out[(int) $row['id']][$key] = (int) $row['n'];
			$smcFunc['db_free_result']($request);
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Calculation
	 * ---------------------------------------------------------------- */

	public static function exemptGroups()
	{
		global $modSettings;

		$groups = isset($modSettings['tl_exempt_groups']) ? json_decode($modSettings['tl_exempt_groups'], true) : null;
		$groups = is_array($groups) ? array_map('intval', $groups) : array(2);
		$groups[] = 1;

		return array_values(array_unique($groups));
	}

	public static function isExempt(array $groups)
	{
		return (bool) array_intersect(array_map('intval', $groups), self::exemptGroups());
	}

	public static function meets(array $req, array $s)
	{
		foreach (self::$req_keys as $key)
			if ((int) $s[$key] < (int) $req[$key])
				return false;

		return true;
	}

	/**
	 * The level a member should have.
	 *
	 * Levels are cumulative: a member must meet every level below too.
	 * Manual levels are never granted automatically. Demotion follows the
	 * tl_demote setting:
	 *   never    - levels only go up
	 *   warnings - levels only go down when the warning limit is exceeded (default)
	 *   always   - recalculated from scratch every time
	 *
	 * @param array $s Stats from stats()
	 * @param int|null $stored Current stored level, or null if none
	 */
	public static function compute(array $s, $stored = null)
	{
		$level = self::computeRaw($s, $stored);

		// Probation: no automatic promotion for a while after a warning.
		if ($stored !== null && $level > (int) $stored && !self::isExempt($s['groups']) && self::probationUntil($s) > 0)
			return (int) $stored;

		return $level;
	}

	/**
	 * When the member's probation ends, or 0 if they aren't on probation.
	 */
	public static function probationUntil(array $s)
	{
		global $modSettings;

		$days = isset($modSettings['tl_probation_days']) ? (int) $modSettings['tl_probation_days'] : 0;
		if ($days <= 0 || empty($s['last_warning']))
			return 0;

		$until = (int) $s['last_warning'] + $days * 86400;

		return $until > time() ? $until : 0;
	}

	/**
	 * The level ignoring probation.
	 */
	public static function computeRaw(array $s, $stored = null)
	{
		global $modSettings;

		$levels = self::levels();
		$top = count($levels) - 1;

		if (self::isExempt($s['groups']))
			return $top;

		// The highest level the member's warning level still allows.
		$warn_cap = $top;
		for ($i = 1; $i <= $top; $i++)
			if ($levels[$i]['req']['max_warning'] >= 0 && $s['warning'] > $levels[$i]['req']['max_warning'])
			{
				$warn_cap = $i - 1;
				break;
			}

		// The highest automatic level reached, ignoring warnings.
		$earned = 0;
		for ($i = 1; $i <= $top; $i++)
		{
			if ($levels[$i]['manual'] || !self::meets($levels[$i]['req'], $s))
				break;
			$earned = $i;
		}

		$computed = min($earned, $warn_cap);

		if ($stored === null)
			return $computed;

		$stored = max(0, min((int) $stored, $top));
		$mode = isset($modSettings['tl_demote']) && in_array($modSettings['tl_demote'], array('never', 'warnings', 'always'), true) ? $modSettings['tl_demote'] : 'warnings';

		// A level at or above a manual level was given by staff, so keep it.
		$manual = false;
		for ($i = 1; $i <= $stored; $i++)
			if ($levels[$i]['manual'])
				$manual = true;

		if ($manual)
			return $mode === 'never' ? $stored : min($stored, max($warn_cap, $computed));

		if ($mode === 'always')
			return $computed;

		if ($mode === 'warnings')
			return min(max($stored, $earned), $warn_cap);

		return max($stored, $computed);
	}

	/**
	 * Recalculate members' levels and save any changes.
	 *
	 * @param array $ids Member IDs
	 * @param bool $force_sync Re-apply level groups even when the level hasn't changed
	 * @return int Number of members whose level changed
	 */
	public static function recalc(array $ids, $force_sync = false)
	{
		global $smcFunc;

		$stats = self::stats($ids);
		if (empty($stats))
			return 0;

		$stored = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, level, locked, held_warning
			FROM {db_prefix}trust_members
			WHERE id_member IN ({array_int:ids})',
			array('ids' => array_keys($stats))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$stored[(int) $row['id_member']] = array('level' => (int) $row['level'], 'locked' => !empty($row['locked']), 'held_warning' => (int) $row['held_warning']);
		$smcFunc['db_free_result']($request);

		$changed = 0;
		$touch = array();

		foreach ($stats as $id => $s)
		{
			$row = isset($stored[$id]) ? $stored[$id] : null;

			if ($row !== null && $row['locked'])
			{
				if ($force_sync)
					self::syncGroups($id, min($row['level'], self::top()), $s['groups']);
				$touch[] = $id;
				continue;
			}

			$old = $row !== null ? $row['level'] : null;
			$new = self::compute($s, $old);

			// A promotion held back by probation: note it in the history once per warning.
			if ($old !== null && $new === $old && $row['held_warning'] !== (int) $s['last_warning'] && ($until = self::probationUntil($s)) > 0)
			{
				$would = self::computeRaw($s, $old);
				if ($would > $old)
				{
					self::logEvent($id, 'held', $old, $would, 0, 'probation:' . $until);
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}trust_members
						SET held_warning = {int:w}
						WHERE id_member = {int:id}',
						array('w' => (int) $s['last_warning'], 'id' => $id)
					);
				}
			}

			if ($old === null || $new !== $old)
			{
				self::setLevel($id, $new, $old, 0, $old === null ? 'initial' : 'auto', false, $s);
				$changed += $old === null ? 0 : 1;
			}
			else
			{
				if ($force_sync)
					self::syncGroups($id, $new, $s['groups']);
				$touch[] = $id;
			}
		}

		if (!empty($touch))
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}trust_members
				SET last_calc = {int:now}
				WHERE id_member IN ({array_int:ids})',
				array('now' => time(), 'ids' => $touch)
			);

		return $changed;
	}

	/**
	 * Save a level change: store it, log it, update groups, send the PM.
	 */
	public static function setLevel($id, $new, $old, $changer = 0, $reason = 'auto', $locked = false, $stats = null)
	{
		global $smcFunc, $user_info;

		$id = (int) $id;
		$new = max(0, min((int) $new, self::top()));

		if ($stats === null)
		{
			$all = self::stats(array($id));
			$stats = isset($all[$id]) ? $all[$id] : null;
			if ($stats === null)
				return;
		}

		$request = $smcFunc['db_query']('', '
			SELECT locked, held_warning
			FROM {db_prefix}trust_members
			WHERE id_member = {int:id}',
			array('id' => $id)
		);
		$prev = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
		$was_locked = !empty($prev['locked']);

		$smcFunc['db_insert']('replace',
			'{db_prefix}trust_members',
			array('id_member' => 'int', 'level' => 'int', 'locked' => 'int', 'updated' => 'int', 'last_calc' => 'int', 'held_warning' => 'int'),
			array($id, $new, $locked ? 1 : 0, time(), time(), $prev ? (int) $prev['held_warning'] : 0),
			array('id_member')
		);

		if ($old !== $new && !($old === null && $new === 0))
			self::logEvent($id, 'level', $old === null ? -1 : (int) $old, $new, $changer, $reason);

		// Locking or unlocking is recorded even when the level stays the same.
		if ($prev && $was_locked !== (bool) $locked)
			self::logEvent($id, $locked ? 'lock' : 'unlock', $new, $new, $changer, in_array($reason, array('auto', 'initial'), true) ? '' : $reason);
		elseif (!$prev && $locked)
			self::logEvent($id, 'lock', $new, $new, $changer, $reason);

		if (!self::isExempt($stats['groups']))
			self::syncGroups($id, $new, $stats['groups']);

		if ($old !== null && $new > $old && $reason !== 'initial')
			self::sendPromotion($id, $stats['name'], $new);

		if (!empty($user_info['id']) && (int) $user_info['id'] === $id)
		{
			self::$own = null;
			unset($_SESSION['tl_level']);
		}
	}

	/**
	 * Add a history entry.
	 *
	 * @param string $action level, lock, unlock or held
	 */
	public static function logEvent($id, $action, $old, $new, $changer = 0, $reason = '')
	{
		global $smcFunc;

		$smcFunc['db_insert']('insert',
			'{db_prefix}trust_log',
			array('id_member' => 'int', 'action' => 'string-12', 'old_level' => 'int', 'new_level' => 'int', 'logged' => 'int', 'id_changer' => 'int', 'reason' => 'string-255'),
			array((int) $id, $action, (int) $old, (int) $new, time(), (int) $changer, mb_substr((string) $reason, 0, 255)),
			array('id_log')
		);
	}

	/**
	 * Level groups this mod gave the member (as opposed to staff).
	 */
	public static function ownedGroups($id)
	{
		global $smcFunc;

		$out = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}trust_groups
			WHERE id_member = {int:id}',
			array('id' => (int) $id)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$out[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		return $out;
	}

	/**
	 * Level groups the member holds that staff assigned, which syncing leaves alone.
	 */
	public static function protectedGroups($id, array $current)
	{
		global $modSettings;

		if (isset($modSettings['tl_protect_groups']) && empty($modSettings['tl_protect_groups']))
			return array();

		$level_groups = array();
		foreach (self::levels() as $l)
			if ($l['group'] > 0)
				$level_groups[] = $l['group'];

		return array_values(array_diff(array_intersect(array_unique($level_groups), $current), self::ownedGroups($id)));
	}

	/**
	 * Put the member in their level's group and take them out of the
	 * other levels' groups. Only additional groups are used.
	 *
	 * Groups are only removed if this mod added them, so a level group
	 * that staff gave a member by hand is kept (unless protection is
	 * turned off in the settings).
	 */
	public static function syncGroups($id, $level, array $current)
	{
		global $sourcedir, $smcFunc, $modSettings;

		$id = (int) $id;
		$current = array_map('intval', $current);
		$protect = !isset($modSettings['tl_protect_groups']) || !empty($modSettings['tl_protect_groups']);

		$levels = self::levels();
		$level_groups = array();
		foreach ($levels as $l)
			if ($l['group'] > 0)
				$level_groups[] = $l['group'];
		$level_groups = array_values(array_unique($level_groups));

		if (empty($level_groups))
			return;

		$target = isset($levels[$level]) ? $levels[$level]['group'] : 0;
		$owned = self::ownedGroups($id);

		// Records for groups the member no longer has (staff removed them).
		$gone = array_values(array_diff($owned, $current));
		if (!empty($gone))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}trust_groups
				WHERE id_member = {int:id}
					AND id_group IN ({array_int:groups})',
				array('id' => $id, 'groups' => $gone)
			);
		$owned = array_values(array_intersect($owned, $current));

		$remove = array_values(array_diff(array_intersect($level_groups, $current), array($target)));
		if ($protect)
			$remove = array_values(array_intersect($remove, $owned));

		require_once($sourcedir . '/Subs-Membergroups.php');

		if (!empty($remove))
		{
			removeMembersFromGroups(array($id), $remove, true);
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}trust_groups
				WHERE id_member = {int:id}
					AND id_group IN ({array_int:groups})',
				array('id' => $id, 'groups' => $remove)
			);
		}

		if ($target > 0 && !in_array($target, $current, true))
		{
			addMembersToGroup(array($id), $target, 'only_additional', true);
			$smcFunc['db_insert']('ignore',
				'{db_prefix}trust_groups',
				array('id_member' => 'int', 'id_group' => 'int', 'added' => 'int'),
				array($id, $target, time()),
				array('id_member', 'id_group')
			);
		}
	}

	/**
	 * integrate_delete_members
	 */
	public static function deleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		foreach (array('trust_members', 'trust_log', 'trust_groups') as $table)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_member IN ({array_int:u})', array('u' => $users));
	}

	protected static function sendPromotion($id, $name, $level)
	{
		global $modSettings, $sourcedir, $smcFunc, $scripturl, $mbname;

		$levels = self::levels();
		$l = $levels[$level];

		if (empty($modSettings['tl_promotion_pm']) || trim($l['pm_subject']) === '' || trim($l['pm_body']) === '')
			return;

		$from_id = !empty($modSettings['tl_pm_from']) ? (int) $modSettings['tl_pm_from'] : 1;
		$request = $smcFunc['db_query']('', '
			SELECT id_member, real_name, member_name
			FROM {db_prefix}members
			WHERE id_member = {int:id}',
			array('id' => $from_id)
		);
		$from = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($from) || (int) $from['id_member'] === (int) $id)
			return;

		$replace = array(
			'{name}' => self::unescape($name),
			'{level}' => $l['name'],
			'{forum}' => $mbname,
			'{link}' => $scripturl . '?action=profile;area=trustlevel;u=' . (int) $id,
		);

		$subject = $smcFunc['htmlspecialchars'](strtr($l['pm_subject'], $replace), ENT_QUOTES);
		$body = $smcFunc['htmlspecialchars'](strtr($l['pm_body'], $replace), ENT_QUOTES);

		require_once($sourcedir . '/Subs-Post.php');

		self::$sending = true;
		preparsecode($body);
		sendpm(
			array('to' => array((int) $id), 'bcc' => array()),
			$subject,
			$body,
			false,
			array('id' => (int) $from['id_member'], 'name' => $from['real_name'], 'username' => $from['member_name'])
		);
		self::$sending = false;
	}

	protected static function unescape($s)
	{
		return function_exists('un_htmlspecialchars') ? un_htmlspecialchars((string) $s) : htmlspecialchars_decode((string) $s, ENT_QUOTES);
	}

	/**
	 * The current member's level (null for guests).
	 */
	public static function myLevel()
	{
		global $user_info, $smcFunc;

		if (!empty($user_info['is_guest']) || empty($user_info['id']))
			return null;

		if (self::$own !== null)
			return self::$own;

		if (self::isExempt($user_info['groups']))
			return self::$own = self::top();

		$level = self::storedLevel($user_info['id']);
		if ($level === null)
		{
			self::recalc(array($user_info['id']));
			$level = self::storedLevel($user_info['id']);
		}

		return self::$own = max(0, min((int) $level, self::top()));
	}

	public static function storedLevel($id)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT level
			FROM {db_prefix}trust_members
			WHERE id_member = {int:id}',
			array('id' => (int) $id)
		);
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return empty($row) ? null : (int) $row[0];
	}

	public static function isExemptUser()
	{
		global $user_info;

		return !empty($user_info['is_admin']) || self::isExempt(isset($user_info['groups']) ? (array) $user_info['groups'] : array());
	}

	/* ------------------------------------------------------------------
	 * Restrictions
	 * ---------------------------------------------------------------- */

	public static function forumHost()
	{
		global $boardurl;

		return preg_replace('~^www\.~', '', strtolower((string) parse_url($boardurl, PHP_URL_HOST)));
	}

	public static function isInternal($url)
	{
		$url = trim(self::unescape($url), " \t\n\"'");
		if (strpos($url, '#') === 0 || strpos($url, '/') === 0 && strpos($url, '//') !== 0)
			return true;
		if (stripos($url, 'www.') === 0)
			$url = 'http://' . $url;

		$host = preg_replace('~^www\.~', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

		return $host !== '' && $host === self::forumHost();
	}

	/** TLDs used when SMF hasn't built its own list yet. */
	const FALLBACK_TLDS = 'com|net|org|info|biz|io|co|me|tv|cc|ws|xyz|top|online|site|shop|store|club|live|app|dev|link|click|uk|us|ca|au|de|fr|es|it|nl|ru|cn|in|br|jp|pl|eu';

	protected static $quoted_bodies = array();

	/**
	 * Put quotes, code and nobbc blocks aside so they aren't counted or changed.
	 *
	 * Code, PHP, nobbc and HTML blocks never show clickable links, so they are
	 * always set aside. A quote is only set aside when it really quotes an
	 * existing post (SMF's link=msg=ID) and every link in it appears in that
	 * post; otherwise "[quote]" would be an easy way around the link limits.
	 */
	public static function protect($text, $verify_quotes = false)
	{
		$text = str_replace(array("\x01", "\x02"), '', (string) $text);
		$store = array();
		$transparent = array();

		for ($i = 0; $i < 50; $i++)
		{
			$new = preg_replace_callback(self::PROTECTED_RE, function ($m) use (&$store, &$transparent, $verify_quotes)
			{
				$store[] = $m[0];
				$index = count($store) - 1;
				if ($verify_quotes && strtolower($m[1]) === 'quote' && !TrustLevels::isGenuineQuote($m[0]))
					$transparent[$index] = true;
				return "\x01" . $index . "\x02";
			}, $text);

			if ($new === null || $new === $text)
				break;
			$text = $new;
		}

		// Put made-up quotes back, so their links are counted like any others.
		for ($i = 0; $i < 60 && !empty($transparent); $i++)
		{
			$before = $text;
			$text = preg_replace_callback('~\x01(\d+)\x02~', function ($m) use ($store, $transparent)
			{
				return isset($transparent[(int) $m[1]]) ? $store[(int) $m[1]] : $m[0];
			}, $text);
			if ($text === $before)
				break;
		}

		return array($text, $store);
	}

	/**
	 * A quote of a real post whose links all appear in that post.
	 */
	public static function isGenuineQuote($block)
	{
		global $smcFunc;

		if (!preg_match('~^\[quote[^\]]*\blink=msg=(\d+)~i', $block, $m))
			return false;
		$id_msg = (int) $m[1];

		if (!array_key_exists($id_msg, self::$quoted_bodies))
		{
			self::$quoted_bodies[$id_msg] = null;
			if (!empty($smcFunc['db_query']))
			{
				$request = $smcFunc['db_query']('', '
					SELECT body
					FROM {db_prefix}messages
					WHERE id_msg = {int:id}
					LIMIT 1',
					array('id' => $id_msg)
				);
				$row = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);
				if (!empty($row))
					self::$quoted_bodies[$id_msg] = strtolower(self::unescape($row['body']));
			}
		}

		$original = self::$quoted_bodies[$id_msg];
		if ($original === null)
			return false;

		// The quote's own content, without the opening and closing tags.
		$inner = preg_replace('~^\[quote[^\]]*\]|\[/quote\]$~i', '', $block);
		$inner = preg_replace('~\x01\d+\x02~', ' ', $inner);

		foreach (self::findUrls($inner) as $url)
			if (strpos($original, strtolower(self::unescape($url))) === false)
				return false;

		return true;
	}

	/**
	 * Every link-like string in some text: [url=...] targets, scheme and www.
	 * links, and bare domains.
	 */
	public static function findUrls($text)
	{
		$urls = array();
		if (preg_match_all('~\[(?:url|iurl|ftp)=([^\]]+)\]~i', $text, $m))
			$urls = array_merge($urls, $m[1]);
		if (preg_match_all('~(?:(?:https?|ftps?)://|www\.)[^\s<>\[\]"\']+~i', $text, $m))
			$urls = array_merge($urls, $m[0]);
		if (preg_match_all(self::nakedRegex(), $text, $m) || preg_match_all(self::nakedRegex(false), $text, $m))
			$urls = array_merge($urls, $m[0]);

		return array_values(array_unique(array_map(function ($u) { return trim($u, " \"'"); }, $urls)));
	}

	/**
	 * Bare domains such as "example.com/page", which SMF 2.1 turns into links
	 * by itself. Uses SMF's own list of top-level domains when it has one.
	 */
	public static function nakedRegex($unicode = true)
	{
		global $modSettings;

		$tlds = !empty($modSettings['tld_regex']) ? $modSettings['tld_regex'] : '(?:' . self::FALLBACK_TLDS . ')';
		$label = $unicode ? '[\p{L}\p{N}](?:[\p{L}\p{N}-]*[\p{L}\p{N}])?' : '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?';
		$end = $unicode ? '(?![\p{L}\p{N}-])' : '(?![a-z0-9-])';

		return '~(?<![\w@./:\x02-])(?:' . $label . '\.)+' . $tlds . $end . '(?:[/?][^\s<>\[\]"\'\x01]*)?~i' . ($unicode ? 'u' : '');
	}

	public static function restore($text, array $store)
	{
		for ($i = 0; $i < 60 && strpos($text, "\x01") !== false; $i++)
			$text = preg_replace_callback('~\x01(\d+)\x02~', function ($m) use ($store)
			{
				return isset($store[(int) $m[1]]) ? $store[(int) $m[1]] : '';
			}, $text);

		return $text;
	}

	/**
	 * Count links and images and, when a limit is given, turn the extras
	 * into plain text (wrapped in [nobbc] so SMF doesn't auto-link them).
	 *
	 * @param int $max_links -1 = unlimited
	 * @param int $max_images -1 = unlimited
	 * @param array $counts Receives array('links' => n, 'images' => n)
	 */
	public static function process($text, $max_links, $max_images, &$counts = array())
	{
		global $modSettings;

		list ($work, $store) = self::protect($text, true);
		$allow_internal = !empty($modSettings['tl_allow_internal']);
		$links = 0;
		$images = 0;

		$work = preg_replace_callback(self::IMG_RE, function ($m) use (&$images, $max_images, &$store)
		{
			$images++;
			if ($max_images < 0 || $images <= $max_images)
				return $m[0];

			// Set aside straight away so the link check below doesn't see it again.
			$store[] = '[nobbc]' . $m[1] . '[/nobbc]';
			return "\x01" . (count($store) - 1) . "\x02";
		}, $work);

		$work = preg_replace_callback(self::LINK_RE, function ($m) use (&$links, $max_links, $allow_internal, &$store)
		{
			// Set every link aside once handled, so the bare-domain check below
			// doesn't count the domain inside it a second time.
			$aside = function ($out) use (&$store)
			{
				$store[] = $out;
				return "\x01" . (count($store) - 1) . "\x02";
			};

			$get = function ($i) use ($m)
			{
				return isset($m[$i]) ? $m[$i] : '';
			};

			// Images were handled above.
			if ($get(1) !== '')
				return $m[0];

			if ($get(2) !== '' || $get(3) !== '')
				list ($url, $label) = array($get(2), $get(3));
			elseif ($get(5) !== '' || $get(6) !== '')
				list ($url, $label) = array($get(5), $get(6));
			elseif ($get(8) !== '' || $get(9) !== '')
				list ($url, $label) = array($get(8), $get(9));
			elseif ($get(4) !== '')
				list ($url, $label) = array($get(4), $get(4));
			elseif ($get(7) !== '')
				list ($url, $label) = array($get(7), $get(7));
			elseif ($get(10) !== '')
				list ($url, $label) = array($get(10), $get(10));
			else
				list ($url, $label) = array($m[0], $m[0]);

			$is_iurl = stripos($m[0], '[iurl') === 0;
			if ($allow_internal && ($is_iurl || TrustLevels::isInternal($url)))
				return $aside($m[0]);

			$links++;
			if ($max_links < 0 || $links <= $max_links)
				return $aside($m[0]);

			$url = trim($url, '"\'');
			$url = preg_replace('~^&quot;|&quot;$~', '', $url);

			return $aside($label === $url || $label === $m[0] ? '[nobbc]' . $url . '[/nobbc]' : $label . ' ([nobbc]' . $url . '[/nobbc])');
		}, $work);

		// Bare domains ("example.com"), which SMF also turns into links.
		$naked = function ($m) use (&$links, $max_links, $allow_internal)
		{
			if ($allow_internal && TrustLevels::isInternal('http://' . $m[0]))
				return $m[0];

			$links++;
			if ($max_links < 0 || $links <= $max_links)
				return $m[0];

			return '[nobbc]' . $m[0] . '[/nobbc]';
		};
		$result = preg_replace_callback(self::nakedRegex(), $naked, $work);
		if ($result === null)
			$result = preg_replace_callback(self::nakedRegex(false), $naked, $work);
		if ($result !== null)
			$work = $result;

		$counts = array('links' => $links, 'images' => $images);

		return self::restore($work, $store);
	}

	public static function limitsFor($level)
	{
		$levels = self::levels();

		return $levels[max(0, min((int) $level, count($levels) - 1))]['limits'];
	}

	/**
	 * integrate_post2_pre: show a clear error when a post breaks the
	 * member's limits.
	 */
	public static function post2Pre(&$post_errors)
	{
		global $modSettings, $topic, $smcFunc, $user_info, $txt;

		if (empty($modSettings['tl_enabled']) || self::isExemptUser())
			return;

		$level = self::myLevel();
		if ($level === null)
			return;

		loadLanguage('TrustLevels');

		$levels = self::levels();
		$limits = $levels[$level]['limits'];
		$name = $smcFunc['htmlspecialchars']($levels[$level]['name'], ENT_QUOTES);

		$counts = array();
		self::process(isset($_POST['message']) ? (string) $_POST['message'] : '', -1, -1, $counts);

		if ($limits['links'] >= 0 && $counts['links'] > $limits['links'])
			$post_errors[] = $limits['links'] === 0 ? array('tl_no_links', array($name)) : array('tl_too_many_links', array($limits['links'], $name));

		if ($limits['images'] >= 0 && $counts['images'] > $limits['images'])
			$post_errors[] = $limits['images'] === 0 ? array('tl_no_images', array($name)) : array('tl_too_many_images', array($limits['images'], $name));

		// Daily limits apply to new posts, not edits.
		if (!empty($_REQUEST['msg']))
			return;

		$since = time() - 86400;

		if (empty($topic) && $limits['topics_day'] >= 0)
		{
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_member_started = {int:me}
					AND m.poster_time > {int:since}',
				array('me' => $user_info['id'], 'since' => $since)
			);
			list ($count) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if ((int) $count >= $limits['topics_day'])
				$post_errors[] = array('tl_topic_limit', array($limits['topics_day'], $name));
		}
		elseif (!empty($topic) && $limits['replies_day'] >= 0)
		{
			// Replies only: a topic's first post counts towards the topics limit.
			$request = $smcFunc['db_query']('', '
				SELECT COUNT(*)
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				WHERE m.id_member = {int:me}
					AND m.poster_time > {int:since}
					AND m.id_msg != t.id_first_msg',
				array('me' => $user_info['id'], 'since' => $since)
			);
			list ($count) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			if ((int) $count >= $limits['replies_day'])
				$post_errors[] = array('tl_reply_limit', array($limits['replies_day'], $name));
		}
	}

	/**
	 * integrate_preparsecode: a safety net for quick edit, PMs and signatures,
	 * which don't go through the post form checks. Extra links and images
	 * become plain text.
	 */
	public static function preparse(&$message, $previewing = false)
	{
		global $modSettings;

		if (self::$sending || empty($modSettings['tl_enabled']) || $message === '' || self::isExemptUser())
			return;

		$level = self::myLevel();
		if ($level === null)
			return;

		$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
		if ($action === 'pm' && empty($modSettings['tl_apply_pm']))
			return;

		$limits = self::limitsFor($level);
		$max_links = $action === 'profile' ? ($limits['sig_links'] ? -1 : 0) : $limits['links'];
		$max_images = $limits['images'];

		if ($max_links < 0 && $max_images < 0)
			return;

		$message = self::process($message, $max_links, $max_images);
	}

	/* ------------------------------------------------------------------
	 * Recalculation triggers
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_load_theme: styles, plus an hourly check of the current
	 * member's level (so promotions happen while they browse).
	 */
	public static function loadTheme()
	{
		global $modSettings, $user_info;

		if (empty($modSettings['tl_enabled']))
			return;

		loadCSSFile('trustlevels.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_trustlevels');

		if (!empty($user_info['is_guest']) || empty($user_info['id']) || (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('logout', 'keepalive', 'dlattach', '.xml', 'xmlhttp'), true)))
			return;

		if (empty($_SESSION['tl_checked']) || $_SESSION['tl_checked'] < time() - 3600)
		{
			$_SESSION['tl_checked'] = time();
			self::recalc(array($user_info['id']));
		}
	}

	/**
	 * integrate_after_create_post
	 */
	public static function afterPost($msgOptions, $topicOptions = array(), $posterOptions = array())
	{
		global $modSettings;

		if (!empty($modSettings['tl_enabled']) && !empty($posterOptions['id']))
			self::recalc(array((int) $posterOptions['id']));
	}

	/**
	 * Scheduled task: recalculate members who were active in the last two
	 * days. Others are checked when they next visit.
	 */
	public static function scheduledTask()
	{
		global $modSettings, $smcFunc;

		if (empty($modSettings['tl_enabled']))
			return true;

		$since = time() - 2 * 86400;
		$start = 0;
		$deadline = microtime(true) + 25;

		do
		{
			$ids = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE last_login >= {int:since}
					AND id_member > {int:start}
					AND is_activated = {int:active}
				ORDER BY id_member
				LIMIT 300',
				array('since' => $since, 'start' => $start, 'active' => 1)
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			if (!empty($ids))
			{
				self::recalc($ids);
				$start = end($ids);
			}
		}
		while (count($ids) === 300 && microtime(true) < $deadline);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}trust_log
			WHERE logged < {int:cutoff}',
			array('cutoff' => time() - 365 * 86400)
		);

		return true;
	}

	/* ------------------------------------------------------------------
	 * Display
	 * ---------------------------------------------------------------- */

	public static function badge($level, $small = false)
	{
		$levels = self::levels();
		if (!isset($levels[$level]))
			return '';

		$l = $levels[$level];

		return '<span class="tl-badge tl-level-' . (int) $level . ($small ? ' tl-badge-small' : '') . '" style="--tl-color:' . $l['color'] . '" title="' . htmlspecialchars($l['description'] !== '' ? $l['description'] : $l['name'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8') . '</span>';
	}

	/**
	 * integrate_display_message_list: load the posters' levels in one query.
	 */
	public static function displayMessageList(&$messages, &$posters)
	{
		global $modSettings, $smcFunc;

		if (empty($modSettings['tl_enabled']) || empty($modSettings['tl_badge']) || $modSettings['tl_badge'] === 'off' || empty($posters))
			return;

		$ids = array_values(array_filter(array_map('intval', (array) $posters)));
		if (empty($ids))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_member, level
			FROM {db_prefix}trust_members
			WHERE id_member IN ({array_int:ids})',
			array('ids' => $ids)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			self::$page_levels[(int) $row['id_member']] = (int) $row['level'];
		$smcFunc['db_free_result']($request);
	}

	/**
	 * integrate_prepare_display_context: add the badge to the poster info.
	 */
	public static function prepareDisplay(&$output, &$message, $counter = 0)
	{
		global $modSettings, $txt;

		if (empty($modSettings['tl_enabled']) || empty($modSettings['tl_badge']) || $modSettings['tl_badge'] === 'off')
			return;

		$id = isset($output['member']['id']) ? (int) $output['member']['id'] : 0;
		if ($id <= 0)
			return;

		$level = isset(self::$page_levels[$id]) ? self::$page_levels[$id] : 0;
		$min = isset($modSettings['tl_badge_min']) ? (int) $modSettings['tl_badge_min'] : 1;
		if ($level < $min || $level > self::top())
			return;

		$placement = in_array($modSettings['tl_badge'], array('below_avatar', 'icons', 'standard', 'above_member', 'after_member'), true) ? $modSettings['tl_badge'] : 'below_avatar';

		if (!isset($output['custom_fields']) || !is_array($output['custom_fields']))
			$output['custom_fields'] = array();

		$output['custom_fields'][$placement][] = array(
			'title' => isset($txt['tl_level']) ? $txt['tl_level'] : 'Trust level',
			'col_name' => 'trust_level',
			'value' => self::badge($level, true),
			'raw' => $level,
			'placement' => $placement,
		);
	}

	/* ------------------------------------------------------------------
	 * Profile
	 * ---------------------------------------------------------------- */

	public static function profileAreas(&$profile_areas)
	{
		global $modSettings, $txt;

		if (empty($modSettings['tl_enabled']))
			return;

		loadLanguage('TrustLevels');

		$profile_areas['info']['areas']['trustlevel'] = array(
			'label' => $txt['tl_profile_label'],
			'function' => 'TrustLevels::profileArea',
			'icon' => 'members',
			'permission' => array(
				'own' => array('is_not_guest'),
				'any' => array('profile_view'),
			),
		);
	}

	public static function profileArea($memID = 0)
	{
		global $context, $txt, $smcFunc, $user_info, $modSettings, $scripturl;

		if (empty($memID))
			$memID = isset($context['id_member']) ? (int) $context['id_member'] : (int) $user_info['id'];
		$memID = (int) $memID;

		loadLanguage('TrustLevels');
		loadTemplate('TrustLevels');

		$own = $memID === (int) $user_info['id'];
		// Staff can't change their own level (and so their own level groups),
		// except administrators.
		$can_manage = allowedTo('tl_manage') && (!$own || !empty($user_info['is_admin']));
		$levels = self::levels();
		$top = count($levels) - 1;

		if (isset($_POST['tl_save']) && $can_manage)
		{
			checkSession();

			$level = max(0, min($top, isset($_POST['tl_level']) ? (int) $_POST['tl_level'] : 0));
			$reason = mb_substr(trim(strip_tags(isset($_POST['tl_reason']) ? (string) $_POST['tl_reason'] : '')), 0, 200);
			$old = self::storedLevel($memID);

			if (!empty($_POST['tl_lock']))
				self::setLevel($memID, $level, $old, $user_info['id'], $reason !== '' ? $reason : 'manual', true);
			else
			{
				// Unlock and recalculate, keeping any level set by hand
				// (a level given manually is kept unless warnings remove it).
				self::setLevel($memID, $level, $old, $user_info['id'], $reason !== '' ? $reason : 'manual', false);
				self::recalc(array($memID));
			}

			redirectexit('action=profile;area=trustlevel;u=' . $memID);
		}

		if (self::storedLevel($memID) === null)
			self::recalc(array($memID));

		$request = $smcFunc['db_query']('', '
			SELECT level, locked, updated
			FROM {db_prefix}trust_members
			WHERE id_member = {int:id}',
			array('id' => $memID)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		$stats = self::stats(array($memID));
		$stats = isset($stats[$memID]) ? $stats[$memID] : array_fill_keys(array_merge(self::$req_keys, array('warning', 'last_warning')), 0) + array('groups' => array(), 'name' => '');

		$level = empty($row) ? 0 : max(0, min((int) $row['level'], $top));

		$next = null;
		for ($i = $level + 1; $i <= $top; $i++)
		{
			$next = $i;
			break;
		}

		$log = array();
		if ($own || $can_manage)
		{
			$request = $smcFunc['db_query']('', '
				SELECT l.action, l.old_level, l.new_level, l.logged, l.reason, l.id_changer, COALESCE(mem.real_name, {string:empty}) AS changer
				FROM {db_prefix}trust_log AS l
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_changer)
				WHERE l.id_member = {int:id}
				ORDER BY l.id_log DESC
				LIMIT 20',
				array('id' => $memID, 'empty' => '')
			);
			while ($r = $smcFunc['db_fetch_assoc']($request))
				$log[] = array(
					'action' => $r['action'] !== '' ? $r['action'] : 'level',
					'old' => (int) $r['old_level'],
					'new' => (int) $r['new_level'],
					'time' => timeformat($r['logged']),
					'reason' => $r['reason'],
					'changer' => (int) $r['id_changer'] > 0 ? $r['changer'] : '',
				);
			$smcFunc['db_free_result']($request);
		}

		$context['tl'] = array(
			'id_member' => $memID,
			'own' => $own,
			'level' => $level,
			'locked' => !empty($row['locked']),
			'updated' => !empty($row['updated']) ? timeformat($row['updated']) : '',
			'exempt' => self::isExempt($stats['groups']),
			'levels' => $levels,
			'stats' => $stats,
			'next' => $next,
			'show_progress' => $own || $can_manage || !empty($modSettings['tl_profile_public']),
			'can_manage' => $can_manage,
			'log' => $log,
			'probation_until' => !self::isExempt($stats['groups']) ? self::probationUntil($stats) : 0,
			'protected_groups' => $can_manage ? self::groupNames(self::protectedGroups($memID, $stats['groups'])) : array(),
			'url' => $scripturl . '?action=profile;area=trustlevel;u=' . $memID,
		);

		$context['page_title'] = $txt['tl_profile_label'];
		$context['sub_template'] = 'tl_profile';
	}

	public static function groupNames(array $ids)
	{
		global $smcFunc;

		$out = array();
		if (empty($ids))
			return $out;

		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:ids})',
			array('ids' => array_map('intval', $ids))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$out[(int) $row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Admin, permissions and credits
	 * ---------------------------------------------------------------- */

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('TrustLevels');

		$admin_areas['members']['areas']['trustlevels'] = array(
			'label' => $txt['tl_title'],
			'file' => 'TrustLevels-Admin.php',
			'function' => 'TrustLevels_Admin::main',
			'icon' => 'members',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'levels' => array($txt['tl_tab_levels']),
				'members' => array($txt['tl_tab_members']),
				'log' => array($txt['tl_tab_log']),
				'recalc' => array($txt['tl_tab_recalc']),
				'settings' => array($txt['tl_tab_settings']),
			),
		);
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('TrustLevels');
		$permissionList['membergroup']['tl_manage'] = array(false, 'member_admin');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'tl_manage';
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('TrustLevels');
		$context['copyrights']['mods'][] = sprintf($txt['tl_credits'], self::VERSION);
	}
}
