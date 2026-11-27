<?php

/**
 * Colorize Boards for SMF 2.1
 *
 * Lets admins give board names, board descriptions, category names and
 * category descriptions their own colour (plus optional bold / italic).
 * Everything is done through integration hooks - no core files are edited.
 *
 * Storage: one JSON blob in $modSettings['colorize_boards_data']
 *   {"b": {"<id_board>": {"n": "#ff0000", "d": "#00aa00", "b": 1, "i": 0}},
 *    "c": {"<id_cat>":   {"n": "#0000ff", "d": "",        "b": 0, "i": 1}}}
 *
 * @package Colorize Boards
 * @version 1.0.2
 * @license ISC - Bryan "BoredSkull" Deakin
 */

if (!defined('SMF'))
	die('No direct access...');

/* ------------------------------------------------------------------------
 * Data helpers
 * --------------------------------------------------------------------- */

/**
 * Returns (and optionally replaces) the stored colour data for this request.
 *
 * @param array|null $new If given, replaces the cached copy.
 * @return array
 */
function colorize_boards_data($new = null)
{
	global $modSettings;
	static $data = null;

	if ($new !== null)
		$data = $new;

	if ($data === null)
	{
		$data = !empty($modSettings['colorize_boards_data']) ? json_decode($modSettings['colorize_boards_data'], true) : array();

		if (!is_array($data))
			$data = array();
	}

	// Settings are held in a JSON blob. Validate them again when reading so
	// damaged or imported settings cannot produce arbitrary inline CSS.
	foreach (array('b', 'c') as $type)
	{
		if (!isset($data[$type]) || !is_array($data[$type]))
			$data[$type] = array();

		foreach ($data[$type] as $id => $settings)
		{
			if (!ctype_digit((string) $id) || (int) $id <= 0 || !is_array($settings))
			{
				unset($data[$type][$id]);
				continue;
			}

			$data[$type][$id] = array(
				'n' => colorize_boards_clean_color(isset($settings['n']) ? $settings['n'] : ''),
				'd' => colorize_boards_clean_color(isset($settings['d']) ? $settings['d'] : ''),
				'b' => !empty($settings['b']) ? 1 : 0,
				'i' => !empty($settings['i']) ? 1 : 0,
			);
		}
	}

	return $data;
}

/**
 * Saves the settings for one board ('b') or category ('c').
 * Blank entries are removed so the blob stays small.
 *
 * @param string $type 'b' or 'c'
 * @param int $id
 * @param array|null $settings null to delete
 */
function colorize_boards_store($type, $id, $settings = null)
{
	$data = colorize_boards_data();
	$id = (int) $id;

	if ($settings === null || ($settings['n'] === '' && $settings['d'] === '' && empty($settings['b']) && empty($settings['i'])))
		unset($data[$type][$id]);
	else
		$data[$type][$id] = $settings;

	colorize_boards_data($data);

	updateSettings(array(
		'colorize_boards_data' => (empty($data['b']) && empty($data['c'])) ? '' : json_encode($data),
	));
}

/**
 * Only allow safe colour values: #rgb, #rrggbb, or a plain CSS colour name.
 *
 * @param mixed $value
 * @return string Clean colour or '' if invalid/blank.
 */
function colorize_boards_clean_color($value)
{
	// A malformed POST may contain an array, which must not be converted
	// into the literal CSS colour name "array".
	if (!is_string($value) && !is_numeric($value))
		return '';

	$value = strtolower(trim((string) $value));

	if ($value === '')
		return '';

	if (preg_match('~^#?([0-9a-f]{3}|[0-9a-f]{6})$~', $value, $m))
		return '#' . $m[1];

	if (preg_match('~^[a-z]{3,20}$~', $value))
		return $value;

	return '';
}

/**
 * Reads the posted form fields for a board/category.
 *
 * @return array
 */
function colorize_boards_from_post()
{
	return array(
		'n' => colorize_boards_clean_color(isset($_POST['colorize_name_color']) ? $_POST['colorize_name_color'] : ''),
		'd' => colorize_boards_clean_color(isset($_POST['colorize_desc_color']) ? $_POST['colorize_desc_color'] : ''),
		'b' => !empty($_POST['colorize_bold']) ? 1 : 0,
		'i' => !empty($_POST['colorize_italic']) ? 1 : 0,
	);
}

/**
 * Builds the CSS declarations for a name.
 *
 * @param array $s Stored settings
 * @param bool $important Add !important (for stylesheet rules, not inline styles)
 * @return string
 */
function colorize_boards_name_css($s, $important = true)
{
	$imp = $important ? ' !important' : '';
	$css = '';

	if (!empty($s['n']))
		$css .= 'color: ' . $s['n'] . $imp . '; ';
	if (!empty($s['b']))
		$css .= 'font-weight: bold' . $imp . '; ';
	if (!empty($s['i']))
		$css .= 'font-style: italic' . $imp . '; ';

	return trim($css);
}

/* ------------------------------------------------------------------------
 * Admin: edit board / edit category forms
 * --------------------------------------------------------------------- */

/**
 * HTML for a text box + colour picker pair.
 */
function colorize_boards_color_input($name, $value)
{
	global $txt;

	$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

	// The picker needs a 6-digit hex value; expand #rgb, default to black.
	$picker = '#000000';
	if (preg_match('~^#([0-9a-f])([0-9a-f])([0-9a-f])$~', $value, $m))
		$picker = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
	elseif (preg_match('~^#[0-9a-f]{6}$~', $value))
		$picker = $value;

	return '
		<input type="text" name="' . $name . '" id="' . $name . '" value="' . $value . '" size="10" maxlength="20" placeholder="#rrggbb" oninput="var p = document.getElementById(\'' . $name . '_picker\'), c = this.value; if (/^#[0-9a-f]{3}$/i.test(c)) c = String.fromCharCode(35) + c[1] + c[1] + c[2] + c[2] + c[3] + c[3]; if (/^#[0-9a-f]{6}$/i.test(c)) p.value = c;">
		<input type="color" id="' . $name . '_picker" value="' . $picker . '" oninput="document.getElementById(\'' . $name . '\').value = this.value;" title="' . $txt['colorize_boards_pick'] . '">
		<a href="#" onclick="document.getElementById(\'' . $name . '\').value = \'\'; return false;" class="smalltext">' . $txt['colorize_boards_clear'] . '</a>';
}

/**
 * Bold / italic checkboxes.
 */
function colorize_boards_style_input($s)
{
	global $txt;

	return '
		<label><input type="checkbox" name="colorize_bold" value="1"' . (!empty($s['b']) ? ' checked' : '') . '> ' . $txt['colorize_boards_bold'] . '</label>
		<label><input type="checkbox" name="colorize_italic" value="1"' . (!empty($s['i']) ? ' checked' : '') . '> ' . $txt['colorize_boards_italic'] . '</label>';
}

/**
 * Builds the form fields for one board or category.
 */
function colorize_boards_fields($type, $id, $name_label, $desc_label)
{
	global $txt;

	loadLanguage('ColorizeBoards');

	$data = colorize_boards_data();
	$s = isset($data[$type][(int) $id]) ? $data[$type][(int) $id] : array();
	$s += array('n' => '', 'd' => '', 'b' => 0, 'i' => 0);

	$hint = '<br><span class="smalltext">' . $txt['colorize_boards_color_hint'] . '</span>';

	return array(
		'colorize_name' => array(
			'dt' => '<strong>' . $name_label . '</strong>' . $hint,
			'dd' => colorize_boards_color_input('colorize_name_color', $s['n']),
		),
		'colorize_style' => array(
			'dt' => '<strong>' . $txt['colorize_boards_style'] . '</strong>',
			'dd' => colorize_boards_style_input($s),
		),
		'colorize_desc' => array(
			'dt' => '<strong>' . $desc_label . '</strong>' . $hint,
			'dd' => colorize_boards_color_input('colorize_desc_color', $s['d']),
		),
	);
}

/**
 * Hook: integrate_edit_board
 */
function colorize_boards_edit_board()
{
	global $context, $txt;

	loadLanguage('ColorizeBoards');

	if (!isset($context['custom_board_settings']) || !is_array($context['custom_board_settings']))
		$context['custom_board_settings'] = array();

	$context['custom_board_settings'] += colorize_boards_fields('b', $context['board']['id'], $txt['colorize_boards_board_name'], $txt['colorize_boards_board_desc']);
}

/**
 * Hook: integrate_edit_category
 */
function colorize_boards_edit_category()
{
	global $context, $txt;

	loadLanguage('ColorizeBoards');

	if (!isset($context['custom_category_settings']) || !is_array($context['custom_category_settings']))
		$context['custom_category_settings'] = array();

	$id = isset($context['category']['id']) ? $context['category']['id'] : 0;

	$context['custom_category_settings'] += colorize_boards_fields('c', $id, $txt['colorize_boards_cat_name'], $txt['colorize_boards_cat_desc']);
}
/** Got to add some copyrights **/

function colorize_credits()
{
	global $context, $txt;

	loadLanguage('ColorizeBoards');

	$context['credits_modifications'][] = sprintf($txt['colorize_credits'], '1.0.2');
}
/* ------------------------------------------------------------------------
 * Admin: saving
 * --------------------------------------------------------------------- */

/**
 * Hook: integrate_modify_board
 * Runs for both new boards (createBoard() calls modifyBoard()) and edits.
 * Only acts on a genuine submit of the edit-board form (session + token are
 * already verified by EditBoard2() before we get here).
 */
function colorize_boards_modify_board($id, $boardOptions, &$boardUpdates, &$boardUpdateParameters)
{
	static $done = false;

	if ($done || !isset($_POST['colorize_name_color'], $_REQUEST['action'], $_REQUEST['sa']) || $_REQUEST['action'] !== 'admin' || $_REQUEST['sa'] !== 'board2')
		return;

	// Editing: make sure it's the board that was submitted.
	if ((int) $id <= 0 || (!isset($_POST['add']) && (!isset($_POST['boardid']) || (int) $id !== (int) $_POST['boardid'])))
		return;

	$done = true;
	colorize_boards_store('b', $id, colorize_boards_from_post());
}

/**
 * Hook: integrate_modify_category
 * createCategory() also calls modifyCategory(), so new categories are covered.
 */
function colorize_boards_modify_category($cat_id, &$catUpdates, &$catParameters)
{
	static $done = false;

	if ($done || !isset($_POST['colorize_name_color'], $_REQUEST['action'], $_REQUEST['sa']) || $_REQUEST['action'] !== 'admin' || $_REQUEST['sa'] !== 'cat2')
		return;

	if ((int) $cat_id <= 0 || (!isset($_POST['add']) && (!isset($_POST['cat']) || (int) $cat_id !== (int) $_POST['cat'])))
		return;

	$done = true;
	colorize_boards_store('c', $cat_id, colorize_boards_from_post());
}

/**
 * Hook: integrate_delete_board - tidy up.
 */
function colorize_boards_delete_board($boards_to_remove, &$moveChildrenTo)
{
	global $boards;

	$data = colorize_boards_data();
	$changed = false;

	// SMF invokes this hook BEFORE recursively expanding deleted boards.
	// Include every descendant only when SMF is deleting children, not
	// when it is moving them into another board.
	if ($moveChildrenTo === null)
	{
		$children_to_remove = array();
		foreach ((array) $boards_to_remove as $id)
			if (isset($boards[(int) $id]['tree']))
				recursiveBoards($children_to_remove, $boards[(int) $id]['tree']);

		$boards_to_remove = array_unique(array_merge((array) $boards_to_remove, $children_to_remove));
	}

	foreach ((array) $boards_to_remove as $id)
		if (isset($data['b'][(int) $id]))
		{
			unset($data['b'][(int) $id]);
			$changed = true;
		}

	if ($changed)
	{
		colorize_boards_data($data);
		updateSettings(array('colorize_boards_data' => (empty($data['b']) && empty($data['c'])) ? '' : json_encode($data)));
	}
}

/**
 * Hook: integrate_delete_category - tidy up.
 */
function colorize_boards_delete_category($categories, &$moveBoardsTo)
{
	// SMF rejects moving boards into a category that is itself being
	// deleted. Do not remove saved colours for that rejected operation.
	if ($moveBoardsTo !== null && in_array((int) $moveBoardsTo, array_map('intval', (array) $categories), true))
		return;

	$data = colorize_boards_data();
	$changed = false;

	foreach ((array) $categories as $id)
		if (isset($data['c'][(int) $id]))
		{
			unset($data['c'][(int) $id]);
			$changed = true;
		}

	if ($changed)
	{
		colorize_boards_data($data);
		updateSettings(array('colorize_boards_data' => (empty($data['b']) && empty($data['c'])) ? '' : json_encode($data)));
	}
}

/**
 * Hook: integrate_modify_board_settings
 * Adds an on/off switch to Admin > Boards > Settings.
 */
function colorize_boards_board_settings(&$config_vars)
{
	loadLanguage('ColorizeBoards');

	$config_vars[] = '';
	$config_vars[] = array('check', 'colorize_boards_enable');
}

/* ------------------------------------------------------------------------
 * Front end
 * --------------------------------------------------------------------- */

/**
 * Hook: integrate_load_theme
 *
 * Outputs one small <style> block. Board links are matched by their URL, so
 * the colours show up everywhere SMF links to a board: board index, child
 * boards, linktree, Recent Posts, Stats, Unread, search results, etc.
 */
function colorize_boards_load_theme()
{
	global $modSettings;

	if (empty($modSettings['colorize_boards_enable']) || empty($modSettings['colorize_boards_data']))
		return;

	$data = colorize_boards_data();
	$rules = array();

	foreach ($data['b'] as $id => $s)
	{
		$id = (int) $id;
		$name_css = colorize_boards_name_css($s);

		if ($name_css !== '')
		{
			// Ends-with match so board=1.0 never hits board=11.0 or the
			// unread/markread/sort links; .nav_page excludes pagination.
			$links = array(
				'a[href$="?board=' . $id . '.0"]:not(.nav_page)',
				'a[href$="/board,' . $id . '.0.html"]:not(.nav_page)', // queryless URLs
			);
			$selectors = array();
			foreach ($links as $link)
			{
				$selectors[] = $link;
				$selectors[] = $link . ' span'; // linktree wraps names in a span
			}

			$rules[] = implode(', ', $selectors) . ' { ' . $name_css . ' }';
		}

		if (!empty($s['d']))
			$rules[] = '#board_' . $id . ' .board_description { color: ' . $s['d'] . ' !important; }';
	}

	foreach ($data['c'] as $id => $s)
	{
		$id = (int) $id;
		$name_css = colorize_boards_name_css($s);

		if ($name_css !== '')
			$rules[] = '#category_' . $id . ' .catbg, #category_' . $id . ' .catbg a, a[href$="#c' . $id . '"], a[href$="#c' . $id . '"] span { ' . $name_css . ' }';

		if (!empty($s['d']))
			$rules[] = '#category_' . $id . ' .desc { color: ' . $s['d'] . ' !important; }';
	}

	if (!empty($rules))
		addInlineCss("\n\t/* Colorize Boards */\n\t" . implode("\n\t", $rules));
}

/**
 * Hook: integrate_messageindex_buttons
 *
 * The board title/description at the top of the message index aren't links,
 * so style them directly.
 */
function colorize_boards_messageindex(&$buttons)
{
	global $context, $board_info, $modSettings;

	if (empty($modSettings['colorize_boards_enable']) || empty($board_info['id']))
		return;

	$data = colorize_boards_data();
	if (empty($data['b'][(int) $board_info['id']]))
		return;

	$s = $data['b'][(int) $board_info['id']];
	$name_css = colorize_boards_name_css($s, false);

	if ($name_css !== '' && isset($context['name']))
		$context['name'] = '<span style="' . $name_css . '">' . $context['name'] . '</span>';

	if (!empty($s['d']) && isset($context['description']) && $context['description'] !== '')
		$context['description'] = '<span style="color: ' . $s['d'] . ';">' . $context['description'] . '</span>';
}
