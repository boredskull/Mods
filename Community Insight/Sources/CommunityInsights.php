<?php
/**
 * Community Insights - independent analytics dashboard for SMF 2.1.x.
 * Data from other modifications is optional and permission scoped.
 *
 * @package CommunityInsights
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */
if (!defined('SMF'))
	die('No direct access...');

class CommunityInsights
{
	const VERSION = '1.1.1';

	public static function enabled()
	{
		global $modSettings;
		return !empty($modSettings['ci_enabled']);
	}

	public static function can($permission)
	{
		return allowedTo('admin_forum') || allowedTo($permission);
	}

	public static function widgets()
	{
		return array(
			'overview' => 'ci_core',
			'smf' => 'ci_core',
			'activity' => 'ci_core',
			'popular' => 'ci_core',
			'discussion' => 'ci_core',
			'boards' => 'ci_core',
			'members' => 'ci_members',
			'moderation' => 'ci_moderation',
			'recommendations' => 'ci_core',
			'integrations' => 'ci_integrations',
		);
	}

	public static function allowedWidgets()
	{
		global $modSettings;
		$out = array();
		foreach (self::widgets() as $id => $permission)
		{
			if (!self::can($permission))
				continue;
			if ($id === 'recommendations' && empty($modSettings['ci_recommendations']))
				continue;
			if ($id === 'integrations')
			{
				if (empty($modSettings['ci_integrations']))
					continue;
				require_once(dirname(__FILE__) . '/CommunityInsights-Providers.php');
				if (!CommunityInsightsProviders::hasAvailableForUser())
					continue;
			}
			$out[] = $id;
		}
		return $out;
	}

	public static function cleanWidgets($widgets, $permitted)
	{
		if (!is_array($widgets))
			return array();
		return array_values(array_unique(array_intersect(array_map('strval', array_filter($widgets, 'is_scalar')), $permitted)));
	}

	/**
	 * Escape plain text for HTML. Uses SMF's own helper so it respects the forum
	 * charset and never blanks out text that isn't valid UTF-8.
	 * Do NOT use on topic subjects or board names: SMF stores those already escaped.
	 */
	public static function h($value)
	{
		global $smcFunc;
		return $smcFunc['htmlspecialchars']((string) $value, ENT_QUOTES);
	}

	/**
	 * Prepare SMF-stored (already escaped) subjects for display: censor only.
	 */
	public static function prepareTopics(array $rows)
	{
		foreach ($rows as &$row)
			if (isset($row['subject']))
				censorText($row['subject']);
		return $rows;
	}

	/**
	 * Run a query and return all rows. With $skipErrors a failed query returns an
	 * empty array instead of a fatal database error (used by optional integrations).
	 */
	public static function read($sql, $params = array(), $skipErrors = false)
	{
		global $smcFunc;
		if ($skipErrors)
			$params['db_error_skip'] = true;
		$request = $smcFunc['db_query']('', $sql, $params);
		if ($request === false)
			return array();
		$rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$rows[] = $row;
		$smcFunc['db_free_result']($request);
		return $rows;
	}

	public static function first($sql, $params = array(), $skipErrors = false)
	{
		$rows = self::read($sql, $params, $skipErrors);
		return isset($rows[0]) ? $rows[0] : array();
	}

	/**
	 * Single UTC reporting-window implementation for the dashboard, reports and
	 * exports. Presets end at the last complete five-minute boundary, allowing
	 * independently opened views to share the same cache window safely.
	 * Boundaries are inclusive and never extend into the future.
	 */
	public static function dateParams($options, $now = null)
	{
		$now = $now === null ? time() : (int) $now;
		$end = (int) (floor($now / 300) * 300);
		$rawDays = isset($options['days']) && is_scalar($options['days']) ? (int) $options['days'] : 30;
		$days = in_array($rawDays, array(7, 30, 90, 365), true) ? $rawDays : 30;
		$start = $end - $days * 86400 + 1;
		$custom = false;
		if (isset($options['from'], $options['to']) && is_string($options['from']) && is_string($options['to'])
			&& preg_match('/^\d{4}-\d{2}-\d{2}$/D', $options['from'])
			&& preg_match('/^\d{4}-\d{2}-\d{2}$/D', $options['to']))
		{
			$a = DateTimeImmutable::createFromFormat('!Y-m-d', $options['from'], new DateTimeZone('UTC'));
			$b = DateTimeImmutable::createFromFormat('!Y-m-d', $options['to'], new DateTimeZone('UTC'));
			if ($a && $b && $a->format('Y-m-d') === $options['from'] && $b->format('Y-m-d') === $options['to']
				&& $a <= $b && $b->getTimestamp() <= $now
				&& $b->getTimestamp() - $a->getTimestamp() <= 365 * 86400)
			{
				$start = $a->getTimestamp();
				$end = min($end, $b->getTimestamp() + 86399);
				$custom = true;
			}
		}
		return array('start' => (int) $start, 'end' => (int) $end, 'days' => $days,
			'custom' => $custom, 'prev' => (int) ($start - ($end - $start + 1)));
	}

	/** The dashboard uses exactly the same date validation as saved reports. */
	public static function dates()
	{
		return self::dateParams($_GET);
	}

	/**
	 * Keep a CSV/JSON export pinned to the exact period shown on the form.
	 * Token and session validation happen in the controller before this runs.
	 * Invalid or overlong submitted periods are rejected, not silently expanded.
	 */
	public static function exportDates($dates, $post, $now = null)
	{
		if (!isset($post['ci_range_start']) && !isset($post['ci_range_end']))
			return $dates; // Support older links/forms that don't include the pair.
		if (!isset($post['ci_range_start'], $post['ci_range_end'])
			|| !is_scalar($post['ci_range_start']) || !is_scalar($post['ci_range_end'])
			|| !preg_match('/^[0-9]{1,12}$/D', (string) $post['ci_range_start'])
			|| !preg_match('/^[0-9]{1,12}$/D', (string) $post['ci_range_end']))
			return null;
		$start = (int) $post['ci_range_start'];
		$end = (int) $post['ci_range_end'];
		$now = $now === null ? time() : (int) $now;
		if ($start < 0 || $end < $start || $end > $now || $end - $start + 1 > 366 * 86400)
			return null;
		$dates['start'] = $start;
		$dates['end'] = $end;
		$dates['prev'] = $start - ($end - $start + 1);
		return $dates;
	}

	public static function scope($dates)
	{
		global $modSettings;
		$conditions = array('t.approved = {int:approved}', 'b.id_board = t.id_board', '{query_see_board}');
		$params = array('approved' => 1, 'start' => (int) $dates['start'], 'end' => (int) $dates['end']);
		if (!empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']))
		{
			$conditions[] = 't.id_board != {int:recycle_board}';
			$params['recycle_board'] = (int) $modSettings['recycle_board'];
		}
		return array('where' => implode(' AND ', $conditions), 'params' => $params);
	}

	/**
	 * Cache built-in report results in the current member's security context.
	 * Third-party integrations are intentionally not cached: their providers may
	 * enforce additional permissions or change availability between page loads.
	 */
	public static function cached($widget, $dates, $callback)
	{
		global $modSettings, $user_info;
		$minutes = isset($modSettings['ci_cache_minutes']) ? max(0, min(1440, (int) $modSettings['ci_cache_minutes'])) : 5;
		if ($minutes === 0 || $widget === 'integrations')
			return call_user_func($callback);

		// Both the identity and current report permissions matter. Board visibility
		// alone does not describe member-specific/provider permission scopes.
		$permissions = array();
		foreach (array('admin_forum', 'ci_view', 'ci_core', 'ci_members', 'ci_moderation', 'ci_integrations') as $permission)
			$permissions[] = allowedTo($permission) ? '1' : '0';

		$key = 'ci_' . $widget . '_' . md5(implode('|', array(
			self::VERSION, (int) $user_info['id'], $dates['start'], $dates['end'],
			isset($user_info['query_see_board']) ? $user_info['query_see_board'] : '',
			implode('', $permissions), isset($modSettings['ci_response_hours']) ? $modSettings['ci_response_hours'] : 48,
		)));
		$data = cache_get_data($key, $minutes * 60);
		if ($data === null)
		{
			$data = call_user_func($callback);
			cache_put_data($key, $data, $minutes * 60);
		}
		return $data;
	}

	public static function getLayout()
	{
		global $user_info, $modSettings;
		$permitted = self::allowedWidgets();
		$default = json_decode(isset($modSettings['ci_default_widgets']) ? $modSettings['ci_default_widgets'] : '', true);
		$default = self::cleanWidgets(is_array($default) ? $default : array('overview', 'discussion', 'boards', 'members', 'recommendations'), $permitted);
		$row = self::first('SELECT widgets FROM {db_prefix}ci_layouts WHERE id_member = {int:member}', array('member' => (int) $user_info['id']));
		if (isset($row['widgets']))
		{
			$selection = json_decode($row['widgets'], true);
			if (is_array($selection))
				return self::cleanWidgets($selection, $permitted);
		}
		return $default;
	}

	public static function saveLayout()
	{
		global $smcFunc, $user_info;
		checkSession('post');
		validateToken('ci_layout');
		$selection = self::cleanWidgets(isset($_POST['widgets']) ? $_POST['widgets'] : array(), self::allowedWidgets());
        // Optional dashboard presets and per-widget widths. The original layout table remains compatible.
        $preset = isset($_POST['ci_preset']) ? (string) $_POST['ci_preset'] : 'custom';
        if (isset(CommunityInsightsAdvanced::reportPresets()[$preset]))
            $selection = self::cleanWidgets(CommunityInsightsAdvanced::reportPresets()[$preset], self::allowedWidgets());
        $sizes = isset($_POST['ci_sizes']) && is_array($_POST['ci_sizes']) ? $_POST['ci_sizes'] : array();
        CommunityInsightsAdvanced::saveLayoutMeta($selection, $sizes, $preset);
		$smcFunc['db_insert']('replace', '{db_prefix}ci_layouts',
			array('id_member' => 'int', 'widgets' => 'string', 'updated' => 'int'),
			array(array((int) $user_info['id'], json_encode($selection), time())), array('id_member'));
		$redirect = 'action=communityinsights;days=' . (isset($_POST['days']) ? (int) $_POST['days'] : 30);
		if (!empty($_POST['from']) && !empty($_POST['to'])
			&& preg_match('/^\d{4}-\d\d-\d\d$/D', (string) $_POST['from'])
			&& preg_match('/^\d{4}-\d\d-\d\d$/D', (string) $_POST['to']))
			$redirect .= ';from=' . $_POST['from'] . ';to=' . $_POST['to'];
		redirectexit($redirect);
	}

	public static function overview($dates)
	{
		$scope = self::scope($dates);
		$base = 'FROM {db_prefix}messages AS m INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . $scope['where'] . ' AND m.approved = {int:approved}
			AND m.poster_time BETWEEN {int:start} AND {int:end}';
		$row = self::first('SELECT COUNT(*) AS posts, COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member ELSE NULL END) AS contributors ' . $base, $scope['params']);
		$topics = self::first('SELECT COUNT(*) AS topics FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE ' . $scope['where'] . ' AND m.approved = {int:approved} AND m.poster_time BETWEEN {int:start} AND {int:end}', $scope['params']);
		$previous = $scope['params'];
		$previous['start'] = (int) $dates['prev'];
		$previous['end'] = (int) $dates['start'] - 1;
		$prev = self::first('SELECT COUNT(*) AS posts, COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member ELSE NULL END) AS contributors ' . $base, $previous);
		return array('posts' => (int) $row['posts'], 'topics' => (int) $topics['topics'], 'contributors' => (int) $row['contributors'], 'previous_posts' => (int) $prev['posts'], 'previous_contributors' => (int) $prev['contributors']);
	}

	public static function smf($dates)
	{
		$scope = self::scope($dates);
		$counts = self::first('SELECT COUNT(*) AS topics, COALESCE(SUM(t.num_replies + 1), 0) AS messages, COALESCE(SUM(t.num_views), 0) AS views
			FROM {db_prefix}topics AS t INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . $scope['where'], $scope['params']);
		return array('topics' => (int) $counts['topics'], 'messages' => (int) $counts['messages'], 'views' => (int) $counts['views']);
	}

	/** Weekly/monthly activity bins without fetching individual posts into PHP. */
	public static function activity($dates)
	{
		$scope = self::scope($dates);
		$range = max(1, $dates['end'] - $dates['start']);
		$bucket = max(86400, (int) ceil($range / 12 / 86400) * 86400);
		$params = $scope['params'];
		$params['bucket'] = $bucket;
		$rows = self::read('SELECT FLOOR((m.poster_time - {int:start}) / {int:bucket}) AS bucket, COUNT(*) AS posts
			FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . $scope['where'] . ' AND m.approved = {int:approved}
			AND m.poster_time BETWEEN {int:start} AND {int:end}
			GROUP BY FLOOR((m.poster_time - {int:start}) / {int:bucket}) ORDER BY bucket ASC', $params);
		$max = min(13, (int) ceil(($range + 1) / $bucket));
		$series = array();
		for ($i = 0; $i < $max; $i++)
			$series[$i] = array('date' => gmdate('j M', $dates['start'] + $i * $bucket), 'posts' => 0);
		foreach ($rows as $row)
			if (isset($series[(int) $row['bucket']]))
				$series[(int) $row['bucket']]['posts'] = max(0, (int) $row['posts']);
		return array_values($series);
	}

	/** Popular topics born within the selected reporting period. */
	public static function popular($dates)
	{
		$scope = self::scope($dates);
		$params = $scope['params'];
		$params['limit'] = 10;
		return self::prepareTopics(self::read('SELECT t.id_topic, m.subject, b.name AS board_name, t.num_views, t.num_replies
			FROM {db_prefix}topics AS t JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE ' . $scope['where'] . ' AND m.approved = {int:approved}
			AND m.poster_time BETWEEN {int:start} AND {int:end}
			ORDER BY t.num_views DESC LIMIT {int:limit}', $params));
	}

	public static function discussion($dates)
	{
		global $modSettings;
		$scope = self::scope($dates);
		$hours = max(1, min(336, (int) (isset($modSettings['ci_response_hours']) ? $modSettings['ci_response_hours'] : 48)));
		// Include only topics old enough to have had the full response window.
		$params = $scope['params'];
		// Maturity is relative to the end of the selected reporting period,
		// not to today. A topic must have had the entire response window
		// before it can be evaluated as of that historical cutoff.
		$params['mature'] = max(0, (int) $dates['end'] - $hours * 3600);
		$params['seconds'] = $hours * 3600;
		// A reply only counts as a response if someone other than the starter wrote it.
		// Guest starters (id 0) are matched by name instead, so guests can't "answer" themselves either.
		$summary = self::first('SELECT COUNT(*) AS eligible,
			SUM(CASE WHEN t.num_replies > 0 AND EXISTS (
				SELECT 1 FROM {db_prefix}messages AS r
				WHERE r.id_topic = t.id_topic AND r.id_msg != t.id_first_msg
				AND r.approved = 1 AND r.poster_time BETWEEN m.poster_time AND {int:end}
				AND r.poster_time <= m.poster_time + {int:seconds}
				AND (CASE WHEN t.id_member_started > 0 THEN r.id_member != t.id_member_started ELSE r.poster_name != m.poster_name END)
			) THEN 1 ELSE 0 END) AS responded
			FROM {db_prefix}topics AS t INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE ' . $scope['where'] . ' AND m.poster_time BETWEEN {int:start} AND {int:mature}', $params);
		$params['limit'] = 15;
		// A starter's follow-up posts do not make a topic answered. For the
		// actionable list, exclude external replies only up to the selected
		// report end date. Replies after it must not change historical results.
		// The summary above remains the stricter "replied within N hours" metric.
		$list = self::read('SELECT t.id_topic, m.subject, b.name AS board_name, m.poster_time
			FROM {db_prefix}topics AS t INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE ' . $scope['where'] . ' AND m.poster_time BETWEEN {int:start} AND {int:mature}
			AND NOT EXISTS (
				SELECT 1 FROM {db_prefix}messages AS r
				WHERE r.id_topic = t.id_topic AND r.id_msg != t.id_first_msg
				AND r.approved = 1 AND r.poster_time BETWEEN m.poster_time AND {int:end}
				AND (CASE WHEN t.id_member_started > 0 THEN r.id_member != t.id_member_started ELSE r.poster_name != m.poster_name END)
			)
			ORDER BY m.poster_time ASC LIMIT {int:limit}', $params);
		return array('eligible' => (int) $summary['eligible'], 'responded' => (int) $summary['responded'], 'unanswered' => self::prepareTopics($list), 'hours' => $hours);
	}

	public static function boards($dates)
	{
		$scope = self::scope($dates);
		$params = $scope['params'];
		$params['limit'] = 15;
		return self::read('SELECT b.id_board, b.name, COUNT(*) AS posts,
			COUNT(DISTINCT CASE WHEN m.id_member > 0 THEN m.id_member ELSE NULL END) AS contributors
			FROM {db_prefix}messages AS m INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE ' . $scope['where'] . ' AND m.approved = {int:approved} AND m.poster_time BETWEEN {int:start} AND {int:end}
			GROUP BY b.id_board, b.name ORDER BY posts DESC LIMIT {int:limit}', $params);
	}

	public static function members($dates)
	{
		// Aggregate cohorts. No email, IP, or identifiable member data is retrieved.
		// Only activated accounts count, so spam, unactivated and banned registrations don't skew retention.
		$first = self::first('SELECT COUNT(*) AS joined FROM {db_prefix}members
			WHERE date_registered BETWEEN {int:start} AND {int:end} AND is_activated = {int:active}',
			array('start' => $dates['start'], 'end' => $dates['end'], 'active' => 1));
		// Count only registrations whose full 30-day observation window had
		// elapsed by the selected end date (not simply by today's date).
		$cohortEnd = max(0, min((int) $dates['end'], time()) - 30 * 86400);
		$cohort = array('matured' => 0, 'first7' => 0, 'returned30' => 0);
		if ($cohortEnd >= $dates['start'])
		{
			$scope = self::scope($dates);
			$params = $scope['params'];
			$params['cohort_end'] = $cohortEnd;
			$params['active'] = 1;
			$cohort = self::first('SELECT COUNT(*) AS matured,
				SUM(CASE WHEN EXISTS (
					SELECT 1 FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					WHERE m.id_member = mem.id_member AND m.approved = 1 AND ' . $scope['where'] . '
					AND m.poster_time BETWEEN mem.date_registered AND mem.date_registered + 604800
					AND m.poster_time <= {int:end}
				) THEN 1 ELSE 0 END) AS first7,
				SUM(CASE WHEN EXISTS (
					SELECT 1 FROM {db_prefix}messages AS m JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					WHERE m.id_member = mem.id_member AND m.approved = 1 AND ' . $scope['where'] . '
					AND m.poster_time BETWEEN mem.date_registered + 604801 AND mem.date_registered + 2592000
					AND m.poster_time <= {int:end}
				) THEN 1 ELSE 0 END) AS returned30
				FROM {db_prefix}members AS mem
				WHERE mem.date_registered BETWEEN {int:start} AND {int:cohort_end} AND mem.is_activated = {int:active}', $params);
		}
		return array('joined' => (int) $first['joined'], 'matured' => (int) $cohort['matured'], 'first7' => (int) $cohort['first7'], 'returned30' => (int) $cohort['returned30']);
	}

	public static function moderation($dates)
	{
		// SMF retains the report/posted date but not a full historical record of
		// approval and closure states. Filter by creation date and explicitly show
		// the CURRENT status of those period items, never a fictitious past backlog.
		$params = array('start' => (int) $dates['start'], 'end' => (int) $dates['end']);
		$pending = self::first('SELECT COUNT(*) AS total FROM {db_prefix}messages AS m
			JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE m.approved = {int:pending} AND {query_see_board}
			AND m.poster_time BETWEEN {int:start} AND {int:end}',
			array_merge($params, array('pending' => 0)));
		$reports = self::first('SELECT COUNT(*) AS total,
			SUM(CASE WHEN r.closed = {int:open} THEN 1 ELSE 0 END) AS still_open
			FROM {db_prefix}log_reported AS r
			JOIN {db_prefix}boards AS b ON (b.id_board = r.id_board)
			WHERE {query_see_board} AND r.time_started BETWEEN {int:start} AND {int:end}',
			array_merge($params, array('open' => 0)));
		return array('pending' => (int) $pending['total'], 'open_reports' => (int) $reports['still_open'],
			'reports_created' => (int) $reports['total'],
			'note' => 'Period-filtered items; pending/open states are current, not reconstructed historical states.');
	}

	public static function recommendations($data)
	{
		$notes = array();
		if (isset($data['overview']) && $data['overview']['posts'] > $data['overview']['previous_posts']
			&& $data['overview']['contributors'] < $data['overview']['previous_contributors'])
			$notes[] = 'ci_advice_concentration';
		if (!empty($data['discussion']['eligible']))
		{
			$rate = (int) $data['discussion']['responded'] / (int) $data['discussion']['eligible'];
			if ($rate < 0.5)
				$notes[] = 'ci_advice_unanswered';
		}
		if (!empty($data['members']['matured']) && $data['members']['returned30'] / $data['members']['matured'] < 0.2)
			$notes[] = 'ci_advice_retention';
		return $notes;
	}

	public static function data($layout, $dates)
	{
		$result = array();
		$allowed = self::allowedWidgets();
		$need = array_unique(array_merge($layout, in_array('recommendations', $layout, true) ? array('overview', 'discussion', 'members') : array()));
		foreach ($need as $widget)
		{
			if (!in_array($widget, $allowed, true))
				continue;
			if (in_array($widget, array('overview', 'smf', 'activity', 'popular', 'discussion', 'boards', 'members', 'moderation'), true))
				$result[$widget] = self::cached($widget, $dates, function () use ($widget, $dates) {
					return call_user_func(array('CommunityInsights', $widget), $dates);
				});
			elseif ($widget === 'integrations')
			{
				require_once(dirname(__FILE__) . '/CommunityInsights-Providers.php');
				// Do not reuse extension/provider results between requests. The enabled
				// mods and their permission checks can change independently of this cache.
				$result[$widget] = CommunityInsightsProviders::collect($dates);
			}
		}
		if (in_array('recommendations', $layout, true))
			$result['recommendations'] = self::recommendations($result);
		return $result;
	}
}

function CommunityInsightsMain()
{
	global $context, $user_info, $modSettings, $scripturl, $txt;

	// Load strings first so the error messages below can be shown.
	loadLanguage('CommunityInsights');

	if (!CommunityInsights::enabled())
		fatal_lang_error('ci_disabled', false);
	if (empty($user_info['id']) || !CommunityInsights::can('ci_view'))
		isAllowedTo('ci_view');

    require_once(dirname(__FILE__) . '/CommunityInsights-Advanced.php');
	if (isset($_GET['sa']) && $_GET['sa'] === 'save')
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			fatal_lang_error('ci_bad_request', false);
		CommunityInsights::saveLayout();
	}
	if (isset($_GET['sa']) && $_GET['sa'] === 'export')
	{
		CommunityInsightsExport();
		return;
	}
    require_once(dirname(__FILE__) . '/CommunityInsights-Advanced.php');
    $subaction = isset($_GET['sa']) ? (string) $_GET['sa'] : '';
    if (in_array($subaction, array('reports','report','saved','save_report','delete_report','history','automation','save_schedule','delete_schedule','alerts','save_alert','delete_alert','access'), true))
    {
        CommunityInsightsAdvancedPage($subaction);
        return;
    }

	loadTemplate('CommunityInsights');
	loadCSSFile('communityinsights.css', array('minimize' => true), 'smf_communityinsights');
    loadJavaScriptFile('communityinsights.js', array('minimize' => true), 'smf_communityinsights_layout');
	$context['page_title'] = $txt['ci_title'];
	$context['sub_template'] = 'ci_dashboard';
	$context['robot_no_index'] = true;
	$context['linktree'][] = array('name' => $txt['ci_title'], 'url' => $scripturl . '?action=communityinsights');
	$dates = CommunityInsights::dates();
	$layout = CommunityInsights::getLayout();
	$context['ci'] = array(
		'dates' => $dates,
		'layout' => $layout,
		'allowed' => CommunityInsights::allowedWidgets(),
		'data' => CommunityInsights::data($layout, $dates),
		'export' => CommunityInsights::can('ci_export'),
		'admin' => allowedTo('admin_forum'),
        'layout_meta' => CommunityInsightsAdvanced::layoutMeta(),
	);
	createToken('ci_layout');
	createToken('ci_report');
}

function CommunityInsightsExport()
{
    global $user_info;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CommunityInsights::can('ci_export') || empty($user_info['id']))
        fatal_lang_error('ci_no_export', false);
    checkSession('post');
    validateToken('ci_report');
    require_once(dirname(__FILE__) . '/CommunityInsights-Advanced.php');
    $saved = array();
    $reportId = isset($_POST['id_report']) ? (int) $_POST['id_report'] : 0;
    if ($reportId)
    {
        $saved = CommunityInsightsAdvanced::reportRecord($reportId);
        if (!$saved) fatal_lang_error('ci_bad_request', false);
        $type = $saved['report_type'];
        $options = json_decode($saved['options'], true);
        $options = is_array($options) ? $options : array();
    }
    else
    {
        $type = isset($_POST['widget']) ? (string) $_POST['widget'] : '';
        $options = $_GET;
        if (isset($_POST['boards']) && is_array($_POST['boards']))
            $options['boards'] = array_slice(array_map('intval', $_POST['boards']), 0, 5);
    }
    if (!CommunityInsightsAdvanced::allowed($type)) fatal_lang_error('ci_bad_request', false);
    $format = isset($_POST['format']) ? (string) $_POST['format'] : 'csv';
    if (!in_array($format, array('csv', 'json'), true)) fatal_lang_error('ci_bad_request', false);
    $dates = $reportId ? CommunityInsightsAdvanced::dateParams($options) : CommunityInsights::dates();
    $dates = CommunityInsights::exportDates($dates, $_POST);
    if ($dates === null) fatal_lang_error('ci_bad_request', false);
    $data = CommunityInsightsAdvanced::calculate($type, $dates, $options);
    CommunityInsightsAdvanced::recordExport($type, $format, $dates, $reportId);
    while (ob_get_level()) ob_end_clean();
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');
    $name = 'community-insights-' . $type . '-' . gmdate('Ymd') . '.' . $format;
    header('Content-Disposition: attachment; filename="' . $name . '"');
    if ($format === 'json')
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('report' => $type, 'generated_utc' => gmdate('c'),
            'range_start' => gmdate('c', $dates['start']), 'range_end' => gmdate('c', $dates['end']),
            'data' => $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0));
        exit;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    CommunityInsightsCsvRow($out, array('Community Insights', $type, gmdate('Y-m-d', $dates['start']), gmdate('Y-m-d', $dates['end'])));
    CommunityInsightsFlattenCsv($out, $data);
    fclose($out);
    exit;
}

/** Scalar CSV flattening keeps nested advanced reports exportable without executable formulas. */
function CommunityInsightsFlattenCsv($out, $data, $prefix = '', $depth = 0, &$count = 0)
{
    if ($depth > 6 || $count >= 2500) return;
    if (!is_array($data))
    {
        CommunityInsightsCsvRow($out, array($prefix, is_scalar($data) ? $data : ''));
        $count++;
        return;
    }
    foreach ($data as $key => $value)
    {
        if ($count >= 2500) break;
        $name = $prefix === '' ? (string) $key : $prefix . '.' . (string) $key;
        CommunityInsightsFlattenCsv($out, $value, $name, $depth + 1, $count);
    }
}

/**
 * Write one CSV row: decode SMF's stored HTML entities, guard against formula injection,
 * and pass the escape argument explicitly (PHP 8.4 deprecates relying on its default).
 */
function CommunityInsightsCsvRow($handle, array $cells)
{
	fputcsv($handle, array_map('CommunityInsightsCsvCell', $cells), ',', '"', '');
}

function CommunityInsightsCsvCell($value)
{
	// SMF stores subjects and board names HTML-escaped; export the readable text.
	$value = un_htmlspecialchars((string) $value);
	// Spreadsheet formula injection protection, including leading whitespace.
	if (preg_match('/^\s*[=+@\-]/u', $value))
		return "'" . $value;
	return $value;
}

function CommunityInsightsAdmin()
{
	global $context, $txt, $modSettings, $scripturl;
    require_once(dirname(__FILE__) . '/CommunityInsights-Providers.php');
	isAllowedTo('admin_forum');
	loadLanguage('CommunityInsights');
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ci_save_settings']))
	{
		checkSession('post');
		validateToken('ci_settings');
		$allowed = array_keys(CommunityInsights::widgets());
		$widgets = CommunityInsights::cleanWidgets(isset($_POST['widgets']) ? $_POST['widgets'] : array(), $allowed);
        $settings = array();
        // Disabled controls are not submitted by browsers. Only write settings
        // for integrations that were actually available and editable.
        $detectedProviders = CommunityInsightsProviders::detected();
        foreach (array('qa','clubs','ranked','wall','rules') as $provider)
            if (in_array($provider, $detectedProviders, true))
                $settings['ci_int_' . $provider] = !empty($_POST['ci_int_' . $provider]) ? 1 : 0;
		updateSettings(array_merge($settings, array(
			'ci_enabled' => !empty($_POST['enabled']) ? 1 : 0,
			'ci_integrations' => !empty($_POST['integrations']) ? 1 : 0,
			'ci_recommendations' => !empty($_POST['recommendations']) ? 1 : 0,
			'ci_response_hours' => max(1, min(336, (int) (isset($_POST['response_hours']) ? $_POST['response_hours'] : 48))),
            'ci_score_replies' => max(0, min(100, (int) (isset($_POST['score_replies']) ? $_POST['score_replies'] : 2))),
            'ci_score_contributors' => max(0, min(100, (int) (isset($_POST['score_contributors']) ? $_POST['score_contributors'] : 4))),
            'ci_score_views' => max(0, min(100, (int) (isset($_POST['score_views']) ? $_POST['score_views'] : 3))),
			'ci_cache_minutes' => max(0, min(1440, (int) (isset($_POST['cache_minutes']) ? $_POST['cache_minutes'] : 5))),
			'ci_default_widgets' => json_encode($widgets),
		)));
		redirectexit('action=admin;area=communityinsights;sa=settings');
	}
	loadTemplate('CommunityInsights');
	loadCSSFile('communityinsights.css', array('minimize' => true), 'smf_communityinsights');
	$context['page_title'] = $txt['ci_settings'];
	$context['sub_template'] = 'ci_settings';
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['ci_title'],
		'description' => $txt['ci_intro'],
	);
	$context['ci_settings'] = array(
		'enabled' => !empty($modSettings['ci_enabled']),
		'integrations' => !empty($modSettings['ci_integrations']),
		'recommendations' => !empty($modSettings['ci_recommendations']),
		'hours' => max(1, (int) (isset($modSettings['ci_response_hours']) ? $modSettings['ci_response_hours'] : 48)),
        'score_replies' => isset($modSettings['ci_score_replies']) ? (int) $modSettings['ci_score_replies'] : 2,
        'score_contributors' => isset($modSettings['ci_score_contributors']) ? (int) $modSettings['ci_score_contributors'] : 4,
        'score_views' => isset($modSettings['ci_score_views']) ? (int) $modSettings['ci_score_views'] : 3,
		'cache_minutes' => isset($modSettings['ci_cache_minutes']) ? max(0, (int) $modSettings['ci_cache_minutes']) : 5,
		'widgets' => json_decode(isset($modSettings['ci_default_widgets']) ? $modSettings['ci_default_widgets'] : '[]', true),
        'providers' => CommunityInsightsProviders::detected(),
        'provider_settings' => $modSettings,
		'all' => array_values(array_filter(array_keys(CommunityInsights::widgets()), function ($widget) {
			if ($widget !== 'integrations')
				return true;
			require_once(dirname(__FILE__) . '/CommunityInsights-Providers.php');
			return CommunityInsightsProviders::hasAvailableForUser();
		})),
	);
	createToken('ci_settings');
}
