<?php
/**
 * Community Insights for SMF 2.1.x - integration hooks.
 *
 * @package CommunityInsights
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */
if (!defined('SMF'))
	die('No direct access...');

/**
 * No longer registered (the language file is now loaded only where needed).
 * Kept as a harmless stub in case an older install left the hook behind.
 */
function ci_load_theme()
{
}

function ci_actions(&$actionArray)
{
	$actionArray['communityinsights'] = array('CommunityInsights.php', 'CommunityInsightsMain');
}

function ci_admin_areas(&$admin_areas)
{
	global $txt;
	loadLanguage('CommunityInsights');
	$admin_areas['config']['areas']['communityinsights'] = array(
		'label' => $txt['ci_title'],
		'file' => 'CommunityInsights.php',
		'function' => 'CommunityInsightsAdmin',
		'icon' => 'stats',
		'permission' => array('admin_forum'),
		'subsections' => array('settings' => array($txt['ci_settings'])),
	);
}

function ci_menu_buttons(&$buttons)
{
	global $txt, $scripturl, $modSettings, $context;
	if (empty($modSettings['ci_enabled']) || !(allowedTo('admin_forum') || allowedTo('ci_view')))
		return;
	loadLanguage('CommunityInsights');

	$button = array(
		'title' => $txt['ci_title'],
		'href' => $scripturl . '?action=communityinsights',
		'show' => true,
		'icon' => 'stats',
		'is_selected' => isset($context['current_action']) && $context['current_action'] === 'communityinsights',
	);

	// Place it after Members (or Search) rather than after Logout.
	$after = isset($buttons['mlist']) ? 'mlist' : (isset($buttons['search']) ? 'search' : null);
	if ($after === null)
	{
		$buttons['communityinsights'] = $button;
		return;
	}

	$new = array();
	foreach ($buttons as $key => $value)
	{
		$new[$key] = $value;
		if ($key === $after)
			$new['communityinsights'] = $button;
	}
	$buttons = $new;
}

function ci_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	loadLanguage('CommunityInsights');
	if (isset($permissionGroups['membergroup']) && is_array($permissionGroups['membergroup']))
		$permissionGroups['membergroup'][] = 'community_insights';
	$permissionList['membergroup'] += array(
		'ci_view' => array(false, 'community_insights'),
		'ci_core' => array(false, 'community_insights'),
		'ci_members' => array(false, 'community_insights'),
		'ci_moderation' => array(false, 'community_insights'),
		'ci_integrations' => array(false, 'community_insights'),
		'ci_export' => array(false, 'community_insights'),
	);
}

function ci_illegal_guest_permissions()
{
	global $context;
	if (!isset($context['non_guest_permissions']))
		$context['non_guest_permissions'] = array();
	$context['non_guest_permissions'] = array_merge($context['non_guest_permissions'], array(
		'ci_view', 'ci_core', 'ci_members', 'ci_moderation', 'ci_integrations', 'ci_export',
	));
}

function ci_whos_online($actions)
{
	global $txt;
	if (isset($actions['action']) && $actions['action'] === 'communityinsights')
	{
		loadLanguage('CommunityInsights');
		return $txt['ci_whoall'];
	}
	return '';
}

function ci_credits()
{
	global $context, $txt;
	loadLanguage('CommunityInsights');
	$context['copyrights']['mods'][] = sprintf($txt['ci_credits'], $txt['ci_by']);
}
