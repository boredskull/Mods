<?php
/** Knowledge Base theme templates - SMF 2.1, ISC. */
if (!defined('SMF'))
    die('No direct access');

function kb_e($s) { return BoredKnowledgeBase::e($s); }
function kb_link($id, $extra = '')
{
    global $scripturl;
    return $scripturl . '?action=knowledgebase;sa=article;id=' . (int) $id . $extra;
}
function kb_session()
{
    global $context;
    echo '<input type="hidden" name="', kb_e($context['session_var']), '" value="', kb_e($context['session_id']), '">';
}
function kb_date($time)
{
    return $time ? timeformat((int) $time) : '-';
}
function template_kb_navigation()
{
    global $txt, $scripturl, $user_info;
    echo '<nav class="kb-nav" aria-label="', kb_e($txt['kb_navigation']), '">',
        '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase">', kb_e($txt['kb_browse']), '</a> ';
    if (empty($user_info['is_guest']))
        echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=my">', kb_e($txt['kb_my_articles']), '</a> ';
    if (BoredKnowledgeBase::canSubmit())
        echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=new">', kb_e($txt['kb_new']), '</a> ';
    if (BoredKnowledgeBase::canReview())
        echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=review">', kb_e($txt['kb_review_queue']), '</a> ';
    if (BoredKnowledgeBase::canManage())
        echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=manage">', kb_e($txt['kb_manage']), '</a> ';
    echo '</nav>';
}

function template_kb_browse()
{
    global $context, $txt, $scripturl;
    $d = $context['kb'];
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_find']), '</h2>',
        '<form method="get" action="', kb_e($scripturl), '"><input type="hidden" name="action" value="knowledgebase">',
        '<div class="kb-row"><div><label for="kb_search">', kb_e($txt['kb_search']), '</label>',
        '<input type="search" name="q" id="kb_search" maxlength="80" value="', kb_e($d['search']), '"></div>',
        '<div><label for="kb_category">', kb_e($txt['kb_category']), '</label><select id="kb_category" name="cat">',
        '<option value="0">', kb_e($txt['kb_all_categories']), '</option>';
    foreach ($d['categories'] as $cat)
        echo '<option value="', (int) $cat['id_category'], '"', (int) $d['category'] === (int) $cat['id_category'] ? ' selected' : '', '>', kb_e($cat['name']), '</option>';
    echo '</select></div><button type="submit" class="button">', kb_e($txt['kb_search_button']), '</button></div></form></section>';
    if (!$d['items'])
        echo '<div class="windowbg kb-card">', kb_e($txt['kb_no_articles']), '</div>';
    else
        foreach ($d['items'] as $a)
            echo '<article class="windowbg kb-card kb-list-item"><h3><a href="', kb_e(kb_link($a['id_article'])), '">', kb_e($a['title']), '</a></h3>',
                '<p>', kb_e($a['summary']), '</p>',
                '<div class="smalltext">', kb_e($a['category_name']), ' · ', BoredKnowledgeBase::name($a['author_name']), ' · ', kb_e($txt['kb_updated']), ': ', kb_date($a['updated']), '</div></article>';
    $base = $scripturl . '?action=knowledgebase;q=' . rawurlencode($d['search']) . ';cat=' . (int) $d['category'];
    echo '<nav class="kb-pagination" aria-label="', kb_e($txt['kb_pagination']), '">';
    if ($d['start'] > 0)
        echo '<a class="button" href="', kb_e($base . ';start=' . max(0, $d['start'] - 20)), '">', kb_e($txt['kb_previous']), '</a> ';
    if ($d['more'])
        echo '<a class="button" href="', kb_e($base . ';start=' . ($d['start'] + 20)), '">', kb_e($txt['kb_next']), '</a>';
    echo '</nav>';
}

function template_kb_my()
{
    global $context, $txt;
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_my_articles']), '</h2>';
    if (!$context['kb']['items'])
        echo '<p>', kb_e($txt['kb_my_empty']), '</p>';
    foreach ($context['kb']['items'] as $a)
        echo '<div class="kb-list-item"><a href="', kb_e(kb_link($a['id_article'])), '">', kb_e($a['title']), '</a>',
            '<p class="smalltext">', kb_e($a['category_name']), ' · ', kb_e($txt['kb_status_' . $a['status']]), ' · ', kb_date($a['updated']), '</p></div>';
    echo '</section>';
}

function template_kb_article()
{
    global $context, $txt, $scripturl;
    $d = $context['kb'];
    $a = $d['article']; $r = $d['revision'];
    template_kb_navigation();
    echo '<article class="windowbg kb-card"><header><h2>', kb_e($r['title']), '</h2>',
        '<div class="smalltext">', kb_e($a['category_name']), ' · ', kb_e($txt['kb_by']), ' ', BoredKnowledgeBase::name($a['author_name']), ' · ', kb_e($txt['kb_updated']), ': ', kb_date($a['updated']), '</div>';
    if ($a['status'] !== 'published' || $d['show_pending'])
        echo '<p class="kb-notice" role="status">', kb_e($d['show_pending'] ? (!empty($a['pending_submitted']) ? $txt['kb_pending_preview'] : $txt['kb_draft_preview']) : $txt['kb_not_public']), '</p>';
    if ((int) $a['review_due'] > 0 && $a['status'] === 'published' && (int) $a['review_due'] < time())
        echo '<p class="kb-warning">', kb_e($txt['kb_review_overdue']), '</p>';
    if (!empty($r['summary']))
        echo '<p class="kb-summary">', kb_e($r['summary']), '</p>';
    echo '</header><div class="kb-body">', $d['body'], '</div>';
    if ($d['source_url'])
        echo '<p class="smalltext"><a href="', kb_e($d['source_url']), '">', kb_e($txt['kb_original_answer']), '</a></p>';
    if ((int) $a['last_reviewed'] > 0)
        echo '<p class="smalltext">', kb_e($txt['kb_last_reviewed']), ': ', kb_date($a['last_reviewed']), '</p>';
    if ((int) $a['review_due'] > 0)
        echo '<p class="smalltext">', kb_e($txt['kb_next_review']), ': ', kb_date($a['review_due']), '</p>';
    echo '</article>';
    if ($d['editable'] || $d['reviewer'])
    {
        echo '<div class="windowbg kb-card kb-actions">';
        if ($d['editable'])
            echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=edit;id=', (int) $a['id_article'], '">', kb_e($txt['kb_edit']), '</a> ';
        echo '<a class="button" href="', kb_e($scripturl), '?action=knowledgebase;sa=history;id=', (int) $a['id_article'], '">', kb_e($txt['kb_history']), '</a> ';
        if ($d['may_see_pending'] && (int) $a['pending_revision'] > 0 && !$d['show_pending'])
            echo '<a class="button" href="', kb_e(kb_link($a['id_article'], ';pending=1')), '">', kb_e($txt['kb_view_proposal']), '</a> ';
        if ($d['reviewer'] && !empty($a['pending_submitted']) && (int) $a['pending_revision'] > 0
            && $a['status'] !== 'archived')
        {
            echo '<form method="post" class="kb-inline" action="', kb_e($scripturl), '?action=knowledgebase;sa=decision;id=', (int) $a['id_article'], '">';
            kb_session();
            echo '<input type="hidden" name="expected_revision" value="', (int) $a['pending_revision'], '">',
                (((int) $a['id_author'] !== (int) $context['user']['id']
                    && (!$d['pending_editor'] || (int) $d['pending_editor']['id_editor'] !== (int) $context['user']['id']))
                    || BoredKnowledgeBase::canManage() ? '<button class="button" name="decision" value="approve">' . kb_e($txt['kb_approve']) . '</button> ' : ''),
                '<button class="button" name="decision" value="reject">', kb_e($txt['kb_reject']), '</button></form> ';
        }
        if ($d['reviewer'] && $a['status'] === 'published')
        {
            echo '<form class="kb-inline" method="post" action="', kb_e($scripturl), '?action=knowledgebase;sa=decision;id=', (int) $a['id_article'], '">';
            kb_session();
            echo '<button class="button" name="decision" value="markreviewed">', kb_e($txt['kb_mark_reviewed']), '</button></form> ';
        }
        if (BoredKnowledgeBase::canManage())
        {
            echo '<form class="kb-inline" method="post" action="', kb_e($scripturl), '?action=knowledgebase;sa=decision;id=', (int) $a['id_article'], '">';
            kb_session();
            if ($a['status'] === 'archived')
                echo '<button class="button" name="decision" value="restore">', kb_e($txt['kb_restore']), '</button>';
            else
                echo '<button class="button" name="decision" value="archive">', kb_e($txt['kb_archive']), '</button>';
            echo '</form>';
        }
        echo '</div>';
    }
    if (BoredKnowledgeBase::setting('feedback_enabled', 1) && $a['status'] === 'published' && !$d['show_pending'] && !empty($a['category_enabled']))
    {
        echo '<section class="windowbg kb-card" aria-label="', kb_e($txt['kb_feedback']), '"><h3>', kb_e($txt['kb_helpful_question']), '</h3>',
            '<p class="smalltext">', kb_e($txt['kb_yes']), ': ', $d['votes'][1], ' · ', kb_e($txt['kb_no']), ': ', $d['votes'][0], '</p>';
        if (!$context['user']['is_guest'])
        {
            echo '<form method="post" action="', kb_e($scripturl), '?action=knowledgebase;sa=feedback;id=', (int) $a['id_article'], '">';
            kb_session();
            echo '<button class="button" name="vote" value="yes"', $d['my_vote'] === 1 ? ' aria-pressed="true"' : '', '>', kb_e($txt['kb_yes']), '</button> ',
                '<button class="button" name="vote" value="no"', $d['my_vote'] === 0 ? ' aria-pressed="true"' : '', '>', kb_e($txt['kb_no']), '</button></form>';
        }
        echo '</section>';
    }
}

function template_kb_editor()
{
    global $context, $txt, $scripturl;
    $d = $context['kb']; $f = $d['form']; $a = $d['article'];
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($a ? $txt['kb_edit'] : $txt['kb_new']), '</h2>';
    if ($d['source'])
        echo '<p class="kb-notice">', kb_e($txt['kb_import_explanation']), '</p>';
    echo '<form method="post" action="', kb_e($scripturl), '?action=knowledgebase;sa=save', $a ? ';id=' . (int) $a['id_article'] : '', '">';
    kb_session();
    echo '<input type="hidden" name="expected_revision" value="', (int) $f['expected_revision'], '">';
    if ($d['source'])
        echo '<input type="hidden" name="qa_topic" value="', (int) $d['source']['id_topic'], '">';
    echo '<div class="kb-field"><label for="kb_title">', kb_e($txt['kb_article_title']), '</label>',
        '<input type="text" id="kb_title" name="title" value="', kb_e($f['title']), '" maxlength="180" required></div>',
        '<div class="kb-field"><label for="kb_summary">', kb_e($txt['kb_summary']), '</label>',
        '<textarea id="kb_summary" name="summary" maxlength="250" rows="2">', kb_e($f['summary']), '</textarea></div>',
        '<div class="kb-field"><label for="kb_body">', kb_e($txt['kb_content']), '</label>',
        '<textarea id="kb_body" name="body" maxlength="50000" rows="16" required>', kb_e($f['body']), '</textarea>',
        '<p class="smalltext">', kb_e($txt['kb_bbcode_help']), '</p></div>',
        '<div class="kb-row"><div class="kb-field"><label for="kb_cat">', kb_e($txt['kb_category']), '</label><select id="kb_cat" name="category" required>';
    foreach ($d['categories'] as $c)
        echo '<option value="', (int) $c['id_category'], '"', (int) $f['category'] === (int) $c['id_category'] ? ' selected' : '', '>', kb_e($c['name']), '</option>';
    echo '</select></div><div class="kb-field"><label for="kb_days">', kb_e($txt['kb_review_interval']), '</label>',
        '<input type="number" min="0" max="3650" id="kb_days" name="review_days" value="', (int) $f['review_days'], '"></div></div>',
        '<div class="kb-field"><label for="kb_note">', kb_e($txt['kb_revision_note']), '</label>',
        '<input type="text" name="note" id="kb_note" maxlength="250"></div>',
        '<div class="kb-actions"><button type="submit" class="button" name="intent" value="submit">', kb_e($txt['kb_submit_review']), '</button> ',
        '<button type="submit" class="button" name="intent" value="draft">', kb_e($txt['kb_save_draft']), '</button></div></form></section>';
}

function template_kb_review()
{
    global $context, $txt;
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_review_queue']), '</h2>',
        '<p>', kb_e($txt['kb_review_explanation']), '</p>';
    if (!$context['kb']['items'])
        echo '<p>', kb_e($txt['kb_empty_queue']), '</p>';
    foreach ($context['kb']['items'] as $row)
        echo '<div class="kb-list-item"><a href="', kb_e(kb_link($row['id_article'], ';pending=1')), '">', kb_e($row['title']), '</a>',
            '<p class="smalltext">', BoredKnowledgeBase::name($row['author_name']), ' · ', kb_e($row['category_name']), ' · ', kb_date($row['updated']), '</p></div>';
    echo '</section>';
}

function template_kb_history()
{
    global $context, $txt, $scripturl;
    $d = $context['kb']; $id = (int) $d['article']['id_article'];
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_history']), '</h2>',
        '<p><a href="', kb_e(kb_link($id)), '">', kb_e($txt['kb_back_article']), '</a></p>';
    foreach ($d['items'] as $r)
        echo '<div class="kb-list-item"><a href="', kb_e($scripturl), '?action=knowledgebase;sa=revision;id=', $id, ';rev=', (int) $r['id_revision'], '">', kb_e($r['title']), ' (#', (int) $r['id_revision'], ')</a>',
            '<p class="smalltext">', BoredKnowledgeBase::name($r['editor_name']), ' · ', kb_date($r['created']), ' · ', kb_e($r['edit_note']), '</p></div>';
    echo '</section>';
}

function template_kb_revision()
{
    global $context, $txt;
    $d = $context['kb'];
    template_kb_navigation();
    echo '<article class="windowbg kb-card"><h2>', kb_e($d['revision']['title']), '</h2>',
        '<p class="kb-notice">', kb_e($txt['kb_history_preview']), '</p>',
        '<p class="smalltext"><a href="', kb_e(kb_link($d['article']['id_article'])), '">', kb_e($txt['kb_back_article']), '</a></p>',
        '<div class="kb-body">', $d['body'], '</div></article>';
}

function template_kb_manage()
{
    global $context, $txt, $scripturl;
    $d = $context['kb'];
    template_kb_navigation();
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_settings']), '</h2>',
        '<form method="post" action="', kb_e($scripturl), '?action=knowledgebase;sa=settings">';
    kb_session();
    $settingFields = array('enabled' => 'kb_enabled', 'show_menu' => 'kb_show_menu', 'feedback' => 'kb_feedback_enabled');
    if (BoredKnowledgeBase::qaAvailable())
        $settingFields['qa_integration'] = 'kb_qa_integration';
    foreach ($settingFields as $input => $setting)
        echo '<label class="kb-check"><input type="checkbox" name="', kb_e($input), '" value="1"',
            BoredKnowledgeBase::setting(substr($setting, 3), 1) ? ' checked' : '', '>', kb_e($txt[$setting]), '</label>';
    echo '<div class="kb-field"><label for="kb_default_days">', kb_e($txt['kb_default_review_interval']), '</label>',
        '<input type="number" min="0" max="3650" id="kb_default_days" name="review_days" value="', (int) BoredKnowledgeBase::setting('review_days', 180), '"></div>',
        '<button class="button" type="submit">', kb_e($txt['kb_save_settings']), '</button></form></section>';
    echo '<section class="windowbg kb-card"><h2>', kb_e($txt['kb_categories']), '</h2>';
    foreach ($d['categories'] as $cat)
        template_kb_category_form($cat);
    template_kb_category_form(null);
    echo '</section><section class="windowbg kb-card"><h2>', kb_e($txt['kb_due_reviews']), '</h2>';
    if (!$d['due'])
        echo '<p>', kb_e($txt['kb_none_due']), '</p>';
    foreach ($d['due'] as $a)
        echo '<div class="kb-list-item"><a href="', kb_e(kb_link($a['id_article'])), '">', kb_e($a['title']), '</a> · ', kb_date($a['review_due']), '</div>';
    echo '</section>';
}

function template_kb_category_form($cat)
{
    global $scripturl, $txt;
    $id = $cat ? (int) $cat['id_category'] : 0;
    echo '<form method="post" class="kb-category-form" action="', kb_e($scripturl), '?action=knowledgebase;sa=savecat;id=', $id, '">';
    kb_session();
    echo '<fieldset><legend>', kb_e($cat ? $cat['name'] : $txt['kb_add_category']), '</legend>',
        '<div class="kb-row"><div class="kb-field"><label for="kb_cat_name_', $id, '">', kb_e($txt['kb_category_name']), '</label>',
        '<input id="kb_cat_name_', $id, '" type="text" name="name" maxlength="120" required value="', kb_e($cat ? $cat['name'] : ''), '"></div>',
        '<div class="kb-field"><label for="kb_cat_order_', $id, '">', kb_e($txt['kb_sort_order']), '</label>',
        '<input id="kb_cat_order_', $id, '" type="number" name="sort_order" min="0" max="99999" value="', $cat ? (int) $cat['sort_order'] : 0, '"></div></div>',
        '<div class="kb-field"><label for="kb_cat_desc_', $id, '">', kb_e($txt['kb_description']), '</label>',
        '<textarea id="kb_cat_desc_', $id, '" name="description" maxlength="1000" rows="2">', kb_e($cat ? $cat['description'] : ''), '</textarea></div>',
        '<label class="kb-check"><input name="enabled" type="checkbox" value="1"', !$cat || !empty($cat['enabled']) ? ' checked' : '', '>', kb_e($txt['kb_enabled']), '</label>',
        '<button type="submit" class="button">', kb_e($txt['kb_save_category']), '</button></fieldset></form>';
}
