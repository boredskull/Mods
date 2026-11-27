<?php
/**
 * Community Insights - optional SMF modification metrics.
 * A provider must not be required for the core dashboard to load.
 *
 * @package CommunityInsights
 * @author BoredSkull (https://boredskull.net)
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */
if (!defined('SMF'))
	die('No direct access...');

class CommunityInsightsProviders
{
	/**
	 * Detection and table names for each built-in adapter, in one place.
	 * 'setting' is the companion mod's enable flag, 'file' its main source file.
	 * Table names are without the forum prefix. Adjust these if a companion mod
	 * uses different names: a mismatch now hides that widget instead of erroring.
	 */
	public static $map = array(
		'qa' => array('setting' => 'qa_enabled', 'file' => 'QuestionsAnswers.php', 'table' => 'qa_topics'),
		'clubs' => array('setting' => 'clubs_enabled', 'file' => 'Clubs.php', 'table' => 'clubs'),
		'ranked' => array('setting' => 'rp_enabled', 'file' => 'RankedPolls.php', 'polls' => 'rp_polls', 'ballots' => 'rp_ballots'),
		'wall' => array('setting' => 'pw_enabled', 'file' => 'ProfileWall.php', 'table' => 'pw_comments'),
		'rules' => array('setting' => 'boardrules_enabled', 'file' => 'BoardRules.php', 'table' => 'boardrules_accept'),
	);

	private static $tableCache = null;

	/** Identify installed tables using SMF's database abstraction. */
	private static function tables()
	{
		global $smcFunc, $db_prefix;
		if (self::$tableCache === null)
		{
			db_extend('packages');
			self::$tableCache = array();
			$prefix = trim(preg_replace('~^.*\.~', '', $db_prefix), '`');
			foreach ((array) $smcFunc['db_list_tables'](false, $prefix . '%') as $table)
			{
				$name = trim(preg_replace('~^.*\.~', '', $table), '`');
				if (strpos($name, $prefix) === 0)
					self::$tableCache[strtolower(substr($name, strlen($prefix)))] = true;
			}
		}
		return self::$tableCache;
	}

	/** Each built-in adapter requires an enabled mod, its source and its tables. */
    public static function detected()
    {
        global $modSettings, $sourcedir;
        $detected = array();
        $tables = self::tables();
        foreach (self::$map as $key => $check)
        {
            if (empty($modSettings[$check['setting']]) || !is_file($sourcedir . '/' . $check['file'])) continue;
            $required = $key === 'ranked' ? array($check['polls'], $check['ballots']) : array($check['table']);
            if (!array_diff($required, array_keys($tables))) $detected[] = $key;
        }
        return $detected;
    }

    public static function available()
    {
        global $modSettings;
        return array_values(array_filter(self::detected(), function ($key) use ($modSettings) {
            return !isset($modSettings['ci_int_' . $key]) || !empty($modSettings['ci_int_' . $key]);
        }));
    }

	/** Hide integration choices if no provider can supply data to this account. */
	public static function hasAvailableForUser()
	{
		global $modSettings;
		$available = self::available();
		if (allowedTo('admin_forum') && !empty($available))
			return true;
		if (in_array('qa', $available, true))
			return true;
		// External mods can add providers via a registered integration hook.
		// The hook is evaluated only when the actual widget is requested.
		return !empty($modSettings['integrate_ci_providers']);
	}

	/** Only add a provider if its query actually succeeded. */
	private static function add(array &$out, $id, $row, array $items)
	{
		if (empty($row))
			return;
		$values = array();
		foreach ($items as $label => $column)
			$values[$label] = isset($row[$column]) ? (int) $row[$column] : 0;
		$out[] = array('id' => $id, 'title' => 'ci_provider_' . $id, 'items' => $values);
	}

	public static function collect($dates)
	{
		global $modSettings, $smcFunc;
		if (empty($modSettings['ci_integrations']) || !CommunityInsights::can('ci_integrations'))
			return array();
		$available = self::available();
		$out = array();
		$scope = CommunityInsights::scope($dates);
		$m = self::$map;

		// All adapter queries skip DB errors: a schema mismatch hides the widget, never the dashboard.
		if (in_array('qa', $available, true))
		{
			// Questions asked within the reporting period, in boards this user can see.
			$row = CommunityInsights::first('SELECT COUNT(*) AS questions,
				COALESCE(SUM(CASE WHEN q.solved = 1 THEN 1 ELSE 0 END), 0) AS solved
				FROM {db_prefix}' . $m['qa']['table'] . ' AS q
				JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
				JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				JOIN {db_prefix}messages AS fm ON (fm.id_msg = t.id_first_msg)
				WHERE ' . $scope['where'] . ' AND fm.poster_time BETWEEN {int:start} AND {int:end}', $scope['params'], true);
			self::add($out, 'qa', $row, array('ci_metric_questions' => 'questions', 'ci_metric_solved' => 'solved'));
		}
		// These providers may count activity in private clubs, polls or profile walls.
		// They are deliberately administrator-only, irrespective of analyst permissions.
		if (allowedTo('admin_forum'))
		{
			if (in_array('clubs', $available, true))
			{
				$row = CommunityInsights::first('SELECT COUNT(*) AS clubs, COALESCE(SUM(num_members), 0) AS members FROM {db_prefix}' . $m['clubs']['table'] . ' WHERE status = {string:active}', array('active' => 'active'), true);
				self::add($out, 'clubs', $row, array('ci_metric_clubs' => 'clubs', 'ci_metric_memberships' => 'members'));
			}
			if (in_array('ranked', $available, true))
			{
				$row = CommunityInsights::first('SELECT COUNT(DISTINCT id_poll) AS polls FROM {db_prefix}' . $m['ranked']['polls'], array(), true);
				$ballots = CommunityInsights::first('SELECT COUNT(*) AS ballots FROM {db_prefix}' . $m['ranked']['ballots'], array(), true);
				if (!empty($row) && !empty($ballots))
					self::add($out, 'ranked', array_merge($row, $ballots), array('ci_metric_polls' => 'polls', 'ci_metric_ballots' => 'ballots'));
			}
			if (in_array('wall', $available, true))
			{
				$row = CommunityInsights::first('SELECT COUNT(*) AS comments FROM {db_prefix}' . $m['wall']['table'] . ' WHERE approved = {int:approved}', array('approved' => 1), true);
				self::add($out, 'wall', $row, array('ci_metric_comments' => 'comments'));
			}
			if (in_array('rules', $available, true))
			{
				$row = CommunityInsights::first('SELECT COUNT(*) AS accepted FROM {db_prefix}' . $m['rules']['table'], array(), true);
				self::add($out, 'rules', $row, array('ci_metric_accepted' => 'accepted'));
			}
		}

		// Documented extension point: third-party mods can provide aggregate metrics.
		// Entries: id, title (plain text), items (label => integer), permission (optional).
		// Providers are independently vetted before their results are included.
		$extensions = array();
		call_integration_hook('integrate_ci_providers', array(&$extensions, $dates));
		foreach ($extensions as $provider)
		{
			if (!is_array($provider) || empty($provider['id']) || empty($provider['title']) || !isset($provider['items']) || !is_array($provider['items']))
				continue;
			if (!empty($provider['permission']) && !CommunityInsights::can($provider['permission']))
				continue;
			if (!preg_match('/^[a-z0-9_]{1,40}$/D', (string) $provider['id']))
				continue;
			$items = array();
			foreach (array_slice($provider['items'], 0, 8, true) as $label => $value)
				if (is_scalar($value) && is_numeric($value))
					// Multibyte-safe truncation, so a cut never leaves a broken character.
					$items[$smcFunc['substr'](strip_tags((string) $label), 0, 80)] = (float) $value;
			if ($items)
				$out[] = array('id' => (string) $provider['id'], 'title_text' => $smcFunc['substr'](strip_tags((string) $provider['title']), 0, 100), 'items' => $items);
		}
		return $out;
	}
}
