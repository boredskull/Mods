<?php
// Questions & Answers by BoredSkull - English

global $txt;

$txt['qa_title'] = 'Questions & Answers';
$txt['qa_title_questions'] = 'Questions';
$txt['qa_menu'] = 'Questions';
$txt['qa_credits'] = '<a href="https://boredskull.net" target="_blank" rel="noopener">Questions &amp; Answers %1$s</a> &copy; 2026 BoredSkull';

// Status
$txt['qa_question'] = 'Question';
$txt['qa_solved'] = 'Solved';
$txt['qa_unsolved'] = 'Unsolved';
$txt['qa_one_answer'] = '%1$d answer';
$txt['qa_n_answers'] = '%1$d answers';
$txt['qa_answer_word'] = 'answer';
$txt['qa_answers_word'] = 'answers';
$txt['qa_n_score'] = 'score %1$d';
$txt['qa_n_views'] = '%1$d views';

// Topic
$txt['qa_sort_by'] = 'Sort answers:';
$txt['qa_sort_votes'] = 'Votes';
$txt['qa_sort_date'] = 'Date';
$txt['qa_mark_solved'] = 'Mark solved';
$txt['qa_mark_solved_desc'] = 'Mark this question solved without choosing an answer.';
$txt['qa_mark_unsolved'] = 'Mark unsolved';
$txt['qa_mark_unsolved_confirm'] = 'Mark this question unsolved? Any accepted answer will be unaccepted.';
$txt['qa_accept_hint'] = 'Found the answer you needed? Click the tick next to it to accept it.';
$txt['qa_convert'] = 'Make this a question';
$txt['qa_convert_desc'] = 'Turn this topic into a question, so replies can be voted on and an answer accepted.';
$txt['qa_unconvert'] = 'Make normal topic';
$txt['qa_unconvert_confirm'] = 'Turn this question back into a normal topic? Votes are kept in case you change it back.';
$txt['qa_vote_up'] = 'This answer is useful';
$txt['qa_vote_down'] = 'This answer is not useful';
$txt['qa_score_title'] = '%1$d up, %2$d down';
$txt['qa_accept'] = 'Accept this answer';
$txt['qa_unaccept'] = 'Unaccept this answer';
$txt['qa_accepted_answer'] = 'Accepted answer';
$txt['qa_solution'] = 'Solution';
$txt['qa_solution_by'] = 'by %1$s';
$txt['qa_go_to_answer'] = 'Go to answer';
$txt['qa_reputation'] = 'Reputation';

// Posting
$txt['qa_post_label'] = 'Question';
$txt['qa_post_desc'] = 'Ask this as a question: replies can be voted on and you can accept the best answer.';
$txt['qa_post_question_only'] = 'This is a question board. Replies to your topic can be voted on, and you can accept the answer that solves it.';

// Questions page
$txt['qa_tab_unsolved'] = 'Unsolved';
$txt['qa_tab_unanswered'] = 'Unanswered';
$txt['qa_tab_solved'] = 'Solved';
$txt['qa_tab_all'] = 'All';
$txt['qa_tab_mine'] = 'My questions';
$txt['qa_none_unsolved'] = 'No unsolved questions. Nice work!';
$txt['qa_none_unanswered'] = 'Every question has at least one answer.';
$txt['qa_none_solved'] = 'No questions have been solved yet.';
$txt['qa_none_all'] = 'No questions have been asked yet.';
$txt['qa_none_mine'] = 'You haven\'t asked any questions.';
$txt['qa_filter_board'] = 'Board';
$txt['qa_all_boards'] = 'All question boards';
$txt['qa_list_sort_newest'] = 'Newest';
$txt['qa_list_sort_active'] = 'Recently active';
$txt['qa_list_sort_answers'] = 'Most answers';
$txt['qa_asked_by'] = 'Asked by %1$s, %2$s';
$txt['qa_last_answer'] = 'Last reply %1$s by %2$s';
$txt['qa_view_solution'] = 'View solution';
$txt['qa_leaders'] = 'Top answerers';
$txt['qa_no_leaders'] = 'Nobody has earned reputation yet.';
$txt['qa_no_boards'] = 'There are no question boards you can see.';

// Profile
$txt['qa_profile_area'] = 'Answers';
$txt['qa_stat_accepted'] = 'Accepted answers';
$txt['qa_stat_up'] = 'Up votes received';
$txt['qa_stat_down'] = 'Down votes received';
$txt['qa_stat_asked'] = 'Questions asked (%1$d solved)';
$txt['qa_points_explain'] = 'Reputation: +%1$d for each up vote, -%2$d for each down vote and +%3$d for each accepted answer (accepting your own answer doesn\'t count).';
$txt['qa_top_answers'] = 'Top answers';
$txt['qa_recent_accepted'] = 'Recently accepted';
$txt['qa_no_answers_yet'] = 'No voted answers yet.';
$txt['qa_no_accepted_yet'] = 'No accepted answers yet.';

// Errors
$txt['qa_error_not_found'] = 'That post doesn\'t exist, or you can\'t see it.';
$txt['qa_error_not_allowed'] = 'Only the person who asked and moderators can do that.';
$txt['qa_error_not_question'] = 'This topic isn\'t a question.';
$txt['qa_error_not_answer'] = 'Only replies to the question can be accepted.';
$txt['qa_error_not_accepted'] = 'That answer is no longer marked as accepted. Please refresh the topic.';
$txt['qa_error_unaccept_failed'] = 'The answer could not be unaccepted. No reputation changes were saved. Please try again or contact an administrator.';
$txt['qa_error_reputation_sync'] = 'The reputation update could not be verified, so the action was cancelled. Please contact an administrator.';
$txt['qa_error_unapproved'] = 'That reply is waiting for approval.';
$txt['qa_error_guest'] = 'Please log in to vote.';
$txt['qa_error_vote_question'] = 'Only answers can be voted on.';
$txt['qa_error_vote_own'] = 'You can\'t vote on your own answer.';
$txt['qa_error_no_vote_permission'] = 'You aren\'t allowed to vote here.';
$txt['qa_error_no_downvotes'] = 'Down votes are turned off.';
$txt['qa_error_downvote_rep'] = 'You need more reputation to vote answers down.';
$txt['qa_error_board_off'] = 'Questions aren\'t used in this board.';
$txt['qa_error_question_only'] = 'Every topic in this board is a question.';

// Alerts
$txt['alert_member_qa_accepted'] = '{member_link} accepted your answer in <a href="{topic_link}">{topic_subject}</a>.';
$txt['alert_member_qa_solved'] = '{member_link} marked your question <a href="{topic_link}">{topic_subject}</a> as solved.';

// Permissions
$txt['permissionname_qa_vote'] = 'Vote on answers';
$txt['permissionhelp_qa_vote'] = 'Vote replies to questions up (and down, if down votes are on). Members can never vote on their own answers.';
$txt['permissionname_qa_accept'] = 'Accept answers and mark questions solved';
$txt['permissionhelp_qa_accept'] = 'Own: in their own questions. Any: in anyone\'s questions (moderators can always do this). Also allows turning topics into questions in boards where questions are optional.';
$txt['permissionname_qa_accept_own'] = 'Own questions';
$txt['permissionname_qa_accept_any'] = 'Any question';

// Admin
$txt['qa_admin_desc'] = 'Question topics with voted answers, accepted solutions and reputation.';
$txt['qa_admin_settings'] = 'Settings';
$txt['qa_admin_boards'] = 'Question boards';
$txt['qa_admin_maintain'] = 'Maintenance';
$txt['qa_enabled'] = 'Turn on Questions & Answers';
$txt['qa_show_menu'] = 'Show "Questions" in the main menu';
$txt['qa_index_badges'] = 'Show Question and Solved badges in topic lists';
$txt['qa_answers_title'] = 'Answers';
$txt['qa_default_sort'] = 'Default answer order';
$txt['qa_pin_accepted'] = 'Show the accepted answer first';
$txt['qa_pin_accepted_desc'] = 'When answers are sorted by votes, put the accepted answer straight after the question.';
$txt['qa_downvotes'] = 'Allow down votes';
$txt['qa_downvote_min_rep'] = 'Reputation needed to vote down';
$txt['qa_downvote_min_rep_desc'] = '0 = anyone who can vote.';
$txt['qa_lock_solved'] = 'Lock questions when they are solved';
$txt['qa_lock_solved_desc'] = 'They are unlocked again if marked unsolved.';
$txt['qa_alerts'] = 'Alert members when their answer is accepted';
$txt['qa_rep_title'] = 'Reputation';
$txt['qa_points_up'] = 'Points per up vote';
$txt['qa_points_down'] = 'Points lost per down vote';
$txt['qa_points_accepted'] = 'Points per accepted answer';
$txt['qa_rep_everywhere'] = 'Show reputation on posts in every board';
$txt['qa_rep_everywhere_desc'] = 'Otherwise it is only shown in question boards.';
$txt['qa_points_note'] = 'Earned reputation comes from votes and accepted answers. Manual admin adjustments are stored separately and are preserved during recounts.';
$txt['qa_recount_needed'] = 'The points have changed. <a href="%1$s">Recount reputation</a> to update everyone.';
$txt['qa_boards_desc'] = '<strong>Optional</strong>: members can tick "Question" when starting a topic, and the asker or a moderator can turn a topic into a question later. <strong>Question-only</strong>: every topic is a question, including existing ones.';
$txt['qa_board'] = 'Board';
$txt['qa_mode_0'] = 'Off';
$txt['qa_mode_1'] = 'Optional';
$txt['qa_mode_2'] = 'Question-only';
$txt['qa_boards_saved'] = 'Saved. %1$d existing topics became questions.';
$txt['qa_recount_desc'] = 'Recount answer scores and earned reputation and clear orphan data. Admin adjustments are retained and included in totals.';
$txt['qa_recount'] = 'Recount now';
$txt['qa_recount_done'] = 'Scores and reputation have been recounted.';


// Reputation management (v1.2.0)
$txt['qa_admin_reputation'] = 'Reputation Management';
$txt['qa_adjust_intro'] = 'Award or deduct points without altering votes or accepted answers. Adjustments are logged and survive a reputation recount. Administrators only.';
$txt['qa_adjust_find'] = 'Member ID or exact username/display name';
$txt['qa_adjust_lookup'] = 'Find member';
$txt['qa_adjust_earned'] = 'Earned from votes and accepted answers';
$txt['qa_adjust_net'] = 'Net manual adjustments';
$txt['qa_adjust_total'] = 'Stored total reputation';
$txt['qa_adjust_add'] = 'New reputation adjustment';
$txt['qa_adjust_type'] = 'Action';
$txt['qa_adjust_award'] = 'Award';
$txt['qa_adjust_deduct'] = 'Deduct';
$txt['qa_adjust_points'] = 'Points';
$txt['qa_adjust_reason'] = 'Reason (required; recorded in history)';
$txt['qa_adjust_apply'] = 'Record adjustment';
$txt['qa_adjust_history'] = 'Adjustment history';
$txt['qa_adjust_no_history'] = 'No manual adjustments for this member.';
$txt['qa_adjust_recent'] = 'Showing the 100 most recent records. Reversed entries remain visible.';
$txt['qa_adjust_time'] = 'Date';
$txt['qa_adjust_admin'] = 'Administrator';
$txt['qa_adjust_status'] = 'Status / reversal';
$txt['qa_adjust_reversal_of'] = 'Reverses #%1$d';
$txt['qa_adjust_reversed_by'] = 'Reversed by #%1$d';
$txt['qa_adjust_reversal_reason'] = 'Reversal reason';
$txt['qa_adjust_reverse'] = 'Reverse';
$txt['qa_adjust_saved'] = 'The reputation adjustment was recorded successfully.';
$txt['qa_adjust_member_not_found'] = 'Member not found, or the name matches more than one member. Use the numeric member ID or exact login name.';
$txt['qa_adjust_invalid_points'] = 'Enter a whole number of points from 1 to 100,000.';
$txt['qa_adjust_invalid_reason'] = 'A reason is required (maximum 1,000 bytes).';
$txt['qa_adjust_invalid_reversal'] = 'The adjustment is unavailable, already reversed, or belongs to another member.';
$txt['qa_adjust_invalid_action'] = 'Unknown reputation action.';
$txt['qa_adjust_recount_warning'] = 'The stored reputation does not match the current formula. Run the maintenance recount to repair earlier differences.';
$txt['qa_adjust_recalculate'] = 'Recalculate this member';
