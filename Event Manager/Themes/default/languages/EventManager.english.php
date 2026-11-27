<?php
// Event Manager by BoredSkull - English

global $txt, $helptxt;

$txt['em_title'] = 'Event Manager';
$txt['em_menu'] = 'Events';
$txt['em_credits'] = '<a href="https://boredskull.net" target="_blank" rel="noopener">Event Manager %1$s</a> &copy; 2026 BoredSkull';

// Statuses
$txt['em_status_going'] = 'Going';
$txt['em_status_maybe'] = 'Maybe';
$txt['em_status_no'] = 'Not going';
$txt['em_status_waitlist'] = 'Waiting list';
$txt['em_status_pending'] = 'Awaiting approval';

// List
$txt['em_tab_upcoming'] = 'Upcoming';
$txt['em_tab_mine'] = 'My events';
$txt['em_tab_organising'] = 'Organising';
$txt['em_tab_past'] = 'Past';
$txt['em_no_events_upcoming'] = 'There are no upcoming events.';
$txt['em_no_events_mine'] = 'You haven\'t signed up for any upcoming events.';
$txt['em_no_events_organising'] = 'You aren\'t organising any upcoming events.';
$txt['em_no_events_past'] = 'There are no past events.';
$txt['em_new_event'] = 'New event';
$txt['em_open_calendar'] = 'Calendar';
$txt['em_no_registration'] = 'No registration';
$txt['em_seats_taken'] = '%1$d of %2$d places taken';
$txt['em_seats_taken_nolimit'] = '%1$d going';
$txt['em_n_waiting'] = '%1$d on the waiting list';

// Feed
$txt['em_feed_title'] = 'Your calendar feed';
$txt['em_feed_desc'] = 'Subscribe to this address in Google Calendar, Outlook or Apple Calendar to see the events you\'re going to. Keep it private: anyone with it can see your events.';
$txt['em_feed_reset_link'] = 'Make a new address';
$txt['em_feed_reset_confirm'] = 'The old address will stop working. Continue?';
$txt['em_feed_reset'] = 'Your calendar feed has a new address. Update it in your calendar app.';
$txt['em_feed_name'] = 'My events - %1$s';

// Event page
$txt['em_when'] = 'When';
$txt['em_where'] = 'Where';
$txt['em_organiser'] = 'Organiser';
$txt['em_add_to_calendar'] = 'Add to my calendar';
$txt['em_discussion'] = 'Discussion';
$txt['em_manage'] = 'Manage';
$txt['em_registration'] = 'Registration';
$txt['em_this_cancelled'] = 'This event has been cancelled.';
$txt['em_list_going'] = 'Going';
$txt['em_list_maybe'] = 'Maybe';
$txt['em_list_waitlist'] = 'Waiting list';
$txt['em_list_pending'] = 'Awaiting approval';
$txt['em_list_hidden_attendees'] = 'The attendee list is only shown to people who have signed up.';
$txt['em_list_hidden_organisers'] = 'The attendee list is only shown to the organisers.';
$txt['em_list_hidden_members'] = 'Log in to see who is going.';
$txt['em_list_hidden_public'] = '';
$txt['em_nobody_yet'] = 'Nobody has signed up yet.';
$txt['em_plus_guests'] = '+%1$d';

// RSVP box
$txt['em_you_are'] = 'You:';
$txt['em_waitlist_position'] = '(number %1$d in the queue)';
$txt['em_going'] = 'Going';
$txt['em_update'] = 'Update';
$txt['em_request'] = 'Request a place';
$txt['em_join_waitlist'] = 'Join waiting list';
$txt['em_maybe'] = 'Maybe';
$txt['em_not_going'] = 'Not going';
$txt['em_withdraw'] = 'Remove my answer';
$txt['em_withdraw_confirm'] = 'Remove your registration? If you have a place, it will go to the next person waiting.';
$txt['em_bring_guests'] = 'Guests you\'re bringing:';
$txt['em_cancel_by'] = 'You can cancel up to %1$d hours before the start.';
$txt['em_closes_at'] = 'Registration closes %1$s.';
$txt['em_login_to_register'] = 'Log in to sign up for this event.';

// Reasons and results
$txt['em_reason_disabled'] = 'Registration isn\'t used for this event.';
$txt['em_reason_cancelled'] = 'This event has been cancelled.';
$txt['em_reason_not_open'] = 'Registration hasn\'t opened yet.';
$txt['em_reason_closed'] = 'Registration has closed.';
$txt['em_reason_no_maybe'] = 'This event doesn\'t take "maybe" answers.';
$txt['em_reason_answer_required'] = 'Please answer the organiser\'s question.';
$txt['em_reason_cancel_deadline'] = 'It\'s too late to cancel online. Please contact the organiser.';
$txt['em_reason_no_room_guests'] = 'There isn\'t room for that many guests.';
$txt['em_reason_full'] = 'Sorry, this event is full.';
$txt['em_reason_busy'] = 'Lots of people are signing up right now. Please try again in a moment.';
$txt['em_n_busy'] = '%1$d couldn\'t be added because lots of people are signing up right now. Please try again in a moment.';
$txt['em_promote_failed'] = 'That person is no longer waiting, so they weren\'t moved.';
$txt['em_promote_later'] = 'The waiting list will be checked for free places within 15 minutes.';
$txt['em_done_going'] = 'You\'re going. See you there!';
$txt['em_done_maybe'] = 'You answered maybe.';
$txt['em_done_no'] = 'You answered not going.';
$txt['em_done_waitlist'] = 'The event is full, so you\'re on the waiting list. You\'ll get an alert if a place opens up.';
$txt['em_done_pending'] = 'Your request has been sent to the organiser.';
$txt['em_done_withdrawn'] = 'Your registration was removed.';

// Manage
$txt['em_manage_title'] = 'Manage: %1$s';
$txt['em_stat_seats'] = 'Places taken';
$txt['em_stat_checked_in'] = 'Checked in';
$txt['em_settings'] = 'Registration settings';
$txt['em_settings_saved'] = 'Settings saved.';
$txt['em_n_promoted'] = '%1$d people moved from the waiting list.';
$txt['em_f_enabled'] = 'Use registration for this event';
$txt['em_f_capacity'] = 'Places';
$txt['em_f_capacity_desc'] = 'Guests count towards this. 0 = no limit.';
$txt['em_f_max_guests'] = 'Guests each member may bring';
$txt['em_f_waitlist'] = 'Waiting list';
$txt['em_f_waitlist_desc'] = 'When full, new sign-ups queue and are moved in automatically when places open.';
$txt['em_f_waitlist_max'] = 'Waiting list size';
$txt['em_zero_unlimited'] = '0 = no limit.';
$txt['em_f_approval'] = 'Organiser approval';
$txt['em_f_approval_desc'] = 'Sign-ups wait until an organiser approves them.';
$txt['em_f_allow_maybe'] = 'Allow "maybe"';
$txt['em_f_question'] = 'Question for attendees';
$txt['em_f_question_desc'] = 'E.g. dietary needs or T-shirt size. Leave empty for none.';
$txt['em_f_required'] = 'Required';
$txt['em_f_visibility'] = 'Who can see the attendee list';
$txt['em_f_opens'] = 'Registration opens';
$txt['em_f_times_desc'] = 'Times are in %1$s. Leave empty for now.';
$txt['em_f_closes'] = 'Registration closes';
$txt['em_f_closes_desc'] = 'Leave empty to close when the event starts.';
$txt['em_f_cancel_hours'] = 'Cancellation deadline (hours before)';
$txt['em_f_cancel_hours_desc'] = 'After this, people who are going can\'t cancel online. 0 = until the start.';
$txt['em_f_reminders'] = 'Reminders';
$txt['em_f_reminders_desc'] = 'Sent to everyone who is going.';
$txt['em_reminder_10080'] = '1 week before';
$txt['em_reminder_1440'] = '1 day before';
$txt['em_reminder_180'] = '3 hours before';
$txt['em_reminder_60'] = '1 hour before';
$txt['em_reminder_15'] = '15 minutes before';
$txt['em_visibility_public'] = 'Everyone';
$txt['em_visibility_members'] = 'Members';
$txt['em_visibility_attendees'] = 'People who signed up';
$txt['em_visibility_organisers'] = 'Organisers only';

$txt['em_attendees'] = 'Attendees';
$txt['em_filter'] = 'Find a member';
$txt['em_export'] = 'Download CSV';
$txt['em_view_event'] = 'View event';
$txt['em_col_member'] = 'Member';
$txt['em_col_status'] = 'Status';
$txt['em_col_guests'] = 'Guests';
$txt['em_col_note'] = 'Note';
$txt['em_col_registered'] = 'Signed up';
$txt['em_col_checked_in'] = 'Checked in';
$txt['em_yes'] = 'Yes';
$txt['em_approve'] = 'Approve';
$txt['em_decline'] = 'Decline';
$txt['em_promote'] = 'Move to going';
$txt['em_remove'] = 'Remove';
$txt['em_remove_confirm'] = 'Remove this person from the event?';
$txt['em_add_people'] = 'Add members:';
$txt['em_add_people_hint'] = 'Names, separated by commas';
$txt['em_add'] = 'Add';
$txt['em_n_added'] = '%1$d members added.';
$txt['em_none_found'] = 'No members were found with those names.';

$txt['em_message_title'] = 'Message attendees';
$txt['em_message_to'] = 'Send to:';
$txt['em_message_subject'] = 'Subject';
$txt['em_message_body'] = 'Your message';
$txt['em_send'] = 'Send personal message';
$txt['em_message_missing'] = 'Please choose who to send to and write a subject and message.';
$txt['em_message_sent'] = 'Message sent to %1$d members.';

$txt['em_cancel_title'] = 'Cancel this event';
$txt['em_cancel_desc'] = 'Everyone who signed up gets an alert. The event stays on the calendar, marked as cancelled, and registration closes.';
$txt['em_cancel_event'] = 'Cancel event';
$txt['em_cancel_confirm'] = 'Cancel this event and tell everyone who signed up?';
$txt['em_reinstate_title'] = 'This event is cancelled';
$txt['em_reinstate_desc'] = 'Reinstating it restores the title and reopens registration. Everyone who signed up gets an alert.';
$txt['em_reinstate'] = 'Reinstate event';
$txt['em_reinstate_confirm'] = 'Reinstate this event?';
$txt['em_event_cancelled'] = 'The event was cancelled.';
$txt['em_event_reinstated'] = 'The event was reinstated.';
$txt['em_cancelled_title'] = 'Cancelled: %1$s';

// Errors
$txt['em_error_not_found'] = 'That event doesn\'t exist, or you can\'t see it.';
$txt['em_error_not_allowed'] = 'Only the organisers can manage this event.';

// Time
$txt['em_in_minutes'] = 'in %1$d minutes';
$txt['em_in_hours'] = 'in %1$d hours';
$txt['em_in_days'] = 'in %1$d days';

// Reminder email
$txt['em_mail_subject'] = 'Reminder: %1$s starts %2$s';
$txt['em_mail_body'] = 'Hi %1$s,

This is a reminder that you\'re going to "%2$s".

When: %3$s
Where: %4$s

Details and your registration:
%5$s

See you there!
%6$s';

// Alerts
$txt['alert_member_em_promoted'] = 'A place opened up: you\'re now going to <a href="{event_link}">{event_title}</a>.';
$txt['alert_member_em_approved'] = 'Your place at <a href="{event_link}">{event_title}</a> was approved.';
$txt['alert_member_em_waitlisted'] = 'Your request for <a href="{event_link}">{event_title}</a> was approved, but it\'s full, so you\'re on the waiting list.';
$txt['alert_member_em_declined'] = 'Your request for <a href="{event_link}">{event_title}</a> was declined.';
$txt['alert_member_em_request'] = '{member_link} asked for a place at <a href="{event_link}">{event_title}</a>.';
$txt['alert_member_em_cancelled'] = '<a href="{event_link}">{event_title}</a> has been cancelled.';
$txt['alert_member_em_reinstated'] = '<a href="{event_link}">{event_title}</a> is back on.';
$txt['alert_member_em_reminder'] = 'Reminder: <a href="{event_link}">{event_title}</a> starts {event_when}.';

// Permissions
$txt['permissionname_em_register'] = 'Sign up for events';
$txt['permissionhelp_em_register'] = 'Answer going, maybe or not going on calendar events.';
$txt['permissionname_em_manage_any'] = 'Manage registration for any event';
$txt['permissionhelp_em_manage_any'] = 'Change settings and attendees for every event. Event creators can always manage their own events, and so can anyone who can edit any event.';

// Admin
$txt['em_admin_desc'] = 'Registration, waiting lists and reminders for calendar events. Create and edit events with the normal calendar; organisers set up registration from each event\'s Manage page.';
$txt['em_calendar_off'] = 'The calendar is turned off, so Event Manager is inactive. Turn it on in Calendar settings.';
$txt['em_enabled'] = 'Turn on Event Manager';
$txt['em_enabled_desc'] = 'Needs the SMF calendar.';
$txt['em_show_menu'] = 'Show "Events" in the main menu';
$txt['em_topic_panel'] = 'Show the sign-up box in event topics';
$txt['em_topic_panel_desc'] = 'Above the first post of a topic that has a calendar event.';
$txt['em_feeds'] = 'Personal calendar feeds';
$txt['em_feeds_desc'] = 'Members get a private address to subscribe to their events in their calendar app.';
$txt['em_defaults_title'] = 'Defaults for events';
$txt['em_default_enabled'] = 'Registration on for new events';
$txt['em_default_enabled_desc'] = 'Used until an organiser changes the event\'s settings.';
$txt['em_default_capacity'] = 'Places';
$txt['em_default_capacity_desc'] = '0 = no limit.';
$txt['em_default_waitlist'] = 'Waiting list';
$txt['em_default_maybe'] = 'Allow "maybe"';
$txt['em_default_visibility'] = 'Who can see the attendee list';
$txt['em_default_reminders'] = 'Reminders (minutes before)';
$txt['em_default_reminders_desc'] = 'Comma separated, up to five. 1440 = 1 day, 60 = 1 hour. Empty for none.';
$txt['em_notify_title'] = 'Notifications';
$txt['em_alerts'] = 'Send alerts';
$txt['em_reminder_email'] = 'Email reminders as well as alerts';
$txt['em_reminder_maybe'] = 'Remind people who answered "maybe" too';

// Scheduled task
$txt['scheduled_task_em_reminders'] = 'Event reminders';
$txt['scheduled_task_desc_em_reminders'] = 'Sends Event Manager reminders before events start.';
