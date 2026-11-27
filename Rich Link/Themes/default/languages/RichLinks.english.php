<?php

/**
 * Rich Link Previews: English language strings.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

$txt['richlinks_title'] = 'Rich Link Previews';
$txt['richlinks_admin_desc'] = 'Shows a preview card (title, description and thumbnail) under plain links in posts. Metadata is fetched once when a post is saved, then stored.';

$txt['richlinks_enabled'] = 'Enable rich link previews';
$txt['richlinks_background'] = 'Fetch previews in the background';
$txt['richlinks_background_desc'] = 'Recommended. Posts save instantly and previews appear a few seconds later. When this is off, metadata is fetched while the post is submitted (up to 10 seconds in total).';
$txt['richlinks_max'] = 'Maximum previews per post';
$txt['richlinks_max_desc'] = 'Between 1 and 20.';
$txt['richlinks_internal'] = 'Show previews for links to this forum';
$txt['richlinks_internal_desc'] = 'Topic and board links are built from the forum database, not by fetching the page. Members only see them for boards they can access.';
$txt['richlinks_images'] = 'Show thumbnail images';
$txt['richlinks_exclude'] = 'Excluded domains';
$txt['richlinks_exclude_desc'] = 'One domain per line. Subdomains are included, so example.com also covers www.example.com and blog.example.com. YouTube links are always excluded because they are embedded already.';
$txt['richlinks_timeout'] = 'Request timeout';
$txt['richlinks_max_kb'] = 'Maximum page size to download';
$txt['richlinks_max_kb_desc'] = 'Downloading stops at the end of the page head or at this size, whichever is first.';
$txt['richlinks_cache_days'] = 'Refresh stored metadata after';
$txt['richlinks_cache_days_desc'] = 'Existing posts keep their preview. This only decides when a new post with the same link fetches fresh metadata.';
$txt['richlinks_seconds'] = 'seconds';
$txt['richlinks_days'] = 'days';

$txt['richlinks_no_curl'] = 'The PHP cURL extension is not available on this server, so previews for external links cannot be fetched. Previews for links to this forum will still work.';

$txt['richlinks_maintenance'] = 'Maintenance';
$txt['richlinks_stats'] = 'Stored previews: <strong>%1$s</strong> &middot; Cached sites: <strong>%2$s</strong> &middot; Failed lookups: <strong>%3$s</strong>';
$txt['richlinks_prune'] = 'Prune orphaned data';
$txt['richlinks_retry'] = 'Forget failed lookups';
$txt['richlinks_maintenance_desc'] = 'Prune unused previews, clear failed lookups, or scan existing posts in batches of 100. Scanning queues background jobs and is safe to repeat. Refresh the affected topics after the background tasks complete.';
$txt['richlinks_pruned'] = 'Pruning finished. Removed previews from %1$s deleted posts.';
$txt['richlinks_retried'] = 'Failed lookups cleared.';
$txt['richlinks_backfill'] = 'Process next 100 existing posts';
$txt['richlinks_backfill_progress'] = 'Scanned %1$s existing posts and queued %2$s for preview generation. Click the button again to scan the next batch.';
$txt['richlinks_backfill_done'] = 'Existing-post scan reached the end. Final batch: %1$s scanned and %2$s queued. The background task runner will generate the cards.';

$txt['richlinks_started_by'] = 'Started by %1$s';
$txt['richlinks_replies'] = '%1$s replies';
$txt['richlinks_board_stats'] = '%1$s topics &middot; %2$s posts';

$txt['richlinks_credits'] = 'Rich Link Previews %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
