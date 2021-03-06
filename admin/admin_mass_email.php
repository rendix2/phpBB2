<?php
/***************************************************************************
*                             admin_mass_email.php
*                              -------------------
*     begin                : Thu May 31, 2001
*     copyright            : (C) 2001 The phpBB Group
*     email                : support@phpbb.com
*
*     $Id: admin_mass_email.php 3966 2003-05-03 23:24:04Z acydburn $
*
****************************************************************************/

use phpBB2\Mailer;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

//
// Load default header
//
$no_page_header = true;
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;

require_once '.' . $sep . 'pagestart.php';

//
// Increase maximum execution time in case of a lot of users, but don't complain about it if it isn't
// allowed.
//
@set_time_limit(1200);

$message = '';
$subject = '';

//
// Do the job ...
//
if (isset($_POST['submit'])) {
	$subject = stripslashes(trim($_POST['subject']));
	$message = stripslashes(trim($_POST['message']));

    $error = false;
	$error_msg = '';

    if (empty($subject)) {
		$error = true;
		$error_msg .= !empty($error_msg) ? '<br />' . $lang['Empty_subject'] : $lang['Empty_subject'];
	}

    if (empty($message)) {
		$error = true;
		$error_msg .= !empty($error_msg) ? '<br />' . $lang['Empty_message'] : $lang['Empty_message'];
	}

	$group_id = (int)$_POST[POST_GROUPS_URL];

    if ($group_id !== -1) {
        $bcc_list = dibi::select('u.user_email')
            ->from(Tables::USERS_TABLE)
            ->as('u')
            ->innerJoin(Tables::USERS_GROUPS_TABLE)
            ->as('ug')
            ->on('[u.user_id] = [ug.user_id]')
            ->where('[ug.group_id] = %i', $group_id)
            ->where('[ug.user_pending] <> %i', 1)
            ->fetchPairs(null, 'user_email');
    } else {
        $bbc_list = dibi::select('user_email')
            ->from(Tables::USERS_TABLE)
            ->fetchPairs(null, 'user_email');
    }

    if (!count($bcc_list)) {
        $message = $group_id !== -1 ? $lang['Group_not_exist'] : $lang['No_such_user'];

        $error = true;
        $error_msg .= !empty($error_msg) ? '<br />' . $message : $message;
    }

    if (!$error) {
		//
		// Let's do some checking to make sure that mass mail functions
		// are working in win32 versions of php.
		//
		if (!$board_config['smtp_delivery'] && preg_match('/[c-z]:\\\.*/i', getenv('PATH'))) {
			// We are running on windows, force delivery to use our smtp functions
			// since php's are broken by default
			$board_config['smtp_delivery'] = 1;
			$board_config['smtp_host'] = @ini_get('SMTP');
		}

		$params = [
            'SITENAME'    => $board_config['sitename'],
            'BOARD_EMAIL' => $board_config['board_email'],
            'MESSAGE'     => $message
        ];

        $mailer = new Mailer(
            new LatteFactory($storage, $userdata),
            $board_config,
            'admin_send_email',
            $params,
            null,
            $subject,
            $board_config['board_email'],
        );

		foreach ($bbc_list as $email) {
            $mailer->getMessage()->addBcc($email);
        }

        $mailer->send();

		$message = $lang['Email_sent'] . '<br /><br />';
		$message .= sprintf($lang['Click_return_admin_index'],  '<a href="' . Session::appendSid('index.php?pane=right') . '">', '</a>');

		message_die(GENERAL_MESSAGE, $message);
	}
}

if ($error) {
    $template->setFileNames(['reg_header' => 'error_body.tpl']);
    $template->assignVars(['ERROR_MESSAGE' => $error_msg]);
    $template->assignVarFromHandle('ERROR_BOX', 'reg_header');
}

//
// Initial selection
//

$groups = dibi::select(['group_id', 'group_name'])
    ->from(Tables::GROUPS_TABLE)
    ->where('[group_single_user] <> %i', 1)
    ->fetchPairs('group_id', 'group_name');

$select_list = '<select name = "' . POST_GROUPS_URL . '"><option value = "-1">' . $lang['All_users'] . '</option>';

foreach ($groups as $group_id => $group_name) {
    $select_list .= '<option value = "' . $group_id . '">' . htmlspecialchars($group_name, ENT_QUOTES) . '</option>';
}

$select_list .= '</select>';

//
// Generate page
//
require_once '.' . $sep . 'page_header_admin.php';

$template->setFileNames(['body' => 'admin/user_email_body.tpl']);

$template->assignVars(
    [
        'MESSAGE' => $message,
        'SUBJECT' => $subject,

        'L_EMAIL_TITLE'   => $lang['Email'],
        'L_EMAIL_EXPLAIN' => $lang['Mass_email_explain'],
        'L_COMPOSE'       => $lang['Compose'],
        'L_RECIPIENTS'    => $lang['Recipients'],
        'L_EMAIL_SUBJECT' => $lang['Subject'],
        'L_EMAIL_MSG'     => $lang['Message'],
        'L_EMAIL'         => $lang['Email'],
        'L_NOTICE'        => $notice,

        'S_USER_ACTION'  => Session::appendSid('admin_mass_email.php'),
        'S_GROUP_SELECT' => $select_list
    ]
);

$template->pparse('body');

require_once '.' . $sep . 'page_footer_admin.php';

?>