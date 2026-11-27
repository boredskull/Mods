<?php

/**
 * Rich Link Previews: background task that fetches metadata for one message.
 *
 * @package RichLinkPreviews
 * @author BoredSkull https://boredskull.net
 * @copyright 2026 BoredSkull
 * @license ISC
 * @version 1.0.3
 */

if (!defined('SMF'))
	die('No direct access...');

class RichLinks_Fetch_Background extends SMF_BackgroundTask
{
	public function execute()
	{
		global $sourcedir, $modSettings;

		if (empty($modSettings['richlinks_enabled']))
			return true;

		if (!class_exists('RichLinks'))
			require_once($sourcedir . '/RichLinks.php');

		$id_msg = isset($this->_details['id_msg']) ? (int) $this->_details['id_msg'] : 0;
		if ($id_msg > 0)
			RichLinks::processMessage($id_msg, null, RichLinks::BACKGROUND_BUDGET);

		// Weekly clean-up of previews for deleted posts and unused cache rows.
		if (empty($modSettings['richlinks_last_prune']) || $modSettings['richlinks_last_prune'] < time() - 604800)
			RichLinks::prune();

		return true;
	}
}
