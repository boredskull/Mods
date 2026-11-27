<?php
/**
 * Social Media Links for SMF 2.1.x
 *
 * Lets members add links to their social media profiles (Bluesky, Mastodon,
 * Threads, X, etc). The links are shown as a row of icons directly below the
 * member's signature on every post:
 *
 *     Post
 *     Signature
 *     Social media icons
 *
 * Icons: Font Awesome Free (icons CC BY 4.0, CSS MIT).
 *
 * Fully hook based - no core files are edited.
 *
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * The list of supported networks.
 *
 * type 'handle' = the member types a username/handle, the URL is built from 'url'.
 *                 A full profile URL on one of 'hosts' is also accepted.
 * type 'url'    = the member must give a full http(s) URL. If 'hosts' is set the
 *                 URL must be on one of those domains.
 *
 * @return array
 */
function sociallinks_networks()
{
	static $networks = null;

	if ($networks !== null)
		return $networks;

	$networks = array(
		'bluesky' => array(
			'label' => 'Bluesky',
			'icon' => 'fa-brands fa-bluesky',
			'type' => 'handle',
			'url' => 'https://bsky.app/profile/%s',
			'pattern' => '~^[A-Za-z0-9][A-Za-z0-9.\-:]{1,252}$~',
			'hosts' => array('bsky.app'),
			'placeholder' => 'yourname.bsky.social',
			'colour' => '#1185fe',
		),
		'mastodon' => array(
			'label' => 'Mastodon',
			'icon' => 'fa-brands fa-mastodon',
			'type' => 'url',
			'hosts' => array(), // Any instance.
			'placeholder' => '@you@mastodon.social or https://mastodon.social/@you',
			'colour' => '#6364ff',
		),
		'threads' => array(
			'label' => 'Threads',
			'icon' => 'fa-brands fa-threads',
			'type' => 'handle',
			'url' => 'https://www.threads.net/@%s',
			'pattern' => '~^[A-Za-z0-9._]{1,30}$~',
			'hosts' => array('threads.net', 'threads.com'),
			'placeholder' => 'username',
			'colour' => '#000000',
		),
		'x' => array(
			'label' => 'X (Twitter)',
			'icon' => 'fa-brands fa-x-twitter',
			'type' => 'handle',
			'url' => 'https://x.com/%s',
			'pattern' => '~^[A-Za-z0-9_]{1,15}$~',
			'hosts' => array('x.com', 'twitter.com'),
			'placeholder' => 'username',
			'colour' => '#000000',
		),
		'facebook' => array(
			'label' => 'Facebook',
			'icon' => 'fa-brands fa-facebook',
			'type' => 'handle',
			'url' => 'https://www.facebook.com/%s',
			'pattern' => '~^[A-Za-z0-9.]{1,50}$~',
			'hosts' => array('facebook.com', 'fb.com'),
			'placeholder' => 'username',
			'colour' => '#0866ff',
		),
		'instagram' => array(
			'label' => 'Instagram',
			'icon' => 'fa-brands fa-instagram',
			'type' => 'handle',
			'url' => 'https://www.instagram.com/%s',
			'pattern' => '~^[A-Za-z0-9._]{1,30}$~',
			'hosts' => array('instagram.com'),
			'placeholder' => 'username',
			'colour' => '#e4405f',
		),
		'pixelfed' => array(
			'label' => 'Pixelfed',
			'icon' => 'fa-solid fa-camera-retro',
			'type' => 'url',
			'hosts' => array(), // Any instance.
			'placeholder' => 'https://pixelfed.social/you',
			'colour' => '#6366f1',
		),
		'youtube' => array(
			'label' => 'YouTube',
			'icon' => 'fa-brands fa-youtube',
			'type' => 'handle',
			'url' => 'https://www.youtube.com/@%s',
			'pattern' => '~^[A-Za-z0-9._\-]{3,30}$~',
			'hosts' => array('youtube.com'),
			'placeholder' => '@handle or channel URL',
			'colour' => '#ff0000',
		),
		'tiktok' => array(
			'label' => 'TikTok',
			'icon' => 'fa-brands fa-tiktok',
			'type' => 'handle',
			'url' => 'https://www.tiktok.com/@%s',
			'pattern' => '~^[A-Za-z0-9._]{2,24}$~',
			'hosts' => array('tiktok.com'),
			'placeholder' => 'username',
			'colour' => '#000000',
		),
		'twitch' => array(
			'label' => 'Twitch',
			'icon' => 'fa-brands fa-twitch',
			'type' => 'handle',
			'url' => 'https://www.twitch.tv/%s',
			'pattern' => '~^[A-Za-z0-9_]{3,25}$~',
			'hosts' => array('twitch.tv'),
			'placeholder' => 'username',
			'colour' => '#9146ff',
		),
		'discord' => array(
			'label' => 'Discord',
			'icon' => 'fa-brands fa-discord',
			'type' => 'url',
			'hosts' => array('discord.gg', 'discord.com'),
			'placeholder' => 'https://discord.gg/invite',
			'colour' => '#5865f2',
		),
		'reddit' => array(
			'label' => 'Reddit',
			'icon' => 'fa-brands fa-reddit',
			'type' => 'handle',
			'url' => 'https://www.reddit.com/user/%s',
			'pattern' => '~^[A-Za-z0-9_\-]{3,20}$~',
			'hosts' => array('reddit.com'),
			'placeholder' => 'username',
			'colour' => '#ff4500',
		),
		'linkedin' => array(
			'label' => 'LinkedIn',
			'icon' => 'fa-brands fa-linkedin',
			'type' => 'handle',
			'url' => 'https://www.linkedin.com/in/%s',
			'pattern' => '~^[A-Za-z0-9\-]{3,100}$~',
			'hosts' => array('linkedin.com'),
			'placeholder' => 'profile-name',
			'colour' => '#0a66c2',
		),
		'github' => array(
			'label' => 'GitHub',
			'icon' => 'fa-brands fa-github',
			'type' => 'handle',
			'url' => 'https://github.com/%s',
			'pattern' => '~^[A-Za-z0-9\-]{1,39}$~',
			'hosts' => array('github.com'),
			'placeholder' => 'username',
			'colour' => '#181717',
		),
		'gitlab' => array(
			'label' => 'GitLab',
			'icon' => 'fa-brands fa-gitlab',
			'type' => 'handle',
			'url' => 'https://gitlab.com/%s',
			'pattern' => '~^[A-Za-z0-9._\-]{2,255}$~',
			'hosts' => array('gitlab.com'),
			'placeholder' => 'username',
			'colour' => '#fc6d26',
		),
		'steam' => array(
			'label' => 'Steam',
			'icon' => 'fa-brands fa-steam',
			'type' => 'url',
			'hosts' => array('steamcommunity.com'),
			'placeholder' => 'https://steamcommunity.com/id/you',
			'colour' => '#1b2838',
		),
		'spotify' => array(
			'label' => 'Spotify',
			'icon' => 'fa-brands fa-spotify',
			'type' => 'url',
			'hosts' => array('open.spotify.com'),
			'placeholder' => 'https://open.spotify.com/user/...',
			'colour' => '#1db954',
		),
		'soundcloud' => array(
			'label' => 'SoundCloud',
			'icon' => 'fa-brands fa-soundcloud',
			'type' => 'handle',
			'url' => 'https://soundcloud.com/%s',
			'pattern' => '~^[A-Za-z0-9_\-]{3,25}$~',
			'hosts' => array('soundcloud.com'),
			'placeholder' => 'username',
			'colour' => '#ff5500',
		),
		'patreon' => array(
			'label' => 'Patreon',
			'icon' => 'fa-brands fa-patreon',
			'type' => 'handle',
			'url' => 'https://www.patreon.com/%s',
			'pattern' => '~^[A-Za-z0-9_]{1,64}$~',
			'hosts' => array('patreon.com'),
			'placeholder' => 'username',
			'colour' => '#ff424d',
		),
		'telegram' => array(
			'label' => 'Telegram',
			'icon' => 'fa-brands fa-telegram',
			'type' => 'handle',
			'url' => 'https://t.me/%s',
			'pattern' => '~^[A-Za-z0-9_]{5,32}$~',
			'hosts' => array('t.me'),
			'placeholder' => 'username',
			'colour' => '#26a5e4',
		),
		'website' => array(
			'label' => 'Website',
			'icon' => 'fa-solid fa-globe',
			'type' => 'url',
			'hosts' => array(),
			'placeholder' => 'https://example.com',
			'colour' => '#4a5568',
		),
	);

	// Let other mods add or remove networks.
	call_integration_hook('integrate_sociallinks_networks', array(&$networks));

	return $networks;
}

/**
 * Networks the admin has not switched off.
 *
 * @return array
 */
function sociallinks_enabled_networks()
{
	global $modSettings;

	$out = array();
	foreach (sociallinks_networks() as $key => $net)
		if (empty($modSettings['social_links_off_' . $key]))
			$out[$key] = $net;

	return $out;
}

/**
 * Does $host match one of $hosts (exact or subdomain)?
 */
function sociallinks_host_ok($host, array $hosts)
{
	$host = strtolower($host);
	foreach ($hosts as $h)
	{
		$h = strtolower($h);
		if ($host === $h || substr($host, -strlen($h) - 1) === '.' . $h)
			return true;
	}
	return false;
}

/**
 * Validates and normalises one value.
 *
 * @param string $key   Network key.
 * @param string $value What the member typed.
 * @return string|false Normalised value ('' = cleared) or false if invalid.
 */
function sociallinks_normalise($key, $value)
{
	$networks = sociallinks_networks();
	if (!isset($networks[$key]))
		return false;

	$net = $networks[$key];
	$value = trim((string) $value);

	if ($value === '')
		return '';

	if (strlen($value) > 255)
		return false;

	// Fediverse style "@user@instance.tld" -> https://instance.tld/@user
	if ($net['type'] === 'url' && in_array($key, array('mastodon', 'pixelfed')) && preg_match('~^@?([A-Za-z0-9_.\-]+)@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})$~', $value, $m))
		$value = 'https://' . strtolower($m[2]) . ($key === 'mastodon' ? '/@' : '/') . $m[1];

	// Something that looks like a URL.
	if (preg_match('~^https?://~i', $value) || ($net['type'] === 'url' && strpos($value, '.') !== false && strpos($value, ' ') === false))
	{
		if (!preg_match('~^https?://~i', $value))
			$value = 'https://' . $value;

		if (filter_var($value, FILTER_VALIDATE_URL) === false)
			return false;

		$parts = parse_url($value);
		if (empty($parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https')))
			return false;

		// No user:pass@host tricks.
		if (isset($parts['user']) || isset($parts['pass']))
			return false;

		if (!empty($net['hosts']) && !sociallinks_host_ok($parts['host'], $net['hosts']))
			return false;

		// Rebuild as https.
		$url = 'https://' . strtolower($parts['host'])
			. (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
			. (isset($parts['path']) ? $parts['path'] : '')
			. (isset($parts['query']) ? '?' . $parts['query'] : '');

		return $url;
	}

	if ($net['type'] === 'url')
		return false;

	// Handle networks.
	$value = ltrim($value, '@');
	if (!preg_match($net['pattern'], $value))
		return false;

	return $value;
}

/**
 * Builds the final link for a stored value.
 */
function sociallinks_build_url($key, $value)
{
	$networks = sociallinks_networks();
	if (!isset($networks[$key]) || $value === '')
		return '';

	if (preg_match('~^https://~', $value))
		return $value;

	if (!empty($networks[$key]['url']))
		return sprintf($networks[$key]['url'], rawurlencode($value));

	return '';
}
/** Got to add some copyrights **/

function sociallink_credits()
{
	global $context, $txt;

	loadLanguage('SocialLinks');

	$context['credits_modifications'][] = sprintf($txt['SocialLinks_credits'], '1.1.1');
}
/**
 * Decodes a member's stored links.
 */
function sociallinks_decode($raw)
{
	if (empty($raw))
		return array();

	$data = json_decode($raw, true);
	return is_array($data) ? $data : array();
}

/**
 * Builds the icon row HTML for a set of stored links.
 */
function sociallinks_render(array $links)
{
	global $modSettings, $txt;

	$networks = sociallinks_enabled_networks();
	$items = array();

	foreach ($networks as $key => $net)
	{
		if (empty($links[$key]))
			continue;

		$url = sociallinks_build_url($key, $links[$key]);
		if ($url === '')
			continue;

		$items[] = '<a href="' . $GLOBALS['smcFunc']['htmlspecialchars']($url) . '" class="sociallinks_icon sociallinks_' . $key . '"'
			. ' style="--sl-brand:' . $net['colour'] . '"'
			. ' title="' . $GLOBALS['smcFunc']['htmlspecialchars']($net['label']) . '"'
			. ' rel="nofollow noopener noreferrer me"'
			. (!empty($modSettings['social_links_new_tab']) ? ' target="_blank"' : '')
			. '><i class="' . $net['icon'] . '" aria-hidden="true"></i><span class="sociallinks_label">' . $GLOBALS['smcFunc']['htmlspecialchars']($net['label']) . '</span></a>';
	}

	if (empty($items))
		return '';

	$separator = isset($modSettings['social_links_separator']) ? $modSettings['social_links_separator'] : 'dashed';
	if (!in_array($separator, array('none', 'dotted', 'solid', 'dashed', 'double'), true))
		$separator = 'dashed';
	return '<div class="sociallinks sociallinks_separator_' . $separator . (!empty($modSettings['social_links_brand_colours']) ? ' sociallinks_brand' : '') . (!empty($modSettings['social_links_show_labels']) ? ' sociallinks_labels' : '') . '" aria-label="' . (isset($txt['social_links_title']) ? $txt['social_links_title'] : 'Social media') . '">'
		. implode('', $items) . '</div>';
}

/**
 * Loads the icon font + our CSS. Must run before the <head> is output.
 */
function sociallinks_load_assets()
{
	global $modSettings;
	static $done = false;

	if ($done)
		return;
	$done = true;

	loadLanguage('SocialLinks');

	if (!empty($modSettings['social_links_fa_url']))
		loadCSSFile($modSettings['social_links_fa_url'], array('external' => true, 'seed' => false), 'smf_sociallinks_fa');

	loadCSSFile('sociallinks.css', array('minimize' => true), 'smf_sociallinks');
}

/**
 * Which membergroups (incl. post count groups) may have social links?
 * Cached for a few minutes.
 *
 * @return array array('allow' => int[], 'deny' => int[])
 */
function sociallinks_permitted_groups()
{
	global $smcFunc;
	static $groups = null;

	if ($groups !== null)
		return $groups;

	if (($groups = cache_get_data('sociallinks_groups', 240)) !== null)
		return $groups;

	$groups = array('allow' => array(), 'deny' => array());

	$request = $smcFunc['db_query']('', '
		SELECT id_group, add_deny
		FROM {db_prefix}permissions
		WHERE permission IN ({array_string:perms})',
		array(
			'perms' => array('social_links_edit_own', 'social_links_edit_any'),
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$groups[$row['add_deny'] ? 'allow' : 'deny'][] = (int) $row['id_group'];
	$smcFunc['db_free_result']($request);

	cache_put_data('sociallinks_groups', $groups, 240);

	return $groups;
}

/**
 * Is this member (from $user_profile) currently allowed to show links?
 * Checks primary group, additional groups and post count group.
 */
function sociallinks_member_allowed(array $profile)
{
	global $modSettings;

	$member_groups = array((int) $profile['id_group'], (int) $profile['id_post_group']);
	if (!empty($profile['additional_groups']))
		$member_groups = array_merge($member_groups, array_map('intval', explode(',', $profile['additional_groups'])));

	// Admins can always.
	if (in_array(1, $member_groups))
		return true;

	$groups = sociallinks_permitted_groups();

	if (!empty($modSettings['permission_enable_deny']) && array_intersect($member_groups, $groups['deny']))
		return false;

	return (bool) array_intersect($member_groups, $groups['allow']);
}

/* ---------------------------------------------------------------------
 *  Hooks
 * ------------------------------------------------------------------- */

/**
 * integrate_load_permissions
 */
function sociallinks_load_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	loadLanguage('SocialLinks');

	// Own/any: members may edit their own; moderators may edit anyone's.
	$permissionList['membergroup']['social_links_edit'] = array(true, 'profile');
	// Who can see the icons on posts.
	$permissionList['membergroup']['social_links_view'] = array(false, 'profile');
}

/**
 * integrate_load_illegal_guest_permissions
 */
function sociallinks_illegal_guest_permissions()
{
	global $context;

	$context['non_guest_permissions'][] = 'social_links_edit';
}

/**
 * integrate_load_member_data - pull our column in with the member row.
 */
function sociallinks_load_member_data(&$select_columns, &$select_tables, &$set)
{
	if ($set === 'normal' || $set === 'profile')
		$select_columns .= ', mem.social_links';
}

/**
 * integrate_display_topic - load CSS/fonts before the page header is sent.
 */
function sociallinks_display_topic(&$topic_selects, &$topic_tables, &$topic_parameters)
{
	global $modSettings;

	if (!empty($modSettings['social_links_enabled']) && allowedTo('social_links_view'))
		sociallinks_load_assets();
}

/**
 * integrate_prepare_display_context - add the icon row in the "below signature"
 * slot, which the default theme outputs straight after the signature.
 */
function sociallinks_prepare_display_context(&$output, &$message, $counter)
{
	global $modSettings, $user_profile;
	static $can_view = null;

	if (empty($modSettings['social_links_enabled']) || empty($message['id_member']))
		return;

	if ($can_view === null)
		$can_view = allowedTo('social_links_view');
	if (!$can_view)
		return;

	$id = (int) $message['id_member'];
	if (empty($user_profile[$id]['social_links']))
		return;

	// Respect the poster's current group / post group permissions.
	if (!sociallinks_member_allowed($user_profile[$id]))
		return;

	$html = sociallinks_render(sociallinks_decode($user_profile[$id]['social_links']));
	if ($html === '')
		return;

	// Put ours last so it sits under any other below-signature fields.
	$output['custom_fields']['below_signature'][] = array(
		'title' => '',
		'col_name' => 'sociallinks_row',
		'value' => $html,
		'placement' => 2,
	);
}

/**
 * integrate_profile_areas - adds "Social Media Links" under Modify Profile.
 */
function sociallinks_profile_areas(&$profile_areas)
{
	global $txt, $modSettings;

	loadLanguage('SocialLinks');

	$profile_areas['edit_profile']['areas']['sociallinks'] = array(
		'label' => $txt['social_links_profile_area'],
		'file' => 'SocialLinks.php',
		'function' => 'sociallinks_profile',
		'icon' => 'members',
		'enabled' => !empty($modSettings['social_links_enabled']),
		'permission' => array(
			'own' => array('social_links_edit_own'),
			'any' => array('social_links_edit_any'),
		),
	);
}

/** Admin sidebar subsection. */
function sociallinks_admin_areas(&$admin_areas)
{
	global $txt;
	loadLanguage('SocialLinks');
	if (isset($admin_areas['config']['areas']['modsettings']))
		$admin_areas['config']['areas']['modsettings']['subsections']['sociallinks'] = array($txt['social_links_settings_title']);
}

/** Dedicated Modification Settings tab. */
function sociallinks_modify_modifications(&$subActions)
{
	global $context, $txt;
	loadLanguage('SocialLinks');
	$subActions['sociallinks'] = 'sociallinks_admin_settings';
	$context[$context['admin_menu_name']]['tab_data']['tabs']['sociallinks'] = array('description' => $txt['social_links_settings_desc']);
}

function sociallinks_settings_config()
{
	global $txt;
	loadLanguage('SocialLinks');
	$config_vars = array(
		array('check', 'social_links_enabled'),
		array('text', 'social_links_fa_url', 'size' => 60, 'subtext' => $txt['social_links_fa_url_desc']),
		array('check', 'social_links_new_tab'),
		array('check', 'social_links_brand_colours'),
		array('check', 'social_links_show_labels'),
		array('select', 'social_links_separator', array(
			'none' => $txt['social_links_separator_none'],
			'dotted' => $txt['social_links_separator_dotted'],
			'solid' => $txt['social_links_separator_solid'],
			'dashed' => $txt['social_links_separator_dashed'],
			'double' => $txt['social_links_separator_double'],
		), 'subtext' => $txt['social_links_separator_desc']),
		array('desc', 'social_links_perm_note'),
	);
	foreach (sociallinks_networks() as $key => $net)
	{
		$txt['social_links_off_' . $key] = sprintf($txt['social_links_hide_network'], $net['label']);
		$config_vars[] = array('check', 'social_links_off_' . $key);
	}
	return $config_vars;
}

function sociallinks_admin_settings($return_config = false)
{
	global $context, $txt, $scripturl;
	$config_vars = sociallinks_settings_config();
	if ($return_config)
		return $config_vars;
	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;sa=sociallinks;save';
	$context['settings_title'] = $txt['social_links_settings_title'];
	if (isset($_GET['save']))
	{
		checkSession();
		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=modsettings;sa=sociallinks');
	}
	prepareDBSettingContext($config_vars);
}

/* ---------------------------------------------------------------------
 *  Profile page
 * ------------------------------------------------------------------- */

/**
 * Profile area: edit social links.
 *
 * @param int $memID
 */
function sociallinks_profile($memID)
{
	global $context, $txt, $user_profile, $scripturl, $smcFunc;

	loadLanguage('SocialLinks');
	loadTemplate('SocialLinks');
	sociallinks_load_assets();

	$networks = sociallinks_enabled_networks();
	$current = sociallinks_decode(isset($user_profile[$memID]['social_links']) ? $user_profile[$memID]['social_links'] : '');

	$context['sociallinks_errors'] = array();
	$context['sociallinks_values'] = array();

	foreach ($networks as $key => $net)
		$context['sociallinks_values'][$key] = isset($current[$key]) ? $current[$key] : '';

	if (isset($_POST['social_save']))
	{
		checkSession('post');

		$new = $current;
		foreach ($networks as $key => $net)
		{
			$posted = isset($_POST['social'][$key]) && is_string($_POST['social'][$key]) ? $_POST['social'][$key] : '';
			$clean = sociallinks_normalise($key, $posted);

			if ($clean === false)
			{
				$context['sociallinks_errors'][$key] = sprintf($txt['social_links_invalid'], $net['label']);
				$context['sociallinks_values'][$key] = $posted;
				continue;
			}

			if ($clean === '')
				unset($new[$key]);
			else
				$new[$key] = $clean;

			$context['sociallinks_values'][$key] = $clean;
		}

		if (empty($context['sociallinks_errors']))
		{
			updateMemberData($memID, array('social_links' => empty($new) ? '' : json_encode($new)));
			redirectexit('action=profile;area=sociallinks;u=' . $memID . ';saved');
		}
	}

	$context['sociallinks_networks'] = $networks;
	$context['sociallinks_saved'] = isset($_GET['saved']);
	$context['sociallinks_preview'] = sociallinks_render($current);
	$context['sociallinks_action'] = $scripturl . '?action=profile;area=sociallinks;u=' . $memID;
	$context['page_title'] = $txt['social_links_profile_area'];
	$context['sub_template'] = 'sociallinks_profile';
}
