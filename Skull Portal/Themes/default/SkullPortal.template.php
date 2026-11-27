<?php
if (!defined('SMF')) die('No direct access');
/** Render styled blocks consistently on portal and forum surfaces. */
function skullportal_render_column($blocks)
{
    foreach ($blocks as $block)
    {
        $style = skullportal_block_style($block);
        $classes = 'skullportal-card';
        foreach (array('header-bg', 'header-text', 'body-bg', 'body-text', 'border', 'radius') as $key)
            if (strpos($style, '--sp-' . $key . ':') !== false) $classes .= ' sp-custom-' . $key;
        echo '<article class="', $classes, '"',
            $style !== '' ? ' style="' . skullportal_esc($style) . '"' : '', '>';
        if (!empty($block['show_title']))
            echo '<div class="cat_bar"><h3 class="catbg">', skullportal_esc($block['title']), '</h3></div>';
        echo '<div class="windowbg">', $block['rendered'], '</div></article>';
    }
}

function template_skullportal()
{
    global $context;
    $columns = $context['skullportal_columns'] ?? array();
    if (!empty($context['skullportal_preview']))
        echo '<div class="skullportal-preview-banner" role="status">Administrator preview: visibility and permissions remain enforced. <a href="', skullportal_esc($context['skullportal_admin_url'] ?? '?action=admin;area=skullportal'), '">Return to settings</a></div>';
    $active = array();
    foreach (array('left', 'centre', 'right') as $position)
        if (!empty($columns[$position])) $active[] = $position;

    if ($active)
    {
        echo '<div class="skullportal-grid skullportal-layout-', implode('-', $active), '">';
        foreach ($active as $position)
        {
            echo '<section class="skullportal-column" data-column="', $position, '">';
            skullportal_render_column($columns[$position]);
            echo '</section>';
        }
        echo '</div>';
    }
    // A dedicated, full-width row below the other columns.
    if (!empty($columns['bottom']))
    {
        echo '<section class="skullportal-bottom" aria-label="Bottom blocks">';
        skullportal_render_column($columns['bottom']);
        echo '</section>';
    }
}

/** The wrapper is a normal SMF template layer: no theme or core edits. */
function template_skullportal_forum_above()
{
    global $context;
    $columns = $context['skullportal_forum_columns'] ?? array();
    $left = !empty($columns['left']);
    $right = !empty($columns['right']);
    $centre = !empty($columns['centre']);
    echo '<div class="skullportal-forum-layout', $left ? ' has-left' : '', $right ? ' has-right' : '', '">';
    if ($left) { echo '<aside class="skullportal-forum-sidebar">'; skullportal_render_column($columns['left']); echo '</aside>'; }
    echo '<main class="skullportal-forum-main">';
    if ($centre) skullportal_render_column($columns['centre']);
}
function template_skullportal_forum_below()
{
    global $context;
    echo '</main>';
    if (!empty($context['skullportal_forum_columns']['right']))
    {
        echo '<aside class="skullportal-forum-sidebar">';
        skullportal_render_column($context['skullportal_forum_columns']['right']);
        echo '</aside>';
    }
    echo '</div>';
    if (!empty($context['skullportal_forum_columns']['bottom']))
    {
        echo '<section class="skullportal-bottom" aria-label="Bottom blocks">';
        skullportal_render_column($context['skullportal_forum_columns']['bottom']);
        echo '</section>';
    }
}

function template_skullportal_admin()
{
    global $context, $txt, $modSettings, $scripturl;
    $url = $context['skullportal_admin_url'];
    $session = '<input type="hidden" name="' . skullportal_esc($context['session_var']) . '" value="' . skullportal_esc($context['session_id']) . '">';
    $token = '<input type="hidden" name="' . skullportal_esc($context['skullportal_token_var']) . '" value="' . skullportal_esc($context['skullportal_token']) . '">';
    echo '<div class="cat_bar"><h3 class="catbg">Skull Portal</h3></div>';
    if ($context['skullportal_edit'] !== null)
    {
        $b = $context['skullportal_edit'];
        echo '<form class="windowbg skullportal-form" action="', $url, ';spop=save" method="post">', $session, $token,
            '<input type="hidden" name="id_block" value="', (int) $b['id_block'], '">',
            '<label for="sp_title">', $txt['skullportal_heading'], '</label><input id="sp_title" type="text" name="title" maxlength="150" value="', skullportal_esc($b['title']), '">',
            '<label for="sp_type">', $txt['skullportal_type'], '</label><select id="sp_type" name="block_type">';
        foreach ($context['skullportal_types'] as $id => $type)
            echo '<option value="', skullportal_esc($id), '"', $id === $b['block_type'] ? ' selected' : '', '>', skullportal_esc($type['label']), '</option>';
        echo '</select>';
        $welcomeOptions = skullportal_welcome_options($b);
        echo '<div id="skullportal-content-fields"><p class="smalltext">', $txt['skullportal_html_warning'], '</p>',
            '<label for="sp_content">', $txt['skullportal_content'], '</label><textarea id="sp_content" name="content">', skullportal_esc($b['block_type'] === 'welcome' ? '' : $b['content']), '</textarea></div>';
        echo '<fieldset id="skullportal-welcome-fields"><legend>', skullportal_esc($txt['skullportal_member_options']), '</legend>',
            '<p class="smalltext">', skullportal_esc($txt['skullportal_member_automatic']), '</p>';
        foreach (array('avatar', 'name', 'group', 'stars', 'posts', 'unread', 'replies', 'profile', 'account', 'logout') as $option)
            echo '<label class="skullportal-option"><input type="checkbox" name="welcome_options[', $option,
                ']" value="1"', $welcomeOptions[$option] ? ' checked' : '', '> ',
                skullportal_esc($txt['skullportal_option_' . $option]), '</label>';
        echo '</fieldset>';
        echo '<script>(function(){var type=document.getElementById("sp_type"),content=document.getElementById("skullportal-content-fields"),welcome=document.getElementById("skullportal-welcome-fields");function toggle(){var isWelcome=type.value==="welcome";content.hidden=isWelcome;welcome.hidden=!isWelcome;content.querySelector("textarea").disabled=isWelcome;welcome.querySelectorAll("input").forEach(function(input){input.disabled=!isWelcome;});}type.addEventListener("change",toggle);toggle();})();</script>';
        echo '<label for="sp_position">', $txt['skullportal_position'], '</label><select id="sp_position" name="position">';
        foreach (array('left', 'centre', 'right', 'bottom') as $p)
            echo '<option value="', $p, '"', $p === $b['position'] ? ' selected' : '', '>', ucfirst($p), '</option>';
        echo '</select>';
        $style = json_decode((string) ($b['style_settings'] ?? ''), true);
        if (!is_array($style)) $style = array();
        echo '<details class="skullportal-style-settings"><summary>', skullportal_esc($txt['skullportal_style_heading']), '</summary><div class="skullportal-style-fields">',
            '<p class="smalltext">', skullportal_esc($txt['skullportal_style_hint']), '</p>';
        foreach (array('header_bg', 'header_text', 'body_bg', 'body_text', 'border') as $styleKey)
            echo '<label for="sp_style_', $styleKey, '">', skullportal_esc($txt['skullportal_style_' . $styleKey]), '</label>',
                '<input class="skullportal-colour-input" type="text" id="sp_style_', $styleKey, '" name="style[', $styleKey,
                ']" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#RRGGBB (optional)" value="',
                skullportal_esc($style[$styleKey] ?? ''), '">';
        echo '<label for="sp_style_radius">', skullportal_esc($txt['skullportal_style_radius']), '</label>',
            '<select id="sp_style_radius" name="style[radius]">';
        foreach (array('theme' => 'Theme default', 'square' => 'Square', 'small' => 'Small (4px)', 'rounded' => 'Rounded (12px)') as $radiusId => $radiusLabel)
            echo '<option value="', $radiusId, '"', ($style['radius'] ?? 'theme') === $radiusId ? ' selected' : '', '>', skullportal_esc($radiusLabel), '</option>';
        echo '</select></div></details>';
        echo '<label for="sp_display_on">Display on</label><select id="sp_display_on" name="display_on">';
        foreach (array('portal' => 'Portal only', 'forum' => 'Forum only', 'both' => 'Portal and forum') as $id => $label)
            echo '<option value="', $id, '"', $id === ($b['display_on'] ?? 'both') ? ' selected' : '', '>', $label, '</option>';
        echo '</select><label for="sp_visibility">', $txt['skullportal_visibility'], '</label><select id="sp_visibility" name="visibility">';
        $visibilityChoices = array('everyone' => 'Everyone', 'members' => 'Members', 'guests' => 'Guests');
        foreach ($context['skullportal_groups'] as $groupID => $groupName)
            $visibilityChoices['group:' . $groupID] = 'Group: ' . $groupName;
        foreach ($visibilityChoices as $id => $label)
            echo '<option value="', skullportal_esc($id), '"', $id === $b['visibility'] ? ' selected' : '', '>', skullportal_esc($label), '</option>';
        echo '</select><label for="sp_sort">', $txt['skullportal_order'], '</label><input id="sp_sort" type="number" name="sort_order" value="', (int) $b['sort_order'], '">',
            '<label><input type="checkbox" name="enabled" value="1"', $b['enabled'] ? ' checked' : '', '> ', $txt['skullportal_enabled'], '</label>',
            '<label><input type="checkbox" name="show_title" value="1"', $b['show_title'] ? ' checked' : '', '> ', $txt['skullportal_show_title'], '</label>',
            '<p><button type="submit" class="button">', $txt['skullportal_save'], '</button> <a class="button" href="', $url, '">Cancel</a></p></form>';
        return;
    }
    echo '<form class="windowbg skullportal-form" action="', $url, ';spop=settings" method="post">', $session, $token,
        '<label><input type="checkbox" name="portal_enabled" value="1"', !empty($modSettings['skullportal_enabled']) ? ' checked' : '', '> ', $txt['skullportal_portal_enabled'], '</label>',
        '<label><input type="checkbox" name="portal_homepage" value="1"', !empty($modSettings['skullportal_homepage']) ? ' checked' : '', '> ', $txt['skullportal_homepage'], '</label>',
        '<p><button class="button" type="submit">Save settings</button> <a class="button" href="', $scripturl, '?action=portal">View portal</a> <a class="button" href="', $scripturl, '?action=portal;sp_preview=1">Live preview</a></p></form>';
    echo '<div class="windowbg skullportal-preset"><h3>Quick layouts</h3><p>Apply a layout to built-in Welcome, Navigation and Statistics blocks. Other blocks stay untouched.</p>';
    foreach (array('classic'=>'Classic sidebars','central'=>'Central column','sidebar'=>'Sidebars') as $id=>$label)
        echo '<form action="', $url, ';spop=layout" method="post" class="skullportal-inline-form">', $session, $token, '<input type="hidden" name="layout" value="', $id, '"><button type="submit" class="button">', skullportal_esc($label), '</button></form> ';
    echo '</div>';
    echo '<div class="cat_bar"><h3 class="catbg">Arrange blocks</h3></div>',
        '<p class="smalltext">Drag blocks between columns or use the Move buttons for keyboard access, then save the layout. Only available block types are shown.</p>',
        '<form action="', $url, ';spop=reorder" method="post" id="skullportal-sort-form">', $session, $token,
        '<div class="skullportal-sort-grid">';
    foreach (array('left'=>'Left','centre'=>'Centre','right'=>'Right','bottom'=>'Bottom') as $pos=>$label)
    {
        echo '<section class="skullportal-sort-column" data-position="', $pos, '"><h4>', $label, '</h4><ol class="skullportal-sort-list" data-position="', $pos, '">';
        foreach ($context['skullportal_rows'] as $row) if ($row['position'] === $pos)
            echo '<li draggable="true" class="skullportal-sort-item"><span class="skullportal-drag-handle" aria-hidden="true">☷</span> ', skullportal_esc($row['title']),
                '<input type="hidden" name="ids[', $pos, '][]" value="', (int) $row['id_block'], '">',
                '<div class="skullportal-sort-controls"><button type="button" data-move="up" aria-label="Move up">↑</button> <button type="button" data-move="down" aria-label="Move down">↓</button>',
                '<select aria-label="Move to column" data-target><option value="">Move to…</option>',
                '<option value="left">Left</option><option value="centre">Centre</option><option value="right">Right</option><option value="bottom">Bottom</option></select></div></li>';
        echo '</ol></section>';
    }
    echo '</div><p><button type="submit" class="button">Save block positions</button></p></form>';
    echo '<script>(function(){var form=document.getElementById("skullportal-sort-form");if(!form)return;var dragged=null;form.addEventListener("dragstart",function(e){var li=e.target.closest(".skullportal-sort-item");if(li){dragged=li;e.dataTransfer.effectAllowed="move";}});form.addEventListener("dragover",function(e){if(e.target.closest(".skullportal-sort-list"))e.preventDefault();});form.addEventListener("drop",function(e){var list=e.target.closest(".skullportal-sort-list");if(!list||!dragged)return;e.preventDefault();var item=e.target.closest(".skullportal-sort-item");if(item&&item!==dragged)list.insertBefore(dragged,item);else list.appendChild(dragged);dragged=null;refresh();});form.addEventListener("change",function(e){if(!e.target.matches("[data-target]"))return;var li=e.target.closest("li"),list=form.querySelector(".skullportal-sort-list[data-position=\""+e.target.value+"\"]");if(li&&list){list.appendChild(li);refresh();}});form.addEventListener("click",function(e){var btn=e.target.closest("[data-move]");if(!btn)return;var li=btn.closest("li"),list=li.parentNode;if(btn.dataset.move==="up"&&li.previousElementSibling)list.insertBefore(li,li.previousElementSibling);if(btn.dataset.move==="down"&&li.nextElementSibling)list.insertBefore(li.nextElementSibling,li);refresh();});function refresh(){form.querySelectorAll(".skullportal-sort-list").forEach(function(list){list.querySelectorAll("li input[type=hidden]").forEach(function(input){input.name="ids["+list.dataset.position+"][]";});});}})();</script>';
    echo '<div class="cat_bar"><h3 class="catbg">', $txt['skullportal_manage'], '</h3></div>',
        '<p><a class="button" href="', $url, ';spop=edit">', $txt['skullportal_add'], '</a></p>',
        '<div class="windowbg"><table class="skullportal-admin-table"><thead><tr><th>Title</th><th>Type</th><th>Column</th><th>Display on</th><th>Order</th><th>Visibility</th><th>Enabled</th><th>Actions</th></tr></thead><tbody>';
    foreach ($context['skullportal_rows'] as $b)
    {
        echo '<tr><td>', skullportal_esc($b['title']), '</td><td>', skullportal_esc($b['block_type']),
            '</td><td>', skullportal_esc($b['position']), '</td><td>', skullportal_esc($b['display_on'] ?? 'both'), '</td><td>', (int) $b['sort_order'],
            '</td><td>', skullportal_esc($b['visibility']), '</td><td>', $b['enabled'] ? 'Yes' : 'No',
            '</td><td><div class="skullportal-actions"><a class="button" href="', $url, ';spop=edit;id=', (int) $b['id_block'], '">Edit</a>',
            '<form method="post" action="', $url, ';spop=duplicate" class="skullportal-inline-form">', $session, $token,
            '<input type="hidden" name="id_block" value="', (int) $b['id_block'], '"><button type="submit" class="button">Duplicate</button></form>',
            '<form method="post" action="', $url, ';spop=delete" onsubmit="return confirm(\'Delete this block?\')">',
            $session, $token, '<input type="hidden" name="id_block" value="', (int) $b['id_block'], '">',
            '<button type="submit" class="button">Delete</button></form></div></td></tr>';
    }
    echo '</tbody></table></div>';
}
