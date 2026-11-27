<?php
/**
 * Board Rules - database installer
 *
 * @package BoardRules
 * @author BoredSkull <https://boredskull.net>
 * @copyright 2026 BoredSkull
 * @license ISC (see license.txt)
 */

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

global $smcFunc, $modSettings, $sourcedir;

db_extend('packages');

$tables = array(
	array(
		'name' => 'boardrules_categories',
		'columns' => array(
			array('name' => 'id_cat', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
			array('name' => 'title', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'description', 'type' => 'text'),
			array('name' => 'cat_order', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'lang', 'type' => 'varchar', 'size' => 80, 'default' => ''),
			array('name' => 'enabled', 'type' => 'tinyint', 'size' => 3, 'default' => 1),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_cat')),
			array('type' => 'index', 'name' => 'idx_order', 'columns' => array('cat_order')),
		),
	),
	array(
		'name' => 'boardrules_rules',
		'columns' => array(
			array('name' => 'id_rule', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
			array('name' => 'id_cat', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'title', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'body', 'type' => 'text'),
			array('name' => 'penalty', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'boards', 'type' => 'text'),
			array('name' => 'lang', 'type' => 'varchar', 'size' => 80, 'default' => ''),
			array('name' => 'rule_order', 'type' => 'int', 'size' => 10, 'default' => 0),
			array('name' => 'enabled', 'type' => 'tinyint', 'size' => 3, 'default' => 1),
			array('name' => 'updated', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_rule')),
			array('type' => 'index', 'name' => 'idx_cat_order', 'columns' => array('id_cat', 'rule_order')),
		),
	),
	array(
		'name' => 'boardrules_log',
		'columns' => array(
			array('name' => 'id_log', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'auto' => true),
			array('name' => 'log_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'id_member', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'member_name', 'type' => 'varchar', 'size' => 255, 'default' => ''),
			array('name' => 'version', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'summary', 'type' => 'varchar', 'size' => 255, 'default' => ''),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_log')),
			array('type' => 'index', 'name' => 'idx_time', 'columns' => array('log_time')),
		),
	),
	array(
		'name' => 'boardrules_accept',
		'columns' => array(
			array('name' => 'id_member', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'version', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
			array('name' => 'accept_time', 'type' => 'int', 'size' => 10, 'unsigned' => true, 'default' => 0),
		),
		'indexes' => array(
			array('type' => 'primary', 'columns' => array('id_member')),
			array('type' => 'index', 'name' => 'idx_version', 'columns' => array('version')),
		),
	),
);

foreach ($tables as $table)
	$smcFunc['db_create_table']('{db_prefix}' . $table['name'], $table['columns'], $table['indexes'], array(), 'ignore');

// Default settings - only add the ones that don't exist yet so upgrades keep their config.
$defaults = array(
	'boardrules_enabled' => 1,
	'boardrules_menu_button' => 1,
	'boardrules_guests_hidden' => 0,
	'boardrules_intro' => 'Welcome! These rules keep our community friendly, useful and safe. By using the forum you agree to follow them. If anything is unclear, please contact a member of staff.',
	'boardrules_numbering' => 1,
	'boardrules_show_changelog' => 1,
	'boardrules_reg_accept' => 1,
	'boardrules_banner' => 1,
	'boardrules_force_accept' => 0,
	'boardrules_alerts' => 1,
	'boardrules_show_messageindex' => 1,
	'boardrules_show_display' => 0,
	'boardrules_show_post' => 1,
	'boardrules_board_style' => 'open',
	'boardrules_version' => 0,
	'boardrules_last_update' => 0,
	'boardrules_last_summary' => '',
	'boardrules_rev' => 1,
);

$new_settings = array();
foreach ($defaults as $key => $value)
	if (!isset($modSettings[$key]))
		$new_settings[$key] = $value;

if (!empty($new_settings))
	updateSettings($new_settings);

// Members can turn the "rules updated" alert off in their profile; default is on.
$smcFunc['db_insert']('ignore',
	'{db_prefix}user_alerts_prefs',
	array('id_member' => 'int', 'alert_pref' => 'string', 'alert_value' => 'int'),
	array(0, 'boardrules_updated', 1),
	array('id_member', 'alert_pref')
);

// Seed the default rule set on a fresh install only.
$request = $smcFunc['db_query']('', '
	SELECT COUNT(*)
	FROM {db_prefix}boardrules_categories',
	array()
);
list ($existing) = $smcFunc['db_fetch_row']($request);
$smcFunc['db_free_result']($request);

if (empty($existing))
{
	require_once($sourcedir . '/Subs-Post.php');

	$default_rules = array(
		array(
			'title' => 'General Conduct',
			'description' => 'How we treat each other, everywhere on the forum - including private messages.',
			'rules' => array(
				array('Be respectful', 'Treat every member with courtesy. Debate ideas, not people. Personal attacks, name-calling, mocking and belittling remarks are not allowed.', 'Warning'),
				array('No harassment or hate speech', 'Harassment, threats, bullying, or content that attacks people based on race, ethnicity, nationality, religion, gender, sexual orientation, disability or age will not be tolerated - in public posts [b]or[/b] private messages.', 'Ban'),
				array('No trolling or flame-baiting', 'Do not post deliberately inflammatory or off-topic messages to provoke a reaction or derail a discussion.', 'Warning'),
				array('Keep it legal', 'Do not post, link to, request or encourage anything illegal, including piracy, cracked software, stolen accounts or illegal goods.', 'Ban'),
				array('Follow staff instructions', 'If a moderator asks you to stop or change something, please comply. If you disagree with a decision, raise it privately by personal message - not in the topic.', 'Warning'),
			),
		),
		array(
			'title' => 'Posting Guidelines',
			'description' => 'Help keep the forum tidy and easy to search.',
			'rules' => array(
				array('Post in the right board', 'Read the board description before starting a topic. Misplaced topics may be moved without notice.', 'Topic moved'),
				array('Search before posting', 'Your question may already have been answered. Duplicate topics may be merged or locked.', ''),
				array('Use descriptive titles', 'Titles like [i]Help![/i] or [i]Question[/i] make it hard for others to help. Summarise your topic in the title.', 'Title edited'),
				array('Stay on topic', 'Keep replies relevant to the original subject. If you want to discuss something new, start a new topic.', ''),
				array('No spam or excessive self-promotion', 'Advertising, referral links and repeated promotional posts are not allowed. Sharing your own work is fine where it is relevant and in the appropriate board.', 'Ban'),
				array('No bumping or post padding', 'Do not bump your own topic more than once every 24 hours, and avoid one-word or "me too" replies made only to raise your post count.', 'Warning'),
				array('Write clearly', 'Avoid writing in ALL CAPS, excessive colours, oversized fonts or text-speak. Clear posts get better answers.', ''),
			),
		),
		array(
			'title' => 'Content & Copyright',
			'description' => 'What you may and may not share.',
			'rules' => array(
				array('Respect copyright', 'Only post material you have the right to share. When quoting articles or other sources, quote a short extract and link to the original.', 'Post removed'),
				array('No adult or graphic content', 'Pornographic, sexually explicit or gratuitously violent material is not allowed anywhere - including avatars, signatures, images and links.', 'Ban'),
				array('Safe links and downloads only', 'Do not post links to malware, phishing sites or suspicious downloads, and do not use link shorteners to hide where a link goes.', 'Ban'),
				array('Signatures and avatars', 'Signatures and avatars must follow the same rules as posts. Keep signatures short and avoid large or animated images that distract from the discussion.', 'Signature removed'),
			),
		),
		array(
			'title' => 'Accounts & Privacy',
			'description' => 'Your account, your safety and the privacy of others.',
			'rules' => array(
				array('One account per person', 'Each member may hold one account. Duplicate accounts, or accounts created to get around a ban, will be removed.', 'Ban'),
				array('Respect privacy', 'Never post anyone\'s personal information - addresses, phone numbers, real names, photos or private messages - without their clear consent.', 'Ban'),
				array('Keep your account secure', 'Do not share your password with anyone. Staff will [b]never[/b] ask for your password.', ''),
				array('Appropriate usernames', 'Usernames must not be offensive, impersonate other members or staff, or advertise a product or website.', 'Name changed'),
			),
		),
		array(
			'title' => 'Moderation & Enforcement',
			'description' => 'How the rules are applied.',
			'rules' => array(
				array('How moderation works', 'Breaking the rules may result in a post being edited or removed, a warning, a temporary suspension or a permanent ban, depending on how serious it is and your history on the forum.', ''),
				array('Report, don\'t retaliate', 'If you see a post that breaks the rules, use the [b]Report to moderator[/b] link instead of replying to it or arguing in the topic.', ''),
				array('Appeals', 'If you believe a moderation decision was wrong, contact an administrator by personal message. Public discussion of moderation actions will be removed.', ''),
				array('Rules may change', 'These rules may be updated at any time. You will be notified of significant changes, and continuing to use the forum means you accept the current rules.', ''),
			),
		),
	);

	$now = time();
	$cat_order = 0;
	foreach ($default_rules as $category)
	{
		$description = $smcFunc['htmlspecialchars']($category['description'], ENT_QUOTES);
		preparsecode($description);

		$id_cat = $smcFunc['db_insert']('insert',
			'{db_prefix}boardrules_categories',
			array('title' => 'string-255', 'description' => 'string', 'cat_order' => 'int', 'lang' => 'string-80', 'enabled' => 'int'),
			array($smcFunc['htmlspecialchars']($category['title'], ENT_QUOTES), $description, ++$cat_order, '', 1),
			array('id_cat'),
			1
		);

		if (empty($id_cat))
			continue;

		$rule_order = 0;
		foreach ($category['rules'] as $rule)
		{
			$body = $smcFunc['htmlspecialchars']($rule[1], ENT_QUOTES);
			preparsecode($body);

			$smcFunc['db_insert']('insert',
				'{db_prefix}boardrules_rules',
				array(
					'id_cat' => 'int', 'title' => 'string-255', 'body' => 'string', 'penalty' => 'string-255',
					'boards' => 'string', 'lang' => 'string-80', 'rule_order' => 'int', 'enabled' => 'int', 'updated' => 'int',
				),
				array(
					$id_cat, $smcFunc['htmlspecialchars']($rule[0], ENT_QUOTES), $body, $smcFunc['htmlspecialchars']($rule[2], ENT_QUOTES),
					'', '', ++$rule_order, 1, $now,
				),
				array('id_rule')
			);
		}
	}

	updateSettings(array('boardrules_rev' => (int) (isset($modSettings['boardrules_rev']) ? $modSettings['boardrules_rev'] : 1) + 1));
}

if (SMF == 'SSI')
	echo 'Board Rules database changes complete.';
