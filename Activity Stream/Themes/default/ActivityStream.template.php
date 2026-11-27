<?php

/**
 * Activity Stream: templates.
 *
 * @package ActivityStream
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.2
 */

function template_as_notice()
{
	global $context;

	if (!empty($context['as_notice']))
	{
		list ($type, $text) = is_array($context['as_notice']) ? $context['as_notice'] : array('info', $context['as_notice']);
		echo '
	<div class="', $type === 'error' ? 'errorbox' : 'infobox', '">', $text, '</div>';
	}
}

function template_as_tabs()
{
	global $context, $txt;

	echo '
	<div class="as_header">
		<nav class="as_tabs" aria-label="', $txt['as_streams_nav'], '">';
	foreach ($context['as_tabs'] as $key => $tab)
		echo '
			<a href="', $context['as_url'], ';stream=', $key, '" class="as_tab', $context['as_current'] === $key ? ' as_tab_active" aria-current="page' : '', !empty($tab['custom']) ? ' as_tab_custom' : '', '">', $tab['label'], '</a>';
	echo '
		</nav>';

	if (ActivityStream::canCustomise())
		echo '
		<div class="as_header_links">
			<a href="', $context['as_url'], ';sa=streams">', $txt['as_manage_streams'], '</a>
			<a href="', $context['as_url'], ';sa=following">', $txt['as_manage_following'], '</a>
		</div>';

	echo '
	</div>';
}

function template_as_main()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['as_title'], '</h3></div>';

	template_as_notice();
	template_as_tabs();

	$current = $context['as_current'];
	$tab = $context['as_tabs'][$current];

	echo '
	<div class="windowbg as_wrap">';

	if (!empty($context['as_can_customise']))
	{
		echo '
		<div class="as_toolbar">';
		if (!empty($context['as_summary']))
			echo '
			<span class="as_summary">', $context['as_summary'], '</span>
			<a href="', $context['as_url'], ';sa=editstream;id=', (int) substr($current, 1), '">', $txt['as_edit'], '</a>';
		if ($context['as_default'] !== $current)
			echo '
			<a href="', $context['as_url'], ';stream=', $current, ';setdefault', $context['as_session'], '" class="as_set_default">', $txt['as_make_default'], '</a>';
		else
			echo '
			<span class="as_is_default">', $txt['as_is_default'], '</span>';
		echo '
		</div>';
	}

	if ($current === 'popular')
		template_as_popular();
	elseif (!empty($context['as_empty_following']))
		echo '
		<div class="as_empty">
			<p><strong>', $txt['as_follow_nobody'], '</strong></p>
			<p>', $txt['as_follow_nobody_desc'], '</p>
			<a class="button" href="', $context['as_url'], ';sa=following">', $txt['as_find_people'], '</a>
		</div>';
	else
		template_as_timeline($context['as_items'], $context['as_next']);

	echo '
	</div>';
}

function template_as_timeline(array $items, $next)
{
	global $txt;

	if (empty($items))
	{
		echo '
		<div class="as_empty"><p>', $txt['as_nothing'], '</p></div>';
		return;
	}

	echo '
		<div class="as_timeline" data-as-timeline>';
	template_as_groups($items);
	echo '
		</div>';

	template_as_more($next);
}

function template_as_groups(array $items)
{
	$day = null;

	foreach ($items as $item)
	{
		if ($item['day'] !== $day)
		{
			if ($day !== null)
				echo '
			</ol>';
			$day = $item['day'];
			echo '
			<h4 class="as_day" data-day="', $item['day'], '">', $item['day_label'], '</h4>
			<ol class="as_items" data-day="', $item['day'], '">';
		}

		template_as_item($item);
	}

	if ($day !== null)
		echo '
			</ol>';
}

function template_as_item(array $i)
{
	echo '
				<li class="as_item as_type_', preg_replace('~[^a-z0-9_]~i', '', $i['type']), '">
					<div class="as_avatar">', $i['avatar'] !== '' ? '<img src="' . htmlspecialchars($i['avatar'], ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">' : '<span class="as_avatar_blank"></span>', '<span class="as_badge" aria-hidden="true"></span></div>
					<div class="as_body">
						<div class="as_line">', $i['line'], '</div>';

	if ($i['snippet'] !== '')
		echo '
						<a class="as_snippet" href="', $i['url'], '">', $i['snippet'], '</a>';

	echo '
						<div class="as_meta"><time datetime="', gmdate('c', $i['time']), '" title="', htmlspecialchars(strip_tags($i['timestamp']), ENT_QUOTES, 'UTF-8'), '">', $i['when'], '</time>';
	foreach ($i['meta'] as $m)
		echo ' <span>· ', $m, '</span>';
	echo '</div>
					</div>
				</li>';
}

function template_as_more($next)
{
	global $txt;

	if ($next === '')
		return;

	echo '
		<div class="as_more">
			<a class="button" href="', $next, '" data-as-more data-loading="', $txt['as_loading'], '">', $txt['as_load_more'], '</a>
		</div>';
}

/**
 * "Load more": only the new items and the next link.
 */
function template_as_fragment()
{
	global $context;

	echo '<div data-as-fragment>';
	if (!empty($context['as_items']))
		template_as_groups($context['as_items']);
	template_as_more($context['as_next']);
	echo '</div>';
}

function template_as_popular()
{
	global $context, $txt, $scripturl;

	echo '
		<p class="as_popular_intro smalltext">', sprintf($txt['as_popular_intro'], $context['as_popular_days']), '</p>';

	if (empty($context['as_popular']))
	{
		echo '
		<div class="as_empty"><p>', $txt['as_nothing_popular'], '</p></div>';
		return;
	}

	$top = max(1, reset($context['as_popular'])['score']);

	echo '
		<ol class="as_popular">';
	$rank = 0;
	foreach ($context['as_popular'] as $id => $t)
	{
		$rank++;
		echo '
			<li>
				<span class="as_rank">', $rank, '</span>
				<div class="as_pop_body">
					<a class="as_pop_title" href="', $scripturl, '?topic=', $id, '.new#new">', $t['subject'], '</a>
					<div class="as_meta">', sprintf($txt['as_popular_meta'], '<a href="' . $scripturl . '?board=' . $t['id_board'] . '.0">' . $t['board_name'] . '</a>', $t['starter']), '</div>
					<div class="as_heat"><div style="width:', round($t['score'] / $top * 100), '%"></div></div>
					<div class="as_meta">', sprintf($txt['as_popular_counts'], comma_format($t['posts']), comma_format($t['likes'])), ' · ', sprintf($txt['as_last_post_by'], $t['last_poster']), '</div>
				</div>
			</li>';
	}
	echo '
		</ol>';
}

function template_as_following()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['as_title'], '</h3></div>';
	template_as_notice();
	template_as_tabs();

	echo '
	<div class="windowbg as_wrap">
		<form class="as_add_follow" action="', $context['as_url'], ';sa=following" method="post" accept-charset="UTF-8">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<label for="as_follow_name">', $txt['as_follow_by_name'], '</label>
			<input type="text" id="as_follow_name" name="name" size="30" maxlength="80" required>
			<input type="submit" name="add" value="', $txt['as_follow'], '" class="button">
		</form>';

	foreach (array('following', 'followers') as $list)
	{
		echo '
		<h4 class="as_list_title">', $txt['as_list_' . $list], ' <span class="as_count">', count($context['as_people'][$list]), '</span></h4>';

		if (empty($context['as_people'][$list]))
		{
			echo '
		<p class="smalltext">', $txt['as_list_' . $list . '_empty'], '</p>';
			continue;
		}

		echo '
		<ul class="as_people">';
		foreach ($context['as_people'][$list] as $p)
			echo '
			<li>
				<span class="as_avatar">', $p['avatar'] !== '' ? '<img src="' . htmlspecialchars($p['avatar'], ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">' : '<span class="as_avatar_blank"></span>', '</span>
				<span class="as_person">', $p['link'], '</span>
				<a class="button" href="', $context['as_url'], ';sa=follow;u=', $p['id'], $p['i_follow'] ? ';unfollow=1' : '', ';back=following', $context['as_session'], '">', $p['i_follow'] ? $txt['as_unfollow'] : $txt['as_follow_back'], '</a>
			</li>';
		echo '
		</ul>';
	}

	echo '
	</div>';
}

function template_as_streams()
{
	global $context, $txt;

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['as_title'], '</h3></div>';
	template_as_notice();
	template_as_tabs();

	echo '
	<div class="windowbg as_wrap">
		<p>', $txt['as_streams_intro'], '</p>';

	if ($context['as_max_streams'] === 0)
		echo '
		<div class="noticebox">', $txt['as_streams_off'], '</div>';
	elseif (count($context['as_streams']) < $context['as_max_streams'])
		echo '
		<p><a class="button" href="', $context['as_url'], ';sa=editstream">', $txt['as_new_stream'], '</a></p>';
	else
		echo '
		<p class="smalltext">', sprintf($txt['as_stream_limit_reached'], $context['as_max_streams']), '</p>';

	if (empty($context['as_streams']))
		echo '
		<p class="smalltext">', $txt['as_no_streams'], '</p>';
	else
	{
		echo '
		<ul class="as_stream_list">';
		foreach ($context['as_streams'] as $key => $s)
			echo '
			<li', $s['active'] ? '' : ' class="as_stream_inactive"', '>
				<div>', $s['active'] ? '<a href="' . $context['as_url'] . ';stream=' . $key . '"><strong>' . $s['name'] . '</strong></a>' : '<strong>' . $s['name'] . '</strong> <span class="as_is_hidden">' . $txt['as_stream_hidden'] . '</span>', $s['active'] && $context['as_default'] === $key ? ' <span class="as_is_default">' . $txt['as_is_default'] . '</span>' : '', '<br><span class="smalltext">', $s['summary'], '</span></div>
				<div class="as_stream_actions">', $s['active'] ? '
					<a class="button" href="' . $context['as_url'] . ';sa=editstream;id=' . $s['id'] . '">' . $txt['as_edit'] . '</a>' : '', '
					<a class="button" href="', $context['as_url'], ';sa=streams;delete=', $s['id'], $context['as_session'], '" data-as-confirm="', htmlspecialchars($txt['as_confirm_delete'], ENT_QUOTES, 'UTF-8'), '">', $txt['as_delete'], '</a>
				</div>
			</li>';
		echo '
		</ul>';
	}

	echo '
	</div>';
}

/**
 * Safe for an HTML attribute. Names are stored escaped (SMF style), so
 * decode first: a saved "A &amp; B" isn't shown as "A &amp;amp; B", and
 * anything not yet escaped is still escaped.
 */
function as_attr($value)
{
	return htmlspecialchars(htmlspecialchars_decode((string) $value, ENT_QUOTES), ENT_QUOTES, 'UTF-8');
}

function template_as_edit_stream()
{
	global $context, $txt;

	$s = $context['as_stream'];
	$c = $s['config'];

	echo '
	<div class="cat_bar"><h3 class="catbg">', $context['page_title'], '</h3></div>';

	if (!empty($context['as_errors']))
	{
		echo '
	<div class="errorbox"><ul>';
		foreach ($context['as_errors'] as $e)
			echo '<li>', $e, '</li>';
		echo '</ul></div>';
	}

	echo '
	<form class="windowbg as_builder" action="', $context['as_url'], ';sa=editstream', $s['id'] ? ';id=' . $s['id'] : '', '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<dl class="settings">
			<dt><label for="as_name">', $txt['as_stream_name'], '</label></dt>
			<dd><input type="text" id="as_name" name="name" value="', as_attr($s['name']), '" maxlength="40" required size="30"></dd>

			<dt>', $txt['as_stream_show'], '</dt>
			<dd>';
	foreach ($context['as_types'] as $key => $label)
		echo '
				<label class="as_chip"><input type="checkbox" name="types[]" value="', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), '"', in_array($key, $c['types'], true) ? ' checked' : '', '> ', $label, '</label>';
	echo '
			</dd>

			<dt>', $txt['as_stream_from'], '</dt>
			<dd>';
	foreach (array('all', 'followed', 'me') as $m)
		echo '
				<label class="as_radio"><input type="radio" name="members" value="', $m, '"', $c['members'] === $m ? ' checked' : '', '> ', $txt['as_members_' . $m], '</label>';
	echo '
				<label class="as_radio"><input type="checkbox" name="own" value="1"', $c['own'] ? ' checked' : '', '> ', $txt['as_include_own'], '</label>
			</dd>

			<dt><label for="as_days">', $txt['as_stream_period'], '</label></dt>
			<dd><select id="as_days" name="days">';
	foreach (array(0, 1, 7, 30, 90, 365) as $d)
		echo '<option value="', $d, '"', $c['days'] === $d ? ' selected' : '', '>', $d === 0 ? $txt['as_any_time'] : sprintf($txt['as_last_n_days'], $d), '</option>';
	echo '</select></dd>

			<dt>', $txt['as_stream_boards'], '<br><span class="smalltext">', $txt['as_stream_boards_desc'], '</span></dt>
			<dd>
				<label><input type="checkbox" name="all_boards" value="1" id="as_all_boards"', empty($c['boards']) ? ' checked' : '', '> <strong>', $txt['as_all_boards'], '</strong></label>
				<div class="as_board_list" id="as_board_list">';
	$cat = null;
	foreach ($context['as_boards'] as $id => $b)
	{
		if ($b['cat_name'] !== $cat)
		{
			$cat = $b['cat_name'];
			echo '
					<strong class="as_board_cat">', $cat, '</strong>';
		}
		echo '
					<label style="margin-left:', 1 + (int) $b['child_level'] * 1.25, 'em"><input type="checkbox" name="boards[]" value="', $id, '"', in_array((int) $id, $c['boards'], true) ? ' checked' : '', '> ', $b['name'], '</label>';
	}
	echo '
				</div>
			</dd>

			<dt><label for="as_make_default">', $txt['as_make_default'], '</label></dt>
			<dd><input type="checkbox" id="as_make_default" name="make_default" value="1"', !empty($context['as_is_default_stream']) ? ' checked' : '', '></dd>
		</dl>
		<input type="submit" name="save" value="', $txt['as_save_stream'], '" class="button">
		<a class="button" href="', $context['as_url'], ';sa=streams">', $txt['as_cancel'], '</a>
	</form>';
}

function template_as_profile()
{
	global $context, $txt;

	$m = $context['as_member'];

	echo '
	<div class="cat_bar"><h3 class="catbg">', $txt['as_profile_label'], '</h3></div>
	<div class="windowbg as_wrap">
		<div class="as_profile_head">
			<div class="as_counts">
				<span><strong data-as-followers>', comma_format($m['counts']['followers']), '</strong> ', $txt['as_followers'], '</span>
				<span><strong>', comma_format($m['counts']['following']), '</strong> ', $txt['as_following'], '</span>
			</div>';

	if ($m['can_follow'])
		echo '
			<a class="button as_follow', $m['following'] ? ' as_following' : '', '" data-as-follow
				data-follow="', $context['as_url'], ';sa=follow;u=', $m['id'], $context['as_session'], '"
				data-unfollow="', $context['as_url'], ';sa=follow;u=', $m['id'], ';unfollow=1', $context['as_session'], '"
				data-label-follow="', htmlspecialchars($txt['as_follow'], ENT_QUOTES, 'UTF-8'), '" data-label-unfollow="', htmlspecialchars($txt['as_unfollow'], ENT_QUOTES, 'UTF-8'), '"
				href="', $context['as_url'], ';sa=follow;u=', $m['id'], $m['following'] ? ';unfollow=1' : '', $context['as_session'], '">', $m['following'] ? $txt['as_unfollow'] : $txt['as_follow'], '</a>';
	elseif ($m['own'])
		echo '
			<a class="button" href="', $context['as_url'], ';sa=following">', $txt['as_manage_following'], '</a>';

	echo '
		</div>';

	template_as_timeline($context['as_items'], $context['as_next']);

	echo '
	</div>';
}
