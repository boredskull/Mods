<?php
if (!defined('SMF')) die('No direct access');
global $smcFunc, $modSettings, $db_type;

// This modification requires MySQL/MariaDB with InnoDB (transactional) tables.
if (isset($db_type) && $db_type !== 'mysql')
	fatal_error('Anonymous Posting requires MySQL or MariaDB with InnoDB tables.', false);

$smcFunc['db_query']('', 'CREATE TABLE IF NOT EXISTS {db_prefix}bsap_authors (id_msg int unsigned NOT NULL PRIMARY KEY, id_member mediumint unsigned NOT NULL, id_board smallint unsigned NOT NULL, created_at int unsigned NOT NULL, KEY member_idx (id_member)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', array());
$smcFunc['db_query']('', 'CREATE TABLE IF NOT EXISTS {db_prefix}bsap_reveals (id_reveal int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, id_msg int unsigned NOT NULL, id_author mediumint unsigned NOT NULL, id_viewer mediumint unsigned NOT NULL, id_board smallint unsigned NOT NULL, viewed_at int unsigned NOT NULL, KEY message_idx (id_msg), KEY viewer_idx (id_viewer)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', array());

$updates = array();
foreach (array('bsap_enabled' => 0, 'bsap_boards' => '', 'bsap_force_boards' => '') as $k => $v)
	if (!isset($modSettings[$k]))
		$updates[$k] = $v;
if ($updates)
	updateSettings($updates);
