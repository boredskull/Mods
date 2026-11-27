<?php
$txt['bsap_title']='Anonymous Posting';
$txt['bsap_admin_desc']='Configure anonymous posting and select the boards where it is allowed.';

$txt['bsap_enabled']='Enable anonymous posting';
$txt['bsap_boards']='Allowed boards';
$txt['bsap_force_boards']='Forced anonymous boards';
$txt['bsap_enabled_desc']='Allow permitted members to submit posts anonymously in the configured boards.';
$txt['bsap_boards_desc']='Select boards where anonymous posting is available.';
$txt['bsap_force_boards_desc']='Select boards where permitted posts must be anonymous. Each board must also be selected in Allowed boards.';

$txt['bsap_post_anonymously']='Post anonymously';
$txt['bsap_disclaimer']='Your public post is anonymous, but authorised staff can reveal your identity. Server and security logs may identify you. Do not include identifying details.';
$txt['error_bsap_not_allowed']='You are not permitted to post anonymously on this board.';
$txt['error_bsap_no_attachments']='Attachments are not supported on anonymous posts.';
$txt['error_bsap_unsupported']='Anonymous posting does not support attachments, polls, calendar events, announcements, topic-moving, locking or stickying actions.';
$txt['error_bsap_unavailable']='Anonymous posting is temporarily unavailable.';
$txt['bsap_failed_safely']='Anonymous posting failed safely. Please try again later.';
$txt['bsap_tx_required']='Anonymous posting is unavailable: transactional database support is required.';
$txt['bsap_tx_begin']='Anonymous posting is unavailable: could not begin a transaction.';
$txt['bsap_verify_failed']='Anonymous posting could not verify the author mapping.';
$txt['bsap_cannot_proceed']='Anonymous posting cannot proceed safely. No post has been published.';
$txt['bsap_bad_schema']='Anonymous posting is unavailable: incompatible message schema.';
$txt['bsap_attribution_failed']='Anonymous post attribution could not be saved.';
$txt['bsap_bad_boards']='Forced boards must also be enabled.';
$txt['permissionname_bsap_use']='Post anonymously';
$txt['permissionname_bsap_reveal']='Reveal anonymous post authors';
$txt['permissionhelp_bsap_use']='Allows members to post anonymously on boards where anonymous posting is enabled. Guests can never use this.';
$txt['permissionhelp_bsap_reveal']='Allows staff to reveal the real author of an anonymous post. Every reveal is recorded in the audit log.';

// Staff-only, audited author reveal.
$txt['bsap_reveal_button'] = 'Reveal author (logged)';
$txt['bsap_reveal_title'] = 'Anonymous author reveal';
$txt['bsap_reveal_confirm_title'] = 'Confirm anonymous author reveal';
$txt['bsap_reveal_confirm_text'] = 'Revealing this author is restricted to authorised staff and will be recorded in the audit log. Continue?';
$txt['bsap_reveal_confirm_button'] = 'Reveal and log author';
$txt['bsap_reveal_audit_failed'] = 'Author attribution cannot be disclosed while auditing is unavailable.';
$txt['bsap_reveal_message'] = 'Message';
$txt['bsap_reveal_logged'] = 'This disclosure has been recorded in the private audit log.';
$txt['bsap_deleted_member'] = 'Deleted member';
$txt['bsap_cancel'] = 'Cancel';

$txt['bsap_audit_title'] = 'Anonymous Posting - reveal audit';
$txt['bsap_audit_desc'] = 'Restricted to administrators. Displays who revealed an author, the message and the date, without disclosing the author again. Records for permanently deleted messages are purged.';
$txt['bsap_purge'] = 'Purge deleted-message records (up to 1,000 per table)';
$txt['bsap_audit_post'] = 'Message';
$txt['bsap_audit_viewer'] = 'Revealed by';
$txt['bsap_audit_time'] = 'Date';
$txt['bsap_audit_empty'] = 'No reveal records on this page.';
$txt['bsap_previous'] = 'Previous';
$txt['bsap_next'] = 'Next';
$txt['bsap_credits'] = 'Anonymous Posting %s %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
