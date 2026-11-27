<?php
/**
 * Enhanced Reactions 1.0.10 installer and non-destructive upgrader. ISC.
 * Uses SMF's database API, so it works on MySQL/MariaDB and PostgreSQL.
 */
if (!defined('SMF')) die('No direct access');
global $smcFunc, $db_prefix, $modSettings;

db_extend('packages');

// Identify fresh installs before creating tables or migrating legacy data.
$prefix = trim(preg_replace('~^.*\.~', '', $db_prefix), '`');
$existingTables = array();
foreach ((array) $smcFunc['db_list_tables'](false, $prefix . 'bs_reaction%') as $t)
    $existingTables[strtolower(trim(preg_replace('~^.*\.~', '', $t), '`'))] = true;
$hadItems = isset($existingTables[strtolower($prefix . 'bs_reaction_items')]);
$hadLegacy = isset($existingTables[strtolower($prefix . 'bs_reaction_types')]);

$col = function ($name, $type, $extra = array()) { return array('name' => $name, 'type' => $type, 'null' => false) + $extra; };
$tables = array(
    'bs_reaction_items' => array(
        array($col('content_type', 'varchar', array('size' => 24, 'default' => '')), $col('content_id', 'int', array('unsigned' => true, 'default' => 0)),
            $col('id_member', 'int', array('unsigned' => true, 'default' => 0)), $col('reaction', 'varchar', array('size' => 24, 'default' => '')),
            $col('changed_at', 'int', array('unsigned' => true, 'default' => 0))),
        array(array('type' => 'primary', 'columns' => array('content_type', 'content_id', 'id_member')),
            array('name' => 'member_changed', 'type' => 'index', 'columns' => array('id_member', 'changed_at'))),
    ),
    'bs_reaction_events' => array(
        array($col('id_event', 'bigint', array('unsigned' => true, 'auto' => true)), $col('content_type', 'varchar', array('size' => 24, 'default' => '')),
            $col('content_id', 'int', array('unsigned' => true, 'default' => 0)), $col('id_member', 'int', array('unsigned' => true, 'default' => 0)),
            $col('old_reaction', 'varchar', array('size' => 24, 'default' => '')), $col('new_reaction', 'varchar', array('size' => 24, 'default' => '')),
            $col('changed_at', 'int', array('unsigned' => true, 'default' => 0))),
        array(array('type' => 'primary', 'columns' => array('id_event')),
            array('name' => 'member_changed', 'type' => 'index', 'columns' => array('id_member', 'changed_at')),
            array('name' => 'content_changed', 'type' => 'index', 'columns' => array('content_type', 'content_id', 'changed_at'))),
    ),
    'bs_reaction_audit' => array(
        array($col('id_event', 'bigint', array('unsigned' => true, 'auto' => true)), $col('content_id', 'int', array('unsigned' => true, 'default' => 0)),
            $col('id_member', 'int', array('unsigned' => true, 'default' => 0)), $col('old_reaction', 'varchar', array('size' => 24, 'default' => '')),
            $col('new_reaction', 'varchar', array('size' => 24, 'default' => '')), $col('changed_at', 'int', array('unsigned' => true, 'default' => 0))),
        array(array('type' => 'primary', 'columns' => array('id_event')),
            array('name' => 'content_changed', 'type' => 'index', 'columns' => array('content_id', 'changed_at')),
            array('name' => 'member_changed', 'type' => 'index', 'columns' => array('id_member', 'changed_at'))),
    ),
    'bs_reaction_types' => array(
        array($col('content_id', 'int', array('unsigned' => true, 'default' => 0)), $col('id_member', 'int', array('unsigned' => true, 'default' => 0)),
            $col('reaction', 'varchar', array('size' => 24, 'default' => '')), $col('changed_at', 'int', array('unsigned' => true, 'default' => 0))),
        array(array('type' => 'primary', 'columns' => array('content_id', 'id_member')),
            array('name' => 'reaction_content', 'type' => 'index', 'columns' => array('reaction', 'content_id')),
            array('name' => 'member_changed', 'type' => 'index', 'columns' => array('id_member', 'changed_at'))),
    ),
);
// 'ignore' leaves tables from earlier versions (and their data) untouched.
foreach ($tables as $name => $def)
    $smcFunc['db_create_table']('{db_prefix}' . $name, $def[0], $def[1], array(), 'ignore');

// Old Enhanced Reactions 1.0.6 stored its identity in native Likes and the
// selected emoji in bs_reaction_types. Migrate once; never delete legacy rows.
// Do not pull unrelated native Likes into a new installation.
if ($hadLegacy && empty($modSettings['bsr_legacy_migrated']))
{
    // In batches of messages, so a large user_likes table can't exhaust memory.
    $last = 0;
    while (true)
    {
        $ids = array();
        $q = $smcFunc['db_query']('', '
            SELECT DISTINCT content_id FROM {db_prefix}user_likes
            WHERE content_type = {string:msg} AND content_id > {int:last}
            ORDER BY content_id
            LIMIT 200', array('msg' => 'msg', 'last' => $last));
        while ($r = $smcFunc['db_fetch_assoc']($q))
            $ids[] = (int) $r['content_id'];
        $smcFunc['db_free_result']($q);
        if (empty($ids))
            break;

        $rows = array();
        $q = $smcFunc['db_query']('', '
            SELECT ul.id_member, ul.content_id, ul.like_time, COALESCE(rt.reaction, {string:default}) AS reaction
            FROM {db_prefix}user_likes AS ul
                LEFT JOIN {db_prefix}bs_reaction_types AS rt ON (rt.content_id = ul.content_id AND rt.id_member = ul.id_member)
            WHERE ul.content_type = {string:msg} AND ul.content_id IN ({array_int:ids})',
            array('msg' => 'msg', 'default' => 'like', 'ids' => $ids));
        while ($r = $smcFunc['db_fetch_assoc']($q))
            $rows[] = array('msg', (int) $r['content_id'], (int) $r['id_member'], substr((string) $r['reaction'], 0, 24), (int) $r['like_time']);
        $smcFunc['db_free_result']($q);

        if (!empty($rows))
            $smcFunc['db_insert']('ignore', '{db_prefix}bs_reaction_items',
                array('content_type' => 'string-24', 'content_id' => 'int', 'id_member' => 'int', 'reaction' => 'string-24', 'changed_at' => 'int'),
                $rows, array('content_type', 'content_id', 'id_member'));
        $last = end($ids);
    }
    $markMigrated = true;
}

// Only insert defaults absent from the database. Existing settings survive.
$defaults = array('bsr_enabled' => '1', 'bsr_enabled_types' => 'like,love,laugh,wow,sad,angry,helpful,thanks',
    'bsr_disabled_boards' => '', 'bsr_rate_limit' => '2');
$missing = array();
foreach ($defaults as $name => $value)
    if (!isset($modSettings[$name]))
        $missing[$name] = $value;
if (!empty($markMigrated))
    $missing['bsr_legacy_migrated'] = '1';
if ($missing)
    updateSettings($missing);

// Fresh install: let normal registered members react. Existing installations:
// copy the old permission grants AND explicit denies, never add a blanket grant.
if (!$hadItems && !$hadLegacy)
    $smcFunc['db_insert']('ignore', '{db_prefix}permissions',
        array('id_group' => 'int', 'permission' => 'string-30', 'add_deny' => 'int'),
        array(0, 'bsr_react', 1), array('id_group', 'permission'));
else
{
    $q = $smcFunc['db_query']('', 'SELECT COUNT(*) AS n FROM {db_prefix}permissions WHERE permission = {string:r}', array('r' => 'bsr_react'));
    $hasNew = (int) $smcFunc['db_fetch_assoc']($q)['n'] > 0;
    $smcFunc['db_free_result']($q);
    if (!$hasNew)
    {
        $rows = array();
        $q = $smcFunc['db_query']('', 'SELECT id_group, add_deny FROM {db_prefix}permissions WHERE permission = {string:likes}', array('likes' => 'likes_like'));
        while ($r = $smcFunc['db_fetch_assoc']($q))
            $rows[] = array((int) $r['id_group'], 'bsr_react', (int) $r['add_deny']);
        $smcFunc['db_free_result']($q);
        if ($rows)
            $smcFunc['db_insert']('ignore', '{db_prefix}permissions',
                array('id_group' => 'int', 'permission' => 'string-30', 'add_deny' => 'int'), $rows, array('id_group', 'permission'));
    }
}
