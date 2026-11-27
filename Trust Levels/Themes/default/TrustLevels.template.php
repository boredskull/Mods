<?php

/**
 * Trust Levels: templates.
 *
 * @package TrustLevels
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.0
 */

function tl_h($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function tl_limit_text($value, $unit_key)
{
	global $txt;

	if ($value < 0)
		return $txt['tl_unlimited'];
	if ($value === 0)
		return $txt['tl_none'];

	return sprintf($txt[$unit_key], comma_format($value));
}

/* --------------------------------------------------------------------
 * Profile
 * ------------------------------------------------------------------ */

function template_tl_profile()
{
	global $context, $txt;

	$tl = $context['tl'];
	$levels = $tl['levels'];
	$current = $levels[$tl['level']];
	$s = $tl['stats'];

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_profile_label'], '</h3></div>
	<div class="windowbg tl_profile">
		<div class="tl_current">
			<div class="tl_current_badge">', TrustLevels::badge($tl['level']), '</div>
			<div>
				<div class="tl_current_name">', sprintf($txt['tl_level_n'], $tl['level']), '</div>', $current['description'] !== '' ? '
				<p class="tl_current_desc">' . tl_h($current['description']) . '</p>' : '', $tl['exempt'] ? '
				<p class="smalltext">' . $txt['tl_exempt_note'] . '</p>' : '', $tl['locked'] ? '
				<p class="smalltext">' . $txt['tl_locked_note'] . '</p>' : '', !empty($tl['probation_until']) && ($tl['own'] || $tl['can_manage']) ? '
				<p class="smalltext tl_probation_note">' . sprintf($txt['tl_probation_note'], timeformat($tl['probation_until'])) . '</p>' : '', !empty($tl['protected_groups']) ? '
				<p class="smalltext">' . sprintf($txt['tl_protected_note'], tl_h(implode(', ', $tl['protected_groups']))) . '</p>' : '', '
			</div>
		</div>

		<ol class="tl_ladder" aria-label="', $txt['tl_all_levels'], '">';
	foreach ($levels as $i => $l)
		echo '
			<li class="', $i < $tl['level'] ? 'tl_done' : ($i === $tl['level'] ? 'tl_here' : 'tl_todo'), '" style="--tl-color:', $l['color'], '"', $i === $tl['level'] ? ' aria-current="step"' : '', '>
				<span class="tl_step_dot"></span><span class="tl_step_name">', tl_h($l['name']), '</span>
			</li>';
	echo '
		</ol>
	</div>';

	if ($tl['show_progress'] && !$tl['exempt'])
	{
		echo '
	<div class="cat_bar"><h3 class="catbg">', $tl['next'] === null ? $txt['tl_top_reached_title'] : sprintf($txt['tl_progress_to'], tl_h($levels[$tl['next']]['name'])), '</h3></div>
	<div class="windowbg">';

		if ($tl['next'] === null)
			echo '
		<p>', $txt['tl_top_reached'], '</p>';
		elseif ($levels[$tl['next']]['manual'])
			echo '
		<p>', $txt['tl_next_manual'], '</p>';
		else
		{
			$req = $levels[$tl['next']]['req'];
			$met = 0;
			$shown = 0;

			echo '
		<ul class="tl_reqs">';
			foreach (TrustLevels::$req_keys as $key)
			{
				if ($req[$key] <= 0)
					continue;

				$shown++;
				$have = (int) $s[$key];
				$ok = $have >= $req[$key];
				$met += $ok ? 1 : 0;
				$pct = min(100, (int) floor($have / max(1, $req[$key]) * 100));

				echo '
			<li class="', $ok ? 'tl_met' : 'tl_unmet', '">
				<div class="tl_req_label"><span>', $txt['tl_req_' . $key], '</span><span>', comma_format($have), ' / ', comma_format($req[$key]), '</span></div>
				<div class="tl_bar" role="progressbar" aria-valuenow="', $pct, '" aria-valuemin="0" aria-valuemax="100" aria-label="', $txt['tl_req_' . $key], '"><div style="width:', $pct, '%"></div></div>
			</li>';
			}

			if ($req['max_warning'] >= 0)
			{
				$shown++;
				$ok = $s['warning'] <= $req['max_warning'];
				$met += $ok ? 1 : 0;
				echo '
			<li class="', $ok ? 'tl_met' : 'tl_unmet', '">
				<div class="tl_req_label"><span>', sprintf($txt['tl_req_warning'], $req['max_warning']), '</span><span>', $s['warning'], '%</span></div>
			</li>';
			}

			echo '
		</ul>
		<p class="smalltext">', $shown ? sprintf($txt['tl_reqs_met'], $met, $shown) : $txt['tl_no_reqs'], '</p>';
		}

		echo '
	</div>

	<div class="cat_bar"><h3 class="catbg">', $txt['tl_at_this_level'], '</h3></div>
	<div class="windowbg">
		<dl class="tl_limits">
			<dt>', $txt['tl_limit_links'], '</dt><dd>', tl_limit_text($current['limits']['links'], 'tl_n_per_post'), '</dd>
			<dt>', $txt['tl_limit_images'], '</dt><dd>', tl_limit_text($current['limits']['images'], 'tl_n_per_post'), '</dd>
			<dt>', $txt['tl_limit_topics_day'], '</dt><dd>', tl_limit_text($current['limits']['topics_day'], 'tl_n_per_day'), '</dd>
			<dt>', $txt['tl_limit_replies_day'], '</dt><dd>', tl_limit_text($current['limits']['replies_day'], 'tl_n_per_day'), '</dd>
			<dt>', $txt['tl_limit_sig_links'], '</dt><dd>', $current['limits']['sig_links'] ? $txt['tl_allowed'] : $txt['tl_not_allowed'], '</dd>
		</dl>
	</div>';
	}

	if ($tl['can_manage'])
	{
		echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_manage'], '</h3></div>
	<form class="windowbg" action="', $tl['url'], '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<dl class="settings">
			<dt><label for="tl_level">', $txt['tl_level'], '</label></dt>
			<dd><select id="tl_level" name="tl_level">';
		foreach ($levels as $i => $l)
			echo '<option value="', $i, '"', $i === $tl['level'] ? ' selected' : '', '>', $i, ': ', tl_h($l['name']), $l['manual'] ? ' (' . $txt['tl_manual_short'] . ')' : '', '</option>';
		echo '</select></dd>
			<dt><label for="tl_lock">', $txt['tl_lock'], '</label><br><span class="smalltext">', $txt['tl_lock_desc'], '</span></dt>
			<dd><input type="checkbox" id="tl_lock" name="tl_lock" value="1"', $tl['locked'] ? ' checked' : '', '></dd>
			<dt><label for="tl_reason">', $txt['tl_reason'], '</label></dt>
			<dd><input type="text" id="tl_reason" name="tl_reason" maxlength="200" size="40"></dd>
		</dl>
		<input type="submit" name="tl_save" value="', $txt['tl_save_level'], '" class="button">
	</form>';
	}

	if (!empty($tl['log']))
	{
		echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_history'], '</h3></div>
	<table class="table_grid">
		<thead><tr class="title_bar"><th>', $txt['tl_col_when'], '</th><th>', $txt['tl_col_change'], '</th><th>', $txt['tl_col_reason'], '</th></tr></thead>
		<tbody>';
		foreach ($tl['log'] as $entry)
			echo '
			<tr class="windowbg">
				<td>', $entry['time'], '</td>
				<td>', template_tl_change($entry), '</td>
				<td>', template_tl_reason($entry['reason'], $entry['changer'], $entry['action']), '</td>
			</tr>';
		echo '
		</tbody>
	</table>';
	}
}

/**
 * The "change" column of a history entry.
 */
function template_tl_change(array $e)
{
	global $txt;

	$action = isset($e['action']) ? $e['action'] : 'level';

	if ($action === 'lock')
		return '<span class="tl_event tl_event_lock">&#128274; ' . $txt['tl_event_lock'] . '</span> ' . TrustLevels::badge($e['new'], true);
	if ($action === 'unlock')
		return '<span class="tl_event tl_event_unlock">&#128275; ' . $txt['tl_event_unlock'] . '</span> ' . TrustLevels::badge($e['new'], true);
	if ($action === 'held')
		return '<span class="tl_event tl_event_held">' . $txt['tl_event_held'] . '</span> ' . TrustLevels::badge($e['old'], true) . ' &rarr; ' . TrustLevels::badge($e['new'], true);

	return ($e['old'] >= 0 ? TrustLevels::badge($e['old'], true) : '–') . ' &rarr; ' . TrustLevels::badge($e['new'], true);
}

function template_tl_reason($reason, $changer, $action = 'level')
{
	global $txt;

	if (strpos((string) $reason, 'probation:') === 0)
		$text = sprintf($txt['tl_reason_probation'], timeformat((int) substr($reason, 10)));
	elseif (in_array($action, array('lock', 'unlock'), true) && in_array($reason, array('', 'manual'), true))
		$text = '';
	elseif ($reason === 'auto')
		$text = $txt['tl_reason_auto'];
	elseif ($reason === 'initial')
		$text = $txt['tl_reason_initial'];
	elseif ($reason === 'manual')
		$text = $txt['tl_reason_manual'];
	else
		$text = tl_h($reason);

	if ($text === '')
		return $changer !== '' ? sprintf($txt['tl_by'], $changer) : '';

	return $text . ($changer !== '' ? ' <span class="smalltext">(' . sprintf($txt['tl_by'], $changer) . ')</span>' : '');
}

/* --------------------------------------------------------------------
 * Admin
 * ------------------------------------------------------------------ */

function template_tl_notices()
{
	global $context, $txt;

	if (!empty($context['tl_notice']))
		echo '
	<div class="infobox">', $context['tl_notice'], '</div>';

	if (!empty($context['tl_errors']))
	{
		echo '
	<div class="errorbox"><ul class="tl_list">';
		foreach ($context['tl_errors'] as $e)
			echo '<li>', $e, '</li>';
		echo '</ul></div>';
	}
}

function template_tl_admin_levels()
{
	global $context, $txt;

	template_tl_notices();

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_tab_levels'], '</h3></div>
	<p class="information">', $txt['tl_levels_intro'], '</p>
	<form id="tl_levels_form" action="', $context['tl_url'], ';sa=levels" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<div id="tl_levels">';

	foreach ($context['tl_levels'] as $i => $l)
		template_tl_level_card($i, $l);

	echo '
		</div>
		<template id="tl_level_template">';
	template_tl_level_card('__i__', TrustLevels::normaliseLevel(array('name' => '', 'color' => '#5a7fa8')));
	echo '
		</template>
		<div class="windowbg">
			<button type="button" class="button" id="tl_add_level">', $txt['tl_add_level'], '</button>
			<input type="submit" name="save" value="', $txt['tl_save_levels'], '" class="button">
			<p class="smalltext">', $txt['tl_levels_after_save'], '</p>
		</div>
	</form>';
}

function template_tl_level_card($i, array $l)
{
	global $context, $txt;

	$n = 'level[' . $i . ']';
	$first = $i === 0;

	echo '
			<fieldset class="windowbg tl_level_card"', $first ? ' data-first="1"' : '', '>
				<legend class="tl_card_head">
					<span class="tl_card_title">', $txt['tl_level'], ' <span class="tl_card_num">', is_int($i) ? $i : '', '</span></span>
					<span class="tl_card_tools">
						<button type="button" class="button tl_up" title="', $txt['tl_move_up'], '">&#9650;</button>
						<button type="button" class="button tl_down" title="', $txt['tl_move_down'], '">&#9660;</button>
						<button type="button" class="button tl_remove">', $txt['tl_remove'], '</button>
					</span>
				</legend>

				<div class="tl_grid">
					<label>', $txt['tl_field_name'], '<input type="text" name="', $n, '[name]" value="', tl_h($l['name']), '" maxlength="40" required></label>
					<label>', $txt['tl_field_color'], '<input type="color" name="', $n, '[color]" value="', tl_h($l['color']), '"></label>
					<label>', $txt['tl_field_group'], '<select name="', $n, '[group]"><option value="0">', $txt['tl_no_group'], '</option>';
	foreach ($context['tl_groups'] as $id => $name)
		echo '<option value="', $id, '"', (int) $l['group'] === (int) $id ? ' selected' : '', '>', $name, '</option>';
	echo '</select></label>
					<label class="tl_check tl_not_first"><input type="checkbox" name="', $n, '[manual]" value="1"', $l['manual'] ? ' checked' : '', '> ', $txt['tl_field_manual'], '</label>
					<label class="tl_wide">', $txt['tl_field_description'], '<input type="text" name="', $n, '[description]" value="', tl_h($l['description']), '" maxlength="255"></label>
				</div>

				<div class="tl_cols">
					<div class="tl_not_first">
						<h4>', $txt['tl_requirements'], '</h4>
						<div class="tl_grid tl_grid_small">';
	foreach (TrustLevels::$req_keys as $key)
		echo '
							<label>', $txt['tl_req_' . $key], '<input type="number" min="0" name="', $n, '[req][', $key, ']" value="', (int) $l['req'][$key], '"></label>';
	echo '
							<label>', $txt['tl_field_max_warning'], '<input type="number" min="0" max="100" name="', $n, '[req][max_warning]" value="', $l['req']['max_warning'] >= 0 ? (int) $l['req']['max_warning'] : '', '" placeholder="', $txt['tl_any'], '"></label>
						</div>
					</div>
					<div class="tl_first_only smalltext"><h4>', $txt['tl_requirements'], '</h4>', $txt['tl_first_level_note'], '</div>

					<div>
						<h4>', $txt['tl_limits'], '</h4>
						<div class="tl_grid tl_grid_small">';
	foreach (TrustLevels::$limit_keys as $key)
		echo '
							<label>', $txt['tl_limit_' . $key], '<input type="number" min="0" name="', $n, '[limits][', $key, ']" value="', $l['limits'][$key] >= 0 ? (int) $l['limits'][$key] : '', '" placeholder="', $txt['tl_unlimited'], '"></label>';
	echo '
							<label class="tl_check"><input type="checkbox" name="', $n, '[limits][sig_links]" value="1"', $l['limits']['sig_links'] ? ' checked' : '', '> ', $txt['tl_limit_sig_links'], '</label>
						</div>
						<p class="smalltext">', $txt['tl_limits_hint'], '</p>
					</div>
				</div>

				<details class="tl_not_first">
					<summary>', $txt['tl_promotion_message'], '</summary>
					<div class="tl_grid">
						<label class="tl_wide">', $txt['tl_pm_subject'], '<input type="text" name="', $n, '[pm_subject]" value="', tl_h($l['pm_subject']), '" maxlength="100"></label>
						<label class="tl_wide">', $txt['tl_pm_body'], '<textarea name="', $n, '[pm_body]" rows="4">', tl_h($l['pm_body']), '</textarea></label>
					</div>
					<p class="smalltext">', $txt['tl_pm_placeholders'], '</p>
				</details>
			</fieldset>';
}

function template_tl_admin_members()
{
	global $context, $txt;

	template_tl_notices();

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_tab_members'], '</h3></div>
	<div class="windowbg">
		<div class="tl_summary">
			<a href="', $context['tl_url'], ';sa=members" class="tl_summary_item', $context['tl_filter'] === null && !$context['tl_locked_only'] ? ' tl_active' : '', '"><strong>', comma_format($context['tl_total']), '</strong><span>', $txt['tl_all_members'], '</span></a>';
	foreach (TrustLevels::levels() as $i => $l)
		echo '
			<a href="', $context['tl_url'], ';sa=members;level=', $i, '" class="tl_summary_item', $context['tl_filter'] === $i ? ' tl_active' : '', '" style="--tl-color:', $l['color'], '"><strong>', comma_format($context['tl_counts'][$i]), '</strong><span>', tl_h($l['name']), '</span></a>';
	echo '
			<a href="', $context['tl_url'], ';sa=members;locked=1" class="tl_summary_item', $context['tl_locked_only'] ? ' tl_active' : '', '"><strong>&#128274;</strong><span>', $txt['tl_locked_only'], '</span></a>
		</div>';

	if ($context['tl_uncalculated'] > 0)
		echo '
		<p class="smalltext">', sprintf($txt['tl_uncalculated'], comma_format($context['tl_uncalculated']), $context['tl_url'] . ';sa=recalc'), '</p>';

	echo '
	</div>';

	if (empty($context['tl_members']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['tl_no_members'], '</p></div>';
		return;
	}

	echo '
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th>', $txt['tl_col_member'], '</th>
				<th>', $txt['tl_level'], '</th>
				<th>', $txt['tl_col_posts'], '</th>
				<th>', $txt['tl_col_registered'], '</th>
				<th>', $txt['tl_col_last_login'], '</th>
				<th>', $txt['tl_col_updated'], '</th>
			</tr>
		</thead>
		<tbody>';
	foreach ($context['tl_members'] as $m)
		echo '
			<tr class="windowbg">
				<td><a href="', $m['href'], '">', $m['name'], '</a></td>
				<td>', TrustLevels::badge($m['level'], true), $m['locked'] ? ' <span title="' . $txt['tl_locked_note'] . '">&#128274;</span>' : '', '</td>
				<td>', $m['posts'], '</td>
				<td>', $m['registered'], '</td>
				<td>', $m['last_login'], '</td>
				<td>', $m['updated'], '</td>
			</tr>';
	echo '
		</tbody>
	</table>
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>';
}

function template_tl_admin_log()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_tab_log'], '</h3></div>';

	if (empty($context['tl_log']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['tl_no_log'], '</p></div>';
		return;
	}

	echo '
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>
	<table class="table_grid">
		<thead><tr class="title_bar"><th>', $txt['tl_col_when'], '</th><th>', $txt['tl_col_member'], '</th><th>', $txt['tl_col_change'], '</th><th>', $txt['tl_col_reason'], '</th></tr></thead>
		<tbody>';
	foreach ($context['tl_log'] as $e)
		echo '
			<tr class="windowbg">
				<td>', $e['time'], '</td>
				<td>', $e['member'], '</td>
				<td>', template_tl_change($e), '</td>
				<td>', template_tl_reason($e['reason'], $e['changer'], $e['action']), '</td>
			</tr>';
	echo '
		</tbody>
	</table>
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>';
}

function template_tl_admin_recalc()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['tl_tab_recalc'], '</h3></div>
	<div class="windowbg">';

	$p = $context['tl_progress'];

	if ($p !== null)
	{
		echo '
		<div class="tl_bar tl_bar_big" role="progressbar" aria-valuenow="', $p['percent'], '" aria-valuemin="0" aria-valuemax="100"><div style="width:', $p['percent'], '%"></div></div>
		<p>', sprintf($txt['tl_recalc_progress'], comma_format($p['done']), comma_format($p['total']), comma_format($p['changed'])), '</p>';

		if ($p['next'] !== '')
			echo '
		<p><a class="button" id="tl_continue" href="', $p['next'], '">', $txt['tl_continue'], '</a></p>
		<script>setTimeout(function () { window.location.href = document.getElementById("tl_continue").href; }, 600);</script>';
		else
			echo '
		<div class="infobox">', $txt['tl_recalc_done'], '</div>';
	}

	echo '
		<p>', $txt['tl_recalc_intro'], '</p>
		<a class="button" href="', $context['tl_url'], ';sa=recalc;run', $context['tl_session'], '">', $txt['tl_recalc_run'], '</a>
		<a class="button" href="', $context['tl_url'], ';sa=recalc;run;sync=1', $context['tl_session'], '">', $txt['tl_recalc_sync'], '</a>
		<p class="smalltext">', $txt['tl_recalc_sync_desc'], '</p>
	</div>';
}
