Federation 1.0.2
============================
SMF 2.1.x | ISC licence | https://boredskull.net

A from-scratch, hook-based ActivityPub implementation for SMF. No SMF core or
installed theme edits are required. This is not a fork or redistribution of the
SMFHacks ActivityPub package.

FEATURES
--------
- Strict opt-in for genuinely guest-public SMF boards.
- Opt-in member Person actors, per-member follow requests and a read-only
  remote activity feed under Modify Profile.
- ActivityPub actor, inbox, outbox, object and followers endpoints.
- WebFinger and NodeInfo (requires webserver rewrite rules; see docs/DEPLOYMENT.md).
- RSA-SHA256 HTTP request signing and signature verification.
- AES-256-GCM encrypted private actor keys, tied to the SMF auth_secret.
- Outbound publication of new public topics, edits, and deletions; optional
  per-member publication of own public topics.
- Signed remote Follow, Create, Update, Delete, Like and Announce intake, held
  for moderator review; recognised Follow responses and Undo handled directly.
- Private read-only feed showing admin-approved incoming activities.
- Configurable domain blocks and SSRF-resistant remote HTTPS fetching.
- Persistent outbound delivery queue, retries, and a CLI worker.
- Admin diagnostics, review and queue screens.
- Optional integration hook for Activity Stream and other modifications.

AFTER INSTALLATION
------------------
1. Read docs/DEPLOYMENT.md (included in the package ZIP).
2. Federation is OFF after installing. Leave it off until HTTPS, .well-known
   routing, guest permissions and the queue cron job are configured.
3. Work through the verification checklist in docs/DEPLOYMENT.md, ideally with
   a disposable account on another Fediverse server, before announcing it.

NOT INCLUDED IN THIS VERSION
----------------------------
- Automatic creation of SMF topics from remote Create activities.
- Automatic conversion of remote likes/boosts to native SMF likes.
- Attachment federation, scheduled tasks inside SMF, and native UI for
  threaded remote conversations.
- Fediverse servers differ in how they handle Group actors, signatures and
  Announce. Please report interoperability problems at https://boredskull.net.

UPGRADING
---------
Install over 1.0.1 or any 1.0.2 test build through the Package Manager.
Existing actors, keys, followers and settings are kept.

CHANGELOG
---------
1.0.2 - Protocol, privacy and maintenance release
- Fixed feed dispatch fall-through and escaped outbound text, retaining literal
  brackets and BBCode link destinations as text. Subjects appear in Note
  content; Notes include publication dates.
- Edited topics are refreshed from saved content; approval, current body and
  public access are checked before serving or delivering. Added topic removal,
  approval and move hooks, plus CLI reconciliation.
- Ordered signed headers with required digest/date/host/request-target, signed
  actor GETs, ID-only Follow responses, immediate validated Undo, and WebFinger
  handle lookup.
- Owner-only follower listing/removal, daily per-host pending quota, retention
  and bounded manual processing. Teredo and 6to4 blocked; UTC dates and
  NodeInfo profile corrected.
- Public Create/Update/Delete activities are grouped by same-origin advertised
  shared inbox, with individual delivery for missing or invalid metadata.
  Existing followers keep individual routing until their next approved Follow.
- Active recipients are rechecked before queued Create/Update delivery.
- Withdrawn, approved public first posts can be restored under a fresh object
  ID, never into a private or recycle board or a disabled identity.
- Credits entry, expanded UI translations and localised protocol diagnostics.
  Removed the unused auto_queue setting.
- Release fixes: no board is treated as public when guest access is switched
  off; JSON responses bypass SMF's output buffers so session IDs can never be
  written into ActivityPub URLs; log and error text is truncated without
  breaking UTF-8; remaining hard-coded English is now translatable; the
  upgrade readme displays correctly and 1.0.2 test builds can upgrade in place.

1.0.1 - Admin interface refresh
- Configuration > Modification Settings > Federation, using SMF native hooks.
- Installation redirects to the new location.
- Responsive navigation, grouped settings, inline help and enabled/disabled status.
- Admin stylesheet loads even when federation is disabled.
- Redesigned member identity and feed with responsive cards, selectable
  address, clear status and follow controls.
