[size=4][b]Event Manager[/b][/size] 1.2.1 by [url=https://boredskull.net]BoredSkull[/url]

Registration for SMF's own calendar events. Members answer [b]Going[/b], [b]Maybe[/b] or [b]Not going[/b]; organisers set limits, run a waiting list and get an attendee list. Reminders go out automatically.

[b]Built on the SMF calendar[/b]
[list]
[li]Events are still created and edited with the normal calendar (including events linked to topics). Nothing about the calendar changes.[/li]
[li]Every event gets registration straight away using the admin defaults. Organisers change it per event on the event's [i]Manage[/i] page.[/li]
[li]A new [b]Events[/b] page lists upcoming events, the member's own events, events they organise, and past events.[/li]
[li]Topics with an event show a sign-up box above the first post.[/li]
[/list]

[b]Registration[/b]
[list]
[li]RSVP buttons: going, maybe (optional), not going.[/li]
[li]Place limits, with guests (plus-ones) counted towards the limit.[/li]
[li]Waiting list, with an optional size limit. When a place opens (someone cancels, is removed, or the limit is raised), the next people in the queue are moved in and alerted. Members see their position in the queue.[/li]
[li]Organiser approval, if wanted.[/li]
[li]Registration opening and closing times, and a cancellation deadline.[/li]
[li]A question for attendees (e.g. dietary needs), optional or required.[/li]
[/list]

[b]For organisers[/b]
[list]
[li]Attendee table with approve, decline, move to going, remove and check-in (ticks save instantly).[/li]
[li]Add members by name, message attendees by personal message, download a CSV.[/li]
[li]Cancel an event: it stays on the calendar as "Cancelled: ..." and everyone who signed up is alerted. It can be reinstated.[/li]
[li]Choose who sees the attendee list: everyone, members, people who signed up, or organisers only.[/li]
[/list]
The event's creator manages it, as do members who can edit any calendar event and those with the new [i]Manage registration for any event[/i] permission.

[b]Reminders and calendars[/b]
[list]
[li]Reminders 1 week, 1 day, 3 hours, 1 hour or 15 minutes before, by alert and (optionally) email. A scheduled task checks every 15 minutes.[/li]
[li]"Add to my calendar" downloads an .ics file for any event.[/li]
[li]Each member gets a private calendar feed address for Google Calendar, Outlook or Apple Calendar.[/li]
[/list]

[b]Settings[/b]
Admin > Configuration > Event Manager. Permissions: [i]Sign up for events[/i] (given to regular members on install) and [i]Manage registration for any event[/i], under Calendar in the permissions page.

The calendar must be turned on.

[b]Uninstalling[/b]
Calendar events are kept. Tick "Remove all data associated with this modification" to delete registrations and settings.

[b]New in 1.1.0[/b] (fixes)
[list]
[li][b]Calendar feeds fail safely[/b]: a feed is refused if the member's board access can't be checked, and also if they're banned, not activated or no longer allowed to see the calendar. Before, events from every board could be included if SMF's access helper wasn't available.[/li]
[li][b]No more overbooking[/b]: counting places and saving a sign-up now happen under a per-event database lock, so two people can't both take the last place at the same moment.[/li]
[li][b]Late sign-ups get sensible reminders[/b]: reminders whose time has already passed are skipped when someone signs up or is moved off the waiting list, and the reminder task only ever sends the most recent reminder that's due.[/li]
[li][b]Fewer guests promotes the waiting list[/b]: places freed when someone reduces their guests now go to the next people waiting.[/li]
[/list]
Upgrading keeps all events, registrations and settings.

[b]New in 1.1.1[/b] (fixes)
[list]
[li]When an event is too busy to change at that moment, organisers and members are always told to try again, instead of being shown a success message (or "event full") for a change that didn't happen. This covers moving someone off the waiting list, removing or withdrawing, approving or declining, adding members and saving settings.[/li]
[li]Nobody gets a "you've been promoted" alert unless their place really was confirmed.[/li]
[li]If places open up while an event is busy, the reminder task moves waiting members in within 15 minutes.[/li]
[/list]

[b]New in 1.2.0[/b] (reliability and compatibility)
[list]
[li]Scheduled reminders now load their source file explicitly, including existing installations after upgrading.[/li]
[li]Text entry works on PHP installations without the optional mbstring extension.[/li]
[li]Named seat locks now select MySQL or PostgreSQL using SMF's configured database engine, and fail closed on unsupported engines.[/li]
[li]RSVPs, organiser setting changes and cancellations share the same event lock; approving pending requests honours waiting-list limits.[/li]
[li]Organiser-added waitlist members are checked under the event lock to prevent duplicate registrations and respect the waiting-list limit.[/li]
[/list]

[b]New in 1.2.1[/b] (fixes)
[list]
[li][b]Reminders work again[/b]: 1.2.0 saved the scheduled task in a form SMF couldn't run, so no reminders were sent, and waiting members weren't moved into places that opened while an event was busy. Upgrading repairs it.[/li]
[li][b]All-day events in calendar apps[/b]: on servers ahead of UTC (including the UK in summer), the .ics file and calendar feed gave one-day all-day events an end date the same as their start, so some calendar apps hid them.[/li]
[li]Upgrading from 1.2.0 is now supported.[/li]
[/list]
