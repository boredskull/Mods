[size=4][b]Ranked Polls[/b][/size] 1.1.1
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

Make any poll a [b]ranked-choice[/b] poll. Instead of picking one option, members put the options in order of preference. The winner is found by instant-runoff counting, and the results are shown round by round. Normal SMF polls are not changed in any way. Hooks only, with no core or theme edits.

[b]Creating a ranked poll[/b]
[list]
[li]Tick [i]Ranked-choice poll[/i] on the normal poll form, when starting a topic with a poll or adding a poll to a topic.[/li]
[li]Only in boards you enable, and only for groups with the new [i]Create ranked-choice polls[/i] board permission.[/li]
[li]Question, options, end date, locking, "change vote" and results visibility all work as they do for normal SMF polls.[/li]
[/list]

[b]Voting[/b]
[list]
[li]Click options to rank them, then drag and drop or use the up/down buttons to reorder. On the keyboard, Alt+Up/Down moves an option. Screen readers announce every change.[/li]
[li]Works without JavaScript too (number each option).[/li]
[li]Members only, one ballot per account. Ballots are checked on the server.[/li]
[li][b]Anonymous ballots[/b] (never / creator chooses / always): stored with no member, time or linking ID. A separate list of voters stops anyone voting twice.[/li]
[/list]

[b]Counting[/b]
Instant-runoff: an option with more than half of the continuing ballots wins. Otherwise the option with the fewest votes is eliminated and its ballots move to their next choice. Ties are broken, in order, by:
[list type=decimal]
[li]votes in earlier rounds (most recent first)[/li]
[li]ranking score (Borda points)[/li]
[li]how many ballots ranked the option[/li]
[li]poll order (the option listed later loses)[/li]
[/list]

[b]Results[/b]
[list]
[li]A bar chart for each round with a 50% line, eliminated options, where their ballots went, and exhausted ballots.[/li]
[li]A ballot summary: first choices, times ranked and ranking score.[/li]
[li]CSV export of the rounds and of every ballot, for administrators (and optionally poll creators). Anonymous ballots are shuffled, and cells are protected against spreadsheet formula injection.[/li]
[li]First-choice counts are also written to SMF's own poll tables, so SSI and other mods still show something sensible.[/li]
[/list]

[b]Alerts[/b]
When a ranked poll with an end date closes, everyone who voted gets an alert.

[b]After installing[/b]
Admin > Configuration > Ranked Polls: tick the boards to use, then give groups the [i]Create ranked-choice polls[/i] board permission.

[b]Requirements[/b]
SMF 2.1.x, PHP 7.0+, and polls turned on in SMF.

[b]Version 1.0.1 fixes[/b]
[list]
[li]Serialises ballot submissions per poll and protects the unique voter claim.[/li]
[li]Wider ballot storage (up to 256 ranked options).[/li]
[li]Resetting votes no longer silently converts a ranked poll into a normal poll.[/li]
[li]After voting starts, poll option labels may be corrected but options cannot be added or removed without resetting votes.[/li]
[li]Both member and guest votes block conversion of an existing normal poll unless votes are reset.[/li]
[li]Poll-closing alerts and notified status are committed together.[/li]
[li]Rejects non-decimal choice and ranking values instead of coercing them to numeric IDs.[/li]
[/list]

[b]Updating from v1.0.0[/b]
Use the SMF package manager upgrade to v1.1.0. The migration preserves the widened ranking column without removing ballots or settings. Back up your database first. Run a staging test before production use.


[b]Version 1.1.0 additional corrections[/b]
[list]
[li]Existing ranked polls stay functional when administrators disable creation of new ranked polls.[/li]
[li]Poll-owner CSV exports respect the poll's result-visibility rules. Administrators can still export any poll.[/li]
[li]The scheduled task explicitly loads the RankedPolls class, including on unattended runs.[/li]
[li]Unapproved topics are excluded from scheduled poll-ending notifications.[/li]
[/list]

[b]Upgrading from 1.0.0 or 1.0.1[/b]
Use Package Manager's upgrade option. The migration preserves existing ballots, settings and poll metadata while updating the scheduled-task callable. Back up the database first.

[b]Version 1.1.1 fix[/b]
[list]
[li]Fixed the hourly scheduled task, which 1.1.0 saved in a form SMF couldn't run. Poll-closing alerts were never sent and data for deleted polls wasn't cleaned up. Upgrading (or reinstalling) repairs the task. Alerts for polls that ended while it was broken are sent on its first run.[/li]
[/list]
