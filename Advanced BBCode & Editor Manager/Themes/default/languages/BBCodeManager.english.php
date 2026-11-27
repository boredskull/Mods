<?php

/**
 * Advanced BBCode & Editor Manager: English strings.
 *
 * @package BBCodeManager
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

$txt['bbm_title'] = 'BBCode & Editor Manager';
$txt['bbm_desc'] = 'Create custom BBCodes, design toolbars, and choose which formatting each membergroup and board can use.';
$txt['bbm_credits'] = 'Advanced BBCode &amp; Editor Manager %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
$txt['bbm_not_found'] = 'That item could not be found.';

// Tabs
$txt['bbm_tab_tags'] = 'Custom BBCodes';
$txt['bbm_tab_library'] = 'Library';
$txt['bbm_tab_profiles'] = 'Editor profiles';
$txt['bbm_tab_rules'] = 'Assignments';
$txt['bbm_tab_buttons'] = 'Button tooltips';
$txt['bbm_tab_transfer'] = 'Import / export';
$txt['bbm_tab_history'] = 'History';
$txt['bbm_tab_diagnostics'] = 'Diagnostics';
$txt['bbm_tab_settings'] = 'Settings';

// Common
$txt['bbm_yes'] = 'Yes';
$txt['bbm_no'] = 'No';
$txt['bbm_edit'] = 'Edit';
$txt['bbm_delete'] = 'Delete';
$txt['bbm_remove'] = 'Remove';
$txt['bbm_save'] = 'Save';
$txt['bbm_cancel'] = 'Cancel';
$txt['bbm_enabled'] = 'Enabled';
$txt['bbm_disabled'] = 'Disabled';
$txt['bbm_default'] = 'Default';
$txt['bbm_any'] = 'Any';
$txt['bbm_current'] = 'Current';
$txt['bbm_download'] = 'Download';
$txt['bbm_move_up'] = 'Move up';
$txt['bbm_move_down'] = 'Move down';
$txt['bbm_fix_errors'] = 'Please fix the following:';
$txt['bbm_guests'] = 'Guests';
$txt['bbm_regular_members'] = 'Regular members';

// Columns
$txt['bbm_col_tag'] = 'Tag';
$txt['bbm_col_title'] = 'Title';
$txt['bbm_col_usage'] = 'Usage';
$txt['bbm_col_button'] = 'Button';
$txt['bbm_col_status'] = 'Status';
$txt['bbm_col_actions'] = 'Actions';
$txt['bbm_col_name'] = 'Name';
$txt['bbm_col_toolbar'] = 'Toolbar';
$txt['bbm_col_allowed'] = 'Allowed tags';
$txt['bbm_col_rules'] = 'Assignments';
$txt['bbm_col_priority'] = 'Priority';
$txt['bbm_col_profile'] = 'Profile';
$txt['bbm_col_where'] = 'Where';
$txt['bbm_col_boards'] = 'Boards';
$txt['bbm_col_groups'] = 'Membergroups';
$txt['bbm_col_default_tooltip'] = 'Current label';
$txt['bbm_col_custom_tooltip'] = 'Custom tooltip';
$txt['bbm_col_when'] = 'When';
$txt['bbm_col_who'] = 'By';
$txt['bbm_col_change'] = 'Change';
$txt['bbm_col_contents'] = 'Contents';

// Tags list
$txt['bbm_tags_intro'] = 'Custom BBCodes work like the built-in ones. Each has an HTML template, optional typed options, and its own toolbar button. Disabled tags are kept but not parsed.';
$txt['bbm_add_tag'] = 'Add custom BBCode';
$txt['bbm_edit_tag'] = 'Edit [%1$s]';
$txt['bbm_browse_library'] = 'Browse the library';
$txt['bbm_no_tags'] = 'No custom BBCodes yet. Add one, or start from the library.';
$txt['bbm_conflict_short'] = 'Name clash with another tag';
$txt['bbm_core_disabled_short'] = 'Disabled in core BBCode settings';
$txt['bbm_confirm_delete_tag'] = 'Delete [%1$s]? Posts that use it will show the tag as plain text. You can undo this from History.';

// Tag form
$txt['bbm_field_tag'] = 'Tag name';
$txt['bbm_field_tag_desc'] = 'Letters and numbers, starting with a letter. Up to 30 characters.';
$txt['bbm_field_title'] = 'Title';
$txt['bbm_field_description'] = 'Description';
$txt['bbm_field_mode'] = 'Content';
$txt['bbm_mode_parsed'] = 'Formatted content';
$txt['bbm_mode_parsed_desc'] = '[tag]…[/tag], and other BBCode inside still works.';
$txt['bbm_mode_unparsed'] = 'Plain content';
$txt['bbm_mode_unparsed_desc'] = '[tag]…[/tag], and the content is shown exactly as typed.';
$txt['bbm_mode_closed'] = 'No content';
$txt['bbm_mode_closed_desc'] = 'A single [tag] that inserts the template.';
$txt['bbm_field_block'] = 'Block element';
$txt['bbm_field_block_desc'] = 'Tick for boxes and panels that sit on their own line. Untick for inline elements such as highlights.';
$txt['bbm_field_enabled'] = 'Enabled';
$txt['bbm_field_enabled_desc'] = 'Untick to keep this tag as a draft until it is ready.';
$txt['bbm_options'] = 'Options';
$txt['bbm_options_desc'] = 'Options are typed in the tag, e.g. [box=red|My title]. Separate several options with |. Every value is checked against its type and escaped before use. An invalid or missing value uses the default.';
$txt['bbm_add_option'] = 'Add option';
$txt['bbm_param_name'] = 'Placeholder';
$txt['bbm_param_type'] = 'Type';
$txt['bbm_param_default'] = 'Default';
$txt['bbm_param_required'] = 'Required';
$txt['bbm_param_min'] = 'Minimum';
$txt['bbm_param_max'] = 'Maximum';
$txt['bbm_param_maxlen'] = 'Max length';
$txt['bbm_param_choices'] = 'Choices (one per line)';
$txt['bbm_type_text'] = 'Text';
$txt['bbm_type_number'] = 'Number';
$txt['bbm_type_color'] = 'Colour';
$txt['bbm_type_url'] = 'Web address';
$txt['bbm_type_choice'] = 'Choice list';
$txt['bbm_template'] = 'HTML template';
$txt['bbm_template_desc'] = 'Use {content} where the content goes, and {name} for each option. Click a placeholder to insert it.';
$txt['bbm_template_help'] = 'For safety, scripts, iframes, forms, styles, event handlers (onclick and so on), javascript: and data: links, url() and HTML entities are not allowed. Inside style attributes use only colour, number or choice options; inside href and src use web address options. Ready-made classes: bbm-box, bbm-box-title, bbm-box-body, bbm-alert (with bbm-alert-info / -success / -warning / -danger / -note), bbm-panel, bbm-badge, bbm-columns, bbm-kbd and bbm-mark.';
$txt['bbm_start_from'] = 'Start from a design:';
$txt['bbm_starter_box'] = 'Box';
$txt['bbm_starter_alert'] = 'Alert';
$txt['bbm_starter_titled'] = 'Titled box';
$txt['bbm_starter_collapsible'] = 'Expandable panel';
$txt['bbm_starter_badge'] = 'Badge';
$txt['bbm_starter_columns'] = 'Columns';
$txt['bbm_insert_placeholder'] = 'Insert placeholder';
$txt['bbm_live_preview'] = 'Live preview';
$txt['bbm_sample_option'] = 'Example options';
$txt['bbm_sample_option_desc'] = 'What goes after the =, e.g. red|My title. Leave blank to use the defaults.';
$txt['bbm_sample_content_label'] = 'Example content';
$txt['bbm_sample_content'] = 'Example content with [b]bold[/b] text.';
$txt['bbm_usage'] = 'Usage:';
$txt['bbm_preview_problems'] = 'The preview will appear once these are fixed:';
$txt['bbm_toolbar_button'] = 'Toolbar button';
$txt['bbm_field_button'] = 'Show a toolbar button';
$txt['bbm_field_tooltip'] = 'Tooltip';
$txt['bbm_field_tooltip_desc'] = 'Shown on hover and in the command palette. Defaults to the title.';
$txt['bbm_field_shortcut'] = 'Keyboard shortcut';
$txt['bbm_field_shortcut_desc'] = 'For example ctrl+shift+1 or alt+i. Common browser shortcuts are not allowed.';
$txt['bbm_field_icon'] = 'Icon';
$txt['bbm_field_icon_desc'] = 'PNG, GIF, JPEG or WebP up to 100 KB. It is resized to 32px and saved as PNG. Without an icon, the button shows the tag\'s first letters.';
$txt['bbm_remove_icon'] = 'Remove icon';
$txt['bbm_save_tag'] = 'Save BBCode';

// Tag errors
$txt['bbm_err_tag_name'] = 'The tag name must start with a letter and contain only letters and numbers (up to 30).';
$txt['bbm_err_tag_exists'] = 'You already have a custom tag called [%1$s].';
$txt['bbm_err_tag_conflict'] = '[%1$s] is already defined by SMF or another mod. Choose a different name.';
$txt['bbm_err_title'] = 'Please enter a title.';
$txt['bbm_err_closed_params'] = 'Tags without content can\'t have options.';
$txt['bbm_err_too_many_params'] = 'A tag can have at most %1$d options.';
$txt['bbm_err_param_name'] = 'Option name "%1$s" is not valid. Use lowercase letters, numbers and underscores, starting with a letter ("content" is reserved).';
$txt['bbm_err_param_dupe'] = 'There are two options called "%1$s".';
$txt['bbm_err_choices'] = 'The choice option "%1$s" needs at least one choice.';
$txt['bbm_err_param_default'] = 'The default for "%1$s" is not a valid %2$s value.';
$txt['bbm_err_template_empty'] = 'The template is empty.';
$txt['bbm_err_template_long'] = 'The template is too long (20,000 characters maximum).';
$txt['bbm_err_closed_content'] = 'Tags without content can\'t use {content}.';
$txt['bbm_err_content_once'] = 'The template must contain {content} exactly once.';
$txt['bbm_err_unknown_placeholder'] = 'The template uses {%1$s}, but there is no option with that name.';
$txt['bbm_err_template_unsafe'] = 'The template contains unsafe or unsupported HTML, CSS, attributes, or links. Use balanced HTML tags and quoted attributes with http(s) URLs.';
$txt['bbm_err_placeholder_tagname'] = 'Placeholders can\'t be used as HTML tag names.';
$txt['bbm_err_placeholder_unquoted'] = '%1$s is used inside an HTML tag but not inside a quoted attribute value.';
$txt['bbm_err_content_in_attr'] = '{content} can\'t be used inside an HTML attribute.';
$txt['bbm_err_style_type'] = 'Option "%1$s" is used in a style attribute, so it must be a colour, number or choice (with simple values) option.';
$txt['bbm_err_url_type'] = 'Option "%1$s" is used in a %2$s attribute, so it must be a web address option placed at the start of the value, or come after a fixed https://domain/ prefix.';
$txt['bbm_err_shortcut'] = 'The shortcut must be one or more of ctrl, alt, shift or meta, plus a letter, number or F1–F12, e.g. ctrl+shift+1.';
$txt['bbm_err_shortcut_reserved'] = '%1$s is a common browser or editor shortcut. Please choose another.';
$txt['bbm_err_icon_upload'] = 'The icon could not be uploaded.';
$txt['bbm_err_icon_size'] = 'The icon must be 100 KB or smaller.';
$txt['bbm_err_icon_type'] = 'The icon must be a PNG, GIF, JPEG or WebP image.';
$txt['bbm_err_icon_dimensions'] = 'The icon must be no larger than 512×512 pixels.';
$txt['bbm_err_icon_dir'] = 'The icon folder (Themes/default/images/bbm) is not writable.';
$txt['bbm_err_icon_gd'] = 'Without the GD extension only PNG icons can be used.';

// Library
$txt['bbm_library_intro'] = 'Ready-made BBCodes you can add with one click, then customise. Previews use your theme\'s colours.';
$txt['bbm_add_to_forum'] = 'Add';
$txt['bbm_installed'] = 'Added';
$txt['bbm_library_added'] = '[%1$s] was added. You can customise it under Custom BBCodes.';
$txt['bbm_simple_profile_name'] = 'Simplified member editor';
$txt['bbm_simple_profile_desc'] = 'An editor profile with a single row of basic buttons and only everyday tags allowed. Assign it to Regular members to give them a cleaner editor.';
$txt['bbm_simple_profile_added'] = 'The simplified profile was created. Next, add an assignment to choose who gets it.';

// Profiles
$txt['bbm_profiles_intro'] = 'A profile sets the toolbar layout and which tags may be used. Assignments decide who gets which profile, in which boards. A profile\'s allowed-tags list is enforced when posting, so removing a button is not the only protection.';
$txt['bbm_add_profile'] = 'Add profile';
$txt['bbm_edit_profile'] = 'Edit profile: %1$s';
$txt['bbm_manage_rules'] = 'Manage assignments';
$txt['bbm_no_profiles'] = 'No profiles yet. Without one, everyone gets SMF\'s normal editor plus your custom buttons.';
$txt['bbm_n_buttons'] = '%1$d buttons';
$txt['bbm_smf_layout'] = 'SMF layout';
$txt['bbm_all_tags'] = 'All tags';
$txt['bbm_n_tags'] = '%1$d tags';
$txt['bbm_try'] = 'Try it';
$txt['bbm_try_desc'] = 'Use this profile in your own editor until you stop';
$txt['bbm_stop_trying'] = 'Stop trying';
$txt['bbm_trying_now'] = 'You are now using "%1$s" in your own editor. Open any post form to see it.';
$txt['bbm_trying_banner'] = 'Preview mode: your editor is using the profile <strong>%1$s</strong>. Other members are not affected.';
$txt['bbm_try_after'] = 'After saving, try this profile in my editor';
$txt['bbm_make_default'] = 'Make default';
$txt['bbm_confirm_delete_profile'] = 'Delete the profile "%1$s" and its assignments?';
$txt['bbm_field_default'] = 'Default profile';
$txt['bbm_field_default_desc'] = 'Used when no assignment matches.';
$txt['bbm_toolbar_layout'] = 'Toolbar layout';
$txt['bbm_toolbar_layout_desc'] = 'Drag buttons into the rows, or click a button to add it to the last row. Drag a separator to start a new button group. Leave every row empty to keep SMF\'s layout (custom buttons are added at the end).';
$txt['bbm_builder_pool'] = 'Available buttons';
$txt['bbm_builder_row'] = 'Row';
$txt['bbm_builder_add_row'] = 'Add row';
$txt['bbm_builder_separator'] = 'Separator';
$txt['bbm_builder_empty'] = 'Keyboard: Enter adds a button, Delete removes it, and Alt+Left/Right moves it.';
$txt['bbm_builder_filter'] = 'Filter…';
$txt['bbm_builder_reset'] = 'Use SMF layout';
$txt['bbm_allowed_tags'] = 'Allowed tags';
$txt['bbm_allowed_tags_desc'] = 'Tags that aren\'t allowed are removed (their content is kept) when a post, PM or signature is saved, previewed or quick-edited. Their buttons are hidden too.';
$txt['bbm_allow_all'] = 'Allow every tag';
$txt['bbm_select_all'] = 'Select all';
$txt['bbm_select_none'] = 'Select none';
$txt['bbm_own_tags'] = 'Custom BBCodes';
$txt['bbm_other_tags'] = 'SMF and other mods';
$txt['bbm_enforce_note'] = 'Existing posts are not changed. Restrictions apply the next time someone saves.';
$txt['bbm_save_profile'] = 'Save profile';
$txt['bbm_err_profile_name'] = 'Please enter a profile name.';

// Rules
$txt['bbm_rules_intro'] = 'Assignments are checked from the highest priority down, and the first match wins. If nothing matches, the default profile is used. Board moderators count as the "Moderator" group in the boards they moderate.';
$txt['bbm_add_rule'] = 'Add assignment';
$txt['bbm_edit_rule'] = 'Edit assignment';
$txt['bbm_no_rules'] = 'No assignments yet.';
$txt['bbm_rule_desc'] = 'Leave a section empty to match everything.';
$txt['bbm_rule_need_profile'] = 'Create a profile first.';
$txt['bbm_rule_saved'] = 'Assignment saved.';
$txt['bbm_confirm_delete_rule'] = 'Delete this assignment?';
$txt['bbm_where_desc'] = 'Where the editor is used.';
$txt['bbm_groups_desc'] = 'Matches if the member is in any ticked group (primary or additional).';
$txt['bbm_boards_desc'] = 'For posts only. An assignment with boards ticked never matches PMs or signatures.';
$txt['bbm_priority_desc'] = 'Higher numbers are checked first.';
$txt['bbm_save_rule'] = 'Save assignment';
$txt['bbm_context_post'] = 'Posts';
$txt['bbm_context_pm'] = 'Personal messages';
$txt['bbm_context_signature'] = 'Signatures';

// Buttons
$txt['bbm_buttons_intro'] = 'Change the hover text of any toolbar button, including buttons from SMF and other mods. Leave a box empty to keep the current text. The list grows as editors are opened.';
$txt['bbm_buttons_custom_note'] = 'Tooltips for your custom BBCodes are set on each BBCode.';
$txt['bbm_tooltips_saved'] = 'Tooltips saved.';

// Transfer
$txt['bbm_export'] = 'Export';
$txt['bbm_export_desc'] = 'Download your custom BBCodes (icons included), profiles, assignments, tooltips and settings as one JSON file.';
$txt['bbm_download_export'] = 'Download configuration';
$txt['bbm_import'] = 'Import';
$txt['bbm_import_desc'] = 'Load a configuration exported from this or another forum. The parts you tick are replaced, and everything is checked again as it is imported. The current state is saved in History first, so you can undo an import. Assignments refer to boards and groups by ID, so check them after importing from another forum.';
$txt['bbm_import_file'] = 'Configuration file';
$txt['bbm_import_parts'] = 'Replace';
$txt['bbm_part_tags'] = 'Custom BBCodes';
$txt['bbm_part_profiles'] = 'Profiles and assignments';
$txt['bbm_part_tooltips'] = 'Button tooltips';
$txt['bbm_part_settings'] = 'Settings';
$txt['bbm_import_button'] = 'Import';
$txt['bbm_confirm_import'] = 'Replace the ticked parts of your configuration?';
$txt['bbm_import_no_file'] = 'No file was uploaded.';
$txt['bbm_import_too_big'] = 'The file is larger than 4 MB.';
$txt['bbm_import_no_parts'] = 'Tick at least one part to import.';
$txt['bbm_import_invalid'] = 'This is not a BBCode & Editor Manager configuration file.';
$txt['bbm_import_aborted'] = 'Import cancelled. Your existing configuration was not replaced.';
$txt['bbm_import_bad_tags'] = 'The imported custom BBCode list is invalid or missing.';
$txt['bbm_import_bad_profiles'] = 'The imported editor profiles are invalid or missing.';
$txt['bbm_import_bad_rules'] = 'The imported profile assignments are invalid.';
$txt['bbm_import_bad_tooltips'] = 'The imported button tooltips are invalid.';
$txt['bbm_import_bad_settings'] = 'The imported settings are invalid or incomplete.';
$txt['bbm_import_empty_tags'] = 'Importing an empty tag library would remove the existing custom BBCodes. This import was refused.';
$txt['bbm_import_empty_profiles'] = 'Importing an empty profile list would remove existing editor profiles. This import was refused.';
$txt['bbm_import_missing_icon'] = 'A revision references an icon file that is no longer present.';
$txt['bbm_import_failed'] = 'Import rolled back following a database error: %s';
$txt['bbm_import_done'] = 'Import finished.';
$txt['bbm_import_skipped'] = 'Skipped [%1$s]: %2$s';
$txt['bbm_import_tags'] = '%1$d custom BBCodes imported.';
$txt['bbm_import_profiles'] = '%1$d profiles and %2$d assignments imported.';

// History
$txt['bbm_history_intro'] = 'Every change is recorded. Restoring a revision replaces the current BBCodes, profiles, assignments, tooltips and settings, and is itself recorded, so it can be undone too.';
$txt['bbm_no_history'] = 'No changes recorded yet.';
$txt['bbm_restore'] = 'Restore';
$txt['bbm_confirm_restore'] = 'Restore this revision? The current configuration will be replaced (it stays in History).';
$txt['bbm_restored'] = 'Restored the revision from %1$s.';
$txt['bbm_rev_counts'] = '%1$d tags, %2$d profiles, %3$d assignments';

// Revision notes
$txt['bbm_log_initial'] = 'Initial configuration';
$txt['bbm_log_added'] = 'Added [%1$s]';
$txt['bbm_log_updated'] = 'Updated [%1$s]';
$txt['bbm_log_deleted'] = 'Deleted [%1$s]';
$txt['bbm_log_enabled'] = 'Enabled [%1$s]';
$txt['bbm_log_disabled'] = 'Disabled [%1$s]';
$txt['bbm_log_profile_saved'] = 'Saved profile "%1$s"';
$txt['bbm_log_profile_deleted'] = 'Deleted profile "%1$s"';
$txt['bbm_log_profile_default'] = 'Changed the default profile';
$txt['bbm_log_simple_profile'] = 'Added the simplified member profile';
$txt['bbm_log_rule_saved'] = 'Saved assignment for "%1$s"';
$txt['bbm_log_rule_deleted'] = 'Deleted an assignment';
$txt['bbm_log_tooltips'] = 'Updated button tooltips';
$txt['bbm_log_imported'] = 'Imported configuration';
$txt['bbm_log_restored'] = 'Restored revision from %1$s';
$txt['bbm_log_settings'] = 'Updated settings';

// Diagnostics
$txt['bbm_diagnostics_intro'] = 'Checks for name clashes, template problems, unused profiles, other mods that use the same editor hooks, and server requirements.';
$txt['bbm_diag_server_title'] = 'Server';
$txt['bbm_diag_tags_title'] = 'Custom BBCodes';
$txt['bbm_diag_profiles_title'] = 'Profiles and assignments';
$txt['bbm_diag_hooks_title'] = 'Other mods using the same hooks';
$txt['bbm_diag_hooks_desc'] = 'These mods also change BBCode or the editor. If buttons or tags behave unexpectedly, check them first.';
$txt['bbm_diag_foreign_title'] = 'Tags provided by SMF and other mods';
$txt['bbm_diag_foreign_desc'] = 'Custom BBCodes can\'t reuse these names.';
$txt['bbm_diag_none'] = 'None';
$txt['bbm_diag_php'] = 'PHP %1$s';
$txt['bbm_diag_gd'] = 'GD extension (re-draws uploaded icons for safety)';
$txt['bbm_diag_icons'] = 'Icon folder Themes/default/images/bbm is writable';
$txt['bbm_diag_cache'] = 'SMF caching is on (recommended, the configuration is cached)';
$txt['bbm_diag_enabled'] = 'The manager is enabled';
$txt['bbm_diag_conflict'] = '[%1$s] has the same name as a tag from SMF or another mod. Both will run, which gives unpredictable results. Rename or delete it.';
$txt['bbm_diag_core_disabled'] = '[%1$s] is disabled in Admin &gt; Posts and Topics &gt; BBCode, so it won\'t be parsed.';
$txt['bbm_diag_template'] = '[%1$s] template: %2$s';
$txt['bbm_diag_shortcut'] = 'Shortcut %1$s is used by both [%2$s] and [%3$s].';
$txt['bbm_diag_tags_ok'] = 'No problems found.';
$txt['bbm_diag_rule_boards'] = 'Assignment #%1$d refers to boards that no longer exist.';
$txt['bbm_diag_rule_groups'] = 'Assignment #%1$d refers to membergroups that no longer exist.';
$txt['bbm_diag_profile_unused'] = 'Profile "%1$s" is not the default and has no assignments, so nobody gets it.';
$txt['bbm_diag_profiles_ok'] = 'No problems found.';

// Settings
$txt['bbm_enabled'] = 'Enabled';
$txt['bbm_admin_bypass'] = 'Administrators may use every tag';
$txt['bbm_admin_bypass_desc'] = 'Administrators keep the toolbar layout of their profile, but tags are never removed from their posts.';
$txt['bbm_wysiwyg'] = 'Show custom tags in WYSIWYG mode';
$txt['bbm_wysiwyg_desc'] = 'Shows custom tags as labelled blocks in the visual editor, so they survive switching between visual and source mode.';
$txt['bbm_palette'] = 'Command palette';
$txt['bbm_palette_desc'] = 'Adds a searchable list of every toolbar command (button or Alt+/).';
$txt['bbm_mobile_toolbar'] = 'Mobile-friendly toolbar';
$txt['bbm_mobile_toolbar_desc'] = 'On small or touch screens the toolbar scrolls sideways and has bigger buttons.';
$txt['bbm_revisions_keep'] = 'Revisions to keep';
$txt['bbm_custom_css'] = 'Custom CSS';
$txt['bbm_custom_css_desc'] = 'Extra CSS for your custom BBCodes, loaded on every page. The &lt; character is removed.';

// Editor
$txt['bbm_palette_search'] = 'Search commands…';
$txt['bbm_palette_none'] = 'No matching commands';
$txt['bbm_palette_hint'] = '↑ ↓ to choose · Enter to run · Esc to close';
