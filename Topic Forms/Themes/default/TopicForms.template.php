<?php
/** BoredSkull Topic Forms administrative template. ISC. */
function template_bstf_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function template_bstf_admin() {
    global $context, $txt, $scripturl;
    $e = 'template_bstf_e';
    $board = (int)$context['bstf_board'];
    $form = $context['bstf_form'];
    $fields = $form ? $form['fields'] : array();
    $boardName = $e(html_entity_decode($context['bstf_boards'][$board] ?? '', ENT_QUOTES, 'UTF-8'));
    echo '<div class="cat_bar"><h3 class="catbg">',$e($txt['bstf']),'</h3></div>';
    $done = $_GET['done'] ?? '';
    if (in_array($done, array('save','layout','move'), true))
        echo '<p class="infobox" role="status">',$e($txt['bstf_done_'.$done]),'</p>';
    if (!$board) { echo '<p class="noticebox">',$e($txt['bstf_no_boards']),'</p>'; return; }
    echo '<div class="windowbg"><form method="get" action="',$e($scripturl),'" id="bstf_switch">';
    template_bstf_route();
    echo '<label>',$e($txt['bstf_board']),' <select name="bstf_board">';
    foreach ($context['bstf_boards'] as $id=>$name) echo '<option value="',(int)$id,'"',($id===$board?' selected':''),'>',$e(html_entity_decode($name,ENT_QUOTES,'UTF-8')),'</option>';
    echo '</select></label> <noscript><button class="button">',$e($txt['bstf_load_board']),'</button></noscript></form>';
    if ($context['bstf_layouts']) {
        echo '<form method="get" id="bstf_load" action="',$e($scripturl),'">';
        template_bstf_route();
        echo '<input type="hidden" name="bstf_board" value="',$board,'"><label>',$e($txt['bstf_layouts']),' <select name="layout">';
        foreach ($context['bstf_layouts'] as $key=>$name) echo '<option value="',$e($key),'"',($name===($context['bstf_loaded'] ?? null)?' selected':''),'>',$e($name),'</option>';
        echo '</select></label> <button class="button">',$e($txt['bstf_load_layout']),'</button></form>';
    }
    echo '</div><div class="title_bar"><h3 class="titlebg">',$boardName,'</h3></div>';
    if (isset($context['bstf_loaded'])) echo '<p class="noticebox">',sprintf($e($txt['bstf_loaded']),$e($context['bstf_loaded'])),'</p>';
    echo '<form method="post" id="bstf_editor" action="',$e($context['bstf_post_url']),'" class="windowbg">';
    template_bstf_tokens();
    echo '<dl class="settings"><dt><label for="bstf_enabled">',$e($txt['bstf_enabled']),'</label></dt><dd><input id="bstf_enabled" type="checkbox" name="bstf_enabled" value="1"',(!empty($form['enabled'])?' checked':''),'></dd>';
    echo '<dt><label for="bstf_only">',$e($txt['bstf_mode']),'</label></dt><dd><select id="bstf_only" name="bstf_only"><option value="0">',$e($txt['bstf_standard']),'</option><option value="1"',(!empty($form['form_only'])?' selected':''),'>',$e($txt['bstf_only']),'</option></select></dd>';
    echo '<dt><label for="bstf_title">',$e($txt['bstf_title']),'</label></dt><dd><input id="bstf_title" type="text" name="bstf_title" maxlength="120" value="',$e($form['title'] ?? $txt['bstf_default_title']),'" required></dd>';
    echo '<dt><label for="bstf_description">',$e($txt['bstf_instructions']),'</label></dt><dd><textarea id="bstf_description" name="bstf_description" maxlength="4000" rows="4">',$e($form['description'] ?? ''),'</textarea></dd>';
    echo '<dt><label for="bstf_prefix_row">',$e($txt['bstf_prefix']),'</label></dt><dd><select id="bstf_prefix_row" name="bstf_prefix_row"><option value="">',$e($txt['bstf_none']),'</option>';
    foreach ($fields as $i=>$f) if ($f['type']==='select') echo '<option value="',(int)$i,'"',($f['key']===($form['prefix_key'] ?? '')?' selected':''),'>',$e($f['label']),'</option>';
    echo '</select><div class="smalltext">',$e($txt['bstf_prefix_help']),'</div></dd></dl>';
    echo '<h3>',$e($txt['bstf_fields']),'</h3><p>',$e($txt['bstf_fields_help']),'</p><div id="bstf_rows">';
    for ($i=0;$i<max(count($fields),1);$i++) echo template_bstf_row_html($i,$fields[$i] ?? array());
    echo '</div><p><button class="button" type="button" id="bstf_add">',$e($txt['bstf_add']),'</button></p>';
    echo '<button type="submit" name="bstf_action" value="save" class="button">',$e($txt['bstf_save']),'</button>';
    echo '<fieldset class="roundframe clear"><legend>',$e($txt['bstf_reuse']),'</legend><p>',$e($txt['bstf_layout_help']),'</p><label>',$e($txt['bstf_layout_name']),' <input name="layout_name" maxlength="100" value="',$e($context['bstf_loaded'] ?? ''),'"></label> <button name="bstf_action" value="layout" class="button">',$e($txt['bstf_save_layout']),'</button></fieldset></form>';
    $targets = $context['bstf_available'];
    unset($targets[$board]);
    if ($context['bstf_assigned'] && $targets) {
        echo '<form method="post" id="bstf_move" class="windowbg" action="',$e($context['bstf_post_url']),'">';
        template_bstf_tokens();
        echo '<h3>',$e($txt['bstf_move']),'</h3><p>',$e($txt['bstf_move_help']),'</p><label>',$e($txt['bstf_target']),' <select name="target" required><option value=""></option>';
        foreach ($targets as $id=>$name) echo '<option value="',(int)$id,'">',$e(html_entity_decode($name,ENT_QUOTES,'UTF-8')),'</option>';
        echo '</select></label> <button class="button" name="bstf_action" value="move">',$e($txt['bstf_move']),'</button></form>';
    }
    echo '<template id="bstf_new_row">',template_bstf_row_html('%IDX%',array()),'</template>';
    echo '<input id="bstf_discard" type="hidden" value="',$e($txt['bstf_discard']),'">';
}
function template_bstf_route() {
    echo '<input type="hidden" name="action" value="admin"><input type="hidden" name="area" value="modsettings"><input type="hidden" name="sa" value="bstf">';
}
function template_bstf_tokens() {
    global $context;
    echo '<input type="hidden" name="bstf_board" value="',(int)$context['bstf_board'],'"><input type="hidden" name="revision" value="',template_bstf_e($context['bstf_revision']),'"><input type="hidden" name="',template_bstf_e($context['session_var']),'" value="',template_bstf_e($context['session_id']),'">';
}
function template_bstf_row_html($i,$f) {
    global $txt;
    $e='template_bstf_e';
    $key=$e($f['key'] ?? ''); $label=$e($f['label'] ?? ''); $options=$e(implode("\n",$f['options'] ?? array()));
    $type=$f['type'] ?? 'text'; $prefix='bstf_fields['.$i.']';
    $html='<div class="roundframe" data-index="'.$i.'" style="margin-bottom:10px"><div><strong>'.$e($txt['bstf_field']).'</strong> <button type="button" data-move="up">'.$e($txt['bstf_up']).'</button> <button type="button" data-move="down">'.$e($txt['bstf_down']).'</button> <button type="button" class="button bstf-remove">'.$e($txt['bstf_remove']).'</button></div><label>'.$e($txt['bstf_key']).' <input type="text" name="'.$prefix.'[key]" value="'.$key.'" maxlength="40"'.($key!==''?' readonly':'').'></label> ';
    $html.='<label>'.$e($txt['bstf_label']).' <input class="bstf-label" type="text" name="'.$prefix.'[label]" value="'.$label.'" maxlength="100"></label> ';
    $html.='<label>'.$e($txt['bstf_type']).' <select class="bstf-type" name="'.$prefix.'[type]">';
    foreach (array('text','textarea','select','number','checkbox') as $t) $html.='<option value="'.$t.'"'.($type===$t?' selected':'').'>'.$e($txt['bstf_type_'.$t]).'</option>';
    $html.='</select></label> <label>'.$e($txt['bstf_required']).' <input type="checkbox" name="'.$prefix.'[required]" value="1"'.(!empty($f['required'])?' checked':'').'></label>';
    $html.='<p class="bstf-options"><label>'.$e($txt['bstf_options']).'<br><textarea name="'.$prefix.'[options]" rows="2" style="width:100%">'.$options.'</textarea></label></p></div>';
    return $html;
}

function template_bstf_compose() {
    global $context, $scripturl, $txt;
    $e = 'template_bstf_e';
    echo '<div class="cat_bar"><h3 class="catbg">',$e($txt['start_new_topic']),'</h3></div>';
    echo '<form id="postmodify" name="postmodify" class="windowbg" method="post" enctype="multipart/form-data" onsubmit="submitonce(this);" action="',
        $e($scripturl.'?action='.$context['destination'].';board='.(int)$context['current_board']), '">';
    if (!empty($context['post_error'])) echo '<div class="errorbox" role="alert">',implode('<br>', $context['post_error']),'</div>';
    if (empty($context['becomes_approved'])) echo '<p class="noticebox">',$txt['wait_for_approval'],'</p>';
    template_post_header();
    echo $context['bstf_html'];
    if (!empty($context['can_post_attachment'])) {
        echo '<fieldset><legend>',$txt['attachments'],'</legend><input type="file" name="attachment[]" multiple>';
        if (!empty($context['attachment_restrictions'])) echo '<p class="smalltext">',implode('<br>', $context['attachment_restrictions']),'</p>';
        echo '<input type="hidden" name="attach_del[]" value="0">';
        foreach ($context['current_attachments'] as $item)
            echo '<label><input type="checkbox" name="attach_del[]" value="',$e($item['attachID']),'"',empty($item['unchecked'])?' checked':'','> ',$item['name'],'</label> ';
        echo '</fieldset>';
    }
    if (!empty($context['require_verification']))
        template_control_verification($context['visual_verification_id'], 'all');
    echo '<input type="hidden" name="',$e($context['session_var']),'" value="',$e($context['session_id']),'">',
        '<input type="hidden" name="seqnum" value="',(int)$context['form_sequence_number'],'">',
        '<button type="submit" name="post" class="button">',$e($context['submit_label']),'</button></form>';
}
