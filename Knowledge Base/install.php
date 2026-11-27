<?php
/** Knowledge Base database installation. Designed to preserve existing data on reinstall. ISC. */
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
    require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
    die('Knowledge Base must be installed through SMF Package Manager.');

global $smcFunc, $modSettings;
db_extend('packages');

$smcFunc['db_create_table']('{db_prefix}kb_categories', array(
    array('name' => 'id_category', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
    array('name' => 'name', 'type' => 'varchar', 'size' => 120, 'default' => ''),
    array('name' => 'description', 'type' => 'text'),
    array('name' => 'sort_order', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'enabled', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 1),
), array(
    array('type' => 'primary', 'columns' => array('id_category')),
    array('name' => 'idx_enabled', 'type' => 'index', 'columns' => array('enabled', 'sort_order')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}kb_articles', array(
    array('name' => 'id_article', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
    array('name' => 'id_category', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'id_author', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'published_at', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'last_reviewed', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'review_due', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'review_days', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 180),
    array('name' => 'status', 'type' => 'varchar', 'size' => 12, 'default' => 'draft'),
    array('name' => 'current_revision', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'pending_revision', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'pending_submitted', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 0),
    array('name' => 'source_topic', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'source_msg', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_article')),
    array('name' => 'idx_status_category', 'type' => 'index', 'columns' => array('status', 'id_category', 'updated')),
    array('name' => 'idx_pending', 'type' => 'index', 'columns' => array('status', 'pending_revision')),
    array('name' => 'idx_review_due', 'type' => 'index', 'columns' => array('review_due')),
    array('name' => 'idx_author', 'type' => 'index', 'columns' => array('id_author')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}kb_revisions', array(
    array('name' => 'id_revision', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
    array('name' => 'id_article', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'id_category', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'review_days', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 180),
    array('name' => 'title', 'type' => 'varchar', 'size' => 180, 'default' => ''),
    array('name' => 'summary', 'type' => 'varchar', 'size' => 250, 'default' => ''),
    array('name' => 'body', 'type' => 'text'),
    array('name' => 'edit_note', 'type' => 'varchar', 'size' => 250, 'default' => ''),
    array('name' => 'id_editor', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'is_published', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 0),
    array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_revision')),
    array('name' => 'idx_article', 'type' => 'index', 'columns' => array('id_article', 'id_revision')),
), array(), 'ignore');

$smcFunc['db_create_table']('{db_prefix}kb_feedback', array(
    array('name' => 'id_article', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
    array('name' => 'id_member', 'type' => 'mediumint', 'size' => 8, 'unsigned' => true, 'default' => 0),
    array('name' => 'vote', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 1),
    array('name' => 'created', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
), array(
    array('type' => 'primary', 'columns' => array('id_article', 'id_member')),
    array('name' => 'idx_member', 'type' => 'index', 'columns' => array('id_member')),
), array(), 'ignore');

// Reinstall/repair path for anyone who staged an earlier pre-release 1.0.0.
// An existing kb_articles table is not altered by db_create_table(..., 'ignore').
$smcFunc['db_add_column']('{db_prefix}kb_articles',
    array('name' => 'pending_submitted', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 0),
    array(), 'ignore');
$smcFunc['db_add_column']('{db_prefix}kb_revisions',
    array('name' => 'is_published', 'type' => 'tinyint', 'size' => 1, 'unsigned' => true, 'default' => 0),
    array(), 'ignore');
if (empty($modSettings['kb_editorial_migration_done']))
{
    // Legacy status=pending represented an explicitly submitted new article.
    // Existing published-article proposals are treated as drafts (safer than
    // putting possibly unfinished revisions into the approval queue).
    $smcFunc['db_query']('', 'UPDATE {db_prefix}kb_articles SET pending_submitted = {int:yes}
        WHERE status = {string:pending} AND pending_revision > {int:zero}',
        array('yes' => 1, 'pending' => 'pending', 'zero' => 0));
    // Portable (MySQL and PostgreSQL): no UPDATE ... JOIN.
    $smcFunc['db_query']('', 'UPDATE {db_prefix}kb_revisions
        SET is_published = {int:yes}
        WHERE id_revision IN (SELECT current_revision FROM {db_prefix}kb_articles WHERE current_revision > {int:zero})',
        array('yes' => 1, 'zero' => 0));
    updateSettings(array('kb_editorial_migration_done' => 1));
}

$defaults = array('kb_enabled' => 1, 'kb_show_menu' => 1, 'kb_feedback_enabled' => 1,
    'kb_qa_integration' => 1, 'kb_review_days' => 180);
$new = array();
foreach ($defaults as $key => $value)
    if (!isset($modSettings[$key]))
        $new[$key] = $value;
if ($new)
    updateSettings($new);

// Seed initial categories only if this table is empty. Custom categories remain untouched.
$req = $smcFunc['db_query']('', 'SELECT id_category FROM {db_prefix}kb_categories LIMIT 1', array());
$exists = (bool) $smcFunc['db_fetch_assoc']($req);
$smcFunc['db_free_result']($req);
if (!$exists)
{
    $smcFunc['db_insert']('insert', '{db_prefix}kb_categories',
        array('name' => 'string-120', 'description' => 'string', 'sort_order' => 'int', 'enabled' => 'int'),
        array(
            array('Getting Started', 'Introductory articles and common first steps.', 10, 1),
            array('Guides', 'Detailed how-to articles and documentation.', 20, 1),
            array('Troubleshooting', 'Solutions and frequently encountered problems.', 30, 1),
        ), array('id_category'));
}

// Conservative default permissions: regular members may submit and edit their own drafts;
// global moderators may review. Admins are handled natively by SMF.
if (empty($modSettings['kb_permission_defaults_installed']))
{
    $smcFunc['db_insert']('ignore', '{db_prefix}permissions',
        array('id_group' => 'int', 'permission' => 'string-50', 'add_deny' => 'int'),
        array(array(0, 'kb_submit', 1), array(0, 'kb_edit_own', 1),
              array(2, 'kb_submit', 1), array(2, 'kb_edit_own', 1),
              array(2, 'kb_edit_any', 1), array(2, 'kb_review', 1)),
        array('id_group', 'permission'));
    updateSettings(array('kb_permission_defaults_installed' => 1));
}
