<?php
/**
 * Knowledge Base 1.0.4 - standalone SMF 2.1.x knowledge articles.
 * Copyright (c) 2026 BoredSkull. ISC licence.
 */
if (!defined('SMF'))
    die('No direct access');

class BoredKnowledgeBase
{
    const VERSION = '1.0.4';
    const PAGE_SIZE = 20;

    public static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escape a name SMF already stores HTML-escaped (member names), without double-escaping. */
    public static function name($s)
    {
        return self::e(html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Body HTML. Bodies are stored as typed, but SMF's parse_bbc() expects
     * HTML-escaped input (as forum posts are stored), so escape first.
     */
    public static function renderBody($body)
    {
        global $smcFunc;
        return parse_bbc($smcFunc['htmlspecialchars']((string) $body, ENT_QUOTES));
    }

    /**
     * SQL: can guests read board alias b? SMF 2.1 decides visibility from
     * board_permissions_view (not the legacy boards.member_groups list, which
     * other mods may not keep in step), honouring deny rows when enabled.
     */
    public static function guestBoardSql($alias = 'b')
    {
        global $modSettings;
        $sql = 'EXISTS (SELECT 1 FROM {db_prefix}board_permissions_view AS kb_gv
                WHERE kb_gv.id_board = ' . $alias . '.id_board AND kb_gv.id_group = -1 AND kb_gv.deny = 0)';
        if (!empty($modSettings['deny_boards_access']))
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM {db_prefix}board_permissions_view AS kb_gd
                WHERE kb_gd.id_board = ' . $alias . '.id_board AND kb_gd.id_group = -1 AND kb_gd.deny = 1)';
        return $sql;
    }

    public static function setting($name, $default = 0)
    {
        global $modSettings;
        return isset($modSettings['kb_' . $name]) ? $modSettings['kb_' . $name] : $default;
    }

    /** Detect the optional Q&A mod through its registered SMF integration hooks. */
    public static function qaAvailable()
    {
        global $modSettings;
        if (class_exists('QA', false) && method_exists('QA', 'enabled'))
            return true;

        // SMF stores registered integration callbacks in modSettings.
        // Match the Q&A class callback, not an unrelated setting or table.
        foreach ($modSettings as $key => $callbacks)
        {
            if (strpos((string) $key, 'integrate_') !== 0 || !is_string($callbacks))
                continue;
            if (preg_match('~(?:^|[,\\n])\\s*(?:[^,\\n]*[|])?QA::[A-Za-z_][A-Za-z_0-9]*~', $callbacks))
                return true;
        }
        return false;
    }

    public static function active()
    {
        return (bool) self::setting('enabled', 1);
    }

    public static function canSubmit()
    {
        global $user_info;
        return !$user_info['is_guest'] && (allowedTo('kb_submit') || allowedTo('admin_forum'));
    }

    public static function canReview()
    {
        return allowedTo('kb_review') || allowedTo('admin_forum');
    }

    public static function canManage()
    {
        return allowedTo('admin_forum');
    }

    public static function canEdit($article)
    {
        global $user_info;
        if (empty($article) || $article['status'] === 'archived' || $user_info['is_guest'])
            return false;
        return allowedTo('admin_forum') || allowedTo('kb_edit_any') ||
            ((int) $article['id_author'] === (int) $user_info['id'] && allowedTo('kb_edit_own'));
    }

    public static function canView($article)
    {
        global $user_info;
        if (!$article)
            return false;
        // The copied source is never declassified. If a public Q&A source becomes
        // restricted, the linked article is withheld from everyone until reviewed.
        if (!empty($article['source_topic']) && !self::publicSource((int) $article['source_topic'], (int) $article['source_msg']))
            return false;
        if ($article['status'] === 'published')
            return !empty($article['category_enabled']) || self::canManage() || self::canReview() || self::canEdit($article);
        // A submitted revision is visible to the reviser and reviewers; an
        // unsubmitted draft belongs to its reviser (plus administrators).
        return self::canViewPending($article);
    }

    /** Only reviewers see submitted proposals. Drafts stay with their editor. */
    public static function canViewPending($article)
    {
        global $user_info;
        if (empty($article['pending_revision']) || $user_info['is_guest'])
            return false;
        if (self::canManage())
            return true;
        $r = self::loadRevision((int) $article['pending_revision'], (int) $article['id_article']);
        return $r && ((int) $r['id_editor'] === (int) $user_info['id']
            || (!empty($article['pending_submitted']) && self::canReview()));
    }

    /** A publicly accessible KB must never copy material from a restricted Q&A board. */
    public static function publicSource($topic, $msg)
    {
        global $smcFunc;
        if ($topic <= 0 || $msg <= 0)
            return false;
        $req = $smcFunc['db_query']('', '
            SELECT t.id_topic FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:msg} AND m.id_topic = t.id_topic)
            WHERE t.id_topic = {int:topic} AND t.approved = {int:yes} AND m.approved = {int:yes}
                AND ' . self::guestBoardSql('b') . '
            LIMIT 1', array('topic' => (int) $topic, 'msg' => (int) $msg,
                'yes' => 1, 'guest' => -1, 'zero' => 0));
        $public = (bool) $smcFunc['db_fetch_assoc']($req);
        $smcFunc['db_free_result']($req);
        return $public;
    }

    /** Public articles, previously approved revisions, and authorised proposals. */
    public static function canViewRevision($article, $revision)
    {
        global $user_info;
        if (!$article || !$revision || $user_info['is_guest'])
            return false;
        if (!empty($revision['is_published']))
            return self::canEdit($article) || self::canReview() || self::canManage();
        if (self::canManage() || (int) $revision['id_editor'] === (int) $user_info['id'])
            return true;
        return self::canReview() && !empty($article['pending_submitted'])
            && (int) $article['pending_revision'] === (int) $revision['id_revision'];
    }

    public static function loadArticle($id)
    {
        global $smcFunc;
        if ($id <= 0)
            return null;
        $r = $smcFunc['db_query']('', '
            SELECT a.*, c.name AS category_name, c.enabled AS category_enabled,
                COALESCE(mem.real_name, {string:former}) AS author_name
            FROM {db_prefix}kb_articles AS a
                INNER JOIN {db_prefix}kb_categories AS c ON (c.id_category = a.id_category)
                LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_author)
            WHERE a.id_article = {int:id}
            LIMIT 1', array('id' => (int) $id, 'former' => 'Former member'));
        $a = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $a ?: null;
    }

    public static function loadRevision($id, $article)
    {
        global $smcFunc;
        if ($id <= 0)
            return null;
        $r = $smcFunc['db_query']('', '
            SELECT r.*, COALESCE(mem.real_name, {string:former}) AS editor_name
            FROM {db_prefix}kb_revisions AS r
                LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = r.id_editor)
            WHERE r.id_revision = {int:rev} AND r.id_article = {int:article}
            LIMIT 1', array('rev' => (int) $id, 'article' => (int) $article, 'former' => 'Former member'));
        $row = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $row ?: null;
    }

    public static function currentRevision($article, $editor = false)
    {
        $id = $editor && !empty($article['pending_revision']) ? (int) $article['pending_revision'] : (int) $article['current_revision'];
        return self::loadRevision($id, (int) $article['id_article']);
    }

    public static function categories($includeDisabled = false)
    {
        global $smcFunc;
        $r = $smcFunc['db_query']('', '
            SELECT id_category, name, description, enabled, sort_order
            FROM {db_prefix}kb_categories' . ($includeDisabled ? '' : ' WHERE enabled = {int:yes}') . '
            ORDER BY sort_order, name, id_category', $includeDisabled ? array() : array('yes' => 1));
        $rows = array();
        while ($row = $smcFunc['db_fetch_assoc']($r))
            $rows[] = $row;
        $smcFunc['db_free_result']($r);
        return $rows;
    }

    public static function validCategory($id)
    {
        foreach (self::categories(false) as $c)
            if ((int) $c['id_category'] === (int) $id)
                return true;
        return false;
    }

    public static function actions(&$actions)
    {
        $actions['knowledgebase'] = array('KnowledgeBase-Pages.php', 'KB_Pages::dispatch');
    }

    public static function menuButtons(&$buttons)
    {
        global $scripturl, $txt;
        if (!self::active() || !self::setting('show_menu', 1))
            return;
        loadLanguage('KnowledgeBase');
        $button = array('knowledgebase' => array(
            'title' => $txt['kb_title'], 'href' => $scripturl . '?action=knowledgebase',
            'show' => true, 'icon' => 'help',
        ));
        $keys = array_keys($buttons);
        $pos = array_search('search', $keys, true);
        $buttons = $pos === false ? $buttons + $button :
            array_slice($buttons, 0, $pos + 1, true) + $button + array_slice($buttons, $pos + 1, null, true);
    }

    public static function loadTheme()
    {
        if (!self::active())
            return;
        loadCSSFile('knowledgebase.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_kb');
    }

    public static function permissions(&$groups, &$list, &$left = array(), &$hidden = array(), &$relabel = array())
    {
        loadLanguage('KnowledgeBase');
        foreach (array('kb_submit', 'kb_edit_own', 'kb_edit_any', 'kb_review') as $perm)
            $list['membergroup'][$perm] = array(false, 'general', 'view_basic_info');
    }

    public static function illegalGuestPermissions()
    {
        global $context;
        foreach (array('kb_submit', 'kb_edit_own', 'kb_edit_any', 'kb_review') as $perm)
            $context['non_guest_permissions'][] = $perm;
    }

    public static function credits()
    {
        global $context, $txt;
        loadLanguage('KnowledgeBase');
        $context['copyrights']['mods'][] = sprintf($txt['kb_credits'], self::VERSION);
    }

    public static function adminAreas(&$areas)
    {
        global $txt, $scripturl;
        loadLanguage('KnowledgeBase');

        // SMF 2.1: register as a subsection of Modification Settings,
        // not a separate Configuration area.
        if (!isset($areas['config']['areas']['modsettings']['subsections']))
            return;

        $areas['config']['areas']['modsettings']['subsections']['knowledgebase'] = array(
            $txt['kb_title'],
            'admin_forum',
            'url' => $scripturl . '?action=knowledgebase;sa=manage',
        );

        // Remove the old standalone entry if another copy registered it.
        unset($areas['config']['areas']['knowledgebase']);
    }

    /** Optional integration with the *actual* BoredSkull Questions & Answers 1.2.0 hooks. */
    public static function prepareDisplayContext(&$output, &$message, $counter)
    {
        global $scripturl, $txt;
        if (!self::active() || !self::setting('qa_integration', 1) || !self::canSubmit()
            || !class_exists('QA', false) || !QA::enabled() || empty(QA::$current))
            return;
        $q = QA::$current;
        $id = isset($output['id']) ? (int) $output['id'] : 0;
        if ($id < 1 || (int) $q['id_answer'] !== $id || empty($output['approved']))
            return;
        $topic = isset(QA::$topic_row['id_topic']) ? (int) QA::$topic_row['id_topic'] : 0;
        if (!$topic || !self::publicSource($topic, $id))
            return;
        loadLanguage('KnowledgeBase');
        $output['body'] .= '<div class="kb-promote"><a href="' . self::e($scripturl . '?action=knowledgebase;sa=new;qa_topic=' . $topic) . '">' . self::e($txt['kb_promote']) . '</a></div>';
    }

    /** Only allow extracting an accepted, approved answer in a currently visible Q&A board. */
    public static function qaSource($topic)
    {
        global $smcFunc;
        if (!self::setting('qa_integration', 1) || !class_exists('QA', false) || !QA::enabled() || $topic <= 0)
            return null;
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic, t.id_board, q.id_answer, m.body AS answer_body, m.approved AS answer_approved,
                   firstmsg.subject AS question_title
            FROM {db_prefix}qa_topics AS q
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer AND m.id_topic = t.id_topic)
                INNER JOIN {db_prefix}messages AS firstmsg ON (firstmsg.id_msg = t.id_first_msg)
            WHERE q.id_topic = {int:topic} AND q.solved = {int:yes}
                AND q.id_answer > {int:zero}
                AND t.approved = {int:yes} AND m.approved = {int:yes} AND firstmsg.approved = {int:yes}
                AND {query_see_board}
                AND ' . self::guestBoardSql('b') . '
            LIMIT 1', array('topic' => (int) $topic, 'yes' => 1, 'zero' => 0));
        $row = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        if (!$row || QA::boardMode((int) $row['id_board']) === QA::MODE_OFF)
            return null;
        // Forum posts are stored HTML-escaped with <br> line breaks; the editor
        // and Knowledge Base store plain text, so convert once on import.
        $row['answer_body'] = un_htmlspecialchars(str_replace(array('<br>', '<br />'), "\n", $row['answer_body']));
        $row['question_title'] = un_htmlspecialchars($row['question_title']);
        return $row;
    }

    /** Check original visibility again before showing a source link. */
    public static function safeSourceLink($topic, $msg)
    {
        global $smcFunc, $scripturl;
        if (!$topic || !$msg)
            return '';
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = {int:msg} AND m.id_topic = t.id_topic)
            WHERE t.id_topic = {int:topic} AND t.approved = {int:yes}
                AND m.approved = {int:yes} AND {query_see_board}
            LIMIT 1', array('topic' => (int) $topic, 'msg' => (int) $msg, 'yes' => 1));
        $visible = (bool) $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $visible ? $scripturl . '?topic=' . (int) $topic . '.msg' . (int) $msg . '#msg' . (int) $msg : '';
    }
}
