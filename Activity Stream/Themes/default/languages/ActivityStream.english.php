<?php

/**
 * Activity Stream: English strings.
 *
 * @package ActivityStream
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

$txt['as_title'] = 'Activity Stream';
$txt['as_menu'] = 'Activity';
$txt['as_credits'] = 'Activity Stream %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';

// Tabs
$txt['as_streams_nav'] = 'Activity streams';
$txt['as_tab_all'] = 'All activity';
$txt['as_tab_following'] = 'Following';
$txt['as_tab_popular'] = 'Popular';
$txt['as_tab_mine'] = 'My activity';
$txt['as_manage_streams'] = 'My streams';
$txt['as_manage_following'] = 'People I follow';
$txt['as_make_default'] = 'Open this stream by default';
$txt['as_is_default'] = 'Default';
$txt['as_default_set'] = '"%1$s" now opens by default.';
$txt['as_edit'] = 'Edit';
$txt['as_delete'] = 'Delete';
$txt['as_cancel'] = 'Cancel';

// Item types
$txt['as_type_topics'] = 'New topics';
$txt['as_type_replies'] = 'Replies';
$txt['as_type_likes'] = 'Likes';
$txt['as_type_members'] = 'New members';

// Lines
$txt['as_line_topic'] = '%1$s started %2$s in %3$s';
$txt['as_line_reply'] = '%1$s replied to %2$s in %3$s';
$txt['as_line_like'] = '%1$s liked %2$s\'s post in %3$s (%4$s)';
$txt['as_line_member'] = '%1$s joined the community. Say hello!';
$txt['as_likers_two'] = '%1$s and %2$s';
$txt['as_likers_other_one'] = '%1$s and 1 other';
$txt['as_likers_others'] = '%1$s and %2$s others';
$txt['as_n_replies'] = '%1$s replies';
$txt['as_n_likes'] = '%1$s likes';

// Time
$txt['as_just_now'] = 'Just now';
$txt['as_minute_ago'] = '1 minute ago';
$txt['as_minutes_ago'] = '%1$d minutes ago';
$txt['as_hour_ago'] = '1 hour ago';
$txt['as_hours_ago'] = '%1$d hours ago';
$txt['as_today'] = 'Today';
$txt['as_yesterday'] = 'Yesterday';
$txt['as_days'] = 'days';

// Stream page
$txt['as_nothing'] = 'Nothing to show yet.';
$txt['as_load_more'] = 'Load more';
$txt['as_loading'] = 'Loading…';
$txt['as_follow_nobody'] = 'You aren\'t following anyone yet.';
$txt['as_follow_nobody_desc'] = 'Follow members to see their new topics, replies and likes here. Use the Follow button on a member\'s Activity profile page, or add people by name.';
$txt['as_find_people'] = 'Find people to follow';

// Popular
$txt['as_popular_intro'] = 'Topics with the most replies and likes in the last %1$d days.';
$txt['as_nothing_popular'] = 'Nothing has been busy recently.';
$txt['as_popular_meta'] = 'in %1$s · started by %2$s';
$txt['as_popular_counts'] = '%1$s new posts · %2$s likes';
$txt['as_last_post_by'] = 'last post by %1$s';

// Following
$txt['as_following_title'] = 'People I follow';
$txt['as_follow'] = 'Follow';
$txt['as_unfollow'] = 'Unfollow';
$txt['as_follow_back'] = 'Follow back';
$txt['as_follow_by_name'] = 'Follow a member by name:';
$txt['as_followed'] = 'You are now following them.';
$txt['as_list_following'] = 'Following';
$txt['as_list_followers'] = 'Followers';
$txt['as_list_following_empty'] = 'You aren\'t following anyone yet.';
$txt['as_list_followers_empty'] = 'Nobody is following you yet.';
$txt['as_followers'] = 'followers';
$txt['as_following'] = 'following';

// Custom streams
$txt['as_streams_title'] = 'My streams';
$txt['as_streams_intro'] = 'Build your own streams: pick the kinds of activity, whose activity, and which boards. Each stream gets its own tab.';
$txt['as_new_stream'] = 'Create a stream';
$txt['as_edit_stream'] = 'Edit stream';
$txt['as_no_streams'] = 'You haven\'t made any streams yet.';
$txt['as_stream_limit_reached'] = 'You can have up to %1$d streams.';
$txt['as_stream_saved'] = 'Stream saved.';
$txt['as_stream_deleted'] = 'Stream deleted.';
$txt['as_confirm_delete'] = 'Delete this stream?';
$txt['as_stream_name'] = 'Name';
$txt['as_stream_show'] = 'Show';
$txt['as_stream_from'] = 'From';
$txt['as_members_all'] = 'Everyone';
$txt['as_members_followed'] = 'People I follow';
$txt['as_members_me'] = 'Only me';
$txt['as_members_user'] = 'One member';
$txt['as_include_own'] = 'Include my own activity';
$txt['as_stream_period'] = 'Time period';
$txt['as_any_time'] = 'Any time';
$txt['as_last_n_days'] = 'Last %1$d days';
$txt['as_stream_boards'] = 'Boards';
$txt['as_stream_boards_desc'] = 'Choosing boards also shows boards you have set to ignore.';
$txt['as_all_boards'] = 'All boards';
$txt['as_save_stream'] = 'Save stream';
$txt['as_all_types'] = 'All activity';
$txt['as_n_boards'] = '%1$d boards';

// Profile
$txt['as_profile_label'] = 'Activity';

// Errors
$txt['as_error_no_permission'] = 'You are not allowed to do that.';
$txt['as_error_follow_self'] = 'You can\'t follow yourself.';
$txt['as_error_no_member'] = 'No member with that name was found.';
$txt['as_error_follow_limit'] = 'You can follow up to %1$d members.';
$txt['as_error_no_stream'] = 'That stream could not be found.';
$txt['as_error_stream_limit'] = 'You can have up to %1$d streams.';
$txt['as_error_stream_name'] = 'Please give the stream a name.';
$txt['as_error_stream_types'] = 'Choose at least one kind of activity.';
$txt['as_error_stream_boards'] = 'Choose at least one board, or tick "All boards".';

// Permissions
$txt['permissionname_as_view'] = 'View the activity stream';
$txt['cannot_as_view'] = 'You are not allowed to view the activity stream.';
$txt['permissionhelp_as_view'] = 'Shows the Activity page and members\' Activity profile pages. Members only see activity from boards they can access.';
$txt['permissionname_as_follow'] = 'Follow members and build activity streams';
$txt['permissionhelp_as_follow'] = 'Allows following other members and creating custom activity streams.';

// Admin
$txt['as_admin_desc'] = 'A forum-wide timeline of new topics, replies, likes and new members. Members can follow each other and build their own streams. Activity only appears to members who can see the board it happened in.';
$txt['as_enabled'] = 'Enable the activity stream';
$txt['as_show_menu'] = 'Show an Activity button in the main menu';
$txt['as_menu_label'] = 'Menu button label';
$txt['as_menu_label_desc'] = 'Leave empty to use "Activity".';
$txt['as_type_likes_desc'] = 'Needs likes to be turned on in SMF.';
$txt['as_type_members_desc'] = 'Only shown to members who can view the member list.';
$txt['as_per_page'] = 'Items per page';
$txt['as_popular_days'] = 'Popular topics period';
$txt['as_max_streams'] = 'Custom streams per member';
$txt['as_max_streams_desc'] = '0 turns off custom streams.';
$txt['as_exclude_boards'] = 'Never show activity from';
$txt['as_exclude_boards_desc'] = 'For example staff boards or the recycle bin (the recycle bin is always excluded).';

// 1.1.0
$txt['as_streams_off'] = 'Custom streams are turned off at the moment. Your saved streams are kept and will come back if they are turned on again. You can still delete them.';
$txt['as_stream_hidden'] = 'Hidden';
$txt['as_error_streams_off'] = 'Custom streams are turned off at the moment.';
$txt['as_error_stream_hidden'] = 'This stream is hidden because members can only have %1$d custom streams at the moment. Delete another stream to bring it back.';

// 1.1.2
$txt['as_whos_online'] = 'Viewing the <a href="%1$s">Activity Stream</a>.';
