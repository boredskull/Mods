<?php

/**
 * Ranked Polls for SMF 2.1
 *
 * Lets a normal SMF poll be a ranked-choice poll. Members rank the
 * options, and the winner is found by instant-runoff counting, with the
 * results shown round by round. The poll keeps everything else SMF gives
 * it (question, options, expiry, locking, results visibility, permissions).
 *
 * @package RankedPolls
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

class RankedPolls
{
	const VERSION = '1.1.1';

	protected static $vote_action = null;
	protected static $edit_poll_action = null;
	protected static $ranked_cache = array();

	/* ------------------------------------------------------------------
	 * Settings helpers
	 * ---------------------------------------------------------------- */

	public static function enabledBoards()
	{
		global $modSettings;

		$boards = !empty($modSettings['rp_boards']) ? json_decode($modSettings['rp_boards'], true) : array();

		return is_array($boards) ? array_map('intval', $boards) : array();
	}

	public static function boardEnabled($id_board)
	{
		global $modSettings;

		return !empty($modSettings['rp_enabled']) && in_array((int) $id_board, self::enabledBoards(), true);
	}

	public static function anonymousMode()
	{
		global $modSettings;

		return isset($modSettings['rp_anonymous']) && in_array($modSettings['rp_anonymous'], array('never', 'optional', 'always'), true) ? $modSettings['rp_anonymous'] : 'optional';
	}

	public static function minRanks()
	{
		global $modSettings;

		return empty($modSettings['rp_min_ranks']) ? 1 : max(1, min(50, (int) $modSettings['rp_min_ranks']));
	}

	/**
	 * @return array|null The ranked-poll row, or null for a normal poll.
	 */
	public static function getRanked($id_poll)
	{
		global $smcFunc;

		$id_poll = (int) $id_poll;
		if ($id_poll <= 0)
			return null;
		if (array_key_exists($id_poll, self::$ranked_cache))
			return self::$ranked_cache[$id_poll];

		$request = $smcFunc['db_query']('', '
			SELECT id_poll, anonymous, notified, created
			FROM {db_prefix}rp_polls
			WHERE id_poll = {int:id}',
			array('id' => $id_poll)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return self::$ranked_cache[$id_poll] = empty($row) ? null : array(
			'id_poll' => (int) $row['id_poll'],
			'anonymous' => !empty($row['anonymous']),
			'notified' => !empty($row['notified']),
			'created' => (int) $row['created'],
		);
	}

	public static function topicPoll($id_topic)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT id_poll
			FROM {db_prefix}topics
			WHERE id_topic = {int:topic}',
			array('topic' => (int) $id_topic)
		);
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return empty($row) ? 0 : (int) $row[0];
	}

	public static function ballotCount($id_poll)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}rp_ballots
			WHERE id_poll = {int:id}',
			array('id' => (int) $id_poll)
		);
		list ($count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $count;
	}

	/** Includes logged-in and guest votes on a standard SMF poll. */
	public static function normalPollHasVotes($id_poll)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT p.num_guest_voters, COUNT(lp.id_choice) AS member_votes
			FROM {db_prefix}polls AS p
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_poll = p.id_poll)
			WHERE p.id_poll = {int:id}
			GROUP BY p.id_poll, p.num_guest_voters',
			array('id' => (int) $id_poll)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return $row && ((int) $row['num_guest_voters'] > 0 || (int) $row['member_votes'] > 0);
	}

	public static function makeRanked($id_poll, $anonymous)
	{
		global $smcFunc;

		$smcFunc['db_insert']('replace',
			'{db_prefix}rp_polls',
			array('id_poll' => 'int', 'anonymous' => 'int', 'notified' => 'int', 'created' => 'int'),
			array((int) $id_poll, $anonymous ? 1 : 0, 0, time()),
			array('id_poll')
		);
		unset(self::$ranked_cache[(int) $id_poll]);
	}

	public static function removeRanked($id_poll)
	{
		global $smcFunc;

		foreach (array('rp_polls', 'rp_ballots', 'rp_voters') as $table)
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}' . $table . '
				WHERE id_poll = {int:id}',
				array('id' => (int) $id_poll)
			);
		unset(self::$ranked_cache[(int) $id_poll]);
	}

	/**
	 * The anonymity a new poll gets, from the setting and the form.
	 */
	protected static function wantedAnonymous()
	{
		$mode = self::anonymousMode();

		return $mode === 'always' || ($mode === 'optional' && !empty($_POST['rp_anonymous']));
	}

	/* ------------------------------------------------------------------
	 * Creating and editing polls
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_after_create_post: a new topic with a poll.
	 */
	public static function afterCreatePost($msgOptions, $topicOptions = array(), $posterOptions = array())
	{
		global $board;

		if (empty($_POST['rp_ranked']) || empty($topicOptions['poll']))
			return;

		$id_board = !empty($topicOptions['board']) ? (int) $topicOptions['board'] : (int) $board;
		if (!self::boardEnabled($id_board) || !allowedTo('rankedpoll_create'))
			return;

		self::makeRanked((int) $topicOptions['poll'], self::wantedAnonymous());
	}

	/**
	 * integrate_redirect: runs after SMF has finished with a poll
	 * (adding one to a topic, editing, resetting votes, removing).
	 */
	public static function redirect(&$setLocation, &$refresh = false, &$permanent = false)
	{
		global $topic, $board, $smcFunc;

		$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

		if ($action === 'removepoll')
		{
			self::pruneOrphans();
			return;
		}

		if ($action !== 'editpoll2' || empty($topic))
			return;

		$id_poll = self::topicPoll($topic);
		if ($id_poll <= 0)
			return;

		$ranked = self::getRanked($id_poll);

		// Remember the pre-reset type: a locked, disabled checkbox is absent
		// from POST, and must never silently convert a ranked poll to normal.
		$had_ballots = $ranked && self::ballotCount($id_poll) > 0;
		if ($ranked && isset($_POST['resetVoteCount']))
			self::clearBallots($id_poll);

		if (empty($_POST['rp_present']))
			return;

		$want = !empty($_POST['rp_ranked']) || $had_ballots;

		if ($want && !$ranked)
		{
			if (!self::boardEnabled($board) || !allowedTo('rankedpoll_create'))
				return;

			// Normal polls cannot become ranked until their votes, including
			// guest votes, have been cleared by SMF's explicit Reset votes.
			if (!self::normalPollHasVotes($id_poll))
				self::makeRanked($id_poll, self::wantedAnonymous());
		}
		elseif (!$want && $ranked && !$had_ballots && self::ballotCount($id_poll) === 0)
			self::removeRanked($id_poll);
		elseif ($want && $ranked && !$had_ballots && self::ballotCount($id_poll) === 0 && self::anonymousMode() === 'optional')
			self::makeRanked($id_poll, !empty($_POST['rp_anonymous']));
	}

	public static function clearBallots($id_poll)
	{
		global $smcFunc;

		foreach (array('rp_ballots', 'rp_voters') as $table)
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}' . $table . '
				WHERE id_poll = {int:id}',
				array('id' => (int) $id_poll)
			);

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}rp_polls
			SET notified = {int:no}
			WHERE id_poll = {int:id}',
			array('no' => 0, 'id' => (int) $id_poll)
		);

		self::updateFirstPreferences($id_poll);
	}

	/**
	 * Remove ranked-poll data for polls that no longer exist.
	 */
	public static function pruneOrphans()
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT rp.id_poll
			FROM {db_prefix}rp_polls AS rp
				LEFT JOIN {db_prefix}polls AS p ON (p.id_poll = rp.id_poll)
			WHERE p.id_poll IS NULL',
			array()
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		foreach ($ids as $id)
			self::removeRanked($id);

		return count($ids);
	}

	/**
	 * integrate_load_theme: styles, plus the "Ranked-choice" option on
	 * the post and edit-poll forms.
	 */
	public static function loadTheme()
	{
		global $modSettings, $board, $topic, $txt, $context, $smcFunc;

		if (empty($modSettings['rp_enabled']))
			return;

		loadCSSFile('rankedpolls.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_rankedpolls');

		$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
		if (!in_array($action, array('post', 'editpoll'), true) || empty($board))
			return;

		// Polls can only be added to new topics from the post form.
		if ($action === 'post' && !empty($topic))
			return;

		$state = array('checked' => false, 'locked' => false, 'anonymous' => false, 'note' => '');

		if ($action === 'editpoll' && !empty($topic))
		{
			$id_poll = self::topicPoll($topic);
			$ranked = $id_poll ? self::getRanked($id_poll) : null;

			if ($ranked)
			{
				$state['checked'] = true;
				$state['anonymous'] = $ranked['anonymous'];
				if (self::ballotCount($id_poll) > 0)
				{
					$state['locked'] = true;
					$state['note'] = 'has_ballots';
				}
			}
			elseif ($id_poll)
			{
				if (self::normalPollHasVotes($id_poll))
				{
					$state['locked'] = true;
					$state['note'] = 'has_votes';
				}
			}
		}

		// Show the option only where it can be used (or to explain a ranked poll).
		if (!$state['checked'] && (!self::boardEnabled($board) || !allowedTo('rankedpoll_create')))
			return;

		loadLanguage('RankedPolls');
		loadJavaScriptFile('rankedpolls.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_rankedpolls');

		addInlineJavaScript('
	var rpFormConfig = ' . json_encode(array(
			'checked' => $state['checked'],
			'locked' => $state['locked'],
			'anonymous' => $state['anonymous'],
			'anonymousMode' => self::anonymousMode(),
			'txt' => array(
				'ranked' => $txt['rp_form_ranked'],
				'ranked_desc' => $txt['rp_form_ranked_desc'],
				'anonymous' => $txt['rp_form_anonymous'],
				'anonymous_desc' => $txt['rp_form_anonymous_desc'],
				'always_anonymous' => $txt['rp_form_always_anonymous'],
				'note' => $state['note'] !== '' ? $txt['rp_form_note_' . $state['note']] : '',
			),
		), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';');
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_actions
	 */
	public static function actions(&$actionArray)
	{
		$actionArray['rankedpoll'] = array('RankedPolls.php', 'RankedPolls::action');

		// SMF's normal vote action must not count votes on ranked polls.
		if (isset($actionArray['vote']))
		{
			self::$vote_action = $actionArray['vote'];
			$actionArray['vote'] = array('RankedPolls.php', 'RankedPolls::voteGuard');
		}

		// SMF edits/deletes choices before it fires its post-save hook.
		// Check structural changes here, before the core edit is executed.
		if (isset($actionArray['editpoll2']))
		{
			self::$edit_poll_action = $actionArray['editpoll2'];
			$actionArray['editpoll2'] = array('RankedPolls.php', 'RankedPolls::editPollGuard');
		}
	}

	public static function voteGuard()
	{
		global $topic, $sourcedir;

		$id_poll = !empty($topic) ? self::topicPoll($topic) : (isset($_REQUEST['poll']) ? (int) $_REQUEST['poll'] : 0);
		if ($id_poll && self::getRanked($id_poll))
		{
			loadLanguage('RankedPolls');
			fatal_lang_error('rp_use_ranked_form', false);
		}

		$original = self::$vote_action ?: array('Poll.php', 'Vote');
		if (!empty($original[0]))
			require_once($sourcedir . '/' . $original[0]);

		call_user_func(call_helper($original[1], true));
	}

	/**
	 * Compare existing choice IDs with the nonempty choices being submitted.
	 * Existing labels may change; IDs may not be added or removed after votes.
	 */
	public static function keepsChoiceIds(array $existing, $submitted)
	{
		if (!is_array($submitted))
			return false;

		$expected = array_fill_keys(array_map('intval', $existing), true);
		$present = array();
		foreach ($submitted as $key => $label)
		{
			if (!is_scalar($label))
				return false;
			if (trim((string) $label) === '')
				continue;
			$id = self::decimalId($key);
			if ($id === null || !isset($expected[$id]) || isset($present[$id]))
				return false;
			$present[$id] = true;
		}
		return count($present) === count($expected);
	}

	/** Guard SMF's editpoll2 action without modifying core files. */
	public static function editPollGuard()
	{
		global $topic, $sourcedir;

		if (!empty($topic) && !isset($_REQUEST['add']) && empty($_POST['preview']))
		{
			$id_poll = self::topicPoll($topic);
			// A regular poll with existing member or guest votes must not be
			// converted as a side-effect of editing/deleting options in SMF.
			if ($id_poll && !self::getRanked($id_poll) && !empty($_POST['rp_ranked'])
				&& !isset($_POST['resetVoteCount']) && self::normalPollHasVotes($id_poll))
			{
				loadLanguage('RankedPolls');
				fatal_lang_error('rp_error_conversion_has_votes', false);
			}
			if ($id_poll && self::getRanked($id_poll) && self::ballotCount($id_poll) > 0
				&& !isset($_POST['resetVoteCount'])
				&& !self::keepsChoiceIds(array_keys(self::choices($id_poll)), isset($_POST['options']) ? $_POST['options'] : null))
			{
				loadLanguage('RankedPolls');
				fatal_lang_error('rp_error_choices_locked', false);
			}
		}

		$original = self::$edit_poll_action ?: array('Poll.php', 'EditPoll2');
		if (!empty($original[0]))
			require_once($sourcedir . '/' . $original[0]);
		call_user_func(call_helper($original[1], true));
	}

	public static function action()
	{
		$sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : '';

		if ($sa === 'vote')
			self::vote();
		elseif ($sa === 'export')
			self::export();
		else
			redirectexit();
	}

	/**
	 * Load a poll with its topic and board, checking that the member can see it.
	 */
	protected static function loadPollTopic($id_poll)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT p.id_poll, p.question, p.voting_locked, p.hide_results, p.expire_time, p.change_vote, p.id_member,
				t.id_topic, t.id_board, t.locked, m.subject
			FROM {db_prefix}polls AS p
				INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE p.id_poll = {int:id}
				AND {query_see_board}
			LIMIT 1',
			array('id' => (int) $id_poll)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return $row ?: null;
	}

	public static function choices($id_poll)
	{
		global $smcFunc;

		$choices = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_choice, label
			FROM {db_prefix}poll_choices
			WHERE id_poll = {int:id}
			ORDER BY id_choice',
			array('id' => (int) $id_poll)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$choices[(int) $row['id_choice']] = $row['label'];
		$smcFunc['db_free_result']($request);

		return $choices;
	}

	/**
	 * Read the ranking from the form: either ranking[] in order (the
	 * JavaScript ballot) or rank[choice] = position (the plain HTML ballot).
	 *
	 * @return array|string Ordered choice IDs, or an error key.
	 */
	/** Only accept canonical, nonnegative decimal IDs; no casting strings to zero. */
	protected static function decimalId($value)
	{
		if (is_int($value))
			return $value >= 0 ? $value : null;
		if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D', $value))
			return null;
		$number = (int) $value;
		return (string) $number === $value ? $number : null;
	}

	public static function parseRanking(array $post, array $choice_ids, $min)
	{
		$order = array();

		if (isset($post['ranking']))
		{
			if (!is_array($post['ranking']))
				return 'rp_error_bad_choice';
			foreach ($post['ranking'] as $raw_id)
			{
				$id = self::decimalId($raw_id);
				if ($id === null)
					return 'rp_error_bad_choice';
				$order[] = $id;
			}
		}
		elseif (isset($post['rank']))
		{
			if (!is_array($post['rank']))
				return 'rp_error_bad_choice';
			$positions = array();
			foreach ($post['rank'] as $raw_id => $raw_pos)
			{
				$id = self::decimalId($raw_id);
				$pos = self::decimalId($raw_pos);
				if ($id === null || $pos === null || $pos > count($choice_ids))
					return 'rp_error_bad_choice';
				if ($pos > 0)
					$positions[$id] = $pos;
			}
			if (count(array_unique($positions)) !== count($positions))
				return 'rp_error_duplicate_rank';
			asort($positions, SORT_NUMERIC);
			$order = array_keys($positions);
		}

		if (count(array_unique($order)) !== count($order))
			return 'rp_error_duplicate_rank';
		foreach ($order as $id)
			if (!in_array($id, $choice_ids, true))
				return 'rp_error_bad_choice';
		if (empty($order) || count($order) < min($min, count($choice_ids)))
			return 'rp_error_too_few';
		return $order;
	}

	protected static function vote()
	{
		global $user_info, $smcFunc, $modSettings, $txt;

		checkSession();
		loadLanguage('RankedPolls');
		is_not_guest();

		$id_poll = isset($_POST['rp_poll']) ? (int) $_POST['rp_poll'] : 0;
		$ranked = self::getRanked($id_poll);
		$poll = $ranked ? self::loadPollTopic($id_poll) : null;

		if (!$ranked || !$poll)
			fatal_lang_error('rp_error_no_poll', false);

		if (!allowedTo('poll_vote', $poll['id_board']))
			fatal_lang_error('rp_error_no_permission', false);
		if (!empty($poll['voting_locked']))
			fatal_lang_error('rp_error_locked', false);
		if (!empty($poll['expire_time']) && $poll['expire_time'] < time())
			fatal_lang_error('rp_error_expired', false);

		$choices = self::choices($id_poll);
		$order = self::parseRanking($_POST, array_keys($choices), self::minRanks());
		if (!is_array($order))
			fatal_lang_error($order, false, array(min(self::minRanks(), count($choices))));
		$serialized = implode(',', $order);
		// There are at most 256 choice IDs; the widened column handles all of them.
		if (strlen($serialized) > 2048)
			fatal_lang_error('rp_error_bad_choice', false);

		$smcFunc['db_transaction']('begin');
		// Serialise updates to the same poll, not merely the same member.
		// This also protects the mirrored first-preference totals from races.
		$request = $smcFunc['db_query']('', '
			SELECT id_poll FROM {db_prefix}polls
			WHERE id_poll = {int:poll} FOR UPDATE', array('poll' => $id_poll));
		$locked_poll = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		if (!$locked_poll)
		{
			$smcFunc['db_transaction']('rollback');
			fatal_lang_error('rp_error_no_poll', false);
		}

		$request = $smcFunc['db_query']('', '
			SELECT id_member FROM {db_prefix}rp_voters
			WHERE id_poll = {int:poll} AND id_member = {int:me}
			FOR UPDATE', array('poll' => $id_poll, 'me' => (int) $user_info['id']));
		$has_voted = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		if ($has_voted && ($ranked['anonymous'] || empty($poll['change_vote'])))
		{
			$smcFunc['db_transaction']('rollback');
			fatal_lang_error('rp_error_already_voted', false);
		}

		if (!$has_voted)
		{
			// The composite primary key is a second, DB-enforced guard.
			$smcFunc['db_insert']('ignore',
				'{db_prefix}rp_voters',
				array('id_poll' => 'int', 'id_member' => 'int', 'voted' => 'int'),
				array($id_poll, (int) $user_info['id'], time()),
				array('id_poll', 'id_member')
			);
			if ($smcFunc['db_affected_rows']() !== 1)
			{
				$smcFunc['db_transaction']('rollback');
				fatal_lang_error('rp_error_already_voted', false);
			}
		}
		else
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}rp_ballots
				WHERE id_poll = {int:poll} AND id_member = {int:me}',
				array('poll' => $id_poll, 'me' => (int) $user_info['id']));

		// Anonymous ballots have no member or timestamp in their row.
		$smcFunc['db_insert']('insert',
			'{db_prefix}rp_ballots',
			array('id_ballot' => 'string-32', 'id_poll' => 'int', 'id_member' => 'int', 'ranking' => 'string', 'voted' => 'int'),
			array(bin2hex(random_bytes(16)), $id_poll, $ranked['anonymous'] ? 0 : (int) $user_info['id'], $serialized, $ranked['anonymous'] ? 0 : time()),
			array('id_ballot')
		);

		self::updateFirstPreferences($id_poll);
		$smcFunc['db_transaction']('commit');

		redirectexit('topic=' . $poll['id_topic'] . '.0#rp_block');
	}

	/**
	 * Store first-preference counts in SMF's own poll tables, so anything
	 * that shows normal poll results (SSI, other mods) still shows something sensible.
	 */
	public static function updateFirstPreferences($id_poll)
	{
		global $smcFunc;

		$choices = self::choices($id_poll);
		$counts = array_fill_keys(array_keys($choices), 0);

		foreach (self::ballots($id_poll) as $ballot)
			foreach ($ballot as $id)
				if (isset($counts[$id]))
				{
					$counts[$id]++;
					break;
				}

		foreach ($counts as $id => $n)
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}poll_choices
				SET votes = {int:n}
				WHERE id_poll = {int:poll}
					AND id_choice = {int:choice}',
				array('n' => $n, 'poll' => (int) $id_poll, 'choice' => $id)
			);

		cache_put_data('rp_results_' . (int) $id_poll, null, 0);
	}

	/**
	 * @return array List of ballots, each an ordered list of choice IDs.
	 */
	public static function ballots($id_poll, $with_members = false)
	{
		global $smcFunc;

		$ballots = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.ranking, b.id_member, b.voted' . ($with_members ? ', COALESCE(mem.real_name, {string:empty}) AS member_name' : '') . '
			FROM {db_prefix}rp_ballots AS b' . ($with_members ? '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = b.id_member)' : '') . '
			WHERE b.id_poll = {int:id}',
			array('id' => (int) $id_poll, 'empty' => '')
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$ranking = array_map('intval', array_filter(explode(',', $row['ranking']), 'strlen'));
			$ballots[] = $with_members ? array('ranking' => $ranking, 'member' => (int) $row['id_member'] > 0 ? $row['member_name'] : '', 'voted' => (int) $row['voted']) : $ranking;
		}
		$smcFunc['db_free_result']($request);

		return $ballots;
	}

	/* ------------------------------------------------------------------
	 * Counting (instant-runoff)
	 * ---------------------------------------------------------------- */

	/**
	 * Count ballots by instant-runoff voting.
	 *
	 * Each round, every ballot counts for its highest-ranked option still in the
	 * running. An option with more than half of the continuing ballots wins.
	 * Otherwise the option with the fewest votes is eliminated and its ballots
	 * move to their next choice. Options with no votes at all are eliminated
	 * together, because removing them can't change anyone else's total.
	 *
	 * Ties (for elimination, or between the last two) are broken by, in order:
	 *   1. previous_rounds: fewer votes in the most recent earlier round where they differed
	 *   2. borda: lower Borda score (points for every position on every ballot)
	 *   3. mentions: ranked on fewer ballots
	 *   4. order: listed later in the poll
	 *
	 * @param array $choices id => label, in poll order
	 * @param array $ballots list of ordered choice-ID lists
	 */
	public static function count(array $choices, array $ballots)
	{
		$ids = array_map('intval', array_keys($choices));
		$n = count($ids);

		// Clean the ballots: known options only, each once.
		$clean = array();
		foreach ($ballots as $b)
		{
			$seen = array();
			foreach ((array) $b as $id)
			{
				$id = (int) $id;
				if (in_array($id, $ids, true) && !isset($seen[$id]))
					$seen[$id] = true;
			}
			if (!empty($seen))
				$clean[] = array_keys($seen);
		}

		$borda = array_fill_keys($ids, 0);
		$mentions = array_fill_keys($ids, 0);
		foreach ($clean as $b)
			foreach ($b as $pos => $id)
			{
				$borda[$id] += $n - $pos;
				$mentions[$id]++;
			}

		$result = array(
			'ballots' => count($clean),
			'rounds' => array(),
			'winner' => null,
			'borda' => $borda,
			'mentions' => $mentions,
			'first' => array_fill_keys($ids, 0),
		);

		if ($n === 0 || empty($clean))
			return $result;

		$active = $ids;
		$history = array();

		for ($guard = 0; $guard <= $n; $guard++)
		{
			$counts = array_fill_keys($active, 0);
			$assign = array();
			$exhausted = 0;

			foreach ($clean as $i => $b)
			{
				$top = null;
				foreach ($b as $id)
					if (isset($counts[$id]))
					{
						$top = $id;
						break;
					}

				if ($top === null)
					$exhausted++;
				else
				{
					$counts[$top]++;
					$assign[$i] = $top;
				}
			}

			if ($guard === 0)
				$result['first'] = $counts + $result['first'];

			$history[] = $counts;
			$continuing = array_sum($counts);
			$round = array(
				'counts' => $counts,
				'continuing' => $continuing,
				'exhausted' => $exhausted,
				'eliminated' => array(),
				'transfers' => array(),
				'winner' => null,
				'tie' => null,
			);

			if ($continuing === 0)
			{
				$result['rounds'][] = $round;
				break;
			}

			$max = max($counts);
			$leaders = array_keys($counts, $max, true);

			if ($max * 2 > $continuing || count($active) === 1)
			{
				$round['winner'] = $leaders[0];
				$result['winner'] = $leaders[0];
				$result['rounds'][] = $round;
				break;
			}

			if (count($active) === 2)
			{
				// Exactly 50/50 between the last two.
				list ($loser, $rule) = self::breakTie($active, $history, $borda, $mentions, $ids);
				$winner = $active[0] === $loser ? $active[1] : $active[0];
				$round['winner'] = $winner;
				$round['tie'] = $rule;
				$result['winner'] = $winner;
				$result['rounds'][] = $round;
				break;
			}

			$min = min($counts);
			$lowest = array_keys($counts, $min, true);

			if ($min === 0)
				$eliminate = $lowest;
			elseif (count($lowest) === 1)
				$eliminate = $lowest;
			else
			{
				list ($loser, $rule) = self::breakTie($lowest, $history, $borda, $mentions, $ids);
				$eliminate = array($loser);
				$round['tie'] = $rule;
			}

			$remaining = array_values(array_diff($active, $eliminate));

			// Where the eliminated options' ballots go next.
			foreach ($eliminate as $e)
				$round['transfers'][$e] = array();
			foreach ($assign as $i => $c)
			{
				if (!in_array($c, $eliminate, true))
					continue;

				$next = 'exhausted';
				foreach ($clean[$i] as $id)
					if (in_array($id, $remaining, true))
					{
						$next = $id;
						break;
					}

				$round['transfers'][$c][$next] = isset($round['transfers'][$c][$next]) ? $round['transfers'][$c][$next] + 1 : 1;
			}

			$round['eliminated'] = $eliminate;
			$result['rounds'][] = $round;
			$active = $remaining;
		}

		return $result;
	}

	/**
	 * @return array array(loser, rule)
	 */
	public static function breakTie(array $tied, array $history, array $borda, array $mentions, array $order)
	{
		$tied = array_values($tied);

		// 1. Earlier rounds, most recent first.
		for ($r = count($history) - 2; $r >= 0 && count($tied) > 1; $r--)
		{
			$values = array();
			foreach ($tied as $id)
				$values[$id] = isset($history[$r][$id]) ? $history[$r][$id] : 0;

			$min = min($values);
			$narrow = array_keys($values, $min, true);
			if (count($narrow) < count($tied))
			{
				$tied = $narrow;
				if (count($tied) === 1)
					return array($tied[0], 'previous_rounds');
			}
		}

		// 2. Borda score, 3. number of ballots ranking it.
		foreach (array('borda' => $borda, 'mentions' => $mentions) as $rule => $scores)
		{
			$values = array();
			foreach ($tied as $id)
				$values[$id] = $scores[$id];

			$min = min($values);
			$tied = array_keys($values, $min, true);
			if (count($tied) === 1)
				return array($tied[0], $rule);
		}

		// 4. The option listed last in the poll.
		$positions = array_flip($order);
		usort($tied, function ($a, $b) use ($positions)
		{
			return $positions[$b] - $positions[$a];
		});

		return array($tied[0], 'order');
	}

	public static function results($id_poll, array $choices)
	{
		$ballots = self::ballots($id_poll);
		$key = 'rp_results_' . (int) $id_poll;

		$cached = cache_get_data($key, 300);
		if (is_array($cached) && $cached['ballots_raw'] === count($ballots) && $cached['choices'] === array_keys($choices))
			return $cached['result'];

		$result = self::count($choices, $ballots);
		cache_put_data($key, array('ballots_raw' => count($ballots), 'choices' => array_keys($choices), 'result' => $result), 300);

		return $result;
	}

	/* ------------------------------------------------------------------
	 * Topic display
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_display_message_list: swap SMF's poll box for the ranked
	 * ballot and results.
	 */
	public static function displayMessageList(&$messages, &$posters)
	{
		global $context, $modSettings;

		// Disabling Ranked Polls stops creating new ranked polls, but existing
		// ranked polls must still render their original ballot and results.
		// Otherwise SMF renders a normal poll that voteGuard rightly rejects.
		if (empty($context['is_poll']) || empty($context['topicinfo']['id_poll']))
			return;

		$id_poll = (int) $context['topicinfo']['id_poll'];
		$ranked = self::getRanked($id_poll);
		if (!$ranked)
			return;

		$view = self::buildView($id_poll, $ranked);
		if ($view === null)
			return;

		loadLanguage('RankedPolls');
		loadTemplate('RankedPolls');
		// loadTheme() intentionally returns early if globally disabled.
		// Still load the stylesheet for already-existing ranked polls.
		loadCSSFile('rankedpolls.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_rankedpolls');
		loadJavaScriptFile('rankedpolls.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_rankedpolls');

		$context['is_poll'] = false;
		$context['ranked_poll'] = $view;
		$context['template_layers'][] = 'ranked_poll';
	}

	public static function buildView($id_poll, array $ranked)
	{
		global $smcFunc, $user_info, $context, $scripturl, $modSettings, $topic;

		$request = $smcFunc['db_query']('', '
			SELECT question, voting_locked, hide_results, expire_time, change_vote, id_member
			FROM {db_prefix}polls
			WHERE id_poll = {int:id}',
			array('id' => (int) $id_poll)
		);
		$poll = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (empty($poll))
			return null;

		$raw = self::choices($id_poll);
		$choices = array();
		foreach ($raw as $id => $label)
		{
			censorText($label);
			$choices[$id] = parse_bbc($label);
		}

		$question = $poll['question'];
		censorText($question);

		$my_ranking = array();
		$has_voted = false;
		if (!$user_info['is_guest'])
		{
			$request = $smcFunc['db_query']('', '
				SELECT voted
				FROM {db_prefix}rp_voters
				WHERE id_poll = {int:poll}
					AND id_member = {int:me}',
				array('poll' => (int) $id_poll, 'me' => $user_info['id'])
			);
			$has_voted = $smcFunc['db_num_rows']($request) > 0;
			$smcFunc['db_free_result']($request);

			if ($has_voted && !$ranked['anonymous'])
			{
				$request = $smcFunc['db_query']('', '
					SELECT ranking
					FROM {db_prefix}rp_ballots
					WHERE id_poll = {int:poll}
						AND id_member = {int:me}',
					array('poll' => (int) $id_poll, 'me' => $user_info['id'])
				);
				$row = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				if (!empty($row))
					$my_ranking = array_values(array_filter(array_map('intval', explode(',', $row[0])), function ($id) use ($choices)
					{
						return isset($choices[$id]);
					}));
			}
		}

		$expired = !empty($poll['expire_time']) && $poll['expire_time'] < time();
		$locked = !empty($poll['voting_locked']);
		$own = !$user_info['is_guest'] && (int) $poll['id_member'] === (int) $user_info['id'];
		$moderator = allowedTo('moderate_board') || allowedTo('admin_forum');

		$can_vote = !$user_info['is_guest'] && allowedTo('poll_vote') && !$locked && !$expired
			&& (!$has_voted || (!empty($poll['change_vote']) && !$ranked['anonymous']));

		$hide = (int) $poll['hide_results'];
		$can_see_results = self::resultsVisibleForMember($hide, $expired, $has_voted, $moderator);

		$ballot_count = self::ballotCount($id_poll);
		$result = $can_see_results ? self::results($id_poll, $raw) : null;

		$base = $scripturl . '?topic=' . $topic . '.0';
		$session = ';' . $context['session_var'] . '=' . $context['session_id'];

		$buttons = array();
		if (allowedTo('poll_edit_any') || ($own && allowedTo('poll_edit_own')))
			$buttons['edit'] = $scripturl . '?action=editpoll;topic=' . $topic . '.0';
		if (allowedTo('poll_lock_any') || ($own && allowedTo('poll_lock_own')))
			$buttons[$locked ? 'unlock' : 'lock'] = $scripturl . '?action=lockvoting;topic=' . $topic . '.0' . $session;
		if (allowedTo('poll_remove_any') || ($own && allowedTo('poll_remove_own')))
			$buttons['remove'] = $scripturl . '?action=removepoll;topic=' . $topic . '.0' . $session;
		if (allowedTo('admin_forum') || ($own && !empty($modSettings['rp_owner_export'])))
		{
			$buttons['export_rounds'] = $scripturl . '?action=rankedpoll;sa=export;type=rounds;poll=' . (int) $id_poll . $session;
			$buttons['export_ballots'] = $scripturl . '?action=rankedpoll;sa=export;type=ballots;poll=' . (int) $id_poll . $session;
		}

		return array(
			'id' => (int) $id_poll,
			'question' => parse_bbc($question),
			'choices' => $choices,
			'anonymous' => $ranked['anonymous'],
			'has_voted' => $has_voted,
			'my_ranking' => $my_ranking,
			'changing' => $has_voted && $can_vote,
			'can_vote' => $can_vote,
			'can_see_results' => $can_see_results,
			'hide_results' => $hide,
			'is_expired' => $expired,
			'is_locked' => $locked,
			'expire_time' => empty($poll['expire_time']) ? '' : timeformat($poll['expire_time']),
			'ballots' => $ballot_count,
			'result' => $result,
			'min_ranks' => min(self::minRanks(), count($choices)),
			'is_guest' => $user_info['is_guest'],
			'buttons' => $buttons,
			'vote_url' => $scripturl . '?action=rankedpoll;sa=vote',
			'base' => $base,
		);
	}

	/**
	 * Same result visibility decision as the topic view for a member.
	 * Used by exports so hiding results cannot be bypassed via a URL.
	 */
	public static function resultsVisibleForMember($hide, $expired, $has_voted, $moderator = false)
	{
		return $moderator || (int) $hide === 0 || ((int) $hide === 1 && $has_voted) || $expired;
	}

	protected static function canMemberSeeResults($id_poll, array $poll, $id_member)
	{
		global $smcFunc;

		$expired = !empty($poll['expire_time']) && (int) $poll['expire_time'] < time();
		$hide = (int) $poll['hide_results'];
		if ($hide === 0 || $expired)
			return true;
		if ($hide !== 1 || $id_member <= 0)
			return false;

		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}rp_voters
			WHERE id_poll = {int:poll} AND id_member = {int:member}
			LIMIT 1',
			array('poll' => (int) $id_poll, 'member' => (int) $id_member)
		);
		$voted = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		return self::resultsVisibleForMember($hide, $expired, $voted);
	}

	/* ------------------------------------------------------------------
	 * Export
	 * ---------------------------------------------------------------- */

	/**
	 * Stop spreadsheet apps from running a cell as a formula.
	 */
	public static function csvCell($value)
	{
		$value = (string) $value;
		if ($value !== '' && strpos('=+-@' . "\t\r", $value[0]) !== false)
			$value = "'" . $value;

		return '"' . str_replace('"', '""', $value) . '"';
	}

	public static function plainLabel($label)
	{
		$label = preg_replace('~\[/?[a-z*][^\]]*\]~i', '', (string) $label);
		$label = function_exists('un_htmlspecialchars') ? un_htmlspecialchars($label) : htmlspecialchars_decode($label, ENT_QUOTES);

		return trim(strip_tags(str_replace(array('<br>', '<br />'), ' ', $label)));
	}

	public static function buildCsv($type, array $choices, array $result, array $ballots, $anonymous)
	{
		global $txt;

		$labels = array();
		foreach ($choices as $id => $label)
			$labels[$id] = self::plainLabel($label);

		$lines = array();

		if ($type === 'ballots')
		{
			$header = array($txt['rp_csv_ballot']);
			if (!$anonymous)
			{
				$header[] = $txt['rp_csv_member'];
				$header[] = $txt['rp_csv_time'];
			}
			for ($i = 1; $i <= count($choices); $i++)
				$header[] = sprintf($txt['rp_csv_rank'], $i);
			$lines[] = $header;

			// Anonymous ballots are shuffled so their order says nothing.
			if ($anonymous)
				shuffle($ballots);

			foreach ($ballots as $n => $b)
			{
				$ranking = $anonymous ? (isset($b['ranking']) ? $b['ranking'] : $b) : $b['ranking'];
				$row = array($n + 1);
				if (!$anonymous)
				{
					$row[] = self::plainLabel($b['member']);
					$row[] = $b['voted'] ? gmdate('Y-m-d H:i:s', $b['voted']) . ' UTC' : '';
				}
				foreach ($ranking as $id)
					if (isset($labels[$id]))
						$row[] = $labels[$id];
				$lines[] = $row;
			}
		}
		else
		{
			$lines[] = array($txt['rp_csv_round'], $txt['rp_csv_option'], $txt['rp_csv_votes'], $txt['rp_csv_percent'], $txt['rp_csv_status']);

			foreach ($result['rounds'] as $r => $round)
			{
				foreach ($round['counts'] as $id => $votes)
				{
					$status = $round['winner'] === $id ? $txt['rp_status_winner'] : (in_array($id, $round['eliminated'], true) ? $txt['rp_status_eliminated'] : '');
					$lines[] = array($r + 1, $labels[$id], $votes, $round['continuing'] > 0 ? round($votes / $round['continuing'] * 100, 2) : 0, $status);
				}
				$lines[] = array($r + 1, $txt['rp_exhausted'], $round['exhausted'], '', '');
				if ($round['tie'] !== null)
					$lines[] = array($r + 1, $txt['rp_tie_' . $round['tie']], '', '', $txt['rp_csv_tie_break']);
			}
		}

		$out = '';
		foreach ($lines as $line)
			$out .= implode(',', array_map(array(__CLASS__, 'csvCell'), $line)) . "\r\n";

		return "\xEF\xBB\xBF" . $out;
	}

	protected static function export()
	{
		global $modSettings, $user_info;

		checkSession('get');
		loadLanguage('RankedPolls');

		$id_poll = isset($_GET['poll']) ? (int) $_GET['poll'] : 0;
		$ranked = self::getRanked($id_poll);
		$poll = $ranked ? self::loadPollTopic($id_poll) : null;
		if (!$ranked || !$poll)
			fatal_lang_error('rp_error_no_poll', false);

		$admin = allowedTo('admin_forum');
		$own = !$user_info['is_guest'] && (int) $poll['id_member'] === (int) $user_info['id'];
		if (!$admin && !($own && !empty($modSettings['rp_owner_export'])))
			fatal_lang_error('rp_error_no_permission', false);

		// Owner exports must honour exactly the same result-hiding rules as
		// the ranked poll's public results. Otherwise the owner can export
		// all ballots before the poll is scheduled to reveal them.
		if (!$admin && !self::canMemberSeeResults($id_poll, $poll, (int) $user_info['id']))
			fatal_lang_error('rp_error_results_hidden', false);

		$type = isset($_GET['type']) && $_GET['type'] === 'ballots' ? 'ballots' : 'rounds';
		$choices = self::choices($id_poll);
		$ballots = self::ballots($id_poll, !$ranked['anonymous']);
		$result = self::count($choices, $ranked['anonymous'] ? $ballots : array_column($ballots, 'ranking'));

		$csv = self::buildCsv($type, $choices, $result, $ballots, $ranked['anonymous']);

		while (ob_get_level() > 0)
			@ob_end_clean();

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="ranked-poll-' . $id_poll . '-' . $type . '.csv"');
		header('X-Content-Type-Options: nosniff');
		echo $csv;
		die;
	}

	/* ------------------------------------------------------------------
	 * Alerts and scheduled task
	 * ---------------------------------------------------------------- */

	/**
	 * Scheduled task (hourly): tell voters when a ranked poll they voted
	 * in has closed, and clean up data for deleted polls.
	 */
	public static function scheduledTask()
	{
		global $modSettings, $smcFunc, $scripturl, $sourcedir;

		// Disabled creation must not stop alerts or orphan cleanup for polls
		// that were already created while the feature was enabled.
		self::pruneOrphans();

		$polls = array();
		$request = $smcFunc['db_query']('', '
			SELECT rp.id_poll, t.id_topic, m.subject
			FROM {db_prefix}rp_polls AS rp
				INNER JOIN {db_prefix}polls AS p ON (p.id_poll = rp.id_poll)
				INNER JOIN {db_prefix}topics AS t ON (t.id_poll = rp.id_poll)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE rp.notified = {int:no}
				AND t.approved = {int:approved}
				AND m.approved = {int:approved}
				AND p.expire_time > {int:zero}
				AND p.expire_time <= {int:now}
			LIMIT 20',
			array('no' => 0, 'approved' => 1, 'zero' => 0, 'now' => time())
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$polls[] = $row;
		$smcFunc['db_free_result']($request);

		foreach ($polls as $p)
		{
			$id_poll = (int) $p['id_poll'];
			$smcFunc['db_transaction']('begin');
			// Lock and recheck: a second task worker must not enqueue duplicates.
			$request = $smcFunc['db_query']('', '
				SELECT notified FROM {db_prefix}rp_polls
				WHERE id_poll = {int:id} FOR UPDATE', array('id' => $id_poll));
			$status = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			if (!$status || !empty($status[0]))
			{
				$smcFunc['db_transaction']('rollback');
				continue;
			}

			if (!empty($modSettings['rp_alerts']))
			{
				$members = array();
				$request = $smcFunc['db_query']('', '
					SELECT id_member FROM {db_prefix}rp_voters
					WHERE id_poll = {int:id}', array('id' => $id_poll));
				while ($row = $smcFunc['db_fetch_row']($request))
					$members[] = (int) $row[0];
				$smcFunc['db_free_result']($request);

				if (!empty($members))
				{
					$extra = json_encode(array(
						'topic' => (int) $p['id_topic'],
						'content_subject' => $p['subject'],
						'content_link' => $scripturl . '?topic=' . $p['id_topic'] . '.0#rp_block',
					));
					foreach (array_chunk($members, 200) as $chunk)
					{
						$rows = array();
						foreach ($chunk as $id)
							$rows[] = array(time(), $id, 0, '', 'topic', (int) $p['id_topic'], 'ranked_results', 0, $extra);
						$smcFunc['db_insert']('insert',
							'{db_prefix}user_alerts',
							array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
							$rows,
							array('id_alert')
						);
						updateMemberData($chunk, array('alerts' => '+'));
					}
				}
			}
			// Only mark complete after ALL alerts and counts have been stored.
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}rp_polls SET notified = {int:yes}
				WHERE id_poll = {int:id}', array('yes' => 1, 'id' => $id_poll));
			$smcFunc['db_transaction']('commit');
		}

		return true;
	}

	/**
	 * integrate_fetch_alerts: our alert text lives in our language file.
	 */
	public static function fetchAlerts(&$alerts, &$formats = array())
	{
		loadLanguage('RankedPolls');
	}

	/* ------------------------------------------------------------------
	 * Permissions, admin, credits
	 * ---------------------------------------------------------------- */

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('RankedPolls');
		$permissionList['board']['rankedpoll_create'] = array(false, 'poll');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'rankedpoll_create';
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('RankedPolls');

		$admin_areas['config']['areas']['rankedpolls'] = array(
			'label' => $txt['rp_title'],
			'file' => 'RankedPolls-Admin.php',
			'function' => 'RankedPolls_Admin::main',
			'icon' => 'poll',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'settings' => array($txt['rp_tab_settings']),
				'polls' => array($txt['rp_tab_polls']),
			),
		);
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('RankedPolls');
		$context['copyrights']['mods'][] = sprintf($txt['rp_credits'], self::VERSION);
	}
}
