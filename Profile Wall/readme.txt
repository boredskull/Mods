[size=14pt][b]Profile Wall v1.1.2[/b][/size] for SMF 2.1.x

Adds a comment "Wall" tab to every member's profile. Written from scratch, 100% hook-based: no core files are edited.

[b]Core features[/b]
[list]
[li]Wall tab on each profile with paginated comments (per-page setting)[/li]
[li]Full SMF editor with BBCode and smileys[/li]
[li]Edit / delete own or any comments (own/any permissions)[/li]
[li]Poster info box: avatar, group, post count, online dot[/li]
[li]Approval queue for groups without the "post without approval" permission[/li]
[li]Native SMF 2.1 alerts (members can switch them off in Notifications), optional PM too[/li]
[/list]

[b]Extra features[/b]
[list]
[li]Wall privacy per member: everyone, buddies only, or closed[/li]
[li]Respects the owner's ignore list[/li]
[li]Owner approval mode - members can moderate their own wall[/li]
[li]Threaded replies (one level), with reply alerts[/li]
[li]Likes on comments, with like alerts[/li]
[li]Feature (pin) one comment to the top of a wall[/li]
[li]Report comments to moderators; reports page in admin[/li]
[li]Admin approval queue with bulk approve/delete[/li]
[li]Flood control, max length, and a time-limited edit window[/li]
[li]"Edited by X" marker, owner badge, permalinks to each comment[/li]
[li]Latest wall comments preview on the profile summary page[/li]
[li]Live character counter on reply boxes[/li]
[li]"Cannot post" bans also block wall posting; guests can only read[/li]
[li]Cleans up automatically when members are deleted[/li]
[/list]

[b]Settings:[/b] Admin > Configuration > Profile Wall
[b]Permissions:[/b] Admin > Members > Permissions > (group) > "Profile Wall"

Default permissions on install: guests can view; regular members can view, post, edit/delete own; global moderators can moderate.

[b]Version 1.1.2 changes[/b]
[list]
[li]Added the four missing permission labels on the Profile Wall settings page.[/li]
[li]Wall alerts now open the linked comment or reply from the notification dropdown, including on older wall pages.[/li]
[/list]

[b]Version 1.1.1 changes[/b]
[list]
[li]Corrected the notification preference language keys for wall comments, replies and likes to match SMF 2.1.x[/li]
[li]Load Profile Wall language strings explicitly when registering alert preferences[/li]
[li]Added a data-preserving upgrade path from v1.1.0; existing comments, likes, permissions and settings are retained[/li]
[/list]

[b]Version 1.1.0 changes[/b]
[list]
[li]Respect global switch when per-member privacy and owner approval are disabled[/li]
[li]Send correct original-author alerts for approved comments, including approved replies[/li]
[li]Limit bulk moderation to pending comments only[/li]
[li]Escape stored display names in guest, admin and summary views[/li]
[li]Protect delayed approvals and like alerts from duplicate concurrent submissions[/li]
[li]Disallow featuring unapproved comments, escape reply editor content and reject negative pagination offsets[/li]
[li]Data-preserving upgrade from v1.0.x; no table migration needed[/li]
[/list]
