<?php
/**
 * Community Insights for SMF 2.1.x - accessible, responsive dashboard.
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */
if (!defined('SMF'))
	die('No direct access...');

function template_ci_card($label, $value, $foot = '')
{
	echo '<div class="ci-card"><div class="ci-label">', CommunityInsights::h($label), '</div><div class="ci-value">', comma_format((float) $value, ($value == (int) $value ? 0 : 1)), '</div>';
	if ($foot !== '')
		echo '<div class="smalltext">', CommunityInsights::h($foot), '</div>';
	echo '</div>';
}

function template_ci_header($id, $title)
{
	$ci = isset($GLOBALS['context']['ci']) ? $GLOBALS['context']['ci'] : array();
    $widths = isset($ci['layout_meta']['widths']) ? $ci['layout_meta']['widths'] : array();
    $width = isset($widths[$id]) && (int) $widths[$id] === 1 ? ' ci-half' : ' ci-full';
    // On the dashboard, use the member's saved widget order. Other panels go after the widgets.
    $order = '';
    if (isset($ci['layout']) && is_array($ci['layout']))
    {
        $position = array_search($id, $ci['layout'], true);
        $order = ' style="order: ' . ($position === false ? 1000 : $position + 1) . '"';
    }
    echo '<section class="ci-section', $width, '" id="ci-', CommunityInsights::h($id), '"', $order, '><div class="cat_bar"><h3 class="catbg">', CommunityInsights::h($title), '</h3></div><div class="windowbg ci-panel">';
}
function template_ci_end()
{
	echo '</div></section>';
}

function template_ci_dashboard()
{
	global $context, $scripturl, $txt, $modSettings;
	$ci = $context['ci'];
	$d = $ci['data'];
	$dates = $ci['dates'];
	$base = $scripturl . '?action=communityinsights';

	echo '<div class="ci-dashboard"><div class="title_bar"><h3 class="titlebg">', CommunityInsights::h($txt['ci_title']), '</h3></div>';
    template_ci_nav();
    echo '<p class="description">', CommunityInsights::h($txt['ci_intro']), '</p>';
	echo '<form method="get" action="', CommunityInsights::h($scripturl), '" class="windowbg ci-period">
		<input type="hidden" name="action" value="communityinsights">
		<label for="ci-days">', CommunityInsights::h($txt['ci_period']), '</label>
		<select name="days" id="ci-days">';
	foreach (array(7, 30, 90, 365) as $days)
		echo '<option value="', $days, '"', !$dates['custom'] && $dates['days'] == $days ? ' selected' : '', '>', $days, ' ', CommunityInsights::h($txt['ci_days']), '</option>';
	echo '</select><label for="ci-from">', CommunityInsights::h($txt['ci_from']), '</label><input type="date" name="from" id="ci-from" value="', !empty($_GET['from']) && $dates['custom'] ? CommunityInsights::h($_GET['from']) : '', '"><label for="ci-to">', CommunityInsights::h($txt['ci_to']), '</label><input type="date" name="to" id="ci-to" value="', !empty($_GET['to']) && $dates['custom'] ? CommunityInsights::h($_GET['to']) : '', '"><button class="button" type="submit">', CommunityInsights::h($txt['ci_apply']), '</button></form>';

	if (isset($d['overview']) && in_array('overview', $ci['layout'], true))
	{
		template_ci_header('overview', $txt['ci_widget_overview']);
		echo '<div class="ci-grid">';
		template_ci_card($txt['ci_posts'], $d['overview']['posts'], $txt['ci_previous'] . ': ' . $d['overview']['previous_posts']);
		template_ci_card($txt['ci_topics'], $d['overview']['topics']);
		template_ci_card($txt['ci_contributors'], $d['overview']['contributors'], $txt['ci_previous'] . ': ' . $d['overview']['previous_contributors']);
		echo '</div>';
		template_ci_end();
	}
	if (isset($d['smf']) && in_array('smf', $ci['layout'], true))
	{
		template_ci_header('smf', $txt['ci_widget_smf']);
		echo '<p class="smalltext">', CommunityInsights::h($txt['ci_visible_scope']), '</p><div class="ci-grid">';
		template_ci_card($txt['ci_topics'], $d['smf']['topics']);
		template_ci_card($txt['ci_messages'], $d['smf']['messages']);
		template_ci_card($txt['ci_views'], $d['smf']['views']);
		echo '</div>';
		template_ci_end();
	}

	if (isset($d['activity']) && in_array('activity', $ci['layout'], true))
	{
		template_ci_header('activity', $txt['ci_widget_activity']);
		$max = 0;
		foreach ($d['activity'] as $period)
			$max = max($max, $period['posts']);
		echo '<div class="ci-chart" role="img" aria-label="', CommunityInsights::h($txt['ci_activity_chart']), '">';
		foreach ($d['activity'] as $period)
		{
			$height = $max > 0 ? max(3, round(100 * $period['posts'] / $max)) : 3;
			echo '<div class="ci-bar-slot"><div class="ci-bar" style="height:', (int) $height, '%" title="', CommunityInsights::h($period['date'] . ': ' . $period['posts']), '"></div><span class="smalltext">', CommunityInsights::h($period['date']), '</span></div>';
		}
		echo '</div>';
		template_ci_end();
	}
	if (isset($d['popular']) && in_array('popular', $ci['layout'], true))
	{
		template_ci_header('popular', $txt['ci_widget_popular']);
		echo '<div class="ci-overflow"><table class="table_grid ci-table"><thead><tr><th>', CommunityInsights::h($txt['ci_topics']), '</th><th>', CommunityInsights::h($txt['ci_views']), '</th><th>', CommunityInsights::h($txt['ci_replies']), '</th></tr></thead><tbody>';
		foreach ($d['popular'] as $topic)
			echo '<tr><td><a href="', CommunityInsights::h($scripturl . '?topic=' . (int) $topic['id_topic'] . '.0'), '">', $topic['subject'], '</a><div class="smalltext">', $topic['board_name'], '</div></td><td>', (int) $topic['num_views'], '</td><td>', (int) $topic['num_replies'], '</td></tr>';
		echo '</tbody></table></div>';
		template_ci_end();
	}

	if (isset($d['discussion']) && in_array('discussion', $ci['layout'], true))
	{
		template_ci_header('discussion', $txt['ci_widget_discussion']);
		$disc = $d['discussion'];
		echo '<div class="ci-grid">';
		template_ci_card($txt['ci_eligible'], $disc['eligible']);
		template_ci_card($txt['ci_responded'], $disc['responded']);
		if ($disc['eligible'] > 0)
			template_ci_card($txt['ci_response_rate'], round(100 * $disc['responded'] / $disc['eligible'], 1), '%');
		echo '</div><p class="smalltext">', sprintf(CommunityInsights::h($txt['ci_response_threshold']), $disc['hours']), '</p>';
		if ($disc['unanswered'])
		{
			echo '<h4>', CommunityInsights::h($txt['ci_unanswered']), '</h4><ul class="ci-list">';
			foreach ($disc['unanswered'] as $topic)
				echo '<li><a href="', CommunityInsights::h($scripturl . '?topic=' . (int) $topic['id_topic'] . '.0'), '">', $topic['subject'], '</a> <span class="smalltext">(', $topic['board_name'], ')</span></li>';
			echo '</ul>';
		}
		template_ci_end();
	}
	if (isset($d['boards']) && in_array('boards', $ci['layout'], true))
	{
		template_ci_header('boards', $txt['ci_widget_boards']);
		echo '<div class="ci-overflow"><table class="table_grid ci-table"><thead><tr><th>', CommunityInsights::h($txt['ci_board']), '</th><th>', CommunityInsights::h($txt['ci_posts']), '</th><th>', CommunityInsights::h($txt['ci_contributors']), '</th></tr></thead><tbody>';
		foreach ($d['boards'] as $board)
			echo '<tr><td><a href="', CommunityInsights::h($scripturl . '?board=' . (int) $board['id_board'] . '.0'), '">', $board['name'], '</a></td><td>', (int) $board['posts'], '</td><td>', (int) $board['contributors'], '</td></tr>';
		echo '</tbody></table></div>';
		template_ci_end();
	}
	if (isset($d['members']) && in_array('members', $ci['layout'], true))
	{
		template_ci_header('members', $txt['ci_widget_members']);
		echo '<div class="ci-grid">';
		template_ci_card($txt['ci_registered'], $d['members']['joined']);
		template_ci_card($txt['ci_matured'], $d['members']['matured']);
		template_ci_card($txt['ci_first7'], $d['members']['first7']);
		template_ci_card($txt['ci_returned30'], $d['members']['returned30']);
		echo '</div><p class="smalltext">', CommunityInsights::h($txt['ci_cohort_note']), '</p>';
		template_ci_end();
	}
	if (isset($d['moderation']) && in_array('moderation', $ci['layout'], true))
	{
		template_ci_header('moderation', $txt['ci_widget_moderation']);
		echo '<div class="ci-grid">';
		template_ci_card($txt['ci_pending'] . ' (currently pending, posted in period)', $d['moderation']['pending']);
		template_ci_card($txt['ci_reports'] . ' (currently open, filed in period)', $d['moderation']['open_reports']);
		template_ci_card('Reports filed in selected period', $d['moderation']['reports_created']);
		echo '</div><p class="smalltext">Current pending/open status for items created during the selected period. Historic moderation status cannot be reconstructed from SMF records.</p>';
		template_ci_end();
	}
	if (isset($d['recommendations']) && in_array('recommendations', $ci['layout'], true))
	{
		template_ci_header('recommendations', $txt['ci_widget_recommendations']);
		if (!$d['recommendations'])
			echo '<p>', CommunityInsights::h($txt['ci_no_advice']), '</p>';
		else
		{
			echo '<ul class="ci-list">';
			foreach ($d['recommendations'] as $key)
				echo '<li>', CommunityInsights::h($txt[$key]), '</li>';
			echo '</ul>';
		}
		template_ci_end();
	}
	if (isset($d['integrations']) && in_array('integrations', $ci['layout'], true))
	{
		template_ci_header('integrations', $txt['ci_widget_integrations']);
		if (!$d['integrations'])
			echo '<p>', CommunityInsights::h($txt['ci_no_integrations']), '</p>';
		else
			foreach ($d['integrations'] as $provider)
			{
				$title = isset($provider['title_text']) ? $provider['title_text'] : (isset($txt[$provider['title']]) ? $txt[$provider['title']] : $provider['id']);
				echo '<div class="ci-provider"><h4>', CommunityInsights::h($title), '</h4><div class="ci-grid">';
				foreach ($provider['items'] as $label => $value)
					template_ci_card(isset($txt[$label]) ? $txt[$label] : $label, $value);
				echo '</div></div>';
			}
		template_ci_end();
	}

	template_ci_header('layout', $txt['ci_customize']);
	echo '<form method="post" action="', CommunityInsights::h($base . ';sa=save'), '">';
    echo '<label>Dashboard preset <select name="ci_preset" id="ci-preset">';
    $currentPreset = isset($ci['layout_meta']['preset']) ? $ci['layout_meta']['preset'] : 'custom';
    foreach (array('custom' => 'Custom', 'general' => 'General overview', 'growth' => 'Member growth', 'moderation' => 'Moderation', 'engagement' => 'Engagement') as $preset => $label)
        echo '<option value="', $preset, '"', $currentPreset === $preset ? ' selected' : '', '>', $label, '</option>';
    echo '</select></label><p class="smalltext">Drag widgets or use the arrows to change the order. Choose half-width or full-width panels.</p><div class="ci-widget-list" id="ci-widget-list">';
    $order = array_unique(array_merge($ci['layout'], $ci['allowed']));
    foreach ($order as $widget)
    {
        $widths = isset($ci['layout_meta']['widths']) ? $ci['layout_meta']['widths'] : array();
        $width = isset($widths[$widget]) && (int) $widths[$widget] === 1 ? 1 : 2;
        echo '<div draggable="true" class="ci-widget-choice"><label><input type="checkbox" name="widgets[]" value="', CommunityInsights::h($widget), '"', in_array($widget, $ci['layout'], true) ? ' checked' : '', '> ', CommunityInsights::h($txt['ci_widget_' . $widget]), '</label>';
        echo '<label>Width <select name="ci_sizes[', CommunityInsights::h($widget), ']"><option value="1"', $width === 1 ? ' selected' : '', '>Half</option><option value="2"', $width === 2 ? ' selected' : '', '>Full</option></select></label>';
        echo '<button class="button ci-up" type="button" aria-label="Move up">↑</button><button class="button ci-down" type="button" aria-label="Move down">↓</button></div>';
    }
    echo '</div><input type="hidden" name="days" value="', (int) $dates['days'], '">';
	if ($dates['custom'])
		echo '<input type="hidden" name="from" value="', gmdate('Y-m-d', $dates['start']), '"><input type="hidden" name="to" value="', gmdate('Y-m-d', $dates['end']), '">';
	echo '<input type="hidden" name="', CommunityInsights::h($context['session_var']), '" value="', CommunityInsights::h($context['session_id']), '"><input type="hidden" name="', CommunityInsights::h($context['ci_layout_token_var']), '" value="', CommunityInsights::h($context['ci_layout_token']), '"><button type="submit" class="button">', CommunityInsights::h($txt['ci_save_layout']), '</button></form>';
	template_ci_end();

	if ($ci['export'])
	{
		template_ci_header('export', $txt['ci_export']);
		echo '<form method="post" action="', CommunityInsights::h($base . ';sa=export;' . ($dates['custom'] ? 'from=' . gmdate('Y-m-d', $dates['start']) . ';to=' . gmdate('Y-m-d', $dates['end']) : 'days=' . $dates['days'])), '"><label for="ci-export-widget">', CommunityInsights::h($txt['ci_export_widget']), '</label><select name="widget" id="ci-export-widget">';
		foreach (array('overview', 'smf', 'discussion', 'boards', 'members', 'moderation') as $widget)
			if (in_array($widget, $ci['allowed'], true))
				echo '<option value="', $widget, '">', CommunityInsights::h($txt['ci_widget_' . $widget]), '</option>';
		echo '</select> <label>Format <select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select></label><input type="hidden" name="', CommunityInsights::h($context['session_var']), '" value="', CommunityInsights::h($context['session_id']), '"><input type="hidden" name="', CommunityInsights::h($context['ci_report_token_var']), '" value="', CommunityInsights::h($context['ci_report_token']), '"><input type="hidden" name="ci_range_start" value="', (int) $dates['start'], '"><input type="hidden" name="ci_range_end" value="', (int) $dates['end'], '"><button type="submit" class="button">Download</button></form>';
		template_ci_end();
	}
	if ($ci['admin'])
		echo '<p><a class="button" href="', CommunityInsights::h($scripturl . '?action=admin;area=communityinsights;sa=settings'), '">', CommunityInsights::h($txt['ci_settings']), '</a></p>';
	echo '</div>';
}

function template_ci_settings()
{
	global $context, $scripturl, $txt;
	$s = $context['ci_settings'];
	echo '<div class="ci-settings"><div class="cat_bar"><h3 class="catbg">', CommunityInsights::h($txt['ci_settings']), '</h3></div><form method="post" action="', CommunityInsights::h($scripturl . '?action=admin;area=communityinsights;sa=settings'), '" class="windowbg ci-panel">';
	foreach (array('enabled' => 'ci_enabled_label', 'integrations' => 'ci_integrations_label', 'recommendations' => 'ci_recommendations_label') as $key => $string)
		echo '<label class="ci-setting"><input type="checkbox" name="', $key, '" value="1"', $s[$key] ? ' checked' : '', '> ', CommunityInsights::h($txt[$string]), '</label>';
	echo '<label class="ci-setting" for="ci-response-hours">', CommunityInsights::h($txt['ci_response_hours']), '</label><input type="number" id="ci-response-hours" name="response_hours" value="', (int) $s['hours'], '" min="1" max="336">';
	echo '<h4>Engagement score weights</h4>';
    foreach (array('score_replies' => 'Replies', 'score_contributors' => 'Contributors', 'score_views' => 'Views (log scale)') as $key => $label)
        echo '<label class="ci-setting">', $label, ' <input type="number" name="', $key, '" min="0" max="100" value="', (int) $s[$key], '"></label>';
    echo '<label class="ci-setting" for="ci-cache-minutes">', CommunityInsights::h($txt['ci_cache_minutes']), '</label><input type="number" id="ci-cache-minutes" name="cache_minutes" value="', (int) $s['cache_minutes'], '" min="0" max="1440">';
	echo '<h4>', CommunityInsights::h($txt['ci_default_layout']), '</h4><div class="ci-checks">';
	foreach ($s['all'] as $widget)
		echo '<label><input type="checkbox" name="widgets[]" value="', CommunityInsights::h($widget), '"', is_array($s['widgets']) && in_array($widget, $s['widgets'], true) ? ' checked' : '', '> ', CommunityInsights::h($txt['ci_widget_' . $widget]), '</label>';
	echo '</div><h4>Individual integrations</h4><p class="smalltext">Only detected, enabled companion mods produce analytics. An unchecked integration remains disabled.</p><div class="ci-checks">';
    foreach (CommunityInsightsProviders::$map as $id => $config)
    {
        $available = in_array($id, $s['providers'], true);
        $enabled = !isset($s['provider_settings']['ci_int_' . $id]) || !empty($s['provider_settings']['ci_int_' . $id]);
        echo '<label><input type="checkbox" name="ci_int_', CommunityInsights::h($id), '" value="1"', $enabled ? ' checked' : '', !$available ? ' disabled' : '', '> ', CommunityInsights::h(ucwords(str_replace(array('qa','ranked','wall','rules'), array('Questions & Answers','Ranked Polls','Profile Wall','Board Rules'), $id))), ' <small>(', $available ? 'available' : 'not detected', ')</small></label>';
    }
    echo '</div><p class="smalltext">', CommunityInsights::h($txt['ci_permissions_hint']), ' <a href="', CommunityInsights::h($scripturl . '?action=admin;area=permissions'), '">', CommunityInsights::h($txt['ci_permissions_link']), '</a></p>';
	echo '<input type="hidden" name="ci_save_settings" value="1"><input type="hidden" name="', CommunityInsights::h($context['session_var']), '" value="', CommunityInsights::h($context['session_id']), '"><input type="hidden" name="', CommunityInsights::h($context['ci_settings_token_var']), '" value="', CommunityInsights::h($context['ci_settings_token']), '"><button type="submit" class="button">', CommunityInsights::h($txt['ci_save_settings']), '</button></form></div>';
}

/** Community Insights 1.1.0: new reporting suite, using native SMF section and button styles. */
function template_ci_nav($current = '')
{
    global $scripturl, $txt, $context;
    $base = $scripturl . '?action=communityinsights';
    $tabs = array('' => 'Dashboard', 'reports' => 'Detailed reports', 'saved' => 'Saved reports', 'history' => 'Export history');
    if (allowedTo('admin_forum'))
        $tabs += array('automation' => 'Automated reports', 'alerts' => 'Custom alerts', 'access' => 'Permissions');
    echo '<nav class="ci-nav" aria-label="Community Insights navigation">';
    foreach ($tabs as $id => $label)
        echo '<a class="button', $current === $id ? ' active' : '', '" href="', CommunityInsights::h($base . ($id !== '' ? ';sa=' . $id : '')), '">', CommunityInsights::h($label), '</a> ';
    echo '</nav>';
}

function template_ci_form_session($token)
{
    global $context;
    echo '<input type="hidden" name="', CommunityInsights::h($context['session_var']), '" value="', CommunityInsights::h($context['session_id']), '">';
    if (isset($context[$token . '_token_var']))
        echo '<input type="hidden" name="', CommunityInsights::h($context[$token . '_token_var']), '" value="', CommunityInsights::h($context[$token . '_token']), '">';
}

/** Accessible compact bar chart; values remain visible via title and surrounding table. */
function template_ci_chart($title, $points)
{
    if (!is_array($points) || !$points) return;
    $max = max(array_values($points));
    echo '<div class="ci-chart-wrapper"><h4>', CommunityInsights::h($title), '</h4><div class="ci-mini-chart" role="img" aria-label="', CommunityInsights::h($title), '">';
    foreach (array_slice($points, 0, 48, true) as $label => $value)
    {
        $height = $max > 0 ? max(2, (int) round(100 * (float) $value / $max)) : 2;
        echo '<div class="ci-mini-bar"><span class="ci-mini-fill" style="height:', $height, '%" title="', CommunityInsights::h((string) $label . ': ' . $value), '"></span><span class="smalltext">', CommunityInsights::h((string) $label), '</span></div>';
    }
    echo '</div></div>';
}

/** Do not output arbitrary provider markup; only escaped text and numeric values. */
function template_ci_data($data, $depth = 0)
{
    global $scripturl;
    if ($depth > 5) return;
    if (!is_array($data))
    {
        echo CommunityInsights::h($data === null ? 'N/A' : (is_bool($data) ? ($data ? 'Yes' : 'No') : $data));
        return;
    }
    if (empty($data)) { echo '<p class="smalltext">No results for this range.</p>'; return; }
    // Compact horizontal chart for time series, with accessible numeric values.
    $isList = array_keys($data) === range(0, count($data) - 1);
    if ($isList && isset($data[0]) && is_array($data[0]))
    {
        $columns = array_keys($data[0]);
        echo '<div class="ci-overflow"><table class="table_grid ci-table"><thead><tr>';
        foreach ($columns as $key) echo '<th>', CommunityInsights::h(str_replace('_', ' ', $key)), '</th>';
        echo '</tr></thead><tbody>';
        foreach (array_slice($data, 0, 150) as $r)
        {
            echo '<tr>';
            foreach ($columns as $key)
            {
                $value = isset($r[$key]) ? $r[$key] : '';
                echo '<td>';
                if ($key === 'subject' && !empty($r['id_topic']))
                    echo '<a href="', CommunityInsights::h($scripturl . '?topic=' . (int) $r['id_topic'] . '.0'), '">', CommunityInsights::h(un_htmlspecialchars((string) $value)), '</a>';
                elseif ($key === 'name' && !empty($r['id_board']))
                    echo '<a href="', CommunityInsights::h($scripturl . '?board=' . (int) $r['id_board'] . '.0'), '">', CommunityInsights::h(un_htmlspecialchars((string) $value)), '</a>';
                elseif (in_array($key, array('board_name', 'real_name'), true))
                    echo CommunityInsights::h(un_htmlspecialchars((string) $value)); // SMF stores these already escaped.
                elseif (is_array($value)) echo CommunityInsights::h(json_encode($value));
                else template_ci_data($value, $depth + 1);
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        return;
    }
    if ($isList)
    {
        echo '<ul class="ci-list">';
        foreach (array_slice($data, 0, 150) as $v) { echo '<li>'; template_ci_data($v, $depth + 1); echo '</li>'; }
        echo '</ul>';
        return;
    }
    echo '<div class="ci-report-values">';
    foreach ($data as $key => $value)
    {
        if ($key === 'daily' && is_array($value) && $value)
        {
            $bars = array();
            foreach ($value as $day => $entry)
            {
                if (!is_array($entry)) continue;
                $label = isset($entry['date']) ? $entry['date'] : (isset($entry['day']) ? $entry['day'] : $day);
                $metric = isset($entry['posts']) ? 'posts' : (isset($entry['reports']) ? 'reports' : (isset($entry['responded']) ? 'responded' : null));
                if ($metric !== null) $bars[(string) $label] = (int) $entry[$metric];
            }
            if ($bars) template_ci_chart('Daily activity', $bars);
        }
        if ($key === 'weekdays' && is_array($value))
        {
            echo '<div class="ci-heatmap" role="table" aria-label="Posting heatmap by weekday and hour in UTC">';
            foreach (array('Mon','Tue','Wed','Thu','Fri','Sat','Sun') as $day => $label)
            {
                echo '<div class="ci-heatmap-label">', $label, '</div>';
                for ($h = 0; $h < 24; $h++)
                {
                    $v = isset($value[$day][$h]) ? (int) $value[$day][$h] : 0;
                    $shade = $v === 0 ? 0 : min(5, 1 + (int) floor(log(1 + $v)));
                    echo '<span class="ci-heat-', $shade, '" title="', $label, ' ', sprintf('%02d', $h), ':00 UTC: ', $v, ' posts" aria-label="', $v, ' posts"></span>';
                }
            }
            echo '</div><p class="smalltext">Columns represent 00:00 to 23:00 UTC.</p>';
            continue;
        }
        echo '<div class="ci-report-pair"><strong>', CommunityInsights::h(str_replace('_', ' ', (string) $key)), '</strong><div>';
        template_ci_data($value, $depth + 1);
        echo '</div></div>';
    }
    echo '</div>';
}

function template_ci_advanced()
{
    global $context, $scripturl, $txt;
    $ci = $context['ci_advanced'];
    $base = $scripturl . '?action=communityinsights';
    echo '<div class="ci-dashboard"><div class="title_bar"><h3 class="titlebg">', CommunityInsights::h($txt['ci_title']), ' ', CommunityInsights::VERSION, '</h3></div>';
    template_ci_nav($ci['sa']);

    if ($ci['sa'] === 'reports')
    {
        template_ci_header('report-index', 'Detailed reports');
        echo '<p>Select a report to view data from boards you are permitted to see.</p><div class="ci-report-index">';
        foreach ($ci['types'] as $id => $label)
            echo '<a class="button" href="', CommunityInsights::h($base . ';sa=report;type=' . $id), '">', CommunityInsights::h($label), '</a>';
        echo '</div>'; template_ci_end();
    }
    elseif ($ci['sa'] === 'report')
    {
        template_ci_header('report-builder', 'Report builder');
        echo '<form method="get" action="', CommunityInsights::h($scripturl), '" class="ci-report-form"><input type="hidden" name="action" value="communityinsights"><input type="hidden" name="sa" value="report">';
        echo '<label>Report type <select name="type">';
        foreach ($ci['types'] as $id => $label)
            echo '<option value="', CommunityInsights::h($id), '"', $id === $ci['type'] ? ' selected' : '', '>', CommunityInsights::h($label), '</option>';
        echo '</select></label><label>Preset range <select name="days">';
        foreach (array(7,30,90,365) as $days)
            echo '<option value="', $days, '"', (int) $ci['dates']['days'] === $days ? ' selected' : '', '>', $days, ' days</option>';
        echo '</select></label><label>From <input type="date" name="from" value="', CommunityInsights::h(isset($ci['options']['from']) ? $ci['options']['from'] : ''), '"></label>';
        echo '<label>To <input type="date" name="to" value="', CommunityInsights::h(isset($ci['options']['to']) ? $ci['options']['to'] : ''), '"></label>';
        if ($ci['type'] === 'compare')
        {
            echo '<fieldset><legend>Compare up to five visible boards</legend><div class="ci-checks">';
            foreach ($ci['boards'] as $board)
                echo '<label><input type="checkbox" name="boards[]" value="', (int) $board['id_board'], '"', in_array((int) $board['id_board'], isset($ci['options']['boards']) && is_array($ci['options']['boards']) ? array_map('intval', $ci['options']['boards']) : array(), true) ? ' checked' : '', '> ', CommunityInsights::h(un_htmlspecialchars($board['name'])), '</label>';
            echo '</div></fieldset>';
        }
        echo '<button class="button" type="submit">Generate report</button></form>'; template_ci_end();
        if ($ci['type'])
        {
            template_ci_header('results', isset($ci['types'][$ci['type']]) ? $ci['types'][$ci['type']] : 'Report');
            if (!empty($ci['report'])) echo '<p class="smalltext">Saved report: ', CommunityInsights::h($ci['report']['title']), '</p>';
            template_ci_data($ci['data']);
            foreach ($ci['charts'] as $chart) template_ci_chart($chart['title'], $chart['points']);
            template_ci_end();
            template_ci_header('save-export', 'Save and export');
            echo '<div class="ci-actions"><form method="post" action="', CommunityInsights::h($base . ';sa=save_report'), '">';
            template_ci_form_session('ci_report');
            echo '<input type="hidden" name="report_type" value="', CommunityInsights::h($ci['type']), '">';
            echo '<input type="hidden" name="days" value="', (int) $ci['dates']['days'], '">';
            echo '<input type="hidden" name="from" value="', CommunityInsights::h(isset($ci['options']['from']) ? $ci['options']['from'] : ''), '">';
            echo '<input type="hidden" name="to" value="', CommunityInsights::h(isset($ci['options']['to']) ? $ci['options']['to'] : ''), '">';
            if (!empty($ci['options']['boards']) && is_array($ci['options']['boards']))
                foreach (array_slice($ci['options']['boards'], 0, 5) as $boardId)
                    echo '<input type="hidden" name="boards[]" value="', (int) $boardId, '">';
            if (!empty($ci['report'])) echo '<input type="hidden" name="id_report" value="', (int) $ci['report']['id_report'], '">';
            echo '<label>Report name <input name="title" required maxlength="120" value="', CommunityInsights::h(!empty($ci['report']) ? $ci['report']['title'] : $ci['types'][$ci['type']]), '"></label> ';
            echo '<button type="submit" class="button">', !empty($ci['report']) ? 'Update saved report' : 'Save report', '</button></form>';
            if ($ci['can_export'])
            {
                $query = ';days=' . (int) $ci['dates']['days'];
                if ($ci['dates']['custom']) $query .= ';from=' . gmdate('Y-m-d', $ci['dates']['start']) . ';to=' . gmdate('Y-m-d', $ci['dates']['end']);
                echo '<form method="post" action="', CommunityInsights::h($base . ';sa=export' . $query), '">';
                template_ci_form_session('ci_report');
                echo '<input type="hidden" name="ci_range_start" value="', (int) $ci['dates']['start'], '"><input type="hidden" name="ci_range_end" value="', (int) $ci['dates']['end'], '">';
                echo '<input type="hidden" name="widget" value="', CommunityInsights::h($ci['type']), '">';
                if (!empty($ci['report'])) echo '<input type="hidden" name="id_report" value="', (int) $ci['report']['id_report'], '">';
                if ($ci['type'] === 'compare' && !empty($ci['options']['boards']) && is_array($ci['options']['boards']))
                    foreach (array_slice($ci['options']['boards'], 0, 5) as $boardId)
                        echo '<input type="hidden" name="boards[]" value="', (int) $boardId, '">';
                echo '<label>Export <select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select></label> <button class="button" type="submit">Download</button></form>';
            }
            echo '</div>'; template_ci_end();
        }
    }
    elseif ($ci['sa'] === 'saved')
    {
        template_ci_header('saved', 'Saved reports');
        if (empty($ci['saved'])) echo '<p>No reports saved yet. Create one in Detailed reports.</p>';
        else
        {
            echo '<div class="ci-overflow"><table class="table_grid ci-table"><thead><tr><th>Name</th><th>Type</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';
            foreach ($ci['saved'] as $r)
            {
                echo '<tr><td>', CommunityInsights::h($r['title']), '</td><td>', CommunityInsights::h($r['report_type']), '</td><td>', timeformat($r['updated']), '</td><td>';
                echo '<a class="button" href="', CommunityInsights::h($base . ';sa=report;id=' . (int) $r['id_report']), '">Run / Edit</a> ';
                echo '<form method="post" class="ci-inline" action="', CommunityInsights::h($base . ';sa=delete_report'), '">';
                template_ci_form_session('ci_report');
                echo '<input type="hidden" name="id_report" value="', (int) $r['id_report'], '"><button class="button" type="submit">Delete</button></form></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        template_ci_end();
    }
    elseif ($ci['sa'] === 'history')
    {
        template_ci_header('history', 'Export history');
        echo '<p class="smalltext">This audit trail records download events. Historical export files are not stored; re-run a saved report to get fresh data under your current permissions.</p>';
        if (!$ci['history']) echo '<p>No exports recorded.</p>';
        else
        {
            echo '<table class="table_grid ci-table"><thead><tr><th>Time</th><th>Report</th><th>Format</th><th>From</th><th>To</th></tr></thead><tbody>';
            foreach ($ci['history'] as $h)
                echo '<tr><td>', timeformat($h['exported']), '</td><td>', CommunityInsights::h($h['report_type']), '</td><td>', CommunityInsights::h(strtoupper($h['format'])), '</td><td>', gmdate('Y-m-d', $h['range_start']), '</td><td>', gmdate('Y-m-d', $h['range_end']), '</td></tr>';
            echo '</tbody></table>';
        }
        template_ci_end();
    }
    elseif ($ci['sa'] === 'automation')
    {
        template_ci_header('automation', 'Automated reports');
        echo '<p class="smalltext">Schedules send a report-type-specific aggregate summary for the saved date range to the report owner only, provided the owner is still an administrator. Snapshot metrics cover complete recorded UTC days; full permission-checked reports remain on-demand.</p>';
        if (empty($ci['saved'])) echo '<p>Save a report first.</p>';
        else
        {
            echo '<form method="post" action="', CommunityInsights::h($base . ';sa=save_schedule'), '">'; template_ci_form_session('ci_automation');
            echo '<label>Saved report <select name="id_report">';
            foreach ($ci['saved'] as $r) echo '<option value="', (int) $r['id_report'], '">', CommunityInsights::h($r['title']), '</option>';
            echo '</select></label> <label>Frequency <select name="frequency"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly (30 days)</option></select></label> <button class="button">Schedule report</button></form>';
        }
        foreach ($ci['schedules'] as $s)
        {
            echo '<div class="ci-schedule-row">', CommunityInsights::h($s['title']), ' · ', CommunityInsights::h($s['frequency']), ' · Next: ', timeformat($s['next_send']);
            echo '<form method="post" class="ci-inline" action="', CommunityInsights::h($base . ';sa=delete_schedule'), '">';
            template_ci_form_session('ci_automation');
            echo '<input type="hidden" name="id_schedule" value="', (int) $s['id_schedule'], '"><button class="button">Remove</button></form></div>';
        }
        template_ci_end();
    }
    elseif ($ci['sa'] === 'alerts')
    {
        template_ci_header('alerts', 'Custom thresholds and alerts');
        echo '<p class="smalltext">Thresholds are checked in the scheduled task, at most once daily per rule, and sent only to current administrator accounts.</p>';
        echo '<form method="post" action="', CommunityInsights::h($base . ';sa=save_alert'), '">'; template_ci_form_session('ci_alert');
        echo '<label>Metric <select name="metric"><option value="posts_day">Posts yesterday</option><option value="topics_day">Topics yesterday</option><option value="posts_change_pct">Absolute day-on-day post change (%)</option><option value="posts_drop_pct">Day-on-day posting drop (%)</option><option value="new_members_day">New registrations yesterday</option><option value="unanswered_topics">Open unanswered topics (no external reply, mature)</option><option value="open_reports">Open reports</option></select></label> ';
        echo '<label>When <select name="comparator"><option value="gt">Above</option><option value="lt">Below</option></select></label> <label>Threshold <input type="number" name="threshold" min="0" max="1000000" step="0.1" required></label> <button class="button">Add alert</button></form>';
        foreach ($ci['alerts'] as $a)
        {
            echo '<div class="ci-schedule-row">', CommunityInsights::h($a['metric'] . ' ' . $a['comparator'] . ' ' . $a['threshold']);
            if ($a['last_triggered']) echo ' · Last triggered: ', timeformat($a['last_triggered']);
            echo '<form method="post" class="ci-inline" action="', CommunityInsights::h($base . ';sa=delete_alert'), '">';
            template_ci_form_session('ci_alert'); echo '<input type="hidden" name="id_rule" value="', (int) $a['id_rule'], '"><button class="button">Remove</button></form></div>';
        }
        template_ci_end();
    }
    elseif ($ci['sa'] === 'access')
    {
        template_ci_header('permissions', 'SMF permissions overview');
        echo '<p>Community Insights uses SMF’s native membergroup permissions. Changes must be made in the standard SMF Permissions screen, not in a separate permission database.</p>';
        echo '<table class="table_grid ci-table"><thead><tr><th>Permission</th><th>Purpose</th><th>Your access</th></tr></thead><tbody>';
        foreach ($ci['permissions'] as $permission => $purpose)
            echo '<tr><td>', CommunityInsights::h($permission), '</td><td>', CommunityInsights::h($purpose), '</td><td>', CommunityInsights::can($permission) ? 'Allowed' : 'Denied', '</td></tr>';
        echo '</tbody></table><p><a class="button" href="', CommunityInsights::h($scripturl . '?action=admin;area=permissions'), '">Open SMF Permissions</a></p>';
        template_ci_end();
    }
    echo '</div>';
}
