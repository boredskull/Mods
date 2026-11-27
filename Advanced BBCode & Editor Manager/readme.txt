[size=4][b]Advanced BBCode & Editor Manager[/b][/size] 1.0.2
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

One place to manage your forum's posting experience: custom BBCodes, toolbar layouts, and which formatting each membergroup can use in each board. Hooks only, with no core or theme edits.

[b]Custom BBCodes[/b]
[list]
[li]Visual builder: choose the content type, add typed options (text, number, colour, web address, choice list) with defaults, min/max and required flags, then write the HTML or start from a ready-made design.[/li]
[li]Live preview, rendered by the server exactly as it will appear in posts.[/li]
[li]Checks before saving: name clashes with SMF and other mods, unknown placeholders, unsafe markup, and placeholders in unsafe places (a text option inside a style or href attribute is refused).[/li]
[li]Toolbar buttons with uploaded icons (re-drawn as PNG for safety), tooltips and keyboard shortcuts.[/li]
[li]Library of 15 ready-made elements: alert box, info/warning/success, moderator note, announcement banner, expandable panel, spoiler, progress bar, styled box, media card, highlight, keyboard key, badge and columns.[/li]
[/list]

[b]Editor profiles[/b]
[list]
[li]Drag-and-drop toolbar builder with any number of profiles (presets).[/li]
[li]Each profile has its own list of allowed tags. The list is [b]enforced on the server[/b] for posts, quick edits, previews, PMs and signatures. Hidden buttons are not the only protection.[/li]
[li]Assignments by membergroup, board and context (posts, PMs or signatures), with priorities. Board moderators match the Moderator group in their boards.[/li]
[li]"Try it": use a profile in your own editor before anyone else sees it.[/li]
[li]Custom tooltips for every button, including SMF's and other mods'.[/li]
[/list]

[b]Editor[/b]
[list]
[li]Custom tags work in WYSIWYG mode (as labelled blocks) and in source mode.[/li]
[li]Searchable command palette (Alt+/), keyboard shortcuts, and a touch-friendly scrolling toolbar on mobile.[/li]
[/list]

[b]Administration[/b]
[list]
[li]Revision history with one-click rollback (every change is recorded, including rollbacks).[/li]
[li]Import and export of the whole configuration between forums, with icons included and full re-validation on import.[/li]
[li]Diagnostics: tag name conflicts, tags disabled in core settings, template problems, shortcut clashes, unused profiles, and other mods hooked into the editor.[/li]
[/list]

[b]Getting started[/b]
Admin > Configuration > BBCode & Editor Manager. Add a few elements from the [b]Library[/b], create a profile, then add an [b]Assignment[/b].

[b]Requirements[/b]
SMF 2.1.x and PHP 7.0+. GD is recommended for icon uploads.

[b]Changes in v1.0.2[/b]
[list]
[li]Shows readable labels on custom editor buttons that have no uploaded icon, instead of an empty grey strip.[/li]
[li]Makes the editor find its SCEditor container reliably and retries configuration until available.[/li]
[li]Prevents custom WYSIWYG formats from being marked registered before their configuration loads.[/li]
[/list]

[b]Earlier fixes in v1.0.1[/b]
[list]
[li]Fixes editor configuration loading by deferring the editor JavaScript and reading the configuration at start-up.[/li]
[li]Replaces pattern-only template filtering with an allowlisted HTML/attribute/URL validator.[/li]
[li]Validates complete imports before database changes; database replacements use transactions and reject invalid profiles, rules and tags.[/li]
[li]Works without the optional PHP mbstring extension, with Unicode-aware fallbacks.[/li]
[li]Cleans up unused generated icons while protecting icons referenced by retained revisions.[/li]
[li]Hides optional media-card images when no image URL was provided.[/li]
[li]Supports upgrading from v1.0.0 without intentionally resetting configuration.[/li]
[/list]
