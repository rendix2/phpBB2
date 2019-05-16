<?php
/***************************************************************************
 *                                index.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: index.php 5502 2006-01-28 11:13:39Z acydburn $
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
include $phpbb_root_path . 'extension.inc';
include $phpbb_root_path . 'common.php';

//
// Start session management
//
$user_data = session_pagestart($user_ip, PAGE_INDEX);
init_userprefs($user_data);
//
// End session management
//

$view_category = !empty($_GET[POST_CAT_URL]) ? $_GET[POST_CAT_URL] : -1;

if (isset($_GET['mark']) || isset($_POST['mark'])) {
    $mark_read = isset($_POST['mark']) ? $_POST['mark'] : $_GET['mark'];
} else {
    $mark_read = '';
}

//
// Handle marking posts
//
if ($mark_read == 'forums') {
    if ($user_data['session_logged_in']) {
        setcookie($board_config['cookie_name'] . '_f_all', time(), 0, $board_config['cookie_path'],
            $board_config['cookie_domain'], $board_config['cookie_secure']);
    }

    $template->assign_vars([
            "META" => '<meta http-equiv="refresh" content="3;url=' . append_sid("index.php") . '">'
        ]);

    $message = $lang['Forums_marked_read'] . '<br /><br />' . sprintf($lang['Click_return_index'],
            '<a href="' . append_sid("index.php") . '">', '</a> ');

    message_die(GENERAL_MESSAGE, $message);
}
//
// End handle marking posts
//

$topic_cookie_name = $board_config['cookie_name'] . '_t';
$forum_cookie_name = $board_config['cookie_name'] . '_f';

$tracking_topics = isset($_COOKIE[$topic_cookie_name]) ? unserialize($_COOKIE[$topic_cookie_name]) : [];
$tracking_forums = isset($_COOKIE[$forum_cookie_name]) ? unserialize($_COOKIE[$forum_cookie_name]) : [];

//
// If you don't use these stats on your index you may want to consider
// removing them
//
$total_posts     = get_db_stat('postcount');
$total_users     = get_db_stat('usercount');
$newest_userdata = get_db_stat('newestuser');
$newest_user     = $newest_userdata['username'];
$newest_uid      = $newest_userdata['user_id'];

if ($total_posts == 0) {
    $l_total_post_s = $lang['Posted_articles_zero_total'];
} elseif ($total_posts == 1) {
    $l_total_post_s = $lang['Posted_article_total'];
} else {
    $l_total_post_s = $lang['Posted_articles_total'];
}

if ($total_users == 0) {
    $l_total_user_s = $lang['Registered_users_zero_total'];
} elseif ($total_users == 1) {
    $l_total_user_s = $lang['Registered_user_total'];
} else {
    $l_total_user_s = $lang['Registered_users_total'];
}

//
// Start page proper
//
$sql = "SELECT c.cat_id, c.cat_title, c.cat_order
	FROM " . CATEGORIES_TABLE . " c 
	ORDER BY c.cat_order";

if (!($result = $db->sql_query($sql))) {
    message_die(GENERAL_ERROR, 'Could not query categories list', '', __LINE__, __FILE__, $sql);
}

$category_rows = [];

while ($row = $db->sql_fetchrow($result)) {
    $category_rows[] = $row;
}

$db->sql_freeresult($result);

$category_count = count($category_rows);

if (!$category_count) {
    message_die(GENERAL_MESSAGE, $lang['No_forums']);
}

//
// Define appropriate SQL
//
switch (SQL_LAYER) {
    case 'postgresql':
        $sql = "SELECT f.*, p.post_time, p.post_username, u.username, u.user_id 
				FROM " . FORUMS_TABLE . " f, " . POSTS_TABLE . " p, " . USERS_TABLE . " u
				WHERE p.post_id = f.forum_last_post_id 
					AND u.user_id = p.poster_id  
					UNION (
						SELECT f.*, NULL, NULL, NULL, NULL
						FROM " . FORUMS_TABLE . " f
						WHERE NOT EXISTS (
							SELECT p.post_time
							FROM " . POSTS_TABLE . " p
							WHERE p.post_id = f.forum_last_post_id  
						)
					)
					ORDER BY cat_id, forum_order";
        break;

    case 'oracle':
        $sql = "SELECT f.*, p.post_time, p.post_username, u.username, u.user_id 
				FROM " . FORUMS_TABLE . " f, " . POSTS_TABLE . " p, " . USERS_TABLE . " u
				WHERE p.post_id = f.forum_last_post_id(+)
					AND u.user_id = p.poster_id(+)
				ORDER BY f.cat_id, f.forum_order";
        break;

    default:
        $sql = "SELECT f.*, p.post_time, p.post_username, u.username, u.user_id
				FROM (( " . FORUMS_TABLE . " f
				LEFT JOIN " . POSTS_TABLE . " p ON p.post_id = f.forum_last_post_id )
				LEFT JOIN " . USERS_TABLE . " u ON u.user_id = p.poster_id )
				ORDER BY f.cat_id, f.forum_order";
        break;
}

if (!($result = $db->sql_query($sql))) {
    message_die(GENERAL_ERROR, 'Could not query forums information', '', __LINE__, __FILE__, $sql);
}

$forum_data = [];

while ($row = $db->sql_fetchrow($result)) {
    $forum_data[] = $row;
}

$db->sql_freeresult($result);

$total_forums = count($forum_data);

if (!$total_forums) {
    message_die(GENERAL_MESSAGE, $lang['No_forums']);
}

//
// Obtain a list of topic ids which contain
// posts made since user last visited
//
if ($user_data['session_logged_in']) {
    // 60 days limit
    if ($user_data['user_lastvisit'] < (time() - 5184000)) {
        $user_data['user_lastvisit'] = time() - 5184000;
    }

    $sql = "SELECT t.forum_id, t.topic_id, p.post_time 
			FROM " . TOPICS_TABLE . " t, " . POSTS_TABLE . " p 
			WHERE p.post_id = t.topic_last_post_id 
				AND p.post_time > " . $user_data['user_lastvisit'] . " 
				AND t.topic_moved_id = 0";

    if (!($result = $db->sql_query($sql))) {
        message_die(GENERAL_ERROR, 'Could not query new topic information', '', __LINE__, __FILE__, $sql);
    }

    $new_topic_data = [];

    while ($topic_data = $db->sql_fetchrow($result)) {
        $new_topic_data[$topic_data['forum_id']][$topic_data['topic_id']] = $topic_data['post_time'];
    }

    $db->sql_freeresult($result);
}

//
// Obtain list of moderators of each forum
// First users, then groups ... broken into two queries
//
$sql = "SELECT aa.forum_id, u.user_id, u.username 
		FROM " . AUTH_ACCESS_TABLE . " aa, " . USER_GROUP_TABLE . " ug, " . GROUPS_TABLE . " g, " . USERS_TABLE . " u
		WHERE aa.auth_mod = " . true . " 
			AND g.group_single_user = 1 
			AND ug.group_id = aa.group_id 
			AND g.group_id = aa.group_id 
			AND u.user_id = ug.user_id 
		GROUP BY u.user_id, u.username, aa.forum_id 
		ORDER BY aa.forum_id, u.user_id";

if (!($result = $db->sql_query($sql))) {
    message_die(GENERAL_ERROR, 'Could not query forum moderator information', '', __LINE__, __FILE__, $sql);
}

$forum_moderators = [];

while ($row = $db->sql_fetchrow($result)) {
    $forum_moderators[$row['forum_id']][] = '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=" . $row['user_id']) . '">' . $row['username'] . '</a>';
}

$db->sql_freeresult($result);

$sql = "SELECT aa.forum_id, g.group_id, g.group_name 
		FROM " . AUTH_ACCESS_TABLE . " aa, " . USER_GROUP_TABLE . " ug, " . GROUPS_TABLE . " g 
		WHERE aa.auth_mod = " . true . " 
			AND g.group_single_user = 0 
			AND g.group_type <> " . GROUP_HIDDEN . "
			AND ug.group_id = aa.group_id 
			AND g.group_id = aa.group_id 
		GROUP BY g.group_id, g.group_name, aa.forum_id 
		ORDER BY aa.forum_id, g.group_id";

if (!($result = $db->sql_query($sql))) {
    message_die(GENERAL_ERROR, 'Could not query forum moderator information', '', __LINE__, __FILE__, $sql);
}

while ($row = $db->sql_fetchrow($result)) {
    $forum_moderators[$row['forum_id']][] = '<a href="' . append_sid("groupcp.php?" . POST_GROUPS_URL . "=" . $row['group_id']) . '">' . $row['group_name'] . '</a>';
}

$db->sql_freeresult($result);

//
// Find which forums are visible for this user
//
$is_auth_array = [];
$is_auth_array = auth(AUTH_VIEW, AUTH_LIST_ALL, $user_data, $forum_data);

//
// Start output of page
//
define('SHOW_ONLINE', true);
$page_title = $lang['Index'];
include $phpbb_root_path . 'includes/page_header.php';

$template->set_filenames(['body' => 'index_body.tpl']);

$template->assign_vars([
        'TOTAL_POSTS' => sprintf($l_total_post_s, $total_posts),
        'TOTAL_USERS' => sprintf($l_total_user_s, $total_users),
        'NEWEST_USER' => sprintf($lang['Newest_user'],
            '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . "=$newest_uid") . '">',
            $newest_user, '</a>'),

        'FORUM_IMG'        => $images['forum'],
        'FORUM_NEW_IMG'    => $images['forum_new'],
        'FORUM_LOCKED_IMG' => $images['forum_locked'],

        'L_FORUM'               => $lang['Forum'],
        'L_TOPICS'              => $lang['Topics'],
        'L_REPLIES'             => $lang['Replies'],
        'L_VIEWS'               => $lang['Views'],
        'L_POSTS'               => $lang['Posts'],
        'L_LASTPOST'            => $lang['Last_Post'],
        'L_NO_NEW_POSTS'        => $lang['No_new_posts'],
        'L_NEW_POSTS'           => $lang['New_posts'],
        'L_NO_NEW_POSTS_LOCKED' => $lang['No_new_posts_locked'],
        'L_NEW_POSTS_LOCKED'    => $lang['New_posts_locked'],
        'L_ONLINE_EXPLAIN'      => $lang['Online_explain'],

        'L_MODERATOR'        => $lang['Moderators'],
        'L_FORUM_LOCKED'     => $lang['Forum_is_locked'],
        'L_MARK_FORUMS_READ' => $lang['Mark_all_forums'],

        'U_MARK_READ' => append_sid("index.php?mark=forums")
    ]);

//
// Let's decide which categories we should display
//
$display_categories = [];

for ($i = 0; $i < $total_forums; $i++) {
    if ($is_auth_array[$forum_data[$i]['forum_id']]['auth_view']) {
        $display_categories[$forum_data[$i]['cat_id']] = true;
    }
}

//
// Okay, let's build the index
//
for ($i = 0; $i < $category_count; $i++) {
    $cat_id = $category_rows[$i]['cat_id'];

    //
    // Yes, we should, so first dump out the category
    // title, then, if appropriate the forum list
    //
    if (isset($display_categories[$cat_id]) && $display_categories[$cat_id]) {
        $template->assign_block_vars('catrow', [
                'CAT_ID'    => $cat_id,
                'CAT_DESC'  => $category_rows[$i]['cat_title'],
                'U_VIEWCAT' => append_sid("index.php?" . POST_CAT_URL . "=$cat_id")
            ]);

        if ($view_category == $cat_id || $view_category == -1) {
            for ($j = 0; $j < $total_forums; $j++) {
                if ($forum_data[$j]['cat_id'] == $cat_id) {
                    $forum_id = $forum_data[$j]['forum_id'];

                    if ($is_auth_array[$forum_id]['auth_view']) {
                        if ($forum_data[$j]['forum_status'] == FORUM_LOCKED) {
                            $folder_image = $images['forum_locked'];
                            $folder_alt   = $lang['Forum_locked'];
                        } else {
                            $unread_topics = false;

                            if ($user_data['session_logged_in']) {
                                if (!empty($new_topic_data[$forum_id])) {
                                    $forum_last_post_time = 0;

                                    while (list($check_topic_id, $check_post_time) = @each($new_topic_data[$forum_id])) {
                                        if (empty($tracking_topics[$check_topic_id])) {
                                            $unread_topics        = true;
                                            $forum_last_post_time = max($check_post_time, $forum_last_post_time);
                                        } else {
                                            if ($tracking_topics[$check_topic_id] < $check_post_time) {
                                                $unread_topics        = true;
                                                $forum_last_post_time = max($check_post_time, $forum_last_post_time);
                                            }
                                        }
                                    }

                                    if (!empty($tracking_forums[$forum_id])) {
                                        if ($tracking_forums[$forum_id] > $forum_last_post_time) {
                                            $unread_topics = false;
                                        }
                                    }

                                    if (isset($_COOKIE[$board_config['cookie_name'] . '_f_all'])) {
                                        if ($_COOKIE[$board_config['cookie_name'] . '_f_all'] > $forum_last_post_time) {
                                            $unread_topics = false;
                                        }
                                    }
                                }
                            }

                            $folder_image = $unread_topics ? $images['forum_new'] : $images['forum'];
                            $folder_alt   = $unread_topics ? $lang['New_posts'] : $lang['No_new_posts'];
                        }

                        $posts  = $forum_data[$j]['forum_posts'];
                        $topics = $forum_data[$j]['forum_topics'];

                        if ($forum_data[$j]['forum_last_post_id']) {
                            $last_post_time = create_date($board_config['default_dateformat'],
                                $forum_data[$j]['post_time'], $board_config['board_timezone']);

                            $last_post = $last_post_time . '<br />';

                            $last_post .= ($forum_data[$j]['user_id'] == ANONYMOUS) ? (($forum_data[$j]['post_username'] != '') ? $forum_data[$j]['post_username'] . ' ' : $lang['Guest'] . ' ') : '<a href="' . append_sid("profile.php?mode=viewprofile&amp;" . POST_USERS_URL . '=' . $forum_data[$j]['user_id']) . '">' . $forum_data[$j]['username'] . '</a> ';

                            $last_post .= '<a href="' . append_sid("viewtopic.php?" . POST_POST_URL . '=' . $forum_data[$j]['forum_last_post_id']) . '#' . $forum_data[$j]['forum_last_post_id'] . '"><img src="' . $images['icon_latest_reply'] . '" border="0" alt="' . $lang['View_latest_post'] . '" title="' . $lang['View_latest_post'] . '" /></a>';
                        } else {
                            $last_post = $lang['No_Posts'];
                        }

                        $moderators_forum_count = count($forum_moderators[$forum_id]);

                        if ($moderators_forum_count > 0) {
                            $l_moderators   = $moderators_forum_count == 1 ? $lang['Moderator'] : $lang['Moderators'];
                            $moderator_list = implode(', ', $forum_moderators[$forum_id]);
                        } else {
                            $l_moderators   = '&nbsp;';
                            $moderator_list = '&nbsp;';
                        }

                        $row_color = (!($i % 2)) ? $theme['td_color1'] : $theme['td_color2'];
                        $row_class = (!($i % 2)) ? $theme['td_class1'] : $theme['td_class2'];


                        $catRowData = [
                            'ROW_COLOR'        => '#' . $row_color,
                            'ROW_CLASS'        => $row_class,
                            'FORUM_FOLDER_IMG' => $folder_image,
                            'FORUM_NAME'       => $forum_data[$j]['forum_name'],
                            'FORUM_DESC'       => $forum_data[$j]['forum_desc'],
                            'POSTS'            => $forum_data[$j]['forum_posts'],
                            'TOPICS'           => $forum_data[$j]['forum_topics'],
                            'LAST_POST'        => $last_post,
                            'MODERATORS'       => $moderator_list,

                            'L_MODERATOR'        => $l_moderators,
                            'L_FORUM_FOLDER_ALT' => $folder_alt,

                            'U_VIEWFORUM' => append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id")
                        ];

                        $template->assign_block_vars('catrow.forumrow', $catRowData);
                    }
                }
            }
        }
    }
} // for ... categories

//
// Generate the page
//
$template->pparse('body');

include $phpbb_root_path . 'includes/page_tail.php';

?>