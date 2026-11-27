YouTube Video Card 1.0.1 for SMF 2.1.x
======================================

What it does
------------
Upgrades the existing [youtube] BBCode tag. The editor's YouTube button is untouched: you paste a link, it stores [youtube]VIDEO_ID[/youtube]. This mod changes how that tag is displayed. Instead of only the player, the post now shows:
* The video player
* The title, channel, date, views and likes
* The description (collapsed, with "Show more")
 * The top comments

Posts that already use [youtube] switch to the new display too. (If an old post still looks the same, empty the cache: Admin > Configuration > Server Settings > Cache > Empty the cache.)

Install
-------
Admin > Packages > Download Packages > Upload Package, then Install Now.

Set up the API key (needed for description and comments)
--------------------------------------------------------
1. Go to https://console.cloud.google.com/ and create or pick a project.
2. Enable "YouTube Data API v3" for it.
3. Create an API key (APIs & Services > Credentials).
4. Restrict the key: API restriction = YouTube Data API v3, and either an IP address restriction
   (this server) or a website restriction (this forum's address).
5. Paste it into Admin > Configuration > Modification Settings > YouTube Video Card.

Without a key the card still shows the player and the video title. Each video costs about 2 API units and is cached (6 hours by default), against Google's free quota of 10,000 units per day.

Notes
-----
- The key stays on the server. Visitors' browsers only talk to this forum.
- Comments are plain text. Links in descriptions are rel="nofollow ugc".
- Videos are embedded from youtube-nocookie.com. YouTube needs the browser to send a Referer for embeds, so don't set a "no-referrer" policy for the forum.
- Uninstalling asks whether to also delete the cache table and settings.
