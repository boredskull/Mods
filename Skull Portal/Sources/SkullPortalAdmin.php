<?php
/** Skull Portal administrator configuration. */
if (!defined('SMF')) die('No direct access');

function skullportal_admin()
{
    global $context, $txt, $smcFunc, $modSettings, $scripturl;
    isAllowedTo('admin_forum');
    loadLanguage('SkullPortal');
    loadTemplate('SkullPortal');
    loadCSSFile('skullportal.css', array('default_theme' => true));
    $context['page_title'] = $txt['skullportal_admin'];
    $context['sub_template'] = 'skullportal_admin';
    $context['skullportal_admin_url'] = $scripturl . '?action=admin;area=skullportal';
    $context['skullportal_types'] = skullportal_types();
    $context['skullportal_edit'] = null;
    $context['skullportal_groups'] = array();
    $groupRequest = $smcFunc['db_query']('', 'SELECT id_group, group_name FROM {db_prefix}membergroups WHERE id_group > {int:min} ORDER BY group_name', array('min' => 0));
    while ($group = $smcFunc['db_fetch_assoc']($groupRequest))
        $context['skullportal_groups'][(int) $group['id_group']] = $group['group_name'];
    $smcFunc['db_free_result']($groupRequest);
    $op = isset($_REQUEST['spop']) && is_string($_REQUEST['spop']) ? $_REQUEST['spop'] : '';

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
    {
        checkSession('post');
        validateToken('skullportal', 'post');
        if ($op === 'settings')
        {
            $enabled = empty($_POST['portal_enabled']) ? '0' : '1';
            $homepage = $enabled === '1' && !empty($_POST['portal_homepage']);
            $previous = (string) ($modSettings['skullportal_previous_default_action'] ?? '');
            $currentDefault = (string) ($modSettings['integrate_default_action'] ?? '');
            if ($homepage && $currentDefault !== 'skullportal_main') $previous = $currentDefault;
            if (!$homepage && $currentDefault !== 'skullportal_main') $previous = $currentDefault;
            updateSettings(array(
                'skullportal_previous_default_action' => $previous,
                'skullportal_enabled' => $enabled,
                'skullportal_homepage' => $homepage ? '1' : '0',
                // SMF's native default-action dispatcher; restore on disable.
                'integrate_default_action' => $homepage ? 'skullportal_main' : ($currentDefault === 'skullportal_main' ? $previous : $currentDefault),
            ));
            redirectexit('action=admin;area=skullportal');
        }
        if ($op === 'save')
        {
            $id = max(0, (int) ($_POST['id_block'] ?? 0));
            $type = (string) ($_POST['block_type'] ?? '');
            if (!isset($context['skullportal_types'][$type])) fatal_lang_error('skullportal_invalid_type', false);
            $title = trim((string) ($_POST['title'] ?? ''));
            $title = function_exists('mb_strcut') ? mb_strcut($title, 0, 150, 'UTF-8') : substr($title, 0, 150);
            if ($title === '') $title = $context['skullportal_types'][$type]['label'];
            $position = (string) ($_POST['position'] ?? 'centre');
            if (!in_array($position, array('left', 'centre', 'right', 'bottom'), true)) $position = 'centre';
            $display_on = (string) ($_POST['display_on'] ?? 'both');
            if (!in_array($display_on, array('portal', 'forum', 'both'), true)) $display_on = 'both';
            $visibility = (string) ($_POST['visibility'] ?? 'everyone');
            if (!in_array($visibility, array('everyone', 'members', 'guests'), true))
            {
                $groupID = strpos($visibility, 'group:') === 0 ? (int) substr($visibility, 6) : 0;
                if (!$groupID || !isset($context['skullportal_groups'][$groupID])) $visibility = 'everyone';
                else $visibility = 'group:' . $groupID;
            }
            $content = (string) ($_POST['content'] ?? '');
            if ($type === 'welcome')
            {
                $options = array();
                foreach (array_keys(skullportal_welcome_options(array())) as $option)
                    $options[$option] = !empty($_POST['welcome_options'])
                        && is_array($_POST['welcome_options'])
                        && !empty($_POST['welcome_options'][$option]);
                $content = json_encode(array('welcome_options' => $options));
            }
            if (strlen($content) > 200000) fatal_error('Block content is too large.', false);
            // Only validated hexadecimal colours are accepted. Empty = theme default.
            $style = array();
            $submittedStyle = isset($_POST['style']) && is_array($_POST['style']) ? $_POST['style'] : array();
            foreach (array('header_bg', 'header_text', 'body_bg', 'body_text', 'border') as $styleKey)
            {
                $value = trim((string) ($submittedStyle[$styleKey] ?? ''));
                if ($value !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/D', $value))
                    fatal_lang_error('skullportal_invalid_colour', false);
                $style[$styleKey] = $value;
            }
            $radius = (string) ($submittedStyle['radius'] ?? 'theme');
            if (!in_array($radius, array('theme', 'square', 'small', 'rounded'), true))
                $radius = 'theme';
            $style['radius'] = $radius;
            $styleSettings = json_encode($style);
            $params = array(
                'title' => $title, 'type' => $type, 'content' => $content, 'position' => $position,
                'sort' => max(-99999, min(99999, (int) ($_POST['sort_order'] ?? 0))),
                'display_on' => $display_on, 'visibility' => $visibility, 'enabled' => empty($_POST['enabled']) ? 0 : 1,
                'show_title' => empty($_POST['show_title']) ? 0 : 1,
                'style_settings' => $styleSettings,
            );
            if ($id > 0)
            {
                // Prevent a forged POST from overwriting a block belonging to
                // an unavailable integration provider.
                $existingRequest = $smcFunc['db_query']('',
                    'SELECT block_type FROM {db_prefix}skullportal_blocks WHERE id_block={int:id} LIMIT 1',
                    array('id' => $id));
                $existing = $smcFunc['db_fetch_assoc']($existingRequest);
                $smcFunc['db_free_result']($existingRequest);
                if (!$existing) fatal_lang_error('skullportal_invalid_block', false);
                if (!isset($context['skullportal_types'][$existing['block_type']]))
                    fatal_lang_error('skullportal_invalid_type', false);
                $params['id'] = $id;
                $smcFunc['db_query']('', 'UPDATE {db_prefix}skullportal_blocks SET
                    title={string:title}, block_type={string:type}, content={string:content},
                    position={string:position}, display_on={string:display_on}, sort_order={int:sort}, visibility={string:visibility},
                    enabled={int:enabled}, show_title={int:show_title}, style_settings={string:style_settings}
                    WHERE id_block={int:id}', $params);
            }
            else
            {
                $smcFunc['db_insert']('insert', '{db_prefix}skullportal_blocks',
                    array('title' => 'string', 'block_type' => 'string', 'content' => 'string',
                        'position' => 'string', 'display_on' => 'string', 'sort_order' => 'int', 'visibility' => 'string',
                        'enabled' => 'int', 'show_title' => 'int', 'style_settings' => 'string'),
                    array(array(
                        $params['title'], $params['type'], $params['content'],
                        $params['position'], $params['display_on'], $params['sort'],
                        $params['visibility'], $params['enabled'], $params['show_title'], $params['style_settings']
                    )), array('id_block'));
            }
            redirectexit('action=admin;area=skullportal');
        }
        if ($op === 'duplicate')
        {
            $id = max(0, (int) ($_POST['id_block'] ?? 0));
            $request = $smcFunc['db_query']('', 'SELECT title, block_type, content, position, display_on, sort_order, visibility, enabled, show_title, style_settings FROM {db_prefix}skullportal_blocks WHERE id_block={int:id}', array('id' => $id));
            $block = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            if ($block && isset($context['skullportal_types'][$block['block_type']]))
                $smcFunc['db_insert']('insert', '{db_prefix}skullportal_blocks',
                    array('title'=>'string','block_type'=>'string','content'=>'string','position'=>'string','display_on'=>'string','sort_order'=>'int','visibility'=>'string','enabled'=>'int','show_title'=>'int','style_settings'=>'string'),
                    array(array(substr($block['title'] . ' (copy)',0,150),$block['block_type'],$block['content'],$block['position'],$block['display_on'],(int)$block['sort_order']+1,$block['visibility'],0,(int)$block['show_title'],$block['style_settings'])), array('id_block'));
            redirectexit('action=admin;area=skullportal');
        }
        if ($op === 'reorder')
        {
            // Only IDs that currently exist may be moved. Uses the same SMF
            // session and CSRF checks as block editing.
            $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : array();
            $valid = array();
            $check = $smcFunc['db_query']('', 'SELECT id_block FROM {db_prefix}skullportal_blocks', array());
            while ($r = $smcFunc['db_fetch_assoc']($check)) $valid[(int) $r['id_block']] = true;
            $smcFunc['db_free_result']($check);
            $order = array('left'=>0,'centre'=>0,'right'=>0,'bottom'=>0);
            foreach ($ids as $position => $list)
            {
                if (!isset($order[$position]) || !is_array($list)) continue;
                foreach (array_unique(array_map('intval', $list)) as $id)
                {
                    if (!isset($valid[$id])) continue;
                    $smcFunc['db_query']('', 'UPDATE {db_prefix}skullportal_blocks SET position={string:pos}, sort_order={int:sort} WHERE id_block={int:id}', array('pos'=>$position,'sort'=>$order[$position]++,'id'=>$id));
                    unset($valid[$id]);
                }
            }
            redirectexit('action=admin;area=skullportal');
        }
        if ($op === 'layout')
        {
            $layout = (string) ($_POST['layout'] ?? '');
            $preset = array('classic'=>array('welcome'=>'left','navigation'=>'left','stats'=>'right'), 'central'=>array('welcome'=>'centre','navigation'=>'centre','stats'=>'centre'), 'sidebar'=>array('welcome'=>'left','navigation'=>'right','stats'=>'right'));
            // Only reposition built-in blocks; never touch extensions or content.
            if (isset($preset[$layout])) foreach ($preset[$layout] as $type=>$position)
                $smcFunc['db_query']('', 'UPDATE {db_prefix}skullportal_blocks SET position={string:pos} WHERE block_type={string:type}',array('pos'=>$position,'type'=>$type));
            redirectexit('action=admin;area=skullportal');
        }
        if ($op === 'delete')
        {
            $id = max(0, (int) ($_POST['id_block'] ?? 0));
            if ($id) $smcFunc['db_query']('', 'DELETE FROM {db_prefix}skullportal_blocks WHERE id_block={int:id}', array('id' => $id));
            redirectexit('action=admin;area=skullportal');
        }
    }

    if ($op === 'edit')
    {
        $id = max(0, (int) ($_GET['id'] ?? 0));
        if ($id)
        {
            $request = $smcFunc['db_query']('', 'SELECT id_block, title, block_type, content, position,
                sort_order, visibility, enabled, show_title, display_on, style_settings FROM {db_prefix}skullportal_blocks
                WHERE id_block={int:id} LIMIT 1', array('id' => $id));
            $context['skullportal_edit'] = $smcFunc['db_fetch_assoc']($request);
            $smcFunc['db_free_result']($request);
            if (!$context['skullportal_edit']) fatal_lang_error('skullportal_invalid_block', false);
            // Do not expose saved blocks whose optional provider is unavailable,
            // including via a manually constructed administrator edit URL.
            if (!isset($context['skullportal_types'][$context['skullportal_edit']['block_type']]))
                fatal_lang_error('skullportal_invalid_type', false);
        }
        else
            $context['skullportal_edit'] = array('id_block' => 0, 'title' => '', 'block_type' => 'bbc',
                'content' => '', 'position' => 'centre', 'sort_order' => 0,
                'visibility' => 'everyone', 'display_on' => 'both', 'enabled' => 1, 'show_title' => 1, 'style_settings' => '');
    }
    $context['skullportal_rows'] = array();
    $request = $smcFunc['db_query']('', 'SELECT id_block, title, block_type, position, sort_order,
        visibility, enabled, display_on FROM {db_prefix}skullportal_blocks ORDER BY position, sort_order, id_block', array());
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        // Keep unavailable provider blocks in the database for reinstalls,
        // but never expose their controls while the provider is absent.
        if (isset($context['skullportal_types'][$row['block_type']]))
            $context['skullportal_rows'][] = $row;
    }
    $smcFunc['db_free_result']($request);
    createToken('skullportal', 'post');
}
