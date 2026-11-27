<?php
/**
 * Topic Tags & Prefixes - SMF 2.1.x
 * (c) 2026 BoredSkull. ISC License.
 * @version 1.0.2
 */
if (!defined('SMF'))
    die('No direct access');

class BoredTopicTags
{
    const VERSION = '1.0.2';
    private static $booted = false;
    private static $topicCache = array();
    private static $prefixCache = null;

    public static function boot()
    {
        global $sourcedir;
        if (self::$booted)
            return;
        self::$booted = true;
        loadLanguage('TopicTags');
    }

    public static function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function setting($name, $fallback = 1)
    {
        global $modSettings;
        return isset($modSettings[$name]) ? $modSettings[$name] : $fallback;
    }

    public static function cleanName($name, $max = 32)
    {
        $name = preg_replace('~[\x00-\x1f\x7f<>\\[\\]]~u', '', trim((string) $name));
        $name = preg_replace('~\s+~u', ' ', $name);
        if (function_exists('mb_substr'))
            return mb_substr($name, 0, $max, 'UTF-8');
        return substr($name, 0, $max);
    }

    public static function slug($name)
    {
        $text = self::cleanName($name, 32);
        if (function_exists('iconv'))
        {
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($trans !== false)
                $text = $trans;
        }
        $text = strtolower($text);
        $text = trim(preg_replace('~[^a-z0-9]+~', '-', $text), '-');
        if ($text === '')
            return 'tag-' . substr(sha1($name), 0, 12);
        // Preserve distinct technical tags such as C++, C# and C.
        if (preg_match('~[^\p{L}\p{N}\s-]~u', $name))
            $text .= '-' . substr(sha1(strtolower($name)), 0, 7);
        return substr($text, 0, 56);
    }

    public static function tagNames($raw)
    {
        $max = max(1, min(12, (int) self::setting('tp_max_tags', 5)));
        $out = array();
        if (!is_scalar($raw)) return $out;
        foreach (explode(',', (string) $raw) as $input)
        {
            $name = self::cleanName($input, 30);
            if ($name === '')
                continue;
            $slug = self::slug($name);
            $out[$slug] = $name;
            if (count($out) >= $max)
                break;
        }
        return $out;
    }

    public static function hookActions(&$actions)
    {
        $actions['topiclabels'] = array('TopicTags.php', 'tp_dispatch');
    }


    public static function hookAdmin(&$areas)
    {
        global $txt;
        self::boot();
        $areas['config']['areas']['topiclabels'] = array(
            'label' => $txt['tp_title'],
            'file' => 'TopicTags.php',
            'function' => 'tp_admin',
            'icon' => 'features',
            'permission' => array('admin_forum'),
        );
    }

    public static function hookPostForm()
    {
        global $context, $sourcedir, $scripturl, $user_info;
        if (!self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)))
            return;
        self::boot();
        $topic = !empty($_REQUEST['topic']) ? (int) $_REQUEST['topic'] : 0;
        $board = !empty($_REQUEST['board']) ? (int) $_REQUEST['board'] : (!empty($context['current_board']) ? (int) $context['current_board'] : 0);
        if (!$board && $topic)
        {
            $meta = self::topicOwner($topic);
            $board = $meta ? (int) $meta['id_board'] : 0;
        }
        if (!$board)
            return;
        if ($topic)
        {
            if (!self::canEdit($topic))
                return;
            $edit = $scripturl . '?action=topiclabels;sa=edit;topic=' . $topic;
            $context['tp_post_link'] = $edit;
            // Updating an existing topic uses the dedicated editor, which does not
            // risk changing metadata when the SMF post edit fails validation.
            return;
        }
        if (!allowedTo('post_new', $board))
            return;
        $prefixes = self::prefixesForBoard($board);
        $choice = array();
        foreach ($prefixes as $p)
            $choice[] = array('id' => (int) $p['id_prefix'], 'name' => $p['name'], 'color' => $p['color']);
        $data = array(
            'prefixes' => $choice,
            'maxTags' => max(1, min(12, (int) self::setting('tp_max_tags', 5))),
            'tagsEnabled' => (bool) self::setting('tp_tags_enabled', 1),
            'prefixEnabled' => (bool) self::setting('tp_prefixes_enabled', 1),
            'label' => 'Topic labels',
            'tagsLabel' => 'Tags (comma-separated)',
            'prefixLabel' => 'Topic prefix',
        );
        self::attachAssets(true);
        // A JSON data island. JS attaches fields to the existing native SMF post form.
        $context['html_headers'] .= '<script>window.boredTopicTagsComposer=' . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
    }

    public static function attachAssets($composer = false)
    {
        global $context, $settings;
        if (function_exists('loadCSSFile'))
            loadCSSFile('topic_tags.css', array('minimize' => true, 'order_pos' => 250), 'topic_tags');
        if ($composer && function_exists('loadJavaScriptFile'))
            loadJavaScriptFile('topic_tags.js', array('defer' => true, 'minimize' => false), 'topic_tags');
    }

    public static function hookRemoveTopics($topics)
    {
        global $smcFunc;
        if (!is_array($topics)) $topics = array($topics);
        $ids = array_values(array_unique(array_filter(array_map('intval', $topics))));
        if (!$ids) return;
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_tags WHERE id_topic IN ({array_int:topics})', array('topics' => $ids));
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_prefix WHERE id_topic IN ({array_int:topics})', array('topics' => $ids));
    }

    /**
     * SMF calls integrate_create_topic only after the new topic row exists.
     * integrate_after_create_post is too early: its topic id is still empty.
     */
    public static function hookCreateTopic(&$msgOptions, &$topicOptions, &$posterOptions)
    {
        global $user_info;
        if (!self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)) ||
            empty($_POST['tp_meta_marker']) || empty($user_info['id']))
            return;

        $topic = !empty($topicOptions['id']) ? (int) $topicOptions['id'] : 0;
        $message = !empty($msgOptions['id']) ? (int) $msgOptions['id'] : 0;
        if (!$topic || !$message || (int) $posterOptions['id'] !== (int) $user_info['id'])
            return;

        $owner = self::topicOwner($topic);
        if (!$owner || (int) $owner['id_first_msg'] !== $message || (int) $owner['id_member_started'] !== (int) $user_info['id'])
            return;

        self::saveLabels($topic, (int) $owner['id_board'], isset($_POST['tp_tags']) ? $_POST['tp_tags'] : '', isset($_POST['tp_prefix']) ? (int) $_POST['tp_prefix'] : 0);
    }

    public static function hookMessageIndex(&$buttons)
    {
        global $context;
        if (!self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)) || empty($context['topics']))
            return;
        self::boot();
        self::attachAssets();
        $ids = array();
        foreach ($context['topics'] as $topic)
            if (!empty($topic['id']) && !empty($topic['approved']))
                $ids[] = (int) $topic['id'];
        $meta = self::topicLabels($ids);
        foreach ($context['topics'] as &$topic)
        {
            $id = (int) $topic['id'];
            if (empty($meta[$id]))
                continue;
            $prefix = !empty($meta[$id]['prefix']) ? self::renderPrefix($meta[$id]['prefix']) : '';
            if ($prefix !== '')
                $topic['first_post']['link'] = $prefix . ' ' . $topic['first_post']['link'];
            if (!empty($meta[$id]['tags']))
                $topic['first_post']['link'] .= '<span class="tp-inline-tags">' . self::renderTags($meta[$id]['tags']) . '</span>';
        }
        unset($topic);
    }

    public static function hookDisplay(&$buttons)
    {
        global $context, $scripturl, $txt;
        if (!self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)))
            return;
        $topic = !empty($context['current_topic']) ? (int) $context['current_topic'] : 0;
        if (!$topic)
            return;
        self::boot();
        self::attachAssets();
        $meta = self::topicLabels(array($topic));
        if (!empty($meta[$topic]['prefix']))
            $context['subject'] = self::renderPrefix($meta[$topic]['prefix']) . ' ' . $context['subject'];
        if (!empty($meta[$topic]['tags']))
            $context['subject'] .= '<span class="tp-topic-tags">' . self::renderTags($meta[$topic]['tags']) . '</span>';
        if ((self::setting('tp_tags_enabled', 1) || self::setting('tp_prefixes_enabled', 1)) && self::canEdit($topic))
            $buttons['tp_edit'] = array('text' => 'tp_edit_button', 'url' => $scripturl . '?action=topiclabels;sa=edit;topic=' . $topic, 'active' => false);
    }

    public static function renderPrefix($prefix)
    {
        global $scripturl;
        $color = preg_match('~^#[0-9a-fA-F]{6}$~', $prefix['color']) ? $prefix['color'] : '#427aa5';
        $rgb = array(hexdec(substr($color, 1, 2)), hexdec(substr($color, 3, 2)), hexdec(substr($color, 5, 2)));
        $dark = ($rgb[0]*299 + $rgb[1]*587 + $rgb[2]*114)/1000 < 150;
        return '<a class="tp-prefix" style="background-color:' . $color . ';color:' . ($dark ? '#ffffff' : '#172334') . '" href="' . $scripturl . '?action=topiclabels;sa=prefix;id=' . (int) $prefix['id_prefix'] . '">' . self::e($prefix['name']) . '</a>';
    }

    public static function renderTags($tags)
    {
        global $scripturl;
        $html = '';
        foreach ($tags as $tag)
            $html .= '<a class="tp-tag" href="' . $scripturl . '?action=topiclabels;sa=tag;tag=' . rawurlencode($tag['slug']) . '">#' . self::e($tag['name']) . '</a> ';
        return $html;
    }

    public static function topicOwner($topic)
    {
        global $smcFunc;
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, t.approved, t.locked,
                m.poster_time AS first_post_time, m.approved AS first_post_approved
            FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
            WHERE t.id_topic = {int:topic} LIMIT 1', array('topic' => (int) $topic));
        $row = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $row ?: null;
    }

    public static function canEdit($topic)
    {
        global $user_info, $smcFunc, $modSettings;
        if ($user_info['is_guest'] || !self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)))
            return false;
        $owner = self::topicOwner((int) $topic);
        if (!$owner)
            return false;
        $board = (int) $owner['id_board'];
        // Having modify_own on a board must not bypass its current visibility.
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic FROM {db_prefix}topics AS t
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE t.id_topic = {int:topic} AND {query_see_board} LIMIT 1', array('topic' => (int) $topic));
        $visible = (bool) $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        if (!$visible)
            return false;
        if (allowedTo('admin_forum'))
            return true;
        // SMF requires moderation permission to edit a locked discussion.
        if (!empty($owner['locked']) && !allowedTo('moderate_board', $board))
            return false;
        if (allowedTo('modify_any', $board))
            return true;
        if ((int) $owner['id_member_started'] !== (int) $user_info['id'] || !allowedTo('modify_own', $board))
            return false;
        // Match SMF's edit deadline, including its five-minute grace period.
        // Unapproved first posts are exempt while post moderation is active.
        if ((empty($modSettings['postmod_active']) || !empty($owner['first_post_approved'])) &&
            !empty($modSettings['edit_disable_time']) &&
            (int) $owner['first_post_time'] + ((int) $modSettings['edit_disable_time'] + 5) * 60 < time())
            return false;
        return true;
    }

    public static function allPrefixes()
    {
        global $smcFunc;
        if (self::$prefixCache !== null)
            return self::$prefixCache;
        $r = $smcFunc['db_query']('', 'SELECT id_prefix, name, color, board_ids, sort_order, enabled FROM {db_prefix}tp_prefixes ORDER BY sort_order, name', array());
        self::$prefixCache = array();
        while ($p = $smcFunc['db_fetch_assoc']($r))
            self::$prefixCache[(int) $p['id_prefix']] = $p;
        $smcFunc['db_free_result']($r);
        return self::$prefixCache;
    }

    public static function prefixesForBoard($board)
    {
        $out = array();
        if (!self::setting('tp_prefixes_enabled', 1))
            return $out;
        foreach (self::allPrefixes() as $p)
        {
            if (empty($p['enabled']))
                continue;
            if ($p['board_ids'] !== '' && !in_array((int) $board, array_map('intval', explode(',', $p['board_ids'])), true))
                continue;
            $out[] = $p;
        }
        return $out;
    }

    public static function topicLabels(array $ids)
    {
        global $smcFunc;
        if (!self::setting('tp_enabled', 1))
            return array();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids)
            return array();
        $out = array();
        if (self::setting('tp_prefixes_enabled', 1))
        {
            $r = $smcFunc['db_query']('', '
            SELECT l.id_topic, p.id_prefix, p.name, p.color
            FROM {db_prefix}tp_topic_prefix AS l
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = l.id_topic)
                INNER JOIN {db_prefix}tp_prefixes AS p ON (p.id_prefix = l.id_prefix AND p.enabled = 1)
            WHERE l.id_topic IN ({array_int:topics})
                AND (p.board_ids = {string:empty} OR FIND_IN_SET(t.id_board, p.board_ids) > {int:zero})', array('topics' => $ids, 'empty' => '', 'zero' => 0));
            while ($row = $smcFunc['db_fetch_assoc']($r))
                $out[(int) $row['id_topic']]['prefix'] = $row;
            $smcFunc['db_free_result']($r);
        }
        if (self::setting('tp_tags_enabled', 1))
        {
            $r = $smcFunc['db_query']('', '
            SELECT x.id_topic, t.id_tag, t.name, t.slug
            FROM {db_prefix}tp_topic_tags AS x
                INNER JOIN {db_prefix}tp_tags AS t ON (t.id_tag = x.id_tag AND t.enabled = 1)
            WHERE x.id_topic IN ({array_int:topics}) ORDER BY t.name', array('topics' => $ids));
            while ($row = $smcFunc['db_fetch_assoc']($r))
                $out[(int) $row['id_topic']]['tags'][] = $row;
            $smcFunc['db_free_result']($r);
        }
        return $out;
    }

    public static function saveLabels($topic, $board, $rawTags, $prefixId)
    {
        global $smcFunc, $user_info;
        $topic = (int) $topic;
        if (!$topic || !$board || !self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)))
            return;
        $allowedPrefix = 0;
        if (self::setting('tp_prefixes_enabled', 1) && $prefixId)
            foreach (self::prefixesForBoard($board) as $p)
                if ((int) $p['id_prefix'] === (int) $prefixId)
                    $allowedPrefix = (int) $prefixId;
        if (self::setting('tp_prefixes_enabled', 1))
        {
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_prefix WHERE id_topic = {int:topic}', array('topic' => $topic));
            if ($allowedPrefix)
                $smcFunc['db_insert']('insert', '{db_prefix}tp_topic_prefix', array('id_topic' => 'int', 'id_prefix' => 'int'), array($topic, $allowedPrefix), array('id_topic'));
        }

        $ids = array();
        if (self::setting('tp_tags_enabled', 1))
        {
            $names = self::tagNames($rawTags);
            foreach ($names as $slug => $name)
            {
                $r = $smcFunc['db_query']('', 'SELECT id_tag FROM {db_prefix}tp_tags WHERE slug = {string:slug} AND enabled = {int:enabled} LIMIT 1', array('slug' => $slug, 'enabled' => 1));
                $found = $smcFunc['db_fetch_assoc']($r);
                $smcFunc['db_free_result']($r);
                if ($found)
                    $ids[] = (int) $found['id_tag'];
                elseif (self::setting('tp_member_tags', 1) || allowedTo('admin_forum'))
                {
                    $smcFunc['db_insert']('ignore', '{db_prefix}tp_tags', array('slug' => 'string-64', 'name' => 'string-40', 'created_by' => 'int', 'enabled' => 'int', 'created_at' => 'int'), array($slug, $name, (int) $user_info['id'], 1, time()), array('id_tag'));
                    $r = $smcFunc['db_query']('', 'SELECT id_tag FROM {db_prefix}tp_tags WHERE slug = {string:slug} LIMIT 1', array('slug' => $slug));
                    $new = $smcFunc['db_fetch_assoc']($r);
                    $smcFunc['db_free_result']($r);
                    if ($new)
                        $ids[] = (int) $new['id_tag'];
                }
            }
        }
        if (self::setting('tp_tags_enabled', 1))
        {
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_tags WHERE id_topic = {int:topic}', array('topic' => $topic));
            foreach (array_unique($ids) as $tag)
                $smcFunc['db_insert']('ignore', '{db_prefix}tp_topic_tags', array('id_topic' => 'int', 'id_tag' => 'int'), array($topic, $tag), array('id_topic', 'id_tag'));
        }
        // Consumers can invalidate caches, index tags or emit their own notifications.
        call_integration_hook('integrate_tp_topic_labels_saved', array($topic, array_unique($ids), $allowedPrefix));
    }

    public static function dispatch()
    {
        global $context, $txt, $user_info, $scripturl;
        self::boot();
        if (!self::setting('tp_enabled', 1) ||
            (!self::setting('tp_tags_enabled', 1) && !self::setting('tp_prefixes_enabled', 1)))
            fatal_lang_error('feature_disabled', false);
        self::attachAssets();
        loadTemplate('TopicTags');
        $context['page_title'] = $txt['tp_title'];
        $sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'browse';
        if ($sa === 'edit')
            return self::editPage();
        if ($sa === 'toggle')
            return self::toggleFollow();
        if ($sa === 'following')
        {
            if (!self::setting('tp_follow_enabled', 1) || !self::setting('tp_tags_enabled', 1))
                fatal_lang_error('no_access', false);
            return self::followingPage();
        }
        if ($sa === 'prefix')
        {
            if (!self::setting('tp_prefixes_enabled', 1))
                fatal_lang_error('no_access', false);
            return self::listPage('prefix');
        }
        if ($sa === 'tag')
        {
            if (!self::setting('tp_tags_enabled', 1))
                fatal_lang_error('no_access', false);
            return self::listPage('tag');
        }
        return self::browsePage();
    }

    private static function currentTag($slug)
    {
        global $smcFunc;
        $r = $smcFunc['db_query']('', 'SELECT id_tag, name, slug FROM {db_prefix}tp_tags WHERE slug = {string:slug} AND enabled = {int:yes} LIMIT 1', array('slug' => $slug, 'yes' => 1));
        $tag = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $tag ?: null;
    }

    public static function browsePage()
    {
        global $context, $smcFunc, $scripturl, $user_info;
        $q = isset($_GET['q']) ? self::cleanName($_GET['q'], 32) : ''; 
        $params = array('q' => '%' . $q . '%', 'approved' => 1, 'yes' => 1);
        // Only surface tags with at least one currently visible and approved topic.
        if (self::setting('tp_tags_enabled', 1))
        {
        $r = $smcFunc['db_query']('', '
            SELECT tg.id_tag, tg.name, tg.slug, COUNT(DISTINCT x.id_topic) AS topic_count
            FROM {db_prefix}tp_tags AS tg
                INNER JOIN {db_prefix}tp_topic_tags AS x ON (x.id_tag = tg.id_tag)
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic AND t.approved = {int:approved})
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE tg.enabled = {int:yes} AND tg.name LIKE {string:q} AND {query_see_board}
            GROUP BY tg.id_tag, tg.name, tg.slug
            ORDER BY topic_count DESC, tg.name ASC
            LIMIT 150', $params);
        $tags = array();
        while ($row = $smcFunc['db_fetch_assoc']($r))
            $tags[] = $row;
        $smcFunc['db_free_result']($r);
        }
        else $tags = array();
        $context['tp'] = array('mode' => 'browse', 'tags' => $tags, 'search' => $q,
            'prefixes' => self::setting('tp_prefixes_enabled', 1) ? self::visiblePrefixes() : array(),
            'can_follow' => !$user_info['is_guest'] && self::setting('tp_tags_enabled', 1) && self::setting('tp_follow_enabled', 1));
        $context['sub_template'] = 'tp_browse';
    }

    private static function visiblePrefixes()
    {
        global $smcFunc;
        $r = $smcFunc['db_query']('', '
            SELECT p.id_prefix, p.name, p.color, COUNT(DISTINCT t.id_topic) AS topic_count
            FROM {db_prefix}tp_prefixes AS p
                INNER JOIN {db_prefix}tp_topic_prefix AS x ON (x.id_prefix = p.id_prefix)
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic AND t.approved = {int:approved})
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE p.enabled = {int:enabled} AND {query_see_board}
                AND (p.board_ids = {string:empty} OR FIND_IN_SET(t.id_board, p.board_ids) > {int:zero})
            GROUP BY p.id_prefix, p.name, p.color ORDER BY topic_count DESC, p.name LIMIT 80', array('approved' => 1, 'enabled' => 1, 'empty' => '', 'zero' => 0));
        $data = array();
        while ($row = $smcFunc['db_fetch_assoc']($r))
            $data[] = $row;
        $smcFunc['db_free_result']($r);
        return $data;
    }

    private static function hasVisibleLabel($type, $id)
    {
        global $smcFunc;
        // Do not disclose tags or prefix names which exist only in private boards.
        $table = $type === 'tag' ? 'tp_topic_tags' : 'tp_topic_prefix';
        $column = $type === 'tag' ? 'id_tag' : 'id_prefix';
        $prefixJoin = $type === 'prefix' ? '
                INNER JOIN {db_prefix}tp_prefixes AS p ON (p.id_prefix = x.id_prefix AND p.enabled = 1)' : '';
        $prefixWhere = $type === 'prefix' ? '
                AND (p.board_ids = {string:empty} OR FIND_IN_SET(t.id_board, p.board_ids) > {int:zero})' : '';
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic FROM {db_prefix}' . $table . ' AS x
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic)
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)' . $prefixJoin . '
            WHERE x.' . $column . ' = {int:id} AND t.approved = {int:approved}
                AND {query_see_board}' . $prefixWhere . ' LIMIT 1',
            array('id' => (int) $id, 'approved' => 1, 'empty' => '', 'zero' => 0));
        $found = (bool) $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $found;
    }

    public static function listPage($type)
    {
        global $context, $smcFunc, $scripturl, $user_info;
        $prefix = null;
        $tag = null;
        if ($type === 'tag')
        {
            $slug = isset($_GET['tag']) ? preg_replace('~[^a-z0-9-]~', '', strtolower((string) $_GET['tag'])) : '';
            $tag = self::currentTag($slug);
            if (!$tag)
                fatal_lang_error('no_access', false);
            $id = (int) $tag['id_tag'];
            $join = 'INNER JOIN {db_prefix}tp_topic_tags AS x ON (x.id_topic = t.id_topic AND x.id_tag = {int:item})';
        }
        else
        {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $prefixes = self::allPrefixes();
            $prefix = isset($prefixes[$id]) && !empty($prefixes[$id]['enabled']) ? $prefixes[$id] : null;
            if (!$prefix)
                fatal_lang_error('no_access', false);
            $join = 'INNER JOIN {db_prefix}tp_topic_prefix AS x ON (x.id_topic = t.id_topic AND x.id_prefix = {int:item})
                INNER JOIN {db_prefix}tp_prefixes AS p ON (p.id_prefix = x.id_prefix AND (p.board_ids = {string:empty} OR FIND_IN_SET(t.id_board, p.board_ids) > {int:zero}))';
        }
        if (!self::hasVisibleLabel($type, $id))
            fatal_lang_error('no_access', false);
        $sort = isset($_GET['sort']) && $_GET['sort'] === 'popular' ? 'popular' : 'recent';
        $order = $sort === 'popular' ? 't.num_replies DESC, t.id_last_msg DESC' : 't.id_last_msg DESC';
        $start = min(5000, max(0, (int) (isset($_GET['start']) ? $_GET['start'] : 0)));
        $params = array('item' => $id, 'approved' => 1, 'offset' => $start, 'empty' => '', 'zero' => 0);
        $r = $smcFunc['db_query']('', '
            SELECT t.id_topic, t.id_board, t.num_replies, t.num_views, t.id_last_msg,
                mf.subject, b.name AS board_name, m.poster_time, m.poster_name
            FROM {db_prefix}topics AS t
                ' . $join . '
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
                INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
            WHERE t.approved = {int:approved} AND {query_see_board}
            ORDER BY ' . $order . '
            LIMIT {int:offset}, 26', $params);
        $rows = array();
        while ($row = $smcFunc['db_fetch_assoc']($r))
            $rows[] = $row;
        $smcFunc['db_free_result']($r);
        $more = count($rows) > 25;
        $rows = array_slice($rows, 0, 25);
        $follow = false;
        if ($tag && !$user_info['is_guest'] && self::setting('tp_follow_enabled', 1) && self::setting('tp_tags_enabled', 1))
            $follow = self::isFollowing($id, (int) $user_info['id']);
        $context['page_title'] = $tag ? $tag['name'] : $prefix['name'];
        $context['tp'] = array('mode' => $type, 'title' => $context['page_title'], 'tag' => $tag, 'prefix' => $prefix,
            'items' => $rows, 'sort' => $sort, 'start' => $start, 'more' => $more, 'following' => $follow);
        $context['sub_template'] = 'tp_list';
    }

    private static function isFollowing($idTag, $member)
    {
        global $smcFunc;
        $r = $smcFunc['db_query']('', 'SELECT id_tag FROM {db_prefix}tp_follows WHERE id_tag = {int:tag} AND id_member = {int:member} LIMIT 1', array('tag' => $idTag, 'member' => $member));
        $yes = (bool) $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        return $yes;
    }

    public static function toggleFollow()
    {
        global $user_info, $smcFunc;
        is_not_guest();
        checkSession('post');
        if (!self::setting('tp_enabled', 1) || !self::setting('tp_tags_enabled', 1) || !self::setting('tp_follow_enabled', 1))
            fatal_lang_error('no_access', false);
        $id = isset($_POST['tag']) ? (int) $_POST['tag'] : 0;
        $r = $smcFunc['db_query']('', 'SELECT slug FROM {db_prefix}tp_tags WHERE id_tag = {int:tag} AND enabled = {int:enabled}', array('tag' => $id, 'enabled' => 1));
        $tag = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        if (!$tag)
            fatal_lang_error('no_access', false);
        $visible = $smcFunc['db_query']('', '
            SELECT x.id_topic FROM {db_prefix}tp_topic_tags AS x
                INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic AND t.approved = {int:approved})
                INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE x.id_tag = {int:tag} AND {query_see_board} LIMIT 1',
            array('tag' => $id, 'approved' => 1));
        $canSee = (bool) $smcFunc['db_fetch_assoc']($visible);
        $smcFunc['db_free_result']($visible);
        if (!$canSee && !self::isFollowing($id, (int) $user_info['id']))
            fatal_lang_error('no_access', false);
        if (self::isFollowing($id, (int) $user_info['id']))
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_follows WHERE id_tag = {int:tag} AND id_member = {int:member}', array('tag' => $id, 'member' => (int) $user_info['id']));
        else
            $smcFunc['db_insert']('ignore', '{db_prefix}tp_follows', array('id_tag' => 'int', 'id_member' => 'int', 'seen_at' => 'int'), array($id, (int) $user_info['id'], time()), array('id_tag', 'id_member'));
        redirectexit('action=topiclabels;sa=tag;tag=' . rawurlencode($tag['slug']));
    }

    public static function followingPage()
    {
        global $smcFunc, $context, $user_info;
        is_not_guest();
        if (!self::setting('tp_enabled', 1) || !self::setting('tp_tags_enabled', 1) || !self::setting('tp_follow_enabled', 1))
            fatal_lang_error('no_access', false);
        $id = (int) $user_info['id'];
        if (isset($_POST['mark_seen']))
        {
            checkSession('post');
            $smcFunc['db_query']('', 'UPDATE {db_prefix}tp_follows SET seen_at = {int:now} WHERE id_member = {int:member}', array('now' => time(), 'member' => $id));
            redirectexit('action=topiclabels;sa=following');
        }
        $r = $smcFunc['db_query']('', '
            SELECT f.id_tag, f.seen_at, tg.name, tg.slug
            FROM {db_prefix}tp_follows AS f
                INNER JOIN {db_prefix}tp_tags AS tg ON (tg.id_tag = f.id_tag AND tg.enabled = 1)
            WHERE f.id_member = {int:member}
                AND EXISTS (
                    SELECT 1 FROM {db_prefix}tp_topic_tags AS x
                        INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic AND t.approved = {int:approved})
                        INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                    WHERE x.id_tag = f.id_tag AND {query_see_board}
                )
            ORDER BY tg.name LIMIT 500', array('member' => $id, 'approved' => 1));
        $tags = array();
        while ($row = $smcFunc['db_fetch_assoc']($r))
            $tags[] = $row;
        $smcFunc['db_free_result']($r);
        $counts = array();
        if ($tags)
        {
            $ids = array_map('intval', array_column($tags, 'id_tag'));
            $r = $smcFunc['db_query']('', '
                SELECT x.id_tag, COUNT(DISTINCT x.id_topic) AS total
                FROM {db_prefix}tp_topic_tags AS x
                    INNER JOIN {db_prefix}tp_follows AS f ON (f.id_tag = x.id_tag AND f.id_member = {int:member})
                    INNER JOIN {db_prefix}topics AS t ON (t.id_topic = x.id_topic AND t.approved = {int:approved})
                    INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                    INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
                WHERE x.id_tag IN ({array_int:tags}) AND mf.poster_time > f.seen_at AND {query_see_board}
                GROUP BY x.id_tag',
                array('member' => $id, 'approved' => 1, 'tags' => $ids));
            while ($row = $smcFunc['db_fetch_assoc']($r))
                $counts[(int) $row['id_tag']] = (int) $row['total'];
            $smcFunc['db_free_result']($r);
        }
        foreach ($tags as &$t)
            $t['new_topics'] = isset($counts[(int) $t['id_tag']]) ? $counts[(int) $t['id_tag']] : 0;
        unset($t);
        $context['tp'] = array('mode' => 'following', 'tags' => $tags);
        $context['sub_template'] = 'tp_following';
    }

    public static function editPage()
    {
        global $context, $smcFunc, $user_info, $scripturl;
        $topic = isset($_REQUEST['topic']) ? (int) $_REQUEST['topic'] : 0;
        $owner = self::topicOwner($topic);
        if (!$owner || !self::canEdit($topic))
            fatal_lang_error('no_access', false);
        $board = (int) $owner['id_board'];
        if (isset($_POST['save_labels']))
        {
            checkSession('post');
            if (!self::canEdit($topic))
                fatal_lang_error('no_access', false);
            self::saveLabels($topic, $board, isset($_POST['tp_tags']) ? $_POST['tp_tags'] : '', isset($_POST['tp_prefix']) ? (int) $_POST['tp_prefix'] : 0);
            redirectexit('topic=' . $topic . '.0');
        }
        $meta = self::topicLabels(array($topic));
        $current = isset($meta[$topic]) ? $meta[$topic] : array();
        $context['page_title'] = 'Edit tags and prefix';
        $context['tp'] = array('mode' => 'edit', 'topic' => $topic, 'prefix' => isset($current['prefix']) ? (int) $current['prefix']['id_prefix'] : 0,
            'tags' => isset($current['tags']) ? implode(', ', array_column($current['tags'], 'name')) : '',
            'prefixes' => self::prefixesForBoard($board), 'can_tags' => (bool) self::setting('tp_tags_enabled', 1), 'can_prefixes' => (bool) self::setting('tp_prefixes_enabled', 1),
            'max_tags' => (int) self::setting('tp_max_tags', 5));
        $context['sub_template'] = 'tp_edit';
    }

    public static function admin()
    {
        global $context, $smcFunc, $txt, $scripturl, $modSettings;
        isAllowedTo('admin_forum');
        self::boot();
        self::attachAssets();
        loadTemplate('TopicTags');
        $context['page_title'] = $txt['tp_admin'];
        $sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'settings';
        if (isset($_POST['tp_save_settings']))
        {
            checkSession('post');
            updateSettings(array(
                'tp_enabled' => isset($_POST['enabled']) ? 1 : 0,
                'tp_tags_enabled' => isset($_POST['tags_enabled']) ? 1 : 0,
                'tp_prefixes_enabled' => isset($_POST['prefixes_enabled']) ? 1 : 0,
                'tp_follow_enabled' => isset($_POST['follow_enabled']) ? 1 : 0,
                'tp_member_tags' => isset($_POST['member_tags']) ? 1 : 0,
                'tp_max_tags' => max(1, min(12, (int) (isset($_POST['max_tags']) ? $_POST['max_tags'] : 5))),
            ));
            redirectexit('action=admin;area=topiclabels;saved');
        }
        if (isset($_POST['tp_save_prefix']))
        {
            checkSession('post');
            $name = self::cleanName(isset($_POST['name']) ? $_POST['name'] : '', 40);
            $color = isset($_POST['color']) ? trim((string) $_POST['color']) : '#3779ad';
            if (!preg_match('~^#[0-9a-fA-F]{6}$~', $color)) $color = '#3779ad';
            if ($name === '') fatal_lang_error('no_access', false);
            $boardInput = isset($_POST['boards']) && is_scalar($_POST['boards']) ? $_POST['boards'] : '';
            $boards = array_values(array_unique(array_filter(array_map('intval', explode(',', $boardInput)))));
            $parts = array();
            foreach ($boards as $b)
            {
                if (strlen(implode(',', array_merge($parts, array($b)))) > 255 || count($parts) >= 50) break;
                $parts[] = $b;
            }
            $ids = implode(',', $parts);
            $order = max(0, min(10000, (int) (isset($_POST['sort_order']) ? $_POST['sort_order'] : 0)));
            $enabled = isset($_POST['prefix_enabled']) ? 1 : 0;
            $id = isset($_POST['id_prefix']) ? (int) $_POST['id_prefix'] : 0;
            if ($id)
                $smcFunc['db_query']('', 'UPDATE {db_prefix}tp_prefixes SET name = {string:name}, color = {string:color}, board_ids = {string:boards}, sort_order = {int:sort}, enabled = {int:enabled} WHERE id_prefix = {int:id}',
                    array('name' => $name, 'color' => $color, 'boards' => $ids, 'sort' => $order, 'enabled' => $enabled, 'id' => $id));
            else
                $smcFunc['db_insert']('insert', '{db_prefix}tp_prefixes', array('name' => 'string-40', 'color' => 'string-7', 'board_ids' => 'string-255', 'sort_order' => 'int', 'enabled' => 'int'), array($name, $color, $ids, $order, $enabled), array('id_prefix'));
            redirectexit('action=admin;area=topiclabels;sa=prefixes');
        }
        if (isset($_POST['tp_delete_prefix']))
        {
            checkSession('post');
            $id = max(0, (int) $_POST['id_prefix']);
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_prefix WHERE id_prefix = {int:id}', array('id' => $id));
            $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_prefixes WHERE id_prefix = {int:id}', array('id' => $id));
            redirectexit('action=admin;area=topiclabels;sa=prefixes');
        }
        if (isset($_POST['tp_tag_action']))
        {
            checkSession('post');
            $id = max(0, (int) $_POST['id_tag']);
            $action = (string) $_POST['tp_tag_action'];
            if ($action === 'delete')
            {
                $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_topic_tags WHERE id_tag = {int:id}', array('id' => $id));
                $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_follows WHERE id_tag = {int:id}', array('id' => $id));
                $smcFunc['db_query']('', 'DELETE FROM {db_prefix}tp_tags WHERE id_tag = {int:id}', array('id' => $id));
            }
            elseif ($action === 'rename')
            {
                $name = self::cleanName(isset($_POST['name']) ? $_POST['name'] : '', 30);
                if ($name !== '')
                    $smcFunc['db_query']('', 'UPDATE {db_prefix}tp_tags SET name = {string:name} WHERE id_tag = {int:id}', array('name' => $name, 'id' => $id));
            }
            elseif ($action === 'disable' || $action === 'enable')
                $smcFunc['db_query']('', 'UPDATE {db_prefix}tp_tags SET enabled = {int:on} WHERE id_tag = {int:id}', array('on' => $action === 'enable' ? 1 : 0, 'id' => $id));
            redirectexit('action=admin;area=topiclabels;sa=tags');
        }
        if ($sa === 'prefixes')
        {
            $edit = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
            $all = self::allPrefixes();
            $context['tp'] = array('mode' => 'admin_prefixes', 'prefixes' => $all, 'edit' => isset($all[$edit]) ? $all[$edit] : null);
            $context['sub_template'] = 'tp_admin_prefixes';
        }
        elseif ($sa === 'tags')
        {
            $r = $smcFunc['db_query']('', 'SELECT id_tag, name, slug, enabled FROM {db_prefix}tp_tags ORDER BY name LIMIT 300', array());
            $tags = array();
            while ($tag = $smcFunc['db_fetch_assoc']($r)) $tags[] = $tag;
            $smcFunc['db_free_result']($r);
            $context['tp'] = array('mode' => 'admin_tags', 'tags' => $tags);
            $context['sub_template'] = 'tp_admin_tags';
        }
        else
        {
            $context['tp'] = array('mode' => 'admin_settings');
            $context['sub_template'] = 'tp_admin_settings';
        }
    }
}

function tp_dispatch() { BoredTopicTags::dispatch(); }
function tp_admin() { BoredTopicTags::admin(); }
