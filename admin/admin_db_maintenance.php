<?php
/***************************************************************************
 *                         admin_db_maintenance.php
 *                            -------------------
 *   begin                : Fri Feb 07, 2003
 *   copyright            : (C) 2004 Philipp Kordowich
 *                          Parts: (C) 2002 The phpBB Group
 *
 *   part of DB Maintenance Mod 1.3.8
 ***************************************************************************/

use Nette\Caching\Cache;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

define('DBMTNC_VERSION', '1.3.8');
// CONFIG_LEVEL = 0: configuration is disabled
// CONFIG_LEVEL = 1: only general configuration available
// CONFIG_LEVEL = 2: also configuration of rebuilding available
// CONFIG_LEVEL = 3: also configuration of current rebuilding available
define('CONFIG_LEVEL', 2); // Level of configuration available (see above)
define('HEAP_SIZE', 500); // Limit of Heap-Table for session data

//
// Load default header
//
$sep = DIRECTORY_SEPARATOR;
$phpbb_root_path = '.' . $sep . '..' . $sep;
$no_page_header = true; // We do not send the page header right here to prevent problems with GZIP-compression

require_once '.' . $sep . 'pagestart.php';
require_once $phpbb_root_path . 'includes' . $sep . 'functions_dbmtnc.php';

//
// Set up timer
//
$timer = getmicrotime();

//
// Get language file for this mod
//
if (!file_exists(@realpath($phpbb_root_path . $sep . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_dbmtnc.php'))) {
    $board_config['default_lang'] = 'english';
}

require_once $phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_dbmtnc.php';

//
// Set up variables and constants
//
$function = isset($_GET['function']) ? htmlspecialchars(trim($_GET['function'])) : '';
$mode_id  = isset($_GET['mode'])     ? htmlspecialchars(trim($_GET['mode']))     : '';
// Check for parameters

foreach ($config_data as $value) {
    if (!isset($board_config[$value])) {
        message_die(GENERAL_MESSAGE, sprintf($lang['Incomplete_configuration'], $value));
    }
}

//
// Get form-data if specified and override old settings
//
if (isset($_POST['mode']) && $_POST['mode'] === 'perform' && isset($_POST['confirm'])) {
    $mode_id  = 'perform';
    $function = isset($_POST['function']) ? htmlspecialchars(trim($_POST['function'])) : '';
}

//
// Switch of GZIP-compression when necessary and send the page header
//
if ($mode_id === 'start' || $mode_id === 'perform') {
    $board_config['gzip_compress'] = false;
}

if ($function !== 'perform_rebuild') {// Don't send header when rebuilding the search index
    require_once '.' . $sep . 'page_header_admin.php';
}

//
// Check the db-type
//
if (Config::DBMS !== 'mysql') {
	message_die(GENERAL_MESSAGE, $lang['dbtype_not_supported']);
}

switch ($mode_id) {
	case 'start': // Show warning message if specified
		if ($function === '') {
			message_die(GENERAL_ERROR, $lang['no_function_specified']);
		}

		$warning_message_defined = false;

        foreach ($mtnc as $value) {
            if (count($value) && $value[0] === $function) {
                $warning_message = $value;
                $warning_message_defined = true;
            }
        }

        if (!$warning_message_defined) {
            message_die(GENERAL_ERROR, $lang['function_unknown']);
        } elseif ($warning_message[3] !== '') {
			$s_hidden_fields = '<input type="hidden" name="mode" value="perform" />';
			$s_hidden_fields .= '<input type="hidden" name="function" value="' . $function . '" />';

            $template->setFileNames(['body' => 'admin/dbmtnc_confirm_body.tpl']);

            $template->assignVars(
                [
                    'MESSAGE_TITLE' => $warning_message[1],
                    'MESSAGE_TEXT'  => $warning_message[3],

                    'L_YES' => $lang['Yes'],
                    'L_NO'  => $lang['No'],

                    'S_CONFIRM_ACTION' => Session::appendSid('admin_db_maintenance.php'),
                    'S_HIDDEN_FIELDS'  => $s_hidden_fields
                ]
            );

            $template->pparse('body');
			break;
		}
		//
		// We do not exit if no warning message is specified. In this case we will start directly with performing...
		//
	case 'perform': // Execute the commands
		//
		// phpBB-Template System not used here to allow output information directly to the screen
		// Using the font tag will allow to get the gen-class applied :-)
		//
		$list_open = false;

		//
		// Increase maximum execution time, but don't complain about it if it isn't
		// allowed.
		@set_time_limit(120);
		// Switch of buffering - not when rebuilding search index since we still need to add some headers 
		if ($function !== 'perform_rebuild') {
			ob_end_flush();
		}

		switch ($function) {
            case 'statistic': // Statistics
                $template->setFileNames(['body' => 'admin/dbmtnc_statistic_body.tpl']);

                // Get board statistics
                $total_topics = get_db_stat('topiccount');
                $total_posts  = get_db_stat('postcount');
                $total_users  = get_db_stat('usercount');

                $total_deactivated_users = dibi::select('COUNT(user_id) - 1')
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_active] = %i', 0)
                    ->fetchSingle();

                if ($total_deactivated_users === false) {
                    throw_error("Couldn't get statistic data!");
                }

                $total_moderators = dibi::select('COUNT(user_id) - 1')
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_level] = %i', MOD)
                    ->fetchSingle();

                if ($total_moderators === false) {
                    throw_error("Couldn't get statistic data!");
                }

                $total_administrators = dibi::select('COUNT(user_id) - 1')
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_level] = %i', ADMIN)
                    ->fetchSingle();

                if ($total_administrators === false) {
                    throw_error("Couldn't get statistic data!");
                }

				$administrator_names = dibi::select('username')
					->from(Tables::USERS_TABLE)
					->where('[user_level] = %i', ADMIN)
					->where('[user_id] <> %i', ANONYMOUS)
					->orderBy('username')
					->fetchPairs(null, 'username');

                $template->assignVars(
                    [
                        'NUMBER_OF_TOPICS'            => $total_topics,
                        'NUMBER_OF_POSTS'             => $total_posts,
                        'NUMBER_OF_USERS'             => $total_users,
                        'NUMBER_OF_DEACTIVATED_USERS' => $total_deactivated_users,
                        'NUMBER_OF_MODERATORS'        => $total_moderators,
                        'NUMBER_OF_ADMINISTRATORS'    => $total_administrators,
                        'NAMES_OF_ADMINISTRATORS'     => htmlspecialchars(implode(', ', $administrator_names))
                    ]
                );

                // Database statistic
                $stat = get_table_statistic();

                $template->assignBlockVars('db_statistics', []);
                $template->assignVars(
                    [
                        'NUMBER_OF_DB_TABLES'           => $stat['all']['count'],
                        'NUMBER_OF_CORE_DB_TABLES'      => $stat['core']['count'],
                        'NUMBER_OF_ADVANCED_DB_TABLES'  => $stat['advanced']['count'],
                        'NUMBER_OF_DB_RECORDS'          => $stat['all']['records'],
                        'NUMBER_OF_CORE_DB_RECORDS'     => $stat['core']['records'],
                        'NUMBER_OF_ADVANCED_DB_RECORDS' => $stat['advanced']['records'],

                        'SIZE_OF_DB'          => get_formatted_filesize($stat['all']['size']),
                        'SIZE_OF_CORE_DB'     => get_formatted_filesize($stat['core']['size']),
                        'SIZE_OF_ADVANCED_DB' => get_formatted_filesize($stat['advanced']['size'])
                    ]
                );

				// Version information
				$mysql_version = dibi::query('SELECT VERSION() AS mysql_version')->fetchSingle();

                $template->assignVars(
                    [
                        'PHPBB_VERSION' => '2' . $board_config['version'],
                        'MOD_VERSION'   => DBMTNC_VERSION,
                        'PHP_VERSION'   => PHP_VERSION,
                        'MYSQL_VERSION' => $mysql_version,

                        'L_DBMTNC_TITLE'             => $lang['DB_Maintenance'],
                        'L_DBMTNC_SUB_TITLE'         => $lang['Statistic_title'],
                        'L_DB_INFO'                  => $lang['Database_table_info'],
                        'L_BOARD_STATISTIC'          => $lang['Board_statistic'],
                        'L_DB_STATISTIC'             => $lang['Database_statistic'],
                        'L_VERSION_INFO'             => $lang['Version_info'],
                        'L_NUMBER_POSTS'             => $lang['Number_posts'], // from lang_admin.php
                        'L_NUMBER_TOPICS'            => $lang['Number_topics'], // from lang_admin.php
                        'L_NUMBER_USERS'             => $lang['Number_users'], // from lang_admin.php
                        'L_NUMBER_DEACTIVATED_USERS' => $lang['Thereof_deactivated_users'],
                        'L_NUMBER_MODERATORS'        => $lang['Thereof_Moderators'],
                        'L_NUMBER_ADMINISTRATORS'    => $lang['Thereof_Administrators'],
                        'L_NAME_ADMINISTRATORS'      => $lang['Users_with_Admin_Privileges'],
                        'L_NUMBER_DB_TABLES'         => $lang['Number_tables'],
                        'L_NUMBER_DB_RECORDS'        => $lang['Number_records'],
                        'L_DB_SIZE'                  => $lang['DB_size'],
                        'L_THEREOF_PHPBB_CORE'       => $lang['Thereof_phpbb_core'],
                        'L_THEREOF_PHPBB_ADVANCED'   => $lang['Thereof_phpbb_advanced'],
                        'L_BOARD_VERSION'            => $lang['Version_of_board'],
                        'L_MOD_VERSION'              => $lang['Version_of_mod'],
                        'L_PHP_VERSION'              => $lang['Version_of_PHP'],
                        'L_MYSQL_VERSION'            => $lang['Version_of_MySQL']
                    ]
                );

                $template->pparse('body');
				break;
			case 'config': // Configuration
                if (isset($_POST['submit'])) {
					$disallow_postcounter = isset($_POST['disallow_postcounter']) ? (int)$_POST['disallow_postcounter'] : 0;
					$disallow_rebuild = isset($_POST['disallow_rebuild']) ? (int)$_POST['disallow_rebuild'] : 0;
					$rebuildcfg_timelimit = isset($_POST['rebuildcfg_timelimit']) && is_numeric($_POST['rebuildcfg_timelimit']) ? (int)$_POST['rebuildcfg_timelimit'] : 240;
					$rebuildcfg_timeoverwrite = isset($_POST['rebuildcfg_timeoverwrite']) && is_numeric($_POST['rebuildcfg_timeoverwrite']) ? (int)$_POST['rebuildcfg_timeoverwrite'] : 0;
					$rebuildcfg_maxmemory = isset($_POST['rebuildcfg_maxmemory']) && is_numeric($_POST['rebuildcfg_maxmemory']) ? (int)$_POST['rebuildcfg_maxmemory'] : 500;
					$rebuildcfg_minposts = isset($_POST['rebuildcfg_minposts']) && is_numeric($_POST['rebuildcfg_minposts']) ? (int)$_POST['rebuildcfg_minposts'] : 3;
					$rebuildcfg_php3only = isset($_POST['rebuildcfg_php3only']) ? (int)$_POST['rebuildcfg_php3only'] : 0;
					$rebuildcfg_php4pps = isset($_POST['rebuildcfg_php4pps']) && is_numeric($_POST['rebuildcfg_php4pps']) ? (int)$_POST['rebuildcfg_php4pps'] : 8;
					$rebuildcfg_php3pps = isset($_POST['rebuildcfg_php3pps']) && is_numeric($_POST['rebuildcfg_php3pps']) ? (int)$_POST['rebuildcfg_php3pps'] : 1;
					$rebuild_pos = isset($_POST['rebuild_pos']) && is_numeric($_POST['rebuild_pos']) ? (int)$_POST['rebuild_pos'] : -1;
					$rebuild_end = isset($_POST['rebuild_end']) && is_numeric($_POST['rebuild_end']) ? (int)$_POST['rebuild_end'] : 0;

                    switch (CONFIG_LEVEL) {
                        case 3: // Current search config
                            if ($rebuild_end >= 0) {
                                update_config('dbmtnc_rebuild_end', $rebuild_end);
                            }
                            if ($rebuild_pos >= -1) {
                                update_config('dbmtnc_rebuild_pos', $rebuild_pos);
                            }
                        case 2: // Search config
                            if ($rebuildcfg_php3pps > 0) {
                                update_config('dbmtnc_rebuildcfg_php3pps', $rebuildcfg_php3pps);
                            }
                            if ($rebuildcfg_php4pps > 0) {
                                update_config('dbmtnc_rebuildcfg_php4pps', $rebuildcfg_php4pps);
                            }
                            if ($rebuildcfg_php3only >= 0 && $rebuildcfg_php3only <= 1) {
                                update_config('dbmtnc_rebuildcfg_php3only', $rebuildcfg_php3only);
                            }
                            if ($rebuildcfg_minposts > 0) {
                                update_config('dbmtnc_rebuildcfg_minposts', $rebuildcfg_minposts);
                            }
                            if ($rebuildcfg_maxmemory >= 0) {
                                update_config('dbmtnc_rebuildcfg_maxmemory', $rebuildcfg_maxmemory);
                            }
                            if ($rebuildcfg_timeoverwrite >= 0) {
                                update_config('dbmtnc_rebuildcfg_timeoverwrite', $rebuildcfg_timeoverwrite);
                            }
                            if ($rebuildcfg_timelimit >= 0) {
                                update_config('dbmtnc_rebuildcfg_timelimit', $rebuildcfg_timelimit);
                            }
                        case 1: // DBMTNC config
                            if ($disallow_rebuild >= 0 && $disallow_rebuild <= 1) {
                                update_config('dbmtnc_disallow_rebuild', $disallow_rebuild);
                            }
                            if ($disallow_postcounter >= 0 && $disallow_postcounter <= 1) {
                                update_config('dbmtnc_disallow_postcounter', $disallow_postcounter);
                            }
                    }
					$message  = $lang['Dbmtnc_config_updated'] . '<br /><br />';
                    $message .= sprintf($lang['Click_return_dbmtnc_config'], '<a href="' . Session::appendSid('admin_db_maintenance.php?mode=start&function=config') . '">', '</a>');

					message_die(GENERAL_MESSAGE, $message);
				}

                $template->setFileNames(['body' => 'admin/dbmtnc_config_body.tpl']);

                $template->assignVars(
                    [
                        'S_CONFIG_ACTION' => Session::appendSid('admin_db_maintenance.php?mode=start&function=config'),

                        'L_DBMTNC_TITLE'                    => $lang['DB_Maintenance'],
                        'L_DBMTNC_SUB_TITLE'                => $lang['Config_title'],
                        'L_CONFIG_INFO'                     => $lang['Config_info'],
                        'L_GENERAL_CONFIG'                  => $lang['General_Config'],
                        'L_REBUILD_CONFIG'                  => $lang['Rebuild_Config'],
                        'L_CURRENTREBUILD_CONFIG'           => $lang['Current_Rebuild_Config'],
                        'L_REBUILD_SETTINGS_EXPLAIN'        => $lang['Rebuild_Settings_Explain'],
                        'L_CURRENTREBUILD_SETTINGS_EXPLAIN' => $lang['Current_Rebuild_Settings_Explain'],

                        'L_DISALLOW_POSTCOUNTER'         => $lang['Disallow_postcounter'],
                        'L_DISALLOW_POSTCOUNTER_EXPLAIN' => $lang['Disallow_postcounter_Explain'],

                        'L_DISALLOW_REBUILD'         => $lang['Disallow_rebuild'],
                        'L_DISALLOW_REBUILD_EXPLAIN' => $lang['Disallow_rebuild_Explain'],

                        'L_REBUILDCFG_TIMELIMIT'         => $lang['Rebuildcfg_Timelimit'],
                        'L_REBUILDCFG_TIMELIMIT_EXPLAIN' => $lang['Rebuildcfg_Timelimit_Explain'],

                        'L_REBUILDCFG_TIMEOVERWRITE'         => $lang['Rebuildcfg_Timeoverwrite'],
                        'L_REBUILDCFG_TIMEOVERWRITE_EXPLAIN' => $lang['Rebuildcfg_Timeoverwrite_Explain'],

                        'L_REBUILDCFG_MAXMEMORY'         => $lang['Rebuildcfg_Maxmemory'],
                        'L_REBUILDCFG_MAXMEMORY_EXPLAIN' => $lang['Rebuildcfg_Maxmemory_Explain'],

                        'L_REBUILDCFG_MINPOSTS'         => $lang['Rebuildcfg_Minposts'],
                        'L_REBUILDCFG_MINPOSTS_EXPLAIN' => $lang['Rebuildcfg_Minposts_Explain'],

                        'L_REBUILDCFG_PHP3ONLY'         => $lang['Rebuildcfg_PHP3Only'],
                        'L_REBUILDCFG_PHP3ONLY_EXPLAIN' => $lang['Rebuildcfg_PHP3Only_Explain'],

                        'L_REBUILDCFG_PHP4PPS'         => $lang['Rebuildcfg_PHP4PPS'],
                        'L_REBUILDCFG_PHP4PPS_EXPLAIN' => $lang['Rebuildcfg_PHP4PPS_Explain'],

                        'L_REBUILDCFG_PHP3PPS'         => $lang['Rebuildcfg_PHP3PPS'],
                        'L_REBUILDCFG_PHP3PPS_EXPLAIN' => $lang['Rebuildcfg_PHP3PPS_Explain'],

                        'L_REBUILD_POS'         => $lang['Rebuild_Pos'],
                        'L_REBUILD_POS_EXPLAIN' => $lang['Rebuild_Pos_Explain'],

                        'L_REBUILD_END'         => $lang['Rebuild_End'],
                        'L_REBUILD_END_EXPLAIN' => $lang['Rebuild_End_Explain'],

                        'L_YES'    => $lang['Yes'],
                        'L_NO'     => $lang['No'],
                        'L_SUBMIT' => $lang['Submit'],
                        'L_RESET'  => $lang['Reset'],

                        'DISALLOW_POSTCOUNTER_YES' => $board_config['dbmtnc_disallow_postcounter'] ? 'checked="checked"' : '',
                        'DISALLOW_POSTCOUNTER_NO'  => !$board_config['dbmtnc_disallow_postcounter'] ? 'checked="checked"' : '',
                        'DISALLOW_REBUILD_YES'     => $board_config['dbmtnc_disallow_rebuild'] ? 'checked="checked"' : '',
                        'DISALLOW_REBUILD_NO'      => !$board_config['dbmtnc_disallow_rebuild'] ? 'checked="checked"' : '',
                        'REBUILDCFG_TIMELIMIT'     => (int)$board_config['dbmtnc_rebuildcfg_timelimit'],
                        'REBUILDCFG_MAXMEMORY'     => (int)$board_config['dbmtnc_rebuildcfg_maxmemory'],
                        'REBUILDCFG_TIMEOVERWRITE' => (int)$board_config['dbmtnc_rebuildcfg_timeoverwrite'],
                        'REBUILDCFG_MINPOSTS'      => (int)$board_config['dbmtnc_rebuildcfg_minposts'],
                        'REBUILDCFG_PHP3ONLY_YES'  => $board_config['dbmtnc_rebuildcfg_php3only'] ? 'checked="checked"' : '',
                        'REBUILDCFG_PHP3ONLY_NO'   => !$board_config['dbmtnc_rebuildcfg_php3only'] ? 'checked="checked"' : '',
                        'REBUILDCFG_PHP4PPS'       => (int)$board_config['dbmtnc_rebuildcfg_php4pps'],
                        'REBUILDCFG_PHP3PPS'       => (int)$board_config['dbmtnc_rebuildcfg_php3pps'],
                        'REBUILD_POS'              => (int)$board_config['dbmtnc_rebuild_pos'],
                        'REBUILD_END'              => (int)$board_config['dbmtnc_rebuild_end']
                    ]
                );

                // Display of vonfiguration dependend on settings
                if (CONFIG_LEVEL >= 2) {
                    $template->assignBlockVars('rebuild_settings', []);
                }
                if (CONFIG_LEVEL >= 3) {
                    $template->assignBlockVars('currentrebuild_settings', []);
                }

                $template->pparse('body');
				break;

            case 'languages':
                echo('<h1>' . $lang['Checking_languages'] . "</h1>\n");
                lock_db();

                // Check for missing languages
                echo('<p class="gen"><b>' . $lang['Checking_missing_languages'] . "</b></p>\n");

                $unknownLanguages = dibi::select(['user_id', 'username', 'user_lang'])
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_lang] NOT IN',
                        dibi::select('lang_name')
                            ->from(Tables::LANGUAGES_TABLE)
                    )
                    ->where('[user_id] != %i', ANONYMOUS)
                    ->fetchAll();

                if (count($unknownLanguages)) {
                    // Getting default board_language as long as the original one was changed in functions.php
                    $boardLanguage = dibi::select('config_value')
                        ->from(Tables::CONFIG_TABLE)
                        ->where('[config_name] = %s', 'default_lang')
                        ->fetchSingle();

                    if ($boardLanguage === false) {
                        throw_error("Couldn't get config data! Please check your configuration table.");
                    }

                    foreach ($unknownLanguages as $row) {
                        if (!$list_open) {
                            echo('<p class="gen"><b>' . $lang['Updating_user_with_unknown_language'] . ":</b></p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . htmlspecialchars($row->username) . ' (' . $row->user_lang . " => " . $boardLanguage . " ) </li>\n");

                        dibi::update(Tables::USERS_TABLE, ['user_lang' => $boardLanguage])
                            ->where('[user_id] = %i', $row->user_id)
                            ->execute();
                    }

                    echo("</ul></font>\n");
                    $list_open = false;

                } else {
                    echo($lang['Nothing_to_do']);
                }

                lock_db(true);
                
                break;

            case 'thanks': // Check thanks
                echo('<h1>' . $lang['Checking_thanks'] . "</h1>\n");
                lock_db();

                // Check for missing thankers
                echo('<p class="gen"><b>' . $lang['Checking_missing_thankers'] . "</b></p>\n");

                $unknownUsers = dibi::select('user_id')
                    ->from(Tables::THANKS_TABLE)
                    ->where('[user_id] NOT IN',
                        dibi::select('user_id')
                            ->from(Tables::USERS_TABLE)
                    )
                    ->fetchPairs(null, 'user_id');

                $countUnknownUsers = count($unknownUsers);

                if ($countUnknownUsers) {
                    dibi::delete(Tables::THANKS_TABLE)
                        ->where('[user_id] IN %in', $unknownUsers)
                        ->execute();

                    if ($countUnknownUsers === 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Deleting_invalid_thanker'], $unknownUsers[0]) . "</p>\n");
                    } elseif ($countUnknownUsers > 1) {
                        $db_updated = true;

                        $unknownUsersList = implode(', ', $unknownUsers);
                        echo('<p class="gen">' . sprintf($lang['Deleting_invalid_thankers'], $countUnknownUsers, $unknownUsersList) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for missing thanked topics
                echo('<p class="gen"><b>' . $lang['Checking_missing_thank_topic'] . "</b></p>\n");

                $unknownTopics = dibi::select('topic_id')
                    ->from(Tables::THANKS_TABLE)
                    ->where('[topic_id] NOT IN',
                        dibi::select('topic_id')
                            ->from(Tables::TOPICS_TABLE)
                    )
                    ->fetchPairs(null, 'topic_id');

                $countUnknownTopics = count($unknownTopics);

                if ($countUnknownTopics) {
                    dibi::delete(Tables::THANKS_TABLE)
                        ->where('[topic_id] IN %in', $unknownTopics)
                        ->execute();

                    if ($countUnknownTopics === 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Deleting_invalid_thank_topic'], $unknownTopics[0]) . "</p>\n");
                    } elseif ($countUnknownTopics > 1) {
                        $db_updated = true;

                        $unknownTopicsList = implode(', ', $unknownTopics);
                        echo('<p class="gen">' . sprintf($lang['Deleting_invalid_thank_topics'], $countUnknownTopics, $unknownTopicsList) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

                lock_db(true);

                break;
			case 'check_user': // Check user tables
				echo('<h1>' . $lang['Checking_user_tables'] . "</h1>\n");
				lock_db();

				// Check for missing anonymous user
				echo('<p class="gen"><b>' . $lang['Checking_missing_anonymous'] . "</b></p>\n");

				$checkAnonymous = dibi::select('user_id')
					->from(Tables::USERS_TABLE)
					->where('[user_id] = %i', ANONYMOUS)
					->fetchSingle();

                if ($checkAnonymous === ANONYMOUS) {
                    echo($lang['Nothing_to_do']);
                } else {// anonymous user does not exist
                    // Recreate entry

                    $insertData = [
                        'user_id' => ANONYMOUS,
                        'username' => 'Anonymous',
                        'user_level' => 0,
                        'user_reg_date' => 0,
                        'user_password' => '',
                        'user_acp_password' => '',
                        'user_email'    => '',
                        'user_website'  => '',
                        'user_occupation'   => '',
                        'user_from' => '',
                        'user_interests' => '',
                        'user_sig' => '',
                        'user_style' => null,
                        'user_posts' => 0,
                        'user_topics' => 0,
                        'user_thanks' => 0,
                        'user_topic_watches' => 0,
                        'user_attach_sig' => 0,
                        'user_allow_smile' => 1,
                        'user_allow_html' => 1,
                        'user_allow_bbcode' => 1,
                        'user_allow_pm' => 1,
                        'user_notify_pm' => 0,
                        'user_allow_view_online' => 1,
                        'user_rank' => 1,
                        'user_avatar' => '',
                        'user_lang' => '',
                        'user_timezone' => '',
                        'user_date_format' => '',
                        'user_act_key' => '',
                        'user_new_password' => '',
                        'user_notify' => 0,
                        'user_active' => 0
                    ];

                    dibi::insert(Tables::USERS_TABLE, $insertData)->execute();

                    echo('<p class="gen">' . sprintf($lang['Anonymous_recreated'], $affected_rows) . "</p>\n");
                }

				// Update incorrect pending information: either a single user group with pending state or a group with pending state NULL
				echo('<p class="gen"><b>' . $lang['Checking_incorrect_pending_information'] . "</b></p>\n");
				$db_updated = false;

				// Update the cases where user_pending is null (there were some cases reported, so we just do it)
				$affected_rows = dibi::update(Tables::USERS_GROUPS_TABLE, ['user_pending' => 1])
					->where('[user_pending] IS NULL')
					->execute(dibi::AFFECTED_ROWS);

                if ($affected_rows === 1) {
                    $db_updated = true;
                    echo('<p class="gen">' . sprintf($lang['Updating_invalid_pendig_user'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    $db_updated = true;
                    echo('<p class="gen">' . sprintf($lang['Updating_invalid_pendig_users'], $affected_rows) . "</p>\n");
                }

                $result_array = dibi::select('g.group_id')
                    ->from(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->innerJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('[ug.group_id] = [g.group_id]')
                    ->where('[ug.user_pending] = %i', 1)
                    ->where('[g.group_single_user] = %i', 1)
                    ->fetchPairs(null, 'group_id');

                if (count($result_array)) {
                    $db_updated  = true;
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Updating_pending_information'] . ": $record_list</p>\n");

                    dibi::update(Tables::USERS_GROUPS_TABLE, ['user_pending' => 0])
                        ->where('[user_pending] = %i', 0)
                        ->where('[group_id] IN %in', $result_array)
                        ->execute();
                }

                if (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

                // Checking for users without a single user group
                echo('<p class="gen"><b>' . $lang['Checking_missing_user_groups'] . "</b></p>\n");
                $db_updated = false;

                $rows = dibi::select('u.user_id')
                    ->select('SUM(g.group_single_user)')
                    ->as('group_count')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->leftJoin(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->on('[u.user_id] = [ug.user_id]')
                    ->leftJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('[ug.group_id] = [g.group_id]')
                    ->groupBy('u.user_id')
                    ->having('group_count <> 1 OR ISNULL(group_count)')
                    ->fetchAll();

                $missing_groups  = [];
                $multiple_groups = [];

                foreach ($rows as $row) {
                    if ($row->group_count !== 0) {
                        $multiple_groups[] = $row->user_id;
                    }

                    $missing_groups[] = $row->user_id;
                }

                // Check for multiple records
                if (count($multiple_groups)) {
                    $db_updated = true;
                    $record_list = implode(',', $multiple_groups);
                    echo('<p class="gen">' . $lang['Found_multiple_SUG'] . ":</p>\n");
                    echo("<font class=\"gen\"><ul>\n");
                    $list_open = true;
                    echo('<li>' . $lang['Resolving_user_id'] . ": $record_list</li>\n");

                    $result_array = dibi::select('g.group_id')
                        ->from(Tables::USERS_TABLE)
                        ->as('u')
                        ->innerJoin(Tables::USERS_GROUPS_TABLE)
                        ->as('ug')
                        ->on('[u.user_id] = [ug.user_id]')
                        ->innerJoin(Tables::GROUPS_TABLE)
                        ->as('g')
                        ->on('[ug.group_id] = [g.group_id]')
                        ->where('[u.user_id] IN %in', $multiple_groups)
                        ->where('[g.group_single_user] = %i', 1)
                        ->fetchPairs(null, 'group_id');

                    $record_list = implode(',', $result_array);
                    echo('<li>' . $lang['Removing_groups'] . ": $record_list</li>\n");

                    dibi::delete(Tables::GROUPS_TABLE)
                        ->where('[group_id] IN %in', $result_array)
                        ->execute();

                    echo('<li>' . $lang['Removing_user_groups'] . ": $record_list</li>\n");
                    dibi::delete(Tables::USERS_GROUPS_TABLE)
                        ->where('[group_id] IN %in', $result_array)
                        ->execute();

                    echo("</ul></font>\n");
                    $list_open = false;
				}
				// Create single user groups
                if (count($missing_groups)) {
					$db_updated = true;
					$record_list = implode(',', $missing_groups);
					echo('<p class="gen">' . $lang['Recreating_SUG'] . ": $record_list</p>\n");

					foreach ($missing_groups as $missingGroup) {
						$group_name = $missingGroup === ANONYMOUS ? 'Anonymous' : '';

						$insertData = [
						    'group_type' => 1,
                            'group_name' => $group_name,
                            'group_description' => 'Personal User',
                            'group_moderator' => 0,
                            'group_single_user' => 1
                        ];

                        $group_id = dibi::insert(Tables::GROUPS_TABLE, $insertData)->execute(dibi::IDENTIFIER);

                        $insertData = [
                            'group_id' => $group_id,
                            'user_id'  => $missingGroup,
                            'user_pending' => 0
                        ];

                        dibi::insert(Tables::USERS_GROUPS_TABLE, $insertData)->execute();
                    }
                }

                if (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

                // Check for group moderators who do not exist
                echo('<p class="gen"><b>' . $lang['Checking_for_invalid_moderators'] . "</b></p>\n");

                $rows = dibi::select(['g.group_id', 'g.group_name'])
                    ->from(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[g.group_moderator] = [u.user_id]')
                    ->where('[g.group_single_user] = %i', 0)
                    ->where('([u.user_id] IS NULL OR [u.user_id] = %i)', ANONYMOUS)
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen"><b>' . $lang['Updating_Moderator'] . ":</b></p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . htmlspecialchars($row->group_name) . ' (' . $row->group_id . ")</li>\n");

                    dibi::update(Tables::GROUPS_TABLE, ['group_moderator' => $userdata['user_id']])
                        ->where('[group_id] = %i', $row->group_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check for group moderators who are not member of the group they moderate
                echo('<p class="gen"><b>' . $lang['Checking_moderator_membership'] . "</b></p>\n");
                $rows = dibi::select(['group_id', 'group_name', 'group_moderator'])
                    ->from(Tables::GROUPS_TABLE)
                    ->where('[group_single_user] = %i', 0)
                    ->fetchAll();

                foreach ($rows as $row) {
                    $row2 = dibi::select('user_pending')
                        ->from(Tables::USERS_GROUPS_TABLE)
                        ->where('[group_id] = %i', $row->group_id)
                        ->where('[user_id] = %i', $row->group_moderator)
                        ->fetch();

                    if (!$row2) {// No record found
                        if (!$list_open) {
                            echo('<p class="gen"><b>' . $lang['Updating_mod_membership'] . ":</b></p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }
                        echo('<li>' . htmlspecialchars($row->group_name) . ' (' . $row->group_id . ') - ' . $lang['Moderator_added'] . "</li>\n");

                        $insertData = [
                            'group_id'     => $row->group_id,
                            'user_id'      => $row->group_moderator,
                            'user_pending' => 0
                        ];

                        dibi::insert(Tables::USERS_GROUPS_TABLE, $insertData)->execute();
                    } elseif ($row2->user_pending === 1) { // Record found but moderator is pending
                        if (!$list_open) {
                            echo('<p class="gen"><b>' . $lang['Updating_mod_membership'] . ":</b></p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }
                        echo('<li>' . htmlspecialchars($row->group_name) . ' (' . $row->group_id . ') - ' . $lang['Moderator_changed_pending'] . "</li>\n");

                        dibi::update(Tables::USERS_GROUPS_TABLE, ['user_pending' => 0])
                            ->where('[group_id] = %i', $row->group_id)
                            ->where('[user_id] = %i', $row->group_moderator)
                            ->execute();
                    }
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Remove user-group data without a valid user
                echo('<p class="gen"><b>' . $lang['Remove_invalid_user_data'] . "</b></p>\n");

                $result_array = dibi::select('ug.user_id')
                    ->from(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[ug.user_id] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->groupBy('ug.user_id')
                    ->fetchPairs(null, 'user_id');

                if (count($result_array)) {
                    $record_list   = implode(',', $result_array);
                    $affected_rows = dibi::delete(Tables::USERS_GROUPS_TABLE)
                        ->where('[user_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Remove groups without any members
                echo('<p class="gen"><b>' . $lang['Remove_empty_groups'] . "</b></p>\n");
                // Since we alread added the moderators to the groups this will only include rests of single user groups. So we don't need to display more information

                $result_array = dibi::select('g.group_id')
                    ->from(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->leftJoin(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->on('[g.group_id] = [ug.group_id]')
                    ->where('[ug.group_id] IS NULL')
                    ->fetchPairs(null, 'group_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);

                    $affected_rows = dibi::delete(Tables::GROUPS_TABLE)
                        ->where('[group_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Remove user-group data without a valid group
				echo('<p class="gen"><b>' . $lang['Remove_invalid_group_data'] . "</b></p>\n");

                $result_array = dibi::select('ug.group_id')
                    ->from(Tables::USERS_GROUPS_TABLE)
                    ->as('ug')
                    ->leftJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('[ug.group_id] = [g.group_id]')
                    ->where('[g.group_id] IS NULL')
                    ->groupBy('ug.group_id')
                    ->fetchPairs(null, 'group_id');

                if (count($result_array)) {
                    $affected_rows = dibi::delete(Tables::USERS_GROUPS_TABLE)
                        ->where('[group_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking for invalid ranks
				echo('<p class="gen"><b>' . $lang['Checking_ranks'] . "</b></p>\n");

                $rows = dibi::select(['u.user_id', 'u.username'])
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->leftJoin(Tables::RANKS_TABLE)
                    ->as('r')
                    ->on('[u.user_rank] = [r.rank_id]')
                    ->where('[r.rank_id] IS NULL')
                    ->where('[u.user_rank] <> %i', 0)
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Invalid_ranks_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . htmlspecialchars($row->username) . ' (' . $row->user_id . ")</li>\n");
                    $result_array[] = $row->user_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    echo('<p class="gen">' . $lang['Removing_invalid_ranks'] . "</p>\n");
                    $record_list = implode(',', $result_array);

                    dibi::update(Tables::USERS_TABLE, ['user_rank' => 0])
                        ->where('[user_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking for invalid themes
                echo('<p class="gen"><b>' . $lang['Checking_themes'] . "</b></p>\n");
                $rows = dibi::select('u.user_style')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->leftJoin(Tables::THEMES_TABLE)
                    ->as('t')
                    ->on('[u.user_style] = [t.themes_id]')
                    ->where('[t.themes_id] IS NULL')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->groupBy('u.user_style')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if ($row->user_style === '' || $row->user_style === null) {
                        // At least one style is NULL, so change these records
                        echo('<p class="gen">' . $lang['Updating_users_without_style'] . "</p>\n");

                        dibi::update(Tables::USERS_TABLE, ['user_style' => 0])
                            ->where('[user_style] IS NULL')
                            ->where('[user_id] <> %i', ANONYMOUS)
                            ->execute();

                        $result_array[] = 0;
                    } else {
                        $result_array[] = $row->user_style;
                    }
                }

                if (count($result_array)) {
                    $new_style   = 0;
                    $record_list = implode(',', $result_array);

                    $new_style = dibi::select('themes_id')
                        ->from(Tables::THEMES_TABLE)
                        ->where('[themes_id] = %i', $board_config['default_style'])
                        ->fetchSingle();

                    // the default template is not available
                    if ($new_style === false) {
                        echo('<p class="gen">' . $lang['Default_theme_invalid'] . "</p>\n");

                        $new_style = dibi::select('themes_id')
                            ->from(Tables::THEMES_TABLE)
                            ->where('[themes_id] = %i', $userdata['user_style'])
                            ->fetchSingle();

                        // We never should get to this point. If both the board and the user style is invalid, I
                        // don't know how someone should get to this point
                        if ($new_style === false) {
                            throw_error('Fatal theme error!');
                        }
                    }

                    echo('<p class="gen">' . sprintf($lang['Updating_themes'], $new_style) . "...</p>\n");

                    dibi::update(Tables::USERS_TABLE, ['user_style' => $new_style])
                        ->where('[user_style] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking for invalid theme names data
				echo('<p class="gen"><b>' . $lang['Checking_theme_names'] . "</b></p>\n");

                $result_array = dibi::select('tn.themes_id')
                    ->from(Tables::THEMES_NAME_TABLE)
                    ->as('tn')
                    ->leftJoin(Tables::THEMES_TABLE)
                    ->as('t')
                    ->on('[tn.themes_id] = [t.themes_id]')
                    ->where('[t.themes_id] IS NULL')
                    ->fetchPairs(null, 'themes_id');

                if (count($result_array)) {
                    echo('<p class="gen">' . $lang['Removing_invalid_theme_names'] . "</p>\n");
                    $record_list = implode(',', $result_array);

                    $affected_rows = dibi::delete(Tables::THEMES_NAME_TABLE)
                        ->where('[themes_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Checking for invalid languages
                echo('<p class="gen"><b>' . $lang['Checking_languages'] . "</b></p>\n");

                $tmp_array = dibi::select('user_lang')
                    ->from(Tables::USERS_TABLE)
                    ->where('[user_id] <> %i', ANONYMOUS)
                    ->groupBy('user_lang')
                    ->fetchPairs(null, 'user_lang');

                $result_array = [];

                foreach ($tmp_array as $userLang) {
                    if (!file_exists(@realpath($phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_' . $userLang . $sep . 'lang_main.php'))) {
                        $result_array[] = $userLang;
                    }
                }

                if (count($result_array)) {
                    // Getting default board_language as long as the original one was changed in functions.php
                    $boardLanguage = dibi::select('config_value')
                        ->from(Tables::CONFIG_TABLE)
                        ->where('[config_name] = %s', 'default_lang')
                        ->fetchSingle();

                    if ($boardLanguage === false) {
                        throw_error("Couldn't get config data! Please check your configuration table.");
                    }

                    // Getting default language
                    if (file_exists(@realpath($phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_' . $boardLanguage . $sep . 'lang_main.php'))) {
                        $default_lang = $boardLanguage;
                    } elseif (file_exists(@realpath($phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_' . $userdata['user_lang'] . $sep . 'lang_main.php'))) {
                        echo('<p class="gen">' . $lang['Default_language_invalid'] . "</p>\n");
                        $default_lang = $userdata['user_lang'];
                    } elseif (file_exists(@realpath($phpbb_root_path . 'app' . $sep . 'language' . $sep . 'lang_english' . $sep . 'lang_main.php'))) {
                        echo('<p class="gen">' . $lang['Default_language_invalid'] . "</p>\n");
                        $default_lang = 'english';
                    } else {
                        echo('<p class="gen">' . $lang['English_language_invalid'] . "</p>\n");
                        $default_lang = 'english';
                    }

                    echo('<p class="gen">' . $lang['Invalid_languages_found'] . ":</p>\n");
                    echo("<font class=\"gen\"><ul>\n");
                    $list_open = true;

                    foreach ($result_array as $value) {
                        if ($value === null) {
                            echo('<li>' . sprintf($lang['Changing_language'], 'NULL', $default_lang) . "</li>\n");
                            dibi::update(Tables::USERS_TABLE, ['user_lang' => $default_lang])
                                ->where('[user_lang] IS NULL')
                                ->where('[user_id] <> %i', ANONYMOUS)
                                ->execute();
                        } else {
                            echo('<li>' . sprintf($lang['Changing_language'], $value, $default_lang) . "</li>\n");

                            dibi::update(Tables::USERS_TABLE, ['user_lang' => $default_lang])
                                ->where('[user_lang] = %s', $value)
                                ->where('[user_id] <> %i', ANONYMOUS)
                                ->execute();
                        }
                    }

                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Remove ban data without a valid user
                echo('<p class="gen"><b>' . $lang['Remove_invalid_ban_data'] . "</b></p>\n");

                $result_array = dibi::select('b.ban_user_id')
                    ->from(Tables::BAN_LIST_TABLE)
                    ->as('b')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[b.ban_user_id] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->where('[b.ban_user_id] <> %i', 0)
                    ->where('[b.ban_user_id] IS NOT NULL')
                    ->fetchPairs(null, 'ban_user_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);

                    $affected_rows = dibi::delete(Tables::BAN_LIST_TABLE)
                        ->where('[ban_user_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Remove session key data without valid user
                if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 18) {
                    echo('<p class="gen"><b>' . $lang['Remove_invalid_session_keys'] . "</b></p>\n");

                    $result_array = dibi::select('k.key_id')
                        ->from(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
                        ->as('k')
                        ->leftJoin(Tables::USERS_TABLE)
                        ->as('u')
                        ->on('[k.user_id] = [u.user_id]')
                        ->where('[u.user_id] IS NULL OR [k.user_id] = %i OR [k.last_login] > %i', ANONYMOUS, time())
                        ->fetchPairs(null, 'key_id');

                    if (count($result_array)) {
                        $record_list = '\'' . implode('\',\'', $result_array) . '\'';

                        $affected_rows = dibi::delete(Tables::SESSIONS_AUTO_LOGIN_KEYS_TABLE)
                            ->where('[key_id] IN %in', $result_array)
                            ->execute(dibi::AFFECTED_ROWS);

                        if ($affected_rows === 1) {
                            echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                        } elseif ($affected_rows > 1) {
                            echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                        }
                    } else {
                        echo($lang['Nothing_to_do']);
                    }
                }

                lock_db(true);
				break;
            case 'check_post': // Checks post data
                echo('<h1>' . $lang['Checking_post_tables'] . "</h1>\n");
                $db_state = lock_db();

                // Set a variable to check whether we should update the post data
                $update_post_data = false;

                // Check posts for invaild posters
                echo('<p class="gen"><b>' . $lang['Checking_invalid_posters'] . "</b></p>\n");

                $result_array = dibi::select('p.post_id')
                    ->from(Tables::POSTS_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[p.poster_id] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->fetchPairs(null, 'post_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Invaliyd_poster_found'] . ": $record_list</p>\n");
                    echo('<p class="gen">' . $lang['Updating_posts'] . "</p>\n");

                    dibi::update(Tables::POSTS_TABLE, ['poster_id' => DELETED, 'post_username' => ''])
                        ->where('[post_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check topics for invaild posters
                echo('<p class="gen"><b>' . $lang['Checking_invalid_topic_posters'] . "</b></p>\n");

                $rows = dibi::select(['t.topic_id', 't.topic_poster'])
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[t.topic_poster] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Invalid_topic_poster_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    $poster_id = get_poster($row->topic_id);
                    echo('<li>' . sprintf($lang['Updating_topic'], $row->topic_id, $row->topic_poster, $poster_id) . "</li>\n");

                    dibi::update(Tables::TOPICS_TABLE, ['topic_poster' => $poster_id])
                        ->where('[topic_id] = %i', $row->topic_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for forums with invalid categories
                echo('<p class="gen"><b>' . $lang['Checking_invalid_forums'] . "</b></p>\n");

                $result_array = [];

                $rows = dibi::select(['f.forum_id', 'f.forum_name'])
                    ->from(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->leftJoin(Tables::CATEGORIES_TABLE)
                    ->as('c')
                    ->on('[f.cat_id] = [c.cat_id]')
                    ->where('[c.cat_id] IS NULL')
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Invalid_forums_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . htmlspecialchars($row->forum_name) . ' (' . $row->forum_id . ")</li>\n");
                    $result_array[] = $row->forum_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    $new_cat     = create_cat();
                    echo('<p class="gen">' . sprintf($lang['Setting_category'], $lang['New_cat_name']) . " </p>\n");

                    dibi::update(Tables::FORUMS_TABLE, ['cat_id' => $new_cat])
                        ->where('[forum_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for posts without a text
                echo('<p class="gen"><b>' . $lang['Checking_posts_wo_text'] . "</b></p>\n");

                $rows = dibi::select(['p.post_id', 't.topic_id', 't.topic_title', 'u.user_id', 'u.username'])
                    ->from(Tables::POSTS_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::POSTS_TEXT_TABLE)
                    ->as('pt')
                    ->on('[p.post_id] = [pt.post_id]')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[p.topic_id] = [t.topic_id]')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[p.poster_id] = [u.user_id]')
                    ->where('[pt.post_id] IS NULL')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Posts_wo_text_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Deleting_post_wo_text'], $row->post_id, htmlspecialchars($row->topic_title), $row->topic_id, htmlspecialchars($row->username), $row->user_id) . "</li>\n");

                    $result_array[] = $row->post_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Deleting_Posts'] . " </p>\n");

                    dibi::delete(Tables::POSTS_TABLE)
                        ->where('[post_id] IN %in', $result_array)
                        ->execute();

                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for topics without a post
                echo('<p class="gen"><b>' . $lang['Checking_topics_wo_post'] . "</b></p>\n");

                $rows = dibi::select(['t.topic_id', 't.topic_title'])
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->leftJoin(Tables::POSTS_TABLE)
                    ->as('p')
                    ->on('[t.topic_id] = [p.topic_id]')
                    ->where('[p.topic_id] IS NULL')
                    ->where('[t.topic_status] <> %i', TOPIC_MOVED)
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Topics_wo_post_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . htmlspecialchars($row->topic_title) . ' (' . $row->topic_id . ")</li>\n");
                    $result_array[] = $row->topic_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Deleting_topics'] . " </p>\n");

                    dibi::delete(Tables::TOPICS_TABLE)
                        ->where('[topic_id] IN %in', $result_array)
                        ->execute();

                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for topics with invalid forum
                echo('<p class="gen"><b>' . $lang['Checking_invalid_topics'] . "</b></p>\n");

                $rows = dibi::select(['t.topic_id', 't.topic_title'])
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->on('[t.forum_id] = [f.forum_id]')
                    ->where('[f.forum_id] IS NULL')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Invalid_topics_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . htmlspecialchars($row->topic_title) . ' (' . $row->topic_id . ")</li>\n");
                    $result_array[] = $row->topic_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    $new_forum   = create_forum();
                    echo('<p class="gen">' . sprintf($lang['Setting_forum'], $lang['New_forum_name']) . " </p>\n");

                    dibi::update(Tables::TOPICS_TABLE, ['forum_id' => $new_forum])
                        ->where('[topic_id] IN %in', $result_array)
                        ->execute();

                    dibi::update(Tables::POSTS_TABLE, ['forum_id' => $new_forum])
                        ->where('[topic_id] IN %in', $result_array)
                        ->execute();
                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check for posts with invalid topic
                // new and simplies logic
                // we group posts by topic, its mooore simplier
				echo('<p class="gen"><b>' . $lang['Checking_invalid_posts'] . "</b></p>\n");

                $rows = dibi::select(['p.post_id', 'p.topic_id'])
                    ->from(Tables::POSTS_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[p.topic_id] = [t.topic_id]')
                    ->where('[t.topic_id] IS NULL OR [t.topic_status] = %i', TOPIC_MOVED)
                    ->orderBy('p.topic_id')
                    ->orderBy('p.post_time')
                    ->fetchAll();

                if (count($rows)) {
                    $topics = [];

                    foreach ($rows as $row) {
                        $topics[$row->topic_id][] = $row->post_id;
                    }

                    $result_array = [];
                    $new_forum = create_forum();

                    foreach ($topics as $topicId => $posts) {
                        $last_post_key = count($posts) - 1;
                        $first_post = $posts[0];
                        $last_post = $posts[$last_post_key];

                        $row2 = dibi::select('post_subject')
                            ->from(Tables::POSTS_TEXT_TABLE)
                            ->where('[post_id] = %i', $first_post)
                            ->fetch();

                        if (!$row2) {
                            throw_error("Couldn't get post information!");
                        }

                        $topic_title = $row2->post_subject === '' ? $lang['Restored_topic_name'] : $row2->post_subject;

                        // Get data from first post
                        $firstPost = dibi::select(['poster_id', 'post_time'])
                            ->from(Tables::POSTS_TABLE)
                            ->where('[post_id] = %i', $first_post)
                            ->fetch();

                        if (!$firstPost) {
                            throw_error("Couldn't get post information!");
                        }

                        // Restore topic
                        $insertData = [
                            'forum_id' => $new_forum,
                            'topic_title' => $topic_title,
                            'topic_poster' => $firstPost->poster_id,
                            'topic_time' => $firstPost->post_time,
                            'topic_views' => 0,
                            'topic_replies' => $last_post_key,
                            'topic_status' => TOPIC_UNLOCKED,
                            'topic_vote' => 0,
                            'topic_type' => POST_NORMAL,
                            'topic_moved_id' => 0,
                            'topic_first_post_id' => $first_post,
                            'topic_last_post_id' => $last_post,
                        ];

                        $new_topic = dibi::insert(Tables::TOPICS_TABLE, $insertData)->execute(dibi::IDENTIFIER);
                        $current_topic = $new_topic;

                        echo('<li>' . sprintf($lang['Setting_topic'], implode(', ', $posts), htmlspecialchars($topic_title), $new_topic, $lang['New_forum_name']) . " </li>\n");

                        dibi::update(Tables::POSTS_TABLE, ['forum_id' => $new_forum, 'topic_id' => $new_topic])
                            ->where('[post_id] IN %in', $result_array)
                            ->execute();
                    }
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check for posts with invalid forum
				echo('<p class="gen"><b>' . $lang['Checking_invalid_forums_posts'] . "</b></p>\n");

                $rows = dibi::select(['p.post_id'])
                    ->select('p.forum_id')
                    ->as('p_forum_id')
                    ->select('fp.forum_name')
                    ->as('p_forum_name')
                    ->select('t.forum_id')
                    ->as('t_forum_id')
                    ->select('ft.forum_name')
                    ->as('t_forum_name')
                    ->from(Tables::POSTS_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[p.topic_id] = [t.topic_id]')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('fp')
                    ->on('[p.forum_id] = [fp.forum_id]')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('ft')
                    ->on('[t.forum_id] = [ft.forum_id]')
                    ->where('[p.forum_id] <> [t.forum_id]')
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Invalid_forum_posts_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Setting_post_forum'], $row->post_id, htmlspecialchars($row->p_forum_name), $row->p_forum_id, htmlspecialchars($row->t_forum_name), $row->t_forum_id) . "</li>\n");

                    dibi::update(Tables::POSTS_TABLE, ['forum_id' => $row->t_forum_id])
                        ->where('[post_id] = %i', $row->post_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for texts without a post
                echo('<p class="gen"><b>' . $lang['Checking_texts_wo_post'] . "</b></p>\n");

                $rows = dibi::select(['pt.post_id', 'pt.bbcode_uid', 'pt.post_text'])
                    ->from(Tables::POSTS_TEXT_TABLE)
                    ->as('pt')
                    ->leftJoin(Tables::POSTS_TABLE)
                    ->as('p')
                    ->on('[pt.post_id] = [p.post_id]')
                    ->where('[p.post_id] IS NULL')
                    ->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Invalid_texts_found'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
						$new_forum = create_forum();
						$new_topic = create_topic();
						$enable_html = $board_config['allow_html'];
						$enable_smilies = $board_config['allow_smilies'];
					}
					$enable_bbcode = $board_config['allow_bbcode'] && $row->bbcode_uid !== '' ? 1 : 0;
					echo('<li>' . sprintf($lang['Recreating_post'], $row->post_id, $lang['New_topic_name'], $lang['New_forum_name'], mb_substr(htmlspecialchars(strip_tags($row->post_text)), 0, 30)) . "</li>\n");

					$insertData = [
					    'post_id' => $row->post_id,
                        'topic_id' => $new_topic,
                        'forum_id' => $new_forum,
                        'poster_id' => ANONYMOUS,
                        'post_time' => time(),
                        'poster_ip' => '',
                        'post_username' => $lang['New_poster_name'],
                        'enable_bbcode' => $enable_bbcode,
                        'enable_html' => $enable_html,
                        'enable_smilies' => $enable_smilies,
                        'enable_sig' => 0,
                        'post_edit_time' => null,
                        'post_edit_count' => 0
                    ];

					dibi::insert(Tables::POSTS_TABLE, $insertData)->execute();
				}

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                    $update_post_data = true;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check moved topics
                echo('<p class="gen"><b>' . $lang['Checking_moved_topics'] . "</b></p>\n");
                $db_updated = false;

                $result_array = dibi::select('t.topic_id')
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('mt')
                    ->on('[t.topic_moved_id] = [mt.topic_id]')
                    ->where('[mt.topic_id] IS NULL')
                    ->where('[t.topic_status] = %i', TOPIC_MOVED)
                    ->fetchPairs(null, 'topic_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Deleting_invalid_moved_topics'] . "</p>\n");

                    $affected_rows = dibi::delete(Tables::TOPICS_TABLE)
                        ->where('[topic_id] IN %in', $result_array)
                        ->where('[topic_status] = %i', TOPIC_MOVED)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                }

				// Check for normal topics with move information
                $affected_rows = dibi::update(Tables::TOPICS_TABLE, ['topic_moved_id' => 0])
                    ->where('[topic_moved_id] <> %i', 0)
                    ->where('[topic_status] <> %i', TOPIC_MOVED)
                    ->execute(dibi::AFFECTED_ROWS);

                if ($affected_rows === 1) {
                    echo('<p class="gen">' . sprintf($lang['Updating_invalid_moved_topic'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    echo('<p class="gen">' . sprintf($lang['Updating_invalid_moved_topics'], $affected_rows) . "</p>\n");
                } elseif (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

                // Checking for invalid prune settings
                echo('<p class="gen"><b>' . $lang['Checking_prune_settings'] . "</b></p>\n");
                $db_updated = false;

                $result_array1 = dibi::select('p.forum_id')
                    ->from(Tables::PRUNE_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->on('[p.forum_id] = [f.forum_id]')
                    ->where('[f.forum_id] IS NULL')
                    ->groupBy('p.forum_id')
                    ->fetchPairs(null, 'forum_id');

                // Forums with multiple prune settings
                $result_array2 = dibi::select('p.forum_id')
                    ->from(Tables::PRUNE_TABLE)
                    ->as('p')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->on('[p.forum_id] = [f.forum_id]')
                    ->groupBy('p.forum_id')
                    ->having('COUNT(p.forum_id) > %i', 1)
                    ->fetchPairs(null, 'forum_id');

                $result_array = array_merge($result_array1, $result_array2);

                if (count($result_array)) {
                    echo('<p class="gen">' . $lang['Removing_invalid_prune_settings'] . "</p>\n");
                    $record_list = implode(',', $result_array);
                    $db_updated  = true;

                    $affected_rows = dibi::delete(Tables::PRUNE_TABLE)
                        ->where('[forum_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Updating_invalid_moved_topic'],
                                $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Updating_invalid_moved_topics'],
                                $affected_rows) . "</p>\n");
                    }
                }

                // Forums with pruning enabled and no prune settings
                $result_array = dibi::select('f.forum_id')
                    ->from(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->leftJoin(Tables::PRUNE_TABLE)
                    ->as('p')
                    ->on('[f.forum_id] = [p.forum_id]')
                    ->where('[p.forum_id] IS NULL')
                    ->where('[f.prune_enable] = %i', 1)
                    ->fetchPairs(null, 'forum_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);

                    $affected_rows = dibi::update(Tables::FORUMS_TABLE, ['prune_enable' => 0])
                        ->where('[forum_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Updating_invalid_prune_setting'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Updating_invalid_moved_settings'], $affected_rows) . "</p>\n");
                    }
                } elseif (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

				// Checking for invalid topic-watch data
				echo('<p class="gen"><b>' . $lang['Checking_topic_watch_data'] . "</b></p>\n");

                $user_array = dibi::select('tw.user_id')
                    ->from(Tables::TOPICS_WATCH_TABLE)
                    ->as('tw')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[tw.user_id] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->groupBy('tw.user_id')
                    ->fetchPairs(null, 'user_id');

                $topic_array = dibi::select('tw.topic_id')
                    ->from(Tables::TOPICS_WATCH_TABLE)
                    ->as('tw')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[tw.topic_id] = [t.topic_id]')
                    ->where('[t.topic_id] IS NULL')
                    ->groupBy('tw.topic_id')
                    ->fetchPairs(null, 'topic_id');

                $countTopics = count($topic_array);
                $countUsers  = count($user_array);

                if ($countUsers || $countTopics) {
                    $affected_rows = 0;

                    if ($countUsers) {
                        $affected_rows += dibi::delete(Tables::TOPICS_WATCH_TABLE)
                            ->where('[user_id] IN %in', $user_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($countTopics) {
                        $affected_rows += dibi::delete(Tables::TOPICS_WATCH_TABLE)
                            ->where('[topic_id] IN %in', $topic_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Checking for invalid auth-access data
                echo('<p class="gen"><b>' . $lang['Checking_auth_access_data'] . "</b></p>\n");

                $group_array = dibi::select('aa.group_id')
                    ->from(Tables::AUTH_ACCESS_TABLE)
                    ->as('aa')
                    ->leftJoin(Tables::GROUPS_TABLE)
                    ->as('g')
                    ->on('[aa.group_id] = [g.group_id]')
                    ->where('[g.group_id] IS NULL')
                    ->groupBy('aa.group_id')
                    ->fetchPairs(null, 'group_id');

                $forum_array = dibi::select('aa.forum_id')
                    ->from(Tables::AUTH_ACCESS_TABLE)
                    ->as('aa')
                    ->leftJoin(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->on('[aa.forum_id] = [f.forum_id]')
                    ->where('[f.forum_id] IS NULL')
                    ->groupBy('aa.forum_id')
                    ->fetchPairs(null, 'forum_id');

                $countGroups = count($group_array);
                $countForums = count($forum_array);

                if ($countGroups || $countForums) {
                    $affected_rows = 0;

                    if ($countGroups) {
                        $affected_rows += dibi::delete(Tables::AUTH_ACCESS_TABLE)
                            ->where('[group_id] IN %in', $group_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($countForums) {
                        $affected_rows += dibi::delete(Tables::AUTH_ACCESS_TABLE)
                            ->where('[forum_id] IN %in', $forum_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// If post or topic data has been updated, we interrupt here and add a link to resync the data
                if ($update_post_data) {
                    echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php?mode=perform&amp;function=synchronize_post_direct&amp;db_state=' . ($db_state ? '1' : '0')) . '">' . $lang['Must_synchronize'] . "</a></p>\n");
                    // Send Information about processing time
                    echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');
                    require_once '.' . $sep . 'page_footer_admin.php';
                    exit;
                } else {
                    lock_db(true);
                }

				break;
			case 'check_vote': // Check vote tables
				echo('<h1>' . $lang['Checking_vote_tables'] . "</h1>\n");
				lock_db();
				
				// Check for votes without a topic
				echo('<p class="gen"><b>' . $lang['Checking_votes_wo_topic'] . "</b></p>\n");

                $rows = dibi::select(['v.vote_id', 'v.vote_text', 'v.vote_start', 'v.vote_length'])
                    ->from(Tables::VOTE_DESC_TABLE)
                    ->as('v')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[v.topic_id] = [t.topic_id]')
                    ->where('[t.topic_id] IS NULL')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Votes_wo_topic_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    $start_time = create_date($board_config['default_dateformat'], $row->vote_start, $board_config['board_timezone']);
                    $end_time   = $row->vote_length === 0 ? '-' : create_date($board_config['default_dateformat'], $row->vote_start + $row->vote_length, $board_config['board_timezone']);
                    echo('<li>' . sprintf($lang['Invalid_vote'], htmlspecialchars($row->vote_text), $row->vote_id, $start_time, $end_time) . "</li>\n");
                    $result_array[] = $row->vote_id;
                }

				if ($list_open) {
					echo("</ul></font>\n");
					$list_open = false;
				}

				if (count($result_array)) {
					$record_list = implode(',', $result_array);
					echo('<p class="gen">' . $lang['Deleting_Votes'] . " </p>\n");

					dibi::delete(Tables::VOTE_DESC_TABLE)
						->where('[vote_id] IN %in', $result_array)
						->execute();

					dibi::delete(Tables::VOTE_RESULTS_TABLE)
						->where('[vote_id] IN %in', $result_array)
						->execute();

					dibi::delete(Tables::VOTE_USERS_TABLE)
						->where('[vote_id] IN %in', $result_array)
						->execute();
				} else {
					echo($lang['Nothing_to_do']);
				}

				// Check for votes without results
				echo('<p class="gen"><b>' . $lang['Checking_votes_wo_result'] . "</b></p>\n");

				$rows = dibi::select(['v.vote_id', 'v.vote_text', 'v.vote_start', 'v.vote_length'])
					->from(Tables::VOTE_DESC_TABLE)
					->as('v')
					->leftJoin(Tables::VOTE_RESULTS_TABLE)
					->as('vr')
					->on('[v.vote_id] = [vr.vote_id]')
					->where('[vr.vote_id] IS NULL')
					->fetchAll();

				$result_array = [];

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Votes_wo_result_found'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
						$list_open = true;
					}
					$start_time = create_date($board_config['default_dateformat'], $row->vote_start,
					$board_config['board_timezone']);
					$end_time = $row->vote_length === 0 ? '-' : create_date($board_config['default_dateformat'], $row->vote_start->vote_length, $board_config['board_timezone']);
					echo('<li>' . sprintf($lang['Invalid_vote'], htmlspecialchars($row->vote_text), $row->vote_id, $start_time, $end_time) . "</li>\n");
					$result_array[] = $row->vote_id;
				}

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);

                    dibi::delete(Tables::VOTE_DESC_TABLE)
                        ->where('[vote_id] IN %in', $result_array)
                        ->execute();

                    dibi::delete(Tables::VOTE_RESULTS_TABLE)
                        ->where('[vote_id] IN %in', $result_array)
                        ->execute();

                    dibi::delete(Tables::VOTE_USERS_TABLE)
                        ->where('[vote_id] IN %in', $result_array)
                        ->execute();

                    echo('<p class="gen">' . $lang['Deleting_Votes'] . " </p>\n");
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check vote data in topics
				echo('<p class="gen"><b>' . $lang['Checking_topics_vote_data'] . "</b></p>\n");
				$db_updated = false;

				$result_array = dibi::select('t.topic_id')
					->from(Tables::TOPICS_TABLE)
					->as('t')
					->leftJoin(Tables::VOTE_DESC_TABLE)
					->as('v')
					->on('[t.topic_id] = [v.topic_id]')
					->where('[v.vote_id] IS NULL')
					->where('[t.topic_vote] = %i', 1)
					->fetchPairs(null, 'topic_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Updating_topics_wo_vote'] . "</p>\n");

                    $affected_rows = dibi::update(Tables::TOPICS_TABLE, ['topic_vote' => 0])
                        ->where('[topic_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                }

				// Check for topics with vote not marked as vote
                $result_array = dibi::select('t.topic_id')
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->innerJoin(Tables::VOTE_DESC_TABLE)
                    ->as('v')
                    ->on('[t.topic_id] = [v.topic_id]')
                    ->where('[t.topic_vote] = %i', 0)
                    ->fetchPairs(null, 'topic_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Updating_topics_w_vote'] . "</p>\n");

                    $affected_rows = dibi::update(Tables::TOPICS_TABLE, ['topic_vote' => 1])
                        ->where('[topic_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        $db_updated = true;
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                }

                if (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

				// Check for vote results without a vote
                echo('<p class="gen"><b>' . $lang['Checking_results_wo_vote'] . "</b></p>\n");

                $rows = dibi::select(['vr.vote_id', 'vr.vote_option_id', 'vr.vote_option_text', 'vr.vote_result'])
                    ->from(Tables::VOTE_RESULTS_TABLE)
                    ->as('vr')
                    ->leftJoin(Tables::VOTE_DESC_TABLE)
                    ->as('v')
                    ->on('[vr.vote_id] = [v.vote_id]')
                    ->where('[v.vote_id] IS NULL')
                    ->fetchAll();

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Results_wo_vote_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Invalid_result'], htmlspecialchars($row->vote_option_text), $row->vote_result) . "</li>\n");

                    dibi::delete(Tables::VOTE_RESULTS_TABLE)
                        ->where('[vote_id] = %i', $row->vote_id)
                        ->where('[vote_option_id] = %i', $row->vote_option_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking for invalid voters data
				echo('<p class="gen"><b>' . $lang['Checking_voters_data'] . "</b></p>\n");

                $user_array = dibi::select('vu.vote_user_id')
                    ->from(Tables::VOTE_USERS_TABLE)
                    ->as('vu')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[vu.vote_user_id] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->groupBy('vu.vote_user_id')
                    ->fetchPairs(null, 'vote_user_id');

                $vote_array = dibi::select('vu.vote_id')
                    ->from(Tables::VOTE_USERS_TABLE)
                    ->as('vu')
                    ->leftJoin(Tables::VOTE_DESC_TABLE)
                    ->as('v')
                    ->on('[vu.vote_id] = [v.vote_id]')
                    ->where('[v.vote_id] IS NULL')
                    ->groupBy('vu.vote_id')
                    ->fetchPairs(null, 'vote_id');

				$countUsers = count($user_array);
				$countVotes = count($vote_array);

                if ($countUsers || $countVotes) {
                    $affected_rows = 0;

                    if ($countUsers) {
                        $affected_rows += dibi::delete(Tables::VOTE_USERS_TABLE)
                            ->where('[vote_user_id] IN %in', $user_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($countVotes) {
                        $affected_rows += dibi::delete(Tables::VOTE_USERS_TABLE)
                            ->where('[vote_id] IN %in', $vote_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				lock_db(true);
				break;
			case 'check_pm': // Check private messages
				echo('<h1>' . $lang['Checking_pm_tables'] . "</h1>\n");
				lock_db();

				// Check for pms without a text
				echo('<p class="gen"><b>' . $lang['Checking_pms_wo_text'] . "</b></p>\n");

                $rows = dibi::select(['pm.privmsgs_id', 'pm.privmsgs_subject'])
                    ->select('uf.user_id')
                    ->as('from_user_id')
                    ->select('uf.username')
                    ->as('from_username')
                    ->select('ut.user_id')
                    ->as('to_user_id')
                    ->select('ut.username')
                    ->as('to_username')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->as('pm')
                    ->leftJoin(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                    ->as('pmt')
                    ->on('[pm.privmsgs_id] = [pmt.privmsgs_text_id]')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('uf')
                    ->on('[pm.privmsgs_from_userid] = [uf.user_id]')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('ut')
                    ->on('[pm.privmsgs_to_userid] = [ut.user_id]')
                    ->where('[pmt.privmsgs_text_id] IS NULL')
                    ->fetchAll();

				$result_array = [];

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Pms_wo_text_found'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . sprintf($lang['Deleting_pn_wo_text'], $row->privmsgs_id, htmlspecialchars($row->privmsgs_subject), htmlspecialchars($row->from_username), $row->from_user_id, htmlspecialchars($row->to_username), $row->to_user_id) . "</li>\n");
                    $result_array[] = $row->privmsgs_id;
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                }

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Deleting_Pms'] . " </p>\n");

                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Check for texts without a private message
				echo('<p class="gen"><b>' . $lang['Checking_texts_wo_pm'] . "</b></p>\n");

				$result_array = dibi::select('pmt.privmsgs_text_id')
					->from(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
					->as('pmt')
					->leftJoin(Tables::PRIVATE_MESSAGE_TABLE)
					->as('pm')
					->on('[pmt.privmsgs_text_id] = [pm.privmsgs_id]')
					->where('[pm.privmsgs_id] IS NULL')
					->fetchPairs(null, 'privmsgs_text_id');

                if (count($result_array)) {
                    echo('<p class="gen">' . $lang['Deleting_pm_texts'] . "</p>\n");
                    $record_list = implode(',', $result_array);

                    $affected_rows = dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                        ->where('[privmsgs_text_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check pms for invaild senders
				echo('<p class="gen"><b>' . $lang['Checking_invalid_pm_senders'] . "</b></p>\n");

                $result_array = dibi::select('pm.privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->as('pm')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[pm.privmsgs_from_userid] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->fetchPairs(null, 'privmsgs_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Invalid_pm_senders_found'] . ": $record_list</p>\n");
                    echo('<p class="gen">' . $lang['Updating_pms'] . "</p>\n");

                    dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_from_userid' => DELETED])
                        ->where('[privmsgs_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Check pms for invaild recipients
				echo('<p class="gen"><b>' . $lang['Checking_invalid_pm_recipients'] . "</b></p>\n");

                $result_array = dibi::select('pm.privmsgs_id')
                    ->from(Tables::PRIVATE_MESSAGE_TABLE)
                    ->as('pm')
                    ->leftJoin(Tables::USERS_TABLE)
                    ->as('u')
                    ->on('[pm.privmsgs_to_userid] = [u.user_id]')
                    ->where('[u.user_id] IS NULL')
                    ->fetchPairs(null, 'privmsgs_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Invalid_pm_recipients_found'] . ": $record_list</p>\n");
                    echo('<p class="gen">' . $lang['Updating_pms'] . "</p>\n");

                    dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_to_userid' => DELETED])
                        ->where('[privmsgs_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

                $fromArray = [
					PRIVMSGS_NEW_MAIL,
					PRIVMSGS_UNREAD_MAIL,
					PRIVMSGS_SENT_MAIL,
					PRIVMSGS_SAVED_OUT_MAIL
				];

                 $toArray = [
					 PRIVMSGS_NEW_MAIL,
					 PRIVMSGS_UNREAD_MAIL,
					 PRIVMSGS_READ_MAIL,
					 PRIVMSGS_SAVED_IN_MAIL
				 ];

				// Check for pns with deleted sender or recipient
				echo('<p class="gen"><b>' . $lang['Checking_pm_deleted_users'] . "</b></p>\n");

				$result_array = dibi::select('privmsgs_id')
					->from(Tables::PRIVATE_MESSAGE_TABLE)
					->where('([privmsgs_from_userid] = %i AND [privmsgs_type] IN %in) OR ([privmsgs_to_userid] = %i AND [privmsgs_type] IN %in)', DELETED, $fromArray, DELETED, $toArray)
					->fetchPairs(null, 'privmsgs_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);
                    echo('<p class="gen">' . $lang['Invalid_pm_users_found'] . ": $record_list</p>\n");
                    echo('<p class="gen">' . $lang['Deleting_pms'] . "</p>\n");

                    dibi::delete(Tables::PRIVATE_MESSAGE_TABLE)
                        ->where('[privmsgs_id] IN %in', $result_array)
                        ->execute();

                    dibi::delete(Tables::PRIVATE_MESSAGE_TEXT_TABLE)
                        ->where('[privmsgs_text_id] IN %in', $result_array)
                        ->execute();
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Updating new pm counter
				echo('<p class="gen"><b>' . $lang['Synchronize_new_pm_data'] . "</b></p>\n");

                $rows = dibi::select(['u.user_id', 'u.username', 'u.user_new_privmsg'])
                    ->select('COUNT(pm.privmsgs_id)')
                    ->as('new_counter')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->innerJoin(Tables::PRIVATE_MESSAGE_TABLE)
                    ->as('pm')
                    ->on('[u.user_id] = [pm.privmsgs_to_userid]')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->where('[pm.privmsgs_type] = %i', PRIVMSGS_NEW_MAIL)
                    ->groupBy('u.user_id')
                    ->groupBy('u.username')
                    ->groupBy('u.user_new_privmsg')
                    ->fetchAll();

                foreach ($rows as $row) {
                    $result_array[] = $row->user_id;

                    if ($row->new_counter !== $row->user_new_privmsg) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }
                        echo('<li>' . sprintf($lang['Synchronizing_user'], htmlspecialchars($row->username), $row->user_id)
                            . "</li>\n");

                        dibi::update(Tables::USERS_TABLE, ['user_new_privmsg' => $row->new_counter])
                            ->where('[user_id] = %i', $row->user_id)
                            ->execute();
                    }
                }

				// All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_new_privmsg] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_new_privmsg] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Synchronizing_user'], htmlspecialchars($row->username), $row->user_id) . "</li>\n");

                    dibi::update(Tables::USERS_TABLE, ['user_new_privmsg' => 0])
                        ->where('[user_id] = %i', $row->user_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Updating unread pm counter
                echo('<p class="gen"><b>' . $lang['Synchronize_unread_pm_data'] . "</b></p>\n");

                $rows = dibi::select(['u.user_id', 'u.username', 'u.user_unread_privmsg'])
                    ->select('COUNT(pm.privmsgs_id)')
                    ->as('new_counter')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->innerJoin(Tables::PRIVATE_MESSAGE_TABLE)
                    ->as('pm')
                    ->on('[u.user_id] = [pm.privmsgs_to_userid]')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    $result_array[] = $row->user_id;

                    if ($row->new_counter !== $row->user_unread_privmsg) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . sprintf($lang['Synchronizing_user'], htmlspecialchars($row->username), $row->user_id) . "</li>\n");

                        dibi::update(Tables::USERS_TABLE, ['user_unread_privmsg' => $row->new_counter])
                            ->where('[user_id] = %i', $row->user_id)
                            ->execute();
                    }
                }

				// All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_unread_privmsg] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_unread_privmsg] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Synchronizing_user'], htmlspecialchars($row->username), $row->user_id) . "</li>\n");

                    dibi::update(Tables::USERS_TABLE, ['user_unread_privmsg' => 0])
                        ->where('[user_id] = %i', $row->user_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				lock_db(true);
				break;
			case 'check_config': // Check config table
				echo('<h1>' . $lang['Checking_config_table'] . "</h1>\n");
				lock_db();

				echo('<p class="gen"><b>' . $lang['Checking_config_entries'] . "</b></p>\n");

				// Update config data to match current configuration
                if (!empty($_SERVER['SERVER_PROTOCOL']) || !empty($_ENV['SERVER_PROTOCOL'])) {
                    $protocol = !empty($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : $_ENV['SERVER_PROTOCOL'];
                    if (mb_strtolower(mb_substr($protocol, 0, 5)) === 'https') {
                        $default_config['cookie_secure'] = '1';
                    }
                }

				if (!empty($_SERVER['SERVER_NAME']) || !empty($_ENV['SERVER_NAME'])) {
					$default_config['server_name'] = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_ENV['SERVER_NAME'];
				} else if (!empty($_SERVER['HTTP_HOST']) || !empty($_ENV['HTTP_HOST'])) {
					$default_config['server_name'] = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_ENV['HTTP_HOST'];
				}

				if (!empty($_SERVER['SERVER_PORT']) || !empty($_ENV['SERVER_PORT'])) {
					$default_config['server_port'] = !empty($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : $_ENV['SERVER_PORT'];
				}

				$default_config['script_path'] = str_replace('admin', '', dirname($_SERVER['PHP_SELF']));

                $startDate = dibi::select('MIN(topic_time)')
                    ->as('startdate')
                    ->from(Tables::TOPICS_TABLE)
                    ->fetch();

                if ($startDate && $startDate->startdate) {
                    $default_config['board_startdate'] = $startDate->startdate;
                }

				// Start the job
                foreach ($default_config as $key => $value) {
                	// todo get_config_data() may be used
					$config = dibi::select('config_value')
						->from(Tables::CONFIG_TABLE)
						->where('[config_name] = %s', $key)
						->fetch();

                    if (!$config) {
                        // entry does not exists
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Restoring_config'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo("<li><b>$key:</b> $value</li>\n");

                        dibi::insert(Tables::CONFIG_TABLE, ['config_name' => $key, 'config_value' => $value])
                            ->execute();
                    }
                }

                $cache = new Cache($storage, Tables::CONFIG_TABLE);
                $cache->remove(Tables::CONFIG_TABLE);

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }
				
				lock_db(true);
				break;
			case 'check_search_wordmatch': // Check search word match data
				echo('<h1>' . $lang['Checking_search_wordmatch_tables'] . "</h1>\n");
				lock_db();

				// Checking for invalid search word match data
				echo('<p class="gen"><b>' . $lang['Checking_search_data'] . "</b></p>\n");

                $post_array = dibi::select('sm.post_id')
                    ->from(Tables::SEARCH_MATCH_TABLE)
                    ->as('sm')
                    ->leftJoin(Tables::POSTS_TABLE)
                    ->as('p')
                    ->on('[sm.post_id] = [p.post_id]')
                    ->where('[p.post_id] IS NULL')
                    ->groupBy('sm.post_id')
                    ->fetchPairs(null, 'post_id');

                $word_array = dibi::select('sm.word_id')
                    ->from(Tables::SEARCH_MATCH_TABLE)
                    ->as('sm')
                    ->leftJoin(Tables::SEARCH_WORD_TABLE)
                    ->as('sw')
                    ->on('[sm.word_id] = [sw.word_id]')
                    ->where('[sw.word_id] IS NULL OR [sw.word_common] = %i', 1)
                    ->groupBy('sm.word_id')
                    ->fetchPairs(null, 'word_id');

                $postCount = count($post_array);
                $wordCount = count($word_array);

                if ($postCount || $wordCount) {
                    $affected_rows = 0;

                    if ($postCount) {
                        $affected_rows += dibi::delete(Tables::SEARCH_MATCH_TABLE)
                            ->where('[post_id] IN %in', $post_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($wordCount) {
                        $affected_rows += dibi::delete(Tables::SEARCH_MATCH_TABLE)
                            ->where('[word_id] IN %in', $word_array)
                            ->execute(dibi::AFFECTED_ROWS);
                    }

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
				} else {
					echo($lang['Nothing_to_do']);
				}

				lock_db(true);
				break;
			case 'check_search_wordlist': // Check search word list data
                echo('<h1>' . $lang['Checking_search_wordlist_tables'] . "</h1>\n");
                lock_db();

                // Checking for invalid search word list data
                echo('<p class="gen"><b>' . $lang['Checking_search_words'] . "</b></p>\n");

                $rows = dibi::select('sw.word_id')
                    ->from(Tables::SEARCH_WORD_TABLE)
                    ->as('sw')
                    ->leftJoin(Tables::SEARCH_MATCH_TABLE)
                    ->as('sm')
                    ->on('[sw.word_id] = [sm.word_id]')
                    ->where('[sm.word_id] IS NULL')
                    ->where('[sw.word_common] <> %i', 1)
                    ->fetchAll();

                $result_array = [];
                $affected_rows = 0;

                foreach ($rows as $row) {
                    $result_array[] = $row->word_id;
                    if (count($result_array) >= 100) {
                        echo('<p class="gen">' . $lang['Removing_part_invalid_words'] . "...</p>\n");
                        $record_list = implode(',', $result_array);

                        $affected_rows += dibi::delete(Tables::SEARCH_WORD_TABLE)
                            ->where('[word_id] IN %in', $result_array)
                            ->execute(dibi::AFFECTED_ROWS);

                        $result_array = [];
                    }
                }

                if (count($result_array)) {
                    echo('<p class="gen">' . $lang['Removing_invalid_words'] . "</p>\n");
                    $record_list = implode(',', $result_array);

                    $affected_rows += dibi::delete(Tables::SEARCH_WORD_TABLE)
                        ->where('[word_id] IN %in', $result_array)
                        ->execute(dibi::AFFECTED_ROWS);
                }

                if ($affected_rows === 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                } else {
                    echo($lang['Nothing_to_do']);
                }

				lock_db(true);
				break;
			case 'rebuild_search_index': // Rebuild Search Index
				echo('<h1>' . $lang['Rebuilding_search_index'] . "</h1>\n");
				$db_state = lock_db();

				// Clear Tables
				echo('<p class="gen"><b>' . $lang['Deleting_search_tables'] . "</b></p>\n");

				dibi::query('TRUNCATE TABLE %n', Tables::SEARCH_TABLE);
				dibi::query('TRUNCATE TABLE %n', Tables::SEARCH_WORD_TABLE);
				dibi::query('TRUNCATE TABLE %n', Tables::SEARCH_MATCH_TABLE);

				echo('<p class="gen">' . $lang['Done'] . "</p>\n");

				// TODO we should not need it
				/*
				// Reset auto increment
				echo("<p class=\"gen\"><b>" . $lang['Reset_search_autoincrement'] . "</b></p>\n");
				$sql = "ALTER TABLE " . SEARCH_WORD_TABLE . " AUTO_INCREMENT=1";

                if (!($db->sql_query($sql))) {
                    throw_error("Couldn't reset auto_increment!", __LINE__, __FILE__, $sql);
                }
                echo("<p class=\"gen\">" . $lang['Done'] . "</p>\n");
				*/

                echo('<p class="gen"><b>' . $lang['Preparing_config_data'] . "</b></p>\n");
                // Set data for start position in config table
                update_config('dbmtnc_rebuild_pos', '0');
                // Get data for end position

                $row = dibi::select('MAX(post_id)')
                    ->as('max_post_id')
                    ->from(Tables::POSTS_TABLE)
                    ->fetch();

                if (!$row) {
                    throw_error("Couldn't get post data!");
                }

				// Set data for end position in config table
				update_config('dbmtnc_rebuild_end', (int)$row->max_post_id);
				echo('<p class="gen">' . $lang['Done'] . "</p>\n");

				echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php?mode=perform&amp;function=perform_rebuild&amp;db_state=' . ($db_state ? '1' : '0')) . '">' . $lang['Can_start_rebuilding'] . '</a><br><span class="gensmall">' . $lang['Click_once_warning'] . "</span></p>\n");
				// Send Information about processing time
				echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');
                require_once '.' . $sep . 'page_footer_admin.php';
				exit;
				break;
			case 'proceed_rebuilding': // Proceed rebuilding search index
				echo('<h1>' . $lang['Preparing_to_proceed'] . "</h1>\n");
				$db_state = lock_db();

                // Clear Tables
                echo('<p class="gen"><b>' . $lang['Preparing_search_tables'] . "</b></p>\n");

                dibi::query('TRUNCATE TABLE %n', Tables::SEARCH_TABLE);

                dibi::delete(Tables::SEARCH_MATCH_TABLE)
                    ->where('[post_id] > %i', (int)$board_config['dbmtnc_rebuild_pos'])
                    ->where('[post_id] <= %i', (int)$board_config['dbmtnc_rebuild_end'])
                    ->execute();

                echo('<p class="gen">' . $lang['Done'] . "</p>\n");

                echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php?mode=perform&amp;function=perform_rebuild&amp;db_state=' . ($db_state ? '1' : '0')) . '">' . $lang['Can_start_rebuilding'] . '</a><br><span class="gensmall">' . $lang['Click_once_warning'] . "</span></p>\n");
                // Send Information about processing time
                echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');
                require_once '.' . $sep . 'page_footer_admin.php';
                exit;
                break;
			case 'perform_rebuild': // Rebuild search index (perform part)
				// ATTENTION: page_header not sent yet!
				$db_state = isset($_GET['db_state']) ? (int)$_GET['db_state'] : 0;

				// Identify PHP version and time limit configuration
                if (PHP_VERSION >= '4.0.5' && ($board_config['dbmtnc_rebuildcfg_php3only'] === 0)) { // Handle PHP beffore 4.0.5 as PHP 3 since array_search is not available
                    $php_ver = 4;
                    // try to reset time limit
                    $reset_allowed = true;
                    $execution_time = $board_config['dbmtnc_rebuildcfg_timelimit'];
                    set_error_handler('catch_error');
                    set_time_limit($board_config['dbmtnc_rebuildcfg_timelimit']);
                    restore_error_handler();
                    // Try to set unlimited execution time
                    @set_time_limit(0);
                } else {
                    $php_ver = 3;
                    $execution_time = get_cfg_var('max_execution_time');
                    // Try to set unlimited execution time
                    @set_time_limit(0);
                }

				if ($execution_time === false) {
					$execution_time = 30; // Asume 30 if an error occurs
				}

				// Calculate posts to process
				$posts_to_index = (int)(($execution_time - 5) * (($php_ver === 4) ? $board_config['dbmtnc_rebuildcfg_php4pps'] : $board_config['dbmtnc_rebuildcfg_php3pps']));

				if ($posts_to_index < $board_config['dbmtnc_rebuildcfg_minposts']) {
					$posts_to_index = $board_config['dbmtnc_rebuildcfg_minposts'];
				}

				// Check whether a special limit was set
                if ((int)$board_config['dbmtnc_rebuildcfg_timeoverwrite'] !== 0) {
                    $posts_to_index = (int)$board_config['dbmtnc_rebuildcfg_timeoverwrite'];
                }

				// We have all data so get the post information
				$rows = dibi::select(['post_id', 'post_subject', 'post_text'])
					->from(Tables::POSTS_TEXT_TABLE)
					->where('[post_id] > %i', $board_config['dbmtnc_rebuild_pos'])
					->where('[post_id] <= %i', $board_config['dbmtnc_rebuild_end'])
					->orderBy('post_id')
					->limit($posts_to_index)
					->fetchAll();

				// Get first record
                if (!count($rows)) {// Yeah! we reached the end of the posts - finish actions and exit
                    require_once '.' . $sep . 'page_header_admin.php';

					update_config('dbmtnc_rebuild_pos', '-1');
					update_config('dbmtnc_rebuild_end', '0');
					
					echo('<p class="gen">' . $lang['Indexing_finished'] . ".</p>\n");

					if ($db_state === 0) {
						lock_db(true, true, true);
					} else {
						echo('<p class="gen"><b>' . $lang['Unlock_db'] . "</b></p>\n");
						echo('<p class="gen">' . $lang['Ignore_unlock_command'] . "</p>\n");
					}

					echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php') . '">' . $lang['Back_to_DB_Maintenance'] . "</a></p>\n");
					// Send Information about processing time
					echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');
                    require_once '.' . $sep . 'page_footer_admin.php';
					exit;
				}

				$last_post = 0;

				// TODO we use native phpBB functions add_search_words()
                foreach ($rows as $row) {
                    $last_post = $row->post_id;
                    SearchHelper::addSearchWords('single', $row->post_id, stripslashes($row->post_text), stripslashes($row->post_subject));
                }

				// All posts are indexed for this turn - update Config-Data
				update_config('dbmtnc_rebuild_pos', $last_post);
				// OK, all actions are done - send headers

                $template->assignVars(
                    [
                        'META' => '<meta http-equiv="refresh" content="1;url=' . Session::appendSid("admin_db_maintenance.php?mode=perform&amp;function=perform_rebuild&amp;db_state=$db_state") . '">'
                    ]
                );

                require_once '.' . $sep . 'page_header_admin.php';

				ob_end_flush();
				// Get Statistics

				$posts_total = dibi::select('Count(*)')
					->as('posts_total')
					->from(Tables::POSTS_TEXT_TABLE)
					->where('[post_id] <= %i', $board_config['dbmtnc_rebuild_end'])
					->fetchSingle();

				$posts_indexed = dibi::select('Count(*)')
					->as('posts_indexed')
					->from(Tables::POSTS_TEXT_TABLE)
					->where('[post_id] <= %i', $last_post)
					->fetchSingle();
				
				echo('<p class="gen">' . sprintf($lang['Indexing_progress'], $posts_indexed, $posts_total, ($posts_indexed / $posts_total) * 100, $last_post) . "</p>\n");
				echo('<p class="gen"><a href="' . Session::appendSid("admin_db_maintenance.php?mode=perform&amp;function=perform_rebuild&amp;db_state=$db_state") . '">' . $lang['Click_or_wait_to_proceed'] . '</a><br><span class="gensmall">' . $lang['Click_once_warning'] . "</span></p>\n");

				// Send Information about processing time
				echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');

                require_once '.' . $sep . 'page_footer_admin.php';

				exit;
				break;
			case 'synchronize_post': // Synchronize post data
			case 'synchronize_post_direct': // Run directly
				echo('<h1>' . $lang['Synchronize_posts'] . "</h1>\n");

				if ($function === 'synchronize_post_direct') {
					$db_state = isset($_GET['db_state']) ? (int)$_GET['db_state'] : 1;
				} else {
					lock_db();
				}

				// Updating normal topics
				echo('<p class="gen"><b>' . $lang['Synchronize_topic_data'] . "</b></p>\n");

				$rows = dibi::select(['t.topic_id', 't.topic_title', 't.topic_replies', 't.topic_first_post_id','t.topic_last_post_id'])
					->select('Count(p.post_id) - 1')
					->as('new_replies')
					->select('Min(p.post_id)')
					->as('new_first_post_id')
					->select('Max(p.post_id)')
					->as('new_last_post_id')
					->from(Tables::TOPICS_TABLE)
					->as('t')
					->innerJoin(Tables::POSTS_TABLE)
					->as('p')
					->on('[t.topic_id] = [p.topic_id]')
					->groupBy('t.topic_id')
					->groupBy('t.topic_title')
					->groupBy('t.topic_replies')
					->groupBy('t.topic_first_post_id')
					->groupBy('t.topic_last_post_id')
					->having('new_replies <> t.topic_replies OR new_first_post_id <> t.topic_first_post_id OR new_last_post_id <> t.topic_last_post_id')
					->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Synchronizing_topics'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
						$list_open = true;
					}

					echo('<li>' . sprintf($lang['Synchronizing_topic'], $row->topic_id, htmlspecialchars($row->topic_title)) . "</li>\n");

					$updateData = [
						'topic_replies'       => $row->new_replies,
						'topic_first_post_id' => $row->new_first_post_id,
						'topic_last_post_id'  => $row->new_last_post_id
					];

					dibi::update(Tables::TOPICS_TABLE, $updateData)
						->where('[topic_id] = %i', $row->topic_id)
						->execute();
				}

                if ($list_open) {
					echo("</ul></font>\n");
					$list_open = false;
				} else {
					echo($lang['Nothing_to_do']);
				}

				// Updating moved topics
				echo('<p class="gen"><b>' . $lang['Synchronize_moved_topic_data'] . "</b></p>\n");

				$rows = dibi::select(['topic_id', 'topic_title', 'topic_last_post_id', 'topic_moved_id'])
					->from(Tables::TOPICS_TABLE)
					->where('[topic_status] = %i', TOPIC_MOVED)
					->fetchAll();

				foreach ($rows as $row) {
					// Getting data for original topic

					$row2 = dibi::select('topic_id')
						->select('COUNT(post_id) - 1')
						->as('topic_replies')
						->select('MIN(post_id)')
						->as('topic_first_post_id')
						->select('MAX(post_id)')
						->as('topic_last_post_id')
						->from(Tables::POSTS_TABLE)
						->where('[topic_id] = %i', $row->topic_moved_id)
						->where('[post_id] <= %i', $row->topic_last_post_id)
						->groupBy('topic_id')
						->fetch();

					if ($row2) {
						$row3 = dibi::select('topic_id')
							->from(Tables::TOPICS_TABLE)
							->where('[topic_id] = %i', $row->topic_id)
							->where(
								'(topic_replies <> %i OR topic_first_post_id <> %i OR topic_last_post_id <> %i)',
								$row2->topic_replies,
								$row2->topic_first_post_id,
								$row2->topic_last_post_id
							)->fetchAll();

						if ($row3) {
							if (!$list_open) {
								echo('<p class="gen">' . $lang['Synchronizing_moved_topics'] . ":</p>\n");
								echo("<font class=\"gen\"><ul>\n");

								$list_open = true;
							}

							echo('<li>' . sprintf($lang['Synchronizing_moved_topic'], $row->topic_id, $row->topic_moved_id, htmlspecialchars($row->topic_title)) . "</li>\n");

							$updateData = [
								'topic_replies' => $row2->topic_replies,
								'topic_first_post_id' => $row2->topic_first_post_id,
								'topic_last_post_id' => $row2->topic_last_post_id
							];

							dibi::update(Tables::TOPICS_TABLE, $updateData)
								->where('[topic_id] = %i', $row->topic_id)
								->execute();
						}
					} else {
						throw_error(sprintf($lang['Inconsistencies_found'], '<a href="' . Session::appendSid('admin_db_maintenance.php?mode=perform&amp;function=check_post') . '">', '</a>'));
					}
				}

				if ($list_open) {
					echo("</ul></font>\n");
					$list_open = false;
				} else {
					echo($lang['Nothing_to_do']);
				}

                $thanksManager = $container->getService('ThanksManager');
                $topicsManager = $container->getService('TopicsManager');

                // Updating normal topics
                echo('<p class="gen"><b>' . $lang['Synchronize_topic_thank_data'] . "</b></p>\n");

                $rows = dibi::select(['t.topic_id', 't.topic_title', 't.topic_thanks'])
                    ->select('COUNT(th.topic_id)')
                    ->as('new_thanks')
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->innerJoin(Tables::THANKS_TABLE)
                    ->as('th')
                    ->on('[t.topic_id] = [th.topic_id]')
                    ->groupBy('t.topic_id')
                    ->fetchAll();

                foreach ($rows as $row) {
                    if ($row->topic_thanks !== $row->new_thanks) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_topics'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . sprintf($lang['Synchronizing_topic'], $row->topic_id, htmlspecialchars($row->topic_title)) . "</li>\n");

                        $topicsManager->updateByPrimary($row->topic_id, ['topic_thanks' => $row->topic_thanks]);
                    }
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                echo('<p class="gen"><b>' . $lang['Synchronize_topic_thank_data2'] . "</b></p>\n");

                // Updating moved topics
                $rows = dibi::select(['t.topic_id', 't.topic_title', 't.topic_thanks'])
                    ->select('COUNT(th.topic_id)')
                    ->as('new_thanks')
                    ->from(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->leftJoin(Tables::THANKS_TABLE)
                    ->as('th')
                    ->on('[t.topic_id] = [th.topic_id]')
                    ->groupBy('t.topic_id')
                    ->having('[new_thanks] <> [t.topic_thanks]')
                    ->fetchAll();

                foreach ($rows as $row) {
                    // Getting data for original topic
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_topic_thank'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");

                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Synchronizing_topic'], $row->topic_id, htmlspecialchars($row->topic_title)) . "</li>\n");

                    $topicsManager->updateByPrimary($row->topic_id, ['topic_thanks' => $row->new_thanks]);
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Updating topic data of forums
				echo('<p class="gen"><b>' . $lang['Synchronize_forum_topic_data'] . "</b></p>\n");

				$rows = dibi::select(['f.forum_id', 'f.forum_name', 'f.forum_topics'])
					->select('COUNT(t.topic_id)')
					->as('new_topics')
					->from(Tables::FORUMS_TABLE)
					->as('f')
					->innerJoin(Tables::TOPICS_TABLE)
					->as('t')
					->on('[f.forum_id] = [t.forum_id]')
					->groupBy('f.forum_id')
					->groupBy('f.forum_name')
					->groupBy('f.forum_topics')
					->having('new_topics <> f.forum_topics')
					->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Synchronizing_forums'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
						$list_open = true;
					}
					echo('<li>' . sprintf($lang['Synchronizing_forum'], $row->forum_id, htmlspecialchars($row->forum_name)) . "</li>\n");

					dibi::update(Tables::FORUMS_TABLE, ['forum_topics' => $row->new_topics])
						->where('[forum_id] = %i', $row->forum_id)
						->execute();
				}

				if ($list_open) {
					echo("</ul></font>\n");
					$list_open = false;
				} else {
					echo($lang['Nothing_to_do']);
				}

				// Updating forums without a topic
				echo('<p class="gen"><b>' . $lang['Synchronize_forum_data_wo_topic'] . "</b></p>\n");

				$result_array = dibi::select('f.forum_id')
					->from(Tables::FORUMS_TABLE)
					->as('f')
					->leftJoin(Tables::TOPICS_TABLE)
					->as('t')
					->on('[f.forum_id] = [t.forum_id]')
					->where('[t.forum_id] IS NULL')
					->where('([f.forum_topics] <> %i OR [f.forum_last_post_id] <> %i)', 0, 0)
					->fetchPairs(null, 'forum_id');

                $db_updated = false;

				if (count($result_array)) {
					$affected_rows = dibi::update(Tables::FORUMS_TABLE, ['forum_topics' => 0, 'forum_last_post_id' => 0])
						->where('[forum_id] IN %in', $result_array)
						->execute(dibi::AFFECTED_ROWS);

					if ($affected_rows === 1) {
                        $db_updated = true;
						echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
					} elseif ($affected_rows > 1) {
                        $db_updated = true;
						echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
					}
				} elseif (!$db_updated) {
					echo($lang['Nothing_to_do']);
				}

				// Updating post data of forums
				echo('<p class="gen"><b>' . $lang['Synchronize_forum_post_data'] . "</b></p>\n");

				$rows = dibi::select(['f.forum_id', 'f.forum_name', 'f.forum_posts', 'f.forum_last_post_id'])
					->select('COUNT(p.post_id)')
					->as('new_posts')
					->select('MAX(p.post_id)')
					->as('new_last_post_id')
					->from(Tables::FORUMS_TABLE)
					->as('f')
					->innerJoin(Tables::POSTS_TABLE)
					->as('p')
					->on('[f.forum_id] = [p.forum_id]')
					->groupBy('f.forum_id')
					->groupBy('f.forum_name')
					->groupBy('f.forum_name')
					->groupBy('f.forum_posts')
					->groupBy('f.forum_last_post_id')
					->having('new_posts <> f.forum_posts OR new_last_post_id <> f.forum_last_post_id')
					->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Synchronizing_forums'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
					}

					echo('<li>' . sprintf($lang['Synchronizing_forum'], $row->forum_id, htmlspecialchars($row->forum_name)) . "</li>\n");

					dibi::update(Tables::FORUMS_TABLE, ['forum_posts' => $row->new_posts, 'forum_last_post_id' => $row->new_last_post_id])
						->where('[forum_id] = %i', $row->forum_id)
						->execute();
				}

				if ($list_open) {
					echo("</ul></font>\n");
					$list_open = false;
				} else {
					echo($lang['Nothing_to_do']);
				}

				// Updating forums without a post
				echo('<p class="gen"><b>' . $lang['Synchronize_forum_data_wo_post'] . "</b></p>\n");

				$result_array = dibi::select('f.forum_id')
					->from(Tables::FORUMS_TABLE)
					->as('f')
					->leftJoin(Tables::POSTS_TABLE)
					->as('p')
					->on('[f.forum_id] = [p.forum_id]')
					->where('[p.forum_id] IS NULL')
					->where('[f.forum_posts] <> %i', 0)
					->fetchPairs(null, 'forum_id');

				if (count($result_array)) {
					$record_list = implode(',', $result_array);

					$affected_rows = dibi::update(Tables::FORUMS_TABLE, ['forum_posts' => 0])
						->where('[forum_id] IN %in', $result_array)
						->execute(dibi::AFFECTED_ROWS);

					if ($affected_rows === 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
					} elseif ($affected_rows > 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
					}
				} elseif (!$db_updated) {
					echo($lang['Nothing_to_do']);
				}

                // Updating thanks data of forums
                echo('<p class="gen"><b>' . $lang['Synchronize_forum_thank_data'] . "</b></p>\n");

                $rows = dibi::select(['f.forum_id', 'f.forum_name', 'f.forum_thanks'])
                    ->select('COUNT(th.topic_id)')
                    ->as('new_thanks')
                    ->from(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->innerJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[t.forum_id] = [f.forum_id]')
                    ->innerJoin(Tables::THANKS_TABLE)
                    ->as('th')
                    ->on('[t.topic_id] = [th.topic_id]')
                    ->groupBy('f.forum_id')
                    ->having('[new_thanks] <> [f.forum_thanks]')
                    ->fetchAll();

                $forumsManager = $container->getService('ForumsManager');

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_forums'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    if ($row->forum_thanks !== $row->new_thanks) {
                        echo('<li>' . sprintf($lang['Synchronizing_forum'], $row->forum_id, htmlspecialchars($row->forum_name)) . "</li>\n");

                        $forumsManager->updateByPrimary($row->forum_id, ['forum_thanks' => $row->new_thanks]);
                    }
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // Updating forums without a thanks
                echo('<p class="gen"><b>' . $lang['Synchronize_forum_data_wo_thank'] . "</b></p>\n");

                $result_array = dibi::select('f.forum_id')
                    ->from(Tables::FORUMS_TABLE)
                    ->as('f')
                    ->leftJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[f.forum_id] = [t.forum_id]')
                    ->leftJoin(Tables::THANKS_TABLE)
                    ->as('th')
                    ->on('[t.topic_id] = [th.topic_id]')
                    ->where('[t.forum_id] IS NULL')
                    ->where('[f.forum_thanks] <> %i', 0)
                    ->fetchPairs(null, 'forum_id');

                if (count($result_array)) {
                    $record_list = implode(',', $result_array);

                    $affected_rows = $forumsManager->updateByPrimarys($result_array, ['forum_thanks' => 0]);

                    if ($affected_rows === 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                    } elseif ($affected_rows > 1) {
                        echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                    }
                } elseif (!$db_updated) {
                    echo($lang['Nothing_to_do']);
                }

				if ($function === 'synchronize_post_direct') {
					if ($db_state === 0) {
						lock_db(true, true, true);
					} else {
						echo('<p class="gen"><b>' . $lang['Unlock_db'] . "</b></p>\n");
						echo('<p class="gen">' . $lang['Ignore_unlock_command'] . "</p>\n");
					}
				} else {
					lock_db(true);
				}

				break;
			case 'synchronize_user': // Synchronize post counter of users
				echo('<h1>' . $lang['Synchronize_post_counters'] . "</h1>\n");
				lock_db();

				// Updating new pm counter

				echo('<p class="gen"><b>' . $lang['Synchronize_user_post_counter'] . "</b></p>\n");

				$rows = dibi::select(['u.user_id', 'u.username', 'u.user_posts'])
					->select('COUNT(p.post_id)')
					->as('new_counter')
					->from(Tables::USERS_TABLE)
					->as('u')
					->innerJoin(Tables::POSTS_TABLE)
					->as('p')
					->on('[u.user_id] = [p.poster_id]')
					->where('[u.user_id] <> %i', ANONYMOUS)
					->groupBy('u.user_id')
					->groupBy('u.username')
					->groupBy('u.user_posts')
					->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
					$result_array[] = $row->user_id;

					if ($row->new_counter !== $row->user_posts) {
						if (!$list_open) {
							echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
							echo("<font class=\"gen\"><ul>\n");
							$list_open = true;
						}

						echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_posts, $row->new_counter) . "</li>\n");

						dibi::update(Tables::USERS_TABLE, ['user_posts' => $row->new_counter])
							->where('[user_id] = %i', $row->user_id)
							->execute();
					}
				}

				// All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username', 'user_posts'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_posts] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username', 'user_posts'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_posts] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_posts, 0) . "</li>\n");

                    dibi::update(Tables::USERS_TABLE, ['user_posts' => 0])
                        ->where('[user_id] = %i', $row->user_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // sync user topic counter

                echo('<p class="gen"><b>' . $lang['Synchronize_user_topic_counter'] . "</b></p>\n");

                $rows = dibi::select(['u.user_id', 'u.username', 'u.user_topics'])
                    ->select('COUNT(t.topic_id)')
                    ->as('new_counter')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->innerJoin(Tables::TOPICS_TABLE)
                    ->as('t')
                    ->on('[u.user_id] = [t.topic_poster]')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->groupBy('u.user_id')
                    ->groupBy('u.username')
                    ->groupBy('u.user_topics')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    $result_array[] = $row->user_id;

                    if ($row->new_counter !== $row->user_topics) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_topics, $row->new_counter) . "</li>\n");

                        dibi::update(Tables::USERS_TABLE, ['user_topics' => $row->new_counter])
                            ->where('[user_id] = %i', $row->user_id)
                            ->execute();
                    }
                }

                // All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username', 'user_topics'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_topics] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username', 'user_topics'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_topics] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }

                    echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_topics, 0) . "</li>\n");

                    dibi::update(Tables::USERS_TABLE, ['user_topics' => 0])
                        ->where('[user_id] = %i', $row->user_id)
                        ->execute();
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // sync user thanks counter

                echo('<p class="gen"><b>' . $lang['Synchronize_user_thanks_counter'] . "</b></p>\n");

                $usersManager = $container->getService('UsersManager');

                $rows = dibi::select(['u.user_id', 'u.username', 'u.user_thanks'])
                    ->select('COUNT(t.topic_id)')
                    ->as('new_counter')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->innerJoin(Tables::THANKS_TABLE)
                    ->as('t')
                    ->on('[u.user_id] = [t.user_id]')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->groupBy('u.user_id')
                    ->groupBy('u.username')
                    ->groupBy('u.user_thanks')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    $result_array[] = $row->user_id;

                    if ($row->new_counter !== $row->user_thanks) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_thanks, $row->new_counter) . "</li>\n");

                        $usersManager->updateByPrimary($row->user_id, ['user_thanks' => $row->new_counter]);
                    }
                }

                // All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username', 'user_thanks'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_thanks] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username', 'user_thanks'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_thanks] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_thanks, 0) . "</li>\n");

                    $usersManager->updateByPrimary($row->user_id, ['user_thanks' => 0]);
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                // sync user topic watches counter

                echo('<p class="gen"><b>' . $lang['Synchronize_user_topics_watches_counter'] . "</b></p>\n");

                $usersManager = $container->getService('UsersManager');

                $rows = dibi::select(['u.user_id', 'u.username', 'u.user_topic_watches'])
                    ->select('COUNT(t.topic_id)')
                    ->as('new_counter')
                    ->from(Tables::USERS_TABLE)
                    ->as('u')
                    ->innerJoin(Tables::TOPICS_WATCH_TABLE)
                    ->as('t')
                    ->on('[u.user_id] = [t.user_id]')
                    ->where('[u.user_id] <> %i', ANONYMOUS)
                    ->groupBy('u.user_id')
                    ->groupBy('u.username')
                    ->groupBy('u.user_topic_watches')
                    ->fetchAll();

                $result_array = [];

                foreach ($rows as $row) {
                    $result_array[] = $row->user_id;

                    if ($row->new_counter !== $row->user_topic_watches) {
                        if (!$list_open) {
                            echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                            echo("<font class=\"gen\"><ul>\n");
                            $list_open = true;
                        }

                        echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_topic_watches, $row->new_counter) . "</li>\n");

                        $usersManager->updateByPrimary($row->user_id, ['user_topic_watches' => $row->new_counter]);
                    }
                }

                // All other users
                if (count($result_array)) {
                    $rows = dibi::select(['user_id', 'username', 'user_topic_watches'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_id] NOT IN %in', $result_array)
                        ->where('[user_topic_watches] <> %i', 0)
                        ->fetchAll();
                } else {
                    $rows = dibi::select(['user_id', 'username', 'user_topic_watches'])
                        ->from(Tables::USERS_TABLE)
                        ->where('[user_topic_watches] <> %i', 0)
                        ->fetchAll();
                }

                foreach ($rows as $row) {
                    if (!$list_open) {
                        echo('<p class="gen">' . $lang['Synchronizing_users'] . ":</p>\n");
                        echo("<font class=\"gen\"><ul>\n");
                        $list_open = true;
                    }
                    echo('<li>' . sprintf($lang['Synchronizing_user_counter'], htmlspecialchars($row->username), $row->user_id, $row->user_topic_watches, 0) . "</li>\n");

                    $usersManager->updateByPrimary($row->user_id, ['user_topic_watches' => 0]);
                }

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

                lock_db(true);
				break;
			case 'synchronize_mod_state': // Synchronize moderator status
				echo('<h1>' . $lang['Synchronize_moderators'] . "</h1>\n");
				lock_db();

				// Getting moderator data
				echo('<p class="gen"><b>' . $lang['Getting_moderators'] . "</b></p>\n");

				$result_array = dibi::select('ug.user_id')
					->from(Tables::USERS_GROUPS_TABLE)
					->as('ug')
					->innerJoin(Tables::AUTH_ACCESS_TABLE)
					->as('aa')
					->on('[ug.group_id] = [aa.group_id]')
					->where('[aa.auth_mod] = %i', 1)
					->where('[ug.user_pending] <> %i', 1)
					->groupBy('ug.user_id')
					->fetchPairs(null, 'user_id');

                if (count($result_array)) {
                    $moderator_list = implode(',', $result_array);
                } else {
                    $moderator_list = '0';
                }

				echo('<p class="gen">' . $lang['Done'] . "</p>\n");

				// Checking non moderators
				echo('<p class="gen"><b>' . $lang['Checking_non_moderators'] . "</b></p>\n");

				$rows = dibi::select(['user_id', 'username'])
					->from(Tables::USERS_TABLE)
					->where('[user_level] = %i', MOD)
					->where('[user_id] NOT IN %in', $result_array)
					->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Updating_mod_state'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
						$list_open = true;
					}
					echo('<li>' . sprintf($lang['Changing_moderator_status'], htmlspecialchars($row->username), $row->user_id) . "</li>\n");

					dibi::update(Tables::USERS_TABLE, ['user_level' => USER])
						->where('[user_id] = %i', $row->user_id)
						->execute();
				}

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking moderators
				echo('<p class="gen"><b>' . $lang['Checking_moderators'] . "</b></p>\n");

				$rows = dibi::select(['user_id', 'username'])
					->from(Tables::USERS_TABLE)
					->where('[user_level] = %i', USER)
					->where('[user_id] IN %in', $result_array)
					->fetchAll();

				foreach ($rows as $row) {
					if (!$list_open) {
						echo('<p class="gen">' . $lang['Updating_mod_state'] . ":</p>\n");
						echo("<font class=\"gen\"><ul>\n");
						$list_open = true;
					}

					echo('<li>' . sprintf($lang['Changing_moderator_status'], htmlspecialchars($row->username), $row->user_id) . "</li>\n");

					dibi::update(Tables::USERS_TABLE, ['user_level' => MOD])
						->where('[user_id] = %i', $row->user_id)
						->execute();
				}

                if ($list_open) {
                    echo("</ul></font>\n");
                    $list_open = false;
                } else {
                    echo($lang['Nothing_to_do']);
                }

				lock_db(true);
				break;
			case 'reset_date': // Reset dates
				echo('<h1>' . $lang['Resetting_future_post_dates'] . "</h1>\n");
				lock_db();

				// Set a variable with the current time
				$time = time();

				// Checking post table
				echo('<p class="gen"><b>' . $lang['Checking_post_dates'] . "</b></p>\n");

				$affected_rows = dibi::update(Tables::POSTS_TABLE, ['post_time' => $time])
					->where('[post_time] > %i', $time)
					->execute(dibi::AFFECTED_ROWS);

                if ($affected_rows === 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking private messages table
				echo('<p class="gen"><b>' . $lang['Checking_pm_dates'] . "</b></p>\n");

				$affected_rows = dibi::update(Tables::PRIVATE_MESSAGE_TABLE, ['privmsgs_date' => $time])
					->where('[privmsgs_date] > %i', $time)
					->execute(dibi::AFFECTED_ROWS);

                if ($affected_rows === 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking user table (last e-mail)
				echo('<p class="gen"><b>' . $lang['Checking_email_dates'] . "</b></p>\n");

				$affected_rows = dibi::update(Tables::USERS_TABLE, ['user_email_time' => $time])
					->where('[user_email_time] > %i', $time)
					->execute(dibi::AFFECTED_ROWS);

                if ($affected_rows === 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
                } elseif ($affected_rows > 1) {
                    echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
                } else {
                    echo($lang['Nothing_to_do']);
                }

				// Checking user table (last login attempt)
                if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 19) {
					echo('<p class="gen"><b>' . $lang['Checking_login_dates'] . "</b></p>\n");

					$affected_rows = dibi::update(Tables::USERS_TABLE, ['user_last_login_try' => $time])
						->where('[user_last_login_try] > %i', $time)
						->execute(dibi::AFFECTED_ROWS);

					if ($affected_rows === 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
					} elseif ($affected_rows > 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
					} else {
						echo($lang['Nothing_to_do']);
					}
				}

				// Checking search table (search time)
                if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 20) {
                    echo('<p class="gen"><b>' . $lang['Checking_search_dates'] . "</b></p>\n");


					$affected_rows = dibi::delete(Tables::SEARCH_TABLE)
						->where('[search_time] > %i', $time)
						->execute(dibi::AFFECTED_ROWS);

					if ($affected_rows === 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_row'], $affected_rows) . "</p>\n");
					} elseif ($affected_rows > 1) {
						echo('<p class="gen">' . sprintf($lang['Affected_rows'], $affected_rows) . "</p>\n");
					} else {
						echo($lang['Nothing_to_do']);
					}
				}

				lock_db(true);
				break;
			case 'reset_sessions': // Reset sessions
				echo('<h1>' . $lang['Resetting_sessions'] . "</h1>\n");
				lock_db();

				// Deleting tables
				echo('<p class="gen"><b>' . $lang['Deleting_session_tables'] . "</b></p>\n");

				dibi::query('TRUNCATE TABLE %n', Tables::SESSIONS_TABLE);
				dibi::query('TRUNCATE TABLE %n', Tables::SEARCH_TABLE);

				echo('<p class="gen">' . $lang['Done'] . "</p>\n");

				// Restore session data of current user to prevent getting thrown out of the admin panel
				echo('<p class="gen"><b>' . $lang['Restoring_session'] . "</b></p>\n");

				$time = time();

				// Set Variables
				$insertData = [
					'session_id' => $userdata['session_id'],
					'session_user_id' => $userdata['user_id'],
					'session_start' => $time,
					'session_time' => $time,
					'session_ip' => $userdata['session_ip'],
					'session_page' => $userdata['session_page'],
					'session_logged_in' => $userdata['session_logged_in'],
					'session_admin' => 1
				];

				dibi::insert(Tables::SESSIONS_TABLE, $insertData)->execute();

				echo('<p class="gen">' . $lang['Done'] . "</p>\n");

				lock_db(true);
				break;
			case 'check_db': // Check database
				echo('<h1>' . $lang['Checking_db'] . "</h1>\n");

				lock_db();
				echo('<p class="gen"><b>' . $lang['Checking_tables'] . ":</b></p>\n");
				echo("<font class=\"gen\"><ul>\n");
				$list_open = true;

				$tables = Tables::getTables();

				foreach ($tables as $table) {
                    $row = dibi::query('CHECK TABLE %n', $table)->fetch();

                    if ($row) {
                        if ($row->Msg_type === 'status') {
                            echo("<li>$table: " . $lang['Table_OK'] . "</li>\n");
                        } else { //  We got an error
							// Check whether the error results from HEAP-table type
                            $row2 = dibi::query('SHOW TABLE STATUS LIKE %~like~', $table)->fetch();

                            if ((isset($row2->Type) && $row2->Type === 'HEAP') || (isset($row2->Engine) && ($row2->Engine === 'HEAP' || $row2->Engine === 'MEMORY'))) {
								// Table is from HEAP-table type
								echo("<li>$table: " . $lang['Table_HEAP_info'] . "</li>\n");
                            } else {
                                echo("<li><b>$table:</b> " . htmlspecialchars($row->Msg_text) . "</li>\n");
                            }
						}
					}
				}

				echo("</ul></font>\n");

				$list_open = false;

				lock_db(true);

				break;
			case 'repair_db': // Repair database
				echo('<h1>' . $lang['Repairing_db'] . "</h1>\n");

				lock_db();

				echo('<p class="gen"><b>' . $lang['Repairing_tables'] . ":</b></p>\n");
				echo("<font class=\"gen\"><ul>\n");

				$list_open = true;

				$tables = Tables::getTables();

				foreach ($tables as $table) {
                    $row = dibi::query('REPAIR TABLE %n', $table)->fetch();

                    if ($row) {
                        if ($row->Msg_type === 'status') {
                            echo("<li>$table: " . $lang['Table_OK'] . "</li>\n");
                        } else { //  We got an error
							// Check whether the error results from HEAP-table type
                            $row2 = dibi::query('SHOW TABLE STATUS LIKE %~like~', $table)->fetch();

                            if ((isset($row2->Type) && $row2->Type === 'HEAP') || (isset($row2->Engine) && ($row2->Engine === 'HEAP' || $row2->Engine === 'MEMORY'))) {
								// Table is from HEAP-table type
								echo("<li>$table: " . $lang['Table_HEAP_info'] . "</li>\n");
							} else {
								echo("<li><b>$table:</b> " . htmlspecialchars($row->Msg_text) . "</li>\n");
							}
						}
					}
				}
				echo("</ul></font>\n");
                $list_open = false;
                lock_db(true);
				break;
			case 'optimize_db': // Optimize database
				echo('<h1>' . $lang['Optimizing_db'] . "</h1>\n");

				lock_db();
				$old_stat = get_table_statistic();
				echo('<p class="gen"><b>' . $lang['Optimizing_tables'] . ":</b></p>\n");
				echo("<font class=\"gen\"><ul>\n");
                $list_open = true;

                $tables = Tables::getTables();

				foreach ($tables as $table) {
					$row = dibi::query('OPTIMIZE TABLE %n', $table)->fetch();

                    if ($row) {
                        if ($row->Msg_type === 'status') {
                            echo("<li>$table: " . $lang['Table_OK'] . "</li>\n");
                        } else {//  We got an error
                            // Check whether the error results from HEAP-table type

                            $row2 = dibi::query('SHOW TABLE STATUS LIKE %~like~', $table)->fetch();

                            if ((isset($row2->Type) && $row2->Type === 'HEAP') || (isset($row2->Engine) && ($row2->Engine === 'HEAP' || $row2->Engine === 'MEMORY'))) {
                                // Table is from HEAP-table type
                                echo("<li>$table: " . $lang['Table_HEAP_info'] . "</li>\n");
                            } else {
                                echo("<li><b>$table:</b> " . htmlspecialchars($row->Msg_text) . "</li>\n");
                            }
                        }
					}
				}
				echo("</ul></font>\n");
				$list_open = false;
				$new_stat = get_table_statistic();
				$reduction_absolute = $old_stat['core']['size'] - $new_stat['core']['size'];
				$reduction_percent = ($reduction_absolute / $old_stat['core']['size']) * 100;
				echo('<p class="gen">' . sprintf($lang['Optimization_statistic'], get_formatted_filesize($old_stat['core']['size']), get_formatted_filesize($new_stat['core']['size']), get_formatted_filesize(abs($reduction_absolute)), $reduction_percent) . "</b></p>\n");
				lock_db(true);
				break;
			case 'reset_auto_increment': // Reset autoincrement values
				echo('<h1>' . $lang['Reset_ai'] . "</h1>\n");
				lock_db();
				echo('<p class="gen"><b>' . $lang['Reset_ai'] . "...</b></p>\n");
				echo("<font class=\"gen\"><ul>\n");

				set_autoincrement(Tables::BAN_LIST_TABLE, 'ban_id', 8);
				set_autoincrement(Tables::CATEGORIES_TABLE, 'cat_id', 8);
				set_autoincrement(Tables::DISS_ALLOW_TABLE, 'disallow_id', 8);
				set_autoincrement(Tables::PRUNE_TABLE, 'prune_id', 8);
				set_autoincrement(Tables::GROUPS_TABLE, 'group_id', 8, false);
				set_autoincrement(Tables::POSTS_TABLE, 'post_id', 8);
				set_autoincrement(Tables::PRIVATE_MESSAGE_TABLE, 'privmsgs_id', 8);
				set_autoincrement(Tables::RANKS_TABLE, 'rank_id', 5);
				set_autoincrement(Tables::SEARCH_WORD_TABLE, 'word_id', 8);
				set_autoincrement(Tables::SMILEYS_TABLE, 'smilies_id', 5);
				set_autoincrement(Tables::THEMES_TABLE, 'themes_id', 8);
				set_autoincrement(Tables::TOPICS_TABLE, 'topic_id', 8);
				set_autoincrement(Tables::VOTE_DESC_TABLE, 'vote_id', 8);
				set_autoincrement(Tables::WORDS_TABLE, 'word_id', 8);

				echo("</ul></font>\n");
				$list_open = false;

				lock_db(true);
				break;
			case 'heap_convert': // Convert session table to HEAP
				echo('<h1>' . $lang['Reset_ai'] . "</h1>\n");

				lock_db();

				echo('<p class="gen"><b>' . $lang['Converting_heap'] . "...</b></p>\n");

				// First check for current table size
				$sessionCount = dibi::select('Count(*)')
					->as('count')
					->from(Tables::SESSIONS_TABLE)
					->fetchSingle();

				if ($sessionCount === false) {
					throw_error("Couldn't get session data!");
				}

				// Table is to big - so delete some records
				if ($sessionCount > HEAP_SIZE) {
					$deleteFluent = dibi::delete(Tables::SESSIONS_TABLE)
						->where('[session_id] != %s', $userdata['session_id']);

					// When using MySQL 4: delete only the oldest records
					if (Config::DBMS === 'mysql4') {
						$deleteFluent->orderBy('session_start')
							->limit($sessionCount - HEAP_SIZE);
					}

					$deleteFluent->execute();
				}

				dibi::query('ALTER TABLE %n ENGINE = HEAP, MAX_ROWS = %i', Tables::SESSIONS_TABLE, HEAP_SIZE);

				lock_db(true);
				break;
			case 'unlock_db': // Unlock the database
				echo('<h1>' . $lang['Unlocking_db'] . "</h1>\n");
				lock_db(true, true, true);
				break;
			default:
				echo('<p class="gen">' . $lang['function_unknown'] . "</p>\n");
		}
		echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php') . '">' . $lang['Back_to_DB_Maintenance'] . "</a></p>\n");
		// Send Information about processing time
		echo('<p class="gensmall">' . sprintf($lang['Processing_time'], getmicrotime() - $timer) . '</p>');
		ob_start();
		break;
	default:
        $template->setFileNames(['body' => 'admin/dbmtnc_list_body.tpl']);

        $template->assignVars(
            [
                'L_DBMTNC_TITLE'         => $lang['DB_Maintenance'],
                'L_DBMTNC_TEXT'          => $lang['DB_Maintenance_Description'],
                'L_FUNCTION'             => $lang['Function'],
                'L_FUNCTION_DESCRIPTION' => $lang['Function_Description']
            ]
        );

        //
		// OK, let's list the functions
		//
        foreach ($mtnc as $value) {
            if (count($value) && check_condition($value[4])) {
                if ($value[0] === '--') {
                    $template->assignBlockVars('function.spaceRow', []);
                } else {
                    $template->assignBlockVars('function',
                        [
                            'FUNCTION_NAME'        => $value[1],
                            'FUNCTION_DESCRIPTION' => $value[2],

                            'U_FUNCTION_URL' => Session::appendSid('admin_db_maintenance.php?mode=start&function=' . $value[0])
                        ]
                    );
                }
			}
		}

        $template->pparse('body');
		break;
}

require_once '.' . $sep . 'page_footer_admin.php';
?>
