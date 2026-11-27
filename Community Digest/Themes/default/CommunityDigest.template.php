<?php
/**
 * Community Digest - templates.
 *
 * @package CommunityDigest
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.7
 */

/**
 * The personal digest dashboard (action=digest).
 */
function template_cd_dashboard()
{
	global $context, $txt, $scripturl;

	$cd = $context['cd'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg"><span class="main_icons mail"></span> ', $txt['cd_dashboard_title'], '</h3>
	</div>
	<div class="information cd_toolbar">
		<div class="cd_toolbar_text">';

	if ($cd['count'] > 0)
		echo sprintf($txt['cd_dashboard_summary'], $cd['count'], $cd['unread']), $cd['batch_time'] !== '' ? ' <span class="cd_muted">' . sprintf($txt['cd_dashboard_updated'], $cd['batch_time']) . '</span>' : '';
	else
		echo $txt['cd_dashboard_empty'];

	echo '
		</div>
		<div class="cd_toolbar_buttons">';

	if ($cd['unread'] > 0)
		echo '
			<a href="', $scripturl, '?action=digest;sa=readall;', $cd['session'], '" class="button">', $txt['cd_mark_all_read'], '</a>';

	echo '
			<a href="', $scripturl, '?action=digest;sa=refresh;', $cd['session'], '" class="button">', $txt['cd_refresh'], '</a>
			<a href="', $scripturl, '?action=digest;sa=preview" class="button" target="_blank" rel="noopener">', $txt['cd_preview_email'], '</a>
			<a href="', $cd['prefs_url'], '" class="button active">', $txt['cd_preferences'], '</a>
		</div>
	</div>';

	if ($cd['is_default'])
		echo '
	<div class="noticebox">', sprintf($txt['cd_dashboard_tip'], $cd['prefs_url']), '</div>';

	echo '
	<div class="cd_layout">
		<div class="cd_main">';

	foreach ($cd['sections'] as $section)
	{
		echo '
			<section class="cd_section cd_section_', $section['id'], '">
				<div class="title_bar"><h4 class="titlebg">', CommunityDigestEngine::escape($section['title']), '</h4></div>
				<div class="cd_cards">';

		foreach ($section['items'] as $item)
			template_cd_card($item, $cd['session']);

		echo '
				</div>
			</section>';
	}

	if (empty($cd['sections']))
		echo '
			<div class="windowbg cd_empty">
				<span class="main_icons mail"></span>
				<p>', $txt['cd_nothing_yet'], '</p>
				<a href="', $scripturl, '?action=recent" class="button">', $txt['cd_browse_recent'], '</a>
			</div>';

	echo '
		</div>';

	if (!empty($cd['live']))
	{
		echo '
		<aside class="cd_side">';

		foreach ($cd['live'] as $section)
		{
			echo '
			<div class="cd_side_block">
				<div class="title_bar"><h4 class="titlebg">', CommunityDigestEngine::escape($section['title']), '</h4></div>
				<div class="windowbg">';

			if (!empty($section['intro']))
				echo '
					<p class="cd_muted">', CommunityDigestEngine::escape($section['intro']), '</p>';

			echo '
					<ul class="cd_lines', !empty($section['inline']) ? ' cd_inline' : '', '">';

			foreach ($section['lines'] as $line)
				echo '
						<li>', !empty($line['href']) && CommunityDigestEngine::safeUrl($line['href']) !== '' ? '<a href="' . CommunityDigestEngine::safeUrl($line['href']) . '">' . CommunityDigestEngine::escape(isset($line['text']) ? $line['text'] : '') . '</a>' : CommunityDigestEngine::escape(isset($line['text']) ? $line['text'] : ''), !empty($line['meta']) ? ' <span class="cd_muted">' . CommunityDigestEngine::escape($line['meta']) . '</span>' : '', '</li>';

			echo '
					</ul>
				</div>
			</div>';
		}

		echo '
		</aside>';
	}

	echo '
	</div>';
}

/**
 * One recommendation card.
 */
function template_cd_card($item, $session)
{
	global $txt, $scripturl;

	echo '
					<article class="windowbg cd_card', $item['is_read'] ? ' cd_read' : '', '">
						<div class="cd_card_head">
							<a href="', $item['board_href'], '" class="cd_board">', CommunityDigestEngine::escape($item['board']), '</a>',
							!empty(CommunityDigestEngine::escape($item['badge'])) ? '<span class="cd_badge">' . CommunityDigestEngine::escape($item['badge']) . '</span>' : '', '
						</div>
						<h5 class="cd_subject"><a href="', $scripturl, '?action=digest;sa=open;topic=', $item['id_topic'], ';', $session, '">', CommunityDigestEngine::escape($item['subject']), '</a></h5>';

	if (!empty($item['excerpt']))
		echo '
						<p class="cd_excerpt">', $item['excerpt'], '</p>';

	if (!empty(CommunityDigestEngine::escape($item['reason'])))
		echo '
						<p class="cd_reason"><span class="main_icons valid"></span> ', CommunityDigestEngine::escape($item['reason']), '</p>';

	echo '
						<div class="cd_card_foot">
							<span class="cd_muted">', sprintf($txt['cd_card_meta'], CommunityDigestEngine::escape($item['starter']), comma_format($item['replies']), comma_format($item['views'])), '</span>';

	if (!$item['is_read'])
		echo '
							<a href="', $scripturl, '?action=digest;sa=read;topic=', $item['id_topic'], ';', $session, '" class="cd_markread" title="', $txt['cd_mark_read'], '">', $txt['cd_mark_read'], '</a>';
	else
		echo '
							<span class="cd_muted cd_readlabel">', $txt['cd_is_read'], '</span>';

	echo '
						</div>
					</article>';
}

/**
 * Profile > Digest preferences.
 */
function template_cd_profile()
{
	global $context, $txt;

	$cd = $context['cd'];
	$p = $cd['prefs'];

	echo '
	<form action="', $cd['post_url'], '" method="post" accept-charset="', $context['character_set'], '" id="cd_prefs">
		<div class="cat_bar">
			<h3 class="catbg profile_hd">', $txt['cd_profile_area'], '</h3>
		</div>
		<p class="information">', $txt['cd_profile_desc'], '</p>';

	if ($cd['saved'])
		echo '
		<div class="infobox">', $txt['cd_saved'], '</div>';

	echo '
		<div class="windowbg">
			<dl class="settings">
				<dt><strong>', $txt['cd_pref_frequency'], '</strong><br><span class="smalltext">', $txt['cd_pref_frequency_desc'], '</span></dt>
				<dd>
					<select name="frequency" id="cd_frequency">
						<option value="0"', $p['frequency'] == 0 ? ' selected' : '', '>', $txt['cd_freq_0'], '</option>';

	foreach ($cd['allowed'] as $f)
		echo '
						<option value="', $f, '"', $p['frequency'] == $f ? ' selected' : '', '>', $txt['cd_freq_' . $f], '</option>';

	echo '
					</select>
				</dd>
				<dt><strong>', $txt['cd_pref_delivery'], '</strong></dt>
				<dd>';

	foreach (array(0, 1, 2) as $d)
		echo '
					<label class="cd_block"><input type="radio" name="delivery" value="', $d, '"', $p['delivery'] == $d ? ' checked' : '', '> ', $txt['cd_delivery_' . $d], '</label>';

	echo '
				</dd>
				<dt class="cd_when"><strong>', $txt['cd_pref_day'], '</strong><br><span class="smalltext">', $txt['cd_pref_day_desc'], '</span></dt>
				<dd class="cd_when">
					<select name="weekly_day">';

	foreach ($txt['days'] as $i => $day)
		echo '
						<option value="', $i, '"', $p['weekly_day'] == $i ? ' selected' : '', '>', $day, '</option>';

	echo '
					</select>
				</dd>
				<dt class="cd_when"><strong>', $txt['cd_pref_hour'], '</strong><br><span class="smalltext">', $txt['cd_pref_hour_desc'], '</span></dt>
				<dd class="cd_when">
					<select name="send_hour">';

	for ($h = 0; $h < 24; $h++)
		echo '
						<option value="', $h, '"', $p['send_hour'] == $h ? ' selected' : '', '>', sprintf('%02d:00', $h), '</option>';

	echo '
					</select>
				</dd>';

	if ($cd['next'] !== '')
		echo '
				<dt><strong>', $txt['cd_pref_next'], '</strong></dt>
				<dd>', $cd['next'], '</dd>';

	echo '
			</dl>
		</div>

		<div class="title_bar"><h4 class="titlebg">', $txt['cd_pref_sections'], '</h4></div>
		<div class="windowbg">
			<p class="smalltext">', $txt['cd_pref_sections_desc'], '</p>
			<div class="cd_checkgrid">';

	foreach ($cd['sections'] as $s)
		echo '
				<label><input type="checkbox" name="sections[]" value="', $s, '"', empty($p['sections']) || in_array($s, $p['sections']) ? ' checked' : '', '> ', $txt['cd_section_' . $s], '</label>';

	echo '
			</div>
		</div>

		<div class="title_bar"><h4 class="titlebg">', $txt['cd_pref_interests'], '</h4></div>
		<div class="windowbg">
			<dl class="settings">
				<dt><strong>', $txt['cd_pref_keywords'], '</strong><br><span class="smalltext">', $txt['cd_pref_keywords_desc'], '</span></dt>
				<dd><input type="text" name="keywords" size="50" maxlength="400" value="', CommunityDigestEngine::escape(implode(', ', $p['keywords'])), '"></dd>
			</dl>
			<p><strong>', $txt['cd_pref_boards'], '</strong><br><span class="smalltext">', $txt['cd_pref_boards_desc'], '</span></p>';

	template_cd_board_checklist($cd['tree'], $p['boards'], 'boards');

	echo '
			<hr>
			<input type="submit" name="cd_save" value="', $txt['save'], '" class="button">
			<a href="', $cd['preview_url'], '" class="button" target="_blank" rel="noopener">', $txt['cd_preview_email'], '</a>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div>
	</form>
	<script>
		(function () {
			var f = document.getElementById("cd_frequency");
			function sync() {
				var v = f.value;
				document.querySelectorAll("#cd_prefs .cd_when").forEach(function (el, i) {
					// First pair is the weekday picker: weekly only. Second pair (hour) is hidden only when off.
					el.style.display = (i < 2 ? v === "2" : v !== "0") ? "" : "none";
				});
			}
			f.addEventListener("change", sync);
			sync();
		})();
	</script>';
}

/**
 * Category/board checklist, shared by profile and admin.
 */
function template_cd_board_checklist($tree, $selected, $name)
{
	global $txt;

	$selected = array_flip($selected);

	echo '
			<div class="cd_boardlist">';

	foreach ($tree as $cat)
	{
		echo '
				<fieldset>
					<legend>', CommunityDigestEngine::escape($cat['name']), '</legend>';

		foreach ($cat['boards'] as $b)
			echo '
					<label style="margin-', $GLOBALS['context']['right_to_left'] ? 'right' : 'left', ': ', $b['level'] * 1.5, 'em"><input type="checkbox" name="', $name, '[]" value="', $b['id'], '"', isset($selected[$b['id']]) ? ' checked' : '', '> ', CommunityDigestEngine::escape($b['name']), '</label>';

		echo '
				</fieldset>';
	}

	if (empty($tree))
		echo $txt['cd_no_boards'];

	echo '
			</div>';
}

/**
 * Admin > Community Digest > Content filters.
 */
function template_cd_admin_content()
{
	global $context, $txt;

	$cd = $context['cd'];

	if ($cd['saved'])
		echo '
	<div class="infobox">', $txt['settings_saved'], '</div>';

	echo '
	<form action="', $cd['post_url'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar"><h3 class="catbg">', $txt['cd_excluded_boards'], '</h3></div>
		<div class="windowbg">
			<p class="smalltext">', $txt['cd_excluded_boards_desc'], '</p>';

	template_cd_board_checklist($cd['tree'], $cd['excluded_boards'], 'boards');

	echo '
			<input type="submit" name="cd_save_boards" value="', $txt['save'], '" class="button">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div>
	</form>

	<form action="', $cd['post_url'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar"><h3 class="catbg">', $txt['cd_excluded_topics'], '</h3></div>
		<div class="windowbg">
			<p class="smalltext">', $txt['cd_excluded_topics_desc'], '</p>';

	if (empty($cd['excluded_topics']))
		echo '
			<p>', $txt['cd_no_excluded_topics'], '</p>';
	else
	{
		echo '
			<table class="table_grid">
				<thead><tr class="title_bar">
					<th class="centercol" style="width:4%"></th>
					<th>', $txt['cd_topic'], '</th>
					<th>', $txt['cd_excluded_by'], '</th>
					<th>', $txt['cd_log_time'], '</th>
				</tr></thead>
				<tbody>';

		foreach ($cd['excluded_topics'] as $t)
			echo '
					<tr class="windowbg">
						<td class="centercol"><input type="checkbox" name="remove[]" value="', $t['id'], '"></td>
						<td><a href="', $t['href'], '">', CommunityDigestEngine::escape($t['subject']), '</a></td>
						<td>', CommunityDigestEngine::escape($t['by']), '</td>
						<td>', $t['time'], '</td>
					</tr>';

		echo '
				</tbody>
			</table>
			<br>
			<input type="submit" name="cd_remove_topics" value="', $txt['cd_remove_selected'], '" class="button">';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div>
	</form>';
}

/**
 * Admin > Community Digest > Test delivery.
 */
function template_cd_admin_test()
{
	global $context, $txt;

	$cd = $context['cd'];
	$s = $cd['stats'];

	echo '
	<div class="cd_statgrid">
		<div class="windowbg"><strong>', comma_format($s['subscribers']), '</strong><span>', $txt['cd_stat_subscribers'], '</span></div>
		<div class="windowbg"><strong>', comma_format($s['email']), '</strong><span>', $txt['cd_stat_email'], '</span></div>
		<div class="windowbg"><strong>', comma_format($s['due']), '</strong><span>', $txt['cd_stat_due'], '</span></div>
		<div class="windowbg"><strong>', comma_format($s['today']), '</strong><span>', $txt['cd_stat_today'], '</span></div>
		<div class="windowbg', $s['failed'] ? ' cd_stat_bad' : '', '"><strong>', comma_format($s['failed']), '</strong><span>', $txt['cd_stat_failed'], '</span></div>
	</div>';

	if ($cd['result'] !== null)
		echo '
	<div class="', $cd['result']['ok'] ? 'infobox' : 'errorbox', '">', $cd['result']['text'], '</div>';

	echo '
	<form action="', $cd['post_url'], '" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar"><h3 class="catbg">', $txt['cd_admin_test'], '</h3></div>
		<div class="windowbg">
			<dl class="settings">
				<dt><strong>', $txt['cd_test_member'], '</strong><br><span class="smalltext">', $txt['cd_test_member_desc'], '</span></dt>
				<dd><input type="text" name="member" id="cd_test_member" value="', $cd['member'], '" size="30"></dd>
			</dl>
			<input type="submit" name="cd_test" value="', $txt['cd_test_send'], '" class="button">
			<input type="submit" name="cd_preview" value="', $txt['cd_preview_email'], '" class="button" formtarget="_blank">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</div>
	</form>';
}

/**
 * Generic message page (unsubscribe flow).
 */
function template_cd_message()
{
	global $context;

	$msg = $context['cd_message'];

	echo '
	<div class="cat_bar"><h3 class="catbg">', $msg['title'], '</h3></div>
	<div class="windowbg cd_message', $msg['error'] ? ' cd_message_error' : '', '">
		<p>', $msg['body'], '</p>';

	if (!empty($msg['form']))
	{
		echo '
		<form action="', $msg['form']['action'], '" method="post">';

		foreach ($msg['form']['fields'] as $k => $v)
			echo '
			<input type="hidden" name="', $k, '" value="', $v, '">';

		echo '
			<input type="submit" value="', $msg['form']['button'], '" class="button">
		</form>';
	}

	echo '
	</div>';
}

/**
 * Raw output (email preview).
 */
function template_cd_raw()
{
	global $context;

	echo $context['cd_raw_html'];
}
