<?php

/**
 * Advanced BBCode & Editor Manager for SMF 2.1
 *
 * Runtime: registers custom BBCodes, builds the editor toolbar from
 * the active profile, and enforces each profile's allowed tags on the server.
 *
 * @package BBCodeManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

class BBM
{
	const VERSION = '1.0.2';
	const MAX_PARAMS = 6;

	public static $modes = array('parsed', 'unparsed', 'closed');
	public static $param_types = array('text', 'number', 'color', 'url', 'choice');
	public static $contexts = array('post', 'pm', 'signature');

	protected static $config = null;
	protected static $profile = false;
	protected static $all_tags = null;

	/* ------------------------------------------------------------------
	 * Configuration
	 * ---------------------------------------------------------------- */

	/**
	 * Tags, profiles, rules and tooltips, cached as one array.
	 */
	public static function config($fresh = false)
	{
		global $smcFunc, $modSettings;

		if (!$fresh && self::$config !== null)
			return self::$config;

		if (!$fresh && ($cached = cache_get_data('bbm_config', 3600)) !== null)
			return self::$config = $cached;

		$config = array('tags' => array(), 'profiles' => array(), 'rules' => array(), 'tooltips' => array());

		$request = $smcFunc['db_query']('', '
			SELECT id_tag, tag, title, description, mode, block_level, params, template,
				button, icon, tooltip, shortcut, enabled, sort_order
			FROM {db_prefix}bbm_tags
			ORDER BY sort_order, tag',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$config['tags'][(int) $row['id_tag']] = self::normaliseTag($row);
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_profile, name, toolbar, allowed, is_default
			FROM {db_prefix}bbm_profiles
			ORDER BY name',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$config['profiles'][(int) $row['id_profile']] = self::normaliseProfile($row);
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT id_rule, id_profile, contexts, boards, id_groups, priority
			FROM {db_prefix}bbm_rules
			ORDER BY priority DESC, id_rule',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$config['rules'][(int) $row['id_rule']] = array(
				'id_rule' => (int) $row['id_rule'],
				'id_profile' => (int) $row['id_profile'],
				'contexts' => self::csvList($row['contexts'], false),
				'boards' => self::csvList($row['boards']),
				'groups' => self::csvList($row['id_groups']),
				'priority' => (int) $row['priority'],
			);
		$smcFunc['db_free_result']($request);

		$tips = !empty($modSettings['bbm_tooltips']) ? json_decode($modSettings['bbm_tooltips'], true) : array();
		$config['tooltips'] = is_array($tips) ? $tips : array();

		cache_put_data('bbm_config', $config, 3600);

		return self::$config = $config;
	}

	public static function clearCache()
	{
		self::$config = null;
		self::$profile = false;
		cache_put_data('bbm_config', null, 0);
	}

	public static function normaliseTag(array $row)
	{
		$params = is_array($row['params']) ? $row['params'] : json_decode((string) $row['params'], true);

		return array(
			'id_tag' => isset($row['id_tag']) ? (int) $row['id_tag'] : 0,
			'tag' => (string) $row['tag'],
			'title' => (string) $row['title'],
			'description' => (string) $row['description'],
			'mode' => in_array($row['mode'], self::$modes, true) ? $row['mode'] : 'parsed',
			'block_level' => !empty($row['block_level']),
			'params' => is_array($params) ? array_values($params) : array(),
			'template' => (string) $row['template'],
			'button' => !empty($row['button']),
			'icon' => (string) $row['icon'],
			'tooltip' => (string) $row['tooltip'],
			'shortcut' => (string) $row['shortcut'],
			'enabled' => !empty($row['enabled']),
			'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
		);
	}

	public static function normaliseProfile(array $row)
	{
		$toolbar = is_array($row['toolbar']) ? $row['toolbar'] : json_decode((string) $row['toolbar'], true);
		$allowed = is_array($row['allowed']) ? $row['allowed'] : json_decode((string) $row['allowed'], true);

		return array(
			'id_profile' => isset($row['id_profile']) ? (int) $row['id_profile'] : 0,
			'name' => (string) $row['name'],
			'toolbar' => is_array($toolbar) ? $toolbar : array(),
			// null means every tag is allowed
			'allowed' => is_array($allowed) ? array_values($allowed) : null,
			'is_default' => !empty($row['is_default']),
		);
	}

	public static function csvList($value, $ints = true)
	{
		$out = array();
		foreach (explode(',', (string) $value) as $item)
		{
			$item = trim($item);
			if ($item === '')
				continue;
			$out[] = $ints ? (int) $item : $item;
		}

		return array_values(array_unique($out));
	}

	/* ------------------------------------------------------------------
	 * BBCode registration
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_bbc_codes
	 */
	public static function bbcCodes(&$codes, &$no_autolink_tags)
	{
		global $modSettings;

		if (empty($modSettings['bbm_enabled']))
			return;

		foreach (self::config()['tags'] as $tag)
			if ($tag['enabled'])
				foreach (self::buildCodes($tag) as $code)
					$codes[] = $code;
	}

	/**
	 * Turn one tag definition into SMF code entries. A tag with
	 * options gets an "=" version, and a plain version too unless
	 * an option is required.
	 */
	public static function buildCodes(array $t)
	{
		list ($before, $after) = self::splitTemplate($t);

		$base = array('tag' => $t['tag'], 'bbm_id' => (int) $t['id_tag']);
		if ($t['block_level'])
		{
			$base['block_level'] = true;
			$base['trim'] = 'both';
		}

		$defaults = self::defaults($t);
		$has_params = !empty($t['params']);
		$required = false;
		foreach ($t['params'] as $p)
			if (!empty($p['required']))
				$required = true;

		$codes = array();

		if ($t['mode'] === 'closed')
		{
			$codes[] = $base + array('type' => 'closed', 'content' => self::fill($before, $defaults));
			return $codes;
		}

		if ($t['mode'] === 'unparsed')
		{
			if ($has_params)
				$codes[] = $base + array(
					'type' => 'unparsed_equals_content',
					'quoted' => 'optional',
					'content' => '$1',
					'validate' => function (&$tag, &$data, $disabled, $params = array()) use ($t, $before, $after)
					{
						$values = BBM::values($t, $data[1]);
						$data = array(BBM::fill($before, $values) . $data[0] . BBM::fill($after, $values), '');
					},
				);

			if (!$has_params || !$required)
				$codes[] = $base + array(
					'type' => 'unparsed_content',
					'content' => '$1',
					'validate' => function (&$tag, &$data, $disabled, $params = array()) use ($before, $after, $defaults)
					{
						$data = BBM::fill($before, $defaults) . $data . BBM::fill($after, $defaults);
					},
				);

			return $codes;
		}

		if ($has_params)
			$codes[] = $base + array(
				'type' => 'parsed_equals',
				'quoted' => 'optional',
				'before' => '',
				'after' => '',
				'validate' => function (&$tag, &$data, $disabled, $params = array()) use ($t, $before, $after)
				{
					$values = BBM::values($t, $data);
					$tag['before'] = BBM::fill($before, $values);
					$tag['after'] = BBM::fill($after, $values);
					$data = '';
				},
			);

		if (!$has_params || !$required)
			$codes[] = $base + array(
				'before' => self::fill($before, $defaults),
				'after' => self::fill($after, $defaults),
			);

		return $codes;
	}

	public static function splitTemplate(array $t)
	{
		if ($t['mode'] === 'closed')
			return array($t['template'], '');

		$parts = explode('{content}', $t['template'], 2);

		return array($parts[0], isset($parts[1]) ? $parts[1] : '');
	}

	public static function fill($html, array $values)
	{
		$map = array();
		foreach ($values as $name => $value)
		{
			// If an optional URL was omitted, omit any image whose entire
			// src attribute consists of its placeholder (no empty src request).
			if ($value === '')
				$html = preg_replace('~<img\b(?=[^>]*\bsrc\s*=\s*["\\\']\{' . preg_quote($name, '~') . '\}["\\\'])[^>]*\/?>~i', '', $html);
			$map['{' . $name . '}'] = $value;
		}

		return strtr($html, $map);
	}

	public static function defaults(array $t)
	{
		$values = array();
		foreach ($t['params'] as $p)
			$values[$p['name']] = self::cleanParam($p, isset($p['default']) ? (string) $p['default'] : '', true);

		return $values;
	}

	/**
	 * Get the parameter values from the raw (HTML-escaped) "=" part of a tag.
	 * Multiple options are separated with "|". Invalid or missing values
	 * fall back to the default.
	 */
	public static function values(array $t, $raw)
	{
		$raw = trim(self::unescape((string) $raw));
		if (strlen($raw) > 1 && $raw[0] === '"' && substr($raw, -1) === '"')
			$raw = substr($raw, 1, -1);

		$count = count($t['params']);
		$parts = $count > 1 ? explode('|', $raw, $count) : array($raw);

		$values = array();
		foreach ($t['params'] as $i => $p)
		{
			$in = isset($parts[$i]) ? trim($parts[$i]) : '';
			$out = $in === '' ? null : self::cleanParam($p, $in);
			$values[$p['name']] = $out !== null ? $out : self::cleanParam($p, isset($p['default']) ? (string) $p['default'] : '', true);
		}

		return $values;
	}

	/**
	 * Unicode-aware limits without requiring the optional mbstring extension.
	 * SMF's minimum PHP environment doesn't guarantee mb_* functions.
	 */
	public static function textLength($value)
	{
		$value = (string) $value;
		if (function_exists('mb_strlen'))
			return mb_strlen($value, 'UTF-8');
		if (preg_match_all('~.~us', $value, $matches) !== false)
			return count($matches[0]);
		return strlen($value);
	}

	public static function textSubstr($value, $start, $length)
	{
		$value = (string) $value;
		if (function_exists('mb_substr'))
			return mb_substr($value, $start, $length, 'UTF-8');
		if (preg_match_all('~.~us', $value, $matches) !== false)
			return implode('', array_slice($matches[0], $start, $length));
		return substr($value, $start, $length);
	}

	/**
	 * Validate one typed option and return HTML-escaped text or null.
	 */
	public static function cleanParam(array $p, $value, $is_default = false)
	{
		$value = trim((string) preg_replace('~[\x00-\x1F\x7F]~u', '', (string) $value));
		$out = null;

		switch (isset($p['type']) ? $p['type'] : 'text')
		{
			case 'number':
				if ($value !== '' && is_numeric($value))
				{
					$n = (float) $value;
					if (isset($p['min']) && $p['min'] !== '' && is_numeric($p['min']) && $n < (float) $p['min'])
						$n = (float) $p['min'];
					if (isset($p['max']) && $p['max'] !== '' && is_numeric($p['max']) && $n > (float) $p['max'])
						$n = (float) $p['max'];

					$out = floor($n) == $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
				}
				break;

			case 'color':
				$v = strtolower($value);
				if (preg_match('~^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$~', $v) || in_array($v, self::colorNames(), true))
					$out = $v;
				break;

			case 'url':
				if (preg_match('~^https?://[^\s<>"\'`]+$~i', $value) && strlen($value) <= 2000)
				{
					$host = parse_url($value, PHP_URL_HOST);
					if (!empty($host))
						$out = $value;
				}
				break;

			case 'choice':
				foreach (isset($p['choices']) ? (array) $p['choices'] : array() as $choice)
					if (strcasecmp((string) $choice, $value) === 0)
					{
						$out = (string) $choice;
						break;
					}
				break;

			default:
				$v = trim((string) preg_replace('~\s+~u', ' ', strip_tags($value)));
				$max = !empty($p['maxlen']) ? max(1, min(500, (int) $p['maxlen'])) : 100;
				if (BBM::textLength($v) > $max)
					$v = BBM::textSubstr($v, 0, $max);
				if ($v !== '')
					$out = $v;
		}

		if ($out === null)
			return $is_default ? '' : null;

		return self::esc($out);
	}

	/**
	 * Escape for HTML (attributes or text). Also stops values from being
	 * read as SMF replacement markers ($1) or placeholders.
	 */
	public static function esc($s)
	{
		return strtr(htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'), array(
			'$' => '&#36;', '{' => '&#123;', '}' => '&#125;', '[' => '&#91;', ']' => '&#93;',
		));
	}

	public static function unescape($s)
	{
		return function_exists('un_htmlspecialchars') ? un_htmlspecialchars($s) : htmlspecialchars_decode($s, ENT_QUOTES);
	}

	public static function colorNames()
	{
		return array(
			'black', 'white', 'red', 'green', 'blue', 'yellow', 'orange', 'purple', 'pink', 'brown',
			'gray', 'grey', 'silver', 'gold', 'navy', 'teal', 'maroon', 'olive', 'lime', 'aqua',
			'cyan', 'magenta', 'fuchsia', 'indigo', 'violet', 'crimson', 'coral', 'salmon', 'tomato',
			'orangered', 'darkorange', 'khaki', 'beige', 'ivory', 'lavender', 'plum', 'orchid', 'tan',
			'chocolate', 'sienna', 'peru', 'turquoise', 'skyblue', 'steelblue', 'royalblue', 'dodgerblue',
			'deepskyblue', 'lightblue', 'darkblue', 'slateblue', 'seagreen', 'forestgreen', 'darkgreen',
			'limegreen', 'lightgreen', 'olivedrab', 'darkred', 'firebrick', 'indianred', 'hotpink',
			'deeppink', 'darkviolet', 'rebeccapurple', 'slategray', 'darkgray', 'lightgray', 'dimgray',
			'whitesmoke', 'gainsboro', 'transparent', 'currentcolor',
		);
	}

	/**
	 * The text inserted by a toolbar button.
	 */
	public static function insertion(array $t)
	{
		$open = '[' . $t['tag'];

		if (!empty($t['params']))
		{
			$values = array();
			$any = false;
			$required = false;
			foreach ($t['params'] as $p)
			{
				$d = isset($p['default']) ? (string) $p['default'] : '';
				$values[] = $d;
				$any = $any || $d !== '';
				$required = $required || !empty($p['required']);
			}

			if ($any || $required)
			{
				$value = rtrim(implode('|', $values), '|');
				$open .= '=' . (preg_match('~[\s\]]~', $value) ? '"' . $value . '"' : $value);
			}
		}

		$open .= ']';

		return array($open, $t['mode'] === 'closed' ? '' : '[/' . $t['tag'] . ']');
	}

	/* ------------------------------------------------------------------
	 * Profiles and enforcement
	 * ---------------------------------------------------------------- */

	public static function postingContext()
	{
		$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

		if ($action === 'pm')
			return 'pm';
		if ($action === 'profile')
			return 'signature';

		return 'post';
	}

	/**
	 * The profile for this user, board and context: the highest priority
	 * matching rule, otherwise the default profile, otherwise none (SMF's normal editor).
	 */
	public static function activeProfile()
	{
		global $user_info, $board;

		if (self::$profile !== false)
			return self::$profile;

		$config = self::config();

		// An admin trying a profile in their own editor.
		if (!empty($_SESSION['bbm_try_profile']) && !empty($user_info['is_admin']) && isset($config['profiles'][(int) $_SESSION['bbm_try_profile']]))
			return self::$profile = $config['profiles'][(int) $_SESSION['bbm_try_profile']];

		$where = self::postingContext();
		$groups = array_map('intval', isset($user_info['groups']) ? (array) $user_info['groups'] : array(-1));
		$id_board = empty($board) ? 0 : (int) $board;

		foreach ($config['rules'] as $rule)
		{
			if (!isset($config['profiles'][$rule['id_profile']]))
				continue;
			if (!empty($rule['contexts']) && !in_array($where, $rule['contexts'], true))
				continue;
			if (!empty($rule['boards']) && ($where !== 'post' || !in_array($id_board, $rule['boards'], true)))
				continue;
			if (!empty($rule['groups']) && !array_intersect($groups, $rule['groups']))
				continue;

			return self::$profile = $config['profiles'][$rule['id_profile']];
		}

		foreach ($config['profiles'] as $profile)
			if ($profile['is_default'])
				return self::$profile = $profile;

		return self::$profile = null;
	}

	/**
	 * @return array|null Allowed tag names, or null for no restriction.
	 */
	public static function allowedTags($profile)
	{
		global $modSettings, $user_info;

		if (empty($profile) || $profile['allowed'] === null)
			return null;

		if (!empty($modSettings['bbm_admin_bypass']) && !empty($user_info['is_admin']) && empty($_SESSION['bbm_try_profile']))
			return null;

		return array_map('strtolower', $profile['allowed']);
	}

	/**
	 * Every tag name known to SMF (core, other mods and ours).
	 */
	public static function allTagNames()
	{
		if (self::$all_tags !== null)
			return self::$all_tags;

		$names = array();
		$codes = parse_bbc(false);
		if (is_array($codes))
			foreach ($codes as $code)
				if (isset($code['tag']))
					$names[strtolower($code['tag'])] = true;

		// Our own tags always count, whatever order the hooks ran in.
		foreach (self::config()['tags'] as $t)
			$names[$t['tag']] = true;

		$names = array_keys($names);
		sort($names);

		return self::$all_tags = $names;
	}

	/**
	 * integrate_preparsecode: remove tags the active profile doesn't allow.
	 * This runs on the server for every post, PM, signature, quick edit and
	 * preview, so hiding a button is never the only protection.
	 */
	public static function preparse(&$message, $previewing = false)
	{
		global $modSettings;

		if (empty($modSettings['bbm_enabled']) || $message === '' || strpos($message, '[') === false)
			return;

		$allowed = self::allowedTags(self::activeProfile());
		if ($allowed === null)
			return;

		$blocked = array_values(array_diff(self::allTagNames(), $allowed));
		if (!empty($blocked))
			$message = self::stripTags($message, $blocked);
	}

	/**
	 * Remove the opening and closing markers of blocked tags but keep their
	 * content. Text inside [code], [php] and [nobbc] is left alone
	 * unless those tags are blocked too.
	 */
	public static function stripTags($message, array $blocked)
	{
		$blocked = array_map('strtolower', $blocked);
		$protect = array_values(array_diff(array('code', 'php', 'nobbc'), $blocked));
		$list = implode('|', array_map(function ($t)
		{
			return preg_quote($t, '~');
		}, $blocked));
		$strip = '~\[/?(?:' . $list . ')(?=[\s=\]/])[^\]]*\]~i';

		if (empty($protect))
			return (string) preg_replace($strip, '', $message);

		$parts = preg_split('~(\[(' . implode('|', $protect) . ')(?=[\s=\]])[^\]]*\].*?\[/\2\])~is', $message, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts === false)
			return (string) preg_replace($strip, '', $message);

		$out = '';
		foreach ($parts as $i => $part)
		{
			if ($i % 3 === 0)
				$out .= preg_replace($strip, '', $part);
			elseif ($i % 3 === 1)
				$out .= $part;
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Editor
	 * ---------------------------------------------------------------- */

	/**
	 * Map editor button codes to BBCode tags (SMF's own map takes priority).
	 */
	public static function coreTagMap()
	{
		return array(
			'bold' => 'b', 'italic' => 'i', 'underline' => 'u', 'strike' => 's',
			'superscript' => 'sup', 'subscript' => 'sub', 'pre' => 'pre', 'left' => 'left',
			'center' => 'center', 'right' => 'right', 'justify' => 'justify', 'font' => 'font',
			'size' => 'size', 'color' => 'color', 'floatleft' => 'float', 'floatright' => 'float',
			'youtube' => 'youtube', 'image' => 'img', 'link' => 'url', 'email' => 'email',
			'table' => 'table', 'code' => 'code', 'quote' => 'quote', 'bulletlist' => 'list',
			'orderedlist' => 'list', 'horizontalrule' => 'hr', 'ltr' => 'ltr', 'rtl' => 'rtl',
		);
	}

	/**
	 * Used by the admin toolbar builder before an editor has been opened.
	 */
	public static function fallbackButtons()
	{
		return array(
			'bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline', 'strike' => 'Strikethrough',
			'superscript' => 'Superscript', 'subscript' => 'Subscript', 'pre' => 'Preformatted',
			'left' => 'Align left', 'center' => 'Center', 'right' => 'Align right',
			'font' => 'Font', 'size' => 'Font size', 'color' => 'Font colour',
			'floatleft' => 'Float left', 'floatright' => 'Float right', 'youtube' => 'YouTube',
			'image' => 'Insert image', 'link' => 'Insert link', 'email' => 'Insert email',
			'code' => 'Code', 'quote' => 'Quote', 'bulletlist' => 'Bulleted list',
			'orderedlist' => 'Numbered list', 'horizontalrule' => 'Horizontal rule',
			'removeformat' => 'Remove formatting', 'source' => 'View source',
		);
	}

	public static function knownButtons()
	{
		global $modSettings;

		$known = !empty($modSettings['bbm_known_buttons']) ? json_decode($modSettings['bbm_known_buttons'], true) : null;

		return is_array($known) && !empty($known) ? $known : self::fallbackButtons();
	}

	/**
	 * integrate_bbc_buttons: rebuild the toolbar from the active profile.
	 */
	public static function bbcButtons(&$bbc_tags, &$editor_tag_map)
	{
		global $modSettings, $txt;

		if (empty($modSettings['bbm_enabled']))
			return;

		loadLanguage('BBCodeManager');
		$config = self::config();

		// Remember the buttons SMF and other mods provide, for the toolbar builder.
		$known = array();
		$seen = array();
		foreach ($bbc_tags as $row)
			foreach ($row as $button)
				if (!empty($button['code']) && strpos($button['code'], 'bbm_') !== 0)
				{
					$known[$button['code']] = $button;
					$seen[$button['code']] = isset($button['description']) && is_string($button['description']) ? $button['description'] : $button['code'];
				}
		self::rememberButtons($seen);

		$tag_map = (is_array($editor_tag_map) ? $editor_tag_map : array()) + self::coreTagMap();

		$custom = array();
		$assets = array('formats' => array(), 'shortcuts' => array(), 'icons' => array());
		foreach ($config['tags'] as $t)
		{
			if (!$t['enabled'])
				continue;

			$assets['formats'][] = array(
				'tag' => $t['tag'],
				'block' => $t['block_level'],
				'closed' => $t['mode'] === 'closed',
				'label' => $t['title'],
			);

			if (!$t['button'])
				continue;

			$code = 'bbm_' . $t['tag'];
			list ($open, $close) = self::insertion($t);
			$custom[$code] = array(
				'code' => $code,
				'description' => $t['tooltip'] !== '' ? $t['tooltip'] : $t['title'],
				'before' => $open,
				'after' => $close,
			);
			$tag_map[$code] = $t['tag'];
			$editor_tag_map[$code] = $t['tag'];

			if ($t['shortcut'] !== '')
				$assets['shortcuts'][] = array('code' => $code, 'keys' => $t['shortcut']);
			$assets['icons'][$code] = $t['icon'] !== '' ? $t['icon'] : '';
		}

		$profile = self::activeProfile();
		$allowed = self::allowedTags($profile);

		$all = $known + $custom;
		foreach ($config['tooltips'] as $code => $tip)
			if (isset($all[$code]) && (string) $tip !== '')
				$all[$code]['description'] = $tip;

		$ok = function ($code) use ($allowed, $tag_map)
		{
			return $allowed === null || !isset($tag_map[$code]) || in_array(strtolower($tag_map[$code]), $allowed, true);
		};

		$rows = array();
		if (!empty($profile['toolbar']))
		{
			foreach ($profile['toolbar'] as $row)
			{
				$out = array();
				foreach ((array) $row as $item)
				{
					if ($item === '|')
						$out[] = array();
					elseif (isset($all[$item]) && $ok($item))
						$out[] = $all[$item];
				}

				if (($out = self::tidyRow($out)) !== array())
					$rows[] = $out;
			}
		}
		else
		{
			foreach ($bbc_tags as $row)
			{
				$out = array();
				foreach ($row as $button)
				{
					if (empty($button['code']))
						$out[] = array();
					elseif (isset($all[$button['code']]) && $ok($button['code']))
						$out[] = $all[$button['code']];
				}

				if (($out = self::tidyRow($out)) !== array())
					$rows[] = $out;
			}

			$extra = array();
			foreach ($custom as $code => $button)
				if ($ok($code))
					$extra[] = $all[$code];
			if (!empty($extra))
				$rows[] = $extra;
		}

		$bbc_tags = $rows;

		self::editorAssets($assets, $allowed);
	}

	/**
	 * Remove leading, trailing and doubled separators.
	 */
	protected static function tidyRow(array $row)
	{
		$out = array();
		foreach ($row as $item)
		{
			if (empty($item) && (empty($out) || empty(end($out))))
				continue;
			$out[] = $item;
		}

		while (!empty($out) && empty(end($out)))
			array_pop($out);

		return $out;
	}

	protected static function rememberButtons(array $seen)
	{
		global $modSettings;

		if (empty($seen))
			return;

		$json = json_encode($seen);
		if (!isset($modSettings['bbm_known_buttons']) || $modSettings['bbm_known_buttons'] !== $json)
			updateSettings(array('bbm_known_buttons' => $json));
	}

	protected static function editorAssets(array $assets, $allowed)
	{
		global $modSettings, $settings, $txt;

		loadCSSFile('bbcodemanager.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_bbm');
		loadJavaScriptFile('bbcodemanager-editor.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_bbm_editor');

		$css = '';
		foreach ($assets['icons'] as $code => $icon)
		{
			if ($icon !== '' && preg_match('~^[a-z0-9_.-]+$~i', $icon))
				$css .= '.sceditor-button-' . $code . ' div{background:url("' . $settings['default_theme_url'] . '/images/bbm/' . $icon . '") center/16px 16px no-repeat !important}';
			// Buttons without icons are given a visible two-letter label by
			// bbcodemanager-editor.js after SCEditor builds its toolbar.
		}
		if ($css !== '')
			addInlineCss($css);

		if ($allowed !== null)
			$assets['formats'] = array_values(array_filter($assets['formats'], function ($f) use ($allowed)
			{
				return in_array($f['tag'], $allowed, true);
			}));

		addInlineJavaScript('
	var bbmConfig = ' . json_encode(array(
			'formats' => !empty($modSettings['bbm_wysiwyg']) ? $assets['formats'] : array(),
			'shortcuts' => $assets['shortcuts'],
			'icons' => $assets['icons'],
			'palette' => !empty($modSettings['bbm_palette']),
			'mobile' => !empty($modSettings['bbm_mobile_toolbar']),
			'txt' => array(
				'palette' => $txt['bbm_palette'],
				'search' => $txt['bbm_palette_search'],
				'none' => $txt['bbm_palette_none'],
				'hint' => $txt['bbm_palette_hint'],
			),
		), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';');
	}

	/* ------------------------------------------------------------------
	 * Other hooks
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_load_theme: styles for custom tags wherever posts appear.
	 */
	public static function loadTheme()
	{
		global $modSettings;

		if (empty($modSettings['bbm_enabled']))
			return;

		loadCSSFile('bbcodemanager.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_bbm');

		if (!empty($modSettings['bbm_custom_css']))
			addInlineCss(str_replace('<', '', $modSettings['bbm_custom_css']));
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('BBCodeManager');

		$admin_areas['config']['areas']['bbcmanager'] = array(
			'label' => $txt['bbm_title'],
			'file' => 'BBCodeManager-Admin.php',
			'function' => 'BBM_Admin::main',
			'icon' => 'posts',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'tags' => array($txt['bbm_tab_tags']),
				'library' => array($txt['bbm_tab_library']),
				'profiles' => array($txt['bbm_tab_profiles']),
				'rules' => array($txt['bbm_tab_rules']),
				'buttons' => array($txt['bbm_tab_buttons']),
				'transfer' => array($txt['bbm_tab_transfer']),
				'history' => array($txt['bbm_tab_history']),
				'diagnostics' => array($txt['bbm_tab_diagnostics']),
				'settings' => array($txt['bbm_tab_settings']),
			),
		);
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('BBCodeManager');
		$context['copyrights']['mods'][] = sprintf($txt['bbm_credits'], self::VERSION);
	}
}
