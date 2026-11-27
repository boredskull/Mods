<?php
if (!defined('SMF')) die('SMF only');
global $smcFunc, $modSettings, $sourcedir;
db_extend('packages');
function BSM_install_column($name, $type = 'int', $size = null, $default = 0)
{
    $column = array('name' => $name, 'type' => $type, 'null' => false, 'default' => $default);
    if ($size !== null) $column['size'] = $size;
    if ($type === 'int' || $type === 'smallint' || $type === 'mediumint') $column['unsigned'] = true;
    return $column;
}
$scope = BSM_install_column('scope', 'varchar', 8, '');
$id = BSM_install_column('scope_id');
$primary = function ($columns) { return array('type' => 'primary', 'columns' => $columns); };
$smcFunc['db_create_table']('{db_prefix}bsm_rules', array($scope, $id, BSM_install_column('minutes', 'smallint'), BSM_install_column('updated_by', 'mediumint'), BSM_install_column('updated_at')), array($primary(array('scope', 'scope_id'))), array(), 'ignore');
$smcFunc['db_create_table']('{db_prefix}bsm_slots', array(BSM_install_column('subject_key', 'varchar', 80, ''), $scope, $id, BSM_install_column('next_allowed'), BSM_install_column('pending_until'), BSM_install_column('claim_token', 'varchar', 32, ''), BSM_install_column('touched_at')), array($primary(array('subject_key', 'scope', 'scope_id')), array('type' => 'index', 'name' => 'idx_touched', 'columns' => array('touched_at'))), array(), 'ignore');
$history_id = BSM_install_column('id');
unset($history_id['default']);
$history_id['auto'] = true;
$smcFunc['db_create_table']('{db_prefix}bsm_history', array($history_id, $scope, $id, BSM_install_column('old_minutes', 'smallint'), BSM_install_column('new_minutes', 'smallint'), BSM_install_column('id_member', 'mediumint'), BSM_install_column('changed_at')), array($primary(array('id')), array('type' => 'index', 'name' => 'idx_changed', 'columns' => array('changed_at'))), array(), 'ignore');
$initial = array();
if (!isset($modSettings['bsm_enabled'])) $initial['bsm_enabled'] = 1;
if (!isset($modSettings['bsm_exempt_mod'])) $initial['bsm_exempt_mod'] = 1;
if (empty($modSettings['bsm_guest_secret'])) $initial['bsm_guest_secret'] = bin2hex(random_bytes(32));
if ($initial) updateSettings($initial);
