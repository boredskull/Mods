<?php

/**
 * Clubs: templates.
 *
 * @package Clubs
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.0
 */

function clubs_h($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function clubs_avatar($url, $class = 'club_avatar')
{
	return '<span class="' . $class . '">' . ($url !== '' ? '<img src="' . clubs_h($url) . '" alt="" loading="lazy">' : '') . '</span>';
}

function template_clubs_notice()
{
	global $context;

	if (!empty($context['clubs_notice']))
		echo '
	<div class="', $context['clubs_notice'][0] === 'error' ? 'errorbox' : 'infobox', '">', $context['clubs_notice'][1], '</div>';

	if (!empty($context['clubs_errors']))
	{
		echo '
	<div class="errorbox"><ul>';
		foreach ($context['clubs_errors'] as $e)
			echo '<li>', $e, '</li>';
		echo '</ul></div>';
	}
}

/* --------------------------------------------------------------------
 * Club banner (also shown above club boards as a template layer)
 * ------------------------------------------------------------------ */

function template_club_banner(array $h)
{
	global $txt, $scripturl;

	$c = $h['club'];
	$color = preg_match('~^#[0-9a-f]{6}$~i', $c['color']) ? $c['color'] : '#557ea0';
	$tabs = isset($h['tabs']) ? $h['tabs'] : array_merge(array('overview', 'members'), $h['can_see_content'] ? array_merge(array('media'), $h['events'] ? array('events') : array()) : array(), $h['is_leader'] ? array('manage') : array());

	echo '
	<div class="club_banner" style="--club-color:', $color, '">
		<div class="club_banner_inner">
			<a class="club_banner_icon" href="', $h['url'], '">', $h['icon'] !== '' ? '<img src="' . clubs_h($h['icon']) . '" alt="">' : '<span>' . clubs_h(mb_strtoupper(mb_substr(un_htmlspecialchars($c['name']), 0, 1))) . '</span>', '</a>
			<div class="club_banner_text">
				<h2 class="club_banner_name"><a href="', $h['url'], '">', $c['name'], '</a></h2>
				<div class="club_banner_meta">
					<span class="club_type club_type_', $c['type'], '">', $txt['clubs_type_' . $c['type']], '</span>
					<span>', sprintf($c['num_members'] === 1 ? $txt['clubs_one_member'] : $txt['clubs_n_members'], comma_format($c['num_members'])), '</span>', $c['status'] === 'pending' ? '
					<span class="club_type club_pending">' . $txt['clubs_pending_approval'] . '</span>' : '', '
				</div>
			</div>
		</div>
		<nav class="club_nav" aria-label="', clubs_h(strip_tags($c['name'])), '">';

	foreach (array('overview', 'discussions', 'members', 'media', 'events', 'manage') as $tab)
	{
		if ($tab === 'discussions')
		{
			if (!$h['can_see_content'] || empty($c['id_board']))
				continue;
			$url = $scripturl . '?board=' . $c['id_board'] . '.0';
		}
		elseif (!in_array($tab, $tabs, true))
			continue;
		else
			$url = $h['url'] . ($tab === 'overview' ? '' : ';tab=' . $tab);

		echo '
			<a href="', $url, '"', $h['active'] === $tab ? ' class="club_nav_active" aria-current="page"' : '', '>', $txt['clubs_tab_' . $tab], '</a>';
	}

	echo '
		</nav>
	</div>';
}

function template_club_header_above()
{
	global $context;

	if (!empty($context['club_header']))
		template_club_banner($context['club_header']);
}

function template_club_header_below()
{
}

/* --------------------------------------------------------------------
 * Directory
 * ------------------------------------------------------------------ */

function template_clubs_card(array $c)
{
	global $txt;

	echo '
		<a class="club_card" href="', $c['url'], '" style="--club-color:', $c['color'], '">
			<span class="club_card_top"></span>
			<span class="club_card_icon">', $c['icon'] !== '' ? '<img src="' . clubs_h($c['icon']) . '" alt="" loading="lazy">' : '<span>' . clubs_h(mb_strtoupper(mb_substr(un_htmlspecialchars($c['name']), 0, 1))) . '</span>', '</span>
			<span class="club_card_body">
				<span class="club_card_name">', $c['name'], '</span>
				<span class="club_card_meta"><span class="club_type club_type_', $c['type'], '">', $txt['clubs_type_' . $c['type']], '</span> ', sprintf($c['members'] === 1 ? $txt['clubs_one_member'] : $txt['clubs_n_members'], comma_format($c['members'])), '</span>
				<span class="club_card_desc">', $c['summary'], '</span>', $c['role'] !== '' ? '
				<span class="club_card_role">' . $txt['clubs_role_' . $c['role']] . '</span>' : '', $c['status'] === 'pending' ? '
				<span class="club_card_role club_pending">' . $txt['clubs_pending_approval'] . '</span>' : '', '
			</span>
		</a>';
}

function template_clubs_directory()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['clubs_title'], '</h3></div>';
	template_clubs_notice();

	echo '
	<div class="clubs_toolbar">
		<form action="', $context['clubs_url'], '" method="get" class="clubs_search" role="search">
			<input type="hidden" name="action" value="clubs">
			<input type="search" name="q" value="', clubs_h($context['clubs_search']), '" placeholder="', $txt['clubs_search'], '" aria-label="', $txt['clubs_search'], '">
			<select name="sort" aria-label="', $txt['clubs_sort'], '">';
	foreach (array('members', 'newest', 'name') as $s)
		echo '<option value="', $s, '"', $context['clubs_sort'] === $s ? ' selected' : '', '>', $txt['clubs_sort_' . $s], '</option>';
	echo '</select>';
	if (!empty($context['user']['is_logged']))
		echo '
			<label><input type="checkbox" name="mine" value="1"', $context['clubs_mine'] ? ' checked' : '', '> ', $txt['clubs_my_clubs'], '</label>';
	echo '
			<input type="submit" value="', $txt['clubs_go'], '" class="button">
		</form>';
	if ($context['clubs_can_create'])
		echo '
		<a class="button club_create_btn" href="', $context['clubs_url'], ';sa=create">', $txt['clubs_create'], '</a>';
	echo '
	</div>';

	if (!empty($context['clubs_invites']))
	{
		echo '
	<div class="windowbg clubs_invites">
		<h4>', $txt['clubs_your_invites'], '</h4>';
		foreach ($context['clubs_invites'] as $c)
			echo '
		<div class="clubs_invite">
			<a href="', $c['url'], '"><strong>', $c['name'], '</strong></a>
			<span>
				<a class="button" href="', $context['clubs_url'], ';sa=accept;club=', $c['id'], $context['clubs_session'], '">', $txt['clubs_accept'], '</a>
				<a class="button" href="', $context['clubs_url'], ';sa=decline;club=', $c['id'], $context['clubs_session'], '">', $txt['clubs_decline'], '</a>
			</span>
		</div>';
		echo '
	</div>';
	}

	if (empty($context['clubs_list']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $context['clubs_search'] !== '' || $context['clubs_mine'] ? $txt['clubs_none_found'] : $txt['clubs_none_yet'], '</p></div>';
		return;
	}

	echo '
	<div class="clubs_grid">';
	foreach ($context['clubs_list'] as $c)
		template_clubs_card($c);
	echo '
	</div>
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>';
}

/* --------------------------------------------------------------------
 * Create / edit form
 * ------------------------------------------------------------------ */

function template_clubs_form_fields()
{
	global $context, $txt;

	$f = $context['clubs_form'];
	$editing = $context['clubs_editing'];
	$can_type = !$editing || !empty($context['club_is_owner']);

	echo '
		<dl class="settings">
			<dt><label for="club_name">', $txt['clubs_field_name'], '</label></dt>
			<dd><input type="text" id="club_name" name="name" value="', $f['name'], '" maxlength="60" minlength="3" required size="40"></dd>

			<dt><label for="club_description">', $txt['clubs_field_description'], '</label><br><span class="smalltext">', $txt['clubs_field_description_desc'], '</span></dt>
			<dd><textarea id="club_description" name="description" rows="6" cols="60" maxlength="5000">', $f['description'], '</textarea></dd>

			<dt>', $txt['clubs_field_type'], '</dt>
			<dd class="club_type_options">';
	if ($can_type)
		foreach ($context['clubs_types'] as $t)
			echo '
				<label class="club_type_option"><input type="radio" name="type" value="', $t, '"', $f['type'] === $t ? ' checked' : '', '> <strong>', $txt['clubs_type_' . $t], '</strong><span class="smalltext">', $txt['clubs_type_' . $t . '_desc'], '</span></label>';
	else
		echo '
				<strong>', $txt['clubs_type_' . $f['type']], '</strong><br><span class="smalltext">', $txt['clubs_type_owner_only'], '</span>';
	echo '
			</dd>

			<dt><label for="club_color">', $txt['clubs_field_color'], '</label></dt>
			<dd><input type="color" id="club_color" name="color" value="', clubs_h($f['color']), '"></dd>

			<dt><label for="club_icon">', $txt['clubs_field_icon'], '</label><br><span class="smalltext">', $txt['clubs_field_icon_desc'], '</span></dt>
			<dd>';
	if (!empty($f['icon']))
		echo '
				<img src="', clubs_h(Clubs::iconUrl($f)), '" alt="" class="club_icon_preview">
				<label><input type="checkbox" name="icon_remove" value="1"> ', $txt['clubs_remove_icon'], '</label><br>';
	echo '
				<input type="file" id="club_icon" name="icon" accept="image/png,image/jpeg,image/gif,image/webp">
			</dd>';

	if (Clubs::calendarEnabled())
		echo '
			<dt><label for="club_member_events">', $txt['clubs_field_member_events'], '</label><br><span class="smalltext">', $txt['clubs_field_member_events_desc'], '</span></dt>
			<dd><input type="checkbox" id="club_member_events" name="member_events" value="1"', !empty($f['member_events']) ? ' checked' : '', '></dd>';

	echo '
		</dl>';
}

function template_clubs_form()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['clubs_create'], '</h3></div>';
	template_clubs_notice();

	echo '
	<form class="windowbg" action="', $context['clubs_url'], ';sa=create" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<p>', $txt['clubs_create_intro'], '</p>';
	template_clubs_form_fields();
	echo '
		<input type="submit" name="save" value="', $txt['clubs_create'], '" class="button">
	</form>';
}

/* --------------------------------------------------------------------
 * Club page
 * ------------------------------------------------------------------ */

function template_clubs_view()
{
	global $context;

	template_club_banner($context['club_header']);
	template_clubs_notice();

	$fn = 'template_clubs_tab_' . $context['club_tab'];
	$fn();
}

function template_clubs_join_box()
{
	global $context, $txt;

	$c = $context['club'];
	$role = $context['club_role'];
	$base = $context['clubs_url'];
	$s = $context['clubs_session'];

	echo '
		<div class="club_box club_join">';

	if (!empty($context['user']['is_guest']))
		echo '<p>', $txt['clubs_login_to_join'], '</p>';
	elseif ($role === 'banned')
		echo '<p>', $txt['clubs_you_banned'], '</p>';
	elseif ($role === 'owner')
		echo '<p><strong>', $txt['clubs_you_own'], '</strong></p>';
	elseif ($role === 'leader' || $role === 'member')
		echo '<p><strong>', $role === 'leader' ? $txt['clubs_you_lead'] : $txt['clubs_you_member'], '</strong></p>
			<a class="button" href="', $base, ';sa=leave;club=', $c['id_club'], $s, '" data-clubs-confirm="', clubs_h($txt['clubs_confirm_leave']), '">', $txt['clubs_leave'], '</a>';
	elseif ($role === 'pending')
		echo '<p>', $txt['clubs_request_pending'], '</p>
			<a class="button" href="', $base, ';sa=cancel;club=', $c['id_club'], $s, '">', $txt['clubs_cancel_request'], '</a>';
	elseif ($role === 'invited')
		echo '<p><strong>', $txt['clubs_you_invited'], '</strong></p>
			<a class="button" href="', $base, ';sa=accept;club=', $c['id_club'], $s, '">', $txt['clubs_accept'], '</a>
			<a class="button" href="', $base, ';sa=decline;club=', $c['id_club'], $s, '">', $txt['clubs_decline'], '</a>';
	elseif (!$context['club_can_join'])
		echo '<p>', $txt['clubs_cannot_join'], '</p>';
	elseif ($c['type'] === 'open')
		echo '<a class="button club_join_btn" href="', $base, ';sa=join;club=', $c['id_club'], $s, '">', $txt['clubs_join'], '</a>';
	elseif ($c['type'] === 'closed')
		echo '
			<form action="', $base, ';sa=request;club=', $c['id_club'], '" method="post" accept-charset="UTF-8">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<label for="club_request_msg">', $txt['clubs_request_message'], '</label>
				<textarea id="club_request_msg" name="message" rows="3" maxlength="250"></textarea>
				<input type="submit" value="', $txt['clubs_request_join'], '" class="button club_join_btn">
			</form>';
	else
		echo '<p>', $txt['clubs_invite_only'], '</p>';

	echo '
		</div>';
}

function template_clubs_people(array $list, $size = 'small')
{
	echo '
			<ul class="club_faces club_faces_', $size, '">';
	foreach ($list as $p)
		echo '
				<li title="', clubs_h(strip_tags($p['name'])), '">', clubs_avatar($p['avatar']), $size === 'list' ? '<span>' . $p['link'] . '</span>' : '', '</li>';
	echo '
			</ul>';
}

function template_clubs_tab_overview()
{
	global $context, $txt, $scripturl;

	$c = $context['club'];

	echo '
	<div class="club_layout">
		<div class="club_main">
			<div class="windowbg">
				<h4 class="club_section">', $txt['clubs_about'], '</h4>
				<div class="club_description">', $c['description_html'] !== '' ? $c['description_html'] : '<em>' . $txt['clubs_no_description'] . '</em>', '</div>
			</div>';

	if ($context['club_can_content'])
	{
		echo '
			<div class="windowbg">
				<h4 class="club_section">', $txt['clubs_latest_discussions'], '</h4>';
		if (empty($context['club_topics']))
			echo '
				<p class="smalltext">', $txt['clubs_no_topics'], '</p>';
		else
		{
			echo '
				<ul class="club_topics">';
			foreach ($context['club_topics'] as $t)
				echo '
					<li><a href="', $t['url'], '">', $t['subject'], '</a><span class="smalltext">', sprintf($txt['clubs_topic_meta'], comma_format($t['replies']), $t['last_poster'], $t['time']), '</span></li>';
			echo '
				</ul>';
		}
		echo '
				<p>', $c['board_url'] !== '' ? '<a class="button" href="' . $c['board_url'] . '">' . $txt['clubs_all_discussions'] . '</a>' : '', ($context['club_is_member'] && $c['type'] !== 'readonly') || $context['club_is_leader'] ? ' <a class="button" href="' . $scripturl . '?action=post;board=' . $c['id_board'] . '.0">' . $txt['clubs_new_topic'] . '</a>' : '', '</p>
			</div>';

		if (!empty($context['club_events']))
		{
			echo '
			<div class="windowbg">
				<h4 class="club_section">', $txt['clubs_upcoming_events'], '</h4>
				<ul class="club_events">';
			foreach ($context['club_events'] as $e)
				template_clubs_event_row($e, false);
			echo '
				</ul>
			</div>';
		}
	}
	else
		echo '
			<div class="windowbg club_locked"><p>', $c['status'] === 'pending' ? $txt['clubs_pending_desc'] : $txt['clubs_members_only_content'], '</p></div>';

	echo '
		</div>
		<aside class="club_side">';
	template_clubs_join_box();

	echo '
			<div class="club_box">
				<h4 class="club_section">', $txt['clubs_stats'], '</h4>
				<ul class="club_stats">
					<li><strong>', comma_format($c['num_members']), '</strong> ', $txt['clubs_stat_members'], '</li>', $context['club_can_content'] ? '
					<li><strong>' . comma_format($context['club_stats']['topics']) . '</strong> ' . $txt['clubs_stat_topics'] . '</li>
					<li><strong>' . comma_format($context['club_stats']['posts']) . '</strong> ' . $txt['clubs_stat_posts'] . '</li>' : '', '
					<li>', sprintf($txt['clubs_created_on'], timeformat($c['created'], false)), '</li>
				</ul>
			</div>
			<div class="club_box">
				<h4 class="club_section">', $txt['clubs_leaders'], '</h4>';
	template_clubs_people($context['club_leaders'], 'list');
	echo '
			</div>';

	if (!empty($context['club_recent']))
	{
		echo '
			<div class="club_box">
				<h4 class="club_section">', $txt['clubs_new_members'], '</h4>';
		template_clubs_people($context['club_recent']);
		echo '
				<a href="', $c['url'], ';tab=members" class="smalltext">', $txt['clubs_see_all_members'], '</a>
			</div>';
	}

	echo '
		</aside>
	</div>';
}

function template_clubs_tab_members()
{
	global $context, $txt;

	$c = $context['club'];
	$base = $context['clubs_url'] . ';sa=member;club=' . $c['id_club'];
	$s = $context['clubs_session'];
	$leader = $context['club_is_leader'];
	$owner = $context['club_is_owner'];

	$actions = function ($p, $section) use ($base, $s, $leader, $owner, $txt, $context)
	{
		if (!$leader || (int) $p['id'] === (int) $context['user']['id'])
			return '';

		$links = array();
		$link = function ($do, $label, $confirm = '') use ($base, $s, $p)
		{
			return '<a href="' . $base . ';u=' . $p['id'] . ';do=' . $do . $s . '"' . ($confirm !== '' ? ' data-clubs-confirm="' . clubs_h($confirm) . '"' : '') . '>' . $label . '</a>';
		};

		if ($section === 'pending')
			$links = array($link('approve', $txt['clubs_approve']), $link('deny', $txt['clubs_deny']));
		elseif ($section === 'invited')
			$links = array($link('cancelinvite', $txt['clubs_cancel_invite']));
		elseif ($section === 'banned')
			$links = array($link('unban', $txt['clubs_unban']));
		elseif ($p['role'] === 'leader' && $owner)
			$links = array($link('demote', $txt['clubs_demote']), $link('remove', $txt['clubs_remove'], $txt['clubs_confirm_remove']));
		elseif ($p['role'] === 'member')
		{
			if ($owner)
				$links[] = $link('promote', $txt['clubs_promote']);
			$links[] = $link('remove', $txt['clubs_remove'], $txt['clubs_confirm_remove']);
			$links[] = $link('ban', $txt['clubs_ban'], $txt['clubs_confirm_ban']);
		}

		return empty($links) ? '' : '<span class="club_member_actions">' . implode(' · ', $links) . '</span>';
	};

	if ($leader)
	{
		echo '
	<div class="windowbg">
		<form class="club_invite_form" action="', $context['clubs_url'], ';sa=invite;club=', $c['id_club'], '" method="post" accept-charset="UTF-8">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<label for="club_invite">', $txt['clubs_invite_label'], '</label>
			<input type="text" id="club_invite" name="names" placeholder="', $txt['clubs_invite_placeholder'], '" size="40">
			<input type="submit" value="', $txt['clubs_invite'], '" class="button">
		</form>
	</div>';
	}

	foreach (array('pending', 'leaders', 'members', 'invited', 'banned') as $section)
	{
		if (!isset($context['club_members'][$section]) || (empty($context['club_members'][$section]) && $section !== 'members'))
			continue;

		echo '
	<div class="windowbg">
		<h4 class="club_section">', $txt['clubs_section_' . $section], ' <span class="club_count">', count($context['club_members'][$section]), '</span></h4>';

		if (empty($context['club_members'][$section]))
			echo '
		<p class="smalltext">', $txt['clubs_no_members'], '</p>';
		else
		{
			echo '
		<ul class="club_member_list">';
			foreach ($context['club_members'][$section] as $p)
				echo '
			<li>
				', clubs_avatar($p['avatar']), '
				<span class="club_member_info">
					<span>', $p['link'], ' <span class="club_role club_role_', $p['role'], '">', $txt['clubs_role_' . $p['role']], '</span></span>
					<span class="smalltext">', sprintf($txt['clubs_since'], $p['joined']), '</span>', $section === 'pending' && $p['message'] !== '' ? '
					<span class="club_request_msg">' . $p['message'] . '</span>' : '', '
				</span>
				', $actions($p, $section), '
			</li>';
			echo '
		</ul>';
		}

		echo '
	</div>';
	}
}

function template_clubs_tab_media()
{
	global $context, $txt;

	echo '
	<div class="windowbg">
		<div class="club_media_head">
			<p class="smalltext">', $txt['clubs_media_intro'], '</p>', !empty($context['club_can_upload']) ? '
			<a class="button" href="' . $context['club_upload_url'] . '">' . $txt['clubs_share_media'] . '</a>' : '', '
		</div>';

	if (empty($context['club_media']))
		echo '
		<p class="centertext">', $txt['clubs_no_media'], '</p>';
	else
	{
		echo '
		<ul class="club_media">';
		foreach ($context['club_media'] as $m)
			echo '
			<li>
				<a href="', $m['full'], '" class="club_media_img" target="_blank" rel="noopener"><img src="', $m['thumb'], '" alt="', clubs_h($m['name']), '" loading="lazy"></a>
				<a href="', $m['post'], '" class="club_media_caption">', $m['subject'], '</a>
			</li>';
		echo '
		</ul>
		<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>';
	}

	echo '
	</div>';
}

function template_clubs_event_row(array $e, $rsvp)
{
	global $context, $txt;

	$start = strtotime($e['start']);
	$month = isset($txt['months_short'][(int) date('n', $start)]) ? $txt['months_short'][(int) date('n', $start)] : date('M', $start);

	echo '
					<li class="club_event', $e['past'] ? ' club_event_past' : '', '">
						<span class="club_event_date"><span>', $month, '</span><strong>', date('j', $start), '</strong></span>
						<span class="club_event_body">
							<strong>', $e['topic_url'] !== '' ? '<a href="' . $e['topic_url'] . '">' . $e['title'] . '</a>' : $e['title'], '</strong>
							<span class="smalltext">', $e['start'] === $e['end'] ? $e['start'] : $e['start'] . ' – ' . $e['end'], $e['location'] !== '' ? ' · ' . $e['location'] : '', '</span>
							<span class="smalltext">', sprintf($txt['clubs_rsvp_counts'], $e['counts']['going'], $e['counts']['maybe']), '</span>';

	if ($rsvp && !$e['past'])
	{
		$base = $context['clubs_url'] . ';sa=rsvp;club=' . $context['club']['id_club'] . ';event=' . $e['id'] . $context['clubs_session'];
		echo '
							<span class="club_rsvp" role="group" aria-label="', $txt['clubs_rsvp'], '">';
		foreach (array('going', 'maybe', 'no') as $st)
			echo '<a href="', $base, ';status=', $e['mine'] === $st ? 'clear' : $st, '" class="club_rsvp_btn', $e['mine'] === $st ? ' club_rsvp_on' : '', '" aria-pressed="', $e['mine'] === $st ? 'true' : 'false', '">', $txt['clubs_rsvp_' . $st], '</a>';
		echo '</span>';
	}

	echo '
						</span>
					</li>';
}

function template_clubs_tab_events()
{
	global $context, $txt;

	$c = $context['club'];

	if ($context['club_can_event'])
		echo '
	<details class="windowbg club_event_form">
		<summary class="button">', $txt['clubs_add_event'], '</summary>
		<form action="', $context['clubs_url'], ';sa=event;club=', $c['id_club'], '" method="post" accept-charset="UTF-8">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<dl class="settings">
				<dt><label for="ev_title">', $txt['clubs_event_title'], '</label></dt>
				<dd><input type="text" id="ev_title" name="title" maxlength="60" required size="40"></dd>
				<dt><label for="ev_start">', $txt['clubs_event_start'], '</label></dt>
				<dd><input type="date" id="ev_start" name="start" required min="', date('Y-m-d'), '"></dd>
				<dt><label for="ev_end">', $txt['clubs_event_end'], '</label><br><span class="smalltext">', $txt['clubs_event_end_desc'], '</span></dt>
				<dd><input type="date" id="ev_end" name="end"></dd>
				<dt><label for="ev_location">', $txt['clubs_event_location'], '</label></dt>
				<dd><input type="text" id="ev_location" name="location" maxlength="200" size="40"></dd>
				<dt><label for="ev_desc">', $txt['clubs_event_description'], '</label><br><span class="smalltext">', $txt['clubs_event_description_desc'], '</span></dt>
				<dd><textarea id="ev_desc" name="description" rows="4" cols="50"></textarea></dd>
			</dl>
			<input type="submit" value="', $txt['clubs_add_event'], '" class="button">
		</form>
	</details>';

	echo '
	<div class="windowbg">
		<h4 class="club_section">', $txt['clubs_upcoming_events'], '</h4>';
	if (empty($context['club_events_upcoming']))
		echo '
		<p class="smalltext">', $txt['clubs_no_events'], '</p>';
	else
	{
		echo '
		<ul class="club_events">';
		foreach ($context['club_events_upcoming'] as $e)
			template_clubs_event_row($e, $context['club_can_rsvp']);
		echo '
		</ul>';
	}
	echo '
	</div>';

	if (!empty($context['club_events_past']))
	{
		echo '
	<details class="windowbg">
		<summary>', $txt['clubs_past_events'], '</summary>
		<ul class="club_events">';
		foreach ($context['club_events_past'] as $e)
			template_clubs_event_row($e, false);
		echo '
		</ul>
	</details>';
	}
}

function template_clubs_tab_manage()
{
	global $context, $txt;

	$c = $context['club'];
	$url = $context['clubs_url'];
	$s = $context['clubs_session'];

	echo '
	<form class="windowbg" action="', $url, ';sa=manage;club=', $c['id_club'], '" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<h4 class="club_section">', $txt['clubs_settings'], '</h4>';
	template_clubs_form_fields();
	echo '
		<input type="submit" value="', $txt['clubs_save'], '" class="button">
	</form>

	<div class="windowbg">
		<h4 class="club_section">', $txt['clubs_boards'], '</h4>
		<p class="smalltext">', $txt['clubs_boards_desc'], '</p>
		<ul class="club_board_list">';
	foreach ($context['club_boards'] as $b)
		echo '
			<li>
				<span><a href="', $GLOBALS['scripturl'], '?board=', $b['id_board'], '.0"><strong>', $b['name'], '</strong></a>', $b['main'] ? ' <span class="club_role">' . $txt['clubs_main_board'] . '</span>' : '', '<br><span class="smalltext">', sprintf($txt['clubs_board_stats'], comma_format($b['num_topics']), comma_format($b['num_posts'])), '</span></span>', $b['main'] ? '' : '
				<a class="button" href="' . $url . ';sa=removeboard;club=' . $c['id_club'] . ';board=' . $b['id_board'] . $s . '" data-clubs-confirm="' . clubs_h($txt['clubs_confirm_remove_board']) . '">' . $txt['clubs_remove'] . '</a>', '
			</li>';
	echo '
		</ul>';

	if (count($context['club_boards']) - 1 < $context['club_max_boards'])
		echo '
		<form class="club_add_board" action="', $url, ';sa=addboard;club=', $c['id_club'], '" method="post" accept-charset="UTF-8">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="text" name="board_name" maxlength="60" placeholder="', $txt['clubs_board_name'], '" required>
			<input type="text" name="board_description" maxlength="250" placeholder="', $txt['clubs_board_description'], '" size="40">
			<input type="submit" value="', $txt['clubs_add_board'], '" class="button">
		</form>';

	echo '
	</div>';

	if ($context['club_is_owner'])
	{
		if (!empty($context['club_transfer_to']))
		{
			echo '
	<div class="windowbg">
		<h4 class="club_section">', $txt['clubs_transfer'], '</h4>
		<p class="smalltext">', $txt['clubs_transfer_desc'], '</p>
		<ul class="club_transfer">';
			foreach ($context['club_transfer_to'] as $p)
				echo '
			<li>', $p['link'], ' <a href="', $url, ';sa=member;club=', $c['id_club'], ';u=', $p['id'], ';do=transfer', $s, '" data-clubs-confirm="', clubs_h(sprintf($txt['clubs_confirm_transfer'], strip_tags($p['name']))), '">', $txt['clubs_make_owner'], '</a></li>';
			echo '
		</ul>
	</div>';
		}

		echo '
	<form class="windowbg club_danger" action="', $url, ';sa=delete;club=', $c['id_club'], '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<h4 class="club_section">', $txt['clubs_delete'], '</h4>
		<p>', $txt['clubs_delete_desc'], '</p>
		<label for="club_confirm_name">', $txt['clubs_delete_confirm_label'], '</label>
		<input type="text" id="club_confirm_name" name="confirm_name" autocomplete="off" required>
		<input type="submit" value="', $txt['clubs_delete'], '" class="button">
	</form>';
	}
}

/* --------------------------------------------------------------------
 * Admin
 * ------------------------------------------------------------------ */

function template_clubs_admin_list()
{
	global $context, $txt;

	if (!empty($context['clubs_notice']))
		echo '
	<div class="infobox">', $context['clubs_notice'][1], '</div>';

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['clubs_tab_list'], '</h3></div>
	<div class="windowbg">
		<a class="button" href="', $context['clubs_admin_url'], ';sa=list;resync', $context['clubs_session'], '">', $txt['clubs_admin_resync'], '</a>
		<span class="smalltext">', $txt['clubs_admin_resync_desc'], '</span>
	</div>';

	if (empty($context['clubs_admin_list']))
	{
		echo '
	<div class="windowbg"><p class="centertext">', $txt['clubs_none_yet'], '</p></div>';
		return;
	}

	echo '
	<table class="table_grid">
		<thead>
			<tr class="title_bar">
				<th>', $txt['clubs_field_name'], '</th>
				<th>', $txt['clubs_field_type'], '</th>
				<th>', $txt['clubs_owner'], '</th>
				<th>', $txt['clubs_stat_members'], '</th>
				<th>', $txt['clubs_created_col'], '</th>
				<th class="righttext">', $txt['clubs_actions'], '</th>
			</tr>
		</thead>
		<tbody>';
	foreach ($context['clubs_admin_list'] as $c)
		echo '
			<tr class="windowbg">
				<td><a href="', $c['url'], '">', $c['name'], '</a>', $c['status'] === 'pending' ? ' <span class="club_type club_pending">' . $txt['clubs_pending_approval'] . '</span>' : '', '</td>
				<td>', $txt['clubs_type_' . $c['type']], '</td>
				<td>', $c['owner_name'], '</td>
				<td>', comma_format($c['num_members']), '</td>
				<td>', $c['created_text'], '</td>
				<td class="righttext">', $c['status'] === 'pending' ? '<a class="button" href="' . $context['clubs_admin_url'] . ';sa=list;approve=' . $c['id_club'] . $context['clubs_session'] . '">' . $txt['clubs_approve'] . '</a> ' : '', '<a class="button" href="', $context['clubs_admin_url'], ';sa=list;remove=', $c['id_club'], $context['clubs_session'], '" data-clubs-confirm="', clubs_h($txt['clubs_admin_confirm_delete']), '">', $txt['clubs_delete'], '</a></td>
			</tr>';
	echo '
		</tbody>
	</table>
	<div class="pagesection"><div class="pagelinks">', $context['page_index'], '</div></div>
	<script>document.addEventListener("click", function (e) { var el = e.target.closest("[data-clubs-confirm]"); if (el && !confirm(el.getAttribute("data-clubs-confirm"))) e.preventDefault(); });</script>';
}
