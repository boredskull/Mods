<?php
/** Accessibility Assistant settings cleanup. No user content is stored. ISC. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Uninstallation must run through the SMF Package Manager.');

global $smcFunc;
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}settings WHERE variable IN ({array_string:names})', array(
    'names' => array('baa_enabled', 'baa_composer', 'baa_skip', 'baa_live', 'baa_quick', 'baa_reading', 'baa_diagnostics'),
));
