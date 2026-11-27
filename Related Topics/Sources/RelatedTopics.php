<?php

/**
 * Related Topics for SMF 2.1
 *
 * Shows a row of topics similar to the one being viewed at the bottom
 * (or top) of the topic page. Hook-only: no core files are edited.
 *
 * @package RelatedTopics
 * @version 1.0.3
 * @license ISC Bryan "Bored Skull" Deakin
 */

if (!defined('SMF'))
	die('No direct access...');

if (!defined('RTOPICS_VERSION'))
	define('RTOPICS_VERSION', '1.0.3');

/**
 * Default values for every setting. Also used by install.php.
 *
 * @return array
 */
function rtopics_defaults()
{
	return array(
		// General
		'rtopics_enabled' => 1,
		'rtopics_guests' => 1,
		'rtopics_last_page_only' => 0,
		// Matching
		'rtopics_count' => 3,
		'rtopics_scope' => 'any',
		'rtopics_fallback' => 'board',
		'rtopics_min_word' => 3,
		'rtopics_min_matches' => 1,
		'rtopics_stopwords' => '',
		'rtopics_exclude_boards' => '',
		'rtopics_cache' => 15,
		// Appearance
		'rtopics_title' => '',
		'rtopics_position' => 'bottom',
		'rtopics_style' => 'cards',
		'rtopics_scheme' => 'light',
		'rtopics_columns' => 3,
		'rtopics_ratio' => 'wide',
		'rtopics_hover' => 'lift',
		'rtopics_mobile' => 'scroll',
		'rtopics_accent' => '#3a70c0',
		'rtopics_animate' => 1,
		// Card content
		'rtopics_show_image' => 1,
		'rtopics_show_board' => 1,
		'rtopics_show_excerpt' => 1,
		'rtopics_excerpt_length' => 110,
		'rtopics_title_length' => 70,
		'rtopics_show_meta' => 1,
		'rtopics_new_window' => 0,
	);
}

/**
 * Read a setting, falling back to its default.
 *
 * @param string $key Setting name without the rtopics_ prefix.
 * @return mixed
 */
function rtopics_opt($key)
{
	global $modSettings;
	static $defaults = null;

	if ($defaults === null)
		$defaults = rtopics_defaults();

	$full = 'rtopics_' . $key;

	if (isset($modSettings[$full]))
		return $modSettings[$full];

	return isset($defaults[$full]) ? $defaults[$full] : null;
}

/**
 * Clamp a number into a range.
 */
function rtopics_clamp($value, $min, $max)
{
	return max($min, min($max, (int) $value));
}

/**
 * Escape text for HTML, including attribute values. SMF may store some fields
 * as HTML entities already, so normalise them before encoding once.
 */
function rtopics_escape($value)
{
	return htmlspecialchars(un_htmlspecialchars((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Board IDs excluded in the admin panel.
 *
 * @return int[]
 */
function rtopics_excluded_boards()
{
	$list = array_map('intval', explode(',', (string) rtopics_opt('exclude_boards')));

	return array_values(array_unique(array_filter($list)));
}

/**
 * Hook: integrate_display_message_list
 * Finds the related topics and adds the template layer.
 */
function rtopics_display(&$messages, &$posters)
{
	global $context, $topic, $board, $board_info, $user_info, $txt, $modSettings;

	if (empty($topic) || !rtopics_opt('enabled'))
		return;

	if (!empty($user_info['is_guest']) && !rtopics_opt('guests'))
		return;

	// Only on the last page of the topic?
	if (rtopics_opt('last_page_only') && isset($context['start'], $context['messages_per_page'], $context['total_visible_posts'])
		&& (int) $context['start'] + (int) $context['messages_per_page'] < (int) $context['total_visible_posts'])
		return;

	$excluded = rtopics_excluded_boards();
	if (in_array((int) $board, $excluded))
		return;

	// Nothing to suggest on a topic in the recycle bin.
	if (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) && (int) $board === (int) $modSettings['recycle_board'])
		return;

	$items = rtopics_find(
		(int) $topic,
		isset($context['subject']) ? $context['subject'] : '',
		(int) $board,
		isset($board_info['cat']['id']) ? (int) $board_info['cat']['id'] : 0,
		$excluded
	);

	if (empty($items))
		return;

	loadLanguage('RelatedTopics');
	loadTemplate('RelatedTopics');
	loadCSSFile('related_topics.css', array('minimize' => true), 'smf_related_topics');

	// Things that depend on the viewer are done after the cache.
	foreach ($items as &$item)
	{
		$item['time'] = timeformat($item['timestamp']);
		$item['replies_formatted'] = comma_format($item['replies']);
		$item['views_formatted'] = comma_format($item['views']);
	}
	unset($item);

	$style = rtopics_opt('style');
	if (!in_array($style, array('cards', 'overlay', 'minimal', 'gradient')))
		$style = 'cards';

	$title = trim((string) rtopics_opt('title'));

	$context['related_topics'] = $items;
	$context['related_topics_opts'] = array(
		'title' => rtopics_escape($title !== '' ? $title : $txt['rtopics_default_title']),
		'position' => in_array(rtopics_opt('position'), array('bottom', 'before_reply', 'top')) ? rtopics_opt('position') : 'bottom',
		'style' => $style,
		'scheme' => rtopics_opt('scheme') == 'dark' ? 'dark' : 'light',
		'columns' => rtopics_clamp(rtopics_opt('columns'), 2, 4),
		'ratio' => in_array(rtopics_opt('ratio'), array('wide', 'classic', 'square')) ? rtopics_opt('ratio') : 'wide',
		'hover' => in_array(rtopics_opt('hover'), array('lift', 'glow', 'zoom', 'none')) ? rtopics_opt('hover') : 'lift',
		'mobile' => rtopics_opt('mobile') == 'stack' ? 'stack' : 'scroll',
		'accent' => rtopics_sanitize_color(rtopics_opt('accent')),
		'animate' => (bool) rtopics_opt('animate'),
		// Overlay always needs a media area; minimal and gradient never use one.
		'show_media' => $style == 'overlay' || ($style == 'cards' && rtopics_opt('show_image')),
		'show_board' => (bool) rtopics_opt('show_board'),
		'show_excerpt' => (bool) rtopics_opt('show_excerpt'),
		'show_meta' => (bool) rtopics_opt('show_meta'),
		'new_window' => (bool) rtopics_opt('new_window'),
	);

	$context['template_layers'][] = 'related_topics';
}
/** Got to add some copyrights **/

function rtopics_credits()
{
	global $context, $txt;

	loadLanguage('RelatedTopics');

	$context['credits_modifications'][] = sprintf($txt['rtopics_credits'], RTOPICS_VERSION);
}
/**
 * Pull search keywords out of a subject.
 *
 * @param string $subject Subject as stored (HTML-escaped).
 * @return string[] Longest words first.
 */
function rtopics_keywords($subject)
{
	global $smcFunc, $txt;

	loadLanguage('RelatedTopics');

	$subject = $smcFunc['strtolower'](un_htmlspecialchars($subject));
	$subject = preg_replace('~^\s*re:\s*~u', '', $subject);
	$words = preg_split('~[^\p{L}\p{N}]+~u', $subject, -1, PREG_SPLIT_NO_EMPTY);
	if (!is_array($words))
		return array();

	$stop_list = (isset($txt['rtopics_stopwords_list']) ? $txt['rtopics_stopwords_list'] : '') . ',' . rtopics_opt('stopwords');
	$stop = array();
	foreach (explode(',', $stop_list) as $word)
	{
		$word = trim($smcFunc['strtolower']($word));
		if ($word !== '')
			$stop[$word] = true;
	}

	$min = rtopics_clamp(rtopics_opt('min_word'), 2, 10);
	$keywords = array();

	foreach ($words as $word)
	{
		$len = $smcFunc['strlen']($word);

		if ($len < $min || isset($stop[$word]))
			continue;

		// Short plain numbers ("1", "21") match far too much.
		if (ctype_digit($word) && $len < 4)
			continue;

		$keywords[$word] = $len;
	}

	arsort($keywords);

	return array_slice(array_keys($keywords), 0, 8);
}

/**
 * Find and build the related topics for a topic.
 *
 * @return array List of items ready for the template (minus viewer formatting).
 */
function rtopics_find($id_topic, $subject, $id_board, $id_cat, $excluded)
{
	global $smcFunc, $modSettings, $user_info;

	$limit = rtopics_clamp(rtopics_opt('count'), 1, 12);
	$ttl = rtopics_clamp(rtopics_opt('cache'), 0, 1440) * 60;

	// Cache expensive keyword matching, but never cache personalised HTML or
	// visibility decisions. On every hit, re-check current board permissions.
	// Include all matching options and language to avoid stale matches after
	// an admin edit or when two members use different language stopwords.
	global $language;
	$match_settings = array();
	foreach (array('count', 'scope', 'fallback', 'min_word', 'min_matches', 'stopwords', 'exclude_boards') as $option)
	{
		$value = rtopics_opt($option);
		$match_settings[] = is_scalar($value) ? (string) $value : '';
	}
	$cache_key = 'rtopics-v103-' . $id_topic . '-' . md5(
		implode(',', $user_info['groups']) . '|' . $user_info['query_see_board'] . '|'
		. (isset($modSettings['settings_updated']) ? $modSettings['settings_updated'] : '') . '|'
		. (isset($language) && is_string($language) ? $language : '') . '|' . implode('|', $match_settings)
	);

	$where = array(
		't.id_topic != {int:current_topic}',
		't.approved = {int:is_approved}',
		't.id_redirect_topic = {int:no_redirect}',
		'{query_see_board}',
	);
	$params = array(
		'current_topic' => $id_topic,
		'current_board' => $id_board,
		'current_cat' => $id_cat,
		'is_approved' => 1,
		'no_redirect' => 0,
	);

	$scope = rtopics_opt('scope');
	if ($scope == 'board')
		$where[] = 't.id_board = {int:current_board}';
	elseif ($scope == 'category' && !empty($id_cat))
		$where[] = 'b.id_cat = {int:current_cat}';

	if (!empty($excluded))
	{
		$where[] = 't.id_board NOT IN ({array_int:excluded_boards})';
		$params['excluded_boards'] = $excluded;
	}

	if (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']))
	{
		$where[] = 't.id_board != {int:recycle_board}';
		$params['recycle_board'] = (int) $modSettings['recycle_board'];
	}

	$select = '
			t.id_topic, t.id_board, t.id_first_msg, t.num_replies, t.num_views,
			m.subject, m.poster_time, b.name AS board_name';
	$from = '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)';

	if ($ttl > 0)
	{
		$cached = cache_get_data($cache_key, $ttl);
		if (is_array($cached))
		{
			if (empty($cached))
				return array();

			// Revalidate all cached topic IDs against current board visibility,
			// approval, recycling, category and exclusions. If anything moved
			// or became inaccessible, recompute suggestions instead.
			$check_where = $where;
			$check_where[] = 't.id_topic IN ({array_int:cached_topics})';
			$check_params = $params;
			$check_params['cached_topics'] = array_map('intval', array_keys($cached));
			$check_request = $smcFunc['db_query']('', '
				SELECT' . $select . $from . '
				WHERE ' . implode(' AND ', $check_where),
				$check_params
			);
			$valid = array();
			while ($row = $smcFunc['db_fetch_assoc']($check_request))
			{
				$id = (int) $row['id_topic'];
				if (array_key_exists($id, $cached))
				{
					$row['score'] = (float) $cached[$id];
					$valid[$id] = $row;
				}
			}
			$smcFunc['db_free_result']($check_request);

			if (count($valid) == count($cached))
			{
				$ordered = array();
				foreach ($cached as $id => $score)
					$ordered[(int) $id] = $valid[(int) $id];

				return rtopics_build_items($ordered);
			}
		}
	}

	$picked = array();

	// 1. Keyword matches, scored in PHP.
	$keywords = rtopics_keywords($subject);
	if (!empty($keywords))
	{
		$likes = array();
		$kw_params = $params;
		foreach ($keywords as $i => $kw)
		{
			$likes[] = 'm.subject LIKE {string:kw_' . $i . '}';
			$kw_params['kw_' . $i] = '%' . strtr($kw, array('%' => '\%', '_' => '\_', '\\' => '\\\\')) . '%';
		}
		$kw_params['pool'] = 150;

		$request = $smcFunc['db_query']('', '
			SELECT' . $select . $from . '
			WHERE ' . implode('
				AND ', $where) . '
				AND (' . implode(' OR ', $likes) . ')
			ORDER BY t.id_last_msg DESC
			LIMIT {int:pool}',
			$kw_params
		);

		$min_matches = rtopics_clamp(rtopics_opt('min_matches'), 1, 8);
		$now = time();

		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$candidate = $smcFunc['strtolower'](un_htmlspecialchars($row['subject']));
			$candidate_words = array_flip(preg_split('~[^\p{L}\p{N}]+~u', $candidate, -1, PREG_SPLIT_NO_EMPTY));

			$score = 0;
			$matched = 0;
			foreach ($keywords as $kw)
			{
				$len = $smcFunc['strlen']($kw);

				// A whole-word match counts for more than a partial one.
				if (isset($candidate_words[$kw]))
				{
					$score += 2 + $len / 4;
					$matched++;
				}
				elseif (strpos($candidate, $kw) !== false)
				{
					$score += 1 + $len / 6;
					$matched++;
				}
			}

			if ($matched < min($min_matches, count($keywords)))
				continue;

			if ((int) $row['id_board'] == $id_board)
				$score += 0.75;

			// Small nudges for newer and busier topics.
			$age_days = max(0, ($now - (int) $row['poster_time']) / 86400);
			$score += max(0, 1 - $age_days / 365);
			$score += log10((int) $row['num_replies'] + 1) * 0.3;

			$row['score'] = $score;
			$picked[(int) $row['id_topic']] = $row;
		}
		$smcFunc['db_free_result']($request);

		uasort($picked, function ($a, $b)
		{
			if ($a['score'] == $b['score'])
				return $b['id_topic'] - $a['id_topic'];

			return $a['score'] < $b['score'] ? 1 : -1;
		});

		$picked = array_slice($picked, 0, $limit, true);
	}

	// 2. Not enough? Fill up with the latest topics.
	$fallback = rtopics_opt('fallback');
	if (count($picked) < $limit && in_array($fallback, array('board', 'recent')))
	{
		$fb_where = $where;
		$fb_params = $params;

		if ($fallback == 'board')
			$fb_where[] = 't.id_board = {int:current_board}';

		if (!empty($picked))
		{
			$fb_where[] = 't.id_topic NOT IN ({array_int:already})';
			$fb_params['already'] = array_keys($picked);
		}
		$fb_params['need'] = $limit - count($picked);

		$request = $smcFunc['db_query']('', '
			SELECT' . $select . $from . '
			WHERE ' . implode('
				AND ', $fb_where) . '
			ORDER BY t.id_last_msg DESC
			LIMIT {int:need}',
			$fb_params
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$row['score'] = 0;
			$picked[(int) $row['id_topic']] = $row;
		}
		$smcFunc['db_free_result']($request);
	}

	// Store only topic IDs and scores. The HTML, censorship, image
	// permissions and text excerpts must always be built for the viewer.
	if ($ttl > 0)
	{
		$cache_ids = array();
		foreach ($picked as $id => $row)
			$cache_ids[(int) $id] = (float) $row['score'];
		cache_put_data($cache_key, $cache_ids, $ttl);
	}

	return empty($picked) ? array() : rtopics_build_items($picked);
}

/**
 * Load first-post details and turn rows into template items.
 *
 * @param array $picked Rows keyed by topic ID, in display order.
 * @return array
 */
function rtopics_build_items($picked)
{
	global $smcFunc, $scripturl, $modSettings;

	$msg_ids = array();
	foreach ($picked as $row)
		$msg_ids[] = (int) $row['id_first_msg'];

	// First post bodies and authors.
	$details = array();
	$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, m.body, m.id_member, COALESCE(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:msgs})',
		array('msgs' => $msg_ids)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$details[(int) $row['id_msg']] = $row;
	$smcFunc['db_free_result']($request);

	// Same rule as the display options: Overlay always has a picture area,
	// Cards only when "Show pictures" is on.
	$style = rtopics_opt('style');
	$want_images = $style == 'overlay' || ($style == 'cards' && rtopics_opt('show_image'));

	// First image attachment of each first post.
	$attachments = array();
	if ($want_images && !empty($modSettings['attachmentEnable']))
	{
		$request = $smcFunc['db_query']('', '
			SELECT a.id_attach, a.id_msg, a.id_thumb
			FROM {db_prefix}attachments AS a
			WHERE a.id_msg IN ({array_int:msgs})
				AND a.attachment_type = {int:normal}
				AND a.width > {int:zero}
				AND a.approved = {int:is_approved}
			ORDER BY a.id_attach',
			array(
				'msgs' => $msg_ids,
				'normal' => 0,
				'zero' => 0,
				'is_approved' => 1,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (!isset($attachments[(int) $row['id_msg']]))
				$attachments[(int) $row['id_msg']] = !empty($row['id_thumb']) ? (int) $row['id_thumb'] : (int) $row['id_attach'];
		}
		$smcFunc['db_free_result']($request);
	}

	$title_len = rtopics_clamp(rtopics_opt('title_length'), 20, 200);
	$excerpt_len = rtopics_clamp(rtopics_opt('excerpt_length'), 30, 400);
	$want_excerpt = (bool) rtopics_opt('show_excerpt');

	$items = array();
	foreach ($picked as $id_topic => $row)
	{
		$detail = isset($details[(int) $row['id_first_msg']]) ? $details[(int) $row['id_first_msg']] : array('body' => '', 'id_member' => 0, 'poster_name' => '');

		$subject = $row['subject'];
		censorText($subject);

		$plain = un_htmlspecialchars($subject);
		$initial = $smcFunc['strtoupper']($smcFunc['substr'](preg_replace('~[^\p{L}\p{N}]+~u', '', $plain), 0, 1));

		// Picture for the card.
		$image = '';
		$is_video = false;
		if ($want_images)
		{
			if (isset($attachments[(int) $row['id_first_msg']]) && allowedTo('view_attachments', (int) $row['id_board']))
				$image = $scripturl . '?action=dlattach;topic=' . $id_topic . '.0;attach=' . $attachments[(int) $row['id_first_msg']] . ';image';
			else
				list($image, $is_video) = rtopics_body_image($detail['body']);
		}

		$excerpt = $want_excerpt ? rtopics_excerpt($detail['body'], $excerpt_len) : '';

		$items[] = array(
			'id' => $id_topic,
			'href' => $scripturl . '?topic=' . $id_topic . '.0',
			'subject' => rtopics_escape(shorten_subject($plain, $title_len)),
			'subject_full' => rtopics_escape($plain),
			'excerpt' => $excerpt,
			'image' => $image,
			'is_video' => $is_video,
			'initial' => $initial !== '' ? $smcFunc['htmlspecialchars']($initial) : '#',
			'hue' => ((int) $row['id_board'] * 67 + 205) % 360,
			'board' => array(
				'id' => (int) $row['id_board'],
				'name' => rtopics_escape($row['board_name']),
			),
			'poster' => array(
				'id' => (int) $detail['id_member'],
				'name' => rtopics_escape($detail['poster_name']),
			),
			'timestamp' => (int) $row['poster_time'],
			'replies' => (int) $row['num_replies'],
			'views' => (int) $row['num_views'],
			'is_match' => $row['score'] > 0,
		);
	}

	return $items;
}

/**
 * Find a picture in a post body: an [img] tag first, then a YouTube link.
 *
 * @param string $body
 * @return array (url, is_video)
 */
function rtopics_body_image($body)
{
	global $modSettings;

	if (preg_match('~\[img(?:\s[^\]]*)?\]\s*(https?://[^\s\[\]"<>]+?)\s*\[/img\]~i', $body, $match))
	{
		$url = un_htmlspecialchars($match[1]);

		if (!empty($modSettings['image_proxy_enabled']) && function_exists('get_proxied_url'))
			$url = get_proxied_url($url);

		return array($url, false);
	}

	if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:\S*?&(?:amp;)?)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $body, $match)
		|| preg_match('~\[youtube[^\]]*\]\s*([A-Za-z0-9_-]{11})\s*\[/youtube\]~i', $body, $match))
		return array('https://i.ytimg.com/vi/' . $match[1] . '/hqdefault.jpg', true);

	return array('', false);
}

/**
 * Plain-text preview of a post.
 *
 * @param string $body Raw BBC body.
 * @param int $length
 * @return string HTML-safe text.
 */
function rtopics_excerpt($body, $length)
{
	if ($body === '')
		return '';

	// Quotes, code and pictures don't make good previews.
	$body = preg_replace('~\[(quote|code|spoiler|hide|php|html|img|youtube|attach)(?:[\s=][^\]]*)?\].*?\[/\1\]~is', ' ', $body);
	$body = preg_replace('~\[attach[^\]]*\]~i', ' ', $body);

	censorText($body);

	$text = parse_bbc($body, false);
	$text = preg_replace('~<br\s*/?>|</(?:p|div|li|h\d)>~i', ' ', $text);
	$text = strip_tags($text);
	$text = trim(preg_replace('~\s+~u', ' ', $text));

	if ($text === '')
		return '';

	return rtopics_escape(shorten_subject(un_htmlspecialchars($text), $length));
}

/**
 * Only allow simple colour values in the accent setting.
 */
function rtopics_sanitize_color($color)
{
	$color = is_string($color) ? trim($color) : '';

	if (preg_match('~^#(?:[0-9a-f]{3}|[0-9a-f]{6})$~i', $color))
		return $color;

	return '#3a70c0';
}

/**
 * Hook: integrate_admin_areas
 */
function rtopics_admin_areas(&$admin_areas)
{
	global $txt;

	loadLanguage('RelatedTopics');

	$admin_areas['config']['areas']['modsettings']['subsections']['relatedtopics'] = array($txt['rtopics_admin_title']);
}

/**
 * Hook: integrate_modify_modifications
 */
function rtopics_modify_modifications(&$sub_actions)
{
	$sub_actions['relatedtopics'] = 'rtopics_settings';
}

/**
 * Hook: integrate_admin_search
 */
function rtopics_admin_search(&$language_files, &$include_files, &$settings_search)
{
	$language_files[] = 'RelatedTopics';
	$settings_search[] = array('rtopics_settings', 'area=modsettings;sa=relatedtopics');
}

/**
 * Admin settings page: Admin > Configuration > Modification Settings > Related Topics
 */
function rtopics_settings($return_config = false)
{
	global $txt, $scripturl, $context;

	loadLanguage('RelatedTopics');

	$config_vars = array(
		array('title', 'rtopics_section_general'),
		array('check', 'rtopics_enabled'),
		array('check', 'rtopics_guests'),
		array('check', 'rtopics_last_page_only'),

		array('title', 'rtopics_section_matching'),
		array('int', 'rtopics_count', 'min' => 1, 'max' => 12, 'subtext' => $txt['rtopics_count_sub']),
		array('select', 'rtopics_scope', array(
			'any' => $txt['rtopics_scope_any'],
			'category' => $txt['rtopics_scope_category'],
			'board' => $txt['rtopics_scope_board'],
		)),
		array('select', 'rtopics_fallback', array(
			'board' => $txt['rtopics_fallback_board'],
			'recent' => $txt['rtopics_fallback_recent'],
			'none' => $txt['rtopics_fallback_none'],
		), 'subtext' => $txt['rtopics_fallback_sub']),
		array('int', 'rtopics_min_word', 'min' => 2, 'max' => 10, 'subtext' => $txt['rtopics_min_word_sub']),
		array('int', 'rtopics_min_matches', 'min' => 1, 'max' => 8, 'subtext' => $txt['rtopics_min_matches_sub']),
		array('text', 'rtopics_stopwords', 'size' => 50, 'subtext' => $txt['rtopics_stopwords_sub']),
		array('text', 'rtopics_exclude_boards', 'size' => 30, 'subtext' => $txt['rtopics_exclude_boards_sub']),
		array('int', 'rtopics_cache', 'min' => 0, 'max' => 1440, 'postinput' => $txt['rtopics_minutes'], 'subtext' => $txt['rtopics_cache_sub']),

		array('title', 'rtopics_section_appearance'),
		array('text', 'rtopics_title', 'size' => 40, 'subtext' => $txt['rtopics_title_sub']),
		array('select', 'rtopics_position', array(
			'bottom' => $txt['rtopics_position_bottom'],
			'before_reply' => $txt['rtopics_position_before_reply'],
			'top' => $txt['rtopics_position_top'],
		)),
		array('select', 'rtopics_style', array(
			'cards' => $txt['rtopics_style_cards'],
			'overlay' => $txt['rtopics_style_overlay'],
			'gradient' => $txt['rtopics_style_gradient'],
			'minimal' => $txt['rtopics_style_minimal'],
		)),
		array('select', 'rtopics_scheme', array(
			'light' => $txt['rtopics_scheme_light'],
			'dark' => $txt['rtopics_scheme_dark'],
		), 'subtext' => $txt['rtopics_scheme_sub']),
		array('select', 'rtopics_columns', array(
			2 => '2',
			3 => '3',
			4 => '4',
		)),
		array('select', 'rtopics_ratio', array(
			'wide' => $txt['rtopics_ratio_wide'],
			'classic' => $txt['rtopics_ratio_classic'],
			'square' => $txt['rtopics_ratio_square'],
		)),
		array('select', 'rtopics_hover', array(
			'lift' => $txt['rtopics_hover_lift'],
			'glow' => $txt['rtopics_hover_glow'],
			'zoom' => $txt['rtopics_hover_zoom'],
			'none' => $txt['rtopics_hover_none'],
		)),
		array('select', 'rtopics_mobile', array(
			'scroll' => $txt['rtopics_mobile_scroll'],
			'stack' => $txt['rtopics_mobile_stack'],
		)),
		array('text', 'rtopics_accent', 'size' => 10, 'subtext' => $txt['rtopics_accent_sub']),
		array('check', 'rtopics_animate'),

		array('title', 'rtopics_section_content'),
		array('check', 'rtopics_show_image', 'subtext' => $txt['rtopics_show_image_sub']),
		array('check', 'rtopics_show_board'),
		array('check', 'rtopics_show_excerpt'),
		array('int', 'rtopics_excerpt_length', 'min' => 30, 'max' => 400, 'postinput' => $txt['rtopics_characters']),
		array('int', 'rtopics_title_length', 'min' => 20, 'max' => 200, 'postinput' => $txt['rtopics_characters']),
		array('check', 'rtopics_show_meta'),
		array('check', 'rtopics_new_window'),
	);

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=relatedtopics';
	$context['settings_title'] = $txt['rtopics_admin_title'];

	if (isset($_GET['save']))
	{
		checkSession();

		// Tidy free-text values before saving.
		$_POST['rtopics_accent'] = rtopics_sanitize_color(isset($_POST['rtopics_accent']) ? $_POST['rtopics_accent'] : '');

		$boards = array();
		$raw_boards = isset($_POST['rtopics_exclude_boards']) && is_string($_POST['rtopics_exclude_boards']) ? $_POST['rtopics_exclude_boards'] : '';
		foreach (explode(',', $raw_boards) as $part)
		{
			$part = trim($part);
			if ($part !== '' && ctype_digit($part) && (int) $part > 0 && (string) (int) $part === ltrim($part, '0'))
				$boards[(int) $part] = (int) $part;
		}
		$_POST['rtopics_exclude_boards'] = implode(',', $boards);

		saveDBSettings($config_vars);
		$_SESSION['adm-save'] = true;

		redirectexit('action=admin;area=modsettings;sa=relatedtopics');
	}

	prepareDBSettingContext($config_vars);
}
