<?php
/**
 * YouTube Video Card for SMF 2.1
 *
 * Upgrades the existing [youtube] BBCode tag. The editor's YouTube button keeps working as it
 * does now (it turns a pasted link into [youtube]VIDEO_ID[/youtube]); this mod changes what the
 * tag displays: the video player, followed by the title, description and top comments.
 *
 * The description and comments come from the YouTube Data API v3 (needs an API key in
 * Admin > Configuration > Modification Settings > YouTube Video Card). They are fetched by the
 * browser from this forum (never from Google directly), cached in the database, and the API key
 * never leaves the server.
 * @license ISC - Bryan "BoredSkull" Deakin
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * integrate_load_theme: language, CSS and JS.
 */
function ytcard_load_theme()
{
	global $modSettings, $txt, $context, $scripturl;

	loadLanguage('YtCard');

	if (empty($modSettings['ytcard_enabled']))
		return;

	loadCSSFile('ytcard.css', array('default_theme' => true, 'minimize' => true), 'ytcard_css');
	loadJavaScriptFile('ytcard.js', array('default_theme' => true, 'defer' => true, 'minimize' => true), 'ytcard_js');

	$keys = array(
		'loading' => 'ytcard_loading',
		'watch' => 'ytcard_watch_on_youtube',
		'more_on_youtube' => 'ytcard_more_on_youtube',
		'show_more' => 'ytcard_show_more',
		'show_less' => 'ytcard_show_less',
		'comments' => 'ytcard_comments',
		'all_comments' => 'ytcard_all_comments',
		'no_comments' => 'ytcard_no_comments',
		'comments_disabled' => 'ytcard_comments_disabled',
		'comments_error' => 'ytcard_comments_error',
		'views' => 'ytcard_views',
		'likes' => 'ytcard_likes',
		'replies' => 'ytcard_replies',
	);

	$strings = array();
	foreach ($keys as $js_key => $txt_key)
		$strings[$js_key] = isset($txt[$txt_key]) ? $txt[$txt_key] : '';

	$cfg = array(
		'url' => $scripturl . '?action=ytcard;sa=data',
		'sessVar' => $context['session_var'],
		'sessId' => $context['session_id'],
		'avatars' => !empty($modSettings['ytcard_show_avatars']),
		'txt' => $strings,
	);

	addJavaScriptVar('ytcard_cfg', json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
}
/** Got to add some copyrights **/

function boredskull_credits()
{
	global $context, $txt;

	loadLanguage('YtCard');

	$context['credits_modifications'][] = sprintf($txt['boredskull_credits'], '1.0.1');
}
/**
 * integrate_bbc_codes: take over the existing [youtube] tag.
 *
 * The tag keeps its name and its content format (the video ID, or a link), so posts made with the
 * editor's YouTube button, and every post made before this mod was installed, now render as cards.
 */
function ytcard_bbc_codes(&$codes, &$no_autolink_tags = array(), &$disabled = array())
{
	global $modSettings;

	if (empty($modSettings['ytcard_enabled']))
		return;

	$card = array(
		'tag' => 'youtube',
		'type' => 'unparsed_content',
		'content' => '$1',
		'validate' => 'ytcard_bbc_validate',
		'block_level' => true,
		'disabled_content' => '<a href="https://www.youtube.com/watch?v=$1" target="_blank" rel="noopener noreferrer">https://www.youtube.com/watch?v=$1</a>',
	);

	// Replace the existing definition in place, so the tag keeps its position (and priority).
	foreach ($codes as $i => $code)
	{
		if (isset($code['tag']) && $code['tag'] === 'youtube')
		{
			// Keep whatever the forum already shows when the tag is switched off in the BBC settings.
			if (isset($code['disabled_content']))
				$card['disabled_content'] = $code['disabled_content'];

			$codes[$i] = $card;
			return;
		}
	}

	$codes[] = $card;
}

/**
 * Validate callback for [youtube]: turns the tag content into the card markup.
 */
function ytcard_bbc_validate(&$tag, &$data, $disabled, $params = null)
{
	$raw = (string) $data;

	// If something already turned the link into an anchor, use its target.
	if (preg_match('~href="([^"]+)"~i', $raw, $m))
		$raw = $m[1];

	$raw = trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br />', '<br/>'), ' ', $raw)), ENT_QUOTES, 'UTF-8'));

	$video = ytcard_parse_video($raw);

	// Not something we recognise: leave the content alone.
	if ($video === false)
		return;

	$data = ytcard_render_card($video['id'], $video['start']);
}

/**
 * integrate_actions: the data endpoint used by the cards.
 */
function ytcard_actions(&$actionArray)
{
	$actionArray['ytcard'] = array('YtCard.php', 'YtCard_Action');
}

/**
 * integrate_admin_areas: a tab under Modification Settings.
 */
function ytcard_admin_areas(&$admin_areas)
{
	global $txt;

	$admin_areas['config']['areas']['modsettings']['subsections']['ytcard'] = array($txt['ytcard_title']);
}

/**
 * integrate_modify_modifications
 */
function ytcard_modify_modifications(&$subActions)
{
	$subActions['ytcard'] = 'ytcard_settings';
}

/**
 * The settings page.
 */
function ytcard_settings($return_config = false)
{
	global $txt, $scripturl, $context, $smcFunc;

	$config_vars = array(
		array('desc', 'ytcard_settings_desc'),
		array('check', 'ytcard_enabled'),
		array('text', 'ytcard_api_key', 'size' => 45, 'subtext' => $txt['ytcard_api_key_sub']),
		'',
		array('check', 'ytcard_show_description'),
		array('check', 'ytcard_show_comments'),
		array('check', 'ytcard_show_avatars', 'subtext' => $txt['ytcard_show_avatars_sub']),
		array('int', 'ytcard_comment_count', 'min' => 1, 'max' => 100, 'subtext' => $txt['ytcard_comment_count_sub']),
		array('select', 'ytcard_comment_order', array(
			'relevance' => $txt['ytcard_order_relevance'],
			'time' => $txt['ytcard_order_time'],
		)),
		array('int', 'ytcard_cache_hours', 'min' => 1, 'max' => 720, 'postinput' => $txt['ytcard_hours'], 'subtext' => $txt['ytcard_cache_hours_sub']),
	);

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=ytcard';
	$context['settings_title'] = $txt['ytcard_title'];
	$context['page_title'] = $txt['ytcard_title'];

	if (isset($_GET['save']))
	{
		checkSession();

		if (isset($_POST['ytcard_api_key']))
			$_POST['ytcard_api_key'] = preg_replace('~[^A-Za-z0-9_-]~', '', (string) $_POST['ytcard_api_key']);

		saveDBSettings($config_vars);

		// Anything cached was fetched with the old settings.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ytcard_cache',
			array()
		);

		$_SESSION['adm-save'] = true;
		redirectexit('action=admin;area=modsettings;sa=ytcard');
	}

	prepareDBSettingContext($config_vars);
}

/**
 * action=ytcard;sa=data;v=VIDEO_ID;s=SIGNATURE - returns the video details and comments as JSON.
 */
function YtCard_Action()
{
	global $modSettings;

	loadLanguage('YtCard');

	if (empty($modSettings['ytcard_enabled']))
		ytcard_send_json(array('ok' => false, 'error' => 'disabled'), 404);

	// Only pages generated by this forum carry a valid session token.
	$error = checkSession('get', '', false);
	if (!empty($error))
		ytcard_send_json(array('ok' => false, 'error' => 'session'), 403);

	$id = isset($_GET['v']) && is_string($_GET['v']) ? $_GET['v'] : '';
	$sig = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';

	// The signature proves the ID came out of a post rendered by this forum, so the endpoint can't
	// be used to spend the API quota on arbitrary videos.
	if (!preg_match('~^[A-Za-z0-9_-]{11}$~', $id) || !ytcard_check_sig($id, $sig))
		ytcard_send_json(array('ok' => false, 'error' => 'invalid'), 400);

	$data = ytcard_get_data($id);

	if (empty($data['ok']))
		ytcard_send_json($data, 503);

	if (empty(ytcard_setting('ytcard_show_description', 1)) && is_array($data['video']))
		$data['video']['description'] = '';

	if (empty(ytcard_setting('ytcard_show_comments', 1)))
	{
		$data['comments'] = array();
		$data['comments_status'] = 'off';
	}
	elseif (empty(ytcard_setting('ytcard_show_avatars', 1)))
	{
		foreach ($data['comments'] as $k => $comment)
			$data['comments'][$k]['avatar'] = '';
	}

	ytcard_send_json($data, 200, true);
}

/**
 * Output JSON and stop.
 */
function ytcard_send_json($data, $status = 200, $cacheable = false)
{
	while (ob_get_level() > 0)
		@ob_end_clean();

	if ($status != 200)
		http_response_code($status);

	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE'))
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;

	header('Content-Type: application/json; charset=UTF-8');
	header('X-Content-Type-Options: nosniff');
	header($cacheable ? 'Cache-Control: private, max-age=300' : 'Cache-Control: no-store');

	echo json_encode($data, $flags);

	obExit(false);
}

/**
 * Returns a setting, or a default when it hasn't been saved yet.
 */
function ytcard_setting($name, $default = '')
{
	global $modSettings;

	return isset($modSettings[$name]) ? $modSettings[$name] : $default;
}

/**
 * The API key, reduced to the characters Google uses.
 */
function ytcard_api_key()
{
	return preg_replace('~[^A-Za-z0-9_-]~', '', (string) ytcard_setting('ytcard_api_key', ''));
}

/**
 * Per-forum secret used to sign video IDs. Created on install, or lazily if missing.
 */
function ytcard_secret()
{
	global $modSettings;

	if (empty($modSettings['ytcard_secret']))
	{
		$secret = bin2hex(function_exists('random_bytes') ? random_bytes(16) : md5(uniqid(mt_rand(), true), true));
		updateSettings(array('ytcard_secret' => $secret));
		$modSettings['ytcard_secret'] = $secret;
	}

	return $modSettings['ytcard_secret'];
}

function ytcard_sign($id)
{
	return substr(hash_hmac('sha256', $id, ytcard_secret()), 0, 20);
}

function ytcard_check_sig($id, $sig)
{
	$expected = ytcard_sign($id);

	return function_exists('hash_equals') ? hash_equals($expected, $sig) : $expected === $sig;
}

/**
 * Pulls the video ID (and an optional start time) out of what was inside the [youtube] tag.
 *
 * Accepts what the editor button produces (the bare 11 character ID) as well as full links:
 * watch?v=, youtu.be/, /embed/, /shorts/, /live/ on youtube.com, m.youtube.com, music.youtube.com
 * and youtube-nocookie.com.
 *
 * @return array|bool array('id' => string, 'start' => int), or false
 */
function ytcard_parse_video($raw)
{
	$raw = trim($raw);

	if ($raw === '')
		return false;

	if (preg_match('~^[A-Za-z0-9_-]{11}$~', $raw))
		return array('id' => $raw, 'start' => 0);

	if (!preg_match('~^(?:https?:)?//~i', $raw))
		$raw = 'https://' . $raw;

	$parts = @parse_url($raw);
	if (empty($parts['host']))
		return false;

	$host = preg_replace('~^(?:www|m|music)\.~', '', strtolower($parts['host']));
	$path = isset($parts['path']) ? trim($parts['path'], '/') : '';
	$segments = $path === '' ? array() : explode('/', $path);

	$query = array();
	if (!empty($parts['query']))
		parse_str($parts['query'], $query);

	$id = '';

	if ($host === 'youtu.be')
		$id = isset($segments[0]) ? $segments[0] : '';
	elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com')
	{
		if (isset($segments[0]) && $segments[0] === 'watch')
			$id = isset($query['v']) && is_string($query['v']) ? $query['v'] : '';
		elseif (isset($segments[0], $segments[1]) && in_array($segments[0], array('embed', 'shorts', 'live', 'v'), true))
			$id = $segments[1];
	}

	if (!preg_match('~^[A-Za-z0-9_-]{11}$~', $id))
		return false;

	$start = 0;
	if (isset($query['t']) && is_string($query['t']))
		$start = ytcard_parse_time($query['t']);
	elseif (isset($query['start']) && is_string($query['start']))
		$start = ytcard_parse_time($query['start']);
	elseif (!empty($parts['fragment']) && preg_match('~(?:^|&)t=([^&]+)~', $parts['fragment'], $m))
		$start = ytcard_parse_time($m[1]);

	return array('id' => $id, 'start' => $start);
}

/**
 * "90", "90s" or "1h2m3s" to seconds.
 */
function ytcard_parse_time($value)
{
	$value = trim($value);

	if ($value === '')
		return 0;

	if (ctype_digit($value))
		return min((int) $value, 86399);

	if (preg_match('~^(?:(\d{1,2})h)?(?:(\d{1,3})m)?(?:(\d{1,5})s?)?$~i', $value, $m))
	{
		$seconds = (isset($m[1]) ? (int) $m[1] * 3600 : 0) + (isset($m[2]) ? (int) $m[2] * 60 : 0) + (isset($m[3]) ? (int) $m[3] : 0);

		return min($seconds, 86399);
	}

	return 0;
}

/**
 * The card markup. Everything here is built from a validated ID, so there is nothing user
 * supplied to escape. It is deliberately one line: SMF would turn newlines into <br>.
 *
 * Without JavaScript (or if the data endpoint is down) the visitor still gets the player and a
 * link to the video; ytcard.js fills in the rest.
 */
function ytcard_render_card($id, $start)
{
	global $txt, $smcFunc;

	$label = $smcFunc['htmlspecialchars'](isset($txt['ytcard_watch_on_youtube']) ? $txt['ytcard_watch_on_youtube'] : 'Watch on YouTube');

	$watch = 'https://www.youtube.com/watch?v=' . $id . ($start > 0 ? '&amp;t=' . $start . 's' : '');
	$embed = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0' . ($start > 0 ? '&amp;start=' . $start : '');

	return '<div class="ytcard" data-ytcard="' . $id . '" data-sig="' . ytcard_sign($id) . '">'
		. '<div class="ytcard_player"><iframe src="' . $embed . '" title="' . $label . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>'
		. '<div class="ytcard_body"><a class="ytcard_fallback bbc_link" href="' . $watch . '" target="_blank" rel="noopener noreferrer nofollow">' . $label . '</a></div>'
		. '</div>';
}

/**
 * Video details and comments, from the cache when possible.
 */
function ytcard_get_data($id)
{
	global $smcFunc;

	$ttl = max(1, min(720, (int) ytcard_setting('ytcard_cache_hours', 6))) * 3600;
	$now = time();

	$request = $smcFunc['db_query']('', '
		SELECT fetched, is_error, data
		FROM {db_prefix}ytcard_cache
		WHERE id_video = {string:id}',
		array(
			'id' => $id,
		)
	);
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	$cached = null;
	if (!empty($row))
	{
		$cached = json_decode($row['data'], true);

		// Failed lookups are retried after ten minutes, good ones after the cache time.
		$max_age = !empty($row['is_error']) ? 600 : $ttl;

		if (is_array($cached) && $now - (int) $row['fetched'] < $max_age)
			return $cached;
	}

	// Global brake on calls to Google: at most 40 fresh lookups a minute.
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(*)
		FROM {db_prefix}ytcard_cache
		WHERE fetched > {int:since}',
		array(
			'since' => $now - 60,
		)
	);
	list ($recent) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	if ($recent >= 40)
		return is_array($cached) ? $cached : array('ok' => false, 'error' => 'busy');

	$is_error = false;
	$data = ytcard_fetch_remote($id, $is_error);

	// Google didn't answer: keep showing the old copy, and look again in ten minutes.
	if ($is_error && empty($data['video']) && is_array($cached) && empty($row['is_error']))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}ytcard_cache
			SET fetched = {int:fetched}
			WHERE id_video = {string:id}',
			array(
				'id' => $id,
				'fetched' => $now - $ttl + 600,
			)
		);

		return $cached;
	}

	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	if (defined('JSON_INVALID_UTF8_SUBSTITUTE'))
		$flags |= JSON_INVALID_UTF8_SUBSTITUTE;

	$json = json_encode($data, $flags);
	if ($json !== false)
	{
		$smcFunc['db_insert']('replace',
			'{db_prefix}ytcard_cache',
			array('id_video' => 'string-16', 'fetched' => 'int', 'is_error' => 'int', 'data' => 'string'),
			array($id, $now, $is_error ? 1 : 0, $json),
			array('id_video')
		);
	}

	// Housekeeping: drop anything that hasn't been refreshed for a month.
	if (mt_rand(1, 50) === 1)
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}ytcard_cache
			WHERE fetched < {int:old}',
			array(
				'old' => $now - 30 * 86400,
			)
		);
	}

	return $data;
}

/**
 * Asks YouTube for the video and its comments.
 *
 * @param string $id
 * @param bool $is_error Set when YouTube could not be reached or refused the request.
 * @return array
 */
function ytcard_fetch_remote($id, &$is_error)
{
	$is_error = false;
	$key = ytcard_api_key();

	$data = array(
		'ok' => true,
		'has_api' => $key !== '',
		'video' => null,
		'comments_status' => 'unavailable',
		'comments' => array(),
	);

	if ($key !== '')
	{
		$json = ytcard_api('videos', array('part' => 'snippet,statistics', 'id' => $id, 'key' => $key), $status, $reason);

		if ($status == 200 && is_array($json))
		{
			if (!empty($json['items'][0]) && is_array($json['items'][0]))
				$data['video'] = ytcard_build_video($json['items'][0]);
			else
				$data['not_found'] = true;
		}
		else
			$is_error = true;

		if (!empty($data['video']))
		{
			if (ytcard_setting('ytcard_show_comments', 1))
				ytcard_fetch_comments($id, $key, $data, $is_error);
			else
				$data['comments_status'] = 'off';
		}
	}

	// No key, or the API refused: the oEmbed endpoint needs no key and still gives us a title.
	if (empty($data['video']) && empty($data['not_found']))
	{
		$body = ytcard_http_get('https://www.youtube.com/oembed?' . http_build_query(array(
			'format' => 'json',
			'url' => 'https://www.youtube.com/watch?v=' . $id,
		), '', '&'), $status);
		$oembed = is_string($body) ? json_decode($body, true) : null;

		if ($status == 200 && is_array($oembed))
		{
			$data['video'] = array(
				'title' => ytcard_clean(ytcard_arr($oembed, array('title')), 300),
				'channel' => ytcard_clean(ytcard_arr($oembed, array('author_name')), 200),
				'channel_url' => ytcard_youtube_url(ytcard_arr($oembed, array('author_url'))),
				'published' => '',
				'description' => '',
				'views' => null,
				'likes' => null,
				'comment_count' => null,
			);
		}
		elseif ($key === '')
			$is_error = true;
	}

	return $data;
}

function ytcard_fetch_comments($id, $key, &$data, &$is_error)
{
	$count = max(1, min(100, (int) ytcard_setting('ytcard_comment_count', 20)));
	$order = ytcard_setting('ytcard_comment_order', 'relevance') === 'time' ? 'time' : 'relevance';

	$json = ytcard_api('commentThreads', array(
		'part' => 'snippet',
		'videoId' => $id,
		'maxResults' => $count,
		'order' => $order,
		'textFormat' => 'plainText',
		'key' => $key,
	), $status, $reason, array('commentsDisabled'));

	if ($status == 200 && is_array($json))
	{
		$items = isset($json['items']) && is_array($json['items']) ? $json['items'] : array();

		foreach ($items as $item)
		{
			$text = ytcard_clean(ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'textDisplay')), 1500);
			if ($text === '')
				continue;

			$data['comments'][] = array(
				'author' => ytcard_clean(ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'authorDisplayName')), 100),
				'author_url' => ytcard_youtube_url(ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'authorChannelUrl'))),
				'avatar' => ytcard_image_url(ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'authorProfileImageUrl'))),
				'text' => $text,
				'likes' => (int) ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'likeCount'), 0),
				'published' => ytcard_clean(ytcard_arr($item, array('snippet', 'topLevelComment', 'snippet', 'publishedAt')), 40),
				'replies' => (int) ytcard_arr($item, array('snippet', 'totalReplyCount'), 0),
			);
		}

		$data['comments_status'] = empty($data['comments']) ? 'none' : 'ok';
	}
	elseif ($reason === 'commentsDisabled')
		$data['comments_status'] = 'disabled';
	else
	{
		$data['comments_status'] = 'error';
		$is_error = true;
	}
}

/**
 * Calls the YouTube Data API. Failures are written to the SMF error log (never with the key).
 *
 * @return array|null Decoded response, or null.
 */
function ytcard_api($endpoint, $params, &$status, &$reason, $quiet_reasons = array())
{
	$status = 0;
	$reason = '';

	$body = ytcard_http_get('https://www.googleapis.com/youtube/v3/' . $endpoint . '?' . http_build_query($params, '', '&'), $status);
	$json = is_string($body) ? json_decode($body, true) : null;

	if ($status != 200)
	{
		$reason = preg_replace('~[^A-Za-z]~', '', (string) ytcard_arr($json, array('error', 'errors', 0, 'reason'), ''));

		if (!in_array($reason, $quiet_reasons, true))
			log_error('YouTube Video Card: the ' . $endpoint . ' request failed (HTTP ' . (int) $status . ($reason !== '' ? ', ' . $reason : '') . ').', 'general');
	}

	return is_array($json) ? $json : null;
}

function ytcard_build_video($item)
{
	$channel_id = (string) ytcard_arr($item, array('snippet', 'channelId'));

	return array(
		'title' => ytcard_clean(ytcard_arr($item, array('snippet', 'title')), 300),
		'channel' => ytcard_clean(ytcard_arr($item, array('snippet', 'channelTitle')), 200),
		'channel_url' => preg_match('~^[A-Za-z0-9_-]{10,40}$~', $channel_id) ? 'https://www.youtube.com/channel/' . $channel_id : '',
		'published' => ytcard_clean(ytcard_arr($item, array('snippet', 'publishedAt')), 40),
		'description' => ytcard_clean(ytcard_arr($item, array('snippet', 'description')), 5000),
		'views' => ytcard_count(ytcard_arr($item, array('statistics', 'viewCount'), null)),
		'likes' => ytcard_count(ytcard_arr($item, array('statistics', 'likeCount'), null)),
		'comment_count' => ytcard_count(ytcard_arr($item, array('statistics', 'commentCount'), null)),
	);
}

/**
 * Safe nested array read.
 */
function ytcard_arr($array, $path, $default = '')
{
	foreach ((array) $path as $key)
	{
		if (!is_array($array) || !isset($array[$key]))
			return $default;

		$array = $array[$key];
	}

	return $array;
}

function ytcard_count($value)
{
	return is_numeric($value) ? (int) $value : null;
}

/**
 * Plain text only: no control characters, bounded length.
 */
function ytcard_clean($string, $max_length)
{
	global $smcFunc;

	$string = is_string($string) ? $string : '';
	$string = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', '', str_replace("\r\n", "\n", $string));

	if ($smcFunc['strlen']($string) > $max_length)
		$string = $smcFunc['substr']($string, 0, $max_length) . "\xE2\x80\xA6";

	return $string;
}

/**
 * Only links to YouTube channels are passed on.
 */
function ytcard_youtube_url($url)
{
	return is_string($url) && preg_match('~^https://(?:www\.)?youtube\.com/[A-Za-z0-9@_./%-]*$~', $url) ? $url : '';
}

/**
 * Only profile pictures hosted by Google are passed on.
 */
function ytcard_image_url($url)
{
	return is_string($url) && preg_match('~^https://[a-z0-9.-]+\.(?:ggpht|googleusercontent)\.com/[^\s"\'<>]*$~i', $url) ? $url : '';
}

/**
 * Minimal HTTPS GET to a fixed Google host. Uses cURL when present, otherwise fopen.
 *
 * @param string $url
 * @param int $status HTTP status, or 0 if there was no response.
 * @return string|bool The body, or false.
 */
function ytcard_http_get($url, &$status = 0)
{
	global $boardurl;

	$status = 0;
	$agent = 'SMF-YouTubeVideoCard/1.0';

	if (function_exists('curl_init'))
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 4,
			CURLOPT_TIMEOUT => 8,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_USERAGENT => $agent,
			// Lets keys that are restricted by website address work too.
			CURLOPT_REFERER => $boardurl . '/',
			CURLOPT_HTTPHEADER => array('Accept: application/json'),
		));

		if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS'))
			curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);

		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		unset($ch);

		return is_string($body) ? $body : false;
	}

	if (ini_get('allow_url_fopen'))
	{
		$context = stream_context_create(array(
			'http' => array(
				'method' => 'GET',
				'timeout' => 8,
				'ignore_errors' => true,
				'follow_location' => 0,
				'header' => "Accept: application/json\r\nUser-Agent: " . $agent . "\r\nReferer: " . $boardurl . "/\r\n",
			),
		));

		$body = @file_get_contents($url, false, $context);

		if (isset($http_response_header[0]) && preg_match('~^HTTP/\S+\s+(\d{3})~', $http_response_header[0], $m))
			$status = (int) $m[1];

		return is_string($body) ? $body : false;
	}

	return false;
}
