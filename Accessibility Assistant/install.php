<?php
/** Accessibility Assistant idempotent settings installation. ISC. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Installation must run through the SMF Package Manager.');

$defaults = array('baa_enabled' => 1, 'baa_composer' => 1, 'baa_skip' => 1, 'baa_live' => 1, 'baa_quick' => 1, 'baa_reading' => 1, 'baa_diagnostics' => 1);
$install = array();
foreach ($defaults as $name => $value)
    if (!isset($modSettings[$name]))
        $install[$name] = $value;
if ($install)
    updateSettings($install);

// Remove the obsolete main-menu integration during upgrades from 1.0.0.
if (function_exists('remove_integration_function'))
    remove_integration_function('integrate_menu_buttons', 'BoredAccessibilityAssistant::hookMenu');
