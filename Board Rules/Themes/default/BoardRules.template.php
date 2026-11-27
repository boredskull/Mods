<?php
/**
 * Board Rules - templates
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 * @version 1.0.3
 */

/* -------------------------------------------------------------------------
 * Public rules page
 * ---------------------------------------------------------------------- */

function template_boardrules_page()
{
	global $context, $txt, $scripturl, $modSettings;

	$numbering = !empty($modSettings['boardrules_numbering']);

	echo '
	<div id="boardrules" class="boardrules">
		<div class="cat_bar">
			<h3 class="catbg"><span class="main_icons boardrules"></span> ', $txt['boardrules_title'], '</h3>
		</div>';

	if (!empty($context['boardrules_forced']) && !empty($context['boardrules_needs_accept']))
		echo '
		<div class="errorbox">', $txt['boardrules_forced_notice'], '</div>';

	if (!empty($context['boardrules_accepted_now']))
		echo '
		<div class="infobox">', $txt['boardrules_thanks'], '</div>';

	echo '
		<div class="roundframe boardrules_intro">';

	if (!empty($context['boardrules_intro']))
		echo '
			<div class="boardrules_introtext">', $context['boardrules_intro'], '</div>';

	echo '
			<div class="boardrules_toolbar boardrules_noprint">
				<input type="search" id="boardrules_filter" placeholder="', $txt['boardrules_filter'], '" aria-label="', $txt['boardrules_filter'], '">
				<a href="#" class="button" data-boardrules="expand">', $txt['boardrules_expand'], '</a>
				<a href="#" class="button" data-boardrules="collapse">', $txt['boardrules_collapse'], '</a>
				<a href="#" class="button" data-boardrules="print">', $txt['boardrules_print'], '</a>';

	if (count($context['boardrules_languages']) > 0)
	{
		echo '
				<select class="boardrules_lang" aria-label="', $txt['boardrules_language'], '" onchange="if (this.value) location.href = this.value;">
					<option value="', $scripturl, '?action=rules"', $context['boardrules_lang'] === '' ? ' selected' : '', '>', $txt['boardrules_lang_auto'], '</option>';

		foreach ($context['boardrules_languages'] as $lang => $name)
			echo '
					<option value="', $scripturl, '?action=rules;lang=', $lang, '"', $context['boardrules_lang'] === $lang ? ' selected' : '', '>', $name, '</option>';

		echo '
				</select>';
	}

	echo '
			</div>
			<div class="boardrules_meta smalltext">',
				sprintf($txt['boardrules_count'], $context['boardrules_total']),
				!empty($context['boardrules_last_update']) ? ' &bull; ' . sprintf($txt['boardrules_last_updated'], $context['boardrules_last_update']) : '', '
			</div>
		</div>';

	if (empty($context['boardrules_categories']))
		echo '
		<div class="infobox">', $txt['boardrules_none'], '</div>';

	// Table of contents; omit entirely for an empty rule set.
	if (!empty($context['boardrules_categories']))
	{
		echo '
		<nav class="windowbg boardrules_toc boardrules_noprint">
			<strong>', $txt['boardrules_contents'], '</strong>
			<ol>';

		foreach ($context['boardrules_categories'] as $cat)
			echo '
				<li><a href="#rcat-', $cat['id'], '">', $cat['title'], '</a> <span class="smalltext">(', count($cat['rules']), ')</span></li>';

		echo '
			</ol>
		</nav>';
	}

	foreach ($context['boardrules_categories'] as $cat)
	{
		echo '
		<details class="boardrules_cat" id="rcat-', $cat['id'], '" open>
			<summary class="title_bar">
				<h4 class="titlebg">', $numbering ? '<span class="boardrules_num">' . $cat['number'] . '.</span> ' : '', $cat['title'], '</h4>
			</summary>';

		if (!empty($cat['description']))
			echo '
			<div class="boardrules_catdesc">', $cat['description'], '</div>';

		echo '
			<ol class="boardrules_list">';

		foreach ($cat['rules'] as $rule)
		{
			echo '
				<li class="windowbg boardrules_rule" id="rule-', $rule['id'], '">
					<div class="boardrules_rule_head">
						', $numbering ? '<span class="boardrules_num">' . $rule['number'] . '</span>' : '', '
						<strong class="boardrules_rule_title">', $rule['title'], '</strong>';

			if (!empty($rule['penalty']))
				echo '
						<span class="boardrules_penalty" title="', $txt['boardrules_penalty'], '">', $rule['penalty'], '</span>';

			echo '
						<a href="#rule-', $rule['id'], '" class="boardrules_anchor boardrules_noprint" title="', $txt['boardrules_permalink'], '">#</a>
					</div>
					<div class="boardrules_body">', $rule['body'], '</div>';

			if (!empty($rule['applies']))
				echo '
					<div class="boardrules_applies smalltext"><span class="main_icons boards"></span> ', $txt['boardrules_applies_to'], ' ', implode(', ', $rule['applies']), '</div>';

			echo '
				</li>';
		}

		echo '
			</ol>
		</details>';
	}

	echo '
		<div class="infobox boardrules_noprint" id="boardrules_nomatch" style="display: none;">', $txt['boardrules_no_match'], '</div>';

	// The acceptance form must remain available even when all categories
	// have been deleted, otherwise forced acceptance can trap existing members.
	// Accept the current version.
	if (!empty($context['boardrules_needs_accept']))
		echo '
		<form action="', $scripturl, '?action=rules;sa=accept" method="post" accept-charset="', $context['character_set'], '" class="roundframe boardrules_acceptbox boardrules_noprint">
			<p>', $txt['boardrules_accept_desc'], '</p>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="forced" value="', !empty($context['boardrules_forced']) ? 1 : 0, '">
			<input type="submit" class="button" value="', $txt['boardrules_accept'], '">
		</form>';

	if (!empty($context['boardrules_changelog']))
	{
		echo '
		<div class="title_bar boardrules_noprint">
			<h4 class="titlebg">', $txt['boardrules_changelog'], '</h4>
		</div>
		<div class="windowbg boardrules_changelog boardrules_noprint">
			<ul>';

		foreach ($context['boardrules_changelog'] as $entry)
			echo '
				<li><span class="smalltext">', $entry['time'], '</span> &ndash; ', $entry['summary'], '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
	</div>';
}

/* -------------------------------------------------------------------------
 * Layers: "rules updated" banner and per-board rules
 * ---------------------------------------------------------------------- */

function template_boardrules_notice_above()
{
	global $txt, $scripturl, $modSettings;

	echo '
	<div class="noticebox boardrules_notice">
		<span class="main_icons boardrules"></span>
		<span>', sprintf($txt['boardrules_notice'], timeformat($modSettings['boardrules_last_update'])),
		!empty($modSettings['boardrules_last_summary']) ? ' <em>' . $modSettings['boardrules_last_summary'] . '</em>' : '', '</span>
		<a href="', $scripturl, '?action=rules" class="button">', $txt['boardrules_review'], '</a>
	</div>';
}

function template_boardrules_notice_below()
{
}

function template_boardrules_board_above()
{
	global $context, $txt, $scripturl, $modSettings;

	$open = !isset($modSettings['boardrules_board_style']) || $modSettings['boardrules_board_style'] !== 'collapsed';

	echo '
	<details class="boardrules_board"', $open ? ' open' : '', '>
		<summary class="title_bar">
			<h3 class="titlebg"><span class="main_icons boardrules"></span> ', sprintf($txt['boardrules_board_title'], $context['boardrules_board_name']), '</h3>
		</summary>
		<div class="windowbg">
			<ul class="boardrules_board_list">';

	foreach ($context['boardrules_board_rules'] as $rule)
		echo '
				<li>
					<strong>', $rule['title'], '</strong>', !empty($rule['penalty']) ? ' <span class="boardrules_penalty">' . $rule['penalty'] . '</span>' : '', '
					<div class="boardrules_body">', $rule['body'], '</div>
				</li>';

	echo '
			</ul>
			<a href="', $scripturl, '?action=rules" class="smalltext">', $txt['boardrules_full_rules'], '</a>
		</div>
	</details>';
}

function template_boardrules_board_below()
{
}

/* -------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------- */

function template_boardrules_admin_rules()
{
	global $context, $txt, $scripturl;

	$base = $scripturl . '?action=admin;area=boardrules';
	$sess = $context['session_var'] . '=' . $context['session_id'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['boardrules_admin_rules'], '</h3>
	</div>
	<div class="windowbg boardrules_admin_toolbar">
		<a class="button" href="', $base, ';sa=editrule">', $txt['boardrules_add_rule'], '</a>
		<a class="button" href="', $base, ';sa=editcat">', $txt['boardrules_add_cat'], '</a>
		<a class="button" href="', $scripturl, '?action=rules" target="_blank" rel="noopener">', $txt['boardrules_view_page'], '</a>
	</div>';

	if (empty($context['boardrules_cats']))
	{
		echo '
	<div class="infobox">', $txt['boardrules_need_category'], '</div>';
		return;
	}

	foreach ($context['boardrules_cats'] as $cat)
	{
		echo '
	<div class="title_bar boardrules_admin_cat">
		<h4 class="titlebg">
			', $cat['title'], ' ', boardrules_lang_badge($cat['lang']), empty($cat['enabled']) ? ' <span class="boardrules_badge boardrules_off">' . $txt['boardrules_disabled'] . '</span>' : '', '
			<a class="button floatright" href="', $base, ';sa=editrule;cat=', $cat['id'], '">', $txt['boardrules_add_rule'], '</a>
		</h4>
	</div>
	<table class="table_grid boardrules_table">
		<thead>
			<tr class="title_bar">
				<th class="centercol" style="width: 4%;">#</th>
				<th>', $txt['boardrules_rule_title'], '</th>
				<th style="width: 12%;">', $txt['boardrules_language'], '</th>
				<th style="width: 18%;">', $txt['boardrules_scope'], '</th>
				<th style="width: 12%;">', $txt['boardrules_penalty'], '</th>
				<th class="centercol" style="width: 8%;">', $txt['boardrules_status'], '</th>
				<th class="centercol" style="width: 20%;">', $txt['boardrules_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

		if (empty($cat['rules']))
			echo '
			<tr class="windowbg"><td colspan="7" class="centertext">', $txt['boardrules_cat_empty'], '</td></tr>';

		$n = 0;
		$last = count($cat['rules']);
		foreach ($cat['rules'] as $rule)
		{
			$n++;
			$scope = array();
			foreach ($rule['boards'] as $id_board)
				$scope[] = isset($context['boardrules_board_names'][$id_board]) ? $context['boardrules_board_names'][$id_board] : '#' . $id_board;

			echo '
			<tr class="windowbg">
				<td class="centercol">', $n, '</td>
				<td><a href="', $base, ';sa=editrule;rule=', $rule['id'], '"><strong>', $rule['title'], '</strong></a>', !empty($rule['updated']) ? '<br><span class="smalltext">' . sprintf($txt['boardrules_last_updated'], $rule['updated']) . '</span>' : '', '</td>
				<td>', $rule['lang'] === '' ? $txt['boardrules_lang_all'] : boardrules_lang_name($rule['lang']), '</td>
				<td>', empty($scope) ? $txt['boardrules_scope_global'] : implode(', ', $scope), '</td>
				<td>', $rule['penalty'] !== '' ? '<span class="boardrules_penalty">' . $rule['penalty'] . '</span>' : '&ndash;', '</td>
				<td class="centercol"><a href="', $base, ';sa=togglerule;rule=', $rule['id'], ';', $sess, '" class="boardrules_badge ', $rule['enabled'] ? 'boardrules_on">' . $txt['boardrules_enabled_short'] : 'boardrules_off">' . $txt['boardrules_disabled'], '</a></td>
				<td class="centercol boardrules_actions">
					', $n > 1 ? '<a href="' . $base . ';sa=moverule;rule=' . $rule['id'] . ';dir=up;' . $sess . '" title="' . $txt['boardrules_move_up'] . '">&#9650;</a>' : '<span class="boardrules_dim">&#9650;</span>', '
					', $n < $last ? '<a href="' . $base . ';sa=moverule;rule=' . $rule['id'] . ';dir=down;' . $sess . '" title="' . $txt['boardrules_move_down'] . '">&#9660;</a>' : '<span class="boardrules_dim">&#9660;</span>', '
					<a href="', $base, ';sa=editrule;rule=', $rule['id'], '">', $txt['modify'], '</a> |
					<a href="', $base, ';sa=deleterule;rule=', $rule['id'], ';', $sess, '" onclick="return confirm(', JavaScriptEscape($txt['boardrules_confirm_delete_rule']), ');">', $txt['delete'], '</a>
				</td>
			</tr>';
		}

		echo '
		</tbody>
	</table>';
	}
}

function template_boardrules_admin_edit_rule()
{
	global $context, $txt, $scripturl;

	$rule = $context['boardrules_rule'];

	echo '
	<form action="', $scripturl, '?action=admin;area=boardrules;sa=saverule" method="post" accept-charset="', $context['character_set'], '" name="boardrules_form" id="boardrules_form">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="br_title"><strong>', $txt['boardrules_rule_title'], '</strong></label></dt>
				<dd><input type="text" name="title" id="br_title" value="', $rule['title'], '" size="60" maxlength="200" required></dd>

				<dt><label for="br_cat"><strong>', $txt['boardrules_category'], '</strong></label></dt>
				<dd>
					<select name="id_cat" id="br_cat">';

	foreach ($context['boardrules_cat_list'] as $id => $title)
		echo '
						<option value="', $id, '"', $id == $rule['id_cat'] ? ' selected' : '', '>', $title, '</option>';

	echo '
					</select>
				</dd>

				<dt><label for="br_lang"><strong>', $txt['boardrules_language'], '</strong></label><br><span class="smalltext">', $txt['boardrules_language_desc'], '</span></dt>
				<dd>';
	boardrules_lang_select('lang', $rule['lang']);
	echo '
				</dd>

				<dt><label for="br_boards"><strong>', $txt['boardrules_scope'], '</strong></label><br><span class="smalltext">', $txt['boardrules_scope_desc'], '</span></dt>
				<dd>
					<select name="boards[]" id="br_boards" multiple size="8" class="boardrules_boards">';

	foreach ($context['boardrules_board_list'] as $category)
	{
		echo '
						<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $b)
			echo '
							<option value="', $b['id'], '"', in_array($b['id'], $rule['boards'], true) ? ' selected' : '', '>', str_repeat('&nbsp;&nbsp;', $b['level']), $b['name'], '</option>';

		echo '
						</optgroup>';
	}

	echo '
					</select>
				</dd>

				<dt><label for="br_penalty"><strong>', $txt['boardrules_penalty'], '</strong></label><br><span class="smalltext">', $txt['boardrules_penalty_desc'], '</span></dt>
				<dd><input type="text" name="penalty" id="br_penalty" value="', $rule['penalty'], '" size="30" maxlength="100"></dd>

				<dt><label for="br_enabled"><strong>', $txt['boardrules_enabled_short'], '</strong></label></dt>
				<dd><input type="checkbox" name="enabled" id="br_enabled" value="1"', $rule['enabled'] ? ' checked' : '', '></dd>
			</dl>
			<div class="boardrules_editor">
				<strong>', $txt['boardrules_rule_body'], '</strong> <span class="smalltext">', $txt['boardrules_bbc_ok'], '</span>';

	template_control_richedit('rule_body', null, null);

	echo '
			</div>';

	template_boardrules_notify_fieldset();

	echo '
			<input type="hidden" name="rule" value="', $rule['id'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" class="button" value="', $txt['save'], '">
		</div>
	</form>';
}

function template_boardrules_admin_cats()
{
	global $context, $txt, $scripturl;

	$base = $scripturl . '?action=admin;area=boardrules';
	$sess = $context['session_var'] . '=' . $context['session_id'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['boardrules_admin_cats'], '</h3>
	</div>
	<div class="windowbg boardrules_admin_toolbar">
		<a class="button" href="', $base, ';sa=editcat">', $txt['boardrules_add_cat'], '</a>
	</div>
	<table class="table_grid boardrules_table">
		<thead>
			<tr class="title_bar">
				<th class="centercol" style="width: 4%;">#</th>
				<th>', $txt['boardrules_cat_title'], '</th>
				<th style="width: 14%;">', $txt['boardrules_language'], '</th>
				<th class="centercol" style="width: 8%;">', $txt['boardrules_rules_count'], '</th>
				<th class="centercol" style="width: 8%;">', $txt['boardrules_status'], '</th>
				<th class="centercol" style="width: 20%;">', $txt['boardrules_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($context['boardrules_cats']))
		echo '
			<tr class="windowbg"><td colspan="6" class="centertext">', $txt['boardrules_need_category'], '</td></tr>';

	$n = 0;
	$last = count($context['boardrules_cats']);
	foreach ($context['boardrules_cats'] as $cat)
	{
		$n++;
		echo '
			<tr class="windowbg">
				<td class="centercol">', $n, '</td>
				<td><a href="', $base, ';sa=editcat;cat=', $cat['id'], '"><strong>', $cat['title'], '</strong></a></td>
				<td>', $cat['lang'] === '' ? $txt['boardrules_lang_all'] : boardrules_lang_name($cat['lang']), '</td>
				<td class="centercol">', count($cat['rules']), '</td>
				<td class="centercol"><span class="boardrules_badge ', $cat['enabled'] ? 'boardrules_on">' . $txt['boardrules_enabled_short'] : 'boardrules_off">' . $txt['boardrules_disabled'], '</span></td>
				<td class="centercol boardrules_actions">
					', $n > 1 ? '<a href="' . $base . ';sa=movecat;cat=' . $cat['id'] . ';dir=up;' . $sess . '" title="' . $txt['boardrules_move_up'] . '">&#9650;</a>' : '<span class="boardrules_dim">&#9650;</span>', '
					', $n < $last ? '<a href="' . $base . ';sa=movecat;cat=' . $cat['id'] . ';dir=down;' . $sess . '" title="' . $txt['boardrules_move_down'] . '">&#9660;</a>' : '<span class="boardrules_dim">&#9660;</span>', '
					<a href="', $base, ';sa=editcat;cat=', $cat['id'], '">', $txt['modify'], '</a> |
					<a href="', $base, ';sa=deletecat;cat=', $cat['id'], ';', $sess, '" onclick="return confirm(', JavaScriptEscape($txt['boardrules_confirm_delete_cat']), ');">', $txt['delete'], '</a>
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

function template_boardrules_admin_edit_cat()
{
	global $context, $txt, $scripturl;

	$cat = $context['boardrules_cat'];

	echo '
	<form action="', $scripturl, '?action=admin;area=boardrules;sa=savecat" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $context['page_title'], '</h3>
		</div>
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="br_title"><strong>', $txt['boardrules_cat_title'], '</strong></label></dt>
				<dd><input type="text" name="title" id="br_title" value="', $cat['title'], '" size="60" maxlength="200" required></dd>

				<dt><label for="br_desc"><strong>', $txt['boardrules_cat_desc'], '</strong></label><br><span class="smalltext">', $txt['boardrules_bbc_ok'], '</span></dt>
				<dd><textarea name="description" id="br_desc" rows="4" cols="60">', $cat['description'], '</textarea></dd>

				<dt><label for="br_lang"><strong>', $txt['boardrules_language'], '</strong></label><br><span class="smalltext">', $txt['boardrules_language_desc'], '</span></dt>
				<dd>';
	boardrules_lang_select('lang', $cat['lang']);
	echo '
				</dd>

				<dt><label for="br_enabled"><strong>', $txt['boardrules_enabled_short'], '</strong></label></dt>
				<dd><input type="checkbox" name="enabled" id="br_enabled" value="1"', $cat['enabled'] ? ' checked' : '', '></dd>
			</dl>';

	template_boardrules_notify_fieldset();

	echo '
			<input type="hidden" name="cat" value="', $cat['id'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" class="button" value="', $txt['save'], '">
		</div>
	</form>';
}

function template_boardrules_admin_changes()
{
	global $context, $txt, $scripturl, $modSettings;

	$s = $context['boardrules_stats'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['boardrules_admin_changes'], '</h3>
	</div>
	<div class="windowbg boardrules_stats">
		<div><strong>', $s['version'], '</strong><span>', $txt['boardrules_stat_version'], '</span></div>
		<div><strong>', $s['accepted'] + $s['newer'], ' / ', $s['members'], '</strong><span>', $txt['boardrules_stat_accepted'], '</span></div>
		<div><strong>', $s['outstanding'], '</strong><span>', $txt['boardrules_stat_outstanding'], '</span></div>
		<div class="boardrules_meter"><span style="width: ', $s['percent'], '%;"></span><em>', $s['percent'], '%</em></div>
	</div>

	<form action="', $scripturl, '?action=admin;area=boardrules;sa=publish" method="post" accept-charset="', $context['character_set'], '">
		<div class="title_bar">
			<h4 class="titlebg">', $txt['boardrules_publish_title'], '</h4>
		</div>
		<div class="windowbg">
			<p class="smalltext">', $txt['boardrules_publish_desc'], '</p>
			<dl class="settings">
				<dt><label for="br_summary"><strong>', $txt['boardrules_summary'], '</strong></label></dt>
				<dd><input type="text" name="update_summary" id="br_summary" size="60" maxlength="200" required></dd>';

	if (!empty($modSettings['boardrules_alerts']))
		echo '
				<dt><label for="br_alert"><strong>', $txt['boardrules_send_alert'], '</strong></label></dt>
				<dd><input type="checkbox" name="send_alert" id="br_alert" value="1" checked></dd>';

	echo '
			</dl>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" class="button" value="', $txt['boardrules_publish_button'], '" onclick="return confirm(', JavaScriptEscape($txt['boardrules_confirm_publish']), ');">
		</div>
	</form>

	<div class="title_bar">
		<h4 class="titlebg">', $txt['boardrules_changelog'], '</h4>
	</div>
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th style="width: 8%;">', $txt['boardrules_stat_version'], '</th>
				<th style="width: 22%;">', $txt['date'], '</th>
				<th style="width: 18%;">', $txt['boardrules_by'], '</th>
				<th>', $txt['boardrules_summary'], '</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($context['boardrules_log']))
		echo '
			<tr class="windowbg"><td colspan="4" class="centertext">', $txt['boardrules_log_empty'], '</td></tr>';

	foreach ($context['boardrules_log'] as $entry)
		echo '
			<tr class="windowbg">
				<td>', $entry['version'], '</td>
				<td>', $entry['time'], '</td>
				<td>', $entry['member'], '</td>
				<td>', $entry['summary'], '</td>
			</tr>';

	echo '
		</tbody>
	</table>';
}

/* -------------------------------------------------------------------------
 * Small helpers
 * ---------------------------------------------------------------------- */

function template_boardrules_notify_fieldset()
{
	global $txt, $modSettings;

	echo '
			<fieldset class="boardrules_notify">
				<legend>', $txt['boardrules_notify_legend'], '</legend>
				<label><input type="checkbox" name="publish_update" value="1"> ', $txt['boardrules_publish_label'], '</label>
				<div>
					<label for="br_upd_summary">', $txt['boardrules_summary'], ':</label>
					<input type="text" name="update_summary" id="br_upd_summary" size="50" maxlength="200">
				</div>';

	if (!empty($modSettings['boardrules_alerts']))
		echo '
				<label><input type="checkbox" name="send_alert" value="1" checked> ', $txt['boardrules_send_alert'], '</label>';

	echo '
				<p class="smalltext">', $txt['boardrules_publish_desc'], '</p>
			</fieldset>';
}

function boardrules_lang_select($name, $current)
{
	global $context, $txt;

	echo '
					<select name="', $name, '" id="br_lang">
						<option value=""', $current === '' ? ' selected' : '', '>', $txt['boardrules_lang_all'], '</option>';

	foreach ($context['boardrules_languages'] as $key => $lang)
		echo '
						<option value="', $key, '"', $current === $key ? ' selected' : '', '>', $lang['name'], '</option>';

	echo '
					</select>';
}

function boardrules_lang_name($lang)
{
	global $context;

	return isset($context['boardrules_languages'][$lang]['name']) ? $context['boardrules_languages'][$lang]['name'] : $lang;
}

function boardrules_lang_badge($lang)
{
	return $lang === '' ? '' : '<span class="boardrules_badge">' . boardrules_lang_name($lang) . '</span>';
}
