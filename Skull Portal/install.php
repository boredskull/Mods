<?php
/** Skull Portal 1.0.0 installer. Executed by the SMF package manager. */
if (!defined('SMF')) die('No direct access');
global $smcFunc, $modSettings;
$smcFunc['db_query']('', 'CREATE TABLE IF NOT EXISTS {db_prefix}skullportal_blocks (
 id_block INT UNSIGNED NOT NULL AUTO_INCREMENT,
 title VARCHAR(150) NOT NULL,
 block_type VARCHAR(60) NOT NULL DEFAULT \'bbc\',
 content MEDIUMTEXT NOT NULL,
 position VARCHAR(12) NOT NULL DEFAULT \'centre\',
 display_on VARCHAR(12) NOT NULL DEFAULT \'both\',
 sort_order INT NOT NULL DEFAULT 0,
 visibility VARCHAR(12) NOT NULL DEFAULT \'everyone\',
 enabled TINYINT NOT NULL DEFAULT 1,
 show_title TINYINT NOT NULL DEFAULT 1,
 style_settings TEXT NULL,
 PRIMARY KEY (id_block), KEY position_order (position, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', array());
// Never overwrite homepage or enabled settings during an upgrade.
if (function_exists('updateSettings'))
{
    $defaults = array();
    if (!isset($modSettings['skullportal_enabled'])) $defaults['skullportal_enabled'] = '1';
    if (!isset($modSettings['skullportal_homepage'])) $defaults['skullportal_homepage'] = '0';
    if ($defaults) updateSettings($defaults);
}

// Upgrade from Skull Portal 1.0.x: preserve all existing blocks.
$columns = $smcFunc['db_query']('', 'SHOW COLUMNS FROM {db_prefix}skullportal_blocks LIKE {string:column}', array('column' => 'display_on'));
$exists = $smcFunc['db_num_rows']($columns) > 0;
$smcFunc['db_free_result']($columns);
if (!$exists)
    $smcFunc['db_query']('', 'ALTER TABLE {db_prefix}skullportal_blocks ADD display_on VARCHAR(12) NOT NULL DEFAULT \'both\' AFTER position', array());

// Existing installs retain their blocks; add the styling field only if missing.
$styleColumns = $smcFunc['db_query']('', 'SHOW COLUMNS FROM {db_prefix}skullportal_blocks LIKE {string:column}', array('column' => 'style_settings'));
$styleExists = $smcFunc['db_num_rows']($styleColumns) > 0;
$smcFunc['db_free_result']($styleColumns);
if (!$styleExists)
    $smcFunc['db_query']('', 'ALTER TABLE {db_prefix}skullportal_blocks ADD style_settings TEXT NULL', array());
