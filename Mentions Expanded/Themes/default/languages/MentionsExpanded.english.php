<?php
// Version: 2.1; MentionsExpanded

global $txt;

$txt['mentions_expanded_title'] = 'Mentions Expanded';
$txt['mentions_expanded_credits'] = 'Mentions Expanded %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
$txt['mentions_expanded_by'] = 'by';

// Settings
$txt['mentions_expanded_core_off'] = 'SMF\'s core mentions feature is disabled. Enable it under Admin &raquo; Configuration &raquo; Features and Options before Mentions Expanded will do anything.';
$txt['mentions_expanded_enabled'] = 'Enable Mentions Expanded';
$txt['mentions_expanded_everyone'] = 'Allow @everyone';
$txt['mentions_expanded_everyone_desc'] = 'Notifies every activated member who can see the board.';
$txt['mentions_expanded_here'] = 'Allow @here';
$txt['mentions_expanded_here_desc'] = 'Notifies members who are online when the post is made.';
$txt['mentions_expanded_here_minutes'] = 'Count members as online if active within';
$txt['mentions_expanded_here_minutes_desc'] = 'Between 1 and 1440.';
$txt['mentions_expanded_groups_enabled'] = 'Allow @groupname';
$txt['mentions_expanded_groups_enabled_desc'] = 'Type @ followed by the exact group name, e.g. @Global Moderator.';
$txt['mentions_expanded_groups'] = 'Mentionable membergroups';
$txt['mentions_expanded_groups_desc'] = 'Only ticked groups can be mentioned. Post-count groups are not listed.';
$txt['mentions_expanded_no_groups'] = 'No membergroups found.';
$txt['mentions_expanded_highlight'] = 'Highlight @everyone, @here and group mentions in posts';
$txt['mentions_expanded_email'] = 'Also send emails';
$txt['mentions_expanded_email_desc'] = 'Only to members whose mention alert preference includes email. Think twice on big forums.';
$txt['mentions_expanded_batch'] = 'Members processed per background batch';
$txt['mentions_expanded_batch_desc'] = 'Between 50 and 2000. Lower it if your host is slow.';

// Permissions
$txt['permissionname_mention_everyone'] = 'Use @everyone';
$txt['permissionhelp_mention_everyone'] = 'Lets members notify everyone who can see the board by writing @everyone.';
$txt['permissionname_mention_here'] = 'Use @here';
$txt['permissionhelp_mention_here'] = 'Lets members notify everyone currently online by writing @here.';
$txt['permissionname_mention_groups'] = 'Use @groupname';
$txt['permissionhelp_mention_groups'] = 'Lets members notify a whole membergroup by writing @ followed by its name.';

// Email
$txt['mentions_expanded_email_subject'] = 'You were mentioned in: %1$s';
$txt['mentions_expanded_email_body'] = 'Hello,

%1$s mentioned you in the post "%2$s".

View the post here:
%3$s

Regards,
The %4$s Team';
