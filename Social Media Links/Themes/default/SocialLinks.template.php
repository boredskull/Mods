<?php
/**
 * Social Media Links - profile template.
 *
 * @license ISC - 2026, Bryan "BoredSkull" Deakin
 */

function template_sociallinks_profile()
{
	global $context, $txt, $smcFunc;

	echo '
	<div class="cat_bar">
		<h3 class="catbg profile_hd">', $txt['social_links_profile_area'], '</h3>
	</div>
	<p class="information">', $txt['social_links_profile_desc'], '</p>';

	if ($context['sociallinks_saved'])
		echo '
	<div class="infobox">', $txt['social_links_saved'], '</div>';

	if (!empty($context['sociallinks_errors']))
		echo '
	<div class="errorbox">
		<ul>
			<li>', implode('</li>
			<li>', $context['sociallinks_errors']), '</li>
		</ul>
	</div>';

	echo '
	<form action="', $context['sociallinks_action'], '" method="post" accept-charset="', $context['character_set'], '" id="sociallinks_form">
		<div class="roundframe">
			<dl class="settings sociallinks_form">';

	foreach ($context['sociallinks_networks'] as $key => $net)
	{
		echo '
				<dt', isset($context['sociallinks_errors'][$key]) ? ' class="error"' : '', '>
					<label for="social_', $key, '">
						<span class="sociallinks_formicon" style="--sl-brand:', $net['colour'], '"><i class="', $net['icon'], '" aria-hidden="true"></i></span>
						<strong>', $smcFunc['htmlspecialchars']($net['label']), '</strong>
					</label>
				</dt>
				<dd>
					<input type="text" name="social[', $key, ']" id="social_', $key, '" value="', $smcFunc['htmlspecialchars']($context['sociallinks_values'][$key]), '" maxlength="255" size="40" placeholder="', $smcFunc['htmlspecialchars']($net['placeholder']), '">
				</dd>';
	}

	echo '
			</dl>';

	if (!empty($context['sociallinks_preview']))
		echo '
			<hr>
			<p><strong>', $txt['social_links_preview'], '</strong></p>
			', $context['sociallinks_preview'];

	echo '
			<hr>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="social_save" value="', $txt['save'], '" class="button floatright">
			<br class="clear">
		</div>
	</form>';
}
