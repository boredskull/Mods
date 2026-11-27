<?php

/**
 * Questions & Answers for SMF 2.1
 *
 * Turns topics into questions: replies are answers that members vote up
 * or down, answers can be sorted by score, the asker (or a moderator)
 * accepts the best answer, which marks the topic solved, and members earn
 * reputation from votes and accepted answers. Boards can allow questions
 * optionally or be question-only.
 *
 * @package QuestionsAnswers
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

if (!defined('SMF'))
	die('No direct access...');

class QA
{
	const VERSION = '1.2.1';

	const MODE_OFF = 0;
	const MODE_OPTIONAL = 1;
	const MODE_ONLY = 2;

	/** @var array|null Question row for the topic being displayed */
	public static $current = null;

	/** @var array Topic row (first message, starter, board...) for the topic being displayed */
	public static $topic_row = array();

	/** @var string|null start= as requested, before Display() changes it */
	protected static $requested_start = null;

	protected static $sort = 'date';
	protected static $page_order = array();
	protected static $scores = array();
	protected static $my_votes = array();
	protected static $reps = array();
	protected static $buffer = null;
	protected static $buffer_pos = 0;
	protected static $questions = array();

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	/**
	 * Escape text for HTML exactly once. SMF stores subjects, member names and
	 * board names already escaped ("&" as "&amp;"), so decode first; otherwise
	 * they show up as "Q&amp;A". Anything not escaped yet is escaped as normal.
	 */
	public static function escape($text)
	{
		$text = function_exists('un_htmlspecialchars') ? un_htmlspecialchars((string) $text) : htmlspecialchars_decode((string) $text, ENT_QUOTES);

		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	public static function enabled()
	{
		global $modSettings;

		return !empty($modSettings['qa_enabled']);
	}

	public static function setting($key, $default = 0)
	{
		global $modSettings;

		return isset($modSettings['qa_' . $key]) ? $modSettings['qa_' . $key] : $default;
	}

	/**
	 * @return array id_board => mode
	 */
	public static function boards()
	{
		$boards = json_decode((string) self::setting('boards', '{}'), true);
		$out = array();
		foreach (is_array($boards) ? $boards : array() as $id => $mode)
			if ((int) $mode > 0)
				$out[(int) $id] = min(2, (int) $mode);

		return $out;
	}

	public static function boardMode($id_board)
	{
		$boards = self::boards();

		return isset($boards[(int) $id_board]) ? $boards[(int) $id_board] : self::MODE_OFF;
	}

	public static function points()
	{
		return array(
			'up' => (int) self::setting('points_up', 10),
			'down' => (int) self::setting('points_down', 2),
			'accepted' => (int) self::setting('points_accepted', 15),
		);
	}

	/* ------------------------------------------------------------------
	 * Questions
	 * ---------------------------------------------------------------- */

	/**
	 * The question row for a topic, or null if it isn't a question
	 * (or its board has questions turned off). Topics in question-only
	 * boards become questions automatically.
	 */
	public static function question($id_topic, $id_board, $fresh = false)
	{
		global $smcFunc;

		$id_topic = (int) $id_topic;
		if ($id_topic <= 0 || !self::enabled())
			return null;

		$mode = self::boardMode($id_board);
		if ($mode === self::MODE_OFF)
			return null;

		if (!$fresh && array_key_exists($id_topic, self::$questions))
			return self::$questions[$id_topic];

		$request = $smcFunc['db_query']('', '
			SELECT id_topic, solved, id_answer, solved_time, solved_by, created, locked_by_qa
			FROM {db_prefix}qa_topics
			WHERE id_topic = {int:topic}',
			array('topic' => $id_topic)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row && $mode === self::MODE_ONLY)
			$row = self::makeQuestion($id_topic);

		if ($row)
			$row = array_map('intval', $row);

		return self::$questions[$id_topic] = $row ?: null;
	}

	/**
	 * Topics moved into question-only boards don't have a question row yet.
	 * Add them (checked at most every few minutes).
	 */
	public static function syncQuestionOnly()
	{
		global $smcFunc;

		$only = array_keys(array_filter(self::boards(), function ($m) { return $m === QA::MODE_ONLY; }));
		if (empty($only) || cache_get_data('qa_sync', 300) !== null)
			return;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}qa_topics AS q ON (q.id_topic = t.id_topic)
			WHERE t.id_board IN ({array_int:boards})
				AND q.id_topic IS NULL
			LIMIT 500',
			array('boards' => $only)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		if (!empty($ids))
		{
			$rows = array();
			foreach ($ids as $id)
				$rows[] = array($id, 0, 0, 0, 0, time(), 0);
			$smcFunc['db_insert']('ignore',
				'{db_prefix}qa_topics',
				array('id_topic' => 'int', 'solved' => 'int', 'id_answer' => 'int', 'solved_time' => 'int', 'solved_by' => 'int', 'created' => 'int', 'locked_by_qa' => 'int'),
				$rows,
				array('id_topic')
			);
		}

		cache_put_data('qa_sync', count($ids) === 500 ? null : time(), 300);
	}

	public static function forget($id_topic)
	{
		unset(self::$questions[(int) $id_topic]);
	}

	public static function makeQuestion($id_topic)
	{
		global $smcFunc;

		$smcFunc['db_insert']('ignore',
			'{db_prefix}qa_topics',
			array('id_topic' => 'int', 'solved' => 'int', 'id_answer' => 'int', 'solved_time' => 'int', 'solved_by' => 'int', 'created' => 'int', 'locked_by_qa' => 'int'),
			array((int) $id_topic, 0, 0, 0, 0, time(), 0),
			array('id_topic')
		);
		self::forget($id_topic);

		return array('id_topic' => (int) $id_topic, 'solved' => 0, 'id_answer' => 0, 'solved_time' => 0, 'solved_by' => 0, 'created' => time(), 'locked_by_qa' => 0);
	}

	/**
	 * Turn a question back into a normal topic. Votes are kept, so
	 * converting back restores the scores.
	 */
	public static function unmakeQuestion(array $topic)
	{
		global $smcFunc;

		$q = self::question($topic['id_topic'], $topic['id_board'], true);
		if (!$q)
			return;

		if ($q['solved'])
			self::setSolved($topic, false);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}qa_topics
			WHERE id_topic = {int:topic}',
			array('topic' => (int) $topic['id_topic'])
		);
		self::forget($topic['id_topic']);
	}

	public static function topicRow($id_topic)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_last_msg, t.id_member_started, t.num_replies, t.locked, t.approved, m.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:topic}',
			array('topic' => (int) $id_topic)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row)
			return null;

		foreach ($row as $k => $v)
			if ($k !== 'subject')
				$row[$k] = (int) $v;

		return $row;
	}

	/**
	 * May the current member accept answers / mark solved / convert?
	 */
	public static function canManage(array $topic)
	{
		global $user_info;

		if ($user_info['is_guest'])
			return false;

		$board = (int) $topic['id_board'];

		return allowedTo('qa_accept_any', $board) || allowedTo('moderate_board', $board)
			|| ((int) $topic['id_member_started'] === (int) $user_info['id'] && allowedTo('qa_accept_own', $board));
	}

	/* ------------------------------------------------------------------
	 * Solving
	 * ---------------------------------------------------------------- */

	/**
	 * Accept an answer: marks the question solved.
	 *
	 * @return true|string true or an error key
	 */
	public static function accept(array $topic, $id_msg)
	{
		global $smcFunc, $user_info;

		if (!self::question($topic['id_topic'], $topic['id_board'], true))
			return 'qa_error_not_question';

		$msg = self::message($id_msg);
		if (!$msg || $msg['id_topic'] !== (int) $topic['id_topic'] || $msg['id_msg'] === (int) $topic['id_first_msg'])
			return 'qa_error_not_answer';
		if (!$msg['approved'])
			return 'qa_error_unapproved';

		// Serialize changes to the accepted-answer record, and calculate
		// reputation using the final committed question state. This also
		// protects against simultaneous accept/unaccept requests.
		$smcFunc['db_transaction']('begin');
		try
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_answer, solved, locked_by_qa
				FROM {db_prefix}qa_topics
				WHERE id_topic = {int:topic} FOR UPDATE',
				array('topic' => (int) $topic['id_topic'])
			);
			$q = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			if (!$q)
			{
				$smcFunc['db_transaction']('rollback');
				return 'qa_error_not_question';
			}
			$q = array_map('intval', $q);

			$old_author = 0;
			if ($q['id_answer'] > 0 && ($old = self::message($q['id_answer'])))
				$old_author = $old['id_member'];

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}qa_topics
				SET solved = {int:one}, id_answer = {int:msg}, solved_time = {int:now}, solved_by = {int:me}
				WHERE id_topic = {int:topic}',
				array('one' => 1, 'msg' => $msg['id_msg'], 'now' => time(), 'me' => (int) $user_info['id'], 'topic' => (int) $topic['id_topic'])
			);
			self::forget($topic['id_topic']);
			self::lockOnSolve($topic, true, $q);

			// Refresh both authors: the old accepted answer loses its award,
			// and the new one receives it. Ordinary vote points are retained.
			self::recalcMembers(array($old_author, $msg['id_member']));
			$smcFunc['db_transaction']('commit');
		}
		catch (\Throwable $e)
		{
			$smcFunc['db_transaction']('rollback');
			self::forget($topic['id_topic']);
			throw $e;
		}

		if ($msg['id_member'] > 0 && $msg['id_member'] !== (int) $user_info['id'] && $msg['id_member'] !== $old_author)
			self::alert(array($msg['id_member']), 'accepted', $topic, $msg['id_msg']);
		if ((int) $topic['id_member_started'] !== (int) $user_info['id'] && !$q['solved'])
			self::alert(array($topic['id_member_started']), 'solved', $topic, $msg['id_msg']);

		return true;
	}

	/**
	 * Mark solved without picking an answer, or mark unsolved
	 * (which also removes the accepted answer).
	 */
	public static function setSolved(array $topic, $solved, $expected_answer = 0)
	{
		global $smcFunc, $user_info;

		if (!self::question($topic['id_topic'], $topic['id_board'], true))
			return 'qa_error_not_question';

		$smcFunc['db_transaction']('begin');
		try
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_answer, solved, locked_by_qa
				FROM {db_prefix}qa_topics
				WHERE id_topic = {int:topic} FOR UPDATE',
				array('topic' => (int) $topic['id_topic'])
			);
			$q = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);
			if (!$q)
			{
				$smcFunc['db_transaction']('rollback');
				return 'qa_error_not_question';
			}
			$q = array_map('intval', $q);

			// An unaccept link identifies one specific answer. Never treat a
			// stale or mismatched link as a successful withdrawal.
			$expected_answer = (int) $expected_answer;
			if (!$solved && $expected_answer > 0 && $q['id_answer'] !== $expected_answer)
			{
				$smcFunc['db_transaction']('rollback');
				return 'qa_error_not_accepted';
			}

			$old_author = 0;
			if (!$solved && $q['id_answer'] > 0)
			{
				$old = self::message($q['id_answer']);
				if (!$old || $old['id_topic'] !== (int) $topic['id_topic'])
				{
					$smcFunc['db_transaction']('rollback');
					return 'qa_error_not_answer';
				}
				$old_author = $old['id_member'];
			}

			if ($solved)
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}qa_topics
					SET solved = {int:one}, solved_time = {int:now}, solved_by = {int:me}
					WHERE id_topic = {int:topic}',
					array('one' => 1, 'now' => time(), 'me' => (int) $user_info['id'], 'topic' => (int) $topic['id_topic'])
				);
			else
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}qa_topics
					SET solved = {int:zero}, id_answer = {int:zero}, solved_time = {int:zero}, solved_by = {int:zero}
					WHERE id_topic = {int:topic}',
					array('zero' => 0, 'topic' => (int) $topic['id_topic'])
				);

			self::forget($topic['id_topic']);
			self::lockOnSolve($topic, $solved, $q);

			// Confirm the accepted answer was actually cleared before redirecting.
			// A failed write must not look like a successful unaccept in the UI.
			if (!$solved)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_answer, solved FROM {db_prefix}qa_topics
					WHERE id_topic = {int:topic}',
					array('topic' => (int) $topic['id_topic'])
				);
				$check = $smcFunc['db_fetch_assoc']($request);
				$smcFunc['db_free_result']($request);
				if (!$check || (int) $check['id_answer'] !== 0 || (int) $check['solved'] !== 0)
				{
					$smcFunc['db_transaction']('rollback');
					self::forget($topic['id_topic']);
					return 'qa_error_unaccept_failed';
				}
			}

			// The award must be removed in the same transaction as unaccepting
			// the answer; otherwise cached reputation can reflect an old state.
			if ($old_author)
			{
				self::recalcMembers(array($old_author));

				// Read back the persisted total and compare it with the real
				// accepted-answer count. The earlier release could redirect even
				// when the visible reputation was still stale.
				$request = $smcFunc['db_query']('', '
					SELECT COUNT(*) AS n
					FROM {db_prefix}qa_topics AS q
						INNER JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer AND m.id_topic = q.id_topic)
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
					WHERE q.solved = {int:one}
						AND m.id_member = {int:member}
						AND t.id_member_started != m.id_member',
					array('one' => 1, 'member' => $old_author)
				);
				$expected = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				$actual = self::loadReps(array($old_author));
				$author_stats = isset($actual[$old_author]) ? $actual[$old_author] : array('accepted' => 0, 'reputation' => 0, 'up' => 0, 'down' => 0);
				$points = self::points();
				$calculated = $author_stats['up'] * $points['up'] - $author_stats['down'] * $points['down'] + $author_stats['accepted'] * $points['accepted'] + self::adjustmentTotal($old_author);
				if (!$expected || (int) $expected[0] !== $author_stats['accepted'] || $calculated !== $author_stats['reputation'])
				{
					$smcFunc['db_transaction']('rollback');
					self::forget($topic['id_topic']);
					return 'qa_error_reputation_sync';
				}
			}
			$smcFunc['db_transaction']('commit');
		}
		catch (\Throwable $e)
		{
			$smcFunc['db_transaction']('rollback');
			self::forget($topic['id_topic']);
			throw $e;
		}

		if ($solved && !$q['solved'] && (int) $topic['id_member_started'] !== (int) $user_info['id'])
			self::alert(array($topic['id_member_started']), 'solved', $topic, 0);

		return true;
	}

	/**
	 * Optionally lock solved questions (and unlock them again if we locked them).
	 */
	protected static function lockOnSolve(array $topic, $solved, $q = null)
	{
		global $smcFunc;

		if ($solved && self::setting('lock_solved', 0))
		{
			$row = self::topicRow($topic['id_topic']);
			if ($row && !$row['locked'])
			{
				$smcFunc['db_query']('', 'UPDATE {db_prefix}topics SET locked = {int:one} WHERE id_topic = {int:topic}', array('one' => 1, 'topic' => (int) $topic['id_topic']));
				$smcFunc['db_query']('', 'UPDATE {db_prefix}qa_topics SET locked_by_qa = {int:one} WHERE id_topic = {int:topic}', array('one' => 1, 'topic' => (int) $topic['id_topic']));
			}
		}
		elseif (!$solved && $q && !empty($q['locked_by_qa']))
		{
			$smcFunc['db_query']('', 'UPDATE {db_prefix}topics SET locked = {int:zero} WHERE id_topic = {int:topic} AND locked = {int:one}', array('zero' => 0, 'one' => 1, 'topic' => (int) $topic['id_topic']));
			$smcFunc['db_query']('', 'UPDATE {db_prefix}qa_topics SET locked_by_qa = {int:zero} WHERE id_topic = {int:topic}', array('zero' => 0, 'topic' => (int) $topic['id_topic']));
		}
	}

	public static function message($id_msg)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT id_msg, id_topic, id_board, id_member, approved, subject
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array('msg' => (int) $id_msg)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row)
			return null;
		foreach (array('id_msg', 'id_topic', 'id_board', 'id_member', 'approved') as $k)
			$row[$k] = (int) $row[$k];

		return $row;
	}

	/* ------------------------------------------------------------------
	 * Voting
	 * ---------------------------------------------------------------- */

	/**
	 * Cast, change or (by repeating it) remove a vote.
	 *
	 * @param int $direction 1 or -1
	 * @return array array(true, score, my vote) or array(false, error key)
	 */
	public static function vote($id_msg, $direction)
	{
		global $smcFunc, $user_info;

		$direction = $direction < 0 ? -1 : 1;

		if ($user_info['is_guest'])
			return array(false, 'qa_error_guest');

		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_member, m.approved, t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, t.locked
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE m.id_msg = {int:msg}
				AND {query_see_board}',
			array('msg' => (int) $id_msg)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row)
			return array(false, 'qa_error_not_found');
		$row = array_map('intval', $row);

		if (!self::question($row['id_topic'], $row['id_board']))
			return array(false, 'qa_error_not_question');
		if ($row['id_msg'] === $row['id_first_msg'])
			return array(false, 'qa_error_vote_question');
		if (!$row['approved'])
			return array(false, 'qa_error_unapproved');
		if ($row['id_member'] === (int) $user_info['id'])
			return array(false, 'qa_error_vote_own');
		if (!allowedTo('qa_vote', $row['id_board']))
			return array(false, 'qa_error_no_vote_permission');

		// Lock the answer author first to serialize reputation updates even
		// when votes arrive simultaneously in different question topics.
		// Locking the topic then serializes votes and score recounts for that topic.
		$smcFunc['db_transaction']('begin');
		try
		{
			if ($row['id_member'] > 0)
			{
				$request = $smcFunc['db_query']('', '
					SELECT id_member FROM {db_prefix}members
					WHERE id_member = {int:member} FOR UPDATE',
					array('member' => $row['id_member']));
				$smcFunc['db_free_result']($request);
			}
			$request = $smcFunc['db_query']('', '
				SELECT id_topic FROM {db_prefix}topics
				WHERE id_topic = {int:topic} FOR UPDATE',
				array('topic' => $row['id_topic']));
			$found = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			if (!$found)
			{
				$smcFunc['db_transaction']('rollback');
				return array(false, 'qa_error_not_found');
			}

			$current = 0;
			$request = $smcFunc['db_query']('', '
				SELECT vote FROM {db_prefix}qa_votes
				WHERE id_msg = {int:msg} AND id_member = {int:me}',
				array('msg' => $row['id_msg'], 'me' => (int) $user_info['id']));
			if ($r = $smcFunc['db_fetch_row']($request))
				$current = (int) $r[0];
			$smcFunc['db_free_result']($request);

			if ($direction < 0)
			{
				if (!self::setting('downvotes', 1))
				{
					$smcFunc['db_transaction']('rollback');
					return array(false, 'qa_error_no_downvotes');
				}
				$min = (int) self::setting('downvote_min_rep', 0);
				if ($current === 0 && $min > 0 && self::reputation($user_info['id']) < $min)
				{
					$smcFunc['db_transaction']('rollback');
					return array(false, 'qa_error_downvote_rep');
				}
			}

			// Any existing vote is cancelled first. Clicking the opposite
			// arrow must go through neutral (1 -> 0 -> -1 or vice versa),
			// not jump directly between two opposite votes.
			if ($current !== 0)
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}qa_votes
					WHERE id_msg = {int:msg} AND id_member = {int:me}',
					array('msg' => $row['id_msg'], 'me' => (int) $user_info['id']));
				$mine = 0;
			}
			else
			{
				$smcFunc['db_insert']('replace',
					'{db_prefix}qa_votes',
					array('id_msg' => 'int', 'id_member' => 'int', 'vote' => 'int', 'vote_time' => 'int'),
					array($row['id_msg'], (int) $user_info['id'], $direction, time()),
					array('id_msg', 'id_member')
				);
				$mine = $direction;
			}

			$scores = self::recountScores(array($row['id_msg']));
			self::recalcMembers(array($row['id_member']));
			$smcFunc['db_transaction']('commit');
			return array(true, isset($scores[$row['id_msg']]) ? $scores[$row['id_msg']]['score'] : 0, $mine);
		}
		catch (\Throwable $e)
		{
			$smcFunc['db_transaction']('rollback');
			throw $e;
		}
	}

	/**
	 * Recount the cached scores of some messages.
	 *
	 * @return array id_msg => array(score, up, down)
	 */
	public static function recountScores(array $msgs)
	{
		global $smcFunc;

		$msgs = array_values(array_unique(array_filter(array_map('intval', $msgs))));
		$out = array();
		if (empty($msgs))
			return $out;

		$request = $smcFunc['db_query']('', '
			SELECT id_msg,
				SUM(CASE WHEN vote > 0 THEN 1 ELSE 0 END) AS up,
				SUM(CASE WHEN vote < 0 THEN 1 ELSE 0 END) AS down
			FROM {db_prefix}qa_votes
			WHERE id_msg IN ({array_int:msgs})
			GROUP BY id_msg',
			array('msgs' => $msgs)
		);
		$rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$up = (int) $row['up'];
			$down = (int) $row['down'];
			$out[(int) $row['id_msg']] = array('score' => $up - $down, 'up' => $up, 'down' => $down);
			$rows[] = array((int) $row['id_msg'], $up - $down, $up, $down);
		}
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}qa_scores
			WHERE id_msg IN ({array_int:msgs})',
			array('msgs' => $msgs)
		);
		if (!empty($rows))
			$smcFunc['db_insert']('replace',
				'{db_prefix}qa_scores',
				array('id_msg' => 'int', 'score' => 'int', 'up' => 'int', 'down' => 'int'),
				$rows,
				array('id_msg')
			);

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Reputation
	 * ---------------------------------------------------------------- */

	public static function reputation($id_member)
	{
		$reps = self::loadReps(array($id_member));

		return isset($reps[(int) $id_member]) ? $reps[(int) $id_member]['reputation'] : 0;
	}

	/**
	 * @return array id_member => array(reputation, up, down, accepted)
	 */
	public static function loadReps(array $members)
	{
		global $smcFunc;

		$members = array_values(array_unique(array_filter(array_map('intval', $members))));
		$out = array();
		if (empty($members))
			return $out;

		$request = $smcFunc['db_query']('', '
			SELECT id_member, reputation, up, down, accepted
			FROM {db_prefix}qa_members
			WHERE id_member IN ({array_int:members})',
			array('members' => $members)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$out[(int) $row['id_member']] = array_map('intval', $row);
		$smcFunc['db_free_result']($request);

		return $out;
	}

	/**
	 * Work out reputation from scratch for some members. Counting from the
	 * votes themselves means deleted posts and changed points settings
	 * never leave stale totals behind.
	 */
	public static function recalcMembers(array $members)
	{
		global $smcFunc;

		$members = array_values(array_unique(array_filter(array_map('intval', $members))));
		if (empty($members))
			return;

		$points = self::points();

		foreach (array_chunk($members, 200) as $chunk)
		{
			$stats = array();
			foreach ($chunk as $id)
				$stats[$id] = array('up' => 0, 'down' => 0, 'accepted' => 0);

			$request = $smcFunc['db_query']('', '
				SELECT m.id_member,
					SUM(CASE WHEN v.vote > 0 THEN 1 ELSE 0 END) AS up,
					SUM(CASE WHEN v.vote < 0 THEN 1 ELSE 0 END) AS down
				FROM {db_prefix}qa_votes AS v
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = v.id_msg)
				WHERE m.id_member IN ({array_int:members})
				GROUP BY m.id_member',
				array('members' => $chunk)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$stats[(int) $row['id_member']]['up'] = (int) $row['up'];
				$stats[(int) $row['id_member']]['down'] = (int) $row['down'];
			}
			$smcFunc['db_free_result']($request);

			// Accepted answers, not counting people answering their own questions.
			$request = $smcFunc['db_query']('', '
				SELECT m.id_member, COUNT(*) AS n
				FROM {db_prefix}qa_topics AS q
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer AND m.id_topic = q.id_topic)
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
				WHERE q.solved = {int:one}
					AND m.id_member IN ({array_int:members})
					AND t.id_member_started != m.id_member
				GROUP BY m.id_member',
				array('one' => 1, 'members' => $chunk)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$stats[(int) $row['id_member']]['accepted'] = (int) $row['n'];
			$smcFunc['db_free_result']($request);

			// Manual awards, deductions and reversals form an append-only ledger.
			// Never overwrite adjustments when rebuilding earned reputation.
			$request = $smcFunc['db_query']('', '
				SELECT id_member, SUM(points) AS adjustment
				FROM {db_prefix}qa_adjustments
				WHERE id_member IN ({array_int:members})
				GROUP BY id_member',
				array('members' => $chunk)
			);
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$stats[(int) $row['id_member']]['adjustment'] = (int) $row['adjustment'];
			$smcFunc['db_free_result']($request);

			$rows = array();
			foreach ($stats as $id => $s)
				$rows[] = array($id, $s['up'] * $points['up'] - $s['down'] * $points['down'] + $s['accepted'] * $points['accepted'] + (isset($s['adjustment']) ? $s['adjustment'] : 0), $s['up'], $s['down'], $s['accepted']);

			$smcFunc['db_insert']('replace',
				'{db_prefix}qa_members',
				array('id_member' => 'int', 'reputation' => 'int', 'up' => 'int', 'down' => 'int', 'accepted' => 'int'),
				$rows,
				array('id_member')
			);
		}
	}

    /** Sum every adjustment, including explicit reversal entries. */
    public static function adjustmentTotal($id_member)
    {
        global $smcFunc;

        $request = $smcFunc['db_query']('', '
            SELECT COALESCE(SUM(points), 0) AS total
            FROM {db_prefix}qa_adjustments
            WHERE id_member = {int:member}',
            array('member' => (int) $id_member)
        );
        $row = $smcFunc['db_fetch_row']($request);
        $smcFunc['db_free_result']($request);
        return $row ? (int) $row[0] : 0;
    }

    /**
     * Make an audited, persistent adjustment to a member's reputation.
     * Only callable by the administrative action (which checks permissions,
     * session and CSRF token). Transactions protect the ledger and cache.
     *
     * @return int newly created adjustment id
     */
    public static function addAdjustment($id_member, $points, $reason)
    {
        global $smcFunc, $user_info;

        $id_member = (int) $id_member;
        $points = (int) $points;
        $reason = trim((string) $reason);
        if ($id_member < 1 || $points === 0 || abs($points) > 100000 || $reason === '' || strlen($reason) > 1000)
            throw new \InvalidArgumentException('Invalid reputation adjustment.');

        $smcFunc['db_transaction']('begin');
        try
        {
            // Vote updates already lock this row, preventing a concurrent
            // vote from overwriting the administrator's cached total.
            $request = $smcFunc['db_query']('', '
                SELECT id_member FROM {db_prefix}members
                WHERE id_member = {int:member} FOR UPDATE',
                array('member' => $id_member)
            );
            $member = $smcFunc['db_fetch_row']($request);
            $smcFunc['db_free_result']($request);
            if (!$member)
                throw new \InvalidArgumentException('Member does not exist.');

            $smcFunc['db_insert']('insert', '{db_prefix}qa_adjustments',
                array('id_member' => 'int', 'id_admin' => 'int', 'admin_name' => 'string', 'points' => 'int', 'reason' => 'string', 'created' => 'int', 'reversal_of' => 'int', 'reversed_by' => 'int'),
                array($id_member, (int) $user_info['id'], (string) $user_info['name'], $points, $reason, time(), 0, 0),
                array('id_adjustment')
            );
            $id = (int) $smcFunc['db_insert_id']('{db_prefix}qa_adjustments', 'id_adjustment');
            if ($id < 1)
                throw new \RuntimeException('Unable to record the adjustment.');

            self::recalcMembers(array($id_member));
            $smcFunc['db_transaction']('commit');
            return $id;
        }
        catch (\Throwable $e)
        {
            $smcFunc['db_transaction']('rollback');
            throw $e;
        }
    }

    /**
     * Reverse a prior adjustment exactly once. The original remains in the
     * ledger for auditing; a separate opposite entry cancels its points.
     *
     * @return int member id affected
     */
    public static function reverseAdjustment($id_adjustment, $reason)
    {
        global $smcFunc, $user_info;

        $id_adjustment = (int) $id_adjustment;
        $reason = trim((string) $reason);
        if ($id_adjustment < 1 || $reason === '' || strlen($reason) > 1000)
            throw new \InvalidArgumentException('An adjustment and a reason are required.');

        $smcFunc['db_transaction']('begin');
        try
        {
            $request = $smcFunc['db_query']('', '
                SELECT id_member, points, reversal_of, reversed_by
                FROM {db_prefix}qa_adjustments
                WHERE id_adjustment = {int:id} FOR UPDATE',
                array('id' => $id_adjustment)
            );
            $original = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            if (!$original || (int) $original['reversal_of'] !== 0 || (int) $original['reversed_by'] !== 0)
                throw new \InvalidArgumentException('This adjustment cannot be reversed again.');

            $id_member = (int) $original['id_member'];
            $request = $smcFunc['db_query']('', '
                SELECT id_member FROM {db_prefix}members WHERE id_member = {int:member} FOR UPDATE',
                array('member' => $id_member)
            );
            $member = $smcFunc['db_fetch_row']($request);
            $smcFunc['db_free_result']($request);
            if (!$member)
                throw new \InvalidArgumentException('Member no longer exists.');

            $smcFunc['db_insert']('insert', '{db_prefix}qa_adjustments',
                array('id_member' => 'int', 'id_admin' => 'int', 'admin_name' => 'string', 'points' => 'int', 'reason' => 'string', 'created' => 'int', 'reversal_of' => 'int', 'reversed_by' => 'int'),
                array($id_member, (int) $user_info['id'], (string) $user_info['name'], -(int) $original['points'], $reason, time(), $id_adjustment, 0),
                array('id_adjustment')
            );
            $new_id = (int) $smcFunc['db_insert_id']('{db_prefix}qa_adjustments', 'id_adjustment');
            if ($new_id < 1)
                throw new \RuntimeException('Unable to record the reversal.');

            $smcFunc['db_query']('', '
                UPDATE {db_prefix}qa_adjustments
                SET reversed_by = {int:reverse}
                WHERE id_adjustment = {int:original} AND reversed_by = {int:zero}',
                array('reverse' => $new_id, 'original' => $id_adjustment, 'zero' => 0)
            );
            $request = $smcFunc['db_query']('', '
                SELECT reversed_by FROM {db_prefix}qa_adjustments
                WHERE id_adjustment = {int:original}',
                array('original' => $id_adjustment)
            );
            $check = $smcFunc['db_fetch_row']($request);
            $smcFunc['db_free_result']($request);
            if (!$check || (int) $check[0] !== $new_id)
                throw new \RuntimeException('Unable to verify adjustment reversal.');
            self::recalcMembers(array($id_member));
            $smcFunc['db_transaction']('commit');
            return $id_member;
        }
        catch (\Throwable $e)
        {
            $smcFunc['db_transaction']('rollback');
            throw $e;
        }
    }

	/**
	 * Recount everything (admin maintenance), in steps.
	 *
	 * @return int|false next start, or false when done
	 */
	public static function recountStep($start, $step = 500)
	{
		global $smcFunc;

		if ($start === 0)
		{
			// Remove data for messages and topics that no longer exist.
			foreach (array('qa_votes', 'qa_scores') as $table)
			{
				$ids = array();
				$request = $smcFunc['db_query']('', '
					SELECT DISTINCT x.id_msg
					FROM {db_prefix}' . $table . ' AS x
						LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = x.id_msg)
					WHERE m.id_msg IS NULL',
					array()
				);
				while ($row = $smcFunc['db_fetch_row']($request))
					$ids[] = (int) $row[0];
				$smcFunc['db_free_result']($request);
				if (!empty($ids))
					$smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_msg IN ({array_int:ids})', array('ids' => $ids));
			}

			$ids = array();
			$request = $smcFunc['db_query']('', '
				SELECT q.id_topic
				FROM {db_prefix}qa_topics AS q
					LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
				WHERE t.id_topic IS NULL',
				array()
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);
			if (!empty($ids))
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_topics WHERE id_topic IN ({array_int:ids})', array('ids' => $ids));

			// Accepted answers that were deleted or split away.
			// Also repair solved=0/locked_by_qa=1 left by older releases.
			$stale = array();
			$request = $smcFunc['db_query']('', '
				SELECT q.id_topic, q.locked_by_qa
				FROM {db_prefix}qa_topics AS q
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer AND m.id_topic = q.id_topic)
				WHERE (q.id_answer > {int:zero} AND m.id_msg IS NULL)
					OR (q.solved = {int:zero} AND q.locked_by_qa = {int:one})',
				array('zero' => 0, 'one' => 1));
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$stale[(int) $row['id_topic']] = (int) $row['locked_by_qa'];
			$smcFunc['db_free_result']($request);
			foreach (array_chunk(array_keys($stale), 500) as $chunk)
			{
				$to_unlock = array();
				foreach ($chunk as $id_topic)
				{
					if ($stale[$id_topic])
						$to_unlock[] = $id_topic;
					self::forget($id_topic);
				}
				if (!empty($to_unlock))
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}topics SET locked = {int:zero}
						WHERE id_topic IN ({array_int:ids}) AND locked = {int:one}',
						array('zero' => 0, 'one' => 1, 'ids' => $to_unlock));
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}qa_topics
					SET id_answer = {int:zero}, solved = {int:zero}, solved_time = {int:zero},
						solved_by = {int:zero}, locked_by_qa = {int:zero}
					WHERE id_topic IN ({array_int:ids})',
					array('zero' => 0, 'ids' => $chunk));
			}

			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_scores', array());
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_members', array());
		}

		$msgs = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_msg
			FROM {db_prefix}qa_votes
			ORDER BY id_msg
			LIMIT {int:start}, {int:step}',
			array('start' => $start, 'step' => $step)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$msgs[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$members = array();
		if (!empty($msgs))
		{
			self::recountScores($msgs);

			$request = $smcFunc['db_query']('', '
				SELECT DISTINCT id_member
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:msgs})',
				array('msgs' => $msgs)
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$members[] = (int) $row[0];
			$smcFunc['db_free_result']($request);
		}

		// Authors of accepted answers (whose answers may have no votes) on the last step.
		if (count($msgs) < $step)
		{
			$request = $smcFunc['db_query']('', '
				SELECT DISTINCT m.id_member
				FROM {db_prefix}qa_topics AS q
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer)
				WHERE q.id_answer > {int:zero}',
				array('zero' => 0)
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$members[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			// Members with only admin adjustments must also survive full recounts.
			$request = $smcFunc['db_query']('', '
				SELECT DISTINCT a.id_member
				FROM {db_prefix}qa_adjustments AS a
				INNER JOIN {db_prefix}members AS m ON (m.id_member = a.id_member)',
				array()
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$members[] = (int) $row[0];
			$smcFunc['db_free_result']($request);
		}

		self::recalcMembers($members);

		return count($msgs) < $step ? false : $start + $step;
	}

	/* ------------------------------------------------------------------
	 * Alerts
	 * ---------------------------------------------------------------- */

	/**
	 * Actions: accepted (your answer was accepted), solved (your question was marked solved by someone else)
	 */
	public static function alert(array $to, $action, array $topic, $id_msg)
	{
		global $smcFunc, $scripturl, $user_info;

		$to = array_values(array_unique(array_filter(array_map('intval', $to))));
		if (empty($to) || !self::setting('alerts', 1))
			return;

		$link = $scripturl . '?topic=' . $topic['id_topic'] . ($id_msg ? '.msg' . $id_msg . '#msg' . $id_msg : '.0');
		$extra = json_encode(array('topic_link' => self::escape($link), 'topic_subject' => isset($topic['subject']) ? self::escape($topic['subject']) : ''));

		$rows = array();
		foreach ($to as $id)
			$rows[] = array(time(), $id, (int) $user_info['id'], (string) $user_info['name'], 'member', (int) $user_info['id'], 'qa_' . $action, 0, $extra);

		$smcFunc['db_insert']('insert',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$rows,
			array('id_alert')
		);

		updateMemberData($to, array('alerts' => '+'));
	}

	/* ------------------------------------------------------------------
	 * Topic display
	 * ---------------------------------------------------------------- */

	public static function sortChoice()
	{
		if (isset($_GET['qa_sort']) && in_array($_GET['qa_sort'], array('votes', 'date'), true))
			$_SESSION['qa_sort'] = $_GET['qa_sort'];

		if (isset($_SESSION['qa_sort']) && in_array($_SESSION['qa_sort'], array('votes', 'date'), true))
			return $_SESSION['qa_sort'];

		return self::setting('default_sort', 'votes') === 'date' ? 'date' : 'votes';
	}

	/**
	 * integrate_load_theme
	 */
	public static function loadTheme()
	{
		global $topic, $board, $context;

		if (!self::enabled())
			return;

		loadCSSFile('questionsanswers.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_qa');

		if (!empty($_REQUEST['action']) || isset($_REQUEST['xml']) || empty($board))
			return;

		// Board index: badges on question topics.
		if (empty($topic))
		{
			if (self::boardMode($board) !== self::MODE_OFF && self::setting('index_badges', 1))
			{
				loadLanguage('QuestionsAnswers');
				loadTemplate('QuestionsAnswers');
				$context['template_layers'][] = 'qa_index';
			}
			return;
		}

		$mode = self::boardMode($board);
		if ($mode === self::MODE_OFF)
		{
			if (self::setting('rep_everywhere', 0))
			{
				loadLanguage('QuestionsAnswers');
				self::$current = false;
			}
			return;
		}

		$row = self::topicRow($topic);
		if (!$row)
			return;

		self::$topic_row = $row;
		self::$current = self::question($topic, $row['id_board']);
		if (!self::$current)
			self::$current = self::setting('rep_everywhere', 0) ? false : null;

		loadLanguage('QuestionsAnswers');
		loadTemplate('QuestionsAnswers');

		if (self::$current)
		{
			self::$requested_start = isset($_REQUEST['start']) ? (string) $_REQUEST['start'] : '0';
			self::$sort = self::sortChoice();
			// "new" links work on dates, so use date order for those.
			if (self::$requested_start === 'new' || strpos(self::$requested_start, 'from') === 0)
				self::$sort = 'date';

			loadJavaScriptFile('questionsanswers.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_qa');
			$context['template_layers'][] = 'qa_topic';
		}
		elseif ($mode === self::MODE_OPTIONAL && self::canManage($row))
			$context['template_layers'][] = 'qa_topic';
	}

	/**
	 * The ordered list of message IDs for a question in score order:
	 * question, accepted answer, then answers by score (oldest first on ties).
	 */
	public static function scoreOrder($id_topic, $first_msg, $accepted, $all_unapproved = false)
	{
		global $smcFunc, $user_info, $modSettings;

		$approval = !empty($modSettings['postmod_active']) && !$all_unapproved;

		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_member, COALESCE(s.score, 0) AS score
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}qa_scores AS s ON (s.id_msg = m.id_msg)
			WHERE m.id_topic = {int:topic}' . ($approval ? '
				AND (m.approved = {int:one}' . ($user_info['is_guest'] ? '' : ' OR m.id_member = {int:me}') . ')' : '') . '
			ORDER BY CASE WHEN m.id_msg = {int:first} THEN 0 WHEN m.id_msg = {int:accepted} THEN 1 ELSE 2 END, score DESC, m.id_msg',
			array('topic' => (int) $id_topic, 'one' => 1, 'me' => (int) $user_info['id'], 'first' => (int) $first_msg, 'accepted' => (int) $accepted)
		);
		$out = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$out[(int) $row[0]] = (int) $row[1];
		$smcFunc['db_free_result']($request);

		return $out;
	}

	/**
	 * integrate_display_message_list: switch the page's messages to score
	 * order, and load scores, votes and reputation for the page.
	 */
	public static function displayMessageList(&$messages, &$posters)
	{
		global $context, $topic, $scripturl;

		if (self::$current === null)
			return;

		$q = self::$current;

		if ($q && self::$sort === 'votes')
		{
			$pin = self::setting('pin_accepted', 1) ? $q['id_answer'] : -1;
			$order = self::scoreOrder($topic, self::$topic_row['id_first_msg'], $pin, allowedTo('approve_posts'));
			$ids = array_keys($order);
			$per_page = isset($context['messages_per_page']) ? (int) $context['messages_per_page'] : count($messages);
			$start = isset($_REQUEST['start']) && is_numeric($_REQUEST['start']) ? (int) $_REQUEST['start'] : (isset($context['start']) ? (int) $context['start'] : 0);

			// A link to a particular message: go to the page it's on in this order.
			if (preg_match('~^msg(\d+)~', (string) self::$requested_start, $m) && ($pos = array_search((int) $m[1], $ids, true)) !== false && $per_page > 0)
			{
				$start = $pos - ($pos % $per_page);
				$_REQUEST['start'] = $start;
				$context['start'] = $start;
				$context['page_index'] = constructPageIndex($scripturl . '?topic=' . $topic . '.%1$d', $start, count($ids), $per_page, true);
			}

			$slice = $per_page > 0 ? array_slice($ids, $start, $per_page) : $ids;
			if (!empty($slice))
			{
				$messages = $slice;
				$posters = array();
				foreach ($slice as $id)
					if (!empty($order[$id]))
						$posters[$order[$id]] = $order[$id];
				$posters = array_values($posters);
				self::$page_order = array_flip($slice);
			}
		}

		if ($q)
		{
			self::$scores = array();
			self::$my_votes = array();
			self::loadPageVotes($messages);
		}

		self::$reps = self::loadReps($posters);
	}

	protected static function loadPageVotes(array $messages)
	{
		global $smcFunc, $user_info;

		$messages = array_values(array_filter(array_map('intval', $messages)));
		if (empty($messages))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_msg, score, up, down
			FROM {db_prefix}qa_scores
			WHERE id_msg IN ({array_int:msgs})',
			array('msgs' => $messages)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			self::$scores[(int) $row['id_msg']] = array_map('intval', $row);
		$smcFunc['db_free_result']($request);

		if ($user_info['is_guest'])
			return;

		$request = $smcFunc['db_query']('', '
			SELECT id_msg, vote
			FROM {db_prefix}qa_votes
			WHERE id_msg IN ({array_int:msgs})
				AND id_member = {int:me}',
			array('msgs' => $messages, 'me' => (int) $user_info['id'])
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			self::$my_votes[(int) $row[0]] = (int) $row[1];
		$smcFunc['db_free_result']($request);
	}

	/**
	 * integrate_prepare_display_context: vote box, accepted highlight,
	 * solution box under the question, and reputation.
	 */
	public static function prepareDisplayContext(&$output, &$message, $counter)
	{
		global $txt, $scripturl, $context, $user_info;

		if (self::$current === null)
			return;

		$id_member = isset($output['member']['id']) ? (int) $output['member']['id'] : 0;

		if ($id_member > 0)
		{
			$rep = isset(self::$reps[$id_member]) ? self::$reps[$id_member]['reputation'] : 0;
			if (!isset($output['custom_fields']) || !is_array($output['custom_fields']))
				$output['custom_fields'] = array();
			$output['custom_fields']['standard']['qa_rep'] = array(
				'title' => $txt['qa_reputation'],
				'col_name' => 'qa_rep',
				'value' => '<a href="' . $scripturl . '?action=profile;area=qa;u=' . $id_member . '" class="qa_rep_value">' . comma_format($rep) . '</a>',
				'placement' => 0,
			);
		}

		$q = self::$current;
		if (!$q)
			return;

		$id_msg = (int) $output['id'];
		$is_question = $id_msg === self::$topic_row['id_first_msg'];
		$can_manage = self::canManage(self::$topic_row);

		if ($is_question)
		{
			$output['css_class'] = (isset($output['css_class']) ? $output['css_class'] : '') . ' qa_question_post';
			if ($q['id_answer'] > 0)
				$output['body'] .= self::solutionBox($q['id_answer']);
			return;
		}

		$is_accepted = $q['id_answer'] === $id_msg;
		$score = isset(self::$scores[$id_msg]) ? self::$scores[$id_msg]['score'] : 0;
		$mine = isset(self::$my_votes[$id_msg]) ? self::$my_votes[$id_msg] : 0;
		$own = $id_member === (int) $user_info['id'];
		$can_vote = !$user_info['is_guest'] && !$own && !empty($output['approved']) && allowedTo('qa_vote');

		$output['css_class'] = (isset($output['css_class']) ? $output['css_class'] : '') . ($is_accepted ? ' qa_accepted' : '');
		$output['qa'] = array('score' => $score, 'accepted' => $is_accepted);

		$output['body'] = template_qa_votebox(array(
			'id' => $id_msg,
			'score' => $score,
			'up' => isset(self::$scores[$id_msg]) ? self::$scores[$id_msg]['up'] : 0,
			'down' => isset(self::$scores[$id_msg]) ? self::$scores[$id_msg]['down'] : 0,
			'mine' => $mine,
			'can_vote' => $can_vote,
			'downvotes' => (bool) self::setting('downvotes', 1),
			'accepted' => $is_accepted,
			'can_accept' => $can_manage && !empty($output['approved']),
			'vote_url' => $scripturl . '?action=questions;sa=vote;msg=' . $id_msg . ';' . $context['session_var'] . '=' . $context['session_id'],
			'accept_url' => $scripturl . '?action=questions;sa=' . ($is_accepted ? 'unaccept' : 'accept') . ';msg=' . $id_msg . ';' . $context['session_var'] . '=' . $context['session_id'],
		)) . $output['body'];
	}

	/**
	 * "Solution" box shown under the question.
	 */
	public static function solutionBox($id_msg)
	{
		global $smcFunc, $scripturl, $txt;

		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.body, m.smileys_enabled, m.id_member, m.poster_name, COALESCE(mem.real_name, m.poster_name) AS name, m.approved
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = {int:msg}
				AND m.id_topic = {int:topic}',
			array('msg' => (int) $id_msg, 'topic' => self::$topic_row['id_topic'])
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		if (!$row || empty($row['approved']))
			return '';

		censorText($row['body']);
		$text = trim(preg_replace('~\s+~u', ' ', strip_tags(str_replace(array('<br>', '<br />'), ' ', parse_bbc($row['body'], false, '')))));
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		if (function_exists('mb_strlen') && function_exists('mb_substr'))
			$short = mb_strlen($text, 'UTF-8') > 400 ? mb_substr($text, 0, 400, 'UTF-8') . '…' : $text;
		else
			$short = preg_match('~\A(.{400}).~us', $text, $match) ? $match[1] . '…' : $text;

		$author_name = self::escape($row['name']);
		return template_qa_solution(array(
			'text' => self::escape($short),
			'author' => $author_name,
			'author_link' => !empty($row['id_member']) ? '<a href="' . self::escape($scripturl) . '?action=profile;u=' . (int) $row['id_member'] . '">' . $author_name . '</a>' : $author_name,
			'url' => $scripturl . '?topic=' . self::$topic_row['id_topic'] . '.msg' . $id_msg . '#msg' . $id_msg,
			'score' => isset(self::$scores[$id_msg]) ? self::$scores[$id_msg]['score'] : self::scoreOf($id_msg),
		));
	}

	public static function scoreOf($id_msg)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', 'SELECT score FROM {db_prefix}qa_scores WHERE id_msg = {int:msg}', array('msg' => (int) $id_msg));
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return $row ? (int) $row[0] : 0;
	}

	/**
	 * Called from the topic template layer, after Display() has finished:
	 * wraps SMF's message loader so the page shows in score order.
	 */
	public static function hookMessageOrder()
	{
		global $context;

		if (empty(self::$page_order) || empty($context['get_message']) || $context['get_message'] === array('QA', 'getMessage'))
			return;

		self::$buffer = null;
		self::$buffer_pos = 0;
		$context['qa_original_get_message'] = $context['get_message'];
		$context['get_message'] = array('QA', 'getMessage');
	}

	public static function getMessage($reset = false)
	{
		global $context;

		if (self::$buffer === null)
		{
			self::$buffer = array();
			while ($m = call_user_func($context['qa_original_get_message']))
				self::$buffer[] = $m;

			$order = self::$page_order;
			usort(self::$buffer, function ($a, $b) use ($order)
			{
				$pa = isset($order[$a['id']]) ? $order[$a['id']] : PHP_INT_MAX;
				$pb = isset($order[$b['id']]) ? $order[$b['id']] : PHP_INT_MAX;
				return $pa - $pb;
			});
		}

		if ($reset)
		{
			self::$buffer_pos = 0;
			return true;
		}

		return self::$buffer_pos < count(self::$buffer) ? self::$buffer[self::$buffer_pos++] : false;
	}

	/**
	 * Data for the bar above a question.
	 */
	public static function topicBar()
	{
		global $context, $scripturl;

		$row = self::$topic_row;
		$q = self::$current;
		$base = $scripturl . '?action=questions;topic=' . $row['id_topic'] . ';' . $context['session_var'] . '=' . $context['session_id'];

		return array(
			'is_question' => (bool) $q,
			'solved' => $q && $q['solved'],
			'has_answer' => $q && $q['id_answer'] > 0,
			'answers' => (int) $row['num_replies'],
			'sort' => self::$sort,
			'sort_url' => $scripturl . '?topic=' . $row['id_topic'] . '.0;qa_sort=',
			'can_manage' => self::canManage($row),
			'can_convert' => self::canManage($row) && self::boardMode($row['id_board']) === self::MODE_OPTIONAL,
			'solve_url' => $base . ';sa=solve',
			'unsolve_url' => $base . ';sa=unsolve',
			'convert_url' => $base . ';sa=convert',
			'unconvert_url' => $base . ';sa=unconvert',
		);
	}

	/* ------------------------------------------------------------------
	 * Message index
	 * ---------------------------------------------------------------- */

	/**
	 * Called from the board template layer: badges on question topics.
	 */
	public static function decorateTopics()
	{
		global $context, $smcFunc, $board, $txt;

		if (empty($context['topics']) || !is_array($context['topics']))
			return;

		$ids = array_map('intval', array_keys($context['topics']));
		$found = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, solved
			FROM {db_prefix}qa_topics
			WHERE id_topic IN ({array_int:ids})',
			array('ids' => $ids)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$found[(int) $row[0]] = (int) $row[1];
		$smcFunc['db_free_result']($request);

		$all = self::boardMode($board) === self::MODE_ONLY;

		foreach ($context['topics'] as $id => $topic)
		{
			if (!isset($found[$id]) && !$all)
				continue;

			$solved = !empty($found[$id]);
			$badge = '<span class="qa_badge ' . ($solved ? 'qa_badge_solved' : 'qa_badge_open') . '">' . ($solved ? $txt['qa_solved'] : $txt['qa_question']) . '</span> ';

			if (isset($context['topics'][$id]['first_post']['link']))
				$context['topics'][$id]['first_post']['link'] = $badge . $context['topics'][$id]['first_post']['link'];
			$context['topics'][$id]['css_class'] = (isset($topic['css_class']) ? $topic['css_class'] : '') . ($solved ? ' qa_solved_topic' : '');
		}
	}

	/* ------------------------------------------------------------------
	 * Posting
	 * ---------------------------------------------------------------- */

	/**
	 * integrate_post_end: "This is a question" option when starting a
	 * topic in a board where questions are optional.
	 */
	public static function postEnd()
	{
		global $context, $board, $topic, $txt;

		if (!self::enabled() || !empty($topic) || empty($board))
			return;

		$mode = self::boardMode($board);
		if ($mode === self::MODE_OFF)
			return;

		loadLanguage('QuestionsAnswers');
		loadTemplate('QuestionsAnswers');

		if ($mode === self::MODE_ONLY)
		{
			$context['qa_post_notice'] = true;
			$context['template_layers'][] = 'qa_post';
			return;
		}

		$checked = !empty($_REQUEST['qa_question']);
		$context['posting_fields']['qa_question'] = array(
			'label' => array('text' => $txt['qa_post_label']),
			'input' => array(
				'type' => 'checkbox',
				'attributes' => array('name' => 'qa_question', 'id' => 'qa_question', 'value' => '1', 'checked' => $checked),
				'after' => '<label for="qa_question" class="smalltext"> ' . $txt['qa_post_desc'] . '</label>',
			),
		);
		// Fallback for themes that don't show posting_fields checkboxes.
		$context['qa_post_checked'] = $checked;
		$context['template_layers'][] = 'qa_post';
	}

	/**
	 * integrate_create_topic runs after SMF has inserted the new topic and
	 * linked its first message. integrate_after_create_post runs too early:
	 * the topic does not have an ID at that point for new topics.
	 */
	public static function afterCreateTopic($msgOptions, $topicOptions, $posterOptions = array())
	{
		if (!self::enabled() || empty($topicOptions['id']))
			return;

		$mode = self::boardMode(isset($topicOptions['board']) ? (int) $topicOptions['board'] : 0);
		if ($mode === self::MODE_ONLY || ($mode === self::MODE_OPTIONAL && !empty($_POST['qa_question'])))
			self::makeQuestion((int) $topicOptions['id']);
	}

	/* ------------------------------------------------------------------
	 * Clean-up hooks
	 * ---------------------------------------------------------------- */

	/**
	 * Snapshot message IDs and authors before SMF removes whole topics.
	 * SMF can recycle some topics and permanently delete others in one call,
	 * so cleanup is deferred until integrate_remove_topics reports the IDs
	 * that were actually deleted.
	 */
	protected static $topic_deletion = array();

	public static function beforeRemoveTopics($topics, $recycle_board = 0)
	{
		global $smcFunc;

		$topics = array_values(array_unique(array_filter(array_map('intval', (array) $topics))));
		if (empty($topics))
			return;

		// Refresh any earlier snapshot (e.g. a topic recycled earlier in this request).
		foreach ($topics as $id_topic)
			unset(self::$topic_deletion[$id_topic]);

		foreach (array_chunk($topics, 500) as $chunk)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_topic, id_msg, id_member
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topics})',
				array('topics' => $chunk));
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				$id_topic = (int) $row['id_topic'];
				if (!isset(self::$topic_deletion[$id_topic]))
					self::$topic_deletion[$id_topic] = array('messages' => array(), 'members' => array());
				self::$topic_deletion[$id_topic]['messages'][] = (int) $row['id_msg'];
				if ((int) $row['id_member'] > 0)
					self::$topic_deletion[$id_topic]['members'][(int) $row['id_member']] = (int) $row['id_member'];
			}
			$smcFunc['db_free_result']($request);
		}
	}

	/** Called after SMF has permanently deleted these topics and messages. */
	public static function removeTopics($topics)
	{
		global $smcFunc;

		$topics = array_values(array_unique(array_filter(array_map('intval', (array) $topics))));
		if (empty($topics))
			return;

		$messages = array();
		$members = array();
		foreach ($topics as $id_topic)
		{
			if (isset(self::$topic_deletion[$id_topic]))
			{
				$messages = array_merge($messages, self::$topic_deletion[$id_topic]['messages']);
				$members += self::$topic_deletion[$id_topic]['members'];
				unset(self::$topic_deletion[$id_topic]);
			}
			self::forget($id_topic);
		}

		foreach (array_chunk($messages, 500) as $chunk)
		{
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_votes WHERE id_msg IN ({array_int:msgs})', array('msgs' => $chunk));
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_scores WHERE id_msg IN ({array_int:msgs})', array('msgs' => $chunk));
		}

		foreach (array_chunk($topics, 500) as $chunk)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_topics WHERE id_topic IN ({array_int:topics})', array('topics' => $chunk));

		self::recalcMembers(array_values($members));
	}

	/**
	 * integrate_remove_message runs after SMF removes/moves the message.
	 * The original row is supplied by SMF, so we must not try to load it
	 * from the messages table again.
	 */
	public static function removeMessage($message, $row = array(), $recycle = false)
	{
		global $smcFunc;

		$id_msg = (int) $message;
		if ($id_msg <= 0)
			return;

		$id_topic = !empty($row['id_topic']) ? (int) $row['id_topic'] : 0;
		$author = !empty($row['id_member']) ? (int) $row['id_member'] : 0;

		// A recycled answer no longer belongs to its original question.
		// Discard its vote/score records and recalculate the author in both cases.
		$request = $smcFunc['db_query']('', '
			SELECT id_topic, locked_by_qa
			FROM {db_prefix}qa_topics
			WHERE id_answer = {int:msg}',
			array('msg' => $id_msg));
		$affected = array();
		while ($q = $smcFunc['db_fetch_assoc']($request))
			$affected[] = array('id_topic' => (int) $q['id_topic'], 'locked_by_qa' => (int) $q['locked_by_qa']);
		$smcFunc['db_free_result']($request);

		foreach ($affected as $q)
		{
			// Never unlock a manually locked topic (or a stronger moderator lock).
			if ($q['locked_by_qa'])
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}topics SET locked = {int:zero}
					WHERE id_topic = {int:topic} AND locked = {int:one}',
					array('zero' => 0, 'one' => 1, 'topic' => $q['id_topic']));
			$smcFunc['db_query']('', '
				UPDATE {db_prefix}qa_topics
				SET id_answer = {int:zero}, solved = {int:zero},
					solved_time = {int:zero}, solved_by = {int:zero}, locked_by_qa = {int:zero}
				WHERE id_topic = {int:topic} AND id_answer = {int:msg}',
				array('zero' => 0, 'topic' => $q['id_topic'], 'msg' => $id_msg));
			self::forget($q['id_topic']);
		}

		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_votes WHERE id_msg = {int:msg}', array('msg' => $id_msg));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_scores WHERE id_msg = {int:msg}', array('msg' => $id_msg));
		if ($id_topic)
			self::forget($id_topic);
		self::recalcMembers(array($author));
	}

	public static function deleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		// Their votes go, so the people they voted for are recounted.
		$msgs = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_msg
			FROM {db_prefix}qa_votes
			WHERE id_member IN ({array_int:u})',
			array('u' => $users)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$msgs[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_votes WHERE id_member IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_members WHERE id_member IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}qa_adjustments WHERE id_member IN ({array_int:u})', array('u' => $users));
		$smcFunc['db_query']('', 'UPDATE {db_prefix}qa_adjustments SET id_admin = {int:zero}, admin_name = {string:anonymous} WHERE id_admin IN ({array_int:u})', array('zero' => 0, 'anonymous' => '[deleted admin]', 'u' => $users));

		if (empty($msgs))
			return;

		self::recountScores($msgs);

		$authors = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_member
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:msgs})',
			array('msgs' => $msgs)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$authors[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		self::recalcMembers(array_diff($authors, $users));
	}

	/* ------------------------------------------------------------------
	 * Registration hooks
	 * ---------------------------------------------------------------- */

	public static function actions(&$actionArray)
	{
		$actionArray['questions'] = array('QuestionsAnswers-Pages.php', 'QA_Pages::action');
	}

	public static function menuButtons(&$buttons)
	{
		global $txt, $scripturl;

		if (!self::enabled() || !self::setting('show_menu', 1) || empty(self::boards()))
			return;

		loadLanguage('QuestionsAnswers');

		$button = array('questions' => array(
			'title' => $txt['qa_menu'],
			'href' => $scripturl . '?action=questions',
			'show' => true,
			'icon' => 'help',
		));

		$keys = array_keys($buttons);
		$pos = array_search('search', $keys, true);
		if ($pos === false)
			$pos = array_search('home', $keys, true);
		$buttons = $pos === false ? $button + $buttons : array_slice($buttons, 0, $pos + 1, true) + $button + array_slice($buttons, $pos + 1, null, true);
	}

	public static function profileAreas(&$profile_areas)
	{
		global $txt;

		if (!self::enabled())
			return;

		loadLanguage('QuestionsAnswers');

		$profile_areas['info']['areas']['qa'] = array(
			'label' => $txt['qa_profile_area'],
			'file' => 'QuestionsAnswers-Pages.php',
			'function' => 'QA_Pages::profile',
			'icon' => 'stats',
			'permission' => array(
				'own' => 'is_not_guest',
				'any' => 'profile_view',
			),
		);
	}

	public static function fetchAlerts(&$alerts, &$formats = array())
	{
		loadLanguage('QuestionsAnswers');
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('QuestionsAnswers');
		$permissionList['board']['qa_vote'] = array(false, 'post');
		$permissionList['board']['qa_accept'] = array(true, 'topic');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		foreach (array('qa_vote', 'qa_accept_own', 'qa_accept_any', 'qa_accept') as $p)
			$context['non_guest_permissions'][] = $p;
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('QuestionsAnswers');

		$admin_areas['config']['areas']['questionsanswers'] = array(
			'label' => $txt['qa_title'],
			'file' => 'QuestionsAnswers-Pages.php',
			'function' => 'QA_Pages::admin',
			'icon' => 'help',
			'permission' => array('admin_forum'),
			'subsections' => array(
				'settings' => array($txt['qa_admin_settings']),
				'boards' => array($txt['qa_admin_boards']),
				'maintain' => array($txt['qa_admin_maintain']),
				'reputation' => array($txt['qa_admin_reputation']),
			),
		);
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('QuestionsAnswers');
		$context['copyrights']['mods'][] = sprintf($txt['qa_credits'], self::VERSION);
	}
}
