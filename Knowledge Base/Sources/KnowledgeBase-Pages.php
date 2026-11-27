<?php
/** Knowledge Base 1.0.4 pages/actions - BoredSkull 2026, ISC. */
if (!defined('SMF'))
    die('No direct access');

class KB_Pages
{
    public static function adminEntry()
    {
        isAllowedTo('admin_forum');
        redirectexit('action=knowledgebase;sa=manage');
    }

    protected static function deny($key = 'kb_denied')
    {
        global $txt;
        fatal_error($txt[$key], false);
    }

    protected static function id()
    {
        return isset($_REQUEST['id']) ? max(0, (int) $_REQUEST['id']) : 0;
    }

    protected static function redirect($id = 0, $extra = '')
    {
        redirectexit('action=knowledgebase' . ($id ? ';sa=article;id=' . (int) $id : '') . $extra);
    }

    /** Prevent pending Q&A-sourced content leaking into review and history to staff without source-board access. */
    protected static function requireSourceAccess($a)
    {
        if ($a && (int) $a['source_topic'] > 0 && (int) $a['source_msg'] > 0
            && BoredKnowledgeBase::safeSourceLink((int) $a['source_topic'], (int) $a['source_msg']) === '')
            self::deny('kb_not_found');
    }

    public static function dispatch()
    {
        global $context, $txt, $scripturl;
        loadLanguage('KnowledgeBase');
        if (!BoredKnowledgeBase::active() && !BoredKnowledgeBase::canManage())
            self::deny('kb_disabled');
        loadTemplate('KnowledgeBase');
        $context['page_title'] = $txt['kb_title'];
        $context['linktree'][] = array('url' => $scripturl . '?action=knowledgebase', 'name' => $txt['kb_title']);
        $sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'browse';
        switch ($sa)
        {
            case 'browse': self::browse(); break;
            case 'my': self::myArticles(); break;
            case 'article': self::article(); break;
            case 'new': self::editor(); break;
            case 'edit': self::editor(self::id()); break;
            case 'save': self::save(); break;
            case 'review': self::reviewQueue(); break;
            case 'decision': self::decision(); break;
            case 'history': self::history(); break;
            case 'revision': self::revision(); break;
            case 'feedback': self::feedback(); break;
            case 'manage': self::manage(); break;
            case 'savecat': self::saveCategory(); break;
            case 'settings': self::saveSettings(); break;
            default: self::deny('kb_not_found');
        }
    }

    public static function browse()
    {
        global $context, $smcFunc, $txt;
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $q = mb_substr($q, 0, 80);
        $cat = isset($_GET['cat']) ? max(0, (int) $_GET['cat']) : 0;
        $start = isset($_GET['start']) ? min(100000, max(0, (int) $_GET['start'])) : 0;
        $cats = BoredKnowledgeBase::categories();
        if ($cat > 0 && !BoredKnowledgeBase::validCategory($cat))
            self::deny('kb_not_found');
        $args = array('published' => 'published', 'yes' => 1, 'zero' => 0,
            'start' => $start, 'limit' => BoredKnowledgeBase::PAGE_SIZE + 1);
        $where = '';
        if ($cat)
        {
            $where .= ' AND a.id_category = {int:cat}';
            $args['cat'] = $cat;
        }
        if ($q !== '')
        {
            $where .= ' AND (r.title LIKE {string:pattern} OR r.body LIKE {string:pattern})';
            $args['pattern'] = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q) . '%';
        }
        $req = $smcFunc['db_query']('', '
            SELECT a.id_article, a.published_at, a.updated, a.review_due, c.name AS category_name,
                r.title, r.summary, COALESCE(mem.real_name, {string:former}) AS author_name
            FROM {db_prefix}kb_articles AS a
                INNER JOIN {db_prefix}kb_categories AS c ON (c.id_category = a.id_category AND c.enabled = {int:yes})
                INNER JOIN {db_prefix}kb_revisions AS r ON (r.id_revision = a.current_revision)
                LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_author)
                LEFT JOIN {db_prefix}topics AS st ON (st.id_topic = a.source_topic)
                LEFT JOIN {db_prefix}boards AS b ON (b.id_board = st.id_board)
                LEFT JOIN {db_prefix}messages AS src ON (src.id_msg = a.source_msg AND src.id_topic = st.id_topic)
            WHERE a.status = {string:published}
                AND (a.source_topic = {int:zero} OR (st.approved = {int:yes} AND src.approved = {int:yes}
                    AND ' . BoredKnowledgeBase::guestBoardSql('b') . '))' . $where . '
            ORDER BY a.updated DESC, a.id_article DESC
            LIMIT {int:limit} OFFSET {int:start}', $args + array('former' => $txt['kb_former_member']));
        $items = array();
        while ($row = $smcFunc['db_fetch_assoc']($req))
            $items[] = $row;
        $smcFunc['db_free_result']($req);
        $more = count($items) > BoredKnowledgeBase::PAGE_SIZE;
        if ($more)
            array_pop($items);
        $context['kb'] = array('items' => $items, 'categories' => $cats, 'search' => $q, 'category' => $cat, 'start' => $start, 'more' => $more);
        $context['sub_template'] = 'kb_browse';
    }

    public static function myArticles()
    {
        global $context, $smcFunc, $user_info, $txt;
        is_not_guest();
        $req = $smcFunc['db_query']('', '
            SELECT a.id_article, a.status, a.updated, a.pending_revision, c.name AS category_name, r.title
            FROM {db_prefix}kb_articles AS a
                INNER JOIN {db_prefix}kb_categories AS c ON (c.id_category = a.id_category)
                LEFT JOIN {db_prefix}kb_revisions AS draft ON (draft.id_revision = a.pending_revision)
                INNER JOIN {db_prefix}kb_revisions AS r ON (r.id_revision = CASE
                    WHEN a.pending_revision > {int:zero} AND draft.id_editor = a.id_author
                        THEN a.pending_revision ELSE a.current_revision END)
            WHERE a.id_author = {int:author}
            ORDER BY a.id_article DESC LIMIT {int:limit}',
            array('zero' => 0, 'author' => (int) $user_info['id'], 'limit' => 100));
        $items = array();
        while ($row = $smcFunc['db_fetch_assoc']($req))
            $items[] = $row;
        $smcFunc['db_free_result']($req);
        $context['kb'] = array('items' => $items);
        $context['sub_template'] = 'kb_my';
        $context['robot_no_index'] = true;
    }

    public static function article()
    {
        global $context, $smcFunc, $txt, $scripturl;
        $a = BoredKnowledgeBase::loadArticle(self::id());
        if (!BoredKnowledgeBase::canView($a))
            self::deny('kb_not_found');
        $editable = BoredKnowledgeBase::canEdit($a);
        $reviewer = BoredKnowledgeBase::canReview();
        $maySeePending = BoredKnowledgeBase::canViewPending($a);
        $showPending = !empty($_GET['pending']) && $maySeePending;
        if ($a['status'] !== 'published' || $showPending)
        {
            if (!$maySeePending)
                self::deny('kb_not_found');
            self::requireSourceAccess($a);
        }
        $revision = BoredKnowledgeBase::currentRevision($a, $showPending || $a['status'] !== 'published');
        if (!$revision)
            self::deny('kb_not_found');
        // Drafts are never publicly indexed.
        if ($a['status'] !== 'published' || $showPending)
            $context['robot_no_index'] = true;
        // SMF prints page_title unescaped (<title>, theme headers).
        $context['page_title'] = BoredKnowledgeBase::e($revision['title']) . ' - ' . $txt['kb_title'];
        $req = $smcFunc['db_query']('', '
            SELECT vote, COUNT(*) AS n FROM {db_prefix}kb_feedback
            WHERE id_article = {int:id} GROUP BY vote', array('id' => (int) $a['id_article']));
        $votes = array(1 => 0, 0 => 0);
        while ($r = $smcFunc['db_fetch_assoc']($req))
            $votes[(int) $r['vote']] = (int) $r['n'];
        $smcFunc['db_free_result']($req);
        $mine = -1;
        if (!empty($context['user']['id']))
        {
            $req = $smcFunc['db_query']('', 'SELECT vote FROM {db_prefix}kb_feedback WHERE id_article = {int:article} AND id_member = {int:member} LIMIT 1',
                array('article' => (int) $a['id_article'], 'member' => (int) $context['user']['id']));
            $v = $smcFunc['db_fetch_assoc']($req);
            $smcFunc['db_free_result']($req);
            $mine = $v ? (int) $v['vote'] : -1;
        }
        $source = BoredKnowledgeBase::safeSourceLink((int) $a['source_topic'], (int) $a['source_msg']);
        // SMF's native BBCode parser performs the same output sanitisation as forum posts.
        $body = BoredKnowledgeBase::renderBody($revision['body']);
        $context['kb'] = array('article' => $a, 'revision' => $revision, 'body' => $body,
            'votes' => $votes, 'my_vote' => $mine, 'source_url' => $source,
            'show_pending' => $showPending, 'may_see_pending' => $maySeePending,
            'editable' => $editable, 'reviewer' => $reviewer,
            'pending_editor' => $maySeePending && !empty($a['pending_revision'])
                ? BoredKnowledgeBase::loadRevision((int) $a['pending_revision'], (int) $a['id_article']) : null);
        $context['sub_template'] = 'kb_article';
    }

    public static function editor($id = 0)
    {
        global $context, $txt;
        if (!BoredKnowledgeBase::canSubmit() && !$id)
            self::deny();
        $article = $id ? BoredKnowledgeBase::loadArticle($id) : null;
        if ($id && !BoredKnowledgeBase::canEdit($article))
            self::deny();
        if ($id)
            self::requireSourceAccess($article);
        $maySeePending = $article && BoredKnowledgeBase::canViewPending($article);
        if ($article && !empty($article['pending_revision']) && !$maySeePending)
            self::deny('kb_conflict');
        $revision = $article ? BoredKnowledgeBase::currentRevision($article, $maySeePending) : null;
        $source = null;
        if (!$article && !empty($_GET['qa_topic']))
        {
            $source = BoredKnowledgeBase::qaSource((int) $_GET['qa_topic']);
            if (!$source)
                self::deny('kb_qa_unavailable');
        }
        $categories = BoredKnowledgeBase::categories(false);
        if (!$article && !$categories)
            self::deny('kb_no_categories');
        $context['kb'] = array('article' => $article, 'revision' => $revision, 'categories' => $categories,
            'source' => $source, 'form' => array(
                'title' => $revision ? $revision['title'] : ($source ? $source['question_title'] : ''),
                'body' => $revision ? $revision['body'] : ($source ? $source['answer_body'] : ''),
                'summary' => $revision ? $revision['summary'] : '',
                'category' => $revision ? (int) $revision['id_category'] : 0,
                'review_days' => $revision ? (int) $revision['review_days'] : (int) BoredKnowledgeBase::setting('review_days', 180),
                'expected_revision' => $article ? (int) ($article['pending_revision'] ?: $article['current_revision']) : 0,
            ));
        $context['sub_template'] = 'kb_editor';
        $context['page_title'] = ($article ? $txt['kb_edit'] : $txt['kb_new']) . ' - ' . $txt['kb_title'];
        $context['robot_no_index'] = true;
    }

    public static function save()
    {
        global $smcFunc, $user_info, $txt;
        checkSession('post');
        $id = self::id();
        $a = $id ? BoredKnowledgeBase::loadArticle($id) : null;
        if ($id ? !BoredKnowledgeBase::canEdit($a) : !BoredKnowledgeBase::canSubmit())
            self::deny();
        if ($id)
            self::requireSourceAccess($a);
        $title = isset($_POST['title']) ? trim((string) $_POST['title']) : '';
        $summary = isset($_POST['summary']) ? trim((string) $_POST['summary']) : '';
        $body = isset($_POST['body']) ? trim((string) $_POST['body']) : '';
        $note = isset($_POST['note']) ? trim((string) $_POST['note']) : '';
        $cat = isset($_POST['category']) ? (int) $_POST['category'] : 0;
        $days = isset($_POST['review_days']) ? (int) $_POST['review_days'] : 180;
        $intent = isset($_POST['intent']) && $_POST['intent'] === 'draft' ? 'draft' : 'submit';
        if ($title === '' || strlen($title) > 180 || preg_match('/[\r\n\x00-\x1f]/', $title)
            || strlen($summary) > 250 || strlen($body) < 10 || strlen($body) > 50000
            || strlen($note) > 250 || $days < 0 || $days > 3650
            || preg_match('~\[\s*/?html(?:\s|=|\])~i', $body))
            self::deny('kb_invalid_content');
        if (!BoredKnowledgeBase::validCategory($cat))
            self::deny('kb_invalid_category');
        $sourceTopic = 0;
        $sourceMessage = 0;
        if (!$id && !empty($_POST['qa_topic']))
        {
            $source = BoredKnowledgeBase::qaSource((int) $_POST['qa_topic']);
            if (!$source)
                self::deny('kb_qa_unavailable');
            $sourceTopic = (int) $source['id_topic'];
            $sourceMessage = (int) $source['id_answer'];
        }
        $now = time();
        $smcFunc['db_transaction']('begin');
        if ($id)
        {
            $lock = $smcFunc['db_query']('', '
                SELECT current_revision, pending_revision, status FROM {db_prefix}kb_articles
                WHERE id_article = {int:id} FOR UPDATE', array('id' => $id));
            $fresh = $smcFunc['db_fetch_assoc']($lock);
            $smcFunc['db_free_result']($lock);
            $expected = isset($_POST['expected_revision']) ? (int) $_POST['expected_revision'] : -1;
            if (!$fresh || $fresh['status'] === 'archived' || $expected !== (int) ($fresh['pending_revision'] ?: $fresh['current_revision']))
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_conflict');
            }
            // Do not let another editor silently replace an unsubmitted draft or
            // an already submitted proposal, even with edit-any permission.
            if (!empty($fresh['pending_revision']))
            {
                $pendingOwner = BoredKnowledgeBase::loadRevision((int) $fresh['pending_revision'], $id);
                if (!$pendingOwner || ((int) $pendingOwner['id_editor'] !== (int) $user_info['id']
                    && !BoredKnowledgeBase::canManage()))
                {
                    $smcFunc['db_transaction']('rollback');
                    self::deny('kb_conflict');
                }
            }
        }
        else
        {
            $smcFunc['db_insert']('insert', '{db_prefix}kb_articles',
                array('id_category' => 'int', 'id_author' => 'int', 'created' => 'int', 'updated' => 'int',
                    'published_at' => 'int', 'last_reviewed' => 'int', 'review_due' => 'int', 'review_days' => 'int',
                    'status' => 'string-12', 'current_revision' => 'int', 'pending_revision' => 'int',
                    'pending_submitted' => 'int', 'source_topic' => 'int', 'source_msg' => 'int'),
                array($cat, (int) $user_info['id'], $now, $now, 0, 0, 0, $days,
                    $intent === 'draft' ? 'draft' : 'pending', 0, 0, $intent === 'submit' ? 1 : 0,
                    $sourceTopic, $sourceMessage), array('id_article'));
            $id = (int) $smcFunc['db_insert_id']('{db_prefix}kb_articles', 'id_article');
            if ($id < 1)
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_save_failed');
            }
        }
        $smcFunc['db_insert']('insert', '{db_prefix}kb_revisions',
            array('id_article' => 'int', 'id_category' => 'int', 'review_days' => 'int', 'title' => 'string-180', 'summary' => 'string-250', 'body' => 'string',
                'edit_note' => 'string-250', 'id_editor' => 'int', 'created' => 'int'),
            array($id, $cat, $days, $title, $summary, $body, $note, (int) $user_info['id'], $now), array('id_revision'));
        $revision = (int) $smcFunc['db_insert_id']('{db_prefix}kb_revisions', 'id_revision');
        if ($revision < 1)
        {
            $smcFunc['db_transaction']('rollback');
            self::deny('kb_save_failed');
        }
        if ($a)
        {
            $newStatus = $fresh['status'] === 'published' ? 'published' : ($intent === 'draft' ? 'draft' : 'pending');
            // A proposal must not change the public article's category, review date or sort order.
            // Those changes are staged in kb_revisions until an editor approves them.
            if ($fresh['status'] === 'published')
                $smcFunc['db_query']('', '
                    UPDATE {db_prefix}kb_articles SET pending_revision = {int:rev},
                        pending_submitted = {int:submitted}
                    WHERE id_article = {int:id}', array('rev' => $revision,
                        'submitted' => $intent === 'submit' ? 1 : 0, 'id' => $id));
            else
                $smcFunc['db_query']('', '
                    UPDATE {db_prefix}kb_articles SET id_category = {int:category}, review_days = {int:days},
                        updated = {int:now}, pending_revision = {int:rev}, pending_submitted = {int:submitted},
                        status = {string:status}
                    WHERE id_article = {int:id}',
                    array('category' => $cat, 'days' => $days, 'now' => $now, 'rev' => $revision,
                        'submitted' => $intent === 'submit' ? 1 : 0, 'status' => $newStatus, 'id' => $id));
        }
        else
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_articles SET pending_revision = {int:rev}, pending_submitted = {int:submitted}
                WHERE id_article = {int:id}',
                array('rev' => $revision, 'submitted' => $intent === 'submit' ? 1 : 0, 'id' => $id));
        $smcFunc['db_transaction']('commit');
        self::redirect($id);
    }

    public static function reviewQueue()
    {
        global $context, $smcFunc, $txt;
        if (!BoredKnowledgeBase::canReview())
            self::deny();
        $req = $smcFunc['db_query']('', '
            SELECT a.id_article, a.status, a.updated, a.review_due, c.name AS category_name,
                r.title, COALESCE(mem.real_name, {string:former}) AS author_name
            FROM {db_prefix}kb_articles AS a
                INNER JOIN {db_prefix}kb_categories AS c ON (c.id_category = a.id_category)
                INNER JOIN {db_prefix}kb_revisions AS r ON (r.id_revision = a.pending_revision)
                LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.id_author)
                LEFT JOIN {db_prefix}topics AS st ON (st.id_topic = a.source_topic)
                LEFT JOIN {db_prefix}boards AS b ON (b.id_board = st.id_board)
                LEFT JOIN {db_prefix}messages AS src ON (src.id_msg = a.source_msg AND src.id_topic = st.id_topic)
            WHERE a.pending_submitted = {int:yes} AND a.pending_revision > {int:zero}
                AND a.status IN ({array_string:statuses})
                AND (a.source_topic = {int:zero} OR (st.approved = {int:yes} AND src.approved = {int:yes}
                    AND {query_see_board} AND ' . BoredKnowledgeBase::guestBoardSql('b') . '))
            ORDER BY r.created ASC, a.id_article ASC LIMIT {int:limit}',
            array('statuses' => array('pending', 'published'), 'zero' => 0, 'yes' => 1,
                'limit' => 100, 'former' => $txt['kb_former_member']));
        $rows = array();
        while ($r = $smcFunc['db_fetch_assoc']($req))
            $rows[] = $r;
        $smcFunc['db_free_result']($req);
        $context['kb'] = array('items' => $rows);
        $context['sub_template'] = 'kb_review';
        $context['robot_no_index'] = true;
    }

    public static function decision()
    {
        global $smcFunc, $user_info;
        if (!BoredKnowledgeBase::canReview())
            self::deny();
        checkSession('post');
        $id = self::id();
        $decision = isset($_POST['decision']) ? (string) $_POST['decision'] : '';
        if (!in_array($decision, array('approve', 'reject', 'markreviewed', 'archive', 'restore'), true))
            self::deny();
        if (in_array($decision, array('archive', 'restore'), true) && !BoredKnowledgeBase::canManage())
            self::deny();
        $smcFunc['db_transaction']('begin');
        $request = $smcFunc['db_query']('', 'SELECT * FROM {db_prefix}kb_articles WHERE id_article = {int:id} FOR UPDATE', array('id' => $id));
        $a = $smcFunc['db_fetch_assoc']($request);
        $smcFunc['db_free_result']($request);
        if (!$a)
        {
            $smcFunc['db_transaction']('rollback');
            self::deny('kb_not_found');
        }
        // Load the exact pending revision while the article is locked, before
        // checking approval permissions. The original article author is not
        // necessarily the person who submitted the revision.
        $pending = null;
        if (in_array($decision, array('approve', 'reject'), true))
        {
            if (empty($a['pending_revision']) || empty($a['pending_submitted'])
                || !in_array($a['status'], array('pending', 'published'), true))
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_invalid_action');
            }
            $pending = BoredKnowledgeBase::loadRevision((int) $a['pending_revision'], $id);
            if (!$pending)
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_conflict');
            }
            if ($decision === 'approve' && !BoredKnowledgeBase::canManage()
                && ((int) $a['id_author'] === (int) $user_info['id']
                    || (int) $pending['id_editor'] === (int) $user_info['id']))
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_cannot_self_approve');
            }
        }
        // Import publication is permitted only while the source remains public.
        if (in_array($decision, array('approve', 'reject'), true) && (int) $a['source_topic'] > 0
            && (!BoredKnowledgeBase::publicSource((int) $a['source_topic'], (int) $a['source_msg'])
                || BoredKnowledgeBase::safeSourceLink((int) $a['source_topic'], (int) $a['source_msg']) === ''))
        {
            $smcFunc['db_transaction']('rollback');
            self::deny('kb_not_found');
        }
        $now = time();
        if (in_array($decision, array('approve', 'reject'), true)
            && (!isset($_POST['expected_revision']) || (int) $_POST['expected_revision'] !== (int) $a['pending_revision']))
        {
            $smcFunc['db_transaction']('rollback');
            self::deny('kb_conflict');
        }
        if ($decision === 'approve' && (int) $a['pending_revision'] > 0 && in_array($a['status'], array('pending', 'published'), true))
        {
            // Read the exact proposed revision under the article lock, then publish its category
            // and review interval together with the text (no pre-approval metadata leaks).
            if (!BoredKnowledgeBase::validCategory((int) $pending['id_category']))
            {
                $smcFunc['db_transaction']('rollback');
                self::deny('kb_invalid_category');
            }
            $days = (int) $pending['review_days'];
            $due = $days > 0 ? $now + $days * 86400 : 0;
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_articles SET current_revision = pending_revision, pending_revision = {int:zero},
                    pending_submitted = {int:zero}, id_category = {int:category}, review_days = {int:days},
                    status = {string:published}, published_at = CASE WHEN published_at = {int:zero} THEN {int:now} ELSE published_at END,
                    updated = {int:now}, last_reviewed = {int:now}, review_due = {int:due}
                WHERE id_article = {int:id}',
                array('zero' => 0, 'category' => (int) $pending['id_category'], 'days' => $days,
                    'published' => 'published', 'now' => $now, 'due' => $due, 'id' => $id));
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_revisions SET is_published = {int:yes} WHERE id_revision = {int:rev}
                    AND id_article = {int:id}', array('yes' => 1, 'rev' => (int) $pending['id_revision'], 'id' => $id));
        }
        elseif ($decision === 'reject' && (int) $a['pending_revision'] > 0 && in_array($a['status'], array('pending', 'published'), true))
        {
            if ((int) $a['current_revision'] > 0)
                $smcFunc['db_query']('', '
                    UPDATE {db_prefix}kb_articles SET pending_revision = {int:zero}, pending_submitted = {int:zero},
                        status = {string:published}
                    WHERE id_article = {int:id}', array('zero' => 0, 'published' => 'published', 'id' => $id));
            else
                $smcFunc['db_query']('', '
                    UPDATE {db_prefix}kb_articles SET status = {string:draft}, pending_submitted = {int:zero},
                        updated = {int:now}
                    WHERE id_article = {int:id}', array('draft' => 'draft', 'now' => $now, 'id' => $id));
        }
        elseif ($decision === 'markreviewed' && $a['status'] === 'published')
        {
            $due = (int) $a['review_days'] > 0 ? $now + (int) $a['review_days'] * 86400 : 0;
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_articles SET last_reviewed = {int:now}, review_due = {int:due} WHERE id_article = {int:id}',
                array('now' => $now, 'due' => $due, 'id' => $id));
        }
        elseif ($decision === 'archive')
            $smcFunc['db_query']('', 'UPDATE {db_prefix}kb_articles SET status = {string:status} WHERE id_article = {int:id}',
                array('status' => 'archived', 'id' => $id));
        elseif ($decision === 'restore' && $a['status'] === 'archived')
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_articles SET status = {string:pending},
                    pending_revision = CASE WHEN pending_revision = {int:zero} THEN current_revision ELSE pending_revision END,
                    pending_submitted = {int:yes}
                WHERE id_article = {int:id}', array('pending' => 'pending', 'zero' => 0, 'id' => $id));
        else
        {
            $smcFunc['db_transaction']('rollback');
            self::deny('kb_invalid_action');
        }
        $smcFunc['db_transaction']('commit');
        self::redirect($id);
    }

    public static function feedback()
    {
        global $smcFunc, $user_info;
        is_not_guest();
        checkSession('post');
        $id = self::id();
        $a = BoredKnowledgeBase::loadArticle($id);
        if (!BoredKnowledgeBase::setting('feedback_enabled', 1) || !$a || $a['status'] !== 'published'
            || !BoredKnowledgeBase::canView($a))
            self::deny();
        $vote = isset($_POST['vote']) && $_POST['vote'] === 'yes' ? 1 : (isset($_POST['vote']) && $_POST['vote'] === 'no' ? 0 : -1);
        if ($vote < 0)
            self::deny();
        $smcFunc['db_insert']('replace', '{db_prefix}kb_feedback',
            array('id_article' => 'int', 'id_member' => 'int', 'vote' => 'int', 'created' => 'int'),
            array($id, (int) $user_info['id'], $vote, time()), array('id_article', 'id_member'));
        self::redirect($id);
    }

    public static function history()
    {
        global $smcFunc, $context;
        $a = BoredKnowledgeBase::loadArticle(self::id());
        if (!$a || !(BoredKnowledgeBase::canEdit($a) || BoredKnowledgeBase::canReview()))
            self::deny();
        self::requireSourceAccess($a);
        $req = $smcFunc['db_query']('', '
            SELECT r.id_revision, r.title, r.edit_note, r.created,
                COALESCE(m.real_name, {string:former}) AS editor_name
            FROM {db_prefix}kb_revisions AS r
                LEFT JOIN {db_prefix}members AS m ON (m.id_member = r.id_editor)
            WHERE r.id_article = {int:article}
                AND (r.is_published = {int:yes} OR r.id_editor = {int:member} OR {int:admin} = {int:yes}
                    OR ({int:reviewer} = {int:yes} AND {int:submitted} = {int:yes}
                        AND r.id_revision = {int:pending}))
            ORDER BY r.id_revision DESC LIMIT {int:limit}',
            array('article' => (int) $a['id_article'], 'limit' => 100, 'former' => 'Former member',
                'yes' => 1, 'member' => (int) $GLOBALS['user_info']['id'],
                'admin' => BoredKnowledgeBase::canManage() ? 1 : 0,
                'reviewer' => BoredKnowledgeBase::canReview() ? 1 : 0,
                'submitted' => !empty($a['pending_submitted']) ? 1 : 0,
                'pending' => (int) $a['pending_revision']));
        $rows = array();
        while ($row = $smcFunc['db_fetch_assoc']($req))
            $rows[] = $row;
        $smcFunc['db_free_result']($req);
        $context['kb'] = array('article' => $a, 'items' => $rows);
        $context['sub_template'] = 'kb_history';
        $context['robot_no_index'] = true;
    }

    public static function revision()
    {
        global $context;
        $a = BoredKnowledgeBase::loadArticle(self::id());
        if (!$a || !(BoredKnowledgeBase::canEdit($a) || BoredKnowledgeBase::canReview()))
            self::deny();
        self::requireSourceAccess($a);
        $revision = BoredKnowledgeBase::loadRevision(isset($_GET['rev']) ? (int) $_GET['rev'] : 0, (int) $a['id_article']);
        if (!$revision || !BoredKnowledgeBase::canViewRevision($a, $revision))
            self::deny('kb_not_found');
        $context['kb'] = array('article' => $a, 'revision' => $revision, 'body' => BoredKnowledgeBase::renderBody($revision['body']));
        $context['sub_template'] = 'kb_revision';
        $context['robot_no_index'] = true;
    }

    public static function manage()
    {
        global $context, $smcFunc, $txt;
        if (!BoredKnowledgeBase::canManage())
            self::deny();
        $categories = BoredKnowledgeBase::categories(true);
        $req = $smcFunc['db_query']('', '
            SELECT a.id_article, a.status, a.review_due, r.title
            FROM {db_prefix}kb_articles AS a
                INNER JOIN {db_prefix}kb_revisions AS r ON (r.id_revision = a.current_revision)
            WHERE a.status = {string:published} AND a.review_due > {int:zero} AND a.review_due <= {int:now}
            ORDER BY a.review_due ASC LIMIT {int:limit}',
            array('published' => 'published', 'zero' => 0, 'now' => time(), 'limit' => 100));
        $due = array();
        while ($r = $smcFunc['db_fetch_assoc']($req))
            $due[] = $r;
        $smcFunc['db_free_result']($req);
        $context['kb'] = array('categories' => $categories, 'due' => $due);
        $context['sub_template'] = 'kb_manage';
        $context['robot_no_index'] = true;
    }

    public static function saveCategory()
    {
        global $smcFunc;
        if (!BoredKnowledgeBase::canManage())
            self::deny();
        checkSession('post');
        $id = self::id();
        $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
        $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $order = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        if ($name === '' || strlen($name) > 120 || preg_match('/[\r\n\x00-\x1f]/', $name) || strlen($description) > 1000)
            self::deny('kb_invalid_content');
        $order = max(0, min(99999, $order));
        if ($id)
        {
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}kb_categories SET name = {string:name}, description = {string:description},
                    enabled = {int:enabled}, sort_order = {int:sort} WHERE id_category = {int:id}',
                array('name' => $name, 'description' => $description, 'enabled' => $enabled, 'sort' => $order, 'id' => $id));
        }
        else
            $smcFunc['db_insert']('insert', '{db_prefix}kb_categories',
                array('name' => 'string-120', 'description' => 'string', 'enabled' => 'int', 'sort_order' => 'int'),
                array($name, $description, $enabled, $order), array('id_category'));
        self::redirect(0, ';sa=manage');
    }

    public static function saveSettings()
    {
        if (!BoredKnowledgeBase::canManage())
            self::deny();
        checkSession('post');
        $days = isset($_POST['review_days']) ? (int) $_POST['review_days'] : 180;
        updateSettings(array(
            'kb_enabled' => !empty($_POST['enabled']) ? 1 : 0,
            'kb_show_menu' => !empty($_POST['show_menu']) ? 1 : 0,
            'kb_feedback_enabled' => !empty($_POST['feedback']) ? 1 : 0,
            // Preserve the existing preference if Q&A is not installed.
            'kb_qa_integration' => BoredKnowledgeBase::qaAvailable()
                ? (!empty($_POST['qa_integration']) ? 1 : 0)
                : (int) BoredKnowledgeBase::setting('qa_integration', 1),
            'kb_review_days' => max(0, min(3650, $days)),
        ));
        self::redirect(0, ';sa=manage');
    }
}
