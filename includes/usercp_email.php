<?php
/***************************************************************************
 *                             usercp_email.php 
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: usercp_email.php 6772 2006-12-16 13:11:28Z acydburn $
 *
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

if ( !defined('IN_PHPBB') ) {
	die('Hacking attempt');
}

// Is send through board enabled? No, return to index
if (!$board_config['board_email_form']) {
	redirect(Session::appendSid('index.php', true));
}

if (!empty($_GET[POST_USERS_URL]) || !empty($_POST[POST_USERS_URL]))  {
    $user_id = !empty($_GET[POST_USERS_URL]) ? (int)$_GET[POST_USERS_URL] : (int)$_POST[POST_USERS_URL];
} else {
	message_die(GENERAL_MESSAGE, $lang['No_user_specified']);
}

if ( !$userdata['session_logged_in'] ) {
	redirect(Session::appendSid('login.php?redirect=profile.php&mode=email&' . POST_USERS_URL . "=$user_id", true));
}

$row = dibi::select(['username', 'user_email', 'user_viewemail', 'user_lang'])
    ->from(USERS_TABLE)
    ->where('user_id = %i', $user_id)
    ->fetch();

if (!$row) {
    message_die(GENERAL_MESSAGE, $lang['User_not_exist']);
}

$username   = $row->username;
$user_email = $row->user_email;
$user_lang  = $row->user_lang;

if ($row->user_viewemail || $userdata['user_level'] === ADMIN) {
    if (time() - $userdata['user_emailtime'] < $board_config['flood_interval']) {
        message_die(GENERAL_MESSAGE, $lang['Flood_email_limit']);
    }

    if (isset($_POST['submit'])) {
        $error = false;

        if (empty($_POST['subject'])) {
            $error     = true;
            $error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Empty_subject_email'] : $lang['Empty_subject_email'];
        } else {
            $subject = trim(stripslashes($_POST['subject']));
        }

        if (empty($_POST['message'])) {
            $error     = true;
            $error_msg = !empty($error_msg) ? $error_msg . '<br />' . $lang['Empty_message_email'] : $lang['Empty_message_email'];
        } else {
            $message = trim(stripslashes($_POST['message']));
        }

        if (!$error) {
            $result = dibi::update(USERS_TABLE, ['user_emailtime' => time()])
                ->where('user_id = %i', $userdata['user_id'])
                ->execute();

            if (!$result) {
                message_die(GENERAL_ERROR, 'Could not update last email time');
            }

            include $phpbb_root_path . 'includes/Emailer.php';
            $emailer = new Emailer($board_config['smtp_delivery']);

            $emailer->setFrom($userdata['user_email']);
            $emailer->setReplyTo($userdata['user_email']);

            $email_headers = 'X-AntiAbuse: Board servername - ' . $server_name . "\n";
            $email_headers .= 'X-AntiAbuse: User_id - ' . $userdata['user_id'] . "\n";
            $email_headers .= 'X-AntiAbuse: Username - ' . $userdata['username'] . "\n";
            $email_headers .= 'X-AntiAbuse: User IP - ' . decode_ip($user_ip) . "\n";

            $emailer->use_template('profile_send_email', $user_lang);
            $emailer->setEmailAddress($user_email);
            $emailer->setSubject($subject);
            $emailer->addExtraHeaders($email_headers);

            $emailer->assignVars(
                [
                    'SITENAME'      => $board_config['sitename'],
                    'BOARD_EMAIL'   => $board_config['board_email'],
                    'FROM_USERNAME' => $userdata['username'],
                    'TO_USERNAME'   => $username,
                    'MESSAGE'       => $message
                ]
            );
            $emailer->send();
            $emailer->reset();

            if (!empty($_POST['cc_email'])) {
                $emailer->setFrom($userdata['user_email']);
                $emailer->setReplyTo($userdata['user_email']);
                $emailer->use_template('profile_send_email');
                $emailer->setEmailAddress($userdata['user_email']);
                $emailer->setSubject($subject);

                $emailer->assignVars(
                    [
                        'SITENAME'      => $board_config['sitename'],
                        'BOARD_EMAIL'   => $board_config['board_email'],
                        'FROM_USERNAME' => $userdata['username'],
                        'TO_USERNAME'   => $username,
                        'MESSAGE'       => $message
                    ]
                );
                $emailer->send();
                $emailer->reset();
            }

            $template->assign_vars(
                [
                    'META' => '<meta http-equiv="refresh" content="5;url=' . Session::appendSid('index.php') . '">'
                ]
            );

            $message = $lang['Email_sent'] . '<br /><br />' . sprintf($lang['Click_return_index'], '<a href="' . Session::appendSid('index.php') . '">', '</a>');

            message_die(GENERAL_MESSAGE, $message);
        }
    }

    include $phpbb_root_path . 'includes/page_header.php';

    $template->set_filenames(['body' => 'profile_send_email.tpl']);
    make_jumpbox('viewforum.php');

    if ($error) {
        $template->set_filenames(['reg_header' => 'error_body.tpl']);
        $template->assign_vars(['ERROR_MESSAGE' => $error_msg]);
        $template->assign_var_from_handle('ERROR_BOX', 'reg_header');
    }

    $template->assign_vars(
        [
            'USERNAME' => $username,

            'S_HIDDEN_FIELDS' => '',
            'S_POST_ACTION'   => Session::appendSid('profile.php?mode=email&amp;' . POST_USERS_URL . "=$user_id"),

            'L_SEND_EMAIL_MSG'      => $lang['Send_email_msg'],
            'L_RECIPIENT'           => $lang['Recipient'],
            'L_SUBJECT'             => $lang['Subject'],
            'L_MESSAGE_BODY'        => $lang['Message_body'],
            'L_MESSAGE_BODY_DESC'   => $lang['Email_message_desc'],
            'L_EMPTY_SUBJECT_EMAIL' => $lang['Empty_subject_email'],
            'L_EMPTY_MESSAGE_EMAIL' => $lang['Empty_message_email'],
            'L_OPTIONS'             => $lang['Options'],
            'L_CC_EMAIL'            => $lang['CC_email'],
            'L_SPELLCHECK'          => $lang['Spellcheck'],
            'L_SEND_EMAIL'          => $lang['Send_email']
        ]
    );

    $template->pparse('body');

    include $phpbb_root_path . 'includes/page_tail.php';
} else {
    message_die(GENERAL_MESSAGE, $lang['User_prevent_email']);
}

?>