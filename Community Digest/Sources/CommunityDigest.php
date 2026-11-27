<?php
/**
 * Community Digest - hooks, member pages, profile preferences and admin panel.
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.7
 */

if (!defined('SMF'))
	die('No direct access...');

class CommunityDigest
{
	const VERSION = '1.1.7';

	public static function boot()
	{
		global $sourcedir;

		require_once($sourcedir . '/CommunityDigest-Engine.php');
		require_once($sourcedir . '/CommunityDigest-Integrations.php');
		loadLanguage('CommunityDigest');
	}

	private static function loadAssets()
	{
		loadTemplate('CommunityDigest');
		loadCSSFile('community_digest.css', array('minimize' => true), 'smf_community_digest');
	}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ------------------------------------------------------------------ */

	public static function hookActions(&$actions)
	{
		$actions['digest'] = array('CommunityDigest.php', 'CommunityDigest::action');
	}

	public static function hookMenu(&$buttons)
	{
		global $modSettings, $txt, $scripturl, $user_info, $context;

		if (empty($modSettings['cd_enabled']) || empty($modSettings['cd_menu_button']) || $user_info['is_guest'])
			return;

		loadLanguage('CommunityDigest');

		$new = array();
		foreach ($buttons as $key => $button)
		{
			$new[$key] = $button;
			if ($key === 'home')
				$new['digest'] = array(
					'title' => $txt['cd_menu'],
					'href' => $scripturl . '?action=digest',
					'show' => true,
					'icon' => 'mail',
					'is_selected' => isset($context['current_action']) && $context['current_action'] === 'digest',
					'sub_buttons' => array(),
				);
		}
		$buttons = $new;
	}

	public static function hookAdminAreas(&$admin_areas)
	{
		global $txt, $sourcedir;

		loadLanguage('CommunityDigest');

		$admin_areas['config']['areas']['communitydigest'] = array(
			'label' => $txt['cd_admin_title'],
			'file' => 'CommunityDigest.php',
			'function' => 'CommunityDigest::admin',
			'icon' => 'mail',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'settings' => array($txt['cd_admin_settings']),
				'content' => array($txt['cd_admin_content']),
				'test' => array($txt['cd_admin_test']),
				'log' => array($txt['cd_admin_log']),
			),
		);

		// Make the task callable when an admin uses "Run now" in Scheduled Tasks.
		if (isset($_REQUEST['area']) && $_REQUEST['area'] === 'scheduledtasks')
		{
			require_once($sourcedir . '/CommunityDigest-Engine.php');
			require_once($sourcedir . '/CommunityDigest-Integrations.php');
		}
	}

	public static function hookProfileAreas(&$profile_areas)
	{
		global $txt, $modSettings;

		loadLanguage('CommunityDigest');

		$profile_areas['edit_profile']['areas']['digest'] = array(
			'label' => $txt['cd_profile_area'],
			'file' => 'CommunityDigest.php',
			'function' => 'CommunityDigest::profile',
			'icon' => 'mail',
			'enabled' => !empty($modSettings['cd_enabled']),
			'permission' => array(
				'own' => array('profile_extra_own'),
				'any' => array('profile_extra_any'),
			),
		);
	}

	public static function hookCredits()
	{
		global $context, $txt;

		loadLanguage('CommunityDigest');
		$context['copyrights']['mods'][] = sprintf($txt['cd_credits'], self::VERSION);
	}

	public static function hookAlertTypes(&$alert_types, &$group_options)
	{
		loadLanguage('CommunityDigest');
		$alert_types['digest'] = array(
			'digest_ready' => array('alert' => 'yes', 'email' => 'never'),
		);
	}

	public static function hookFetchAlerts(&$alerts, &$formats)
	{
		global $txt, $scripturl;

		loadLanguage('CommunityDigest');

		$found = false;
		foreach ($alerts as $id => $alert)
		{
			if ($alert['content_type'] !== 'digest')
				continue;

			$found = true;
			$extra = is_string($alert['extra']) ? (array) json_decode($alert['extra'], true) : (array) $alert['extra'];
			$items = isset($extra['items']) ? (int) $extra['items'] : 0;
			$alerts[$id]['text'] = sprintf($txt['cd_alert_text'], $scripturl . '?action=digest', $items);
			$alerts[$id]['target_href'] = $scripturl . '?action=digest';
			$alerts[$id]['icon'] = '<span class="main_icons mail"></span>';
		}

		// The preference label and alert text share a key in SMF; swap in the display text here.
		if ($found)
			$txt['alert_digest_ready'] = strtr($txt['cd_alert_text'], array('%1$s' => '{scripturl}?action=digest', '%2$d' => '{items}'));
	}

	public static function hookModButtons(&$mod_buttons)
	{
		global $context, $scripturl, $smcFunc, $modSettings;

		if (empty($modSettings['cd_enabled']) || empty($context['current_topic']) || !allowedTo('moderate_board'))
			return;

		loadLanguage('CommunityDigest');

		$request = $smcFunc['db_query']('', '
			SELECT 1 FROM {db_prefix}digest_excluded WHERE id_topic = {int:topic}',
			array('topic' => $context['current_topic'])
		);
		$excluded = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		$mod_buttons['digest_exclude'] = array(
			'text' => $excluded ? 'cd_include_topic' : 'cd_exclude_topic',
			'url' => $scripturl . '?action=digest;sa=exclude;topic=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id'],
		);
	}

	public static function hookWhosOnline($actions)
	{
		global $txt;

		if (isset($actions['action']) && $actions['action'] === 'digest')
		{
			loadLanguage('CommunityDigest');
			return $txt['cd_whoall'];
		}

		return '';
	}

	public static function hookDeleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		foreach (array('digest_prefs', 'digest_items', 'digest_log', 'digest_email_queue') as $table)
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}' . $table . '
				WHERE id_member IN ({array_int:users})',
				array('users' => $users)
			);
	}

	/* ------------------------------------------------------------------
	 * action=digest
	 * ------------------------------------------------------------------ */

	public static function action()
	{
		global $modSettings;

		self::boot();

		$sa = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : 'view';

		// Unsubscribing works without being logged in (token-authenticated).
		if ($sa === 'unsubscribe')
			return self::unsubscribe();

		is_not_guest();

		if (empty($modSettings['cd_enabled']))
			fatal_lang_error('cd_disabled', false);

		switch ($sa)
		{
			case 'open':
			case 'read':
			case 'readall':
				return self::markRead($sa);
			case 'refresh':
				return self::dashboard(true);
			case 'preview':
				return self::preview();
			case 'exclude':
				return self::toggleExclude();
			default:
				return self::dashboard(false);
		}
	}

	private static function currentMember($id = 0)
	{
		global $user_info;

		$id = $id ?: $user_info['id'];
		$members = CommunityDigestEngine::loadMembers(array($id));
		if (empty($members[$id]))
			fatal_lang_error('cd_no_member', false);

		return $members[$id];
	}

	private static function dashboard($forceRefresh)
	{
		global $context, $txt, $scripturl, $user_info;

		self::loadAssets();

		$m = self::currentMember();
		$prefs = CommunityDigestEngine::loadPrefs($m['id_member']);
		$stored = CommunityDigestEngine::loadStoredBatch($m, $prefs);

		$refreshHours = max(1, (int) CommunityDigestEngine::setting('cd_dashboard_refresh_hours', 12));
		$stale = empty($stored['batch']) || $stored['batch'] < time() - $refreshHours * 3600;

		if ($forceRefresh)
		{
			checkSession('get');
			// Light rate limit on manual refreshes.
			if (!empty($stored['batch']) && $stored['batch'] > time() - 300)
				$forceRefresh = false;
		}

		// Avoid rebuilding on every page view when there is genuinely nothing new.
		if ($stale && !$forceRefresh && !empty($_SESSION['cd_last_build']) && $_SESSION['cd_last_build'] > time() - 600)
			$stale = false;

		if ($stale || $forceRefresh)
		{
			$_SESSION['cd_last_build'] = time();
			$period = CommunityDigestEngine::periodSeconds($prefs['frequency']);
			$digest = CommunityDigestEngine::build($m, $prefs, time() - $period);
			CommunityDigestEngine::storeItems($m['id_member'], $digest);
			$stored = CommunityDigestEngine::loadStoredBatch($m, $prefs);

			if ($forceRefresh)
				redirectexit('action=digest');
		}

		$live = CommunityDigestEngine::build($m, $prefs, time() - CommunityDigestEngine::periodSeconds($prefs['frequency']), CommunityDigestEngine::$liveSections);

		$context['cd'] = array(
			'sections' => $stored['sections'],
			'live' => $live['sections'],
			'count' => $stored['count'],
			'unread' => $stored['unread'],
			'batch_time' => $stored['batch'] ? timeformat($stored['batch']) : '',
			'prefs' => $prefs,
			'session' => $context['session_var'] . '=' . $context['session_id'],
			'prefs_url' => $scripturl . '?action=profile;area=digest',
			'email_on' => in_array($prefs['delivery'], array(CommunityDigestEngine::DELIVER_EMAIL, CommunityDigestEngine::DELIVER_BOTH)) && $prefs['frequency'] > 0,
			'is_default' => !empty($prefs['is_default']),
		);

		$context['page_title'] = $txt['cd_dashboard_title'];
		$context['linktree'][] = array('url' => $scripturl . '?action=digest', 'name' => $txt['cd_dashboard_title']);
		$context['sub_template'] = 'cd_dashboard';
		$context['robot_no_index'] = true;
	}

	private static function markRead($sa)
	{
		global $user_info;

		checkSession('get');
		$topic = isset($_GET['topic']) ? (int) $_GET['topic'] : 0;

		if ($sa === 'readall')
			CommunityDigestEngine::markRead($user_info['id']);
		elseif ($topic > 0)
			CommunityDigestEngine::markRead($user_info['id'], $topic);

		if ($sa === 'open' && $topic > 0)
			redirectexit('topic=' . $topic . '.new#new');

		redirectexit('action=digest');
	}

	private static function preview()
	{
		global $context;

		$id = isset($_GET['u']) ? (int) $_GET['u'] : 0;
		if ($id && !allowedTo('admin_forum'))
			$id = 0;

		$m = self::currentMember($id);
		$prefs = CommunityDigestEngine::loadPrefs($m['id_member']);
		if (empty($prefs['token']))
			$prefs['token'] = 'preview';
		if ($prefs['frequency'] == 0)
			$prefs['frequency'] = CommunityDigestEngine::FREQ_WEEKLY;

		$digest = CommunityDigestEngine::build($m, $prefs, time() - CommunityDigestEngine::periodSeconds($prefs['frequency']));
		list (, $html) = CommunityDigestEngine::renderEmail($m, $prefs, $digest);

		loadTemplate('CommunityDigest');
		$context['cd_raw_html'] = $html;
		$context['template_layers'] = array();
		$context['sub_template'] = 'cd_raw';
	}

	private static function toggleExclude()
	{
		global $smcFunc, $user_info, $topic;

		checkSession('get');

		$id = isset($_GET['topic']) ? (int) $_GET['topic'] : (int) $topic;
		if ($id <= 0)
			fatal_lang_error('not_a_topic', false);

		// Always verify the requested topic's own board. The current $board global
		// might refer to a different board if a moderator submits a crafted URL.
		$request = $smcFunc['db_query']('', 'SELECT id_board FROM {db_prefix}topics WHERE id_topic = {int:t}', array('t' => $id));
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		if (empty($row))
			fatal_lang_error('not_a_topic', false);
		$targetBoard = (int) $row[0];
		if (!allowedTo('moderate_forum') && !allowedTo('moderate_board', $targetBoard))
			isAllowedTo('moderate_board', $targetBoard);

		$request = $smcFunc['db_query']('', 'SELECT 1 FROM {db_prefix}digest_excluded WHERE id_topic = {int:t}', array('t' => $id));
		$exists = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		if ($exists)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_excluded WHERE id_topic = {int:t}', array('t' => $id));
		else
		{
			$smcFunc['db_insert']('ignore',
				'{db_prefix}digest_excluded',
				array('id_topic' => 'int', 'id_member' => 'int', 'excluded_time' => 'int'),
				array($id, $user_info['id'], time()),
				array('id_topic')
			);
			// Pull it from any stored dashboards straight away.
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_items WHERE id_topic = {int:t}', array('t' => $id));
		}

		logAction($exists ? 'cd_include' : 'cd_exclude', array('topic' => $id), 'moderate');
		redirectexit('topic=' . $id . '.0');
	}

	private static function unsubscribe()
	{
		global $context, $txt, $smcFunc, $scripturl;

		self::loadAssets();

		$id = isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0;
		$token = isset($_REQUEST['t']) ? (string) $_REQUEST['t'] : '';
		$prefs = $id ? CommunityDigestEngine::loadPrefs($id) : null;

		$context['page_title'] = $txt['cd_unsub_title'];
		$context['sub_template'] = 'cd_message';
		$context['robot_no_index'] = true;

		if (!$prefs || $prefs['is_default'] || $token === '' || !hash_equals($prefs['token'], $token))
		{
			$context['cd_message'] = array('title' => $txt['cd_unsub_title'], 'body' => $txt['cd_unsub_invalid'], 'form' => null, 'error' => true);
			return;
		}

		// Confirm on POST only, so mail scanners that prefetch links don't unsubscribe people.
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
		{
			$prefs['delivery'] = CommunityDigestEngine::DELIVER_ONSITE;
			CommunityDigestEngine::savePrefs($id, $prefs);
			CommunityDigestEngine::log($id, 'email', CommunityDigestEngine::LOG_SKIPPED, 0, 'Unsubscribed via email link');

			$context['cd_message'] = array('title' => $txt['cd_unsub_title'], 'body' => sprintf($txt['cd_unsub_done'], $scripturl . '?action=profile;area=digest'), 'form' => null, 'error' => false);
			return;
		}

		$context['cd_message'] = array(
			'title' => $txt['cd_unsub_title'],
			'body' => $txt['cd_unsub_confirm'],
			'form' => array(
				'action' => $scripturl . '?action=digest;sa=unsubscribe',
				'fields' => array('u' => $id, 't' => $smcFunc['htmlspecialchars']($token)),
				'button' => $txt['cd_unsub_button'],
			),
			'error' => false,
		);
	}

	/* ------------------------------------------------------------------
	 * Profile preferences
	 * ------------------------------------------------------------------ */

	public static function profile($memID = 0)
	{
		global $context, $txt, $scripturl, $smcFunc;

		self::boot();
		self::loadAssets();

		$memID = (int) ($memID ?: $context['id_member']);
		$m = self::currentMember($memID);
		$prefs = CommunityDigestEngine::loadPrefs($memID);
		$allowed = CommunityDigestEngine::allowedFrequencies();
		$tree = CommunityDigestEngine::boardTree($m);

		if (isset($_POST['cd_save']))
		{
			checkSession();

			$freq = isset($_POST['frequency']) ? (int) $_POST['frequency'] : 0;
			$prefs['frequency'] = in_array($freq, $allowed) ? $freq : CommunityDigestEngine::FREQ_OFF;

			$delivery = isset($_POST['delivery']) ? (int) $_POST['delivery'] : 0;
			$prefs['delivery'] = in_array($delivery, array(0, 1, 2)) ? $delivery : 0;

			$prefs['weekly_day'] = max(0, min(6, isset($_POST['weekly_day']) ? (int) $_POST['weekly_day'] : 1));
			$prefs['send_hour'] = max(0, min(23, isset($_POST['send_hour']) ? (int) $_POST['send_hour'] : 8));

			$sections = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : array();
			$availableSections = CommunityDigestEngine::selectableSections($m);
			$prefs['sections'] = array_values(array_intersect($availableSections, $sections));
			// No boxes checked means no sections. Empty array is reserved for
			// default/all, so persist an explicit sentinel instead.
			if (empty($prefs['sections']))
				$prefs['sections'] = array('__none__');
			elseif (count($prefs['sections']) === count($availableSections))
				$prefs['sections'] = array();

			$visible = array_flip(CommunityDigestEngine::visibleBoards($m, false));
			$boards = isset($_POST['boards']) && is_array($_POST['boards']) ? array_map('intval', $_POST['boards']) : array();
			$prefs['boards'] = array_values(array_filter(array_unique($boards), function ($b) use ($visible) { return isset($visible[$b]); }));

			$keywords = array();
			foreach (explode(',', isset($_POST['keywords']) ? (string) $_POST['keywords'] : '') as $kw)
			{
				$kw = $smcFunc['strtolower'](trim(strip_tags($kw)));
				$kw = $smcFunc['substr'](preg_replace('~[^\p{L}\p{N} \-\.\+#]~u', '', $kw), 0, 30);
				if ($kw !== '' && !in_array($kw, $keywords))
					$keywords[] = $kw;
				if (count($keywords) >= 10)
					break;
			}
			$prefs['keywords'] = $keywords;

			$prefs['next_send'] = $prefs['frequency'] > 0
				? CommunityDigestEngine::computeNextSend($prefs['frequency'], $prefs['send_hour'], $prefs['weekly_day'], $m['timezone'])
				: 0;

			CommunityDigestEngine::savePrefs($memID, $prefs);
			redirectexit('action=profile;area=digest;u=' . $memID . ';saved');
		}

		$context['cd'] = array(
			'prefs' => $prefs,
			'allowed' => $allowed,
			'sections' => CommunityDigestEngine::selectableSections($m),
			'tree' => $tree,
			'saved' => isset($_GET['saved']),
			'next' => $prefs['next_send'] > 0 ? timeformat($prefs['next_send']) : '',
			'post_url' => $scripturl . '?action=profile;area=digest;u=' . $memID,
			'preview_url' => $scripturl . '?action=digest;sa=preview' . ($memID != $context['user']['id'] ? ';u=' . $memID : ''),
			'is_default' => !empty($prefs['is_default']),
		);

		$context['page_title'] = $txt['cd_profile_area'];
		$context['sub_template'] = 'cd_profile';
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	public static function admin()
	{
		global $context, $txt;

		self::boot();
		isAllowedTo('admin_forum');
		self::loadAssets();

		$subActions = array(
			'settings' => 'adminSettings',
			'content' => 'adminContent',
			'test' => 'adminTest',
			'log' => 'adminLog',
		);
		$sa = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'settings';

		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['cd_admin_title'],
			'description' => $txt['cd_admin_desc'],
			'tabs' => array(
				'settings' => array(),
				'content' => array('description' => $txt['cd_admin_content_desc']),
				'test' => array('description' => $txt['cd_admin_test_desc']),
				'log' => array('description' => $txt['cd_admin_log_desc']),
			),
		);
		$context['page_title'] = $txt['cd_admin_title'];

		call_user_func(array(__CLASS__, $subActions[$sa]));
	}

	public static function adminSettings($return_config = false)
	{
		global $context, $txt, $scripturl, $sourcedir;

		require_once($sourcedir . '/ManageServer.php');

		$freqOptions = array(1 => $txt['cd_freq_1'], 2 => $txt['cd_freq_2'], 3 => $txt['cd_freq_3']);
		$deliveryOptions = array(0 => $txt['cd_delivery_0'], 1 => $txt['cd_delivery_1'], 2 => $txt['cd_delivery_2']);

		$config_vars = array(
			array('check', 'cd_enabled'),
			array('check', 'cd_menu_button'),
			array('title', 'cd_hdr_delivery'),
			array('check', 'cd_allow_daily'),
			array('check', 'cd_allow_weekly'),
			array('check', 'cd_allow_monthly'),
			array('select', 'cd_default_frequency', $freqOptions),
			array('select', 'cd_default_delivery', $deliveryOptions, 'subtext' => $txt['cd_default_delivery_sub']),
			array('int', 'cd_default_hour', 'min' => 0, 'max' => 23),
			array('check', 'cd_alert_on_ready', 'subtext' => $txt['cd_alert_on_ready_sub']),
			array('check', 'cd_skip_empty'),
			array('int', 'cd_dashboard_refresh_hours', 'min' => 1, 'max' => 168),
			array('text', 'cd_email_accent', 'size' => 8, 'subtext' => $txt['cd_email_accent_sub']),
			array('title', 'cd_hdr_content'),
			array('int', 'cd_max_per_section', 'min' => 1, 'max' => 20),
			array('int', 'cd_max_total', 'min' => 1, 'max' => 100),
			array('int', 'cd_excerpt_length', 'min' => 0, 'max' => 500, 'subtext' => $txt['cd_excerpt_length_sub']),
			array('int', 'cd_pool_size', 'min' => 50, 'max' => 2000, 'subtext' => $txt['cd_pool_size_sub']),
			array('int', 'cd_unanswered_min_hours', 'min' => 0, 'max' => 720),
		);
		foreach (CommunityDigestEngine::$allSections as $s)
			if (CommunityDigestEngine::sectionAvailable($s))
				$config_vars[] = array('check', 'cd_section_' . $s, 'text_label' => sprintf($txt['cd_section_toggle'], $txt['cd_section_' . $s]));

		$config_vars = array_merge($config_vars, array(
			array('title', 'cd_hdr_filter'),
			array('int', 'cd_min_starter_posts', 'min' => 0, 'subtext' => $txt['cd_min_starter_posts_sub']),
			array('check', 'cd_exclude_reported'),
			array('check', 'cd_exclude_locked'),
			array('check', 'cd_skip_read'),
			array('int', 'cd_max_repeats', 'min' => 0, 'max' => 20, 'subtext' => $txt['cd_max_repeats_sub']),
			array('title', 'cd_hdr_weights'),
			array('int', 'cd_w_board', 'min' => 0, 'max' => 200),
			array('int', 'cd_w_participated', 'min' => 0, 'max' => 200),
			array('int', 'cd_w_keyword', 'min' => 0, 'max' => 200),
			array('int', 'cd_w_following', 'min' => 0, 'max' => 200),
			array('int', 'cd_w_replies', 'min' => 0, 'max' => 200),
			array('int', 'cd_w_views', 'min' => 0, 'max' => 200),
			array('title', 'cd_hdr_sending'),
			array('int', 'cd_batch_size', 'min' => 1, 'max' => 1000, 'subtext' => $txt['cd_batch_size_sub']),
			array('int', 'cd_daily_email_cap', 'min' => 0, 'subtext' => $txt['cd_daily_email_cap_sub']),
			array('int', 'cd_log_days', 'min' => 0, 'max' => 365),
			array('title', 'cd_hdr_integrations'),
		));
		foreach (CommunityDigestIntegrations::$names as $key => $name)
		{
			$found = CommunityDigestIntegrations::detected($key);
			$followUnavailable = $key === 'activity' && $found && !CommunityDigestIntegrations::activityFollowsAvailable();
			$status = $followUnavailable ? $txt['cd_int_follow_unavailable'] : ($found ? $txt['cd_int_detected'] : $txt['cd_int_missing']);
			$config_vars[] = array('check', 'cd_int_' . $key,
				'text_label' => $name,
				'postinput' => ' <span class="cd_status ' . ($found && !$followUnavailable ? 'cd_ok' : 'cd_off') . '">' . $status . '</span>',
			);
		}

		if ($return_config)
			return $config_vars;

		if (isset($_GET['save']))
		{
			checkSession();
			saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=communitydigest;sa=settings');
		}

		$context['post_url'] = $scripturl . '?action=admin;area=communitydigest;sa=settings;save';
		$context['settings_title'] = $txt['cd_admin_settings'];
		$context['sub_template'] = 'show_settings';
		prepareDBSettingContext($config_vars);
	}

	public static function adminContent()
	{
		global $context, $txt, $scripturl, $smcFunc, $user_info;

		if (isset($_POST['cd_save_boards']))
		{
			checkSession();
			$boards = isset($_POST['boards']) && is_array($_POST['boards']) ? array_filter(array_unique(array_map('intval', $_POST['boards']))) : array();
			updateSettings(array('cd_excluded_boards' => implode(',', $boards)));
			redirectexit('action=admin;area=communitydigest;sa=content;saved');
		}

		if (isset($_POST['cd_remove_topics']) && !empty($_POST['remove']) && is_array($_POST['remove']))
		{
			checkSession();
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}digest_excluded
				WHERE id_topic IN ({array_int:topics})',
				array('topics' => array_map('intval', $_POST['remove']))
			);
			redirectexit('action=admin;area=communitydigest;sa=content;saved');
		}

		$admin = CommunityDigestEngine::loadMembers(array($user_info['id']));
		$context['cd'] = array(
			'tree' => CommunityDigestEngine::boardTree($admin[$user_info['id']]),
			'excluded_boards' => array_map('intval', array_filter(explode(',', CommunityDigestEngine::setting('cd_excluded_boards', '')))),
			'excluded_topics' => array(),
			'saved' => isset($_GET['saved']),
			'post_url' => $scripturl . '?action=admin;area=communitydigest;sa=content',
		);

		$request = $smcFunc['db_query']('', '
			SELECT de.id_topic, de.excluded_time, COALESCE(m.subject, {string:missing}) AS subject, COALESCE(mem.real_name, {string:empty}) AS excluded_by
			FROM {db_prefix}digest_excluded AS de
				LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = de.id_topic)
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = de.id_member)
			ORDER BY de.excluded_time DESC
			LIMIT 200',
			array('missing' => $txt['cd_topic_missing'], 'empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['cd']['excluded_topics'][] = array(
				'id' => (int) $row['id_topic'],
				'subject' => $row['subject'],
				'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'by' => $row['excluded_by'],
				'time' => timeformat($row['excluded_time']),
			);
		$smcFunc['db_free_result']($request);

		$context['sub_template'] = 'cd_admin_content';
	}

	public static function adminTest()
	{
		global $context, $txt, $scripturl, $smcFunc, $user_info;

		$context['cd'] = array(
			'post_url' => $scripturl . '?action=admin;area=communitydigest;sa=test',
			'member' => isset($_POST['member']) ? $smcFunc['htmlspecialchars'](trim($_POST['member'])) : $user_info['name'],
			'result' => null,
			'preview_url' => '',
			'stats' => self::adminStats(),
		);

		if (!isset($_POST['cd_test']) && !isset($_POST['cd_preview']))
		{
			$context['sub_template'] = 'cd_admin_test';
			return;
		}

		checkSession();

		$name = trim(isset($_POST['member']) ? $_POST['member'] : '');
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE ' . (ctype_digit($name) ? 'id_member = {int:id}' : '(real_name IN ({array_string:names}) OR member_name IN ({array_string:names}))') . '
			LIMIT 1',
			// SMF stores names HTML-escaped (O&#039;Brien), so try both forms.
			array('id' => (int) $name, 'names' => array_values(array_unique(array($name, $smcFunc['htmlspecialchars']($name, ENT_QUOTES)))))
		);
		list ($id) = $smcFunc['db_fetch_row']($request) ?: array(0);
		$smcFunc['db_free_result']($request);

		if (empty($id))
		{
			$context['cd']['result'] = array('ok' => false, 'text' => $txt['cd_test_no_member']);
			$context['sub_template'] = 'cd_admin_test';
			return;
		}

		if (isset($_POST['cd_preview']))
			redirectexit('action=digest;sa=preview;u=' . (int) $id);

		$m = self::currentMember((int) $id);
		$prefs = CommunityDigestEngine::loadPrefs($m['id_member']);
		if ($prefs['frequency'] == 0)
			$prefs['frequency'] = CommunityDigestEngine::FREQ_WEEKLY;
		// A test delivery must not subscribe the member or create a due schedule.

		// Send in the recipient's language, then restore the admin's.
		loadLanguage('CommunityDigest', !empty($m['lngfile']) ? $m['lngfile'] : '', false, true);
		$digest = CommunityDigestEngine::build($m, $prefs, time() - CommunityDigestEngine::periodSeconds($prefs['frequency']));
		list ($ok, $msg) = CommunityDigestEngine::sendEmail($m, $prefs, $digest, true);
		loadLanguage('CommunityDigest', '', false, true);

		$context['cd']['result'] = array(
			'ok' => $ok,
			'text' => $ok
				? sprintf($txt['cd_test_sent'], $smcFunc['htmlspecialchars']($m['email_address']), $digest['count'])
				: sprintf($txt['cd_test_failed'], $smcFunc['htmlspecialchars']($msg)),
		);
		$context['sub_template'] = 'cd_admin_test';
	}

	private static function adminStats()
	{
		global $smcFunc;

		$stats = array('subscribers' => 0, 'email' => 0, 'due' => 0, 'today' => 0, 'failed' => 0);

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) AS subscribers,
				SUM(CASE WHEN delivery IN (1, 2) THEN 1 ELSE 0 END) AS email,
				SUM(CASE WHEN next_send <= {int:now} THEN 1 ELSE 0 END) AS due
			FROM {db_prefix}digest_prefs
			WHERE frequency > {int:off}',
			array('now' => time(), 'off' => 0)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);
		foreach (array('subscribers', 'email', 'due') as $k)
			$stats[$k] = (int) $row[$k];

		$stats['today'] = CommunityDigestEngine::emailsSentSince(strtotime('today'));

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*) FROM {db_prefix}digest_log WHERE status = {int:failed} AND log_time >= {int:since}',
			array('failed' => CommunityDigestEngine::LOG_FAILED, 'since' => time() - 7 * 86400)
		);
		list ($stats['failed']) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return $stats;
	}

	public static function adminLog()
	{
		global $context, $txt, $scripturl, $sourcedir, $smcFunc;

		if (isset($_POST['cd_clear_log']))
		{
			checkSession();
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}digest_log', array());
			redirectexit('action=admin;area=communitydigest;sa=log');
		}

		$filter = isset($_GET['failed']) ? 'failed' : '';
		require_once($sourcedir . '/Subs-List.php');

		$statusLabels = array(
			CommunityDigestEngine::LOG_FAILED => '<span class="cd_status cd_bad">' . $txt['cd_log_failed'] . '</span>',
			CommunityDigestEngine::LOG_SENT => '<span class="cd_status cd_ok">' . $txt['cd_log_sent'] . '</span>',
			CommunityDigestEngine::LOG_TEST => '<span class="cd_status cd_info">' . $txt['cd_log_test'] . '</span>',
			CommunityDigestEngine::LOG_SKIPPED => '<span class="cd_status cd_off">' . $txt['cd_log_skipped'] . '</span>',
		);

		$where = $filter === 'failed' ? 'WHERE l.status = {int:failed}' : '';

		$listOptions = array(
			'id' => 'cd_log',
			'title' => $txt['cd_admin_log'],
			'items_per_page' => 30,
			'base_href' => $scripturl . '?action=admin;area=communitydigest;sa=log' . ($filter ? ';failed' : ''),
			'default_sort_col' => 'time',
			'no_items_label' => $txt['cd_log_empty'],
			'get_items' => array(
				'function' => function ($start, $per_page, $sort) use ($smcFunc, $where) {
					$rows = array();
					$request = $smcFunc['db_query']('', '
						SELECT l.id_log, l.id_member, l.log_time, l.channel, l.status, l.items, l.message, COALESCE(mem.real_name, {string:empty}) AS real_name
						FROM {db_prefix}digest_log AS l
							LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
						' . $where . '
						ORDER BY {raw:sort}
						LIMIT {int:start}, {int:per_page}',
						array('sort' => $sort, 'start' => $start, 'per_page' => $per_page, 'empty' => '', 'failed' => 0)
					);
					while ($row = $smcFunc['db_fetch_assoc']($request))
						$rows[] = $row;
					$smcFunc['db_free_result']($request);

					return $rows;
				},
			),
			'get_count' => array(
				'function' => function () use ($smcFunc, $where) {
					$request = $smcFunc['db_query']('', '
						SELECT COUNT(*) FROM {db_prefix}digest_log AS l ' . $where,
						array('failed' => 0)
					);
					list ($n) = $smcFunc['db_fetch_row']($request);
					$smcFunc['db_free_result']($request);

					return (int) $n;
				},
			),
			'columns' => array(
				'time' => array(
					'header' => array('value' => $txt['cd_log_time']),
					'data' => array('function' => function ($r) { return timeformat($r['log_time']); }),
					'sort' => array('default' => 'l.id_log DESC', 'reverse' => 'l.id_log'),
				),
				'member' => array(
					'header' => array('value' => $txt['cd_log_member']),
					'data' => array('function' => function ($r) use ($scripturl) {
						return $r['real_name'] !== '' ? '<a href="' . $scripturl . '?action=profile;u=' . $r['id_member'] . '">' . $r['real_name'] . '</a>' : '#' . $r['id_member'];
					}),
					'sort' => array('default' => 'real_name', 'reverse' => 'real_name DESC'),
				),
				'channel' => array(
					'header' => array('value' => $txt['cd_log_channel']),
					'data' => array('function' => function ($r) use ($txt) { return isset($txt['cd_channel_' . $r['channel']]) ? $txt['cd_channel_' . $r['channel']] : $r['channel']; }),
				),
				'status' => array(
					'header' => array('value' => $txt['cd_log_status']),
					'data' => array('function' => function ($r) use ($statusLabels) { return isset($statusLabels[$r['status']]) ? $statusLabels[$r['status']] : $r['status']; }),
					'sort' => array('default' => 'l.status', 'reverse' => 'l.status DESC'),
				),
				'items' => array(
					'header' => array('value' => $txt['cd_log_items']),
					'data' => array('db' => 'items', 'class' => 'centercol'),
				),
				'message' => array(
					'header' => array('value' => $txt['cd_log_message']),
					'data' => array('function' => function ($r) use ($smcFunc) { return $smcFunc['htmlspecialchars']($r['message']); }),
				),
			),
			'form' => array(
				'href' => $scripturl . '?action=admin;area=communitydigest;sa=log',
				'include_sort' => true,
				'include_start' => true,
			),
			'additional_rows' => array(
				array(
					'position' => 'top_of_list',
					'value' => '<a href="' . $scripturl . '?action=admin;area=communitydigest;sa=log' . ($filter ? '' : ';failed') . '" class="button">' . ($filter ? $txt['cd_log_show_all'] : $txt['cd_log_show_failed']) . '</a>',
				),
				array(
					'position' => 'below_table_data',
					'value' => '<input type="submit" name="cd_clear_log" value="' . $txt['cd_log_clear'] . '" class="button you_sure" data-confirm="' . $txt['cd_log_clear_confirm'] . '">',
				),
			),
		);

		createList($listOptions);
		$context['sub_template'] = 'show_list';
		$context['default_list'] = 'cd_log';
	}
}
