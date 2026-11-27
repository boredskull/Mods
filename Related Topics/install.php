<?php

/**
 * Related Topics - install: adds default settings without touching existing ones.
 *
 * @license ISC (see LICENSE)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $modSettings, $sourcedir;

require_once($sourcedir . '/RelatedTopics.php');

$new_settings = array();
foreach (rtopics_defaults() as $key => $value)
{
	if (!isset($modSettings[$key]))
		$new_settings[$key] = $value;
}

if (!empty($new_settings))
	updateSettings($new_settings);
