<?php
/** Remove queued Mentions Expanded tasks on uninstall. */
if (!defined('SMF'))
	die('No direct access...');
global $smcFunc;
$smcFunc['db_query']('', '
	DELETE FROM {db_prefix}background_tasks
	WHERE task_class = {string:class}',
	array('class' => 'MentionsExpanded_Notify')
);
