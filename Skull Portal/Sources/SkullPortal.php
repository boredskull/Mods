<?php
/**
 * Skull Portal 1.0.0 - BoredSkull. ISC License.
 * Public extension hooks: integrate_skullportal_blocks(&$types),
 * integrate_skullportal_block_output(&$html, $block, $page).
 * Registry entry: ['label'=>string, 'render'=>callable, 'visible'=>optional callable].
 * A registered render callback receives (array $block) and RETURNS trusted HTML.
 * Integration authors must enforce their own access permissions and HTML escaping.
 */
if (!defined('SMF')) die('No direct access');

function skullportal_actions(&$actions)
{
    $actions['portal'] = array('SkullPortal.php', 'skullportal_main');
    // Keep the normal board index accessible when Skull Portal is the homepage.
    $actions['forum'] = array('SkullPortal.php', 'skullportal_forum_main');
}

/** Dedicated entry point for the traditional board index when the portal is Home. */
function skullportal_forum_main()
{
    global $sourcedir;
    require_once($sourcedir . '/BoardIndex.php');
    BoardIndex();
}

function skullportal_menu(&$buttons)
{
    global $scripturl, $modSettings, $txt, $user_info;

    // Keep management accessible from the existing Admin dropdown, without
    // adding another top-level menu button. SMF only supplies the Admin button
    // to users permitted to access the administration centre.
    if (isset($buttons['admin']) && !empty($buttons['admin']['show']) && !empty($user_info['is_admin']))
    {
        loadLanguage('SkullPortal');
        if (!isset($buttons['admin']['sub_buttons']) || !is_array($buttons['admin']['sub_buttons']))
            $buttons['admin']['sub_buttons'] = array();
        $buttons['admin']['sub_buttons']['skullportal'] = array(
            'title' => $txt['skullportal_admin'],
            'href' => $scripturl . '?action=admin;area=skullportal',
            'show' => true,
        );
    }

    // The portal is already reachable through Home when used as the homepage.
    // Do not add a duplicate top-level Skull Portal menu item.
    unset($buttons['skullportal']);

    // If the forum index is no longer the homepage, provide its own link
    // directly after Home, without changing the order of other SMF buttons.
    if (empty($modSettings['skullportal_enabled']) || empty($modSettings['skullportal_homepage']))
    {
        unset($buttons['skullportal_forum']);
        return;
    }

    loadLanguage('SkullPortal');
    $forum = array(
        'title' => $txt['skullportal_forum'],
        'href' => $scripturl . '?action=forum',
        'show' => true,
        'sub_buttons' => array(),
    );

    // Remove any previous instance first to prevent duplicate buttons.
    unset($buttons['skullportal_forum']);
    $reordered = array();
    $inserted = false;
    foreach ($buttons as $key => $button)
    {
        $reordered[$key] = $button;
        if ($key === 'home')
        {
            $reordered['skullportal_forum'] = $forum;
            $inserted = true;
        }
    }
    if (!$inserted)
        $reordered = array('skullportal_forum' => $forum) + $reordered;
    $buttons = $reordered;
}

/** Highlight the correct menu item when the portal replaces the forum homepage. */
function skullportal_current_action(&$current_action)
{
    global $modSettings;
    if (empty($modSettings['skullportal_enabled']) || empty($modSettings['skullportal_homepage']))
        return;

    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
    if ($action === 'portal')
    {
        $current_action = 'home';
        return;
    }

    // SMF routes boards and topics without an action parameter; these belong
    // to Forum rather than the portal's Home button.
    if ($action === 'forum' || $action === 'display' || ($action === '' &&
        (isset($_REQUEST['board']) || isset($_REQUEST['topic']))))
        $current_action = 'skullportal_forum';
}

/**
 * Preserve a proper Forum breadcrumb when Skull Portal replaces SMF's home.
 * The main menu and the breadcrumb tree are independent in SMF 2.1.
 * Only replace the root URL, leaving board/topic ancestors untouched.
 */
function skullportal_forum_linktree()
{
    global $context, $modSettings, $scripturl;

    if (empty($modSettings['skullportal_enabled']) || empty($modSettings['skullportal_homepage']))
        return;

    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $is_forum_page = $action === 'forum' || $action === 'display'
        || ($action === '' && (isset($_REQUEST['board']) || isset($_REQUEST['topic'])));

    if (!$is_forum_page || empty($context['linktree']) || !is_array($context['linktree']))
        return;

    // SMF's initial breadcrumb is the forum root, traditionally $scripturl.
    // Don't change a custom theme/mod's unrelated first breadcrumb.
    if (!isset($context['linktree'][0]) || !is_array($context['linktree'][0]))
        return;

    $root = &$context['linktree'][0];
    if (!isset($root['url']) || !is_string($root['url']))
        return;

    if ($root['url'] === $scripturl || $root['url'] === $scripturl . '?'
        || $root['url'] === $scripturl . '?action=portal')
        $root['url'] = $scripturl . '?action=forum';
}

function skullportal_admin_areas(&$admin_areas)
{
    global $txt;
    loadLanguage('SkullPortal');
    $admin_areas['config']['areas']['skullportal'] = array(
        'label' => $txt['skullportal_admin'],
        'file' => 'SkullPortalAdmin.php', 'function' => 'skullportal_admin',
        'icon' => 'features', 'permission' => array('admin_forum'),
    );
}

function skullportal_pre_load()
{
    // Loading this file before the SMF default-action dispatcher makes the
    // skullportal_main callback available when configured as default action.
}

function skullportal_esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function skullportal_types()
{
    global $scripturl, $modSettings, $user_info, $txt;
    $types = array(
        'bbc' => array('label' => 'BBCode', 'render' => 'skullportal_render_bbc'),
        'text' => array('label' => 'Plain text', 'render' => 'skullportal_render_text'),
        'html' => array('label' => 'HTML (trusted admin)', 'render' => 'skullportal_render_html'),
        'welcome' => array('label' => 'Welcome', 'render' => 'skullportal_render_welcome'),
        'stats' => array('label' => 'Forum statistics', 'render' => 'skullportal_render_stats'),
        'navigation' => array('label' => 'Navigation links', 'render' => 'skullportal_render_navigation'),
    );
    // Providers register themselves. Never hard-code ecosystem blocks: if the
    // provider hook is gone, its block type disappears from the admin and site.
    $native = $types;
    call_integration_hook('integrate_skullportal_blocks', array(&$types));
    // Built-ins are not replaceable by optional integrations.
    foreach ($native as $key => $definition) $types[$key] = $definition;
    $builtins = array('bbc', 'text', 'html', 'welcome', 'stats', 'navigation');
    foreach ($types as $id => $type)
    {
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{0,59}$/', $id) || !is_array($type)
            || empty($type['label']) || !isset($type['render']) || !is_callable($type['render']))
            unset($types[$id]);
        // Every extension must explicitly confirm that its provider is active.
        // Hook registration by itself is not evidence that a mod is enabled.
        elseif (!in_array($id, $builtins, true))
        {
            if (!isset($type['active']) || !is_callable($type['active']))
            {
                unset($types[$id]);
                continue;
            }
            try { if (!call_user_func($type['active'])) unset($types[$id]); }
            catch (Throwable $exception) { unset($types[$id]); }
        }
    }
    return $types;
}

function skullportal_render_bbc($block)
{
    return parse_bbc($block['content']);
}
function skullportal_render_text($block)
{
    return nl2br(skullportal_esc($block['content']));
}
function skullportal_render_html($block)
{
    // The editor is restricted to admin_forum; do not use this for untrusted input.
    return $block['content'];
}
/**
 * Welcome is a self-contained member information block. The content field is
 * reserved for JSON display preferences, never custom HTML/BBCode.
 * Existing free-form content from older release candidates is ignored.
 */
function skullportal_welcome_options($block)
{
    $defaults = array('avatar' => true, 'name' => true, 'group' => true,
        'stars' => true, 'posts' => true, 'unread' => true,
        'replies' => true, 'profile' => true, 'account' => true, 'logout' => true);
    $raw = json_decode((string) ($block['content'] ?? ''), true);
    if (!is_array($raw) || !isset($raw['welcome_options']) || !is_array($raw['welcome_options']))
        return $defaults;
    foreach ($defaults as $key => $value)
        if (array_key_exists($key, $raw['welcome_options']))
            $defaults[$key] = (bool) $raw['welcome_options'][$key];
    return $defaults;
}

function skullportal_render_welcome($block)
{
    global $user_info, $memberContext, $scripturl, $txt, $modSettings, $context;

    $options = skullportal_welcome_options($block);
    if (!empty($user_info['is_guest']))
    {
        $html = '<div class="skullportal-welcome"><p>'
            . skullportal_esc($txt['skullportal_guest_welcome'] ?? 'Welcome, guest!')
            . '</p><div class="skullportal-member-links"><a href="'
            . skullportal_esc($scripturl . '?action=login') . '">'
            . skullportal_esc($txt['login'] ?? 'Log in') . '</a>';
        // SMF disables registration using registration_method = 3.
        if ((int) ($modSettings['registration_method'] ?? 0) !== 3)
            $html .= '<a href="' . skullportal_esc($scripturl . '?action=register') . '">'
                . skullportal_esc($txt['register'] ?? 'Register') . '</a>';
        return $html . '</div></div>';
    }

    $id = (int) ($user_info['id'] ?? 0);
    $profile = array();
    if ($id > 0 && function_exists('loadMemberData') && function_exists('loadMemberContext'))
    {
        loadMemberData($id);
        if (loadMemberContext($id) && !empty($memberContext[$id]))
            $profile = $memberContext[$id];
    }

    $profileUrl = $scripturl . '?action=profile;u=' . $id;
    $name = (string) ($profile['name'] ?? $user_info['name'] ?? 'Member');
    $html = '<div class="skullportal-welcome"><div class="skullportal-member-panel">';
    if ($options['avatar'] && !empty($profile['avatar']['image']) && is_string($profile['avatar']['image']))
        $html .= '<div class="skullportal-member-avatar">' . $profile['avatar']['image'] . '</div>';
    $html .= '<div class="skullportal-member-details">';
    if ($options['name'])
        $html .= '<a class="skullportal-member-name" href="' . skullportal_esc($profileUrl) . '">'
            . skullportal_esc($name) . '</a>';
    $rank = (string) ($profile['group'] ?? $profile['post_group'] ?? '');
    if ($options['group'] && $rank !== '')
        $html .= '<div class="skullportal-member-rank">' . skullportal_esc($rank) . '</div>';
    if ($options['stars'] && !empty($profile['group_stars']) && is_string($profile['group_stars']))
        $html .= '<div class="skullportal-member-stars">' . $profile['group_stars'] . '</div>';
    if ($options['posts'] && isset($profile['posts']))
        $html .= '<div class="skullportal-member-posts">'
            . skullportal_esc($txt['posts'] ?? 'Posts') . ': ' . skullportal_esc($profile['posts']) . '</div>';
    $html .= '</div></div><div class="skullportal-member-links">';
    $links = array();
    if ($options['unread'])
        $links[] = array($scripturl . '?action=unread', $txt['show_unread'] ?? 'Unread posts');
    if ($options['replies'])
        $links[] = array($scripturl . '?action=unreadreplies', $txt['show_replies'] ?? 'New replies');
    if ($options['profile'])
        $links[] = array($profileUrl, $txt['profile'] ?? 'Profile');
    if ($options['account'])
        $links[] = array($scripturl . '?action=profile;area=account', $txt['account'] ?? 'Account');
    // Use SMF's pre-built logout link, which contains its session token.
    if ($options['logout'] && !empty($context['menu_buttons']['logout']['href']))
        $links[] = array($context['menu_buttons']['logout']['href'], $txt['logout'] ?? 'Log out');
    foreach ($links as $link)
        $html .= '<a href="' . skullportal_esc($link[0]) . '">' . skullportal_esc($link[1]) . '</a>';
    return $html . '</div></div>';
}
function skullportal_render_stats($block)
{
    global $modSettings;
    return '<ul><li>Members: ' . (int) ($modSettings['totalMembers'] ?? 0)
        . '</li><li>Topics: ' . (int) ($modSettings['totalTopics'] ?? 0)
        . '</li><li>Posts: ' . (int) ($modSettings['totalMessages'] ?? 0) . '</li></ul>';
}
function skullportal_render_navigation($block)
{
    global $scripturl;
    return '<ul><li><a href="' . skullportal_esc($scripturl . '?action=forum') . '">Forum</a></li>'
        . '<li><a href="' . skullportal_esc($scripturl . '?action=recent') . '">Recent posts</a></li>'
        . '<li><a href="' . skullportal_esc($scripturl . '?action=help') . '">Help</a></li></ul>';
}

/**
 * Convert saved per-block presentation options into safe, scoped CSS variables.
 * Never allow arbitrary CSS from stored data to reach the page.
 */
function skullportal_block_style($block)
{
    $settings = json_decode((string) ($block['style_settings'] ?? ''), true);
    if (!is_array($settings)) return '';
    $names = array(
        'header_bg' => '--sp-header-bg', 'header_text' => '--sp-header-text',
        'body_bg' => '--sp-body-bg', 'body_text' => '--sp-body-text',
        'border' => '--sp-border',
    );
    $css = array();
    foreach ($names as $name => $variable)
        if (!empty($settings[$name]) && is_string($settings[$name])
            && preg_match('/^#[0-9a-fA-F]{6}$/D', $settings[$name]))
            $css[] = $variable . ':' . $settings[$name];
    $radii = array('square' => '0px', 'small' => '4px', 'rounded' => '12px');
    if (isset($settings['radius']) && isset($radii[$settings['radius']]))
        $css[] = '--sp-radius:' . $radii[$settings['radius']];
    return implode(';', $css);
}

function skullportal_visible($block, $types)
{
    global $user_info;
    if (!$block['enabled'] || !isset($types[$block['block_type']])) return false;
    if ($block['visibility'] === 'members' && !empty($user_info['is_guest'])) return false;
    if ($block['visibility'] === 'guests' && empty($user_info['is_guest'])) return false;
    if (strpos((string) $block['visibility'], 'group:') === 0)
    {
        if (!empty($user_info['is_guest'])) return false;
        $wanted = (int) substr($block['visibility'], 6);
        $groups = array_map('intval', array_merge(
            array((int) ($user_info['groups'][0] ?? $user_info['id_group'] ?? 0)),
            (array) ($user_info['groups'] ?? array())
        ));
        if ($wanted < 1 || !in_array($wanted, $groups, true)) return false;
    }
    if (isset($types[$block['block_type']]['visible']))
    {
        $fn = $types[$block['block_type']]['visible'];
        if (!is_callable($fn)) return false;
        try { if (!call_user_func($fn, $block)) return false; }
        catch (Throwable $exception)
        {
            error_log('Skull Portal visibility check failed: ' . $exception->getMessage());
            return false;
        }
    }
    return true;
}

/** Fetch and render blocks for a given page type, using the same visibility rules. */
function skullportal_collect_blocks($page)
{
    global $smcFunc;
    $columns = array('left' => array(), 'centre' => array(), 'right' => array(), 'bottom' => array());
    $types = skullportal_types();
    $request = $smcFunc['db_query']('', '
        SELECT id_block, title, block_type, content, position, sort_order, visibility, enabled, show_title, display_on, style_settings
        FROM {db_prefix}skullportal_blocks
        WHERE enabled = {int:enabled} AND display_on IN ({array_string:scope})
        ORDER BY sort_order, id_block', array('enabled' => 1, 'scope' => array('both', $page)));
    while ($block = $smcFunc['db_fetch_assoc']($request))
    {
        if (!isset($columns[$block['position']]) || !skullportal_visible($block, $types)) continue;
        try
        {
            $html = call_user_func($types[$block['block_type']]['render'], $block);
            if (!is_string($html)) continue;
            // Optional output integration for installed ecosystem providers.
            // This is invoked only after block visibility checks have passed.
            call_integration_hook('integrate_skullportal_block_output', array(&$html, $block, $page));
            if (!is_string($html)) continue;
            $block['rendered'] = $html;
            $columns[$block['position']][] = $block;
        }
        catch (Throwable $exception)
        {
            error_log('Skull Portal block ' . (int) $block['id_block'] . ': ' . $exception->getMessage());
        }
    }
    $smcFunc['db_free_result']($request);
    return $columns;
}

/** Inject a theme-independent content layer around regular forum pages. */
function skullportal_forum_context()
{
    global $context, $modSettings;
    if (empty($modSettings['skullportal_enabled'])) return;

    // Do not wrap the portal itself, admin, login, profiles, XML, or other actions.
    $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
    if ($action !== '' && !in_array($action, array('forum', 'display'), true)) return;
    // An empty action with board/topic parameters is a forum page, even when
    // Skull Portal owns the site's default (empty-URL) homepage.
    if ($action === '' && !empty($modSettings['skullportal_homepage'])
        && !isset($_REQUEST['board']) && !isset($_REQUEST['topic'])) return;
    if (!empty($context['xml']) || !isset($context['template_layers'])
        || !is_array($context['template_layers'])) return;
    // Board and topic pages are routed by board/topic query parameters.
    // An empty action is SMF's board index or a board/topic route.
    if ($action === '' && isset($_REQUEST['action'])) return;

    // A forum-page integration hook can run more than once if another theme
    // rebuilds context; avoid duplicating the wrapping template layer.
    if (in_array('skullportal_forum', $context['template_layers'], true)) return;

    $columns = skullportal_collect_blocks('forum');
    if (!array_filter($columns)) return;

    loadTemplate('SkullPortal');
    loadCSSFile('skullportal.css', array('default_theme' => true));
    $context['skullportal_forum_columns'] = $columns;
    // SMF 2.1 layers run *_above before the action and *_below after it.
    // BoardIndex's Info Centre is in the boardindex_outer layer. Put our
    // wrapper outside that layer, so the bottom blocks are emitted after it.
    // The layer's *_below function runs after the layers nested within it.
    $index = array_search('boardindex_outer', $context['template_layers'], true);
    if ($index !== false)
        array_splice($context['template_layers'], $index, 0, array('skullportal_forum'));
    else
        $context['template_layers'][] = 'skullportal_forum';
}

function skullportal_main()
{
    global $modSettings, $context, $txt, $scripturl;
    if (empty($modSettings['skullportal_enabled'])) redirectexit();
    loadLanguage('SkullPortal');
    loadTemplate('SkullPortal');
    loadCSSFile('skullportal.css', array('default_theme' => true));
    // Respect the site name configured in SMF rather than showing the mod name.
    // SMF provides the forum name in theme context; Settings.php is a fallback.
    global $mbname;
    $context['page_title'] = !empty($context['forum_name']) ? $context['forum_name'] : $mbname;
    $context['sub_template'] = 'skullportal';
    $context['canonical_url'] = $scripturl . '?action=portal';
    $context['skullportal_columns'] = skullportal_collect_blocks('portal');
    // Admin-only preview. Still uses the normal visibility and permission
    // checks, so a preview never grants access to private provider content.
    if (!empty($_GET['sp_preview']) && allowedTo('admin_forum'))
        $context['skullportal_preview'] = true;
}
