<?php
/**
 * Mentions Expanded - templates
 *
 * @package MentionsExpanded
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

/**
 * Checkbox list of mentionable membergroups on the settings page.
 */
function template_callback_mentions_expanded_groups()
{
	global $context, $txt;

	echo '
						<dt>
							<span>', $txt['mentions_expanded_groups'], '</span><br>
							<span class="smalltext">', $txt['mentions_expanded_groups_desc'], '</span>
						</dt>
						<dd>';

	if (empty($context['mex_all_groups']))
		echo '
							<em>', $txt['mentions_expanded_no_groups'], '</em>';
	else
		foreach ($context['mex_all_groups'] as $group)
			echo '
							<label><input type="checkbox" name="mentions_expanded_groups[]" value="', $group['id'], '"', $group['checked'] ? ' checked' : '', '> ', htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8'), '</label><br>';

	echo '
						</dd>';
}
