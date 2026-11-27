<?php
/** BoredSkull Topic Tags & Prefixes display templates. ISC. */
if (!defined('SMF'))
    die('No direct access');

function tp_url($s) { return BoredTopicTags::e($s); }
function tp_e($s) { return BoredTopicTags::e($s); }

function template_tp_nav()
{
    global $scripturl, $context;
    echo '<div class="tp-nav"><a class="button" href="', $scripturl, '?action=topiclabels">',
        BoredTopicTags::setting('tp_tags_enabled', 1) ? 'Explore tags' : 'Explore prefixes', '</a> ';
    if (!$context['user']['is_guest'] && BoredTopicTags::setting('tp_enabled', 1) &&
        BoredTopicTags::setting('tp_tags_enabled', 1) && BoredTopicTags::setting('tp_follow_enabled', 1))
        echo '<a class="button" href="', $scripturl, '?action=topiclabels;sa=following">Following</a> ';
    if (allowedTo('admin_forum'))
        echo '<a class="button" href="', $scripturl, '?action=admin;area=topiclabels">Manage tags & prefixes</a>';
    echo '</div>';
}

function template_tp_browse()
{
    global $context, $scripturl, $txt;
    $tp = $context['tp'];
    template_tp_nav();
    if (BoredTopicTags::setting('tp_tags_enabled', 1))
    {
        echo '<div class="cat_bar"><h3 class="catbg">Browse topic tags</h3></div><div class="windowbg tp-block">',
            '<p>Explore discussions by topic, discover popular labels and follow your interests.</p>',
            '<form method="get" action="', $scripturl, '" class="tp-search">',
            '<input type="hidden" name="action" value="topiclabels">',
            '<label for="tp-find">Find a tag</label> <input type="search" id="tp-find" name="q" value="', tp_e($tp['search']), '" maxlength="32" placeholder="Search tags"> ',
            '<input class="button" type="submit" value="Search"></form>';
        if (!$tp['tags'])
            echo '<p class="smalltext">No visible topics match this search.</p>';
        else
        {
            echo '<div class="tp-cloud">';
            foreach ($tp['tags'] as $tag)
                echo '<a class="tp-discover-tag" href="', $scripturl, '?action=topiclabels;sa=tag;tag=', rawurlencode($tag['slug']), '">#', tp_e($tag['name']),
                    ' <span class="tp-count">', (int) $tag['topic_count'], '</span></a> ';
            echo '</div>';
        }
        echo '</div>';
    }
    if (!empty($tp['prefixes']))
    {
        echo '<div class="cat_bar"><h3 class="catbg">Topic prefixes</h3></div><div class="windowbg tp-block"><div class="tp-cloud">';
        foreach ($tp['prefixes'] as $prefix)
            echo BoredTopicTags::renderPrefix($prefix), ' <span class="smalltext">', (int) $prefix['topic_count'], ' topics</span> ';
        echo '</div></div>';
    }
}

function template_tp_list()
{
    global $scripturl, $context;
    $tp = $context['tp'];
    template_tp_nav();
    $title = $tp['tag'] ? '#' . $tp['tag']['name'] : $tp['title'];
    echo '<div class="cat_bar"><h3 class="catbg">', tp_e($title), '</h3></div>',
        '<div class="windowbg tp-block">',
        '<div class="tp-row tp-tools"><div><a href="', $scripturl, '?action=topiclabels;sa=', $tp['mode'],
        $tp['tag'] ? ';tag=' . rawurlencode($tp['tag']['slug']) : ';id=' . (int) $tp['prefix']['id_prefix'],
        ';sort=recent">Recent activity</a> · <a href="', $scripturl, '?action=topiclabels;sa=', $tp['mode'],
        $tp['tag'] ? ';tag=' . rawurlencode($tp['tag']['slug']) : ';id=' . (int) $tp['prefix']['id_prefix'],
        ';sort=popular">Most replies</a></div>';
    if ($tp['tag'] && !$context['user']['is_guest'] && BoredTopicTags::setting('tp_follow_enabled', 1))
    {
        echo '<form method="post" action="', $scripturl, '?action=topiclabels;sa=toggle">',
            '<input type="hidden" name="tag" value="', (int) $tp['tag']['id_tag'], '">',
            '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
            '<button type="submit" class="button">', $tp['following'] ? 'Unfollow tag' : 'Follow tag', '</button></form>';
    }
    echo '</div>';
    if (!$tp['items'])
        echo '<p>No topics are available for this label.</p>';
    else
    {
        echo '<div class="tp-topic-list">';
        foreach ($tp['items'] as $topic)
            echo '<div class="tp-topic-row"><div class="tp-topic-main"><a href="', $scripturl, '?topic=', (int) $topic['id_topic'], '.0"><strong>', tp_e($topic['subject']), '</strong></a>',
                '<div class="smalltext">', tp_e($topic['board_name']), ' · ', tp_e($topic['poster_name']), '</div></div>',
                '<div class="smalltext">', (int) $topic['num_replies'], ' replies · ', (int) $topic['num_views'], ' views</div></div>';
        echo '</div>';
    }
    $base = $scripturl . '?action=topiclabels;sa=' . $tp['mode'] . ($tp['tag'] ? ';tag=' . rawurlencode($tp['tag']['slug']) : ';id=' . (int) $tp['prefix']['id_prefix']) . ';sort=' . $tp['sort'];
    echo '<div class="tp-pager">';
    if ($tp['start'] > 0)
        echo '<a class="button" href="', $base, ';start=', max(0, (int) $tp['start'] - 25), '">Previous</a> ';
    if ($tp['more'])
        echo '<a class="button" href="', $base, ';start=', (int) $tp['start'] + 25, '">Next</a>';
    echo '</div></div>';
}

function template_tp_following()
{
    global $context, $scripturl;
    $tp = $context['tp'];
    template_tp_nav();
    echo '<div class="cat_bar"><h3 class="catbg">Tags you follow</h3></div><div class="windowbg tp-block">',
        '<p>New-topic counts are shown since you last marked your tag feed as seen. Only topics in boards you can currently access are counted.</p>';
    if (!$tp['tags'])
        echo '<p>You are not following any tags yet. <a href="', $scripturl, '?action=topiclabels">Browse tags</a> to get started.</p>';
    else
    {
        echo '<div class="tp-cloud">';
        foreach ($tp['tags'] as $t)
            echo '<a class="tp-discover-tag" href="', $scripturl, '?action=topiclabels;sa=tag;tag=', rawurlencode($t['slug']), '">#', tp_e($t['name']), ' <span class="tp-count">', (int) $t['new_topics'], ' new</span></a> ';
        echo '</div><form method="post" action="', $scripturl, '?action=topiclabels;sa=following">',
            '<input type="hidden" name="mark_seen" value="1">',
            '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
            '<p><button class="button" type="submit">Mark all as seen</button></p></form>';
    }
    echo '</div>';
}

function template_tp_edit()
{
    global $context, $scripturl;
    $tp = $context['tp'];
    template_tp_nav();
    echo '<div class="cat_bar"><h3 class="catbg">Edit topic tags and prefix</h3></div>',
        '<form class="windowbg tp-block" method="post" action="', $scripturl, '?action=topiclabels;sa=edit;topic=', (int) $tp['topic'], '">',
        '<p>Update labels without changing the original discussion.</p>';
    if ($tp['can_prefixes'])
    {
        echo '<p><label for="tp-prefix"><strong>Topic prefix</strong></label><br><select id="tp-prefix" name="tp_prefix"><option value="0">No prefix</option>';
        foreach ($tp['prefixes'] as $p)
            echo '<option value="', (int) $p['id_prefix'], '"', (int) $tp['prefix'] === (int) $p['id_prefix'] ? ' selected' : '', '>', tp_e($p['name']), '</option>';
        echo '</select></p>';
    }
    if ($tp['can_tags'])
        echo '<p><label for="tp-tags"><strong>Tags (comma-separated)</strong></label><br><input id="tp-tags" name="tp_tags" class="tp-long-input" type="text" value="', tp_e($tp['tags']), '" maxlength="350">',
            '<br><span class="smalltext">Maximum ', (int) $tp['max_tags'], ' tags, 30 characters each.</span></p>';
    echo '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
        '<button class="button" name="save_labels" value="1" type="submit">Save labels</button> ',
        '<a class="button" href="', $scripturl, '?topic=', (int) $tp['topic'], '.0">Cancel</a></form>';
}

function template_tp_admin_tabs()
{
    global $scripturl;
    echo '<div class="tp-nav"><a class="button" href="', $scripturl, '?action=admin;area=topiclabels">Settings</a> ',
        '<a class="button" href="', $scripturl, '?action=admin;area=topiclabels;sa=prefixes">Manage prefixes</a> ',
        '<a class="button" href="', $scripturl, '?action=admin;area=topiclabels;sa=tags">Manage tags</a> ',
        '<a class="button" href="', $scripturl, '?action=topiclabels">View tag browser</a></div>';
}

function template_tp_admin_settings()
{
    global $context, $scripturl;
    template_tp_admin_tabs();
    echo '<div class="cat_bar"><h3 class="catbg">Topic tags & prefixes settings</h3></div>',
        '<form class="windowbg tp-block" method="post" action="', $scripturl, '?action=admin;area=topiclabels">';
    $options = array('enabled' => array('tp_enabled', 'Enable the modification'),
        'tags_enabled' => array('tp_tags_enabled', 'Enable topic tags'),
        'prefixes_enabled' => array('tp_prefixes_enabled', 'Enable topic prefixes'),
        'follow_enabled' => array('tp_follow_enabled', 'Allow tag subscriptions'),
        'member_tags' => array('tp_member_tags', 'Let members create new tags (otherwise existing tags only)'));
    foreach ($options as $field => $detail)
        echo '<p><label><input type="checkbox" name="', $field, '" value="1"', BoredTopicTags::setting($detail[0], 1) ? ' checked' : '', '> ', tp_e($detail[1]), '</label></p>';
    echo '<p><label for="tp-max"><strong>Maximum tags per topic</strong></label><br>',
        '<input type="number" name="max_tags" id="tp-max" min="1" max="12" value="', (int) BoredTopicTags::setting('tp_max_tags', 5), '"></p>',
        '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
        '<button class="button" name="tp_save_settings" value="1" type="submit">Save settings</button></form>';
}

function template_tp_admin_prefixes()
{
    global $context, $scripturl;
    $tp = $context['tp'];
    template_tp_admin_tabs();
    echo '<div class="cat_bar"><h3 class="catbg">Coloured topic prefixes</h3></div>',
        '<div class="windowbg tp-block"><table class="table_grid tp-admin-table"><thead><tr><th>Name</th><th>Colour</th><th>Boards</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($tp['prefixes'] as $p)
        echo '<tr><td>', tp_e($p['name']), '</td><td>', BoredTopicTags::renderPrefix($p), '</td><td>', tp_e($p['board_ids'] === '' ? 'All boards' : $p['board_ids']), '</td>',
            '<td>', $p['enabled'] ? 'Enabled' : 'Disabled', '</td><td><a href="', $scripturl, '?action=admin;area=topiclabels;sa=prefixes;edit=', (int) $p['id_prefix'], '">Edit</a></td></tr>';
    echo '</tbody></table></div>';
    $p = $tp['edit'];
    echo '<div class="cat_bar"><h3 class="catbg">', $p ? 'Edit prefix' : 'Add prefix', '</h3></div>',
        '<form class="windowbg tp-block" method="post" action="', $scripturl, '?action=admin;area=topiclabels;sa=prefixes">',
        '<input type="hidden" name="id_prefix" value="', $p ? (int) $p['id_prefix'] : 0, '">',
        '<p><label>Name<br><input type="text" required name="name" maxlength="40" value="', $p ? tp_e($p['name']) : '', '"></label></p>',
        '<p><label>Colour<br><input type="color" name="color" value="', $p ? tp_e($p['color']) : '#3779ad', '"></label></p>',
        '<p><label>Allowed board IDs (comma-separated, blank means all boards)<br><input type="text" class="tp-long-input" name="boards" value="', $p ? tp_e($p['board_ids']) : '', '"></label></p>',
        '<p><label>Sort order<br><input type="number" name="sort_order" min="0" max="10000" value="', $p ? (int) $p['sort_order'] : 0, '"></label></p>',
        '<p><label><input type="checkbox" name="prefix_enabled" value="1"', !$p || $p['enabled'] ? ' checked' : '', '> Enabled</label></p>',
        '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
        '<button class="button" type="submit" name="tp_save_prefix" value="1">Save prefix</button>';
    if ($p)
        echo ' <button class="button" name="tp_delete_prefix" value="1" type="submit" onclick="return confirm(\'Delete this prefix from every topic?\')">Delete prefix</button>';
    echo '</form>';
}

function template_tp_admin_tags()
{
    global $context, $scripturl;
    template_tp_admin_tabs();
    echo '<div class="cat_bar"><h3 class="catbg">Manage tags</h3></div>',
        '<div class="windowbg tp-block"><p>The first 300 tags are shown alphabetically. Renaming a tag keeps its URL unchanged.</p>',
        '<table class="table_grid tp-admin-table"><thead><tr><th>Tag</th><th>Slug</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($context['tp']['tags'] as $tag)
    {
        echo '<tr><td>', tp_e($tag['name']), '</td><td>', tp_e($tag['slug']), '</td><td>', $tag['enabled'] ? 'Enabled' : 'Disabled', '</td><td>',
            '<form class="tp-inline-form" method="post" action="', $scripturl, '?action=admin;area=topiclabels;sa=tags">',
            '<input type="hidden" name="id_tag" value="', (int) $tag['id_tag'], '">',
            '<input type="text" name="name" maxlength="30" aria-label="New tag name" value="', tp_e($tag['name']), '">',
            '<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">',
            '<button class="button" name="tp_tag_action" value="rename">Rename</button> ',
            '<button class="button" name="tp_tag_action" value="', $tag['enabled'] ? 'disable' : 'enable', '">', $tag['enabled'] ? 'Disable' : 'Enable', '</button> ',
            '<button class="button" name="tp_tag_action" value="delete" onclick="return confirm(\'Delete this tag, its topic assignments and subscriptions?\')">Delete</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
}
