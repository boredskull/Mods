<?php

/**
 * Event Manager for SMF 2.1
 *
 * Adds registration to SMF's own calendar events: RSVP (going, maybe,
 * not going), seat limits with plus-ones, waiting lists with automatic
 * promotion, organiser approval, registration windows, attendee lists,
 * check-in, messaging, CSV export, iCalendar files and feeds, and
 * automatic reminders.
 *
 * Events are still created and edited with SMF's calendar. This mod
 * stores only the registration settings and registrations.
 *
 * @package EventManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.2.1
 */

if (!defined('SMF'))
	die('No direct access...');

class EM
{
	const VERSION = '1.2.1';

	public static $statuses = array('going', 'maybe', 'no', 'waitlist', 'pending');
	public static $visibility = array('public', 'members', 'attendees', 'organisers');
	public static $reminder_options = array(10080, 1440, 180, 60, 15);

	protected static $events = array();

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	public static function enabled()
	{
		global $modSettings;

		return !empty($modSettings['em_enabled']) && !empty($modSettings['cal_enabled']);
	}

	public static function setting($key, $default = 0)
	{
		global $modSettings;

		return isset($modSettings['em_' . $key]) ? $modSettings['em_' . $key] : $default;
	}

	/**
	 * Registration settings for events that haven't been set up yet.
	 */
	public static function defaults()
	{
		return array(
			'enabled' => (int) self::setting('default_enabled', 1),
			'capacity' => (int) self::setting('default_capacity', 0),
			'waitlist' => (int) self::setting('default_waitlist', 1),
			'waitlist_max' => 0,
			'approval' => 0,
			'allow_maybe' => (int) self::setting('default_maybe', 1),
			'max_guests' => 0,
			'question' => '',
			'question_required' => 0,
			'visibility' => in_array(self::setting('default_visibility', 'members'), self::$visibility, true) ? self::setting('default_visibility', 'members') : 'members',
			'opens' => 0,
			'closes' => 0,
			'cancel_hours' => 0,
			'reminders' => self::cleanReminders(self::setting('default_reminders', '1440,60')),
			'cancelled' => 0,
			'original_title' => '',
		);
	}

	/**
	 * UTF-8-safe text operations, including hosts without the optional
	 * mbstring extension.
	 */
	public static function truncate($value, $length)
	{
		$value = (string) $value;
		$length = max(0, (int) $length);
		if (function_exists('mb_substr'))
			return mb_substr($value, 0, $length, 'UTF-8');
		if (function_exists('iconv_substr'))
		{
			$cut = iconv_substr($value, 0, $length, 'UTF-8');
			if ($cut !== false)
				return $cut;
		}
		$chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
		return is_array($chars) ? implode('', array_slice($chars, 0, $length)) : substr($value, 0, $length);
	}

	public static function lower($value)
	{
		$value = (string) $value;
		if (function_exists('mb_strtolower'))
			return mb_strtolower($value, 'UTF-8');
		return function_exists('smf_strtolower') ? smf_strtolower($value) : strtolower($value);
	}

	public static function cleanReminders($value)
	{
		$out = array();
		foreach (preg_split('~[\s,]+~', (string) $value) as $v)
		{
			$v = (int) $v;
			if ($v >= 5 && $v <= 43200)
				$out[$v] = $v;
		}
		rsort($out);

		return implode(',', array_slice(array_values($out), 0, 5));
	}

	/* ------------------------------------------------------------------
	 * Loading events
	 * ---------------------------------------------------------------- */

	/**
	 * An SMF calendar event plus its registration settings.
	 */
	public static function load($id_event, $fresh = false)
	{
		global $smcFunc;

		$id_event = (int) $id_event;
		if ($id_event <= 0)
			return null;
		if (!$fresh && array_key_exists($id_event, self::$events))
			return self::$events[$id_event];

		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event, cal.title, cal.start_date, cal.end_date, cal.start_time, cal.end_time, cal.timezone, cal.location,
				cal.id_board, cal.id_topic, cal.id_member, COALESCE(mem.real_name, {string:empty}) AS organiser,
				e.id_event AS has_settings, e.enabled, e.capacity, e.waitlist, e.waitlist_max, e.approval, e.allow_maybe, e.max_guests,
				e.question, e.question_required, e.visibility, e.opens, e.closes, e.cancel_hours, e.reminders, e.cancelled, e.original_title
			FROM {db_prefix}calendar AS cal
				LEFT JOIN {db_prefix}em_events AS e ON (e.id_event = cal.id_event)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)
			WHERE cal.id_event = {int:id}',
			array('id' => $id_event, 'empty' => '')
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return self::$events[$id_event] = $row ? self::normalise($row) : null;
	}

	public static function normalise(array $row)
	{
		$event = array(
			'id' => (int) $row['id_event'],
			'title' => $row['title'],
			'start_date' => $row['start_date'],
			'end_date' => $row['end_date'],
			'start_time' => isset($row['start_time']) ? (string) $row['start_time'] : '',
			'end_time' => isset($row['end_time']) ? (string) $row['end_time'] : '',
			'timezone' => isset($row['timezone']) ? (string) $row['timezone'] : '',
			'location' => isset($row['location']) ? (string) $row['location'] : '',
			'id_board' => (int) $row['id_board'],
			'id_topic' => (int) $row['id_topic'],
			'id_member' => (int) $row['id_member'],
			'organiser' => isset($row['organiser']) ? $row['organiser'] : '',
		);

		$settings = self::defaults();
		if (!empty($row['has_settings']))
			foreach ($settings as $key => $default)
				if (isset($row[$key]))
					$settings[$key] = is_int($default) ? (int) $row[$key] : (string) $row[$key];

		$event += $settings;
		$event['has_settings'] = !empty($row['has_settings']);
		$event['all_day'] = $event['start_time'] === '';
		$event['tz'] = self::timezone($event['timezone']);
		$event['start_ts'] = self::timestamp($event['start_date'], $event['start_time'] !== '' ? $event['start_time'] : '00:00:00', $event['tz']);
		$event['end_ts'] = $event['end_time'] !== ''
			? self::timestamp($event['end_date'], $event['end_time'], $event['tz'])
			: self::timestamp($event['end_date'], '23:59:59', $event['tz']);
		$event['closes_ts'] = $event['closes'] > 0 ? $event['closes'] : $event['start_ts'];

		return $event;
	}

	public static function timezone($tz)
	{
		global $modSettings;

		foreach (array($tz, isset($modSettings['default_timezone']) ? $modSettings['default_timezone'] : '', 'UTC') as $candidate)
		{
			if ($candidate === '' || $candidate === null)
				continue;
			try
			{
				return new DateTimeZone($candidate);
			}
			catch (\Exception $e)
			{
			}
		}

		return new DateTimeZone('UTC');
	}

	public static function timestamp($date, $time, DateTimeZone $tz)
	{
		try
		{
			return (new DateTime($date . ' ' . $time, $tz))->getTimestamp();
		}
		catch (\Exception $e)
		{
			return 0;
		}
	}

	public static function forget($id_event)
	{
		unset(self::$events[(int) $id_event]);
	}

	/**
	 * Can the current member see this event? (Calendar permission and,
	 * for events linked to a board, access to that board.)
	 */
	public static function canSee($event)
	{
		global $smcFunc;

		if (empty($event) || !allowedTo('calendar_view'))
			return false;
		if ($event['id_board'] <= 0)
			return true;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_board = {int:board}
				AND {query_see_board}',
			array('board' => $event['id_board'])
		);
		$ok = $smcFunc['db_num_rows']($request) > 0;
		$smcFunc['db_free_result']($request);

		return $ok;
	}

	public static function canManage($event)
	{
		global $user_info;

		if (empty($event) || $user_info['is_guest'])
			return false;

		return allowedTo('em_manage_any') || allowedTo('calendar_edit_any')
			|| ((int) $event['id_member'] === (int) $user_info['id'] && allowedTo('calendar_edit_own'));
	}

	/* ------------------------------------------------------------------
	 * Locking
	 *
	 * Seat counting and saving must happen one request at a time per
	 * event, or two people could both take the last place. SMF 2.1 runs on
	 * MySQL/MariaDB or PostgreSQL, which both have named (advisory) locks
	 * that are released automatically if a request dies.
	 * ---------------------------------------------------------------- */

	protected static $locks = array();

	/**
	 * @return bool false if the lock couldn't be had in time
	 */
	public static function lock($id_event)
	{
		global $smcFunc, $db_type;

		$id_event = (int) $id_event;
		if (!empty(self::$locks[$id_event]))
		{
			self::$locks[$id_event]++;
			return true;
		}

		$type = strtolower((string) $db_type);
		$ok = false;

		if ($type === 'postgresql')
		{
			$request = $smcFunc['db_query']('', '
				SELECT pg_try_advisory_lock({int:space}, {int:id})',
				array('space' => 0x454d, 'id' => $id_event)
			);
			list ($got) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			$ok = $got === true || $got === 't' || $got === '1' || $got === 1;

			// Wait a little if someone else is registering right now.
			for ($i = 0; !$ok && $i < 50; $i++)
			{
				usleep(100000);
				$request = $smcFunc['db_query']('', 'SELECT pg_try_advisory_lock({int:space}, {int:id})', array('space' => 0x454d, 'id' => $id_event));
				list ($got) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				$ok = $got === true || $got === 't' || $got === '1' || $got === 1;
			}
		}
		elseif ($type === 'mysql' || $type === 'mysqli')
		{
			$request = $smcFunc['db_query']('', '
				SELECT GET_LOCK({string:name}, {int:wait})',
				array('name' => self::lockName($id_event), 'wait' => 5)
			);
			list ($got) = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			$ok = (int) $got === 1;
		}
		// Unsupported database engines must fail closed: capacity is not safe without a lock.

		if ($ok)
			self::$locks[$id_event] = 1;

		return $ok;
	}

	public static function unlock($id_event)
	{
		global $smcFunc, $db_type;

		$id_event = (int) $id_event;
		if (empty(self::$locks[$id_event]))
			return;
		if (--self::$locks[$id_event] > 0)
			return;
		unset(self::$locks[$id_event]);

		$type = strtolower((string) $db_type);
		if ($type === 'postgresql')
			$smcFunc['db_query']('', 'SELECT pg_advisory_unlock({int:space}, {int:id})', array('space' => 0x454d, 'id' => $id_event));
		elseif ($type === 'mysql' || $type === 'mysqli')
			$smcFunc['db_query']('', 'SELECT RELEASE_LOCK({string:name})', array('name' => self::lockName($id_event)));
	}

	protected static function lockName($id_event)
	{
		global $db_name, $db_prefix;

		// Unique per forum, in case several forums share a database server.
		return 'em_' . substr(md5((isset($db_name) ? $db_name : '') . '|' . (isset($db_prefix) ? $db_prefix : '')), 0, 12) . '_' . (int) $id_event;
	}

	/**
	 * Run a callback while holding the event's lock.
	 */
	public static function locked($id_event, callable $fn, $busy = null)
	{
		if (!self::lock($id_event))
			return $busy;

		try
		{
			return $fn();
		}
		finally
		{
			self::unlock($id_event);
		}
	}

	/* ------------------------------------------------------------------
	 * Registrations
	 * ---------------------------------------------------------------- */

	public static function registration($id_event, $id_member)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT status, guests, note, registered, updated, checked_in
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
				AND id_member = {int:member}',
			array('event' => (int) $id_event, 'member' => (int) $id_member)
		);
		$row = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		return $row ?: null;
	}

	/**
	 * @return array going, maybe, no, waitlist, pending (people) and seats
	 */
	public static function counts($id_event)
	{
		global $smcFunc;

		$out = array_fill_keys(self::$statuses, 0) + array('seats' => 0, 'checked_in' => 0);
		$request = $smcFunc['db_query']('', '
			SELECT status, COUNT(*) AS n, SUM(guests) AS g, SUM(checked_in) AS c
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
			GROUP BY status',
			array('event' => (int) $id_event)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (isset($out[$row['status']]))
				$out[$row['status']] = (int) $row['n'];
			if ($row['status'] === 'going')
			{
				$out['seats'] = (int) $row['n'] + (int) $row['g'];
				$out['checked_in'] = (int) $row['c'];
			}
		}
		$smcFunc['db_free_result']($request);

		return $out;
	}

	public static function seatsLeft(array $event, $counts = null)
	{
		if ($event['capacity'] <= 0)
			return PHP_INT_MAX;

		$counts = $counts ?: self::counts($event['id']);

		return max(0, $event['capacity'] - $counts['seats']);
	}

	/**
	 * Is registration open now?
	 *
	 * @return true|string true, or a reason key
	 */
	public static function registrationOpen(array $event)
	{
		$now = time();

		if (!$event['enabled'])
			return 'em_reason_disabled';
		if ($event['cancelled'])
			return 'em_reason_cancelled';
		if ($event['opens'] > 0 && $now < $event['opens'])
			return 'em_reason_not_open';
		if ($now >= $event['closes_ts'])
			return 'em_reason_closed';

		return true;
	}

	public static function canCancel(array $event)
	{
		if ($event['cancel_hours'] <= 0)
			return time() < $event['start_ts'];

		return time() < $event['start_ts'] - $event['cancel_hours'] * 3600;
	}

	/**
	 * Register (or change the answer of) a member.
	 *
	 * @param string $answer going, maybe or no
	 * @return array array(status or false, message key)
	 */
	public static function respond(array $event, $id_member, $answer, $guests = 0, $note = '', $force = false)
	{
		return self::locked($event['id'], function () use ($event, $id_member, $answer, $guests, $note, $force)
		{
			// Recheck calendar and registration settings after acquiring the lock.
			// An organiser may have cancelled or changed capacity in the meantime.
			$fresh = EM::load($event['id'], true);
			return $fresh ? EM::respondLocked($fresh, $id_member, $answer, $guests, $note, $force)
				: array(false, 'em_error_not_found');
		}, array(false, 'em_reason_busy'));
	}

	/**
	 * respond(), with the event already locked.
	 */
	public static function respondLocked(array $event, $id_member, $answer, $guests = 0, $note = '', $force = false)
	{
		global $smcFunc;

		$id_member = (int) $id_member;
		$answer = in_array($answer, array('going', 'maybe', 'no'), true) ? $answer : 'going';
		$guests = max(0, min((int) $event['max_guests'], (int) $guests));
		$note = self::truncate(trim(strip_tags((string) $note)), 250);

		if (!$force)
		{
			$open = self::registrationOpen($event);
			if ($open !== true)
				return array(false, $open);
			if ($answer === 'maybe' && !$event['allow_maybe'])
				return array(false, 'em_reason_no_maybe');
			if ($answer !== 'no' && $event['question'] !== '' && $event['question_required'] && $note === '')
				return array(false, 'em_reason_answer_required');
		}

		$current = self::registration($event['id'], $id_member);
		$was_going = $current && $current['status'] === 'going';

		// Leaving a seat: only before the cancellation deadline.
		if (!$force && $was_going && $answer !== 'going' && !self::canCancel($event))
			return array(false, 'em_reason_cancel_deadline');

		if ($answer === 'going')
		{
			$needed = 1 + $guests;
			$counts = self::counts($event['id']);
			$held = $was_going ? 1 + (int) $current['guests'] : 0;
			$left = $event['capacity'] > 0 ? $event['capacity'] - $counts['seats'] + $held : PHP_INT_MAX;

			if ($current && in_array($current['status'], array('waitlist', 'pending'), true) && !$force)
				$status = $current['status'];
			elseif ($event['approval'] && !$force && !$was_going)
				$status = 'pending';
			elseif ($needed <= $left)
				$status = 'going';
			elseif ($was_going)
				return array(false, 'em_reason_no_room_guests');
			elseif ($event['waitlist'] && ($event['waitlist_max'] <= 0 || $counts['waitlist'] < $event['waitlist_max']))
				$status = 'waitlist';
			else
				return array(false, 'em_reason_full');
		}
		else
			$status = $answer;

		$now = time();
		$smcFunc['db_insert']('replace',
			'{db_prefix}em_registrations',
			array('id_event' => 'int', 'id_member' => 'int', 'status' => 'string-10', 'guests' => 'int', 'note' => 'string-255', 'registered' => 'int', 'updated' => 'int', 'checked_in' => 'int'),
			array($event['id'], $id_member, $status, $guests, $smcFunc['htmlspecialchars']($note, ENT_QUOTES),
				// Keep the original time so waiting list order stays fair.
				$current && $current['status'] === $status ? (int) $current['registered'] : $now, $now, $current ? (int) $current['checked_in'] : 0),
			array('id_event', 'id_member')
		);

		if (in_array($status, array('going', 'maybe'), true))
			self::skipPastReminders($event, array($id_member));

		// Places freed: leaving, or bringing fewer guests.
		if ($was_going && ($status !== 'going' || $guests < (int) $current['guests']))
			self::promote($event);

		return array($status, 'em_done_' . $status);
	}

	/**
	 * Remove a registration completely.
	 */
	/**
	 * @return bool false if the event was busy and nothing was removed
	 */
	public static function remove(array $event, $id_member)
	{
		return self::locked($event['id'], function () use ($event, $id_member)
		{
			EM::removeLocked($event, $id_member);
			return true;
		}, false);
	}

	public static function removeLocked(array $event, $id_member)
	{
		global $smcFunc;

		$current = self::registration($event['id'], $id_member);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
				AND id_member = {int:member}',
			array('event' => $event['id'], 'member' => (int) $id_member)
		);
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}em_reminders
			WHERE id_event = {int:event}
				AND id_member = {int:member}',
			array('event' => $event['id'], 'member' => (int) $id_member)
		);

		if ($current && $current['status'] === 'going')
			self::promote($event);
	}

	/**
	 * Move people from the waiting list into free seats, first come first served.
	 *
	 * @return array Promoted member IDs
	 */
	/**
	 * @return array|null Promoted member IDs, or null if the event was busy
	 *  (the scheduled task catches up within 15 minutes)
	 */
	public static function promote(array $event)
	{
		return self::locked($event['id'], function () use ($event)
		{
			return EM::promoteLocked($event);
		}, null);
	}

	public static function promoteLocked(array $event)
	{
		global $smcFunc;

		if ($event['cancelled'] || time() >= $event['start_ts'])
			return array();

		$promoted = array();
		$left = self::seatsLeft($event);

		$request = $smcFunc['db_query']('', '
			SELECT id_member, guests
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
				AND status = {string:waitlist}
			ORDER BY registered, id_member',
			array('event' => $event['id'], 'waitlist' => 'waitlist')
		);
		$waiting = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$waiting[] = $row;
		$smcFunc['db_free_result']($request);

		foreach ($waiting as $row)
		{
			$needed = 1 + (int) $row['guests'];
			if ($needed > $left)
				continue;

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}em_registrations
				SET status = {string:going}, updated = {int:now}
				WHERE id_event = {int:event}
					AND id_member = {int:member}',
				array('going' => 'going', 'now' => time(), 'event' => $event['id'], 'member' => (int) $row['id_member'])
			);
			$left -= $needed;
			$promoted[] = (int) $row['id_member'];
		}

		if (!empty($promoted))
		{
			self::skipPastReminders($event, $promoted);
			self::alert($promoted, 'promoted', $event);
		}

		return $promoted;
	}

	/**
	 * Organiser decision on a pending registration.
	 */
	public static function decide(array $event, $id_member, $approve)
	{
		return self::locked($event['id'], function () use ($event, $id_member, $approve)
		{
			return EM::decideLocked($event, $id_member, $approve);
		}, 'busy');
	}

	public static function decideLocked(array $event, $id_member, $approve)
	{
		global $smcFunc;

		$current = self::registration($event['id'], $id_member);
		if (!$current || $current['status'] !== 'pending')
			return false;

		if (!$approve)
		{
			self::removeLocked($event, $id_member);
			self::alert(array($id_member), 'declined', $event);
			return 'declined';
		}

		$counts = self::counts($event['id']);
		$has_room = 1 + (int) $current['guests'] <= self::seatsLeft($event, $counts);
		$can_wait = $event['waitlist'] && ($event['waitlist_max'] <= 0 || $counts['waitlist'] < $event['waitlist_max']);
		$status = $has_room ? 'going' : ($can_wait ? 'waitlist' : false);
		if ($status === false)
			return false;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}em_registrations
			SET status = {string:status}, updated = {int:now}
			WHERE id_event = {int:event}
				AND id_member = {int:member}',
			array('status' => $status, 'now' => time(), 'event' => $event['id'], 'member' => (int) $id_member)
		);

		if ($status === 'going')
			self::skipPastReminders($event, array($id_member));
		self::alert(array($id_member), $status === 'going' ? 'approved' : 'waitlisted', $event);

		return $status;
	}

	/**
	 * Organiser lets someone in from the waiting list (or maybe), even if
	 * that goes over the limit.
	 *
	 * @return bool|string true, false if they can't be promoted, or 'busy'
	 */
	public static function forcePromote(array $event, $id_member)
	{
		global $smcFunc;

		$done = self::locked($event['id'], function () use ($smcFunc, $event, $id_member)
		{
			$current = EM::registration($event['id'], $id_member);
			if (!$current || !in_array($current['status'], array('waitlist', 'maybe', 'pending'), true))
				return false;

			$smcFunc['db_query']('', '
				UPDATE {db_prefix}em_registrations
				SET status = {string:going}, updated = {int:now}
				WHERE id_event = {int:event}
					AND id_member = {int:member}',
				array('going' => 'going', 'now' => time(), 'event' => $event['id'], 'member' => (int) $id_member)
			);

			$after = EM::registration($event['id'], $id_member);
			return $after && $after['status'] === 'going';
		}, 'busy');

		if ($done === true)
		{
			self::skipPastReminders($event, array($id_member));
			self::alert(array($id_member), 'promoted', $event);
		}

		return $done;
	}

	/* ------------------------------------------------------------------
	 * Saving settings and cancelling
	 * ---------------------------------------------------------------- */

	/**
	 * Serialize organiser changes with RSVPs and waiting-list promotions.
	 * Returning null means the lock was busy and no settings were saved.
	 */
	public static function saveSettings(array $event, array $s)
	{
		return self::locked($event['id'], function () use ($event, $s)
		{
			$fresh = EM::load($event['id'], true);
			return $fresh ? EM::saveSettingsLocked($fresh, $s) : array();
		}, null);
	}

	/** Called only while the event lock is held. */
	protected static function saveSettingsLocked(array $event, array $s)
	{
		global $smcFunc;

		$smcFunc['db_insert']('replace',
			'{db_prefix}em_events',
			array(
				'id_event' => 'int', 'enabled' => 'int', 'capacity' => 'int', 'waitlist' => 'int', 'waitlist_max' => 'int', 'approval' => 'int',
				'allow_maybe' => 'int', 'max_guests' => 'int', 'question' => 'string-255', 'question_required' => 'int', 'visibility' => 'string-12',
				'opens' => 'int', 'closes' => 'int', 'cancel_hours' => 'int', 'reminders' => 'string-60', 'cancelled' => 'int', 'original_title' => 'string-255',
			),
			array(
				$event['id'], $s['enabled'], $s['capacity'], $s['waitlist'], $s['waitlist_max'], $s['approval'], $s['allow_maybe'], $s['max_guests'],
				$s['question'], $s['question_required'], $s['visibility'], $s['opens'], $s['closes'], $s['cancel_hours'], $s['reminders'],
				$event['cancelled'], $event['original_title'],
			),
			array('id_event')
		);

		self::forget($event['id']);
		$fresh = self::load($event['id'], true);
		return $fresh ? self::promoteLocked($fresh) : array();
	}

	/**
	 * Cancel (or reinstate) an event, under the same lock as registration.
	 * Calendar titles still get the normal "Cancelled:" prefix.
	 * @return bool false when the event was busy or vanished
	 */
	public static function setCancelled(array $event, $cancelled)
	{
		return self::locked($event['id'], function () use ($event, $cancelled)
		{
			$fresh = EM::load($event['id'], true);
			return $fresh ? EM::setCancelledLocked($fresh, $cancelled) : false;
		}, false);
	}

	/** Called only while the event lock is held. */
	protected static function setCancelledLocked(array $event, $cancelled)
	{
		global $smcFunc, $txt;

		loadLanguage('EventManager');

		if (!$event['has_settings'])
		{
			self::saveSettingsLocked($event, $event);
			$event = self::load($event['id'], true);
			if (!$event)
				return false;
		}

		if ($cancelled && !$event['cancelled'])
		{
			$original = $event['title'];
			$new_title = self::truncate(sprintf($txt['em_cancelled_title'], $original), 255);
		}
		elseif (!$cancelled && $event['cancelled'])
		{
			$original = '';
			$new_title = $event['original_title'] !== '' ? $event['original_title'] : $event['title'];
		}
		else
			return true;

		$smcFunc['db_query']('', '
			UPDATE {db_prefix}em_events
			SET cancelled = {int:c}, original_title = {string:o}
			WHERE id_event = {int:id}',
			array('c' => $cancelled ? 1 : 0, 'o' => $original, 'id' => $event['id'])
		);
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}calendar
			SET title = {string:t}
			WHERE id_event = {int:id}',
			array('t' => $new_title, 'id' => $event['id'])
		);
		updateSettings(array('calendar_updated' => time()));

		self::forget($event['id']);
		$members = self::members($event['id'], array('going', 'maybe', 'waitlist', 'pending'));
		self::alert($members, $cancelled ? 'cancelled' : 'reinstated', self::load($event['id'], true));
		return true;
	}

	public static function members($id_event, array $statuses)
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
				AND status IN ({array_string:statuses})',
			array('event' => (int) $id_event, 'statuses' => $statuses)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		return $ids;
	}

	/* ------------------------------------------------------------------
	 * Alerts and reminders
	 * ---------------------------------------------------------------- */

	/**
	 * Actions: promoted, approved, declined, waitlisted, cancelled, reinstated, reminder
	 */
	public static function alert(array $to, $action, $event, $extra_data = array())
	{
		global $smcFunc, $scripturl, $user_info;

		$to = array_values(array_unique(array_filter(array_map('intval', $to))));
		if (empty($to) || empty($event) || !self::setting('alerts', 1))
			return;

		$extra = json_encode(array(
			'event_title' => $event['title'],
			'event_link' => $scripturl . '?action=events;sa=view;event=' . $event['id'],
		) + $extra_data);

		$actor = $action === 'reminder' ? 0 : (int) $user_info['id'];
		$rows = array();
		foreach ($to as $id)
			$rows[] = array(time(), $id, $actor, $actor ? (string) $user_info['name'] : '', 'member', $actor, 'em_' . $action, 0, $extra);

		$smcFunc['db_insert']('insert',
			'{db_prefix}user_alerts',
			array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int', 'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int', 'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
			$rows,
			array('id_alert')
		);

		updateMemberData($to, array('alerts' => '+'));
	}

	/**
	 * Mark reminders whose time has already passed as done for these
	 * members, so someone who signs up late doesn't get the "1 week" and
	 * "1 day" reminders an hour before the event.
	 */
	public static function skipPastReminders(array $event, array $members)
	{
		global $smcFunc;

		$members = array_values(array_unique(array_filter(array_map('intval', $members))));
		if (empty($members) || $event['reminders'] === '')
			return;

		$now = time();
		$rows = array();
		foreach (array_map('intval', explode(',', $event['reminders'])) as $offset)
			if ($offset > 0 && $event['start_ts'] - $offset * 60 <= $now)
				foreach ($members as $id)
					$rows[] = array($event['id'], $id, $offset, $now);

		if (!empty($rows))
			$smcFunc['db_insert']('ignore',
				'{db_prefix}em_reminders',
				array('id_event' => 'int', 'id_member' => 'int', 'offset_min' => 'int', 'sent' => 'int'),
				$rows,
				array('id_event', 'id_member', 'offset_min')
			);
	}

	/**
	 * Scheduled task (every 15 minutes): send due reminders.
	 */
	public static function scheduledTask()
	{
		global $smcFunc, $sourcedir, $txt, $scripturl, $modSettings;

		if (!self::enabled())
			return true;

		loadLanguage('EventManager');

		$now = time();
		$events = array();
		$request = $smcFunc['db_query']('', '
			SELECT cal.id_event
			FROM {db_prefix}calendar AS cal
			WHERE cal.start_date >= {date:from}
				AND cal.start_date <= {date:to}',
			array('from' => gmdate('Y-m-d', $now - 86400), 'to' => gmdate('Y-m-d', $now + 31 * 86400))
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$events[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$statuses = self::setting('reminder_maybe', 0) ? array('going', 'maybe') : array('going');
		$sent = 0;

		foreach ($events as $id_event)
		{
			$event = self::load($id_event, true);
			if (!$event || !$event['enabled'] || $event['cancelled'] || $event['start_ts'] <= $now)
				continue;

			// Places that opened while the event was too busy to promote anyone.
			if ($event['waitlist'])
			{
				$counts = self::counts($id_event);
				if ($counts['waitlist'] > 0 && self::seatsLeft($event, $counts) > 0)
					self::promote($event);
			}

			if ($event['reminders'] === '')
				continue;

			// Only the latest reminder that is due gets sent. Earlier ones that
			// were missed (late sign-up, event moved, task not running) are
			// marked done instead of all arriving at once.
			$due_offsets = array();
			foreach (array_map('intval', explode(',', $event['reminders'])) as $offset)
				if ($offset > 0 && $event['start_ts'] - $offset * 60 <= $now)
					$due_offsets[] = $offset;
			if (empty($due_offsets))
				continue;
			sort($due_offsets);
			$send_offset = $due_offsets[0];
			$skip = array_slice($due_offsets, 1);

			foreach (array_merge($skip, array($send_offset)) as $offset)
			{
				$silent = $offset !== $send_offset;

				// Who hasn't had this reminder yet?
				$due = array();
				$request = $smcFunc['db_query']('', '
					SELECT r.id_member, mem.email_address, mem.real_name
					FROM {db_prefix}em_registrations AS r
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = r.id_member)
						LEFT JOIN {db_prefix}em_reminders AS s ON (s.id_event = r.id_event AND s.id_member = r.id_member AND s.offset_min = {int:offset})
					WHERE r.id_event = {int:event}
						AND r.status IN ({array_string:statuses})
						AND s.id_event IS NULL',
					array('event' => $id_event, 'offset' => $offset, 'statuses' => $statuses)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$due[(int) $row['id_member']] = $row;
				$smcFunc['db_free_result']($request);

				if (empty($due))
					continue;

				// Record first, so a slow mail server can't cause duplicates.
				$rows = array();
				foreach (array_keys($due) as $id)
					$rows[] = array($id_event, $id, $offset, $now);
				$smcFunc['db_insert']('ignore',
					'{db_prefix}em_reminders',
					array('id_event' => 'int', 'id_member' => 'int', 'offset_min' => 'int', 'sent' => 'int'),
					$rows,
					array('id_event', 'id_member', 'offset_min')
				);

				if ($silent)
					continue;

				$when = self::relative($event['start_ts'] - $now);
				self::alert(array_keys($due), 'reminder', $event, array('event_when' => $when));

				if (self::setting('reminder_email', 1))
				{
					require_once($sourcedir . '/Subs-Post.php');
					$url = $scripturl . '?action=events;sa=view;event=' . $id_event;
					$title = un_htmlspecialchars($event['title']);
					foreach ($due as $person)
						if (!empty($person['email_address']))
							sendmail(
								$person['email_address'],
								sprintf($txt['em_mail_subject'], $title, $when),
								sprintf($txt['em_mail_body'], un_htmlspecialchars($person['real_name']), $title, self::describeTime($event, true), un_htmlspecialchars($event['location']), $url, $modSettings['mbname'] ?? ''),
								null,
								'em_' . $id_event . '_' . $offset,
								false,
								2
							);
				}

				$sent += count($due);
			}
		}

		// Tidy up old reminder records and registrations for deleted events.
		$smcFunc['db_query']('', 'DELETE FROM {db_prefix}em_reminders WHERE sent < {int:old}', array('old' => $now - 90 * 86400));
		self::pruneOrphans();

		return true;
	}

	public static function pruneOrphans()
	{
		global $smcFunc;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT r.id_event
			FROM {db_prefix}em_registrations AS r
				LEFT JOIN {db_prefix}calendar AS cal ON (cal.id_event = r.id_event)
			WHERE cal.id_event IS NULL',
			array()
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$request = $smcFunc['db_query']('', '
			SELECT e.id_event
			FROM {db_prefix}em_events AS e
				LEFT JOIN {db_prefix}calendar AS cal ON (cal.id_event = e.id_event)
			WHERE cal.id_event IS NULL',
			array()
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		$ids = array_values(array_unique($ids));
		if (!empty($ids))
			foreach (array('em_registrations', 'em_events', 'em_reminders') as $table)
				$smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_event IN ({array_int:ids})', array('ids' => $ids));

		return count($ids);
	}

	/* ------------------------------------------------------------------
	 * Formatting
	 * ---------------------------------------------------------------- */

	public static function relative($seconds)
	{
		global $txt;

		if ($seconds < 3600)
			return sprintf($txt['em_in_minutes'], max(1, (int) round($seconds / 60)));
		if ($seconds < 86400)
			return sprintf($txt['em_in_hours'], (int) round($seconds / 3600));

		return sprintf($txt['em_in_days'], (int) round($seconds / 86400));
	}

	/**
	 * "Saturday 14 March 2026, 19:00 – 22:00 (Europe/London)"
	 */
	public static function describeTime(array $event, $plain = false)
	{
		global $txt;

		$fmt = function ($ts, $with_day, DateTimeZone $zone)
		{
			global $txt;
			$d = (new DateTime('@' . $ts))->setTimezone($zone);
			$day = isset($txt['days'][(int) $d->format('w')]) ? $txt['days'][(int) $d->format('w')] : $d->format('l');
			$month = isset($txt['months'][(int) $d->format('n')]) ? $txt['months'][(int) $d->format('n')] : $d->format('F');
			return ($with_day ? $day . ' ' : '') . $d->format('j') . ' ' . $month . ' ' . $d->format('Y');
		};

		$tz = $event['tz'];
		$start = (new DateTime('@' . $event['start_ts']))->setTimezone($tz);
		$end = (new DateTime('@' . $event['end_ts']))->setTimezone($tz);
		$same_day = $start->format('Y-m-d') === $end->format('Y-m-d');

		$out = $fmt($event['start_ts'], true, $tz);
		if ($event['all_day'])
			$out .= $same_day ? '' : ' – ' . $fmt($event['end_ts'], true, $tz);
		else
		{
			$out .= ', ' . $start->format('H:i') . ' – ' . ($same_day ? '' : $fmt($event['end_ts'], true, $tz) . ', ') . $end->format('H:i');
			$out .= ' (' . $tz->getName() . ')';
		}

		return $out;
	}

	/* ------------------------------------------------------------------
	 * iCalendar
	 * ---------------------------------------------------------------- */

	public static function icsEscape($text)
	{
		$text = un_htmlspecialchars(strip_tags((string) $text));

		return strtr($text, array('\\' => '\\\\', ';' => '\\;', ',' => '\\,', "\r\n" => '\\n', "\n" => '\\n', "\r" => ''));
	}

	/**
	 * Fold lines at 75 octets, as RFC 5545 requires.
	 */
	public static function icsFold($line)
	{
		$out = '';
		while (strlen($line) > 75)
		{
			$cut = 75;
			// Don't split a multi-byte character.
			while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80)
				$cut--;
			$out .= substr($line, 0, $cut) . "\r\n ";
			$line = substr($line, $cut);
		}

		return $out . $line . "\r\n";
	}

	public static function ics(array $events, $name)
	{
		global $scripturl, $boardurl;

		$host = parse_url($boardurl, PHP_URL_HOST) ?: 'smf';
		$lines = array('BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//BoredSkull//SMF Event Manager ' . self::VERSION . '//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . self::icsEscape($name));

		foreach ($events as $e)
		{
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:event-' . $e['id'] . '@' . $host;
			$lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
			if ($e['all_day'])
			{
				$lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $e['start_date']);
				// All-day dates have no time zone: add the day in UTC so a server
				// ahead of UTC doesn't end a one-day event on the day it starts.
				$lines[] = 'DTEND;VALUE=DATE:' . (new DateTime($e['end_date'], new DateTimeZone('UTC')))->modify('+1 day')->format('Ymd');
			}
			else
			{
				$lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $e['start_ts']);
				$lines[] = 'DTEND:' . gmdate('Ymd\THis\Z', max($e['end_ts'], $e['start_ts'] + 60));
			}
			$lines[] = 'SUMMARY:' . self::icsEscape($e['title']);
			if ($e['location'] !== '')
				$lines[] = 'LOCATION:' . self::icsEscape($e['location']);
			$lines[] = 'URL:' . $scripturl . '?action=events;sa=view;event=' . $e['id'];
			if ($e['cancelled'])
				$lines[] = 'STATUS:CANCELLED';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';

		$out = '';
		foreach ($lines as $line)
			$out .= self::icsFold($line);

		return $out;
	}

	public static function csvCell($value)
	{
		$value = (string) $value;
		if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false)
			$value = "'" . $value;

		return '"' . str_replace('"', '""', $value) . '"';
	}

	/* ------------------------------------------------------------------
	 * Hooks
	 * ---------------------------------------------------------------- */

	public static function actions(&$actionArray)
	{
		$actionArray['events'] = array('EventManager-Pages.php', 'EM_Pages::action');
	}

	public static function menuButtons(&$buttons)
	{
		global $txt, $scripturl;

		if (!self::enabled() || !self::setting('show_menu', 1))
			return;

		loadLanguage('EventManager');

		$button = array('events' => array(
			'title' => $txt['em_menu'],
			'href' => $scripturl . '?action=events',
			'show' => allowedTo('calendar_view'),
			'icon' => 'calendar',
		));

		// Next to the calendar button when there is one.
		$keys = array_keys($buttons);
		$after = in_array('calendar', $keys, true) ? 'calendar' : 'home';
		$pos = array_search($after, $keys, true);
		$buttons = $pos === false ? $button + $buttons : array_slice($buttons, 0, $pos + 1, true) + $button + array_slice($buttons, $pos + 1, null, true);
	}

	/**
	 * integrate_load_theme: RSVP panel above topics that have events.
	 */
	public static function loadTheme()
	{
		global $topic, $context, $smcFunc;

		if (!self::enabled())
			return;

		loadCSSFile('eventmanager.css', array('minimize' => true, 'seed' => self::VERSION), 'smf_eventmanager');

		if (empty($topic) || !self::setting('topic_panel', 1) || isset($_REQUEST['xml']) || !empty($_REQUEST['action']))
			return;

		$ids = array();
		$request = $smcFunc['db_query']('', '
			SELECT id_event
			FROM {db_prefix}calendar
			WHERE id_topic = {int:topic}
			ORDER BY start_date
			LIMIT 3',
			array('topic' => (int) $topic)
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$ids[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		if (empty($ids))
			return;

		$panels = array();
		foreach ($ids as $id)
		{
			$event = self::load($id);
			if ($event && $event['enabled'] && self::canSee($event))
				$panels[] = self::panel($event);
		}

		if (empty($panels))
			return;

		loadLanguage('EventManager');
		loadTemplate('EventManager');
		loadJavaScriptFile('eventmanager.js', array('minimize' => true, 'defer' => true, 'seed' => self::VERSION), 'smf_eventmanager');

		$context['em_panels'] = $panels;
		$context['em_notice'] = isset($_SESSION['em_notice']) ? $_SESSION['em_notice'] : null;
		unset($_SESSION['em_notice']);
		$context['template_layers'][] = 'em_topic';
	}

	/**
	 * Data for the RSVP box (used on the event page and in topics).
	 */
	public static function panel(array $event)
	{
		global $user_info, $context, $scripturl;

		$counts = self::counts($event['id']);
		$mine = $user_info['is_guest'] ? null : self::registration($event['id'], $user_info['id']);
		$open = self::registrationOpen($event);

		$position = 0;
		if ($mine && $mine['status'] === 'waitlist')
			$position = self::waitlistPosition($event['id'], $user_info['id']);

		return array(
			'event' => $event,
			'counts' => $counts,
			'mine' => $mine,
			'open' => $open,
			'position' => $position,
			'can_register' => !$user_info['is_guest'] && allowedTo('em_register'),
			'can_cancel' => self::canCancel($event),
			'seats_left' => $event['capacity'] > 0 ? self::seatsLeft($event, $counts) : null,
			'when' => self::describeTime($event),
			'url' => $scripturl . '?action=events;sa=view;event=' . $event['id'],
			'rsvp_url' => $scripturl . '?action=events;sa=rsvp;event=' . $event['id'],
			'session' => ';' . $context['session_var'] . '=' . $context['session_id'],
		);
	}

	public static function waitlistPosition($id_event, $id_member)
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}em_registrations
			WHERE id_event = {int:event}
				AND status = {string:waitlist}
			ORDER BY registered, id_member',
			array('event' => (int) $id_event, 'waitlist' => 'waitlist')
		);
		$pos = 0;
		$found = 0;
		while ($row = $smcFunc['db_fetch_row']($request))
		{
			$pos++;
			if ((int) $row[0] === (int) $id_member)
				$found = $pos;
		}
		$smcFunc['db_free_result']($request);

		return $found;
	}

	public static function deleteMembers($users)
	{
		global $smcFunc;

		$users = array_filter(array_map('intval', (array) $users));
		if (empty($users))
			return;

		$events = array();
		$request = $smcFunc['db_query']('', '
			SELECT DISTINCT id_event
			FROM {db_prefix}em_registrations
			WHERE id_member IN ({array_int:u})
				AND status = {string:going}',
			array('u' => $users, 'going' => 'going')
		);
		while ($row = $smcFunc['db_fetch_row']($request))
			$events[] = (int) $row[0];
		$smcFunc['db_free_result']($request);

		foreach (array('em_registrations', 'em_reminders', 'em_feeds') as $table)
			$smcFunc['db_query']('', 'DELETE FROM {db_prefix}' . $table . ' WHERE id_member IN ({array_int:u})', array('u' => $users));

		foreach ($events as $id)
			if ($event = self::load($id, true))
				self::promote($event);
	}

	public static function fetchAlerts(&$alerts, &$formats = array())
	{
		loadLanguage('EventManager');
	}

	public static function loadPermissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups = array(), &$hiddenPermissions = array(), &$relabelPermissions = array())
	{
		loadLanguage('EventManager');
		$permissionList['membergroup']['em_register'] = array(false, 'calendar');
		$permissionList['membergroup']['em_manage_any'] = array(false, 'calendar');
	}

	public static function illegalGuestPermissions()
	{
		global $context;

		$context['non_guest_permissions'][] = 'em_register';
		$context['non_guest_permissions'][] = 'em_manage_any';
	}

	public static function adminAreas(&$admin_areas)
	{
		global $txt;

		loadLanguage('EventManager');

		$admin_areas['config']['areas']['eventmanager'] = array(
			'label' => $txt['em_title'],
			'file' => 'EventManager-Pages.php',
			'function' => 'EM_Pages::admin',
			'icon' => 'calendar',
			'permission' => array('admin_forum'),
		);
	}

	public static function credits()
	{
		global $context, $txt;

		loadLanguage('EventManager');
		$context['copyrights']['mods'][] = sprintf($txt['em_credits'], self::VERSION);
	}
}
