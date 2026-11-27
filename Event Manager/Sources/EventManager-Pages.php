<?php

/**
 * Event Manager: pages and actions.
 *
 * @package EventManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

if (!defined('SMF'))
	die('No direct access...');

class EM_Pages
{
	public static function action()
	{
		global $context, $txt, $scripturl;

		if (!EM::enabled())
			redirectexit();

		loadLanguage('EventManager');

		$sa = isset($_REQUEST['sa']) ? (string) $_REQUEST['sa'] : 'list';

		// Calendar feeds use a personal token instead of a login.
		if ($sa === 'feed')
		{
			self::feed();
			return;
		}

		isAllowedTo('calendar_view');

		loadTemplate('EventManager');
		loadJavaScriptFile('eventmanager.js', array('minimize' => true, 'defer' => true, 'seed' => EM::VERSION), 'smf_eventmanager');

		$context['em_url'] = $scripturl . '?action=events';
		$context['em_session'] = ';' . $context['session_var'] . '=' . $context['session_id'];
		$context['page_title'] = $txt['em_title'];
		$context['linktree'][] = array('url' => $context['em_url'], 'name' => $txt['em_title']);

		$context['em_notice'] = isset($_SESSION['em_notice']) ? $_SESSION['em_notice'] : null;
		unset($_SESSION['em_notice']);

		$pages = array(
			'list' => 'listing', 'view' => 'view', 'rsvp' => 'rsvp', 'manage' => 'manage', 'attendee' => 'attendee',
			'add' => 'add', 'message' => 'message', 'export' => 'export', 'ics' => 'ics', 'token' => 'token', 'cancel' => 'cancel',
		);
		$method = isset($pages[$sa]) ? $pages[$sa] : 'listing';
		self::$method();
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	protected static function notice($text, $type = 'info')
	{
		$_SESSION['em_notice'] = array($type, $text);
	}

	protected static function requireEvent()
	{
		$event = EM::load(isset($_REQUEST['event']) ? (int) $_REQUEST['event'] : 0);
		if (!$event || !EM::canSee($event))
			fatal_lang_error('em_error_not_found', false);

		return $event;
	}

	protected static function requireManager(array $event)
	{
		if (!EM::canManage($event))
			fatal_lang_error('em_error_not_allowed', false);
	}

	protected static function back(array $event, $page = 'view')
	{
		redirectexit('action=events;sa=' . $page . ';event=' . $event['id']);
	}

	protected static function people(array $ids)
	{
		global $memberContext;

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
		$out = array();
		if (empty($ids))
			return $out;

		foreach ((array) loadMemberData($ids) as $id)
			if (loadMemberContext($id))
				$out[$id] = array(
					'id' => $id,
					'name' => $memberContext[$id]['name'],
					'link' => $memberContext[$id]['link'],
					'avatar' => !empty($memberContext[$id]['avatar']['href']) ? $memberContext[$id]['avatar']['href'] : '',
				);

		return $out;
	}

	/**
	 * Registrations for an event, grouped by status.
	 */
	public static function attendees(array $event)
	{
		global $smcFunc;

		$rows = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member, status, guests, note, registered, updated, checked_in
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
			ORDER BY registered, id_member',
			array('event' => $event['id'])
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$rows[(int) $row['id_member']] = $row;
		$smcFunc['db_free_result']($request);

		$people = self::people(array_keys($rows));
		$out = array_fill_keys(EM::$statuses, array());
		foreach ($rows as $id => $row)
		{
			if (!isset($people[$id]) || !isset($out[$row['status']]))
				continue;
			$out[$row['status']][] = $people[$id] + array(
				'status' => $row['status'],
				'guests' => (int) $row['guests'],
				'note' => $row['note'],
				'registered' => timeformat($row['registered']),
				'registered_ts' => (int) $row['registered'],
				'checked_in' => !empty($row['checked_in']),
			);
		}

		return $out;
	}

	protected static function canSeeAttendees(array $event, $mine)
	{
		global $user_info;

		if (EM::canManage($event))
			return true;

		switch ($event['visibility'])
		{
			case 'public':
				return true;
			case 'members':
				return !$user_info['is_guest'];
			case 'attendees':
				return $mine && in_array($mine['status'], array('going', 'maybe', 'waitlist', 'pending'), true);
			default:
				return false;
		}
	}

	/* ------------------------------------------------------------------
	 * Event list
	 * ---------------------------------------------------------------- */

	public static function listing()
	{
		global $context, $smcFunc, $user_info, $txt;

		$tabs = array('upcoming', 'past');
		if (!$user_info['is_guest'])
			array_splice($tabs, 1, 0, array('mine', 'organising'));

		$tab = isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true) ? $_GET['tab'] : 'upcoming';
		$today = date('Y-m-d');

		$where = array('(cal.id_board = {int:zero} OR {query_see_board})');
		$params = array('zero' => 0, 'me' => (int) $user_info['id'], 'today' => $today, 'empty' => '', 'active' => array('going', 'maybe', 'waitlist', 'pending'));

		if ($tab === 'past')
			$where[] = 'cal.end_date < {date:today}';
		else
			$where[] = 'cal.end_date >= {date:today}';
		if ($tab === 'mine')
			$where[] = 'r.status IN ({array_string:active})';
		if ($tab === 'organising')
			$where[] = 'cal.id_member = {int:me}';

		$from = '
			FROM {db_prefix}calendar AS cal
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
				LEFT JOIN {db_prefix}em_registrations AS r ON (r.id_event = cal.id_event AND r.id_member = {int:me})
			WHERE ' . implode(' AND ', $where);

		$request = $smcFunc['db_query']('', 'SELECT COUNT(*) ' . $from, $params);
		list ($total) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$start = isset($_GET['start']) ? max(0, (int) $_GET['start']) : 0;
		$context['page_index'] = constructPageIndex($context['em_url'] . ';tab=' . $tab, $start, (int) $total, 20);

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event, COALESCE(r.status, {string:empty}) AS my_status ' . $from . '
			ORDER BY cal.start_date ' . ($tab === 'past' ? 'DESC' : 'ASC') . ', cal.id_event
			LIMIT {int:start}, 20',
			$params + array('start' => $start)
		);
		$mine = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$ids[] = (int) $row['id_event'];
			$mine[(int) $row['id_event']] = $row['my_status'];
		}
		$smcFunc['db_free_result']($request);

		$context['em_events'] = array();
		foreach ($ids as $id)
		{
			$event = EM::load($id);
			if (!$event)
				continue;
			$counts = EM::counts($id);
			$context['em_events'][] = array(
				'event' => $event,
				'counts' => $counts,
				'my_status' => $mine[$id],
				'when' => EM::describeTime($event),
				'url' => $context['em_url'] . ';sa=view;event=' . $id,
				'month' => self::monthLabel($event),
			);
		}

		$context['em_tab'] = $tab;
		$context['em_tabs'] = $tabs;
		$context['em_can_post'] = allowedTo('calendar_post');
		$context['em_feed_url'] = self::feedUrl();
		$context['sub_template'] = 'em_list';
	}

	protected static function monthLabel(array $event)
	{
		global $txt;

		$d = (new DateTime('@' . $event['start_ts']))->setTimezone($event['tz']);
		$month = isset($txt['months'][(int) $d->format('n')]) ? $txt['months'][(int) $d->format('n')] : $d->format('F');

		return $month . ' ' . $d->format('Y');
	}

	/* ------------------------------------------------------------------
	 * Event page
	 * ---------------------------------------------------------------- */

	public static function view()
	{
		global $context, $txt, $scripturl, $user_info;

		$event = self::requireEvent();
		$panel = EM::panel($event);

		$context['em_event'] = $event;
		$context['em_panel'] = $panel;
		$context['em_can_manage'] = EM::canManage($event);
		$context['em_show_attendees'] = self::canSeeAttendees($event, $panel['mine']);
		$context['em_attendees'] = $context['em_show_attendees'] ? self::attendees($event) : array();
		$context['em_topic_url'] = $event['id_topic'] > 0 ? $scripturl . '?topic=' . $event['id_topic'] . '.0' : '';
		$context['em_calendar_url'] = $scripturl . '?action=calendar;year=' . substr($event['start_date'], 0, 4) . ';month=' . (int) substr($event['start_date'], 5, 2);
		$context['em_ics_url'] = $context['em_url'] . ';sa=ics;event=' . $event['id'];

		$context['page_title'] = strip_tags($event['title']);
		$context['linktree'][] = array('url' => $panel['url'], 'name' => $event['title']);
		$context['sub_template'] = 'em_view';
	}

	/**
	 * Members answer going / maybe / not going, or withdraw.
	 */
	public static function rsvp()
	{
		global $user_info, $txt;

		checkSession('request');
		is_not_guest();
		isAllowedTo('em_register');

		$event = self::requireEvent();
		$answer = isset($_REQUEST['answer']) ? (string) $_REQUEST['answer'] : 'going';

		if ($answer === 'withdraw')
		{
			$mine = EM::registration($event['id'], $user_info['id']);
			if ($mine && $mine['status'] === 'going' && !EM::canCancel($event))
				self::notice($txt['em_reason_cancel_deadline'], 'error');
			elseif (EM::remove($event, $user_info['id']))
				self::notice($txt['em_done_withdrawn']);
			else
				self::notice($txt['em_reason_busy'], 'error');
		}
		else
		{
			$before = EM::registration($event['id'], $user_info['id']);
			list ($status, $key) = EM::respond($event, $user_info['id'], $answer, isset($_REQUEST['guests']) ? (int) $_REQUEST['guests'] : 0, isset($_REQUEST['note']) ? (string) $_REQUEST['note'] : '');
			self::notice($txt[$key], $status === false ? 'error' : 'info');

			// Let the organiser know about new requests.
			if ($status === 'pending' && (!$before || $before['status'] !== 'pending') && $event['id_member'] > 0 && $event['id_member'] != $user_info['id'])
				EM::alert(array($event['id_member']), 'request', $event);
		}

		// Back to wherever the member clicked from.
		if (!empty($_REQUEST['from_topic']) && $event['id_topic'] > 0)
			redirectexit('topic=' . $event['id_topic'] . '.0');

		self::back($event);
	}

	/* ------------------------------------------------------------------
	 * Organiser pages
	 * ---------------------------------------------------------------- */

	protected static function parseDateTime($value, DateTimeZone $tz)
	{
		$value = trim((string) $value);
		if ($value === '' || !preg_match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$~', $value))
			return 0;

		try
		{
			return (new DateTime(str_replace('T', ' ', $value), $tz))->getTimestamp();
		}
		catch (\Exception $e)
		{
			return 0;
		}
	}

	public static function manage()
	{
		global $context, $txt, $smcFunc;

		$event = self::requireEvent();
		self::requireManager($event);

		if (isset($_POST['save']))
		{
			checkSession();

			$reminders = array();
			foreach (isset($_POST['reminders']) ? (array) $_POST['reminders'] : array() as $r)
				if (in_array((int) $r, EM::$reminder_options, true))
					$reminders[] = (int) $r;

			$settings = array(
				'enabled' => empty($_POST['enabled']) ? 0 : 1,
				'capacity' => max(0, min(100000, (int) ($_POST['capacity'] ?? 0))),
				'waitlist' => empty($_POST['waitlist']) ? 0 : 1,
				'waitlist_max' => max(0, min(100000, (int) ($_POST['waitlist_max'] ?? 0))),
				'approval' => empty($_POST['approval']) ? 0 : 1,
				'allow_maybe' => empty($_POST['allow_maybe']) ? 0 : 1,
				'max_guests' => max(0, min(10, (int) ($_POST['max_guests'] ?? 0))),
				'question' => $smcFunc['htmlspecialchars'](EM::truncate(trim(strip_tags((string) ($_POST['question'] ?? ''))), 200), ENT_QUOTES),
				'question_required' => empty($_POST['question_required']) ? 0 : 1,
				'visibility' => in_array($_POST['visibility'] ?? '', EM::$visibility, true) ? $_POST['visibility'] : 'members',
				'opens' => self::parseDateTime($_POST['opens'] ?? '', $event['tz']),
				'closes' => self::parseDateTime($_POST['closes'] ?? '', $event['tz']),
				'cancel_hours' => max(0, min(720, (int) ($_POST['cancel_hours'] ?? 0))),
				'reminders' => EM::cleanReminders(implode(',', $reminders)),
			);
			if ($settings['question'] === '')
				$settings['question_required'] = 0;
			if ($settings['closes'] > 0 && $settings['opens'] > 0 && $settings['closes'] <= $settings['opens'])
				$settings['closes'] = 0;

			$promoted = EM::saveSettings($event, $settings);
			if ($promoted === null)
				self::notice($txt['em_reason_busy'], 'error');
			else
				self::notice($txt['em_settings_saved'] . (!empty($promoted) ? ' ' . sprintf($txt['em_n_promoted'], count($promoted)) : ''));
			self::back($event, 'manage');
		}

		$context['em_event'] = $event;
		$context['em_counts'] = EM::counts($event['id']);
		$context['em_attendees'] = self::attendees($event);
		$context['em_local'] = function ($ts) use ($event)
		{
			return $ts > 0 ? (new DateTime('@' . $ts))->setTimezone($event['tz'])->format('Y-m-d\TH:i') : '';
		};
		$context['em_view_url'] = $context['em_url'] . ';sa=view;event=' . $event['id'];
		$context['page_title'] = sprintf($txt['em_manage_title'], strip_tags($event['title']));
		$context['linktree'][] = array('url' => $context['em_view_url'], 'name' => $event['title']);
		$context['linktree'][] = array('name' => $txt['em_manage']);
		$context['sub_template'] = 'em_manage';
	}

	/**
	 * Organiser actions on one registration.
	 */
	public static function attendee()
	{
		global $smcFunc, $txt;

		checkSession('get');
		$event = self::requireEvent();
		self::requireManager($event);

		$member = isset($_GET['u']) ? (int) $_GET['u'] : 0;
		$do = isset($_GET['do']) ? (string) $_GET['do'] : '';
		$current = EM::registration($event['id'], $member);

		if ($current)
			switch ($do)
			{
				case 'approve':
				case 'decline':
					$result = EM::decide($event, $member, $do === 'approve');
					if ($result === 'busy')
						self::notice($txt['em_reason_busy'], 'error');
					elseif ($result === false)
						self::notice($txt['em_reason_full'], 'error');
					break;

				case 'promote':
					$result = EM::forcePromote($event, $member);
					if ($result === 'busy')
						self::notice($txt['em_reason_busy'], 'error');
					elseif ($result !== true)
						self::notice($txt['em_promote_failed'], 'error');
					break;

				case 'remove':
					if (!EM::remove($event, $member))
						self::notice($txt['em_reason_busy'], 'error');
					break;

				case 'checkin':
				case 'uncheck':
					$smcFunc['db_query']('', '
						UPDATE {db_prefix}em_registrations
						SET checked_in = {int:c}
						WHERE id_event = {int:event} AND id_member = {int:member}',
						array('c' => $do === 'checkin' ? 1 : 0, 'event' => $event['id'], 'member' => $member)
					);
					if (!empty($_GET['js']))
					{
						while (ob_get_level() > 0)
							@ob_end_clean();
						header('Content-Type: application/json');
						echo json_encode(array('ok' => true, 'checked_in' => $do === 'checkin'));
						die;
					}
					break;
			}

		self::back($event, 'manage');
	}

	public static function add()
	{
		global $smcFunc, $txt;

		checkSession();
		$event = self::requireEvent();
		self::requireManager($event);

		$names = array_slice(array_filter(array_map('trim', preg_split('~[,\n]+~', (string) ($_POST['names'] ?? '')))), 0, 50);
		$status = ($_POST['status'] ?? 'going') === 'waitlist' ? 'waitlist' : 'going';
		$added = 0;
		$busy = 0;

		if (!empty($names))
		{
			$escaped = array();
			foreach ($names as $n)
				$escaped[] = $smcFunc['htmlspecialchars'](EM::lower($n), ENT_QUOTES);

			$request = $smcFunc['db_query']('', '
				SELECT id_member
				FROM {db_prefix}members
				WHERE (LOWER(real_name) IN ({array_string:names}) OR LOWER(member_name) IN ({array_string:names}))
					AND is_activated = {int:active}',
				array('names' => $escaped, 'active' => 1)
			);
			$ids = array();
			while ($row = $smcFunc['db_fetch_row']($request))
				$ids[] = (int) $row[0];
			$smcFunc['db_free_result']($request);

			foreach ($ids as $id)
			{
				if ($status === 'waitlist')
				{
					$ok = EM::locked($event['id'], function () use ($smcFunc, $event, $id)
					{
						// Check inside the lock, since another request may have added them.
						if (EM::registration($event['id'], $id))
							return false;
						$fresh = EM::load($event['id'], true);
						if (!$fresh || !$fresh['waitlist'])
							return false;
						$counts = EM::counts($event['id']);
						if ($fresh['waitlist_max'] > 0 && $counts['waitlist'] >= $fresh['waitlist_max'])
							return false;
						$smcFunc['db_insert']('ignore',
							'{db_prefix}em_registrations',
							array('id_event' => 'int', 'id_member' => 'int', 'status' => 'string-10', 'guests' => 'int', 'note' => 'string-255', 'registered' => 'int', 'updated' => 'int', 'checked_in' => 'int'),
							array($event['id'], $id, 'waitlist', 0, '', time(), time(), 0),
							array('id_event', 'id_member')
						);
						return true;
					}, false);
				}
				else
				{
					list ($result) = EM::respond($event, $id, 'going', 0, '', true);
					$ok = $result !== false;
				}

				if ($ok)
					$added++;
				else
					$busy++;
			}
		}

		if ($busy)
			self::notice(($added ? sprintf($txt['em_n_added'], $added) . ' ' : '') . sprintf($txt['em_n_busy'], $busy), 'error');
		else
			self::notice($added ? sprintf($txt['em_n_added'], $added) : $txt['em_none_found'], $added ? 'info' : 'error');
		self::back($event, 'manage');
	}

	/**
	 * Send a personal message to attendees.
	 */
	public static function message()
	{
		global $smcFunc, $sourcedir, $user_info, $txt;

		checkSession();
		$event = self::requireEvent();
		self::requireManager($event);

		$statuses = array_values(array_intersect(array('going', 'maybe', 'waitlist', 'pending'), (array) ($_POST['to'] ?? array())));
		$subject = trim((string) ($_POST['subject'] ?? ''));
		$body = trim((string) ($_POST['body'] ?? ''));

		$to = empty($statuses) ? array() : array_slice(array_diff(EM::members($event['id'], $statuses), array((int) $user_info['id'])), 0, 500);

		if ($subject === '' || $body === '' || empty($to))
		{
			self::notice($txt['em_message_missing'], 'error');
			self::back($event, 'manage');
		}

		require_once($sourcedir . '/Subs-Post.php');
		$subject = $smcFunc['htmlspecialchars'](EM::truncate($subject, 100), ENT_QUOTES);
		$body = $smcFunc['htmlspecialchars'](EM::truncate($body, 20000), ENT_QUOTES);
		preparsecode($body);

		sendpm(array('to' => array_values($to), 'bcc' => array()), $subject, $body, true, array('id' => $user_info['id'], 'name' => $user_info['name'], 'username' => $user_info['username']));

		self::notice(sprintf($txt['em_message_sent'], count($to)));
		self::back($event, 'manage');
	}

	public static function export()
	{
		global $txt;

		checkSession('get');
		$event = self::requireEvent();
		self::requireManager($event);

		$lines = array(array($txt['em_col_member'], $txt['em_col_status'], $txt['em_col_guests'], $txt['em_col_note'], $txt['em_col_registered'], $txt['em_col_checked_in']));
		foreach (self::attendees($event) as $status => $list)
			foreach ($list as $p)
				$lines[] = array(un_htmlspecialchars(strip_tags($p['name'])), $txt['em_status_' . $status], $p['guests'], un_htmlspecialchars($p['note']), gmdate('Y-m-d H:i', $p['registered_ts']) . ' UTC', $p['checked_in'] ? $txt['em_yes'] : '');

		$csv = "\xEF\xBB\xBF";
		foreach ($lines as $line)
			$csv .= implode(',', array_map(array('EM', 'csvCell'), $line)) . "\r\n";

		self::download($csv, 'text/csv', 'event-' . $event['id'] . '-attendees.csv');
	}

	public static function cancel()
	{
		global $txt;

		checkSession();
		$event = self::requireEvent();
		self::requireManager($event);

		$cancel = !empty($_POST['cancel']);
		if (EM::setCancelled($event, $cancel))
			self::notice($cancel ? $txt['em_event_cancelled'] : $txt['em_event_reinstated']);
		else
			self::notice($txt['em_reason_busy'], 'error');
		self::back($event, 'manage');
	}

	/* ------------------------------------------------------------------
	 * iCalendar
	 * ---------------------------------------------------------------- */

	protected static function download($content, $type, $name)
	{
		while (ob_get_level() > 0)
			@ob_end_clean();

		header('Content-Type: ' . $type . '; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $name . '"');
		header('X-Content-Type-Options: nosniff');
		echo $content;
		die;
	}

	public static function ics()
	{
		global $txt;

		$event = self::requireEvent();
		self::download(EM::ics(array($event), un_htmlspecialchars($event['title'])), 'text/calendar', 'event-' . $event['id'] . '.ics');
	}

	public static function feedUrl()
	{
		global $smcFunc, $user_info, $scripturl;

		if ($user_info['is_guest'] || !EM::setting('feeds', 1))
			return '';

		$request = $smcFunc['db_query']('', 'SELECT token FROM {db_prefix}em_feeds WHERE id_member = {int:me}', array('me' => $user_info['id']));
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		$token = empty($row) ? self::newToken($user_info['id']) : $row[0];

		return $scripturl . '?action=events;sa=feed;u=' . $user_info['id'] . ';token=' . $token;
	}

	protected static function newToken($id_member)
	{
		global $smcFunc;

		$token = bin2hex(random_bytes(16));
		$smcFunc['db_insert']('replace',
			'{db_prefix}em_feeds',
			array('id_member' => 'int', 'token' => 'string-32'),
			array((int) $id_member, $token),
			array('id_member')
		);

		return $token;
	}

	public static function token()
	{
		global $user_info, $txt;

		checkSession('get');
		is_not_guest();
		self::newToken($user_info['id']);
		self::notice($txt['em_feed_reset']);
		redirectexit('action=events');
	}

	/**
	 * Board access for a feed, worked out as the feed's owner.
	 *
	 * @return array|false array('query_see_board' => SQL) or false if the
	 *  member mustn't get a feed (or access can't be checked)
	 */
	public static function feedAccess($id_member)
	{
		global $smcFunc, $sourcedir;

		$request = $smcFunc['db_query']('', '
			SELECT id_group, additional_groups, id_post_group, is_activated
			FROM {db_prefix}members
			WHERE id_member = {int:m}',
			array('m' => (int) $id_member)
		);
		$member = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Gone, not activated, or banned (SMF adds 10 to is_activated for bans).
		if (!$member || (int) $member['is_activated'] !== 1)
			return false;

		$groups = array((int) $member['id_group'], (int) $member['id_post_group']);
		foreach (explode(',', (string) $member['additional_groups']) as $g)
			if (trim($g) !== '')
				$groups[] = (int) $g;
		$groups = array_values(array_unique($groups));

		// They must still be allowed to see the calendar.
		if (!in_array(1, $groups, true))
		{
			$allow = false;
			$request = $smcFunc['db_query']('', '
				SELECT add_deny
				FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:groups})
					AND permission = {string:perm}',
				array('groups' => $groups, 'perm' => 'calendar_view')
			);
			while ($row = $smcFunc['db_fetch_row']($request))
			{
				if ((int) $row[0] === 0)
				{
					$allow = false;
					break;
				}
				$allow = true;
			}
			$smcFunc['db_free_result']($request);

			if (!$allow)
				return false;
		}

		if (!function_exists('build_query_board') && is_file($sourcedir . '/Security.php'))
			require_once($sourcedir . '/Security.php');
		if (!function_exists('build_query_board'))
			return false;

		$see = build_query_board((int) $id_member);
		if (empty($see['query_see_board']) || !is_string($see['query_see_board']))
			return false;

		return $see;
	}

	/**
	 * Personal calendar feed: the member's events (going or maybe).
	 * Uses a secret token, because calendar apps can't log in.
	 */
	public static function feed()
	{
		global $smcFunc, $mbname, $txt;

		if (!EM::setting('feeds', 1))
			fatal_lang_error('em_error_not_found', false);

		$id_member = isset($_GET['u']) ? (int) $_GET['u'] : 0;
		$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

		$request = $smcFunc['db_query']('', 'SELECT token FROM {db_prefix}em_feeds WHERE id_member = {int:m}', array('m' => $id_member));
		$row = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		if (empty($row) || !hash_equals($row[0], $token))
		{
			header('HTTP/1.1 403 Forbidden');
			die('Forbidden');
		}

		// Check access as that member, and refuse rather than guess if we can't.
		$see = self::feedAccess($id_member);
		if ($see === false)
		{
			header('HTTP/1.1 403 Forbidden');
			die('Forbidden');
		}

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event
			FROM {db_prefix}em_registrations AS r
				INNER JOIN {db_prefix}calendar AS cal ON (cal.id_event = r.id_event)
				LEFT JOIN {db_prefix}boards AS b ON (b.id_board = cal.id_board)
			WHERE r.id_member = {int:m}
				AND r.status IN ({array_string:statuses})
				AND cal.end_date >= {date:since}
				AND (cal.id_board = {int:zero} OR ' . $see['query_see_board'] . ')
			ORDER BY cal.start_date
			LIMIT 300',
			array('m' => $id_member, 'statuses' => array('going', 'maybe'), 'since' => date('Y-m-d', time() - 30 * 86400), 'zero' => 0)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$events = array();
		foreach ($ids as $id)
			if ($e = EM::load($id))
				$events[] = $e;

		while (ob_get_level() > 0)
			@ob_end_clean();
		header('Content-Type: text/calendar; charset=UTF-8');
		header('X-Content-Type-Options: nosniff');
		echo EM::ics($events, sprintf($txt['em_feed_name'], un_htmlspecialchars($mbname)));
		die;
	}

	/* ------------------------------------------------------------------
	 * Admin
	 * ---------------------------------------------------------------- */

	public static function admin()
	{
		global $context, $txt, $sourcedir, $scripturl;

		isAllowedTo('admin_forum');
		require_once($sourcedir . '/ManageServer.php');
		loadLanguage('EventManager');

		$visibility = array();
		foreach (EM::$visibility as $v)
			$visibility[$v] = $txt['em_visibility_' . $v];

		$config_vars = array(
			array('check', 'em_enabled', 'subtext' => $txt['em_enabled_desc']),
			array('check', 'em_show_menu'),
			array('check', 'em_topic_panel', 'subtext' => $txt['em_topic_panel_desc']),
			array('check', 'em_feeds', 'subtext' => $txt['em_feeds_desc']),
			'',
			array('title', 'em_defaults_title'),
			array('check', 'em_default_enabled', 'subtext' => $txt['em_default_enabled_desc']),
			array('int', 'em_default_capacity', 'min' => 0, 'subtext' => $txt['em_default_capacity_desc']),
			array('check', 'em_default_waitlist'),
			array('check', 'em_default_maybe'),
			array('select', 'em_default_visibility', $visibility),
			array('text', 'em_default_reminders', 'subtext' => $txt['em_default_reminders_desc']),
			'',
			array('title', 'em_notify_title'),
			array('check', 'em_alerts'),
			array('check', 'em_reminder_email'),
			array('check', 'em_reminder_maybe'),
		);

		$context['page_title'] = $txt['em_title'];
		$context['post_url'] = $scripturl . '?action=admin;area=eventmanager;save';
		$context['settings_title'] = $txt['em_title'];
		$context['settings_message'] = empty($GLOBALS['modSettings']['cal_enabled']) ? '<div class="errorbox">' . $txt['em_calendar_off'] . '</div>' : $txt['em_admin_desc'];

		if (isset($_GET['save']))
		{
			checkSession();
			$_POST['em_default_capacity'] = max(0, (int) ($_POST['em_default_capacity'] ?? 0));
			$_POST['em_default_reminders'] = EM::cleanReminders($_POST['em_default_reminders'] ?? '');
			saveDBSettings($config_vars);
			$_SESSION['adm-save'] = true;
			redirectexit('action=admin;area=eventmanager');
		}

		prepareDBSettingContext($config_vars);
		$context['sub_template'] = 'show_settings';
	}
}
