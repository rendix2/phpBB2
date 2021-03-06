<?php
/***************************************************************************
 *                               privmsgs.php
 *                            -------------------
 *   begin                : Saturday, Jun 9, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: privmsg.php 8342 2008-01-29 11:05:17Z Kellanved $
 *
 *
 ***************************************************************************/

use Nette\Utils\Random;
use phpBB2\Mailer;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep;

require_once $phpbb_root_path . 'common.php';
require_once $phpbb_root_path . 'includes' . $sep . 'bbcode.php';

//
// Is PM disabled?
//
if (!empty($board_config['privmsg_disable'])) {
	message_die(GENERAL_MESSAGE, 'PM_disabled');
}

//
// Parameters
//
$submit = isset($_POST['post']);
$submit_search = isset($_POST['usersubmit']);
$submit_msgdays = isset($_POST['submit_msgdays']);
$cancel = isset($_POST['cancel']);
$preview = isset($_POST['preview']);
$confirm = isset($_POST['confirm']);
$delete = isset($_POST['delete']);
$delete_all = isset($_POST['deleteall']);
$save = isset($_POST['save']);
$sid = isset($_POST['sid']) ? $_POST['sid'] : 0;

$refresh = $preview || $submit_search;

$mark_list = !empty($_POST['mark']) ? $_POST['mark'] : 0;

// todo

// $folder = 'inbox' if we dont have $folder OR folder is not in enabled
if (isset($_POST['folder']) || isset($_GET['folder'])) {
    $folder = isset($_POST['folder']) ? $_POST['folder'] : $_GET['folder'];

    $enabledFolders = ['inbox', 'outbox', 'sentbox', 'savebox'];

    if (!in_array($folder, $enabledFolders, true)) {
        $folder = 'inbox';
    }
} else {
    $folder = 'inbox';
}

//
// Start session management
//
$userdata = init_userprefs(PAGE_PRIVMSGS);
//
// End session management
//

//
// Cancel 
//
if ($cancel) {
    redirect(Session::appendSid("privmsg.php?folder=$folder", true));
}

//
// Var definitions
//
$mode = '';

if (!empty($_POST[POST_MODE]) || !empty($_GET[POST_MODE])) {
    $mode = !empty($_POST[POST_MODE]) ? $_POST[POST_MODE] : $_GET[POST_MODE];
}

$start = !empty($_GET['start']) ? (int)$_GET['start'] : 0;
$start = $start < 0 ? 0 : $start;

$privmsg_id = '';

if (isset($_POST[POST_POST_URL]) || isset($_GET[POST_POST_URL])) {
    $privmsg_id = isset($_POST[POST_POST_URL]) ? (int)$_POST[POST_POST_URL] : (int)$_GET[POST_POST_URL];
}

$error = false;

//
// Define the box image links
//
$inboxImage = $folder !== 'inbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=inbox') . '"><img src="' . $images['pm_inbox'] . '" border="0" alt="' . $lang['Inbox'] . '" /></a>' : '<img src="' . $images['pm_inbox'] . '" border="0" alt="' . $lang['Inbox'] . '" />';
$inboxUrl   = $folder !== 'inbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=inbox') . '">' . $lang['Inbox'] . '</a>' : $lang['Inbox'];

$outBoxImage = $folder !== 'outbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=outbox') . '"><img src="' . $images['pm_outbox'] . '" border="0" alt="' . $lang['Outbox'] . '" /></a>' : '<img src="' . $images['pm_outbox'] . '" border="0" alt="' . $lang['Outbox'] . '" />';
$outBoxUrl   = $folder !== 'outbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=outbox') . '">' . $lang['Outbox'] . '</a>' : $lang['Outbox'];

$sentBoxImage = $folder !== 'sentbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=sentbox') . '"><img src="' . $images['pm_sentbox'] . '" border="0" alt="' . $lang['Sentbox'] . '" /></a>' : '<img src="' . $images['pm_sentbox'] . '" border="0" alt="' . $lang['Sentbox'] . '" />';
$sentBoxUrl   = $folder !== 'sentbox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=sentbox') . '">' . $lang['Sentbox'] . '</a>' : $lang['Sentbox'];

$saveBoxImage = $folder !== 'savebox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=savebox') . '"><img src="' . $images['pm_savebox'] . '" border="0" alt="' . $lang['Savebox'] . '" /></a>' : '<img src="' . $images['pm_savebox'] . '" border="0" alt="' . $lang['Savebox'] . '" />';
$saveBoxUrl   = $folder !== 'savebox' || $mode !== '' ? '<a href="' . Session::appendSid('privmsg.php?folder=savebox') . '">' . $lang['Savebox'] . '</a>' : $lang['Savebox'];

execute_privmsgs_attachment_handling($mode);

// ----------
// Start main
//
if ($mode === 'newpm') {
    $gen_simple_header = true;

	$page_title = $lang['Private_Messaging'];

    PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

    $template->setFileNames(['body' => 'privmsgs_popup.tpl']);

    if ($userdata['session_logged_in']) {
        if ($userdata['user_new_privmsg']) {
            $l_new_message = ($userdata['user_new_privmsg'] === 1) ? $lang['You_new_pm'] : $lang['You_new_pms'];
        } else {
            $l_new_message = $lang['You_no_new_pm'];
        }

		$l_new_message .= '<br /><br />' . sprintf($lang['Click_view_privmsg'], '<a href="' . Session::appendSid('privmsg.php?folder=inbox') . '" onclick="jump_to_inbox();return false;" target="_new">', '</a>');
	} else {
		$l_new_message = $lang['Login_check_pm'];
	}

    $template->assignVars(
        [
            'L_CLOSE_WINDOW' => $lang['Close_window'],
            'L_MESSAGE'      => $l_new_message
        ]
    );

    $template->pparse('body');

    PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
} elseif ($mode === 'read') {
    if (!empty($_GET[POST_POST_URL])) {
        $privmsgs_id = (int)$_GET[POST_POST_URL];
    } else {
        message_die(GENERAL_ERROR, $lang['No_post_id']);
    }

	if (!$userdata['session_logged_in']) {
		redirect(Session::appendSid("login.php?redirect=privmsg.php&folder=$folder&mode=$mode&" . POST_POST_URL . "=$privmsgs_id", true));
	}

    $columns = [
        'u.user_sig_bbcode_uid',
        'u.user_posts',
        'u.user_from',
        'u.user_website',
        'u.user_email',
        'u.user_reg_date',
        'u.user_rank',
        'u.user_sig',
        'u.user_avatar',
        'pm.*',
        'pmt.privmsgs_bbcode_uid',
        'pmt.privmsgs_text'
    ];

    //
    // Major query obtains the message ...
    //
    $privmsg = dibi::select('u.username')
        ->as('username_1')
        ->select('u.user_id')
        ->as('user_id_1')
        ->select('u2.username')
        ->as('username_2')
        ->select('u2.user_id')
        ->as('user_id_2')
        ->select('u.user_session_time')
        ->as('user_session_time_1')
        ->select('u.user_allow_view_online')
        ->select($columns)
        ->from(Tables::PRIVATE_MESSAGE_TABLE)
        ->as('pm')
        ->innerJoin(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
        ->as('pmt')
        ->on('[pmt.privmsgs_text_id] = [pm.privmsgs_id]')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u')
        ->on('[u.user_id] = [pm.privmsgs_from_userid]')
        ->innerJoin(Tables::USERS_TABLE)
        ->as('u2')
        ->on('[u2.user_id] = [pm.privmsgs_to_userid]')
        ->where('[pm.privmsgs_id] = %i', $privmsgs_id);

	//
	// SQL to pull appropriate message, prevents nosey people
	// reading other peoples messages ... hopefully!
	//
    switch ($folder) {
		case 'inbox':
			$l_box_name = $lang['Inbox'];

            $privmsg->where('[pm.privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[pm.privmsgs_type] IN %in', [PRIVMSGS_READ_MAIL, PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL]);
			break;
			
		case 'outbox':
			$l_box_name = $lang['Outbox'];

            $privmsg->where('[pm.privmsgs_from_userid] = %i', $userdata['user_id'])
                    ->where('[pm.privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL]);
            break;
			
		case 'sentbox':
			$l_box_name = $lang['Sentbox'];

            $privmsg->where('[pm.privmsgs_from_userid] = %i', $userdata['user_id'])
                    ->where('[pm.privmsgs_type] = %i', PRIVMSGS_SENT_MAIL);
			break;
			
		case 'savebox':
			$l_box_name = $lang['Savebox'];

            $privmsg->where('(([pm.privmsgs_to_userid] = %i AND [pm.privmsgs_type] = %i) OR ([pm.privmsgs_from_userid] = %i AND [pm.privmsgs_type] = %i))',
                    $userdata['user_id'],
                    PRIVMSGS_SAVED_IN_MAIL,
                    $userdata['user_id'],
                    PRIVMSGS_SAVED_OUT_MAIL
                );
			break;
			
		default:
			message_die(GENERAL_ERROR, $lang['No_such_folder']);
			break;
	}

	$privmsg = $privmsg->fetch();

	//
	// Did the query return any data?
	//
	if (!$privmsg) {
		redirect(Session::appendSid("privmsg.php?folder=$folder", true));
	}

	$privmsg_id = $privmsg->privmsgs_id;

	//
	// Is this a new message in the inbox? If it is then save
	// a copy in the posters sent box
	//
	if (($privmsg->privmsgs_type === PRIVMSGS_NEW_MAIL || $privmsg->privmsgs_type === PRIVMSGS_UNREAD_MAIL) && $folder === 'inbox') {
	    // Update appropriate counter
        switch ($privmsg->privmsgs_type) {
            case PRIVMSGS_NEW_MAIL:
                $countUnread = dibi::select('COUNT(*)')
                    ->as('count')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] = %i', PRIVMSGS_NEW_MAIL)
                    ->fetchSingle();

                $countUnread--;

                if ($countUnread < 0) {
                    $countUnread = 0;
                }

                dibi::update(Tables::USERS_TABLE, ['user_new_privmsg' => 'user_new_privmsg - 1'])
                    ->where('[user_id] = %i', $userdata['user_id'])
                    ->execute();
                break;
            case PRIVMSGS_UNREAD_MAIL:
                $countUnread = dibi::select('COUNT(*)')
                    ->as('count')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] = %i', PRIVMSGS_UNREAD_MAIL)
                    ->fetchSingle();

                $countUnread--;

                if ($countUnread < 0) {
                    $countUnread = 0;
                }

                dibi::update(Tables::USERS_TABLE, ['user_unread_privmsg%sql' => $countUnread])
                    ->where('[user_id] = %i', $userdata['user_id'])
                    ->execute();
                break;
        }

		dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_type' => PRIVMSGS_READ_MAIL])
            ->where('[privmsgs_id] = %i', $privmsg->privmsgs_id)
            ->execute();

        $sent_info = dibi::select('COUNT(privmsgs_id)')
            ->as('sent_items')
            ->select('MIN(privmsgs_date)')
            ->as('oldest_post_time')
            ->from(Tables::PRIVATE_MESSAGE_TABLE)
            ->where('[privmsgs_from_userid] = %i', $privmsg->privmsgs_from_userid)
            ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL)
            ->fetch();

        $sql_priority = (Config::DBMS === 'mysql') ? 'LOW_PRIORITY' : '';

        if ($sent_info) {
			if ($board_config['max_sentbox_privmsgs'] && $sent_info->sent_items >= $board_config['max_sentbox_privmsgs']) {
                $old_privmsgs_id = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL)
                    ->where('[privmsgs_date] = %i', $sent_info->oldest_post_time)
                    ->where('[privmsgs_from_userid] = %i', $privmsg->privmsgs_from_userid)
                    ->fetchSingle();

				dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_id] = %i', $old_privmsgs_id)
                    ->execute();

                dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_text_id] = %i', $old_privmsgs_id)
                    ->execute();
			}
		}

		//
		// This makes a copy of the post and stores it as a SENT message from the sendee. Perhaps
		// not the most DB friendly way but a lot easier to manage, besides the admin will be able to
		// set limits on numbers of storable posts for users ... hopefully!
		//

        // TODO duplicate key
        $insert_data = [
            'privmsgs_type'           => PRIVMSGS_SENT_MAIL,
            'privmsgs_subject'        => $privmsg->privmsgs_subject,
            'privmsgs_from_userid'    => $privmsg->privmsgs_from_userid,
            'privmsgs_to_userid'      => $privmsg->privmsgs_to_userid,
            'privmsgs_date'           => $privmsg->privmsgs_date,
            'privmsgs_ip'             => $privmsg->privmsgs_ip,
            'privmsgs_enable_html'    => $privmsg->privmsgs_enable_html,
            'privmsgs_enable_bbcode'  => $privmsg->privmsgs_enable_bbcode,
            'privmsgs_enable_smilies' => $privmsg->privmsgs_enable_smilies,
            'privmsgs_attach_sig'     => $privmsg->privmsgs_attach_sig
        ];

        $privmsg_sent_id = dibi::insert(Tables::PRIVATE_MESSAGE_TABLE, $insert_data)->execute(dibi::IDENTIFIER);

        $insert_data = [
            'privmsgs_text_id'    => $privmsg_sent_id,
            'privmsgs_bbcode_uid' => $privmsg->privmsgs_bbcode_uid,
            'privmsgs_text'       => $privmsg->privmsgs_text
        ];

        dibi::insert(Tables::PRIVATE_MESSAGE_TEXT_TABLE, $insert_data)->execute();
	}

    $privmsg_sent_id = isset($privmsg_sent_id) ? $privmsg_sent_id : $privmsg_id;

    $attachment_mod['pm']->duplicate_attachment_pm($privmsg->privmsgs_attachment, $privmsg->privmsgs_id, $privmsg_sent_id);

	//
	// Pick a folder, any folder, so long as it's one below ...
	//
	$post_urls = [
        'post'  => Session::appendSid('privmsg.php?mode=post'),
        'reply' => Session::appendSid('privmsg.php?mode=reply&amp;' . POST_POST_URL . "=$privmsg_id"),
        'quote' => Session::appendSid('privmsg.php?mode=quote&amp;' . POST_POST_URL . "=$privmsg_id"),
        'edit'  => Session::appendSid('privmsg.php?mode=edit&amp;' . POST_POST_URL . "=$privmsg_id")
	];
	
	$post_icons = [
		'post_img' => '<a href="' . $post_urls['post'] . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0" /></a>',
		'post'     => '<a href="' . $post_urls['post'] . '">' . $lang['Post_new_pm'] . '</a>',
	    
		'reply_img' => '<a href="' . $post_urls['reply'] . '"><img src="' . $images['pm_replymsg'] . '" alt="' . $lang['Post_reply_pm'] . '" border="0" /></a>',
		'reply'     => '<a href="' . $post_urls['reply'] . '">' . $lang['Post_reply_pm'] . '</a>',
	    
		'quote_img' => '<a href="' . $post_urls['quote'] . '"><img src="' . $images['pm_quotemsg'] . '" alt="' . $lang['Post_quote_pm'] . '" border="0" /></a>',
		'quote'     => '<a href="' . $post_urls['quote'] . '">' . $lang['Post_quote_pm'] . '</a>',
	    
		'edit_img' => '<a href="' . $post_urls['edit'] . '"><img src="' . $images['pm_editmsg'] . '" alt="' . $lang['Edit_pm'] . '" border="0" /></a>',
		'edit'     => '<a href="' . $post_urls['edit'] . '">' . $lang['Edit_pm'] . '</a>'
	];

    // <!-- BEGIN Another Online/Offline indicator -->
    if ((!$privmsg->user_allow_view_online && $userdata['user_level'] === ADMIN) || $privmsg->user_allow_view_online) {
        $expiry_time = time() - ONLINE_TIME_DIFF;

        if ($privmsg->user_session_time_1 >= $expiry_time) {
            $user_onlinestatus = '<img src="' . $images['Online_small'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

            if (!$privmsg->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Hidden_Admin_small'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

            if (!$privmsg->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
            }
        }
    } else {
        $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
    }
    // <!-- END Another Online/Offline indicator -->

    if ($folder === 'inbox') {
		$postImage  = $post_icons['post_img'];
		$replyImage = $post_icons['reply_img'];
		$quoteImage = $post_icons['quote_img'];
		$editImage  = '';
		$post       = $post_icons['post'];
		$reply      = $post_icons['reply'];
		$quote      = $post_icons['quote'];
		$edit       = '';
		$l_box_name = $lang['Inbox'];
	} elseif ($folder === 'outbox') {
		$postImage  = $post_icons['post_img'];
		$replyImage = '';
		$quoteImage = '';
		$editImage  = $post_icons['edit_img'];
		$post       = $post_icons['post'];
		$reply      = '';
		$quote      = '';
		$edit       = $post_icons['edit'];
		$l_box_name = $lang['Outbox'];

        // <!-- BEGIN Another Online/Offline indicator -->
        $user_onlinestatus = '';
        // <!-- END Another Online/Offline indicator -->
    } elseif ($folder === 'savebox') {
        if ($privmsg->privmsgs_type === PRIVMSGS_SAVED_IN_MAIL) {
			$postImage  = $post_icons['post_img'];
			$replyImage = $post_icons['reply_img'];
			$quoteImage = $post_icons['quote_img'];
			$editImage  = '';
			$post       = $post_icons['post'];
			$reply      = $post_icons['reply'];
			$quote      = $post_icons['quote'];
			$edit       = '';

            // <!-- BEGIN Another Online/Offline indicator -->
            $user_onlinestatus = '';
            // <!-- END Another Online/Offline indicator -->
		} else {
			$postImage  = $post_icons['post_img'];
			$replyImage = '';
			$quoteImage = '';
			$editImage  = '';
			$post       = $post_icons['post'];
			$reply      = '';
			$quote      = '';
			$edit       = '';

            // <!-- BEGIN Another Online/Offline indicator -->
            $user_onlinestatus = '';
            // <!-- END Another Online/Offline indicator -->
		}

		$l_box_name = $lang['Saved'];
    } elseif ($folder === 'sentbox') {
		$postImage  = $post_icons['post_img'];
		$replyImage = '';
		$quoteImage = '';
		$editImage  = '';
		$post       = $post_icons['post'];
		$reply      = '';
		$quote      = '';
		$edit       = '';
		$l_box_name = $lang['Sent'];
	}

	$s_hidden_fields = '<input type="hidden" name="mark[]" value="' . $privmsgs_id . '" />';

	$page_title = $lang['Read_pm'];

    PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

	//
	// Load templates
	//
    $template->setFileNames(['body' => 'privmsgs_read_body.tpl']);
    make_jumpbox('viewforum.php');

    $template->assignVars(
        [
            'INBOX_IMG'   => $inboxImage,
            'SENTBOX_IMG' => $sentBoxImage,
            'OUTBOX_IMG'  => $outBoxImage,
            'SAVEBOX_IMG' => $saveBoxImage,

            'INBOX'   => $inboxUrl,
            'SENTBOX' => $sentBoxUrl,
            'OUTBOX'  => $outBoxUrl,
            'SAVEBOX' => $saveBoxUrl,

            'POST_PM_IMG'  => $postImage,
            'REPLY_PM_IMG' => $replyImage,
            'EDIT_PM_IMG'  => $editImage,
            'QUOTE_PM_IMG' => $quoteImage,

            'POST_PM'      => $post,
            'REPLY_PM'     => $reply,
            'EDIT_PM'      => $edit,
            'QUOTE_PM'     => $quote,

            'BOX_NAME' => $l_box_name,

            'L_MESSAGE'    => $lang['Message'],
            'L_INBOX'      => $lang['Inbox'],
            'L_OUTBOX'     => $lang['Outbox'],
            'L_SENTBOX'    => $lang['Sent'],
            'L_SAVEBOX'    => $lang['Saved'],
            'L_FLAG'       => $lang['Flag'],
            'L_SUBJECT'    => $lang['Subject'],
            'L_POSTED'     => $lang['Posted'],
            'L_DATE'       => $lang['Date'],
            'L_FROM'       => $lang['From'],
            'L_TO'         => $lang['To'],
            'L_SAVE_MSG'   => $lang['Save_message'],
            'L_DELETE_MSG' => $lang['Delete_message'],

            'S_PRIVMSGS_ACTION' => Session::appendSid("privmsg.php?folder=$folder"),
            'S_HIDDEN_FIELDS'   => $s_hidden_fields
        ]
    );

    $username_from = $privmsg->username_1;
	$user_id_from = $privmsg->user_id_1;
	$username_to = $privmsg->username_2;
	$user_id_to = $privmsg->user_id_2;

    init_display_pm_attachments($privmsg->privmsgs_attachment);

	$post_date = create_date($board_config['default_dateformat'], $privmsg->privmsgs_date, $board_config['board_timezone']);

	$temp_url = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $user_id_from);
	$profile_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_profile'] . '" alt="' . $lang['Read_profile'] . '" title="' . $lang['Read_profile'] . '" border="0" /></a>';
	$profile = '<a href="' . $temp_url . '">' . $lang['Read_profile'] . '</a>';

	$temp_url = Session::appendSid('privmsg.php?mode=post&amp;' . POST_USERS_URL . "=$user_id_from");
	$pm_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_pm'] . '" alt="' . $lang['Send_private_message'] . '" title="' . $lang['Send_private_message'] . '" border="0" /></a>';
	$pm = '<a href="' . $temp_url . '">' . $lang['Send_private_message'] . '</a>';

	if ($board_config['board_email_form']|| $userdata['user_level'] === ADMIN) {
		$email_uri = Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL .'=' . $user_id_from);

		$email_img = '<a href="' . $email_uri . '"><img src="' . $images['icon_email'] . '" alt="' . $lang['Send_email'] . '" title="' . $lang['Send_email'] . '" border="0" /></a>';
		$email = '<a href="' . $email_uri . '">' . $lang['Send_email'] . '</a>';
    } else {
		$email_img = '';
		$email = '';
	}

	$www_img = $privmsg->user_website ? '<a href="' . $privmsg->user_website . '" target="_userwww"><img src="' . $images['icon_www'] . '" alt="' . $lang['Visit_website'] . '" title="' . $lang['Visit_website'] . '" border="0" /></a>' : '';
	$www = $privmsg->user_website ? '<a href="' . $privmsg->user_website . '" target="_userwww">' . $lang['Visit_website'] . '</a>' : '';

	$temp_url   = Session::appendSid('search.php?search_author=' . urlencode($username_from) . '&amp;show_results=posts');
	$search_img = '<a href="' . $temp_url . '"><img src="' . $images['icon_search'] . '" alt="' . sprintf($lang['Search_user_posts'], $username_from) . '" title="' . sprintf($lang['Search_user_posts'], $username_from) . '" border="0" /></a>';
	$search     = '<a href="' . $temp_url . '">' . sprintf($lang['Search_user_posts'], $username_from) . '</a>';

	//
	// Processing of post
	//
	$post_subject    = $privmsg->privmsgs_subject;
	$private_message = $privmsg->privmsgs_text;
	$bbcode_uid      = $privmsg->privmsgs_bbcode_uid;

    if ($board_config['allow_sig']) {
        $user_sig = $privmsg->privmsgs_from_userid === $userdata['user_id'] ? $userdata['user_sig'] : $privmsg->user_sig;
    } else {
        $user_sig = '';
    }

    if ($privmsg->privmsgs_from_userid === $userdata['user_id']) {
        $user_sig_bbcode_uid = $userdata['user_sig_bbcode_uid'];
    } else {
        $user_sig_bbcode_uid = $privmsg->user_sig_bbcode_uid;
    }

	//
	// If the board has HTML off but the post has HTML
	// on then we process it, else leave it alone
	//
    if (!$board_config['allow_html'] || !$userdata['user_allow_html']) {
        if ($user_sig !== '') {
            $user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $user_sig);
        }

        if ($privmsg->privmsgs_enable_html) {
            $private_message = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $private_message);
        }
    }

	if ($user_sig !== '' && $privmsg->privmsgs_attach_sig && $user_sig_bbcode_uid !== '') {
		$user_sig = $board_config['allow_bbcode'] ? bbencode_second_pass($user_sig, $user_sig_bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $user_sig);
	}

	if ($bbcode_uid !== '') {
		$private_message = $board_config['allow_bbcode'] ? bbencode_second_pass($private_message, $bbcode_uid) : preg_replace('/\:[0-9a-z\:]+\]/si', ']', $private_message);
	}

	$private_message = make_clickable($private_message);

	if ($privmsg->privmsgs_attach_sig && $user_sig !== '') {
		$private_message .= $board_config['signature_delimiter'] . make_clickable($user_sig);
	}

	$orig_word = [];
	$replacement_word = [];
	obtain_word_list($orig_word, $replacement_word);

    if (count($orig_word)) {
        $post_subject    = preg_replace($orig_word, $replacement_word, $post_subject);
        $private_message = preg_replace($orig_word, $replacement_word, $private_message);
    }

    if ($board_config['allow_smilies'] && $privmsg->privmsgs_enable_smilies) {
        $private_message = smilies_pass($private_message);
    }

	$private_message = nl2br($private_message);

	//
	// Dump it to the templating engine
	//
    $template->assignVars(
        [
            'MESSAGE_TO'    => $username_to,
            'MESSAGE_FROM'  => $username_from . '&nbsp;' . $user_onlinestatus,

            /*
             * this variables are not used in template
             *
            'RANK_IMAGE'    => $rank_image,
            'POSTER_JOINED' => $poster_joined,
            'POSTER_POSTS'  => $poster_posts,
            'POSTER_FROM'   => $poster_from,
            'POSTER_AVATAR' => $poster_avatar,
            */

            'POST_SUBJECT'  => htmlspecialchars($post_subject, ENT_QUOTES),
            'POST_DATE'     => $post_date,
            'MESSAGE'       => $private_message,

            'PROFILE_IMG'    => $profile_img,
            'PROFILE'        => $profile,

            'SEARCH_IMG'     => $search_img,
            'SEARCH'         => $search,

            'EMAIL_IMG'      => $email_img,
            'EMAIL'          => $email,

            'WWW_IMG'        => $www_img,
            'WWW'            => $www,
        ]
    );

    $template->pparse('body');

    PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

} elseif (($delete && $mark_list) || $delete_all) {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=privmsg.php&folder=inbox', true));
    }

    if (isset($mark_list) && !is_array($mark_list)) {
        // Set to empty array instead of '0' if nothing is selected.
        $mark_list = [];
    }

    if (!$confirm) {
		$s_hidden_fields = '<input type="hidden" name="mode" value="' . $mode . '" />';
		$s_hidden_fields .= isset($_POST['delete']) ? '<input type="hidden" name="delete" value="true" />' : '<input type="hidden" name="deleteall" value="true" />';
		$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

		foreach ($mark_list as $value) {
			$s_hidden_fields .= '<input type="hidden" name="mark[]" value="' . (int)$value . '" />';
		}

		//
		// Output confirmation page
		//
        PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

        $template->setFileNames(['confirm_body' => 'confirm_body.tpl']);
        $template->assignVars(
            [
                'MESSAGE_TITLE' => $lang['Information'],
                'MESSAGE_TEXT'  => count($mark_list) === 1 ? $lang['Confirm_delete_pm'] : $lang['Confirm_delete_pms'],

                'L_YES' => $lang['Yes'],
                'L_NO'  => $lang['No'],

                'S_CONFIRM_ACTION' => Session::appendSid("privmsg.php?folder=$folder"),
                'S_HIDDEN_FIELDS'  => $s_hidden_fields
            ]
        );

        $template->pparse('confirm_body');

        PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

	} elseif ($confirm && $sid === $userdata['session_id']) {
	    // check marklist
		switch ($folder) {
			case 'inbox':
                $mark_list = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] IN %in', [PRIVMSGS_READ_MAIL, PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->fetchPairs(null, 'privmsgs_id');
                break;

			case 'outbox':
                $mark_list = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->fetchPairs(null, 'privmsgs_id');
				break;

			case 'sentbox':
                $mark_list = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL)
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->fetchPairs(null, 'privmsgs_id');
				break;

			case 'savebox':
                $mark_list = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where(
                        '( ([privmsgs_from_userid] = %i AND [privmsgs_type] = %i) OR ([privmsgs_to_userid]  = %i AND [privmsgs_type] = %i) )',
                        $userdata['user_id'],
                        PRIVMSGS_SAVED_OUT_MAIL,
                        $userdata['user_id'],
                        PRIVMSGS_SAVED_IN_MAIL
                    )
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->fetchPairs(null, 'privmsgs_id');
				break;
		}

        $attachment_mod['pm']->delete_all_pm_attachments($mark_list);

        if (count($mark_list)) {
            if ($folder === 'inbox' || $folder === 'outbox') {
                // Get information relevant to new or unread mail
                // so we can adjust users counters appropriately
			    $rows = dibi::select(['privmsgs_to_userid', 'privmsgs_type'])
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_id] IN %in', $mark_list);

				switch ($folder) {
					case 'inbox':
                        $rows->where('[privmsgs_to_userid] = %i', $userdata['user_id']);
						break;
					case 'outbox':
                        $rows->where('[privmsgs_from_userid] = %i', $userdata['user_id']);
						break;
				}

                $rows = $rows->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->fetchAll();

                if (count($rows)) {
					$update_users = $update_list = [];

					// todo is this safe? use ++ on empty array...
					foreach ($rows as $row) {
						switch ($row->privmsgs_type) {
							case PRIVMSGS_NEW_MAIL:
								$update_users['new'][$row->privmsgs_to_userid]++;
								break;

							case PRIVMSGS_UNREAD_MAIL:
								$update_users['unread'][$row->privmsgs_to_userid]++;
								break;
						}
					}

					if (count($update_users)) {
						foreach ($update_users as $type => $users) {
    						foreach ($users as $user_id => $dec) {
								$update_list[$type][$dec][] = $user_id;
							}
						}
						unset($update_users);

						foreach ($update_list as $type => $dec_array) {
							switch ($type) {
								case 'new':
									$type = 'user_new_privmsg';
									break;

								case 'unread':
									$type = 'user_unread_privmsg';
									break;
							}

							foreach ($dec_array as $dec => $user_ids) {
								dibi::update(Tables::USERS_TABLE, [$type . '%sql' => $type . ' - ' . $dec])
                                    ->where('[user_id] IN %in', $user_ids)
                                    ->execute();
							}
						}
						
						unset($update_list);
					}
				}
			}

            // Delete the messages text
			dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                ->where('[privmsgs_text_id] IN %in', $mark_list)
                ->execute();

            // Delete the messages
            switch ($folder) {
				case 'inbox':
                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $mark_list)
                        ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                        ->where('[privmsgs_type] IN %in', [PRIVMSGS_READ_MAIL, PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                        ->execute();
                    break;

				case 'outbox':
                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $mark_list)
                        ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
                        ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                        ->execute();
                    break;

				case 'sentbox':
                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $mark_list)
                        ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
                        ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL)
                        ->execute();
					break;

				case 'savebox':
                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $mark_list)
                        ->where(
                            '( ([privmsgs_from_userid] = %i AND [privmsgs_type] = %i ) OR ([privmsgs_to_userid] = %i AND [privmsgs_type] = %i ) )',
                            $userdata['user_id'],
                            PRIVMSGS_SAVED_OUT_MAIL,
                            $userdata['user_id'],
                            PRIVMSGS_SAVED_IN_MAIL
                        )
                        ->execute();
					break;
			}
		}
	}
} elseif ($save && $mark_list && $folder !== 'savebox' && $folder !== 'outbox') {
    if (!$userdata['session_logged_in']) {
        redirect(Session::appendSid('login.php?redirect=privmsg.php&folder=inbox', true));
    }
	
	if (count($mark_list)) {
		// See if recipient is at their savebox limit
        $saved_info = dibi::select('COUNT(privmsgs_id)')
            ->as('savebox_items')
            ->select('MIN(privmsgs_date)')
            ->as('oldest_post_time')
            ->from(Tables::PRIVATE_MESSAGE_TABLE)
            ->where(
                '(([privmsgs_to_userid] = %i AND [privmsgs_type] = %i) OR ([privmsgs_from_userid] = %i AND [privmsgs_type] = %i))',
                $userdata['user_id'],
                PRIVMSGS_SAVED_IN_MAIL,
                $userdata['user_id'],
                PRIVMSGS_SAVED_OUT_MAIL
            )->fetch();

		$sql_priority = Config::DBMS === 'mysql' ? 'LOW_PRIORITY' : '';

        if ($saved_info) {
			if ($board_config['max_savebox_privmsgs'] && $saved_info->savebox_items >= $board_config['max_savebox_privmsgs']) {
			    $old_privmsgs_id = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where(
                        '(([privmsgs_to_userid] = %i AND [privmsgs_type] = %i) OR ([privmsgs_from_userid] = %i AND [privmsgs_type] = %i))',
                        $userdata['user_id'],
                        PRIVMSGS_SAVED_IN_MAIL,
                        $userdata['user_id'],
                        PRIVMSGS_SAVED_OUT_MAIL
                    )
                    ->where('[privmsgs_date] = %i', $saved_info->oldest_post_time)
                    ->fetchSingle();

				dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_id] = %i', $old_privmsgs_id)
                    ->execute();

                dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_text_id] = %i', $old_privmsgs_id)
                    ->execute();
			}
		}

		// Decrement read/new counters if appropriate
		if ($folder === 'inbox' || $folder === 'outbox') {
			// Get information relevant to new or unread mail
			// so we can adjust users counters appropriately
            $rows = dibi::select(['privmsgs_to_userid','privmsgs_type'])
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->where('[privmsgs_id] IN %in', $mark_list);

            switch ($folder) {
                case 'inbox':
                    $rows->where('[privmsgs_to_userid] = %i', $userdata['user_id']);
                    break;
                case 'outbox':
                    $rows->where('[privmsgs_from_userid] = %i', $userdata['user_id']);
                    break;
            }

            $rows = $rows->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                ->fetchAll();

            // TODO its safe do ++ on empty array? :O
            if (count($rows)) {
				$update_users = $update_list = [];
			
				foreach ($rows as $row) {
					switch ($row->privmsgs_type) {
						case PRIVMSGS_NEW_MAIL:
							$update_users['new'][$row->privmsgs_to_userid]++;
							break;

						case PRIVMSGS_UNREAD_MAIL:
							$update_users['unread'][$row->privmsgs_to_userid]++;
							break;
					}
				}

				if (count($update_users)) {
					foreach ($update_users as $type => $users) {
						foreach ($users as $user_id => $dec) {
							$update_list[$type][$dec][] = $user_id;
						}
					}
					
					unset($update_users);

					foreach ($update_list as $type => $dec_array) {
						switch ($type) {
							case 'new':
								$type = 'user_new_privmsg';
								break;

							case 'unread':
								$type = 'user_unread_privmsg';
								break;
						}

						foreach ($dec_array as $dec => $user_ids) {
                            dibi::update(Tables::USERS_TABLE, [$type . '%sql' => $type . ' - ' . $dec])
                                ->where('[user_id] IN %in', $user_ids)
                                ->execute();
						}
					}
					
					unset($update_list);
				}
			}
		}

		switch ($folder) {
			case 'inbox':
                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_type' => PRIVMSGS_SAVED_IN_MAIL])
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] IN %in', [PRIVMSGS_READ_MAIL, PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->execute();
				break;

			case 'outbox':
                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_type' => PRIVMSGS_SAVED_OUT_MAIL])
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->execute();
				break;

			case 'sentbox':
                dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_type' => PRIVMSGS_SAVED_OUT_MAIL])
                    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
                    ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL)
                    ->where('[privmsgs_id] IN %in', $mark_list)
                    ->execute();
				break;
		}

		redirect(Session::appendSid('privmsg.php?folder=savebox', true));
	}
} elseif ($submit || $refresh || $mode !== '') {
	if (!$userdata['session_logged_in']) {
		$user_id = isset($_GET[POST_USERS_URL]) ? '&' . POST_USERS_URL . '=' . (int)$_GET[POST_USERS_URL] : '';
		redirect(Session::appendSid("login.php?redirect=privmsg.php&folder=$folder&mode=$mode" . $user_id, true));
	}
	
	//
	// Toggles
	//
    $html_on = 0;

    if ($board_config['allow_html']) {
        $html_on = $submit || $refresh ? !isset($_POST['disable_html']) : $userdata['user_allow_html'];
    }

    $bbcode_on = 0;

    if ($board_config['allow_bbcode']) {
        $bbcode_on = $submit || $refresh ? !isset($_POST['disable_bbcode']) : $userdata['user_allow_bbcode'];
    }

    $smilies_on = 0;

    if ($board_config['allow_smilies']) {
        $smilies_on = $submit || $refresh ? !isset($_POST['disable_smilies']) : $userdata['user_allow_smile'];
    }

	$attach_sig = $submit || $refresh ? isset($_POST['attach_sig']) : $userdata['user_attach_sig'];
	$user_sig = $userdata['user_sig'] !== '' && $board_config['allow_sig'] ? $userdata['user_sig'] : '';

    if ($submit && $mode !== 'edit') {
		//
		// Flood control
		//
        $last_post_time = dibi::select('MAX(privmsgs_date)')
            ->as('last_post_time')
            ->from(Tables::PRIVATE_MESSAGE_TABLE)
            ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
            ->fetchSingle();

        if ($last_post_time) {
            if ((time() - $last_post_time) < $board_config['flood_interval']) {
				message_die(GENERAL_MESSAGE, $lang['Flood_Error']);
			}
		}
		//
		// End Flood control
		//
	}

	if ($submit && $mode === 'edit') {
	    $row = dibi::select('privmsgs_from_userid')
            ->from(Tables::PRIVATE_MESSAGE_TABLE)
            ->where('[privmsgs_id] = %i', (int) $privmsg_id)
            ->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
            ->fetch();

		if (!$row) {
			message_die(GENERAL_MESSAGE, $lang['No_such_post']);
		}

		unset($row);
	}

    if ($submit) {
        $error_msg = '';

        // session id check
        if ($sid === '' || $sid !== $userdata['session_id']) {
            $error = true;
            $error_msg .= !empty($error_msg) ? '<br />' : '';
            $error_msg .= $lang['Session_invalid'];
        }

        if (!empty($_POST['username'])) {
            $toUserName = phpbb_clean_username($_POST['username']);

            $to_userdata = dibi::select(['user_id', 'user_notify_pm', 'user_email', 'user_lang', 'user_active'])
                ->from(Tables::USERS_TABLE)
                ->where('[username] = %s', $toUserName)
                ->fetch();

            if (!$to_userdata) {
                $error = true;
                $error_msg = $lang['No_such_user'];
            }

            $to_userdata = $to_userdata->toArray();
        } else {
            $error = true;
            $error_msg .= (!empty($error_msg) ? '<br />' : '') . $lang['No_to_user'];
        }

        $privmsg_subject = trim($_POST['subject']);
        if (empty($privmsg_subject)) {
            $error = true;
            $error_msg .= (!empty($error_msg) ? '<br />' : '') . $lang['Empty_subject'];
        }

        if (!empty($_POST['message'])) {
            if (!$error) {
                $bbcode_uid = 0;

                if ($bbcode_on) {
                    $bbcode_uid = Random::generate(BBCODE_UID_LEN);
                }

                $privmsg_message = PostHelper::prepareMessage($_POST['message'], $html_on, $bbcode_on, $smilies_on, $bbcode_uid);
            }
        } else {
            $error = true;
            $error_msg .= (!empty($error_msg) ? '<br />' : '') . $lang['Empty_message'];
        }
	}

    if ($submit && !$error) {
		//
		// Has admin prevented user from sending PM's?
		//
		if (!$userdata['user_allow_pm']) {
			message_die(GENERAL_MESSAGE, $lang['Cannot_send_privmsg']);
		}

		$msg_time = time();

        if ($mode !== 'edit') {
			//
			// See if recipient is at their inbox limit
			//

            $inbox_info = dibi::select('COUNT(privmsgs_id)')
                ->as('inbox_items')
                ->select('MIN(privmsgs_date)')
                ->as('oldest_post_time')
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_READ_MAIL, PRIVMSGS_UNREAD_MAIL])
                ->where('[privmsgs_to_userid] = %i', $to_userdata['user_id'])
                ->fetch();

            if (!$inbox_info) {
                message_die(GENERAL_MESSAGE, $lang['No_such_user']);
            }

            $sql_priority = Config::DBMS === 'mysql' ? 'LOW_PRIORITY' : '';

            if ($board_config['max_inbox_privmsgs'] && $inbox_info->inbox_items >= $board_config['max_inbox_privmsgs']) {
                $old_privmsgs_id = dibi::select('privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_READ_MAIL, PRIVMSGS_UNREAD_MAIL])
                    ->where('[privmsgs_date] = %i', $inbox_info->oldest_post_time)
                    ->where('[privmsgs_to_userid] = %i', $to_userdata['user_id'])
                    ->fetchSingle();

                dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_id = %i', $old_privmsgs_id)
                    ->execute();

                dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                    ->setFlag($sql_priority)
                    ->where('[privmsgs_text_id = %i', $old_privmsgs_id)
                    ->execute();
            }

            $insert_data = [
                'privmsgs_type'           => PRIVMSGS_NEW_MAIL,
                'privmsgs_subject'        => $privmsg_subject,
                'privmsgs_from_userid'    => $userdata['user_id'],
                'privmsgs_to_userid'      => $to_userdata['user_id'],
                'privmsgs_date'           => $msg_time,
                'privmsgs_ip'             => $user_ip,
                'privmsgs_enable_html'    => $html_on,
                'privmsgs_enable_bbcode'  => $bbcode_on,
                'privmsgs_enable_smilies' => $smilies_on,
                'privmsgs_attach_sig'     => $attach_sig
            ];

            $privmsg_sent_id = dibi::insert(Tables::PRIVATE_MESSAGE_TABLE, $insert_data)
                ->execute(dibi::IDENTIFIER);
		} else {
            $update_data = [
                'privmsgs_type'           => PRIVMSGS_NEW_MAIL,
                'privmsgs_subject'        => $privmsg_subject,
                'privmsgs_from_userid'    => $userdata['user_id'],
                'privmsgs_to_userid'      => $to_userdata['user_id'],
                'privmsgs_date'           => $msg_time,
                'privmsgs_ip'             => $user_ip,
                'privmsgs_enable_html'    => $html_on,
                'privmsgs_enable_bbcode'  => $bbcode_on,
                'privmsgs_enable_smilies' => $smilies_on,
                'privmsgs_attach_sig'     => $attach_sig
            ];

		    dibi::update(Tables::PRIVATE_MESSAGE_TABLE, $update_data)
                ->where('[privmsgs_id] = %i', $privmsg_id)
                ->execute();
		}

        if ($mode !== 'edit') {
            $insert_data = [
                'privmsgs_text_id'    => $privmsg_sent_id,
                'privmsgs_bbcode_uid' => $bbcode_uid,
                'privmsgs_text'       => $privmsg_message
            ];

			dibi::insert(Tables::PRIVATE_MESSAGE_TEXT_TABLE, $insert_data)->execute();
		} else {
            $update_data = [
                'privmsgs_text'       => $privmsg_message,
                'privmsgs_bbcode_uid' => $bbcode_uid
            ];

		    dibi::update(Tables::PRIVATE_MESSAGE_TEXT_TABLE, $update_data)
                ->where('[privmsgs_text_id] = %i', $privmsg_id)
                ->execute();
		}

        $attachment_mod['pm']->insert_attachment_pm($privmsg_id);

        if ($mode !== 'edit') {
			//
			// Add to the users new pm counter
			//

            $update_data = [
                'user_new_privmsg%sql' => 'user_new_privmsg + 1',
                'user_last_privmsg' => time()
            ];

            dibi::update(Tables::USERS_TABLE, $update_data)
                ->where('[user_id] = %i', $to_userdata['user_id'])
                ->execute();

            if ($to_userdata['user_notify_pm'] && !empty($to_userdata['user_email']) && $to_userdata['user_active']) {
				$script_name = preg_replace('/^\/?(.*?)\/?$/', "\\1", trim($board_config['script_path']));
				$script_name = $script_name !== '' ? $script_name . '/privmsg.php' : 'privmsg.php';
				$server_name = trim($board_config['server_name']);
				$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
				$server_port = $board_config['server_port'] !== 80 ? ':' . trim($board_config['server_port']) . '/' : '/';

				$params =
                    [
                        'USERNAME'  => stripslashes($toUserName),
                        'SITENAME'  => $board_config['sitename'],
                        'EMAIL_SIG' => $board_config['board_email_sig'],

                        'U_INBOX' => $server_protocol . $server_name . $server_port . $script_name . '?folder=inbox'
                    ];

                $mailer = new Mailer(
                    new LatteFactory($storage, $userdata),
                    $board_config,
                    'privmsg_notify',
                    $params,
                    $to_userdata['user_lang'],
                    $lang['Notification_subject'],
                    $to_userdata['user_email']
                );

				$mailer->send();
			}
		}

        $template->assignVars(
            [
                'META' => '<meta http-equiv="refresh" content="3;url=' . Session::appendSid('privmsg.php?folder=inbox') . '">'
            ]
        );

        $message  = $lang['Message_sent'] . '<br /><br />';
        $message .= sprintf($lang['Click_return_inbox'], '<a href="' . Session::appendSid('privmsg.php?folder=inbox') . '">', '</a> ') . '<br /><br />';
        $message .= sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
    } elseif ($preview || $refresh || $error) {
		//
		// If we're previewing or refreshing then obtain the data
		// passed to the script, process it a little, do some checks
		// where neccessary, etc.
		//
		$toUserName      = isset($_POST['username']) ? trim(htmlspecialchars(stripslashes($_POST['username']))) : '';
		$privmsg_subject = isset($_POST['subject']) ? trim(stripslashes($_POST['subject']))   : '';

		$privmsg_message = isset($_POST['message']) ? trim($_POST['message']) : '';
		// $privmsg_message = preg_replace('#<textarea>#si', '&lt;textarea&gt;', $privmsg_message);

		if (!$preview) {
			$privmsg_message = stripslashes($privmsg_message);
		}

		//
		// Do mode specific things
		//
        if ($mode === 'post') {
            $page_title = $lang['Post_new_pm'];

            $user_sig = $userdata['user_sig'] !== '' && $board_config['allow_sig'] ? $userdata['user_sig'] : '';
        } elseif ($mode === 'reply') {
            $page_title = $lang['Post_reply_pm'];

            $user_sig = $userdata['user_sig'] !== '' && $board_config['allow_sig'] ? $userdata['user_sig'] : '';
        } elseif ($mode === 'edit') {
            $page_title = $lang['Edit_pm'];

            $postrow = dibi::select(['u.user_id', 'u.user_sig'])
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->as('pm')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('[u.user_id] = [pm.privmsgs_from_userid]')
                ->where('[pm.privmsgs_id] = %i', $privmsg_id)
                ->fetch();

            if ($userdata['user_id'] !== $postrow->user_id) {
                message_die(GENERAL_MESSAGE, $lang['Edit_own_posts']);
            }

            $user_sig = $postrow->user_sig !== '' && $board_config['allow_sig'] ? $postrow->user_sig : '';
        }
	} else {
        if (!$privmsg_id && ($mode === 'reply' || $mode === 'edit' || $mode === 'quote')) {
            message_die(GENERAL_ERROR, $lang['No_post_id']);
        }

        if (!empty($_GET[POST_USERS_URL])) {
			$user_id = (int)$_GET[POST_USERS_URL];

			if ($user_id === ANONYMOUS) {
                $error = true;
                $error_msg = $lang['No_such_user'];
            } else {
                $user_check = dibi::select('username')
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_id] = %i', $user_id)
                    ->fetch();

                if ($user_check) {
                    $toUserName = $user_check->username;
                } else {
                    $error     = true;
                    $error_msg = $lang['No_such_user'];
                }
            }
        } elseif ($mode === 'edit') {
		    $columns = [
		        'pm.*',
                'pmt.privmsgs_bbcode_uid',
                'pmt.privmsgs_text',
                'u.username',
                'u.user_id',
                'u.user_sig',
                'u.user_allow_view_online',
                'u.user_session_time'
            ];

            $privmsg = dibi::select($columns)
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->as('pm')
                ->innerJoin(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                ->as('pmt')
                ->on('[pmt.privmsgs_text_id] = [pm.privmsgs_id]')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('[u.user_id] = [pm.privmsgs_to_userid]')
                ->where('[pm.privmsgs_id] = %i', $privmsg_id)
                ->where('[pm.privmsgs_from_userid] = %i', $userdata['user_id'])
                ->where('[pm.privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL])
                ->fetch();

            if (!$privmsg) {
                redirect(Session::appendSid("privmsg.php?folder=$folder", true));
            }

			$privmsg_subject = $privmsg->privmsgs_subject;
			$privmsg_message = $privmsg->privmsgs_text;
			$privmsg_bbcode_uid = $privmsg->privmsgs_bbcode_uid;
			$privmsg_bbcode_enabled = $privmsg->privmsgs_enable_bbcode === 1;

            if ($privmsg_bbcode_enabled) {
                $privmsg_message = preg_replace("/\:(([a-z0-9]:)?)$privmsg_bbcode_uid/si", '', $privmsg_message);
            }
			
			$privmsg_message = str_replace('<br />', "\n", $privmsg_message);
			// $privmsg_message = preg_replace('#</textarea>#si', '&lt;/textarea&gt;', $privmsg_message);

            $user_sig = '';

            if ($board_config['allow_sig'] && $privmsg->privmsgs_type !== PRIVMSGS_NEW_MAIL) {
                $user_sig = $privmsg->user_sig;
            }

			$toUserName = $privmsg->username;
			$to_userid  = $privmsg->user_id;
        } elseif ($mode === 'reply' || $mode === 'quote') {
            $columns = [
                'pm.privmsgs_subject',
                'pm.privmsgs_date',
                'pmt.privmsgs_bbcode_uid',
                'pmt.privmsgs_text',
                'u.username',
                'u.user_id',
            ];

            $privmsg = dibi::select($columns)
                ->from(Tables::PRIVATE_MESSAGE_TABLE)
                ->as('pm')
                ->innerJoin(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                ->as('pmt')
                ->on('[pmt.privmsgs_text_id] = [pm.privmsgs_id]')
                ->innerJoin(Tables::USERS_TABLE)
                ->as('u')
                ->on('[u.user_id] = [pm.privmsgs_from_userid]')
                ->where('[pm.privmsgs_id] = %i', $privmsg_id)
                ->where('[pm.privmsgs_to_userid] = %i', $userdata['user_id'])
                ->fetch();

            if (!$privmsg) {
                redirect(Session::appendSid("privmsg.php?folder=$folder", true));
            }

			$orig_word = $replacement_word = [];
			obtain_word_list($orig_word, $replacement_word);

			$privmsg_subject = preg_match('/^Re:/', $privmsg->privmsgs_subject) ? '' : 'Re: ';
			$privmsg_subject .= $privmsg->privmsgs_subject;
			$privmsg_subject = preg_replace($orig_word, $replacement_word, $privmsg_subject);

			$toUserName = $privmsg->username;
			$to_userid  = $privmsg->user_id;

            if ($mode === 'quote') {
				$privmsg_message = $privmsg->privmsgs_text;
				$privmsg_bbcode_uid = $privmsg->privmsgs_bbcode_uid;

				$privmsg_message = preg_replace("/\:(([a-z0-9]:)?)$privmsg_bbcode_uid/si", '', $privmsg_message);
				$privmsg_message = str_replace('<br />', "\n", $privmsg_message);
				// $privmsg_message = preg_replace('#</textarea>#si', '&lt;/textarea&gt;', $privmsg_message);
				$privmsg_message = preg_replace($orig_word, $replacement_word, $privmsg_message);
				
				$msg_date = create_date($board_config['default_dateformat'], $privmsg->privmsgs_date, $board_config['board_timezone']);

				$privmsg_message = '[quote="' . $toUserName . '"]' . $privmsg_message . '[/quote]';

				$mode = 'reply';
			}
		} else {
			$privmsg_subject = $privmsg_message = $toUserName = '';
		}
	}

	//
	// Has admin prevented user from sending PM's?
	//
    if (!$userdata['user_allow_pm'] && $mode !== 'edit') {
        message_die(GENERAL_MESSAGE, $lang['Cannot_send_privmsg']);
    }

	//
	// Start output, first preview, then errors then post form
	//
	$page_title = $lang['Send_private_message'];

    PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

    if ($preview && !$error) {
		$orig_word = [];
		$replacement_word = [];
		obtain_word_list($orig_word, $replacement_word);

        if ($bbcode_on) {
			$bbcode_uid = Random::generate(BBCODE_UID_LEN);
		}

        $previewMessage = stripslashes(PostHelper::prepareMessage($privmsg_message, $html_on, $bbcode_on, $smilies_on, $bbcode_uid));
		$privmsg_message = stripslashes(preg_replace(PostHelper::$htmlEntitiesMatch, PostHelper::$htmlEntitiesReplace, $privmsg_message));

        //
        // Finalise processing as per viewtopic
        //
        if (!$html_on || !$board_config['allow_html'] || !$userdata['user_allow_html']) {
            if ($user_sig !== '') {
                $user_sig = preg_replace('#(<)([\/]?.*?)(>)#is', "&lt;\\2&gt;", $user_sig);
            }
        }

        if ($attach_sig && $user_sig !== '' && $userdata['user_sig_bbcode_uid']) {
            $user_sig = bbencode_second_pass($user_sig, $userdata['user_sig_bbcode_uid']);
        }

        if ($bbcode_on) {
            $previewMessage = bbencode_second_pass($previewMessage, $bbcode_uid);
        }

        if ($attach_sig && $user_sig !== '') {
            $previewMessage .= $user_sig . $board_config['signature_delimiter'];
        }

        if (count($orig_word)) {
            $previewSubject = preg_replace($orig_word, $replacement_word, $privmsg_subject);
            $previewMessage = preg_replace($orig_word, $replacement_word, $previewMessage);
        } else {
            $previewSubject = $privmsg_subject;
        }

        if ($smilies_on) {
            $previewMessage = smilies_pass($previewMessage);
        }

		$previewMessage = make_clickable($previewMessage);
		$previewMessage = nl2br($previewMessage);

		$s_hidden_fields = '<input type="hidden" name="folder" value="' . $folder . '" />';
		$s_hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';

        if (isset($privmsg_id)) {
            $s_hidden_fields .= '<input type="hidden" name="' . POST_POST_URL . '" value="' . $privmsg_id . '" />';
        }

        $template->setFileNames(['preview' => 'privmsgs_preview.tpl']);

        $attachment_mod['pm']->preview_attachments();

        $template->assignVars(
            [
                'TOPIC_TITLE'  => $previewSubject,
                'POST_SUBJECT' => $previewSubject,
                'MESSAGE_TO'   => $toUserName,
                'MESSAGE_FROM' => $userdata['username'],
                'POST_DATE'    => create_date($board_config['default_dateformat'], time(), $board_config['board_timezone']),
                'MESSAGE'      => $previewMessage,

                'S_HIDDEN_FIELDS' => $s_hidden_fields,

                'L_SUBJECT' => $lang['Subject'],
                'L_DATE'    => $lang['Date'],
                'L_FROM'    => $lang['From'],
                'L_TO'      => $lang['To'],
                'L_PREVIEW' => $lang['Preview'],
                'L_POSTED'  => $lang['Posted']
            ]
        );

        $template->assignVarFromHandle('POST_PREVIEW_BOX', 'preview');
	}

	//
	// Start error handling
	//
    if ($error) {
        $privmsg_message = htmlspecialchars($privmsg_message);

        $template->setFileNames(['reg_header' => 'error_body.tpl']);
        $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
        $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
    }

    //
    // Load templates
	//
    $template->setFileNames(['body' => 'posting_body.tpl']);
    make_jumpbox('viewforum.php');

	//
	// Enable extensions in posting_body
	//
	$template->assignBlockVars('switch_privmsg', []);

	//
	// HTML toggle selection
	//
    if ($board_config['allow_html']) {
        $html_status = $lang['HTML_is_ON'];
        $template->assignBlockVars('switch_html_checkbox', []);
    } else {
        $html_status = $lang['HTML_is_OFF'];
    }

	//
	// BBCode toggle selection
	//
    if ($board_config['allow_bbcode']) {
        $bbcode_status = $lang['BBCode_is_ON'];
        $template->assignBlockVars('switch_bbcode_checkbox', []);
    } else {
        $bbcode_status = $lang['BBCode_is_OFF'];
    }

	//
	// Smilies toggle selection
	//
    if ($board_config['allow_smilies']) {
        $smilies_status = $lang['Smilies_are_ON'];
        $template->assignBlockVars('switch_smilies_checkbox', []);
    } else {
        $smilies_status = $lang['Smilies_are_OFF'];
    }

	//
	// Signature toggle selection - only show if
	// the user has a signature
	//
    if ($user_sig !== '') {
        $template->assignBlockVars('switch_signature_checkbox', []);
    }

    if ($mode === 'post') {
        $post_a = $lang['Send_a_new_message'];
        $privmsg_subject = isset($_POST['subject']) ? $_POST['subject'] : '';
        $privmsg_message  = isset($_POST['message']) ? $_POST['message'] : '';

        // $l_box_name was undefined :O
        $l_box_name = $lang['Send_a_new_message'];
    } elseif ($mode === 'reply') {
        $post_a = $lang['Send_a_reply'];
        $l_box_name = $lang['Send_a_reply'];
        $mode   = 'post';
    } elseif ($mode === 'edit') {
        $post_a = $lang['Edit_message'];
        $l_box_name = $lang['Edit_message'];
    }

	$s_hidden_fields = '<input type="hidden" name="folder" value="' . $folder . '" />';
	$s_hidden_fields .= '<input type="hidden" name="mode" value="' . $mode . '" />';
	$s_hidden_fields .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';

    if ($mode === 'edit') {
        $s_hidden_fields .= '<input type="hidden" name="' . POST_POST_URL . '" value="' . $privmsg_id . '" />';
    }

	//
	// Send smilies to template
	//
    PostHelper::generateSmileys('inline', PAGE_PRIVMSGS);

	$template->assignVars(
	    [
            'SUBJECT' => $privmsg_subject,
            'USERNAME' => $toUserName,
            'MESSAGE' => $privmsg_message,
            'HTML_STATUS' => $html_status,
            'SMILIES_STATUS' => $smilies_status,
            'BBCODE_STATUS' => sprintf($bbcode_status, '<a href="' . Session::appendSid('faq.php?mode=bbcode') . '" target="_phpbbcode">', '</a>'),
            'FORUM_NAME' => $lang['Private_Message'],

            'BOX_NAME' => $l_box_name,
            'INBOX_IMG' => $inboxImage,
            'SENTBOX_IMG' => $sentBoxImage,
            'OUTBOX_IMG' => $outBoxImage,
            'SAVEBOX_IMG' => $saveBoxImage,
            'INBOX' => $inboxUrl,
            'SENTBOX' => $sentBoxUrl,
            'OUTBOX' => $outBoxUrl,
            'SAVEBOX' => $saveBoxUrl,

            'L_SUBJECT' => $lang['Subject'],
            'L_MESSAGE_BODY' => $lang['Message_body'],
            'L_OPTIONS' => $lang['Options'],
            'L_SPELLCHECK' => $lang['Spellcheck'],
            'L_PREVIEW' => $lang['Preview'],
            'L_SUBMIT' => $lang['Submit'],
            'L_CANCEL' => $lang['Cancel'],
            'L_POST_A' => $post_a,
            'L_FIND_USERNAME' => $lang['Find_username'],
            'L_FIND' => $lang['Find'],
            'L_DISABLE_HTML' => $lang['Disable_HTML_pm'],
            'L_DISABLE_BBCODE' => $lang['Disable_BBCode_pm'],
            'L_DISABLE_SMILIES' => $lang['Disable_Smilies_pm'],
            'L_ATTACH_SIGNATURE' => $lang['Attach_signature'],

            'L_BBCODE_B_HELP' => $lang['bbcode_b_help'],
            'L_BBCODE_I_HELP' => $lang['bbcode_i_help'],
            'L_BBCODE_U_HELP' => $lang['bbcode_u_help'],
            'L_BBCODE_Q_HELP' => $lang['bbcode_q_help'],
            'L_BBCODE_C_HELP' => $lang['bbcode_c_help'],
            'L_BBCODE_L_HELP' => $lang['bbcode_l_help'],
            'L_BBCODE_O_HELP' => $lang['bbcode_o_help'],
            'L_BBCODE_P_HELP' => $lang['bbcode_p_help'],
            'L_BBCODE_W_HELP' => $lang['bbcode_w_help'],
            'L_BBCODE_A_HELP' => $lang['bbcode_a_help'],
            'L_BBCODE_S_HELP' => $lang['bbcode_s_help'],
            'L_BBCODE_F_HELP' => $lang['bbcode_f_help'],
            'L_EMPTY_MESSAGE' => $lang['Empty_message'],

            'L_FONT_COLOR' => $lang['Font_color'],
            'L_COLOR_DEFAULT' => $lang['color_default'],
            'L_COLOR_DARK_RED' => $lang['color_dark_red'],
            'L_COLOR_RED' => $lang['color_red'],
            'L_COLOR_ORANGE' => $lang['color_orange'],
            'L_COLOR_BROWN' => $lang['color_brown'],
            'L_COLOR_YELLOW' => $lang['color_yellow'],
            'L_COLOR_GREEN' => $lang['color_green'],
            'L_COLOR_OLIVE' => $lang['color_olive'],
            'L_COLOR_CYAN' => $lang['color_cyan'],
            'L_COLOR_BLUE' => $lang['color_blue'],
            'L_COLOR_DARK_BLUE' => $lang['color_dark_blue'],
            'L_COLOR_INDIGO' => $lang['color_indigo'],
            'L_COLOR_VIOLET' => $lang['color_violet'],
            'L_COLOR_WHITE' => $lang['color_white'],
            'L_COLOR_BLACK' => $lang['color_black'],

            'L_FONT_SIZE' => $lang['Font_size'],
            'L_FONT_TINY' => $lang['font_tiny'],
            'L_FONT_SMALL' => $lang['font_small'],
            'L_FONT_NORMAL' => $lang['font_normal'],
            'L_FONT_LARGE' => $lang['font_large'],
            'L_FONT_HUGE' => $lang['font_huge'],

            'L_BBCODE_CLOSE_TAGS' => $lang['Close_Tags'],
            'L_STYLES_TIP' => $lang['Styles_tip'],

            'S_HTML_CHECKED' => !$html_on ? ' checked="checked"' : '',
            'S_BBCODE_CHECKED' => !$bbcode_on ? ' checked="checked"' : '',
            'S_SMILIES_CHECKED' => !$smilies_on ? ' checked="checked"' : '',
            'S_SIGNATURE_CHECKED' => $attach_sig ? ' checked="checked"' : '',
            'S_HIDDEN_FORM_FIELDS' => $s_hidden_fields,
            'S_POST_ACTION' => Session::appendSid('privmsg.php'),

            'U_SEARCH_USER' => Session::appendSid('search.php?mode=searchuser'),
            'U_VIEW_FORUM' => Session::appendSid('privmsg.php')
        ]
	);

	$template->pparse('body');

    PageHelper::footer($template, $userdata, $lang, $gen_simple_header);
}

//
// Default page
//
if (!$userdata['session_logged_in']) {
    redirect(Session::appendSid('login.php?redirect=privmsg.php&folder=inbox', true));
}

//
// Update unread status 
//
$update_data = [
    'user_unread_privmsg%sql' => 'user_unread_privmsg + user_new_privmsg',
    'user_new_privmsg' => 0,
    'user_last_privmsg' => $userdata['session_start']
];

dibi::update(Tables::USERS_TABLE, $update_data)
    ->where('[user_id] = %i', $userdata['user_id'])
    ->execute();

dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_type' => PRIVMSGS_UNREAD_MAIL])
    ->where('[privmsgs_type] = %i', PRIVMSGS_NEW_MAIL)
    ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
    ->execute();

//
// Reset PM counters
//
$userdata['user_new_privmsg'] = 0;
$userdata['user_unread_privmsg'] = $userdata['user_new_privmsg'] + $userdata['user_unread_privmsg'];

//
// Generate page
//
$page_title = $lang['Private_Messaging'];

PageHelper::header($template, $userdata, $board_config, $lang, $images, $theme, $page_title, $gen_simple_header);

//
// Load templates
//
$template->setFileNames(['body' => 'privmsgs_body.tpl']);
make_jumpbox('viewforum.php');

$orig_word = [];
$replacement_word = [];
obtain_word_list($orig_word, $replacement_word);

//
// New message
//
$post_new_mesg_url = '<a href="' . Session::appendSid('privmsg.php?mode=post') . '"><img src="' . $images['post_new'] . '" alt="' . $lang['Send_a_new_message'] . '" border="0" /></a>';

//
// General SQL to obtain messages
//
$sql_tot = dibi::select('COUNT(privmsgs_id)')
    ->as('total')
    ->from(Tables::PRIVATE_MESSAGE_TABLE);

$columns = [
    'pm.privmsgs_type',
    'pm.privmsgs_id',
    'pm.privmsgs_date',
    'pm.privmsgs_subject',
    'u.user_id',
    'u.username',
    'u.user_session_time',
    'u.user_allow_view_online'
];

$sql = dibi::select($columns)
    ->from(Tables::PRIVATE_MESSAGE_TABLE)
    ->as('pm')
    ->innerJoin(Tables::USERS_TABLE)
    ->as('u');

switch ($folder) {
	case 'inbox':
        $sql_tot->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
            ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_READ_MAIL, PRIVMSGS_UNREAD_MAIL]);

		$sql->on('[u.user_id] = [pm.privmsgs_from_userid]')
            ->where('[privmsgs_to_userid] = %i', $userdata['user_id'])
            ->where('[pm.privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_READ_MAIL, PRIVMSGS_UNREAD_MAIL]);
		break;

	case 'outbox':
        $sql_tot->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
            ->where('[privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_READ_MAIL]);

        $sql->on('[u.user_id] = [pm.privmsgs_to_userid]')
            ->where('[pm.privmsgs_from_userid] = %i', $userdata['user_id'])
            ->where('[pm.privmsgs_type] IN %in', [PRIVMSGS_NEW_MAIL, PRIVMSGS_UNREAD_MAIL]);
		break;

	case 'sentbox':
        $sql_tot->where('[privmsgs_from_userid] = %i', $userdata['user_id'])
            ->where('[privmsgs_type] = %i', PRIVMSGS_SENT_MAIL);

        $sql->on('[u.user_id] = [pm.privmsgs_to_userid]')
            ->where('[pm.privmsgs_from_userid] = %i', $userdata['user_id'])
            ->where('[pm.privmsgs_type] = %i', PRIVMSGS_SENT_MAIL);
		break;

	case 'savebox':
        $sql_tot->where(
            '(([privmsgs_to_userid] = %i AND [privmsgs_type] = %i) OR ([privmsgs_from_userid] = %i AND [privmsgs_type] = %i))',
            $userdata['user_id'],
            PRIVMSGS_SAVED_IN_MAIL,
            $userdata['user_id'],
            PRIVMSGS_SAVED_OUT_MAIL
        );

        $sql->on('[u.user_id] = [pm.privmsgs_from_userid]')
            ->where(
                '(([pm.privmsgs_to_userid] = %i AND [pm.privmsgs_type] = %i) OR ([pm.privmsgs_from_userid] = %i AND [pm.privmsgs_type] = %i))',
                $userdata['user_id'],
                PRIVMSGS_SAVED_IN_MAIL,
                $userdata['user_id'],
                PRIVMSGS_SAVED_OUT_MAIL
            );

		break;

	default:
		message_die(GENERAL_MESSAGE, $lang['No_such_folder']);
		break;
}

//
// Show messages over previous x days/months
//
if ($submit_msgdays && (!empty($_POST['msgdays']) || !empty($_GET['msgdays']))) {
	$msg_days = !empty($_POST['msgdays']) ? (int)$_POST['msgdays'] : (int)$_GET['msgdays'];
    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $min_msg_time = new DateTime();
    $min_msg_time->setTimezone(new DateTimeZone($user_timezone));
    $min_msg_time->sub(new DateInterval('P'. $msg_days. 'D'));

    $min_msg_time = $min_msg_time->getTimestamp();

    $limit_msg_time_total = $sql_tot->where('[privmsgs_date] > %i', $min_msg_time);
    $limit_msg_time       = $sql->where('[pm.privmsgs_date] > %i', $min_msg_time);

	if (!empty($_POST['msgdays'])) {
		$start = 0;
	}
} else {
    $limit_msg_time       = $sql;
    $limit_msg_time_total = $sql_tot;
	$msg_days = 0;
}

$sql = $limit_msg_time->orderBy('pm.privmsgs_date', dibi::DESC)
    ->limit($board_config['pm_per_page'])
    ->offset($start);

$sql_all_tot = $sql_tot;
$sql_tot = $limit_msg_time_total;

//
// Get messages
//
$pm_total = $sql_tot->fetchSingle();
$pm_all_total = $sql_all_tot->fetchSingle();

//
// Build select box
//
$previous_days = [
    0   => $lang['All_Posts'],
    1   => $lang['1_Day'],
    7   => $lang['7_Days'],
    14  => $lang['2_Weeks'],
    30  => $lang['1_Month'],
    90  => $lang['3_Months'],
    180 => $lang['6_Months'],
    364 => $lang['1_Year']
];

$select_msg_days = '';

foreach ($previous_days as $previous_day_key => $previous_days_value) {
    $selected = $msg_days === $previous_day_key ? ' selected="selected"' : '';

    $select_msg_days .= '<option value="' . $previous_day_key . '"' . $selected . '>' . $previous_days_value . '</option>';
}

//
// Define correct icons
//
switch ($folder) {
    case 'inbox':
        $l_box_name = $lang['Inbox'];
        break;
    case 'outbox':
        $l_box_name = $lang['Outbox'];
        break;
    case 'savebox':
        $l_box_name = $lang['Savebox'];
        break;
    case 'sentbox':
        $l_box_name = $lang['Sentbox'];
        break;
}

$post_pm = Session::appendSid('privmsg.php?mode=post');
$post_pm_img = '<a href="' . $post_pm . '"><img src="' . $images['pm_postmsg'] . '" alt="' . $lang['Post_new_pm'] . '" border="0" /></a>';
$post_pm = '<a href="' . $post_pm . '">' . $lang['Post_new_pm'] . '</a>';

//
// Output data for inbox status
//
if ($folder !== 'outbox') {
	$inbox_limit_pct = $board_config['max_' . $folder . '_privmsgs'] > 0 ? round(( $pm_all_total / $board_config['max_' . $folder . '_privmsgs'] ) * 100) : 100;
	$inbox_limit_img_length = $board_config['max_' . $folder . '_privmsgs'] > 0 ? round(( $pm_all_total / $board_config['max_' . $folder . '_privmsgs'] ) * $board_config['privmsg_graphic_length']) : $board_config['privmsg_graphic_length'];
	$inbox_limit_remain = $board_config['max_' . $folder . '_privmsgs'] > 0 ? $board_config['max_' . $folder . '_privmsgs'] - $pm_all_total : 0;

	$template->assignBlockVars('switch_box_size_notice', []);

    switch ($folder) {
        case 'inbox':
            $l_box_size_status = sprintf($lang['Inbox_size'], $inbox_limit_pct);
            break;
        case 'sentbox':
            $l_box_size_status = sprintf($lang['Sentbox_size'], $inbox_limit_pct);
            break;
        case 'savebox':
            $l_box_size_status = sprintf($lang['Savebox_size'], $inbox_limit_pct);
            break;
        default:
            $l_box_size_status = '';
            break;
    }
} else {
	$inbox_limit_img_length = $inbox_limit_pct = $l_box_size_status = '';
}

//
// Dump vars to template
//
$template->assignVars(
    [
        'BOX_NAME'    => $l_box_name,
        'INBOX_IMG'   => $inboxImage,
        'SENTBOX_IMG' => $sentBoxImage,
        'OUTBOX_IMG'  => $outBoxImage,
        'SAVEBOX_IMG' => $saveBoxImage,
        'INBOX'       => $inboxUrl,
        'SENTBOX'     => $sentBoxUrl,
        'OUTBOX'      => $outBoxUrl,
        'SAVEBOX'     => $saveBoxUrl,

        'POST_PM_IMG' => $post_pm_img,
        'POST_PM'     => $post_pm,

        'INBOX_LIMIT_IMG_WIDTH' => $inbox_limit_img_length,
        'INBOX_LIMIT_PERCENT'   => $inbox_limit_pct,

        'BOX_SIZE_STATUS' => $l_box_size_status,

        'L_INBOX'            => $lang['Inbox'],
        'L_OUTBOX'           => $lang['Outbox'],
        'L_SENTBOX'          => $lang['Sent'],
        'L_SAVEBOX'          => $lang['Saved'],
        'L_MARK'             => $lang['Mark'],
        'L_FLAG'             => $lang['Flag'],
        'L_SUBJECT'          => $lang['Subject'],
        'L_DATE'             => $lang['Date'],
        'L_DISPLAY_MESSAGES' => $lang['Display_messages'],
        'L_FROM_OR_TO'       => $folder === 'inbox' || $folder === 'savebox' ? $lang['From'] : $lang['To'],
        'L_MARK_ALL'         => $lang['Mark_all'],
        'L_UNMARK_ALL'       => $lang['Unmark_all'],
        'L_DELETE_MARKED'    => $lang['Delete_marked'],
        'L_DELETE_ALL'       => $lang['Delete_all'],
        'L_SAVE_MARKED'      => $lang['Save_marked'],

        'S_PRIVMSGS_ACTION' => Session::appendSid("privmsg.php?folder=$folder"),
        'S_HIDDEN_FIELDS'   => '',
        'S_POST_NEW_MSG'    => $post_new_mesg_url,
        'S_SELECT_MSG_DAYS' => $select_msg_days,

        'U_POST_NEW_TOPIC' => Session::appendSid('privmsg.php?mode=post')
    ]
);

//
// Okay, let's build the correct folder
//


$rows = $sql->fetchAll();

if (count($rows)) {
	foreach ($rows as $i => $row) {
		$privmsg_id = $row->privmsgs_id;

		$flag = $row->privmsgs_type;

		$icon_flag = $flag === PRIVMSGS_NEW_MAIL || $flag === PRIVMSGS_UNREAD_MAIL ? $images['pm_unreadmsg'] : $images['pm_readmsg'];
		$icon_flag_alt = $flag === PRIVMSGS_NEW_MAIL || $flag === PRIVMSGS_UNREAD_MAIL ? $lang['Unread_message'] : $lang['Read_message'];

		$msg_userid = $row->user_id;
		$msg_username = $row->username;

        // <!-- BEGIN Another Online/Offline indicator -->
        if ((!$row->user_allow_view_online && $userdata['user_level'] === ADMIN) || $row->user_allow_view_online) {
            $expiry_time = time() - ONLINE_TIME_DIFF;

            if ($row->user_session_time >= $expiry_time) {
                $user_onlinestatus = '<img src="' . $images['Online_small'] . '" alt="' . $lang['Online'] . '" title="' . $lang['Online'] . '" border="0" />';

                if (!$row->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Hidden_Admin_small'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                }
            } else {
                $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';

                if (!$row->user_allow_view_online && $userdata['user_level'] === ADMIN) {
                    $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Hidden'] . '" title="' . $lang['Hidden'] . '" border="0" />';
                }
            }
        } else {
            $user_onlinestatus = '<img src="' . $images['Offline_small'] . '" alt="' . $lang['Offline'] . '" title="' . $lang['Offline'] . '" border="0" />';
        }
        // <!-- END Another Online/Offline indicator -->

		$u_from_user_profile = Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . "=$msg_userid");

		$msg_subject = $row->privmsgs_subject;

        if (count($orig_word)) {
            $msg_subject = preg_replace($orig_word, $replacement_word, $msg_subject);
        }
		
		$u_subject = Session::appendSid("privmsg.php?folder=$folder&amp;mode=read&amp;" . POST_POST_URL . "=$privmsg_id");

		$msg_date = create_date($board_config['default_dateformat'], $row->privmsgs_date, $board_config['board_timezone']);

        if ($flag === PRIVMSGS_NEW_MAIL && $folder === 'inbox') {
            $msg_subject  = '<b>' . $msg_subject . '</b>';
            $msg_date     = '<b>' . $msg_date . '</b>';
            $msg_username = '<b>' . $msg_username . '</b>';
        }

		$rowColor = ($i % 2) ? $theme['td_color1'] : $theme['td_color2'];
		$rowClass = ($i % 2) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assignBlockVars('listrow',
            [
                'ROW_COLOR'          => '#' . $rowColor,
                'ROW_CLASS'          => $rowClass,
                'FROM'               => $msg_username . '&nbsp;' . $user_onlinestatus,
                'SUBJECT'            => htmlspecialchars($msg_subject, ENT_QUOTES),
                'DATE'               => $msg_date,
                'PRIVMSG_ATTACHMENTS_IMG' => privmsgs_attachment_image($privmsg_id),
                'PRIVMSG_FOLDER_IMG' => $icon_flag,

                'L_PRIVMSG_FOLDER_ALT' => $icon_flag_alt,

                'S_MARK_ID' => $privmsg_id,

                'U_READ'              => $u_subject,
                'U_FROM_USER_PROFILE' => $u_from_user_profile
            ]
        );
    }

    $template->assignVars(
        [
            'PAGINATION'  => generate_pagination("privmsg.php?folder=$folder", $pm_total, $board_config['pm_per_page'], $start),
            'PAGE_NUMBER' => sprintf($lang['Page_of'], floor($start / $board_config['pm_per_page']) + 1, ceil($pm_total / $board_config['pm_per_page'])),

            'L_GOTO_PAGE' => $lang['Goto_page']
        ]
    );

} else {
    $template->assignVars(['L_NO_MESSAGES' => $lang['No_messages_folder']]);
    $template->assignBlockVars('switch_no_messages', [] );
}

$template->pparse('body');

PageHelper::footer($template, $userdata, $lang, $gen_simple_header);

?>