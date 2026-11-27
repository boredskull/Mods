<?php
/**
 * Profile Wall for SMF 2.1.x
 * Integration hooks. This file is loaded on every page, so it stays small;
 * the heavy lifting lives in ProfileWall.php and is only loaded when needed.
 *
 * @license ISC
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * integrate_load_theme - language strings are needed by menus, permissions and alerts.
 */
function pw_load_theme()
{
	loadLanguage('ProfileWall');
}

/**
 * integrate_credits - list the mod on the Credits page.
 */
function pw_credits()
{
	global $context, $txt;

	loadLanguage('ProfileWall');
	$context['copyrights']['mods'][] = sprintf($txt['pw_credits'], '1.1.0');
}

/**
 * integrate_profile_areas - add the "Wall" tab and, optionally, the summary preview.
 */
function pw_profile_areas(&$profile_areas)
{
	global $txt, $modSettings;

	if (empty($modSettings['pw_enabled']) || !isset($profile_areas['info']['areas']))
		return;

	$wall_area = array(
		'label' => $txt['pw_tab'],
		'file' => 'ProfileWall.php',
		'function' => 'ProfileWallMain',
		'icon' => 'members',
		'permission' => array(
			'own' => array('profile_wall_view'),
			'any' => array('profile_wall_view'),
		),
	);

	// Put the Wall straight after Summary.
	$areas = array();
	foreach ($profile_areas['info']['areas'] as $key => $area)
	{
		$areas[$key] = $area;
		if ($key === 'summary')
			$areas['wall'] = $wall_area;
	}
	if (!isset($areas['wall']))
		$areas['wall'] = $wall_area;
	$profile_areas['info']['areas'] = $areas;

	// Latest comments preview on the summary page (only if nobody else replaced summary).
	if (!empty($modSettings['pw_summary_count'])
		&& isset($profile_areas['info']['areas']['summary']['function'])
		&& $profile_areas['info']['areas']['summary']['function'] === 'summary')
	{
		$profile_areas['info']['areas']['summary']['file'] = 'ProfileWall.php';
		$profile_areas['info']['areas']['summary']['function'] = 'ProfileWallSummary';
		// Keep the stock summary template; otherwise SMF uses the function name as the sub-template.
		$profile_areas['info']['areas']['summary']['sub_template'] = 'summary';
	}
}

/**
 * integrate_admin_areas - Admin > Configuration > Profile Wall.
 */
function pw_admin_areas(&$admin_areas)
{
	global $txt;

	$admin_areas['config']['areas']['profilewall'] = array(
		'label' => $txt['pw_admin_title'],
		'file' => 'ProfileWall.php',
		'function' => 'ProfileWallAdmin',
		'icon' => 'members',
		'permission' => array('admin_forum', 'moderate_forum'),
		'subsections' => array(
			'settings' => array($txt['pw_admin_settings'], 'admin_forum'),
			'queue' => array($txt['pw_admin_queue']),
			'reports' => array($txt['pw_admin_reports']),
		),
	);
}

/**
 * integrate_load_permissions
 */
function pw_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	if (isset($permissionGroups['membergroup']) && is_array($permissionGroups['membergroup']))
		$permissionGroups['membergroup'][] = 'profile_wall';

	$permissionList['membergroup'] += array(
		'profile_wall_view' => array(false, 'profile_wall'),
		'profile_wall_post' => array(false, 'profile_wall'),
		'profile_wall_autoapprove' => array(false, 'profile_wall'),
		'profile_wall_edit' => array(true, 'profile_wall'),
		'profile_wall_delete' => array(true, 'profile_wall'),
		'profile_wall_moderate' => array(false, 'profile_wall'),
	);
}

/**
 * integrate_load_illegal_guest_permissions - guests may only read.
 */
function pw_illegal_guest_permissions()
{
	global $context;

	$context['non_guest_permissions'] = array_merge(
		$context['non_guest_permissions'],
		array('profile_wall_post', 'profile_wall_autoapprove', 'profile_wall_edit', 'profile_wall_delete', 'profile_wall_moderate')
	);
}

/**
 * integrate_post_ban_permissions - "cannot post" bans also stop wall posting.
 */
function pw_ban_permissions(&$denied_permissions)
{
	$denied_permissions = array_merge($denied_permissions, array(
		'profile_wall_post', 'profile_wall_edit_own', 'profile_wall_edit_any',
	));
}

/**
 * integrate_alert_types - lets members switch wall alerts on/off in their notification settings.
 */
function pw_alert_types(&$alert_types, &$group_options)
{
	global $modSettings;

	if (empty($modSettings['pw_enabled']))
		return;

	$alert_types['profile_wall'] = array(
		'pw_new' => array('alert' => 'yes', 'email' => 'never'),
		'pw_reply' => array('alert' => 'yes', 'email' => 'never'),
		'pw_like' => array('alert' => 'yes', 'email' => 'never'),
	);
}

/**
 * integrate_delete_members - tidy up when accounts are removed.
 */
function pw_delete_members($users)
{
	global $smcFunc, $sourcedir;

	$users = array_filter(array_map('intval', (array) $users));
	if (empty($users))
		return;

	require_once($sourcedir . '/ProfileWall.php');

	// Remove everything posted on the deleted members' walls.
	$ids = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_comment
		FROM {db_prefix}pw_comments
		WHERE id_profile IN ({array_int:users})',
		array('users' => $users)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$ids[] = (int) $row['id_comment'];
	$smcFunc['db_free_result']($request);

	if (!empty($ids))
		pw_delete_comments($ids);

	// Their comments elsewhere stay, but become "guest" comments.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pw_comments
		SET id_poster = {int:guest}
		WHERE id_poster IN ({array_int:users})',
		array('users' => $users, 'guest' => 0)
	);

	// Remove their likes and recount.
	$liked = array();
	$request = $smcFunc['db_query']('', '
		SELECT DISTINCT id_comment
		FROM {db_prefix}pw_likes
		WHERE id_member IN ({array_int:users})',
		array('users' => $users)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$liked[] = (int) $row['id_comment'];
	$smcFunc['db_free_result']($request);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}pw_likes
		WHERE id_member IN ({array_int:users})',
		array('users' => $users)
	);
	if (!empty($liked))
		pw_recount_likes($liked);

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}pw_walls
		WHERE id_member IN ({array_int:users})',
		array('users' => $users)
	);
}
