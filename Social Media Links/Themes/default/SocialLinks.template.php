<?php
/**
 * Social Media Links - profile template.
 *
 * @copyright 2026, Bryan "BoredSkull" Deakin
 * @license ISC
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
	{
		echo '
	<div class="errorbox" role="alert"><ul>';
		foreach ($context['sociallinks_errors'] as $error)
			echo '<li>', $smcFunc['htmlspecialchars']($error), '</li>';
		echo '</ul></div>';
	}

	echo '
	<form action="', $context['sociallinks_action'], '" method="post" accept-charset="', $context['character_set'], '" id="sociallinks_form">
		<div class="roundframe">
			<div class="sociallinks_form">';

	foreach ($context['sociallinks_networks'] as $key => $net)
	{
		$has_error = isset($context['sociallinks_errors'][$key]);
		$safe_key = $smcFunc['htmlspecialchars']($key);
		$safe_label = $smcFunc['htmlspecialchars']($net['label']);
		$safe_value = $smcFunc['htmlspecialchars']($context['sociallinks_values'][$key]);
		$safe_placeholder = $smcFunc['htmlspecialchars']($net['placeholder']);
		$safe_colour = $smcFunc['htmlspecialchars']($net['colour']);
		$safe_icon = $smcFunc['htmlspecialchars']($net['icon']);
		echo '
				<div class="sociallinks_formrow', $has_error ? ' sociallinks_formrow_error' : '', '">
					<label class="sociallinks_formlabel" for="social_', $safe_key, '">
						<span class="sociallinks_formicon" style="--sl-brand:', $safe_colour, '"><i class="', $safe_icon, '" aria-hidden="true"></i></span>
						<strong>', $safe_label, '</strong>
					</label>
					<div class="sociallinks_formfield">
						<input type="text" name="social[', $safe_key, ']" id="social_', $safe_key,
						'" value="', $safe_value, '" maxlength="255" size="40" placeholder="', $safe_placeholder,
						'"', $has_error ? ' aria-invalid="true" aria-describedby="social_error_' . $safe_key . '"' : '', '>';
		if ($has_error)
			echo '<span class="sociallinks_field_error" id="social_error_', $safe_key, '">', $smcFunc['htmlspecialchars']($context['sociallinks_errors'][$key]), '</span>';
		echo '
					</div>
				</div>';
	}
	echo '
			</div>';

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
