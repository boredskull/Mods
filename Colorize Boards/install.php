<?php
/**
 * Colorize Boards - install: switch the mod on by default.
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

global $modSettings;

if (!isset($modSettings['colorize_boards_enable']))
	updateSettings(array('colorize_boards_enable' => 1));
