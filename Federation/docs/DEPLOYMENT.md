# Federation 1.0.2 deployment

Federation is installed switched off. Before enabling it on a live forum, verify discovery, signature verification, external delivery, moderation and permissions, ideally on a staging copy first.

## Requirements

- SMF 2.1.x, PHP 8.1+ recommended, cURL, OpenSSL, DNS resolution, mbstring.
- HTTPS forum URL, working publicly trusted certificate, a valid SMF `auth_secret` in Settings.php. Encryption of actor signing keys uses AES-256-GCM derived from that secret. **Changing `auth_secret` without migrating keys makes existing actor keys unreadable**.
- A scheduler / cron that can run PHP CLI to process the outbound queue. ActivityPub outbound delivery does not happen on a web request automatically.
- Outgoing HTTPS to remote servers on port 443; ensure firewall/firewall DNS restrictions remain in place. Never whitelist internal IPs.

## Installation

1. Back up files and database, then install the ZIP with SMF Package Manager.
2. Admin → Configuration → Modification Settings → Federation. Keep **Enable federation** disabled initially.
3. Add WebFinger and NodeInfo URL rewrites in the webserver configuration. **This package does not overwrite `.well-known/.htaccess` because that could break existing site discovery**, email verification or other apps.
4. Check externally that `https://YOUR-FORUM/.well-known/webfinger?resource=acct:board-BOARD_ID@YOUR-HOST` returns a JRD JSON document after enabling that board. NodeInfo should respond at `https://YOUR-FORUM/.well-known/nodeinfo`.
5. Make sure the SMF `auth_secret` exists. Enable a test board that is demonstrably visible to guests. Enable member identities only if needed.
6. Turn on **incoming moderation** and **outbound federation**, then test with a disposable ActivityPub account on a separate instance.
7. Set up queue delivery: `*/5 * * * * /usr/bin/php /absolute/path/to/SMF/federation-worker.php >/dev/null 2>&1`. Confirm your site's PHP CLI configuration is the same version as PHP-FPM and can load SSI.php. This script refuses web execution.
8. Confirm that remote Follow requests appear in the Inbox moderation tab. Approve only actors you recognise. Queue deliveries should move to `done`.

## Apache rewrite rules

When using Apache with `mod_rewrite`, place these in your forum's **existing** root `.htaccess` above SMF's general rewrite rules (adapt RewriteBase for subdirectory installations):

```apache
RewriteEngine On
RewriteRule ^\.well-known/webfinger$ index.php?action=federation;sa=webfinger [QSA,L]
RewriteRule ^\.well-known/nodeinfo$ index.php?action=federation;sa=nodeinfo [QSA,L]
```

For subdirectory forums, put the rules in the forum's `.htaccess` and include the real external forum path in your tests.

## nginx example

Inside the appropriate server block, **adjust** `/index.php` to `/subdir/index.php` for subdirectory installs:

```nginx
location = /.well-known/webfinger { rewrite ^ /index.php?action=federation;sa=webfinger last; }
location = /.well-known/nodeinfo { rewrite ^ /index.php?action=federation;sa=nodeinfo last; }
```

**Important:** The examples are deployment guidance, not automatic configuration. Test actual proxy/webserver forwarding, including `Host`, `Date`, `Digest`, and `Signature` headers. Security depends on a correct HTTPS reverse-proxy configuration.

## Verification checklist

- [ ] Federation is disabled immediately after installing.
- [ ] The intended public board is visible in a logged-out private browser window.
- [ ] A member-only or private board cannot be enabled.
- [ ] Disabling a board prevents subsequent actor/object publication.
- [ ] WebFinger/NodeInfo are reachable at standard `/.well-known/` URLs.
- [ ] Actor key cannot be fetched through a public response (only public PEM appears).
- [ ] A signed remote Follow reaches moderation; an unsigned or stale Follow receives 401.
- [ ] No remote reply is inserted as an SMF post without administrator approval and custom integration.
- [ ] Blocked domains cannot be fetched and queued deliveries fail closed.
- [ ] The CLI worker performs signed deliveries; retries appear for network failures.
- [ ] A deleted local topic's object stops being served and a Delete is queued when possible.
- [ ] Forum backups include `federation_` tables and the SMF `auth_secret` is stored safely.

## Known limits

- Inbound Create/Update/Delete/Like activities are **quarantined and reviewed**, not automatically converted into native SMF posts, likes or replies. Approved items appear in a read-only personal feed or via the `integrate_federation_approved_activity` hook. A dedicated forum discussion mapping layer is future work.
- This version publishes **new public topics**, edits and removals. Replies to existing SMF topics are not federated as separate objects.
- Mastodon, Lemmy and other servers have subtly different interpretation of Group, Follow, Announce, and inbox verification. Live interoperability testing is required; syntax checks cannot establish Fediverse compatibility.
- WebFinger identifies board actors as `board-ID` and member actors as `member-ID`. Changing host names breaks remote identities.
- Disabling federation cannot guarantee removal of copies previously received by remote servers. Notify members about this before opt-in.
- Rejected/unverified activities never enter the native posts table. HTTP signature parsing currently implements RSA-SHA256 with `(request-target) host date digest` and does not accept every signing dialect in the fediverse.
- Automated moderation is deliberately absent. Admin approval is required for new remote activities and Follow requests.
- Queue processing uses a **manually configured cron job**, not an SMF built-in background task. Retries are persisted with exponential backoff.
- A security review of your server and proxy configuration is recommended before federating a large public forum.

## Ecosystem hook

After manual approval the module calls:

`call_integration_hook('integrate_federation_approved_activity', array(&$safe_event));`

The event only includes verified metadata (`id`, `type`, `actor`, `received`), not raw remote HTML. Activity Stream, Profile Wall and Community Insights may subscribe independently; this package has no hard dependencies.

## Recommended checks after installing or upgrading

- After upgrading, verify keys, followers and settings remain.
- Test the private feed and owner-only controls, handle lookup, signed GET on an authorized-fetch instance, different signed header orders, incoming Undo, and Accept/Reject by Follow ID.
- Create a topic containing quotes, ampersands, links and line breaks; edit, approve, unapprove, move to a private board, recycle and delete it. Confirm the object and queue never expose stale or nonpublic text.
- Run the CLI worker regularly: it reconciles moves and legacy objects, prunes pending items older than 7 days and completed inbox/delivery/log entries older than 30 days. Old approved feed entries therefore expire.
- The daily domain review quota is 200 pending activities per remote actor host per UTC day. This is not a global hard cap; use webserver rate limiting and domain blocks as well.
- Manual delivery processing is limited to 3 deliveries with a 15-second target budget and shorter per-request timeout. DNS can exceed that budget.
- Outbound shared inbox routing deduplicates public Create/Update/Delete by advertised same-origin endpoint. New or renewed approved follows store the endpoint; existing followers use their individual inbox until the next approved Follow. Missing or invalid metadata uses individual delivery. No inbound shared inbox endpoint is advertised. Test that removing the final active follower prevents further queued Create/Update delivery to that shared endpoint. Delete delivery and remote removal are best-effort; local follower removal cannot revoke previously received public content.
- Previously withdrawn first posts can be republished on approval or CLI reconciliation, only when the topic and message are approved, still public, not recycled, and the original actor is enabled and eligible for outbound federation. The old object URL is not reused. Check that queued Deletes still refer to the old ID and stale Creates cannot send it. Run the worker regularly; some moves are only detected there.
- 1.0.1 and 1.0.2 test builds upgrade in place through the Package Manager. Back up the database first; the installer adds new columns non-destructively.
