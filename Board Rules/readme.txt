[size=14pt][b]Board Rules[/b][/size] v1.0.3 for SMF 2.1.x
By [url=https://boredskull.net]BoredSkull[/url] - ISC License

A complete, hook-based rules system for your forum. No core files are edited.

[b]Features[/b]
[list]
[li]Dedicated rules page ([i]?action=rules[/i]) with categories, automatic numbering (1.1, 1.2...), table of contents, live search, expand/collapse, permalinks to each rule and a print-friendly view.[/li]
[li]Admin management of individual rules: add, edit, delete, reorder, enable/disable, and an optional penalty badge per rule (e.g. "Warning", "Ban").[/li]
[li]Board-specific rules: a rule can apply forum-wide or to selected boards only. Board rules are shown above the topic list, above topics and/or on the posting page (each optional, expanded or collapsed).[/li]
[li]Notifications when rules change: publish an update with a summary, show members a "rules updated" notice, send SMF alerts (members can opt out in their alert preferences) and optionally require members to re-accept before they continue using the forum.[/li]
[li]Acceptance tracking with statistics and a full change history.[/li]
[li]Optional acceptance of the rules during registration (required checkbox on the registration form).[/li]
[li]Multilingual rules: categories and rules can be for all languages or a single language, with automatic fallback to the forum default language and a language switcher on the rules page.[/li]
[li]Full BBCode support with the standard editor.[/li]
[li]A ready-made set of 24 standard forum rules in 5 categories is installed as an example - edit them to suit your community.[/li]
[li]"Who's online" support, menu button, credits entry and clean uninstall.[/li]
[/list]

[b]After installing[/b]
Go to [i]Admin > Configuration > Board Rules[/i]. Rule changes do not notify anybody until you either tick "This is a significant change" when saving, or use [i]Changes & Notifications > Publish update[/i].

[b]Uninstalling[/b]
Tick "Remove all data associated with this modification" to delete the rules tables, settings and alerts. Leave it unticked to keep your rules for a later reinstall.

[b]Changes in v1.0.2[/b]
[list]
[li]Members can still accept an update when the rules list is empty, preventing forced-acceptance lockouts.[/li]
[li]New members are no longer automatically counted as having accepted rules when the registration checkbox is switched off.[/li]
[li]Improved alert metadata resilience, posted board-ID validation and language selection behaviour.[/li]
[li]Corrected internal version numbers and added a data-preserving 1.0.1 upgrade path.[/li]
[/list]

[b]Changes in v1.0.3[/b]
[list]
[li]"Require members to re-accept" now applies everywhere. Before, members could still browse the board index, boards and topics without accepting, because the check ran in a hook SMF doesn't call on those pages.[/li]
[li]Forced acceptance no longer redirects SSI pages (portals and external scripts) or RSS feeds.[/li]
[li]Deleting a member now removes their acceptance record.[/li]
[li]The readme now shows correctly in the Package Manager.[/li]
[li]Added an upgrade path from 1.0.2 (and 1.0.1); rules, acceptances and settings are kept.[/li]
[/list]
