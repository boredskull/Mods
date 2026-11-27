[size=4][b]Clubs[/b][/size] 1.1.3
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

Member-created spaces inside your forum, inspired by Invision Community's Clubs. Unlike membergroups, clubs are run by members: they start clubs for their interests, choose leaders, approve applications, hold private discussions, and share media and events. Hooks only, with no core or theme edits.

[b]Built on SMF itself[/b]
Each club is a real SMF board (or several) in a "Clubs" category, with its own hidden membergroup. The mod keeps board access, permissions and moderators in step with the club's type and roles. So club discussions get everything SMF offers (topics, replies, attachments, polls, likes, search, notifications, drafts, moderation tools), and SMF enforces privacy everywhere: recent posts, search, feeds, the calendar and other mods.

[b]Club types[/b]
[list]
[li][b]Open:[/b] everyone can see the club and its posts. Anyone can join.[/li]
[li][b]Closed:[/b] everyone can see the club and who's in it, but only members see posts. Members join by request.[/li]
[li][b]Private:[/b] only members can find the club. Members join by invitation.[/li]
[li][b]Read only:[/b] everyone can read, and only leaders post. Members join by invitation.[/li]
[/list]

[b]Roles[/b]
Owner, leaders and members. Leaders automatically become moderators of the club's boards. They approve or deny requests, invite members by name, remove or ban members, add extra boards, create events and edit the club. Only the owner can appoint leaders, change the club type, transfer ownership or delete the club.

[b]Each club has[/b]
[list]
[li][b]Overview:[/b] description, latest discussions, upcoming events, leaders, newest members and stats[/li]
[li][b]Discussions:[/b] the club's own board, plus optional extra boards[/li]
[li][b]Members:[/b] roles, requests with messages, invitations and bans[/li]
[li][b]Media:[/b] a gallery of every image shared in the club's discussions[/li]
[li][b]Events:[/b] events in SMF's calendar with RSVP (going, maybe, not going). Each event gets a discussion topic.[/li]
[li][b]Manage:[/b] name, description, type, colour, icon, boards, ownership transfer and deletion[/li]
[/list]
A colourful club banner with tabs also appears above the club's boards and topics.

[b]Alerts[/b]
Join requests (to leaders), approvals and refusals, and invitations.

[b]Admin[/b]
Admin > Configuration > Clubs: which types members can create, approval of new clubs, clubs per member, extra boards per club, guest reading, global moderator access to private clubs, events, alerts, and hiding the club boards from the board index. The list of all clubs lets you approve, delete and repair access.
New permissions: [i]View clubs[/i], [i]Join clubs[/i], [i]Start clubs[/i] and [i]Manage all clubs[/i].

[b]Uninstalling[/b]
Removing the mod's data keeps the club boards, their topics and the club membergroups as normal SMF boards and groups, so no posts are lost.

[b]Requirements[/b]
SMF 2.1.x and PHP 7.0+. GD is needed for club icons. Events need SMF's calendar turned on.

[b]New in 1.1.0[/b] (fixes)
[list]
[li][b]Club permissions are separate[/b]: each club now has its own permission profile ("Club: name" in Admin > Permissions). In 1.0 all clubs shared one, so joining one club let you post in other public clubs, including read-only ones.[/li]
[li][b]Read-only clubs[/b]: only leaders can add events, because each event starts a discussion topic.[/li]
[li][b]Bans[/b] now work on the boards too: a banned member goes into a hidden "Banned from club" group that is denied access to the club's boards (this switches on SMF's "deny board access" option). An admin setting lets banned members keep reading open clubs instead. They can't post either way.[/li]
[li][b]Icons[/b]: the old icon is only deleted after the club has been saved, and an upload from a form with errors is cleaned up.[/li]
[li][b]Moderators[/b]: Clubs only adds and removes the moderator entries it created. Moderators an admin assigns to club boards are left alone.[/li]
[li][b]Guests[/b] aren't shown club content when guest reading is turned off.[/li]
[/list]
Upgrading keeps every club, board, member and post.

[b]New in 1.1.1[/b] (fix)
[list]
[li][b]Board visibility is now always applied[/b]: SMF 2.1 decides who can see a board from its board_permissions_view table. Clubs now updates it together with the board's access list, so changing a club's type, banning, and the guest setting take effect straight away. Before, a club changed from open to private (or closed) after it was created could stay readable by everyone. Upgrading fixes the visibility of every existing club.[/li]
[/list]

[b]New in 1.1.2[/b] (fixes)
[list]
[li][b]Club creator access[/b]: clear SMF's cached membergroup data when a new club is activated, so the owner can open its board immediately.
[li][b]Joining and leaving[/b]: clear the affected member's cached groups after approvals, invitations, joining, leaving and bans, so access changes are reflected on the next request.
[li][b]Extra board access[/b]: sync newly added sub-boards with the club's current visibility and ban settings.
[li][b]Upgrade repair[/b]: fix missing group assignments for active club members and refresh their cache entries. Existing club boards, members and posts are preserved.
[/list]

[b]New in 1.1.3[/b] (fix)
[list]
[li][b]Club creation for regular members[/b]: SMF's board tree must include the newly inserted board while the forum's createBoard/modifyBoard operation is running. Temporarily use the full tree inside this trusted operation, then restore the user's normal visibility rules. This prevents the misleading "The board you specified doesn't exist" error and incomplete club boards.
[li][b]Existing affected clubs[/b]: The upgrade restores missing main boards for active club entries which were left without a board by the failed operation. No existing club boards or posts are deleted.
[/list]
