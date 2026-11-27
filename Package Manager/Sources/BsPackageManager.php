<?php
/**
 * Package Manager Enhancements 1.0.0
 * ISC License. SMF 2.1.x.
 * This mod NEVER installs, upgrades, removes, or executes package content.
 */
if (!defined('SMF')) {
    die('No direct access');
}

/**
 * Register Package Dashboard in SMF's REAL admin menu, before createMenu()
 * builds the package tabs. `integrate_manage_packages` is too late for that.
 * Preserve Browse Packages as SMF's default first subsection.
 */
function bspm_register_admin_areas(&$admin_areas)
{
    if (empty($admin_areas['forum']['areas']['packages']['subsections']) ||
        !is_array($admin_areas['forum']['areas']['packages']['subsections'])) {
        return;
    }

    loadLanguage('BsPackageManager');
    global $txt;

    $subsections = $admin_areas['forum']['areas']['packages']['subsections'];
    if (isset($subsections['bspm'])) {
        return;
    }

    // Keep Browse as the native default, then insert Dashboard beside it.
    $updated = array();
    $added = false;
    foreach ($subsections as $key => $definition) {
        $updated[$key] = $definition;
        if ($key === 'browse') {
            $updated['bspm'] = array($txt['bspm_dashboard']);
            $added = true;
        }
    }
    if (!$added) {
        $updated['bspm'] = array($txt['bspm_dashboard']);
    }
    $admin_areas['forum']['areas']['packages']['subsections'] = $updated;
}

/** Extend SMF's existing Package Manager menu; the native engine is untouched. */
function bspm_register_dashboard(&$subActions)
{
    global $context, $txt;
    loadLanguage('BsPackageManager');
    $subActions['bspm'] = 'bspm_dashboard';
    bspm_load_assets();

    // Packages.php resolves the subaction before firing this hook, so its
    // value must be updated here for our added subaction to be dispatched.
    if (isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'bspm') {
        $context['sub_action'] = 'bspm';
    }

    // Packages.php has already created tab_data by this point. Provide the
    // dashboard's description, leaving all four stock tabs untouched.
    if (!empty($context['admin_menu_name']) &&
        isset($context[$context['admin_menu_name']]['tab_data']['tabs']) &&
        is_array($context[$context['admin_menu_name']]['tab_data']['tabs'])) {
        $tabs = $context[$context['admin_menu_name']]['tab_data']['tabs'];
        if (!isset($tabs['bspm'])) {
            $new_tabs = array();
            $added = false;
            foreach ($tabs as $key => $definition) {
                $new_tabs[$key] = $definition;
                if ($key === 'browse') {
                    $new_tabs['bspm'] = array('description' => $txt['bspm_dashboard_desc']);
                    $added = true;
                }
            }
            if (!$added) {
                $new_tabs['bspm'] = array('description' => $txt['bspm_dashboard_desc']);
            }
            $context[$context['admin_menu_name']]['tab_data']['tabs'] = $new_tabs;
        }
    }
}

/**
 * Add Packages uses PackageGet(), not Packages(). Its tab_data is built
 * separately, so the manage-packages hook never runs on that page.
 */
function bspm_register_package_get_tabs(&$subActions)
{
    global $context, $txt;
    loadLanguage('BsPackageManager');
    bspm_load_assets();
    if (empty($context['admin_menu_name'])) {
        return;
    }
    $menu_name = $context['admin_menu_name'];
    if (empty($context[$menu_name]['tab_data']['tabs']) ||
        isset($context[$menu_name]['tab_data']['tabs']['bspm'])) {
        return;
    }
    $tabs = $context[$menu_name]['tab_data']['tabs'];
    $ordered = array();
    foreach ($tabs as $key => $definition) {
        $ordered[$key] = $definition;
        if ($key === 'browse') {
            $ordered['bspm'] = array('description' => $txt['bspm_dashboard_desc']);
        }
    }
    if (!isset($ordered['bspm'])) {
        $ordered['bspm'] = array('description' => $txt['bspm_dashboard_desc']);
    }
    $context[$menu_name]['tab_data']['tabs'] = $ordered;
}

/** Load assets only in Package Manager. Files live in the default theme, with SMF's theme fallback. */
function bspm_load_assets()
{
    global $context, $txt, $scripturl;
    if (!empty($context['bspm_loaded'])) return;
    if (empty($_REQUEST['action']) || $_REQUEST['action'] !== 'admin' || empty($_REQUEST['area']) || $_REQUEST['area'] !== 'packages') {
        return;
    }
    if (function_exists('allowedTo') && !allowedTo('admin_forum')) {
        return;
    }
    if (isset($_REQUEST['bspm_classic']) && (string) $_REQUEST['bspm_classic'] === '1') {
        return;
    }
    loadLanguage('BsPackageManager');
    loadCSSFile('BsPackageManager.css', array('minimize' => false), 'bspm-css');
    loadJavaScriptFile('BsPackageManager.js', array('defer' => true, 'minimize' => false), 'bspm-js');
    $strings = array();
    foreach ($txt as $key => $value) {
        if (strpos($key, 'bspm_') === 0 && is_string($value)) $strings[$key] = $value;
    }
    addInlineJavaScript('window.bspmStrings = ' . json_encode($strings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) . ';', false);
    addInlineJavaScript('window.bspmClassicUrl = ' . json_encode($scripturl . '?action=admin;area=packages;sa=browse;bspm_classic=1', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';', false);
    $context['bspm_loaded'] = true;
}

/**
 * Return a read-only overview: installed package log + locally uploaded packages.
 * No remote URLs are fetched; no file writes. External mods can add notes/cards.
 */
function bspm_archive_info($packagesdir, $filename)
{
    $path = $packagesdir . '/' . $filename;
    $metadata_path = is_dir($path) ? $path . '/package-info.xml' : $path;
    $stat = @stat($metadata_path);
    if (!$stat) return false;
    $key = 'bspm_meta_' . sha1($metadata_path . '|' . $stat['mtime'] . '|' . $stat['ctime'] . '|' . $stat['size']);
    if (function_exists('cache_get_data')) {
        $cached = cache_get_data($key, 180);
        if (is_array($cached) && array_key_exists('info', $cached)) return $cached['info'];
    }
    $info = getPackageInfo($filename);
    if (is_array($info)) $info = array_intersect_key($info, array_flip(array('id', 'name', 'version', 'type', 'description', 'author')));
    else $info = false;
    if (function_exists('cache_put_data')) cache_put_data($key, array('info' => $info), 180);
    return $info;
}

function bspm_collect_packages()
{
    global $sourcedir, $packagesdir, $smcFunc, $txt;
    require_once($sourcedir . '/Subs-Package.php');
    $installed = loadInstalledPackages();
    $by_id = array();
    $entries = array();
    foreach ($installed as $pkg) {
        $id = isset($pkg['package_id']) ? (string) $pkg['package_id'] : '';
        if ($id === '') continue;
        $by_id[$id] = $pkg;
        $entries[$id] = array(
            'id' => $id,
            'name' => html_entity_decode($pkg['name'], ENT_QUOTES, 'UTF-8'),
            'installed' => (string) $pkg['version'],
            'available' => '',
            'filename' => '',
            'type' => 'modification',
            'status' => 'installed',
            'date' => (int) $pkg['time_installed'],
            'notes' => array(),
            'dependencies' => array(),
            'description' => '',
            'author' => '',
        );
    }

    $dir = @opendir($packagesdir);
    $candidates = array();
    if ($dir !== false) {
        while (($filename = readdir($dir)) !== false) {
            if ($filename === '.' || $filename === '..' || $filename === 'temp') continue;
            if (!is_file($packagesdir . '/' . $filename) && !(is_dir($packagesdir . '/' . $filename) && is_file($packagesdir . '/' . $filename . '/package-info.xml'))) continue;
            if (!is_dir($packagesdir . '/' . $filename) && !preg_match('~\.(?:zip|tgz|tar\.gz)$~i', $filename)) continue;
            $candidates[] = $filename;
        }
        closedir($dir);
    }
    // Stable alphabetical selection; only metadata is cached, never installation state.
    usort($candidates, function ($a, $b) { return strcasecmp($a, $b) ?: strcmp($a, $b); });
    $count = count($candidates);
    foreach (array_slice($candidates, 0, 250) as $filename) {
        $info = bspm_archive_info($packagesdir, $filename);
        if (!is_array($info) || empty($info['id']) || !isset($info['version'])) continue;
        $id = html_entity_decode((string) $info['id'], ENT_QUOTES, 'UTF-8');
        $version = html_entity_decode((string) $info['version'], ENT_QUOTES, 'UTF-8');
        if (!isset($entries[$id])) {
            $entries[$id] = array('id' => $id, 'name' => '', 'installed' => '', 'available' => '', 'filename' => '', 'type' => '', 'status' => 'available', 'date' => 0, 'notes' => array(), 'dependencies' => array(), 'description' => '', 'author' => '');
        }
        if ($entries[$id]['available'] !== '' && version_compare($version, $entries[$id]['available'], '<=')) continue;
        $entries[$id]['name'] = html_entity_decode((string) ($info['name'] ?? $id), ENT_QUOTES, 'UTF-8');
        $entries[$id]['available'] = $version;
        $entries[$id]['filename'] = $filename;
        $entries[$id]['type'] = html_entity_decode((string) ($info['type'] ?? 'modification'), ENT_QUOTES, 'UTF-8');
        $entries[$id]['description'] = trim(strip_tags(html_entity_decode((string) ($info['description'] ?? ''), ENT_QUOTES, 'UTF-8')));
        if (isset($info['author'])) {
            $entries[$id]['author'] = trim(strip_tags(html_entity_decode((string) (is_array($info['author']) ? ($info['author']['name'] ?? '') : $info['author']), ENT_QUOTES, 'UTF-8')));
        }
        if ($entries[$id]['installed'] !== '') {
            $entries[$id]['status'] = version_compare($version, $entries[$id]['installed'], '>') ? 'update' : 'installed';
        }
    }

    foreach ($entries as &$entry) {
        // Supply hooks with package metadata, but never trust its output as HTML.
        call_integration_hook('integrate_bspm_package_metadata', array(&$entry));
    }
    unset($entry);
    // Optional explicit dependencies declared by ecosystem extensions, not guessed from file references.
    foreach ($entries as &$entry) {
        if (empty($entry['dependencies']) || !is_array($entry['dependencies'])) continue;
        foreach ($entry['dependencies'] as $dependency) {
            if (!is_string($dependency) || trim($dependency) === '') continue;
            if (!isset($by_id[$dependency])) $entry['notes'][] = sprintf($txt['bspm_dependency_missing'], $dependency);
        }
    }
    unset($entry);
    uasort($entries, function($a, $b) { return strcasecmp((string) $a['name'], (string) $b['name']); });
    return array($entries, $count > 250);
}

/** Dashboard is an additional admin view, not a replacement for SMF's installation steps. */
function bspm_dashboard()
{
    global $context, $txt, $sourcedir, $smcFunc;
    isAllowedTo('admin_forum');
    loadLanguage('BsPackageManager');
    loadTemplate('BsPackageManager', 'admin');
    $context['sub_template'] = 'bspm_dashboard';
    $context['page_title'] = $txt['bspm_dashboard'];
    list($context['bspm_packages'], $context['bspm_truncated']) = bspm_collect_packages();

    // Read-only history: this is SMF's canonical installation log.
    $context['bspm_history'] = array();
    $request = $smcFunc['db_query']('', '
        SELECT id_install, name, package_id, version, filename, time_installed,
            time_removed, member_installed, member_removed, install_state
        FROM {db_prefix}log_packages
        ORDER BY CASE WHEN install_state = 0 AND time_removed > 0 THEN time_removed ELSE time_installed END DESC, id_install DESC
        LIMIT 100', array());
    while ($row = $smcFunc['db_fetch_assoc']($request)) {
        $context['bspm_history'][] = $row;
    }
    $smcFunc['db_free_result']($request);

    $context['bspm_cards'] = array();
    call_integration_hook('integrate_bspm_dashboard_cards', array(&$context['bspm_cards']));
}

/** Add the mod to SMF credits. */
function bspm_credits()
{
    global $context, $txt;
    loadLanguage('BsPackageManager');
    $context['credits_modifications'][] = sprintf($txt['bspm_credits'], '1.0.0');
}
