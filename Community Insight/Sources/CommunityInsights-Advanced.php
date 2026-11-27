<?php
/**
 * Community Insights 1.1.x - reporting, automation and historical analytics.
 * All report routes recheck SMF permissions and visible board scope.
 * @license ISC
 */
if (!defined('SMF'))
    die('No direct access...');

class CommunityInsightsAdvanced
{
    public static function featureList()
    {
        // Each entry is [permission, display label]. Use only identifiers, never SQL fragments from requests.
        return array(
            'content' => array('ci_core', 'Content and engagement'),
            'response' => array('ci_core', 'Response times'),
            'scoring' => array('ci_core', 'Topic engagement rankings'),
            'trending' => array('ci_core', 'Trending discussions'),
            'heatmap' => array('ci_core', 'Posting heatmap (UTC)'),
            'compare' => array('ci_core', 'Board comparison'),
            'onboarding' => array('ci_members', 'New member onboarding'),
            'retention' => array('ci_members', 'Retention by cohort'),
            'contributions' => array('ci_members', 'Helpful contributors'),
            'modtrends' => array('ci_moderation', 'Moderation trends'),
            'snapshots' => array('ci_core', 'Daily historical snapshots'),
        );
    }

    public static function availableFeatures()
    {
        $out = array();
        foreach (self::featureList() as $id => $config)
            if (CommunityInsights::can($config[0]))
                $out[$id] = $config[1];
        $extra = array();
        call_integration_hook('integrate_ci_report_types', array(&$extra));
        foreach ((array) $extra as $id => $config)
            if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', (string) $id)
                && is_array($config) && !empty($config['label']) && !empty($config['permission'])
                && CommunityInsights::can($config['permission']) && isset($config['callback']) && is_callable($config['callback']) && !isset($out[$id]))
                $out[$id] = strip_tags((string) $config['label']);
        return $out;
    }

    public static function features()
    {
        return array_merge(array('overview' => 'Community overview', 'smf' => 'SMF forum totals', 'activity' => 'Activity trends',
            'popular' => 'Most viewed topics', 'discussion' => 'Discussion health', 'boards' => 'Board performance',
            'members' => 'Member engagement', 'moderation' => 'Moderation workload'), self::availableFeatures());
    }

    public static function allowed($id)
    {
        if (isset(self::featureList()[$id]))
            return CommunityInsights::can(self::featureList()[$id][0]);
        if (in_array($id, array('overview', 'smf', 'activity', 'popular', 'discussion', 'boards', 'members', 'moderation'), true))
            return in_array($id, CommunityInsights::allowedWidgets(), true);
        return isset(self::availableFeatures()[$id]);
    }

    public static function calculate($type, $dates, $options = array())
    {
        if (!self::allowed($type))
            return array();
        if (in_array($type, array('overview', 'smf', 'activity', 'popular', 'discussion', 'boards', 'members', 'moderation'), true))
            return CommunityInsights::cached($type, $dates, function () use ($type, $dates) { return call_user_func(array('CommunityInsights', $type), $dates); });
        $method = 'report_' . $type;
        if (isset(self::featureList()[$type]) && method_exists(__CLASS__, $method))
            return call_user_func(array(__CLASS__, $method), $dates, $options);
        $extra = array();
        call_integration_hook('integrate_ci_report_types', array(&$extra));
        if (isset($extra[$type]) && is_array($extra[$type]) && !empty($extra[$type]['permission']) && CommunityInsights::can($extra[$type]['permission'])
            && isset($extra[$type]['callback']) && is_callable($extra[$type]['callback']))
            return self::sanitizeExternal(call_user_func($extra[$type]['callback'], $dates, $options));
        return array();
    }

    /** Limit untrusted provider data to bounded scalar aggregates (never HTML). */
    public static function sanitizeExternal($data)
    {
        global $smcFunc;
        if (!is_array($data))
            return array();
        $out = array();
        foreach (array_slice($data, 0, 100, true) as $k => $value)
        {
            if (!is_scalar($value))
                continue;
            $label = $smcFunc['substr'](strip_tags((string) $k), 0, 80);
            $out[$label] = is_numeric($value) ? (float) $value : $smcFunc['substr'](strip_tags((string) $value), 0, 180);
        }
        return $out;
    }

    /** Third-party charts: at most four safe numeric series per report, no raw HTML. */
    public static function chartExtensions($reportType, $dates)
    {
        global $smcFunc;
        $requested = array();
        call_integration_hook('integrate_ci_report_charts', array(&$requested, $reportType, $dates));
        $out = array();
        foreach (array_slice((array) $requested, 0, 4) as $chart)
        {
            if (!is_array($chart) || empty($chart['title']) || empty($chart['points']) || !is_array($chart['points'])) continue;
            $points = array();
            foreach (array_slice($chart['points'], 0, 48) as $label => $value)
            {
                if (!is_scalar($value) || !is_numeric($value) || !is_finite((float) $value)) continue;
                $points[$smcFunc['substr'](strip_tags((string) $label), 0, 32)] = max(0, (float) $value);
            }
            if ($points)
                $out[] = array('title' => $smcFunc['substr'](strip_tags((string) $chart['title']), 0, 80), 'points' => $points);
        }
        return $out;
    }

    /** Use the dashboard's date boundaries for every saved and detailed report. */
    public static function dateParams($options)
    {
        return CommunityInsights::dateParams($options);
    }

    public static function report_content($dates)
    {
        $scope = CommunityInsights::scope($dates);
        $base = ' FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE ' . $scope['where'] . ' AND m.approved = {int:approved}
            AND m.poster_time BETWEEN {int:start} AND {int:end}';
        $summary = CommunityInsights::first('SELECT COUNT(*) AS posts, COUNT(DISTINCT m.id_topic) AS active_topics,
            COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member END) AS contributors' . $base, $scope['params']);
        $boards = CommunityInsights::boards($dates);
        $activity = CommunityInsights::activity($dates);
        return array('posts' => (int) $summary['posts'], 'active_topics' => (int) $summary['active_topics'],
            'contributors' => (int) $summary['contributors'], 'board_performance' => $boards, 'activity' => $activity);
    }

    public static function report_response($dates)
    {
        $scope = CommunityInsights::scope($dates);
        $params = $scope['params'];
        $params['limit'] = 2000;
        // Only the first external reply counts; guest starters are compared by poster name.
        $rows = CommunityInsights::read('SELECT t.id_topic, fm.poster_time AS started,
            (SELECT MIN(r.poster_time) FROM {db_prefix}messages AS r
                WHERE r.id_topic = t.id_topic AND r.id_msg != t.id_first_msg AND r.approved = {int:approved}
                AND r.poster_time BETWEEN fm.poster_time AND {int:end}
                AND (CASE WHEN t.id_member_started > 0 THEN r.id_member != t.id_member_started
                  ELSE r.poster_name != fm.poster_name END)) AS first_response
            FROM {db_prefix}topics AS t
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE ' . $scope['where'] . ' AND fm.poster_time BETWEEN {int:start} AND {int:end}
            ORDER BY t.id_first_msg DESC LIMIT {int:limit}', $params);
        $durations = array(); $bins = array(); $responded = 0;
        foreach ($rows as $r)
        {
            $day = gmdate('Y-m-d', (int) $r['started']);
            if (!isset($bins[$day])) $bins[$day] = array('started' => 0, 'responded' => 0, 'hours_sum' => 0);
            $bins[$day]['started']++;
            if ($r['first_response'] !== null && (int) $r['first_response'] >= (int) $r['started'])
            {
                $hours = ((int) $r['first_response'] - (int) $r['started']) / 3600;
                $durations[] = $hours; $responded++; $bins[$day]['responded']++; $bins[$day]['hours_sum'] += $hours;
            }
        }
        sort($durations, SORT_NUMERIC); $n = count($durations);
        $median = $n ? ($durations[(int) floor(($n - 1) / 2)] + $durations[(int) floor($n / 2)]) / 2 : 0;
        ksort($bins);
        foreach ($bins as &$v) $v['average_hours'] = $v['responded'] ? round($v['hours_sum'] / $v['responded'], 2) : null;
        unset($v);
        return array('topics_analysed' => count($rows), 'replied' => $responded, 'average_hours' => $n ? round(array_sum($durations) / $n, 2) : null,
            'median_hours' => $n ? round($median, 2) : null, 'daily' => $bins,
            'sample_limit' => 2000, 'sampled' => count($rows) >= 2000);
    }

    /** Relative 0-100 scoring within the displayed candidate set, not universal performance. */
    public static function report_scoring($dates)
    {
        $scope = CommunityInsights::scope($dates); $p = $scope['params']; $p['limit'] = 75;
        // Replies and contributors are limited to the selected report end;
        // SMF does not retain historical per-topic view totals, so views remain
        // an explicitly live signal rather than an invented historical count.
        $rows = CommunityInsights::prepareTopics(CommunityInsights::read('SELECT t.id_topic, fm.subject, b.name AS board_name,
            t.num_views,
            SUM(CASE WHEN m.id_msg != t.id_first_msg THEN 1 ELSE 0 END) AS num_replies,
            COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member ELSE NULL END) AS contributors
            FROM {db_prefix}topics AS t JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.approved = {int:approved}
                AND m.poster_time BETWEEN fm.poster_time AND {int:end})
            WHERE ' . $scope['where'] . ' AND fm.poster_time BETWEEN {int:start} AND {int:end}
            GROUP BY t.id_topic, fm.subject, b.name, t.num_views
            ORDER BY t.id_first_msg DESC LIMIT {int:limit}', $p));
        global $modSettings;
        $wReplies = isset($modSettings['ci_score_replies']) ? (int) $modSettings['ci_score_replies'] : 2;
        $wContributors = isset($modSettings['ci_score_contributors']) ? (int) $modSettings['ci_score_contributors'] : 4;
        $wViews = isset($modSettings['ci_score_views']) ? (int) $modSettings['ci_score_views'] : 3;
        $max = 0;
        foreach ($rows as &$r)
        {
            $r['raw_score'] = round($wReplies * (int) $r['num_replies'] + $wContributors * max(0, (int) $r['contributors']) + $wViews * log(1 + (int) $r['num_views']), 3);
            $max = max($max, $r['raw_score']);
        }
        unset($r);
        foreach ($rows as &$r) { $r['score'] = $max > 0 ? (int) round(100 * $r['raw_score'] / $max) : 0; unset($r['raw_score']); }
        unset($r);
        usort($rows, function ($a, $b) { return $b['score'] <=> $a['score']; });
        return array('formula' => 'Relative score: ' . $wReplies . '×replies + ' . $wContributors . '×contributors + ' . $wViews . '×ln(1+views), normalised to 0–100 among up to 75 recent topics',
            'topics' => array_slice($rows, 0, 25), 'note' => 'Replies and contributors are measured through the selected end date. Topic views are current totals because SMF does not retain historical per-topic view counts.');
    }

    /** Compare equal adjacent windows *within* the selected reporting range. */
    public static function report_trending($dates)
    {
        $scope = CommunityInsights::scope($dates); $p = $scope['params'];
        // The normal case uses two 24-hour windows ending at the selected
        // period's end, not at the server's current time. Shorter custom ranges
        // use equal halves so neither comparison leaks outside the selection.
        $span = max(1, (int) $dates['end'] - (int) $dates['start'] + 1);
        if ($span < 2)
            return array('window' => 'Not enough time in the selected range', 'topics' => array());
        $seconds = min(86400, (int) floor($span / 2));
        $p['recent'] = (int) $dates['end'] - $seconds + 1;
        $p['previous'] = $p['recent'] - $seconds;
        $p['end'] = (int) $dates['end'];
        $p['limit'] = 30;
        $rows = CommunityInsights::prepareTopics(CommunityInsights::read('SELECT t.id_topic, fm.subject, b.name AS board_name,
            SUM(CASE WHEN m.poster_time >= {int:recent} THEN 1 ELSE 0 END) AS last24,
            SUM(CASE WHEN m.poster_time < {int:recent} THEN 1 ELSE 0 END) AS previous24
            FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE ' . $scope['where'] . ' AND m.approved = {int:approved} AND m.poster_time BETWEEN {int:previous} AND {int:end}
            GROUP BY t.id_topic, fm.subject, b.name
            HAVING SUM(CASE WHEN m.poster_time >= {int:recent} THEN 1 ELSE 0 END) > 0 ORDER BY last24 DESC LIMIT {int:limit}', $p));
        foreach ($rows as &$r) { $r['momentum'] = (int) $r['last24'] - (int) $r['previous24']; } unset($r);
        usort($rows, function ($a, $b) { return $b['momentum'] <=> $a['momentum']; });
        $label = $seconds === 86400 ? '24 hours' : ($seconds % 3600 === 0 ? ($seconds / 3600) . ' hours' : $seconds . ' seconds');
        return array('window' => 'Final ' . $label . ' versus preceding equal window within selected period', 'topics' => $rows);
    }

    public static function report_heatmap($dates)
    {
        $scope = CommunityInsights::scope($dates);
        $rows = CommunityInsights::read('SELECT MOD(FLOOR(m.poster_time / 86400) + 3, 7) AS weekday,
            MOD(FLOOR(m.poster_time / 3600), 24) AS hour, COUNT(*) AS posts
            FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE ' . $scope['where'] . ' AND m.approved = {int:approved} AND m.poster_time BETWEEN {int:start} AND {int:end}
            GROUP BY weekday, hour', $scope['params']);
        $out = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($rows as $r) if (isset($out[(int) $r['weekday']][(int) $r['hour']])) $out[(int) $r['weekday']][(int) $r['hour']] = (int) $r['posts'];
        return array('timezone' => 'UTC', 'weekdays' => $out);
    }

    public static function report_compare($dates, $options = array())
    {
        $scope = CommunityInsights::scope($dates); $p = $scope['params'];
        $selected = isset($options['boards']) && is_array($options['boards']) ? array_values(array_unique(array_filter(array_map('intval', $options['boards'])))) : array();
        $selected = array_slice($selected, 0, 5);
        $where = $selected ? ' AND b.id_board IN ({array_int:chosen})' : '';
        if ($selected) $p['chosen'] = $selected;
        $p['limit'] = 5;
        $rows = CommunityInsights::read('SELECT b.id_board, b.name,
            COUNT(m.id_msg) AS posts, COUNT(DISTINCT m.id_topic) AS active_topics,
            COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member END) AS contributors
            FROM {db_prefix}boards AS b LEFT JOIN {db_prefix}topics AS t ON (t.id_board = b.id_board AND t.approved = {int:approved})
            LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.approved = {int:approved}
                 AND m.poster_time BETWEEN {int:start} AND {int:end})
            WHERE {query_see_board}' . $where . '
            GROUP BY b.id_board, b.name ORDER BY posts DESC LIMIT {int:limit}', $p);
        $period = $dates['end'] - $dates['start'] + 1;
        $prev = $p;
        $prev['start'] = max(0, $dates['start'] - $period);
        $prev['end'] = $dates['start'] - 1;
        // Always compare exactly the boards selected in this period, not a different top-five list.
        $prev['selected_boards'] = $rows ? array_map('intval', array_column($rows, 'id_board')) : array(0);
        $prior = CommunityInsights::read('SELECT b.id_board, COUNT(m.id_msg) AS posts
            FROM {db_prefix}boards AS b LEFT JOIN {db_prefix}topics AS t ON (t.id_board = b.id_board AND t.approved = {int:approved})
            LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.approved = {int:approved}
              AND m.poster_time BETWEEN {int:start} AND {int:end})
            WHERE {query_see_board} AND b.id_board IN ({array_int:selected_boards})
            GROUP BY b.id_board ORDER BY posts DESC LIMIT {int:limit}', $prev);
        $previous = array(); foreach ($prior as $r) $previous[(int) $r['id_board']] = (int) $r['posts'];
        $responseRates = array();
        if ($rows)
        {
            $responseParams = array('boards' => array_map('intval', array_column($rows, 'id_board')),
                'approved' => 1, 'start' => (int) $dates['start'],
                'end' => (int) $dates['end'],
                'mature' => max(0, (int) $dates['end'] - 3600 * 48), 'seconds' => 3600 * 48);
            $responseRows = CommunityInsights::read('SELECT t.id_board, COUNT(*) AS eligible,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM {db_prefix}messages AS reply
                    WHERE reply.id_topic = t.id_topic AND reply.id_msg != t.id_first_msg
                    AND reply.approved = {int:approved}
                    AND reply.poster_time BETWEEN first_msg.poster_time AND {int:end}
                    AND reply.poster_time <= first_msg.poster_time + {int:seconds}
                    AND (CASE WHEN t.id_member_started > 0 THEN reply.id_member != t.id_member_started
                              ELSE reply.poster_name != first_msg.poster_name END))
                    THEN 1 ELSE 0 END) AS responded
                FROM {db_prefix}topics AS t JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                JOIN {db_prefix}messages AS first_msg ON (first_msg.id_msg = t.id_first_msg)
                WHERE t.approved = {int:approved} AND {query_see_board}
                    AND t.id_board IN ({array_int:boards})
                    AND first_msg.poster_time BETWEEN {int:start} AND {int:mature}
                GROUP BY t.id_board', $responseParams);
            foreach ($responseRows as $r)
                $responseRates[(int) $r['id_board']] = (int) $r['eligible'] > 0 ? round(100 * (int) $r['responded'] / (int) $r['eligible'], 1) : null;
        }
        foreach ($rows as &$row)
        {
            $old = isset($previous[(int) $row['id_board']]) ? $previous[(int) $row['id_board']] : 0;
            $row['previous_posts'] = $old;
            $row['change_pct'] = $old ? round(((int) $row['posts'] - $old) * 100 / $old, 1) : null;
            $row['response_rate_pct'] = isset($responseRates[(int) $row['id_board']]) ? $responseRates[(int) $row['id_board']] : null;
        }
        unset($row);
        return array('boards' => $rows, 'note' => 'Board selection is limited to five visible boards. Change compares matching date ranges; response rate is first external reply within 48 hours.');
    }

    public static function report_onboarding($dates)
    {
        $p = array('start' => $dates['start'], 'end' => min(time(), $dates['end']), 'active' => 1, 'limit' => 500);
        $members = CommunityInsights::read('SELECT id_member, date_registered FROM {db_prefix}members
            WHERE is_activated = {int:active} AND date_registered BETWEEN {int:start} AND {int:end}
            ORDER BY date_registered DESC LIMIT {int:limit}', $p);
        if (!$members) return array('joined' => 0, 'first_post' => 0, 'first_topic' => 0, 'replied_to' => 0, 'regular_contributors' => 0, 'sampled' => false);
        $ids = array_column($members, 'id_member');
        $p['ids'] = $ids;
        // Count first post/topic and an external response, without displaying private message text.
        $rows = CommunityInsights::read('SELECT m.id_member,
            COUNT(*) AS posts, SUM(CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END) AS topics,
            SUM(CASE WHEN m.id_msg = t.id_first_msg AND EXISTS
                (SELECT 1 FROM {db_prefix}messages AS r WHERE r.id_topic = t.id_topic AND r.approved = {int:active}
                AND r.poster_time BETWEEN m.poster_time AND {int:end}
                AND r.id_member != m.id_member AND r.id_msg != m.id_msg) THEN 1 ELSE 0 END) AS topics_replied
            FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            WHERE m.id_member IN ({array_int:ids}) AND m.approved = {int:active} AND t.approved = {int:active} AND {query_see_board}
              AND m.poster_time BETWEEN {int:start} AND {int:end}
            GROUP BY m.id_member', $p);
        $first = 0; $topics = 0; $replied = 0; $regular = 0;
        foreach ($rows as $r) { if ($r['posts']) $first++; if ($r['topics']) $topics++; if ($r['topics_replied']) $replied++; if ((int) $r['posts'] >= 5) $regular++; }
        return array('joined' => count($members), 'first_post' => $first, 'first_topic' => $topics,
            'replied_to' => $replied, 'regular_contributors' => $regular, 'sampled' => count($members) >= 500, 'note' => 'Only activity in boards you can access is counted. Most recent 500 activated signups maximum.');
    }

    public static function report_retention($dates)
    {
        $cohorts = array();
        $end = min($dates['end'], time());
        $firstMonth = new DateTimeImmutable(gmdate('Y-m-01 00:00:00', $end), new DateTimeZone('UTC'));
        for ($i = 0; $i < 6; $i++)
        {
            $monthStart = $firstMonth->modify('-' . $i . ' months');
            $month = $monthStart->format('Y-m-01');
            $start = $monthStart->getTimestamp();
            // A partial historical month must not include registrations made
            // after the selected report end date.
            $finish = min($end, $monthStart->modify('+1 month')->getTimestamp() - 1);
            $p = array('start' => $start, 'finish' => $finish, 'active' => 1, 'limit' => 250);
            $members = CommunityInsights::read('SELECT id_member, date_registered FROM {db_prefix}members
                WHERE is_activated = {int:active} AND date_registered BETWEEN {int:start} AND {int:finish}
                ORDER BY id_member DESC LIMIT {int:limit}', $p);
            $line = array('month' => $month, 'members_sampled' => count($members), 'sampled' => count($members) >= 250, 'day7' => 0, 'day30' => 0, 'day90' => 0,
                'eligible7' => 0, 'eligible30' => 0, 'eligible90' => 0);
            if ($members)
            {
                $p['ids'] = array_map('intval', array_column($members, 'id_member'));
                $p['horizon'] = min($end, $finish + 90 * 86400);
                $rows = CommunityInsights::read('SELECT m.id_member, m.poster_time FROM {db_prefix}messages AS m
                    JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
                    JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
                    WHERE m.id_member IN ({array_int:ids}) AND m.approved = {int:active} AND t.approved = {int:active} AND {query_see_board}
                      AND m.poster_time BETWEEN {int:start} AND {int:horizon}', $p);
                $posts = array(); foreach ($rows as $r) $posts[(int) $r['id_member']][] = (int) $r['poster_time'];
                foreach ($members as $m)
                {
                    foreach (array(7, 30, 90) as $days)
                    {
                        $registered = (int) $m['date_registered'];
                        if ($registered + $days * 86400 > $end) continue;
                        $line['eligible' . $days]++;
                        // Day 0 is initial signup activity, not a return visit.
                        $returnStart = $registered + max(1, $days - 7) * 86400;
                        foreach (isset($posts[(int) $m['id_member']]) ? $posts[(int) $m['id_member']] : array() as $at)
                            if ($at >= $returnStart && $at <= $registered + $days * 86400)
                            { $line['day' . $days]++; break; }
                    }
                }
            }
            $cohorts[] = $line;
        }
        return array('cohorts' => $cohorts, 'note' => 'Retention means an approved post after the first 24 hours, in the seven-day window ending on day 7, 30, or 90. Historical ranges are evaluated as at their selected end date. Up to 250 registered members per month.');
    }

    public static function report_contributions($dates)
    {
        $scope = CommunityInsights::scope($dates); $p = $scope['params']; $p['limit'] = 20;
        $rows = CommunityInsights::read('SELECT m.id_member, mem.real_name, COUNT(*) AS external_replies,
            COUNT(DISTINCT t.id_topic) AS discussions_helped
            FROM {db_prefix}messages AS m
            JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            WHERE ' . $scope['where'] . ' AND m.approved = {int:approved}
              AND m.id_msg != t.id_first_msg AND m.id_member != t.id_member_started
              AND m.poster_time BETWEEN {int:start} AND {int:end}
            GROUP BY m.id_member, mem.real_name
            ORDER BY discussions_helped DESC LIMIT {int:limit}', $p);
        return array('members' => $rows, 'note' => 'Helpful contribution here means a reply to someone else’s topic, not a quality judgement.');
    }

    public static function report_modtrends($dates)
    {
        $params = array('start' => $dates['start'], 'end' => $dates['end']);
        $rows = CommunityInsights::read('SELECT FLOOR(r.time_started / 86400) AS day,
            COUNT(*) AS reports, SUM(CASE WHEN r.closed = 1 THEN 1 ELSE 0 END) AS currently_closed,
            AVG(CASE WHEN r.closed = 1 THEN GREATEST(0, r.time_updated - r.time_started) ELSE NULL END) AS last_update_seconds
            FROM {db_prefix}log_reported AS r JOIN {db_prefix}boards AS b ON (b.id_board = r.id_board)
            WHERE {query_see_board} AND r.time_started BETWEEN {int:start} AND {int:end}
            GROUP BY FLOOR(r.time_started / 86400) ORDER BY day ASC', $params, true);
        $out = array(); $total = 0; $closed = 0;
        foreach ($rows as $r)
        {
            $total += (int) $r['reports']; $closed += (int) $r['currently_closed'];
            $out[] = array('day' => gmdate('Y-m-d', (int) $r['day'] * 86400), 'reports' => (int) $r['reports'],
                'currently_closed' => (int) $r['currently_closed'], 'average_time_to_last_update_hours' => $r['last_update_seconds'] !== null ? round($r['last_update_seconds'] / 3600, 1) : null);
        }
        return array('reports' => $total, 'currently_closed' => $closed, 'daily' => $out,
            'note' => 'Time to last update is a proxy only; SMF does not separately record an exact report-closure timestamp.');
    }

    public static function report_snapshots($dates)
    {
        // Historical snapshots must be filtered by CURRENT board visibility.
        $rows = CommunityInsights::read('SELECT s.day_utc, SUM(s.posts) AS posts, SUM(s.topics) AS topics
            FROM {db_prefix}ci_snapshots AS s
            JOIN {db_prefix}boards AS b ON (b.id_board = s.id_board)
            WHERE {query_see_board} AND s.day_utc BETWEEN {int:start} AND {int:end}
            GROUP BY s.day_utc ORDER BY s.day_utc', array('start' => (int) floor($dates['start'] / 86400), 'end' => (int) floor($dates['end'] / 86400)));
        foreach ($rows as &$r) $r['date'] = gmdate('Y-m-d', (int) $r['day_utc'] * 86400); unset($r);
        return array('daily' => $rows, 'note' => 'Daily UTC snapshots begin accumulating after installation. Historical days are not fabricated or backfilled.');
    }

    public static function reportPresets()
    {
        return array('general' => array('overview','activity','discussion','boards'),
            'growth' => array('overview','members','activity'),
            'moderation' => array('moderation','discussion'),
            'engagement' => array('activity','popular','boards','members'));
    }

    public static function layoutMeta()
    {
        global $user_info;
        $row = CommunityInsights::first('SELECT config FROM {db_prefix}ci_layout_meta WHERE id_member = {int:member}', array('member' => (int) $user_info['id']));
        $data = !empty($row['config']) ? json_decode($row['config'], true) : array();
        return is_array($data) ? $data : array();
    }

    public static function saveLayoutMeta($widgets, $sizes, $preset)
    {
        global $smcFunc, $user_info;
        $allowed = CommunityInsights::allowedWidgets(); $widths = array();
        foreach ($widgets as $id) if (in_array($id, $allowed, true)) $widths[$id] = isset($sizes[$id]) && (int) $sizes[$id] === 2 ? 2 : 1;
        $config = array('widths' => $widths, 'preset' => isset(self::reportPresets()[$preset]) ? $preset : 'custom');
        $smcFunc['db_insert']('replace', '{db_prefix}ci_layout_meta', array('id_member' => 'int', 'config' => 'string', 'updated' => 'int'),
            array((int) $user_info['id'], json_encode($config), time()), array('id_member'));
    }

    public static function reportRecord($id)
    {
        global $user_info;
        if ($id <= 0) return array();
        return CommunityInsights::first('SELECT id_report, id_member, title, report_type, options, created, updated
          FROM {db_prefix}ci_reports WHERE id_report = {int:report} AND id_member = {int:member}',
          array('report' => (int) $id, 'member' => (int) $user_info['id']));
    }

    public static function savedReports()
    {
        global $user_info;
        return CommunityInsights::read('SELECT id_report, title, report_type, options, created, updated
          FROM {db_prefix}ci_reports WHERE id_member = {int:member} ORDER BY updated DESC LIMIT 100', array('member' => (int) $user_info['id']));
    }

    public static function saveReport()
    {
        global $smcFunc, $user_info;
        checkSession('post'); validateToken('ci_report');
        if (!CommunityInsights::can('ci_view')) fatal_lang_error('ci_bad_request', false);
        $id = !empty($_POST['id_report']) ? (int) $_POST['id_report'] : 0;
        if ($id && !self::reportRecord($id)) fatal_lang_error('ci_bad_request', false);
        $type = isset($_POST['report_type']) ? (string) $_POST['report_type'] : '';
        if (!self::allowed($type)) fatal_lang_error('ci_bad_request', false);
        $title = preg_replace('/[\x00-\x1f\x7f]+/', ' ', trim(strip_tags(isset($_POST['title']) ? (string) $_POST['title'] : '')));
        if ($title === '' || strlen($title) > 120) fatal_lang_error('ci_bad_request', false);
        $options = array('days' => isset($_POST['days']) ? (int) $_POST['days'] : 30,
            'from' => isset($_POST['from']) ? (string) $_POST['from'] : '', 'to' => isset($_POST['to']) ? (string) $_POST['to'] : '',
            'boards' => isset($_POST['boards']) && is_array($_POST['boards']) ? array_slice(array_values(array_unique(array_map('intval', $_POST['boards']))), 0, 5) : array());
        $options['days'] = in_array($options['days'], array(7,30,90,365), true) ? $options['days'] : 30;
        // Persist only validated calendar inputs.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $options['from']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $options['to'])) { $options['from'] = ''; $options['to'] = ''; }
        if ($id)
            $smcFunc['db_query']('', 'UPDATE {db_prefix}ci_reports SET title = {string:title}, report_type = {string:type},
              options = {string:options}, updated = {int:now} WHERE id_report = {int:id} AND id_member = {int:member}',
              array('title' => $title, 'type' => $type, 'options' => json_encode($options), 'now' => time(), 'id' => $id, 'member' => (int) $user_info['id']));
        else
            $smcFunc['db_insert']('insert', '{db_prefix}ci_reports',
              array('id_member' => 'int', 'title' => 'string-120', 'report_type' => 'string-32', 'options' => 'string', 'created' => 'int', 'updated' => 'int'),
              array((int) $user_info['id'], $title, $type, json_encode($options), time(), time()), array('id_report'));
        redirectexit('action=communityinsights;sa=saved');
    }

    public static function deleteReport()
    {
        global $smcFunc, $user_info;
        checkSession('post'); validateToken('ci_report');
        $id = isset($_POST['id_report']) ? (int) $_POST['id_report'] : 0;
        if (!self::reportRecord($id)) fatal_lang_error('ci_bad_request', false);
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}ci_schedules WHERE id_report = {int:id} AND id_member = {int:member}', array('id' => $id, 'member' => (int) $user_info['id']));
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}ci_reports WHERE id_report = {int:id} AND id_member = {int:member}', array('id' => $id, 'member' => (int) $user_info['id']));
        redirectexit('action=communityinsights;sa=saved');
    }

    public static function recordExport($type, $format, $dates, $reportId = 0)
    {
        global $smcFunc, $user_info;
        $smcFunc['db_insert']('insert', '{db_prefix}ci_export_history',
            array('id_member' => 'int', 'id_report' => 'int', 'report_type' => 'string-32', 'format' => 'string-8', 'range_start' => 'int', 'range_end' => 'int', 'exported' => 'int'),
            array((int) $user_info['id'], (int) $reportId, $type, $format, $dates['start'], $dates['end'], time()), array('id_export'));
    }

    public static function exportHistory()
    {
        global $user_info;
        return CommunityInsights::read('SELECT id_export, id_report, report_type, format, range_start, range_end, exported
          FROM {db_prefix}ci_export_history WHERE id_member = {int:member} ORDER BY id_export DESC LIMIT 100', array('member' => (int) $user_info['id']));
    }

    public static function schedules()
    {
        global $user_info;
        return CommunityInsights::read('SELECT s.id_schedule, s.id_report, r.title, s.frequency, s.next_send, s.enabled
          FROM {db_prefix}ci_schedules AS s JOIN {db_prefix}ci_reports AS r ON (r.id_report = s.id_report)
          WHERE s.id_member = {int:member} ORDER BY s.id_schedule DESC', array('member' => (int) $user_info['id']));
    }

    public static function saveSchedule()
    {
        global $smcFunc, $user_info;
        if (!allowedTo('admin_forum')) fatal_lang_error('ci_bad_request', false);
        checkSession('post'); validateToken('ci_automation');
        $id = isset($_POST['id_report']) ? (int) $_POST['id_report'] : 0;
        $row = self::reportRecord($id);
        if (!$row || !self::allowed($row['report_type'])) fatal_lang_error('ci_bad_request', false);
        $freq = isset($_POST['frequency']) ? (string) $_POST['frequency'] : 'weekly';
        if (!in_array($freq, array('daily','weekly','monthly'), true)) fatal_lang_error('ci_bad_request', false);
        $existing = CommunityInsights::first('SELECT id_schedule FROM {db_prefix}ci_schedules WHERE id_report = {int:report} AND id_member = {int:member}',
          array('report' => $id, 'member' => (int) $user_info['id']));
        $next = time() + ($freq === 'daily' ? 86400 : ($freq === 'weekly' ? 604800 : 2592000));
        if ($existing)
            $smcFunc['db_query']('', 'UPDATE {db_prefix}ci_schedules SET frequency={string:freq}, next_send={int:next}, enabled={int:on}
              WHERE id_schedule={int:id} AND id_member={int:member}',
              array('freq' => $freq, 'next' => $next, 'on' => 1, 'id' => (int) $existing['id_schedule'], 'member' => (int) $user_info['id']));
        else
            $smcFunc['db_insert']('insert', '{db_prefix}ci_schedules',
              array('id_member' => 'int', 'id_report' => 'int', 'frequency' => 'string-12', 'next_send' => 'int', 'enabled' => 'int'),
              array((int) $user_info['id'], $id, $freq, $next, 1), array('id_schedule'));
        redirectexit('action=communityinsights;sa=automation');
    }

    public static function deleteSchedule()
    {
        global $smcFunc, $user_info;
        if (!allowedTo('admin_forum')) fatal_lang_error('ci_bad_request', false);
        checkSession('post'); validateToken('ci_automation');
        $id = isset($_POST['id_schedule']) ? (int) $_POST['id_schedule'] : 0;
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}ci_schedules WHERE id_schedule={int:id} AND id_member={int:member}',
          array('id' => $id, 'member' => (int) $user_info['id']));
        redirectexit('action=communityinsights;sa=automation');
    }

    public static function alertRules()
    {
        global $user_info;
        return CommunityInsights::read('SELECT id_rule, metric, comparator, threshold, enabled, last_triggered FROM {db_prefix}ci_alert_rules
           WHERE id_member={int:member} ORDER BY id_rule DESC', array('member' => (int) $user_info['id']));
    }

    public static function saveAlert()
    {
        global $smcFunc, $user_info;
        if (!allowedTo('admin_forum')) fatal_lang_error('ci_bad_request', false);
        checkSession('post'); validateToken('ci_alert');
        $metric = isset($_POST['metric']) ? (string) $_POST['metric'] : '';
        $comparison = isset($_POST['comparator']) ? (string) $_POST['comparator'] : '';
        if (!in_array($metric, array('posts_day', 'topics_day', 'posts_change_pct', 'posts_drop_pct', 'new_members_day', 'unanswered_topics', 'open_reports'), true)
            || !in_array($comparison, array('gt','lt'), true)) fatal_lang_error('ci_bad_request', false);
        $threshold = isset($_POST['threshold']) ? (float) $_POST['threshold'] : -1;
        if (!is_finite($threshold) || $threshold < 0 || $threshold > 1000000) fatal_lang_error('ci_bad_request', false);
        $smcFunc['db_insert']('insert', '{db_prefix}ci_alert_rules',
          array('id_member' => 'int', 'metric' => 'string-32', 'comparator' => 'string-2', 'threshold' => 'float', 'enabled' => 'int', 'last_triggered' => 'int'),
          array((int) $user_info['id'], $metric, $comparison, $threshold, 1, 0), array('id_rule'));
        redirectexit('action=communityinsights;sa=alerts');
    }

    public static function deleteAlert()
    {
        global $smcFunc, $user_info;
        if (!allowedTo('admin_forum')) fatal_lang_error('ci_bad_request', false);
        checkSession('post'); validateToken('ci_alert');
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}ci_alert_rules WHERE id_rule={int:id} AND id_member={int:member}',
          array('id' => isset($_POST['id_rule']) ? (int) $_POST['id_rule'] : 0, 'member' => (int) $user_info['id']));
        redirectexit('action=communityinsights;sa=alerts');
    }

    public static function accessOverview()
    {
        return array('ci_view' => 'Open analytics dashboard', 'ci_core' => 'View general metrics', 'ci_members' => 'Member engagement and cohort analysis',
          'ci_moderation' => 'Moderation metrics', 'ci_integrations' => 'Provider metrics', 'ci_export' => 'Export permitted reports');
    }

    /**
     * Scheduled delivery does not emulate a signed-in user's SMF permissions.
     * Instead, after checking that the recipient is still an Administrator, it
     * sends a report-type-specific, bounded aggregate summary for the SAVED
     * date selection. The full report is available only after signing in.
     */
    public static function scheduledReportSummary($type, array $options, $now)
    {
        $dates = CommunityInsights::dateParams($options, (int) $now);
        $start = (int) $dates['start'];
        $end = (int) $dates['end'];
        $lines = array('Selected report period (UTC): ' . gmdate('Y-m-d H:i', $start) . ' to ' . gmdate('Y-m-d H:i', $end));

        // A snapshot covers the entire UTC day. Never count partial days as if
        // they were complete; never manufacture records before installation.
        $firstDay = (int) floor(($start + 86399) / 86400);
        $lastDay = (int) floor(($end - 86399) / 86400);
        $aggregateTypes = array('overview', 'smf', 'activity', 'popular', 'content', 'scoring',
            'trending', 'heatmap', 'boards', 'compare', 'snapshots');
        if (in_array($type, $aggregateTypes, true))
        {
            // Board comparisons must use the same selection in both the
            // aggregate header and the board breakdown.
            $chosen = in_array($type, array('boards', 'compare'), true) && isset($options['boards']) && is_array($options['boards'])
                ? array_slice(array_values(array_unique(array_filter(array_map('intval', $options['boards'])))), 0, 5) : array();
            $boardWhere = $chosen ? ' AND id_board IN ({array_int:boards})' : '';
            $totalsParams = array('start' => $firstDay, 'end' => $lastDay);
            if ($chosen) $totalsParams['boards'] = $chosen;
            if ($firstDay > $lastDay)
                $lines[] = 'No complete UTC day falls entirely within the selected period.';
            else
            {
                $totals = CommunityInsights::first('SELECT COUNT(DISTINCT day_utc) AS days,
                    COALESCE(SUM(posts), 0) AS posts, COALESCE(SUM(topics), 0) AS topics
                    FROM {db_prefix}ci_snapshots
                    WHERE day_utc BETWEEN {int:start} AND {int:end}' . $boardWhere,
                    $totalsParams);
                $days = (int) (isset($totals['days']) ? $totals['days'] : 0);
                if (!$days)
                    $lines[] = 'No captured daily snapshots are available for this period.';
                else
                {
                    $lines[] = 'Complete UTC days captured: ' . $days . ' (missing days are not filled in).';
                    $lines[] = 'Approved posts recorded: ' . (int) $totals['posts'];
                    $lines[] = 'New topics recorded: ' . (int) $totals['topics'];
                    if (in_array($type, array('boards', 'compare'), true))
                    {
                        $boardParams = array('start' => $firstDay, 'end' => $lastDay, 'limit' => 3);
                        $where = '';
                        if ($chosen)
                        {
                            $where = ' AND s.id_board IN ({array_int:boards})';
                            $boardParams['boards'] = $chosen;
                        }
                        $boards = CommunityInsights::read('SELECT b.name, SUM(s.posts) AS posts
                            FROM {db_prefix}ci_snapshots AS s JOIN {db_prefix}boards AS b ON (b.id_board = s.id_board)
                            WHERE s.day_utc BETWEEN {int:start} AND {int:end}' . $where . '
                            GROUP BY s.id_board, b.name ORDER BY posts DESC LIMIT {int:limit}', $boardParams);
                        foreach ($boards as $board)
                            $lines[] = 'Board: ' . preg_replace('/[\x00-\x1f\x7f]+/', ' ', strip_tags((string) $board['name']))
                                . ' - posts: ' . (int) $board['posts'];
                    }
                }
            }
            if (in_array($type, array('popular', 'scoring', 'trending', 'heatmap'), true))
                $lines[] = 'Topic-level rankings, momentum, and heatmap detail require opening the full report.';
            if ($type === 'compare')
                $lines[] = 'Comparative response-rate detail is available after sign-in.';
        }
        elseif (in_array($type, array('members', 'onboarding', 'retention'), true))
        {
            $r = CommunityInsights::first('SELECT COUNT(*) AS registrations FROM {db_prefix}members
                WHERE date_registered BETWEEN {int:start} AND {int:end} AND is_activated = {int:active}',
                array('start' => $start, 'end' => $end, 'active' => 1));
            $lines[] = 'Activated registrations in selected period: ' . (int) $r['registrations'];
            $lines[] = 'Cohort retention, first-post activity and individual details require opening the full report.';
        }
        elseif (in_array($type, array('moderation', 'modtrends'), true))
        {
            $r = CommunityInsights::first('SELECT COUNT(*) AS reports, SUM(CASE WHEN closed = {int:open} THEN 1 ELSE 0 END) AS still_open
                FROM {db_prefix}log_reported WHERE time_started BETWEEN {int:start} AND {int:end}',
                array('start' => $start, 'end' => $end, 'open' => 0));
            $lines[] = 'Reports opened during selected period: ' . (int) $r['reports'];
            $lines[] = 'Of those, currently open: ' . (int) $r['still_open'];
            $lines[] = 'Current status is not a historical state snapshot.';
        }
        elseif (in_array($type, array('response', 'discussion'), true))
        {
            $r = CommunityInsights::first('SELECT COUNT(*) AS topics FROM {db_prefix}topics AS t
                JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
                WHERE t.approved = {int:yes} AND m.approved = {int:yes}
                AND m.poster_time BETWEEN {int:start} AND {int:end}',
                array('yes' => 1, 'start' => $start, 'end' => $end));
            $lines[] = 'Approved discussions started during period: ' . (int) $r['topics'];
            $lines[] = 'First-response times and unanswered status require the full permission-checked report.';
        }
        elseif ($type === 'contributions')
        {
            $r = CommunityInsights::first('SELECT COUNT(*) AS replies,
                COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member END) AS contributors
                FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
                WHERE m.approved = {int:yes} AND t.approved = {int:yes}
                AND m.id_msg != t.id_first_msg AND m.id_member != t.id_member_started
                AND m.poster_time BETWEEN {int:start} AND {int:end}',
                array('yes' => 1, 'start' => $start, 'end' => $end));
            $lines[] = 'Replies from someone other than the topic starter: ' . (int) $r['replies'];
            $lines[] = 'Distinct registered contributors: ' . (int) $r['contributors'];
        }
        else
            $lines[] = 'This custom report has no safe background summary; open it to run with current permissions.';
        $lines[] = 'This email is a bounded aggregate summary, not a full report or attachment.';
        return implode("\n", $lines);
    }

    /** Open, mature topics that have received no approved external reply. */
    public static function currentUnansweredCount($now)
    {
        global $modSettings;
        $hours = max(1, min(336, (int) (isset($modSettings['ci_response_hours']) ? $modSettings['ci_response_hours'] : 48)));
        $params = array('yes' => 1, 'zero' => 0, 'mature' => max(0, (int) $now - $hours * 3600));
        $recycle = '';
        if (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']))
        {
            $recycle = ' AND t.id_board != {int:recycle_board}';
            $params['recycle_board'] = (int) $modSettings['recycle_board'];
        }
        $row = CommunityInsights::first('SELECT COUNT(*) AS value
            FROM {db_prefix}topics AS t
            JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
            WHERE t.approved = {int:yes} AND fm.approved = {int:yes}
              AND t.locked = {int:zero} AND fm.poster_time <= {int:mature}' . $recycle . '
              AND NOT EXISTS (SELECT 1 FROM {db_prefix}messages AS r
                WHERE r.id_topic = t.id_topic AND r.id_msg != t.id_first_msg AND r.approved = {int:yes}
                AND r.poster_time >= fm.poster_time
                AND (CASE WHEN t.id_member_started > 0 THEN r.id_member != t.id_member_started
                    ELSE r.poster_name != fm.poster_name END))', $params);
        return (int) (isset($row['value']) ? $row['value'] : 0);
    }

    /** Scheduled job checks the report owner again, and never emails private posts. */
    public static function scheduled()
    {
        global $smcFunc, $modSettings, $sourcedir, $scripturl;
        if (empty($modSettings['ci_enabled'])) return true;
        if (!function_exists('sendmail'))
            require_once($sourcedir . '/Subs-Post.php');
        self::captureSnapshot();
        $now = time();
        $schedules = CommunityInsights::read('SELECT s.id_schedule, s.id_member, s.id_report, s.frequency, s.next_send,
            r.title, r.report_type, r.options FROM {db_prefix}ci_schedules AS s JOIN {db_prefix}ci_reports AS r
            ON (r.id_report = s.id_report AND r.id_member = s.id_member)
            WHERE s.enabled = {int:on} AND s.next_send <= {int:now} LIMIT 30',
            array('on' => 1, 'now' => $now));
        foreach ($schedules as $s)
        {
            $member = self::adminRecipient((int) $s['id_member']);
            $sent = false;
            if ($member)
            {
                $options = json_decode((string) $s['options'], true);
                if (!is_array($options)) $options = array();
                $title = preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', strip_tags((string) $s['title']));
                $type = preg_replace('/[^a-z0-9_]/', '', (string) $s['report_type']);
                $body = 'Community Insights scheduled summary: ' . $title . "\n"
                    . 'Report type: ' . $type . "\n\n"
                    . self::scheduledReportSummary($type, $options, $now) . "\n\n"
                    . 'Open your saved report after signing in: ' . $scripturl
                    . '?action=communityinsights;sa=report;id=' . (int) $s['id_report'] . "\n";
                $sent = (bool) sendmail($member['email_address'], 'Community Insights: ' . $title, $body);
            }
            $step = $s['frequency'] === 'daily' ? 86400 : ($s['frequency'] === 'weekly' ? 604800 : 2592000);
            $next = $member && !$sent ? $now + 3600 : max($now + 300, (int) $s['next_send'] + $step);
            $smcFunc['db_query']('', 'UPDATE {db_prefix}ci_schedules SET next_send = {int:next} WHERE id_schedule = {int:id}',
                array('next' => $next, 'id' => (int) $s['id_schedule']));
        }
        self::runAlerts($now);
        return true;
    }

    private static function adminRecipient($memberId)
    {
        $r = CommunityInsights::first('SELECT email_address, id_group, additional_groups, is_activated
            FROM {db_prefix}members WHERE id_member={int:member}', array('member' => $memberId));
        if (!$r || (int) $r['is_activated'] !== 1 || !filter_var($r['email_address'], FILTER_VALIDATE_EMAIL)) return null;
        $groups = array_merge(array((int) $r['id_group']), array_map('intval', explode(',', $r['additional_groups'])));
        return in_array(1, $groups, true) ? $r : null;
    }

    public static function captureSnapshot()
    {
        global $smcFunc, $modSettings;
        $day = (int) floor((time() - 86400) / 86400); // Only complete UTC days, no shifting partial snapshots.
        $start = $day * 86400; $end = $start + 86399;
        $rows = CommunityInsights::read('SELECT b.id_board,
            COUNT(m.id_msg) AS posts,
            COUNT(DISTINCT CASE WHEN m.id_msg = t.id_first_msg THEN t.id_topic END) AS topics
            FROM {db_prefix}boards AS b
            LEFT JOIN {db_prefix}topics AS t ON (t.id_board = b.id_board AND t.approved = {int:yes})
            LEFT JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.approved = {int:yes}
              AND m.poster_time BETWEEN {int:start} AND {int:end})
            GROUP BY b.id_board', array('yes' => 1, 'start' => $start, 'end' => $end));
        foreach ($rows as $r)
            $smcFunc['db_insert']('replace', '{db_prefix}ci_snapshots',
              array('day_utc' => 'int', 'id_board' => 'int', 'posts' => 'int', 'topics' => 'int', 'captured' => 'int'),
              array($day, (int) $r['id_board'], (int) $r['posts'], (int) $r['topics'], time()), array('day_utc', 'id_board'));
        // Retain two years; scheduled cleanup is bounded and only touches mod data.
        $smcFunc['db_query']('', 'DELETE FROM {db_prefix}ci_snapshots WHERE day_utc < {int:minimum}', array('minimum' => $day - 730));
    }

    private static function snapshotTotals($day)
    {
        $r = CommunityInsights::first('SELECT COALESCE(SUM(posts),0) AS posts, COALESCE(SUM(topics),0) AS topics
             FROM {db_prefix}ci_snapshots WHERE day_utc = {int:day}', array('day' => $day));
        return array('posts' => (int) $r['posts'], 'topics' => (int) $r['topics']);
    }

    private static function runAlerts($now)
    {
        global $smcFunc;
        $rules = CommunityInsights::read('SELECT id_rule, id_member, metric, comparator, threshold, last_triggered
             FROM {db_prefix}ci_alert_rules WHERE enabled={int:on} LIMIT 200', array('on' => 1));
        if (!$rules) return;
        $y = self::snapshotTotals((int) floor(($now - 86400) / 86400));
        $dayBefore = self::snapshotTotals((int) floor(($now - 172800) / 86400));
        $reported = CommunityInsights::first('SELECT COUNT(*) AS value FROM {db_prefix}log_reported WHERE closed={int:open}', array('open' => 0));
        $members = CommunityInsights::first('SELECT COUNT(*) AS value FROM {db_prefix}members WHERE date_registered BETWEEN {int:start} AND {int:end} AND is_activated = {int:active}',
            array('start' => (int) floor(($now - 86400) / 86400) * 86400, 'end' => (int) floor(($now - 86400) / 86400) * 86400 + 86399, 'active' => 1));
        $unansweredCount = self::currentUnansweredCount($now);
        $metrics = array('posts_day' => $y['posts'], 'topics_day' => $y['topics'],
            'posts_change_pct' => $dayBefore['posts'] ? round(abs($y['posts'] - $dayBefore['posts']) * 100 / $dayBefore['posts'], 2) : 0,
            'posts_drop_pct' => $dayBefore['posts'] ? round(max(0, $dayBefore['posts'] - $y['posts']) * 100 / $dayBefore['posts'], 2) : 0,
            'new_members_day' => (int) $members['value'], 'unanswered_topics' => $unansweredCount,
            'open_reports' => (int) $reported['value']);
        foreach ($rules as $rule)
        {
            if ((int) $rule['last_triggered'] >= $now - 86400) continue;
            if (!isset($metrics[$rule['metric']])) continue;
            $value = $metrics[$rule['metric']]; $threshold = (float) $rule['threshold'];
            $trigger = $rule['comparator'] === 'gt' ? $value > $threshold : $value < $threshold;
            if (!$trigger) continue;
            $member = self::adminRecipient((int) $rule['id_member']);
            if (!$member) continue;
            if (!sendmail($member['email_address'], 'Community Insights threshold alert',
                'Your Community Insights alert triggered. Metric: ' . $rule['metric'] . "\nValue: " . $value .
                "\nThreshold: " . $rule['comparator'] . ' ' . $threshold . "\n\nReview the analytics dashboard for details."))
                continue;
            $smcFunc['db_query']('', 'UPDATE {db_prefix}ci_alert_rules SET last_triggered={int:now} WHERE id_rule={int:id}',
              array('now' => $now, 'id' => (int) $rule['id_rule']));
        }
    }
}

/** Page controller for the expanded reporting suite. */
function CommunityInsightsAdvancedPage($sa)
{
    global $context, $txt, $scripturl, $user_info;
    if (!CommunityInsights::can('ci_view') || empty($user_info['id']))
        fatal_lang_error('ci_bad_request', false);

    $changes = array('save_report' => 'saveReport', 'delete_report' => 'deleteReport',
        'save_schedule' => 'saveSchedule', 'delete_schedule' => 'deleteSchedule',
        'save_alert' => 'saveAlert', 'delete_alert' => 'deleteAlert');
    if (isset($changes[$sa]))
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') fatal_lang_error('ci_bad_request', false);
        call_user_func(array('CommunityInsightsAdvanced', $changes[$sa]));
        return;
    }
    if (in_array($sa, array('automation','alerts','access'), true) && !allowedTo('admin_forum'))
        fatal_lang_error('ci_bad_request', false);

    loadTemplate('CommunityInsights');
    loadCSSFile('communityinsights.css', array('minimize' => true), 'smf_communityinsights');
    $context['sub_template'] = 'ci_advanced';
    $context['page_title'] = $txt['ci_title'];
    $context['robot_no_index'] = true;
    $context['linktree'][] = array('name' => $txt['ci_title'], 'url' => $scripturl . '?action=communityinsights');
    $types = CommunityInsightsAdvanced::features();
    $allowed = array();
    foreach ($types as $id => $label)
        if (CommunityInsightsAdvanced::allowed($id)) $allowed[$id] = $label;

    $report = array(); $options = $_GET;
    $type = isset($_GET['type']) ? (string) $_GET['type'] : 'content';
    if ($sa === 'report' && !empty($_GET['id']))
    {
        $report = CommunityInsightsAdvanced::reportRecord((int) $_GET['id']);
        if (!$report) fatal_lang_error('ci_bad_request', false);
        $type = $report['report_type'];
        $opts = json_decode($report['options'], true);
        $options = is_array($opts) ? $opts : array();
    }
    if (!isset($allowed[$type])) $type = key($allowed);
    if ($type === null) $type = '';
    foreach (array('from','to') as $field)
        if (isset($options[$field]) && !is_string($options[$field])) $options[$field] = '';
    $dates = CommunityInsightsAdvanced::dateParams($options);
    $data = $sa === 'report' && $type !== '' ? CommunityInsightsAdvanced::calculate($type, $dates, $options) : array();
    $boards = array();
    if ($sa === 'report')
        $boards = CommunityInsights::read('SELECT b.id_board, b.name FROM {db_prefix}boards AS b
            WHERE {query_see_board} ORDER BY b.board_order ASC LIMIT 300');
    $context['ci_advanced'] = array(
        'sa' => $sa, 'types' => $allowed, 'report' => $report, 'type' => $type, 'options' => $options,
        'dates' => $dates, 'data' => $data, 'boards' => $boards,
        'charts' => $sa === 'report' && $type !== '' ? CommunityInsightsAdvanced::chartExtensions($type, $dates) : array(),
        'saved' => in_array($sa, array('saved','automation'), true) ? CommunityInsightsAdvanced::savedReports() : array(),
        'history' => $sa === 'history' ? CommunityInsightsAdvanced::exportHistory() : array(),
        'schedules' => $sa === 'automation' ? CommunityInsightsAdvanced::schedules() : array(),
        'alerts' => $sa === 'alerts' ? CommunityInsightsAdvanced::alertRules() : array(),
        'permissions' => CommunityInsightsAdvanced::accessOverview(),
        'can_export' => CommunityInsights::can('ci_export'), 'admin' => allowedTo('admin_forum'),
    );
    createToken('ci_report');
    createToken('ci_automation');
    createToken('ci_alert');
}
