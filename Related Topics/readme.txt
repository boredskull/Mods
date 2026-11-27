[center][size=5][b]Related Topics - v1.0.3[/b][/size]
[b]Discover Similar Discussions for SMF 2.1.x[/b]
[b]By BoredSkull[/b][/center]

[hr]

[b]Overview[/b]

Keep discussions flowing and help members discover more content with [b]Related Topics[/b], a modification that automatically suggests similar discussions at the bottom of each topic.

Using subject-based matching, the modification identifies relevant topics and displays them in a modern, customisable card layout, encouraging members to explore more of your forum.

[color=green][b]Uses SMF hooks. No core or theme file edits required![/b][/color]

[hr]

[b]Features[/b]

[list]
[li][b]Automatic Suggestions[/b] - Displays related discussions beneath the current topic.[/li]
[li][b]Smart Topic Matching[/b] - Analyses topic titles, scores relevant matches and fills remaining spaces with recent topics.[/li]
[li][b]Four Display Styles[/b] - Choose between Cards, Overlay, Gradient and Minimal layouts.[/li]
[li][b]Light & Dark Themes[/b] - Customise card appearances to complement your forum's design.[/li]
[li][b]Automatic Thumbnails[/b] - Extracts images from attachments, [img] BBCode or YouTube links in the first post.[/li]
[li][b]Fallback Images[/b] - Displays a coloured letter tile when no suitable image is available.[/li]
[li][b]Flexible Layouts[/b] - Select 2, 3 or 4 columns with three available image shapes.[/li]
[li][b]Visual Customisation[/b] - Configure accent colours and interactive hover effects.[/li]
[li][b]Mobile Friendly[/b] - Responsive layouts with swipe or stacked display options for smaller screens.[/li]
[li][b]Permission Aware[/b] - Respects board permissions and excludes unapproved topics, moved notices and recycle bin content.[/li]
[li][b]Caching Support[/b] - Caches results when SMF caching is enabled to help reduce repeated processing.[/li]
[/list]

[hr]

[b]Installation & Configuration[/b]

Install the package through [b]Admin > Packages > Download Packages > Upload Package[/b], then select [b]Install Now[/b].

To configure Related Topics:

[list]
[li]Navigate to [b]Admin > Configuration > Modification Settings > Related Topics[/b].[/li]
[li]Choose your preferred card style and light or dark appearance.[/li]
[li]Configure the number of columns, image shapes and accent colour.[/li]
[li]Adjust hover effects and mobile display options to suit your forum.[/li]
[/list]

Once configured, related topic suggestions appear automatically beneath discussions.

[hr]

[b]How Related Topics Work[/b]

The modification analyses words within the current topic's subject and compares them against other discussions.

Matching topics are scored for relevance, with the most suitable suggestions displayed first. If insufficient matches are found, the remaining positions are filled using recent topics.

Each suggestion can include a thumbnail, providing members with a visual preview of discussions they may find interesting.

[hr]

[b]Technical Notes[/b]

[list]
[li][b]100% Hook Based[/b] - Integrates through SMF hooks without modifying core files or theme templates.[/li]
[li][b]SMF 2.1.x Compatibility[/b] - Designed for the SMF 2.1 series.[/li]
[li][b]Permission Friendly[/b] - Suggestions respect board access permissions to prevent restricted content from appearing.[/li]
[li][b]Optimised Performance[/b] - Supports SMF's built-in caching system when enabled.[/li]
[li][b]Responsive Design[/b] - Adapts to desktop, tablet and mobile displays.[/li]
[/list]

[hr]

[b]Credits[/b]

[b]Developed by BoredSkull[/b]
https://boredskull.net

[b]Version 1.0.2 maintenance changes[/b]
[list]
[li]Re-check topic approval and board visibility on cache hits. Cached matches cannot bypass recent privacy changes.[/li]
[li]Build censored, viewer-specific content freshly rather than caching rendered card details.[/li]
[li]HTML-escape card headings, subjects, author names and board names.[/li]
[li]Handle malformed admin colour and excluded-board inputs safely.[/li]
[li]Added in-place upgrade from 1.0.1; existing settings are retained.[/li]
[/list]

[b]Version 1.0.3 fixes[/b]
[list]
[li]The Overlay style now always loads pictures. Before, turning off "Show pictures" left Overlay cards with letter tiles only, although that setting is meant for the Cards style.[/li]
[li]Topics in the recycle bin no longer show suggestions.[/li]
[li]The credits line now takes its version number from the mod itself.[/li]
[li]Added in-place upgrade from 1.0.2 (and 1.0.1); existing settings are retained.[/li]
[/list]
