<?php

/**
 * Advanced BBCode & Editor Manager: admin panel.
 *
 * @package BBCodeManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.2
 */

if (!defined('SMF'))
	die('No direct access...');

class BBM_Admin
{
	protected static $foreign = null;

	public static function main()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('admin_forum');

		loadLanguage('BBCodeManager');
		loadTemplate('BBCodeManager');
		loadCSSFile('bbcodemanager.css', array('minimize' => true, 'seed' => BBM::VERSION), 'smf_bbm');
		loadJavaScriptFile('bbcodemanager-admin.js', array('minimize' => true, 'defer' => true, 'seed' => BBM::VERSION), 'smf_bbm_admin');

		$subActions = array(
			'tags' => 'tags',
			'edittag' => 'editTag',
			'library' => 'library',
			'profiles' => 'profiles',
			'editprofile' => 'editProfile',
			'rules' => 'rules',
			'editrule' => 'editRule',
			'buttons' => 'buttons',
			'transfer' => 'transfer',
			'history' => 'history',
			'diagnostics' => 'diagnostics',
			'settings' => 'settings',
			'preview' => 'preview',
		);
		$parents = array('edittag' => 'tags', 'editprofile' => 'profiles', 'editrule' => 'rules');

		$sa = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'tags';

		$context['page_title'] = $txt['bbm_title'];
		$context['bbm_url'] = $scripturl . '?action=admin;area=bbcmanager';
		$context['bbm_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['bbm_title'],
			'description' => $txt['bbm_desc'],
			'tabs' => array(
				'tags' => array(), 'library' => array(), 'profiles' => array(), 'rules' => array(),
				'buttons' => array(), 'transfer' => array(), 'history' => array(),
				'diagnostics' => array(), 'settings' => array(),
			),
		);
		if (isset($parents[$sa]))
			$context[$context['admin_menu_name']]['current_subsection'] = $parents[$sa];

		$context['bbm_notice'] = isset($_SESSION['bbm_notice']) ? $_SESSION['bbm_notice'] : null;
		unset($_SESSION['bbm_notice']);

		$config = BBM::config(true);
		$context['bbm_trying'] = !empty($_SESSION['bbm_try_profile']) && isset($config['profiles'][(int) $_SESSION['bbm_try_profile']])
			? $config['profiles'][(int) $_SESSION['bbm_try_profile']]
			: null;

		$method = $subActions[$sa];
		self::$method();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	protected static function notice($message, $type = 'info')
	{
		$_SESSION['bbm_notice'] = array('text' => $message, 'type' => $type);
	}

	protected static function back($sa, $extra = '')
	{
		redirectexit('action=admin;area=bbcmanager;sa=' . $sa . $extra);
	}

	/**
	 * Tag names provided by SMF and other mods (not this one).
	 *
	 * @return array name => number of definitions
	 */
	public static function foreignTags()
	{
		if (self::$foreign !== null)
			return self::$foreign;

		$names = array();
		$codes = parse_bbc(false);
		if (is_array($codes))
			foreach ($codes as $code)
				if (isset($code['tag']) && empty($code['bbm_id']))
				{
					$tag = strtolower($code['tag']);
					$names[$tag] = isset($names[$tag]) ? $names[$tag] + 1 : 1;
				}

		return self::$foreign = $names;
	}

	/**
	 * Tags a profile can allow: every tag SMF knows, plus this mod's
	 * disabled tags.
	 */
	protected static function allowableTags()
	{
		$names = array_keys(self::foreignTags());
		foreach (BBM::config()['tags'] as $t)
			$names[] = $t['tag'];

		$names = array_values(array_unique($names));
		sort($names);

		return $names;
	}

	protected static function boardList()
	{
		global $smcFunc;

		$boards = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, b.child_level, c.name AS cat_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$boards[(int) $row['id_board']] = $row;
		$smcFunc['db_free_result']($request);

		return $boards;
	}

	protected static function groupList()
	{
		global $smcFunc, $txt;

		$groups = array(-1 => $txt['bbm_guests'], 0 => $txt['bbm_regular_members']);
		$request = $smcFunc['db_query']('', '
			SELECT id_group, group_name
			FROM {db_prefix}membergroups
			ORDER BY min_posts, id_group',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$groups[(int) $row['id_group']] = $row['group_name'];
		$smcFunc['db_free_result']($request);

		return $groups;
	}

	/* ------------------------------------------------------------------
	 * Tags
	 * ---------------------------------------------------------------- */

	public static function tags()
	{
		global $context, $smcFunc, $txt, $modSettings;

		$config = BBM::config(true);

		foreach (array('toggle', 'delete', 'move') as $action)
		{
			if (!isset($_GET[$action]))
				continue;

			checkSession('get');
			$id = (int) $_GET[$action];
			if (!isset($config['tags'][$id]))
				self::back('tags');

			$tag = $config['tags'][$id];
			self::baseline();

			if ($action === 'toggle')
			{
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}bbm_tags
					SET enabled = {int:enabled}
					WHERE id_tag = {int:id}',
					array('enabled' => $tag['enabled'] ? 0 : 1, 'id' => $id)
				);
				self::afterChange(sprintf($tag['enabled'] ? $txt['bbm_log_disabled'] : $txt['bbm_log_enabled'], $tag['tag']));
			}
			elseif ($action === 'delete')
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}bbm_tags
					WHERE id_tag = {int:id}',
					array('id' => $id)
				);
				self::afterChange(sprintf($txt['bbm_log_deleted'], $tag['tag']));
				self::notice(sprintf($txt['bbm_log_deleted'], $tag['tag']));
			}
			else
			{
				$ids = array_keys($config['tags']);
				$pos = array_search($id, $ids, true);
				$swap = isset($_GET['dir']) && $_GET['dir'] === 'up' ? $pos - 1 : $pos + 1;
				if ($swap >= 0 && $swap < count($ids))
				{
					list ($ids[$pos], $ids[$swap]) = array($ids[$swap], $ids[$pos]);
					foreach ($ids as $order => $tag_id)
						$smcFunc['db_query']('', '
							UPDATE {db_prefix}bbm_tags
							SET sort_order = {int:order}
							WHERE id_tag = {int:id}',
							array('order' => $order, 'id' => $tag_id)
						);
					BBM::clearCache();
				}
			}

			self::back('tags');
		}

		$foreign = self::foreignTags();
		$disabled = array_map('trim', explode(',', isset($modSettings['disabledBBC']) ? strtolower($modSettings['disabledBBC']) : ''));

		$context['bbm_tags'] = array();
		foreach ($config['tags'] as $id => $t)
		{
			list ($open, $close) = BBM::insertion($t);
			$t['usage'] = $open . ($t['mode'] === 'closed' ? '' : '…' . $close);
			$t['conflict'] = isset($foreign[$t['tag']]);
			$t['core_disabled'] = in_array($t['tag'], $disabled, true);
			$context['bbm_tags'][$id] = $t;
		}

		$context['sub_template'] = 'bbm_tags';
	}

	protected static function blankTag()
	{
		return array(
			'id_tag' => 0, 'tag' => '', 'title' => '', 'description' => '', 'mode' => 'parsed',
			'block_level' => true, 'params' => array(), 'template' => '<div class="bbm-box">{content}</div>',
			'button' => true, 'icon' => '', 'tooltip' => '', 'shortcut' => '', 'enabled' => true, 'sort_order' => 0,
		);
	}

	public static function editTag()
	{
		global $context, $smcFunc, $txt;

		$config = BBM::config(true);
		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

		if ($id && !isset($config['tags'][$id]))
			fatal_lang_error('bbm_not_found', false);

		$existing = $id ? $config['tags'][$id] : null;
		$context['bbm_errors'] = array();

		if (isset($_POST['save']))
		{
			checkSession();

			$errors = array();
			$def = self::validateTag(self::collectTagForm(), $id, $errors);

			$def['icon'] = $existing ? $existing['icon'] : '';
			if (!empty($_POST['icon_remove']))
				$def['icon'] = '';

			if (empty($errors) && !empty($_FILES['icon']) && $_FILES['icon']['error'] !== UPLOAD_ERR_NO_FILE)
			{
				$file = self::handleIconUpload($def['tag'], $errors);
				if ($file !== '')
					$def['icon'] = $file;
			}

			if (empty($errors))
			{
				self::baseline();
				self::saveTag($def, $id);
				self::afterChange(sprintf($id ? $txt['bbm_log_updated'] : $txt['bbm_log_added'], $def['tag']));
				self::notice(sprintf($id ? $txt['bbm_log_updated'] : $txt['bbm_log_added'], $def['tag']));
				self::back('tags');
			}

			$def['id_tag'] = $id;
			$context['bbm_errors'] = $errors;
			$context['bbm_tag'] = $def;
		}
		else
			$context['bbm_tag'] = $existing ?: self::blankTag();

		$context['bbm_starters'] = self::starterDesigns();
		$context['page_title'] = $id ? sprintf($txt['bbm_edit_tag'], $existing['tag']) : $txt['bbm_add_tag'];
		$context['sub_template'] = 'bbm_edit_tag';
	}

	/**
	 * Raw values from the tag form, before validation.
	 */
	protected static function collectTagForm()
	{
		$params = array();
		if (!empty($_POST['param']) && is_array($_POST['param']))
			foreach ($_POST['param'] as $p)
				if (is_array($p) && isset($p['name']) && trim((string) $p['name']) !== '')
					$params[] = array(
						'name' => (string) $p['name'],
						'type' => isset($p['type']) ? (string) $p['type'] : 'text',
						'default' => isset($p['default']) ? (string) $p['default'] : '',
						'required' => !empty($p['required']),
						'choices' => isset($p['choices']) ? (string) $p['choices'] : '',
						'min' => isset($p['min']) ? (string) $p['min'] : '',
						'max' => isset($p['max']) ? (string) $p['max'] : '',
						'maxlen' => isset($p['maxlen']) ? (string) $p['maxlen'] : '',
					);

		$get = function ($key, $default = '')
		{
			return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : $default;
		};

		return array(
			'tag' => $get('tag'),
			'title' => $get('title'),
			'description' => $get('description'),
			'mode' => $get('mode', 'parsed'),
			'block_level' => !empty($_POST['block_level']),
			'params' => $params,
			'template' => str_replace("\r", '', $get('template')),
			'button' => !empty($_POST['button']),
			'tooltip' => $get('tooltip'),
			'shortcut' => $get('shortcut'),
			'enabled' => !empty($_POST['enabled']),
		);
	}

	/**
	 * Check and normalise a tag definition (from the form, the library
	 * or an import). Errors are added to $errors.
	 */
	public static function validateTag(array $d, $id_tag, array &$errors, $check_own = true)
	{
		global $txt;

		$tag = strtolower(trim(isset($d['tag']) ? (string) $d['tag'] : ''));
		if (!preg_match('~^[a-z][a-z0-9]{0,29}$~', $tag))
			$errors[] = $txt['bbm_err_tag_name'];
		else
		{
			if ($check_own)
				foreach (BBM::config()['tags'] as $t)
					if ($t['tag'] === $tag && (int) $t['id_tag'] !== (int) $id_tag)
					{
						$errors[] = sprintf($txt['bbm_err_tag_exists'], $tag);
						break;
					}

			$foreign = self::foreignTags();
			if (isset($foreign[$tag]))
				$errors[] = sprintf($txt['bbm_err_tag_conflict'], $tag);
		}

		$title = trim(strip_tags(isset($d['title']) ? (string) $d['title'] : ''));
		if ($title === '')
			$errors[] = $txt['bbm_err_title'];

		$mode = isset($d['mode']) && in_array($d['mode'], BBM::$modes, true) ? $d['mode'] : 'parsed';

		$params = array();
		$names = array();
		$raw_params = isset($d['params']) && is_array($d['params']) ? array_values($d['params']) : array();

		if ($mode === 'closed' && !empty($raw_params))
			$errors[] = $txt['bbm_err_closed_params'];
		elseif (count($raw_params) > BBM::MAX_PARAMS)
			$errors[] = sprintf($txt['bbm_err_too_many_params'], BBM::MAX_PARAMS);
		else
			foreach ($raw_params as $p)
			{
				if (!is_array($p))
					continue;

				$name = strtolower(trim(isset($p['name']) ? (string) $p['name'] : ''));
				if (!preg_match('~^[a-z][a-z0-9_]{0,19}$~', $name) || $name === 'content')
				{
					$errors[] = sprintf($txt['bbm_err_param_name'], BBM::esc($name));
					continue;
				}
				if (isset($names[$name]))
				{
					$errors[] = sprintf($txt['bbm_err_param_dupe'], $name);
					continue;
				}
				$names[$name] = true;

				$type = isset($p['type']) && in_array($p['type'], BBM::$param_types, true) ? $p['type'] : 'text';

				$choices = array();
				if ($type === 'choice')
				{
					$list = is_array(isset($p['choices']) ? $p['choices'] : null) ? $p['choices'] : preg_split('~[\n,]+~', isset($p['choices']) ? (string) $p['choices'] : '');
					foreach ($list as $c)
					{
						$c = BBM::textSubstr(trim(strip_tags((string) $c)), 0, 40);
						if ($c !== '' && !in_array($c, $choices, true))
							$choices[] = $c;
					}
					$choices = array_slice($choices, 0, 30);
					if (empty($choices))
						$errors[] = sprintf($txt['bbm_err_choices'], $name);
				}

				$num = function ($v)
				{
					$v = trim((string) $v);
					return $v !== '' && is_numeric($v) ? (string) (float) $v : '';
				};

				$param = array(
					'name' => $name,
					'type' => $type,
					'default' => BBM::textSubstr(trim(isset($p['default']) ? (string) $p['default'] : ''), 0, 500),
					'required' => !empty($p['required']),
					'choices' => $choices,
					'min' => $type === 'number' ? $num(isset($p['min']) ? $p['min'] : '') : '',
					'max' => $type === 'number' ? $num(isset($p['max']) ? $p['max'] : '') : '',
					'maxlen' => $type === 'text' && isset($p['maxlen']) && (int) $p['maxlen'] > 0 ? (string) min(500, (int) $p['maxlen']) : '',
				);

				if ($param['default'] !== '' && BBM::cleanParam($param, $param['default']) === null)
					$errors[] = sprintf($txt['bbm_err_param_default'], $name, $txt['bbm_type_' . $type]);

				$params[] = $param;
			}

		$template = trim(isset($d['template']) ? (string) $d['template'] : '');
		if ($template === '')
			$errors[] = $txt['bbm_err_template_empty'];
		elseif (strlen($template) > 20000)
			$errors[] = $txt['bbm_err_template_long'];
		else
			self::checkTemplate($template, $mode, $params, $errors);

		$shortcut = self::normaliseShortcut(isset($d['shortcut']) ? (string) $d['shortcut'] : '', $errors);

		return array(
			'tag' => $tag,
			'title' => BBM::textSubstr($title, 0, 80),
			'description' => BBM::textSubstr(trim(strip_tags(isset($d['description']) ? (string) $d['description'] : '')), 0, 255),
			'mode' => $mode,
			'block_level' => !empty($d['block_level']),
			'params' => $params,
			'template' => $template,
			'button' => !empty($d['button']),
			'icon' => isset($d['icon']) ? (string) $d['icon'] : '',
			'tooltip' => BBM::textSubstr(trim(strip_tags(isset($d['tooltip']) ? (string) $d['tooltip'] : '')), 0, 100),
			'shortcut' => $shortcut,
			'enabled' => !empty($d['enabled']),
		);
	}

	/**
	 * Validate a deliberately limited HTML fragment. We cannot safely validate
	 * arbitrary markup with a denylist: character references and malformed
	 * attributes can bypass one. This scanner accepts only balanced, safe
	 * elements with quoted, explicitly allowed attributes.
	 */
	public static function checkTemplate($tpl, $mode, array $params, array &$errors)
	{
		global $txt;

		$types = array();
		foreach ($params as $param)
			$types[$param['name']] = $param;

		if ($mode === 'closed')
		{
			if (strpos($tpl, '{content}') !== false)
				$errors[] = $txt['bbm_err_closed_content'];
		}
		elseif (substr_count($tpl, '{content}') !== 1)
			$errors[] = $txt['bbm_err_content_once'];

		preg_match_all('~\{([a-z][a-z0-9_]*)\}~', $tpl, $placeholders);
		foreach (array_unique($placeholders[1]) as $name)
			if ($name !== 'content' && !isset($types[$name]))
				$errors[] = sprintf($txt['bbm_err_unknown_placeholder'], $name);

		$fail = function () use (&$errors, $txt)
		{
			$errors[] = $txt['bbm_err_template_unsafe'];
		};

		// No comments, processing instructions, doctype declarations or CDATA.
		if (preg_match('~<\s*[!?]~', $tpl))
			$fail();

		$elements = array_fill_keys(array(
			'a', 'div', 'span', 'strong', 'em', 'b', 'i', 'u', 's', 'del',
			'small', 'p', 'br', 'hr', 'ul', 'ol', 'li', 'blockquote',
			'code', 'pre', 'kbd', 'mark', 'details', 'summary', 'img',
			'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'sup', 'sub'
		), true);
		$voids = array('img' => true, 'br' => true, 'hr' => true);
		$attributes = array_fill_keys(array(
			'class', 'id', 'title', 'role', 'alt', 'href', 'src', 'target',
			'rel', 'loading', 'referrerpolicy', 'style', 'width', 'height',
			'tabindex', 'dir', 'lang', 'value', 'min', 'max', 'colspan',
			'rowspan'
		), true);
		$stack = array();
		$cursor = 0;

		// The tokenizer respects quoted > characters; markup syntax inside
		// attribute values is rejected below, never interpreted as extra tags.
		preg_match_all('~<(?:(?:"[^"]*")|(?:\'[^\']*\')|[^\'">])*>~s', $tpl, $tags, PREG_OFFSET_CAPTURE);
		foreach ($tags[0] as $hit)
		{
			$raw = $hit[0];
			$offset = $hit[1];
			if (strpos(substr($tpl, $cursor, $offset - $cursor), '<') !== false)
			{
				$fail();
				break;
			}
			$cursor = $offset + strlen($raw);

			if (!preg_match('~^<\s*(/?)\s*([a-z][a-z0-9]*)\b(.*?)>$~is', $raw, $tag))
			{
				$fail();
				break;
			}
			$name = strtolower($tag[2]);
			if (!isset($elements[$name]))
			{
				$fail();
				break;
			}

			$remainder = $tag[3];
			if ($tag[1] === '/')
			{
				if (trim($remainder) !== '' || isset($voids[$name]) || array_pop($stack) !== $name)
					$fail();
				continue;
			}

			$self_closing = preg_match('~/\s*$~', $remainder) === 1;
			if ($self_closing)
				$remainder = preg_replace('~/\s*$~', '', $remainder);

			$seen = array();
			while (trim($remainder) !== '')
			{
				if (!preg_match('~^\s+([a-z][a-z0-9_-]*)\s*=\s*(?:"([^"<>]*)"|\'([^\'<>]*)\')~i', $remainder, $attr))
				{
					$fail();
					break;
				}
				$remainder = substr($remainder, strlen($attr[0]));
				$key = strtolower($attr[1]);
				$value = isset($attr[3]) && $attr[3] !== '' ? $attr[3] : $attr[2];

				if ((isset($seen[$key])) || (!isset($attributes[$key]) && !preg_match('~^(?:aria|data)-[a-z0-9_-]+$~', $key)))
				{
					$fail();
					continue;
				}
				$seen[$key] = true;

				// Interpret entities exactly as a browser would in an attribute,
				// including &colon;, &#x3a;, and encoded whitespace.
				$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if (preg_match('~[<>\x00-\x1f\x7f]~', $decoded) || strpos($decoded, '{content}') !== false)
				{
					$fail();
					continue;
				}

				preg_match_all('~\{([a-z][a-z0-9_]*)\}~', $decoded, $attr_placeholders);
				if ($key === 'style')
				{
					$without_params = preg_replace('~\{[a-z][a-z0-9_]*\}~', '0', $decoded);
					if (preg_match('~(?:url\s*\(|expression\s*\(|@import|behavior\s*:|-moz-binding\s*:|\\\\|[{}])~i', $without_params))
						$fail();
				}

				foreach ($attr_placeholders[1] as $param_name)
				{
					if (!isset($types[$param_name]))
						continue;
					$type = $types[$param_name]['type'];
					if ($key === 'style' && !in_array($type, array('color', 'number', 'choice'), true))
						$errors[] = sprintf($txt['bbm_err_style_type'], $param_name);
					if ($key === 'style' && $type === 'choice')
						foreach ($types[$param_name]['choices'] as $choice)
							if (!preg_match('~^[a-z0-9 #%.-]*$~i', $choice))
							{
								$errors[] = sprintf($txt['bbm_err_style_type'], $param_name);
								break;
							}
				}

				if (in_array($key, array('href', 'src'), true))
				{
					if (($key === 'href' && $name !== 'a') || ($key === 'src' && $name !== 'img'))
					{
						$fail();
						continue;
					}

					// A whole-attribute URL placeholder is safe because its type
					// is checked by BBM::cleanParam on every render.
					if (preg_match('~^\{([a-z][a-z0-9_]*)\}$~', $decoded, $whole))
					{
						if (!isset($types[$whole[1]]) || $types[$whole[1]]['type'] !== 'url')
							$errors[] = sprintf($txt['bbm_err_url_type'], $whole[1], $key);
					}
					else
					{
						$placeholder_url = preg_replace('~\{[a-z][a-z0-9_]*\}~', 'example', $decoded);
						if (!preg_match('~^https?://[a-z0-9.-]+(?::[0-9]{1,5})?(?:/[^\s"\']*)?$~i', $placeholder_url))
								$fail();
						foreach ($attr_placeholders[1] as $param_name)
							if (isset($types[$param_name]) && $types[$param_name]['type'] === 'url')
								$errors[] = sprintf($txt['bbm_err_url_type'], $param_name, $key);
					}
				}
			}
			if ($self_closing && !isset($voids[$name]))
				$fail();
			elseif (!isset($voids[$name]))
				$stack[] = $name;
		}

		if (strpos(substr($tpl, $cursor), '<') !== false || !empty($stack))
			$fail();
		$errors = array_values(array_unique($errors));
	}

	protected static function normaliseShortcut($value, array &$errors)
	{
		global $txt;

		$value = strtolower(str_replace(' ', '', trim($value)));
		if ($value === '')
			return '';

		$parts = explode('+', $value);
		$key = array_pop($parts);
		$mods = array_values(array_unique($parts));
		$order = array('ctrl', 'alt', 'shift', 'meta');

		if (empty($mods) || array_diff($mods, $order) || !preg_match('~^(?:[a-z0-9]|f(?:[1-9]|1[0-2]))$~', $key))
		{
			$errors[] = $txt['bbm_err_shortcut'];
			return '';
		}

		$mods = array_values(array_intersect($order, $mods));
		$shortcut = implode('+', $mods) . '+' . $key;

		$reserved = array('ctrl+a', 'ctrl+b', 'ctrl+c', 'ctrl+i', 'ctrl+u', 'ctrl+v', 'ctrl+x', 'ctrl+y', 'ctrl+z', 'ctrl+s', 'ctrl+p', 'ctrl+f', 'ctrl+r', 'ctrl+w', 'ctrl+t', 'ctrl+n', 'ctrl+enter', 'alt+s', 'alt+p');
		if (in_array($shortcut, $reserved, true))
			$errors[] = sprintf($txt['bbm_err_shortcut_reserved'], $shortcut);

		return $shortcut;
	}

	protected static function saveTag(array $def, $id)
	{
		global $smcFunc;

		$row = array(
			'tag' => $def['tag'],
			'title' => $def['title'],
			'description' => $def['description'],
			'mode' => $def['mode'],
			'block_level' => $def['block_level'] ? 1 : 0,
			'params' => json_encode($def['params']),
			'template' => $def['template'],
			'button' => $def['button'] ? 1 : 0,
			'icon' => $def['icon'],
			'tooltip' => $def['tooltip'],
			'shortcut' => $def['shortcut'],
			'enabled' => $def['enabled'] ? 1 : 0,
		);

		if ($id)
		{
			$set = array();
			foreach ($row as $key => $value)
				$set[] = $key . ' = {' . (is_int($value) ? 'int' : 'string') . ':' . $key . '}';

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}bbm_tags
				SET ' . implode(', ', $set) . '
				WHERE id_tag = {int:id_tag}',
				$row + array('id_tag' => $id)
			);
			return $id;
		}

		$request = $smcFunc['db_query']('', '
			SELECT COALESCE(MAX(sort_order), 0) + 1
			FROM {db_prefix}bbm_tags',
			array()
		);
		list ($order) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return self::insertTag($row + array('sort_order' => (int) $order));
	}

	protected static function insertTag(array $row)
	{
		global $smcFunc;

		return $smcFunc['db_insert']('insert',
			'{db_prefix}bbm_tags',
			array(
				'tag' => 'string-30', 'title' => 'string-80', 'description' => 'string-255', 'mode' => 'string-10',
				'block_level' => 'int', 'params' => 'string', 'template' => 'string', 'button' => 'int',
				'icon' => 'string-80', 'tooltip' => 'string-100', 'shortcut' => 'string-30', 'enabled' => 'int', 'sort_order' => 'int',
			),
			array(
				$row['tag'], $row['title'], $row['description'], $row['mode'], $row['block_level'], $row['params'],
				$row['template'], $row['button'], $row['icon'], $row['tooltip'], $row['shortcut'], $row['enabled'], $row['sort_order'],
			),
			array('id_tag'),
			1
		);
	}

	/**
	 * Live preview for the tag builder (AJAX).
	 */
	public static function preview()
	{
		global $context, $txt;

		checkSession();

		$errors = array();
		$def = self::validateTag(self::collectTagForm(), isset($_POST['id']) ? (int) $_POST['id'] : 0, $errors);

		// A name clash isn't a reason to hide the preview.
		$errors = array_values(array_filter($errors, function ($e) use ($txt, $def)
		{
			return $e !== sprintf($txt['bbm_err_tag_exists'], $def['tag']) && $e !== sprintf($txt['bbm_err_tag_conflict'], $def['tag']) && $e !== $txt['bbm_err_tag_name'] && $e !== $txt['bbm_err_title'];
		}));

		$context['bbm_preview_errors'] = $errors;
		$context['bbm_preview'] = empty($errors) ? self::renderSample($def, isset($_POST['sample_option']) ? (string) $_POST['sample_option'] : '', isset($_POST['sample_content']) ? (string) $_POST['sample_content'] : '') : '';

		$context['template_layers'] = array();
		$context['sub_template'] = 'bbm_preview';
	}

	public static function renderSample(array $def, $option, $content)
	{
		global $smcFunc, $txt;

		$def += array('id_tag' => 0);
		list ($before, $after) = BBM::splitTemplate($def);

		$values = $option === '' || empty($def['params'])
			? BBM::defaults($def)
			: BBM::values($def, $smcFunc['htmlspecialchars']($option, ENT_QUOTES));

		if ($def['mode'] === 'closed')
			return BBM::fill($before, BBM::defaults($def));

		if ($content === '')
			$content = $txt['bbm_sample_content'];

		$escaped = str_replace("\n", '<br>', $smcFunc['htmlspecialchars'](str_replace("\r", '', $content), ENT_QUOTES));
		$inner = $def['mode'] === 'unparsed' ? $escaped : parse_bbc($escaped);

		return BBM::fill($before, $values) . $inner . BBM::fill($after, $values);
	}

	/**
	 * Ready-made layouts for the visual builder's "Start from a design".
	 */
	protected static function starterDesigns()
	{
		return array(
			'box' => '<div class="bbm-box">{content}</div>',
			'alert' => '<div class="bbm-alert bbm-alert-info" role="note"><div class="bbm-alert-body">{content}</div></div>',
			'titled' => '<div class="bbm-box"><div class="bbm-box-title">Title</div><div class="bbm-box-body">{content}</div></div>',
			'collapsible' => '<details class="bbm-panel"><summary>Click to expand</summary><div class="bbm-panel-body">{content}</div></details>',
			'badge' => '<span class="bbm-badge">{content}</span>',
			'columns' => '<div class="bbm-columns">{content}</div>',
		);
	}

	/* ------------------------------------------------------------------
	 * Library
	 * ---------------------------------------------------------------- */

	public static function presets()
	{
		$alert_types = array('info', 'success', 'warning', 'danger', 'note');

		return array(
			'alert' => array(
				'tag' => 'alert', 'title' => 'Alert box', 'description' => 'Coloured alert with an optional title.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true, 'tooltip' => 'Alert box',
				'params' => array(
					array('name' => 'type', 'type' => 'choice', 'default' => 'info', 'choices' => $alert_types),
					array('name' => 'title', 'type' => 'text', 'default' => '', 'maxlen' => 80),
				),
				'template' => '<div class="bbm-alert bbm-alert-{type}" role="note"><strong class="bbm-alert-title">{title}</strong><div class="bbm-alert-body">{content}</div></div>',
				'sample' => array('warning|Heads up', 'Maintenance starts at [b]22:00[/b].'),
			),
			'info' => array(
				'tag' => 'info', 'title' => 'Information box', 'description' => 'Simple information box.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true, 'params' => array(),
				'template' => '<div class="bbm-alert bbm-alert-info" role="note"><div class="bbm-alert-body">{content}</div></div>',
				'sample' => array('', 'Useful information for readers.'),
			),
			'warning' => array(
				'tag' => 'warning', 'title' => 'Warning box', 'description' => 'For moderator warnings or important notices.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true, 'params' => array(),
				'template' => '<div class="bbm-alert bbm-alert-warning" role="note"><div class="bbm-alert-body">{content}</div></div>',
				'sample' => array('', 'Please keep this topic on track.'),
			),
			'success' => array(
				'tag' => 'success', 'title' => 'Success box', 'description' => 'Confirmation or solved message.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true, 'params' => array(),
				'template' => '<div class="bbm-alert bbm-alert-success" role="note"><div class="bbm-alert-body">{content}</div></div>',
				'sample' => array('', 'This issue has been fixed.'),
			),
			'modnote' => array(
				'tag' => 'modnote', 'title' => 'Moderator note', 'description' => 'Labelled note for moderators. Restrict it to moderator groups with a profile.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(array('name' => 'title', 'type' => 'text', 'default' => 'Moderator note', 'maxlen' => 60)),
				'template' => '<div class="bbm-modnote" role="note"><strong class="bbm-modnote-title">{title}</strong><div class="bbm-modnote-body">{content}</div></div>',
				'sample' => array('', 'Topic moved to the correct board.'),
			),
			'announce' => array(
				'tag' => 'announce', 'title' => 'Announcement banner', 'description' => 'Large banner for announcements. Restrict it to administrators with a profile.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(
					array('name' => 'title', 'type' => 'text', 'default' => 'Announcement', 'maxlen' => 100),
					array('name' => 'color', 'type' => 'color', 'default' => '#3d6e9f'),
				),
				'template' => '<div class="bbm-announce" style="--bbm-accent:{color}"><div class="bbm-announce-title">{title}</div><div class="bbm-announce-body">{content}</div></div>',
				'sample' => array('Forum upgrade|#7a4b9c', 'We are moving to a new server this weekend.'),
			),
			'panel' => array(
				'tag' => 'panel', 'title' => 'Expandable panel', 'description' => 'Collapsible section with a title.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(array('name' => 'title', 'type' => 'text', 'default' => 'Click to expand', 'maxlen' => 120)),
				'template' => '<details class="bbm-panel"><summary>{title}</summary><div class="bbm-panel-body">{content}</div></details>',
				'sample' => array('Full changelog', 'Hidden until opened.'),
			),
			'spoiler' => array(
				'tag' => 'spoiler', 'title' => 'Spoiler', 'description' => 'Hides content until clicked.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(array('name' => 'title', 'type' => 'text', 'default' => 'Spoiler', 'maxlen' => 80)),
				'template' => '<details class="bbm-panel bbm-spoiler"><summary>{title}</summary><div class="bbm-panel-body">{content}</div></details>',
				'sample' => array('', 'The butler did it.'),
			),
			'progress' => array(
				'tag' => 'progress', 'title' => 'Progress bar', 'description' => 'Progress indicator from 0 to 100 with a label.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(
					array('name' => 'value', 'type' => 'number', 'default' => '50', 'min' => '0', 'max' => '100', 'required' => true),
					array('name' => 'color', 'type' => 'color', 'default' => ''),
				),
				'template' => '<div class="bbm-progress" role="progressbar" aria-valuenow="{value}" aria-valuemin="0" aria-valuemax="100"><div class="bbm-progress-track"><div class="bbm-progress-bar" style="width:{value}%;background-color:{color}"></div></div><div class="bbm-progress-label">{content} <span>{value}%</span></div></div>',
				'sample' => array('72|seagreen', 'Translation progress'),
			),
			'box' => array(
				'tag' => 'box', 'title' => 'Styled box', 'description' => 'Content box with a coloured title bar.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(
					array('name' => 'color', 'type' => 'color', 'default' => '#557ea0'),
					array('name' => 'title', 'type' => 'text', 'default' => '', 'maxlen' => 100),
				),
				'template' => '<div class="bbm-box" style="--bbm-accent:{color}"><div class="bbm-box-title">{title}</div><div class="bbm-box-body">{content}</div></div>',
				'sample' => array('crimson|Rules', 'Be kind to each other.'),
			),
			'card' => array(
				'tag' => 'card', 'title' => 'Media card', 'description' => 'Linked card with title, optional image and description.',
				'mode' => 'parsed', 'block_level' => true, 'button' => true,
				'params' => array(
					array('name' => 'url', 'type' => 'url', 'default' => '', 'required' => true),
					array('name' => 'title', 'type' => 'text', 'default' => 'Link', 'maxlen' => 120),
					array('name' => 'image', 'type' => 'url', 'default' => ''),
				),
				'template' => '<a class="bbm-card" href="{url}" target="_blank" rel="nofollow ugc noopener noreferrer"><img class="bbm-card-img" src="{image}" alt="" loading="lazy" referrerpolicy="no-referrer"><span class="bbm-card-body"><span class="bbm-card-title">{title}</span><span class="bbm-card-text">{content}</span></span></a>',
				'sample' => array('https://www.simplemachines.org|Simple Machines', 'Free, open source forum software.'),
			),
			'highlight' => array(
				'tag' => 'highlight', 'title' => 'Highlight', 'description' => 'Highlighted text (inline).',
				'mode' => 'parsed', 'block_level' => false, 'button' => true,
				'params' => array(array('name' => 'color', 'type' => 'color', 'default' => 'yellow')),
				'template' => '<mark class="bbm-mark" style="background-color:{color}">{content}</mark>',
				'sample' => array('', 'important words'),
			),
			'kbd' => array(
				'tag' => 'kbd', 'title' => 'Keyboard key', 'description' => 'Shows a key, e.g. [kbd]Ctrl[/kbd].',
				'mode' => 'unparsed', 'block_level' => false, 'button' => true, 'params' => array(),
				'template' => '<kbd class="bbm-kbd">{content}</kbd>',
				'sample' => array('', 'Ctrl'),
			),
			'badge' => array(
				'tag' => 'badge', 'title' => 'Badge', 'description' => 'Small coloured label (inline).',
				'mode' => 'parsed', 'block_level' => false, 'button' => true,
				'params' => array(array('name' => 'color', 'type' => 'color', 'default' => '#557ea0')),
				'template' => '<span class="bbm-badge" style="--bbm-accent:{color}">{content}</span>',
				'sample' => array('seagreen', 'Solved'),
			),
			'columns' => array(
				'tag' => 'columns', 'title' => 'Two columns', 'description' => 'Splits content into two columns (one column on mobile).',
				'mode' => 'parsed', 'block_level' => true, 'button' => false, 'params' => array(),
				'template' => '<div class="bbm-columns">{content}</div>',
				'sample' => array('', 'Text flows into two columns on wide screens.'),
			),
		);
	}

	public static function library()
	{
		global $context, $txt;

		$presets = self::presets();
		$own = array();
		foreach (BBM::config(true)['tags'] as $t)
			$own[$t['tag']] = true;
		$foreign = self::foreignTags();

		if (isset($_POST['add'], $presets[$_POST['add']]))
		{
			checkSession();

			$preset = $presets[$_POST['add']];
			$errors = array();
			$def = self::validateTag($preset + array('enabled' => true), 0, $errors);

			if (empty($errors))
			{
				self::baseline();
				self::saveTag($def + array('icon' => ''), 0);
				self::afterChange(sprintf($txt['bbm_log_added'], $def['tag']));
				self::notice(sprintf($txt['bbm_library_added'], $def['tag']));
			}
			else
				self::notice(implode('<br>', $errors), 'error');

			self::back('library');
		}

		if (isset($_POST['add_profile']))
		{
			checkSession();
			self::baseline();
			self::createSimpleProfile();
			self::afterChange($txt['bbm_log_simple_profile']);
			self::notice($txt['bbm_simple_profile_added']);
			self::back('profiles');
		}

		$context['bbm_presets'] = array();
		foreach ($presets as $key => $p)
		{
			$def = $p + array('id_tag' => 0, 'icon' => '', 'shortcut' => '', 'enabled' => true);
			list ($open, $close) = BBM::insertion($def);
			$context['bbm_presets'][$key] = array(
				'tag' => $p['tag'],
				'title' => $p['title'],
				'description' => $p['description'],
				'usage' => ($p['sample'][0] !== '' ? '[' . $p['tag'] . '=' . $p['sample'][0] . ']' : $open) . $p['sample'][1] . $close,
				'preview' => self::renderSample($def, $p['sample'][0], $p['sample'][1]),
				'installed' => isset($own[$p['tag']]),
				'conflict' => isset($foreign[$p['tag']]),
			);
		}

		$context['sub_template'] = 'bbm_library';
	}

	/**
	 * A cut-down profile for ordinary members: basic formatting only.
	 */
	protected static function createSimpleProfile()
	{
		global $smcFunc, $txt;

		$toolbar = array(
			array('bold', 'italic', 'underline', 'strike', '|', 'link', 'image', '|', 'quote', 'code', '|', 'bulletlist', 'orderedlist', '|', 'removeformat', 'source'),
		);
		$allowed = array('b', 'i', 'u', 's', 'url', 'iurl', 'img', 'quote', 'code', 'list', 'li', '*', 'email', 'youtube', 'me', 'nobbc', 'br', 'hr', 'left', 'center', 'right', 'size', 'color');

		$smcFunc['db_insert']('insert',
			'{db_prefix}bbm_profiles',
			array('name' => 'string-60', 'toolbar' => 'string', 'allowed' => 'string', 'is_default' => 'int'),
			array($txt['bbm_simple_profile_name'], json_encode($toolbar), json_encode(array_values(array_intersect($allowed, self::allowableTags()))), 0),
			array('id_profile')
		);
	}

	/* ------------------------------------------------------------------
	 * Profiles
	 * ---------------------------------------------------------------- */

	public static function profiles()
	{
		global $context, $smcFunc, $txt;

		$config = BBM::config(true);

		if (isset($_GET['try']) || isset($_GET['untry']))
		{
			checkSession('get');
			if (isset($_GET['try']) && isset($config['profiles'][(int) $_GET['try']]))
			{
				$_SESSION['bbm_try_profile'] = (int) $_GET['try'];
				self::notice(sprintf($txt['bbm_trying_now'], $smcFunc['htmlspecialchars']($config['profiles'][(int) $_GET['try']]['name'])));
			}
			else
				unset($_SESSION['bbm_try_profile']);

			self::back('profiles');
		}

		if (isset($_GET['delete']))
		{
			checkSession('get');
			$id = (int) $_GET['delete'];
			if (isset($config['profiles'][$id]))
			{
				self::baseline();
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_profiles WHERE id_profile = {int:id}', array('id' => $id));
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_rules WHERE id_profile = {int:id}', array('id' => $id));
				if (!empty($_SESSION['bbm_try_profile']) && (int) $_SESSION['bbm_try_profile'] === $id)
					unset($_SESSION['bbm_try_profile']);
				self::afterChange(sprintf($txt['bbm_log_profile_deleted'], $config['profiles'][$id]['name']));
			}

			self::back('profiles');
		}

		if (isset($_GET['default']))
		{
			checkSession('get');
			$id = (int) $_GET['default'];
			self::baseline();
			$smcFunc['db_query']('', 'UPDATE {db_prefix}bbm_profiles SET is_default = CASE WHEN id_profile = {int:id} THEN 1 ELSE 0 END', array('id' => $id));
			self::afterChange($txt['bbm_log_profile_default']);
			self::back('profiles');
		}

		$rule_counts = array();
		foreach ($config['rules'] as $rule)
			$rule_counts[$rule['id_profile']] = isset($rule_counts[$rule['id_profile']]) ? $rule_counts[$rule['id_profile']] + 1 : 1;

		$context['bbm_profiles'] = array();
		foreach ($config['profiles'] as $id => $p)
		{
			$buttons = 0;
			foreach ($p['toolbar'] as $row)
				foreach ((array) $row as $item)
					if ($item !== '|')
						$buttons++;

			$p['rules'] = isset($rule_counts[$id]) ? $rule_counts[$id] : 0;
			$p['buttons'] = $buttons;
			$context['bbm_profiles'][$id] = $p;
		}

		$context['sub_template'] = 'bbm_profiles';
	}

	public static function editProfile()
	{
		global $context, $smcFunc, $txt;

		$config = BBM::config(true);
		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
		if ($id && !isset($config['profiles'][$id]))
			fatal_lang_error('bbm_not_found', false);

		$profile = $id ? $config['profiles'][$id] : array('id_profile' => 0, 'name' => '', 'toolbar' => array(), 'allowed' => null, 'is_default' => false);
		$context['bbm_errors'] = array();

		if (isset($_POST['save']))
		{
			checkSession();

			$errors = array();
			$profile = self::validateProfile(array(
				'name' => isset($_POST['name']) ? (string) $_POST['name'] : '',
				'toolbar' => json_decode(isset($_POST['toolbar']) ? (string) $_POST['toolbar'] : '[]', true),
				'allowed' => !empty($_POST['allow_all']) ? null : (isset($_POST['allowed']) && is_array($_POST['allowed']) ? $_POST['allowed'] : array()),
				'is_default' => !empty($_POST['is_default']),
			), $errors);

			if (empty($errors))
			{
				self::baseline();

				if ($profile['is_default'])
					$smcFunc['db_query']('', 'UPDATE {db_prefix}bbm_profiles SET is_default = {int:no}', array('no' => 0));

				$values = array(
					'name' => $profile['name'],
					'toolbar' => json_encode($profile['toolbar']),
					'allowed' => $profile['allowed'] === null ? '' : json_encode($profile['allowed']),
					'is_default' => $profile['is_default'] ? 1 : 0,
				);

				if ($id)
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}bbm_profiles
						SET name = {string:name}, toolbar = {string:toolbar}, allowed = {string:allowed}, is_default = {int:is_default}
						WHERE id_profile = {int:id}',
						$values + array('id' => $id)
					);
				else
					$id = $smcFunc['db_insert']('insert',
						'{db_prefix}bbm_profiles',
						array('name' => 'string-60', 'toolbar' => 'string', 'allowed' => 'string', 'is_default' => 'int'),
						array_values($values),
						array('id_profile'),
						1
					);

				self::afterChange(sprintf($txt['bbm_log_profile_saved'], $profile['name']));
				self::notice(sprintf($txt['bbm_log_profile_saved'], $smcFunc['htmlspecialchars']($profile['name'])));

				if (!empty($_POST['try_after']))
				{
					$_SESSION['bbm_try_profile'] = (int) $id;
					self::notice(sprintf($txt['bbm_trying_now'], $smcFunc['htmlspecialchars']($profile['name'])));
				}

				self::back('profiles');
			}

			$context['bbm_errors'] = $errors;
			$profile['id_profile'] = $id;
		}

		// Buttons for the builder: SMF's and other mods', then ours.
		$buttons = array();
		foreach (BBM::knownButtons() as $code => $label)
			$buttons[] = array('code' => $code, 'label' => is_string($label) && $label !== '' ? $label : $code, 'custom' => false);
		foreach ($config['tags'] as $t)
			if ($t['button'])
				$buttons[] = array('code' => 'bbm_' . $t['tag'], 'label' => $t['title'] . ($t['enabled'] ? '' : ' (' . $txt['bbm_disabled'] . ')'), 'custom' => true);

		$own = array();
		foreach ($config['tags'] as $t)
			$own[$t['tag']] = $t['title'];

		$context['bbm_profile'] = $profile;
		$context['bbm_buttons'] = $buttons;
		$context['bbm_allowable'] = self::allowableTags();
		$context['bbm_own_tags'] = $own;
		$context['page_title'] = $id ? sprintf($txt['bbm_edit_profile'], $profile['name']) : $txt['bbm_add_profile'];
		$context['sub_template'] = 'bbm_edit_profile';
	}

	public static function validateProfile(array $p, array &$errors, $available_tags = null)
	{
		global $txt;

		$name = BBM::textSubstr(trim(strip_tags(isset($p['name']) ? (string) $p['name'] : '')), 0, 60);
		if ($name === '')
			$errors[] = $txt['bbm_err_profile_name'];

		$toolbar = array();
		foreach (is_array(isset($p['toolbar']) ? $p['toolbar'] : null) ? array_slice($p['toolbar'], 0, 6) : array() as $row)
		{
			$items = array();
			foreach (is_array($row) ? array_slice($row, 0, 80) : array() as $item)
				if (is_string($item) && ($item === '|' || preg_match('~^[a-z0-9_]{1,40}$~i', $item)))
					$items[] = $item;
			if (!empty($items))
				$toolbar[] = $items;
		}

		$allowed = null;
		if (isset($p['allowed']) && is_array($p['allowed']))
		{
			$allowable = $available_tags === null ? self::allowableTags() : $available_tags;
			$allowed = array();
			foreach ($p['allowed'] as $tag)
				if (is_string($tag) && in_array(strtolower($tag), $allowable, true))
					$allowed[] = strtolower($tag);
			$allowed = array_values(array_unique($allowed));
		}

		return array(
			'id_profile' => isset($p['id_profile']) ? (int) $p['id_profile'] : 0,
			'name' => $name,
			'toolbar' => $toolbar,
			'allowed' => $allowed,
			'is_default' => !empty($p['is_default']),
		);
	}

	/* ------------------------------------------------------------------
	 * Rules
	 * ---------------------------------------------------------------- */

	public static function rules()
	{
		global $context, $smcFunc, $txt;

		$config = BBM::config(true);

		if (isset($_GET['delete']))
		{
			checkSession('get');
			if (isset($config['rules'][(int) $_GET['delete']]))
			{
				self::baseline();
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_rules WHERE id_rule = {int:id}', array('id' => (int) $_GET['delete']));
				self::afterChange($txt['bbm_log_rule_deleted']);
			}
			self::back('rules');
		}

		$context['bbm_rules'] = $config['rules'];
		$context['bbm_profiles'] = $config['profiles'];
		$context['bbm_board_list'] = self::boardList();
		$context['bbm_group_list'] = self::groupList();
		$context['sub_template'] = 'bbm_rules';
	}

	public static function editRule()
	{
		global $context, $smcFunc, $txt;

		$config = BBM::config(true);
		if (empty($config['profiles']))
		{
			self::notice($txt['bbm_rule_need_profile'], 'error');
			self::back('profiles');
		}

		$id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
		if ($id && !isset($config['rules'][$id]))
			fatal_lang_error('bbm_not_found', false);

		$rule = $id ? $config['rules'][$id] : array('id_rule' => 0, 'id_profile' => (int) key($config['profiles']), 'contexts' => array('post'), 'boards' => array(), 'groups' => array(), 'priority' => 10);
		$boards = self::boardList();
		$groups = self::groupList();

		if (isset($_POST['save']))
		{
			checkSession();

			$rule = array(
				'id_rule' => $id,
				'id_profile' => isset($_POST['id_profile'], $config['profiles'][(int) $_POST['id_profile']]) ? (int) $_POST['id_profile'] : (int) key($config['profiles']),
				'contexts' => array_values(array_intersect(BBM::$contexts, isset($_POST['contexts']) ? (array) $_POST['contexts'] : array())),
				'boards' => array_values(array_intersect(array_keys($boards), array_map('intval', isset($_POST['boards']) ? (array) $_POST['boards'] : array()))),
				'groups' => array_values(array_intersect(array_keys($groups), array_map('intval', isset($_POST['groups']) ? (array) $_POST['groups'] : array()))),
				'priority' => max(-1000, min(1000, isset($_POST['priority']) ? (int) $_POST['priority'] : 0)),
			);

			self::baseline();
			$values = array(
				'id_profile' => $rule['id_profile'],
				'contexts' => implode(',', $rule['contexts']),
				'boards' => implode(',', $rule['boards']),
				'groups' => implode(',', $rule['groups']),
				'priority' => $rule['priority'],
			);

			if ($id)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}bbm_rules
					SET id_profile = {int:id_profile}, contexts = {string:contexts}, boards = {string:boards},
						id_groups = {string:groups}, priority = {int:priority}
					WHERE id_rule = {int:id}',
					$values + array('id' => $id)
				);
			else
				$smcFunc['db_insert']('insert',
					'{db_prefix}bbm_rules',
					array('id_profile' => 'int', 'contexts' => 'string-60', 'boards' => 'string', 'id_groups' => 'string', 'priority' => 'int'),
					array_values($values),
					array('id_rule')
				);

			self::afterChange(sprintf($txt['bbm_log_rule_saved'], $config['profiles'][$rule['id_profile']]['name']));
			self::notice($txt['bbm_rule_saved']);
			self::back('rules');
		}

		$context['bbm_rule'] = $rule;
		$context['bbm_profiles'] = $config['profiles'];
		$context['bbm_board_list'] = $boards;
		$context['bbm_group_list'] = $groups;
		$context['page_title'] = $id ? $txt['bbm_edit_rule'] : $txt['bbm_add_rule'];
		$context['sub_template'] = 'bbm_edit_rule';
	}

	/* ------------------------------------------------------------------
	 * Button tooltips
	 * ---------------------------------------------------------------- */

	public static function buttons()
	{
		global $context, $txt;

		$config = BBM::config(true);

		if (isset($_POST['save']))
		{
			checkSession();

			$tips = array();
			foreach (isset($_POST['tip']) && is_array($_POST['tip']) ? $_POST['tip'] : array() as $code => $tip)
			{
				$tip = BBM::textSubstr(trim(strip_tags((string) $tip)), 0, 100);
				if ($tip !== '' && preg_match('~^[a-z0-9_]{1,40}$~i', (string) $code))
					$tips[$code] = $tip;
			}

			self::baseline();
			updateSettings(array('bbm_tooltips' => json_encode($tips)));
			self::afterChange($txt['bbm_log_tooltips']);
			self::notice($txt['bbm_tooltips_saved']);
			self::back('buttons');
		}

		$context['bbm_known'] = BBM::knownButtons();
		$context['bbm_tooltips'] = $config['tooltips'];
		$context['sub_template'] = 'bbm_buttons';
	}

	/* ------------------------------------------------------------------
	 * Import and export
	 * ---------------------------------------------------------------- */

	public static function transfer()
	{
		global $context, $txt, $mbname;

		if (isset($_GET['export']))
		{
			checkSession('get');

			$json = json_encode(self::exportState(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$name = 'bbcode-manager-' . preg_replace('~[^a-z0-9]+~', '-', strtolower($mbname)) . '-' . date('Y-m-d') . '.json';

			while (ob_get_level() > 0)
				@ob_end_clean();

			header('Content-Type: application/json; charset=UTF-8');
			header('Content-Disposition: attachment; filename="' . $name . '"');
			header('X-Content-Type-Options: nosniff');
			echo $json;
			die;
		}

		$context['bbm_report'] = array();

		if (isset($_POST['import']))
		{
			checkSession();

			$parts = array_values(array_intersect(array('tags', 'profiles', 'tooltips', 'settings'), isset($_POST['parts']) ? (array) $_POST['parts'] : array()));
			$report = array();

			if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name']))
				$report[] = $txt['bbm_import_no_file'];
			elseif ($_FILES['file']['size'] > 4 * 1024 * 1024)
				$report[] = $txt['bbm_import_too_big'];
			elseif (empty($parts))
				$report[] = $txt['bbm_import_no_parts'];
			else
			{
				$state = json_decode(file_get_contents($_FILES['file']['tmp_name']), true);
				if (self::restoreState($state, $parts, true, $report))
				{
					self::afterChange($txt['bbm_log_imported']);
					array_unshift($report, $txt['bbm_import_done']);
				}
			}

			$context['bbm_report'] = $report;
		}

		$context['sub_template'] = 'bbm_transfer';
	}

	public static function settingKeys()
	{
		return array('bbm_enabled', 'bbm_admin_bypass', 'bbm_wysiwyg', 'bbm_palette', 'bbm_mobile_toolbar', 'bbm_revisions_keep', 'bbm_custom_css');
	}

	/**
	 * The whole configuration as one array (for export and revisions).
	 */
	public static function exportState($embed_icons)
	{
		global $modSettings;

		$config = BBM::config(true);

		$tags = array();
		foreach ($config['tags'] as $t)
		{
			unset($t['id_tag'], $t['sort_order']);
			if ($embed_icons)
			{
				$t['icon_data'] = self::iconData($t['icon']);
				unset($t['icon']);
			}
			$tags[] = $t;
		}

		$profiles = array();
		foreach ($config['profiles'] as $id => $p)
			$profiles[] = array('ref' => $id, 'name' => $p['name'], 'toolbar' => $p['toolbar'], 'allowed' => $p['allowed'], 'is_default' => $p['is_default']);

		$rules = array();
		foreach ($config['rules'] as $r)
			$rules[] = array('profile' => $r['id_profile'], 'contexts' => $r['contexts'], 'boards' => $r['boards'], 'groups' => $r['groups'], 'priority' => $r['priority']);

		$settings = array();
		foreach (self::settingKeys() as $key)
			$settings[$key] = isset($modSettings[$key]) ? $modSettings[$key] : '';

		return array(
			'format' => 'bbm',
			'version' => 1,
			'generator' => 'BBCodeManager ' . BBM::VERSION,
			'exported' => time(),
			'tags' => $tags,
			'profiles' => $profiles,
			'rules' => $rules,
			'tooltips' => $config['tooltips'],
			'settings' => $settings,
		);
	}

	/**
	 * Replace the chosen parts of the configuration. Everything goes
	 * through the same validation as the admin forms.
	 */
	/**
	 * Validate an entire incoming configuration before replacing any records.
	 * A rejected tag/profile/rule aborts the whole operation, rather than
	 * silently clearing or partially replacing the working configuration.
	 */
	public static function restoreState($state, array $parts, $from_import, array &$report)
	{
		global $smcFunc, $txt;

		if (!is_array($state) || !isset($state['format']) || $state['format'] !== 'bbm')
		{
			$report[] = $txt['bbm_import_invalid'];
			return false;
		}

		$parts = array_values(array_intersect(array('tags', 'profiles', 'tooltips', 'settings'), $parts));
		if (empty($parts))
		{
			$report[] = $txt['bbm_import_no_parts'];
			return false;
		}

		$errors = array();
		$tags = array();
		$profiles = array();
		$rules = array();
		$tips = array();
		$new_settings = array();

		if (in_array('tags', $parts, true))
		{
			if (!isset($state['tags']) || !is_array($state['tags']))
				$errors[] = $txt['bbm_import_bad_tags'];
			else
			{
				$seen = array();
				foreach ($state['tags'] as $i => $row)
				{
					if (!is_array($row))
					{
						$errors[] = $txt['bbm_import_bad_tags'];
						continue;
					}
					$tag_errors = array();
					// Exported tags always carry an explicit mode and array of
					// typed options. Do not silently discard malformed options.
					if (!isset($row['mode']) || !in_array($row['mode'], BBM::$modes, true) ||
						!isset($row['params']) || !is_array($row['params']))
						$tag_errors[] = $txt['bbm_import_bad_tags'];
					else
						foreach ($row['params'] as $option)
							if (!is_array($option) || !isset($option['name'], $option['type']) ||
								!is_string($option['type']) || !in_array($option['type'], BBM::$param_types, true))
								$tag_errors[] = $txt['bbm_import_bad_tags'];
					$def = self::validateTag($row, 0, $tag_errors, false);
					if (isset($seen[$def['tag']]))
						$tag_errors[] = sprintf($txt['bbm_err_tag_exists'], $def['tag']);
					if (!empty($tag_errors))
					{
						$errors[] = sprintf($txt['bbm_import_skipped'], BBM::esc($def['tag']), implode(' ', $tag_errors));
						continue;
					}
					$seen[$def['tag']] = true;
					$tags[] = array('def' => $def, 'source' => $row);
				}

				// An intentionally empty archive must not silently erase tags on import.
				if ($from_import && count($tags) === 0 && !empty(BBM::config(true)['tags']))
					$errors[] = $txt['bbm_import_empty_tags'];
			}
		}

		if (in_array('profiles', $parts, true))
		{
			if (!isset($state['profiles'], $state['rules']) || !is_array($state['profiles']) || !is_array($state['rules']))
				$errors[] = $txt['bbm_import_bad_profiles'];
			else
			{
				$allowed_tags = in_array('tags', $parts, true)
					? array_merge(array_keys(self::foreignTags()), array_map(function ($item) { return $item['def']['tag']; }, $tags))
					: self::allowableTags();
				$seen_ref = array();
				foreach ($state['profiles'] as $i => $row)
				{
					if (!is_array($row) || !isset($row['ref']) || !is_numeric($row['ref']) || (int) $row['ref'] <= 0 || isset($seen_ref[(int) $row['ref']]))
					{
						$errors[] = $txt['bbm_import_bad_profiles'];
						continue;
					}
					$ref = (int) $row['ref'];
					$seen_ref[$ref] = true;
					$profile_errors = array();
					$profile = self::validateProfile($row, $profile_errors, $allowed_tags);
					if (isset($row['allowed']) && $row['allowed'] !== null && !is_array($row['allowed']))
						$profile_errors[] = $txt['bbm_import_bad_profiles'];
					if (isset($row['allowed']) && is_array($row['allowed']))
						foreach ($row['allowed'] as $allowed)
							if (!is_string($allowed) || !in_array(strtolower($allowed), $allowed_tags, true))
								$profile_errors[] = $txt['bbm_import_bad_profiles'];
					if (!isset($row['toolbar']) || !is_array($row['toolbar']) || count($row['toolbar']) > 6)
						$profile_errors[] = $txt['bbm_import_bad_profiles'];
					else
						foreach ($row['toolbar'] as $toolbar_row)
						{
							if (!is_array($toolbar_row) || count($toolbar_row) > 80)
							{
								$profile_errors[] = $txt['bbm_import_bad_profiles'];
								continue;
							}
							foreach ($toolbar_row as $button)
								if (!is_string($button) || ($button !== '|' && !preg_match('~^[a-z0-9_]{1,40}$~i', $button)))
									$profile_errors[] = $txt['bbm_import_bad_profiles'];
						}
					if (!empty($profile_errors))
					{
						$errors[] = implode(' ', array_unique($profile_errors));
						continue;
					}
					$profiles[$ref] = $profile;
				}
				foreach ($state['rules'] as $rule)
				{
					if (!is_array($rule) || !isset($rule['profile']) || !isset($profiles[(int) $rule['profile']]) ||
						!isset($rule['contexts']) || !is_array($rule['contexts']) || empty($rule['contexts']) ||
						!empty(array_diff($rule['contexts'], BBM::$contexts)) ||
						!isset($rule['boards'], $rule['groups']) || !is_array($rule['boards']) || !is_array($rule['groups']))
					{
						$errors[] = $txt['bbm_import_bad_rules'];
						continue;
					}
					// A malformed board/group ID must never be converted to 0
					// and inadvertently assigned to a different audience.
					$invalid_ids = false;
					foreach (array_merge($rule['boards'], $rule['groups']) as $id)
						if ((!is_string($id) && !is_int($id)) || !preg_match('~^-?[0-9]+$~', (string) $id))
							$invalid_ids = true;
					if ($invalid_ids)
					{
						$errors[] = $txt['bbm_import_bad_rules'];
						continue;
					}
					$rules[] = array(
						'profile' => (int) $rule['profile'],
						'contexts' => implode(',', array_intersect(BBM::$contexts, $rule['contexts'])),
						'boards' => implode(',', array_map('intval', $rule['boards'])),
						'groups' => implode(',', array_map('intval', $rule['groups'])),
						'priority' => max(-1000, min(1000, isset($rule['priority']) ? (int) $rule['priority'] : 0)),
					);
				}
				if ($from_import && count($profiles) === 0 && !empty(BBM::config(true)['profiles']))
					$errors[] = $txt['bbm_import_empty_profiles'];
			}
		}

		if (in_array('tooltips', $parts, true))
		{
			if (!isset($state['tooltips']) || !is_array($state['tooltips']))
				$errors[] = $txt['bbm_import_bad_tooltips'];
			else
				foreach ($state['tooltips'] as $code => $tip)
				{
					if (!preg_match('~^[a-z0-9_]{1,40}$~i', (string) $code) || !is_string($tip))
						$errors[] = $txt['bbm_import_bad_tooltips'];
					elseif (trim($tip) !== '')
						$tips[$code] = BBM::textSubstr(trim(strip_tags($tip)), 0, 100);
				}
		}

		if (in_array('settings', $parts, true))
		{
			if (!isset($state['settings']) || !is_array($state['settings']))
				$errors[] = $txt['bbm_import_bad_settings'];
			else
				foreach (self::settingKeys() as $key)
				{
					if (!array_key_exists($key, $state['settings']) || !is_scalar($state['settings'][$key]))
					{
						$errors[] = $txt['bbm_import_bad_settings'];
						continue;
					}
					$new_settings[$key] = $key === 'bbm_custom_css'
						? str_replace('<', '', BBM::textSubstr((string) $state['settings'][$key], 0, 20000))
						: ($key === 'bbm_revisions_keep' ? max(10, min(500, (int) $state['settings'][$key])) : (empty($state['settings'][$key]) ? 0 : 1));
				}
		}

		if (!empty($errors))
		{
			$report[] = $txt['bbm_import_aborted'];
			foreach (array_unique($errors) as $error)
				$report[] = $error;
			return false;
		}

		// Image bytes are validated and written before modifying the database.
		// Track freshly written files so failure doesn't leave orphaned uploads.
		$staged = array();
		foreach ($tags as &$item)
		{
			$source = $item['source'];
			$item['def']['icon'] = '';
			if ($from_import && !empty($source['icon_data']))
			{
				$icon_errors = array();
				if (!is_string($source['icon_data']))
					$icon_errors[] = $txt['bbm_err_icon_type'];
				else
					$item['def']['icon'] = self::saveIconData($source['icon_data'], $item['def']['tag'], $icon_errors);
				if (empty($icon_errors) && $item['def']['icon'] !== '')
					$staged[] = $item['def']['icon'];
				else
					$errors = array_merge($errors, $icon_errors);
			}
			elseif (!$from_import && !empty($source['icon']))
			{
				if (!is_string($source['icon']) || !preg_match('~^[a-z0-9_.-]+$~i', $source['icon']) || !is_file(self::iconDir() . '/' . $source['icon']))
					$errors[] = $txt['bbm_import_missing_icon'];
				else
					$item['def']['icon'] = $source['icon'];
			}
		}
		unset($item);
		if (!empty($errors))
		{
			self::deleteStagedIcons($staged);
			$report[] = $txt['bbm_import_aborted'];
			foreach (array_unique($errors) as $error)
				$report[] = $error;
			return false;
		}

		if ($from_import)
			self::baseline();

		$started = false;
		try
		{
			if (!isset($smcFunc['db_transaction']))
				throw new RuntimeException('Database transactions are not available.');
			if ($smcFunc['db_transaction']('begin') === false)
				throw new RuntimeException('Unable to start a database transaction.');
			$started = true;

			if (in_array('tags', $parts, true))
			{
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_tags', array());
				foreach ($tags as $order => $item)
				{
					$def = $item['def'];
					self::insertTag(array(
						'tag' => $def['tag'], 'title' => $def['title'], 'description' => $def['description'],
						'mode' => $def['mode'], 'block_level' => $def['block_level'] ? 1 : 0, 'params' => json_encode($def['params']),
						'template' => $def['template'], 'button' => $def['button'] ? 1 : 0, 'icon' => $def['icon'],
						'tooltip' => $def['tooltip'], 'shortcut' => $def['shortcut'], 'enabled' => $def['enabled'] ? 1 : 0,
						'sort_order' => $order,
					));
				}
			}

			if (in_array('profiles', $parts, true))
			{
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_rules', array());
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}bbm_profiles', array());
				$map = array();
				$has_default = false;
				foreach ($profiles as $ref => $profile)
				{
					$is_default = $profile['is_default'] && !$has_default;
					$has_default = $has_default || $is_default;
					$map[$ref] = $smcFunc['db_insert']('insert', '{db_prefix}bbm_profiles',
						array('name' => 'string-60', 'toolbar' => 'string', 'allowed' => 'string', 'is_default' => 'int'),
						array($profile['name'], json_encode($profile['toolbar']), $profile['allowed'] === null ? '' : json_encode($profile['allowed']), $is_default ? 1 : 0),
						array('id_profile'), 1);
				}
				foreach ($rules as $rule)
					$smcFunc['db_insert']('insert', '{db_prefix}bbm_rules',
						array('id_profile' => 'int', 'contexts' => 'string-60', 'boards' => 'string', 'id_groups' => 'string', 'priority' => 'int'),
						array($map[$rule['profile']], $rule['contexts'], $rule['boards'], $rule['groups'], $rule['priority']),
						array('id_rule'));
			}

			if (in_array('tooltips', $parts, true))
				updateSettings(array('bbm_tooltips' => json_encode($tips)));
			if (in_array('settings', $parts, true))
				updateSettings($new_settings);

			if ($smcFunc['db_transaction']('commit') === false)
				throw new RuntimeException('Unable to commit the configuration.');
			$started = false;
		}
		catch (Throwable $e)
		{
			if ($started)
				$smcFunc['db_transaction']('rollback');
			self::deleteStagedIcons($staged);
			BBM::clearCache();
			$report[] = sprintf($txt['bbm_import_failed'], BBM::esc($e->getMessage()));
			return false;
		}

		if (in_array('profiles', $parts, true))
			unset($_SESSION['bbm_try_profile']);
		BBM::clearCache();
		self::pruneUnusedIcons();
		if (in_array('tags', $parts, true))
			$report[] = sprintf($txt['bbm_import_tags'], count($tags));
		if (in_array('profiles', $parts, true))
			$report[] = sprintf($txt['bbm_import_profiles'], count($profiles), count($rules));
		return true;
	}

	/* ------------------------------------------------------------------
	 * Revisions
	 * ---------------------------------------------------------------- */

	/**
	 * Save the state before the first change, so it can be rolled back to.
	 */
	protected static function baseline()
	{
		global $smcFunc, $txt;

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}bbm_revisions', array());
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($count))
			self::snapshot($txt['bbm_log_initial']);
	}

	protected static function afterChange($note)
	{
		BBM::clearCache();
		self::snapshot($note);
		self::pruneUnusedIcons();
	}

	public static function snapshot($note)
	{
		global $smcFunc, $user_info, $modSettings;

		$smcFunc['db_insert']('insert',
			'{db_prefix}bbm_revisions',
			array('logged' => 'int', 'id_member' => 'int', 'member_name' => 'string-80', 'note' => 'string-255', 'data' => 'string'),
			array(time(), (int) $user_info['id'], (string) $user_info['name'], BBM::textSubstr(strip_tags($note), 0, 255), json_encode(self::exportState(false))),
			array('id_revision')
		);

		$keep = empty($modSettings['bbm_revisions_keep']) ? 50 : max(10, min(500, (int) $modSettings['bbm_revisions_keep']));
		$request = $smcFunc['db_query']('', '
			SELECT id_revision
			FROM {db_prefix}bbm_revisions
			ORDER BY id_revision DESC
			LIMIT {int:offset}, 1',
			array('offset' => $keep)
		);
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (!empty($row))
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}bbm_revisions
				WHERE id_revision <= {int:id}',
				array('id' => (int) $row[0])
			);
	}

	public static function history()
	{
		global $context, $smcFunc, $txt;

		if (isset($_POST['restore']))
		{
			checkSession();

			$request = $smcFunc['db_query']('', '
				SELECT id_revision, logged, data
				FROM {db_prefix}bbm_revisions
				WHERE id_revision = {int:id}',
				array('id' => (int) $_POST['restore'])
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($row))
			{
				$report = array();
				if (self::restoreState(json_decode($row['data'], true), array('tags', 'profiles', 'tooltips', 'settings'), false, $report))
				{
					self::afterChange(sprintf($txt['bbm_log_restored'], timeformat($row['logged'])));
					self::notice(sprintf($txt['bbm_restored'], timeformat($row['logged'])) . (count($report) > 2 ? '<br>' . implode('<br>', $report) : ''));
				}
				else
					self::notice(implode('<br>', $report), 'error');
			}

			self::back('history');
		}

		if (isset($_GET['download']))
		{
			checkSession('get');

			$request = $smcFunc['db_query']('', '
				SELECT logged, data
				FROM {db_prefix}bbm_revisions
				WHERE id_revision = {int:id}',
				array('id' => (int) $_GET['download'])
			);
			$row = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($row))
			{
				while (ob_get_level() > 0)
					@ob_end_clean();

				header('Content-Type: application/json; charset=UTF-8');
				header('Content-Disposition: attachment; filename="bbcode-manager-revision-' . date('Y-m-d-His', $row['logged']) . '.json"');
				header('X-Content-Type-Options: nosniff');
				echo $row['data'];
				die;
			}
		}

		$context['bbm_revisions'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_revision, logged, id_member, member_name, note, data
			FROM {db_prefix}bbm_revisions
			ORDER BY id_revision DESC',
			array()
		);
		$first = true;
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$data = json_decode($row['data'], true);
			$context['bbm_revisions'][] = array(
				'id' => (int) $row['id_revision'],
				'time' => timeformat($row['logged']),
				'member' => $row['member_name'],
				'note' => $row['note'],
				'tags' => isset($data['tags']) ? count($data['tags']) : 0,
				'profiles' => isset($data['profiles']) ? count($data['profiles']) : 0,
				'rules' => isset($data['rules']) ? count($data['rules']) : 0,
				'current' => $first,
			);
			$first = false;
		}
		$smcFunc['db_free_result']($request);

		$context['sub_template'] = 'bbm_history';
	}

	/* ------------------------------------------------------------------
	 * Diagnostics
	 * ---------------------------------------------------------------- */

	public static function diagnostics()
	{
		global $context, $modSettings, $txt;

		$config = BBM::config(true);
		$foreign = self::foreignTags();
		$disabled = array_map('trim', explode(',', isset($modSettings['disabledBBC']) ? strtolower($modSettings['disabledBBC']) : ''));
		$checks = array();

		// Server
		$checks['server'] = array(
			array(version_compare(PHP_VERSION, '7.0', '>='), sprintf($txt['bbm_diag_php'], PHP_VERSION)),
			array(function_exists('imagecreatefromstring'), $txt['bbm_diag_gd']),
			array(is_dir(self::iconDir()) && is_writable(self::iconDir()), $txt['bbm_diag_icons']),
			array(!empty($modSettings['cache_enable']), $txt['bbm_diag_cache'], true),
			array(!empty($modSettings['bbm_enabled']), $txt['bbm_diag_enabled']),
		);

		// Tags
		$tag_checks = array();
		$shortcuts = array();
		foreach ($config['tags'] as $t)
		{
			if (isset($foreign[$t['tag']]))
				$tag_checks[] = array(false, sprintf($txt['bbm_diag_conflict'], $t['tag']));
			if (in_array($t['tag'], $disabled, true))
				$tag_checks[] = array(false, sprintf($txt['bbm_diag_core_disabled'], $t['tag']), true);

			$errors = array();
			self::checkTemplate($t['template'], $t['mode'], $t['params'], $errors);
			if (!empty($errors))
				$tag_checks[] = array(false, sprintf($txt['bbm_diag_template'], $t['tag'], implode(' ', $errors)));

			if ($t['shortcut'] !== '')
			{
				if (isset($shortcuts[$t['shortcut']]))
					$tag_checks[] = array(false, sprintf($txt['bbm_diag_shortcut'], $t['shortcut'], $shortcuts[$t['shortcut']], $t['tag']));
				$shortcuts[$t['shortcut']] = $t['tag'];
			}
		}
		if (empty($tag_checks))
			$tag_checks[] = array(true, $txt['bbm_diag_tags_ok']);
		$checks['tags'] = $tag_checks;

		// Profiles and rules
		$boards = self::boardList();
		$groups = self::groupList();
		$used = array();
		$profile_checks = array();
		foreach ($config['rules'] as $r)
		{
			$used[$r['id_profile']] = true;
			if (array_diff($r['boards'], array_keys($boards)))
				$profile_checks[] = array(false, sprintf($txt['bbm_diag_rule_boards'], $r['id_rule']), true);
			if (array_diff($r['groups'], array_keys($groups)))
				$profile_checks[] = array(false, sprintf($txt['bbm_diag_rule_groups'], $r['id_rule']), true);
		}
		foreach ($config['profiles'] as $id => $p)
			if (!$p['is_default'] && !isset($used[$id]))
				$profile_checks[] = array(false, sprintf($txt['bbm_diag_profile_unused'], BBM::esc($p['name'])), true);
		if (empty($profile_checks))
			$profile_checks[] = array(true, $txt['bbm_diag_profiles_ok']);
		$checks['profiles'] = $profile_checks;

		// Other mods on the same hooks
		$mods = array();
		foreach (array('integrate_bbc_codes', 'integrate_bbc_buttons', 'integrate_preparsecode', 'integrate_sceditor_options') as $hook)
		{
			$functions = array();
			foreach (explode(',', isset($modSettings[$hook]) ? $modSettings[$hook] : '') as $fn)
			{
				$fn = trim($fn);
				if ($fn !== '' && strpos($fn, 'BBM::') === false)
					$functions[] = $fn;
			}
			$mods[$hook] = $functions;
		}

		$context['bbm_checks'] = $checks;
		$context['bbm_hook_mods'] = $mods;
		$context['bbm_foreign_tags'] = $foreign;
		$context['sub_template'] = 'bbm_diagnostics';
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	public static function settings()
	{
		global $context, $txt, $sourcedir;

		require_once($sourcedir . '/ManageServer.php');

		$config_vars = array(
			array('check', 'bbm_enabled'),
			array('check', 'bbm_admin_bypass', 'subtext' => $txt['bbm_admin_bypass_desc']),
			'',
			array('check', 'bbm_wysiwyg', 'subtext' => $txt['bbm_wysiwyg_desc']),
			array('check', 'bbm_palette', 'subtext' => $txt['bbm_palette_desc']),
			array('check', 'bbm_mobile_toolbar', 'subtext' => $txt['bbm_mobile_toolbar_desc']),
			'',
			array('int', 'bbm_revisions_keep', 'min' => 10, 'max' => 500),
			array('large_text', 'bbm_custom_css', 'rows' => 10, 'subtext' => $txt['bbm_custom_css_desc']),
		);

		$context['post_url'] = $context['bbm_url'] . ';sa=settings;save';
		$context['settings_title'] = $txt['bbm_tab_settings'];

		if (isset($_GET['save']))
		{
			checkSession();

			$_POST['bbm_revisions_keep'] = max(10, min(500, isset($_POST['bbm_revisions_keep']) ? (int) $_POST['bbm_revisions_keep'] : 50));
			if (isset($_POST['bbm_custom_css']))
				$_POST['bbm_custom_css'] = str_replace('<', '', BBM::textSubstr((string) $_POST['bbm_custom_css'], 0, 20000));

			self::baseline();
			saveDBSettings($config_vars);
			self::afterChange($txt['bbm_log_settings']);
			$_SESSION['adm-save'] = true;
			self::back('settings');
		}

		prepareDBSettingContext($config_vars);
		$context['sub_template'] = 'show_settings';
	}

	/* ------------------------------------------------------------------
	 * Icons
	 * ---------------------------------------------------------------- */

	/** Remove files produced during a rejected or rolled-back import. */
	protected static function deleteStagedIcons(array $names)
	{
		foreach ($names as $name)
			if (is_string($name) && preg_match('~^[a-z0-9]+_[a-f0-9]{8}\.png$~i', $name))
				@unlink(self::iconDir() . '/' . $name);
	}

	/**
	 * Keep icons used by current tags OR any retained historical revision.
	 * Only touch PNGs with names generated by BBCode Manager itself.
	 * This permits tag deletion/replacement without breaking one-click rollback.
	 */
	protected static function pruneUnusedIcons()
	{
		global $smcFunc;

		$dir = self::iconDir();
		if (!is_dir($dir))
			return;

		$used = array();
		$request = $smcFunc['db_query']('', 'SELECT icon FROM {db_prefix}bbm_tags', array());
		while ($row = $smcFunc['db_fetch_assoc']($request))
			if (!empty($row['icon']))
				$used[$row['icon']] = true;
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', 'SELECT data FROM {db_prefix}bbm_revisions', array());
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$state = json_decode($row['data'], true);
			if (!isset($state['tags']) || !is_array($state['tags']))
				continue;
			foreach ($state['tags'] as $tag)
				if (is_array($tag) && !empty($tag['icon']) && is_string($tag['icon']))
					$used[$tag['icon']] = true;
		}
		$smcFunc['db_free_result']($request);

		foreach (glob($dir . '/*.png') ?: array() as $path)
		{
			$name = basename($path);
			if (preg_match('~^[a-z0-9]+_[a-f0-9]{8}\.png$~i', $name) && !isset($used[$name]))
				@unlink($path);
		}
	}

	public static function iconDir()
	{
		global $settings;

		return $settings['default_theme_dir'] . '/images/bbm';
	}

	protected static function handleIconUpload($tag, array &$errors)
	{
		global $txt;

		$file = $_FILES['icon'];
		if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))
		{
			$errors[] = $txt['bbm_err_icon_upload'];
			return '';
		}
		if ($file['size'] > 102400)
		{
			$errors[] = $txt['bbm_err_icon_size'];
			return '';
		}

		return self::saveIconBinary(file_get_contents($file['tmp_name']), $tag, $errors);
	}

	protected static function saveIconData($uri, $tag, array &$errors)
	{
		global $txt;

		if (!preg_match('~^data:image/(?:png|gif|jpeg|webp);base64,([a-z0-9+/=\s]+)$~i', $uri, $m))
		{
			$errors[] = $txt['bbm_err_icon_type'];
			return '';
		}

		$binary = base64_decode(preg_replace('~\s+~', '', $m[1]), true);
		if ($binary === false || strlen($binary) > 102400)
		{
			$errors[] = $txt['bbm_err_icon_size'];
			return '';
		}

		return self::saveIconBinary($binary, $tag, $errors);
	}

	/**
	 * Icons are checked, then re-drawn as a 32px PNG with GD. Re-drawing
	 * removes anything hidden inside the file.
	 */
	public static function saveIconBinary($binary, $tag, array &$errors)
	{
		global $txt;

		$info = @getimagesizefromstring($binary);
		$types = array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG);
		if (defined('IMAGETYPE_WEBP'))
			$types[] = IMAGETYPE_WEBP;

		if (empty($info) || !in_array($info[2], $types, true))
		{
			$errors[] = $txt['bbm_err_icon_type'];
			return '';
		}
		if ($info[0] < 1 || $info[1] < 1 || $info[0] > 512 || $info[1] > 512)
		{
			$errors[] = $txt['bbm_err_icon_dimensions'];
			return '';
		}

		$dir = self::iconDir();
		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
			@file_put_contents($dir . '/index.php', '<?php header(\'Location: ../../index.php\'); exit;');
		}
		if (!is_dir($dir) || !is_writable($dir))
		{
			$errors[] = $txt['bbm_err_icon_dir'];
			return '';
		}

		$name = preg_replace('~[^a-z0-9]~', '', $tag) . '_' . bin2hex(random_bytes(4)) . '.png';

		if (function_exists('imagecreatefromstring') && function_exists('imagepng'))
		{
			$src = @imagecreatefromstring($binary);
			if ($src === false)
			{
				$errors[] = $txt['bbm_err_icon_type'];
				return '';
			}

			$w = imagesx($src);
			$h = imagesy($src);
			$scale = min(1, 32 / max($w, $h));
			$nw = max(1, (int) round($w * $scale));
			$nh = max(1, (int) round($h * $scale));

			$dst = imagecreatetruecolor($nw, $nh);
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
			imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
			imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
			$ok = imagepng($dst, $dir . '/' . $name);
			imagedestroy($src);
			imagedestroy($dst);

			if (!$ok)
			{
				$errors[] = $txt['bbm_err_icon_dir'];
				return '';
			}
		}
		else
		{
			// Without GD only PNG files are accepted, and they're stored as uploaded.
			if ($info[2] !== IMAGETYPE_PNG || @file_put_contents($dir . '/' . $name, $binary) === false)
			{
				$errors[] = $txt['bbm_err_icon_gd'];
				return '';
			}
		}

		return $name;
	}

	protected static function iconData($file)
	{
		if ($file === '' || !preg_match('~^[a-z0-9_.-]+$~i', $file))
			return '';

		$path = self::iconDir() . '/' . $file;
		if (!is_file($path) || filesize($path) > 102400)
			return '';

		return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
	}
}
