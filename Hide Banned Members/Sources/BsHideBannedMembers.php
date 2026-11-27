<?php
/**
 * BoredSkull Hide Banned Members 1.0.2
 * SMF 2.1.x; ISC license.
 * No core edits, no additional database tables.
 */
if (!defined('SMF'))
    die('No direct access...');

/**
 * Existing installations default to enabled, even before settings are saved.
 * A disabled toggle takes effect immediately without changing SMF ban records.
 */
function bshbm_enabled($key = 'bshbm_enabled')
{
    global $modSettings;
    return !array_key_exists($key, $modSettings) || !empty($modSettings[$key]);
}

/** Add a settings tab to Admin > Members > Members List via SMF 2.1 hook. */
function bshbm_register_members_settings(&$subActions)
{
    global $context, $txt, $scripturl;
    if (!allowedTo('admin_forum'))
        return;
    loadLanguage('BsHideBannedMembers');
    $subActions['bshbm'] = array('bshbm_settings', 'admin_forum');
    $context['tabs']['bshbm'] = array(
        'label' => $txt['bshbm_settings_title'],
        'description' => $txt['bshbm_settings_title'],
        'url' => $scripturl . '?action=admin;area=viewmembers;sa=bshbm',
    );
    $context['last_tab'] = 'bshbm';
}

/** SMF's standard settings renderer and CSRF validation are used. */
function bshbm_settings($return_config = false)
{
    global $context, $txt, $scripturl, $sourcedir;
    isAllowedTo('admin_forum');
    // The member administration area does not load the DB settings helpers.
    // SMF defines prepareDBSettingContext() and saveDBSettings() here.
    require_once($sourcedir . '/ManageServer.php');
    loadLanguage('BsHideBannedMembers');
    loadLanguage('ManageSettings');
    $context['sub_template'] = 'show_settings';
    $config_vars = array(
        array('check', 'bshbm_enabled'),
        array('check', 'bshbm_public'),
        array('check', 'bshbm_admin'),
        array('check', 'bshbm_search'),
        array('check', 'bshbm_notice'),
    );
    if ($return_config)
        return $config_vars;

    if (isset($_GET['save']))
    {
        checkSession();
        saveDBSettings($config_vars);
        $_SESSION['adm-save'] = true;
        redirectexit('action=admin;area=viewmembers;sa=bshbm');
    }

    // SMF's settings context normally displays missing checkbox values as off.
    // Match our backward-compatible runtime defaults on first install.
    global $modSettings;
    foreach (array('bshbm_enabled', 'bshbm_public', 'bshbm_admin', 'bshbm_search', 'bshbm_notice') as $key)
        if (!isset($modSettings[$key]))
            $modSettings[$key] = '1';

    $context['post_url'] = $scripturl . '?action=admin;area=viewmembers;sa=bshbm;save';
    $context['settings_title'] = $txt['bshbm_settings_title'];
    prepareDBSettingContext($config_vars);
}


/**
 * Fetch account-specific, active access/login bans.
 * An IP, hostname or email match is deliberately NOT treated as an account ban.
 * Expired or future-dated rules are excluded. The cache is intentionally short,
 * so ban changes show up promptly without introducing stale permanent state.
 *
 * @return array<int,bool> Member IDs as a membership set.
 */
function bshbm_banned_ids()
{
    global $smcFunc;
    static $cached = null;
    if ($cached !== null)
        return $cached;

    $cached = array();
    $now = time();
    $request = $smcFunc['db_query']('', '
        SELECT DISTINCT bi.id_member
        FROM {db_prefix}ban_items AS bi
            INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
        WHERE bi.id_member > {int:zero}
            AND bg.ban_time <= {int:now}
            AND (bg.expire_time = {int:zero} OR bg.expire_time > {int:now})
            AND (bg.cannot_access = {int:yes} OR bg.cannot_login = {int:yes})',
        array('zero' => 0, 'now' => $now, 'yes' => 1)
    );
    while ($row = $smcFunc['db_fetch_row']($request))
        $cached[(int) $row[0]] = true;
    $smcFunc['db_free_result']($request);

    // SMF also records banned account state in members.is_activated.
    // 10 = banned/unactivated, 11 = banned/activated in SMF 2.1.
    $request = $smcFunc['db_query']('', '
        SELECT id_member FROM {db_prefix}members
        WHERE is_activated >= {int:banned_status}',
        array('banned_status' => 10));
    while ($row = $smcFunc['db_fetch_row']($request))
        $cached[(int) $row[0]] = true;
    $smcFunc['db_free_result']($request);

    // Other BoredSkull mods may add IDs for their own ban systems. Consumers
    // must still apply their own board and content visibility checks.
    call_integration_hook('integrate_bshbm_banned_ids', array(&$cached));
    $cached = array_filter($cached);
    return $cached;
}

function bshbm_should_hide($member_id)
{
    if (!bshbm_enabled())
        return false;
    $banned = bshbm_banned_ids();
    return isset($banned[(int) $member_id]);
}

/** Return a filtered copy of member IDs for external discovery lists. */
function bshbm_filter_member_ids($ids)
{
    if (!bshbm_enabled())
        return array_values((array) $ids);
    $banned = bshbm_banned_ids();
    return array_values(array_filter((array) $ids, function ($id) use ($banned) {
        return !isset($banned[(int) $id]);
    }));
}

/**
 * SMF has no filter hook in MLAll()/MLSearch() query itself. At the pre-CSS
 * stage Memberlist() has populated $context['members'], and rendering has not
 * started. This provides a no-theme-edit, server-side display filter.
 * Pagination is still calculated by SMF before the filter; see README.
 */
function bshbm_filter_memberlist()
{
    global $context;
    if (!bshbm_enabled() || !bshbm_enabled('bshbm_public'))
        return;
    if (!empty($context['listing_by']) && $context['listing_by'] === 'search' && !bshbm_enabled('bshbm_search'))
        return;
    if (empty($context['current_action']) || $context['current_action'] !== 'mlist' || empty($context['members']))
        return;

    $banned = bshbm_banned_ids();
    if (!$banned)
        return;
    foreach ($context['members'] as $id => $info)
        if (isset($banned[(int) $id]))
            unset($context['members'][$id]);
}

/** Mark loaded member context so other mods can omit the same account. */
function bshbm_member_context(&$member, $member_id, $custom_fields = false)
{
    $member['bshbm_hidden'] = bshbm_should_hide($member_id);
}

/**
 * SMF 2.1 Subs-List.php calls integrate_member_list before counting or paging
 * the Admin > Members List. Apply identical SQL conditions to both callbacks.
 * Unlike a render-only filter, this gives correct admin totals and full pages.
 */
function bshbm_admin_member_list(&$options)
{
    if (!bshbm_enabled() || !bshbm_enabled('bshbm_admin'))
        return;
    if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] === 'query' && !bshbm_enabled('bshbm_search'))
        return;
    if (!isset($options['get_items']['params'], $options['get_count']['params']))
        return;

    $where_extra = 'mem.is_activated < {int:bshbm_banned_status}
        AND NOT EXISTS (
            SELECT 1 FROM {db_prefix}ban_items AS bshbi
                INNER JOIN {db_prefix}ban_groups AS bshbg
                    ON bshbg.id_ban_group = bshbi.id_ban_group
            WHERE bshbi.id_member = mem.id_member
                AND bshbg.ban_time <= {int:bshbm_now}
                AND (bshbg.expire_time = {int:bshbm_zero} OR bshbg.expire_time > {int:bshbm_now})
                AND (bshbg.cannot_access = {int:bshbm_yes} OR bshbg.cannot_login = {int:bshbm_yes})
        )';
    $params_extra = array('bshbm_banned_status' => 10, 'bshbm_now' => time(), 'bshbm_zero' => 0, 'bshbm_yes' => 1);

    bshbm_admin_notice($options);

    foreach (array('get_items', 'get_count') as $part)
    {
        $params = &$options[$part]['params'];
        $params[0] = '(' . (!empty($params[0]) ? $params[0] : '1=1') . ') AND (' . $where_extra . ')';
        if (!isset($params[1]) || !is_array($params[1]))
            $params[1] = array();
        $params[1] = array_merge($params[1], $params_extra);
        unset($params);
    }
}


/** Optional informational notice on admin Member List (not a new ban list). */
function bshbm_admin_notice(&$options)
{
    global $txt;
    if (!bshbm_enabled() || !bshbm_enabled('bshbm_admin') || !bshbm_enabled('bshbm_notice'))
        return;
    loadLanguage('BsHideBannedMembers');
    $n = count(bshbm_banned_ids());
    if ($n === 0)
        return;
    if (!isset($options['additional_rows']) || !is_array($options['additional_rows']))
        $options['additional_rows'] = array();
    $options['additional_rows'][] = array(
        'position' => 'top_of_list',
        'value' => '<div class="information">' . sprintf($txt['bshbm_hidden_count'], $n) . '</div>',
    );
}
