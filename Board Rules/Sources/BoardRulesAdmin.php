<?php
/**
 * Board Rules - administration
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 * @version 1.0.3
 */

if (!defined('SMF'))
	die('No direct access...');

function BoardRules_Admin()
{
	global $context, $txt;

	isAllowedTo('admin_forum');
	loadLanguage('BoardRules');
	BoardRules_LoadTemplateOnce();

	$subActions = array(
		'rules' => array('BoardRules_AdminRules', 'rules'),
		'editrule' => array('BoardRules_AdminEditRule', 'rules'),
		'saverule' => array('BoardRules_AdminSaveRule', 'rules'),
		'deleterule' => array('BoardRules_AdminDeleteRule', 'rules'),
		'moverule' => array('BoardRules_AdminMoveRule', 'rules'),
		'togglerule' => array('BoardRules_AdminToggleRule', 'rules'),
		'categories' => array('BoardRules_AdminCategories', 'categories'),
		'editcat' => array('BoardRules_AdminEditCat', 'categories'),
		'savecat' => array('BoardRules_AdminSaveCat', 'categories'),
		'deletecat' => array('BoardRules_AdminDeleteCat', 'categories'),
		'movecat' => array('BoardRules_AdminMoveCat', 'categories'),
		'changes' => array('BoardRules_AdminChanges', 'changes'),
		'publish' => array('BoardRules_AdminPublish', 'changes'),
		'settings' => array('BoardRules_AdminSettings', 'settings'),
	);

	$sa = isset($_REQUEST['sa']) && is_string($_REQUEST['sa']) && isset($subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'rules';

	$context['page_title'] = $txt['boardrules_admin'];
	$context[$context['admin_menu_name']]['current_subsection'] = $subActions[$sa][1];
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['boardrules_admin'],
		'description' => $txt['boardrules_admin_desc'],
		'tabs' => array(
			'rules' => array('description' => $txt['boardrules_admin_rules_desc']),
			'categories' => array('description' => $txt['boardrules_admin_cats_desc']),
			'changes' => array('description' => $txt['boardrules_admin_changes_desc']),
			'settings' => array('description' => $txt['boardrules_admin_settings_desc']),
		),
	);

	call_helper($subActions[$sa][0]);
}

/* ------------------------------- Rules -------------------------------- */

function BoardRules_AdminRules()
{
	global $context;

	$context['boardrules_cats'] = BoardRules_AdminLoadAll();
	$context['boardrules_languages'] = getLanguages();
	$context['boardrules_board_names'] = BoardRules_AdminBoardNames();
	$context['sub_template'] = 'boardrules_admin_rules';
}

function BoardRules_AdminEditRule()
{
	global $context, $smcFunc, $txt, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	$id = isset($_REQUEST['rule']) ? (int) $_REQUEST['rule'] : 0;
	$rule = array(
		'id' => 0,
		'id_cat' => isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0,
		'title' => '',
		'body' => '',
		'penalty' => '',
		'lang' => '',
		'boards' => array(),
		'enabled' => 1,
	);

	if ($id > 0)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_rule, id_cat, title, body, penalty, boards, lang, enabled
			FROM {db_prefix}boardrules_rules
			WHERE id_rule = {int:id}',
			array('id' => $id)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row))
			fatal_lang_error('boardrules_not_found', false);

		$rule = array(
			'id' => (int) $row['id_rule'],
			'id_cat' => (int) $row['id_cat'],
			'title' => $row['title'],
			'body' => un_preparsecode($row['body']),
			'penalty' => $row['penalty'],
			'lang' => $row['lang'],
			'boards' => BoardRules_ParseBoards($row['boards']),
			'enabled' => (int) $row['enabled'],
		);
	}

	$context['boardrules_cat_list'] = BoardRules_AdminCatList();
	if (empty($context['boardrules_cat_list']))
		fatal_lang_error('boardrules_need_category', false);

	$context['boardrules_rule'] = $rule;
	$context['boardrules_languages'] = getLanguages();
	$context['boardrules_board_list'] = BoardRules_AdminBoardList();

	require_once($sourcedir . '/Subs-Editor.php');
	create_control_richedit(array(
		'id' => 'rule_body',
		'value' => $rule['body'],
		'width' => '100%',
		'height' => '220px',
		'preview_type' => 0,
		'form' => 'boardrules_form',
		'required' => true,
	));

	$context['page_title'] = $id > 0 ? $txt['boardrules_edit_rule'] : $txt['boardrules_add_rule'];
	$context['sub_template'] = 'boardrules_admin_edit_rule';
}

function BoardRules_AdminSaveRule()
{
	global $smcFunc, $sourcedir;

	checkSession();
	require_once($sourcedir . '/Subs-Post.php');

	if (!empty($_REQUEST['rule_body_mode']) && isset($_REQUEST['rule_body']))
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$_REQUEST['rule_body'] = html_to_bbc($_REQUEST['rule_body']);
		$_REQUEST['rule_body'] = un_htmlspecialchars($_REQUEST['rule_body']);
	}

	$id = isset($_POST['rule']) ? (int) $_POST['rule'] : 0;
	$title = BoardRules_CleanText(isset($_POST['title']) ? $_POST['title'] : '', 200);
	$penalty = BoardRules_CleanText(isset($_POST['penalty']) ? $_POST['penalty'] : '', 100);
	$body = $smcFunc['htmlspecialchars'](isset($_REQUEST['rule_body']) ? (string) $_REQUEST['rule_body'] : '', ENT_QUOTES);
	preparsecode($body);

	if ($title === '' || $smcFunc['htmltrim']($body) === '')
		fatal_lang_error('boardrules_missing_fields', false);

	$id_cat = isset($_POST['id_cat']) ? (int) $_POST['id_cat'] : 0;
	$cat_list = BoardRules_AdminCatList();
	if (!isset($cat_list[$id_cat]))
		fatal_lang_error('boardrules_need_category', false);

	$lang = BoardRules_CleanLang(isset($_POST['lang']) ? $_POST['lang'] : '');
	$enabled = !empty($_POST['enabled']) ? 1 : 0;

	$boards = array();
	if (!empty($_POST['boards']) && is_array($_POST['boards']))
	{
		// Only store board IDs that exist; malformed or forged POST values
		// must not create rules that silently apply to nonexistent boards.
		$valid_boards = BoardRules_AdminBoardNames();
		foreach ($_POST['boards'] as $posted_board)
		{
			if (!is_scalar($posted_board) || !ctype_digit((string) $posted_board))
				continue;
			$id_board = (int) $posted_board;
			if ($id_board > 0 && isset($valid_boards[$id_board]))
				$boards[$id_board] = $id_board;
		}
	}
	$boards = implode(',', $boards);

	$order = null;
	if ($id > 0)
	{
		// Moving to another category puts it at the end of that category.
		$request = $smcFunc['db_query']('', '
			SELECT id_cat
			FROM {db_prefix}boardrules_rules
			WHERE id_rule = {int:id}',
			array('id' => $id)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row))
			fatal_lang_error('boardrules_not_found', false);

		if ((int) $row['id_cat'] !== $id_cat)
			$order = BoardRules_NextOrder('rules', $id_cat);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boardrules_rules
			SET id_cat = {int:cat}, title = {string:title}, body = {string:body}, penalty = {string:penalty},
				boards = {string:boards}, lang = {string:lang}, enabled = {int:enabled}, updated = {int:now}' . ($order !== null ? ', rule_order = {int:order}' : '') . '
			WHERE id_rule = {int:id}',
			array(
				'cat' => $id_cat, 'title' => $title, 'body' => $body, 'penalty' => $penalty, 'boards' => $boards,
				'lang' => $lang, 'enabled' => $enabled, 'now' => time(), 'order' => (int) $order, 'id' => $id,
			)
		);
	}
	else
	{
		$smcFunc['db_insert']('insert',
			'{db_prefix}boardrules_rules',
			array(
				'id_cat' => 'int', 'title' => 'string-255', 'body' => 'string', 'penalty' => 'string-255',
				'boards' => 'string', 'lang' => 'string-80', 'rule_order' => 'int', 'enabled' => 'int', 'updated' => 'int',
			),
			array($id_cat, $title, $body, $penalty, $boards, $lang, BoardRules_NextOrder('rules', $id_cat), $enabled, time()),
			array('id_rule')
		);
	}

	BoardRules_Touch();
	BoardRules_MaybePublish();

	redirectexit('action=admin;area=boardrules;sa=rules');
}

function BoardRules_AdminDeleteRule()
{
	global $smcFunc;

	checkSession('get');

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}boardrules_rules
		WHERE id_rule = {int:id}',
		array('id' => isset($_GET['rule']) ? (int) $_GET['rule'] : 0)
	);

	BoardRules_Touch();
	redirectexit('action=admin;area=boardrules;sa=rules');
}

function BoardRules_AdminToggleRule()
{
	global $smcFunc;

	checkSession('get');

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}boardrules_rules
		SET enabled = CASE WHEN enabled = {int:on} THEN {int:off} ELSE {int:on} END
		WHERE id_rule = {int:id}',
		array('on' => 1, 'off' => 0, 'id' => isset($_GET['rule']) ? (int) $_GET['rule'] : 0)
	);

	BoardRules_Touch();
	redirectexit('action=admin;area=boardrules;sa=rules');
}

function BoardRules_AdminMoveRule()
{
	global $smcFunc;

	checkSession('get');

	$id = isset($_GET['rule']) ? (int) $_GET['rule'] : 0;
	$request = $smcFunc['db_query']('', '
		SELECT id_cat
		FROM {db_prefix}boardrules_rules
		WHERE id_rule = {int:id}',
		array('id' => $id)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($row))
		BoardRules_Reorder('rules', $id, isset($_GET['dir']) && $_GET['dir'] === 'up' ? 'up' : 'down', (int) $row['id_cat']);

	redirectexit('action=admin;area=boardrules;sa=rules');
}

/* ----------------------------- Categories ----------------------------- */

function BoardRules_AdminCategories()
{
	global $context;

	$context['boardrules_cats'] = BoardRules_AdminLoadAll();
	$context['boardrules_languages'] = getLanguages();
	$context['sub_template'] = 'boardrules_admin_cats';
}

function BoardRules_AdminEditCat()
{
	global $context, $smcFunc, $txt, $sourcedir;

	require_once($sourcedir . '/Subs-Post.php');

	$id = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;
	$cat = array('id' => 0, 'title' => '', 'description' => '', 'lang' => '', 'enabled' => 1);

	if ($id > 0)
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_cat, title, description, lang, enabled
			FROM {db_prefix}boardrules_categories
			WHERE id_cat = {int:id}',
			array('id' => $id)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row))
			fatal_lang_error('boardrules_not_found', false);

		$cat = array(
			'id' => (int) $row['id_cat'],
			'title' => $row['title'],
			'description' => un_preparsecode((string) $row['description']),
			'lang' => $row['lang'],
			'enabled' => (int) $row['enabled'],
		);
	}

	$context['boardrules_cat'] = $cat;
	$context['boardrules_languages'] = getLanguages();
	$context['page_title'] = $id > 0 ? $txt['boardrules_edit_cat'] : $txt['boardrules_add_cat'];
	$context['sub_template'] = 'boardrules_admin_edit_cat';
}

function BoardRules_AdminSaveCat()
{
	global $smcFunc, $sourcedir;

	checkSession();
	require_once($sourcedir . '/Subs-Post.php');

	$id = isset($_POST['cat']) ? (int) $_POST['cat'] : 0;
	$title = BoardRules_CleanText(isset($_POST['title']) ? $_POST['title'] : '', 200);
	$description = $smcFunc['htmlspecialchars'](isset($_POST['description']) ? (string) $_POST['description'] : '', ENT_QUOTES);
	preparsecode($description);
	$lang = BoardRules_CleanLang(isset($_POST['lang']) ? $_POST['lang'] : '');
	$enabled = !empty($_POST['enabled']) ? 1 : 0;

	if ($title === '')
		fatal_lang_error('boardrules_missing_fields', false);

	if ($id > 0)
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}boardrules_categories
			SET title = {string:title}, description = {string:description}, lang = {string:lang}, enabled = {int:enabled}
			WHERE id_cat = {int:id}',
			array('title' => $title, 'description' => $description, 'lang' => $lang, 'enabled' => $enabled, 'id' => $id)
		);
	else
		$smcFunc['db_insert']('insert',
			'{db_prefix}boardrules_categories',
			array('title' => 'string-255', 'description' => 'string', 'cat_order' => 'int', 'lang' => 'string-80', 'enabled' => 'int'),
			array($title, $description, BoardRules_NextOrder('categories'), $lang, $enabled),
			array('id_cat')
		);

	BoardRules_Touch();
	BoardRules_MaybePublish();

	redirectexit('action=admin;area=boardrules;sa=categories');
}

function BoardRules_AdminDeleteCat()
{
	global $smcFunc;

	checkSession('get');
	$id = isset($_GET['cat']) ? (int) $_GET['cat'] : 0;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}boardrules_rules
		WHERE id_cat = {int:id}',
		array('id' => $id)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}boardrules_categories
		WHERE id_cat = {int:id}',
		array('id' => $id)
	);

	BoardRules_Touch();
	redirectexit('action=admin;area=boardrules;sa=categories');
}

function BoardRules_AdminMoveCat()
{
	checkSession('get');

	BoardRules_Reorder('categories', isset($_GET['cat']) ? (int) $_GET['cat'] : 0, isset($_GET['dir']) && $_GET['dir'] === 'up' ? 'up' : 'down');
	redirectexit('action=admin;area=boardrules;sa=categories');
}

/* ------------------------ Changes & notifications ---------------------- */

function BoardRules_AdminChanges()
{
	global $context, $smcFunc, $modSettings, $scripturl;

	$context['boardrules_log'] = array();
	$request = $smcFunc['db_query']('', '
		SELECT l.log_time, l.id_member, l.member_name, l.version, l.summary, mem.real_name
		FROM {db_prefix}boardrules_log AS l
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = l.id_member)
		ORDER BY l.log_time DESC, l.id_log DESC
		LIMIT {int:limit}',
		array('limit' => 50)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$context['boardrules_log'][] = array(
			'time' => timeformat($row['log_time']),
			'version' => (int) $row['version'],
			'summary' => $row['summary'],
			'member' => !empty($row['real_name'])
				? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
				: $row['member_name'],
		);
	$smcFunc['db_free_result']($request);

	// Acceptance statistics for the current version.
	$version = (int) $modSettings['boardrules_version'];
	$last = (int) $modSettings['boardrules_last_update'];
	$stats = array('version' => $version, 'members' => 0, 'accepted' => 0, 'newer' => 0);

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*) AS members,
			SUM(CASE WHEN {int:reg_accept} = 1 AND mem.date_registered >= {int:last} THEN 1 ELSE 0 END) AS newer,
			SUM(CASE WHEN ({int:reg_accept} = 0 OR mem.date_registered < {int:last}) AND acc.version >= {int:version} THEN 1 ELSE 0 END) AS accepted
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}boardrules_accept AS acc ON (acc.id_member = mem.id_member)
		WHERE mem.is_activated = {int:active}',
		array('last' => $last, 'version' => $version, 'active' => 1, 'reg_accept' => !empty($modSettings['boardrules_reg_accept']) ? 1 : 0)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	if (!empty($row))
	{
		$stats['members'] = (int) $row['members'];
		$stats['newer'] = (int) $row['newer'];
		$stats['accepted'] = (int) $row['accepted'];
	}
	$stats['outstanding'] = max(0, $stats['members'] - $stats['newer'] - $stats['accepted']);
	$stats['percent'] = $stats['members'] > 0 ? round((($stats['accepted'] + $stats['newer']) / $stats['members']) * 100) : 100;

	$context['boardrules_stats'] = $stats;
	$context['sub_template'] = 'boardrules_admin_changes';
}

function BoardRules_AdminPublish()
{
	checkSession();

	BoardRules_PublishUpdate(isset($_POST['update_summary']) ? $_POST['update_summary'] : '', !empty($_POST['send_alert']));
	redirectexit('action=admin;area=boardrules;sa=changes');
}

/**
 * Publishes a "significant" change if the edit form asked for it.
 */
function BoardRules_MaybePublish()
{
	if (!empty($_POST['publish_update']))
		BoardRules_PublishUpdate(isset($_POST['update_summary']) ? $_POST['update_summary'] : '', !empty($_POST['send_alert']));
}

/**
 * Bumps the rules version: members see the banner (and may be forced to
 * accept), the change is logged, and alerts are optionally sent.
 */
function BoardRules_PublishUpdate($summary, $send_alert)
{
	global $smcFunc, $modSettings, $user_info, $txt;

	$summary = BoardRules_CleanText($summary, 200);
	if ($summary === '')
		$summary = $txt['boardrules_default_summary'];

	$version = (int) $modSettings['boardrules_version'] + 1;
	$now = time();

	updateSettings(array(
		'boardrules_version' => $version,
		'boardrules_last_update' => $now,
		'boardrules_last_summary' => $summary,
	));

	$smcFunc['db_insert']('insert',
		'{db_prefix}boardrules_log',
		array('log_time' => 'int', 'id_member' => 'int', 'member_name' => 'string-255', 'version' => 'int', 'summary' => 'string-255'),
		array($now, $user_info['id'], $user_info['name'], $version, $summary),
		array('id_log')
	);

	// The admin publishing the change has obviously read it.
	$smcFunc['db_insert']('replace',
		'{db_prefix}boardrules_accept',
		array('id_member' => 'int', 'version' => 'int', 'accept_time' => 'int'),
		array($user_info['id'], $version, $now),
		array('id_member')
	);
	cache_put_data('boardrules_acc_' . $user_info['id'], null);

	if ($send_alert && !empty($modSettings['boardrules_alerts']))
		BoardRules_SendAlerts($version, $summary, $now);
}

/**
 * One alert per active member who hasn't switched this alert off.
 */
function BoardRules_SendAlerts($version, $summary, $now)
{
	global $smcFunc, $user_info;

	$request = $smcFunc['db_query']('', '
		SELECT alert_value
		FROM {db_prefix}user_alerts_prefs
		WHERE id_member = {int:guest}
			AND alert_pref = {string:pref}',
		array('guest' => 0, 'pref' => 'boardrules_updated')
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);
	$default = isset($row['alert_value']) ? (int) $row['alert_value'] : 1;

	$smcFunc['db_query']('', '
		INSERT INTO {db_prefix}user_alerts
			(alert_time, id_member, id_member_started, member_name, content_type, content_id, content_action, is_read, extra)
		SELECT {int:now}, mem.id_member, {int:sender}, {string:sender_name}, {string:type}, {int:version}, {string:action}, {int:unread}, {string:extra}
		FROM {db_prefix}members AS mem
			LEFT JOIN {db_prefix}user_alerts_prefs AS up ON (up.id_member = mem.id_member AND up.alert_pref = {string:pref})
		WHERE mem.is_activated = {int:active}
			AND mem.id_member != {int:sender}
			AND COALESCE(up.alert_value, {int:default}) IN ({array_int:on})',
		array(
			'now' => $now,
			'sender' => $user_info['id'],
			'sender_name' => $user_info['name'],
			'type' => 'boardrules',
			'version' => $version,
			'action' => 'updated',
			'unread' => 0,
			'extra' => json_encode(array('summary' => $summary, 'version' => $version)),
			'pref' => 'boardrules_updated',
			'active' => 1,
			'default' => $default,
			'on' => array(1, 3),
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET alerts = alerts + 1
		WHERE id_member IN (
			SELECT ua.id_member
			FROM {db_prefix}user_alerts AS ua
			WHERE ua.content_type = {string:type}
				AND ua.content_id = {int:version}
				AND ua.alert_time = {int:now}
		)',
		array('type' => 'boardrules', 'version' => $version, 'now' => $now)
	);
}

/* ------------------------------- Settings ----------------------------- */

function BoardRules_AdminSettings($return_config = false)
{
	global $txt, $scripturl, $context, $sourcedir;

	require_once($sourcedir . '/ManageServer.php');

	$config_vars = array(
		array('title', 'boardrules_settings_general'),
		array('check', 'boardrules_enabled'),
		array('check', 'boardrules_menu_button'),
		array('check', 'boardrules_guests_hidden'),
		array('large_text', 'boardrules_intro', 'subtext' => $txt['boardrules_intro_sub']),
		array('check', 'boardrules_numbering'),
		array('check', 'boardrules_show_changelog'),
		array('title', 'boardrules_settings_accept'),
		array('check', 'boardrules_reg_accept', 'subtext' => $txt['boardrules_reg_accept_sub']),
		array('check', 'boardrules_banner'),
		array('check', 'boardrules_force_accept', 'subtext' => $txt['boardrules_force_accept_sub']),
		array('check', 'boardrules_alerts'),
		array('title', 'boardrules_settings_boards'),
		array('check', 'boardrules_show_messageindex'),
		array('check', 'boardrules_show_display'),
		array('check', 'boardrules_show_post'),
		array('select', 'boardrules_board_style', array(
			'open' => $txt['boardrules_board_style_open'],
			'collapsed' => $txt['boardrules_board_style_collapsed'],
		)),
	);

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=boardrules;sa=settings;save';
	$context['settings_title'] = $txt['boardrules_admin_settings'];

	if (isset($_GET['save']))
	{
		checkSession();
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=boardrules;sa=settings');
	}

	prepareDBSettingContext($config_vars);
	$context['sub_template'] = 'show_settings';
}

/* -------------------------------- Helpers ----------------------------- */

/**
 * Bumps the data revision so every cached copy is discarded.
 */
function BoardRules_Touch()
{
	global $modSettings;

	updateSettings(array('boardrules_rev' => (int) (isset($modSettings['boardrules_rev']) ? $modSettings['boardrules_rev'] : 0) + 1));
}

function BoardRules_AdminLoadAll()
{
	global $smcFunc;

	$cats = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_cat, title, description, lang, enabled
		FROM {db_prefix}boardrules_categories
		ORDER BY cat_order, id_cat',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$cats[$row['id_cat']] = array(
			'id' => (int) $row['id_cat'],
			'title' => $row['title'],
			'description' => (string) $row['description'],
			'lang' => $row['lang'],
			'enabled' => (int) $row['enabled'],
			'rules' => array(),
		);
	$smcFunc['db_free_result']($request);

	$request = $smcFunc['db_query']('', '
		SELECT id_rule, id_cat, title, penalty, boards, lang, enabled, updated
		FROM {db_prefix}boardrules_rules
		ORDER BY rule_order, id_rule',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		if (isset($cats[$row['id_cat']]))
			$cats[$row['id_cat']]['rules'][] = array(
				'id' => (int) $row['id_rule'],
				'title' => $row['title'],
				'penalty' => $row['penalty'],
				'boards' => BoardRules_ParseBoards($row['boards']),
				'lang' => $row['lang'],
				'enabled' => (int) $row['enabled'],
				'updated' => !empty($row['updated']) ? timeformat($row['updated']) : '',
			);
	$smcFunc['db_free_result']($request);

	return $cats;
}

function BoardRules_AdminCatList()
{
	global $smcFunc;
	static $list = null;

	if ($list !== null)
		return $list;

	$list = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_cat, title, lang
		FROM {db_prefix}boardrules_categories
		ORDER BY cat_order, id_cat',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$list[(int) $row['id_cat']] = $row['title'] . ($row['lang'] !== '' ? ' [' . $row['lang'] . ']' : '');
	$smcFunc['db_free_result']($request);

	return $list;
}

function BoardRules_AdminBoardList()
{
	global $smcFunc;

	$list = array();
	$request = $smcFunc['db_query']('', '
		SELECT b.id_board, b.name, b.child_level, c.id_cat, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		ORDER BY c.cat_order, b.board_order',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if (!isset($list[$row['id_cat']]))
			$list[$row['id_cat']] = array('name' => $row['cat_name'], 'boards' => array());

		$list[$row['id_cat']]['boards'][] = array(
			'id' => (int) $row['id_board'],
			'name' => $row['name'],
			'level' => (int) $row['child_level'],
		);
	}
	$smcFunc['db_free_result']($request);

	return $list;
}

function BoardRules_AdminBoardNames()
{
	global $smcFunc;

	$names = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_board, name
		FROM {db_prefix}boards',
		array()
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$names[(int) $row['id_board']] = $row['name'];
	$smcFunc['db_free_result']($request);

	return $names;
}

function BoardRules_CleanText($text, $max)
{
	global $smcFunc;

	$text = trim(strtr((string) $text, array("\r" => '', "\n" => ' ', "\t" => ' ')));
	if ($smcFunc['strlen']($text) > $max)
		$text = $smcFunc['substr']($text, 0, $max);

	return $smcFunc['htmlspecialchars']($text, ENT_QUOTES);
}

function BoardRules_CleanLang($lang)
{
	if (!is_string($lang) || $lang === '')
		return '';

	$languages = getLanguages();
	return isset($languages[$lang]) ? $lang : '';
}

/**
 * Next order value for a new rule (within a category) or category.
 */
function BoardRules_NextOrder($type, $id_cat = 0)
{
	global $smcFunc;

	if ($type === 'rules')
		$request = $smcFunc['db_query']('', '
			SELECT COALESCE(MAX(rule_order), 0)
			FROM {db_prefix}boardrules_rules
			WHERE id_cat = {int:cat}',
			array('cat' => (int) $id_cat)
		);
	else
		$request = $smcFunc['db_query']('', '
			SELECT COALESCE(MAX(cat_order), 0)
			FROM {db_prefix}boardrules_categories',
			array()
		);

	list ($max) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	return (int) $max + 1;
}

/**
 * Moves an item up/down by renumbering its group sequentially.
 */
function BoardRules_Reorder($type, $id, $dir, $id_cat = 0)
{
	global $smcFunc;

	if ($type === 'rules')
	{
		$table = '{db_prefix}boardrules_rules';
		$id_col = 'id_rule';
		$order_col = 'rule_order';
		$where = 'WHERE id_cat = {int:cat}';
	}
	else
	{
		$table = '{db_prefix}boardrules_categories';
		$id_col = 'id_cat';
		$order_col = 'cat_order';
		$where = '';
	}

	$ids = array();
	$request = $smcFunc['db_query']('', '
		SELECT ' . $id_col . ' AS id
		FROM ' . $table . '
		' . $where . '
		ORDER BY ' . $order_col . ', ' . $id_col,
		array('cat' => (int) $id_cat)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ids[] = (int) $row['id'];
	$smcFunc['db_free_result']($request);

	$pos = array_search((int) $id, $ids, true);
	if ($pos === false)
		return;

	$swap = $dir === 'up' ? $pos - 1 : $pos + 1;
	if (!isset($ids[$swap]))
		return;

	list ($ids[$pos], $ids[$swap]) = array($ids[$swap], $ids[$pos]);

	foreach ($ids as $order => $item)
		$smcFunc['db_query']('', '
			UPDATE ' . $table . '
			SET ' . $order_col . ' = {int:order}
			WHERE ' . $id_col . ' = {int:id}',
			array('order' => $order + 1, 'id' => $item)
		);

	BoardRules_Touch();
}
