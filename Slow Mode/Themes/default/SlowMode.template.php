<?php
if (!defined('SMF')) die('No direct access');
function template_bsm_topic()
{
    global $context, $txt;
    echo '<div class="cat_bar"><h3 class="catbg">' , $txt['bsm_topic_title'], '</h3></div><div class="roundframe">',
      '<p>' , $txt['bsm_topic_help'], '</p>',
      '<p>' , $txt['bsm_current_board'], (int) $context['bsm_board_minutes'], $txt['bsm_minute_suffix'], '</p>',
      '<form method="post" action="', htmlspecialchars($context['bsm_post_url'], ENT_QUOTES, 'UTF-8'), '">',
      '<label for="bsm_minutes">' , $txt['bsm_topic_minutes'], '</label> ',
      '<input type="number" id="bsm_minutes" name="minutes" min="0" max="1440" value="', (int) $context['bsm_minutes'], '"> ',
      '<button type="submit" name="bsm_save" value="1" class="button">' , $txt['bsm_save_topic'], '</button>',
      '<input type="hidden" name="', htmlspecialchars($context['session_var'], ENT_QUOTES, 'UTF-8'), '" value="', htmlspecialchars($context['session_id'], ENT_QUOTES, 'UTF-8'), '"></form></div>';
}
function template_bsm_admin()
{
    global $context, $txt;
    echo '<div class="cat_bar"><h3 class="catbg">' , $txt['bsm_settings_title'], '</h3></div><div class="roundframe">',
       '<p>' , $txt['bsm_admin_help'], '</p>',
       '<form method="post" action="', htmlspecialchars($context['post_url'], ENT_QUOTES, 'UTF-8'), '">',
       '<p><label><input type="checkbox" name="enabled" value="1"', $context['bsm_enabled'] ? ' checked' : '', '>' , $txt['bsm_enable'], '</label></p>',
       '<p><label><input type="checkbox" name="exempt_mod" value="1"', $context['bsm_exempt_mod'] ? ' checked' : '', '>' , $txt['bsm_exempt'], '</label></p>',
       '<table class="table_grid"><thead><tr class="title_bar"><th>' , $txt['bsm_board'], '</th><th>' , $txt['bsm_cooldown'], '</th></tr></thead><tbody>';
    foreach ($context['bsm_boards'] as $id => $name)
       echo '<tr class="windowbg"><td>', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), '</td><td><input type="number" min="0" max="1440" name="minutes[', (int)$id, ']" value="', (int)($context['bsm_rules'][$id] ?? 0), '"></td></tr>';
    echo '</tbody></table><h3>' , $txt['bsm_history'], '</h3><table class="table_grid"><thead><tr><th>' , $txt['bsm_date'], '</th><th>' , $txt['bsm_scope'], '</th><th>' , $txt['bsm_old'], '</th><th>' , $txt['bsm_new'], '</th><th>' , $txt['bsm_member'], '</th></tr></thead><tbody>';
    foreach ($context['bsm_history'] as $h)
        echo '<tr class="windowbg"><td>', gmdate('Y-m-d H:i', (int) $h['changed_at']), '</td><td>', htmlspecialchars($h['scope'], ENT_QUOTES, 'UTF-8'), ' #', (int)$h['scope_id'], '</td><td>', (int)$h['old_minutes'], $txt['bsm_min'], '</td><td>', (int)$h['new_minutes'], $txt['bsm_min'], '</td><td>', (int)$h['id_member'], '</td></tr>';
    echo '</tbody></table><p><button class="button" type="submit" name="bsm_save" value="1">' , $txt['bsm_save'], '</button></p>',
       '<input type="hidden" name="', htmlspecialchars($context['session_var'], ENT_QUOTES, 'UTF-8'), '" value="', htmlspecialchars($context['session_id'], ENT_QUOTES, 'UTF-8'), '"></form></div>';
}
