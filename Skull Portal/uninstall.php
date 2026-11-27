<?php
/** Only remove package settings; retain blocks against accidental data loss. */
if (!defined('SMF')) die('No direct access');
global $modSettings;
if (function_exists('updateSettings')) updateSettings(array(
    'skullportal_enabled' => '0',
    'skullportal_homepage' => '0',
    'integrate_default_action' => ($modSettings['integrate_default_action'] ?? '') === 'skullportal_main' ? ($modSettings['skullportal_previous_default_action'] ?? '') : ($modSettings['integrate_default_action'] ?? ''),
));
// Deliberately preserve skullportal_blocks on uninstall for safe reinstalls.
