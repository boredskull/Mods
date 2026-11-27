[size=4][b]Activity Stream[/b][/size] 1.1.2
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

A forum-wide timeline, similar to the activity streams in Invision Community and vBulletin. Members follow each other, browse ready-made streams, and build and save their own. Hooks only, with no core or theme edits.

[b]What appears[/b]
[list]
[li]New topics and replies, with a short preview (quotes removed)[/li]
[li]Likes, grouped as "Anna, Ben and 3 others liked…"[/li]
[li]New members joining[/li]
[li]Items from other mods, through an extension hook (see below)[/li]
[/list]
Activity comes from data SMF already stores, so nothing new is tracked. The timeline is grouped by day, with icons, avatars and "Load more" paging.

[b]Streams[/b]
[list]
[li][b]All activity:[/b] everything you're allowed to see[/li]
[li][b]Following:[/b] activity from members you follow[/li]
[li][b]Popular:[/b] topics with the most replies and likes recently[/li]
[li][b]My activity:[/b] your own posts and likes[/li]
[li][b]Custom streams:[/b] choose the kinds of activity, whose activity (everyone, people I follow, or only me), boards and time period. Each stream gets its own tab, and any tab can be your default.[/li]
[/list]

[b]Following[/b]
[list]
[li]A Follow button on each member's Activity profile page (works without reloading)[/li]
[li]Follower and following counts[/li]
[li]A "People I follow" page to add people by name, see followers and follow back[/li]
[/list]

[b]Privacy and permissions[/b]
[list]
[li]Activity only appears to members who can see the board it happened in. Unapproved posts are never shown.[/li]
[li]Boards and members on someone's ignore list are hidden from them.[/li]
[li]The admin can exclude boards (the recycle bin is always excluded).[/li]
[li]New permissions: [i]View the activity stream[/i] (guests included by default) and [i]Follow members and build activity streams[/i].[/li]
[/list]

[b]For mod authors[/b]
[code]integrate_activity_stream_types (&$types)            // add: $types['wall'] = 'Wall posts';
integrate_activity_stream_fetch (&$items, $config, $before, $limit)
// append items: array('type' => 'wall', 'time' => 1700000000, 'key' => 'w123',
//   'actor' => 5, 'html' => 'Safe HTML line', 'snippet' => 'Safe HTML', 'url' => '...')
// Only return items older than $before, newest first, at most $limit.[/code]

[b]Settings[/b]
Admin > Configuration > Modification Settings > Activity Stream: item types, items per page, popular period, custom streams per member, excluded boards and the menu button.

[b]Requirements[/b]
SMF 2.1.x and PHP 7.0+.

[b]New in 1.1.0[/b] (fixes)
[list]
[li][b]Security[/b]: a stream name is now always escaped in the edit form, including when the form is shown again after an error. Saved names aren't escaped twice.[/li]
[li][b]Nothing skipped when paging[/b]: "Load more" now continues from the exact last item (time plus a unique ID), so items posted in the same second are never skipped or repeated. Old links still work.[/li]
[li][b]Excluded boards[/b] are saved as a plain list of board IDs, and read back correctly whatever format an older version saved.[/li]
[li][b]Turning custom streams off[/b] (maximum 0) now hides members' existing custom streams too. They're kept, and come back if streams are turned on again. Lowering the maximum hides the streams over the new limit in the same way.[/li]
[/list]

[b]New in 1.1.1[/b] (maintenance)
[list]
[li]Fixed the undefined array key cannot_as_view warning when guests without Activity Stream access visit the Activity page.[/li]
[li]Loads Activity Stream language strings before checking page access.[/li]
[li]Adds a clear permission-denied message without changing guest permissions or privacy settings.[/li]
[/list]

[b]New in 1.1.2[/b] (fixes)
[list]
[li][b]Settings moved[/b] to Admin > Configuration > Modification Settings > Activity Stream. They can also be found with the admin search, and old links to the previous page go to the new one.[/li]
[li]Fixed a database error on a member's Activity profile page when the viewer has anyone on their ignore list.[/li]
[li]Topic titles in the timeline and the Popular list now use the forum's word censoring.[/li]
[li]Popular no longer counts likes when likes are turned off.[/li]
[li]Stream names with characters such as & or quotes are no longer cut short when saved.[/li]
[li]A custom menu button label is now escaped when shown.[/li]
[li]"Open this stream by default" is ticked when you edit your default stream, and unticking it goes back to All activity.[/li]
[li]The "follow limit reached" message now shows the number instead of a placeholder.[/li]
[li]Who's Online shows "Viewing the Activity Stream".[/li]
[li]Upgrading from 1.1.1 is now supported.[/li]
[/list]
