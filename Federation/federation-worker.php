<?php
/** CLI-only delivery worker. Example: php /path/to/forum/federation-worker.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base = __DIR__;
if (!is_file($base . '/SSI.php')) { fwrite(STDERR, "SMF SSI.php missing\n"); exit(1); }
require_once($base . '/SSI.php');
if (!defined('SMF')) exit(1);
global $sourcedir, $modSettings;
if (empty($modSettings['federation_enabled'])) exit(0);
require_once($sourcedir . '/Federation-Lib.php');
require_once($sourcedir . '/Federation-Protocol.php');
echo 'Processed ' . federation_process_queue(25) . " deliveries\n";
// Keep the flood-protection table bounded.
global $smcFunc;
$smcFunc['db_query']('', 'DELETE FROM {db_prefix}federation_rate WHERE window_start < {int:cutoff}',
    array('cutoff' => time() - 86400));
