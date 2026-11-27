<?php
/**
 * Board Downloads 1.3.0 for SMF 2.1.x.
 * Turns ordinary topics and their existing approved attachments into a download catalogue.
 * Copyright (c) 2026 BoredSkull. ISC licence.
 */
if (!defined('SMF'))
    die('No direct access');

class BoredBoardDownloads
{
    const VERSION = '1.3.0';

    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function setting($name, $default = '')
    {
        global $modSettings;
        return isset($modSettings['bd_' . $name]) ? $modSettings['bd_' . $name] : $default;
    }

    public static function ids($value, $limit = 500)
    {
        $out = array();
        foreach ((array) (is_array($value) ? $value : explode(',', (string) $value)) as $part)
        {
            $id = (int) $part;
            if ($id > 0)
                $out[$id] = $id;
            if (count($out) >= $limit)
                break;
        }
        return array_values($out);
    }

    public static function extensions($input = null)
    {
        $value = $input === null ? self::setting('extensions', 'zip,7z,rar,tar,gz,pdf,txt,docx,odt,ods,xlsx,pptx,mp3,mp4') : $input;
        $out = array();
        foreach (explode(',', strtolower((string) $value)) as $part)
        {
            $part = trim(ltrim(trim($part), '.'));
            if (preg_match('/^[a-z0-9]{1,10}$/D', $part))
                $out[$part] = $part;
            if (count($out) >= 40)
                break;
        }
        return array_values($out);
    }

    /**
     * Show companion controls only when their source modification is present
     * and enabled. Do not include absent/disabled integrations in the form.
     * Checking both the registered setting and the installed source file avoids
     * misleading options after a partial uninstall or a disabled mod.
     */
    public static function availableIntegrations()
    {
        global $modSettings, $sourcedir;
        $checks = array(
            'tags' => array('tp_enabled', 'TopicTags.php'),
            'digest' => array('cd_enabled', 'CommunityDigest.php'),
            'insights' => array('ci_enabled', 'CommunityInsights.php'),
            'activity' => array('as_enabled', 'ActivityStream.php'),
            'knowledge' => array('kb_enabled', 'KnowledgeBase.php'),
            'events' => array('em_enabled', 'EventManager.php'),
        );
        $available = array();
        foreach ($checks as $key => $check)
            if (!empty($modSettings[$check[0]]) && is_file($sourcedir . '/' . $check[1]))
                $available[$key] = true;
        return $available;
    }

    /** Native SMF general permission, configurable per membergroup, including guests. */
    public static function canView()
    {
        return allowedTo('admin_forum') || allowedTo('bd_view');
    }

    public static function traditional()
    {
        return self::setting('mode', 'board') === 'traditional';
    }

    public static function hooksPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
    {
        loadLanguage('BoardDownloads');
        if (!isset($permissionGroups['membergroup']) || !is_array($permissionGroups['membergroup']))
            $permissionGroups['membergroup'] = array();
        if (!in_array('boarddownloads', $permissionGroups['membergroup'], true))
            $permissionGroups['membergroup'][] = 'boarddownloads';
        $permissionList['membergroup']['bd_view'] = array(false, 'boarddownloads', 'boarddownloads');
        $permissionList['membergroup']['bd_submit'] = array(false, 'boarddownloads', 'boarddownloads');
    }

    public static function hooksIllegalGuestPermissions()
    {
        global $context;
        if (!in_array('bd_submit', $context['non_guest_permissions'], true))
            $context['non_guest_permissions'][] = 'bd_submit';
    }

    public static function hooksCredits()
    {
        global $context, $txt;
        loadLanguage('BoardDownloads');
        $context['copyrights']['mods'][] = sprintf($txt['bd_credits'], $txt['by'] ?? 'by');
    }

    // Explicit SQL escape character keeps %, _, ! and backslashes literal on both engines.
    public static function searchPattern($value)
    {
        return '%' . strtr($value, array('!' => '!!', '%' => '!%', '_' => '!_')) . '%';
    }

    public static function cut($value, $length)
    {
        global $smcFunc;
        return $smcFunc['substr']($value, 0, $length);
    }

    public static function boardTopicId()
    {
        // Support old bookmarked catalogue links; all new links use id.
        $value = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['topic']) ? $_GET['topic'] : 0);
        return is_scalar($value) && ctype_digit((string) $value) ? (int) $value : 0;
    }

    public static function allowedBoards()
    {
        // An empty configuration deliberately publishes nothing.
        $configured = self::ids(self::setting('boards'), 200);
        if (!$configured)
            return array();
        // view_attachments is an SMF general permission, not a per-board
        // visibility test. boardsAllowedTo() can return no board IDs for
        // guests even when the source board is publicly readable.
        // Every catalogue SQL query applies {query_see_board}; SMF's
        // dlattach handler independently enforces attachment permissions.
        return $configured;
    }

    /** A club board is supported as an ordinary SMF source board, never as a permission bypass. */
    public static function tagsForTopics($ids)
    {
        global $modSettings, $sourcedir;
        if (!self::setting('tags', 1) || empty($modSettings['tp_enabled']) || !is_file($sourcedir . '/TopicTags.php'))
            return array();
        require_once($sourcedir . '/TopicTags.php');
        if (!class_exists('BoredTopicTags', false) || !is_callable(array('BoredTopicTags', 'topicLabels')))
            return array();
        return BoredTopicTags::topicLabels(self::ids($ids, 100));
    }

    /** Community Insights 1.1.x: aggregates only; no private topic names or downloads. */
    public static function insightsProvider(&$providers, $dates)
    {
        if (self::traditional()) return;
        if (!self::setting('enabled', 1) || !self::setting('insights', 1) || !allowedTo('admin_forum'))
            return;
        $boards = self::ids(self::setting('boards'), 200);
        if (!$boards || !self::extensions())
            return;
        $params = self::boardConditions($boards);
        $params['start'] = isset($dates['start']) ? (int) $dates['start'] : 0;
        $params['end'] = isset($dates['end']) ? (int) $dates['end'] : time();
        // SMF core permission clause is still applied even to administrator metrics.
        $rows = self::rows(self::query('SELECT COUNT(*) AS total
            FROM {db_prefix}topics AS t
              INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
              INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE t.id_board IN ({array_int:boards}) AND {query_see_board}
              AND t.approved = {int:approved} AND fm.approved = {int:approved}
              AND fm.poster_time BETWEEN {int:start} AND {int:end}
              AND ' . self::fileExistsSql(), $params));
        global $txt;
        loadLanguage('BoardDownloads');
        $providers[] = array('id' => 'boarddownloads', 'title' => $txt['bd_admin_title'],
            'permission' => 'admin_forum',
            'items' => array($txt['bd_insights_new_topics'] => $rows ? (int) $rows[0]['total'] : 0));
    }

    /** Community Digest 1.1.x builder: uses its per-recipient approved, visible topic pool. */
    public static function digestSections(&$builders, &$ctx)
    {
        if (self::traditional()) return;
        if (self::setting('enabled', 1) && self::setting('digest', 1))
            $builders['boarddownloads'] = array(__CLASS__, 'digestSection');
    }

    public static function digestSection(array &$ctx)
    {
        if (self::traditional()) return array();
        if (empty($ctx['pool']) || empty($ctx['boards']) || empty($ctx['member'])
            || !class_exists('CommunityDigestEngine', false)
            || !CommunityDigestEngine::generalPermission($ctx['member'], 'bd_view'))
            return array();
        $boards = array_values(array_intersect(self::ids(self::setting('boards'), 200), self::ids($ctx['boards'], 2000)));
        if (!$boards || !self::extensions())
            return array();
        $candidates = array();
        foreach ($ctx['pool'] as $id => $topic)
            if (isset($topic['id_board']) && in_array((int) $topic['id_board'], $boards, true))
                $candidates[(int) $id] = $topic;
        if (!$candidates)
            return array();
        $params = self::boardConditions($boards, array('topics' => array_keys($candidates)));
        $valid = self::rows(self::query('SELECT t.id_topic
            FROM {db_prefix}topics AS t
              INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
              INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE t.id_topic IN ({array_int:topics}) AND t.id_board IN ({array_int:boards})
              AND t.approved = {int:approved} AND fm.approved = {int:approved}
              AND ' . self::fileExistsSql(), $params));
        global $txt;
        loadLanguage('BoardDownloads');
        $wanted = array();
        foreach ($valid as $row)
        {
            $id = (int) $row['id_topic'];
            if (!isset($candidates[$id]))
                continue;
            $t = $candidates[$id];
            $wanted[$id] = array('topic' => $t,
                'score' => isset($t['last_time']) ? (float) $t['last_time'] : 0,
                'reason' => $txt['bd_digest_reason'], 'badge' => $txt['bd_download']);
        }
        return $wanted ? array('title' => $txt['bd_new_downloads'],
            'items' => CommunityDigestEngine::take($ctx, $wanted)) : array();
    }


    /** Activity Stream 1.1.x. Only visible approved topics with a downloadable file.
     * Cursor is interpreted conservatively and never broadens access. */
    public static function activityTypes(&$types)
    {
        if (self::traditional()) return;
        if (self::setting('enabled', 1) && self::setting('activity', 1) && self::canView())
        {
            global $txt;
            loadLanguage('BoardDownloads');
            $types['boarddownload'] = $txt['bd_new_downloads'];
        }
    }

    public static function activityFetch(&$items, $config, $before, $limit)
    {
        if (self::traditional()) return;
        global $scripturl, $txt;
        loadLanguage('BoardDownloads');
        if (!self::setting('enabled', 1) || !self::setting('activity', 1) || !self::canView())
            return;
        $boards = self::allowedBoards();
        if (!$boards || !self::extensions())
            return;
        $max = max(1, min(30, (int) $limit));
        $cutoff = time() + 1;
        if (is_numeric($before) && (int) $before > 0)
            $cutoff = (int) $before;
        elseif (is_array($before) && isset($before['time']) && is_numeric($before['time']))
            $cutoff = (int) $before['time'];
        // Respect the companion stream's chosen activity types, when supplied.
        if (is_array($config) && !empty($config['types']) && is_array($config['types'])
            && !in_array('boarddownload', $config['types'], true))
            return;
        $params = self::boardConditions($boards, array('cutoff' => $cutoff, 'max_rows' => $max));
        $rows = self::rows(self::query('SELECT t.id_topic, fm.id_member, fm.subject, fm.poster_time
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE t.id_board IN ({array_int:boards}) AND {query_see_board}
                AND t.approved = {int:approved} AND fm.approved = {int:approved}
                AND fm.poster_time < {int:cutoff} AND ' . self::fileExistsSql() . '
            ORDER BY fm.poster_time DESC, t.id_topic DESC LIMIT {int:max_rows}', $params));
        foreach ($rows as $row)
        {
            $id = (int) $row['id_topic'];
            $subject = $row['subject'];
            censorText($subject);
            $items[] = array('type' => 'boarddownload', 'time' => (int) $row['poster_time'],
                'key' => 'bd' . $id, 'actor' => (int) $row['id_member'],
                'html' => self::e($txt['bd_shared_download']) . self::e($subject),
                'snippet' => '', 'url' => $scripturl . '?action=boarddownloads;sa=topic;id=' . $id);
        }
    }

    /** Published KB articles from the same source topic and visible calendar events. */
    public static function related($id)
    {
        global $sourcedir, $modSettings, $scripturl, $txt;
        loadLanguage('BoardDownloads');
        $result = array();
        if (self::setting('knowledge', 1) && !empty($modSettings['kb_enabled'])
            && is_file($sourcedir . '/KnowledgeBase.php'))
        {
            require_once($sourcedir . '/KnowledgeBase.php');
            if (class_exists('BoredKnowledgeBase', false) && is_callable(array('BoredKnowledgeBase', 'loadArticle')))
            {
                $articles = self::rows(self::query('SELECT a.id_article
                    FROM {db_prefix}kb_articles AS a
                    WHERE a.source_topic = {int:topic} AND a.status = {string:published}
                    ORDER BY a.updated DESC LIMIT 8',
                    array('topic' => (int) $id, 'published' => 'published')));
                foreach ($articles as $article)
                {
                    $a = BoredKnowledgeBase::loadArticle((int) $article['id_article']);
                    if (!$a || !BoredKnowledgeBase::canView($a))
                        continue;
                    $result[] = array('text' => $txt['bd_kb_article'] . (int) $a['id_article'],
                        'url' => $scripturl . '?action=knowledgebase;sa=article;id=' . (int) $a['id_article']);
                }
            }
        }
        if (self::setting('events', 1) && !empty($modSettings['em_enabled'])
            && is_file($sourcedir . '/EventManager.php'))
        {
            require_once($sourcedir . '/EventManager.php');
            if (class_exists('EM', false) && EM::enabled())
            {
                $events = self::rows(self::query('SELECT cal.id_event
                    FROM {db_prefix}calendar AS cal
                    WHERE cal.id_topic = {int:topic} ORDER BY cal.start_date DESC LIMIT 8',
                    array('topic' => (int) $id)));
                foreach ($events as $row)
                {
                    $event = EM::load((int) $row['id_event']);
                    if (!$event || !$event['enabled'] || !EM::canSee($event))
                        continue;
                    $result[] = array('text' => $txt['bd_event'] . $event['title'],
                        'url' => $scripturl . '?action=events;sa=view;event=' . (int) $event['id']);
                }
            }
        }
        // External extensions (including chat integrations) can add safe related URLs.
        // Consumers must check permissions and return only text/url pairs.
        call_integration_hook('integrate_boarddownloads_related', array(&$result, (int) $id));
        return $result;
    }

    /** A stable read-only integration point for future BoredSkull companion mods. */
    public static function catalogueInfo($topicId)
    {
        if (self::traditional()) return null;
        if (!self::canView())
            return null;
        $topic = self::loadTopic((int) $topicId);
        if (!$topic || !self::setting('enabled', 1) || !self::canView())
            return null;
        $files = self::attachments(array((int) $topicId), false);
        $choices = self::choices(array((int) $topicId));
        $visible = self::visibleFiles(isset($files[$topicId]) ? $files[$topicId] : array(),
            isset($choices[$topicId]) ? $choices[$topicId] : null);
        if (!$visible['files'])
            return null;
        censorText($topic['subject']);
        return array('topic' => (int) $topicId,
            'title' => $topic['subject'], 'url' => self::baseUrl() . ';sa=topic;id=' . (int) $topicId,
            'files' => count($visible['files']));
    }

    /** Viewer-scoped traditional catalogue descriptor for optional ecosystem consumers. */
    public static function traditionalInfo($entryId)
    {
        if (!self::traditional() || !self::setting('enabled', 1) || !self::canView()) return null;
        require_once(__DIR__ . '/BoardDownloadsTraditional.php');
        return BoredBDTraditional::catalogueInfo((int) $entryId);
    }

    public static function hooksActions(&$actions)
    {
        $actions['boarddownloads'] = array('BoardDownloads.php', 'BoredBoardDownloads::dispatch');
    }

    public static function hooksMenu(&$buttons)
    {
        global $scripturl, $txt;
        if (!self::setting('enabled', 1) || !self::setting('show_menu', 1) || !self::canView())
            return;
        loadLanguage('BoardDownloads');
        $new = array('boarddownloads' => array(
            'title' => $txt['bd_title'],
            'href' => $scripturl . '?action=boarddownloads',
            'show' => true,
        ));
        $keys = array_keys($buttons);
        $index = array_search('search', $keys, true);
        $buttons = $index === false ? $buttons + $new :
            array_slice($buttons, 0, $index + 1, true) + $new + array_slice($buttons, $index + 1, null, true);
    }

    public static function hooksAdmin(&$areas)
    {
        global $txt;
        loadLanguage('BoardDownloads');
        $areas['config']['areas']['modsettings']['subsections']['boarddownloads'] = array($txt['bd_admin_title']);
    }

    public static function hooksModifications(&$subActions)
    {
        $subActions['boarddownloads'] = 'bored_bd_modsettings';
    }

    public static function hooksTheme()
    {
        if (($_REQUEST['action'] ?? '') !== 'boarddownloads' && !(($_REQUEST['action'] ?? '') === 'admin' && ($_REQUEST['area'] ?? '') === 'modsettings' && ($_REQUEST['sa'] ?? '') === 'boarddownloads')) return;
        loadCSSFile('board_downloads.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_boarddownloads');
    }

    public static function startPage($template, $title)
    {
        global $context;
        loadLanguage('BoardDownloads');
        loadTemplate('BoardDownloads');
        $context['sub_template'] = $template;
        $context['page_title'] = $title;
        $context['linktree'][] = array('url' => self::baseUrl(), 'name' => $title);
    }

    public static function baseUrl()
    {
        global $scripturl;
        return $scripturl . '?action=boarddownloads';
    }

    public static function dispatch()
    {
        loadLanguage('BoardDownloads');
        $sa = isset($_REQUEST['sa']) && is_string($_REQUEST['sa']) ? $_REQUEST['sa'] : '';
        if ($sa === 'settings')
        {
            isAllowedTo('admin_forum');
            redirectexit('action=admin;area=modsettings;sa=boarddownloads');
            return;
        }
        if (!self::setting('enabled', 1))
            fatal_lang_error('bd_disabled', false);
        if (!self::canView())
            isAllowedTo('bd_view');
        if (self::traditional())
        {
            require_once(__DIR__ . '/BoardDownloadsTraditional.php');
            BoredBDTraditional::dispatch($sa);
            return;
        }
        if ($sa === 'manage') self::manage();
        elseif ($sa === 'topic') self::topic();
        elseif ($sa === '') self::browse();
        else fatal_lang_error('no_access', false);
    }

    public static function query($sql, $params = array())
    {
        global $smcFunc;
        return $smcFunc['db_query']('', $sql, $params);
    }

    public static function rows($request)
    {
        global $smcFunc;
        $rows = array();
        while ($row = $smcFunc['db_fetch_assoc']($request))
            $rows[] = $row;
        $smcFunc['db_free_result']($request);
        return $rows;
    }

    public static function boardList()
    {
        return self::rows(self::query('
            SELECT b.id_board, b.name, b.child_level, b.redirect
            FROM {db_prefix}boards AS b
            ORDER BY b.board_order, b.id_board'));
    }

    /** SQL clause is only appended for the allowed board IDs, never a user-supplied identifier. */
    public static function boardConditions($boards, $params = array())
    {
        $params['boards'] = $boards;
        $params['approved'] = 1;
        $params['normal_attach'] = 0;
        $params['extensions'] = self::extensions();
        return $params;
    }

    /** Only approved files from approved posts inside approved, visible topics. */
    public static function fileExistsSql()
    {
        return 'EXISTS (
            SELECT 1 FROM {db_prefix}messages AS fm
                INNER JOIN {db_prefix}attachments AS fa ON (fa.id_msg = fm.id_msg)
            WHERE fm.id_topic = t.id_topic AND fm.approved = {int:approved}
                AND fa.approved = {int:approved} AND fa.attachment_type = {int:normal_attach}
                AND fa.fileext IN ({array_string:extensions})
        )';
    }

    public static function browse()
    {
        global $context, $smcFunc, $txt;
        if (!self::canView())
            isAllowedTo('bd_view');
        self::startPage('bd_browse', $txt['bd_title']);
        $boards = self::allowedBoards();
        $context['bd_boards'] = array();
        $context['bd_topics'] = array();
        $context['bd_board_filter'] = 0;
        $context['bd_search'] = '';
        $context['bd_page'] = 0;
        $context['bd_pages'] = 0;
        $context['bd_sort'] = 'recent';
        $context['bd_no_boards'] = !$boards;
        $context['bd_admin'] = allowedTo('admin_forum');
        if (!$boards || !self::extensions())
            return;
        $names = self::rows(self::query('
            SELECT b.id_board, b.name FROM {db_prefix}boards AS b
            WHERE b.id_board IN ({array_int:boards}) AND {query_see_board}
            ORDER BY b.board_order', array('boards' => $boards)));
        $visible = array();
        foreach ($names as $name)
        {
            $context['bd_boards'][] = $name;
            $visible[] = (int) $name['id_board'];
        }
        if (!$visible)
        {
            $context['bd_no_boards'] = true;
            return;
        }
        $selected = !empty($_GET['board_id']) ? (int) $_GET['board_id'] : 0;
        $context['bd_board_filter'] = in_array($selected, $visible, true) ? $selected : 0;
        $boards = $context['bd_board_filter'] ? array($context['bd_board_filter']) : $visible;
        $search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
        $search = self::cut($search, 80);
        $context['bd_search'] = $search;
        $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'recent';
        $order = array('recent' => 'fm.poster_time DESC', 'popular' => 't.num_views DESC, lm.poster_time DESC',
            'title' => 'fm.subject ASC', 'updated' => 'lm.poster_time DESC');
        $context['bd_sort'] = isset($order[$sort]) ? $sort : 'recent';
        $filter = $search === '' ? '' : ' AND fm.subject LIKE {string:search} ESCAPE {string:like_escape}';
        $params = self::boardConditions($boards, array('search' => self::searchPattern($search), 'like_escape' => '!'));
        $base = '
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
                INNER JOIN {db_prefix}messages AS lm ON (lm.id_msg = t.id_last_msg)
            WHERE t.id_board IN ({array_int:boards}) AND {query_see_board}
                AND t.approved = {int:approved} AND fm.approved = {int:approved}
                AND ' . self::fileExistsSql() . $filter;
        $count = self::rows(self::query('SELECT COUNT(*) AS total ' . $base, $params));
        $total = $count ? (int) $count[0]['total'] : 0;
        $limit = max(5, min(50, (int) self::setting('page_size', 15)));
        $pages = (int) ceil($total / $limit);
        $page = isset($_GET['page']) ? max(0, min(100000, (int) $_GET['page'])) : 0;
        $page = $pages ? min($page, $pages - 1) : 0;
        $context['bd_page'] = $page;
        $context['bd_pages'] = $pages;
        $context['bd_total'] = $total;
        if (!$total)
            return;
        $params['offset'] = $page * $limit;
        $params['page_size'] = $limit;
        $topics = self::rows(self::query('
            SELECT t.id_topic, t.id_board, t.num_views, t.num_replies, t.id_first_msg,
                fm.subject, fm.body, fm.smileys_enabled, fm.poster_time,
                fm.poster_name, lm.poster_time AS updated_time, b.name AS board_name
            ' . $base . '
            ORDER BY ' . $order[$context['bd_sort']] . '
            LIMIT {int:page_size} OFFSET {int:offset}', $params));
        $ids = array();
        foreach ($topics as $topic)
            $ids[] = (int) $topic['id_topic'];
        $files = self::attachments($ids, true);
        $choices = self::choices($ids);
        $labels = self::tagsForTopics($ids);
        foreach ($topics as $topic)
        {
            $tid = (int) $topic['id_topic'];
            $available = isset($files[$tid]) ? $files[$tid] : array();
            $chosen = self::visibleFiles($available, isset($choices[$tid]) ? $choices[$tid] : null);
            $topic['preview'] = null;
            foreach ($available as $file)
                if (!empty($file['is_preview']))
                {
                    $topic['preview'] = $file;
                    break;
                }
            $topic['labels'] = isset($labels[$tid]) ? $labels[$tid] : array();
            $topic['files'] = $chosen['files'];
        $topic['primary'] = $chosen['primary'];
            $topic['summary'] = self::excerpt($topic['body'], 225);
            censorText($topic['summary']);
            censorText($topic['subject']);
            $context['bd_topics'][] = $topic;
        }
    }

    public static function excerpt($bbcode, $max)
    {
        $text = preg_replace('~\[(?:/?[a-z][a-z0-9]*)(?:=[^\]]*)?\]~i', ' ', (string) $bbcode);
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return self::cut($text, $max);
    }

    /** Loads attached files only through authorised, approved topic and message rows. */
    public static function attachments($topicIds, $includeImages = true)
    {
        $topicIds = self::ids($topicIds, 100);
        if (!$topicIds)
            return array();
        $boards = self::allowedBoards();
        if (!$boards)
            return array();
        $params = array('topics' => $topicIds, 'boards' => $boards, 'approved' => 1, 'normal_attach' => 0);
        $files = self::rows(self::query('
            SELECT m.id_topic, a.id_attach, a.filename, a.fileext, a.size, a.mime_type,
                m.poster_time, m.id_msg
            FROM {db_prefix}attachments AS a
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE t.id_topic IN ({array_int:topics}) AND t.id_board IN ({array_int:boards})
                AND {query_see_board} AND t.approved = {int:approved} AND m.approved = {int:approved}
                AND a.approved = {int:approved} AND a.attachment_type = {int:normal_attach}
            ORDER BY m.poster_time DESC, a.id_attach DESC', $params));
        $allowed = self::extensions();
        $all = array();
        foreach ($files as $file)
        {
            $extension = strtolower((string) $file['fileext']);
            $isPreview = in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)
                && strpos(strtolower((string) $file['mime_type']), 'image/') === 0;
            $file['is_preview'] = $isPreview;
            $file['is_download'] = in_array($extension, $allowed, true);
            if (!$includeImages && !$file['is_download'])
                continue;
            $all[(int) $file['id_topic']][(int) $file['id_attach']] = $file;
        }
        return $all;
    }

    public static function choices($topicIds)
    {
        $topicIds = self::ids($topicIds, 100);
        if (!$topicIds)
            return array();
        $rows = self::rows(self::query('
            SELECT id_topic, id_primary, selected_ids FROM {db_prefix}bd_topic_files
            WHERE id_topic IN ({array_int:topics})', array('topics' => $topicIds)));
        $out = array();
        foreach ($rows as $row)
            $out[(int) $row['id_topic']] = $row;
        return $out;
    }

    /** Prevent stale attachment IDs or hidden files from being rendered. */
    public static function visibleFiles($available, $selection)
    {
        $files = array();
        if ($selection !== null)
        {
            foreach (self::ids($selection['selected_ids'], 200) as $id)
                if (isset($available[$id]) && !empty($available[$id]['is_download']))
                    $files[$id] = $available[$id];
        }
        else
        {
            foreach ($available as $id => $file)
                if (!empty($file['is_download']))
                    $files[$id] = $file;
        }
        $primaryId = $selection === null ? 0 : (int) $selection['id_primary'];
        if (!$primaryId || !isset($files[$primaryId]))
            $primaryId = $files ? (int) key($files) : 0;
        return array('files' => $files, 'primary' => $primaryId ? $files[$primaryId] : null);
    }

    public static function loadTopic($id)
    {
        $boards = self::allowedBoards();
        if ($id <= 0 || !$boards)
            return null;
        $rows = self::rows(self::query('
            SELECT t.id_topic, t.id_board, t.id_first_msg, t.num_replies, t.num_views,
                fm.subject, fm.body, fm.smileys_enabled, fm.poster_name, fm.poster_time,
                b.name AS board_name, lm.poster_time AS updated_time
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
                INNER JOIN {db_prefix}messages AS lm ON (lm.id_msg = t.id_last_msg)
            WHERE t.id_topic = {int:topic} AND t.id_board IN ({array_int:boards})
                AND {query_see_board} AND t.approved = {int:approved}
                AND fm.approved = {int:approved}
            LIMIT 1', array('topic' => $id, 'boards' => $boards, 'approved' => 1)));
        return $rows ? $rows[0] : null;
    }

    public static function topic()
    {
        global $context, $scripturl;
        if (!self::canView())
            isAllowedTo('bd_view');
        $id = self::boardTopicId();
        $topic = self::loadTopic($id);
        if (!$topic)
            fatal_lang_error('topic_gone', false);
        $files = self::attachments(array($id), true);
        $available = isset($files[$id]) ? $files[$id] : array();
        $choice = self::choices(array($id));
        $chosen = self::visibleFiles($available, isset($choice[$id]) ? $choice[$id] : null);
        if (!$chosen['files'] && !allowedTo('admin_forum'))
            fatal_lang_error('topic_gone', false);
        $labels = self::tagsForTopics(array($id));
        $topic['labels'] = isset($labels[$id]) ? $labels[$id] : array();
        $topic['related'] = self::related($id);
        $topic['primary'] = $chosen['primary'];
        $topic['files'] = $chosen['files'];
        $topic['screenshots'] = array();
        foreach ($available as $file)
            if (!empty($file['is_preview']) && count($topic['screenshots']) < 6)
                $topic['screenshots'][] = $file;
        censorText($topic['body']);
        $topic['body_html'] = parse_bbc($topic['body'], !empty($topic['smileys_enabled']), (int) $topic['id_first_msg']);
        censorText($topic['subject']);
        self::startPage('bd_topic', $topic['subject']);
        $context['bd_topic'] = $topic;
        $context['bd_admin'] = allowedTo('admin_forum');
    }

    public static function manage()
    {
        global $context, $scripturl, $smcFunc, $user_info, $txt;
        isAllowedTo('admin_forum');
        if (!self::canView())
            isAllowedTo('bd_view');
        $id = self::boardTopicId();
        $topic = self::loadTopic($id);
        if (!$topic)
            fatal_lang_error('topic_gone', false);
        $all = self::attachments(array($id), true);
        $available = isset($all[$id]) ? $all[$id] : array();
        $choices = self::choices(array($id));
        $existing = isset($choices[$id]) ? $choices[$id] : null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkSession('post');
            $selected = isset($_POST['files']) && is_array($_POST['files']) ? self::ids($_POST['files'], 200) : array();
            $filtered = array();
            foreach ($selected as $fileId)
                if (isset($available[$fileId]) && !empty($available[$fileId]['is_download']))
                    $filtered[] = $fileId;
            if (!$filtered)
                fatal_lang_error('bd_choose_file', false);
            $primary = isset($_POST['primary']) ? (int) $_POST['primary'] : 0;
            if (!in_array($primary, $filtered, true))
                $primary = $filtered[0];
            $smcFunc['db_insert']('replace', '{db_prefix}bd_topic_files',
                array('id_topic'=>'int','id_primary'=>'int','selected_ids'=>'string','updated_by'=>'int','updated_at'=>'int'),
                array($id, $primary, implode(',', $filtered), (int) $user_info['id'], time()), array('id_topic'));
            redirectexit('action=boarddownloads;sa=topic;id=' . $id);
        }
        self::startPage('bd_manage', $txt['bd_choose_title']);
        censorText($topic['subject']);
        $context['bd_manage_topic'] = $topic;
        $context['bd_manage_files'] = $available;
        $context['bd_manage_choice'] = self::visibleFiles($available, $existing);
    }

    public static function settings()
    {
        global $context, $txt;
        loadLanguage('BoardDownloads');
        require_once(__DIR__ . '/BoardDownloadsTraditional.php');
        isAllowedTo('admin_forum');
        $integrations = self::traditional() ? array() : self::availableIntegrations();
        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkSession('post');
            $known = array();
            foreach (self::boardList() as $board)
                if (empty($board['redirect']))
                    $known[(int) $board['id_board']] = true;
            $selected = array();
            foreach (self::ids(isset($_POST['boards']) && is_array($_POST['boards']) ? $_POST['boards'] : array(), 200) as $id)
                if (isset($known[$id]))
                    $selected[] = $id;
            $extensions = self::extensions(isset($_POST['extensions']) && is_string($_POST['extensions']) ? $_POST['extensions'] : '');
            if (!$extensions)
                fatal_lang_error('bd_extensions_required', false);
            $updates = array(
                'bd_mode' => isset($_POST['mode']) && $_POST['mode'] === 'traditional' ? 'traditional' : 'board',
                'bd_storage' => isset($_POST['storage']) && is_string($_POST['storage']) ? substr(trim($_POST['storage']), 0, 1000) : self::setting('storage', ''),
                'bd_max_mb' => max(1, min(2048, (int) (isset($_POST['max_mb']) ? $_POST['max_mb'] : 100))),
                'bd_enabled' => empty($_POST['enabled']) ? 0 : 1,
                'bd_show_menu' => empty($_POST['show_menu']) ? 0 : 1,
                'bd_boards' => implode(',', $selected),
                'bd_extensions' => implode(',', $extensions),
                'bd_page_size' => max(5, min(50, (int) (isset($_POST['page_size']) ? $_POST['page_size'] : 15))),
            );
            // Leave stored options untouched when the companion mod is absent.
            foreach ($integrations as $name => $present)
                $updates['bd_' . $name] = empty($_POST[$name]) ? 0 : 1;
            // Reject an unsafe path without discarding unrelated settings. A new
            // switch to Traditional mode requires valid retained or new storage.
            $invalidStorage = ($updates['bd_mode'] === 'traditional' || $updates['bd_storage'] !== '')
                && !BoredBDTraditional::storage(false, $updates['bd_storage']);
            if ($invalidStorage)
            {
                $updates['bd_storage'] = self::setting('storage', '');
                if ($updates['bd_mode'] === 'traditional' && !self::traditional()
                    && !BoredBDTraditional::storage(false, $updates['bd_storage']))
                    $updates['bd_mode'] = 'board';
                $_SESSION['bd_storage_rejected'] = true;
            }
            else
                unset($_SESSION['bd_storage_rejected']);
            updateSettings($updates);
            redirectexit('action=admin;area=modsettings;sa=boarddownloads');
        }
        self::startPage('bd_settings', $txt['bd_settings']);
        $context['bd_storage_rejected'] = !empty($_SESSION['bd_storage_rejected']);
        unset($_SESSION['bd_storage_rejected']);
        $context['bd_storage_warning'] = (self::traditional() || self::setting('storage', '') !== '') && !BoredBDTraditional::storage(false);
        $context['bd_configured_boards'] = self::ids(self::setting('boards'));
        $context['bd_all_boards'] = self::boardList();
        $context['bd_integrations_available'] = $integrations;
        $context['bd_settings'] = array(
            'mode' => self::traditional() ? 'traditional' : 'board',
            'storage' => self::setting('storage', ''),
            'max_mb' => (int) self::setting('max_mb', 100),
            'enabled' => (bool) self::setting('enabled', 1),
            'show_menu' => (bool) self::setting('show_menu', 1),
            'tags' => (bool) self::setting('tags', 1),
            'digest' => (bool) self::setting('digest', 1),
            'insights' => (bool) self::setting('insights', 1),
            'activity' => (bool) self::setting('activity', 1),
            'knowledge' => (bool) self::setting('knowledge', 1),
            'events' => (bool) self::setting('events', 1),
            'extensions' => implode(',', self::extensions()),
            'page_size' => (int) self::setting('page_size', 15),
        );
    }
}

// SMF 2.1 Modification Settings dispatch expects a named function.
function bored_bd_modsettings() { BoredBoardDownloads::settings(); }
