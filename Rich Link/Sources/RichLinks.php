<?php

/**
 * Rich Link Previews for SMF 2.1
 *
 * Turns plain URLs in posts into preview cards (title, description and
 * thumbnail). Metadata is fetched once when a post is saved and stored,
 * so page views never trigger remote requests.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.3
 */

if (!defined('SMF'))
	die('No direct access...');

class RichLinks
{
	const VERSION = '1.0.3';

	/** Redirect hops followed (each one is re-validated). */
	const MAX_REDIRECTS = 3;

	/** Total seconds allowed for fetching while a post is being submitted. */
	const SYNC_BUDGET = 10;

	/** Total seconds allowed per post when fetching in the background. */
	const BACKGROUND_BUDGET = 45;

	/** Seconds before a failed URL is tried again. */
	const FAIL_RETRY = 86400;

	/** File types that never have an HTML page worth previewing. */
	const SKIP_EXTENSIONS = 'jpe?g|png|gif|webp|bmp|svg|avif|ico|tiff?|mp4|m4v|webm|mov|mkv|avi|mp3|m4a|ogg|oga|wav|flac|pdf|zip|rar|7z|gz|tgz|bz2|xz|tar|exe|msi|dmg|apk|iso|bin|torrent';

	/**
	 * Always excluded. YouTube is handled by SMF's built-in embedding
	 * and the YouTube Video Card mod.
	 */
	protected static $always_excluded = array(
		'youtube.com',
		'youtu.be',
		'youtube-nocookie.com',
		'youtubekids.com',
		'youtubeeducation.com',
	);

	/** BBCode whose contents must never produce a preview. */
	protected static $ignored_tags = array(
		'quote', 'code', 'php', 'nobbc', 'html', 'img',
		'email', 'ftp', 'youtube', 'spoiler', 'hide',
	);

	protected static $excluded_cache = null;

	/* ------------------------------------------------------------------
	 * Hooks: posting
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_after_create_post
	 */
	public static function afterCreatePost($msgOptions, $topicOptions = array(), $posterOptions = array(), $message_columns = array(), $message_parameters = array())
	{
		if (empty($msgOptions['id']))
			return;

		self::handle((int) $msgOptions['id'], isset($msgOptions['body']) ? $msgOptions['body'] : '');
	}

	/**
	 * integrate_modify_post
	 */
	public static function modifyPost(&$messages_columns, &$update_parameters, &$msgOptions, &$topicOptions, &$posterOptions, &$messageInts)
	{
		if (empty($msgOptions['id']) || !isset($msgOptions['body']))
			return;

		self::handle((int) $msgOptions['id'], $msgOptions['body']);
	}

	/**
	 * integrate_remove_message
	 */
	public static function removeMessage($message, $row = array(), $recycle = false)
	{
		// A recycled message keeps its ID, so keep its previews too.
		if (!$recycle)
			self::deleteForMessages(array((int) $message));
	}

	/**
	 * Decide whether to fetch now, queue a background task, or clear old previews.
	 */
	protected static function handle($id_msg, $body)
	{
		global $modSettings;

		if (empty($modSettings['richlinks_enabled']) || $id_msg <= 0)
			return;

		if (self::extractUrls($body) === array())
		{
			self::deleteForMessages(array($id_msg));
			return;
		}

		if (!empty($modSettings['richlinks_background']))
			self::queue($id_msg);
		else
		{
			self::processMessage($id_msg, $body, self::SYNC_BUDGET);

			// The weekly clean-up runs in the background task, which is never
			// queued when background fetching is off, so queue one on its own.
			if (empty($modSettings['richlinks_last_prune']) || $modSettings['richlinks_last_prune'] < time() - 604800)
			{
				updateSettings(array('richlinks_last_prune' => time()));
				self::queue(0);
			}
		}
	}

	/**
	 * Add a background task. cron.php picks it up a few seconds later.
	 */
	protected static function queue($id_msg)
	{
		global $smcFunc;

		$smcFunc['db_insert']('insert',
			'{db_prefix}background_tasks',
			array('task_file' => 'string-255', 'task_class' => 'string-255', 'task_data' => 'string', 'claimed_time' => 'int'),
			array('$sourcedir/tasks/RichLinks-Fetch.php', 'RichLinks_Fetch_Background', $smcFunc['json_encode'](array('id_msg' => (int) $id_msg)), 0),
			array('id_task')
		);
	}

	/**
	 * Build and store the previews for a single message.
	 *
	 * @param int $id_msg
	 * @param string|null $body Preparsed body, or null to read it from the database.
	 * @param int $budget Total seconds allowed for remote fetching.
	 */
	public static function processMessage($id_msg, $body = null, $budget = self::BACKGROUND_BUDGET)
	{
		global $smcFunc;

		$id_msg = (int) $id_msg;
		$loaded_from_database = $body === null;

		if ($loaded_from_database)
		{
			$request = $smcFunc['db_query']('', '
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array('id_msg' => $id_msg)
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (empty($row))
			{
				self::deleteForMessages(array($id_msg));
				return;
			}

			$body = $row['body'];
		}

		$urls = self::extractUrls($body);
		$max = self::maxPerPost();
		$deadline = microtime(true) + $budget;
		$rows = array();
		$position = 0;
		$retry = false;

		foreach ($urls as $url)
		{
			if ($position >= $max)
				break;

			$link = self::classify($url);
			if ($link === false)
				continue;

			if ($link['type'] === 'web')
			{
				$hash = sha1($url);

				// Keep a basic card even when a site blocks our request, times out,
				// or has no usable Open Graph metadata. Do not fetch beyond budget.
				if (microtime(true) >= $deadline || self::ensureMetadata($url, $hash, $deadline) === null)
					$retry = true;

				$rows[] = array($id_msg, $position++, 'web', 0, $hash, $url);
			}
			else
				$rows[] = array($id_msg, $position++, $link['type'], $link['id'], '', $url);
		}

		// An older background job must not overwrite cards for a post edited
		// while its remote metadata was being downloaded. A subsequent edit job
		// will use the latest saved body.
		if ($loaded_from_database)
		{
			$request = $smcFunc['db_query']('', '
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array('id_msg' => $id_msg)
			);
			$current = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			if (empty($current))
			{
				self::deleteForMessages(array($id_msg));
				return;
			}
			if ($current['body'] !== $body)
				return;
		}

		self::deleteForMessages(array($id_msg));

		if (!empty($rows))
			$smcFunc['db_insert']('replace',
				'{db_prefix}rich_links_msg',
				array('id_msg' => 'int', 'position' => 'int', 'link_type' => 'string-8', 'ref_id' => 'int', 'url_hash' => 'string-40', 'url' => 'string'),
				$rows,
				array('id_msg', 'position')
			);

		// Links that ran out of time get another go in the background. Every
		// run looks up at least one new link (results are cached, failures
		// included), so this always finishes.
		if ($retry)
			self::queue($id_msg);
	}

	public static function deleteForMessages(array $ids)
	{
		global $smcFunc;

		$ids = array_filter(array_map('intval', $ids));
		if (empty($ids))
			return;

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}rich_links_msg
			WHERE id_msg IN ({array_int:ids})',
			array('ids' => $ids)
		);
	}

	/* ------------------------------------------------------------------
	 * URL detection
	 * ---------------------------------------------------------------- */

	/**
	 * Find the plain URLs in a preparsed post body, skipping anything
	 * inside quotes, code, existing [url] tags and so on.
	 *
	 * @return array Unique URLs in the order they appear (fragments removed).
	 */
	public static function extractUrls($body)
	{
		$body = (string) $body;
		if ($body === '' || (stripos($body, 'http') === false && stripos($body, 'www.') === false))
			return array();

		$text = self::stripBlocks($body, self::$ignored_tags);
		if ($text === null)
			return array();

		// SMF's editor may store a pasted hyperlink as [url]...[/url] or
		// [url=https://example.com]label[/url]. Extract its actual destination
		// BEFORE removing BBCode attributes. Excluded quote/code/etc. blocks
		// have already been removed, so their links still won't be previewed.
		$text = preg_replace_callback('~\[(url|iurl)(?:\s*=\s*([^\]]+))?\](.*?)\[/\1\]~is', function ($m)
		{
			$target = !empty($m[2]) ? trim($m[2]) : trim($m[3]);
			return trim($target, " \t\n\r\0\x0B\"'");
		}, $text);
		if ($text === null)
			return array();

		// Opening tags with attributes, e.g. an unclosed [url=...] or [color=...].
		$text = preg_replace('~\[[a-z][a-z0-9]*(?:=[^\]]*|\s[^\]]*)\]~i', ' ', $text);
		$text = preg_replace('~<br\s*/?>~i', ' ', $text);
		$text = strtr($text, array(
			'&nbsp;' => ' ', '&#160;' => ' ', '&quot;' => '"', '&#039;' => '\'',
			'&#39;' => '\'', '&lt;' => '<', '&gt;' => '>', '&amp;' => '&',
		));

		$pattern = '~(?<![\w/=@.-])(?:https?://|www\.)[^\s<>\[\]"\'`{}|\\\\^]+~i';
		if (!preg_match_all($pattern . 'u', $text, $matches) && !preg_match_all($pattern, $text, $matches))
			return array();

		$urls = array();
		foreach ($matches[0] as $url)
		{
			// Trailing punctuation that belongs to the sentence, not the link.
			do
			{
				$before = $url;
				$url = rtrim($url, '.,;:!?*\'"');
				if (substr($url, -1) === ')' && substr_count($url, '(') < substr_count($url, ')'))
					$url = substr($url, 0, -1);
			}
			while ($url !== $before);

			if (stripos($url, 'www.') === 0)
				$url = 'http://' . $url;

			$url = preg_replace('~#.*$~s', '', $url);

			if (strlen($url) > 2000 || !preg_match('~^https?://[^/?#]+~i', $url))
				continue;

			$host = parse_url($url, PHP_URL_HOST);
			if (empty($host))
				continue;

			$urls[$url] = $url;
		}

		return array_values($urls);
	}

	/**
	 * Remove BBCode blocks (and their contents), innermost first so
	 * nested quotes are handled.
	 *
	 * @return string|null Null on a PCRE failure.
	 */
	public static function stripBlocks($text, array $tags)
	{
		$tag_list = implode('|', array_map('preg_quote', $tags));
		$pattern = '~\[(' . $tag_list . ')(?=[\s=\]])[^\]]*\](?:(?!\[\1[\s=\]]).)*?\[/\1\]~is';

		for ($i = 0; $i < 50; $i++)
		{
			$new = preg_replace($pattern, ' ', $text);
			if ($new === null)
				return null;
			if ($new === $text)
				break;
			$text = $new;
		}

		return $text;
	}

	/**
	 * Decide what kind of preview a URL gets.
	 *
	 * @return array|false array('type' => web|topic|board, 'id' => int) or false to skip.
	 */
	protected static function classify($url)
	{
		global $boardurl, $modSettings;

		$host = self::lowerText((string) parse_url($url, PHP_URL_HOST));
		$host = preg_replace('~^www\.~', '', trim($host, '[].'));
		if ($host === '')
			return false;

		if (self::hostMatches($host, self::$always_excluded) || self::hostMatches($host, self::excludedDomains()))
			return false;

		$path = (string) parse_url($url, PHP_URL_PATH);
		if (preg_match('~\.(?:' . self::SKIP_EXTENSIONS . ')$~i', $path))
			return false;

		$forum_host = preg_replace('~^www\.~', '', self::lowerText((string) parse_url($boardurl, PHP_URL_HOST)));
		if ($forum_host !== '' && $host === $forum_host)
		{
			if (empty($modSettings['richlinks_internal']))
				return false;

			return self::classifyInternal($url);
		}

		return array('type' => 'web', 'id' => 0);
	}

	/**
	 * Links to this forum come from the database, never from an HTTP request.
	 */
	protected static function classifyInternal($url)
	{
		global $smcFunc;

		$query = (string) parse_url($url, PHP_URL_QUERY);
		$haystack = (string) parse_url($url, PHP_URL_PATH) . '?' . $query;

		// Reply links, profile pages, and other actions don't get a preview.
		if (preg_match('~(?:^|[?&;])action=~i', $query))
			return false;

		if (preg_match('~[?&;/]topic[=,](\d+)~i', $haystack, $m))
			return array('type' => 'topic', 'id' => (int) $m[1]);

		if (preg_match('~(?:^|[?&;])msg=(\d+)~i', $query, $m))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array('id_msg' => (int) $m[1])
			);
			list ($id_topic) = $smcFunc['db_fetch_row']($request) ?: array(0);
			$smcFunc['db_free_result']($request);

			return empty($id_topic) ? false : array('type' => 'topic', 'id' => (int) $id_topic);
		}

		if (preg_match('~[?&;/]board[=,](\d+)~i', $haystack, $m))
			return array('type' => 'board', 'id' => (int) $m[1]);

		return false;
	}

	protected static function hostMatches($host, array $domains)
	{
		foreach ($domains as $domain)
		{
			if ($domain === '')
				continue;
			if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain)
				return true;
		}

		return false;
	}

	public static function excludedDomains()
	{
		global $modSettings;

		if (self::$excluded_cache !== null)
			return self::$excluded_cache;

		$domains = array();
		$lines = preg_split('~[\s,]+~', isset($modSettings['richlinks_exclude']) ? (string) $modSettings['richlinks_exclude'] : '');

		foreach ($lines as $line)
		{
			$line = self::lowerText(trim($line));
			$line = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $line);
			$line = preg_replace('~[/?#:].*$~', '', $line);
			$line = preg_replace('~^(?:\*\.|\.|www\.)+~', '', $line);
			$line = trim($line, '.');

			if ($line !== '')
				$domains[$line] = $line;
		}

		return self::$excluded_cache = array_values($domains);
	}

	/* ------------------------------------------------------------------
	 * Metadata cache
	 * ---------------------------------------------------------------- */

	/**
	 * Make sure a usable cache row exists for this URL.
	 *
	 * @return bool|null True if a preview can be shown, null if there was no time left to try.
	 */
	protected static function ensureMetadata($url, $hash, $deadline)
	{
		global $smcFunc;

		$now = time();

		$request = $smcFunc['db_query']('', '
			SELECT status, fetched
			FROM {db_prefix}rich_links_cache
			WHERE url_hash = {string:hash}
			LIMIT 1',
			array('hash' => $hash)
		);
		$cached = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($cached))
		{
			$age = $now - (int) $cached['fetched'];

			if ((int) $cached['status'] === 1 && $age < self::cacheTtl())
				return true;
			if ((int) $cached['status'] === 0 && $age < self::FAIL_RETRY)
				return false;
		}

		$data = self::fetchMetadata($url, $deadline);

		// Out of time: nothing was learned about this URL, so record nothing.
		// Saving it as failed would block every post linking to it for a day.
		if ($data === null)
			return null;

		if ($data === false)
		{
			// Keep a working preview if a refresh fails, and try again later.
			if (!empty($cached) && (int) $cached['status'] === 1)
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}rich_links_cache
					SET fetched = {int:now}
					WHERE url_hash = {string:hash}',
					array('now' => $now, 'hash' => $hash)
				);
				return true;
			}

			$data = array('title' => '', 'description' => '', 'image' => '', 'site_name' => '');
			$status = 0;
		}
		else
			$status = 1;

		$smcFunc['db_insert']('replace',
			'{db_prefix}rich_links_cache',
			array(
				'url_hash' => 'string-40', 'url' => 'string', 'title' => 'string-255', 'description' => 'string',
				'image' => 'string', 'site_name' => 'string-255', 'status' => 'int', 'fetched' => 'int',
			),
			array($hash, $url, $data['title'], $data['description'], $data['image'], $data['site_name'], $status, $now),
			array('url_hash')
		);

		return $status === 1;
	}

	protected static function fetchMetadata($url, $deadline)
	{
		$page = self::fetchPage($url, $deadline);
		if ($page === false || $page === null)
			return $page;

		return self::parseMeta($page['body'], $page['url'], $page['charset']);
	}

	/* ------------------------------------------------------------------
	 * Safe fetching
	 * ---------------------------------------------------------------- */

	/**
	 * Fetch the <head> of an HTML page. Redirects are followed by hand so
	 * every hop goes through the same checks, and each connection is pinned
	 * to the IP address that was checked (this blocks DNS rebinding).
	 *
	 * @return array|false|null array('url' => final URL, 'body' => html, 'charset' => string),
	 *  false when the page can't be previewed, or null when time ran out first.
	 */
	public static function fetchPage($url, $deadline)
	{
		global $boardurl;

		if (!function_exists('curl_init'))
			return false;

		$max_bytes = self::maxBytes();
		$timeout = self::timeout();

		for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++)
		{
			// Out of time before the first request: not tried, so the caller can
			// retry later. Out of time part-way through redirects counts as tried.
			$remaining = $deadline - microtime(true);
			if ($remaining < 1)
				return $hop === 0 ? null : false;

			$url = self::normaliseUrl($url);
			if ($url === false)
				return false;

			$target = self::validateTarget($url);
			if ($target === false)
				return false;

			$state = array('headers' => array(), 'body' => '', 'aborted' => false);
			$limit = (int) max(1, min($timeout, floor($remaining)));

			$options = array(
				CURLOPT_URL => $url,
				CURLOPT_NOPROXY => '*',
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_CONNECTTIMEOUT => $limit,
				CURLOPT_TIMEOUT => $limit,
				CURLOPT_ENCODING => '',
				CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SMF-RichLinks/' . self::VERSION . '; +' . $boardurl . ')',
				CURLOPT_HTTPHEADER => array(
					'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
					'Accept-Language: en-GB,en;q=0.9,*;q=0.5',
				),
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_NOSIGNAL => true,
				CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$state)
				{
					if (stripos($line, 'HTTP/') === 0)
						$state['headers'] = array();
					elseif (strpos($line, ':') !== false)
					{
						list ($name, $value) = explode(':', $line, 2);
						$state['headers'][strtolower(trim($name))] = trim($value);
					}

					return strlen($line);
				},
				CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$state, $max_bytes)
				{
					$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

					// No need for redirect bodies or anything that isn't HTML.
					if (($code >= 300 && $code < 400) || !RichLinks::isHtml($state['headers']))
					{
						$state['aborted'] = true;
						return 0;
					}

					$state['body'] .= $chunk;
					$length = strlen($state['body']);

					if ($length >= $max_bytes)
					{
						$state['body'] = substr($state['body'], 0, $max_bytes);
						$state['aborted'] = true;
						return 0;
					}

					// Everything needed is in <head>.
					if (stripos($state['body'], '</head>', max(0, $length - strlen($chunk) - 7)) !== false)
					{
						$state['aborted'] = true;
						return 0;
					}

					return strlen($chunk);
				},
			);

			if (defined('CURLOPT_PROTOCOLS_STR'))
			{
				$options[CURLOPT_PROTOCOLS_STR] = 'http,https';
				$options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
			}
			else
			{
				$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
				$options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
			}

			if (!empty($target['resolve']))
				$options[CURLOPT_RESOLVE] = array($target['resolve']);

			$ch = curl_init();
			curl_setopt_array($ch, $options);
			curl_exec($ch);
			$errno = curl_errno($ch);
			$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);

			if ($errno !== 0 && !$state['aborted'])
				return false;

			if ($status >= 300 && $status < 400)
			{
				if (empty($state['headers']['location']))
					return false;

				$url = self::resolveUrl($url, $state['headers']['location']);
				if ($url === false)
					return false;

				continue;
			}

			if ($status < 200 || $status >= 300 || $state['body'] === '' || !self::isHtml($state['headers']))
				return false;

			$charset = '';
			if (!empty($state['headers']['content-type']) && preg_match('~charset\s*=\s*["\']?([a-z0-9_.:-]+)~i', $state['headers']['content-type'], $m))
				$charset = $m[1];

			return array('url' => $url, 'body' => $state['body'], 'charset' => $charset);
		}

		return false;
	}

	public static function isHtml(array $headers)
	{
		if (empty($headers['content-type']))
			return true;

		$type = strtolower($headers['content-type']);

		return strpos($type, 'text/html') !== false || strpos($type, 'application/xhtml+xml') !== false;
	}

	/**
	 * Turn a URL into the form cURL needs: an ASCII (punycode) host and
	 * percent-encoded non-ASCII characters.
	 *
	 * @return string|false
	 */
	public static function normaliseUrl($url)
	{
		$parts = parse_url($url);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host']))
			return false;

		$scheme = strtolower($parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https')
			return false;

		if (isset($parts['user']) || isset($parts['pass']))
			return false;

		$host = $parts['host'];
		if (preg_match('~[^\x20-\x7E]~', $host))
		{
			if (!function_exists('idn_to_ascii'))
				return false;

			$host = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
			if ($host === false)
				return false;
		}
		$host = strtolower($host);

		$encode = function ($s)
		{
			return preg_replace_callback('~[^\x21-\x7E]~', function ($m)
			{
				return rawurlencode($m[0]);
			}, $s);
		};

		return $scheme . '://' . $host
			. (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
			. (isset($parts['path']) && $parts['path'] !== '' ? $encode($parts['path']) : '/')
			. (isset($parts['query']) ? '?' . $encode($parts['query']) : '');
	}

	/**
	 * Allow only public hosts on standard web ports.
	 *
	 * @return array|false array('resolve' => cURL resolve entry or '')
	 */
	public static function validateTarget($url)
	{
		$parts = parse_url($url);
		if ($parts === false || empty($parts['scheme']) || empty($parts['host']))
			return false;

		$scheme = strtolower($parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https')
			return false;

		if (isset($parts['user']) || isset($parts['pass']))
			return false;

		$port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
		if (!in_array($port, array(80, 443, 8080, 8443), true))
			return false;

		$host = strtolower($parts['host']);
		$literal = trim($host, '[]');

		if (filter_var($literal, FILTER_VALIDATE_IP))
			return self::isPublicIp($literal) ? array('resolve' => '') : false;

		// A normal DNS name with a real TLD (this rejects "localhost",
		// "2130706433", "0x7f.1", and similar).
		if (!preg_match('~^(?=.{1,253}$)(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$~', $host))
			return false;

		if (preg_match('~(?:^|\.)(?:localhost|local|localdomain|internal|intranet|lan|home|corp|home\.arpa|onion|test|invalid)$~', $host))
			return false;

		$ips = self::resolve($host);
		if (empty($ips))
			return false;

		// Every address must be public, otherwise the host is refused.
		foreach ($ips as $ip)
			if (!self::isPublicIp($ip))
				return false;

		$ip = $ips[0];

		return array('resolve' => $host . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip));
	}

	protected static function resolve($host)
	{
		$ips = @gethostbynamel($host);
		$ips = is_array($ips) ? $ips : array();

		if (function_exists('dns_get_record'))
		{
			$records = @dns_get_record($host, DNS_AAAA);
			if (is_array($records))
				foreach ($records as $record)
					if (!empty($record['ipv6']))
						$ips[] = $record['ipv6'];
		}

		return array_values(array_unique($ips));
	}

	/**
	 * True only for globally routable unicast addresses.
	 */
	public static function isPublicIp($ip)
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		{
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
				return false;

			$blocked = array(
				'0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
				'172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
				'198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
			);

			foreach ($blocked as $cidr)
				if (self::ipInCidr($ip, $cidr))
					return false;

			return true;
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
		{
			$bin = inet_pton($ip);
			if ($bin === false)
				return false;

			// IPv4 embedded in IPv6: check the IPv4 part instead.
			if (self::ipInCidr($ip, '::ffff:0:0/96') || self::ipInCidr($ip, '64:ff9b::/96') || self::ipInCidr($ip, '::/96'))
				return self::isPublicIp(inet_ntop(substr($bin, 12, 4)));
			if (self::ipInCidr($ip, '2002::/16'))
				return self::isPublicIp(inet_ntop(substr($bin, 2, 4)));

			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
				return false;

			$blocked = array(
				'::/128', '::1/128', '100::/64', '2001::/32', '2001:db8::/32', 'fc00::/7',
				'fe80::/10', 'fec0::/10', 'ff00::/8', '64:ff9b:1::/48',
			);

			foreach ($blocked as $cidr)
				if (self::ipInCidr($ip, $cidr))
					return false;

			return true;
		}

		return false;
	}

	public static function ipInCidr($ip, $cidr)
	{
		list ($subnet, $bits) = explode('/', $cidr);

		$ip_bin = @inet_pton($ip);
		$sub_bin = @inet_pton($subnet);
		if ($ip_bin === false || $sub_bin === false || strlen($ip_bin) !== strlen($sub_bin))
			return false;

		$bits = (int) $bits;
		$bytes = intdiv($bits, 8);
		$rem = $bits % 8;

		if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($sub_bin, 0, $bytes))
			return false;

		if ($rem > 0)
		{
			$mask = (0xFF << (8 - $rem)) & 0xFF;
			if ((ord($ip_bin[$bytes]) & $mask) !== (ord($sub_bin[$bytes]) & $mask))
				return false;
		}

		return true;
	}

	/**
	 * Resolve a (possibly relative) URL against a base URL.
	 *
	 * @return string|false
	 */
	public static function resolveUrl($base, $rel)
	{
		$rel = trim((string) $rel);
		if ($rel === '')
			return false;

		if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $rel))
			return preg_match('~^https?://~i', $rel) ? $rel : false;

		$b = parse_url($base);
		if (empty($b['scheme']) || empty($b['host']))
			return false;

		$host = strpos($b['host'], ':') !== false && $b['host'][0] !== '[' ? '[' . $b['host'] . ']' : $b['host'];
		$authority = $b['scheme'] . '://' . $host . (isset($b['port']) ? ':' . $b['port'] : '');
		$base_path = isset($b['path']) && $b['path'] !== '' ? $b['path'] : '/';

		if (substr($rel, 0, 2) === '//')
			return $b['scheme'] . ':' . $rel;
		if ($rel[0] === '#')
			return $authority . $base_path . (isset($b['query']) ? '?' . $b['query'] : '');
		if ($rel[0] === '?')
			return $authority . $base_path . $rel;

		$path = $rel[0] === '/' ? $rel : preg_replace('~/[^/]*$~', '/', $base_path) . $rel;

		$suffix = '';
		if (preg_match('~^([^?#]*)(.*)$~s', $path, $m))
		{
			$path = $m[1];
			$suffix = $m[2];
		}

		$out = array();
		$segments = explode('/', $path);
		$last = count($segments) - 1;
		foreach ($segments as $i => $segment)
		{
			if ($segment === '..')
			{
				if (count($out) > 1)
					array_pop($out);
				if ($i === $last)
					$out[] = '';
			}
			elseif ($segment === '.')
			{
				if ($i === $last)
					$out[] = '';
			}
			else
				$out[] = $segment;
		}

		$path = implode('/', $out);
		if ($path === '' || $path[0] !== '/')
			$path = '/' . $path;

		return $authority . $path . $suffix;
	}

	/* ------------------------------------------------------------------
	 * HTML parsing
	 * ---------------------------------------------------------------- */

	/**
	 * Get the title, description, image, and site name from page HTML.
	 *
	 * @return array|false False if the page has no usable title.
	 */
	public static function parseMeta($html, $base_url, $charset = '')
	{
		$html = self::toUtf8((string) $html, (string) $charset);

		// The text is UTF-8 now, so remove the page's own charset declarations,
		// otherwise libxml would decode it a second time.
		$html = preg_replace('~<meta\b[^>]*\bcharset\s*=[^>]*>~i', '', $html);

		$meta = array();
		$doc_title = '';
		$image_src = '';

		if (class_exists('DOMDocument'))
		{
			$previous = libxml_use_internal_errors(true);
			$doc = new DOMDocument();
			$loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
			libxml_clear_errors();
			libxml_use_internal_errors($previous);

			if ($loaded)
			{
				foreach ($doc->getElementsByTagName('meta') as $node)
				{
					$key = $node->getAttribute('property');
					if ($key === '')
						$key = $node->getAttribute('name');
					if ($key === '')
						$key = $node->getAttribute('itemprop');

					$key = strtolower(trim($key));
					if ($key !== '' && !isset($meta[$key]) && $node->hasAttribute('content'))
						$meta[$key] = $node->getAttribute('content');
				}

				$node = $doc->getElementsByTagName('title')->item(0);
				if ($node !== null)
					$doc_title = $node->textContent;

				foreach ($doc->getElementsByTagName('link') as $node)
					if (strtolower(trim($node->getAttribute('rel'))) === 'image_src')
					{
						$image_src = $node->getAttribute('href');
						break;
					}
			}
		}
		else
		{
			if (preg_match_all('~<meta\s[^>]*>~i', $html, $tags))
				foreach ($tags[0] as $tag)
				{
					$attrs = array();
					if (preg_match_all('~([a-z:-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $pairs, PREG_SET_ORDER))
						foreach ($pairs as $pair)
							$attrs[strtolower($pair[1])] = html_entity_decode(isset($pair[4]) ? $pair[4] : (isset($pair[3]) && $pair[3] !== '' ? $pair[3] : $pair[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

					$key = !empty($attrs['property']) ? $attrs['property'] : (!empty($attrs['name']) ? $attrs['name'] : (!empty($attrs['itemprop']) ? $attrs['itemprop'] : ''));
					$key = strtolower(trim($key));
					if ($key !== '' && !isset($meta[$key]) && isset($attrs['content']))
						$meta[$key] = $attrs['content'];
				}

			if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m))
				$doc_title = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		$first = function (array $keys) use ($meta)
		{
			foreach ($keys as $key)
				if (isset($meta[$key]) && trim($meta[$key]) !== '')
					return $meta[$key];

			return '';
		};

		$title = self::cleanText($first(array('og:title', 'twitter:title')), 200);
		if ($title === '')
			$title = self::cleanText($doc_title, 200);
		if ($title === '')
			return false;

		$description = self::cleanText($first(array('og:description', 'twitter:description', 'description')), 400);
		if ($description === $title)
			$description = '';

		$site_name = self::cleanText($first(array('og:site_name', 'application-name', 'apple-mobile-web-app-title')), 100);

		$image = trim($first(array('og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src', 'image')));
		if ($image === '')
			$image = trim($image_src);
		$image = $image !== '' ? self::safeImageUrl($base_url, $image) : '';

		return array(
			'title' => $title,
			'description' => $description,
			'image' => $image,
			'site_name' => $site_name,
		);
	}

	/**
	 * The image is never downloaded by the server, but its host still has to be
	 * public so it can't point visitors (or SMF's image proxy) at internal addresses.
	 */
	protected static function safeImageUrl($base_url, $image)
	{
		$image = self::resolveUrl($base_url, html_entity_decode($image, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($image === false)
			return '';

		$image = self::normaliseUrl($image);
		if ($image === false || strlen($image) > 2048 || self::validateTarget($image) === false)
			return '';

		return $image;
	}

	/**
	 * Convert remote HTML to UTF-8 without requiring the optional mbstring
	 * extension. Avoid passing malformed byte sequences to the HTML parser.
	 */
	protected static function toUtf8($html, $charset)
	{
		if ($charset === '' && preg_match('~<meta[^>]+charset\s*=\s*["\']?\s*([a-z0-9_.:-]+)~i', $html, $m))
			$charset = $m[1];

		$charset = strtoupper(trim((string) $charset));
		if ($charset === '' || $charset === 'UTF-8' || $charset === 'UTF8')
		{
			if (self::isUtf8($html))
				return $html;
			$charset = 'Windows-1252';
		}

		$converted = false;
		if (function_exists('mb_convert_encoding'))
		{
			try
			{
				$converted = @mb_convert_encoding($html, 'UTF-8', $charset);
			}
			catch (\Throwable $e) {}
		}
		if ((!is_string($converted) || $converted === '') && function_exists('iconv'))
			$converted = @iconv($charset, 'UTF-8//IGNORE', $html);

		if (!is_string($converted) || $converted === '')
		{
			if (self::isUtf8($html))
				return $html;
			// Last-resort ASCII-only fallback when conversion extensions are absent.
			return (string) preg_replace('~[\x80-\xFF]~', '', $html);
		}

		return self::isUtf8($converted) ? $converted : (string) preg_replace('~[\x80-\xFF]~', '', $converted);
	}

	protected static function isUtf8($text)
	{
		return function_exists('mb_check_encoding') ? mb_check_encoding($text, 'UTF-8') : preg_match('//u', $text) === 1;
	}

	protected static function lowerText($text)
	{
		return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
	}

	protected static function textLength($text)
	{
		if (function_exists('mb_strlen'))
			return mb_strlen($text, 'UTF-8');
		if (function_exists('iconv_strlen'))
		{
			$length = @iconv_strlen($text, 'UTF-8');
			if ($length !== false)
				return $length;
		}
		return preg_match_all('/./us', $text, $matches) !== false ? count($matches[0]) : strlen($text);
	}

	protected static function textSubstr($text, $start, $length)
	{
		if (function_exists('mb_substr'))
			return mb_substr($text, $start, $length, 'UTF-8');
		if (function_exists('iconv_substr'))
		{
			$part = @iconv_substr($text, $start, $length, 'UTF-8');
			if ($part !== false)
				return $part;
		}
		return preg_match_all('/./us', $text, $matches) !== false ? implode('', array_slice($matches[0], $start, $length)) : substr($text, $start, $length);
	}

	/**
	 * Normalise both SMF's pre-escaped fields and raw database values into
	 * exactly one HTML-escaped output string.
	 */
	protected static function escapeStored($value)
	{
		$value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * Plain text only: entities decoded, tags, control and bidi-override
	 * characters removed, whitespace collapsed, and shortened.
	 */
	public static function cleanText($text, $max)
	{
		$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = strip_tags($text);
		$text = (string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]~u', '', $text);
		$text = trim((string) preg_replace('~\s+~u', ' ', $text));

		if (self::textLength($text) > $max)
			$text = rtrim(self::textSubstr($text, 0, $max - 1)) . '…';

		return $text;
	}

	/* ------------------------------------------------------------------
	 * Hooks: display
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_display_message_list: load every preview for the page in one query.
	 */
	public static function displayMessageList(&$messages, &$posters)
	{
		global $modSettings, $context, $smcFunc;

		if (empty($modSettings['richlinks_enabled']) || empty($messages))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT rl.id_msg, rl.position, rl.link_type, rl.ref_id, rl.url,
				c.title, c.description, c.image, c.site_name, c.status
			FROM {db_prefix}rich_links_msg AS rl
				LEFT JOIN {db_prefix}rich_links_cache AS c ON (c.url_hash = rl.url_hash AND rl.link_type = {string:web})
			WHERE rl.id_msg IN ({array_int:messages})
			ORDER BY rl.id_msg, rl.position',
			array(
				'messages' => array_map('intval', $messages),
				'web' => 'web',
			)
		);

		$rows = array();
		$topics = array();
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$rows[] = $row;

			if ($row['link_type'] === 'topic')
				$topics[(int) $row['ref_id']] = (int) $row['ref_id'];
			elseif ($row['link_type'] === 'board')
				$boards[(int) $row['ref_id']] = (int) $row['ref_id'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($rows))
			return;

		loadLanguage('RichLinks');

		$internal = self::loadInternal($topics, $boards);
		$show_images = !empty($modSettings['richlinks_images']);
		$max = self::maxPerPost();
		$context['richlinks_cards'] = array();

		foreach ($rows as $row)
		{
			$id_msg = (int) $row['id_msg'];
			if (isset($context['richlinks_cards'][$id_msg]) && count($context['richlinks_cards'][$id_msg]) >= $max)
				continue;

			$card = null;

			if ($row['link_type'] === 'web')
				$card = self::webCard($row, $show_images);
			elseif ($row['link_type'] === 'topic' && isset($internal['topics'][(int) $row['ref_id']]))
				$card = $internal['topics'][(int) $row['ref_id']];
			elseif ($row['link_type'] === 'board' && isset($internal['boards'][(int) $row['ref_id']]))
				$card = $internal['boards'][(int) $row['ref_id']];

			if ($card === null)
				continue;

			$card['href'] = $smcFunc['htmlspecialchars']($row['url'], ENT_QUOTES);
			$context['richlinks_cards'][$id_msg][] = $card;
		}

		if (!empty($context['richlinks_cards']))
		{
			loadTemplate('RichLinks');
			loadCSSFile('richlinks.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_richlinks');
		}
	}

	/**
	 * integrate_prepare_display_context
	 */
	public static function prepareDisplayContext(&$output, &$message, $counter = 0)
	{
		global $context;

		if (empty($output['id']) || empty($context['richlinks_cards'][$output['id']]) || !function_exists('template_richlinks_block'))
			return;

		$output['body'] .= template_richlinks_block($context['richlinks_cards'][$output['id']]);
	}

	protected static function webCard(array $row, $show_images)
	{
		global $smcFunc;

		$domain = preg_replace('~^www\.~', '', self::lowerText((string) parse_url($row['url'], PHP_URL_HOST)));
		if (function_exists('idn_to_utf8') && strpos($domain, 'xn--') !== false)
			$domain = idn_to_utf8($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;

		$has_metadata = !empty($row['status']) && !empty($row['title']);
		$image = '';
		if ($has_metadata && $show_images && !empty($row['image']))
			$image = function_exists('get_proxied_url') ? get_proxied_url($row['image']) : $row['image'];

		// The hyperlink itself can still be previewed when external sites
		// reject automated requests. Never use untrusted HTML in the card.
		$site = $has_metadata && !empty($row['site_name']) ? $row['site_name'] : $domain;
		$title = $has_metadata ? $row['title'] : $domain;
		$description = $has_metadata ? (string) $row['description'] : self::cleanText($row['url'], 200);

		return array(
			'internal' => false,
			'title' => $smcFunc['htmlspecialchars']($title, ENT_QUOTES),
			'description' => $smcFunc['htmlspecialchars']($description, ENT_QUOTES),
			'image' => $smcFunc['htmlspecialchars']($image, ENT_QUOTES),
			'site' => $smcFunc['htmlspecialchars']($site, ENT_QUOTES),
			'domain' => self::lowerText($site) === $domain ? '' : $smcFunc['htmlspecialchars']($domain, ENT_QUOTES),
			'meta' => '',
		);
	}

	/**
	 * Topic and board cards are built live, so they respect the viewer's
	 * board access and always show the current title.
	 */
	protected static function loadInternal(array $topics, array $boards)
	{
		global $smcFunc, $modSettings, $txt, $context;

		$out = array('topics' => array(), 'boards' => array());
		if (empty($modSettings['richlinks_internal']) || (empty($topics) && empty($boards)))
			return $out;

		$site = !empty($context['forum_name_html_safe']) ? $context['forum_name_html_safe'] : $smcFunc['htmlspecialchars']($context['forum_name'], ENT_QUOTES);

		if (!empty($topics))
		{
			$request = $smcFunc['db_query']('', '
				SELECT t.id_topic, t.num_replies, m.subject, m.body,
					COALESCE(mem.real_name, m.poster_name) AS poster_name, b.name AS board_name
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE t.id_topic IN ({array_int:topics})
					AND {query_see_board}' . (!empty($modSettings['postmod_active']) ? '
					AND t.approved = {int:approved}' : ''),
				array(
					'topics' => array_values($topics),
					'approved' => 1,
				)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$out['topics'][(int) $row['id_topic']] = array(
					'internal' => true,
					'title' => self::escapeStored($row['subject']),
					'description' => self::snippet($row['body'], 220),
					'image' => '',
					'site' => $site,
					'domain' => '',
					'meta' => self::escapeStored($row['board_name'])
						. ' &middot; ' . sprintf($txt['richlinks_started_by'], self::escapeStored($row['poster_name']))
						. ' &middot; ' . sprintf($txt['richlinks_replies'], comma_format($row['num_replies'])),
				);
			$smcFunc['db_free_result']($request);
		}

		if (!empty($boards))
		{
			$request = $smcFunc['db_query']('', '
				SELECT b.id_board, b.name, b.description, b.num_topics, b.num_posts
				FROM {db_prefix}boards AS b
				WHERE b.id_board IN ({array_int:boards})
					AND {query_see_board}',
				array('boards' => array_values($boards))
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$out['boards'][(int) $row['id_board']] = array(
					'internal' => true,
					'title' => self::escapeStored($row['name']),
					'description' => self::snippet($row['description'], 220),
					'image' => '',
					'site' => $site,
					'domain' => '',
					'meta' => sprintf($txt['richlinks_board_stats'], comma_format($row['num_topics']), comma_format($row['num_posts'])),
				);
			$smcFunc['db_free_result']($request);
		}

		return $out;
	}

	/**
	 * Short plain-text extract of a stored (already HTML-escaped) body.
	 */
	protected static function snippet($body, $length)
	{
		$body = self::stripBlocks((string) $body, array('quote', 'code', 'php', 'html', 'spoiler', 'hide', 'img'));
		if ($body === null)
			return '';

		$body = preg_replace('~<br\s*/?>~i', ' ', $body);
		$body = preg_replace('~\[/?[a-z][^\]]*\]~i', '', $body);
		$body = trim((string) preg_replace('~\s+~u', ' ', strip_tags($body)));

		$body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$body = self::cleanText($body, $length);
		return self::escapeStored($body);
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ---------------------------------------------------------------- */

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('RichLinks');
		$admin_areas['config']['areas']['modsettings']['subsections']['richlinks'] = array($txt['richlinks_title']);
	}

	public static function modifyModifications(&$subActions)
	{
		$subActions['richlinks'] = 'RichLinks::settings';
	}

	public static function settings($return_config = false)
	{
		global $context, $txt, $scripturl, $smcFunc, $modSettings;

		loadLanguage('RichLinks');

		$config_vars = array(
			array('check', 'richlinks_enabled'),
			array('check', 'richlinks_background', 'subtext' => $txt['richlinks_background_desc']),
			'',
			array('int', 'richlinks_max', 'min' => 1, 'max' => 20, 'subtext' => $txt['richlinks_max_desc']),
			array('check', 'richlinks_internal', 'subtext' => $txt['richlinks_internal_desc']),
			array('check', 'richlinks_images'),
			array('large_text', 'richlinks_exclude', 'rows' => 6, 'subtext' => $txt['richlinks_exclude_desc']),
			'',
			array('int', 'richlinks_timeout', 'min' => 1, 'max' => 15, 'postinput' => $txt['richlinks_seconds']),
			array('int', 'richlinks_max_kb', 'min' => 32, 'max' => 2048, 'postinput' => 'KB', 'subtext' => $txt['richlinks_max_kb_desc']),
			array('int', 'richlinks_cache_days', 'min' => 1, 'max' => 365, 'postinput' => $txt['richlinks_days'], 'subtext' => $txt['richlinks_cache_days_desc']),
		);

		if ($return_config)
			return $config_vars;

		$base = $scripturl . '?action=admin;area=modsettings;sa=richlinks';
		$context['post_url'] = $base . ';save';
		$context['settings_title'] = $txt['richlinks_title'];
		$context[$context['admin_menu_name']]['tab_data']['tabs']['richlinks'] = array('description' => $txt['richlinks_admin_desc']);

		if (isset($_GET['save']))
		{
			checkSession();

			$clamp = array('richlinks_max' => array(1, 20), 'richlinks_timeout' => array(1, 15), 'richlinks_max_kb' => array(32, 2048), 'richlinks_cache_days' => array(1, 365));
			foreach ($clamp as $key => $range)
				$_POST[$key] = max($range[0], min($range[1], isset($_POST[$key]) ? (int) $_POST[$key] : $range[0]));

			saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=modsettings;sa=richlinks');
		}

		if (isset($_GET['maintain']))
		{
			checkSession('get');

			if ($_GET['maintain'] === 'prune')
				$_SESSION['richlinks_notice'] = sprintf($txt['richlinks_pruned'], comma_format(self::prune()));
			elseif ($_GET['maintain'] === 'retry')
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}rich_links_cache
					WHERE status = {int:failed}',
					array('failed' => 0)
				);
				$_SESSION['richlinks_notice'] = $txt['richlinks_retried'];
			}
			elseif ($_GET['maintain'] === 'backfill')
			{
				// Scan by indexed message ID, rather than OFFSET or a full
				// unindexed LIKE query. Queue only messages containing URLs.
				$cursor = !empty($_SESSION['richlinks_backfill_cursor']) ? (int) $_SESSION['richlinks_backfill_cursor'] : 0;
				$request = $smcFunc['db_query']('', '
					SELECT id_msg, body
					FROM {db_prefix}messages
					WHERE id_msg > {int:cursor}
					ORDER BY id_msg
					LIMIT 100',
					array('cursor' => $cursor)
				);
				$scanned = 0;
				$queued = 0;
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$cursor = (int) $row['id_msg'];
					$scanned++;
					if (self::extractUrls($row['body']) !== array())
					{
						self::queue($cursor);
						$queued++;
					}
				}
				$smcFunc['db_free_result']($request);

				if ($scanned < 100)
				{
					unset($_SESSION['richlinks_backfill_cursor']);
					$_SESSION['richlinks_notice'] = sprintf($txt['richlinks_backfill_done'], $scanned, $queued);
				}
				else
				{
					$_SESSION['richlinks_backfill_cursor'] = $cursor;
					$_SESSION['richlinks_notice'] = sprintf($txt['richlinks_backfill_progress'], $scanned, $queued);
				}
			}

			redirectexit('action=admin;area=modsettings;sa=richlinks');
		}

		$stats = array();
		$queries = array(
			'previews' => 'SELECT COUNT(*) FROM {db_prefix}rich_links_msg',
			'cached' => 'SELECT COUNT(*) FROM {db_prefix}rich_links_cache WHERE status = 1',
			'failed' => 'SELECT COUNT(*) FROM {db_prefix}rich_links_cache WHERE status = 0',
		);
		foreach ($queries as $key => $sql)
		{
			$request = $smcFunc['db_query']('', $sql, array());
			list ($stats[$key]) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
		}

		$session = ';' . $context['session_var'] . '=' . $context['session_id'];

		if (!function_exists('curl_init'))
			$context['settings_insert_above'] = '<div class="errorbox">' . $txt['richlinks_no_curl'] . '</div>';

		$context['settings_insert_below'] = '
			<div class="cat_bar"><h3 class="catbg">' . $txt['richlinks_maintenance'] . '</h3></div>
			<div class="windowbg">' . (!empty($_SESSION['richlinks_notice']) ? '
				<div class="infobox">' . $_SESSION['richlinks_notice'] . '</div>' : '') . '
				<p>' . sprintf($txt['richlinks_stats'], comma_format($stats['previews']), comma_format($stats['cached']), comma_format($stats['failed'])) . '</p>
				<p>
					<a class="button" href="' . $base . ';maintain=prune' . $session . '">' . $txt['richlinks_prune'] . '</a>
					<a class="button" href="' . $base . ';maintain=retry' . $session . '">' . $txt['richlinks_retry'] . '</a>
					<a class="button" href="' . $base . ';maintain=backfill' . $session . '">' . $txt['richlinks_backfill'] . '</a>
				</p>
				<p class="smalltext">' . $txt['richlinks_maintenance_desc'] . '</p>
			</div>';

		unset($_SESSION['richlinks_notice']);

		prepareDBSettingContext($config_vars);
	}

	/**
	 * Remove previews for deleted messages and unused cache rows.
	 * Uses LEFT JOINs in batches (SMF's query checker blocks subqueries).
	 *
	 * @return int Number of message rows removed.
	 */
	public static function prune()
	{
		global $smcFunc;

		$removed = 0;

		for ($i = 0; $i < 100; $i++)
		{
			$request = $smcFunc['db_query']('', '
				SELECT DISTINCT rl.id_msg
				FROM {db_prefix}rich_links_msg AS rl
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = rl.id_msg)
				WHERE m.id_msg IS NULL
				LIMIT 500',
				array()
			);
			$ids = array();
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			if (empty($ids))
				break;

			self::deleteForMessages($ids);
			$removed += count($ids);
		}

		$cutoff = time() - self::cacheTtl();
		for ($i = 0; $i < 100; $i++)
		{
			$request = $smcFunc['db_query']('', '
				SELECT c.url_hash
				FROM {db_prefix}rich_links_cache AS c
					LEFT JOIN {db_prefix}rich_links_msg AS rl ON (rl.url_hash = c.url_hash)
				WHERE rl.url_hash IS NULL
					AND c.fetched < {int:cutoff}
				LIMIT 500',
				array('cutoff' => $cutoff)
			);
			$hashes = array();
			while ($row = $smcFunc['db_fetch_row']($request))
				$hashes[] = $row[0];
			$smcFunc['db_free_result']($request);

			if (empty($hashes))
				break;

			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}rich_links_cache
				WHERE url_hash IN ({array_string:hashes})',
				array('hashes' => $hashes)
			);
		}

		updateSettings(array('richlinks_last_prune' => time()));

		return $removed;
	}

	/**
	 * integrate_credits
	 */
	public static function credits()
	{
		global $context, $txt;

		loadLanguage('RichLinks');
		$context['copyrights']['mods'][] = sprintf($txt['richlinks_credits'], self::VERSION);
	}

	/* ------------------------------------------------------------------
	 * Settings helpers
	 * ---------------------------------------------------------------- */

	public static function maxPerPost()
	{
		global $modSettings;

		return empty($modSettings['richlinks_max']) ? 3 : max(1, min(20, (int) $modSettings['richlinks_max']));
	}

	protected static function timeout()
	{
		global $modSettings;

		return empty($modSettings['richlinks_timeout']) ? 5 : max(1, min(15, (int) $modSettings['richlinks_timeout']));
	}

	protected static function maxBytes()
	{
		global $modSettings;

		return (empty($modSettings['richlinks_max_kb']) ? 512 : max(32, min(2048, (int) $modSettings['richlinks_max_kb']))) * 1024;
	}

	protected static function cacheTtl()
	{
		global $modSettings;

		return (empty($modSettings['richlinks_cache_days']) ? 30 : max(1, min(365, (int) $modSettings['richlinks_cache_days']))) * 86400;
	}
}
