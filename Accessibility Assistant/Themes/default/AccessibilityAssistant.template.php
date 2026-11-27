<?php
/** Accessibility Assistant interface. SMF 2.1.x. ISC. */
if (!defined('SMF'))
    die('No direct access');

function template_baa_home()
{
    global $context, $txt, $scripturl;
    $e = array('BoredAccessibilityAssistant', 'escape');
    echo '
    <div class="cat_bar"><h3 class="catbg">', $txt['baa_title'], '</h3></div>
    <div class="windowbg baa-page">
      <p>', $txt['baa_intro'], '</p>
      <p class="smalltext">', $txt['baa_disclaimer'], '</p>
      <div class="baa-grid"><section class="baa-section" aria-labelledby="baa-review-title">
       <h3 id="baa-review-title">', $txt['baa_checker_title'], '</h3>
       <p>', $txt['baa_checker_help'], '</p>
       <form action="', $e($context['baa_check_action']), '" method="post" class="baa-check-form">
        <label for="baa_content">', $txt['baa_content_label'], '</label>
        <textarea id="baa_content" name="baa_content" rows="10" maxlength="200000" style="width:100%;box-sizing:border-box;">', $e($context['baa_content']), '</textarea>
        <input type="hidden" name="', $e($context['session_var']), '" value="', $e($context['session_id']), '">
        <input type="hidden" name="', $e($context['baa_check_token_var']), '" value="', $e($context['baa_check_token']), '">
        <button class="button" type="submit">', $txt['baa_check_button'], '</button>
       </form>';
    if (!empty($context['baa_posted']))
    {
        echo '<section class="baa-results" aria-labelledby="baa-results-title" aria-live="polite"><h4 id="baa-results-title">', $txt['baa_results'], '</h4>';
        if (empty($context['baa_result']))
            echo '<p>', $txt['baa_no_findings'], '</p>';
        else
        {
            echo '<ul>';
            foreach ($context['baa_result'] as $item)
            {
                // Results have already been normalized and length-bounded by audit().
                echo '<li><strong>', $e($item['title']), '</strong>';
                if (!empty($item['reference']))
                    echo ' <span class="smalltext">(', $e($item['reference']), ')</span>';
                echo '<p>', $e($item['detail']), '</p>';
                if (!empty($item['example']))
                    echo '<p class="smalltext">', $e($item['example']), '</p>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    }
    echo '</section><section class="baa-section" aria-labelledby="baa-prefs-title">
       <h3 id="baa-prefs-title">', $txt['baa_prefs_title'], '</h3>
       <p>', $txt['baa_prefs_explain'], '</p>
       <form id="baa-preferences" autocomplete="off">
        <fieldset><legend>', $txt['baa_reading_prefs'], '</legend>';
    foreach (array(
        'largeText' => 'baa_large',
        'spacedText' => 'baa_spacing',
        'underlineLinks' => 'baa_underlines',
        'reduceMotion' => 'baa_motion',
        'focusVisible' => 'baa_focus',
        'lineHeight' => 'baa_line_height',
        'wideSpacing' => 'baa_wide_spacing',
        'narrowReading' => 'baa_narrow',
        'serifText' => 'baa_serif',
    ) as $field => $key)
    {
        echo '<label class="baa-checkbox"><input type="checkbox" data-baa-pref="', $e($field), '"> <span>', $txt[$key], '</span></label>';
    }
    echo '</fieldset><label for="baa-presets">', $txt['baa_preset_label'], '</label>
       <select id="baa-presets"><option value="custom">', $txt['baa_preset_default'], '</option><option value="reading">', $txt['baa_preset_reading'], '</option><option value="visibility">', $txt['baa_preset_focus'], '</option><option value="motion">', $txt['baa_preset_motion'], '</option></select>
       <p class="smalltext">', $txt['baa_local_only'], '</p>
         <button type="button" id="baa-reset-prefs" class="button">', $txt['baa_reset'], '</button>
         <p id="baa-settings-feedback" role="status" aria-live="polite"></p>
       </form>
       <section aria-labelledby="baa-contrast-title">
        <h4 id="baa-contrast-title">', $txt['baa_contrast_title'], '</h4>
        <p>', $txt['baa_contrast_help'], '</p>
        <div class="baa-contrast-row">
         <label for="baa-fg">', $txt['baa_foreground'], '</label><input type="text" id="baa-fg" value="#333333" maxlength="7" spellcheck="false" autocomplete="off" inputmode="text">
         <label for="baa-bg">', $txt['baa_background'], '</label><input type="text" id="baa-bg" value="#ffffff" maxlength="7" spellcheck="false" autocomplete="off" inputmode="text">
        </div>
        <button type="button" id="baa-contrast-button" class="button">', $txt['baa_contrast_check'], '</button>
        <p id="baa-contrast-result" role="status" aria-live="polite"></p><div id="baa-contrast-preview" class="baa-contrast-preview" aria-label="', $txt['baa_preview'], '">', $txt['baa_preview'], '</div>
       </section>
      </section></div>
      <section class="baa-section" aria-labelledby="baa-guidance-title">
       <h3 id="baa-guidance-title">', $txt['baa_guide_title'], '</h3>
       <ul class="baa-guidance">
        <li>', $txt['baa_guide_images'], '</li>
        <li>', $txt['baa_guide_links'], '</li>
        <li>', $txt['baa_guide_colour'], '</li>
        <li>', $txt['baa_guide_media'], '</li>
        <li>', $txt['baa_guide_structure'], '</li>
        <li>', $txt['baa_guide_interaction'], '</li>
        <li>', $txt['baa_guide_motion'], '</li>
        <li>', $txt['baa_guide_testing'], '</li>
       </ul>
       <p class="smalltext">', $txt['baa_reference_standards'], ' <a href="https://www.w3.org/TR/WCAG22/">WCAG 2.2</a>, <a href="https://www.w3.org/TR/ATAG20/">ATAG 2.0</a>, <a href="https://www.w3.org/TR/wai-aria-1.2/">WAI-ARIA 1.2</a>.</p>
      </section>
    </div>';
}

function template_baa_admin()
{
    global $context, $txt;
    $e = array('BoredAccessibilityAssistant', 'escape');
    echo '<div class="cat_bar"><h3 class="catbg">', $txt['baa_admin'], '</h3></div><div class="windowbg baa-page">
      <p>', $txt['baa_admin_help'], '</p>
      <form action="', $e($context['baa_admin_action']), '" method="post"><fieldset><legend>', $txt['baa_admin'], '</legend>';
    foreach (array(
        'baa_enabled' => 'baa_enabled_label',
        'baa_composer' => 'baa_composer_label',
        'baa_skip' => 'baa_skip_label',
        'baa_live' => 'baa_live_setting',
        'baa_quick' => 'baa_quick_setting',
        'baa_reading' => 'baa_reading_setting',
        'baa_diagnostics' => 'baa_diagnostics_setting',
    ) as $name => $key)
    {
        echo '<label class="baa-checkbox"><input type="checkbox" name="', $name, '" value="1"', BoredAccessibilityAssistant::setting($name, 1) ? ' checked' : '', '> ', $txt[$key], '</label>';
    }
    echo '</fieldset>';
    if (BoredAccessibilityAssistant::setting('baa_diagnostics', 1))
    {
        echo '<section class="baa-section" aria-labelledby="baa-diag-title"><h4 id="baa-diag-title">', $txt['baa_diagnostics_title'], '</h4><p>', $txt['baa_diagnostics_help'], '</p><dl class="baa-diagnostics">';
        foreach ($context['baa_diagnostics'] as $key => $value)
            echo '<dt>', $e(str_replace('_', ' ', $key)), '</dt><dd>', $e((string) $value), '</dd>';
        echo '</dl></section>';
    }
    echo '
      <input type="hidden" name="', $e($context['session_var']), '" value="', $e($context['session_id']), '">
      <input type="hidden" name="', $e($context['baa_admin_token_var']), '" value="', $e($context['baa_admin_token']), '">
      <button class="button" type="submit">', $txt['baa_save'], '</button>
      </form><p class="smalltext">', $txt['baa_disclaimer'], '</p></div>';
}
