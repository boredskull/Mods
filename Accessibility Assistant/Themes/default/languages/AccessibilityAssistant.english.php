<?php
/** English strings for Accessibility Assistant. ISC. */
if (!defined('SMF')) die('No direct access');
$txt['baa_title'] = 'Accessibility Assistant';
$txt['baa_intro'] = 'Check your post for common accessibility concerns and adjust your reading preferences.';
$txt['baa_disclaimer'] = 'These checks are advisory only. No automated tool can certify WCAG compliance or replace testing with people and assistive technology.';
$txt['baa_checker_title'] = 'Post accessibility checker';
$txt['baa_checker_help'] = 'Paste BBCode from a post to get suggestions. The content is processed for this request only and is not saved by the modification.';
$txt['baa_content_label'] = 'BBCode to check';
$txt['baa_check_button'] = 'Check accessibility';
$txt['baa_results'] = 'Review suggestions';
$txt['baa_close'] = 'Close';
$txt['baa_no_findings'] = 'No issues were detected by these limited checks. This does not mean the content is accessible or WCAG compliant.';
$txt['baa_prefs_title'] = 'Reading preferences';
$txt['baa_prefs_explain'] = 'These optional adjustments affect your browser only.';
$txt['baa_reading_prefs'] = 'Display options';
$txt['baa_large'] = 'Larger text in main content';
$txt['baa_spacing'] = 'More line and paragraph spacing';
$txt['baa_underlines'] = 'Underline text links';
$txt['baa_motion'] = 'Reduce animation and transitions';
$txt['baa_focus'] = 'Stronger keyboard focus indicators';
$txt['baa_local_only'] = 'Preferences are saved in this browser (local storage), not on the server. They do not replace browser or operating-system settings.';
$txt['baa_reset'] = 'Reset preferences';
$txt['baa_saved_local'] = 'Preferences applied in this browser.';
$txt['baa_guide_title'] = 'Accessible content checklist';
$txt['baa_admin'] = 'Accessibility Assistant settings';
$txt['baa_admin_help'] = 'Enable or disable the assistant, posting checker and optional skip link. These settings do not change SMF posting permissions.';
$txt['baa_enabled_label'] = 'Enable Accessibility Assistant';
$txt['baa_composer_label'] = 'Show a checker button on the posting page';
$txt['baa_skip_label'] = 'Add a skip-to-content link where a reliable target exists';
$txt['baa_save'] = 'Save settings';

$txt['baa_contrast_title'] = 'Colour contrast calculator';
$txt['baa_contrast_help'] = 'Enter the actual text and background colours as hex values (#RRGGBB). Ratios are numerical estimates; gradients, images, transparency and text size still need review.';
$txt['baa_foreground'] = 'Text colour';
$txt['baa_background'] = 'Background colour';
$txt['baa_contrast_check'] = 'Calculate contrast';

// Localized instructions for the browser-based assistant.
$txt['baa_storage_blocked'] = 'Preferences were applied, but this browser blocked local storage.';
$txt['baa_reset_local'] = 'Preferences reset for this page; this browser blocked local storage.';
$txt['baa_skip_label'] = 'Skip to main content';
$txt['baa_composer_label'] = 'Accessibility posting assistant';
$txt['baa_composer_help'] = 'Suggestions only. Posting is never blocked.';
$txt['baa_contrast_invalid'] = 'Enter two valid hex colours, for example #333333 and #ffffff.';
$txt['baa_contrast_output'] = '{ratio}:1 contrast ratio. WCAG 2.2 AA: {normal}, {large}. AAA normal text: {aaa}. {note}';
$txt['baa_contrast_normal_pass'] = 'normal text threshold met';
$txt['baa_contrast_normal_fail'] = 'below normal-text threshold';
$txt['baa_contrast_large_pass'] = 'large text threshold met';
$txt['baa_contrast_large_fail'] = 'below large-text threshold';
$txt['baa_contrast_aaa_pass'] = 'threshold met';
$txt['baa_contrast_aaa_fail'] = 'below threshold';
$txt['baa_contrast_caution'] = 'Actual page styling and exceptions still need manual verification.';
$txt['baa_guide_images'] = 'Images: Describe the information conveyed. Use empty alternatives only for truly decorative images.';
$txt['baa_guide_links'] = 'Links: Make link text understandable in context. Avoid repeated vague labels.';
$txt['baa_guide_colour'] = 'Colour: Do not communicate meaning with colour alone. Check actual colour contrast.';
$txt['baa_guide_media'] = 'Media: Supply captions, transcripts and audio descriptions where needed.';
$txt['baa_guide_structure'] = 'Structure: Use clear headings and meaningful lists; mark data tables appropriately.';
$txt['baa_guide_interaction'] = 'Interaction: Make everything operable with a keyboard, show focus, and avoid keyboard traps.';
$txt['baa_guide_motion'] = 'Motion: Support reduced motion and avoid flashing or automatically moving content.';
$txt['baa_guide_testing'] = 'Testing: Check zoom, mobile reflow and real assistive technologies on the final theme.';
$txt['baa_reference_standards'] = 'Reference standards:';

// Localizable issue descriptions and reference labels.
$baaRuleStrings = array(
    'image_alt' => array('Image description to review', 'Provide meaningful alternative text for informative images. If truly decorative, use an empty alternative where the editor supports it.', 'WCAG 2.2 1.1.1'),
    'image_generic' => array('Image description may be vague', 'Avoid using image, picture or filename alone as an alternative description.', 'WCAG 2.2 1.1.1'),
    'link_generic' => array('Link purpose may be unclear', 'Use descriptive link text that makes sense with its surrounding context.', 'WCAG 2.2 2.4.4'),
    'color_contrast' => array('Text colour contrast should be checked', 'This estimate assumes a white background; compare the actual foreground and background. Normal text generally needs 4.5:1 for AA.', 'WCAG 2.2 1.4.3'),
    'text_size' => array('Very small text needs review', 'Avoid reducing the legibility of text; test resizing and zoom.', 'WCAG 2.2 1.4.4'),
    'media_captions' => array('Media alternatives need review', 'Check captions, transcripts and audio description as appropriate for the media type.', 'WCAG 2.2 1.2.1–1.2.5'),
    'table_structure' => array('Table structure needs review', 'Use tables for tabular data, and ensure header relationships are available to assistive technology.', 'WCAG 2.2 1.3.1'),
    'attachment_alt' => array('Attached media needs review', 'Confirm attached images have useful text alternatives and documents are accessible.', 'WCAG 2.2 1.1.1 / 1.3.1'),
    'motion' => array('Movement or flashing needs review', 'Avoid flashing content and provide pause/stop controls for moving information.', 'WCAG 2.2 2.2.2 / 2.3.1'),
    'input_truncated' => array('Only part of the content was checked', 'The checker has a size limit. Content beyond the limit has NOT been reviewed. Check shorter sections separately.', 'Review scope'),
);
foreach ($baaRuleStrings as $baaId => $baaParts)
{
    $txt['baa_rule_' . $baaId . '_title'] = $baaParts[0];
    $txt['baa_rule_' . $baaId . '_detail'] = $baaParts[1];
    $txt['baa_rule_' . $baaId . '_wcag'] = $baaParts[2];
}
unset($baaRuleStrings, $baaId, $baaParts);

$txt['baa_on_white'] = 'on white';

// Version 1.3.0: all strings are localizable; no third-party services are required.
$txt['baa_quick_title'] = 'Accessibility options';
$txt['baa_reading_title'] = 'Focus reading mode';
$txt['baa_live_label'] = 'Check as you write (optional)';
$txt['baa_preset_label'] = 'Reading preset';
$txt['baa_jump_label'] = 'Find in editor';
$txt['baa_alt_help'] = 'Image alternatives: use [img alt="A description"]URL[/img] where supported. Describe what matters in context, not simply "image".';
$txt['baa_live_setting'] = 'Enable optional live composer suggestions';
$txt['baa_quick_setting'] = 'Enable floating accessibility options button';
$txt['baa_reading_setting'] = 'Enable focus reading mode';
$txt['baa_diagnostics_setting'] = 'Show read-only administration diagnostics';
$txt['baa_diagnostics_title'] = 'Installation diagnostics';
$txt['baa_diagnostics_help'] = 'Configuration and environment details only. These results are not WCAG compliance tests. Theme testing and assistive-technology checks remain necessary.';
$txt['baa_preview'] = 'Sample text preview';
$txt['baa_line_height'] = 'Increased line height';
$txt['baa_wide_spacing'] = 'Extra paragraph spacing';
$txt['baa_narrow'] = 'Narrower reading width';
$txt['baa_serif'] = 'Serif text for main content';
$txt['baa_focus_reading'] = 'Focus reading mode';
$txt['baa_preset_default'] = 'Custom / default';
$txt['baa_preset_reading'] = 'Comfortable reading';
$txt['baa_preset_focus'] = 'High visibility';
$txt['baa_preset_motion'] = 'Reduced movement';
$txt['baa_admin_help'] = 'Manage the posting assistant, member reading options and integration features. All checks are advisory. No post is blocked.';
