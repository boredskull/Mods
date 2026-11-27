<?php

/**
 * Clubs for SMF 2.1
 *
 * Member-created communities inside the forum. Each club is built from
 * SMF's own parts: one or more real boards in a "Clubs" category, plus a
 * hidden membergroup. Board access, permissions and moderators are kept in
 * step with the club's type and roles, so SMF itself enforces privacy
 * everywhere (topics, search, recent posts, attachments, calendar, feeds).
 *
 * Club types (as in Invision Community):
 *   open     - everyone can see it and join
 *   closed   - everyone can see the club and its members, posts are for members; join by request
 *   private  - only members can find it; join by invitation
 *   readonly - everyone can read, only leaders post; join by invitation
 *
 * @package Clubs
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.3
 */

if (!defined('SMF'))
	die('No direct access...');

class Clubs
{
	const VERSION = '1.1.3';

	public static $types = array('open', 'closed', 'private', 'readonly');
	public static $in_group = array('owner', 'leader', 'member');

	/** Board permissions for club members. */
	public static $perm_member = array(
		'post_new', 'post_reply_own', 'post_reply_any', 'post_draft', 'poll_view', 'poll_vote', 'poll_post',
		'poll_add_own', 'poll_edit_own', 'poll_lock_own', 'post_attachment', 'view_attachments', 'modify_own',
		'delete_own', 'lock_own', 'remove_own', 'report_any', 'mark_any_notify', 'mark_notify', 'likes_like',
	);

	/** Members of read-only clubs: no posting. */
	public static $perm_readonly = array('poll_view', 'poll_vote', 'view_attachments', 'report_any', 'mark_any_notify', 'mark_notify', 'likes_like');

	/** Non-members reading an open or read-only club. */
	public static $perm_viewer = array('poll_view', 'view_attachments', 'report_any', 'mark_any_notify', 'mark_notify');

	/** Club leaders (as board moderators) and global moderators. */
	public static $perm_moderator = array(
		'post_new', 'post_reply_own', 'post_reply_any', 'post_draft', 'poll_view', 'poll_vote', 'poll_post',
		'poll_add_own', 'poll_add_any', 'poll_edit_own', 'poll_edit_any', 'poll_lock_own', 'poll_lock_any',
		'poll_remove_own', 'poll_remove_any', 'post_attachment', 'view_attachments', 'modify_own', 'modify_any',
		'delete_own', 'delete_any', 'delete_replies', 'lock_own', 'lock_any', 'remove_own', 'remove_any',
		'make_sticky', 'split_any', 'merge_any', 'moderate_board', 'approve_posts', 'report_any',
		'mark_any_notify', 'mark_notify', 'likes_like', 'announce_topic',
	);

	protected static $clubs = array();
	protected static $roles = array();
	protected static $map = null;

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	public static function enabled()
	{
		global $modSettings;

		return !empty($modSettings['clubs_enabled']);
	}

	public static function setting($key, $default = 0)
	{
		global $modSettings;

		return isset($modSettings['clubs_' . $key]) ? $modSettings['clubs_' . $key] : $default;
	}

	public static function allowedTypes()
	{
		$types = json_decode((string) self::setting('types', ''), true);
		$types = is_array($types) ? array_values(array_intersect(self::$types, $types)) : self::$types;

		return empty($types) ? array('open') : $types;
	}

	public static function isAdmin()
	{
		return allowedTo('admin_forum') || allowedTo('clubs_manage_all');
	}

	/* ------------------------------------------------------------------
	 * Loading
	 * ---------------------------------------------------------------- */

	public static function load($id_club, $fresh = false)
	{
		global $smcFunc;

		$id_club = (int) $id_club;
		if ($id_club <= 0)
			return null;
		if (!$fresh && array_key_exists($id_club, self::$clubs))
			return self::$clubs[$id_club];

		$request = $smcFunc['db_query']('', '
			SELECT id_club, name, description, type, status, id_owner, id_group, id_board, icon, color,
				member_events, num_members, created, id_profile, id_ban_group
			FROM {db_prefix}clubs
			WHERE id_club = {int:id}',
			array('id' => $id_club)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($row))
			foreach (array('id_club', 'id_owner', 'id_group', 'id_board', 'member_events', 'num_members', 'created', 'id_profile', 'id_ban_group') as $key)
				$row[$key] = (int) $row[$key];

		return self::$clubs[$id_club] = $row ?: null;
	}

	public static function forget($id_club)
	{
		unset(self::$clubs[(int) $id_club]);
		foreach (array_keys(self::$roles) as $key)
			if (strpos($key, (int) $id_club . ':') === 0)
				unset(self::$roles[$key]);
		self::$map = null;
		cache_put_data('clubs_board_map', null, 0);
	}

	/**
	 * id_board => id_club for every club board.
	 */
	public static function boardMap()
	{
		global $smcFunc;

		if (self::$map !== null)
			return self::$map;

		if (($cached = cache_get_data('clubs_board_map', 600)) !== null)
			return self::$map = $cached;

		$map = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_board, id_club
			FROM {db_prefix}club_boards',
			array()
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$map[(int) $row[0]] = (int) $row[1];
		$smcFunc['db_free_result']($request);

		cache_put_data('clubs_board_map', $map, 600);

		return self::$map = $map;
	}

	public static function clubBoards($id_club)
	{
		return array_keys(self::boardMap(), (int) $id_club, true);
	}

	/**
	 * @return string|null owner, leader, member, pending, invited, banned
	 */
	public static function role($id_club, $id_member = null)
	{
		global $smcFunc, $user_info;

		$id_member = $id_member === null ? (int) $user_info['id'] : (int) $id_member;
		if ($id_member <= 0)
			return null;

		$key = (int) $id_club . ':' . $id_member;
		if (array_key_exists($key, self::$roles))
			return self::$roles[$key];

		$request = $smcFunc['db_query']('', '
			SELECT role
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND id_member = {int:member}',
			array('club' => (int) $id_club, 'member' => $id_member)
		);
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return self::$roles[$key] = empty($row) ? null : $row[0];
	}

	public static function isMember($club, $id_member = null)
	{
		return in_array(self::role($club['id_club'], $id_member), self::$in_group, true);
	}

	public static function isLeader($club, $id_member = null)
	{
		return in_array(self::role($club['id_club'], $id_member), array('owner', 'leader'), true) || ($id_member === null && self::isAdmin());
	}

	public static function isOwner($club, $id_member = null)
	{
		return self::role($club['id_club'], $id_member) === 'owner' || ($id_member === null && self::isAdmin());
	}

	/**
	 * Can the current member see that this club exists?
	 */
	public static function canSee($club)
	{
		if (empty($club))
			return false;
		if (self::isAdmin())
			return true;

		$role = self::role($club['id_club']);

		if ($club['status'] !== 'active')
			return $role === 'owner';
		if ($club['type'] === 'private')
			return in_array($role, array('owner', 'leader', 'member', 'invited'), true);

		// Open, closed and read-only clubs are listed for everyone.
		return true;
	}

	/**
	 * Can the current member see the club's discussions, media and events?
	 */
	public static function canSeeContent($club)
	{
		global $user_info;

		if (self::isAdmin())
			return true;
		if (in_array($club['type'], array('open', 'readonly'), true))
		{
			// Same rule as the boards: guests only if guest reading is on.
			if (!empty($user_info['is_guest']))
				return (bool) self::setting('guests', 1);

			return self::role($club['id_club']) !== 'banned';
		}

		return self::isMember($club);
	}

	/* ------------------------------------------------------------------
	 * SMF structures: category, permission profile, groups
	 * ---------------------------------------------------------------- */

	public static function category()
	{
		global $smcFunc, $sourcedir, $txt;

		$id = (int) self::setting('category', 0);
		if ($id > 0)
		{
			$request = $smcFunc['db_query']('', 'SELECT id_cat FROM {db_prefix}categories WHERE id_cat = {int:id}', array('id' => $id));
			$ok = $smcFunc['db_num_rows']($request) > 0;
			$smcFunc['db_free_result']($request);
			if ($ok)
				return $id;
		}

		loadLanguage('Clubs');
		require_once($sourcedir . '/Subs-Categories.php');
		$id = (int) createCategory(array('cat_name' => $txt['clubs_category_name'], 'move_to' => 'bottom', 'is_collapsible' => true));
		updateSettings(array('clubs_category' => $id));

		return $id;
	}

	/**
	 * The club's own permission profile.
	 *
	 * Each club has a separate profile, so its membergroup's posting
	 * permissions only ever apply to that club's boards. (In 1.0 every club
	 * shared one profile, so joining one club let you post in any other
	 * public club.)
	 */
	public static function clubProfile(array $club)
	{
		global $smcFunc;

		$id = isset($club['id_profile']) ? (int) $club['id_profile'] : 0;
		if ($id > 0)
		{
			$request = $smcFunc['db_query']('', 'SELECT id_profile FROM {db_prefix}permission_profiles WHERE id_profile = {int:id}', array('id' => $id));
			$ok = $smcFunc['db_num_rows']($request) > 0;
			$smcFunc['db_free_result']($request);
			if ($ok)
				return $id;
		}

		$id = (int) $smcFunc['db_insert']('insert',
			'{db_prefix}permission_profiles',
			array('profile_name' => 'string-255'),
			array(mb_substr('Club: ' . un_htmlspecialchars($club['name']), 0, 255)),
			array('id_profile'),
			1
		);
		$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_profile = {int:p} WHERE id_club = {int:c}', array('p' => $id, 'c' => (int) $club['id_club']));
		if (isset(self::$clubs[(int) $club['id_club']]))
			self::$clubs[(int) $club['id_club']]['id_profile'] = $id;

		return $id;
	}

	/**
	 * Fill a club's profile: readers for everyone (open and read-only
	 * clubs), moderation for global and board moderators, and member
	 * permissions for this club's group only.
	 */
	public static function syncClubProfile(array $club)
	{
		global $smcFunc;

		if ($club['id_group'] <= 0)
			return 0;

		$profile = self::clubProfile($club);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:profile}',
			array('profile' => $profile)
		);

		$rows = array();
		if (in_array($club['type'], array('open', 'readonly'), true))
			foreach (array_diff(self::publicGroups(true), array(2)) as $g)
				foreach (self::$perm_viewer as $p)
					if ($g !== -1 || !in_array($p, array('report_any', 'mark_any_notify', 'mark_notify'), true))
						$rows[] = array($g, $profile, $p, 1);
		foreach (array(2, 3) as $g)
			foreach (self::$perm_moderator as $p)
				$rows[] = array($g, $profile, $p, 1);
		foreach ($club['type'] === 'readonly' ? self::$perm_readonly : self::$perm_member as $p)
			$rows[] = array($club['id_group'], $profile, $p, 1);

		self::insertPermissions($rows);

		return $profile;
	}

	/**
	 * Refresh every club's profile (when membergroups or settings change).
	 */
	public static function syncProfile()
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs WHERE id_group > {int:zero}', array('zero' => 0));
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		foreach ($ids as $id)
			if ($club = self::load($id, true))
				self::syncClubProfile($club);
	}

	/**
	 * Upgrading from 1.0: move club boards off the shared "Clubs" profile
	 * onto their own, then remove the shared one if nothing else uses it.
	 */
	public static function migrateSharedProfile()
	{
		global $smcFunc;

		$shared = (int) self::setting('profile', 0);

		$ids = array();
		$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs WHERE status = {string:active}', array('active' => 'active'));
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);
		foreach ($ids as $id)
			self::syncAccess($id);

		if ($shared <= 0)
			return;

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}boards WHERE id_profile = {int:p}', array('p' => $shared));
		list ($in_use) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if ((int) $in_use === 0)
		{
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions WHERE id_profile = {int:p}', array('p' => $shared));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permission_profiles WHERE id_profile = {int:p}', array('p' => $shared));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable = {string:v}', array('v' => 'clubs_profile'));
			updateSettings(array('settings_updated' => time()));
		}
		else
		{
			// Something else uses it: at least take the club groups out.
			$groups = self::clubGroups();
			if (!empty($groups))
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions WHERE id_profile = {int:p} AND id_group IN ({array_int:g})', array('p' => $shared, 'g' => $groups));
		}
	}

	protected static function insertPermissions(array $rows)
	{
		global $smcFunc;

		if (empty($rows))
			return;

		$smcFunc['db_insert']('replace',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$rows,
			array('id_group', 'id_profile', 'permission')
		);
	}

	public static function clubGroups()
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', 'SELECT id_group, id_ban_group FROM {db_prefix}clubs', array());
		while ($row = $smcFunc['db_fetch_row']($request))
			foreach ($row as $g)
				if ((int) $g > 0)
					$ids[] = (int) $g;
		$smcFunc['db_free_result']($request);

		return array_values(array_unique($ids));
	}

	/**
	 * Every non-club group (regular members, post-count groups, normal
	 * groups) plus guests if allowed. Every member is in a post-count group,
	 * so this covers everyone.
	 */
	public static function publicGroups($force_guests = false)
	{
		global $smcFunc;

		$groups = array(0);
		if ($force_guests || self::setting('guests', 1))
			$groups[] = -1;

		$club_groups = self::clubGroups();
		$request = $smcFunc['db_query']('', '
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group NOT IN ({array_int:skip})',
			array('skip' => array_merge(array(1, 3), $club_groups ?: array(0)))
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$groups[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		return array_values(array_unique($groups));
	}

	public static function accessGroups(array $club)
	{
		$groups = array($club['id_group']);

		if (in_array($club['type'], array('open', 'readonly'), true))
			$groups = array_merge($groups, self::publicGroups());
		elseif (self::setting('gmods_private', 1))
			$groups[] = 2;

		return array_values(array_unique(array_filter($groups, function ($g) { return $g !== null; })));
	}

	/**
	 * Apply a club's type and roles to its boards: access groups,
	 * posting permissions for the club group, and leaders as moderators.
	 */
	public static function syncAccess($id_club)
	{
		global $smcFunc;

		$club = self::load($id_club, true);
		if (!$club || $club['id_group'] <= 0)
			return;

		$boards = self::clubBoards($club['id_club']);
		$profile = self::syncClubProfile($club);

		// Members banned from the club can't read its boards either.
		$deny = $club['id_ban_group'] > 0 && self::setting('ban_hides', 1) ? (string) $club['id_ban_group'] : '';
		if ($deny !== '')
			self::needDenyAccess();

		self::setBoardAccess($boards, self::accessGroups($club), $deny === '' ? array() : array((int) $deny), $profile);

		self::syncModerators($club['id_club']);
		self::flushCaches($boards);
	}

	/**
	 * Set who can see boards. SMF 2.1 checks visibility against the
	 * board_permissions_view table, not the boards.member_groups list
	 * (which is what the board editor shows), so both are written
	 * together, as SMF's own board editor does.
	 */
	public static function setBoardAccess(array $boards, array $allow, array $deny, $profile)
	{
		global $smcFunc;

		$boards = array_values(array_unique(array_filter(array_map('intval', $boards))));
		if (empty($boards))
			return;

		$allow = array_values(array_unique(array_map('intval', $allow)));
		$deny = array_values(array_unique(array_map('intval', $deny)));

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boards
			SET member_groups = {string:groups}, deny_member_groups = {string:deny}, id_profile = {int:profile}
			WHERE id_board IN ({array_int:boards})',
			array('groups' => implode(',', $allow), 'deny' => implode(',', $deny), 'profile' => (int) $profile, 'boards' => $boards)
		);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}board_permissions_view
			WHERE id_board IN ({array_int:boards})',
			array('boards' => $boards)
		);

		$rows = array();
		foreach ($boards as $b)
		{
			foreach ($allow as $g)
				$rows[] = array($g, $b, 0);
			foreach ($deny as $g)
				$rows[] = array($g, $b, 1);
		}
		if (!empty($rows))
			$smcFunc['db_insert']('ignore',
				'{db_prefix}board_permissions_view',
				array('id_group' => 'int', 'id_board' => 'int', 'deny' => 'int'),
				$rows,
				array('id_group', 'id_board', 'deny')
			);
	}

	public static function syncModerators($id_club)
	{
		global $smcFunc;

		$boards = self::clubBoards($id_club);
		if (empty($boards))
			return;

		$leaders = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND role IN ({array_string:roles})',
			array('club' => (int) $id_club, 'roles' => array('owner', 'leader'))
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$leaders[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		// What's there now, and which of those Clubs added.
		$existing = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_board, id_member
			FROM {db_prefix}moderators
			WHERE id_board IN ({array_int:boards})',
			array('boards' => $boards)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$existing[(int) $row[0] . ':' . (int) $row[1]] = true;
		$smcFunc['db_free_result']($request);

		$ours = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_board, id_member
			FROM {db_prefix}club_moderators
			WHERE id_board IN ({array_int:boards})',
			array('boards' => $boards)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ours[(int) $row[0] . ':' . (int) $row[1]] = array((int) $row[0], (int) $row[1]);
		$smcFunc['db_free_result']($request);

		$wanted = array();
		foreach ($boards as $b)
			foreach ($leaders as $m)
				$wanted[$b . ':' . $m] = array($b, $m);

		// Remove only moderators Clubs added that are no longer leaders.
		foreach (array_diff_key($ours, $wanted) as $pair)
		{
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}moderators WHERE id_board = {int:b} AND id_member = {int:m}', array('b' => $pair[0], 'm' => $pair[1]));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_moderators WHERE id_board = {int:b} AND id_member = {int:m}', array('b' => $pair[0], 'm' => $pair[1]));
		}

		// Add missing leaders. Someone an admin already made a moderator
		// stays theirs: not recorded, so never removed by Clubs.
		$add = array();
		foreach (array_diff_key($wanted, $existing) as $pair)
			$add[] = $pair;
		if (!empty($add))
		{
			$smcFunc['db_insert']('ignore', '{db_prefix}moderators', array('id_board' => 'int', 'id_member' => 'int'), $add, array('id_board', 'id_member'));
			$smcFunc['db_insert']('ignore', '{db_prefix}club_moderators', array('id_board' => 'int', 'id_member' => 'int'), $add, array('id_board', 'id_member'));
		}

		// Records of ours that an admin has since removed by hand.
		foreach (array_diff_key($ours, $existing) as $pair)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_moderators WHERE id_board = {int:b} AND id_member = {int:m}', array('b' => $pair[0], 'm' => $pair[1]));

		self::flushCaches($boards);
	}

	/**
	 * SMF 2.1 caches the user's primary and additional groups for 60 seconds
	 * (and can cache profile data separately). Purge those after membership
	 * changes so the next request sees the new board access immediately.
	 */
	public static function refreshMemberCache($id_member)
	{
		$id_member = (int) $id_member;
		if ($id_member <= 0)
			return;

		cache_put_data('user_settings-' . $id_member, null, 0);
		cache_put_data('member_data-normal-' . $id_member, null, 0);
		cache_put_data('member_data-profile-' . $id_member, null, 0);
	}

	protected static function flushCaches(array $boards)
	{
		foreach ($boards as $b)
			cache_put_data('board-' . (int) $b, null, 0);

		updateSettings(array('settings_updated' => time()));
	}

	/* ------------------------------------------------------------------
	 * Members and roles
	 * ---------------------------------------------------------------- */

	/**
	 * Set (or remove, with null) a member's role. Keeps the club group
	 * and board moderators in step.
	 */
	public static function setRole($id_club, $id_member, $role, $message = '', $invited_by = 0)
	{
		global $smcFunc, $sourcedir;

		$club = self::load($id_club);
		$id_member = (int) $id_member;
		if (!$club || $id_member <= 0)
			return;

		$old = self::role($club['id_club'], $id_member);

		if ($role === null)
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}club_members
				WHERE id_club = {int:club}
					AND id_member = {int:member}',
				array('club' => $club['id_club'], 'member' => $id_member)
			);
		else
			$smcFunc['db_insert']('replace',
				'{db_prefix}club_members',
				array('id_club' => 'int', 'id_member' => 'int', 'role' => 'string-10', 'joined' => 'int', 'message' => 'string-255', 'invited_by' => 'int'),
				array($club['id_club'], $id_member, $role, time(), mb_substr((string) $message, 0, 255), (int) $invited_by),
				array('id_club', 'id_member')
			);

		self::$roles[$club['id_club'] . ':' . $id_member] = $role;

		$was_in = in_array($old, self::$in_group, true);
		$now_in = in_array($role, self::$in_group, true);

		if ($club['id_group'] > 0 && $was_in !== $now_in)
		{
			require_once($sourcedir . '/Subs-Membergroups.php');
			if ($now_in)
				addMembersToGroup(array($id_member), $club['id_group'], 'only_additional', true);
			else
				removeMembersFromGroups(array($id_member), array($club['id_group']), true);

			// SMF caches user_settings separately from permissions. Without this,
			// a newly joined/approved member can be denied the club board until
			// their old group list expires, despite being a club member.
			self::refreshMemberCache($id_member);
		}

		$was_lead = in_array($old, array('owner', 'leader'), true);
		$now_lead = in_array($role, array('owner', 'leader'), true);
		if ($was_lead !== $now_lead)
			self::syncModerators($club['id_club']);

		// Banned members go into the club's ban group, which is denied access to its boards.
		if (($old === 'banned') !== ($role === 'banned'))
		{
			require_once($sourcedir . '/Subs-Membergroups.php');
			if ($role === 'banned')
			{
				$ban_group = self::banGroup($club);
				addMembersToGroup(array($id_member), $ban_group, 'only_additional', true);
			}
			elseif ($club['id_ban_group'] > 0)
				removeMembersFromGroups(array($id_member), array($club['id_ban_group']), true);

			self::refreshMemberCache($id_member);
		}

		self::updateCount($club['id_club']);
	}

	/**
	 * The club's hidden "banned" group, created the first time someone is banned.
	 */
	public static function banGroup(array $club)
	{
		global $smcFunc, $txt;

		if ($club['id_ban_group'] > 0)
			return $club['id_ban_group'];

		loadLanguage('Clubs');
		$id = (int) $smcFunc['db_insert']('insert',
			'{db_prefix}membergroups',
			array('group_name' => 'string-80', 'description' => 'string', 'online_color' => 'string-20', 'min_posts' => 'int',
				'max_messages' => 'int', 'icons' => 'string-255', 'group_type' => 'int', 'hidden' => 'int', 'id_parent' => 'int'),
			array(mb_substr(sprintf($txt['clubs_ban_group_name'], un_htmlspecialchars($club['name'])), 0, 80), '', '', -1, 0, '', 0, 2, -2),
			array('id_group'),
			1
		);
		$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_ban_group = {int:g} WHERE id_club = {int:c}', array('g' => $id, 'c' => (int) $club['id_club']));
		self::forget($club['id_club']);

		// Not a public group: take it out of every club's reader list.
		self::syncAccess($club['id_club']);

		return $id;
	}

	/**
	 * SMF only applies "deny" board access when that option is on.
	 */
	public static function needDenyAccess()
	{
		global $modSettings;

		if (empty($modSettings['deny_boards_access']))
			updateSettings(array('deny_boards_access' => 1));
	}

	public static function updateCount($id_club)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND role IN ({array_string:roles})',
			array('club' => (int) $id_club, 'roles' => self::$in_group)
		);
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}clubs
			SET num_members = {int:n}
			WHERE id_club = {int:club}',
			array('n' => (int) $count, 'club' => (int) $id_club)
		);

		if (isset(self::$clubs[(int) $id_club]))
			self::$clubs[(int) $id_club]['num_members'] = (int) $count;
	}

	public static function leaders($id_club)
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND role IN ({array_string:roles})',
			array('club' => (int) $id_club, 'roles' => array('owner', 'leader'))
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		return $ids;
	}

	public static function countOwned($id_member)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}clubs WHERE id_owner = {int:m}', array('m' => (int) $id_member));
		list ($n) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $n;
	}

	/* ------------------------------------------------------------------
	 * Creating and deleting
	 * ---------------------------------------------------------------- */

	/**
	 * @param array $data name (escaped), description (preparsed), type, color, icon, member_events
	 * @return int id_club
	 */
	public static function create(array $data, $id_owner)
	{
		global $smcFunc;

		$pending = self::setting('approval', 0) && !self::isAdmin();

		$id_club = (int) $smcFunc['db_insert']('insert',
			'{db_prefix}clubs',
			array('name' => 'string-255', 'description' => 'string', 'type' => 'string-10', 'status' => 'string-10', 'id_owner' => 'int',
				'id_group' => 'int', 'id_board' => 'int', 'icon' => 'string-80', 'color' => 'string-7', 'member_events' => 'int', 'num_members' => 'int', 'created' => 'int'),
			array($data['name'], $data['description'], $data['type'], $pending ? 'pending' : 'active', (int) $id_owner,
				0, 0, $data['icon'], $data['color'], $data['member_events'] ? 1 : 0, 0, time()),
			array('id_club'),
			1
		);

		self::setRole($id_club, $id_owner, 'owner');

		if (!$pending)
			self::activate($id_club);

		return $id_club;
	}

	/**
	 * Give a club its membergroup and main board (on creation, or when an admin approves it).
	 */
	public static function activate($id_club)
	{
		global $smcFunc, $sourcedir, $txt;

		$club = self::load($id_club, true);
		if (!$club)
			return false;

		loadLanguage('Clubs');

		if ($club['id_group'] <= 0)
		{
			$id_group = (int) $smcFunc['db_insert']('insert',
				'{db_prefix}membergroups',
				array('group_name' => 'string-80', 'description' => 'string', 'online_color' => 'string-20', 'min_posts' => 'int',
					'max_messages' => 'int', 'icons' => 'string-255', 'group_type' => 'int', 'hidden' => 'int', 'id_parent' => 'int'),
				array(mb_substr(sprintf($txt['clubs_group_name'], $club['name']), 0, 80), '', '', -1, 0, '', 0, 2, -2),
				array('id_group'),
				1
			);

			$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_group = {int:g} WHERE id_club = {int:c}', array('g' => $id_group, 'c' => $club['id_club']));
			$club['id_group'] = $id_group;
			self::$clubs[$club['id_club']] = $club;
		}

		if ($club['id_board'] <= 0)
		{
			$id_board = self::createBoard($club, $club['name'], self::plain($club['description'], 250), 0);
			$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_board = {int:b} WHERE id_club = {int:c}', array('b' => $id_board, 'c' => $club['id_club']));
		}

		$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET status = {string:active} WHERE id_club = {int:c}', array('active' => 'active', 'c' => $club['id_club']));

		// Members added while the club was pending join the group now.
		$members = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}club_members
			WHERE id_club = {int:club}
				AND role IN ({array_string:roles})',
			array('club' => $club['id_club'], 'roles' => self::$in_group)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$members[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		if (!empty($members))
		{
			require_once($sourcedir . '/Subs-Membergroups.php');
			addMembersToGroup($members, $club['id_group'], 'only_additional', true);

			// In particular, clear the creator's group list before redirecting
			// them to the newly activated club's board.
			foreach ($members as $member)
				self::refreshMemberCache($member);
		}

		self::forget($club['id_club']);
		self::syncAccess($club['id_club']);

		return true;
	}

	/**
	 * Create a club board (the main one, or a sub-board of it).
	 */
	public static function createBoard(array $club, $name, $description, $parent)
	{
		global $smcFunc, $sourcedir, $user_info;

		require_once($sourcedir . '/Subs-Boards.php');

		$options = array(
			'board_name' => $name,
			'board_description' => $description,
			'target_category' => self::category(),
			'move_to' => $parent ? 'child' : 'bottom',
			'posts_count' => true,
			'override_theme' => false,
			'board_theme' => 0,
			'access_groups' => self::accessGroups($club),
			'deny_groups' => array(),
			'profile' => self::clubProfile($club),
			'inherit_permissions' => false,
		);
		if ($parent)
			$options['target_board'] = (int) $parent;

		// SMF's createBoard() inserts the board first, then calls modifyBoard().
		// modifyBoard() calls getBoardTree(), whose {query_see_board} filter is
		// based on the currently logged-in member. The new board has no access
		// groups yet, so it is invisible to ordinary members at that point.
		// This makes modifyBoard() throw no_board AFTER the row has been inserted.
		// Only for this trusted board-maintenance call, allow SMF to load the
		// complete board tree, just as it does for an administrator. Restore
		// the original visibility filter even if the operation throws.
		$old_board_query = isset($user_info['query_see_board']) ? $user_info['query_see_board'] : null;
		$user_info['query_see_board'] = '1=1';
		try
		{
			$id_board = (int) createBoard($options);
		}
		finally
		{
			if ($old_board_query === null)
				unset($user_info['query_see_board']);
			else
				$user_info['query_see_board'] = $old_board_query;
		}

		if ($id_board <= 0)
			throw new RuntimeException('Could not create club board.');

		$smcFunc['db_insert']('ignore',
			'{db_prefix}club_boards',
			array('id_club' => 'int', 'id_board' => 'int'),
			array($club['id_club'], $id_board),
			array('id_club', 'id_board')
		);

		self::forget($club['id_club']);

		// Extra boards must inherit current access and bans too, not just the
		// original club type. The main board is synced during activate().
		if ($parent)
			self::syncAccess($club['id_club']);

		return $id_board;
	}

	public static function removeBoard(array $club, $id_board)
	{
		global $smcFunc, $sourcedir;

		$id_board = (int) $id_board;
		if ($id_board === $club['id_board'] || !in_array($id_board, self::clubBoards($club['id_club']), true))
			return false;

		require_once($sourcedir . '/Subs-Boards.php');
		deleteBoards(array($id_board), $club['id_board']);

		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_boards WHERE id_board = {int:b}', array('b' => $id_board));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_moderators WHERE id_board = {int:b}', array('b' => $id_board));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions_view WHERE id_board = {int:b}', array('b' => $id_board));
		self::forget($club['id_club']);
		self::cleanRsvps();

		return true;
	}

	/**
	 * Delete a club with its boards (and all their topics), group and data.
	 */
	public static function delete($id_club)
	{
		global $smcFunc, $sourcedir;

		$club = self::load($id_club, true);
		if (!$club)
			return;

		$boards = self::clubBoards($club['id_club']);

		if (!empty($boards))
		{
			require_once($sourcedir . '/Subs-Boards.php');
			deleteBoards($boards, null);
		}

		$groups = array_values(array_filter(array($club['id_group'], $club['id_ban_group'])));
		if (!empty($groups))
		{
			require_once($sourcedir . '/Subs-Membergroups.php');
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions WHERE id_group IN ({array_int:g})', array('g' => $groups));
			if (function_exists('deleteMembergroups'))
				deleteMembergroups($groups);
		}

		if ($club['id_profile'] > 0)
		{
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions WHERE id_profile = {int:p}', array('p' => $club['id_profile']));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}permission_profiles WHERE id_profile = {int:p}', array('p' => $club['id_profile']));
		}

		if (!empty($boards))
		{
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_moderators WHERE id_board IN ({array_int:b})', array('b' => $boards));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}board_permissions_view WHERE id_board IN ({array_int:b})', array('b' => $boards));
		}

		foreach (array('clubs', 'club_members', 'club_boards') as $table)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_club = {int:c}', array('c' => $club['id_club']));

		if (!empty($club['icon']))
			@unlink(self::iconDir() . '/' . basename($club['icon']));

		self::forget($club['id_club']);
		self::cleanRsvps();
		updateSettings(array('settings_updated' => time()));
	}

	public static function cleanRsvps()
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT r.id_event
			FROM {db_prefix}club_rsvp AS r
				LEFT JOIN {db_prefix}calendar AS cal ON (cal.id_event = r.id_event)
			WHERE cal.id_event IS NULL',
			array()
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		if (!empty($ids))
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_rsvp WHERE id_event IN ({array_int:ids})', array('ids' => $ids));
	}

	/* ------------------------------------------------------------------
	 * Alerts
	 * ---------------------------------------------------------------- */

	/**
	 * Send alerts about a club. Action: request, approved, denied, invited.
	 */
	public static function alert(array $to, $action, $actor_id, $actor_name, array $club)
	{
		global $smcFunc, $scripturl;

		$to = array_values(array_unique(array_filter(array_map('intval', $to))));
		$to = array_values(array_diff($to, array((int) $actor_id)));
		if (empty($to) || !self::setting('alerts', 1))
			return;

		$extra = json_encode(array(
			'club_name' => $club['name'],
			'club_link' => $scripturl . '?action=clubs;sa=view;club=' . $club['id_club'] . ($action === 'request' ? ';tab=members' : ''),
		));

		$rows = array();
		foreach ($to as $id)
			$rows[] = array(time(), $id, (int) $actor_id, (string) $actor_name, 'member', (int) $actor_id, 'club_' . $action, 0, $extra);

		$smcFunc['db_insert']('insert',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$rows,
			array('id_alert')
		);

		updateMemberData($to, array('alerts' => '+'));
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	public static function plain($bbc, $length = 200)
	{
		$text = preg_replace('~\[/?[a-z*][^\]]*\]~i', '', (string) $bbc);
		$text = trim(preg_replace('~\s+~u', ' ', strip_tags(str_replace(array('<br>', '<br />'), ' ', $text))));

		return function_exists('shorten_subject') ? shorten_subject($text, $length) : mb_substr($text, 0, $length);
	}

	public static function iconDir()
	{
		global $settings;

		return $settings['default_theme_dir'] . '/images/clubs';
	}

	public static function iconUrl(array $club)
	{
		global $settings;

		return !empty($club['icon']) && preg_match('~^[a-z0-9_.-]+$~i', $club['icon']) ? $settings['default_theme_url'] . '/images/clubs/' . $club['icon'] : '';
	}

	/**
	 * Check an uploaded image and re-draw it as a 256px PNG.
	 *
	 * @return string File name, or '' with an error key in $error.
	 */
	public static function saveIcon($tmp, $size, &$error)
	{
		if ($size > 2 * 1024 * 1024)
		{
			$error = 'clubs_error_icon_size';
			return '';
		}

		$binary = file_get_contents($tmp);
		$info = @getimagesizefromstring($binary);
		$types = array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG);
		if (defined('IMAGETYPE_WEBP'))
			$types[] = IMAGETYPE_WEBP;

		if (empty($info) || !in_array($info[2], $types, true) || $info[0] > 4000 || $info[1] > 4000 || !function_exists('imagecreatefromstring'))
		{
			$error = 'clubs_error_icon_type';
			return '';
		}

		$src = @imagecreatefromstring($binary);
		if ($src === false)
		{
			$error = 'clubs_error_icon_type';
			return '';
		}

		// Square crop from the centre, then resize.
		$w = imagesx($src);
		$h = imagesy($src);
		$side = min($w, $h);
		$out = 256;
		$dst = imagecreatetruecolor($out, $out);
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
		imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $out, $out, $side, $side);

		$dir = self::iconDir();
		if (!is_dir($dir))
			@mkdir($dir, 0755, true);

		$name = 'club_' . bin2hex(random_bytes(6)) . '.png';
		$ok = is_writable($dir) && imagepng($dst, $dir . '/' . $name);
		imagedestroy($src);
		imagedestroy($dst);

		if (!$ok)
		{
			$error = 'clubs_error_icon_dir';
			return '';
		}

		return $name;
	}

	public static function calendarEnabled()
	{
		global $modSettings;

		return !empty($modSettings['cal_enabled']) && self::setting('events', 1);
	}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ---------------------------------------------------------------- */

	public static function actions(&$actionArray)
	{
		$actionArray['clubs'] = array('Clubs-Pages.php', 'Clubs_Pages::action');
	}

	public static function menuButtons(&$buttons)
	{
		global $txt, $scripturl;

		if (!self::enabled() || !self::setting('show_menu', 1))
			return;

		loadLanguage('Clubs');

		$button = array('clubs' => array(
			'title' => $txt['clubs_menu'],
			'href' => $scripturl . '?action=clubs',
			'show' => allowedTo('clubs_view'),
			'icon' => 'members',
		));

		$keys = array_keys($buttons);
		$after = in_array('activity', $keys, true) ? 'activity' : 'home';
		$pos = array_search($after, $keys, true);
		$buttons = $pos === false ? $button + $buttons : array_slice($buttons, 0, $pos + 1, true) + $button + array_slice($buttons, $pos + 1, null, true);
	}

	/**
	 * integrate_load_theme: show the club header above club boards and topics.
	 */
	public static function loadTheme()
	{
		global $board, $context, $scripturl;

		if (!self::enabled())
			return;

		loadCSSFile('clubs.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_clubs');

		if (empty($board) || isset($_REQUEST['xml']) || isset($_REQUEST['api']))
			return;

		$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
		if ($action !== '' && $action !== 'post')
			return;

		$map = self::boardMap();
		if (!isset($map[(int) $board]))
			return;

		$club = self::load($map[(int) $board]);
		if (!$club)
			return;

		loadLanguage('Clubs');
		loadTemplate('Clubs');

		$context['club_header'] = array(
			'club' => $club,
			'icon' => self::iconUrl($club),
			'url' => $scripturl . '?action=clubs;sa=view;club=' . $club['id_club'],
			'active' => 'discussions',
			'can_see_content' => true,
			'is_leader' => self::isLeader($club),
			'events' => self::calendarEnabled(),
		);
		$context['template_layers'][] = 'club_header';
	}

	/**
	 * integrate_mark_read_button runs on the board index after the
	 * categories are built; use it to hide the Clubs category there.
	 */
	public static function boardIndex()
	{
		global $context;

		if (!self::enabled() || !self::setting('hide_index', 1))
			return;

		$cat = (int) self::setting('category', 0);
		if ($cat > 0 && isset($context['categories'][$cat]))
			unset($context['categories'][$cat]);
	}

	public static function deleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		// Clubs owned by deleted members pass to a leader, or the oldest member.
		$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs WHERE id_owner IN ({array_int:u})', array('u' => $users));
		$owned = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$owned[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_members WHERE id_member IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}club_rsvp WHERE id_member IN ({array_int:u})', array('u' => $users));

		foreach ($owned as $id_club)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}club_members
				WHERE id_club = {int:c}
					AND role IN ({array_string:roles})
				ORDER BY CASE WHEN role = {string:leader} THEN 0 ELSE 1 END, joined
				LIMIT 1',
				array('c' => $id_club, 'roles' => array('leader', 'member'), 'leader' => 'leader')
			);
			$row = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$new = empty($row) ? 0 : (int) $row[0];
			$smcFunc['db_query']('', 'UPDATE {db_prefix}clubs SET id_owner = {int:o} WHERE id_club = {int:c}', array('o' => $new, 'c' => $id_club));
			if ($new)
				self::setRole($id_club, $new, 'owner');
			self::updateCount($id_club);
			self::syncModerators($id_club);
		}

		$request = $smcFunc['db_query']('', 'SELECT id_club FROM {db_prefix}clubs', array());
		$all = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$all[] = (int) $row[0];
		$smcFunc['db_free_result']($request);
		foreach ($all as $id_club)
			self::updateCount($id_club);
	}

	public static function fetchAlerts(&$alerts, &$formats = array())
	{
		loadLanguage('Clubs');
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('Clubs');
		$permissionList['membergroup']['clubs_view'] = array(false, 'general');
		$permissionList['membergroup']['clubs_join'] = array(false, 'general');
		$permissionList['membergroup']['clubs_create'] = array(false, 'general');
		$permissionList['membergroup']['clubs_manage_all'] = array(false, 'maintenance');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		foreach (array('clubs_join', 'clubs_create', 'clubs_manage_all') as $p)
			$context['non_guest_permissions'][] = $p;
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('Clubs');

		$admin_areas['config']['areas']['clubs'] = array(
			'label' => $txt['clubs_title'],
			'file' => 'Clubs-Pages.php',
			'function' => 'Clubs_Pages::admin',
			'icon' => 'members',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'settings' => array($txt['clubs_tab_settings']),
				'list' => array($txt['clubs_tab_list']),
			),
		);
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('Clubs');
		$context['copyrights']['mods'][] = sprintf($txt['clubs_credits'], self::VERSION);
	}
}
