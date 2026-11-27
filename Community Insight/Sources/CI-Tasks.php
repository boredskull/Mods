<?php
/** SMF scheduled task entrypoint for Community Insights 1.1.1. */
if (!defined('SMF'))
    die('No direct access...');
require_once(dirname(__FILE__) . '/CommunityInsights.php');
require_once(dirname(__FILE__) . '/CommunityInsights-Advanced.php');
function ci_run()
{
    return CommunityInsightsAdvanced::scheduled();
}
