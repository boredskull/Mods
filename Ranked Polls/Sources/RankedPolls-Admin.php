<?php

/**
 * Ranked Polls: admin panel.
 *
 * @package RankedPolls
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

class RankedPolls_Admin
{
	public static function main()
	{
		global $context, $txt, $scripturl;

		isAllowedTo('admin_forum');

		loadLanguage('RankedPolls');
		loadTemplate('RankedPolls');

		$sa = isset($_REQUEST['sa']) && $_REQUEST['sa'] === 'polls' ? 'polls' : 'settings';

		$context['page_title'] = $txt['rp_title'];
		$context['rp_url'] = $scripturl . '?action=admin;area=rankedpolls';
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['rp_title'],
			'description' => $txt['rp_desc'],
			'tabs' => array('settings' => array(), 'polls' => array()),
		);

		$context['rp_notice'] = isset($_SESSION['rp_notice']) ? $_SESSION['rp_notice'] : null;
		unset($_SESSION['rp_notice']);

		if ($sa === 'polls')
			self::polls();
		else
			self::settings();
	}

	public static function settings()
	{
		global $context, $smcFunc, $txt, $modSettings;

		if (isset($_POST['save']))
		{
			checkSession();

			$boards = array();
			$request = $smcFunc['db_query']('', 'SELECT id_board FROM {db_prefix}boards', array());
			$valid = array();
			while ($row = $smcFunc['db_fetch_row']($request))
				$valid[(int) $row[0]] = true;
			$smcFunc['db_free_result']($request);

			foreach (isset($_POST['rp_boards']) ? (array) $_POST['rp_boards'] : array() as $b)
				if (isset($valid[(int) $b]))
					$boards[] = (int) $b;

			updateSettings(array(
				'rp_enabled' => empty($_POST['rp_enabled']) ? 0 : 1,
				'rp_boards' => json_encode(array_values(array_unique($boards))),
				'rp_anonymous' => isset($_POST['rp_anonymous']) && in_array($_POST['rp_anonymous'], array('never', 'optional', 'always'), true) ? $_POST['rp_anonymous'] : 'optional',
				'rp_min_ranks' => max(1, min(50, isset($_POST['rp_min_ranks']) ? (int) $_POST['rp_min_ranks'] : 1)),
				'rp_alerts' => empty($_POST['rp_alerts']) ? 0 : 1,
				'rp_owner_export' => empty($_POST['rp_owner_export']) ? 0 : 1,
			));

			$_SESSION['rp_notice'] = $txt['rp_settings_saved'];
			redirectexit('action=admin;area=rankedpolls;sa=settings');
		}

		$context['rp_boards'] = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, b.child_level, c.name AS cat_name
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$context['rp_boards'][(int) $row['id_board']] = $row;
		$smcFunc['db_free_result']($request);

		$context['rp_settings'] = array(
			'enabled' => !empty($modSettings['rp_enabled']),
			'boards' => RankedPolls::enabledBoards(),
			'anonymous' => RankedPolls::anonymousMode(),
			'min_ranks' => RankedPolls::minRanks(),
			'alerts' => !empty($modSettings['rp_alerts']),
			'owner_export' => !empty($modSettings['rp_owner_export']),
		);
		$context['rp_poll_mode_off'] = empty($modSettings['pollMode']);
		$context['sub_template'] = 'rp_admin_settings';
	}

	public static function polls()
	{
		global $context, $smcFunc, $scripturl, $txt;

		if (isset($_GET['prune']))
		{
			checkSession('get');
			$_SESSION['rp_notice'] = sprintf($txt['rp_pruned'], RankedPolls::pruneOrphans());
			redirectexit('action=admin;area=rankedpolls;sa=polls');
		}

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) FROM {db_prefix}rp_polls', array());
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['rp_url'] . ';sa=polls', $start, (int) $total, 25);

		$session = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['rp_polls'] = array();

		$request = $smcFunc['db_query']('', '
			SELECT rp.id_poll, rp.anonymous, rp.created, p.question, p.voting_locked, p.expire_time,
				t.id_topic, b.name AS board_name, m.subject
			FROM {db_prefix}rp_polls AS rp
				INNER JOIN {db_prefix}polls AS p ON (p.id_poll = rp.id_poll)
				INNER JOIN {db_prefix}topics AS t ON (t.id_poll = rp.id_poll)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			ORDER BY rp.created DESC
			LIMIT {int:start}, 25',
			array('start' => $start)
		);
		$rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$rows[(int) $row['id_poll']] = $row + array('ballots' => 0);
		$smcFunc['db_free_result']($request);

		if (!empty($rows))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_poll, COUNT(*) AS n
				FROM {db_prefix}rp_ballots
				WHERE id_poll IN ({array_int:polls})
				GROUP BY id_poll',
				array('polls' => array_keys($rows))
			);
			while ($r = $smcFunc['db_fetch_assoc']($request))
				$rows[(int) $r['id_poll']]['ballots'] = (int) $r['n'];
			$smcFunc['db_free_result']($request);
		}

		foreach ($rows as $row)
		{
			$status = !empty($row['voting_locked']) ? 'locked' : (!empty($row['expire_time']) && $row['expire_time'] < time() ? 'ended' : 'open');

			$context['rp_polls'][] = array(
				'topic' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0#rp_block">' . rp_h(function_exists('un_htmlspecialchars') ? un_htmlspecialchars($row['subject']) : htmlspecialchars_decode($row['subject'], ENT_QUOTES)) . '</a>',
				'question' => rp_h(function_exists('un_htmlspecialchars') ? un_htmlspecialchars($row['question']) : htmlspecialchars_decode($row['question'], ENT_QUOTES)),
				'board' => rp_h(function_exists('un_htmlspecialchars') ? un_htmlspecialchars($row['board_name']) : htmlspecialchars_decode($row['board_name'], ENT_QUOTES)),
				'ballots' => comma_format($row['ballots']),
				'anonymous' => !empty($row['anonymous']),
				'status' => $status,
				'created' => timeformat($row['created']),
				'rounds_url' => $scripturl . '?action=rankedpoll;sa=export;type=rounds;poll=' . $row['id_poll'] . $session,
				'ballots_url' => $scripturl . '?action=rankedpoll;sa=export;type=ballots;poll=' . $row['id_poll'] . $session,
			);
		}

		$context['rp_prune_url'] = $context['rp_url'] . ';sa=polls;prune' . $session;
		$context['sub_template'] = 'rp_admin_polls';
	}
}
