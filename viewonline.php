<?php
/***************************************************************************
 *                              viewonline.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: viewonline.php 5142 2005-05-06 20:50:13Z acydburn $
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
 ***************************************************************************/

define('IN_PHPBB', true);
$phpbb_root_path = './';

include $phpbb_root_path . 'common.php';

//
// Start session management
//
$userdata = session_pagestart($user_ip, PAGE_VIEWONLINE);
init_userprefs($userdata);
//
// End session management
//

//
// Output page header and load viewonline template
//
$page_title = $lang['Who_is_Online'];
include $phpbb_root_path . 'includes/page_header.php';

$template->set_filenames(
    [
	   'body' => 'viewonline_body.tpl'        
    ]
);
make_jumpbox('viewforum.php');

$template->assign_vars(
    [
        'L_WHOSONLINE'     => $lang['Who_is_Online'],
        'L_ONLINE_EXPLAIN' => $lang['Online_explain'],
        'L_USERNAME'       => $lang['Username'],
        'L_FORUM_LOCATION' => $lang['Forum_Location'],
        'L_LAST_UPDATE'    => $lang['Last_updated']
    ]
);

//
// Forum info
//
$forum_data = dibi::select(['forum_id', 'forum_name'])
    ->from(FORUMS_TABLE)
    ->fetchPairs('forum_id', 'forum_name');

//
// Get auth data
//
$is_auth_ary = [];
$is_auth_ary = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

//
// Get user list
//
$columns = ['u.user_id', 'u.username', 'u.user_allow_viewonline', 'u.user_level', 's.session_logged_in', 's.session_time', 's.session_page', 's.session_ip'];

$rows = dibi::select($columns)
    ->from(USERS_TABLE)
    ->as('u')
    ->from(SESSIONS_TABLE)
    ->as('s')
    ->where('u.user_id = s.session_user_id')
    ->where('s.session_time >= %i', time() - 30)
    ->orderBy('u.username', dibi::ASC)
    ->orderBy('s.session_ip', dibi::ASC)
    ->fetchAll();

if ( !($result = $db->sql_query($sql)) ) {
	message_die(GENERAL_ERROR, 'Could not obtain regd user/online information', '', __LINE__, __FILE__, $sql);
}

$guest_users = 0;
$registered_users = 0;
$hidden_users = 0;

$reg_counter = 0;
$guest_counter = 0;
$prev_user = 0;
$prev_ip = '';

foreach ($rows as $row) {
	$view_online = false;

	if ( $row->session_logged_in )  {
		$user_id = $row->user_id;

		if ( $user_id != $prev_user ) {
			$username = $row->username;

			$style_color = '';
			if ( $row->user_level == ADMIN ) {
				$username = '<b style="color:#' . $theme['fontcolor3'] . '">' . $username . '</b>';
			} elseif ( $row->user_level == MOD ) {
				$username = '<b style="color:#' . $theme['fontcolor2'] . '">' . $username . '</b>';
			}

            if (!$row->user_allow_viewonline) {
				$view_online = ( $userdata['user_level'] == ADMIN ) ? true : false;
				$hidden_users++;

				$username = '<i>' . $username . '</i>';
			} else {
				$view_online = true;
				$registered_users++;
			}

			$which_counter = 'reg_counter';
			$which_row = 'reg_user_row';
			$prev_user = $user_id;
		}
	}
	else {
		if ( $row->session_ip != $prev_ip ) {
			$username = $lang['Guest'];
			$view_online = true;
			$guest_users++;
	
			$which_counter = 'guest_counter';
			$which_row = 'guest_user_row';
		}
	}

	$prev_ip = $row['session_ip'];

    if ($view_online) {
        if ($row->session_page < 1 || !$is_auth_ary[$row->session_page]['auth_view']) {
            switch ($row->session_page) {
				case PAGE_INDEX:
					$location = $lang['Forum_index'];
					$location_url = "index.php";
					break;
				case PAGE_POSTING:
					$location = $lang['Posting_message'];
					$location_url = "index.pphp";
					break;
				case PAGE_LOGIN:
					$location = $lang['Logging_on'];
					$location_url = "index.php";
					break;
				case PAGE_SEARCH:
					$location = $lang['Searching_forums'];
					$location_url = "search.php";
					break;
				case PAGE_PROFILE:
					$location = $lang['Viewing_profile'];
					$location_url = "index.php";
					break;
				case PAGE_VIEWONLINE:
					$location = $lang['Viewing_online'];
					$location_url = "viewonline.php";
					break;
				case PAGE_VIEWMEMBERS:
					$location = $lang['Viewing_member_list'];
					$location_url = "memberlist.php";
					break;
				case PAGE_PRIVMSGS:
					$location = $lang['Viewing_priv_msgs'];
					$location_url = "privmsg.php";
					break;
				case PAGE_FAQ:
					$location = $lang['Viewing_FAQ'];
					$location_url = "faq.php";
					break;
				default:
					$location = $lang['Forum_index'];
					$location_url = "index.php";
			}
		} else {
			$location_url = append_sid("viewforum.php?" . POST_FORUM_URL . '=' . $row->session_page);
			$location = $forum_data[$row->session_page];
		}

		$row_color = ( $$which_counter % 2 ) ? $theme['td_color1'] : $theme['td_color2'];
		$row_class = ( $$which_counter % 2 ) ? $theme['td_class1'] : $theme['td_class2'];

        $template->assign_block_vars((string)$which_row,
            [
                'ROW_COLOR'      => '#' . $row_color,
                'ROW_CLASS'      => $row_class,
                'USERNAME'       => $username,
                'LASTUPDATE'     => create_date($board_config['default_dateformat'], $row->session_time, $board_config['board_timezone']),
                'FORUM_LOCATION' => $location,

                'U_USER_PROFILE'   => append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . '=' . $user_id),
                'U_FORUM_LOCATION' => append_sid($location_url)
            ]
        );

        $$which_counter++;
	}
}

if ($registered_users == 0) {
    $l_r_user_s = $lang['Reg_users_zero_online'];
} elseif ($registered_users == 1) {
    $l_r_user_s = $lang['Reg_user_online'];
} else {
    $l_r_user_s = $lang['Reg_users_online'];
}

if ($hidden_users == 0) {
    $l_h_user_s = $lang['Hidden_users_zero_online'];
} elseif ($hidden_users == 1) {
    $l_h_user_s = $lang['Hidden_user_online'];
} else {
    $l_h_user_s = $lang['Hidden_users_online'];
}

if ($guest_users == 0) {
    $l_g_user_s = $lang['Guest_users_zero_online'];
} elseif ($guest_users == 1) {
    $l_g_user_s = $lang['Guest_user_online'];
} else {
    $l_g_user_s = $lang['Guest_users_online'];
}

$template->assign_vars(
    [
        'TOTAL_REGISTERED_USERS_ONLINE' => sprintf($l_r_user_s, $registered_users) . sprintf($l_h_user_s, $hidden_users),
        'TOTAL_GUEST_USERS_ONLINE'      => sprintf($l_g_user_s, $guest_users)
    ]
);

if ($registered_users + $hidden_users == 0) {
    $template->assign_vars(['L_NO_REGISTERED_USERS_BROWSING' => $lang['No_users_browsing']]);
}

if ($guest_users == 0) {
    $template->assign_vars(['L_NO_GUESTS_BROWSING' => $lang['No_users_browsing']]);
}

$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>