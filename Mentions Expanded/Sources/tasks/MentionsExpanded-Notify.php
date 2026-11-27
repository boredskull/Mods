<?php
/**
 * Mentions Expanded - background notification task.
 *
 * Checks the current post/board state and records each processed recipient to
 * prevent duplicate notifications across retries, edits and overlapping groups.
 *
 * @package MentionsExpanded
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.1.1
 */

if (!defined('SMF'))
	die('No direct access...');

class MentionsExpanded_Notify extends SMF_BackgroundTask
{
	public function execute()
	{
		global $smcFunc, $sourcedir, $modSettings, $scripturl, $language, $mbname, $txt;

		if (empty($modSettings['mentions_expanded_enabled']) || empty($modSettings['enable_mentions']))
			return true;

		$d = $this->_details;
		$msg = !empty($d['msg']) ? (int) $d['msg'] : 0;
		$poster = !empty($d['poster_id']) ? (int) $d['poster_id'] : 0;
		$last_id = !empty($d['last_id']) ? (int) $d['last_id'] : 0;
		$batch = max(50, min(2000, (int) (isset($modSettings['mentions_expanded_batch']) ? $modSettings['mentions_expanded_batch'] : 500)));

		if (!$msg || !$poster)
			return true;

		// Core's edit hook runs BEFORE the edited body is saved. Never send a
		// notification until we've verified what actually exists in the database.
		$request = $smcFunc['db_query']('', '
			SELECT m.id_board, m.id_topic, m.body, m.subject, m.approved,
				t.approved AS topic_approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE m.id_msg = {int:msg}
			LIMIT 1',
			array('msg' => $msg)
		);
		$post = $smcFunc['db_fetch_assoc']($request);
		$smcFunc['db_free_result']($request);

		// Deleted, unapproved or moved to an inaccessible context? Do not notify.
		if (empty($post) || empty($post['approved']) || empty($post['topic_approved']))
			return true;

		$board = (int) $post['id_board'];
		if (!$board)
			return true;

		require_once($sourcedir . '/MentionsExpanded.php');
		$body = mex_clean_body($post['body']);
		$everyone = !empty($d['everyone']) && !empty($modSettings['mentions_expanded_everyone']) && mex_has_token($body, 'everyone');
		$here = array();
		if (!empty($d['here']) && !empty($modSettings['mentions_expanded_here']) && mex_has_token($body, 'here'))
			$here = array_values(array_unique(array_filter(array_map('intval', (array) $d['here']))));

		$groups = array();
		if (!empty($d['groups']) && !empty($modSettings['mentions_expanded_groups_enabled']))
		{
			$available = mex_mentionable_groups();
			foreach ((array) $d['groups'] as $id)
			{
				$id = (int) $id;
				if (isset($available[$id]) && mex_has_token($body, $available[$id]))
					$groups[$id] = $id;
			}
		}

		if (!$everyone && empty($here) && empty($groups))
			return true;

		require_once($sourcedir . '/Subs-Notify.php');

		$params = array(
			'msg' => $msg,
			'board' => $board,
			'last' => $last_id,
			'poster' => $poster,
			'active' => 1,
			'batch' => $batch,
			'admin' => 1,
			'regular' => 0,
			'allow' => 0,
			'deny' => 1,
			'type' => 'msg',
		);

		$targets = array();
		if ($everyone)
			$targets[] = '1=1';
		if (!empty($groups))
		{
			$params['t_groups'] = array_values($groups);
			$targets[] = 'mem.id_group IN ({array_int:t_groups})';
			foreach ($params['t_groups'] as $i => $group)
			{
				$params['tg_' . $i] = $group;
				$targets[] = 'FIND_IN_SET({int:tg_' . $i . '}, mem.additional_groups) != 0';
			}
		}
		if (!empty($here))
		{
			$params['t_here'] = $here;
			$targets[] = 'mem.id_member IN ({array_int:t_here})';
		}

		// SMF 2.1's board_permissions_view is the authoritative table for board visibility.
		// Group 0 ("Regular Members") only applies to members with no primary group,
		// exactly as SMF builds $user_info['groups'].
		$groupMatch = '(bpv.id_group = mem.id_group
			OR (bpv.id_group > {int:regular} AND (bpv.id_group = mem.id_post_group
				OR FIND_IN_SET(bpv.id_group, mem.additional_groups) != 0)))';
		$visibility = 'EXISTS (
			SELECT 1 FROM {db_prefix}board_permissions_view AS bpv
			WHERE bpv.id_board = {int:board} AND bpv.deny = {int:allow} AND ' . $groupMatch . ')';
		if (!empty($modSettings['deny_boards_access']))
			$visibility .= ' AND NOT EXISTS (
				SELECT 1 FROM {db_prefix}board_permissions_view AS bpv
				WHERE bpv.id_board = {int:board} AND bpv.deny = {int:deny} AND ' . $groupMatch . ')';
		$visibility = '(mem.id_group = {int:admin} OR FIND_IN_SET({int:admin}, mem.additional_groups) != 0 OR (' . $visibility . '))';

		// Core mentions have their own notification task. Skip those recipients
		// regardless of which job is scheduled first so we don't duplicate emails.
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.email_address, mem.lngfile
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}mentions AS core ON (
					core.content_type = {string:type} AND core.content_id = {int:msg}
					AND core.id_mentioned = mem.id_member)
			WHERE mem.id_member > {int:last}
				AND mem.id_member != {int:poster}
				AND mem.is_activated = {int:active}
				AND core.id_mentioned IS NULL
				AND (' . implode(' OR ', $targets) . ')
				AND ' . $visibility . '
				AND FIND_IN_SET({int:poster}, mem.pm_ignore_list) = 0
			ORDER BY mem.id_member
			LIMIT {int:batch}',
			$params
		);

		$members = array();
		$max_id = $last_id;
		$fetched = 0;
		while ($member = $smcFunc['db_fetch_assoc']($request))
		{
			++$fetched;
			$max_id = max($max_id, (int) $member['id_member']);
			$members[(int) $member['id_member']] = $member;
		}
		$smcFunc['db_free_result']($request);

		$email = array();
		if (!empty($members))
		{
			$prefs = getNotifyPrefs(array_keys($members), 'msg_mention', true);
			$extra = $smcFunc['json_encode'](array(
				'content_subject' => $post['subject'],
				'content_link' => $scripturl . '?msg=' . $msg,
			));
			$alert_rows = array();
			$alert_ids = array();

			// Recipient claims and alert counter updates belong to one transaction.
			// A retry must not send every alert in the batch again.
			$smcFunc['db_transaction']('begin');
			try
			{
				$already = array();
				$request = $smcFunc['db_query']('', '
					SELECT id_member FROM {db_prefix}user_alerts
					WHERE content_type = {string:type} AND content_id = {int:msg}
						AND content_action = {string:action} AND id_member IN ({array_int:members})',
					array('type' => 'msg', 'msg' => $msg, 'action' => 'mention', 'members' => array_keys($members))
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
					$already[(int) $row['id_member']] = true;
				$smcFunc['db_free_result']($request);

				foreach ($members as $id => $member)
				{
					if (isset($already[$id]))
						continue;

					$smcFunc['db_insert']('ignore',
						'{db_prefix}mentions_expanded_recipients',
						array('id_msg' => 'int', 'id_member' => 'int', 'processed_time' => 'int'),
						array($msg, $id, time()),
						array('id_msg', 'id_member')
					);
					if (!$smcFunc['db_affected_rows']())
						continue;

					$pref = isset($prefs[$id]['msg_mention']) ? (int) $prefs[$id]['msg_mention'] : 1;
					if ($pref & 0x01)
					{
						$alert_ids[] = $id;
						$alert_rows[] = array(time(), $id, $poster,
							isset($d['poster_name']) ? $d['poster_name'] : '', 'msg', $msg, 'mention', 0, $extra);
					}

					if (($pref & 0x02) && !empty($modSettings['mentions_expanded_email']) && !empty($member['email_address']))
					{
						$lang = empty($member['lngfile']) || empty($modSettings['userLanguage']) ? $language : $member['lngfile'];
						$email[$lang][] = $member['email_address'];
					}
				}

				if (!empty($alert_rows))
				{
					$smcFunc['db_insert']('',
						'{db_prefix}user_alerts',
						array('alert_time' => 'int', 'id_member' => 'int', 'id_member_started' => 'int',
							'member_name' => 'string', 'content_type' => 'string', 'content_id' => 'int',
							'content_action' => 'string', 'is_read' => 'int', 'extra' => 'string'),
						$alert_rows,
						array('id_alert')
					);
					updateMemberData($alert_ids, array('alerts' => '+'));
				}
				$smcFunc['db_transaction']('commit');
			}
			catch (Throwable $error)
			{
				$smcFunc['db_transaction']('rollback');
				throw $error;
			}
		}

		// Email delivery happens after the DB transaction. A crashed mail process
		// could lose an email, but claiming recipients prevents email floods on retry.
		if (!empty($email))
		{
			require_once($sourcedir . '/Subs-Post.php');
			foreach ($email as $lang => $addresses)
			{
				loadLanguage('MentionsExpanded', $lang, false, true);
				$subject = sprintf($txt['mentions_expanded_email_subject'], un_htmlspecialchars($post['subject']));
				$body = sprintf($txt['mentions_expanded_email_body'],
					un_htmlspecialchars(isset($d['poster_name']) ? $d['poster_name'] : ''),
					un_htmlspecialchars($post['subject']),
					$scripturl . '?msg=' . $msg,
					un_htmlspecialchars($mbname));
				foreach ($addresses as $address)
					sendmail($address, $subject, $body, null, 'mex_' . $msg, false, 3);
			}
		}

		if ($fetched >= $batch)
		{
			$d['last_id'] = $max_id;
			$smcFunc['db_insert']('insert',
				'{db_prefix}background_tasks',
				array('task_file' => 'string', 'task_class' => 'string', 'task_data' => 'string', 'claimed_time' => 'int'),
				array('$sourcedir/tasks/MentionsExpanded-Notify.php', 'MentionsExpanded_Notify', $smcFunc['json_encode']($d), 0),
				array('id_task')
			);
		}
		return true;
	}
}
