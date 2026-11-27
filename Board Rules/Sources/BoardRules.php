<?php
/**
 * Board Rules for SMF 2.1
 *
 * Dedicated rules page, per-board rules, multilingual rules, rule-change
 * notifications/acceptance and registration acceptance - all hook based.
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 * @version 1.0.3
 */

if (!defined('SMF'))
	die('No direct access...');

if (!defined('BOARDRULES_VERSION'))
	define('BOARDRULES_VERSION', '1.0.3');

/* -------------------------------------------------------------------------
 * Hooks
 * ---------------------------------------------------------------------- */

/**
 * integrate_actions - registers ?action=rules and wraps registration.
 */
function BoardRules_Actions(&$actionArray)
{
	global $modSettings;

	if (empty($modSettings['boardrules_enabled']))
		return;

	$actionArray['rules'] = array('BoardRules.php', 'BoardRules_Page');

	if (!empty($modSettings['boardrules_reg_accept']))
	{
		$actionArray['signup'] = array('BoardRules.php', 'BoardRules_Signup');
		$actionArray['signup2'] = array('BoardRules.php', 'BoardRules_Signup2');
	}
}

/**
 * integrate_load_theme - language, CSS and the template layers for the
 * "rules changed" banner and per-board rules.
 */
function BoardRules_LoadTheme()
{
	global $modSettings, $context, $board, $topic, $user_info, $board_info;

	loadLanguage('BoardRules');

	if (empty($modSettings['boardrules_enabled']))
		return;

	// Enforce acceptance here, not in integrate_actions: SMF never runs that
	// hook for the board index, message index or topic pages, so members
	// could read the whole forum without accepting.
	BoardRules_ForceAcceptance();

	loadCSSFile('boardrules.css', array('minimize' => true), 'smf_boardrules');

	if (isset($_REQUEST['xml']) || isset($_REQUEST['api']) || empty($context['template_layers']) || !in_array('body', $context['template_layers']))
		return;

	$action = BoardRules_CurrentAction();

	// Rules changed since this member last accepted them?
	if (!empty($modSettings['boardrules_banner']) && $action !== 'rules' && $action !== 'admin' && BoardRules_NeedsAcceptance())
	{
		BoardRules_LoadTemplateOnce();
		$context['template_layers'][] = 'boardrules_notice';
	}

	// Rules that apply to the current board.
	if (empty($board) || ($user_info['is_guest'] && !empty($modSettings['boardrules_guests_hidden'])))
		return;

	$show = false;
	if ($action === '' && empty($topic))
		$show = !empty($modSettings['boardrules_show_messageindex']);
	elseif ($action === '' && !empty($topic))
		$show = !empty($modSettings['boardrules_show_display']);
	elseif ($action === 'post' || $action === 'post2')
		$show = !empty($modSettings['boardrules_show_post']);

	if (!$show)
		return;

	$rules = BoardRules_GetBoardRules((int) $board);
	if (empty($rules))
		return;

	$context['boardrules_board_rules'] = $rules;
	$context['boardrules_board_name'] = isset($board_info['name']) ? $board_info['name'] : '';
	BoardRules_LoadTemplateOnce();
	$context['template_layers'][] = 'boardrules_board';
}

/**
 * integrate_menu_buttons - adds a "Rules" button after Home.
 */
function BoardRules_MenuButtons(&$buttons)
{
	global $modSettings, $txt, $scripturl, $user_info;

	if (empty($modSettings['boardrules_enabled']) || empty($modSettings['boardrules_menu_button']))
		return;

	if ($user_info['is_guest'] && !empty($modSettings['boardrules_guests_hidden']))
		return;

	$new = array(
		'rules' => array(
			'title' => $txt['boardrules_menu'],
			'href' => $scripturl . '?action=rules',
			'icon' => 'boardrules',
			'show' => true,
		),
	);

	$keys = array_keys($buttons);
	$pos = array_search('home', $keys);
	if ($pos === false)
		$buttons += $new;
	else
		$buttons = array_slice($buttons, 0, $pos + 1, true) + $new + array_slice($buttons, $pos + 1, null, true);
}

/**
 * integrate_admin_areas - Admin > Configuration > Board Rules.
 */
function BoardRules_AdminAreas(&$admin_areas)
{
	global $txt;

	loadLanguage('BoardRules');

	$admin_areas['config']['areas']['boardrules'] = array(
		'label' => $txt['boardrules_admin'],
		'file' => 'BoardRulesAdmin.php',
		'function' => 'BoardRules_Admin',
		'icon' => 'boardrules',
		'permission' => array('admin_forum'),
		'subsections' => array(
			'rules' => array($txt['boardrules_admin_rules']),
			'categories' => array($txt['boardrules_admin_cats']),
			'changes' => array($txt['boardrules_admin_changes']),
			'settings' => array($txt['boardrules_admin_settings']),
		),
	);
}

/**
 * integrate_credits - copyright line under "Modifications" on the credits page.
 */
function BoardRules_Credits()
{
	global $context, $txt;

	loadLanguage('BoardRules');
	$context['copyrights']['mods'][] = sprintf($txt['boardrules_credits'], BOARDRULES_VERSION);
}

/**
 * integrate_delete_members - removes acceptance records of deleted members.
 */
function BoardRules_DeleteMembers($users)
{
	global $smcFunc;

	$users = array_filter(array_map('intval', (array) $users));
	if (empty($users))
		return;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}boardrules_accept
		WHERE id_member IN ({array_int:users})',
		array('users' => $users)
	);
}

/**
 * integrate_alert_types - lets members switch the "rules updated" alert off.
 */
function BoardRules_AlertTypes(&$alert_types, &$group_options)
{
	loadLanguage('BoardRules');
	$alert_types['members']['boardrules_updated'] = array('alert' => 'yes', 'email' => 'never');
}

/**
 * integrate_fetch_alerts - renders our alerts.
 */
function BoardRules_FetchAlerts(&$alerts, &$formats)
{
	global $txt, $scripturl;

	loadLanguage('BoardRules');

	foreach ($alerts as $id => $alert)
	{
		if (!isset($alert['content_type']) || $alert['content_type'] !== 'boardrules')
			continue;

		$extra = isset($alert['extra']) ? $alert['extra'] : array();
		if (is_string($extra))
			$extra = json_decode($extra, true);
		if (!is_array($extra))
			$extra = array();

		$summary = !empty($extra['summary']) && is_string($extra['summary']) ? $extra['summary'] : $txt['boardrules_default_summary'];

		$alerts[$id]['visible'] = true;
		$alerts[$id]['target_href'] = $scripturl . '?action=rules';
		$alerts[$id]['icon'] = '<span class="main_icons boardrules"></span>';
		$alerts[$id]['text'] = sprintf($txt['boardrules_alert_text'], $scripturl . '?action=rules', $summary);
	}
}

/* -------------------------------------------------------------------------
 * Rules page  (?action=rules)
 * ---------------------------------------------------------------------- */

function BoardRules_Page()
{
	global $context, $txt, $modSettings, $scripturl, $user_info, $smcFunc;

	if ($user_info['is_guest'] && !empty($modSettings['boardrules_guests_hidden']))
		is_not_guest();

	BoardRules_LoadTemplateOnce();

	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'accept')
		return BoardRules_Accept();

	// Language: explicit choice (if rules exist in it) or the member's own with fallback.
	$used_langs = BoardRules_UsedLanguages();
	$explicit_lang = isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], $used_langs, true);
	$lang = $explicit_lang ? $_GET['lang'] : BoardRules_EffectiveLang();

	$data = BoardRules_GetData($lang);

	// Names of boards that board-specific rules apply to (only boards this user can see).
	$board_ids = array();
	foreach ($data as $cat)
		foreach ($cat['rules'] as $rule)
			$board_ids = array_merge($board_ids, $rule['boards']);
	$board_names = array();
	if (!empty($board_ids))
	{
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:boards})
				AND {query_see_board}',
			array('boards' => array_unique($board_ids))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$board_names[$row['id_board']] = $row['name'];
		$smcFunc['db_free_result']($request);
	}

	$context['boardrules_categories'] = array();
	$cat_num = 0;
	$total = 0;
	foreach ($data as $cat)
	{
		$rules = array();
		$rule_num = 0;
		foreach ($cat['rules'] as $rule)
		{
			$applies = array();
			if (!empty($rule['boards']))
			{
				foreach ($rule['boards'] as $id_board)
					if (isset($board_names[$id_board]))
						$applies[] = '<a href="' . $scripturl . '?board=' . $id_board . '.0">' . $board_names[$id_board] . '</a>';

				// A board-only rule for boards this user can't see is irrelevant to them.
				if (empty($applies))
					continue;
			}

			$rule_num++;
			$rules[] = array(
				'id' => $rule['id'],
				'number' => ($cat_num + 1) . '.' . $rule_num,
				'title' => $rule['title'],
				'body' => parse_bbc($rule['body'], true, 'boardrules_' . $rule['id'] . '_' . $rule['updated']),
				'penalty' => $rule['penalty'],
				'applies' => $applies,
			);
		}

		if (empty($rules))
			continue;

		$cat_num++;
		$total += count($rules);
		$context['boardrules_categories'][] = array(
			'id' => $cat['id'],
			'number' => $cat_num,
			'title' => $cat['title'],
			'description' => $cat['description'] !== '' ? parse_bbc($cat['description']) : '',
			'rules' => $rules,
		);
	}

	$context['boardrules_total'] = $total;
	$context['boardrules_intro'] = !empty($modSettings['boardrules_intro']) ? parse_bbc($smcFunc['htmlspecialchars']($modSettings['boardrules_intro'], ENT_QUOTES)) : '';
	$context['boardrules_last_update'] = !empty($modSettings['boardrules_last_update']) ? timeformat($modSettings['boardrules_last_update']) : '';
	$context['boardrules_needs_accept'] = !$user_info['is_guest'] && BoardRules_NeedsAcceptance();
	$context['boardrules_forced'] = isset($_GET['force']);
	$context['boardrules_accepted_now'] = isset($_GET['accepted']);

	// Language switcher.
	$context['boardrules_lang'] = $explicit_lang ? $lang : '';
	$context['boardrules_languages'] = array();
	if (!empty($used_langs))
	{
		$all = getLanguages();
		foreach ($used_langs as $l)
			$context['boardrules_languages'][$l] = $smcFunc['htmlspecialchars'](isset($all[$l]['name']) ? $all[$l]['name'] : $l, ENT_QUOTES);
	}

	// Recent changes.
	$context['boardrules_changelog'] = array();
	if (!empty($modSettings['boardrules_show_changelog']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT log_time, summary, version
			FROM {db_prefix}boardrules_log
			ORDER BY log_time DESC, id_log DESC
			LIMIT {int:limit}',
			array('limit' => 5)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['boardrules_changelog'][] = array(
				'time' => timeformat($row['log_time']),
				'summary' => $row['summary'],
				'version' => (int) $row['version'],
			);
		$smcFunc['db_free_result']($request);
	}

	// Reading the rules clears any pending "rules updated" alerts.
	if (!$user_info['is_guest'] && !empty($user_info['alerts']))
		BoardRules_MarkAlertsRead($user_info['id']);

	$context['page_title'] = $txt['boardrules_title'];
	$context['canonical_url'] = $scripturl . '?action=rules';
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=rules',
		'name' => $txt['boardrules_title'],
	);
	$context['sub_template'] = 'boardrules_page';

	addInlineJavaScript(BoardRules_PageJs(), true);
}

/**
 * Records that the current member accepts the current rules.
 */
function BoardRules_Accept()
{
	global $user_info, $modSettings, $smcFunc;

	is_not_guest();
	checkSession();

	$smcFunc['db_insert']('replace',
		'{db_prefix}boardrules_accept',
		array('id_member' => 'int', 'version' => 'int', 'accept_time' => 'int'),
		array($user_info['id'], (int) $modSettings['boardrules_version'], time()),
		array('id_member')
	);
	cache_put_data('boardrules_acc_' . $user_info['id'], null);

	BoardRules_MarkAlertsRead($user_info['id']);

	if (!empty($_POST['forced']))
		redirectexit();

	redirectexit('action=rules;accepted');
}

/* -------------------------------------------------------------------------
 * Registration
 * ---------------------------------------------------------------------- */

function BoardRules_Signup()
{
	global $sourcedir;

	require_once($sourcedir . '/Register.php');
	Register();
	BoardRules_AddRegField();
}

function BoardRules_Signup2()
{
	global $sourcedir, $txt, $context;

	require_once($sourcedir . '/Register.php');

	if (empty($_POST['boardrules_accept']))
	{
		$context['boardrules_reg_error'] = true;
		Register(array($txt['boardrules_reg_error']));
		BoardRules_AddRegField();
		return;
	}

	Register2();

	// Register2() falls back to the form when something else failed.
	BoardRules_AddRegField();
}

/**
 * Adds the "I accept the rules" checkbox to the registration form using
 * the custom-field slot the core template already renders.
 */
function BoardRules_AddRegField()
{
	global $context, $txt, $scripturl;

	if (!isset($context['sub_template']) || $context['sub_template'] !== 'registration_form')
		return;

	if (!isset($context['custom_fields']) || !is_array($context['custom_fields']))
		$context['custom_fields'] = array();

	$context['custom_fields'][] = array(
		'name' => $txt['boardrules_reg_label'],
		'desc' => sprintf($txt['boardrules_reg_desc'], $scripturl . '?action=rules'),
		'input_html' => '<input type="checkbox" name="boardrules_accept" id="boardrules_accept" value="1" required' . (!empty($_POST['boardrules_accept']) ? ' checked' : '') . '>',
		'show_reg' => 2,
		'colname' => 'boardrules_accept',
		'type' => 'check',
		'value' => !empty($_POST['boardrules_accept']) ? 1 : 0,
		'is_error' => !empty($context['boardrules_reg_error']),
	);
	$context['custom_fields_required'] = true;
}

/* -------------------------------------------------------------------------
 * Acceptance / notification helpers
 * ---------------------------------------------------------------------- */

/**
 * Does the current member still need to review the latest rule change?
 */
function BoardRules_NeedsAcceptance()
{
	global $modSettings, $user_info, $user_settings;
	static $needs = null;

	if ($needs !== null)
		return $needs;

	$needs = false;
	if (empty($modSettings['boardrules_enabled']) || $user_info['is_guest'] || empty($modSettings['boardrules_version']))
		return $needs;

	// Only treat a recent registration as acceptance if the rules checkbox
	// was actually enabled. Without it, they must explicitly accept updates.
	if (!empty($modSettings['boardrules_reg_accept']) && !empty($user_settings['date_registered']) && (int) $user_settings['date_registered'] >= (int) $modSettings['boardrules_last_update'])
		return $needs;

	$needs = BoardRules_AcceptedVersion($user_info['id']) < (int) $modSettings['boardrules_version'];
	return $needs;
}

function BoardRules_AcceptedVersion($id_member)
{
	global $smcFunc;

	$id_member = (int) $id_member;
	if (($version = cache_get_data('boardrules_acc_' . $id_member, 600)) !== null)
		return (int) $version;

	$request = $smcFunc['db_query']('', '
		SELECT version
		FROM {db_prefix}boardrules_accept
		WHERE id_member = {int:member}',
		array('member' => $id_member)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$version = !empty($row) ? (int) $row['version'] : 0;
	cache_put_data('boardrules_acc_' . $id_member, $version, 600);

	return $version;
}

/**
 * Sends members who still need to accept to the rules page.
 */
function BoardRules_ForceAcceptance()
{
	global $modSettings, $user_info;

	if (empty($modSettings['boardrules_force_accept']) || $user_info['is_guest'] || $user_info['is_admin'])
		return;

	// SSI pages (portals, external scripts) load the theme too: never redirect those.
	if (SMF === 'SSI')
		return;

	if (isset($_REQUEST['xml']) || isset($_REQUEST['api']) || isset($_REQUEST['ajax']))
		return;

	$allowed = array(
		'rules', 'logout', 'login', 'login2', 'keepalive', 'jsoption', 'verificationcode',
		'dlattach', 'helpadmin', 'reminder', 'activate', 'xmlhttp', 'viewsmfile', 'about:unknown', '.xml',
	);
	if (in_array(BoardRules_CurrentAction(), $allowed, true))
		return;

	if (!BoardRules_NeedsAcceptance())
		return;

	redirectexit('action=rules;force');
}

function BoardRules_MarkAlertsRead($id_member)
{
	global $smcFunc, $user_info;

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}user_alerts
		SET is_read = {int:now}
		WHERE id_member = {int:member}
			AND content_type = {string:type}
			AND is_read = {int:unread}',
		array('now' => time(), 'member' => (int) $id_member, 'type' => 'boardrules', 'unread' => 0)
	);
	$affected = $smcFunc['db_affected_rows']();

	if ($affected > 0 && $id_member == $user_info['id'])
	{
		$user_info['alerts'] = max(0, (int) $user_info['alerts'] - $affected);
		updateMemberData($id_member, array('alerts' => $user_info['alerts']));
	}
}

/* -------------------------------------------------------------------------
 * Data helpers
 * ---------------------------------------------------------------------- */

/**
 * All enabled categories + rules visible in $lang (plus language-neutral ones).
 */
function BoardRules_GetData($lang)
{
	global $smcFunc, $modSettings;

	$key = 'boardrules_data_' . (int) (isset($modSettings['boardrules_rev']) ? $modSettings['boardrules_rev'] : 0) . '_' . md5($lang);
	if (($data = cache_get_data($key, 3600)) !== null)
		return $data;

	$langs = array('');
	if ($lang !== '')
		$langs[] = $lang;

	$request = $smcFunc['db_query']('', '
		SELECT c.id_cat, c.title AS cat_title, c.description AS cat_desc,
			r.id_rule, r.title, r.body, r.penalty, r.boards, r.updated
		FROM {db_prefix}boardrules_categories AS c
			LEFT JOIN {db_prefix}boardrules_rules AS r ON (r.id_cat = c.id_cat AND r.enabled = {int:on} AND r.lang IN ({array_string:langs}))
		WHERE c.enabled = {int:on}
			AND c.lang IN ({array_string:langs})
		ORDER BY c.cat_order, c.id_cat, r.rule_order, r.id_rule',
		array('on' => 1, 'langs' => $langs)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($data[$row['id_cat']]))
			$data[$row['id_cat']] = array(
				'id' => (int) $row['id_cat'],
				'title' => $row['cat_title'],
				'description' => (string) $row['cat_desc'],
				'rules' => array(),
			);

		if (empty($row['id_rule']))
			continue;

		$data[$row['id_cat']]['rules'][$row['id_rule']] = array(
			'id' => (int) $row['id_rule'],
			'title' => $row['title'],
			'body' => $row['body'],
			'penalty' => $row['penalty'],
			'boards' => BoardRules_ParseBoards($row['boards']),
			'updated' => (int) $row['updated'],
		);
	}
	$smcFunc['db_free_result']($request);

	cache_put_data($key, $data, 3600);
	return $data;
}

/**
 * Rules that target a specific board, in the member's language.
 */
function BoardRules_GetBoardRules($id_board)
{
	$rules = array();
	foreach (BoardRules_GetData(BoardRules_EffectiveLang()) as $cat)
		foreach ($cat['rules'] as $rule)
			if (in_array($id_board, $rule['boards'], true))
				$rules[] = array(
					'id' => $rule['id'],
					'title' => $rule['title'],
					'body' => parse_bbc($rule['body'], true, 'boardrules_' . $rule['id'] . '_' . $rule['updated']),
					'penalty' => $rule['penalty'],
				);

	return $rules;
}

/**
 * Languages that have at least one language-specific category or rule.
 */
function BoardRules_UsedLanguages()
{
	global $smcFunc, $modSettings;

	$key = 'boardrules_langs_' . (int) (isset($modSettings['boardrules_rev']) ? $modSettings['boardrules_rev'] : 0);
	if (($langs = cache_get_data($key, 3600)) !== null)
		return $langs;

	$langs = array();
	foreach (array('boardrules_categories', 'boardrules_rules') as $table)
	{
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT lang
			FROM {db_prefix}' . $table . '
			WHERE lang != {string:blank}
				AND enabled = {int:on}',
			array('blank' => '', 'on' => 1)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$langs[$row['lang']] = $row['lang'];
		$smcFunc['db_free_result']($request);
	}

	$langs = array_values($langs);
	sort($langs);
	cache_put_data($key, $langs, 3600);

	return $langs;
}

/**
 * Member language if rules exist in it, else the forum default, else neutral only.
 */
function BoardRules_EffectiveLang()
{
	global $user_info, $language;

	$used = BoardRules_UsedLanguages();
	$want = !empty($user_info['language']) ? $user_info['language'] : $language;

	if (in_array($want, $used, true))
		return $want;
	if (in_array($language, $used, true))
		return $language;

	return '';
}

function BoardRules_ParseBoards($csv)
{
	$csv = trim((string) $csv);
	if ($csv === '')
		return array();

	return array_values(array_filter(array_map('intval', explode(',', $csv))));
}

function BoardRules_CurrentAction()
{
	return isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
}

function BoardRules_LoadTemplateOnce()
{
	static $loaded = false;

	if (!$loaded)
	{
		loadTemplate('BoardRules');
		$loaded = true;
	}
}

/**
 * Filter, expand/collapse and print helpers for the rules page.
 */
function BoardRules_PageJs()
{
	return <<<'JS'
	(function () {
		var root = document.getElementById("boardrules");
		if (!root) return;
		var cats = root.querySelectorAll(".boardrules_cat");
		var input = document.getElementById("boardrules_filter");
		var none = document.getElementById("boardrules_nomatch");
		if (input) input.addEventListener("input", function () {
			var q = this.value.trim().toLowerCase(), shown = 0;
			cats.forEach(function (cat) {
				var any = false;
				cat.querySelectorAll(".boardrules_rule").forEach(function (li) {
					var hit = q === "" || li.textContent.toLowerCase().indexOf(q) !== -1;
					li.style.display = hit ? "" : "none";
					if (hit) { any = true; shown++; }
				});
				cat.style.display = any ? "" : "none";
				if (q !== "" && any) cat.open = true;
			});
			if (none) none.style.display = shown ? "none" : "";
		});
		root.querySelectorAll("[data-boardrules]").forEach(function (btn) {
			btn.addEventListener("click", function (e) {
				e.preventDefault();
				var mode = this.getAttribute("data-boardrules");
				if (mode === "print") { cats.forEach(function (c) { c.open = true; }); window.print(); return; }
				cats.forEach(function (c) { c.open = mode === "expand"; });
			});
		});
		if (location.hash && location.hash.indexOf("#rule-") === 0) {
			var t = document.getElementById(location.hash.substring(1));
			if (t) { var p = t.closest(".boardrules_cat"); if (p) p.open = true; t.classList.add("boardrules_target"); t.scrollIntoView(); }
		}
	})();
JS;
}
