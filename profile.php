<?php
/***************************************************************************
 *                                profile.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: profile.php 5777 2006-04-09 16:17:28Z grahamje $
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
$userdata = session_pagestart($user_ip, PAGE_PROFILE);
init_userprefs($userdata);
//
// End session management
//

// session id check
if (!empty($_POST['sid']) || !empty($_GET['sid'])) {
	$sid = !empty($_POST['sid']) ? $_POST['sid'] : $_GET['sid'];
} else {
	$sid = '';
}

//
// Set default email variables
//
$script_name = preg_replace('/^\/?(.*?)\/?$/', '\1', trim($board_config['script_path']));
$script_name = ( $script_name !== '' ) ? $script_name . '/profile.php' : 'profile.php';
$server_name = trim($board_config['server_name']);
$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
$server_port = ( $board_config['server_port'] <> 80 ) ? ':' . trim($board_config['server_port']) . '/' : '/';

$server_url = $server_protocol . $server_name . $server_port . $script_name;

// -----------------------
// Page specific functions
//
function gen_rand_string($hash)
{
	$rand_str = dss_rand();

	return $hash ? md5($rand_str) : substr($rand_str, 0, 8);
}
//
// End page specific functions
// ---------------------------

//
// Start of program proper
//
if (isset($_GET['mode']) || isset($_POST['mode'])) {
    $mode = isset($_GET['mode']) ? $_GET['mode'] : $_POST['mode'];
    $mode = htmlspecialchars($mode);

    if ($mode === 'viewprofile') {
        include $phpbb_root_path . 'includes/usercp_viewprofile.php';
        exit;
    } elseif ($mode === 'editprofile' || $mode === 'register') {
        if (!$userdata['session_logged_in'] && $mode === 'editprofile') {
            redirect(append_sid("login.php?redirect=profile.php&mode=editprofile", true));
        }

        include $phpbb_root_path . 'includes/usercp_register.php';
        exit;
    } elseif ($mode === 'confirm') {
        // Visual Confirmation
        if ($userdata['session_logged_in']) {
            exit;
        }

        include $phpbb_root_path . 'includes/usercp_confirm.php';
        exit;
    } elseif ($mode === 'sendpassword') {
        include $phpbb_root_path . 'includes/usercp_sendpasswd.php';
        exit;
    } elseif ($mode === 'activate') {
        include $phpbb_root_path . 'includes/usercp_activate.php';
        exit;
    } elseif ($mode === 'email') {
        include $phpbb_root_path . 'includes/usercp_email.php';
        exit;
    }
}

redirect(append_sid("index.php", true));

?>