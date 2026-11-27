<?php
/** BoredSkull Topic Forms 1.1.0 - ISC licence. SMF 2.1.x. */
if (!defined('SMF')) die('No direct access');

function BSTF_cut($value, $limit) {
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit, 'UTF-8');
    if (function_exists('iconv_substr')) return iconv_substr($value, 0, $limit, 'UTF-8');
    if (preg_match('/^.{0,'.(int)$limit.'}/us', $value, $m)) return $m[0];
    return '';
}
/** Single-line admin text: control characters become spaces. Output is always escaped, so no tag stripping. */
function BSTF_line($value) {
    $value = preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', (string) $value);
    return trim(is_string($value) ? $value : '');
}
function BSTF_lang() {
    global $txt;
    loadLanguage('TopicForms');
}
function BSTF_admin(&$subActions) {
    global $context, $txt;
    BSTF_lang();
    // ModifyModSettings() builds tab_data after this hook, so the tab
    // description is set in BSTF_admin_page() instead.
    $subActions['bstf'] = 'BSTF_admin_page';
}
function BSTF_admin_areas(&$areas) {
    global $txt;
    BSTF_lang();
    if (isset($areas['config']['areas']['modsettings']))
        $areas['config']['areas']['modsettings']['subsections']['bstf'] = array($txt['bstf']);
}
function BSTF_credits() {
    global $context, $txt;
    BSTF_lang();
    $context['credits_modifications'][] = sprintf($txt['bstf_credits'], '1.1.0', $txt['by']);
}
function BSTF_form($board) {
    global $smcFunc;
    if ($board < 1) return null;
    $r = $smcFunc['db_query']('', 'SELECT id_board, title, description, fields_json, enabled, form_only, prefix_key FROM {db_prefix}bstf_forms WHERE id_board={int:board} LIMIT 1', array('board' => $board));
    $row = $smcFunc['db_fetch_assoc']($r);
    $smcFunc['db_free_result']($r);
    if (!$row) return null;
    $fields = json_decode($row['fields_json'], true);
    $row['fields'] = is_array($fields) ? $fields : array();
    return $row;
}
function BSTF_clean_fields($raw, &$rowKeys = null) {
    $fields = array(); $keys = array(); $rowKeys = array();
    if (!is_array($raw)) return $fields;
    foreach (array_slice($raw, 0, 20, true) as $rowIndex => $row) {
        if (!is_array($row)) continue;
        $label = BSTF_line($row['label'] ?? '');
        if ($label === '') continue;
        $label = BSTF_cut($label, 100);
        $type = (string) ($row['type'] ?? 'text');
        if (!in_array($type, array('text','textarea','select','number','checkbox'), true)) $type = 'text';
        $key = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) ($row['key'] ?? ''))));
        if ($key === '') $key = 'field_' . bin2hex(random_bytes(8));
        $key = substr($key, 0, 40);
        // Allocate a unique key even when pre-existing names end in numeric suffixes.
        $base = $key; $suffix = 2;
        while (isset($keys[$key])) {
            $tail = '_' . $suffix++;
            $key = substr($base, 0, 40 - strlen($tail)) . $tail;
        }
        $keys[$key] = true;
        $options = array();
        if ($type === 'select') {
            foreach (preg_split('/\r?\n|\|/', (string) ($row['options'] ?? '')) as $value) {
                $value = BSTF_line($value);
                if ($value !== '') $options[] = BSTF_cut($value, 90);
            }
            $options = array_slice(array_values(array_unique($options)), 0, 30);
            if (!$options) continue;
        }
        $rowKeys[(string)$rowIndex] = $key;
        $fields[] = array('key'=>$key,'label'=>$label,'type'=>$type,'required'=>!empty($row['required']),'options'=>$options);
    }
    return $fields;
}
function BSTF_admin_page() {
    global $context, $smcFunc, $scripturl, $txt;
    isAllowedTo('admin_forum'); BSTF_lang();
    if (!empty($context['admin_menu_name']))
        $context[$context['admin_menu_name']]['tab_data']['tabs']['bstf'] = array('description' => $txt['bstf_admin_desc']);
    $r = $smcFunc['db_query']('', 'SELECT b.id_board, b.name, f.id_board AS assigned FROM {db_prefix}boards AS b LEFT JOIN {db_prefix}bstf_forms AS f ON (f.id_board=b.id_board) WHERE redirect={string:empty} ORDER BY board_order', array('empty'=>''));
    $boards = array();
    $available = array();
    while ($row=$smcFunc['db_fetch_assoc']($r)) {
        $boards[(int)$row['id_board']]=$row['name'];
        if ($row['assigned'] === null) $available[(int)$row['id_board']]=$row['name'];
    }
    $smcFunc['db_free_result']($r);
    $board = (int) ($_POST['bstf_board'] ?? $_GET['bstf_board'] ?? $_GET['board'] ?? key($boards));
    if ($board && !isset($boards[$board])) fatal_lang_error('bstf_invalid_board', false);
    $form = BSTF_form($board);
    $revision = hash('sha256', json_encode($form));
    if (isset($_POST['bstf_action'])) {
        checkSession('post');
        if (!isset($boards[$board])) fatal_lang_error('bstf_invalid_board', false);
        if (($_POST['revision'] ?? '') !== $revision) fatal_lang_error('bstf_stale_admin', false);
        $action = $_POST['bstf_action'];
        if ($action === 'move') {
            $target = (int) ($_POST['target'] ?? 0);
            if (!$form || !isset($available[$target]) || $target === $board) fatal_lang_error('bstf_invalid_move', false);
            // A single primary-key update transfers ownership, including disabled forms.
            $smcFunc['db_query']('', 'UPDATE {db_prefix}bstf_forms SET id_board={int:target} WHERE id_board={int:board}',
                array('target'=>$target, 'board'=>$board));
            redirectexit('action=admin;area=modsettings;sa=bstf;bstf_board='.$target.';done=move');
        }
        $fields = BSTF_clean_fields($_POST['bstf_fields'] ?? array(), $rowKeys);
        $selected = $_POST['bstf_prefix_row'] ?? '';
        $prefix = is_string($selected) && $selected !== '' ? ($rowKeys[$selected] ?? '') : '';
        $prefixKeys = array();
        foreach ($fields as $f) if ($f['type']==='select') $prefixKeys[]=$f['key'];
        if (!in_array($prefix, $prefixKeys, true)) $prefix = '';
        $title = BSTF_cut(BSTF_line($_POST['bstf_title'] ?? ''),120);
        if ($title === '') $title = $txt['bstf_default_title'];
        $description = (string) ($_POST['bstf_description'] ?? '');
        $description = preg_replace('/\r\n?/', "\n", $description);
        $description = BSTF_cut(trim((string) preg_replace('/[^\P{Cc}\n\t]+/u', '', $description)),4000);
        $only = (int) !empty($_POST['bstf_only']);
        $enabled = (int) !empty($_POST['bstf_enabled']);
        if (($enabled || $action === 'layout') && !$fields) fatal_lang_error('bstf_need_fields', false);
        if ($action === 'layout') {
            $name = BSTF_cut(BSTF_line($_POST['layout_name'] ?? ''), 100);
            if ($name === '') fatal_lang_error('bstf_need_name', false);
            $definition = array('title'=>$title, 'description'=>$description, 'fields'=>$fields, 'form_only'=>$only, 'prefix_key'=>$prefix);
            $smcFunc['db_insert']('replace','{db_prefix}bstf_layouts',
                array('layout_key'=>'string-64','name'=>'string-100','definition'=>'string'),
                array(hash('sha256',$name), $name, json_encode($definition, JSON_UNESCAPED_UNICODE)), array('layout_key'));
            redirectexit('action=admin;area=modsettings;sa=bstf;bstf_board='.$board.';layout='.hash('sha256',$name).';done=layout;active='.$enabled);
        }
        if ($action !== 'save') fatal_lang_error('bstf_invalid_board', false);
        $smcFunc['db_insert']('replace','{db_prefix}bstf_forms',
            array('id_board'=>'int','title'=>'string-120','description'=>'string-4000','fields_json'=>'string','enabled'=>'int','prefix_key'=>'string-40','form_only'=>'int'),
            array($board,$title,$description,json_encode($fields, JSON_UNESCAPED_UNICODE),$enabled,$prefix,$only),array('id_board'));
        redirectexit('action=admin;area=modsettings;sa=bstf;bstf_board='.$board.';done=save');
    }
    $context['bstf_available'] = $available;
    $context['bstf_assigned'] = (bool) $form;
    $context['bstf_revision'] = $revision;
    $context['bstf_layouts'] = array();
    $r = $smcFunc['db_query']('', 'SELECT layout_key, name FROM {db_prefix}bstf_layouts ORDER BY name', array());
    while ($row = $smcFunc['db_fetch_assoc']($r)) $context['bstf_layouts'][$row['layout_key']] = $row['name'];
    $smcFunc['db_free_result']($r);
    $layout = (string) ($_GET['layout'] ?? '');
    if (isset($context['bstf_layouts'][$layout])) {
        $r = $smcFunc['db_query']('', 'SELECT definition FROM {db_prefix}bstf_layouts WHERE layout_key={string:key}', array('key'=>$layout));
        $row = $smcFunc['db_fetch_assoc']($r);
        $smcFunc['db_free_result']($r);
        if ($row) {
            $enabled = $form['enabled'] ?? 0;
            $form = json_decode($row['definition'], true);
            $form['enabled'] = isset($_GET['active']) ? (int) !empty($_GET['active']) : $enabled;
            $context['bstf_loaded'] = $context['bstf_layouts'][$layout];
        }
    }
    $context['bstf_boards'] = $boards;
    $context['bstf_board'] = $board;
    $context['bstf_form'] = $form;
    $context['bstf_post_url'] = $scripturl.'?action=admin;area=modsettings;sa=bstf';
    $context['page_title'] = $txt['bstf'];
    loadCSSFile('TopicForms.css', array('default_theme'=>true));
    loadJavaScriptFile('TopicForms.js', array('default_theme'=>true, 'defer'=>true));
    loadTemplate('TopicForms'); $context['sub_template'] = 'bstf_admin';
}
/** Fingerprint the active definition; users must refresh if it changes mid-composition. */
function BSTF_version($form) {
    return hash('sha256', json_encode(array(
        (int) $form['id_board'], (int) $form['enabled'], (int) $form['form_only'],
        $form['fields'], (string) $form['prefix_key']
    ), JSON_UNESCAPED_UNICODE));
}
/** Hook runs after SMF prepares the posting fields and before the theme renders them. */
function BSTF_post_end() {
    global $topic, $board, $context, $txt;
    if (!empty($topic) || !empty($_REQUEST['msg']) || isset($_REQUEST['poll']) || !empty($context['make_event'])) return;
    // SMF's boardless composer is used for calendar events, which are excluded.
    if (empty($board)) return;
    BSTF_lang();
    $form = BSTF_form((int) $board);
    if (!$form || empty($form['enabled']) || empty($form['fields'])) return;
    loadCSSFile('TopicForms.css', array('default_theme'=>true));
    // The rendered form belongs to this board. SMF 2.1's "Post in board" selector
    // would let the topic be submitted to a board with a different (or no) form.
    unset($context['posting_fields']['board']);
    $values = is_array($_POST['bstf'] ?? null) ? $_POST['bstf'] : array();
    $html = '<fieldset id="bstf_fields" class="roundframe"><legend><strong>'.htmlspecialchars($form['title'], ENT_QUOTES, 'UTF-8').'</strong></legend>';
    if ($form['description'] !== '') $html .= '<p style="white-space:pre-line">'.htmlspecialchars($form['description'], ENT_QUOTES, 'UTF-8').'</p>';
    $html .= '<input type="hidden" name="bstf_version" value="'.BSTF_version($form).'">';
    foreach ($form['fields'] as $f) {
        $key=$f['key']; $val=is_scalar($values[$key] ?? null) ? (string)$values[$key] : '';
        $a=' name="bstf['.htmlspecialchars($key,ENT_QUOTES,'UTF-8').']" id="bstf_'.htmlspecialchars($key,ENT_QUOTES,'UTF-8').'"';
        $req = $f['required'] ? ' required' : '';
        $html .= '<div class="bstf_field" style="margin:8px 0"><label for="bstf_'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'"><strong>'.htmlspecialchars($f['label'], ENT_QUOTES, 'UTF-8').($f['required']?' *':'').'</strong></label> ';
        if ($f['type']==='textarea') $html.='<textarea'.$a.$req.' maxlength="4000" rows="4" style="width:100%">'.htmlspecialchars($val, ENT_QUOTES, 'UTF-8').'</textarea>';
        elseif ($f['type']==='select') {
            $html.='<select'.$a.$req.'><option value="">'.htmlspecialchars($txt['bstf_select'], ENT_QUOTES, 'UTF-8').'</option>';
            foreach ($f['options'] as $op) $html.='<option value="'.htmlspecialchars($op, ENT_QUOTES, 'UTF-8').'"'.($op===$val?' selected':'').'>'.htmlspecialchars($op, ENT_QUOTES, 'UTF-8').'</option>';
            $html.='</select>';
        } elseif ($f['type']==='checkbox') $html.='<input type="checkbox" value="1"'.$a.($val==='1'?' checked':'').($f['required']?' required':'').'>';
        elseif ($f['type']==='number') $html.='<input type="number" step="any"'.$a.$req.' value="'.htmlspecialchars($val,ENT_QUOTES,'UTF-8').'">';
        else $html.='<input type="text"'.$a.$req.' maxlength="4000" value="'.htmlspecialchars($val,ENT_QUOTES,'UTF-8').'">';
        $html.='</div>';
    }
    $html.='</fieldset>';
    if (!empty($form['form_only']) && !isset($_REQUEST['xml'])) {
        loadTemplate('TopicForms');
        $context['sub_template'] = 'bstf_compose';
        $context['bstf_html'] = $html;
        // This template submits files with the post. SMF's dropzone bootstrap
        // expects the rich editor's attachment markup to be on the page.
        foreach ($context['javascript_inline']['defer'] ?? array() as $index => $script)
            if (preg_match('/^\s*\$\(function\s*\(\)\s*\{\s*smf_fileUpload\s*\(/', $script))
                unset($context['javascript_inline']['defer'][$index]);
        unset($context['posting_fields']['icon']);
        return;
    }
    // Draft saves are allowed without complete answers server-side; stop the
    // browser's required-field validation from blocking the Save draft button.
    addInlineJavaScript('
    document.querySelectorAll(\'#postmodify [name="save_draft"]\').forEach(function (b) { b.formNoValidate = true; });', true);
    $context['posting_fields']['bstf'] = array(
        'label' => array('html' => '<strong>'.htmlspecialchars($form['title'],ENT_QUOTES,'UTF-8').'</strong>'),
        'input' => array('html' => $html),
    );
}
/** Allow a form-only topic through SMF's ordinary non-empty message check. */
function BSTF_post2_start(&$errors) {
    global $topic, $board, $txt;
    // Never trust a client-supplied internal form-only marker.
    unset($_POST['bstf_form_only']);
    if (!empty($topic) || !empty($_REQUEST['msg']) || isset($_REQUEST['poll']) || isset($_POST['calendar'])) return;
    global $modSettings;
    if (isset($_REQUEST['preview']) || (!empty($modSettings['drafts_post_enabled']) && isset($_POST['save_draft']))) return;
    $form = BSTF_form((int) $board);
    if (!$form || empty($form['enabled']) || empty($form['fields'])) return;
    if (empty($form['form_only']) && (trim((string) ($_POST['message'] ?? '')) !== '' || !isset($_POST['bstf']))) return;
    // Temporary content is replaced with the generated, validated block at
    // integrate_post2_pre. Otherwise SMF rejects an empty editor before we can.
    $_POST['bstf_form_only'] = '1';
    BSTF_lang();
    $_POST['message'] = $txt['bstf_placeholder'];
}
/** Validate at the final native Post2 hook, covering normal post forms and direct HTTP requests. */
function BSTF_post2_pre(&$errors) {
    global $topic, $board, $smcFunc, $modSettings, $txt;
    if (!empty($topic) || !empty($_REQUEST['msg']) || isset($_REQUEST['poll']) || isset($_POST['calendar'])) return;
    $form=BSTF_form((int)$board);
    if (!$form || empty($form['enabled']) || empty($form['fields'])) return;
    BSTF_lang();
    // SMF handles previews and draft saves; neither should require complete fields
    // nor insert a generated block into the editor source.
    if (isset($_REQUEST['preview'])) return;
    if (!isset($_POST['bstf_version']) || !is_string($_POST['bstf_version']) || !hash_equals(BSTF_version($form), $_POST['bstf_version'])) {
        $errors[] = !isset($_POST['bstf_version']) ? 'bstf_missing' : 'bstf_changed';
        if (!empty($_POST['bstf_form_only'])) $_POST['message'] = '';
        return;
    }
    $values = is_array($_POST['bstf'] ?? null) ? $_POST['bstf'] : array();
    $clean = array();
    foreach ($form['fields'] as $f) {
        $label = $smcFunc['htmlspecialchars']($f['label'], ENT_QUOTES);
        $v = $values[$f['key']] ?? '';
        if (!is_scalar($v)) { $errors[]=array('bstf_invalid',array($label)); continue; }
        $v=trim((string)$v);
        if ($f['type']==='checkbox') $v=$v==='1'?'1':'';
        if ($f['type']==='select' && $v!=='' && !in_array($v,$f['options'],true)) { $errors[]=array('bstf_invalid',array($label));continue; }
        if ($f['type']==='number' && $v!=='' && (!is_numeric($v) || strlen($v)>40)) { $errors[]=array('bstf_invalid',array($label));continue; }
        if ($smcFunc['strlen']($v)>4000) { $errors[]=array('bstf_invalid',array($label));continue; }
        if ($f['required'] && $v==='') { $errors[]=array('bstf_required',array($label));continue; }
        $clean[$f['key']]=$v;
    }
    if ($errors) {
        if (!empty($_POST['bstf_form_only'])) $_POST['message'] = '';
        return;
    }
    $pieces=array();
    foreach ($form['fields'] as $f) {
        $v=$clean[$f['key']] ?? '';
        if ($v==='') continue;
        if ($f['type']==='checkbox') $v=$txt['bstf_yes'];
        // The original POST body has already been HTML escaped and preparsed by SMF.
        // Prepare the new BBCode segment with the same transformation.
        $label=str_replace(array('[',']'),array('&#91;','&#93;'),$smcFunc['htmlspecialchars']($f['label'], ENT_QUOTES));
        $value=$smcFunc['htmlspecialchars']($v, ENT_QUOTES);
        $value=str_replace(array('[',']'),array('&#91;','&#93;'),$value);
        $pieces[]='[b]'.$label.'[/b]: '.$value;
    }
    $block=implode("\n",$pieces);
    if ($block==='') {
        if (!empty($form['form_only']) || !empty($_POST['bstf_form_only'])) { $errors[]='no_message'; $_POST['message']=''; }
        return;
    }
    // Stage the generated content and subject until every validation succeeds.
    preparsecode($block);
    $existing = (!empty($form['form_only']) || !empty($_POST['bstf_form_only'])) ? '' : (string) ($_POST['message'] ?? '');
    $combined = $block . ($existing !== '' ? "\n\n" . $existing : '');
    $max = !empty($modSettings['max_messageLength']) ? (int)$modSettings['max_messageLength'] : 0;
    if (strlen($combined) > 65534 || ($max && $smcFunc['strlen']($combined) > $max)) {
        $errors[] = array('long_message', array($max ? min($max, 65534) : 65534));
        if (!empty($_POST['bstf_form_only'])) $_POST['message']='';
        return;
    }
    $subject = (string)($_POST['subject'] ?? '');
    $prefix = (string)($form['prefix_key'] ?? '');
    if ($prefix !== '' && isset($clean[$prefix]) && $clean[$prefix] !== '') {
        // Keep punctuation such as C++ or v2.1; drop only square brackets (they
        // would break the [Prefix] wrapper) and invisible control characters.
        $value = preg_replace('/[\[\]\p{C}]+/u', '', $clean[$prefix]);
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        $value = trim(BSTF_cut((string)$value, 35));
        if ($value !== '') {
            $new = '['.$value.'] '.$subject;
            if ($smcFunc['strlen']($smcFunc['htmlspecialchars']($new)) > 100) {
                $errors[] = 'bstf_subject';
                if (!empty($_POST['bstf_form_only'])) $_POST['message']='';
                return;
            }
            $subject = $new;
        }
    }
    $_POST['message'] = $combined;
    $_POST['subject'] = $subject;
    // Integrations may inspect validated values. The fields themselves are not
    // mutable here, so required-field checks cannot be bypassed afterward.
    call_integration_hook('integrate_bstf_before_post', array($clean,$form,(int)$board));
}
function BSTF_errors(&$errors,&$minor,$message,$subject) {
    BSTF_lang();
}
