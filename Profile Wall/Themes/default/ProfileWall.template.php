<?php
/**
 * Profile Wall for SMF 2.1.x - templates
 *
 * @license ISC
 */

/**
 * The wall itself.
 */
function template_pw_wall()
{
	global $context, $txt;

	$pw = $context['pw'];

	echo '
	<div id="profile_wall">
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="main_icons members"></span> ', $pw['title'], '
				<span class="pw_count">', sprintf($txt['pw_comment_count'], $pw['total']), '</span>
			</h3>
		</div>';

	// Feedback after actions.
	if (isset($_GET['pending']))
		echo '
		<div class="noticebox">', $txt['pw_notice_pending'], '</div>';
	if (isset($_GET['reported']))
		echo '
		<div class="infobox">', $txt['pw_notice_reported'], '</div>';
	if (isset($_GET['saved']))
		echo '
		<div class="infobox">', $txt['pw_notice_saved'], '</div>';

	if (!empty($pw['pending_count']))
		echo '
		<div class="noticebox">', sprintf($txt['pw_pending_count'], $pw['pending_count']), '</div>';

	if ($pw['show_wall_settings'])
		template_pw_wall_settings();

	// Why can't I post?
	if ($pw['post_status'] !== true && $pw['post_status'] !== 'no_permission')
		echo '
		<div class="infobox">', $txt['pw_status_' . $pw['post_status']], '</div>';

	// New comment form.
	if ($pw['post_status'] === true)
	{
		echo '
		<form action="', $pw['url'], ';sa=post" method="post" accept-charset="', $context['character_set'], '" id="pw_post_form" class="windowbg pw_post_form">
			<h4 class="pw_form_title">', $pw['is_owner'] ? $txt['pw_post_own'] : $txt['pw_leave_comment'], '</h4>';

		template_control_richedit($pw['editor_id'], null, null);

		echo '
			<div class="pw_form_buttons">';
		if (!empty($pw['max_length']))
			echo '
				<span class="smalltext">', sprintf($txt['pw_max_chars'], $pw['max_length']), '</span>';
		echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" value="', $txt['pw_post'], '" class="button">
			</div>
		</form>';
	}

	// The comments.
	if (empty($pw['comments']))
		echo '
		<div class="windowbg centertext pw_empty">', $txt['pw_no_comments'], '</div>';
	else
	{
		echo '
		<div class="pagesection">
			<div class="pagelinks">', $pw['page_index'], '</div>
		</div>
		<div class="pw_list">';

		foreach ($pw['comments'] as $comment)
			template_pw_comment($comment, false);

		echo '
		</div>
		<div class="pagesection">
			<div class="pagelinks">', $pw['page_index'], '</div>
		</div>';
	}

	echo '
	</div>';
}

/**
 * Owner's privacy panel.
 */
function template_pw_wall_settings()
{
	global $context, $txt;

	$pw = $context['pw'];
	$privacy = $pw['wall']['privacy'];

	echo '
		<details class="windowbg pw_settings">
			<summary><span class="main_icons settings"></span> ', $txt['pw_wall_settings'], '</summary>
			<form action="', $pw['url'], ';sa=settings" method="post" accept-charset="', $context['character_set'], '">
				<dl class="settings">
					<dt><label for="pw_privacy">', $txt['pw_privacy'], '</label></dt>
					<dd>
						<select name="privacy" id="pw_privacy">
							<option value="0"', $privacy === 0 ? ' selected' : '', '>', $txt['pw_privacy_0'], '</option>
							<option value="1"', $privacy === 1 ? ' selected' : '', '>', $txt['pw_privacy_1'], '</option>
							<option value="2"', $privacy === 2 ? ' selected' : '', '>', $txt['pw_privacy_2'], '</option>
						</select>
					</dd>
					<dt>
						<label for="pw_owner_approval">', $txt['pw_owner_approval'], '</label><br>
						<span class="smalltext">', $txt['pw_owner_approval_sub'], '</span>
					</dt>
					<dd><input type="checkbox" name="owner_approval" id="pw_owner_approval" value="1"', !empty($pw['wall']['owner_approval']) ? ' checked' : '', '></dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" value="', $txt['save'], '" class="button floatright">
				<br class="clear">
			</form>
		</details>';
}

/**
 * One comment (and, for top-level ones, its replies + reply box).
 */
function template_pw_comment($c, $is_reply)
{
	global $context, $txt;

	$pw = $context['pw'];
	$classes = array('pw_comment');
	$classes[] = $is_reply ? 'pw_is_reply' : 'windowbg';
	if ($c['is_pinned'])
		$classes[] = 'pw_pinned';
	if ($c['is_pending'])
		$classes[] = 'pw_pending';

	echo '
			<div class="', implode(' ', $classes), '" id="pw', $c['id'], '">
				<div class="pw_poster">
					', !empty($c['poster']['avatar']) ? '<div class="pw_avatar">' . $c['poster']['avatar'] . '</div>' : '<div class="pw_avatar pw_avatar_blank"></div>';

	if (!$is_reply)
	{
		echo '
					<div class="pw_poster_name">', $c['poster']['link'], $c['poster']['is_online'] ? ' <span class="pw_online" title="' . $txt['online'] . '"></span>' : '', '</div>
					<div class="pw_poster_group smalltext">', $c['poster']['group'], '</div>';
		if ($c['poster']['posts'] !== '')
			echo '
					<div class="pw_poster_posts smalltext">', $txt['posts'], ': ', $c['poster']['posts'], '</div>';
	}

	echo '
				</div>
				<div class="pw_main">
					<div class="pw_meta">';

	if ($is_reply)
		echo '
						<strong>', $c['poster']['link'], '</strong>';
	if ($c['poster']['is_wall_owner'])
		echo '
						<span class="pw_badge pw_badge_owner">', $txt['pw_badge_owner'], '</span>';
	if ($c['is_pinned'])
		echo '
						<span class="pw_badge pw_badge_pinned">', $txt['pw_badge_pinned'], '</span>';
	if ($c['is_pending'])
		echo '
						<span class="pw_badge pw_badge_pending">', $txt['pw_badge_pending'], '</span>';

	echo '
						<a href="', $c['href'], '" class="pw_time smalltext" title="', $txt['pw_permalink'], '">', $c['time'], '</a>';

	if (!empty($c['modified']))
		echo '
						<span class="pw_edited smalltext">', sprintf($txt['pw_edited_by'], $c['modified']['name'], $c['modified']['time']), '</span>';

	echo '
					</div>
					<div class="pw_body">', $c['body'], '</div>
					<ul class="quickbuttons pw_buttons">';

	if ($c['can_like'] || $c['likes'] > 0)
	{
		if ($c['can_like'])
			echo '
						<li><a href="', $c['urls']['like'], '" class="pw_like', $c['liked'] ? ' pw_liked' : '', '"><span class="main_icons ', $c['liked'] ? 'unlike' : 'like', '"></span> ', $c['liked'] ? $txt['pw_unlike'] : $txt['pw_like'], $c['likes'] > 0 ? ' (' . $c['likes'] . ')' : '', '</a></li>';
		else
			echo '
						<li><span class="pw_like_count"><span class="main_icons like"></span> ', $c['likes'], '</span></li>';
	}
	if ($c['can_approve'])
		echo '
						<li><a href="', $c['urls']['approve'], '"><span class="main_icons approve"></span> ', $txt['pw_approve'], '</a></li>';
	if ($c['can_pin'])
		echo '
						<li><a href="', $c['urls']['pin'], '"><span class="main_icons sticky"></span> ', $c['is_pinned'] ? $txt['pw_unpin'] : $txt['pw_pin'], '</a></li>';
	if ($c['can_edit'])
		echo '
						<li><a href="', $c['urls']['edit'], '"><span class="main_icons modify_button"></span> ', $txt['modify'], '</a></li>';
	if ($c['can_delete'])
		echo '
						<li><a href="', $c['urls']['delete'], '" class="you_sure" data-confirm="', $txt['pw_delete_confirm'], '"><span class="main_icons remove_button"></span> ', $txt['remove'], '</a></li>';
	if ($c['can_report'])
		echo '
						<li><a href="', $c['urls']['report'], '"><span class="main_icons warning"></span> ', $txt['pw_report'], '</a></li>';

	echo '
					</ul>';

	// Replies.
	if (!$is_reply)
	{
		if (!empty($c['replies']))
		{
			echo '
					<div class="pw_replies">';
			foreach ($c['replies'] as $reply)
				template_pw_comment($reply, true);
			echo '
					</div>';
		}

		if (!empty($c['can_reply']))
		{
			echo '
					<details class="pw_reply_box">
						<summary>', $txt['pw_reply'], '</summary>
						<form action="', $pw['url'], ';sa=post" method="post" accept-charset="', $context['character_set'], '">
							<textarea name="pw_reply_body" rows="3" required', !empty($pw['max_length']) ? ' data-pw-max="' . $pw['max_length'] . '"' : '', ' placeholder="', $txt['pw_reply_placeholder'], '"></textarea>
							<div class="pw_reply_actions">
								<span class="pw_counter smalltext"></span>
								<input type="hidden" name="parent" value="', $c['id'], '">
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
								<input type="submit" value="', $txt['pw_reply'], '" class="button">
							</div>
						</form>
					</details>';
		}
	}

	echo '
				</div>
			</div>';
}

/**
 * Edit form.
 */
function template_pw_edit()
{
	global $context, $txt;

	$pw = $context['pw'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $pw['title'], '</h3>
	</div>
	<form action="', $pw['url'], ';sa=edit;cid=', $pw['cid'], '" method="post" accept-charset="', $context['character_set'], '" class="windowbg pw_post_form">';

	if ($pw['is_reply'])
		echo '
		<textarea name="pw_reply_body" rows="5" required', !empty($pw['max_length']) ? ' data-pw-max="' . $pw['max_length'] . '"' : '', '>', htmlspecialchars(un_htmlspecialchars($pw['value']), ENT_QUOTES, 'UTF-8'), '</textarea>
		<span class="pw_counter smalltext"></span>';
	else
		template_control_richedit('pw_body', null, null);

	echo '
		<div class="pw_form_buttons">
			<a href="', $pw['url'], '#pw', $pw['cid'], '" class="button">', $txt['pw_cancel'], '</a>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="save" value="', $txt['save'], '" class="button">
		</div>
	</form>';
}

/**
 * Report form.
 */
function template_pw_report()
{
	global $context, $txt;

	$pw = $context['pw'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $pw['title'], '</h3>
	</div>
	<form action="', $pw['url'], ';sa=report;cid=', $pw['cid'], '" method="post" accept-charset="', $context['character_set'], '" class="windowbg pw_post_form">
		<p>', $txt['pw_report_intro'], '</p>
		<textarea name="reason" rows="4" maxlength="250" required data-pw-max="250"></textarea>
		<span class="pw_counter smalltext"></span>
		<div class="pw_form_buttons">
			<a href="', $pw['url'], '#pw', $pw['cid'], '" class="button">', $txt['pw_cancel'], '</a>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="save" value="', $txt['pw_report_send'], '" class="button">
		</div>
	</form>';
}

/**
 * Summary page layer: latest wall comments under the normal summary.
 */
function template_pw_summary_above()
{
}

function template_pw_summary_below()
{
	global $context, $txt;

	if (empty($context['pw_summary']))
		return;

	$s = $context['pw_summary'];

	echo '
	<div class="pw_summary">
		<div class="cat_bar">
			<h3 class="catbg"><span class="main_icons members"></span> ', $txt['pw_summary_title'], '</h3>
		</div>
		<div class="windowbg">';

	if (empty($s['items']))
		echo '
			<p class="centertext">', $txt['pw_no_comments'], '</p>';
	else
	{
		echo '
			<ul class="pw_summary_list">';
		foreach ($s['items'] as $item)
			echo '
				<li>
					', !empty($item['avatar']) ? '<span class="pw_avatar pw_avatar_small">' . $item['avatar'] . '</span>' : '<span class="pw_avatar pw_avatar_small pw_avatar_blank"></span>', '
					<div>
						<strong>', $item['poster'], '</strong>
						<a href="', $item['href'], '" class="smalltext pw_time">', $item['time'], '</a><br>
						<span class="pw_excerpt">', $item['excerpt'], '</span>
					</div>
				</li>';
		echo '
			</ul>';
	}

	echo '
			<a href="', $s['url'], '" class="button floatright">', $txt['pw_view_wall'], '</a>
			<br class="clear">
		</div>
	</div>';
}

/**
 * Admin: approval queue.
 */
function template_pw_admin_queue()
{
	global $context, $txt, $scripturl;

	$a = $context['pw_admin'];

	echo '
	<form action="', $scripturl, '?action=admin;area=profilewall;sa=queue" method="post" accept-charset="', $context['character_set'], '">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['pw_admin_queue'], '</h3>
		</div>';

	if (empty($a['items']))
		echo '
		<div class="windowbg centertext">', $txt['pw_queue_empty'], '</div>';
	else
	{
		echo '
		<div class="pagesection"><div class="pagelinks">', $a['page_index'], '</div></div>
		<table class="table_grid pw_admin_table">
			<thead>
				<tr class="title_bar">
					<th class="centercol"><input type="checkbox" onclick="invertAll(this, this.form, \'items[]\');"></th>
					<th>', $txt['pw_col_comment'], '</th>
					<th>', $txt['pw_col_poster'], '</th>
					<th>', $txt['pw_col_wall'], '</th>
					<th>', $txt['pw_col_time'], '</th>
				</tr>
			</thead>
			<tbody>';

		foreach ($a['items'] as $item)
			echo '
				<tr class="windowbg">
					<td class="centercol"><input type="checkbox" name="items[]" value="', $item['id'], '"></td>
					<td class="pw_admin_body">', $item['is_reply'] ? '<span class="pw_badge">' . $txt['pw_badge_reply'] . '</span> ' : '', $item['body'], '</td>
					<td>', $item['poster'], '</td>
					<td>', $item['wall'], '</td>
					<td class="smalltext">', $item['time'], '</td>
				</tr>';

		echo '
			</tbody>
		</table>
		<div class="pagesection">
			<div class="pagelinks">', $a['page_index'], '</div>
			<div class="buttonlist floatright">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="do_approve" value="', $txt['pw_approve_selected'], '" class="button">
				<input type="submit" name="do_delete" value="', $txt['pw_delete_selected'], '" class="button you_sure" data-confirm="', $txt['pw_delete_confirm'], '">
			</div>
		</div>';
	}

	echo '
	</form>';
}

/**
 * Admin: open reports.
 */
function template_pw_admin_reports()
{
	global $context, $txt, $scripturl;

	$a = $context['pw_admin'];
	$session = $context['session_var'] . '=' . $context['session_id'];

	echo '
	<div class="cat_bar">
		<h3 class="catbg">', $txt['pw_admin_reports'], '</h3>
	</div>';

	if (empty($a['items']))
	{
		echo '
	<div class="windowbg centertext">', $txt['pw_reports_empty'], '</div>';
		return;
	}

	foreach ($a['items'] as $item)
		echo '
	<div class="windowbg pw_report_item">
		<div class="pw_meta smalltext">
			', sprintf($txt['pw_reported_by'], $item['reporter'], $item['reported']), ' &middot;
			', $txt['pw_col_poster'], ': ', $item['poster'], ' &middot; ', $txt['pw_col_wall'], ': ', $item['wall'], '
		</div>
		<blockquote class="pw_reason"><strong>', $txt['pw_reason'], ':</strong> ', $item['reason'], '</blockquote>
		<div class="pw_body">', $item['body'], '</div>
		<ul class="quickbuttons">
			<li><a href="', $scripturl, '?action=admin;area=profilewall;sa=reports;close=', $item['report_id'], ';', $session, '"><span class="main_icons approve"></span> ', $txt['pw_dismiss'], '</a></li>
			<li><a href="', $scripturl, '?action=admin;area=profilewall;sa=reports;remove=', $item['report_id'], ';', $session, '" class="you_sure" data-confirm="', $txt['pw_delete_confirm'], '"><span class="main_icons remove_button"></span> ', $txt['pw_delete_comment'], '</a></li>
		</ul>
	</div>';
}
