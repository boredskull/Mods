<?php
/** Accessible administration and member UI for Federation. */
function federation_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function federation_t($key) { global $txt; return federation_h($txt[$key]); }
function federation_post_fields($who)
{
    global $context;
    echo '<input type="hidden" name="',federation_h($context['session_var']),'" value="',federation_h($context['session_id']),'">';
    echo '<input type="hidden" name="',federation_h($context[$who.'_token_var']),'" value="',federation_h($context[$who.'_token']),'">';
}
function federation_nav()
{
    global $context, $txt;
    echo '<nav class="federation-nav" aria-label="',federation_t('federation_ui_sections'),'">';
    foreach (array('settings'=>$txt['federation_ui_settings'],'boards'=>$txt['federation_ui_public_boards'],'review'=>$txt['federation_ui_inbox_moderation'],'deliveries'=>$txt['federation_ui_delivery_queue'],'blocks'=>$txt['federation_ui_blocked_domains'],'diagnostics'=>$txt['federation_ui_diagnostics']) as $k=>$v)
        echo '<a class="federation-tab', $context['federation_tab']===$k?' active':'','"', $context['federation_tab']===$k?' aria-current="page"':'',' href="',federation_h($context['federation_base'].';tab='.$k),'">',federation_h($v),'</a> ';
    echo '</nav>';
}
function template_federation_admin()
{
    global $context, $txt;
    echo '<div class="cat_bar"><h3 class="catbg">',federation_h($txt['federation_title']),' ',federation_h(FEDERATION_VERSION),'</h3></div>';
    if ($context['federation_error']!=='') echo '<div class="errorbox" role="alert">',federation_h($context['federation_error']),'</div>';
    if ($context['federation_message']!=='') echo '<div class="infobox" role="status">',federation_h($context['federation_message']),'</div>';
    federation_nav();
    $base=federation_h($context['federation_base'].';tab='.$context['federation_tab']);
    echo '<div class="federation-panel federation-admin">';
    if ($context['federation_tab']==='settings') {
        echo '<div class="federation-overview"><div><h4>' . federation_t('federation_ui_federation_settings') . '</h4><p>',federation_h($txt['federation_intro']),'</p></div><strong class="federation-state">',!empty($context['federation_settings']['enabled'])?$txt['federation_ui_enabled']:$txt['federation_ui_disabled'],'</strong></div>';
        echo '<p class="federation-warn">' . federation_t('federation_ui_before_enabling_federation_configure_https_and_webfinger_routes_c') . '</p><form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
        $groups = array(
            $txt['federation_ui_general'] => array('enabled'=>array($txt['federation_ui_enable_federation'],$txt['federation_ui_master_switch_for_activitypub_federation'])),
            $txt['federation_ui_board_federation'] => array('inbound'=>array($txt['federation_ui_accept_signed_incoming_activities'],$txt['federation_ui_incoming_activities_are_held_for_moderator_review']),'outbound'=>array($txt['federation_ui_federate_new_public_topics'],$txt['federation_ui_publish_new_topics_from_explicitly_enabled_guest_public_boards'])),
            $txt['federation_ui_member_identities'] => array('allow_members'=>array($txt['federation_ui_allow_individual_federated_identities'],$txt['federation_ui_members_can_choose_to_create_their_own_discoverable_identity']),'member_outbound'=>array($txt['federation_ui_federate_opted_in_members_new_public_topics'],$txt['federation_ui_allows_publication_of_eligible_public_topics_by_opted_in_members'])),
        );
        foreach ($groups as $heading=>$options) {
            echo '<fieldset class="federation-group"><legend>',federation_h($heading),'</legend>';
            foreach ($options as $key=>$option)
                echo '<div class="federation-row federation-setting"><div><label for="fed-',federation_h($key),'">',federation_h($option[0]),'</label><p id="fed-help-',federation_h($key),'">',federation_h($option[1]),'</p></div><input id="fed-',federation_h($key),'" aria-describedby="fed-help-',federation_h($key),'" type="checkbox" name="federation_',federation_h($key),'" value="1"',!empty($context['federation_settings'][$key])?' checked':'','></div>';
            echo '</fieldset>';
        }
        echo '<div class="federation-save"><button type="submit" class="button" name="federation_action" value="save_settings">' . federation_t('federation_ui_save_settings') . '</button></div></form>';
    } elseif ($context['federation_tab']==='boards') {
        echo '<h4>' . federation_t('federation_ui_explicit_public_board_opt_in') . '</h4><p class="federation-warn">',federation_h($txt['federation_board_warning']),'</p>';
        foreach ($context['federation_boards'] as $board) {
            echo '<div class="federation-row"><form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
            echo '<strong>',federation_h($board['name']),'</strong> (#',(int)$board['id_board'],') ', $board['public']?$txt['federation_ui_guest_public']:$txt['federation_ui_not_guest_public'], ' · ', $board['active']?$txt['federation_ui_federating']:$txt['federation_ui_not_federating'];
            echo '<input type="hidden" name="board_id" value="',(int)$board['id_board'],'">';
            if ($board['active']) echo ' <button class="button" type="submit" name="federation_action" value="disable_board">' . federation_t('federation_ui_disable') . '</button>';
            elseif ($board['public']) echo ' <button class="button" type="submit" name="federation_action" value="enable_board">' . federation_t('federation_ui_enable') . '</button>';
            echo '</form></div>';
        }
    } elseif ($context['federation_tab']==='review') {
        echo '<h4>',federation_h($txt['federation_review']),'</h4><p class="federation-warn">',federation_h($txt['federation_security']),'</p>';
        if (!$context['federation_inbox']) echo '<p>',federation_h($txt['federation_no_items']),'</p>';
        foreach ($context['federation_inbox'] as $entry) {
            echo '<div class="federation-row"><strong>',federation_h($entry['activity_type']),'</strong>',federation_t('federation_ui_from'),federation_h($entry['remote_actor']),'<p>',federation_t('federation_ui_id'),federation_h($entry['activity_id']),'</p>';
            echo '<details><summary>' . federation_t('federation_ui_view_signed_payload') . '</summary><pre>',federation_h($entry['payload']),'</pre></details>';
            echo '<form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
            echo '<input type="hidden" name="item_id" value="',(int)$entry['id_inbox'],'"><input type="hidden" name="federation_action" value="review">';
            echo '<button class="button" name="approve" value="1">' . federation_t('federation_ui_approve') . '</button> <button class="button" name="approve" value="0">' . federation_t('federation_ui_reject') . '</button></form></div>';
        }
    } elseif ($context['federation_tab']==='deliveries') {
        echo '<h4>' . federation_t('federation_ui_delivery_queue') . '</h4><form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
        echo '<button class="button" name="federation_action" value="run_queue">' . federation_t('federation_ui_process_up_to_3_due_deliveries') . '</button></form>';
        foreach ($context['federation_delivery'] as $row) {
            echo '<div class="federation-row">#',(int)$row['id_delivery'],' <strong>',federation_h($row['status']),'</strong> · ',federation_h($row['inbox']),
                federation_t('federation_ui_attempts'),(int)$row['attempts'],federation_t('federation_ui_next'),gmdate('Y-m-d H:i', (int)$row['next_try']),' UTC';
            if ($row['last_error']!=='') echo '<p>',federation_h($row['last_error']),'</p>';
            if ($row['status']==='failed') {echo '<form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
                echo '<input type="hidden" name="delivery_id" value="',(int)$row['id_delivery'],'"><button class="button" name="federation_action" value="retry_delivery">' . federation_t('federation_ui_retry') . '</button></form>';}
            echo '</div>';
        }
    } elseif ($context['federation_tab']==='blocks') {
        echo '<h4>' . federation_t('federation_ui_blocked_domains') . '</h4><form method="post" action="',$base,'">'; federation_post_fields('federation_admin');
        echo '<label for="federation-domain">' . federation_t('federation_ui_domain') . '</label> <input type="text" id="federation-domain" name="domain" maxlength="190" placeholder="example.social" required> ';
        echo '<button class="button" name="federation_action" value="block_domain">' . federation_t('federation_ui_block') . '</button></form>';
        foreach ($context['federation_blocks'] as $row) { echo '<div class="federation-row">',federation_h($row['domain']),'<form method="post" action="',$base,'">';federation_post_fields('federation_admin');
            echo '<input type="hidden" name="block_id" value="',(int)$row['id_block'],'"><button class="button" name="federation_action" value="unblock_domain">' . federation_t('federation_ui_unblock') . '</button></form></div>'; }
    } else {
        echo '<h4>',federation_h($txt['federation_diagnostics']),'</h4>';
        echo '<p>', $context['federation_https']?$txt['federation_ui_https_site_url']:$txt['federation_ui_https_site_url_required'], '</p>';
        echo '<p>',function_exists('openssl_sign')?$txt['federation_ui_openssl']:$txt['federation_ui_openssl_missing'],'</p>';
        echo '<p>',function_exists('curl_init')?$txt['federation_ui_curl']:$txt['federation_ui_curl_missing'],'</p>';
        echo '<p>' . federation_t('federation_ui_test_discovery_endpoints_externally_routing_instructions_in_docs_') . '</p>';
        foreach($context['federation_discovery'] as $name=>$url) echo '<div class="federation-row">',federation_h($name),': <code>',federation_h($url),'</code></div>';
        echo '<h4>' . federation_t('federation_ui_recent_events') . '</h4>';
        foreach($context['federation_log'] as $row) echo '<div class="federation-row">',gmdate('Y-m-d H:i',(int)$row['logged']),' ',federation_h($row['event_type']),' : ',federation_h($row['details']),'</div>';
    }
    echo '</div>';
}
function template_federation_profile()
{
    global $context, $txt;
    $actor = $context['federation_member_actor'];
    $url = federation_h($context['federation_profile_post']);
    echo '<div class="cat_bar"><h3 class="catbg">',federation_h($txt['federation_profile']),'</h3></div><div class="federation-panel federation-member"><p>',federation_h($txt['federation_member_intro']),'</p>';
    if ($context['federation_member_error']) echo '<div class="errorbox" role="alert">',federation_h($context['federation_member_error']),'</div>';
    if ($context['federation_member_message']) echo '<div class="infobox" role="status">',federation_h($context['federation_member_message']),'</div>';
    echo '<section class="federation-member-card"><div class="federation-member-heading"><h4>' . federation_t('federation_ui_my_federated_identity') . '</h4><strong class="federation-state ', $actor?'fed-enabled':'fed-disabled','">', $actor?$txt['federation_ui_enabled']:$txt['federation_ui_disabled'],'</strong></div><div class="federation-member-body">';
    if ($actor) echo '<label for="fed-address">' . federation_t('federation_ui_your_federated_address') . '</label><input id="fed-address" class="fed-address" type="text" readonly value="@member-',(int)$actor['id_local'],'@',federation_h(federation_origin()),'"><p class="fed-help">' . federation_t('federation_ui_select_and_copy_this_address_to_share_your_identity_with_others') . '</p>';
    else echo '<p>' . federation_t('federation_ui_enable_your_identity_to_follow_remote_accounts_and_receive_approv') . '</p>';
    echo '<div class="federation-member-actions">';
    if ($actor) echo '<a class="button" href="',federation_h(federation_url('feed')),'">' . federation_t('federation_ui_view_my_federated_feed') . '</a>';
    echo '<form method="post" action="',$url,'">'; federation_post_fields('federation_member');
    echo '<button type="submit" class="button" name="federation_action" value="',$actor?'disable':'enable','">',$actor?$txt['federation_ui_disable_my_federated_identity']:$txt['federation_ui_enable_my_federated_identity'],'</button></form></div></div></section>';
    if ($actor) {
        echo '<section class="federation-member-card"><div class="federation-member-heading"><h4>' . federation_t('federation_ui_follow_a_remote_account') . '</h4></div><div class="federation-member-body"><p id="fed-follow-help">' . federation_t('federation_ui_enter_an_user_host_handle_or_full_https_activitypub_actor_url_you') . '</p><form method="post" action="',$url,'">'; federation_post_fields('federation_member');
        echo '<label for="federation-remote">' . federation_t('federation_ui_remote_actor_handle_or_url') . '</label><div class="federation-member-actions fed-follow-form"><input id="federation-remote" type="text" name="remote_url" maxlength="2048" required aria-describedby="fed-follow-help" placeholder="@alex@example.social"><button type="submit" class="button" name="federation_action" value="follow">' . federation_t('federation_ui_follow') . '</button></div></form></div></section>';
        echo '<section class="federation-member-card"><div class="federation-member-heading"><h4>' . federation_t('federation_ui_following') . '</h4><span>',count($context['federation_member_follows']), federation_t('federation_ui_shown'),'</span></div><div class="federation-member-body">';
        if (!$context['federation_member_follows']) echo '<div class="fed-empty"><strong>' . federation_t('federation_ui_no_remote_accounts_yet') . '</strong><p>' . federation_t('federation_ui_use_the_form_above_to_send_your_first_follow_request') . '</p></div>';
        foreach ($context['federation_member_follows'] as $row) {
            echo '<div class="federation-row fed-account"><div><strong>',federation_h($row['remote_actor']),'</strong><p>',federation_t('federation_ui_status'),federation_h($row['status']),'</p></div><form method="post" action="',$url,'">'; federation_post_fields('federation_member');
            echo '<input type="hidden" name="remote_url" value="',federation_h($row['remote_actor']),'"><button type="submit" class="button" name="federation_action" value="unfollow">' . federation_t('federation_ui_unfollow') . '</button></form></div>';
        }
        echo '</div></section>';
        echo '<section class="federation-member-card"><div class="federation-member-heading"><h4>' . federation_t('federation_ui_my_followers') . '</h4></div><div class="federation-member-body">';
        if (!$context['federation_member_followers']) echo '<p>' . federation_t('federation_ui_no_followers_yet') . '</p>';
        foreach ($context['federation_member_followers'] as $row) {
            echo '<div class="federation-row fed-account"><strong>',federation_h($row['remote_actor']),'</strong><form method="post" action="',$url,'">'; federation_post_fields('federation_member');
            echo '<input type="hidden" name="remote_url" value="',federation_h($row['remote_actor']),'"><button type="submit" class="button" name="federation_action" value="remove_follower">' . federation_t('federation_ui_remove_follower') . '</button></form></div>';
        }
        echo '</div></section>';
    }
    echo '</div>';
}

function template_federation_feed()
{
    global $context, $scripturl, $user_info, $txt;
    echo '<div class="cat_bar"><h3 class="catbg">' . federation_t('federation_ui_federated_feed') . '</h3></div><div class="federation-panel federation-member"><div class="federation-member-actions"><a class="button" href="',federation_h($scripturl.'?action=profile;area=federation;u='.(int)$user_info['id']),'">' . federation_t('federation_ui_back_to_my_federated_identity') . '</a></div><p>' . federation_t('federation_ui_only_administrator_approved_remote_activities_delivered_to_your_f') . '</p><section class="federation-member-card"><div class="federation-member-heading"><h4>' . federation_t('federation_ui_recent_activity') . '</h4></div><div class="federation-member-body">';
    if (empty($context['federation_feed'])) echo '<div class="fed-empty"><strong>' . federation_t('federation_ui_no_approved_remote_activity_yet') . '</strong><p>' . federation_t('federation_ui_approved_activities_will_appear_here_when_received') . '</p></div>';
    foreach ($context['federation_feed'] as $item) {
        echo '<article class="federation-row fed-feed-item"><div class="federation-member-heading"><strong>', federation_h($item['type']), '</strong><small>', gmdate('Y-m-d H:i', $item['received']), ' UTC</small></div><p class="fed-help">',federation_t('federation_ui_from_76'),federation_h($item['actor']),'</p><p class="fed-content">', federation_h($item['content']), '</p></article>';
    }
    echo '</div></section></div>';
}
