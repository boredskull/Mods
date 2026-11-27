<?php

/**
 * Ranked Polls: English strings.
 *
 * @package RankedPolls
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 */

$txt['rp_title'] = 'Ranked Polls';
$txt['rp_desc'] = 'Let members rank poll options in order of preference. Ranked polls are counted by instant-runoff, and the results are shown round by round. Normal SMF polls are not affected.';
$txt['rp_credits'] = 'Ranked Polls %s <a href="https://boredskull.net">BoredSkull</a> &copy; 2026';
$txt['rp_tab_settings'] = 'Settings';
$txt['rp_tab_polls'] = 'Ranked polls';

// Poll form
$txt['rp_form_ranked'] = 'Ranked-choice poll';
$txt['rp_form_ranked_desc'] = 'Members put the options in order of preference instead of picking one. The winner is decided by instant-runoff counting. Guests can\'t vote, and "votes per member" doesn\'t apply.';
$txt['rp_form_anonymous'] = 'Anonymous ballots';
$txt['rp_form_anonymous_desc'] = 'Nobody, including administrators, can see how each member ranked the options. Members can\'t change an anonymous vote.';
$txt['rp_form_always_anonymous'] = 'Ballots in ranked polls are always anonymous on this forum.';
$txt['rp_form_note_has_ballots'] = 'This poll already has ranked ballots, so it stays ranked. Reset the votes to change this.';
$txt['rp_form_note_has_votes'] = 'This poll already has votes, so it can\'t become ranked. Reset the votes first.';

// Topic display
$txt['rp_ranked_poll'] = 'Ranked poll';
$txt['rp_tag_ranked'] = 'Ranked choice';
$txt['rp_tag_anonymous'] = 'Anonymous';
$txt['rp_n_ballots'] = '%1$s ballots';
$txt['rp_locked'] = 'Voting is locked';
$txt['rp_ended'] = 'Voting ended %1$s';
$txt['rp_ends'] = 'Voting ends %1$s';
$txt['rp_you_voted'] = 'You have voted.';
$txt['rp_anonymous_recorded'] = 'Your ballot was recorded anonymously.';
$txt['rp_change_vote'] = 'Change your ranking';
$txt['rp_login_to_vote'] = 'Log in to vote in this poll.';
$txt['rp_results_after_vote'] = 'The results will be shown after you vote.';
$txt['rp_results_after_end'] = 'The results will be shown when voting ends.';
$txt['rp_confirm_remove'] = 'Remove this poll and all of its ballots?';

$txt['rp_button_edit'] = 'Edit poll';
$txt['rp_button_lock'] = 'Lock voting';
$txt['rp_button_unlock'] = 'Unlock voting';
$txt['rp_button_remove'] = 'Remove poll';
$txt['rp_button_export_rounds'] = 'Export results (CSV)';
$txt['rp_button_export_ballots'] = 'Export ballots (CSV)';

// Ballot
$txt['rp_your_ranking'] = 'Your ranking';
$txt['rp_options'] = 'Options';
$txt['rp_ranking_empty'] = 'Choose options from the list to rank them. Your first choice goes at the top.';
$txt['rp_all_ranked'] = 'All options are ranked.';
$txt['rp_move_up'] = 'Move up';
$txt['rp_move_down'] = 'Move down';
$txt['rp_remove'] = 'Remove';
$txt['rp_add'] = 'Add to ranking';
$txt['rp_clear'] = 'Clear ranking';
$txt['rp_announce_moved'] = '%1$s moved to position %2$s.';
$txt['rp_announce_added'] = '%1$s added at position %2$s.';
$txt['rp_announce_removed'] = '%1$s removed from your ranking.';
$txt['rp_ballot_help'] = 'Rank as many options as you like. Click to add, then drag or use the arrows to reorder. On the keyboard, Alt+Up/Down moves the selected option. If your first choice is eliminated, your vote moves to your next choice.';
$txt['rp_fallback_help'] = 'Give each option you want to rank a number, with 1 for your first choice. Rank at least %1$d.';
$txt['rp_not_ranked'] = '—';
$txt['rp_submit'] = 'Submit ranking';
$txt['rp_submit_change'] = 'Update ranking';

// Results
$txt['rp_results'] = 'Results';
$txt['rp_no_ballots'] = 'No ballots yet.';
$txt['rp_winner'] = 'Winner';
$txt['rp_leading'] = 'Currently winning';
$txt['rp_after_one_round'] = 'Decided in the first round';
$txt['rp_after_rounds'] = 'Decided after %1$d rounds';
$txt['rp_round_n'] = 'Round %1$d';
$txt['rp_prev_round'] = 'Previous round';
$txt['rp_next_round'] = 'Next round';
$txt['rp_out_in_round'] = 'Eliminated in round %1$d';
$txt['rp_round_stats'] = '%1$s continuing ballots · %2$s needed to win · %3$s exhausted';
$txt['rp_eliminated'] = '%1$s is eliminated.';
$txt['rp_transfers'] = 'Its ballots move to:';
$txt['rp_exhausted'] = 'Exhausted (no further choices)';
$txt['rp_wins_majority'] = '%1$s has a majority and wins.';
$txt['rp_wins_tie'] = '%1$s wins a tie between the last two options.';
$txt['rp_tie_previous_rounds'] = 'Tie broken by an earlier round\'s votes';
$txt['rp_tie_borda'] = 'Tie broken by overall ranking score';
$txt['rp_tie_mentions'] = 'Tie broken by how many ballots ranked each option';
$txt['rp_tie_order'] = 'Tie broken by poll order (the option listed later loses)';
$txt['rp_ballot_summary'] = 'Ballot summary';
$txt['rp_first_preferences'] = 'First choices';
$txt['rp_times_ranked'] = 'Times ranked';
$txt['rp_borda'] = 'Ranking score';
$txt['rp_how_counted'] = 'How it\'s counted: each round, every ballot counts for its highest-ranked option still in the race. An option with more than half of those ballots wins. Otherwise the option with the fewest votes is eliminated, and its ballots move to their next choice. Ties are broken by votes in earlier rounds, then by ranking score (more points the higher an option is ranked), then by how many ballots ranked each option, then by poll order.';

// Errors
$txt['rp_use_ranked_form'] = 'This is a ranked-choice poll. Please vote using the ranking ballot in the topic.';
$txt['rp_error_no_poll'] = 'That ranked poll could not be found.';
$txt['rp_error_no_permission'] = 'You are not allowed to do that.';
$txt['rp_error_locked'] = 'Voting in this poll is locked.';
$txt['rp_error_expired'] = 'Voting in this poll has ended.';
$txt['rp_error_already_voted'] = 'You have already voted in this poll.';
$txt['rp_error_duplicate_rank'] = 'Each position can only be used once.';
$txt['rp_error_bad_choice'] = 'Your ballot contained an option that isn\'t in this poll.';
$txt['rp_error_too_few'] = 'Please rank at least %1$d options.';

// CSV
$txt['rp_csv_ballot'] = 'Ballot';
$txt['rp_csv_member'] = 'Member';
$txt['rp_csv_time'] = 'Voted';
$txt['rp_csv_rank'] = 'Rank %1$d';
$txt['rp_csv_round'] = 'Round';
$txt['rp_csv_option'] = 'Option';
$txt['rp_csv_votes'] = 'Votes';
$txt['rp_csv_percent'] = 'Percent of continuing ballots';
$txt['rp_csv_status'] = 'Status';
$txt['rp_csv_tie_break'] = 'Tie break';
$txt['rp_status_winner'] = 'Winner';
$txt['rp_status_eliminated'] = 'Eliminated';

// Alerts
$txt['alert_topic_ranked_results'] = 'The results of the ranked poll in <a href="{content_link}">{content_subject}</a> are now final.';

// Permissions
$txt['permissionname_rankedpoll_create'] = 'Create ranked-choice polls';
$txt['permissionhelp_rankedpoll_create'] = 'Allows making a poll ranked-choice, in boards where ranked polls are enabled. Members also need permission to post polls.';

// Admin
$txt['rp_enabled'] = 'Enable ranked polls';
$txt['rp_anonymous'] = 'Anonymous ballots';
$txt['rp_anonymous_desc'] = 'Anonymous ballots are stored without the member, time or any ID that links them to the voter. A separate list of who has voted stops anyone voting twice.';
$txt['rp_anonymous_never'] = 'Never: ballots show who voted';
$txt['rp_anonymous_optional'] = 'The poll creator chooses';
$txt['rp_anonymous_always'] = 'Always anonymous';
$txt['rp_min_ranks'] = 'Minimum options to rank';
$txt['rp_min_ranks_desc'] = 'Capped at the number of options in the poll.';
$txt['rp_alerts'] = 'Alert voters when a poll ends';
$txt['rp_alerts_desc'] = 'When a ranked poll with an end date closes, everyone who voted gets an alert linking to the final results.';
$txt['rp_owner_export'] = 'Poll creators can export';
$txt['rp_owner_export_desc'] = 'Lets the member who created a poll download its results and ballots. Administrators can always export.';
$txt['rp_boards'] = 'Boards with ranked polls';
$txt['rp_boards_desc'] = 'Members can make a poll ranked-choice in these boards. Existing ranked polls keep working if you untick a board.';
$txt['rp_permission_note'] = 'Who can create ranked polls is set by the "Create ranked-choice polls" board permission. Voting and viewing use SMF\'s normal poll permissions.';
$txt['rp_save'] = 'Save';
$txt['rp_settings_saved'] = 'Settings saved.';
$txt['rp_polls_disabled'] = 'Polls are turned off in Admin &gt; Posts and Topics &gt; Polls, so ranked polls won\'t be shown.';
$txt['rp_no_polls'] = 'No ranked polls yet.';
$txt['rp_col_topic'] = 'Topic';
$txt['rp_col_board'] = 'Board';
$txt['rp_col_ballots'] = 'Ballots';
$txt['rp_col_status'] = 'Status';
$txt['rp_col_created'] = 'Created';
$txt['rp_col_export'] = 'Export';
$txt['rp_status_open'] = 'Open';
$txt['rp_status_locked'] = 'Locked';
$txt['rp_status_ended'] = 'Ended';
$txt['rp_prune'] = 'Clean up';
$txt['rp_prune_desc'] = 'Removes ranked-poll data left behind by deleted polls. This also runs automatically every hour.';
$txt['rp_pruned'] = 'Removed data for %1$d deleted polls.';

// Scheduled task
$txt['scheduled_task_rp_tasks'] = 'Ranked poll alerts';
$txt['scheduled_task_desc_rp_tasks'] = 'Alerts voters when a ranked poll closes, and removes data for deleted polls.';

$txt['rp_error_choices_locked'] = 'Poll options cannot be added or removed after ranked ballots have been cast. Correct option labels only, or explicitly reset the votes first.';
$txt['rp_error_conversion_has_votes'] = 'This poll already has votes. Reset the votes before converting it to a ranked poll.';

$txt['rp_error_results_hidden'] = 'These poll results are not available yet. Exports follow the poll\'s result visibility settings.';
