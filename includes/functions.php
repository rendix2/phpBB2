<?php
/***************************************************************************
 *                               functions.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions.php 8377 2008-02-10 12:52:05Z acydburn $
 *
 *
 ***************************************************************************/

use Dibi\Row;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *
 ***************************************************************************/

/**
 * @param string $mode
 *
 * @return Row|false
 */
function get_db_stat($mode)
{
    switch ($mode) {
        case 'usercount':
            return dibi::select('COUNT(user_id) - 1')
                ->as('total')
                ->from(USERS_TABLE)
                ->fetchSingle();

        case 'newestuser':
            return dibi::select(['user_id', 'username'])
                ->from(USERS_TABLE)
                ->where('user_id <> %i', ANONYMOUS)
                ->orderBy('user_id', dibi::DESC)
                ->fetch();

        case 'postcount':
            return dibi::select('SUM(forum_posts)')
                ->as('post_total')
                ->from(FORUMS_TABLE)
                ->fetchSingle();

        case 'topiccount':
            return dibi::select('SUM(forum_topics)')
                ->as('topic_total')
                ->from(FORUMS_TABLE)
                ->fetchSingle();
    }
}

// added at phpBB 2.0.11 to properly format the username
function phpbb_clean_username($username)
{
	$username = substr(htmlspecialchars(str_replace("\'", "'", trim($username)), ENT_QUOTES), 0, 25);
	$username = rtrim($username, "\\");
	$username = str_replace("'", "\'", $username);

	return $username;
}

/**
* Our own generator of random values
* This uses a constantly changing value as the base for generating the values
* The board wide setting is updated once per page if this code is called
* With thanks to Anthrax101 for the inspiration on this one
* Added in phpBB 2.0.20
*/
function dss_rand()
{
	global $board_config, $dss_seeded;
	global $storage;

	$val = $board_config['rand_seed'] . microtime();
	$val = md5($val);
	$board_config['rand_seed'] = md5($board_config['rand_seed'] . $val . 'a');

    if ($dss_seeded !== true) {
        dibi::update(CONFIG_TABLE, ['config_value' => $board_config['rand_seed']])
            ->where('config_name = %s', 'rand_seed')
            ->execute();

        $cache = new Cache($storage, CONFIG_TABLE);
        $cache->remove(CONFIG_TABLE);

        $dss_seeded = true;
    }

	return substr($val, 4, 16);
}

/**
 *
 * Get Userdata, $user can be username or user_id. If force_str is true, the username will be forced.
 *
 * TODO try to force use by user_id, NOT username
 *
 * @param int  $user_id
 * @param bool $force_str
 *
 * @return Row|false
 */
function get_userdata($user_id, $force_str = false)
{
    if (!is_numeric($user_id) || $force_str) {
        $user_id = phpbb_clean_username($user_id);
    } else {
        $user_id = (int)$user_id;
    }

    $user = dibi::select('*')
        ->from(USERS_TABLE);

    if (is_int($user_id)) {
        $user->where('user_id = %i', $user_id);
    } else {
        $user->where('username = %s', $user_id);
    }

    $user = $user->where('user_id <> %i', ANONYMOUS)->fetch();

	return $user;
}

function make_jumpbox($action, $match_forum_id = 0)
{
    /**
     * @var Template $template
     */
	global $template;
	global $userdata, $lang, $SID;

//	$is_auth = auth(AUTH_VIEW, AUTH_LIST_ALL, $userdata);

    $boxstring = '';

    $categories = dibi::select(['c.cat_id', 'c.cat_title', 'c.cat_order'])
        ->from(CATEGORIES_TABLE)
        ->as('c')
        ->innerJoin(FORUMS_TABLE)
        ->as('f')
        ->on('f.cat_id = c.cat_id')
        ->groupBy('c.cat_id')
        ->groupBy('c.cat_title')
        ->groupBy(' c.cat_order')
        ->orderBy('c.cat_order')
        ->fetchAll();

    if (count($categories)) {
        $forums = dibi::select('*')
            ->from(FORUMS_TABLE)
            ->orderBy('cat_id')
            ->orderBy('forum_order')
            ->fetchAll();

		$boxstring = '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"><option value="-1">' . $lang['Select_forum'] . '</option>';

        if (count($forums)) {
            foreach ($categories as $category) {
				$boxstring_forums = '';

				foreach ($forums as $forum) {
					if ($forum->cat_id === $category->cat_id && $forum->auth_view <= AUTH_REG) {

//					if ($forum_rows[$j]['cat_id'] == $category_rows[$i]['cat_id'] && $is_auth[$forum_rows[$j]['forum_id']]['auth_view'] )
//					{
						$selected = $forum->forum_id === $match_forum_id ? 'selected="selected"' : '';
						$boxstring_forums .=  '<option value="' . $forum->forum_id . '"' . $selected . '>' . htmlspecialchars($forum->forum_name, ENT_QUOTES) . '</option>';
					}
				}

                if ($boxstring_forums !== '') {
                    $boxstring .= '<optgroup label="'.$category->cat_title .'">' . $boxstring_forums . '</optgroup>';
				}
			}
		}

		$boxstring .= '</select>';
	} else {
		$boxstring .= '<select name="' . POST_FORUM_URL . '" onchange="if (this.options[this.selectedIndex].value != -1){ forms[\'jumpbox\'].submit() }"></select>';
	}

	// Let the jumpbox work again in sites having additional session id checks.
//	if (!empty($SID) )
//	{
		$boxstring .= '<input type="hidden" name="sid" value="' . $userdata['session_id'] . '" />';
//	}

    $template->setFileNames(['jumpbox' => 'jumpbox.tpl']);

    $template->assignVars(
        [
            'L_GO'           => $lang['Go'],
            'L_JUMP_TO'      => $lang['Jump_to'],
            'L_SELECT_FORUM' => $lang['Select_forum'],

            'S_JUMPBOX_SELECT' => $boxstring,
            'S_JUMPBOX_ACTION' => Session::appendSid($action)
        ]
    );
    $template->assignVarFromHandle('JUMPBOX', 'jumpbox');
}

//
// Initialise user settings on page load
function init_userprefs($userdata)
{
	global $board_config, $theme, $images;
	global $template, $lang, $phpbb_root_path;
	global $storage;

    $default_lang = '';
    $sep = DIRECTORY_SEPARATOR;

    if ($userdata['user_id'] !== ANONYMOUS) {
        if (!empty($userdata['user_lang'])) {
            $default_lang = ltrim(basename(rtrim($userdata['user_lang'])), "'");
        }

        if (!empty($userdata['user_dateformat'])) {
            $board_config['default_dateformat'] = $userdata['user_dateformat'];
        }

        if (isset($userdata['user_timezone'])) {
            $board_config['board_timezone'] = $userdata['user_timezone'];
        }
    } else {
        $default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
    }

    if (!file_exists(@phpbb_realpath($phpbb_root_path . 'language' . $sep . 'lang_' . $default_lang . $sep . 'lang_main.php'))) {
		if ($userdata['user_id'] !== ANONYMOUS) {
			// For logged in users, try the board default language next
			$default_lang = ltrim(basename(rtrim($board_config['default_lang'])), "'");
		} else {
			// For guests it means the default language is not present, try english
			// This is a long shot since it means serious errors in the setup to reach here,
			// but english is part of a new install so it's worth us trying
			$default_lang = 'english';
		}

        if (!file_exists(@phpbb_realpath($phpbb_root_path . 'language' . $sep . 'lang_' . $default_lang . $sep . 'lang_main.php'))) {
            message_die(CRITICAL_ERROR, 'Could not locate valid language pack');
        }
	}

	// If we've had to change the value in any way then let's write it back to the database
	// before we go any further since it means there is something wrong with it
	if ($userdata['user_id'] !== ANONYMOUS && $userdata['user_lang'] !== $default_lang) {
	    dibi::update(USERS_TABLE, ['user_lang' => $default_lang])
            ->where('user_lang = %s', $userdata['user_lang'])
            ->execute();

		$userdata['user_lang'] = $default_lang;
	} elseif ($userdata['user_id'] === ANONYMOUS && $board_config['default_lang'] !== $default_lang) {
        dibi::update(CONFIG_TABLE, ['config_value' => $default_lang])
            ->where('config_name = %s', 'default_lang')
            ->execute();

        $cache = new Cache($storage, CONFIG_TABLE);
        $cache->remove(CONFIG_TABLE);
	}

	$board_config['default_lang'] = $default_lang;

    require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_main.php';

	if (defined('IN_ADMIN')) {
        if (!file_exists(@phpbb_realpath($phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_admin.php'))) {
			$board_config['default_lang'] = 'english';
		}

        require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_admin.php';
	}

	//
	// Set up style
	//
	if (!$board_config['override_user_style']) {
		if ($userdata['user_id'] !== ANONYMOUS && $userdata['user_style'] > 0) {
			if ($theme = setup_style($userdata['user_style'])) {
				return;
			}
		}
	}

	$theme = setup_style($board_config['default_style']);
}

function setup_style($style)
{
	global $board_config, $template, $images, $phpbb_root_path;
	global $storage;

	$sep = DIRECTORY_SEPARATOR;

	$cache = new Cache($storage, THEMES_TABLE);

	$key = THEMES_TABLE . '_'. (int)$style;
	$cachedTheme = $cache->load($key);

	if ($cachedTheme !== null) {
        $theme = $cachedTheme;
	} else {
        $theme = dibi::select('*')
            ->from(THEMES_TABLE)
            ->where('themes_id = %i', (int)$style)
            ->fetch();

        $cache->save($key, $theme);
    }

	if (!$theme) {
        if ($board_config['default_style'] === $style) {
            message_die(CRITICAL_ERROR, 'Could not set up default theme');
        }

	    $default_theme = dibi::select('*')
            ->from(THEMES_TABLE)
            ->where('themes_id = %i',(int) $board_config['default_style'])
            ->fetch();

	    if ($default_theme) {
	        dibi::update(USERS_TABLE, ['user_style' => (int) $board_config['default_style']])
                ->where('user_style = %s', $style)
                ->execute();
        } else {
            message_die(CRITICAL_ERROR, "Could not get theme data for themes_id [$style]");
        }
	}

    $template_path = 'templates' . $sep;
	$template_name = $theme->template_name;

	$template = new Template($phpbb_root_path . $template_path . $template_name);

	if ($template) {
		$current_template_path = $template_path . $template_name;
        @require_once $phpbb_root_path . $template_path . $template_name . $sep . $template_name . '.cfg';

		if (!defined('TEMPLATE_CONFIG')) {
			message_die(CRITICAL_ERROR, "Could not open $template_name template config file", '', __LINE__, __FILE__);
		}

        $img_lang = file_exists(@phpbb_realpath($phpbb_root_path . $current_template_path . $sep . 'images' . $sep . 'lang_' . $board_config['default_lang'])) ? $board_config['default_lang'] : 'english';

		foreach ($images as $key => $value) {
			if (!is_array($value)) {
				$images[$key] = str_replace('{LANG}', 'lang_' . $img_lang, $value);
			}
		}
	}

	return $theme;
}

function encode_ip($dotquad_ip)
{
	$ip_sep = explode('.', $dotquad_ip);
	return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
}

function decode_ip($int_ip)
{
	$hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
	return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

/**
 * Create date/time from format and timezone
 *
 * @param string $format
 * @param int    $time
 * @param string $time_zone
 *
 * @return string
 * @throws Exception
 */
function create_date($format, $time, $time_zone)
{
    $started = new DateTime('now', new DateTimeZone($time_zone));
    $started->setTimestamp((int)$time);
    return $started->format($format);
}

//
// Pagination routine, generates
// page number sequence
//
function generate_pagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true)
{
	global $lang;

	$total_pages = ceil($num_items/$per_page);

	if ($total_pages === 1) {
		return '';
	}

	$on_page = floor($start_item / $per_page) + 1;

	$page_string = '';
	if ($total_pages > 10) {
		$init_page_max = $total_pages > 3 ? 3 : $total_pages;

		for ($i = 1; $i < $init_page_max + 1; $i++) {
			$page_string .= ( $i === $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ($i <  $init_page_max) {
				$page_string .= ', ';
			}
		}

		if ($total_pages > 3) {
			if ($on_page > 1  && $on_page < $total_pages) {
				$page_string .= $on_page > 5 ? ' ... ' : ', ';

				$init_page_min = $on_page > 4 ? $on_page : 5;
				$init_page_max = $on_page < $total_pages - 4 ? $on_page : $total_pages - 4;

				for ($i = $init_page_min - 1; $i < $init_page_max + 2; $i++) {
					$page_string .= $i === $on_page ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

					if ($i <  $init_page_max + 1) {
						$page_string .= ', ';
					}
				}

				$page_string .= $on_page < $total_pages - 4 ? ' ... ' : ', ';
			} else {
				$page_string .= ' ... ';
			}

			for ($i = $total_pages - 2; $i < $total_pages + 1; $i++) {
				$page_string .= $i === $on_page ? '<b>' . $i . '</b>'  : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

				if ($i <  $total_pages) {
					$page_string .= ', ';
				}
			}
		}
	}
	else {
		for ($i = 1; $i < $total_pages + 1; $i++) {
			$page_string .= $i === $on_page ? '<b>' . $i . '</b>' : '<a href="' . Session::appendSid($base_url . '&amp;start=' . ( ( $i - 1 ) * $per_page ) ) . '">' . $i . '</a>';

			if ($i <  $total_pages) {
				$page_string .= ', ';
			}
		}
	}

    if ($add_prevnext_text) {
        if ($on_page > 1) {
            $page_string = ' <a href="' . Session::appendSid($base_url . '&amp;start=' . (($on_page - 2) * $per_page)) . '">' . $lang['Previous'] . '</a>&nbsp;&nbsp;' . $page_string;
        }

        if ($on_page < $total_pages) {
            $page_string .= '&nbsp;&nbsp;<a href="' . Session::appendSid($base_url . '&amp;start=' . ($on_page * $per_page)) . '">' . $lang['Next'] . '</a>';
        }

    }

	$page_string = $lang['Goto_page'] . ' ' . $page_string;

	return $page_string;
}

//
// Obtain list of naughty words and build preg style replacement arrays for use by the
// calling script, note that the vars are passed as references this just makes it easier
// to return both sets of arrays
//
function obtain_word_list(&$orig_word, &$replacement_word)
{
    global $storage;

    $cache = new Cache($storage, WORDS_TABLE);

    $cachedWords = $cache->load(WORDS_TABLE);

	//
	// Define censored word matches
	//
    if ($cachedWords !== null) {
        $words = $cachedWords;
    } else {
        $words = dibi::select(['word', 'replacement'])
            ->from(WORDS_TABLE)
            ->fetchPairs('word', 'replacement');

        $cache->save(WORDS_TABLE, $words);
    }

    foreach ($words as $word => $replacement) {
        $orig_word[] = '#\b(' . str_replace('\*', '\w*?', preg_quote($word, '#')) . ')\b#i';
        $replacement_word[] = $replacement;
	}

	return true;
}

//
// This is general replacement for die(), allows templated
// output in users (or default) language, etc.
//
// $msg_code can be one of these constants:
//
// GENERAL_MESSAGE : Use for any simple text message, eg. results 
// of an operation, authorisation failures, etc.
//
// GENERAL ERROR : Use for any error which occurs _AFTER_ the 
// common.php include and session code, ie. most errors in 
// pages/functions
//
// CRITICAL_MESSAGE : Used when basic config data is available but 
// a session may not exist, eg. banned users
//
// CRITICAL_ERROR : Used when config data cannot be obtained, eg
// no database connection. Should _not_ be used in 99.5% of cases
//
function message_die($msg_code, $msg_text = '', $msg_title = '', $err_line = '', $err_file = '')
{
	global $db, $template, $board_config, $theme, $lang, $phpbb_root_path, $gen_simple_header, $images;
	global $userdata, $user_ip, $session_length;

	$sep = DIRECTORY_SEPARATOR;

    if (defined('HAS_DIED')) {
        die("message_die() was called multiple times. This isn't supposed to happen. Was message_die() used in page_tail.php?");
    }
	
	define('HAS_DIED', 1);

    $debug_text = '';
	
	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent 
	// subsequent queries from overwriting the status of sql_error()
	//
    if (DEBUG && ($msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR)) {
        if ($err_line !== '' && $err_file !== '') {
            $debug_text .= '<br /><br />Line : ' . $err_line . '<br />File : ' . basename($err_file);
        }
    }

    if (empty($userdata) && ($msg_code === GENERAL_MESSAGE || $msg_code === GENERAL_ERROR)) {
        $userdata = Session::pageStart($user_ip, PAGE_INDEX);
        init_userprefs($userdata);
    }

    switch ($msg_code) {
        case GENERAL_MESSAGE:
            $page_title = $lang['Information'];

            if ($msg_title === '') {
                $msg_title = $lang['Information'];
            }
            break;

        case CRITICAL_MESSAGE:
            $page_title = $lang['Critical_Information'];

            if ($msg_title === '') {
                $msg_title = $lang['Critical_Information'];
            }
            break;

        case GENERAL_ERROR:
            $page_title = $lang['An_error_occured'];

            if ($msg_text === '') {
                $msg_text = $lang['An_error_occured'];
            }

            if ($msg_title === '') {
                $msg_title = $lang['General_Error'];
            }
            break;

        case CRITICAL_ERROR:
            $page_title = $lang['A_critical_error'];

            //
            // Critical errors mean we cannot rely on _ANY_ DB information being
            // available so we're going to dump out a simple echo'd statement
            //

            require_once $phpbb_root_path . 'language' . $sep . 'lang_english' . $sep . 'lang_main.php';
            if ($msg_text === '') {
                $msg_text = $lang['A_critical_error'];
            }

            if ($msg_title === '') {
                $msg_title = 'phpBB : <b>' . $lang['Critical_Error'] . '</b>';
            }
            break;
    }

	//
	// If the header hasn't been output then do it
	//
    if (!defined('HEADER_INC') && $msg_code !== CRITICAL_ERROR) {
        if (empty($lang)) {
            if (!empty($board_config['default_lang'])) {
                require_once $phpbb_root_path . 'language' . $sep . 'lang_' . $board_config['default_lang'] . $sep . 'lang_main.php';
            } else {
                require_once $phpbb_root_path . 'language' . $sep . 'lang_english' . $sep . 'lang_main.php';
            }
        }

        if (empty($template) || empty($theme)) {
            $theme = setup_style($board_config['default_style']);
        }

        //
        // Load the Page Header
        //
        if (defined('IN_ADMIN')) {
            require_once $phpbb_root_path . 'admin' . $sep . 'page_header_admin.php';
        } else {
            require_once $phpbb_root_path . 'includes' . $sep . 'page_header.php';
        }
	}

	//
	// Add on DEBUG info if we've enabled debug mode and this is an error. This
	// prevents debug info being output for general messages should DEBUG be
	// set true by accident (preventing confusion for the end user!)
    //
    if (DEBUG && ($msg_code === GENERAL_ERROR || $msg_code === CRITICAL_ERROR)) {
        if ($debug_text !== '') {
            $msg_text .= $debug_text . '<br /><br /><b><u>DEBUG MODE</u></b>';
        }
    }

    if ($msg_code !== CRITICAL_ERROR) {
        if (!empty($lang[$msg_text])) {
            $msg_text = $lang[$msg_text];
        }

        if (defined('IN_ADMIN')) {
            $template->setFileNames(['message_body' => 'admin/admin_message_body.tpl']);
        } else {
            $template->setFileNames(['message_body' => 'message_body.tpl']);
        }

        $template->assignVars(
            [
                'MESSAGE_TITLE' => $msg_title,
                'MESSAGE_TEXT'  => $msg_text
            ]
        );
        $template->pparse('message_body');

        if (defined('IN_ADMIN')) {
            require_once $phpbb_root_path . 'admin' . $sep . 'page_footer_admin.php';
        } else {
            require_once $phpbb_root_path . 'includes' . $sep . 'page_tail.php';
        }
    } else {
        echo "<html>\n<body>\n" . $msg_title . "\n<br /><br />\n" . $msg_text . "</body>\n</html>";
    }

    exit;
}

//
// This function is for compatibility with PHP 4.x's realpath()
// function.  In later versions of PHP, it needs to be called
// to do checks with some functions.  Older versions of PHP don't
// seem to need this, so we'll just return the original value.
// dougk_ff7 <October 5, 2002>
function phpbb_realpath($path)
{
	global $phpbb_root_path;

	return (!@function_exists('realpath') || !@realpath($phpbb_root_path . 'includes/functions.php')) ? $path : @realpath($path);
}

/**
 * @param string $url
 */
function redirect($url)
{
	global $board_config;

	dibi::disconnect();

	if (false !== strpos(urldecode($url), "\n") || false !== strpos(urldecode($url), "\r") || false !== strpos(urldecode($url),
            ';url')) {
		message_die(GENERAL_ERROR, 'Tried to redirect to potentially insecure url.');
	}

	$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
	$server_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['server_name']));
	$server_port = ($board_config['server_port'] !== 80) ? ':' . trim($board_config['server_port']) : '';
	$script_name = preg_replace('#^\/?(.*?)\/?$#', '\1', trim($board_config['script_path']));
	$script_name = ($script_name === '') ? $script_name : '/' . $script_name;
	$url = preg_replace('#^\/?(.*?)\/?$#', '/\1', trim($url));

	// Redirect via an HTML form for PITA webservers
	if (@preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE'))) {
		header('Refresh: 0; URL=' . $server_protocol . $server_name . $server_port . $script_name . $url);
		echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><meta http-equiv="refresh" content="0; url=' . $server_protocol . $server_name . $server_port . $script_name . $url . '"><title>Redirect</title></head><body><div align="center">If your browser does not support meta redirection please click <a href="' . $server_protocol . $server_name . $server_port . $script_name . $url . '">HERE</a> to be redirected</div></body></html>';
		exit;
	}

	// Behave as per HTTP/1.1 spec for others
	header('Location: ' . $server_protocol . $server_name . $server_port . $script_name . $url);
	exit;
}

/**
 * Return formatted string for filesizes
 *
 * copied from phpBB3
 *
 * @param mixed	$value			filesize in bytes
 *								(non-negative number; int, float or string)
 * @param bool	$string_only	true if language string should be returned
 * @param array	$allowed_units	only allow these units (data array indexes)
 *
 * @return mixed					data array if $string_only is false
 */
function get_formatted_filesize($value, $string_only = true, $allowed_units = false)
{
    global $user;

    $available_units = array(
        'tb' => array(
            'min' 		=> 1099511627776, // pow(2, 40)
            'index'		=> 4,
            'si_unit'	=> 'TB',
            'iec_unit'	=> 'TIB',
        ),
        'gb' => array(
            'min' 		=> 1073741824, // pow(2, 30)
            'index'		=> 3,
            'si_unit'	=> 'GB',
            'iec_unit'	=> 'GIB',
        ),
        'mb' => array(
            'min'		=> 1048576, // pow(2, 20)
            'index'		=> 2,
            'si_unit'	=> 'MB',
            'iec_unit'	=> 'MIB',
        ),
        'kb' => array(
            'min'		=> 1024, // pow(2, 10)
            'index'		=> 1,
            'si_unit'	=> 'KB',
            'iec_unit'	=> 'KIB',
        ),
        'b' => array(
            'min'		=> 0,
            'index'		=> 0,
            'si_unit'	=> 'BYTES', // Language index
            'iec_unit'	=> 'BYTES',  // Language index
        ),
    );

    foreach ($available_units as $si_identifier => $unit_info) {
        if (!empty($allowed_units) && $si_identifier !== 'b' && !in_array($si_identifier, $allowed_units, true)) {
            continue;
        }

        if ($value >= $unit_info['min']) {
            $unit_info['si_identifier'] = $si_identifier;

            break;
        }
    }
    unset($available_units);

    for ($i = 0; $i < $unit_info['index']; $i++) {
        $value /= 1024;
    }
    $value = round($value, 2);

    // Default to IEC
    $unit_info['unit'] = $unit_info['iec_unit'];

    if (!$string_only) {
        $unit_info['value'] = $value;

        return $unit_info;
    }

    return $value  . ' ' . $unit_info['unit'];
}

/**
 * TODO this need some improvement..... its very heavy
 * TODO this doeas lots of work.. :( its need some decompose
 *
 * @param int|null $forum_id
 * @param array    $userdata
 * @param array    $board_config
 * @param array    $theme
 * @param array    $lang
 * @param IStorage $storage
 * @param Template $template
 *
 */
function showOnline($forum_id, $userdata, array &$board_config, $theme, array $lang, IStorage $storage, Template $template)
{
    $loggedVisibleOnline = 0;
    $loggedHiddenOnline  = 0;
    $guestsOnline        = 0;

    $onlineUserList = [];

    $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

    $time = new DateTime();
    $time->setTimezone(new DateTimeZone($user_timezone));
    $time->sub(new DateInterval('PT' . ONLINE_TIME_DIFF . 'S'));

    $rows = dibi::select(['u.username', 'u.user_id', 'u.user_allow_viewonline', 'u.user_level', 's.session_logged_in', 's.session_ip'])
        ->from(USERS_TABLE)
        ->as('u')
        ->innerJoin(SESSIONS_TABLE)
        ->as('s')
        ->on('u.user_id = s.session_user_id')
        ->where('s.session_time >= %i', $time->getTimestamp());

    if ($forum_id !== null) {
        $rows->where('s.session_page = %i', $forum_id);
    }

    $rows = $rows->orderBy('u.username', dibi::ASC)
        ->orderBy('s.session_ip', dibi::ASC)
        ->fetchAll();

    $prev_user_id = 0;
    $prev_user_ip = $prev_session_ip = '';

    foreach ($rows as $row) {
        // User is logged in and therefor not a guest
        if ($row->session_logged_in) {
            // Skip multiple sessions for one user
            if ($row->user_id !== $prev_user_id) {
                $style_color = '';

                // decide user colior
                if ($row->user_level === ADMIN) {
                    $row->username = '<b>' . $row->username . '</b>';
                    $style_color = 'style="color:#' . $theme['fontcolor3'] . '"';
                } elseif ($row->user_level === MOD) {
                    $row->username = '<b>' . $row->username . '</b>';
                    $style_color = 'style="color:#' . $theme['fontcolor2'] . '"';
                }

                if ($row->user_allow_viewonline) {
                    $userOnlineLink = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id) . '"' . $style_color .'>' . $row->username . '</a>';
                    $loggedVisibleOnline++;
                } else {
                    $userOnlineLink = '<a href="' . Session::appendSid('profile.php?mode=viewprofile&amp;' . POST_USERS_URL . '=' . $row->user_id) . '"' . $style_color .'><i>' . $row->username . '</i></a>';
                    $loggedHiddenOnline++;
                }

                if ($row->user_allow_viewonline || $userdata['user_level'] === ADMIN) {
                    $onlineUserList[] = $userOnlineLink;
                }
            }

            $prev_user_id = $row->user_id;
        } else {
            // Skip multiple sessions for one user
            if ($row->session_ip !== $prev_session_ip) {
                $guestsOnline++;
            }
        }

        $prev_session_ip = $row->session_ip;
    }

    $onlineUserListString = isset($forum_id) ? $lang['Browsing_forum'] : $lang['Registered_users'];
    $onlineUserListString .= ' ';

    if (empty($onlineUserList)) {
        $onlineUserListString .= $lang['None'];
    } else {
        $onlineUserListString .= implode(', ', $onlineUserList);
    }

    $totalOnlineUsers = $loggedVisibleOnline + $loggedHiddenOnline + $guestsOnline;

    // new record of online users
    if ($totalOnlineUsers > $board_config['record_online_users']) {
        $board_config['record_online_users'] = $totalOnlineUsers;
        $board_config['record_online_date']  = time();

        dibi::update(CONFIG_TABLE, ['config_value' => $totalOnlineUsers])
            ->where('config_name = %s', 'record_online_users')
            ->execute();

        dibi::update(CONFIG_TABLE, ['config_value' => $board_config['record_online_date']])
            ->where('config_name = %s', 'record_online_date')
            ->execute();

        $cache = new Cache($storage, CONFIG_TABLE);
        $cache->remove(CONFIG_TABLE);
    }

    // online users
    if ($totalOnlineUsers === 0) {
        $l_t_user_s = $lang['Online_users_zero_total'];
    } elseif ($totalOnlineUsers === 1) {
        $l_t_user_s = $lang['Online_user_total'];
    } else {
        $l_t_user_s = $lang['Online_users_total'];
    }

    // registered users
    if ($loggedVisibleOnline === 0) {
        $l_r_user_s = $lang['Reg_users_zero_total'];
    } elseif ($loggedVisibleOnline === 1) {
        $l_r_user_s = $lang['Reg_user_total'];
    } else {
        $l_r_user_s = $lang['Reg_users_total'];
    }

    // registered hidden users
    if ($loggedHiddenOnline === 0) {
        $l_h_user_s = $lang['Hidden_users_zero_total'];
    } elseif ($loggedHiddenOnline === 1) {
        $l_h_user_s = $lang['Hidden_user_total'];
    } else {
        $l_h_user_s = $lang['Hidden_users_total'];
    }

    // guests users
    if ($guestsOnline === 0) {
        $l_g_user_s = $lang['Guest_users_zero_total'];
    } elseif ($guestsOnline === 1) {
        $l_g_user_s = $lang['Guest_user_total'];
    } else {
        $l_g_user_s = $lang['Guest_users_total'];
    }

    // finishing the online string
    $l_online_users  = sprintf($l_t_user_s, $totalOnlineUsers);
    $l_online_users .= sprintf($l_r_user_s, $loggedVisibleOnline);
    $l_online_users .= sprintf($l_h_user_s, $loggedHiddenOnline);
    $l_online_users .= sprintf($l_g_user_s, $guestsOnline);

    $template->assignVars(
        [
            'TOTAL_USERS_ONLINE'  => $l_online_users,
            'LOGGED_IN_USER_LIST' => $onlineUserListString,
        ]
    );
}

?>