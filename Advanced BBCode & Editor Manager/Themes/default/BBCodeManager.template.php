<?php

/**
 * Advanced BBCode & Editor Manager: admin templates.
 *
 * @package BBCodeManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.2
 */

function bbm_h($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function template_bbm_notices()
{
	global $context, $txt;

	if (!empty($context['bbm_trying']))
		echo '
	<div class="noticebox">', sprintf($txt['bbm_trying_banner'], bbm_h($context['bbm_trying']['name'])), '
		<a class="button floatright" href="', $context['bbm_url'], ';sa=profiles;untry', $context['bbm_session'], '">', $txt['bbm_stop_trying'], '</a>
	</div>';

	if (!empty($context['bbm_notice']))
		echo '
	<div class="', $context['bbm_notice']['type'] === 'error' ? 'errorbox' : 'infobox', '">', $context['bbm_notice']['text'], '</div>';

	if (!empty($context['bbm_errors']))
	{
		echo '
	<div class="errorbox">
		<strong>', $txt['bbm_fix_errors'], '</strong>
		<ul class="bbm_error_list">';
		foreach ($context['bbm_errors'] as $error)
			echo '
			<li>', $error, '</li>';
		echo '
		</ul>
	</div>';
	}
}

function template_bbm_title($title, $intro = '')
{
	echo '
	<div class="cat_bar"><h3 class="catbg">', $title, '</h3></div>';

	if ($intro !== '')
		echo '
	<p class="information">', $intro, '</p>';
}

/* --------------------------------------------------------------------
 * Tags
 * ------------------------------------------------------------------ */

function template_bbm_tags()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_tags'], $txt['bbm_tags_intro']);

	echo '
	<div class="bbm_actions">
		<a class="button" href="', $context['bbm_url'], ';sa=edittag">', $txt['bbm_add_tag'], '</a>
		<a class="button" href="', $context['bbm_url'], ';sa=library">', $txt['bbm_browse_library'], '</a>
	</div>';

	if (empty($context['bbm_tags']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['bbm_no_tags'], '</p></div>';
		return;
	}

	echo '
	<table class="table_grid bbm_table">
		<thead>
			<tr class="title_bar">
				<th scope="col">', $txt['bbm_col_tag'], '</th>
				<th scope="col">', $txt['bbm_col_title'], '</th>
				<th scope="col">', $txt['bbm_col_usage'], '</th>
				<th scope="col">', $txt['bbm_col_button'], '</th>
				<th scope="col">', $txt['bbm_col_status'], '</th>
				<th scope="col" class="righttext">', $txt['bbm_col_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['bbm_tags'] as $id => $t)
	{
		$base = $context['bbm_url'] . ';sa=tags';

		echo '
			<tr class="windowbg">
				<td><code>[', $t['tag'], ']</code>', $t['conflict'] ? '
					<div class="bbm_warn">' . $txt['bbm_conflict_short'] . '</div>' : '', $t['core_disabled'] ? '
					<div class="bbm_warn">' . $txt['bbm_core_disabled_short'] . '</div>' : '', '</td>
				<td><strong>', bbm_h($t['title']), '</strong>', $t['description'] !== '' ? '<br><span class="smalltext">' . bbm_h($t['description']) . '</span>' : '', '</td>
				<td><code class="bbm_usage">', bbm_h($t['usage']), '</code></td>
				<td>', $t['button'] ? $txt['bbm_yes'] : $txt['bbm_no'], $t['shortcut'] !== '' ? '<br><kbd class="bbm-kbd">' . bbm_h($t['shortcut']) . '</kbd>' : '', '</td>
				<td><a href="', $base, ';toggle=', $id, $context['bbm_session'], '" class="bbm_status ', $t['enabled'] ? 'bbm_on' : 'bbm_off', '">', $t['enabled'] ? $txt['bbm_enabled'] : $txt['bbm_disabled'], '</a></td>
				<td class="righttext bbm_nowrap">
					<a href="', $base, ';move=', $id, ';dir=up', $context['bbm_session'], '" title="', $txt['bbm_move_up'], '">&#9650;</a>
					<a href="', $base, ';move=', $id, ';dir=down', $context['bbm_session'], '" title="', $txt['bbm_move_down'], '">&#9660;</a>
					<a class="button" href="', $context['bbm_url'], ';sa=edittag;id=', $id, '">', $txt['bbm_edit'], '</a>
					<a class="button" href="', $base, ';delete=', $id, $context['bbm_session'], '" data-bbm-confirm="', bbm_h(sprintf($txt['bbm_confirm_delete_tag'], $t['tag'])), '">', $txt['bbm_delete'], '</a>
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

function template_bbm_edit_tag()
{
	global $context, $txt, $settings;

	$t = $context['bbm_tag'];

	template_bbm_notices();

	echo '
	<form id="bbm_tag_form" class="bbm_form" action="', $context['bbm_url'], ';sa=edittag', $t['id_tag'] ? ';id=' . $t['id_tag'] : '', '" method="post" accept-charset="UTF-8" enctype="multipart/form-data"
		data-preview-url="', $context['bbm_url'], ';sa=preview">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="id" value="', (int) $t['id_tag'], '">';

	// Basics
	template_bbm_title($context['page_title']);
	echo '
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="bbm_tag">', $txt['bbm_field_tag'], '</label><br><span class="smalltext">', $txt['bbm_field_tag_desc'], '</span></dt>
				<dd>[<input type="text" id="bbm_tag" name="tag" value="', bbm_h($t['tag']), '" maxlength="30" pattern="[a-zA-Z][a-zA-Z0-9]*" required class="bbm_tag_input">]</dd>

				<dt><label for="bbm_title">', $txt['bbm_field_title'], '</label></dt>
				<dd><input type="text" id="bbm_title" name="title" value="', bbm_h($t['title']), '" maxlength="80" required size="40"></dd>

				<dt><label for="bbm_description">', $txt['bbm_field_description'], '</label></dt>
				<dd><input type="text" id="bbm_description" name="description" value="', bbm_h($t['description']), '" maxlength="255" size="60"></dd>

				<dt>', $txt['bbm_field_mode'], '</dt>
				<dd>
					<fieldset class="bbm_radios">';
	foreach (array('parsed', 'unparsed', 'closed') as $mode)
		echo '
						<label><input type="radio" name="mode" value="', $mode, '"', $t['mode'] === $mode ? ' checked' : '', '> <strong>', $txt['bbm_mode_' . $mode], '</strong> <span class="smalltext">', $txt['bbm_mode_' . $mode . '_desc'], '</span></label>';
	echo '
					</fieldset>
				</dd>

				<dt><label for="bbm_block">', $txt['bbm_field_block'], '</label><br><span class="smalltext">', $txt['bbm_field_block_desc'], '</span></dt>
				<dd><input type="checkbox" id="bbm_block" name="block_level" value="1"', $t['block_level'] ? ' checked' : '', '></dd>

				<dt><label for="bbm_enabled">', $txt['bbm_field_enabled'], '</label><br><span class="smalltext">', $txt['bbm_field_enabled_desc'], '</span></dt>
				<dd><input type="checkbox" id="bbm_enabled" name="enabled" value="1"', $t['enabled'] ? ' checked' : '', '></dd>
			</dl>
		</div>';

	// Options
	template_bbm_title($txt['bbm_options'], $txt['bbm_options_desc']);
	echo '
		<div class="windowbg">
			<div id="bbm_params">';
	foreach ($t['params'] as $i => $p)
		template_bbm_param_row($i, $p);
	echo '
			</div>
			<button type="button" class="button" id="bbm_add_param">', $txt['bbm_add_option'], '</button>
			<template id="bbm_param_template">';
	template_bbm_param_row('__i__', array());
	echo '
			</template>
		</div>';

	// Template
	template_bbm_title($txt['bbm_template'], $txt['bbm_template_desc']);
	echo '
		<div class="windowbg">
			<div class="bbm_starters">
				<span class="smalltext">', $txt['bbm_start_from'], '</span>';
	foreach ($context['bbm_starters'] as $key => $html)
		echo '
				<button type="button" class="button bbm_starter" data-template="', bbm_h($html), '">', $txt['bbm_starter_' . $key], '</button>';
	echo '
			</div>
			<div class="bbm_placeholders" id="bbm_placeholders" aria-label="', $txt['bbm_insert_placeholder'], '"></div>
			<textarea id="bbm_template" name="template" rows="8" class="bbm_code" spellcheck="false" required>', bbm_h($t['template']), '</textarea>
			<p class="smalltext">', $txt['bbm_template_help'], '</p>
		</div>';

	// Preview
	template_bbm_title($txt['bbm_live_preview']);
	echo '
		<div class="windowbg bbm_preview_wrap">
			<dl class="settings">
				<dt><label for="bbm_sample_option">', $txt['bbm_sample_option'], '</label><br><span class="smalltext">', $txt['bbm_sample_option_desc'], '</span></dt>
				<dd><input type="text" id="bbm_sample_option" name="sample_option" size="40"></dd>
				<dt><label for="bbm_sample_content">', $txt['bbm_sample_content_label'], '</label></dt>
				<dd><textarea id="bbm_sample_content" name="sample_content" rows="3" cols="50">', $txt['bbm_sample_content'], '</textarea></dd>
			</dl>
			<p class="smalltext">', $txt['bbm_usage'], ' <code id="bbm_usage_example"></code></p>
			<div id="bbm_preview" class="bbm_preview_area" aria-live="polite"></div>
		</div>';

	// Button
	template_bbm_title($txt['bbm_toolbar_button']);
	echo '
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="bbm_button">', $txt['bbm_field_button'], '</label></dt>
				<dd><input type="checkbox" id="bbm_button" name="button" value="1"', $t['button'] ? ' checked' : '', '></dd>

				<dt><label for="bbm_tooltip">', $txt['bbm_field_tooltip'], '</label><br><span class="smalltext">', $txt['bbm_field_tooltip_desc'], '</span></dt>
				<dd><input type="text" id="bbm_tooltip" name="tooltip" value="', bbm_h($t['tooltip']), '" maxlength="100" size="40"></dd>

				<dt><label for="bbm_shortcut">', $txt['bbm_field_shortcut'], '</label><br><span class="smalltext">', $txt['bbm_field_shortcut_desc'], '</span></dt>
				<dd><input type="text" id="bbm_shortcut" name="shortcut" value="', bbm_h($t['shortcut']), '" maxlength="30" placeholder="ctrl+shift+1" class="bbm_shortcut_input"></dd>

				<dt><label for="bbm_icon">', $txt['bbm_field_icon'], '</label><br><span class="smalltext">', $txt['bbm_field_icon_desc'], '</span></dt>
				<dd>';
	if ($t['icon'] !== '')
		echo '
					<img src="', $settings['default_theme_url'], '/images/bbm/', bbm_h($t['icon']), '" alt="" class="bbm_icon_preview">
					<label><input type="checkbox" name="icon_remove" value="1"> ', $txt['bbm_remove_icon'], '</label><br>';
	echo '
					<input type="file" id="bbm_icon" name="icon" accept="image/png,image/gif,image/jpeg,image/webp">
				</dd>
			</dl>
			<input type="submit" name="save" value="', $txt['bbm_save_tag'], '" class="button">
			<a class="button" href="', $context['bbm_url'], ';sa=tags">', $txt['bbm_cancel'], '</a>
		</div>
	</form>';
}

function template_bbm_param_row($i, array $p)
{
	global $txt;

	$p += array('name' => '', 'type' => 'text', 'default' => '', 'required' => false, 'choices' => array(), 'min' => '', 'max' => '', 'maxlen' => '');
	$choices = is_array($p['choices']) ? implode("\n", $p['choices']) : (string) $p['choices'];
	$n = 'param[' . $i . ']';

	echo '
				<fieldset class="bbm_param" data-type="', bbm_h($p['type']), '">
					<div class="bbm_param_grid">
						<label>', $txt['bbm_param_name'], '<input type="text" name="', $n, '[name]" value="', bbm_h($p['name']), '" maxlength="20" pattern="[a-zA-Z][a-zA-Z0-9_]*" class="bbm_param_name"></label>
						<label>', $txt['bbm_param_type'], '<select name="', $n, '[type]" class="bbm_param_type">';
	foreach (array('text', 'number', 'color', 'url', 'choice') as $type)
		echo '<option value="', $type, '"', $p['type'] === $type ? ' selected' : '', '>', $txt['bbm_type_' . $type], '</option>';
	echo '</select></label>
						<label>', $txt['bbm_param_default'], '<input type="text" name="', $n, '[default]" value="', bbm_h($p['default']), '" maxlength="500"></label>
						<label class="bbm_param_check"><input type="checkbox" name="', $n, '[required]" value="1"', !empty($p['required']) ? ' checked' : '', '> ', $txt['bbm_param_required'], '</label>
						<label class="bbm_for_number">', $txt['bbm_param_min'], '<input type="number" step="any" name="', $n, '[min]" value="', bbm_h($p['min']), '"></label>
						<label class="bbm_for_number">', $txt['bbm_param_max'], '<input type="number" step="any" name="', $n, '[max]" value="', bbm_h($p['max']), '"></label>
						<label class="bbm_for_text">', $txt['bbm_param_maxlen'], '<input type="number" min="1" max="500" name="', $n, '[maxlen]" value="', bbm_h($p['maxlen']), '"></label>
						<label class="bbm_for_choice bbm_wide">', $txt['bbm_param_choices'], '<textarea name="', $n, '[choices]" rows="3">', bbm_h($choices), '</textarea></label>
					</div>
					<button type="button" class="button bbm_remove_param">', $txt['bbm_remove'], '</button>
				</fieldset>';
}

function template_bbm_preview()
{
	global $context, $txt;

	if (!empty($context['bbm_preview_errors']))
	{
		echo '<div class="errorbox"><strong>', $txt['bbm_preview_problems'], '</strong><ul class="bbm_error_list">';
		foreach ($context['bbm_preview_errors'] as $error)
			echo '<li>', $error, '</li>';
		echo '</ul></div>';
		return;
	}

	echo '<div class="bbm_preview_post inner">', $context['bbm_preview'], '</div>';
}

/* --------------------------------------------------------------------
 * Library
 * ------------------------------------------------------------------ */

function template_bbm_library()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_library'], $txt['bbm_library_intro']);

	echo '
	<div class="bbm_library">';

	foreach ($context['bbm_presets'] as $key => $p)
	{
		echo '
		<div class="windowbg bbm_library_item">
			<h4>', bbm_h($p['title']), ' <code>[', $p['tag'], ']</code></h4>
			<p class="smalltext">', bbm_h($p['description']), '</p>
			<div class="bbm_preview_area"><div class="bbm_preview_post inner">', $p['preview'], '</div></div>
			<p class="smalltext"><code class="bbm_usage">', bbm_h($p['usage']), '</code></p>';

		if ($p['installed'])
			echo '
			<span class="bbm_status bbm_on">', $txt['bbm_installed'], '</span>';
		elseif ($p['conflict'])
			echo '
			<span class="bbm_status bbm_off">', $txt['bbm_conflict_short'], '</span>';
		else
			echo '
			<form action="', $context['bbm_url'], ';sa=library" method="post" accept-charset="UTF-8">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<button type="submit" name="add" value="', $key, '" class="button">', $txt['bbm_add_to_forum'], '</button>
			</form>';

		echo '
		</div>';
	}

	echo '
		<div class="windowbg bbm_library_item">
			<h4>', $txt['bbm_simple_profile_name'], '</h4>
			<p class="smalltext">', $txt['bbm_simple_profile_desc'], '</p>
			<form action="', $context['bbm_url'], ';sa=library" method="post" accept-charset="UTF-8">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<button type="submit" name="add_profile" value="1" class="button">', $txt['bbm_add_to_forum'], '</button>
			</form>
		</div>
	</div>';
}

/* --------------------------------------------------------------------
 * Profiles
 * ------------------------------------------------------------------ */

function template_bbm_profiles()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_profiles'], $txt['bbm_profiles_intro']);

	echo '
	<div class="bbm_actions">
		<a class="button" href="', $context['bbm_url'], ';sa=editprofile">', $txt['bbm_add_profile'], '</a>
		<a class="button" href="', $context['bbm_url'], ';sa=rules">', $txt['bbm_manage_rules'], '</a>
	</div>';

	if (empty($context['bbm_profiles']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['bbm_no_profiles'], '</p></div>';
		return;
	}

	echo '
	<table class="table_grid bbm_table">
		<thead>
			<tr class="title_bar">
				<th scope="col">', $txt['bbm_col_name'], '</th>
				<th scope="col">', $txt['bbm_col_toolbar'], '</th>
				<th scope="col">', $txt['bbm_col_allowed'], '</th>
				<th scope="col">', $txt['bbm_col_rules'], '</th>
				<th scope="col" class="righttext">', $txt['bbm_col_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['bbm_profiles'] as $id => $p)
	{
		$base = $context['bbm_url'] . ';sa=profiles';
		$trying = !empty($context['bbm_trying']) && (int) $context['bbm_trying']['id_profile'] === (int) $id;

		echo '
			<tr class="windowbg">
				<td><strong>', bbm_h($p['name']), '</strong>', $p['is_default'] ? ' <span class="bbm_status bbm_on">' . $txt['bbm_default'] . '</span>' : '', '</td>
				<td>', $p['buttons'] ? sprintf($txt['bbm_n_buttons'], $p['buttons']) : $txt['bbm_smf_layout'], '</td>
				<td>', $p['allowed'] === null ? $txt['bbm_all_tags'] : sprintf($txt['bbm_n_tags'], count($p['allowed'])), '</td>
				<td>', $p['rules'], '</td>
				<td class="righttext bbm_nowrap">
					<a class="button" href="', $context['bbm_url'], ';sa=editprofile;id=', $id, '">', $txt['bbm_edit'], '</a>',
					$trying
						? '<a class="button" href="' . $base . ';untry' . $context['bbm_session'] . '">' . $txt['bbm_stop_trying'] . '</a>'
						: '<a class="button" href="' . $base . ';try=' . $id . $context['bbm_session'] . '" title="' . $txt['bbm_try_desc'] . '">' . $txt['bbm_try'] . '</a>',
					$p['is_default'] ? '' : '<a class="button" href="' . $base . ';default=' . $id . $context['bbm_session'] . '">' . $txt['bbm_make_default'] . '</a>', '
					<a class="button" href="', $base, ';delete=', $id, $context['bbm_session'], '" data-bbm-confirm="', bbm_h(sprintf($txt['bbm_confirm_delete_profile'], $p['name'])), '">', $txt['bbm_delete'], '</a>
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

function template_bbm_edit_profile()
{
	global $context, $txt;

	$p = $context['bbm_profile'];

	template_bbm_notices();

	echo '
	<form class="bbm_form" action="', $context['bbm_url'], ';sa=editprofile', $p['id_profile'] ? ';id=' . $p['id_profile'] : '', '" method="post" accept-charset="UTF-8" id="bbm_profile_form">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	template_bbm_title($context['page_title']);
	echo '
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="bbm_pname">', $txt['bbm_col_name'], '</label></dt>
				<dd><input type="text" id="bbm_pname" name="name" value="', bbm_h($p['name']), '" maxlength="60" required size="40"></dd>
				<dt><label for="bbm_pdefault">', $txt['bbm_field_default'], '</label><br><span class="smalltext">', $txt['bbm_field_default_desc'], '</span></dt>
				<dd><input type="checkbox" id="bbm_pdefault" name="is_default" value="1"', $p['is_default'] ? ' checked' : '', '></dd>
			</dl>
		</div>';

	template_bbm_title($txt['bbm_toolbar_layout'], $txt['bbm_toolbar_layout_desc']);
	echo '
		<div class="windowbg">
			<div id="bbm_builder" class="bbm_builder" data-buttons="', bbm_h(json_encode($context['bbm_buttons'])), '"
				data-txt="', bbm_h(json_encode(array(
					'pool' => $txt['bbm_builder_pool'], 'row' => $txt['bbm_builder_row'], 'add_row' => $txt['bbm_builder_add_row'],
					'separator' => $txt['bbm_builder_separator'], 'remove' => $txt['bbm_remove'], 'empty' => $txt['bbm_builder_empty'],
					'filter' => $txt['bbm_builder_filter'], 'reset' => $txt['bbm_builder_reset'],
				))), '"></div>
			<input type="hidden" name="toolbar" id="bbm_toolbar_json" value="', bbm_h(json_encode($p['toolbar'])), '">
		</div>';

	template_bbm_title($txt['bbm_allowed_tags'], $txt['bbm_allowed_tags_desc']);
	echo '
		<div class="windowbg">
			<label class="bbm_allow_all"><input type="checkbox" name="allow_all" value="1" id="bbm_allow_all"', $p['allowed'] === null ? ' checked' : '', '> <strong>', $txt['bbm_allow_all'], '</strong></label>
			<div id="bbm_allowed_list" class="bbm_allowed">
				<p>
					<button type="button" class="button" data-bbm-check="1">', $txt['bbm_select_all'], '</button>
					<button type="button" class="button" data-bbm-check="0">', $txt['bbm_select_none'], '</button>
				</p>';

	$allowed = $p['allowed'] === null ? $context['bbm_allowable'] : $p['allowed'];

	echo '
				<fieldset><legend>', $txt['bbm_own_tags'], '</legend><div class="bbm_check_grid">';
	foreach ($context['bbm_own_tags'] as $tag => $title)
		echo '
					<label title="', bbm_h($title), '"><input type="checkbox" name="allowed[]" value="', $tag, '"', in_array($tag, $allowed, true) ? ' checked' : '', '> [', $tag, ']</label>';
	if (empty($context['bbm_own_tags']))
		echo '<span class="smalltext">', $txt['bbm_no_tags'], '</span>';
	echo '
				</div></fieldset>
				<fieldset><legend>', $txt['bbm_other_tags'], '</legend><div class="bbm_check_grid">';
	foreach ($context['bbm_allowable'] as $tag)
		if (!isset($context['bbm_own_tags'][$tag]))
			echo '
					<label><input type="checkbox" name="allowed[]" value="', bbm_h($tag), '"', in_array($tag, $allowed, true) ? ' checked' : '', '> [', bbm_h($tag), ']</label>';
	echo '
				</div></fieldset>
			</div>
			<p class="smalltext">', $txt['bbm_enforce_note'], '</p>
			<hr>
			<label><input type="checkbox" name="try_after" value="1"> ', $txt['bbm_try_after'], '</label><br><br>
			<input type="submit" name="save" value="', $txt['bbm_save_profile'], '" class="button">
			<a class="button" href="', $context['bbm_url'], ';sa=profiles">', $txt['bbm_cancel'], '</a>
		</div>
	</form>';
}

/* --------------------------------------------------------------------
 * Rules
 * ------------------------------------------------------------------ */

function template_bbm_rules()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_rules'], $txt['bbm_rules_intro']);

	echo '
	<div class="bbm_actions">
		<a class="button" href="', $context['bbm_url'], ';sa=editrule">', $txt['bbm_add_rule'], '</a>
	</div>';

	if (empty($context['bbm_rules']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['bbm_no_rules'], '</p></div>';
		return;
	}

	echo '
	<table class="table_grid bbm_table">
		<thead>
			<tr class="title_bar">
				<th scope="col">', $txt['bbm_col_priority'], '</th>
				<th scope="col">', $txt['bbm_col_profile'], '</th>
				<th scope="col">', $txt['bbm_col_where'], '</th>
				<th scope="col">', $txt['bbm_col_boards'], '</th>
				<th scope="col">', $txt['bbm_col_groups'], '</th>
				<th scope="col" class="righttext">', $txt['bbm_col_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['bbm_rules'] as $id => $r)
	{
		$where = array();
		foreach ($r['contexts'] ?: BBM::$contexts as $c)
			$where[] = $txt['bbm_context_' . $c];

		$boards = array();
		foreach ($r['boards'] as $b)
			$boards[] = isset($context['bbm_board_list'][$b]) ? $context['bbm_board_list'][$b]['name'] : '#' . $b;

		$groups = array();
		foreach ($r['groups'] as $g)
			$groups[] = isset($context['bbm_group_list'][$g]) ? $context['bbm_group_list'][$g] : '#' . $g;

		echo '
			<tr class="windowbg">
				<td>', $r['priority'], '</td>
				<td><strong>', isset($context['bbm_profiles'][$r['id_profile']]) ? bbm_h($context['bbm_profiles'][$r['id_profile']]['name']) : '?', '</strong></td>
				<td>', implode(', ', $where), '</td>
				<td>', $boards ? implode(', ', $boards) : $txt['bbm_any'], '</td>
				<td>', $groups ? implode(', ', $groups) : $txt['bbm_any'], '</td>
				<td class="righttext bbm_nowrap">
					<a class="button" href="', $context['bbm_url'], ';sa=editrule;id=', $id, '">', $txt['bbm_edit'], '</a>
					<a class="button" href="', $context['bbm_url'], ';sa=rules;delete=', $id, $context['bbm_session'], '" data-bbm-confirm="', bbm_h($txt['bbm_confirm_delete_rule']), '">', $txt['bbm_delete'], '</a>
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

function template_bbm_edit_rule()
{
	global $context, $txt;

	$r = $context['bbm_rule'];

	template_bbm_notices();

	echo '
	<form class="bbm_form" action="', $context['bbm_url'], ';sa=editrule', $r['id_rule'] ? ';id=' . $r['id_rule'] : '', '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	template_bbm_title($context['page_title'], $txt['bbm_rule_desc']);

	echo '
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="bbm_rprofile">', $txt['bbm_col_profile'], '</label></dt>
				<dd><select id="bbm_rprofile" name="id_profile">';
	foreach ($context['bbm_profiles'] as $id => $p)
		echo '<option value="', $id, '"', (int) $r['id_profile'] === (int) $id ? ' selected' : '', '>', bbm_h($p['name']), '</option>';
	echo '</select></dd>

				<dt>', $txt['bbm_col_where'], '<br><span class="smalltext">', $txt['bbm_where_desc'], '</span></dt>
				<dd>';
	foreach (BBM::$contexts as $c)
		echo '
					<label><input type="checkbox" name="contexts[]" value="', $c, '"', in_array($c, $r['contexts'], true) ? ' checked' : '', '> ', $txt['bbm_context_' . $c], '</label><br>';
	echo '
				</dd>

				<dt>', $txt['bbm_col_groups'], '<br><span class="smalltext">', $txt['bbm_groups_desc'], '</span></dt>
				<dd><div class="bbm_check_grid">';
	foreach ($context['bbm_group_list'] as $id => $name)
		echo '
					<label><input type="checkbox" name="groups[]" value="', $id, '"', in_array((int) $id, $r['groups'], true) ? ' checked' : '', '> ', $name, '</label>';
	echo '
				</div></dd>

				<dt>', $txt['bbm_col_boards'], '<br><span class="smalltext">', $txt['bbm_boards_desc'], '</span></dt>
				<dd><div class="bbm_board_list">';
	$cat = null;
	foreach ($context['bbm_board_list'] as $id => $b)
	{
		if ($b['cat_name'] !== $cat)
		{
			$cat = $b['cat_name'];
			echo '
					<strong class="bbm_board_cat">', $cat, '</strong>';
		}
		echo '
					<label style="margin-left:', 1 + (int) $b['child_level'] * 1.25, 'em"><input type="checkbox" name="boards[]" value="', $id, '"', in_array((int) $id, $r['boards'], true) ? ' checked' : '', '> ', $b['name'], '</label>';
	}
	echo '
				</div></dd>

				<dt><label for="bbm_rpriority">', $txt['bbm_col_priority'], '</label><br><span class="smalltext">', $txt['bbm_priority_desc'], '</span></dt>
				<dd><input type="number" id="bbm_rpriority" name="priority" value="', (int) $r['priority'], '" min="-1000" max="1000"></dd>
			</dl>
			<input type="submit" name="save" value="', $txt['bbm_save_rule'], '" class="button">
			<a class="button" href="', $context['bbm_url'], ';sa=rules">', $txt['bbm_cancel'], '</a>
		</div>
	</form>';
}

/* --------------------------------------------------------------------
 * Buttons, transfer, history, diagnostics
 * ------------------------------------------------------------------ */

function template_bbm_buttons()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_buttons'], $txt['bbm_buttons_intro']);

	echo '
	<form class="bbm_form" action="', $context['bbm_url'], ';sa=buttons" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<table class="table_grid bbm_table">
			<thead>
				<tr class="title_bar">
					<th scope="col">', $txt['bbm_col_button'], '</th>
					<th scope="col">', $txt['bbm_col_default_tooltip'], '</th>
					<th scope="col">', $txt['bbm_col_custom_tooltip'], '</th>
				</tr>
			</thead>
			<tbody>';

	foreach ($context['bbm_known'] as $code => $label)
		echo '
				<tr class="windowbg">
					<td><code>', bbm_h($code), '</code></td>
					<td>', bbm_h($label), '</td>
					<td><input type="text" name="tip[', bbm_h($code), ']" value="', bbm_h(isset($context['bbm_tooltips'][$code]) ? $context['bbm_tooltips'][$code] : ''), '" maxlength="100" size="40"></td>
				</tr>';

	echo '
			</tbody>
		</table>
		<div class="windowbg">
			<p class="smalltext">', $txt['bbm_buttons_custom_note'], '</p>
			<input type="submit" name="save" value="', $txt['bbm_save'], '" class="button">
		</div>
	</form>';
}

function template_bbm_transfer()
{
	global $context, $txt;

	template_bbm_notices();

	if (!empty($context['bbm_report']))
	{
		echo '
	<div class="infobox"><ul class="bbm_error_list">';
		foreach ($context['bbm_report'] as $line)
			echo '<li>', $line, '</li>';
		echo '</ul></div>';
	}

	template_bbm_title($txt['bbm_export'], $txt['bbm_export_desc']);
	echo '
	<div class="windowbg">
		<a class="button" href="', $context['bbm_url'], ';sa=transfer;export', $context['bbm_session'], '">', $txt['bbm_download_export'], '</a>
	</div>';

	template_bbm_title($txt['bbm_import'], $txt['bbm_import_desc']);
	echo '
	<form class="windowbg bbm_form" action="', $context['bbm_url'], ';sa=transfer" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<dl class="settings">
			<dt><label for="bbm_import_file">', $txt['bbm_import_file'], '</label></dt>
			<dd><input type="file" id="bbm_import_file" name="file" accept=".json,application/json" required></dd>
			<dt>', $txt['bbm_import_parts'], '</dt>
			<dd>';
	foreach (array('tags', 'profiles', 'tooltips', 'settings') as $part)
		echo '
				<label><input type="checkbox" name="parts[]" value="', $part, '"', $part !== 'settings' ? ' checked' : '', '> ', $txt['bbm_part_' . $part], '</label><br>';
	echo '
			</dd>
		</dl>
		<input type="submit" name="import" value="', $txt['bbm_import_button'], '" class="button" data-bbm-confirm="', bbm_h($txt['bbm_confirm_import']), '">
	</form>';
}

function template_bbm_history()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_history'], $txt['bbm_history_intro']);

	if (empty($context['bbm_revisions']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['bbm_no_history'], '</p></div>';
		return;
	}

	echo '
	<table class="table_grid bbm_table">
		<thead>
			<tr class="title_bar">
				<th scope="col">', $txt['bbm_col_when'], '</th>
				<th scope="col">', $txt['bbm_col_who'], '</th>
				<th scope="col">', $txt['bbm_col_change'], '</th>
				<th scope="col">', $txt['bbm_col_contents'], '</th>
				<th scope="col" class="righttext">', $txt['bbm_col_actions'], '</th>
			</tr>
		</thead>
		<tbody>';

	foreach ($context['bbm_revisions'] as $rev)
	{
		echo '
			<tr class="windowbg">
				<td class="bbm_nowrap">', $rev['time'], '</td>
				<td>', bbm_h($rev['member']), '</td>
				<td>', bbm_h($rev['note']), $rev['current'] ? ' <span class="bbm_status bbm_on">' . $txt['bbm_current'] . '</span>' : '', '</td>
				<td class="smalltext">', sprintf($txt['bbm_rev_counts'], $rev['tags'], $rev['profiles'], $rev['rules']), '</td>
				<td class="righttext bbm_nowrap">
					<a class="button" href="', $context['bbm_url'], ';sa=history;download=', $rev['id'], $context['bbm_session'], '">', $txt['bbm_download'], '</a>';

		if (!$rev['current'])
			echo '
					<form action="', $context['bbm_url'], ';sa=history" method="post" accept-charset="UTF-8" class="bbm_inline">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<button type="submit" name="restore" value="', $rev['id'], '" class="button" data-bbm-confirm="', bbm_h($txt['bbm_confirm_restore']), '">', $txt['bbm_restore'], '</button>
					</form>';

		echo '
				</td>
			</tr>';
	}

	echo '
		</tbody>
	</table>';
}

function template_bbm_diagnostics()
{
	global $context, $txt;

	template_bbm_notices();
	template_bbm_title($txt['bbm_tab_diagnostics'], $txt['bbm_diagnostics_intro']);

	foreach ($context['bbm_checks'] as $section => $checks)
	{
		echo '
	<div class="title_bar"><h4 class="titlebg">', $txt['bbm_diag_' . $section . '_title'], '</h4></div>
	<div class="windowbg">
		<ul class="bbm_checks">';
		foreach ($checks as $check)
		{
			$class = $check[0] ? 'bbm_check_ok' : (!empty($check[2]) ? 'bbm_check_warn' : 'bbm_check_bad');
			echo '
			<li class="', $class, '">', $check[1], '</li>';
		}
		echo '
		</ul>
	</div>';
	}

	echo '
	<div class="title_bar"><h4 class="titlebg">', $txt['bbm_diag_hooks_title'], '</h4></div>
	<div class="windowbg">
		<p class="smalltext">', $txt['bbm_diag_hooks_desc'], '</p>
		<dl class="settings">';
	foreach ($context['bbm_hook_mods'] as $hook => $functions)
		echo '
			<dt><code>', $hook, '</code></dt>
			<dd>', empty($functions) ? '<span class="smalltext">' . $txt['bbm_diag_none'] . '</span>' : '<code>' . implode('</code><br><code>', array_map('bbm_h', $functions)) . '</code>', '</dd>';
	echo '
		</dl>
	</div>

	<div class="title_bar"><h4 class="titlebg">', $txt['bbm_diag_foreign_title'], '</h4></div>
	<div class="windowbg">
		<p class="smalltext">', $txt['bbm_diag_foreign_desc'], '</p>
		<p class="bbm_tag_cloud">';
	foreach ($context['bbm_foreign_tags'] as $tag => $count)
		echo '<code>[', bbm_h($tag), ']</code> ';
	echo '</p>
	</div>';
}
