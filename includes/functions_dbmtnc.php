<?php
/***************************************************************************
 *                           functions_dbmtnc.php
 *                            -------------------
 *   begin                : Fri Feb 07, 2003
 *   copyright            : (C) 2004 Philipp Kordowich
 *                          Parts: (C) 2002 The phpBB Group
 *
 *   part of DB Maintenance Mod 1.3.8
 ***************************************************************************/

use Nette\Caching\Cache;
use Nette\Utils\Finder;

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

// List of tables used
$tables = [
    'auth_access',
    'banlist',
    'categories',
    'config',
    'confirm',
    'disallow',
    'forums',
    'forum_prune',
    'groups',
    'languages',
    'posts',
    'posts_text',
    'privmsgs',
    'privmsgs_text',
    'ranks',
    'search_results',
    'search_wordlist',
    'search_wordmatch',
    'sessions',
    'sessions_keys',
    'smilies',
    'template_cache',
    'themes',
    'themes_name',
    'topics',
    'topics_watch',
    'user_group',
    'users',
    'vote_desc',
    'vote_results',
    'vote_voters',
    'words'
];

// List of configuration data required
$config_data = [
    'dbmtnc_disallow_postcounter',
    'dbmtnc_disallow_rebuild',
    'dbmtnc_rebuildcfg_maxmemory',
    'dbmtnc_rebuildcfg_minposts',
    'dbmtnc_rebuildcfg_php3only',
    'dbmtnc_rebuildcfg_php3pps',
    'dbmtnc_rebuildcfg_php4pps',
    'dbmtnc_rebuildcfg_timeoverwrite',
    'dbmtnc_rebuildcfg_timelimit',
    'dbmtnc_rebuild_end',
    'dbmtnc_rebuild_pos'
];

// Default configuration records - from installation file
$default_config = [
    'config_id'                       => '1',
    'board_disable'                   => '0',
    'sitename'                        => 'yourdomain.com',
    'site_desc'                       => 'A _little_ text to describe your forum',
    'cookie_name'                     => 'phpbb2mysql',
    'cookie_path'                     => '/',
    'cookie_domain'                   => '',
    'cookie_secure'                   => '0',
    'session_length'                  => '3600',
    'allow_html'                      => '0',
    'allow_html_tags'                 => 'b,i,u,pre',
    'allow_bbcode'                    => '1',
    'allow_smilies'                   => '1',
    'allow_sig'                       => '1',
    'allow_namechange'                => '0',
    'allow_theme_create'              => '0',
    'allow_avatar_local'              => '0',
    'allow_avatar_remote'             => '0',
    'allow_avatar_upload'             => '0',
    'enable_confirm'                  => '0',
    'override_user_style'             => '0',
    'posts_per_page'                  => '15',
    'topics_per_page'                 => '50',
    'members_per_page'                => '50',
    'group_members_per_page'          => '50',
    'pm_per_page'                     => '50',
    'hot_threshold'                   => '25',
    'max_poll_options'                => '10',
    'max_sig_chars'                   => '255',
    'max_inbox_privmsgs'              => '50',
    'max_sentbox_privmsgs'            => '25',
    'max_savebox_privmsgs'            => '50',
    'board_email_sig'                 => 'Thanks, The Management',
    'board_email'                     => 'youraddress@yourdomain.com',
    'smtp_delivery'                   => '0',
    'smtp_host'                       => '',
    'smtp_username'                   => '',
    'smtp_password'                   => '',
    'sendmail_fix'                    => '0',
    'require_activation'              => '0',
    'flood_interval'                  => '15',
    'board_email_form'                => '0',
    'avatar_filesize'                 => '6144',
    'avatar_max_width'                => '80',
    'avatar_max_height'               => '80',
    'avatar_path'                     => 'images/avatars',
    'avatar_gallery_path'             => 'images/avatars/gallery',
    'smilies_path'                    => 'images/smiles',
    'default_style'                   => '1',
    'default_dateformat'              => 'D M d, Y g:i a',
    'board_timezone'                  => '0',
    'prune_enable'                    => '1',
    'privmsg_disable'                 => '0',
    'gzip_compress'                   => '0',
    'coppa_fax'                       => '',
    'coppa_mail'                      => '',
    'record_online_users'             => '0',
    'record_online_date'              => '0',
    'server_name'                     => 'www.myserver.tld',
    'server_port'                     => '80',
    'script_path'                     => '/phpBB2/',
    'version'                         => '.0.0',
    'default_lang'                    => 'english',
    'board_startdate'                 => '0',
    'enable_topic_review'             => '0',

    // DB Maintenance specific entries
    'dbmtnc_rebuild_end'              => '0',
    'dbmtnc_rebuild_pos'              => '-1',
    'dbmtnc_rebuildcfg_maxmemory'     => '500',
    'dbmtnc_rebuildcfg_minposts'      => '3',
    'dbmtnc_rebuildcfg_php3only'      => '0',
    'dbmtnc_rebuildcfg_php3pps'       => '1',
    'dbmtnc_rebuildcfg_php4pps'       => '8',
    'dbmtnc_rebuildcfg_timelimit'     => '240',
    'dbmtnc_rebuildcfg_timeoverwrite' => '0',
    'dbmtnc_disallow_postcounter'     => '0',
    'dbmtnc_disallow_rebuild'         => '0',

    'complex_acp_pw' => '1',
];

$sep = DIRECTORY_SEPARATOR;

// append data added in later versions
if (isset($board_config, $board_config['version'])) {
    $phpbb_version = explode('.', substr($board_config['version'], 1));
} else {
    // Fallback for ERC
    $phpbb_version = [0, 22];
}

if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 19) {
    $default_config['max_login_attempts'] = '5';
    $default_config['login_reset_time']   = '30';
}

if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 20) {
    $default_config['search_flood_interval'] = '15';
    $default_config['rand_seed'] = '0';
}

if ($phpbb_version[0] === 0 && $phpbb_version[1] >= 21) {
    $default_config['search_min_chars'] = '3';
}
sort($tables);

//
// Function for updating the config_table
//
function update_config($name, $value)
{
	global $board_config;
	global $storage;

    dibi::update(Tables::CONFIG_TABLE, ['config_value' => $value])
        ->where('config_name = %s', $name)
        ->execute();

    $cache = new Cache($storage, Tables::CONFIG_TABLE);
    $cache->remove(Tables::CONFIG_TABLE);

	$board_config[$name] = $value;
}

//
// This is the equivalent function for message_die. Since we do not use the template system when doing database work, message_die() will not work.
//
function throw_error($msg_text = '', $err_line = '', $err_file = '')
{
	global $template, $lang, $phpbb_root_path, $theme;
	global $list_open;

	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent
	// subsequent queries from overwriting the status of sql_error()
	//
    if (DEBUG) {
        if ($err_line !== '' && $err_file !== '') {
            $debug_text = '</br /><br />Line : ' . $err_line . '<br />File : ' . $err_file;
        }
    } else {
        $debug_text = '';
    }

	//
	// Close the list if one is still open
	//
    if ($list_open) {
        echo("</ul></span>\n");
    }

    if ($msg_text === '') {
        $msg_text = $lang['An_error_occured'];
    }

	echo('<p class="gen"><b><span style="color:#' . $theme['fontcolor3'] . '">' . $lang['Error'] . ":</span></b> $msg_text$debug_text</p>\n");

	//
	// Include Tail and exit
	//
	echo('<p class="gen"><a href="' . Session::appendSid('admin_db_maintenance.php') . '">' . $lang['Back_to_DB_Maintenance'] . "</a></p>\n");

	$sep = DIRECTORY_SEPARATOR;

    require_once '.' . $sep . 'page_footer_admin.php';
	exit;
}

//
// Locks or unlocks the database
//
function lock_db($unlock = false, $delay = true, $ignore_default = false)
{
    global $board_config, $lang;
    static $db_was_locked = false;

	if ($unlock) {
		echo('<p class="gen"><b>' . $lang['Unlock_db'] . "</b></p>\n");
        if ($db_was_locked && !$ignore_default) {
            // The database was locked and we were not told to ignore the default. So we exit
            echo('<p class="gen">' . $lang['Ignore_unlock_command'] . "</p>\n");

            return;
        }
	} else {
		echo('<p class="gen"><b>' . $lang['Lock_db'] . "</b></p>\n");
		// Check current lock state
        if ($board_config['board_disable'] === 1) {
            // DB is already locked. Write this to var and exit
            $db_was_locked = true;
            echo('<p class="gen">' . $lang['Already_locked'] . "</p>\n");

            return $db_was_locked;
        } else {
            $db_was_locked = false;
        }
	}

	// OK, now we can update the settings
	update_config('board_disable', $unlock ? '0' : '1');

	//
	// Delay 3 seconds to allow database to finish operation
	//
	if (!$unlock && $delay) {
		global $timer;
		echo('<p class="gen">' . $lang['Delay_info'] . "</p>\n");
		sleep(3);
		$timer += 3; // remove delaying time from timer
	} else {
		echo('<p class="gen">' . $lang['Done'] . "</p>\n");
	}
	return $db_was_locked;
}

//
// Checks several conditions for the menu
//
function check_condition($check)
{
	global $board_config;

	switch ($check)
	{
        case 0: // No check
        case 1: // MySQL >= 3.23.17
            return true;
		case 2: // Session Table not HEAP
            $row = dibi::query('SHOW TABLE STATUS LIKE %~like~', Tables::SESSIONS_TABLE)->fetch();

            if (!$row) {
                return false; // Status unknown
            }

            return !((isset($row->Type) && $row->Type === 'HEAP') || (isset($row->Engine) && ($row->Engine === 'HEAP' || $row->Engine === 'MEMORY')));
			break;
		case 3: // DB locked
           return (int)$board_config['board_disable'] === 1;
			break;
		case 4: // Search index in recreation
            if ($board_config['dbmtnc_rebuild_pos'] !== -1) {
				// Rebuilding was interrupted - check for end position
                return $board_config['dbmtnc_rebuild_end'] >= $board_config['dbmtnc_rebuild_pos'];
            } else {
                // Rebuilding was not interrupted
                return false;
            }
			break;
		case 5: // Configuration disabled
			return CONFIG_LEVEL !== 0;
			break;
		case 6: // User post counter disabled
			return $board_config['dbmtnc_disallow_postcounter'] !== 1;
			break;
		case 7: // Rebuilding disabled
			return $board_config['dbmtnc_disallow_rebuild'] !== 1;
			break;
		case 8: // Seperator for rebuilding
			return (check_condition(4) || check_condition(7));
			break;
        default:
            return false;
	}
}

//
// Gets the current time in microseconds
//
function getmicrotime()
{
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}

//
// Gets table statistics
//
function get_table_statistic()
{
	global $tables;

	$stat['all']['count'] = 0;
	$stat['all']['records'] = 0;
	$stat['all']['size'] = 0;
	$stat['advanced']['count'] = 0;
	$stat['advanced']['records'] = 0;
	$stat['advanced']['size'] = 0;
	$stat['core']['count'] = 0;
	$stat['core']['records'] = 0;
	$stat['core']['size'] = 0;

	$rows = dibi::query('SHOW TABLE STATUS')->fetchAll();

	$prefixedTables = [];

	foreach ($tables as  $key => $table) {
	    $prefixedTables[$key] = Config::TABLE_PREFIX . $table;
    }

    foreach ($rows as $row) {
        $stat['all']['count']++;
        $stat['all']['records'] += (int)$row->Rows;
        $stat['all']['size']    += (int)$row->Data_length + (int)$row->Index_length;

        if (!in_array($row->Name, $prefixedTables, true)) {
            $stat['advanced']['count']++;
            $stat['advanced']['records'] += (int)$row->Rows;
            $stat['advanced']['size']    += (int)$row->Data_length + (int)$row->Index_length;
        }

        foreach ($tables as $table) {
            if (Config::TABLE_PREFIX . $table === $row->Name) {
                $stat['core']['count']++;
                $stat['core']['records'] += (int)$row->Rows;
                $stat['core']['size']    += (int)$row->Data_length + (int)$row->Index_length;
            }
        }
    }

	return $stat;
}

//
// Creates a new category
//
function create_cat()
{
	global $lang;

    static $cat_created = false;
	static $cat_id = 0;

	if (!$cat_created) {
		// H�chten Wert von cat_order ermitteln
        $next_cat_order = dibi::select('MAX(cat_order)')
            ->as('cart_order')
            ->from(Tables::CATEGORIES_TABLE)
            ->fetchSingle();

        $next_cat_order += 10;

        $insertData = [
            'cat_title' => $lang['New_cat_name'],
            'cat_order' => $next_cat_order
        ];

        $cat_id = dibi::insert(Tables::CATEGORIES_TABLE, $insertData)->execute(dibi::IDENTIFIER);
		$cat_created = true;
	}

	return $cat_id;
}

//
// Creates a new forum
//
function create_forum()
{
	global $lang;

    static $forum_created = false;
	static $forum_id = 0;
	$cat_id = create_cat();

	if (!$forum_created) {
        $next_forum_order = dibi::select('MAX(forum_order)')
            ->as('forum_order')
            ->from(Tables::FORUMS_TABLE)
            ->where('cat_id = %i', $cat_id)
            ->fetchSingle();

        $next_forum_order += 10;

        $insertData = [
            'cat_id' =>  $cat_id,
            'forum_name' => $lang['New_forum_name'],
            'forum_desc' => '',
            'forum_status' => FORUM_LOCKED,
            'forum_order' => $next_forum_order,
            'forum_posts' => 0,
            'forum_topics' => 0,
            'forum_last_post_id' => 0,
            'prune_next' => null,
            'prune_enable' => 0,
            'auth_view' => Auth::AUTH_ADMIN,
            'auth_read' => Auth::AUTH_ADMIN,
            'auth_post' => Auth::AUTH_ADMIN,
            'auth_reply' => Auth::AUTH_ADMIN,
            'auth_edit' => Auth::AUTH_ADMIN,
            'auth_delete' => Auth::AUTH_ADMIN,
            'auth_sticky' => Auth::AUTH_ADMIN,
            'auth_announce' => Auth::AUTH_ADMIN,
            'auth_vote' => Auth::AUTH_ADMIN,
            'auth_pollcreate' => Auth::AUTH_ADMIN,
            'auth_attachments' => 0,
        ];

        $forum_id = dibi::insert(Tables::FORUMS_TABLE, $insertData)->execute(dibi::IDENTIFIER);
        $forum_created = true;
	}
	return $forum_id;
}

//
// Create a new topic
//
function create_topic()
{
	global $lang;

    static $topic_created = false;
	static $topic_id = 0;
	$forum_id = create_forum();

	if (!$topic_created) {
	    $insertData = [
	        'forum_id' => $forum_id,
            'topic_title' => $lang['New_topic_name'],
            'topic_poster' => ANONYMOUS,
            'topic_time' => time(),
            'topic_views' => 0,
            'topic_replies' => 0,
            'topic_status' => TOPIC_UNLOCKED,
            'topic_vote' => 0,
            'topic_type' => POST_NORMAL,
            'topic_first_post_id' => 0,
            'topic_last_post_id' => 0,
            'topic_moved_id' => 0,
        ];

	    $topic_id = dibi::insert(Tables::TOPICS_TABLE, $insertData)->execute(dibi::IDENTIFIER);
        $topic_created = true;
	}
	return $topic_id;
}

//
// Gets the poster of a topic
//
function get_poster($topic_id)
{
    $firstPost = dibi::select('Min(post_id)')
        ->as('first_post')
        ->from(Tables::POSTS_TABLE)
        ->where('topic_id = %i', $topic_id)
        ->fetchSingle();

    if (!$firstPost) {
        return DELETED;
    }

    $posterId = dibi::select('poster_id')
        ->from(Tables::POSTS_TABLE)
        ->where('post_id = %i', $firstPost)
        ->fetch();

    if (!$posterId) {
        throw_error("Couldn't get post data!");
    }

	return $posterId;
}

//
// Error handler when trying to reset timelimit
//
function catch_error($errno, $errstr)
{
	global $execution_time;

	$execution_time = ini_get('max_execution_time'); // Will only get executet when running on PHP 4+
}

//
// Gets the ID of a word or creates it
//
function get_word_id($word)
{
	global $stopword_array, $synonym_array;

	// Check whether word is in stopword array
    if (in_array($word, $stopword_array, true)) {
        return null;
    }
    if (in_array($word, $synonym_array[1], true)) {
        $key  = array_search($word, $synonym_array[1], true);
        $word = $synonym_array[0][$key];
    }

    $row = dibi::select(['word_id', 'word_common'])
        ->from(Tables::SEARCH_WORD_TABLE)
        ->where('word_text = %s', $word)
        ->fetch();

    if ($row) { // Word was found
        if ($row->word_common) {// Common word
            return null;
        } else {// Not a common word
            return $row->word_id;
        }
    } else { // Word was not found
        return dibi::insert(Tables::SEARCH_WORD_TABLE, ['word_text' => $word, 'word_common' => 0])
            ->execute(dibi::IDENTIFIER);
    }
}

//
// Resets the auto increment for a table
//
function set_autoincrement($table, $column, $length, $unsigned = TRUE)
{
	global $lang;

    $sql = "ALTER IGNORE TABLE $table MODIFY $column mediumint($length) " . ($unsigned ? 'unsigned ' : '') . 'NOT NULL auto_increment';

    $row = dibi::query('SHOW COLUMNS FROM %n LIKE %~like~', $table, $column)->fetch();

    if (strpos($row->Extra, 'auto_increment') !== FALSE) {
        echo("<li>$table: " . $lang['Ai_message_no_update'] . "</li>\n");
    } else {
        echo("<li>$table: <b>" . $lang['Ai_message_update_table'] . "</b></li>\n");

        dibi::query($sql);
    }
}

//
// Functions for Emergency Recovery Console
//
function erc_throw_error($msg_text = '', $err_line = '', $err_file = '')
{
	global $lang;

	//
	// Get SQL error if we are debugging. Do this as soon as possible to prevent
	// subsequent queries from overwriting the status of sql_error()
	//
    if (DEBUG) {
        if ($err_line !== '' && $err_file !== '') {
            $debug_text = '</br /><br />Line : ' . $err_line . '<br />File : ' . $err_file;
        }
    } else {
        $debug_text = '';
    }

    if ($msg_text === '') {
        $msg_text = $lang['An_error_occured'];
    }

	echo('<p class="gen"><b>' . $lang['Error'] . ":</b> $msg_text$debug_text</p>\n");

	exit;
}

// TODO we should someway use Select class!!!
function language_select($default, $select_name = 'language', $file_to_check = 'main', $dirname= 'language')
{
	global $phpbb_root_path, $lang;

	$sep = DIRECTORY_SEPARATOR;

    $lg = [];

    $files = Finder::findDirectories('^lang_')->from($phpbb_root_path . $dirname);

    /**
     * @var SplFileInfo $file
     */
    foreach ($files as $file) {
        if (is_file(@phpbb_realpath($phpbb_root_path . $dirname . $sep . $file->getFilename() . $sep . 'lang_' . $file_to_check . '.php'))) {
            $filename = trim(str_replace('lang_', '', $file->getFilename()));
            $displayName = preg_replace('/^(.*?)_(.*)$/', "\\1 [ \\2 ]", $filename);
            $displayName = preg_replace("/\[(.*?)_(.*)\]/", "[ \\1 - \\2 ]", $displayName);
            $lg[$displayName] = $filename;
        }
    }

	@asort($lg);

    if (count($lg)) {
		$options = '';

		foreach ($lg as $displayName => $filename) {
			$selected = ( mb_strtolower($default) === mb_strtolower($filename) ) ? 'selected="selected"' : '';

            $options .= '<option value="' . $filename . '" ' . $selected . '>' . htmlspecialchars(ucwords($displayName), ENT_QUOTES) . '</option>';
		}

        $lang_select = '<select name="' . $select_name . '">' . $options . '</select>';
	} else {
		$lang_select = $lang['No_selectable_language'];
	}

	return $lang_select;
}

function check_authorisation($die = true)
{
	global $lang, $option, $_POST;

    $auth_method    = isset($_POST['auth_method']) ? htmlspecialchars($_POST['auth_method']) : '';
    $board_password = isset($_POST['board_password']) ? $_POST['board_password'] : '';
    $db_user        = isset($_POST['db_user']) ? $_POST['db_user'] : '';
    $db_password    = isset($_POST['db_password']) ? $_POST['db_password'] : '';

    $board_user = isset($_POST['board_user']) ? trim(htmlspecialchars($_POST['board_user'])) : '';
    $board_user = substr(str_replace("\\'", "'", $board_user), 0, 25);
    $board_user = str_replace("'", "\\'", $board_user);

	// Change authentication mode if selected option does not allow database authentication

    if ($option === 'rld' || $option === 'rtd') {
		$auth_method = 'board';
	}

	switch ($auth_method) {
		case 'board':
            $row = dibi::select(['user_id', 'username', 'user_password', 'user_acp_password', 'user_active', 'user_level'])
                ->from(Tables::USERS_TABLE)
                ->where('username = %s', $board_user)
                ->fetch();

            if ($row === false) {
                $allow_access = false;
            } else {
                if ($row->user_acp_password) {
                    $allow_access = password_verify($board_password, $row->user_password) && $row->user_active && $row->user_level === ADMIN;
                } else {
                    $allow_access = password_verify($board_password, $row->user_password) && $row->user_active && $row->user_level === ADMIN;
                }
            }

			break;
		case 'db':
            $allow_access = $db_user === Config::DATABASE_USER && $db_password === CONFIG::DATABASE_PASSWORD;
			break;
		default:
            $allow_access = false;
	}
    if (!$allow_access && $die) {
?>
	<p><span style="color:red"><?php echo $lang['Auth_failed']; ?></span></p>
</body>
</html>
<?php
		exit;
	}
	return $allow_access;
}

function get_config_data($option)
{
	$config = dibi::select('config_value')
        ->from(Tables::CONFIG_TABLE)
        ->where('config_name = %s', $option)
        ->fetchSingle();

    if (!$config) {
        erc_throw_error("Couldn't get config data!");
    }

	return $config;
}

function success_message($text)
{
	global $lang, $lg, $_SERVER;

?>
	<p><?php echo $text; ?></p>
	<p style="text-align:center"><a href="<?php echo $_SERVER['PHP_SELF'] . '?lg=' . $lg; ?>"><?php echo $lang['Return_ERC']; ?></a></p>
<?php
}
?>
