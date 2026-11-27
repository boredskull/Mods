<?php

/**
 * Ranked Polls: templates.
 *
 * @package RankedPolls
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

function rp_h($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/* --------------------------------------------------------------------
 * Topic: ballot and results (template layer)
 * ------------------------------------------------------------------ */

function template_ranked_poll_above()
{
	global $context, $txt;

	$v = $context['ranked_poll'];

	echo '
	<div id="rp_block" class="rp_block">
		<div class="cat_bar">
			<h3 class="catbg"><span class="main_icons poll"></span> ', $txt['rp_ranked_poll'], '</h3>
		</div>
		<div class="windowbg">
			<h4 class="rp_question">', $v['question'], '</h4>
			<ul class="rp_meta">
				<li class="rp_tag">', $txt['rp_tag_ranked'], '</li>', $v['anonymous'] ? '
				<li class="rp_tag">' . $txt['rp_tag_anonymous'] . '</li>' : '', '
				<li>', sprintf($txt['rp_n_ballots'], comma_format($v['ballots'])), '</li>';

	if ($v['is_locked'])
		echo '
				<li>', $txt['rp_locked'], '</li>';
	elseif ($v['is_expired'])
		echo '
				<li>', sprintf($txt['rp_ended'], $v['expire_time']), '</li>';
	elseif ($v['expire_time'] !== '')
		echo '
				<li>', sprintf($txt['rp_ends'], $v['expire_time']), '</li>';

	echo '
			</ul>';

	// Ballot
	if ($v['can_vote'] && !$v['has_voted'])
		template_rp_ballot($v);
	elseif ($v['has_voted'])
	{
		echo '
			<div class="rp_voted">
				<strong>', $txt['rp_you_voted'], '</strong>';

		if ($v['anonymous'])
			echo '
				<p class="smalltext">', $txt['rp_anonymous_recorded'], '</p>';
		elseif (!empty($v['my_ranking']))
		{
			echo '
				<ol class="rp_my_ranking">';
			foreach ($v['my_ranking'] as $id)
				echo '
					<li>', $v['choices'][$id], '</li>';
			echo '
				</ol>';
		}

		echo '
			</div>';

		if ($v['changing'])
		{
			echo '
			<details class="rp_change">
				<summary>', $txt['rp_change_vote'], '</summary>';
			template_rp_ballot($v);
			echo '
			</details>';
		}
	}
	elseif ($v['is_guest'] && !$v['is_locked'] && !$v['is_expired'])
		echo '
			<p class="rp_note">', $txt['rp_login_to_vote'], '</p>';

	// Results
	if ($v['can_see_results'])
		template_rp_results($v);
	else
		echo '
			<p class="rp_note">', $v['hide_results'] === 2 ? $txt['rp_results_after_end'] : $txt['rp_results_after_vote'], '</p>';

	// Buttons
	if (!empty($v['buttons']))
	{
		echo '
			<div class="rp_buttons">';
		foreach ($v['buttons'] as $key => $url)
			echo '
				<a class="button" href="', $url, '"', $key === 'remove' ? ' data-rp-confirm="' . rp_h($txt['rp_confirm_remove']) . '"' : '', '>', $txt['rp_button_' . $key], '</a>';
		echo '
			</div>';
	}

	echo '
		</div>
	</div>';
}

function template_ranked_poll_below()
{
}

function template_rp_ballot(array $v)
{
	global $context, $txt;

	static $count = 0;
	$count++;

	$choices = array();
	foreach ($v['choices'] as $id => $html)
		$choices[] = array('id' => (int) $id, 'html' => $html);

	$data = array(
		'choices' => $choices,
		'ranking' => $v['my_ranking'],
		'min' => (int) $v['min_ranks'],
		'txt' => array(
			'yours' => $txt['rp_your_ranking'],
			'options' => $txt['rp_options'],
			'empty' => $txt['rp_ranking_empty'],
			'all_ranked' => $txt['rp_all_ranked'],
			'up' => $txt['rp_move_up'],
			'down' => $txt['rp_move_down'],
			'remove' => $txt['rp_remove'],
			'add' => $txt['rp_add'],
			'clear' => $txt['rp_clear'],
			'moved' => $txt['rp_announce_moved'],
			'added' => $txt['rp_announce_added'],
			'removed' => $txt['rp_announce_removed'],
			'too_few' => sprintf($txt['rp_error_too_few'], (int) $v['min_ranks']),
			'help' => $txt['rp_ballot_help'],
		),
	);

	echo '
			<form class="rp_ballot_form" action="', $v['vote_url'], '" method="post" accept-charset="UTF-8" data-rp-ballot="', rp_h(json_encode($data)), '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="rp_poll" value="', (int) $v['id'], '">
				<div class="rp_fallback">
					<p class="smalltext">', sprintf($txt['rp_fallback_help'], (int) $v['min_ranks']), '</p>
					<table class="rp_fallback_table">';

	$n = count($v['choices']);
	foreach ($v['choices'] as $id => $html)
	{
		$pos = array_search($id, $v['my_ranking'], true);
		$field = 'rp_rank_' . $count . '_' . (int) $id;

		echo '
						<tr>
							<td><select id="', $field, '" name="rank[', (int) $id, ']">
								<option value="0">', $txt['rp_not_ranked'], '</option>';
		for ($i = 1; $i <= $n; $i++)
			echo '<option value="', $i, '"', $pos !== false && $pos + 1 === $i ? ' selected' : '', '>', $i, '</option>';
		echo '</select></td>
							<td><label for="', $field, '">', $html, '</label></td>
						</tr>';
	}

	echo '
					</table>
				</div>
				<div class="rp_ballot_error" role="alert" hidden></div>
				<input type="submit" value="', $v['has_voted'] ? $txt['rp_submit_change'] : $txt['rp_submit'], '" class="button">
			</form>';
}

function template_rp_results(array $v)
{
	global $txt;

	$res = $v['result'];

	echo '
			<div class="rp_results">
				<h4 class="rp_results_title">', $txt['rp_results'], '</h4>';

	if (empty($res['ballots']))
	{
		echo '
				<p class="rp_note">', $txt['rp_no_ballots'], '</p>
			</div>';
		return;
	}

	$rounds = $res['rounds'];
	$last = end($rounds);

	if ($res['winner'] !== null)
		echo '
				<div class="rp_winner">
					<span class="rp_winner_label">', $v['is_expired'] || $v['is_locked'] ? $txt['rp_winner'] : $txt['rp_leading'], '</span>
					<span class="rp_winner_name">', $v['choices'][$res['winner']], '</span>
					<span class="smalltext">', sprintf(count($rounds) === 1 ? $txt['rp_after_one_round'] : $txt['rp_after_rounds'], count($rounds)), $last['tie'] !== null ? ' · ' . $txt['rp_tie_' . $last['tie']] : '', '</span>
				</div>';

	// When each option was eliminated.
	$out = array();
	foreach ($rounds as $r => $round)
		foreach ($round['eliminated'] as $id)
			$out[$id] = $r;

	echo '
				<div class="rp_rounds" data-rp-rounds data-label="', rp_h($txt['rp_round_n']), '" data-prev="', rp_h($txt['rp_prev_round']), '" data-next="', rp_h($txt['rp_next_round']), '">';

	foreach ($rounds as $r => $round)
	{
		$counts = $round['counts'];
		arsort($counts);
		$majority = (int) floor($round['continuing'] / 2) + 1;

		echo '
					<section class="rp_round" data-round="', $r + 1, '" aria-label="', rp_h(sprintf($txt['rp_round_n'], $r + 1)), '">
						<h5 class="rp_round_title">', sprintf($txt['rp_round_n'], $r + 1), '</h5>
						<ul class="rp_bars">';

		foreach ($counts as $id => $votes)
		{
			$pct = $round['continuing'] > 0 ? $votes / $round['continuing'] * 100 : 0;
			$class = $round['winner'] === $id ? 'rp_is_winner' : (in_array($id, $round['eliminated'], true) ? 'rp_is_eliminated' : '');

			echo '
							<li class="', $class, '">
								<div class="rp_bar_label"><span>', $v['choices'][$id], '</span><span class="rp_bar_value">', comma_format($votes), ' · ', number_format($pct, 1), '%</span></div>
								<div class="rp_track"><div class="rp_fill" style="width:', round(min(100, $pct), 2), '%"></div></div>
							</li>';
		}

		// Options already out of the running.
		foreach ($out as $id => $when)
			if ($when < $r)
				echo '
							<li class="rp_is_out">
								<div class="rp_bar_label"><span>', $v['choices'][$id], '</span><span class="rp_bar_value">', sprintf($txt['rp_out_in_round'], $when + 1), '</span></div>
							</li>';

		echo '
						</ul>
						<p class="rp_round_stats smalltext">', sprintf($txt['rp_round_stats'], comma_format($round['continuing']), comma_format($majority), comma_format($round['exhausted'])), '</p>';

		if (!empty($round['eliminated']))
		{
			echo '
						<div class="rp_round_outcome">';
			foreach ($round['eliminated'] as $id)
			{
				$moves = array();
				foreach ($round['transfers'][$id] as $to => $n)
					$moves[] = ($to === 'exhausted' ? $txt['rp_exhausted'] : $v['choices'][$to]) . ' +' . comma_format($n);

				echo '
							<p><strong>', sprintf($txt['rp_eliminated'], $v['choices'][$id]), '</strong>', $round['tie'] !== null && count($round['eliminated']) === 1 ? ' <span class="smalltext">(' . $txt['rp_tie_' . $round['tie']] . ')</span>' : '', empty($moves) ? '' : '<br><span class="smalltext">' . $txt['rp_transfers'] . ' ' . implode(', ', $moves) . '</span>', '</p>';
			}
			echo '
						</div>';
		}
		elseif ($round['winner'] !== null)
			echo '
						<p class="rp_round_outcome"><strong>', sprintf($round['tie'] !== null ? $txt['rp_wins_tie'] : $txt['rp_wins_majority'], $v['choices'][$round['winner']]), '</strong></p>';

		echo '
					</section>';
	}

	echo '
				</div>

				<details class="rp_summary">
					<summary>', $txt['rp_ballot_summary'], '</summary>
					<table class="table_grid">
						<thead><tr class="title_bar"><th>', $txt['rp_csv_option'], '</th><th>', $txt['rp_first_preferences'], '</th><th>', $txt['rp_times_ranked'], '</th><th>', $txt['rp_borda'], '</th></tr></thead>
						<tbody>';
	foreach ($v['choices'] as $id => $html)
		echo '
							<tr class="windowbg"><td>', $html, '</td><td>', comma_format($res['first'][$id]), '</td><td>', comma_format($res['mentions'][$id]), '</td><td>', comma_format($res['borda'][$id]), '</td></tr>';
	echo '
						</tbody>
					</table>
					<p class="smalltext">', $txt['rp_how_counted'], '</p>
				</details>
			</div>';
}

/* --------------------------------------------------------------------
 * Admin
 * ------------------------------------------------------------------ */

function template_rp_admin_settings()
{
	global $context, $txt;

	$s = $context['rp_settings'];

	if (!empty($context['rp_notice']))
		echo '
	<div class="infobox">', $context['rp_notice'], '</div>';

	if ($context['rp_poll_mode_off'])
		echo '
	<div class="errorbox">', $txt['rp_polls_disabled'], '</div>';

	echo '
	<form action="', $context['rp_url'], ';sa=settings" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<div class="cat_bar"><h3 class="catbg">', $txt['rp_tab_settings'], '</h3></div>
		<div class="windowbg">
			<dl class="settings">
				<dt><label for="rp_enabled">', $txt['rp_enabled'], '</label></dt>
				<dd><input type="checkbox" id="rp_enabled" name="rp_enabled" value="1"', $s['enabled'] ? ' checked' : '', '></dd>

				<dt><label for="rp_anonymous">', $txt['rp_anonymous'], '</label><br><span class="smalltext">', $txt['rp_anonymous_desc'], '</span></dt>
				<dd><select id="rp_anonymous" name="rp_anonymous">';
	foreach (array('never', 'optional', 'always') as $mode)
		echo '<option value="', $mode, '"', $s['anonymous'] === $mode ? ' selected' : '', '>', $txt['rp_anonymous_' . $mode], '</option>';
	echo '</select></dd>

				<dt><label for="rp_min_ranks">', $txt['rp_min_ranks'], '</label><br><span class="smalltext">', $txt['rp_min_ranks_desc'], '</span></dt>
				<dd><input type="number" id="rp_min_ranks" name="rp_min_ranks" min="1" max="50" value="', (int) $s['min_ranks'], '"></dd>

				<dt><label for="rp_alerts">', $txt['rp_alerts'], '</label><br><span class="smalltext">', $txt['rp_alerts_desc'], '</span></dt>
				<dd><input type="checkbox" id="rp_alerts" name="rp_alerts" value="1"', $s['alerts'] ? ' checked' : '', '></dd>

				<dt><label for="rp_owner_export">', $txt['rp_owner_export'], '</label><br><span class="smalltext">', $txt['rp_owner_export_desc'], '</span></dt>
				<dd><input type="checkbox" id="rp_owner_export" name="rp_owner_export" value="1"', $s['owner_export'] ? ' checked' : '', '></dd>

				<dt>', $txt['rp_boards'], '<br><span class="smalltext">', $txt['rp_boards_desc'], '</span></dt>
				<dd><div class="rp_board_list">';
	$cat = null;
	foreach ($context['rp_boards'] as $id => $b)
	{
		if ($b['cat_name'] !== $cat)
		{
			$cat = $b['cat_name'];
			echo '
					<strong class="rp_board_cat">', $cat, '</strong>';
		}
		echo '
					<label style="margin-left:', 1 + (int) $b['child_level'] * 1.25, 'em"><input type="checkbox" name="rp_boards[]" value="', $id, '"', in_array((int) $id, $s['boards'], true) ? ' checked' : '', '> ', $b['name'], '</label>';
	}
	echo '
				</div></dd>
			</dl>
			<p class="smalltext">', $txt['rp_permission_note'], '</p>
			<input type="submit" name="save" value="', $txt['rp_save'], '" class="button">
		</div>
	</form>';
}

function template_rp_admin_polls()
{
	global $context, $txt;

	if (!empty($context['rp_notice']))
		echo '
	<div class="infobox">', $context['rp_notice'], '</div>';

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['rp_tab_polls'], '</h3></div>';

	if (empty($context['rp_polls']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['rp_no_polls'], '</p></div>';
		return;
	}

	echo '
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th>', $txt['rp_col_topic'], '</th>
				<th>', $txt['rp_col_board'], '</th>
				<th>', $txt['rp_col_ballots'], '</th>
				<th>', $txt['rp_col_status'], '</th>
				<th>', $txt['rp_col_created'], '</th>
				<th class="righttext">', $txt['rp_col_export'], '</th>
			</tr>
		</thead>
		<tbody>';
	foreach ($context['rp_polls'] as $p)
		echo '
			<tr class="windowbg">
				<td>', $p['topic'], '<br><span class="smalltext">', $p['question'], '</span></td>
				<td>', $p['board'], '</td>
				<td>', $p['ballots'], $p['anonymous'] ? ' <span class="rp_tag">' . $txt['rp_tag_anonymous'] . '</span>' : '', '</td>
				<td>', $txt['rp_status_' . $p['status']], '</td>
				<td>', $p['created'], '</td>
				<td class="righttext"><a class="button" href="', $p['rounds_url'], '">', $txt['rp_button_export_rounds'], '</a> <a class="button" href="', $p['ballots_url'], '">', $txt['rp_button_export_ballots'], '</a></td>
			</tr>';
	echo '
		</tbody>
	</table>
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>
	<div class="windowbg"><a class="button" href="', $context['rp_prune_url'], '">', $txt['rp_prune'], '</a> <span class="smalltext">', $txt['rp_prune_desc'], '</span></div>';
}
