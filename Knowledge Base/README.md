# Knowledge Base 1.0.0

By BoredSkull · ISC licence · Simple Machines Forum 2.1.x

Knowledge Base is a standalone knowledge-article publishing system for SMF. It does not replace forum topics, downloads, SimpleDesk, the SMF Package Manager or the official Simple Machines Modification Site. Optional integration with BoredSkull **Questions & Answers 1.2.0** creates reviewable articles from accepted answers, but Q&A is **not required**.

## Features

- Public article library with categories, search, pagination, article summaries and BBCode articles.
- Private per-editor drafts, explicitly submitted editorial proposals, approval, rejection, revision history and archiving.
- Editing an already published article creates a **proposed revision**. The public text, category, review interval and sort date do **not** change until approval.
- Revision history records the editing member, creation time and optional change note. Approved revisions are available to authorised contributors/reviewers; an unsubmitted draft is visible only to its editor and administrators. Submitted proposals are visible to their editor, reviewers and administrators. Other editors cannot overwrite a draft or submitted proposal.
- Member submission permissions, own-article editing, editing anyone's articles, editorial review and administrator category/settings controls.
- Member-specific **My articles** area for finding drafts, pending submissions and published articles.
- Optional one-vote-per-member **Was this helpful?** feedback, with Yes/No counts.
- Administrators can create, rename, reorder, enable and disable article categories.
- Optional review intervals and due-review list to help keep published documentation current.
- SMF-native BBCode rendering and integration hooks, no core edits and no modifications to theme templates.
- Optional **Promote accepted answer to Knowledge Base** link for BoredSkull Q&A 1.2.0. Importing only prefills the editor. A person must submit and a reviewer must approve the article before it becomes public.

## Install

Admin → Packages → Download Packages → Upload Package. Upload `KnowledgeBase_1.0.0.zip` and install on **SMF 2.1.x**. Back up your forum's files and database first. The installation creates four tables, including distinct submission-state and published-history flags:

- `{db_prefix}kb_categories`
- `{db_prefix}kb_articles`
- `{db_prefix}kb_revisions`
- `{db_prefix}kb_feedback`

Default categories are **Getting Started**, **Guides**, and **Troubleshooting**. You can edit them at Admin → Configuration → Knowledge Base or `index.php?action=knowledgebase;sa=manage`.

## Permissions

The install applies conservative initial defaults to SMF's membergroup permissions:

| Group | Initial Knowledge Base permissions |
| --- | --- |
| Guests | Read published articles only |
| Regular members | Submit articles; edit their own articles |
| Global moderators | Submit; edit own; edit any; review/publish |
| Administrators | All actions via SMF administrator permission |

Update these in SMF's membergroup permissions screen to suit your community. The four permissions are `kb_submit`, `kb_edit_own`, `kb_edit_any` and `kb_review`. Administrators alone manage global settings and categories. **Ordinary members cannot self-publish, and non-administrator reviewers cannot approve their own submissions or revisions.** The check applies both to the original article author and to the editor who submitted the current revision. Authors' proposed updates to a published article require editorial approval.

If no review-capable membergroup is present, an administrator can process the review queue.

## Workflow

1. A permitted member selects **New article** (or optionally promotes an accepted Q&A answer).
2. Select a category, write a title, summary and BBCode body, and optionally configure days between reviews.
3. **Save draft** keeps the change private to its editor and administrators, including when the original article is already published. **Submit for review** explicitly places it in the editorial queue.
4. A reviewer opens **Review submissions**, previews the proposed revision, and approves or rejects it.
5. Approved articles appear in browsing and search. Subsequent edits enter the queue while the approved version stays online.
6. A reviewer can mark an article reviewed. An administrator can archive it; restoring it puts it back in the approval queue.

My articles can be accessed from the Knowledge Base navigation. Article history is restricted to contributors and authorised editors/reviewers; only the draft writer and administrators can see unsubmitted drafts, and approved public pages do not reveal draft content.

## Optional Questions & Answers integration

- Requires **Questions & Answers 1.2.0** to be installed and enabled with an approved accepted answer in an enabled Q&A board.
- The link appears beside accepted replies only to members with permission to submit articles, and only if the source discussion is on a publicly readable board.
- In addition to SMF's `{query_see_board}` checks and approved-topic/message flags, import and approval require that **guests are allowed to view the original board** (and not denied). Content from members-only or restricted boards cannot be promoted automatically. The original source must remain public; if it later moves to a restricted board or becomes unapproved, the published Knowledge Base article is withheld from public browsing, search and direct access until the source becomes public again. The source is never silently published.
- Sources are linked from published articles only when the current viewer can still see the source discussion. Authors should still remove personal details and rewrite answers into durable documentation before submitting. If an independently public article is needed after moving a source to a restricted board, create a new standalone article without copied restricted content.
- If Q&A is not installed, the base Knowledge Base works normally and makes no queries to its tables.

## Security and data privacy

- All content-changing requests require SMF's session token and a POST request.
- Public search and category lists show only published articles in enabled categories.
- Unsubmitted drafts belong to the member who saved them (and administrators), even when they concern somebody else's published article. Reviewers see only explicitly submitted revisions. Published revision history remains available to permitted editors and reviewers. A draft cannot enter the approval queue without the editor explicitly submitting it.
- Article titles, summaries, metadata and change notes are HTML-escaped. Article bodies are rendered with SMF's BBCode renderer, not raw HTML.
- HTML BBCode is not accepted in submissions. Reviewers should nevertheless check submitted article content before approval.
- All queries use SMF's typed parameter placeholders. Pages cap result sets. The package does not send data to third-party services.
- This is not a formal security certification. Test on a staging site and verify theme compatibility and your installed extensions.

## Limitations in 1.0.0

- English UI is included. Additional translations can be added using SMF's language system.
- No automatic email notifications or queued background scanning. Review reminders appear in the administrator dashboard, not via scheduled emails.
- Revision history and **My articles** list up to 100 most recent entries; the public library is paginated at 20 items.
- Search matches article titles and bodies via SQL `LIKE`. On very large collections, a full-text search backend would be preferable.
- Images in BBCode and embedded remote media follow SMF's own content settings. They are not uploaded to Knowledge Base as independent files.
- No live SMF/MySQL integration, usability or assistive-technology certification is implied by static and stub tests.
- Existing test installs of the early pre-release 1.0.0 are upgraded non-destructively by the installer when rerun: new columns are added without removing data, prior **submitted new articles** stay queued, but existing revisions to published articles are conservatively treated as drafts until explicitly resubmitted. Earlier revision history is retained, although only the current published revision can be reliably backfilled as approved in a legacy database. Back up before replacing any installed package with the same version number.

## Uninstall and data

Uninstall removes its hooks and PHP/theme/language/CSS files. **Knowledge Base's four tables and data remain in the database by default** so a reinstall cannot silently destroy accumulated community knowledge. To remove the data permanently, back up first, then tick **Remove all data associated with this modification** when uninstalling: this drops the four `kb_` tables and removes the `kb_` settings and permissions.

## Developer details

Hooks: `integrate_actions`, `integrate_credits`, `integrate_menu_buttons`, `integrate_load_theme`, `integrate_load_permissions`, `integrate_load_illegal_guest_permissions`, `integrate_admin_areas`, `integrate_prepare_display_context`. All are removed on uninstall. No SMF core or theme-template modifications.

## Testing status

Package structure, PHP syntax, hook pairing, static security checks and isolated controller tests are included in development verification. Staging verification on a real SMF 2.1.x database remains necessary before production deployment and submission to the Simple Machines Modification Site.


## 1.0.1
- Alternate category editor backgrounds for easier scanning.
- Redirect to Knowledge Base management after installation.


## 1.0.2
Knowledge Base now appears as its own admin entry immediately below Modification Settings.


## 1.0.2 rebuilt: admin navigation correction
- Knowledge Base now appears as its own subsection inside Configuration > Modification Settings, not as a separate Configuration menu item.
- Existing Knowledge Base management route remains unchanged.

## 1.0.3
- Only display the optional Questions & Answers integration checkbox when the Q&A modification is detected via its SMF integration hooks.
- Preserve the saved preference if Q&A is absent.

## 1.0.4
- Security: article bodies are now HTML-escaped before SMF's BBCode parser, as forum posts are. Before, HTML typed into an article body was output as-is, so a submission could run script for reviewers previewing it and, once approved, for every reader.
- Security: article titles are escaped in the page title.
- Public-source checks for Questions & Answers imports now use SMF 2.1's board visibility table (board_permissions_view), not the legacy board access list, and no longer use a MySQL-only query.
- Importing an accepted Q&A answer no longer fills the editor with HTML entities such as &quot; and <br>.
- Member names with apostrophes or ampersands display correctly instead of as &#039; or &amp;.
- The installer's upgrade step for early 1.0.0 test installs now also works on PostgreSQL.
- Searches are cut to 80 characters without splitting accented letters or emoji.
- Added a credits line and an upgrade path from 1.0.0 to 1.0.3.
- Uninstalling with "Remove all data associated with this modification" ticked now removes the Knowledge Base tables, settings and permissions. Before, the box was shown but did nothing. A normal uninstall still keeps everything.

