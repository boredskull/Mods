<?php
if (!defined('SMF')) die('No direct access');
function template_bsap_reveal_confirm() {
    global $context, $scripturl, $txt;
    $id = (int) $context['bsap_reveal_msg'];
    echo '<div class="cat_bar"><h3 class="catbg">', $txt['bsap_reveal_confirm_title'], '</h3></div>',
        '<div class="roundframe"><p>', $txt['bsap_reveal_confirm_text'], '</p>',
        '<form method="post" action="', htmlspecialchars($scripturl . '?action=bsapreveal', ENT_QUOTES, 'UTF-8'), '">',
        '<input type="hidden" name="msg" value="', $id, '">',
        '<input type="hidden" name="', htmlspecialchars($context['session_var'], ENT_QUOTES, 'UTF-8'), '" value="', htmlspecialchars($context['session_id'], ENT_QUOTES, 'UTF-8'), '">',
        '<button type="submit" class="button">', $txt['bsap_reveal_confirm_button'], '</button> ',
        '<a class="button" href="', htmlspecialchars($scripturl . '?msg=' . $id, ENT_QUOTES, 'UTF-8'), '">', $txt['bsap_cancel'], '</a>',
        '</form></div>';
}
function template_bsap_reveal() {
    global $context, $scripturl, $txt;
    $r = $context['bsap_reveal_data'];
    echo '<div class="cat_bar"><h3 class="catbg">', $txt['bsap_reveal_title'], '</h3></div>',
        '<div class="roundframe"><p>', $txt['bsap_reveal_message'], ' #', (int) $r['msg'], ': ',
        '<a href="', htmlspecialchars($scripturl . '?action=profile;u=' . (int) $r['member'], ENT_QUOTES, 'UTF-8'), '">',
        $r['name'], '</a>.</p><p>', $txt['bsap_reveal_logged'], '</p></div>';
}

function template_bsap_audit() {
    global $context, $scripturl, $txt, $smcFunc;
    $url = $scripturl . '?action=admin;area=modsettings;sa=bsapaudit';
    $e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    echo '<div class="cat_bar"><h3 class="catbg">', $e($txt['bsap_audit_title']), '</h3></div><div class="roundframe"><p>', $e($txt['bsap_audit_desc']), '</p>',
        '<form method="post" action="', $e($url), '"><input type="hidden" name="', $e($context['session_var']), '" value="', $e($context['session_id']), '"><button class="button" name="bsap_purge" value="1">', $e($txt['bsap_purge']), '</button></form></div>',
        '<table class="table_grid"><thead><tr class="title_bar"><th>', $e($txt['bsap_audit_post']), '</th><th>', $e($txt['bsap_audit_viewer']), '</th><th>', $e($txt['bsap_audit_time']), '</th></tr></thead><tbody>';
    foreach ($context['bsap_audit_rows'] as $r) {
        $viewer = isset($r['real_name']) ? $e(un_htmlspecialchars($r['real_name'])) : $e($txt['bsap_deleted_member']);
        echo '<tr class="windowbg"><td><a href="', $e($scripturl . '?msg=' . (int)$r['id_msg']), '">#', (int)$r['id_msg'], '</a></td><td>', $viewer, ' (#', (int)$r['id_viewer'], ')</td><td>', timeformat((int)$r['viewed_at']), '</td></tr>';
    }
    if (!$context['bsap_audit_rows']) echo '<tr class="windowbg"><td colspan="3">', $e($txt['bsap_audit_empty']), '</td></tr>';
    echo '</tbody></table><div class="pagesection">';
    if ($context['bsap_audit_start'] > 0) echo '<a class="button" href="', $e($url . ';start=' . max(0,$context['bsap_audit_start']-100)), '">', $e($txt['bsap_previous']), '</a> ';
    if ($context['bsap_audit_more']) echo '<a class="button" href="', $e($url . ';start=' . ($context['bsap_audit_start']+100)), '">', $e($txt['bsap_next']), '</a>';
    echo '</div>';
}
