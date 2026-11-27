<?php
/** Board Downloads templates. Native SMF-compatible HTML, no theme core edits. */
if (!defined('SMF'))
    die('No direct access');

function bd_esc($value)
{
    return BoredBoardDownloads::e($value);
}
function bd_file_url($id)
{
    global $scripturl;
    return $scripturl . '?action=dlattach;attach=' . (int) $id;
}
function bd_size($bytes)
{
    $bytes = max(0, (int) $bytes);
    if ($bytes < 1024) return number_format($bytes) . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 2) . ' MB';
}
/** Labels are supplied by Topic Tags & Prefixes, already scoped to this topic. */
function bd_labels($topic)
{
    if (empty($topic['labels'])) return;
    $labels = $topic['labels'];
    echo '<div class="bd-labels">';
    if (!empty($labels['prefix']) && !empty($labels['prefix']['name']))
        echo '<span class="bd-label bd-prefix">', bd_esc($labels['prefix']['name']), '</span> ';
    if (!empty($labels['tags']))
        foreach ($labels['tags'] as $tag)
            if (!empty($tag['name']))
                echo '<span class="bd-label">#', bd_esc($tag['name']), '</span> ';
    echo '</div>';
}
function bd_menu()
{
    global $scripturl, $txt;
    echo '<div class="bd-header"><h2>', bd_esc($txt['bd_title']), '</h2><div class="bd-links"><a href="', bd_esc($scripturl . '?action=boarddownloads'), '">', bd_esc($txt['bd_browse']), '</a>';
    if (allowedTo('admin_forum'))
        echo '<a href="', bd_esc($scripturl . '?action=admin;area=modsettings;sa=boarddownloads'), '">', bd_esc($txt['bd_settings']), '</a>';
    echo '</div></div>';
}
function template_bd_browse()
{
    global $context, $txt, $scripturl;
    bd_menu();
    if (!empty($context['bd_no_boards']))
    {
        echo '<div class="windowbg bd-notice">', bd_esc($txt['bd_no_boards']), '</div>';
        return;
    }
    echo '<form class="windowbg bd-search" action="', bd_esc($scripturl), '" method="get"><input type="hidden" name="action" value="boarddownloads">',
        '<label for="bd-query">', bd_esc($txt['bd_search']), '</label> ',
        '<input id="bd-query" name="q" type="search" maxlength="80" value="', bd_esc($context['bd_search']), '"> ',
        '<label for="bd-filter">', bd_esc($txt['bd_filter_board']), '</label> ',
        '<select id="bd-filter" name="board_id"><option value="0">', bd_esc($txt['bd_all_boards']), '</option>';
    foreach ($context['bd_boards'] as $board)
        echo '<option value="', (int) $board['id_board'], '"', (int) $board['id_board'] === (int) $context['bd_board_filter'] ? ' selected' : '', '>', bd_esc($board['name']), '</option>';
    echo '</select> <label for="bd-sort">', bd_esc($txt['bd_sort']), '</label> ',
        '<select id="bd-sort" name="sort">';
    foreach (array('recent', 'popular', 'title', 'updated') as $sort)
        echo '<option value="', $sort, '"', $context['bd_sort'] === $sort ? ' selected' : '', '>', bd_esc($txt['bd_sort_' . $sort]), '</option>';
    echo '</select> <button class="button" type="submit">', bd_esc($txt['bd_search_button']), '</button></form>';
    if (empty($context['bd_topics']))
    {
        echo '<div class="windowbg bd-notice">', bd_esc($txt['bd_no_downloads']), '</div>';
        return;
    }
    echo '<div class="bd-grid">';
    foreach ($context['bd_topics'] as $topic)
    {
        $id = (int) $topic['id_topic'];
        $url = $scripturl . '?action=boarddownloads;sa=topic;id=' . $id;
        echo '<article class="windowbg bd-card">';
        if (!empty($topic['preview']))
        {
            $previewUrl = bd_file_url($topic['preview']['id_attach']) . ';image';
            echo '<a href="', bd_esc($url), '" class="bd-cover"><img loading="lazy" src="', bd_esc($previewUrl), '" alt="', bd_esc($txt['bd_screenshot_alt'] . ': ' . $topic['subject']), '"></a>';
        }
        echo '<div class="bd-meta">', bd_esc($topic['board_name']), ' · ',
            bd_esc(timeformat($topic['updated_time'])), '</div>',
            '<h3><a href="', bd_esc($url), '">', bd_esc($topic['subject']), '</a></h3>',
            '<div class="bd-summary">';
        bd_labels($topic);
        echo '<p>', bd_esc($topic['summary']), '</p></div>',
            '<div class="bd-meta">', bd_esc($topic['poster_name']), ' · ', (int) $topic['num_replies'], ' ', bd_esc($txt['bd_replies']), ' · ', (int) $topic['num_views'], ' ', bd_esc($txt['bd_views']), '</div>',
            '<div class="bd-card-actions">';
        if (!empty($topic['primary']))
            echo '<a class="button bd-primary" href="', bd_esc(bd_file_url($topic['primary']['id_attach'])), '">',
                bd_esc($txt['bd_download']), ' · ', bd_esc(bd_size($topic['primary']['size'])), '</a>';
        else
            echo '<span class="bd-meta">', bd_esc($txt['bd_file_unavailable']), '</span>';
        echo '<a class="button" href="', bd_esc($url), '">', bd_esc($txt['bd_details']), '</a></div></article>';
    }
    echo '</div>';
    if ($context['bd_pages'] > 1)
    {
        echo '<nav class="bd-pagination" aria-label="', bd_esc($txt['bd_pages']), '">';
        for ($i = 0; $i < $context['bd_pages']; $i++)
        {
            // Keep pagination compact on large catalogues.
            if ($i !== 0 && $i !== $context['bd_pages'] - 1 && abs($i - $context['bd_page']) > 2)
                continue;
            $href = $scripturl . '?action=boarddownloads;page=' . $i . ';board_id=' . (int) $context['bd_board_filter']
                . ';sort=' . rawurlencode($context['bd_sort']) . ';q=' . rawurlencode($context['bd_search']);
            echo '<a class="button', $context['bd_page'] === $i ? ' active' : '', '" ',
                $context['bd_page'] === $i ? 'aria-current="page" ' : '', 'href="', bd_esc($href), '">', $i + 1, '</a>';
        }
        echo '</nav>';
    }
}
function template_bd_topic()
{
    global $context, $txt, $scripturl;
    $topic = $context['bd_topic'];
    $id = (int) $topic['id_topic'];
    bd_menu();
    echo '<div class="cat_bar"><h3 class="catbg">', bd_esc($topic['subject']), '</h3></div>',
        '<article class="windowbg bd-detail"><div class="bd-meta">', bd_esc($topic['board_name']), ' · ',
        bd_esc($topic['poster_name']), ' · ', bd_esc(timeformat($topic['poster_time'])),
        ' · ', bd_esc($txt['bd_updated']), ' ', bd_esc(timeformat($topic['updated_time'])), '</div>',
        '<div class="bd-content">', $topic['body_html'], '</div>',
        '<p><a class="button" href="', bd_esc($scripturl . '?topic=' . $id . '.0'), '">', bd_esc($txt['bd_discussion']), '</a>';
    if (!empty($context['bd_admin']))
        echo ' <a class="button" href="', bd_esc($scripturl . '?action=boarddownloads;sa=manage;id=' . $id), '">', bd_esc($txt['bd_manage_files']), '</a>';
    echo '</p></article>';
    bd_labels($topic);
    if (!empty($topic['related']))
    {
        echo '<section class="windowbg bd-related"><h3>', bd_esc($txt['bd_related']), '</h3><ul>';
        foreach ($topic['related'] as $related)
            if (is_array($related) && isset($related['text'], $related['url'])
                && is_string($related['text']) && is_string($related['url'])
                && strpos($related['url'], $scripturl) === 0)
                echo '<li><a href="', bd_esc($related['url']), '">', bd_esc($related['text']), '</a></li>';
        echo '</ul></section>';
    }
    if (!empty($topic['screenshots']))
    {
        echo '<div class="cat_bar"><h3 class="catbg">', bd_esc($txt['bd_screenshots']), '</h3></div><div class="bd-screenshots">';
        foreach ($topic['screenshots'] as $file)
        {
            $url = bd_file_url($file['id_attach']) . ';image';
            echo '<a href="', bd_esc($url), '"><img loading="lazy" src="', bd_esc($url), '" alt="',
                bd_esc($txt['bd_screenshot_alt'] . ': ' . $file['filename']), '"></a>';
        }
        echo '</div>';
    }
    echo '<div class="cat_bar"><h3 class="catbg">', bd_esc($txt['bd_available_files']), '</h3></div>';
    if (empty($topic['files']))
        echo '<div class="windowbg bd-notice">', bd_esc($txt['bd_file_unavailable']), '</div>';
    else
    {
        echo '<div class="bd-files">';
        foreach ($topic['files'] as $file)
            echo '<div class="windowbg bd-file"><div><strong>', bd_esc($file['filename']), '</strong><div class="bd-meta">',
                bd_esc(bd_size($file['size'])), ' · ', bd_esc(strtoupper($file['fileext'])), '</div></div>',
                '<a class="button', $topic['primary'] && (int) $topic['primary']['id_attach'] === (int) $file['id_attach'] ? ' bd-primary' : '',
                '" href="', bd_esc(bd_file_url($file['id_attach'])), '">', bd_esc($txt['bd_download']), '</a></div>';
        echo '</div>';
    }
}
function template_bd_manage()
{
    global $context, $txt, $scripturl;
    $topic = $context['bd_manage_topic'];
    $selected = array_keys($context['bd_manage_choice']['files']);
    $primary = $context['bd_manage_choice']['primary'] ? (int) $context['bd_manage_choice']['primary']['id_attach'] : 0;
    bd_menu();
    echo '<div class="cat_bar"><h3 class="catbg">', bd_esc($txt['bd_manage_files']), ': ', bd_esc($topic['subject']), '</h3></div>',
        '<p class="description">', bd_esc($txt['bd_manage_help']), '</p>',
        '<form class="windowbg bd-adminform" action="', bd_esc($scripturl . '?action=boarddownloads;sa=manage;id=' . (int) $topic['id_topic']), '" method="post">';
    if (!$context['bd_manage_files'])
        echo '<p>', bd_esc($txt['bd_no_attachments']), '</p>';
    foreach ($context['bd_manage_files'] as $file)
    {
        $id = (int) $file['id_attach'];
        if (empty($file['is_download']))
        {
            echo '<p class="bd-meta">', bd_esc($file['filename']), ' (', bd_esc($txt['bd_not_downloadable']), ')</p>';
            continue;
        }
        echo '<div class="bd-file bd-manage-row"><label><input type="checkbox" name="files[]" value="', $id, '"',
            in_array($id, $selected, true) ? ' checked' : '', '> ', bd_esc($file['filename']), ' (', bd_esc(bd_size($file['size'])), ')</label>',
            '<label><input type="radio" name="primary" value="', $id, '"', $primary === $id ? ' checked' : '', '> ', bd_esc($txt['bd_primary']), '</label></div>';
    }
    echo '<input type="hidden" name="', bd_esc($context['session_var']), '" value="', bd_esc($context['session_id']), '">',
        '<p><button type="submit" class="button">', bd_esc($txt['bd_save']), '</button></p></form>';
}
function template_bd_settings()
{
    global $context, $txt, $scripturl;
    bd_menu();
    $config = $context['bd_settings'];
    if (!empty($context['bd_storage_rejected'])) echo '<p class="errorbox">', bd_esc($txt['bdt_storage_rejected']), '</p>';
    if (!empty($context['bd_storage_warning'])) echo '<p class="errorbox">', bd_esc($txt['bdt_storage_invalid']), '</p>';
    echo '<div class="cat_bar"><h3 class="catbg">', bd_esc($txt['bd_settings']), '</h3></div>',
        '<form class="windowbg bd-adminform" method="post" action="', bd_esc($scripturl . '?action=admin;area=modsettings;sa=boarddownloads'), '">',
        '<div class="bd-settings-main" role="group" aria-label="', bd_esc($txt['bd_settings']), '">',
        '<label class="bd-settings-option"><input type="checkbox" name="enabled" value="1"', $config['enabled'] ? ' checked' : '', '> ', bd_esc($txt['bd_enabled']), '</label>',
        '<label class="bd-settings-option"><input type="checkbox" name="show_menu" value="1"', $config['show_menu'] ? ' checked' : '', '> ', bd_esc($txt['bd_show_menu']), '</label>',
        '</div>';
    echo '<fieldset><legend>', bd_esc($txt['bdt_mode']), '</legend>',
        '<label class="bd-settings-option"><input type="radio" name="mode" value="board"', $config['mode'] === 'board' ? ' checked' : '', '> ', bd_esc($txt['bdt_board']), '</label>',
        '<label class="bd-settings-option"><input type="radio" name="mode" value="traditional"', $config['mode'] === 'traditional' ? ' checked' : '', '> ', bd_esc($txt['bdt_traditional']), '</label>',
        '<p class="smalltext">', bd_esc($txt['bdt_mode_help']), '</p></fieldset>',
        '<fieldset><legend>', bd_esc($txt['bdt_storage']), '</legend><label>', bd_esc($txt['bdt_storage_path']), ' <input type="text" name="storage" size="60" maxlength="1000" value="', bd_esc($config['storage']), '"></label>',
        '<p class="smalltext">', bd_esc($txt['bdt_storage_help']), '</p>',
        '<p class="smalltext">', bd_esc($txt['bdt_storage_example']), ' <code>/home/yourdomain/boarddownloads</code></p><label>', bd_esc($txt['bdt_max_mb']), ' <input type="number" name="max_mb" min="1" max="2048" value="', (int) $config['max_mb'], '"></label></fieldset>';
    if (!empty($context['bd_integrations_available']))
    {
        echo '<fieldset class="bd-integrations"><legend>', bd_esc($txt['bd_integrations']), '</legend>';
        foreach (array('tags', 'digest', 'insights', 'activity', 'knowledge', 'events') as $integration)
            if (!empty($context['bd_integrations_available'][$integration]))
                echo '<label class="bd-settings-option"><input type="checkbox" name="', $integration, '" value="1"', !empty($config[$integration]) ? ' checked' : '', '> ', bd_esc($txt['bd_int_' . $integration]), '</label>';
        echo '</fieldset>';
    }
    echo '<div class="bd-permissions-info"><p>', bd_esc($txt['bd_permissions_help']), '</p>',
        '<p><a class="button" href="', bd_esc($scripturl . '?action=admin;area=permissions;sa=index'), '">',
        bd_esc($txt['bd_manage_permissions']), '</a></p></div>';
    echo '<fieldset><legend>', bd_esc($txt['bd_source_boards']), '</legend><p>', bd_esc($txt['bd_boards_help']), '</p>';
    foreach ($context['bd_all_boards'] as $board)
    {
        if (!empty($board['redirect']))
            continue;
        $id = (int) $board['id_board'];
        echo '<label class="bd-board-option"><input type="checkbox" name="boards[]" value="', $id, '"',
            in_array($id, $context['bd_configured_boards'], true) ? ' checked' : '', '> ', bd_esc($board['name']), ' (#', $id, ')</label>';
    }
    echo '</fieldset><p><label for="bd-ext">', bd_esc($txt['bd_extensions']), '</label><br>',
        '<input id="bd-ext" type="text" name="extensions" value="', bd_esc($config['extensions']), '" maxlength="450" size="70" required>',
        '<br><span class="smalltext">', bd_esc($txt['bd_extensions_help']), '</span></p>',
        '<p><label for="bd-page-size">', bd_esc($txt['bd_page_size']), '</label> ',
        '<input id="bd-page-size" type="number" name="page_size" value="', (int) $config['page_size'], '" min="5" max="50"></p>',
        '<input type="hidden" name="', bd_esc($context['session_var']), '" value="', bd_esc($context['session_id']), '">',
        '<p><button type="submit" class="button">', bd_esc($txt['bd_save']), '</button></p></form>';
}
