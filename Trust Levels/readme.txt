[size=4][b]Trust Levels[/b][/size] 1.1.1
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

Members earn trust as they take part, and new accounts start with sensible limits. This stops most spam without getting in the way of real members. Hooks only, with no core or theme edits.

[b]How members level up[/b]
Levels use data SMF already records, so nothing extra is tracked:
[list]
[li]Days since joining, posts, topics started, likes received (not counting their own), topics read and time online[/li]
[li]Optional maximum warning level, so warned members drop down automatically[/li]
[li]Levels marked "given by staff" are never reached automatically[/li]
[/list]
Levels are checked when a member posts, about once an hour while they browse, by a daily scheduled task, and on demand from the admin panel (in batches, safe for large forums).

[b]What levels control[/b]
[list]
[li][b]Links per post[/b], [b]images per post[/b], [b]new topics per day[/b], [b]replies per day[/b] and [b]links in signatures[/b], all checked on the server with clear error messages. Quick edits, PMs and signatures, which skip the post form, have extra links turned into plain text.[/li]
[li]Code blocks, quotes of real posts and links to your own forum are not counted. Bare domains like example.com, which SMF turns into links, are counted.[/li]
[li][b]Membergroups:[/b] each level can give an additional group, so any SMF permission can depend on trust: PMs, polls, attachments, post approval and so on.[/li]
[/list]

[b]For members[/b]
[list]
[li]A level badge on posts (placement can be set).[/li]
[li]A Trust level page in the profile showing progress bars to the next level, the current limits and a history of changes.[/li]
[li]An optional congratulations PM on each promotion, with placeholders.[/li]
[/list]

[b]For staff[/b]
[list]
[li]Levels editor: 2–10 levels with name, colour, group, requirements, limits and promotion message.[/li]
[li]Members overview with counts per level, a log of every change, and batched recalculation.[/li]
[li]Set and lock any member's level from their profile ("Manage members' trust levels" permission).[/li]
[li]Demotion modes: never, only for warnings (default), or always.[/li]
[li]Exempt groups (Administrators and Global Moderators by default) always get the top level with no limits.[/li]
[/list]

[b]After installing[/b]
1. Admin > Members > Trust Levels > Levels: adjust the five default levels (New, Basic, Member, Regular, Leader).
2. Optionally give levels a membergroup and set that group's permissions.
3. Run Recalculate once to give existing members their levels.

[b]Requirements[/b]
SMF 2.1.x and PHP 7.0+.


[b]New in 1.1.0[/b]
[list]
[li][b]Fuller history[/b]: locking and unlocking a member's level is recorded (with who did it and why) even when the level itself doesn't change, and so are promotions held back by probation.[/li]
[li][b]Staff-assigned groups are protected[/b]: Trust Levels now remembers which level membergroups it added itself and only ever removes those. A level group a moderator gives someone by hand stays put. Can be turned off in the settings.[/li]
[li][b]Probation after warnings[/b] (optional): for a set number of days after a member's warning level goes up, they can't be promoted automatically, even if the warning level drops again. Their profile shows when probation ends. Staff can still change levels by hand.[/li]
[li]Data for deleted members is now removed.[/li]
[li]Fixed: on a fresh install the database step ran before the files were copied.[/li]
[/list]
Upgrading from 1.0 keeps all levels and history. Level groups members already have that match their current level are recorded as added by Trust Levels; any other level group they hold is treated as assigned by staff.

[b]New in 1.1.1[/b] (fixes)
[list]
[li][b]Bare domains count as links[/b]: SMF turns text like "example.com" into a link by itself, so members limited to no links could still post them. They are now counted, and extras are turned into plain text.[/li]
[li][b]Made-up quotes count[/b]: links inside a [nobbc][quote][/nobbc] are only left out when it quotes a real post and the links are in that post. Before, wrapping links in [nobbc][quote][/nobbc] got round the limits.[/li]
[li]Replies per day no longer counts the first posts of new topics (those count towards topics per day).[/li]
[li]Staff with "Manage members' trust levels" can no longer change their own level. Administrators still can.[/li]
[/list]
