<?php

/**
 * Rich Link Previews: card templates.
 *
 * Every value in $card is already HTML-safe when it reaches this file.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.3
 */

function template_richlinks_block(array $cards)
{
	$out = '
		<div class="richlinks">';

	foreach ($cards as $card)
		$out .= template_richlinks_card($card);

	return $out . '
		</div>';
}

function template_richlinks_card(array $card)
{
	$classes = 'richlink' . (!empty($card['internal']) ? ' richlink_internal' : '') . (empty($card['image']) ? ' richlink_noimage' : '');

	$out = '
			<a class="' . $classes . '" href="' . $card['href'] . '"' . (empty($card['internal']) ? ' target="_blank" rel="nofollow ugc noopener noreferrer"' : '') . '>';

	if (!empty($card['image']))
		$out .= '
				<span class="richlink_thumb"><img src="' . $card['image'] . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="var c=this.closest(\'.richlink\');if(c)c.classList.add(\'richlink_noimage\');this.parentNode.remove();"></span>';

	$out .= '
				<span class="richlink_body">
					<span class="richlink_site">' . $card['site'] . (!empty($card['domain']) ? ' <span class="richlink_domain">' . $card['domain'] . '</span>' : '') . '</span>
					<span class="richlink_title">' . $card['title'] . '</span>';

	if (!empty($card['description']))
		$out .= '
					<span class="richlink_desc">' . $card['description'] . '</span>';

	if (!empty($card['meta']))
		$out .= '
					<span class="richlink_meta">' . $card['meta'] . '</span>';

	return $out . '
				</span>
			</a>';
}
