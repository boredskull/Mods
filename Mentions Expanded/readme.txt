[size=5][b]Mentions Expanded[/b][/size] 1.1.1 by [url=https://boredskull.net]BoredSkull[/url]
For SMF 2.1.x - built on SMF's core mentions, 100% hooks, no file edits.

[b]Adds[/b]
[list]
[li][b]@everyone[/b] - notifies every activated member who can see the board.[/li]
[li][b]@here[/b] - notifies members who are online when the post is made (time window set in admin).[/li]
[li][b]@groupname[/b] - notifies members of a membergroup, e.g. [i]@Global Moderator[/i]. Admin picks which groups can be mentioned.[/li]
[/list]

[b]How it behaves[/b]
[list]
[li]Uses SMF's normal "mentioned you" alert and respects each member's mention alert/email preference.[/li]
[li]Only members who can see the board are notified; banned/unactivated members and anyone ignoring the poster are skipped.[/li]
[li]Nobody is notified twice for the same post (normal @mention + group + @everyone all collapse to one alert).[/li]
[li]Mentions inside quotes, code and nobbc are ignored.[/li]
[li]Edits only notify targets that were newly added.[/li]
[li]Unapproved posts don't notify anyone.[/li]
[li]Notifications are sent by a background task in batches, so @everyone on a big forum doesn't slow down posting.[/li]
[li]Optional highlighting of @everyone / @here / @group in posts.[/li]
[/list]

[b]Setup[/b]
[list type=decimal]
[li]Make sure core mentions are enabled (Admin > Configuration > Features and Options).[/li]
[li]Configure under Admin > Configuration > Modification Settings > Mentions Expanded.[/li]
[li]Grant the new board permissions [i]Use @everyone[/i], [i]Use @here[/i] and [i]Use @groupname[/i] to the groups you trust. Nobody but admins has them by default.[/li]
[/list]

[b]License[/b]
ISC - see license.txt.

[b]What's new in v1.1.1[/b]
[list]
[li]Fixed @everyone and group mentions reaching members whose primary group can't see the board. "Regular Members" board access now only counts for members with no primary group, as in SMF itself.[/li]
[li]Fixed "@Senior Staff" also notifying a group called "Senior". Longer group names now take priority.[/li]
[li]Fixed the readme and licence showing as filenames in the Package Manager.[/li]
[li]Added an upgrade path from 1.1.0.[/li]
[/list]

[b]What's new in v1.1.0[/b]
[list]
[li]Validates post and topic approval before sending notifications, including after edits.[/li]
[li]Respects live board visibility using SMF 2.1 board_permissions_view, including regular members who have a named primary group.[/li]
[li]Avoids duplicate recipient notifications when background tasks overlap, a post is edited, or the job is retried.[/li]
[li]Skips members already directly mentioned with SMF's own mention tag.[/li]
[li]Does not permanently log an @here mention if nobody was online to receive it.[/li]
[li]Obeys SMF's send_notifications flag and re-checks enabled mention types when tasks run.[/li]
[li]Validates admin-submitted mentionable groups against real non-post-count groups.[/li]
[li]Clean upgrade from v1.0.x; uninstall cancels pending background tasks. Existing configuration is preserved.[/li]
[/list]
[b]Upgrading[/b]
Back up the database, then use the SMF Package Manager's upgrade operation.
The upgrade adds a small mentions_expanded_recipients table and preserves the existing
mentions_expanded_log and all settings. Previous notifications are not resent.

[b]Compatibility[/b]
This review does not replace live forum tests. Test a private board, a named
membergroup on a regular-members board, a moderator edit, and repeat background
execution before public release.
