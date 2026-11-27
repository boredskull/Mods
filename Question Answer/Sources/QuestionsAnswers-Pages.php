<?php

/**
 * Questions & Answers: pages and actions.
 *
 * @package QuestionsAnswers
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

if (!defined('SMF'))
	die('No direct access...');

class QA_Pages
{
	public static function action()
	{
		global $context, $txt, $scripturl;

		if (!QA::enabled())
			redirectexit();

		loadLanguage('QuestionsAnswers');

		$sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'list';
		$actions = array(
			'vote' => 'vote', 'accept' => 'accept', 'unaccept' => 'unaccept', 'solve' => 'solve',
			'unsolve' => 'unsolve', 'convert' => 'convert', 'unconvert' => 'unconvert',
		);

		if (isset($actions[$sa]))
		{
			$method = $actions[$sa];
			self::$method();
			return;
		}

		self::listing();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	protected static function wantsJson()
	{
		return !empty($_REQUEST['js']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
	}

	protected static function json(array $data)
	{
		while (ob_get_level() > 0)
			@ob_end_clean();

		header('Content-Type: application/json; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data);
		die;
	}

	/**
	 * Topic the member can see, as a topic row.
	 */
	protected static function requireTopic($id_topic)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:topic}
				AND {query_see_board}',
			array('topic' => (int) $id_topic)
		);
		$ok = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		$row = $ok ? QA::topicRow($id_topic) : null;
		if (!$row)
			fatal_lang_error('qa_error_not_found', false);

		return $row;
	}

	protected static function requireManager(array $topic)
	{
		is_not_guest();
		if (!QA::canManage($topic))
			fatal_lang_error('qa_error_not_allowed', false);
	}

	protected static function backToTopic(array $topic, $id_msg = 0)
	{
		redirectexit('topic=' . $topic['id_topic'] . ($id_msg ? '.msg' . $id_msg . '#msg' . $id_msg : '.0'));
	}

	/* ------------------------------------------------------------------
	 * Actions
	 * ---------------------------------------------------------------- */

	public static function vote()
	{
		global $txt;

		checkSession('request');

		$id_msg = isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0;
		$dir = isset($_REQUEST['v']) && $_REQUEST['v'] === 'down' ? -1 : 1;

		$result = QA::vote($id_msg, $dir);

		if (self::wantsJson())
			self::json($result[0]
				? array('ok' => true, 'score' => $result[1], 'vote' => $result[2])
				: array('ok' => false, 'error' => isset($txt[$result[1]]) ? $txt[$result[1]] : $result[1]));

		if (!$result[0])
			fatal_lang_error($result[1], false);

		$msg = QA::message($id_msg);
		redirectexit('topic=' . $msg['id_topic'] . '.msg' . $id_msg . '#msg' . $id_msg);
	}

	protected static function topicFromMsg()
	{
		$msg = QA::message(isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0);
		if (!$msg)
			fatal_lang_error('qa_error_not_found', false);

		return array(self::requireTopic($msg['id_topic']), $msg);
	}

	public static function accept()
	{
		checkSession('get');
		list ($topic, $msg) = self::topicFromMsg();
		self::requireManager($topic);

		$result = QA::accept($topic, $msg['id_msg']);
		if ($result !== true)
			fatal_lang_error($result, false);

		self::backToTopic($topic, $msg['id_msg']);
	}

	public static function unaccept()
	{
		checkSession('get');
		list ($topic, $msg) = self::topicFromMsg();
		self::requireManager($topic);

		// Do not silently redirect if the clicked answer is no longer the
		// accepted one or if the database update fails.
		$result = QA::setSolved($topic, false, $msg['id_msg']);
		if ($result !== true)
			fatal_lang_error($result, false);

		self::backToTopic($topic, $msg['id_msg']);
	}

	protected static function topicFromRequest()
	{
		$topic = self::requireTopic(isset($_REQUEST['topic']) ? (int) $_REQUEST['topic'] : 0);
		self::requireManager($topic);

		return $topic;
	}

	public static function solve()
	{
		checkSession('get');
		$topic = self::topicFromRequest();
		$result = QA::setSolved($topic, true);
		if ($result !== true)
			fatal_lang_error($result, false);
		self::backToTopic($topic);
	}

	public static function unsolve()
	{
		checkSession('get');
		$topic = self::topicFromRequest();
		$result = QA::setSolved($topic, false);
		if ($result !== true)
			fatal_lang_error($result, false);
		self::backToTopic($topic);
	}

	public static function convert()
	{
		checkSession('get');
		$topic = self::topicFromRequest();
		if (QA::boardMode($topic['id_board']) === QA::MODE_OFF)
			fatal_lang_error('qa_error_board_off', false);

		QA::makeQuestion($topic['id_topic']);
		self::backToTopic($topic);
	}

	public static function unconvert()
	{
		checkSession('get');
		$topic = self::topicFromRequest();
		if (QA::boardMode($topic['id_board']) !== QA::MODE_OPTIONAL)
			fatal_lang_error('qa_error_question_only', false);

		QA::unmakeQuestion($topic);
		self::backToTopic($topic);
	}

	/* ------------------------------------------------------------------
	 * Questions page
	 * ---------------------------------------------------------------- */

	/**
	 * Question boards the member can see.
	 */
	public static function visibleBoards()
	{
		global $smcFunc;

		$qa_boards = QA::boards();
		if (empty($qa_boards))
			return array();

		$out = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE b.id_board IN ({array_int:boards})
				AND {query_see_board}
			ORDER BY c.cat_order, b.board_order',
			array('boards' => array_keys($qa_boards))
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$out[(int) $row['id_board']] = $row['name'];
		$smcFunc['db_free_result']($request);

		return $out;
	}

	public static function listing()
	{
		global $context, $smcFunc, $user_info, $txt, $scripturl, $modSettings;

		loadTemplate('QuestionsAnswers');

		$context['page_title'] = $txt['qa_title_questions'];
		$context['linktree'][] = array('url' => $scripturl . '?action=questions', 'name' => $txt['qa_title_questions']);
		$context['sub_template'] = 'qa_list';

		$tabs = array('unsolved', 'unanswered', 'solved', 'all');
		if (!$user_info['is_guest'])
			$tabs[] = 'mine';
		$sorts = array('newest', 'active', 'answers');

		$tab = isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true) ? $_GET['tab'] : 'unsolved';
		$sort = isset($_GET['sort']) && in_array($_GET['sort'], $sorts, true) ? $_GET['sort'] : 'newest';

		$boards = self::visibleBoards();
		$id_board = isset($_GET['board']) && isset($boards[(int) $_GET['board']]) ? (int) $_GET['board'] : 0;

		$context['qa_tabs'] = $tabs;
		$context['qa_tab'] = $tab;
		$context['qa_sorts'] = $sorts;
		$context['qa_sort'] = $sort;
		$context['qa_boards'] = $boards;
		$context['qa_board'] = $id_board;
		$context['qa_base'] = $scripturl . '?action=questions' . ($id_board ? ';board=' . $id_board : '');
		$context['qa_questions'] = array();
		$context['qa_leaders'] = self::leaders(10);
		$context['page_index'] = '';

		if (empty($boards))
			return;

		QA::syncQuestionOnly();

		$where = array('t.id_board IN ({array_int:boards})');
		$params = array('boards' => $id_board ? array($id_board) : array_keys($boards), 'one' => 1, 'zero' => 0, 'me' => (int) $user_info['id']);

		if (!empty($modSettings['postmod_active']) && !allowedTo('approve_posts'))
			$where[] = '(t.approved = {int:one}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:me}') . ')';

		switch ($tab)
		{
			case 'unsolved':
				$where[] = 'q.solved = {int:zero}';
				break;
			case 'unanswered':
				$where[] = 'q.solved = {int:zero}';
				$where[] = 't.num_replies = {int:zero}';
				break;
			case 'solved':
				$where[] = 'q.solved = {int:one}';
				break;
			case 'mine':
				$where[] = 't.id_member_started = {int:me}';
				break;
		}

		$from = '
			FROM {db_prefix}qa_topics AS q
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
			WHERE ' . implode('
				AND ', $where);

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) ' . $from, $params);
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$per_page = 20;
		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['qa_base'] . ';tab=' . $tab . ';sort=' . $sort, $start, (int) $total, $per_page);

		$order = array(
			'newest' => 't.id_first_msg DESC',
			'active' => 't.id_last_msg DESC',
			'answers' => 't.num_replies DESC, t.id_last_msg DESC',
		);

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT q.id_topic ' . $from . '
			ORDER BY ' . $order[$sort] . '
			LIMIT {int:start}, {int:limit}',
			$params + array('start' => $start, 'limit' => $per_page)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		if (empty($ids))
			return;

		$request = $smcFunc['db_query']('', '
			SELECT t.id_topic, t.id_board, t.num_replies, t.num_views, t.id_member_started, t.id_last_msg,
				mf.subject, mf.poster_time, COALESCE(memf.real_name, mf.poster_name) AS starter,
				ml.poster_time AS last_time, COALESCE(meml.real_name, ml.poster_name) AS last_poster, ml.id_member AS last_member,
				q.solved, q.id_answer, COALESCE(s.score, 0) AS answer_score
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}qa_topics AS q ON (q.id_topic = t.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}qa_scores AS s ON (s.id_msg = q.id_answer)
			WHERE t.id_topic IN ({array_int:ids})',
			array('ids' => $ids)
		);
		$rows = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$rows[(int) $row['id_topic']] = $row;
		$smcFunc['db_free_result']($request);

		foreach ($ids as $id)
		{
			if (!isset($rows[$id]))
				continue;
			$row = $rows[$id];
			censorText($row['subject']);

			$context['qa_questions'][] = array(
				'id' => $id,
				'subject' => QA::escape($row['subject']),
				'url' => $scripturl . '?topic=' . $id . '.0',
				'board' => isset($boards[(int) $row['id_board']]) ? QA::escape($boards[(int) $row['id_board']]) : '',
				'board_url' => $scripturl . '?board=' . $row['id_board'] . '.0',
				'starter' => !empty($row['id_member_started']) ? '<a href="' . QA::escape($scripturl) . '?action=profile;u=' . (int) $row['id_member_started'] . '">' . QA::escape($row['starter']) . '</a>' : QA::escape($row['starter']),
				'time' => timeformat($row['poster_time']),
				'answers' => (int) $row['num_replies'],
				'views' => (int) $row['num_views'],
				'solved' => !empty($row['solved']),
				'has_answer' => !empty($row['id_answer']),
				'answer_score' => (int) $row['answer_score'],
				'answer_url' => !empty($row['id_answer']) ? $scripturl . '?topic=' . $id . '.msg' . $row['id_answer'] . '#msg' . $row['id_answer'] : '',
				'last' => (int) $row['num_replies'] > 0 ? sprintf($txt['qa_last_answer'], timeformat($row['last_time']), QA::escape($row['last_poster'])) : '',
			);
		}
	}

	public static function leaders($limit)
	{
		global $smcFunc, $scripturl;

		$out = array();
		$request = $smcFunc['db_query']('', '
			SELECT q.id_member, q.reputation, q.accepted, mem.real_name
			FROM {db_prefix}qa_members AS q
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = q.id_member)
			WHERE q.reputation > {int:zero}
			ORDER BY q.reputation DESC, q.accepted DESC, q.id_member
			LIMIT {int:limit}',
			array('zero' => 0, 'limit' => (int) $limit)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$out[] = array(
				'link' => '<a href="' . QA::escape($scripturl) . '?action=profile;area=qa;u=' . (int) $row['id_member'] . '">' . QA::escape($row['real_name']) . '</a>',
				'reputation' => (int) $row['reputation'],
				'accepted' => (int) $row['accepted'],
			);
		$smcFunc['db_free_result']($request);

		return $out;
	}

	/* ------------------------------------------------------------------
	 * Profile
	 * ---------------------------------------------------------------- */

	public static function profile($memID)
	{
		global $context, $smcFunc, $txt, $scripturl, $user_profile;

		loadLanguage('QuestionsAnswers');
		loadTemplate('QuestionsAnswers');

		$memID = (int) $memID;
		QA::syncQuestionOnly();
		$reps = QA::loadReps(array($memID));
		$stats = isset($reps[$memID]) ? $reps[$memID] : array('reputation' => 0, 'up' => 0, 'down' => 0, 'accepted' => 0);

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*), SUM(CASE WHEN q.solved = {int:one} THEN 1 ELSE 0 END)
			FROM {db_prefix}qa_topics AS q
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_member_started = {int:mem}
				AND t.approved = {int:approved}
				AND {query_see_board}',
			array('mem' => $memID, 'one' => 1, 'approved' => 1)
		);
		list ($asked, $asked_solved) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$stats['asked'] = (int) $asked;
		$stats['asked_solved'] = (int) $asked_solved;
		$stats['rank'] = self::rank($stats['reputation']);

		// Best answers: accepted first, then by score.
		$answers = array();
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_topic, m.poster_time, mf.subject, COALESCE(s.score, 0) AS score,
				CASE WHEN q.id_answer = m.id_msg THEN 1 ELSE 0 END AS accepted
			FROM {db_prefix}qa_scores AS s
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = s.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}qa_topics AS q ON (q.id_topic = t.id_topic)
			WHERE m.id_member = {int:mem}
				AND m.approved = {int:approved}
				AND t.approved = {int:approved}
				AND {query_see_board}
			ORDER BY s.score DESC, m.id_msg DESC
			LIMIT 10',
			array('mem' => $memID, 'approved' => 1)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$answers[(int) $row['id_msg']] = $row;
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg, m.id_topic, m.poster_time, mf.subject, COALESCE(s.score, 0) AS score, 1 AS accepted
			FROM {db_prefix}qa_topics AS q
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = q.id_answer AND m.id_topic = q.id_topic)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = q.id_topic)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}qa_scores AS s ON (s.id_msg = m.id_msg)
			WHERE m.id_member = {int:mem}
				AND m.approved = {int:approved}
				AND t.approved = {int:approved}
				AND q.solved = {int:one}
				AND {query_see_board}
			ORDER BY q.solved_time DESC
			LIMIT 10',
			array('mem' => $memID, 'approved' => 1, 'one' => 1)
		);
		$accepted = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$accepted[(int) $row['id_msg']] = $row;
		$smcFunc['db_free_result']($request);

		$format = function (array $rows) use ($scripturl)
		{
			$out = array();
			foreach ($rows as $row)
			{
				censorText($row['subject']);
				$out[] = array(
					'subject' => QA::escape($row['subject']),
					'url' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
					'score' => (int) $row['score'],
					'accepted' => !empty($row['accepted']),
					'time' => timeformat($row['poster_time']),
				);
			}
			return $out;
		};

		$context['qa_profile'] = array(
			'stats' => $stats,
			'points' => QA::points(),
			'top' => $format($answers),
			'accepted' => $format($accepted),
			'name' => isset($user_profile[$memID]['real_name']) ? QA::escape($user_profile[$memID]['real_name']) : '',
		);
		$context['page_title'] = $txt['qa_profile_area'];
		$context['sub_template'] = 'qa_profile';
	}

	/**
	 * Position on the reputation leaderboard (1 = top), or 0.
	 */
	protected static function rank($reputation)
	{
		global $smcFunc;

		if ($reputation <= 0)
			return 0;

		$request = $smcFunc['db_query']('', '
			SELECT COUNT(*)
			FROM {db_prefix}qa_members
			WHERE reputation > {int:rep}',
			array('rep' => (int) $reputation)
		);
		list ($above) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		return (int) $above + 1;
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ---------------------------------------------------------------- */

	public static function admin()
	{
		global $context, $txt;

		isAllowedTo('admin_forum');
		loadLanguage('QuestionsAnswers');
		loadTemplate('QuestionsAnswers');

		$context['page_title'] = $txt['qa_title'];
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['qa_title'],
			'description' => $txt['qa_admin_desc'],
			'tabs' => array('settings' => array(), 'boards' => array(), 'maintain' => array(), 'reputation' => array()),
		);

		$sa = isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], array('settings', 'boards', 'maintain', 'reputation'), true) ? $_REQUEST['sa'] : 'settings';
		$method = 'admin' . ucfirst($sa);
		self::$method();
	}

	public static function adminSettings()
	{
		global $context, $txt, $sourcedir, $scripturl;

		require_once($sourcedir . '/ManageServer.php');

		$config_vars = array(
			array('check', 'qa_enabled'),
			array('check', 'qa_show_menu'),
			array('check', 'qa_index_badges'),
			'',
			array('title', 'qa_answers_title'),
			array('select', 'qa_default_sort', array('votes' => $txt['qa_sort_votes'], 'date' => $txt['qa_sort_date'])),
			array('check', 'qa_pin_accepted', 'subtext' => $txt['qa_pin_accepted_desc']),
			array('check', 'qa_downvotes'),
			array('int', 'qa_downvote_min_rep', 'subtext' => $txt['qa_downvote_min_rep_desc']),
			array('check', 'qa_lock_solved', 'subtext' => $txt['qa_lock_solved_desc']),
			array('check', 'qa_alerts'),
			'',
			array('title', 'qa_rep_title'),
			array('int', 'qa_points_up', 'min' => 0, 'max' => 1000),
			array('int', 'qa_points_down', 'min' => 0, 'max' => 1000),
			array('int', 'qa_points_accepted', 'min' => 0, 'max' => 1000),
			array('check', 'qa_rep_everywhere', 'subtext' => $txt['qa_rep_everywhere_desc']),
		);

		$context['post_url'] = $scripturl . '?action=admin;area=questionsanswers;sa=settings;save';
		$context['settings_title'] = $txt['qa_admin_settings'];
		$context['settings_message'] = $txt['qa_points_note'];

		if (isset($_GET['save']))
		{
			checkSession();
			$old = QA::points();
			foreach (array('qa_points_up', 'qa_points_down', 'qa_points_accepted', 'qa_downvote_min_rep') as $k)
				$_POST[$k] = max(0, min(1000, (int) ($_POST[$k] ?? 0)));
			saveDBSettings($config_vars);

			// New points values: everyone's reputation needs recounting.
			if (QA::points() !== $old)
				$_SESSION['qa_recount_needed'] = true;

			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=questionsanswers;sa=settings');
		}

		if (!empty($_SESSION['qa_recount_needed']))
			$context['settings_message'] = '<div class="noticebox">' . sprintf($txt['qa_recount_needed'], $scripturl . '?action=admin;area=questionsanswers;sa=maintain') . '</div>';

		prepareDBSettingContext($config_vars);
		$context['sub_template'] = 'show_settings';
	}

	public static function adminBoards()
	{
		global $context, $smcFunc, $txt;

		$all = array();
		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.name, b.child_level, c.id_cat, c.name AS cat_name
			FROM {db_prefix}boards AS b
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			ORDER BY c.cat_order, b.board_order',
			array()
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$all[(int) $row['id_board']] = $row;
		$smcFunc['db_free_result']($request);

		if (isset($_POST['save']))
		{
			checkSession();

			$old = QA::boards();
			$new = array();
			foreach ((array) ($_POST['mode'] ?? array()) as $id => $mode)
				if (isset($all[(int) $id]) && in_array((int) $mode, array(1, 2), true))
					$new[(int) $id] = (int) $mode;

			updateSettings(array('qa_boards' => json_encode((object) $new)));

			// Boards that became question-only: make their topics questions.
			$converted = 0;
			foreach ($new as $id => $mode)
				if ($mode === QA::MODE_ONLY && (!isset($old[$id]) || $old[$id] !== QA::MODE_ONLY))
					$converted += self::convertBoard($id);

			$_SESSION['qa_board_notice'] = sprintf($txt['qa_boards_saved'], $converted);
			redirectexit('action=admin;area=questionsanswers;sa=boards');
		}

		$context['qa_notice'] = isset($_SESSION['qa_board_notice']) ? $_SESSION['qa_board_notice'] : '';
		unset($_SESSION['qa_board_notice']);

		$context['qa_all_boards'] = $all;
		$context['qa_modes'] = QA::boards();
		$context['sub_template'] = 'qa_admin_boards';
	}

	public static function convertBoard($id_board)
	{
		global $smcFunc;

		$total = 0;
		$last = 0;
		do
		{
			$ids = array();
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}topics
				WHERE id_board = {int:board}
					AND id_topic > {int:last}
				ORDER BY id_topic
				LIMIT 500',
				array('board' => (int) $id_board, 'last' => $last)
			);
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			if (empty($ids))
				break;

			$rows = array();
			foreach ($ids as $id)
				$rows[] = array($id, 0, 0, 0, 0, time(), 0);
			$smcFunc['db_insert']('ignore',
				'{db_prefix}qa_topics',
				array('id_topic' => 'int', 'solved' => 'int', 'id_answer' => 'int', 'solved_time' => 'int', 'solved_by' => 'int', 'created' => 'int', 'locked_by_qa' => 'int'),
				$rows,
				array('id_topic')
			);

			$total += count($ids);
			$last = end($ids);
		}
		while (count($ids) === 500);

		return $total;
	}

    /** Resolve exact member ID or login name. Display names are allowed only
     * when unambiguous; never silently adjust the wrong person's points. */
    protected static function resolveRepMember($value)
    {
        global $smcFunc;
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 100)
            return 0;

        if (ctype_digit($value))
        {
            $request = $smcFunc['db_query']('', '
                SELECT id_member FROM {db_prefix}members WHERE id_member = {int:id}',
                array('id' => (int) $value)
            );
        }
        else
        {
            $request = $smcFunc['db_query']('', '
                SELECT id_member FROM {db_prefix}members
                WHERE member_name = {string:name} OR real_name = {string:name}
                LIMIT 2',
                array('name' => $value)
            );
        }
        $ids = array();
        while ($row = $smcFunc['db_fetch_row']($request))
            $ids[] = (int) $row[0];
        $smcFunc['db_free_result']($request);
        return count($ids) === 1 ? $ids[0] : 0;
    }

    /** Administrator-only reputation adjustments and immutable audit trail. */
    public static function adminReputation()
    {
        global $smcFunc, $context, $txt, $scripturl;

        isAllowedTo('admin_forum');
        $member_input = isset($_REQUEST['member']) && is_scalar($_REQUEST['member']) ? (string) $_REQUEST['member'] : '';
        $member_id = self::resolveRepMember($member_input);
        $context['qa_rep_message'] = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkSession('post');
            validateToken('qa_reputation', 'post');
            if (isset($_POST['lookup']))
            {
                if (!$member_id)
                    fatal_lang_error('qa_adjust_member_not_found', false);
                redirectexit('action=admin;area=questionsanswers;sa=reputation;member=' . $member_id);
            }

            if (isset($_POST['add_adjustment']))
            {
                if (!$member_id)
                    fatal_lang_error('qa_adjust_member_not_found', false);
                $amount = isset($_POST['points']) && is_scalar($_POST['points']) ? trim((string) $_POST['points']) : '';
                $reason = isset($_POST['reason']) && is_scalar($_POST['reason']) ? trim((string) $_POST['reason']) : '';
                $type = isset($_POST['direction']) && is_scalar($_POST['direction']) ? (string) $_POST['direction'] : '';
                if (!preg_match('/^[1-9][0-9]{0,5}$/D', $amount) || (int) $amount > 100000 || !in_array($type, array('award', 'deduct'), true))
                    fatal_lang_error('qa_adjust_invalid_points', false);
                if ($reason === '' || strlen($reason) > 1000)
                    fatal_lang_error('qa_adjust_invalid_reason', false);
                QA::addAdjustment($member_id, ($type === 'deduct' ? -1 : 1) * (int) $amount, $reason);
                redirectexit('action=admin;area=questionsanswers;sa=reputation;member=' . $member_id . ';saved=1');
            }
            if (isset($_POST['recalculate_member']))
            {
                if (!$member_id)
                    fatal_lang_error('qa_adjust_member_not_found', false);
                $smcFunc['db_transaction']('begin');
                try
                {
                    $request = $smcFunc['db_query']('', '
                        SELECT id_member FROM {db_prefix}members WHERE id_member = {int:member} FOR UPDATE',
                        array('member' => $member_id)
                    );
                    $found = $smcFunc['db_fetch_row']($request);
                    $smcFunc['db_free_result']($request);
                    if (!$found)
                        fatal_lang_error('qa_adjust_member_not_found', false);
                    QA::recalcMembers(array($member_id));
                    $smcFunc['db_transaction']('commit');
                }
                catch (\Throwable $e)
                {
                    $smcFunc['db_transaction']('rollback');
                    throw $e;
                }
                redirectexit('action=admin;area=questionsanswers;sa=reputation;member=' . $member_id . ';saved=1');
            }
            if (isset($_POST['reverse_adjustment']))
            {
                $id_adjustment = isset($_POST['adjustment']) && is_scalar($_POST['adjustment']) ? (int) $_POST['adjustment'] : 0;
                $reason = isset($_POST['reason']) && is_scalar($_POST['reason']) ? trim((string) $_POST['reason']) : '';
                if (!$member_id || $id_adjustment < 1 || $reason === '' || strlen($reason) > 1000)
                    fatal_lang_error('qa_adjust_invalid_reason', false);
                // Never reverse a record belonging to a different member.
                $request = $smcFunc['db_query']('', '
                    SELECT id_member FROM {db_prefix}qa_adjustments WHERE id_adjustment = {int:id}',
                    array('id' => $id_adjustment)
                );
                $row = $smcFunc['db_fetch_row']($request);
                $smcFunc['db_free_result']($request);
                if (!$row || (int) $row[0] !== $member_id)
                    fatal_lang_error('qa_adjust_invalid_reversal', false);
                try
                {
                    QA::reverseAdjustment($id_adjustment, $reason);
                }
                catch (\InvalidArgumentException $e)
                {
                    fatal_lang_error('qa_adjust_invalid_reversal', false);
                }
                redirectexit('action=admin;area=questionsanswers;sa=reputation;member=' . $member_id . ';saved=1');
            }
            fatal_lang_error('qa_adjust_invalid_action', false);
        }

        $context['qa_rep_input'] = $member_input;
        $context['qa_rep_member'] = null;
        $context['qa_rep_history'] = array();
        if (!empty($_GET['saved']))
            $context['qa_rep_message'] = $txt['qa_adjust_saved'];
        if ($member_input !== '' && !$member_id)
            $context['qa_rep_message'] = $txt['qa_adjust_member_not_found'];

        if ($member_id)
        {
            $request = $smcFunc['db_query']('', '
                SELECT id_member, real_name, member_name
                FROM {db_prefix}members WHERE id_member = {int:id}',
                array('id' => $member_id)
            );
            $member = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            if ($member)
            {
                $reps = QA::loadReps(array($member_id));
                $stats = isset($reps[$member_id]) ? $reps[$member_id] : array('up' => 0, 'down' => 0, 'accepted' => 0, 'reputation' => 0);
                $points = QA::points();
                $earned = (int) $stats['up'] * $points['up'] - (int) $stats['down'] * $points['down'] + (int) $stats['accepted'] * $points['accepted'];
                $adjustments = QA::adjustmentTotal($member_id);
                $context['qa_rep_member'] = array(
                    'id' => $member_id,
                    'name' => $member['real_name'],
                    'username' => $member['member_name'],
                    'earned' => $earned,
                    'adjustments' => $adjustments,
                    'total' => (int) $stats['reputation'],
                    'up' => (int) $stats['up'],
                    'down' => (int) $stats['down'],
                    'accepted' => (int) $stats['accepted'],
                    'needs_recount' => $earned + $adjustments !== (int) $stats['reputation'],
                );
                $request = $smcFunc['db_query']('', '
                    SELECT id_adjustment, id_admin, admin_name, points, reason, created, reversal_of, reversed_by
                    FROM {db_prefix}qa_adjustments
                    WHERE id_member = {int:member}
                    ORDER BY id_adjustment DESC LIMIT 100',
                    array('member' => $member_id)
                );
                while ($row = $smcFunc['db_fetch_assoc']($request))
                    $context['qa_rep_history'][] = $row;
                $smcFunc['db_free_result']($request);
            }
        }

        createToken('qa_reputation');
        $context['qa_rep_post_url'] = $scripturl . '?action=admin;area=questionsanswers;sa=reputation';
        $context['sub_template'] = 'qa_admin_reputation';
    }

	public static function adminMaintain()
	{
		global $context, $txt;

		$context['qa_done'] = false;

		if (isset($_REQUEST['recount']))
		{
			checkSession('request');

			$start = isset($_REQUEST['start']) ? max(0, (int) $_REQUEST['start']) : 0;
			$next = QA::recountStep($start);

			if ($next !== false)
				redirectexit('action=admin;area=questionsanswers;sa=maintain;recount;start=' . $next . ';' . $context['session_var'] . '=' . $context['session_id']);

			unset($_SESSION['qa_recount_needed']);
			$context['qa_done'] = true;
		}

		$context['sub_template'] = 'qa_admin_maintain';
	}
}
