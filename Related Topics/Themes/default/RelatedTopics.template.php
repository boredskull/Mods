<?php

/**
 * Related Topics for SMF 2.1 - template
 *
 * @package RelatedTopics
 * @version 1.0.3
 * @license ISC Bryan "Bored Skull" Deakin
 */

/**
 * Layer above the topic.
 */
function template_related_topics_above()
{
	global $context;

	if ($context['related_topics_opts']['position'] == 'top')
		template_related_topics_block();
}

/**
 * Layer below the topic.
 */
function template_related_topics_below()
{
	global $context;

	if ($context['related_topics_opts']['position'] == 'top')
		return;

	template_related_topics_block();

	// Move the block up so it sits between the posts and the quick reply box.
	if ($context['related_topics_opts']['position'] == 'before_reply')
		echo '
	<script>
		(function () {
			var rt = document.getElementById("related_topics"),
				target = document.getElementById("quickreply_anchor") || document.getElementById("quickreply");
			if (rt && target && target.parentNode)
				target.parentNode.insertBefore(rt, target);
		})();
	</script>';
}

/**
 * The block itself.
 */
function template_related_topics_block()
{
	global $context, $txt, $scripturl;

	$o = $context['related_topics_opts'];

	$classes = array(
		'rt_wrap',
		'rt_style_' . $o['style'],
		'rt_scheme_' . $o['scheme'],
		'rt_cols_' . $o['columns'],
		'rt_ratio_' . $o['ratio'],
		'rt_hover_' . $o['hover'],
		'rt_mobile_' . $o['mobile'],
		$o['show_media'] ? 'rt_hasmedia' : 'rt_nomedia',
	);
	if ($o['animate'])
		$classes[] = 'rt_animate';

	$target = $o['new_window'] ? ' target="_blank" rel="noopener"' : '';

	echo '
	<section id="related_topics" class="', implode(' ', $classes), '" style="--rt-accent: ', $o['accent'], '; --rt-cols: ', $o['columns'], ';" aria-labelledby="rt_heading">
		<div class="rt_head">
			<h3 id="rt_heading" class="rt_title">', $o['title'], '</h3>
		</div>
		<div class="rt_grid">';

	foreach ($context['related_topics'] as $i => $item)
	{
		echo '
			<a class="rt_card" href="', htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'), '"', $target, ' title="', $item['subject_full'], '" style="--rt-hue: ', $item['hue'], '; --rt-i: ', $i, ';" data-initial="', $item['initial'], '">';

		if ($o['show_media'])
		{
			echo '
				<div class="rt_media">';

			if (!empty($item['image']))
				echo '
					<img src="', htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'), '" alt="" loading="lazy" decoding="async" onerror="this.parentNode.classList.add(\'rt_broken\'); this.remove();">';

			echo '
					<span class="rt_placeholder" aria-hidden="true"><span>', $item['initial'], '</span></span>';

			if ($item['is_video'])
				echo '
					<span class="rt_play" aria-hidden="true"></span>';

			echo '
				</div>';
		}

		echo '
				<div class="rt_body">';

		if ($o['show_board'])
			echo '
					<span class="rt_kicker">', $item['board']['name'], '</span>';

		echo '
					<span class="rt_subject">', $item['subject'], '</span>';

		if ($o['show_excerpt'] && $item['excerpt'] !== '')
			echo '
					<span class="rt_excerpt">', $item['excerpt'], '</span>';

		if ($o['show_meta'])
		{
			echo '
					<span class="rt_meta">
						<span class="rt_by">', $txt['rtopics_by'], ' <strong>', $item['poster']['name'], '</strong> &middot; <time datetime="', gmdate('Y-m-d\TH:i:s\Z', $item['timestamp']), '">', $item['time'], '</time></span>
						<span class="rt_stats">
							<span class="rt_stat" title="', $txt['rtopics_replies'], '">
								<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1.1-4.4A8 8 0 1 1 21 12z"/></svg>
								<span class="rt_sr">', $txt['rtopics_replies'], ': </span>', $item['replies_formatted'], '
							</span>
							<span class="rt_stat" title="', $txt['rtopics_views'], '">
								<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
								<span class="rt_sr">', $txt['rtopics_views'], ': </span>', $item['views_formatted'], '
							</span>
						</span>
					</span>';
		}

		echo '
				</div>
			</a>';
	}

	echo '
		</div>
	</section>';
}
