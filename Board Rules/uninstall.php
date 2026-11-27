<?php
/**
 * Board Rules - data removal (runs only when "remove data" is ticked on uninstall)
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot uninstall - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

global $smcFunc;

db_extend('packages');

foreach (array('boardrules_categories', 'boardrules_rules', 'boardrules_log', 'boardrules_accept') as $table)
	$smcFunc['db_drop_table']('{db_prefix}' . $table);

// Members holding unread rule alerts need their counters fixed after we remove them.
$members = array();
$request = $smcFunc['db_query']('', '
	SELECT DISTINCT id_member
	FROM {db_prefix}user_alerts
	WHERE content_type = {string:type}
		AND is_read = {int:unread}',
	array('type' => 'boardrules', 'unread' => 0)
);
while ($row = $smcFunc['db_fetch_assoc']($request))
	$members[] = (int) $row['id_member'];
$smcFunc['db_free_result']($request);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts
	WHERE content_type = {string:type}',
	array('type' => 'boardrules')
);

foreach (array_chunk($members, 500) as $chunk)
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET alerts = (
			SELECT COUNT(*)
			FROM {db_prefix}user_alerts AS ua
			WHERE ua.id_member = {db_prefix}members.id_member
				AND ua.is_read = {int:unread}
		)
		WHERE id_member IN ({array_int:members})',
		array('unread' => 0, 'members' => $chunk)
	);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}user_alerts_prefs
	WHERE alert_pref = {string:pref}',
	array('pref' => 'boardrules_updated')
);

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}settings
	WHERE variable LIKE {string:prefix}',
	array('prefix' => 'boardrules\_%')
);

cache_put_data('modSettings', null, 90);

if (SMF == 'SSI')
	echo 'Board Rules data removed.';
