<?php

/**
 * Questions & Answers templates.
 *
 * @package QuestionsAnswers
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

/**
 * Vote box shown at the side of each answer (returned, not echoed).
 */
function template_qa_votebox(array $v)
{
	global $txt;

	$score_title = sprintf($txt['qa_score_title'], $v['up'], $v['down']);
	$out = '
		<div class="qa_votebox" data-msg="' . $v['id'] . '" data-url="' . $v['vote_url'] . '">';

	if ($v['can_vote'])
		$out .= '
			<a href="' . $v['vote_url'] . ';v=up" class="qa_vote qa_up' . ($v['mine'] > 0 ? ' qa_voted' : '') . '" data-v="up" title="' . $txt['qa_vote_up'] . '" aria-label="' . $txt['qa_vote_up'] . '" aria-pressed="' . ($v['mine'] > 0 ? 'true' : 'false') . '" rel="nofollow"></a>';

	$out .= '
			<span class="qa_score' . ($v['score'] > 0 ? ' qa_pos' : ($v['score'] < 0 ? ' qa_neg' : '')) . '" title="' . $score_title . '">' . $v['score'] . '</span>';

	if ($v['can_vote'] && $v['downvotes'])
		$out .= '
			<a href="' . $v['vote_url'] . ';v=down" class="qa_vote qa_down' . ($v['mine'] < 0 ? ' qa_voted' : '') . '" data-v="down" title="' . $txt['qa_vote_down'] . '" aria-label="' . $txt['qa_vote_down'] . '" aria-pressed="' . ($v['mine'] < 0 ? 'true' : 'false') . '" rel="nofollow"></a>';

	if ($v['can_accept'])
		$out .= '
			<a href="' . $v['accept_url'] . '" class="qa_accept' . ($v['accepted'] ? ' qa_is_accepted' : '') . '" title="' . ($v['accepted'] ? $txt['qa_unaccept'] : $txt['qa_accept']) . '" aria-label="' . ($v['accepted'] ? $txt['qa_unaccept'] : $txt['qa_accept']) . '"></a>';
	elseif ($v['accepted'])
		$out .= '
			<span class="qa_accept qa_is_accepted" title="' . $txt['qa_accepted_answer'] . '"></span>';

	$out .= '
		</div>';

	if ($v['accepted'])
		$out .= '
		<div class="qa_accepted_label">' . $txt['qa_accepted_answer'] . '</div>';

	return $out;
}

/**
 * Accepted answer summary under the question (returned, not echoed).
 */
function template_qa_solution(array $s)
{
	global $txt;

	return '
		<div class="qa_solution">
			<div class="qa_solution_head">
				<strong>' . $txt['qa_solution'] . '</strong>
				<span class="smalltext">' . sprintf($txt['qa_solution_by'], $s['author_link']) . ' · ' . sprintf($txt['qa_n_score'], $s['score']) . '</span>
			</div>
			<div class="qa_solution_text">' . $s['text'] . '</div>
			<a href="' . $s['url'] . '" class="qa_solution_link">' . $txt['qa_go_to_answer'] . '</a>
		</div>';
}

/* ----------------------------------------------------------------------
 * Topic layer
 * -------------------------------------------------------------------- */

function template_qa_topic_above()
{
	global $txt;

	// Display() has finished: show the page in score order if needed.
	QA::hookMessageOrder();

	$b = QA::topicBar();

	if (!$b['is_question'])
	{
		if ($b['can_convert'])
			echo '
	<div class="qa_bar qa_bar_plain">
		<span>', $txt['qa_convert_desc'], '</span>
		<a href="', $b['convert_url'], '" class="button">', $txt['qa_convert'], '</a>
	</div>';
		return;
	}

	echo '
	<div class="qa_bar">
		<span class="qa_badge ', $b['solved'] ? 'qa_badge_solved' : 'qa_badge_open', '">', $b['solved'] ? $txt['qa_solved'] : $txt['qa_unsolved'], '</span>
		<span class="qa_bar_count">', sprintf($b['answers'] === 1 ? $txt['qa_one_answer'] : $txt['qa_n_answers'], $b['answers']), '</span>
		<span class="qa_sort">', $txt['qa_sort_by'], '
			<a href="', $b['sort_url'], 'votes"', $b['sort'] === 'votes' ? ' class="active" aria-current="true"' : '', '>', $txt['qa_sort_votes'], '</a>
			<a href="', $b['sort_url'], 'date"', $b['sort'] === 'date' ? ' class="active" aria-current="true"' : '', '>', $txt['qa_sort_date'], '</a>
		</span>';

	if ($b['can_manage'])
	{
		echo '
		<span class="qa_bar_actions">';
		if (!$b['solved'])
			echo '
			<a href="', $b['solve_url'], '" class="button" title="', $txt['qa_mark_solved_desc'], '">', $txt['qa_mark_solved'], '</a>';
		else
			echo '
			<a href="', $b['unsolve_url'], '" class="button" data-qa-confirm="', $txt['qa_mark_unsolved_confirm'], '">', $txt['qa_mark_unsolved'], '</a>';
		if ($b['can_convert'])
			echo '
			<a href="', $b['unconvert_url'], '" class="button" data-qa-confirm="', $txt['qa_unconvert_confirm'], '">', $txt['qa_unconvert'], '</a>';
		echo '
		</span>';
	}

	echo '
	</div>';

	if ($b['can_manage'] && !$b['solved'])
		echo '
	<p class="qa_hint smalltext">', $txt['qa_accept_hint'], '</p>';
}

function template_qa_topic_below()
{
}

/* ----------------------------------------------------------------------
 * Board layer
 * -------------------------------------------------------------------- */

function template_qa_index_above()
{
	QA::decorateTopics();
}

function template_qa_index_below()
{
}

/* ----------------------------------------------------------------------
 * Post page layer
 * -------------------------------------------------------------------- */

function template_qa_post_above()
{
	global $context, $txt;

	if (!empty($context['qa_post_notice']))
	{
		echo '
	<div class="noticebox qa_post_notice">', $txt['qa_post_question_only'], '</div>';
		return;
	}

	// If the theme doesn't draw the option from posting_fields, add it here.
	echo '
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			if (document.getElementById("qa_question"))
				return;
			var header = document.getElementById("post_header"), form = document.forms.postmodify;
			if (!header && !form)
				return;
			var wrap = document.createElement("div");
			wrap.className = "qa_post_option";
			wrap.innerHTML = ', JavaScriptEscape('<label><input type="checkbox" name="qa_question" id="qa_question" value="1"' . (!empty($context['qa_post_checked']) ? ' checked' : '') . '> <strong>' . $txt['qa_post_label'] . '</strong> <span class="smalltext">' . $txt['qa_post_desc'] . '</span></label>'), ';
			if (header)
				header.parentNode.insertBefore(wrap, header.nextSibling);
			else
				form.insertBefore(wrap, form.firstChild);
		});
	</script>';
}

function template_qa_post_below()
{
}

/* ----------------------------------------------------------------------
 * Questions page
 * -------------------------------------------------------------------- */

function template_qa_list()
{
	global $context, $txt;

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['qa_title_questions'], '</h3>
	</div>';

	if (empty($context['qa_boards']))
	{
		echo '
	<div class="windowbg">', $txt['qa_no_boards'], '</div>';
		return;
	}

	echo '
	<div class="qa_page">
		<div class="qa_main">
			<div class="qa_toolbar">
				<div class="qa_tabs" role="tablist">';
	foreach ($context['qa_tabs'] as $tab)
		echo '
					<a href="', $context['qa_base'], ';tab=', $tab, ';sort=', $context['qa_sort'], '" class="qa_tab', $context['qa_tab'] === $tab ? ' active' : '', '" role="tab"', $context['qa_tab'] === $tab ? ' aria-selected="true"' : '', '>', $txt['qa_tab_' . $tab], '</a>';
	echo '
				</div>
				<form action="', $context['qa_base'], '" method="get" class="qa_filters">
					<input type="hidden" name="action" value="questions">
					<input type="hidden" name="tab" value="', $context['qa_tab'], '">
					<select name="board" aria-label="', $txt['qa_filter_board'], '" onchange="this.form.submit();">
						<option value="0">', $txt['qa_all_boards'], '</option>';
	foreach ($context['qa_boards'] as $id => $name)
		echo '
						<option value="', $id, '"', $context['qa_board'] === $id ? ' selected' : '', '>', QA::escape($name), '</option>';
	echo '
					</select>
					<select name="sort" aria-label="', $txt['qa_sort_by'], '" onchange="this.form.submit();">';
	foreach ($context['qa_sorts'] as $s)
		echo '
						<option value="', $s, '"', $context['qa_sort'] === $s ? ' selected' : '', '>', $txt['qa_list_sort_' . $s], '</option>';
	echo '
					</select>
					<noscript><input type="submit" value="', $txt['go'], '" class="button"></noscript>
				</form>
			</div>';

	if (empty($context['qa_questions']))
		echo '
			<div class="windowbg qa_empty">', $txt['qa_none_' . $context['qa_tab']], '</div>';

	foreach ($context['qa_questions'] as $q)
	{
		echo '
			<div class="windowbg qa_item', $q['solved'] ? ' qa_item_solved' : '', '">
				<div class="qa_stat', $q['answers'] ? ' qa_has' : '', $q['solved'] ? ' qa_stat_solved' : '', '">
					<strong>', $q['answers'], '</strong>
					<span>', $q['answers'] === 1 ? $txt['qa_answer_word'] : $txt['qa_answers_word'], '</span>
				</div>
				<div class="qa_item_main">
					<a href="', $q['url'], '" class="qa_item_title">', $q['subject'], '</a>
					<div class="qa_item_meta">
						', $q['solved'] ? '<span class="qa_badge qa_badge_solved">' . $txt['qa_solved'] . '</span> ' : '', sprintf($txt['qa_asked_by'], $q['starter'], $q['time']), ' · <a href="', $q['board_url'], '">', $q['board'], '</a>
					</div>';
		if ($q['last'] !== '' || $q['has_answer'])
		{
			echo '
					<div class="qa_item_meta">', $q['last'];
			if ($q['has_answer'])
				echo ($q['last'] !== '' ? ' · ' : ''), '<a href="', $q['answer_url'], '">', $txt['qa_view_solution'], '</a> (', sprintf($txt['qa_n_score'], $q['answer_score']), ')';
			echo '</div>';
		}
		echo '
				</div>
				<div class="qa_views smalltext">', sprintf($txt['qa_n_views'], $q['views']), '</div>
			</div>';
	}

	if (!empty($context['page_index']))
		echo '
			<div class="pagesection">
				<div class="pagelinks">', $context['page_index'], '</div>
			</div>';

	echo '
		</div>
		<aside class="qa_side">
			<div class="windowbg">
				<h4>', $txt['qa_leaders'], '</h4>';
	if (empty($context['qa_leaders']))
		echo '
				<p class="smalltext">', $txt['qa_no_leaders'], '</p>';
	else
	{
		echo '
				<ol class="qa_leaders">';
		foreach ($context['qa_leaders'] as $l)
			echo '
					<li>', $l['link'], ' <span class="qa_rep_pill">', comma_format($l['reputation']), '</span></li>';
		echo '
				</ol>';
	}
	echo '
			</div>
		</aside>
	</div>';
}

/* ----------------------------------------------------------------------
 * Profile
 * -------------------------------------------------------------------- */

function template_qa_answer_list(array $list, $empty)
{
	global $txt;

	if (empty($list))
	{
		echo '
		<p class="smalltext">', $empty, '</p>';
		return;
	}

	echo '
		<ul class="qa_answer_list">';
	foreach ($list as $a)
		echo '
			<li>
				<span class="qa_score_pill', $a['score'] > 0 ? ' qa_pos' : ($a['score'] < 0 ? ' qa_neg' : ''), '">', $a['score'], '</span>
				', $a['accepted'] ? '<span class="qa_tick" title="' . $txt['qa_accepted_answer'] . '"></span>' : '', '
				<a href="', $a['url'], '">', $a['subject'], '</a>
				<span class="smalltext">', $a['time'], '</span>
			</li>';
	echo '
		</ul>';
}

function template_qa_profile()
{
	global $context, $txt;

	$p = $context['qa_profile'];
	$s = $p['stats'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['qa_profile_area'], '</h3>
	</div>
	<div class="qa_profile_stats">
		<div class="windowbg qa_big"><strong>', comma_format($s['reputation']), '</strong><span>', $txt['qa_reputation'], $s['rank'] ? ' · #' . $s['rank'] : '', '</span></div>
		<div class="windowbg"><strong>', $s['accepted'], '</strong><span>', $txt['qa_stat_accepted'], '</span></div>
		<div class="windowbg"><strong>', $s['up'], '</strong><span>', $txt['qa_stat_up'], '</span></div>
		<div class="windowbg"><strong>', $s['down'], '</strong><span>', $txt['qa_stat_down'], '</span></div>
		<div class="windowbg"><strong>', $s['asked'], '</strong><span>', sprintf($txt['qa_stat_asked'], $s['asked_solved']), '</span></div>
	</div>
	<p class="smalltext qa_points_explain">', sprintf($txt['qa_points_explain'], $p['points']['up'], $p['points']['down'], $p['points']['accepted']), '</p>
	<div class="qa_profile_cols">
		<div class="windowbg">
			<h4>', $txt['qa_top_answers'], '</h4>';
	template_qa_answer_list($p['top'], $txt['qa_no_answers_yet']);
	echo '
		</div>
		<div class="windowbg">
			<h4>', $txt['qa_recent_accepted'], '</h4>';
	template_qa_answer_list($p['accepted'], $txt['qa_no_accepted_yet']);
	echo '
		</div>
	</div>';
}

/* ----------------------------------------------------------------------
 * Admin
 * -------------------------------------------------------------------- */

function template_qa_admin_boards()
{
	global $context, $txt, $scripturl;

	if ($context['qa_notice'] !== '')
		echo '
	<div class="infobox">', $context['qa_notice'], '</div>';

	echo '
	<form action="', $scripturl, '?action=admin;area=questionsanswers;sa=boards" method="post" accept-charset="UTF-8">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['qa_admin_boards'], '</h3>
		</div>
		<div class="information">', $txt['qa_boards_desc'], '</div>
		<table class="table_grid qa_board_table">
			<thead>
				<tr class="title_bar">
					<th>', $txt['qa_board'], '</th>
					<th class="centertext">', $txt['qa_mode_0'], '</th>
					<th class="centertext">', $txt['qa_mode_1'], '</th>
					<th class="centertext">', $txt['qa_mode_2'], '</th>
				</tr>
			</thead>
			<tbody>';

	$cat = null;
	foreach ($context['qa_all_boards'] as $id => $b)
	{
		if ($b['id_cat'] !== $cat)
		{
			$cat = $b['id_cat'];
			echo '
				<tr class="windowbg qa_cat_row"><td colspan="4"><strong>', QA::escape($b['cat_name']), '</strong></td></tr>';
		}
		$mode = isset($context['qa_modes'][$id]) ? $context['qa_modes'][$id] : 0;
		echo '
				<tr class="windowbg">
					<td style="padding-left: ', 12 + 18 * (int) $b['child_level'], 'px">', QA::escape($b['name']), '</td>';
		for ($m = 0; $m <= 2; $m++)
			echo '
					<td class="centertext"><input type="radio" name="mode[', $id, ']" value="', $m, '"', $mode === $m ? ' checked' : '', ' aria-label="', QA::escape($b['name']), ': ', $txt['qa_mode_' . $m], '"></td>';
		echo '
				</tr>';
	}

	echo '
			</tbody>
		</table>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<div class="righttext padding"><input type="submit" name="save" value="', $txt['save'], '" class="button"></div>
	</form>';
}

function template_qa_admin_maintain()
{
	global $context, $txt, $scripturl;

	if ($context['qa_done'])
		echo '
	<div class="infobox">', $txt['qa_recount_done'], '</div>';

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['qa_admin_maintain'], '</h3>
	</div>
	<div class="windowbg">
		<p>', $txt['qa_recount_desc'], '</p>
		<a href="', $scripturl, '?action=admin;area=questionsanswers;sa=maintain;recount;', $context['session_var'], '=', $context['session_id'], '" class="button">', $txt['qa_recount'], '</a>
	</div>';
}


/** Administration Centre: inspect reputation and make recorded adjustments. */
function template_qa_admin_reputation()
{
    global $context, $txt;

    $url = QA::escape($context['qa_rep_post_url']);
    $token_var = QA::escape($context['qa_reputation_token_var']);
    $token = QA::escape($context['qa_reputation_token']);
    $session_var = QA::escape($context['session_var']);
    $session_id = QA::escape($context['session_id']);
    $hidden = '<input type="hidden" name="' . $session_var . '" value="' . $session_id . '"><input type="hidden" name="' . $token_var . '" value="' . $token . '">';

    echo '
<div class="cat_bar"><h3 class="catbg">', $txt['qa_admin_reputation'], '</h3></div>',
         '<div class="windowbg"><p>', $txt['qa_adjust_intro'], '</p>',
         '<form action="', $url, '" method="post">', $hidden,
         '<label for="qa_rep_lookup">', $txt['qa_adjust_find'], '</label> ',
         '<input type="text" id="qa_rep_lookup" name="member" value="', QA::escape($context['qa_rep_input']), '" maxlength="100" required> ',
         '<button type="submit" name="lookup" value="1" class="button">', $txt['qa_adjust_lookup'], '</button></form></div>';

    if (!empty($context['qa_rep_message']))
        echo '<div class="infobox">', QA::escape($context['qa_rep_message']), '</div>';
    if (empty($context['qa_rep_member']))
        return;

    $m = $context['qa_rep_member'];
    echo '<div class="cat_bar"><h3 class="catbg">', QA::escape($m['name']), ' (#', (int) $m['id'], ')</h3></div>',
         '<div class="windowbg"><p>', $txt['qa_adjust_earned'], ': <strong>', comma_format($m['earned']), '</strong> &nbsp; ',
         $txt['qa_adjust_net'], ': <strong>', comma_format($m['adjustments']), '</strong> &nbsp; ',
         $txt['qa_adjust_total'], ': <strong>', comma_format($m['total']), '</strong></p>',
         '<p class="smalltext">', $txt['qa_stat_up'], ': ', $m['up'], ' · ', $txt['qa_stat_down'], ': ', $m['down'], ' · ', $txt['qa_stat_accepted'], ': ', $m['accepted'], '</p>';
    if ($m['needs_recount'])
        echo '<div class="errorbox">', $txt['qa_adjust_recount_warning'], '</div>';
    echo '<form action="', $url, '" method="post" style="margin-top:0.75em">', $hidden,
         '<input type="hidden" name="member" value="', (int) $m['id'], '">',
         '<button type="submit" class="button" name="recalculate_member" value="1">', $txt['qa_adjust_recalculate'], '</button></form></div>';

    echo '<div class="cat_bar"><h3 class="catbg">', $txt['qa_adjust_add'], '</h3></div>',
         '<div class="windowbg"><form action="', $url, '" method="post">', $hidden,
         '<input type="hidden" name="member" value="', (int) $m['id'], '">',
         '<p><label for="qa_adjust_dir">', $txt['qa_adjust_type'], '</label> ',
         '<select name="direction" id="qa_adjust_dir"><option value="deduct">', $txt['qa_adjust_deduct'], '</option><option value="award">', $txt['qa_adjust_award'], '</option></select> ',
         '<label for="qa_adjust_points">', $txt['qa_adjust_points'], '</label> ',
         '<input type="number" id="qa_adjust_points" name="points" min="1" max="100000" step="1" required></p>',
         '<p><label for="qa_adjust_reason">', $txt['qa_adjust_reason'], '</label><br>',
         '<textarea name="reason" id="qa_adjust_reason" rows="3" style="width:100%" maxlength="1000" required></textarea></p>',
         '<button class="button" type="submit" name="add_adjustment" value="1">', $txt['qa_adjust_apply'], '</button>',
         '</form></div>';

    echo '<div class="cat_bar"><h3 class="catbg">', $txt['qa_adjust_history'], '</h3></div>',
         '<div class="windowbg">';
    if (empty($context['qa_rep_history']))
        echo '<p>', $txt['qa_adjust_no_history'], '</p>';
    else
    {
        echo '<p class="smalltext">', $txt['qa_adjust_recent'], '</p>',
             '<div class="table_container"><table class="table_grid"><thead><tr class="title_bar">',
             '<th>ID</th><th>', $txt['qa_adjust_time'], '</th><th>', $txt['qa_adjust_admin'], '</th>',
             '<th>', $txt['qa_adjust_points'], '</th><th>', $txt['qa_adjust_reason'], '</th>',
             '<th>', $txt['qa_adjust_status'], '</th></tr></thead><tbody>';
        foreach ($context['qa_rep_history'] as $entry)
        {
            $is_reversal = (int) $entry['reversal_of'] > 0;
            $reversed = (int) $entry['reversed_by'] > 0;
            echo '<tr class="windowbg"><td>#', (int) $entry['id_adjustment'], '</td>',
                 '<td>', timeformat((int) $entry['created']), '</td>',
                 '<td>', QA::escape($entry['admin_name']), '</td>',
                 '<td>', (int) $entry['points'] > 0 ? '+' : '', (int) $entry['points'], '</td>',
                 '<td>', nl2br(QA::escape($entry['reason'])), '</td><td>';
            if ($is_reversal)
                echo sprintf($txt['qa_adjust_reversal_of'], (int) $entry['reversal_of']);
            elseif ($reversed)
                echo sprintf($txt['qa_adjust_reversed_by'], (int) $entry['reversed_by']);
            else
            {
                echo '<form action="', $url, '" method="post">', $hidden,
                     '<input type="hidden" name="member" value="', (int) $m['id'], '">',
                     '<input type="hidden" name="adjustment" value="', (int) $entry['id_adjustment'], '">',
                     '<label>', $txt['qa_adjust_reversal_reason'], ' <input type="text" name="reason" maxlength="1000" required></label> ',
                     '<button type="submit" class="button" name="reverse_adjustment" value="1">', $txt['qa_adjust_reverse'], '</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}
