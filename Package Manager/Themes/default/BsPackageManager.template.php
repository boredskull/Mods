<?php
/** Package Manager Enhancements 1.0.0: read-only dashboard. */
if (!defined('SMF')) die('No direct access');
function template_bspm_dashboard()
{
    global $context, $txt, $scripturl;
    $h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $items = $context['bspm_packages'];
    $counts = array('all' => count($items), 'installed' => 0, 'available' => 0, 'update' => 0, 'other' => 0);
    foreach ($items as $item) {
        $key = $item['status'] === 'available' && $item['type'] !== 'modification' ? 'other' : $item['status'];
        if (isset($counts[$key])) ++$counts[$key];
    }
    echo '<div id="bspm" class="bspm-page">';
    echo '<section class="bspm-hero windowbg"><div><p class="bspm-eyebrow">', $h($txt['bspm_eyebrow']), '</p><h2>', $h($txt['bspm_dashboard']), '</h2><p>', $h($txt['bspm_hero_desc']), '</p><p><a href="', $h($scripturl . '?action=admin;area=packages;sa=browse;bspm_classic=1'), '">', $h($txt['bspm_classic']), '</a></p></div></section>';
    echo '<section class="bspm-summary" aria-label="', $h($txt['bspm_overview']), '">';
    // Every card is a genuine library filter. The library starts in neutral All mode.
    foreach (array('available' => array('pending', 'pending_help'), 'update' => array('local_updates', 'update_help'), 'installed' => array('installed', 'installed_help'), 'other' => array('other', 'other_help'), 'all' => array('total', 'total_help')) as $key => $labels) {
        echo '<a href="#bspm-packages" role="button" class="windowbg bspm-stat bspm-stat-', $h($key), '" data-bspm-filter="', $h($key), '" aria-controls="bspm-table" aria-pressed="', $key === 'all' ? 'true' : 'false', '"><span class="bspm-stat-number">', $counts[$key], '</span><span class="bspm-stat-label">', $h($txt['bspm_' . $labels[0]]), '</span><span class="bspm-stat-help">', $h($txt['bspm_' . $labels[1]]), '</span><span class="bspm-stat-action">', $h($txt['bspm_view_library']), '</span></a>';
    }
    echo '</section>';
    if ($counts['available']) echo '<section class="bspm-pending" role="status"><div><strong>', $h(sprintf($txt['bspm_pending_count'], $counts['available'])), '</strong><p>', $h($txt['bspm_pending_desc']), '</p></div><button type="button" class="button" data-bspm-filter="available">', $h($txt['bspm_view_pending']), '</button></section>';
    else echo '<section class="bspm-pending bspm-pending-empty" role="status"><strong>', $h($txt['bspm_no_pending']), '</strong><p>', $h($txt['bspm_pending_empty_desc']), '</p></section>';
    if ($context['bspm_truncated']) echo '<p class="noticebox">', $h($txt['bspm_limited']), ' ', $h($txt['bspm_counts_incomplete']), '</p>';
    echo '<section class="bspm-panel windowbg" id="bspm-packages" data-active-filter="all"><div class="bspm-panel-head"><div><h3>', $h($txt['bspm_library']), '</h3><p>', $h($txt['bspm_library_desc']), '</p></div><button type="button" class="button" id="bspm-reset-filter">', $h($txt['bspm_reset']), '</button></div>';
    echo '<div class="bspm-toolbar"><div class="bspm-field bspm-field-search"><label for="bspm-search">', $h($txt['bspm_search']), '</label><input id="bspm-search" type="search" autocomplete="off" placeholder="', $h($txt['bspm_search_placeholder']), '"></div><div class="bspm-field"><label for="bspm-filter">', $h($txt['bspm_show']), '</label><select id="bspm-filter"><option value="all">', $h($txt['bspm_all_packages']), '</option><option value="available">', $h($txt['bspm_pending']), '</option><option value="update">', $h($txt['bspm_local_updates']), '</option><option value="other">', $h($txt['bspm_other']), '</option><option value="installed">', $h($txt['bspm_installed']), '</option></select></div><div class="bspm-field"><label for="bspm-sort">', $h($txt['bspm_sort']), '</label><select id="bspm-sort"><option value="name">', $h($txt['bspm_name_asc']), '</option><option value="name-desc">', $h($txt['bspm_name_desc']), '</option><option value="status">', $h($txt['bspm_status']), '</option></select></div></div>';
    echo '<div class="bspm-scroll"><table class="table_grid bspm-table bspm-package-list" id="bspm-table"><thead><tr class="titlebg"><th scope="col">', $h($txt['bspm_package']), '</th><th scope="col">', $h($txt['bspm_version']), '</th><th scope="col">', $h($txt['bspm_status']), '</th><th scope="col">', $h($txt['bspm_actions']), '</th></tr></thead><tbody>';
    foreach ($items as $item) {
        $status = $item['status'];
        $filterStatus = $status === 'available' && $item['type'] !== 'modification' ? 'other' : $status;
        $name = $item['name'] ?: $item['id'];
        $filename = $item['filename'];
        // The native install action is only appropriate for a new package or a newer local version.
        $canReview = $filename !== '' && ($status === 'available' || $status === 'update');
        $review = $canReview ? $scripturl . '?action=admin;area=packages;sa=install;package=' . rawurlencode($filename) : $scripturl . '?action=admin;area=packages;sa=browse';
        $label = $status === 'available' ? ($filterStatus === 'available' ? $txt['bspm_awaiting_install'] : $txt['bspm_other']) : ($status === 'update' ? $txt['bspm_local_update_available'] : $txt['bspm_installed']);
        $version = $item['installed'] ?: ($item['available'] ?: '—');
        echo '<tr class="windowbg" data-bspm-name="', $h(strtolower($name . ' ' . $item['id'] . ' ' . $item['author'] . ' ' . $version)), '" data-bspm-status="', $h($filterStatus), '" data-bspm-installed="', $item['installed'] === '' ? '0' : '1', '">';
        echo '<td data-label="', $h($txt['bspm_package']), '"><div class="bspm-package-identity"><span class="bspm-package-icon" aria-hidden="true">▣</span><div><strong>', $h($name), '</strong><small>', $h($item['id']), '</small></div></div></td>';
        echo '<td data-label="', $h($txt['bspm_version']), '">', $h($version), '</td><td data-label="', $h($txt['bspm_status']), '"><span class="bspm-pill bspm-', $h($status), '">', $h($label), '</span></td>';
        echo '<td data-label="', $h($txt['bspm_actions']), '"><div class="bspm-row-actions"><details class="bspm-details"><summary>', $h($txt['bspm_details']), '</summary><div class="bspm-details-body">';
        echo '<p><strong>', $h($txt['bspm_package_id']), '</strong> ', $h($item['id']), '</p>';
        if (!empty($item['author'])) echo '<p><strong>', $h($txt['bspm_author']), '</strong> ', $h($item['author']), '</p>';
        if (!empty($item['description'])) echo '<p>', $h($item['description']), '</p>';
        echo '<p><strong>', $h($txt['bspm_installed_version']), '</strong> ', $h($item['installed'] ?: $txt['bspm_not_installed']), '</p>';
        echo '<p><strong>', $h($txt['bspm_local_version']), '</strong> ', $h($item['available'] ?: $txt['bspm_no_archive']), '</p>';
        if (!empty($item['date'])) echo '<p><strong>', $h($txt['bspm_installed_on']), '</strong> ', $h(strip_tags(timeformat($item['date']))), '</p>';
        foreach ((array) $item['notes'] as $note) echo '<p>', $h(is_scalar($note) ? $note : ''), '</p>';
        echo '</div></details>';
        if ($canReview) echo '<a class="button" href="', $h($review), '">', $status === 'update' ? $h($txt['bspm_review_update']) : $h($txt['bspm_review_install']), '</a>';
        else echo '<a class="button" href="', $h($review), '">', $h($txt['bspm_browse']), '</a>';
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div><p id="bspm-empty" hidden role="status">', $h($txt['bspm_empty']), '</p></section>';
    echo '<section class="bspm-panel windowbg"><div class="bspm-panel-head"><div><h3>', $h($txt['bspm_history']), '</h3><p>', $h($txt['bspm_history_note']), '</p></div></div><div class="bspm-scroll"><table class="table_grid bspm-table"><thead><tr class="titlebg"><th scope="col">', $h($txt['bspm_date']), '</th><th scope="col">', $h($txt['bspm_package']), '</th><th scope="col">', $h($txt['bspm_version']), '</th><th scope="col">', $h($txt['bspm_activity']), '</th><th scope="col">', $h($txt['bspm_by']), '</th></tr></thead><tbody>';
    foreach ($context['bspm_history'] as $event) {
        $removed = (int) $event['install_state'] === 0;
        $date = $removed ? (int) $event['time_removed'] : (int) $event['time_installed'];
        $actor = $removed ? $event['member_removed'] : $event['member_installed'];
        $event_label = $removed ? $txt['bspm_uninstall'] : ((int) $event['install_state'] === 2 ? $txt['bspm_upgraded'] : $txt['bspm_installed']);
        echo '<tr class="windowbg"><td>', $h($date ? strip_tags(timeformat($date)) : $txt['bspm_unknown']), '</td><td>', $h(html_entity_decode($event['name'], ENT_QUOTES, 'UTF-8')), '</td><td>', $h($event['version']), '</td><td>', $h($event_label), '</td><td>', $h($actor !== '' ? html_entity_decode($actor, ENT_QUOTES, 'UTF-8') : $txt['bspm_unknown']), '</td></tr>';
    }
    echo '</tbody></table></div>';
    if (!$context['bspm_history']) echo '<p>', $h($txt['bspm_no_history']), '</p>';
    echo '</section>';
    if (!empty($context['bspm_cards'])) {
        echo '<section class="bspm-panel windowbg"><h3>', $h($txt['bspm_integrations']), '</h3>';
        foreach ($context['bspm_cards'] as $card) {
            if (!is_array($card)) continue;
            echo '<p><strong>', $h($card['title'] ?? ''), '</strong> ', $h($card['description'] ?? ''), '</p>';
        }
        echo '</section>';
    }
    echo '<p class="bspm-footnote">', $h($txt['bspm_footnote']), '</p></div>';
}
