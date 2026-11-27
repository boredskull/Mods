[size=4][b]Rich Link Previews[/b][/size] 1.0.3
by [url=https://boredskull.net]BoredSkull[/url] for SMF 2.1.x · ISC licence

Paste a link and get a preview card. Members don't need any BBCode: when a post is saved, plain URLs and [nobbc][url][/nobbc] links are found automatically, and each website's title, description and thumbnail are shown under the post.

[b]Features[/b]
[list]
[li][b]Automatic previews[/b]: plain URLs and BBCode [nobbc][url][/nobbc]/[nobbc][iurl][/nobbc] links are found when a post is submitted or edited.[/li]
[li][b]Website metadata[/b]: Open Graph, Twitter Card and standard HTML title, description and image.[/li]
[li][b]Fallback cards[/b]: if a website blocks metadata requests, a basic card still displays the domain and link.[/li]
[li][b]Existing posts[/b]: use the admin maintenance button to queue previews for older posts, in batches of 100.[/li]
[li][b]Stored, not refetched[/b]: metadata is saved with the post, so viewing a topic never contacts other sites.[/li]
[li][b]Background fetching[/b]: posts save instantly and previews are fetched by SMF's background task runner (this can be switched off).[/li]
[li][b]Smart exclusions[/b]: links inside [nobbc][quote][/nobbc], [nobbc][code][/nobbc], [nobbc][img][/nobbc], [nobbc][spoiler][/nobbc] and similar tags are ignored. Links to images and files are skipped.[/li]
[li][b]YouTube is always excluded[/b]: SMF embeds it already, and the YouTube Video Card mod handles the rest.[/li]
[li][b]Domain exclusion list[/b]: block previews for any domain and its subdomains.[/li]
[li][b]Internal links[/b]: topic and board links to your own forum are built from the database, and members only see them for boards they can access.[/li]
[li][b]Admin settings[/b]: on/off switch, internal links, 1 to 20 previews per post, thumbnails, timeout, download size, refresh interval, plus maintenance tools.[/li]
[li][b]Responsive[/b]: the image moves above the text on mobile. The cards use translucent colours, so they suit light and dark themes.[/li]
[li][b]Security[/b]: only http/https on standard ports; every redirect is checked; private, loopback, link-local, CGNAT and reserved addresses (IPv4 and IPv6) are blocked; connections are pinned to the checked IP to stop DNS rebinding; only HTML is accepted; downloads stop at the end of the page head or at the size limit. All text is stored as plain text and escaped when shown.[/li]
[/list]

[b]Settings[/b]
Admin > Configuration > Modifications > Rich Link Previews

[b]Requirements[/b]
SMF 2.1.x, PHP 7.0+, and the PHP cURL extension for external links (DOM and intl are optional but recommended).

[b]v1.0.2 maintenance update[/b]: improves PHP compatibility without mbstring, escapes internal card fields safely, avoids stale cards after concurrent edits, and removes pending fetch jobs on uninstall without deleting stored metadata. Existing settings and previews are preserved during the upgrade.

[b]v1.0.3 fixes[/b]
[list]
[li]Links that weren't looked up because a post's time limit ran out are no longer saved as failed. Before, they were blocked for every post for 24 hours, and the post kept a basic card permanently. They are now looked up again in the background.[/li]
[li]The weekly clean-up of old data now also runs when "Fetch previews in the background" is off.[/li]
[/list]
