<?php

/**
 * Event Manager templates.
 *
 * @package EventManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

function template_em_notice()
{
	global $context;

	if (empty($context['em_notice']))
		return;

	list ($type, $text) = $context['em_notice'];
	echo '
	<div class="', $type === 'error' ? 'errorbox' : 'infobox', '">', $text, '</div>';
}

/**
 * Status badge.
 */
function em_badge($status)
{
	global $txt;

	return '<span class="em_badge em_badge_' . $status . '">' . $txt['em_status_' . $status] . '</span>';
}

/**
 * Seats meter.
 */
function em_meter(array $event, array $counts)
{
	global $txt;

	if ($event['capacity'] <= 0)
		return '<div class="em_meter_text">' . sprintf($txt['em_seats_taken_nolimit'], $counts['seats']) . '</div>';

	$pct = min(100, (int) round($counts['seats'] / $event['capacity'] * 100));
	$full = $counts['seats'] >= $event['capacity'];

	return '
		<div class="em_meter' . ($full ? ' em_full' : '') . '" role="progressbar" aria-valuemin="0" aria-valuemax="' . $event['capacity'] . '" aria-valuenow="' . $counts['seats'] . '">
			<span style="width: ' . $pct . '%"></span>
		</div>
		<div class="em_meter_text">' . sprintf($txt['em_seats_taken'], $counts['seats'], $event['capacity'])
		. ($counts['waitlist'] ? ' · ' . sprintf($txt['em_n_waiting'], $counts['waitlist']) : '') . '</div>';
}

/**
 * The RSVP box, used on the event page and in topics.
 */
function template_em_rsvp_box(array $p, $from_topic = false)
{
	global $txt;

	$e = $p['event'];
	$mine = $p['mine'];
	$status = $mine ? $mine['status'] : '';
	$extra = $from_topic ? ';from_topic=1' : '';

	echo '
	<div class="em_rsvp" data-event="', $e['id'], '">';

	// Where the member stands.
	if ($status !== '')
	{
		echo '
		<p class="em_you">', $txt['em_you_are'], ' ', em_badge($status);
		if ($status === 'waitlist' && $p['position'])
			echo ' <span class="em_position">', sprintf($txt['em_waitlist_position'], $p['position']), '</span>';
		if ($status === 'going' && $mine['guests'])
			echo ' <span class="em_guests">', sprintf($txt['em_plus_guests'], $mine['guests']), '</span>';
		echo '</p>';
	}

	if ($p['open'] !== true)
	{
		echo '
		<p class="em_closed">', $txt[$p['open']], '</p>';
	}
	elseif (!$p['can_register'])
	{
		echo '
		<p class="em_closed">', $txt['em_login_to_register'], '</p>';
	}
	else
	{
		$full = $p['seats_left'] !== null && $p['seats_left'] <= 0;
		$going_label = in_array($status, array('going', 'waitlist', 'pending'), true) ? $txt['em_update'] : ($e['approval'] ? $txt['em_request'] : ($full && $e['waitlist'] ? $txt['em_join_waitlist'] : $txt['em_going']));

		echo '
		<form action="', $p['rsvp_url'], $p['session'], $extra, '" method="post" accept-charset="UTF-8" class="em_rsvp_form">';

		if ($e['max_guests'] > 0)
		{
			echo '
			<label class="em_field">', $txt['em_bring_guests'], '
				<select name="guests">';
			for ($i = 0; $i <= $e['max_guests']; $i++)
				echo '
					<option value="', $i, '"', $mine && (int) $mine['guests'] === $i ? ' selected' : '', '>', $i, '</option>';
			echo '
				</select>
			</label>';
		}

		if ($e['question'] !== '')
			echo '
			<label class="em_field em_question">', $e['question'], $e['question_required'] ? ' <span class="em_req">*</span>' : '', '
				<input type="text" name="note" maxlength="250" value="', $mine ? $mine['note'] : '', '"', $e['question_required'] ? ' data-required="1"' : '', '>
			</label>';

		echo '
			<div class="em_buttons">
				<button type="submit" name="answer" value="going" class="button em_btn_going', $status === 'going' ? ' active' : '', '"', $full && !$e['waitlist'] && $status !== 'going' ? ' disabled' : '', '>', $going_label, '</button>';
		if ($e['allow_maybe'])
			echo '
				<button type="submit" name="answer" value="maybe" class="button em_btn_maybe', $status === 'maybe' ? ' active' : '', '">', $txt['em_maybe'], '</button>';
		echo '
				<button type="submit" name="answer" value="no" class="button em_btn_no', $status === 'no' ? ' active' : '', '">', $txt['em_not_going'], '</button>';
		if ($status !== '' && ($status !== 'going' || $p['can_cancel']))
			echo '
				<button type="submit" name="answer" value="withdraw" class="button em_btn_withdraw" data-confirm="', $txt['em_withdraw_confirm'], '">', $txt['em_withdraw'], '</button>';
		echo '
			</div>';

		if ($status === 'going' && !$p['can_cancel'])
			echo '
			<p class="smalltext em_note">', $txt['em_reason_cancel_deadline'], '</p>';
		elseif ($e['cancel_hours'] > 0)
			echo '
			<p class="smalltext em_note">', sprintf($txt['em_cancel_by'], $e['cancel_hours']), '</p>';

		if ($e['closes'] > 0)
			echo '
			<p class="smalltext em_note">', sprintf($txt['em_closes_at'], timeformat($e['closes'])), '</p>';

		echo '
		</form>';
	}

	echo '
	</div>';
}

/* ----------------------------------------------------------------------
 * List
 * -------------------------------------------------------------------- */

function template_em_list()
{
	global $context, $txt, $scripturl;

	template_em_notice();

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['em_title'], '</h3>
	</div>
	<div class="em_toolbar">
		<div class="em_tabs" role="tablist">';
	foreach ($context['em_tabs'] as $tab)
		echo '
			<a href="', $context['em_url'], ';tab=', $tab, '" class="em_tab', $context['em_tab'] === $tab ? ' active' : '', '" role="tab"', $context['em_tab'] === $tab ? ' aria-selected="true"' : '', '>', $txt['em_tab_' . $tab], '</a>';
	echo '
		</div>
		<div class="em_actions">';
	if ($context['em_can_post'])
		echo '
			<a href="', $scripturl, '?action=calendar;sa=post" class="button">', $txt['em_new_event'], '</a>';
	echo '
			<a href="', $scripturl, '?action=calendar" class="button">', $txt['em_open_calendar'], '</a>
		</div>
	</div>';

	if (empty($context['em_events']))
	{
		echo '
	<div class="windowbg em_empty">', $txt['em_no_events_' . $context['em_tab']], '</div>';
	}
	else
	{
		$month = '';
		foreach ($context['em_events'] as $item)
		{
			$e = $item['event'];
			if ($item['month'] !== $month)
			{
				$month = $item['month'];
				echo '
	<h4 class="em_month">', $month, '</h4>';
			}

			$d = (new DateTime('@' . $e['start_ts']))->setTimezone($e['tz']);
			echo '
	<div class="windowbg em_item', $e['cancelled'] ? ' em_is_cancelled' : '', '">
		<div class="em_date">
			<span class="em_day">', $d->format('j'), '</span>
			<span class="em_mon">', isset($txt['months_short'][(int) $d->format('n')]) ? $txt['months_short'][(int) $d->format('n')] : $d->format('M'), '</span>
		</div>
		<div class="em_info">
			<a href="', $item['url'], '" class="em_item_title">', $e['title'], '</a>
			<div class="em_meta">', $item['when'], $e['location'] !== '' ? ' · ' . $e['location'] : '', '</div>
			<div class="em_meta">';
			if ($e['enabled'])
			{
				echo $e['capacity'] > 0 ? sprintf($txt['em_seats_taken'], $item['counts']['seats'], $e['capacity']) : sprintf($txt['em_seats_taken_nolimit'], $item['counts']['seats']);
				if ($item['counts']['waitlist'])
					echo ' · ', sprintf($txt['em_n_waiting'], $item['counts']['waitlist']);
			}
			else
				echo $txt['em_no_registration'];
			echo '</div>
		</div>
		<div class="em_mine">', $item['my_status'] !== '' ? em_badge($item['my_status']) : '', '</div>
	</div>';
		}

		echo '
	<div class="pagesection">
		<div class="pagelinks">', $context['page_index'], '</div>
	</div>';
	}

	if (!empty($context['em_feed_url']))
		echo '
	<div class="windowbg em_feed">
		<strong>', $txt['em_feed_title'], '</strong>
		<p class="smalltext">', $txt['em_feed_desc'], '</p>
		<input type="text" readonly value="', $context['em_feed_url'], '" class="em_feed_url" onclick="this.select();">
		<a href="', $context['em_url'], ';sa=token', $context['em_session'], '" class="smalltext" data-confirm="', $txt['em_feed_reset_confirm'], '">', $txt['em_feed_reset_link'], '</a>
	</div>';
}

/* ----------------------------------------------------------------------
 * Event page
 * -------------------------------------------------------------------- */

function template_em_attendee_list($title, array $list, $show_notes)
{
	global $txt;

	if (empty($list))
		return;

	echo '
		<h4 class="em_list_title">', $title, ' <span class="em_count">', count($list), '</span></h4>
		<ul class="em_people">';
	foreach ($list as $person)
	{
		echo '
			<li>', $person['avatar'] !== '' ? '<img src="' . $person['avatar'] . '" alt="" class="em_avatar" loading="lazy">' : '<span class="em_avatar em_avatar_blank"></span>', '
				<span class="em_person">', $person['link'];
		if ($person['guests'])
			echo ' <span class="em_guests">', sprintf($txt['em_plus_guests'], $person['guests']), '</span>';
		if ($show_notes && $person['note'] !== '')
			echo '<span class="em_person_note">', $person['note'], '</span>';
		echo '</span>
			</li>';
	}
	echo '
		</ul>';
}

function template_em_view()
{
	global $context, $txt;

	$e = $context['em_event'];
	$p = $context['em_panel'];

	template_em_notice();

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $e['title'], '</h3>
	</div>
	<div class="em_view">
		<div class="windowbg em_details">';

	if ($e['cancelled'])
		echo '
			<div class="errorbox">', $txt['em_this_cancelled'], '</div>';

	echo '
			<dl class="em_facts">
				<dt>', $txt['em_when'], '</dt><dd>', $p['when'], '</dd>';
	if ($e['location'] !== '')
		echo '
				<dt>', $txt['em_where'], '</dt><dd>', $e['location'], '</dd>';
	if ($e['organiser'] !== '')
		echo '
				<dt>', $txt['em_organiser'], '</dt><dd>', $e['organiser'], '</dd>';
	echo '
			</dl>
			<div class="em_links">
				<a href="', $context['em_ics_url'], '" class="button">', $txt['em_add_to_calendar'], '</a>';
	if ($context['em_topic_url'] !== '')
		echo '
				<a href="', $context['em_topic_url'], '" class="button">', $txt['em_discussion'], '</a>';
	echo '
				<a href="', $context['em_calendar_url'], '" class="button">', $txt['em_open_calendar'], '</a>';
	if ($context['em_can_manage'])
		echo '
				<a href="', $context['em_url'], ';sa=manage;event=', $e['id'], '" class="button em_manage_btn">', $txt['em_manage'], '</a>';
	echo '
			</div>
		</div>';

	if ($e['enabled'])
	{
		echo '
		<div class="windowbg em_register">
			<h4>', $txt['em_registration'], '</h4>', em_meter($e, $p['counts']);
		template_em_rsvp_box($p);
		echo '
		</div>';
	}

	echo '
	</div>';

	if (!$e['enabled'])
		return;

	echo '
	<div class="windowbg em_attendees">';
	if (!$context['em_show_attendees'])
		echo '
		<p class="em_hidden">', $txt['em_list_hidden_' . $e['visibility']], '</p>';
	else
	{
		$a = $context['em_attendees'];
		$notes = $context['em_can_manage'];
		template_em_attendee_list($txt['em_list_going'], $a['going'], $notes);
		template_em_attendee_list($txt['em_list_maybe'], $a['maybe'], $notes);
		template_em_attendee_list($txt['em_list_waitlist'], $a['waitlist'], $notes);
		if ($context['em_can_manage'])
			template_em_attendee_list($txt['em_list_pending'], $a['pending'], $notes);
		if (empty($a['going']) && empty($a['maybe']) && empty($a['waitlist']))
			echo '
		<p class="em_hidden">', $txt['em_nobody_yet'], '</p>';
	}
	echo '
	</div>';
}

/* ----------------------------------------------------------------------
 * Manage
 * -------------------------------------------------------------------- */

function template_em_manage()
{
	global $context, $txt;

	$e = $context['em_event'];
	$c = $context['em_counts'];
	$local = $context['em_local'];
	$base = $context['em_url'] . ';event=' . $e['id'];
	$reminders = array_map('intval', array_filter(explode(',', $e['reminders'])));

	template_em_notice();

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', sprintf($txt['em_manage_title'], $e['title']), '</h3>
	</div>
	<div class="em_stats">
		<div class="windowbg"><strong>', $c['seats'], $e['capacity'] > 0 ? ' / ' . $e['capacity'] : '', '</strong><span>', $txt['em_stat_seats'], '</span></div>
		<div class="windowbg"><strong>', $c['going'], '</strong><span>', $txt['em_status_going'], '</span></div>
		<div class="windowbg"><strong>', $c['maybe'], '</strong><span>', $txt['em_status_maybe'], '</span></div>
		<div class="windowbg"><strong>', $c['waitlist'], '</strong><span>', $txt['em_status_waitlist'], '</span></div>
		<div class="windowbg"><strong>', $c['pending'], '</strong><span>', $txt['em_status_pending'], '</span></div>
		<div class="windowbg"><strong>', $c['checked_in'], '</strong><span>', $txt['em_stat_checked_in'], '</span></div>
	</div>

	<form action="', $base, ';sa=manage" method="post" accept-charset="UTF-8" class="windowbg em_settings">
		<h4>', $txt['em_settings'], '</h4>
		<dl class="settings">
			<dt><label for="em_enabled">', $txt['em_f_enabled'], '</label></dt>
			<dd><input type="checkbox" name="enabled" id="em_enabled" value="1"', $e['enabled'] ? ' checked' : '', '></dd>
			<dt><label for="em_capacity">', $txt['em_f_capacity'], '</label><br><span class="smalltext">', $txt['em_f_capacity_desc'], '</span></dt>
			<dd><input type="number" name="capacity" id="em_capacity" min="0" max="100000" value="', $e['capacity'], '"></dd>
			<dt><label for="em_max_guests">', $txt['em_f_max_guests'], '</label></dt>
			<dd><input type="number" name="max_guests" id="em_max_guests" min="0" max="10" value="', $e['max_guests'], '"></dd>
			<dt><label for="em_waitlist">', $txt['em_f_waitlist'], '</label><br><span class="smalltext">', $txt['em_f_waitlist_desc'], '</span></dt>
			<dd><input type="checkbox" name="waitlist" id="em_waitlist" value="1"', $e['waitlist'] ? ' checked' : '', '></dd>
			<dt><label for="em_waitlist_max">', $txt['em_f_waitlist_max'], '</label><br><span class="smalltext">', $txt['em_zero_unlimited'], '</span></dt>
			<dd><input type="number" name="waitlist_max" id="em_waitlist_max" min="0" value="', $e['waitlist_max'], '"></dd>
			<dt><label for="em_approval">', $txt['em_f_approval'], '</label><br><span class="smalltext">', $txt['em_f_approval_desc'], '</span></dt>
			<dd><input type="checkbox" name="approval" id="em_approval" value="1"', $e['approval'] ? ' checked' : '', '></dd>
			<dt><label for="em_allow_maybe">', $txt['em_f_allow_maybe'], '</label></dt>
			<dd><input type="checkbox" name="allow_maybe" id="em_allow_maybe" value="1"', $e['allow_maybe'] ? ' checked' : '', '></dd>
			<dt><label for="em_question">', $txt['em_f_question'], '</label><br><span class="smalltext">', $txt['em_f_question_desc'], '</span></dt>
			<dd><input type="text" name="question" id="em_question" maxlength="200" size="40" value="', $e['question'], '">
				<label><input type="checkbox" name="question_required" value="1"', $e['question_required'] ? ' checked' : '', '> ', $txt['em_f_required'], '</label></dd>
			<dt><label for="em_visibility">', $txt['em_f_visibility'], '</label></dt>
			<dd><select name="visibility" id="em_visibility">';
	foreach (EM::$visibility as $v)
		echo '
				<option value="', $v, '"', $e['visibility'] === $v ? ' selected' : '', '>', $txt['em_visibility_' . $v], '</option>';
	echo '
			</select></dd>
			<dt><label for="em_opens">', $txt['em_f_opens'], '</label><br><span class="smalltext">', sprintf($txt['em_f_times_desc'], $e['tz']->getName()), '</span></dt>
			<dd><input type="datetime-local" name="opens" id="em_opens" value="', $local($e['opens']), '"></dd>
			<dt><label for="em_closes">', $txt['em_f_closes'], '</label><br><span class="smalltext">', $txt['em_f_closes_desc'], '</span></dt>
			<dd><input type="datetime-local" name="closes" id="em_closes" value="', $local($e['closes']), '"></dd>
			<dt><label for="em_cancel_hours">', $txt['em_f_cancel_hours'], '</label><br><span class="smalltext">', $txt['em_f_cancel_hours_desc'], '</span></dt>
			<dd><input type="number" name="cancel_hours" id="em_cancel_hours" min="0" max="720" value="', $e['cancel_hours'], '"></dd>
			<dt>', $txt['em_f_reminders'], '<br><span class="smalltext">', $txt['em_f_reminders_desc'], '</span></dt>
			<dd class="em_reminder_opts">';
	foreach (EM::$reminder_options as $r)
		echo '
				<label><input type="checkbox" name="reminders[]" value="', $r, '"', in_array($r, $reminders, true) ? ' checked' : '', '> ', $txt['em_reminder_' . $r], '</label>';
	echo '
			</dd>
		</dl>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<div class="righttext"><input type="submit" name="save" value="', $txt['save'], '" class="button"></div>
	</form>';

	// Attendees.
	echo '
	<div class="windowbg em_manage_people">
		<div class="em_people_head">
			<h4>', $txt['em_attendees'], '</h4>
			<div>
				<input type="search" class="em_filter" placeholder="', $txt['em_filter'], '" aria-label="', $txt['em_filter'], '">
				<a href="', $base, ';sa=export', $context['em_session'], '" class="button">', $txt['em_export'], '</a>
				<a href="', $context['em_view_url'], '" class="button">', $txt['em_view_event'], '</a>
			</div>
		</div>
		<table class="table_grid em_table">
			<thead>
				<tr class="title_bar">
					<th>', $txt['em_col_member'], '</th>
					<th>', $txt['em_col_status'], '</th>
					<th>', $txt['em_col_guests'], '</th>
					<th>', $e['question'] !== '' ? $e['question'] : $txt['em_col_note'], '</th>
					<th>', $txt['em_col_registered'], '</th>
					<th>', $txt['em_col_checked_in'], '</th>
					<th></th>
				</tr>
			</thead>
			<tbody>';

	$any = false;
	foreach (array('pending', 'going', 'waitlist', 'maybe', 'no') as $status)
		foreach ($context['em_attendees'][$status] as $person)
		{
			$any = true;
			$act = $base . ';sa=attendee;u=' . $person['id'] . $context['em_session'] . ';do=';
			echo '
				<tr class="windowbg" data-name="', strtolower(strip_tags($person['name'])), '">
					<td>', $person['link'], '</td>
					<td>', em_badge($status), '</td>
					<td>', $person['guests'] ?: '', '</td>
					<td>', $person['note'], '</td>
					<td class="smalltext">', $person['registered'], '</td>
					<td>';
			if ($status === 'going')
				echo '<input type="checkbox" class="em_checkin" data-url="', $act, '"', $person['checked_in'] ? ' checked' : '', ' aria-label="', $txt['em_col_checked_in'], '">';
			echo '</td>
					<td class="em_row_actions">';
			if ($status === 'pending')
				echo '
						<a href="', $act, 'approve">', $txt['em_approve'], '</a>
						<a href="', $act, 'decline">', $txt['em_decline'], '</a>';
			if ($status === 'waitlist' || $status === 'maybe')
				echo '
						<a href="', $act, 'promote">', $txt['em_promote'], '</a>';
			echo '
						<a href="', $act, 'remove" data-confirm="', $txt['em_remove_confirm'], '">', $txt['em_remove'], '</a>
					</td>
				</tr>';
		}
	if (!$any)
		echo '
				<tr class="windowbg"><td colspan="7" class="centertext">', $txt['em_nobody_yet'], '</td></tr>';
	echo '
			</tbody>
		</table>

		<form action="', $base, ';sa=add" method="post" accept-charset="UTF-8" class="em_add">
			<label for="em_add_names">', $txt['em_add_people'], '</label>
			<input type="text" name="names" id="em_add_names" size="40" placeholder="', $txt['em_add_people_hint'], '">
			<select name="status">
				<option value="going">', $txt['em_status_going'], '</option>
				<option value="waitlist">', $txt['em_status_waitlist'], '</option>
			</select>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" value="', $txt['em_add'], '" class="button">
		</form>
	</div>';

	// Message attendees.
	echo '
	<form action="', $base, ';sa=message" method="post" accept-charset="UTF-8" class="windowbg em_message">
		<h4>', $txt['em_message_title'], '</h4>
		<div class="em_to">', $txt['em_message_to'];
	foreach (array('going', 'maybe', 'waitlist', 'pending') as $s)
		echo '
			<label><input type="checkbox" name="to[]" value="', $s, '"', $s === 'going' ? ' checked' : '', '> ', $txt['em_status_' . $s], ' (', $c[$s], ')</label>';
	echo '
		</div>
		<input type="text" name="subject" maxlength="100" placeholder="', $txt['em_message_subject'], '" class="em_subject" value="', $e['title'], '">
		<textarea name="body" rows="5" placeholder="', $txt['em_message_body'], '"></textarea>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<div class="righttext"><input type="submit" value="', $txt['em_send'], '" class="button"></div>
	</form>';

	// Cancel or reinstate.
	echo '
	<form action="', $base, ';sa=cancel" method="post" accept-charset="UTF-8" class="windowbg em_danger">
		<h4>', $e['cancelled'] ? $txt['em_reinstate_title'] : $txt['em_cancel_title'], '</h4>
		<p class="smalltext">', $e['cancelled'] ? $txt['em_reinstate_desc'] : $txt['em_cancel_desc'], '</p>
		<input type="hidden" name="cancel" value="', $e['cancelled'] ? 0 : 1, '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="submit" value="', $e['cancelled'] ? $txt['em_reinstate'] : $txt['em_cancel_event'], '" class="button', $e['cancelled'] ? '' : ' em_btn_danger', '" data-confirm="', $e['cancelled'] ? $txt['em_reinstate_confirm'] : $txt['em_cancel_confirm'], '">
	</form>';
}

/* ----------------------------------------------------------------------
 * Topic panel (template layer)
 * -------------------------------------------------------------------- */

function template_em_topic_above()
{
	global $context, $txt;

	template_em_notice();

	foreach ($context['em_panels'] as $p)
	{
		$e = $p['event'];
		echo '
	<div class="windowbg em_topic_panel', $e['cancelled'] ? ' em_is_cancelled' : '', '">
		<div class="em_topic_info">
			<a href="', $p['url'], '" class="em_item_title">', $e['title'], '</a>
			<div class="em_meta">', $p['when'], $e['location'] !== '' ? ' · ' . $e['location'] : '', '</div>', em_meter($e, $p['counts']), '
		</div>';
		template_em_rsvp_box($p, true);
		echo '
	</div>';
	}
}

function template_em_topic_below()
{
}
