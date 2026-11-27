# BoredSkull Accessibility Assistant 1.3.1

Standalone accessibility authoring and reading aids for **Simple Machines Forum 2.1.x**.
Author: BoredSkull. Licence: ISC. No required companion mods; no core or theme edits.

## Important scope: no universal compliance claim

**This modification does not, and cannot, make an arbitrary theme or forum fully WCAG compliant.**
There is no single tool that can automatically meet "all known accessibility guidelines".
It implements a useful subset of checks and reading preferences in line with guidance from:

- **WCAG 2.2**, the current W3C Recommendation baseline (principles: perceivable, operable, understandable, robust). WCAG 2.1 and 2.0 are substantially included in 2.2. Conformance at A, AA or AAA requires evaluating the complete site and content, not this modification.
- **ATAG 2.0** Parts A (accessible authoring interface) and B (helping authors make accessible content).
- **WAI-ARIA 1.2** principles: use semantic HTML first; announce feedback without requiring a mouse; avoid inappropriate ARIA roles.
- Other jurisdictional requirements (e.g. EN 301 549 / Section 508) may impose additional testing and obligations. This package provides no legal certification.

## Included functionality

1. **On-posting accessibility checker**: a button is added progressively to SMF's new-topic and reply editor where the standard message textarea exists. Shows advisory findings. Works with typical SCEditor instances (using editor `.val()`) and plain textarea. No posting blocks or automatic changes to the post.
2. **Standalone checker**: `Profile > Modify Profile > Accessibility Assistant` (the legacy `index.php?action=accessibility` link remains available). Submit BBCode and receive server-rendered suggestions, useful when JavaScript is unavailable or themes differ. Text is not stored by the modification. Normal SMF server/application logs remain outside its control.
3. **Content checks**: `[img]` without a genuine BBCode `alt` parameter (quote-aware parsing), generic image alt, vague link text including nested formatting, hex colour contrast against *assumed white* (not actual theme colour), very small BBC sizes, video/audio captions and transcripts, data tables, attachments, and animated/flashing content. Manual review required for any positive or negative result.
4. **Browser-local reading preferences**: larger main-area text, more spacing, underlined text links, lower motion, stronger keyboard focus indication. Applied opt-in via CSS classes; only the member's own browser remembers these preferences (local storage). These controls do not override native browser accessibility tools.
5. **Colour contrast calculator**: compare any two supplied hex colours and see numerical thresholds for WCAG 2.2 AA normal and large text, and AAA normal text. User must supply actual rendered colours.
6. **Skip to main content**: focusable skip link added when the active theme has a recognised main content target. Gracefully skipped otherwise.
7. **Admin controls**: enable/disable mod, composer button and skip link. `Admin > Configuration > Accessibility Assistant` (exact admin menu placement determined by SMF).
8. **Extension hook**: `integrate_baa_audit` lets compatible modifications append advisory findings to the server-side checker. Optional; no dependencies on Questions & Answers, Breeze, Activity Stream or other modifications. Custom results use a safe, documented finding schema (below).
9. **Progressive enhancement**: no server-side rejection of posts. No database tables. Uses SMF integration hooks, with a light CSS/JS file in the default theme assets inherited by SMF themes when appropriate.

## Pre-release corrections included in 1.0.0

- Parameter-aware `img` BBCode scanning distinguishes a real `alt` parameter from `title` text or `data-alt`. Duplicate/ambiguous parameters are flagged for review.
- Single forward-only bounded scans of `img` and `url` tokens replace repeated attempts to match unclosed links. Formatted generic links now agree across the PHP and JavaScript checkers.
- Public checker results are capped, schema-validated and HTML-escaped, including extension findings.
- Input beyond the checker limit produces an explicit **partial review warning**. The server caps by UTF-8 byte count (200,000 bytes); the browser checks a maximum of 200,000 JavaScript characters. Multi-byte content can make the two limits differ.
- Keyboard focus uses a two-colour high-contrast indicator with forced-colours support; text-size preference no longer compounds on nested Curve content areas.
- Previously fixed English interface copy is now passed through SMF language strings, including guidance, contrast results, storage warnings and posting hints. The language file can be translated.

## Developer integration format

The `integrate_baa_audit` hook receives a reference to `$found` and a bounded string of BBCode. Third-party mods may add entries by unique ID:

```php
$found['my_accessibility_rule'] = array(
    'id' => 'my_accessibility_rule',
    'title' => 'Check this content',
    'detail' => 'A plain-text explanation of what to review.',
    'reference' => 'WCAG 2.2 1.1.1',
    'example' => 'Optional plain-text example',
);
```

`id` must be lowercase letters, numbers and underscores (up to 64 characters); custom results need nonempty `title` and `detail`. At most 50 valid unique findings are displayed. Each string is length-limited and escaped as text, never interpreted as HTML. Use SMF language strings for these fields. The hook is server-side only; browser editor checks are built-in.

## Installation

1. Backup files and database. Use a staging forum.
2. Admin > Packages > Download Packages > Upload Package, then install ZIP.
3. Open your own **Profile > Modify Profile > Accessibility Assistant** for the checker and reading preferences. The old `?action=accessibility` URL remains functional for existing bookmarks.
4. Open Admin > Configuration > Accessibility Assistant to set site switches.
5. Test the posting form on active themes, including SCEditor and plain-text modes.

Uninstall removes package hooks, files and three settings. Browser-local preferences remain in each device's storage until reset/cleared; the JS and CSS are removed, so these inactive preferences have no effect after uninstall.

## WCAG/ATAG mapping: what is checked vs what requires manual testing

| Standard | Assistant | Important limits |
| --- | --- | --- |
| WCAG 1.1.1 Non-text Content | Flags image BBCode with missing/generic `alt`; attachments reviewed | Cannot judge meaning of alternative text, attachments or all dynamic images. Verify SMF's BBC syntax on your forum. |
| WCAG 1.2.x Time-based Media | Warns about embedded media | Cannot inspect captions, transcripts, sign language or audio descriptions. |
| WCAG 1.3.1 Info and Relationships | Table review prompt | Cannot guarantee correct headings, form labels, relationships or landmark structure in a theme. |
| WCAG 1.4.1 Use of Color | Manual checklist | Cannot know if information is conveyed only by colour. |
| WCAG 1.4.3 Contrast (Minimum) | Flags low-contrast explicit hex colours against assumed white | Cannot determine real computed colours, size, font weight, imagery, transparent backgrounds or actual contrast. |
| WCAG 1.4.4 Resize Text / 1.4.12 Text Spacing | Opt-in preferences | Need browser zoom 200%, reflow and clipping tests across themes. |
| WCAG 2.1.x Keyboard | Native controls and skip-link keyboard access | Keyboard testing required for SMF, themes and third-party mods. |
| WCAG 2.2.x Enough Time / WCAG 2.3.x Seizures | Animation warning and reduced-motion option | Not a flash analysis tool or media pause-control implementation. |
| WCAG 2.4.x Navigation / Focus | Skip link where possible; stronger focus indicator preference | Focus order, focus not obscured, headings, link purpose and name all need manual checks. |
| WCAG 2.5.8 Target Size (Minimum) | Assistant controls designed for 24px+ targets | Other theme controls and exceptions need inspection. |
| WCAG 3.x Understandable | Advisory text; labels, legends, error feedback | Language identification, predictable navigation and complete error prevention are outside scope. |
| WCAG 4.1.2 Name, Role, Value / 4.1.3 Status Messages | Semantic native controls; status region for live results | Must test final DOM and assistive technology. |
| ATAG 2.0 A/B | Accessible interface intent, prompts and checks | Full ATAG conformance requires an audit of the entire authoring environment. |

## Recommended manual verification before release

- Keyboard-only navigation on Curve and every other installed theme, including all posting modes.
- NVDA + Firefox, VoiceOver + Safari, and TalkBack + Chrome; verify labels, announcements and editor behaviour.
- 200% and 400% zoom, 320 CSS-pixel reflow, text spacing overrides, dark/light themes.
- Contrast (text 4.5:1; large 3:1; non-text UI components 3:1, subject to WCAG exceptions).
- Mobile and touch target sizes, focus-visible, focus order, sticky headers/focus obscured.
- User-generated images, attachments, inline media captions and links; verify real output markup, not only input BBCode.
- Browser storage disabled; JavaScript disabled; reduced-motion system setting.

## Security and privacy

- The checker examines only submitted BBCode. It performs no external requests and writes no post body to the database.
- The browser checker operates locally; settings are stored in `localStorage`, not an online account.
- Admin saves require `admin_forum`, session validation and a CSRF token. Standalone checker submissions validate session and token.
- The server-side checker reviews at most 200,000 bytes, using forward-only bounded BBCode scans; the client checks up to 200,000 characters. Both report when input is truncated. Every result is schema-validated and escaped before server-side display. The client inserts result text with `textContent`, not HTML.
- Do not paste passwords, private messages or sensitive information into any checker form.

## Known limitations / test status

No actual running SMF 2.1 plus MySQL staging test, browser assistive-tech audit or WCAG certification has been performed in this environment. Static syntax and isolated tests are provided in `tests` but cannot substitute for full integration testing. Composer injection may not display on heavily customised editor themes; the standalone checker remains available. A clean scan, or an input-limit warning, must never be interpreted as evidence that the post is accessible. Colour calculations are estimates based on white background and do not report conformance. Input in WYSIWYG editor may be HTML rather than BBCode depending on SCEditor mode.

## Version 1.0.1

- Moved member settings and the checker into **Profile > Modify Profile > Accessibility Assistant** using SMF 2.1 `integrate_pre_profile_areas`.
- Removed the standalone main-menu entry. Existing browser-local preference keys and all checker behaviour are preserved.
- Maintained the legacy direct checker URL for bookmarks; admin controls remain in Admin > Configuration.
- Added upgrade cleanup for the old menu hook and an inert compatibility method.


## 1.0.2 profile navigation fix
- Corrected SMF profile area permission structure (`own`/`any`) so the menu item is enabled for the profile owner.
- Use `$context["id_member"]` and `$context["user"]["is_owner"]` rather than a non-global `$memID` in the profile action.
- Register both modern `integrate_profile_areas` and legacy `integrate_pre_profile_areas` for SMF 2.1.x compatibility.


## Version 1.3.1 (cumulative upgrade from 1.0.2)

This package installs over the older Accessibility Assistant using the SMF Package Manager. The ISC license remains included. Back up forum files and database before installing any modification, and test on a staging forum first.

### What's new

- **Optional live checking:** A debounced (650 ms) browser-side advisory check on the SMF post editor, with a manual checking button still available. Suggestions never block posting. Live mode can be disabled by administrators or by an individual member.
- **Floating quick-access control:** Opt-in browser-local settings for larger text, link underlines, spacing, reduced movement and stronger keyboard focus, with an Escape-key dismissal.
- **Reading presets:** Comfortable reading, high visibility and reduced movement; add optional line-height, paragraph-spacing, reading-width and serif-font preferences. All are browser-local.
- **Topic focus reading mode:** A toggle appears on recognised SMF post layouts. It reduces some visual distractions and narrows the topic reading column, without editing or removing forum content. Depends on theme markup; verify against your theme.
- **Contrast preview:** A sample preview accompanies the numerical colour contrast calculator.
- **Admin configuration and diagnostics:** Individual enable/disable settings for the new features; an informational environment/configuration display. An `integrate_baa_diagnostics` server-side hook lets other mods supply plain-text diagnostic fields.
- **Client-side extension interface:** `window.BoredAccessibilityAPI.addRule(id, title, detail, reference, test)` lets other mods register limited local, advisory checks; listen for the `boredAccessibilityReady` document event. Third-party rule errors are isolated. Existing `integrate_baa_audit` PHP hook is preserved.
- **English strings and test scripts:** New settings and guidance labels are translatable. Run `php tests/smoke.php` and `node tests/smoke.js` to check basic parsers and regressions outside SMF.

### Installation and upgrades

1. Back up both forum files and the database; prefer a staging copy for the first installation.
2. Admin > Package Manager > Upload Package, then install `AccessibilityAssistant_1.3.1.zip`.
3. Admin > Configuration > Accessibility Assistant settings to configure features.
4. Clear SMF/browser caches if changes are not reflected immediately.
5. Test a regular member's edit/new-post workflow, an admin account, and your active theme on both desktop and mobile.

The installer sets defaults only if a setting does not already exist. Existing 1.0.x preferences and server settings are preserved. Uninstall removes the modification's seven server-side settings; browser-local settings may remain until manually reset/cleared.

### Important limitations

- Live checking is a heuristic on editor text. SCEditor WYSIWYG HTML can differ from BBCode; switch to BBCode mode for dependable BBCode-specific checks. This release does not implement automatic correction of editor text or remote image analysis.
- Findings are aggregated by rule, not by every occurrence. There is currently no reliable jump-to-error control in SCEditor.
- Image alternative-text syntax is theme/editor-dependent: verify rendering. The assistant does not modify SMF's BBCode parser.
- The focus reading toggle changes presentation only and is not a fully isolated document reader.
- Admin diagnostics are configuration checks, **not a full accessibility audit** or certification.
- No optional BoredSkull mod integration is mandatory: extensions must register their own rules/hooks. The modification works independently.
- Accessibility conformance still needs manual testing with keyboards, screen readers and real users.

### Extension API example

```javascript
document.addEventListener('boredAccessibilityReady', function () {
  if (!window.BoredAccessibilityAPI) return;
  window.BoredAccessibilityAPI.addRule(
    'my_mod_hint', 'Check linked resource',
    'Provide context for the linked resource.', 'WCAG 2.2 2.4.4',
    function (text) { return text.indexOf('[myresource]') !== -1; }
  );
});
```

PHP integration hooks: `integrate_baa_audit` accepts the mutable findings array and source text; `integrate_baa_diagnostics` accepts a mutable associative array of bounded, display-only scalar fields. Third-party code must not include private information in diagnostics. Both hooks are optional.

### Release history

- 1.0.x: Original accessibility checking and personal preferences; 1.0.2 corrected profile navigation.
- 1.3.1: Live suggestions, reading options, quick controls, diagnostics and documented optional extension interface.


## Version 1.3.1 (maintenance update)

- Larger text also applies to the top navigation menu labels, including typical Curve and custom-theme menu selectors.
- Repositioned the floating accessibility button and its panel to the lower-left, with safe-area spacing and improved mobile sizing.
- Added explicit button typography and box sizing to prevent custom theme CSS from clipping its text.
- Existing locally saved reading preferences are preserved.
