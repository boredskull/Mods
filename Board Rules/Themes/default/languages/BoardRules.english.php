<?php
/**
 * Board Rules - English language strings
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 */

// Credits page (Modifications section). %s = mod version.
$txt['boardrules_credits'] = 'Board Rules %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';

// General
$txt['boardrules_title'] = 'Forum Rules';
$txt['boardrules_menu'] = 'Rules';
$txt['whoall_rules'] = 'Reading the <a href="{scripturl}?action=rules">forum rules</a>.';

// Rules page
$txt['boardrules_filter'] = 'Search the rules...';
$txt['boardrules_expand'] = 'Expand all';
$txt['boardrules_collapse'] = 'Collapse all';
$txt['boardrules_print'] = 'Print';
$txt['boardrules_language'] = 'Language';
$txt['boardrules_lang_auto'] = 'My language';
$txt['boardrules_count'] = '%1$d rules';
$txt['boardrules_last_updated'] = 'Last updated %1$s';
$txt['boardrules_none'] = 'No rules have been published yet.';
$txt['boardrules_contents'] = 'Contents';
$txt['boardrules_penalty'] = 'Penalty';
$txt['boardrules_permalink'] = 'Link to this rule';
$txt['boardrules_applies_to'] = 'Applies to:';
$txt['boardrules_no_match'] = 'No rules match your search.';
$txt['boardrules_accept_desc'] = 'The rules have changed since you last agreed to them. Please confirm you have read and agree to the current rules.';
$txt['boardrules_accept'] = 'I have read and agree to the rules';
$txt['boardrules_thanks'] = 'Thank you - your acceptance of the current rules has been recorded.';
$txt['boardrules_forced_notice'] = 'The forum rules have been updated. Please read and accept them to continue using the forum.';
$txt['boardrules_changelog'] = 'Recent rule changes';

// Banner and board rules
$txt['boardrules_notice'] = 'The forum rules were updated on %1$s.';
$txt['boardrules_review'] = 'Review the rules';
$txt['boardrules_board_title'] = 'Rules for %1$s';
$txt['boardrules_full_rules'] = 'Read the full forum rules';

// Alerts
$txt['alert_boardrules_updated'] = 'When the forum rules are updated';
$txt['boardrules_alert_text'] = 'The <a href="%1$s">forum rules</a> have been updated: %2$s';
$txt['boardrules_default_summary'] = 'The forum rules have been updated.';

// Registration
$txt['boardrules_reg_label'] = 'Forum rules';
$txt['boardrules_reg_desc'] = 'I have read and agree to the <a href="%1$s" target="_blank" rel="noopener">forum rules</a>.';
$txt['boardrules_reg_error'] = 'You must agree to the forum rules to register.';

// Admin - menu and tabs
$txt['boardrules_admin'] = 'Board Rules';
$txt['boardrules_admin_desc'] = 'Manage your forum rules, their categories and translations, and notify members when they change.';
$txt['boardrules_admin_rules'] = 'Rules';
$txt['boardrules_admin_rules_desc'] = 'Add, edit, reorder, enable or disable individual rules. Rules can apply forum-wide or to selected boards only.';
$txt['boardrules_admin_cats'] = 'Categories';
$txt['boardrules_admin_cats_desc'] = 'Rules are grouped into categories. A category can be shown in all languages or a single language.';
$txt['boardrules_admin_changes'] = 'Changes &amp; Notifications';
$txt['boardrules_admin_changes_desc'] = 'Publish a rules update, see who has accepted the current rules and review the change history.';
$txt['boardrules_admin_settings'] = 'Settings';
$txt['boardrules_admin_settings_desc'] = 'General options for the rules page, acceptance and per-board display.';

// Admin - lists and forms
$txt['boardrules_add_rule'] = 'Add rule';
$txt['boardrules_edit_rule'] = 'Edit rule';
$txt['boardrules_add_cat'] = 'Add category';
$txt['boardrules_edit_cat'] = 'Edit category';
$txt['boardrules_view_page'] = 'View rules page';
$txt['boardrules_disabled'] = 'Disabled';
$txt['boardrules_enabled_short'] = 'Enabled';
$txt['boardrules_cat_empty'] = 'No rules in this category yet.';
$txt['boardrules_rule_title'] = 'Rule title';
$txt['boardrules_rule_body'] = 'Rule text';
$txt['boardrules_bbc_ok'] = '(BBCode allowed)';
$txt['boardrules_category'] = 'Category';
$txt['boardrules_cat_title'] = 'Category title';
$txt['boardrules_cat_desc'] = 'Description';
$txt['boardrules_rules_count'] = 'Rules';
$txt['boardrules_scope'] = 'Applies to';
$txt['boardrules_scope_desc'] = 'Leave nothing selected for a forum-wide rule. Hold Ctrl/Cmd to select several boards.';
$txt['boardrules_scope_global'] = 'Whole forum';
$txt['boardrules_status'] = 'Status';
$txt['boardrules_actions'] = 'Actions';
$txt['boardrules_lang_all'] = 'All languages';
$txt['boardrules_language_desc'] = 'Members see "All languages" items plus items in their own language (falling back to the forum default language).';
$txt['boardrules_penalty_desc'] = 'Optional, e.g. "Warning" or "Ban". Shown as a badge.';
$txt['boardrules_move_up'] = 'Move up';
$txt['boardrules_move_down'] = 'Move down';
$txt['boardrules_confirm_delete_rule'] = 'Delete this rule?';
$txt['boardrules_confirm_delete_cat'] = 'Delete this category AND all of its rules?';

// Admin - change notification
$txt['boardrules_notify_legend'] = 'Notify members';
$txt['boardrules_publish_label'] = 'This is a significant change - publish it as a rules update';
$txt['boardrules_summary'] = 'Change summary';
$txt['boardrules_send_alert'] = 'Send an alert to all members';
$txt['boardrules_publish_desc'] = 'Publishing an update shows members a "rules updated" notice (and requires them to re-accept if that option is on). Leave it unticked for typo fixes.';
$txt['boardrules_publish_title'] = 'Publish a rules update';
$txt['boardrules_publish_button'] = 'Publish update';
$txt['boardrules_confirm_publish'] = 'Publish a rules update to all members now?';
$txt['boardrules_stat_version'] = 'Version';
$txt['boardrules_stat_accepted'] = 'members up to date';
$txt['boardrules_stat_outstanding'] = 'still to review';
$txt['boardrules_by'] = 'By';
$txt['boardrules_log_empty'] = 'No rule updates have been published yet.';

// Admin - settings
$txt['boardrules_settings_general'] = 'Rules page';
$txt['boardrules_enabled'] = 'Enable Board Rules';
$txt['boardrules_menu_button'] = 'Show a "Rules" button in the main menu';
$txt['boardrules_guests_hidden'] = 'Hide the rules from guests';
$txt['boardrules_intro'] = 'Introduction text';
$txt['boardrules_intro_sub'] = 'Shown at the top of the rules page. BBCode allowed.';
$txt['boardrules_numbering'] = 'Number categories and rules (1, 1.1, 1.2 ...)';
$txt['boardrules_show_changelog'] = 'Show recent changes on the rules page';
$txt['boardrules_settings_accept'] = 'Acceptance &amp; notifications';
$txt['boardrules_reg_accept'] = 'Require new members to accept the rules when registering';
$txt['boardrules_reg_accept_sub'] = 'Adds a required checkbox to the registration form.';
$txt['boardrules_banner'] = 'Show a "rules updated" notice until members review the changes';
$txt['boardrules_force_accept'] = 'Require existing members to accept rule updates';
$txt['boardrules_force_accept_sub'] = 'Members are sent to the rules page until they accept. Administrators are never blocked.';
$txt['boardrules_alerts'] = 'Allow alerts to be sent when rules are updated';
$txt['boardrules_settings_boards'] = 'Board-specific rules';
$txt['boardrules_show_messageindex'] = 'Show board rules above the topic list';
$txt['boardrules_show_display'] = 'Show board rules above topics';
$txt['boardrules_show_post'] = 'Show board rules on the posting page';
$txt['boardrules_board_style'] = 'Board rules box';
$txt['boardrules_board_style_open'] = 'Expanded';
$txt['boardrules_board_style_collapsed'] = 'Collapsed';

// Errors
$txt['boardrules_not_found'] = 'That item could not be found.';
$txt['boardrules_need_category'] = 'Please create a category first.';
$txt['boardrules_missing_fields'] = 'Please fill in the title and the text.';
