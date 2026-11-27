<?php

/**
 * Rich Link Previews: discard pending fetch jobs during uninstallation.
 * The cache and user settings are retained unless data removal is selected.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('No direct access...');

global $smcFunc;

$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}background_tasks
	WHERE task_class = {string:class}',
	array('class' => 'RichLinks_Fetch_Background')
);
