# BoredSkull Topic Forms 1.1.0 (test build)

Topic Forms adds board-specific forms to ordinary new topics on SMF 2.1.x. Install or upgrade the ZIP through Package Manager. The package supports direct upgrades from 1.0.1 and 1.0.2. Existing board forms initially use the standard editor mode. The package uses hooks and its own template, JavaScript and stylesheet. Licence: ISC.

## Configure a board

1. Open **Admin → Configuration → Modification Settings → Topic Forms** and select a board. It loads automatically. With JavaScript disabled, use **Load board**.
2. Tick **Enabled on this board**, enter a title and instructions, and choose the **New-topic layout**.
3. Add up to 20 fields. Short text, long text, number, checkbox and dropdown fields are supported. Mark required answers, enter dropdown choices on separate lines, and use **Up** and **Down** to set the order. Field keys identify answers and are generated when left blank.
4. Optionally select a dropdown as the automatic subject prefix. A selected value such as Bug produces a subject such as “[Bug] Installation help”.
5. Select **Save topic form**. Instructions support line breaks and up to 4,000 characters. Text previously cut off in an earlier release must be re-entered.

## Reuse or move forms

**Save reusable layout** stores the current editor contents under the name you enter. It captures the title, instructions, posting mode, prefix and ordered fields. Enter the same layout name to update that saved layout. To reuse it, select another board, choose the saved layout and select **Load into editor**. Review the copy, set the board’s enabled checkbox and select **Save topic form**. Saving or updating a reusable layout does not publish changes to a board.

**Move saved form** transfers a board’s saved form, including its enabled setting, to a board with no assignment. The source board goes back to the ordinary SMF posting page. Saved layouts are independent, and previously published topics keep their messages.

## Posting modes

| Mode | Member experience |
| --- | --- |
| Form above the SMF message editor | The form appears alongside the standard composer. Submitted answers are added ahead of the authored message. SMF preview and draft controls handle editor text. |
| Use the form as the new-topic page | The page contains the subject, board form, Post button, and any applicable guest, verification and file attachment controls. The answers supply the entire message. Rich-text editing, preview and drafts are available in standard editor mode. |

Replies, topic edits, polls and calendar events use SMF’s own interfaces. SMF handles topic creation, permissions, moderation, attachments and session checks. At least one answer is needed when the form supplies the entire message. The add-on checks required answers, allowed dropdown choices and message length before publishing. A form definition change can require the member to review their answers and resubmit. Stored topics contain ordinary formatted message text. Draft storage in standard mode does not preserve custom field answers.

## Integration and uninstall

The standalone hook `integrate_bstf_before_post` provides validated field values, the form definition and board ID. Values are raw user input. Escape them for the output context in each listener. The standard editor mode uses SMF’s native posting fields. The form-only template uses the theme’s posting header for the subject and applicable guest fields.

A normal uninstall removes the hooks and package files. Keep the optional Package Manager data-removal checkbox clear to retain board forms and saved layouts.

## Changes in 1.1.0

- Board auto-loading, change warnings when switching boards or loading layouts, a responsive field editor, field reordering and dropdown-specific option controls.
- Named reusable layouts, independent board copies and transfer of an existing assignment to an unassigned board.
- Per-board form-only posting with server-side generation of the topic body.
- Longer instructions with line breaks and an upgrade that preserves existing forms.

### Test-build fixes
- Save draft is no longer blocked by the browser's required-field checks in standard editor mode.
- The SMF 2.1 "Post in board" selector is hidden on form boards, so a topic cannot be sent to a board with a different form or none.
- Answer length is counted in characters, matching the 4,000-character input limit for non-English text.
- Field labels are escaped in posting error messages.
- Pressing Enter in the layout name box saves the layout instead of publishing the board form.
- The Topic Forms tab description now appears in Modification Settings.
- Titles, instructions, labels, dropdown choices and layout names keep text such as `<version>`; everything is escaped on display instead of having tags stripped on save.
- Subject prefixes keep punctuation, so C++ gives “[C++]” and v2.1 gives “[v2.1]”. Only square brackets and invisible control characters are removed.
- Number fields accept decimals in the browser (matching the server check), with the ineffective length attribute and an empty element removed.

## 1.0.1 maintenance changes
- Use native SMF posting fields rather than JavaScript injection (better theme and keyboard support).
- Preview and draft saves never add generated content to the editor or require all form fields. The final submitted topic receives formatted fields once.
- When starting outside a board, choose a board and load its corresponding form before composing.
- Ensure sanitized field keys remain unique even when other keys already have numeric suffixes.
- Reject stale form submissions if an admin changes the form definition while a topic is being written. Values remain available for review.
- Keep attachments in SMF's normal workflow. No integration code or data tables are removed during upgrade.

## 1.0.2 maintenance changes
- Install through SMF's database table API; existing definitions are preserved. The unused legacy `id_form` column is no longer read or created on new installs; it is left intact on upgrades to avoid destructive schema changes.
- Register the Modification Settings menu and credits hooks, with automatic admin redirection.
- Exclude valueless poll requests consistently in the editor and both submission hooks. Removed the boardless composer navigation; SMF's ordinary new-topic composer requires a board and calendar events are excluded.
- Differentiate missing form markers from stale definitions; do not silently bypass required fields on incompatible themes.
- New forms start disabled with no sample required fields.
- Select the prefix field from a dropdown. New blank keys receive random stable IDs, existing keys remain read-only, and removing fields does not renumber saved IDs. The dropdown updates when fields are added, removed or change type.
- Support Unicode letters, combining marks and digits in prefixes; validate against SMF's 100-character subject limit.
- Correct board-name entity handling and localise interface text, error messages and checkbox output.
- Validate final generated message length against the storage byte ceiling and the configured forum character limit; do not append generated content before prefix validation succeeds.
- Do not trust a submitted internal form-only marker.
- Document that integration listeners receive raw field data and must escape it themselves.
- Add a direct 1.0.1 upgrade path and retain ISC licence and ecosystem hook.

