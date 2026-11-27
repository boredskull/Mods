<?php
/**
 * Federation 1.0.2. Independent ActivityPub module for SMF 2.1.x.
 * Copyright (c) 2026 BoredSkull. ISC licence.
 */
if (!defined('SMF')) die('No direct access');

if (!defined('FEDERATION_VERSION')) define('FEDERATION_VERSION', '1.0.2');

function federation_actions(&$actions)
{
    $actions['federation'] = array('Federation.php', 'federation_dispatch');
}
function federation_admin_areas(&$areas)
{
    global $txt;
    loadLanguage('Federation');
    $areas['config']['areas']['modsettings']['subsections']['federation'] = array($txt['federation_title']);
}
function federation_modsettings(&$subActions)
{
    $subActions['federation'] = 'federation_admin';
}
function federation_profile_areas(&$areas)
{
    global $modSettings, $txt, $context;
    if (empty($context['user']['is_owner'])) return;
    if (empty($modSettings['federation_enabled']) || empty($modSettings['federation_allow_members'])) return;
    loadLanguage('Federation');
    $areas['edit_profile']['areas']['federation'] = array(
        'label' => $txt['federation_profile'], 'file' => 'Federation.php',
        'function' => 'federation_profile', 'icon' => 'members',
        'permission' => array('own' => array('profile_extra_own'), 'any' => array()),
    );
}
function federation_load_theme()
{
    global $modSettings;
    if (empty($modSettings['federation_enabled'])) return;
    loadCSSFile('federation.css', array('default_theme' => true, 'minimize' => true, 'seed' => FEDERATION_VERSION . '-ui2'), 'federation_css');
}
function federation_create_topic($msgOptions, $topicOptions, $posterOptions = array())
{
    global $sourcedir, $modSettings;
    if (empty($modSettings['federation_enabled']) || empty($modSettings['federation_outbound'])) return;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Protocol.php');
    $board = !empty($topicOptions['board']) ? (int) $topicOptions['board'] : 0;
    $topic = !empty($topicOptions['id']) ? (int) $topicOptions['id'] : 0;
    $message = !empty($msgOptions['id']) ? (int) $msgOptions['id'] : 0;
    if ($board && $topic && $message) federation_publish_topic($board, $topic, $message);
}
function federation_modify_post($message_columns, $update_parameters, $msgOptions, $topicOptions, $posterOptions)
{
    global $sourcedir, $modSettings;
    if (empty($modSettings['federation_enabled']) || empty($modSettings['federation_outbound']) || empty($msgOptions['id'])) return;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Protocol.php');
    // SMF fires this hook before saving. Read committed content at shutdown.
    register_shutdown_function('federation_update_message', (int)$msgOptions['id']);
}
function federation_remove_message($message, $row, $recycle)
{
    global $sourcedir, $modSettings;
    if (empty($modSettings['federation_enabled'])) return;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Protocol.php');
    federation_delete_message((int)$message);
}
function federation_dispatch()
{
    global $sourcedir, $modSettings;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Protocol.php');
    $sa = isset($_GET['sa']) && is_string($_GET['sa']) ? $_GET['sa'] : '';
    if (empty($modSettings['federation_enabled'])) federation_error(404, federation_message('federation_error_federation_disabled', 'Federation disabled'));
    if ($sa === 'webfinger') return federation_webfinger();
    if ($sa === 'nodeinfo') return federation_nodeinfo(false);
    if ($sa === 'nodeinfo_document') return federation_nodeinfo(true);
    if ($sa === 'actor') return federation_actor_endpoint();
    if ($sa === 'outbox') return federation_outbox_endpoint();
    if ($sa === 'followers') return federation_followers_endpoint();
    if ($sa === 'object') return federation_object_endpoint();
    if ($sa === 'feed') return federation_feed_endpoint();
    if ($sa === 'inbox') return federation_inbox_endpoint();
    federation_error(404, federation_message('federation_error_unknown_endpoint', 'Unknown endpoint'));
}
function federation_admin()
{
    global $sourcedir;
    isAllowedTo('admin_forum');
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Admin.php');
    federation_admin_page();
}
function federation_profile($memID)
{
    global $sourcedir;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Admin.php');
    federation_member_page((int) $memID);
}

function federation_credits()
{
    global $context, $txt;
    loadLanguage('Federation');
    $context['credits_modifications'][] = sprintf($txt['federation_credits'], FEDERATION_VERSION, $txt['by']);
}
function federation_lifecycle_load()
{
    global $sourcedir, $modSettings;
    if (empty($modSettings['federation_enabled'])) return false;
    require_once($sourcedir . '/Federation-Lib.php');
    require_once($sourcedir . '/Federation-Protocol.php');
    return true;
}
function federation_remove_topics($topics)
{
    if (federation_lifecycle_load()) federation_withdraw_topics((array)$topics);
}
function federation_after_approve($approve, $msgs, $topic_changes, $member_changes)
{
    global $smcFunc, $modSettings;
    if (!federation_lifecycle_load()) return;
    $ids = array_map('intval', (array)$msgs);
    if (!$ids) return;
    if (!$approve) { foreach ($ids as $id) federation_delete_message($id); return; }
    if (empty($modSettings['federation_outbound'])) return;
    $q = $smcFunc['db_query']('', 'SELECT id_board, id_topic, id_first_msg FROM {db_prefix}topics WHERE id_first_msg IN ({array_int:ids}) AND approved = 1', array('ids'=>$ids));
    $rows = array(); while ($r=$smcFunc['db_fetch_assoc']($q)) $rows[]=$r;
    $smcFunc['db_free_result']($q);
    foreach ($rows as $r) federation_publish_topic((int)$r['id_board'], (int)$r['id_topic'], (int)$r['id_first_msg']);
}
function federation_move_end()
{
    global $topic;
    if (!empty($topic) && federation_lifecycle_load()) federation_reconcile_topics(array((int)$topic));
}
