<?php

/**
 * Trust Levels: admin panel.
 *
 * @package TrustLevels
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

class TrustLevels_Admin
{
	public static function main()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('admin_forum');

		loadLanguage('TrustLevels');
		loadTemplate('TrustLevels');
		loadCSSFile('trustlevels.css', array('minimize' => true, 'seed' => TrustLevels::VERSION), 'smf_trustlevels');
		loadJavaScriptFile('trustlevels-admin.js', array('minimize' => true, 'defer' => true, 'seed' => TrustLevels::VERSION), 'smf_trustlevels_admin');

		$subActions = array(
			'levels' => 'levels',
			'members' => 'members',
			'log' => 'log',
			'recalc' => 'recalc',
			'settings' => 'settings',
		);
		$sa = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'levels';

		$context['page_title'] = $txt['tl_title'];
		$context['tl_url'] = $scripturl . '?action=admin;area=trustlevels';
		$context['tl_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['tl_title'],
			'description' => $txt['tl_desc'],
			'tabs' => array('levels' => array(), 'members' => array(), 'log' => array(), 'recalc' => array(), 'settings' => array()),
		);

		$context['tl_notice'] = isset($_SESSION['tl_notice']) ? $_SESSION['tl_notice'] : null;
		unset($_SESSION['tl_notice']);

		$method = $subActions[$sa];
		self::$method();
	}

	/**
	 * Groups that can be given by a level: normal (not post-count)
	 * groups, but not Administrator or the special Moderator group.
	 */
	public static function assignableGroups()
	{
		global $smcFunc;

		$groups = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE min_posts = {int:no_posts}
				AND id_group NOT IN ({array_int:excluded})
			ORDER BY group_name',
			array('no_posts' => -1, 'excluded' => array(1, 3))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[(int) $row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);

		return $groups;
	}

	public static function allGroups()
	{
		global $smcFunc;

		$groups = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			WHERE min_posts = {int:no_posts}
				AND id_group != {int:moderator}
			ORDER BY id_group',
			array('no_posts' => -1, 'moderator' => 3)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[(int) $row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);

		return $groups;
	}

	/* ------------------------------------------------------------------
	 * Levels
	 * ---------------------------------------------------------------- */

	public static function levels()
	{
		global $context, $txt, $smcFunc;

		$groups = self::assignableGroups();
		$context['tl_errors'] = array();

		if (isset($_POST['save']))
		{
			checkSession();

			$errors = array();
			$levels = self::validateLevels(isset($_POST['level']) && is_array($_POST['level']) ? $_POST['level'] : array(), $groups, $errors);

			if (empty($errors))
			{
				$old_count = count(TrustLevels::levels());
				updateSettings(array('tl_levels' => json_encode($levels)));
				TrustLevels::resetCache();

				// Members above the new top level are moved down to it.
				$top = count($levels) - 1;
				if (count($levels) < $old_count)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}trust_members
						SET level = {int:top}
						WHERE level > {int:top}',
						array('top' => $top)
					);

				$_SESSION['tl_notice'] = $txt['tl_levels_saved'];
				redirectexit('action=admin;area=trustlevels;sa=levels');
			}

			$context['tl_errors'] = $errors;
			$context['tl_levels'] = array_map(array('TrustLevels', 'normaliseLevel'), $levels);
		}
		else
			$context['tl_levels'] = TrustLevels::levels();

		$context['tl_groups'] = $groups;
		$context['sub_template'] = 'tl_admin_levels';
	}

	public static function validateLevels(array $input, array $groups, array &$errors)
	{
		global $txt;

		$levels = array();
		$used_groups = array();

		foreach ($input as $l)
		{
			if (!is_array($l))
				continue;

			$name = mb_substr(trim(strip_tags(isset($l['name']) ? (string) $l['name'] : '')), 0, 40);
			$n = count($levels);

			if ($name === '')
			{
				$errors[] = sprintf($txt['tl_err_name'], $n);
				$name = 'Level ' . $n;
			}

			$group = isset($l['group']) ? (int) $l['group'] : 0;
			if ($group > 0 && !isset($groups[$group]))
			{
				$errors[] = sprintf($txt['tl_err_group'], htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
				$group = 0;
			}
			if ($group > 0 && isset($used_groups[$group]))
				$errors[] = sprintf($txt['tl_err_group_dupe'], htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
			$used_groups[$group] = true;

			$req = array();
			foreach (TrustLevels::$req_keys as $key)
				$req[$key] = $n === 0 ? 0 : max(0, (int) (isset($l['req'][$key]) ? $l['req'][$key] : 0));
			$req['max_warning'] = isset($l['req']['max_warning']) && trim((string) $l['req']['max_warning']) !== '' ? max(-1, min(100, (int) $l['req']['max_warning'])) : -1;

			$limits = array();
			foreach (TrustLevels::$limit_keys as $key)
			{
				$v = isset($l['limits'][$key]) ? trim((string) $l['limits'][$key]) : '';
				$limits[$key] = $v === '' ? -1 : max(-1, min(100000, (int) $v));
			}
			$limits['sig_links'] = !empty($l['limits']['sig_links']);

			$levels[] = array(
				'name' => $name,
				'description' => mb_substr(trim(strip_tags(isset($l['description']) ? (string) $l['description'] : '')), 0, 255),
				'color' => isset($l['color']) && preg_match('~^#[0-9a-f]{6}$~i', $l['color']) ? strtolower($l['color']) : '#7a7a7a',
				'group' => $group,
				'manual' => $n > 0 && !empty($l['manual']),
				'req' => $req,
				'limits' => $limits,
				'pm_subject' => mb_substr(trim(strip_tags(isset($l['pm_subject']) ? (string) $l['pm_subject'] : '')), 0, 100),
				'pm_body' => mb_substr(str_replace("\r", '', isset($l['pm_body']) ? (string) $l['pm_body'] : ''), 0, 5000),
			);

			if (count($levels) >= TrustLevels::MAX_LEVELS)
				break;
		}

		if (count($levels) < 2)
			$errors[] = $txt['tl_err_count'];

		return $levels;
	}

	/* ------------------------------------------------------------------
	 * Members overview
	 * ---------------------------------------------------------------- */

	public static function members()
	{
		global $context, $smcFunc, $scripturl;

		$levels = TrustLevels::levels();

		$counts = array_fill(0, count($levels), 0);
		$request = $smcFunc['db_query']('', '
			SELECT level, COUNT(*) AS n
			FROM {db_prefix}trust_members
			GROUP BY level',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (isset($counts[(int) $row['level']]))
				$counts[(int) $row['level']] = (int) $row['n'];
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}members', array());
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$filter = isset($_GET['level']) && $_GET['level'] !== '' ? max(0, min(count($levels) - 1, (int) $_GET['level'])) : null;
		$locked_only = !empty($_GET['locked']);

		$where = '1=1';
		if ($filter !== null)
			$where .= ' AND tl.level = {int:level}';
		if ($locked_only)
			$where .= ' AND tl.locked = {int:yes}';

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}trust_members AS tl
			WHERE ' . $where,
			array('level' => (int) $filter, 'yes' => 1)
		);
		list ($matching) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$per_page = 30;
		$base = $context['tl_url'] . ';sa=members' . ($filter !== null ? ';level=' . $filter : '') . ($locked_only ? ';locked=1' : '');
		$context['page_index'] = constructPageIndex($base, $start, (int) $matching, $per_page);

		$context['tl_members'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT tl.id_member, tl.level, tl.locked, tl.updated, mem.real_name, mem.posts, mem.date_registered, mem.last_login
			FROM {db_prefix}trust_members AS tl
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = tl.id_member)
			WHERE ' . $where . '
			ORDER BY tl.updated DESC
			LIMIT {int:start}, {int:per_page}',
			array('level' => (int) $filter, 'yes' => 1, 'start' => $start, 'per_page' => $per_page)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['tl_members'][] = array(
				'id' => (int) $row['id_member'],
				'name' => $row['real_name'],
				'href' => $scripturl . '?action=profile;area=trustlevel;u=' . $row['id_member'],
				'level' => (int) $row['level'],
				'locked' => !empty($row['locked']),
				'updated' => timeformat($row['updated']),
				'posts' => comma_format($row['posts']),
				'registered' => timeformat($row['date_registered'], false),
				'last_login' => empty($row['last_login']) ? '-' : timeformat($row['last_login']),
			);
		$smcFunc['db_free_result']($request);

		$context['tl_counts'] = $counts;
		$context['tl_total'] = (int) $total;
		$context['tl_uncalculated'] = max(0, (int) $total - array_sum($counts));
		$context['tl_filter'] = $filter;
		$context['tl_locked_only'] = $locked_only;
		$context['sub_template'] = 'tl_admin_members';
	}

	/* ------------------------------------------------------------------
	 * Log
	 * ---------------------------------------------------------------- */

	public static function log()
	{
		global $context, $smcFunc, $scripturl;

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}trust_log', array());
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['tl_url'] . ';sa=log', $start, (int) $total, 40);

		$context['tl_log'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT l.id_member, l.action, l.old_level, l.new_level, l.logged, l.reason, l.id_changer,
				COALESCE(m.real_name, {string:empty}) AS member_name, COALESCE(c.real_name, {string:empty}) AS changer_name
			FROM {db_prefix}trust_log AS l
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = l.id_member)
				LEFT JOIN {db_prefix}members AS c ON (c.id_member = l.id_changer)
			ORDER BY l.id_log DESC
			LIMIT {int:start}, 40',
			array('start' => $start, 'empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['tl_log'][] = array(
				'member' => $row['member_name'] !== '' ? '<a href="' . $scripturl . '?action=profile;area=trustlevel;u=' . $row['id_member'] . '">' . $row['member_name'] . '</a>' : '#' . $row['id_member'],
				'action' => $row['action'] !== '' ? $row['action'] : 'level',
				'old' => (int) $row['old_level'],
				'new' => (int) $row['new_level'],
				'time' => timeformat($row['logged']),
				'reason' => $row['reason'],
				'changer' => (int) $row['id_changer'] > 0 ? ($row['changer_name'] !== '' ? $row['changer_name'] : '#' . $row['id_changer']) : '',
			);
		$smcFunc['db_free_result']($request);

		$context['sub_template'] = 'tl_admin_log';
	}

	/* ------------------------------------------------------------------
	 * Recalculation (batched)
	 * ---------------------------------------------------------------- */

	public static function recalc()
	{
		global $context, $smcFunc;

		$context['tl_progress'] = null;

		if (isset($_GET['run']))
		{
			checkSession('get');

			@set_time_limit(120);

			$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
			$sync = !empty($_GET['sync']);
			$changed = isset($_GET['changed']) ? max(0, (int) $_GET['changed']) : 0;
			$batch = 200;

			$request = $smcFunc['db_query']('', 'SELECT COUNT(*), MAX(id_member) FROM {db_prefix}members', array());
			list ($total, $max_id) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);

			$ids = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE id_member > {int:start}
				ORDER BY id_member
				LIMIT {int:batch}',
				array('start' => $start, 'batch' => $batch)
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			if (!empty($ids))
			{
				$changed += TrustLevels::recalc($ids, $sync);
				$last = end($ids);

				$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}members WHERE id_member <= {int:last}', array('last' => $last));
				list ($done) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);

				$context['tl_progress'] = array(
					'percent' => $total > 0 ? min(100, (int) round($done / $total * 100)) : 100,
					'done' => (int) $done,
					'total' => (int) $total,
					'changed' => $changed,
					'next' => count($ids) === $batch && $last < $max_id
						? $context['tl_url'] . ';sa=recalc;run;start=' . $last . ';changed=' . $changed . ($sync ? ';sync=1' : '') . $context['tl_session']
						: '',
				);
			}
			else
				$context['tl_progress'] = array('percent' => 100, 'done' => (int) $total, 'total' => (int) $total, 'changed' => $changed, 'next' => '');
		}

		$context['sub_template'] = 'tl_admin_recalc';
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	public static function settings()
	{
		global $context, $txt, $sourcedir;

		require_once($sourcedir . '/ManageServer.php');

		$levels = array();
		foreach (TrustLevels::levels() as $i => $l)
			$levels[$i] = $i . ': ' . $l['name'];

		$config_vars = array(
			array('check', 'tl_enabled'),
			array('select', 'tl_demote', array(
				'never' => $txt['tl_demote_never'],
				'warnings' => $txt['tl_demote_warnings'],
				'always' => $txt['tl_demote_always'],
			), 'subtext' => $txt['tl_demote_desc']),
			array('select', 'tl_exempt_groups', self::allGroups(), 'multiple' => true, 'subtext' => $txt['tl_exempt_groups_desc']),
			array('int', 'tl_probation_days', 'min' => 0, 'max' => 365, 'postinput' => $txt['tl_days'], 'subtext' => $txt['tl_probation_days_desc']),
			array('check', 'tl_protect_groups', 'subtext' => $txt['tl_protect_groups_desc']),
			'',
			array('check', 'tl_allow_internal', 'subtext' => $txt['tl_allow_internal_desc']),
			array('check', 'tl_apply_pm', 'subtext' => $txt['tl_apply_pm_desc']),
			'',
			array('select', 'tl_badge', array(
				'off' => $txt['tl_badge_off'],
				'below_avatar' => $txt['tl_badge_below_avatar'],
				'icons' => $txt['tl_badge_icons'],
				'standard' => $txt['tl_badge_standard'],
			)),
			array('select', 'tl_badge_min', $levels, 'subtext' => $txt['tl_badge_min_desc']),
			array('check', 'tl_profile_public', 'subtext' => $txt['tl_profile_public_desc']),
			'',
			array('check', 'tl_promotion_pm', 'subtext' => $txt['tl_promotion_pm_desc']),
			array('int', 'tl_pm_from', 'subtext' => $txt['tl_pm_from_desc']),
		);

		$context['post_url'] = $context['tl_url'] . ';sa=settings;save';
		$context['settings_title'] = $txt['tl_tab_settings'];

		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['tl_pm_from'] = max(1, isset($_POST['tl_pm_from']) ? (int) $_POST['tl_pm_from'] : 1);
			$_POST['tl_probation_days'] = max(0, min(365, isset($_POST['tl_probation_days']) ? (int) $_POST['tl_probation_days'] : 0));
			if (!isset($_POST['tl_exempt_groups']))
				$_POST['tl_exempt_groups'] = array();

			saveDBSettings($config_vars);
			TrustLevels::resetCache();

			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=trustlevels;sa=settings');
		}

		prepareDBSettingContext($config_vars);
		$context['sub_template'] = 'show_settings';
	}
}
