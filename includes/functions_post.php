<?php
/***************************************************************************
 *                            functions_post.php
 *                            -------------------
 *   begin                : Saturday, Feb 13, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: functions_post.php 5886 2006-05-06 13:38:55Z grahamje $
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

if (!defined('IN_PHPBB'))
{
	die('Hacking attempt');
}

$html_entities_match   = ['#&(?!(\#[0-9]+;))#', '#<#', '#>#', '#"#'];
$html_entities_replace = ['&amp;', '&lt;', '&gt;', '&quot;'];

$unhtml_specialchars_match   = ['#&gt;#', '#&lt;#', '#&quot;#', '#&amp;#'];
$unhtml_specialchars_replace = ['>', '<', '"', '&'];

//
// This function will prepare a posted message for
// entry into the database.
//
function prepare_message($message, $html_on, $bbcode_on, $smile_on, $bbcode_uid = 0)
{
	global $board_config, $html_entities_match, $html_entities_replace;

	//
	// Clean up the message
	//
	$message = trim($message);

	if ($html_on)
	{
		// If HTML is on, we try to make it safe
		// This approach is quite agressive and anything that does not look like a valid tag
		// is going to get converted to HTML entities
		$message = stripslashes($message);
		$html_match = '#<[^\w<]*(\w+)((?:"[^"]*"|\'[^\']*\'|[^<>\'"])+)?>#';
		$matches = [];

		$message_split = preg_split($html_match, $message);
		preg_match_all($html_match, $message, $matches);

		$message = '';

		foreach ($message_split as $part) {
            $tag     = [array_shift($matches[0]), array_shift($matches[1]), array_shift($matches[2])];
            $message .= preg_replace($html_entities_match, $html_entities_replace, $part) . clean_html($tag);
		}

		$message = addslashes($message);
		$message = str_replace('&quot;', '\&quot;', $message);
	} else {
		$message = preg_replace($html_entities_match, $html_entities_replace, $message);
	}

	if ($bbcode_on && $bbcode_uid != '') {
		$message = bbencode_first_pass($message, $bbcode_uid);
	}

	return $message;
}

function unprepare_message($message)
{
	global $unhtml_specialchars_match, $unhtml_specialchars_replace;

	return preg_replace($unhtml_specialchars_match, $unhtml_specialchars_replace, $message);
}

//
// Prepare a message for posting
// 
function prepare_post(&$mode, &$post_data, &$bbcode_on, &$html_on, &$smilies_on, &$error_msg, &$username, &$bbcode_uid, &$subject, &$message, &$poll_title, &$poll_options, &$poll_length)
{
	global $board_config, $userdata, $lang, $phpbb_root_path;

	// Check username
	if (!empty($username)) {
		$username = phpbb_clean_username($username);

		if (!$userdata['session_logged_in'] || ($userdata['session_logged_in'] && $username != $userdata['username'])) {
			include $phpbb_root_path . 'includes/functions_validate.php';

			$result = validate_username($username);
			if ($result['error']) {
				$error_msg .= !empty($error_msg) ? '<br />' . $result['error_msg'] : $result['error_msg'];
			}
		} else {
			$username = '';
		}
	}

	// Check subject
	if (!empty($subject)) {
		$subject = htmlspecialchars(trim($subject));
	} elseif ($mode == 'newtopic' || ($mode == 'editpost' && $post_data['first_post'])) {
		$error_msg .= !empty($error_msg) ? '<br />' . $lang['Empty_subject'] : $lang['Empty_subject'];
	}

	// Check message
	if (!empty($message)) {
		$bbcode_uid = $bbcode_on ? make_bbcode_uid() : '';
		$message = prepare_message(trim($message), $html_on, $bbcode_on, $smilies_on, $bbcode_uid);
	} elseif ($mode != 'delete' && $mode != 'poll_delete')  {
		$error_msg .= !empty($error_msg) ? '<br />' . $lang['Empty_message'] : $lang['Empty_message'];
	}

	//
	// Handle poll stuff
	//
	if ($mode == 'newtopic' || ($mode == 'editpost' && $post_data['first_post'])) {
		$poll_length = isset($poll_length) ? max(0, (int)$poll_length) : 0;

		if (!empty($poll_title)) {
			$poll_title = htmlspecialchars(trim($poll_title));
		}

		if (!empty($poll_options)) {
			$temp_option_text = [];

			while (list($option_id, $option_text) = @each($poll_options)) {
				$option_text = trim($option_text);

				if (!empty($option_text)) {
                    $temp_option_text[(int)$option_id] = htmlspecialchars($option_text);
				}
			}
			$option_text = $temp_option_text;

            if (count($poll_options) < 2) {
                $error_msg .= !empty($error_msg) ? '<br />' . $lang['To_few_poll_options'] : $lang['To_few_poll_options'];
            } elseif (count($poll_options) > $board_config['max_poll_options']) {
                $error_msg .= !empty($error_msg) ? '<br />' . $lang['To_many_poll_options'] : $lang['To_many_poll_options'];
            } elseif ($poll_title == '') {
                $error_msg .= !empty($error_msg) ? '<br />' . $lang['Empty_poll_title'] : $lang['Empty_poll_title'];
            }
		}
	}

	return;
}

//
// Post a new topic/reply/poll or edit existing post/poll
//
function submit_post($mode, &$post_data, &$message, &$meta, &$forum_id, &$topic_id, &$post_id, &$poll_id, &$topic_type, &$bbcode_on, &$html_on, &$smilies_on, &$attach_sig, &$bbcode_uid, $post_username, $post_subject, $post_message, $poll_title, &$poll_options, &$poll_length)
{
	global $board_config, $lang, $db, $phpbb_root_path;
	global $userdata, $user_ip;

	include $phpbb_root_path . 'includes/functions_search.php';

	$current_time = time();

	if ($mode == 'newtopic' || $mode == 'reply' || $mode == 'editpost') {
		//
		// Flood control
		//
		$where_sql = ($userdata['user_id'] == ANONYMOUS) ? "poster_ip = '$user_ip'" : 'poster_id = ' . $userdata['user_id'];
		$sql = "SELECT MAX(post_time) AS last_post_time
			FROM " . POSTS_TABLE . "
			WHERE $where_sql";
		if ($result = $db->sql_query($sql)) {
			if ($row = $db->sql_fetchrow($result)) {
				if ((int)$row['last_post_time'] > 0 && ($current_time - (int)$row['last_post_time']) < (int)$board_config['flood_interval']) {
					message_die(GENERAL_MESSAGE, $lang['Flood_Error']);
				}
			}
		}
	}

	if ($mode == 'editpost') {
		remove_search_post($post_id);
	}

	if ($mode == 'newtopic' || ($mode == 'editpost' && $post_data['first_post'])) {
		$topic_vote = (!empty($poll_title) && count($poll_options) >= 2) ? 1 : 0;

		if ($mode != "editpost") {
            $insert_data = [
                'topic_title' => $post_subject,
                'topic_poster' => $userdata['user_id'],
                'topic_time'   =>  $current_time,
                'forum_id'     => $forum_id,
                'topic_status' => TOPIC_UNLOCKED,
                'topic_type'   => $topic_type,
                'topic_vote'   => $topic_vote
            ];

            $topic_id = dibi::insert(TOPICS_TABLE, $insert_data)->execute(dibi::IDENTIFIER);
        } else {
		    $update_data = [
		        'topic_title' => $post_subject,
                'topic_type' => $topic_type
            ];

		    if ($post_data['edit_vote'] || !empty($poll_title)) {
		        $update_data['topic_vote'] = $topic_vote;
            }

		    dibi::update(TOPICS_TABLE, $update_data)
                ->where('topic_id = %i', $topic_id)
                ->execute();
        }
	}

	if ($mode != "editpost") {
        $insert_data = [
            'topic_id'       => $topic_id,
            'forum_id'       => $forum_id,
            'poster_id'      => $userdata['user_id'],
            'post_username'  => $post_username,
            'post_time'      => $current_time,
            'poster_ip'      => $user_ip, 'enable_bbcode'  => $bbcode_on,
            'enable_html'    => $html_on,
            'enable_smilies' => $smilies_on,
            'enable_sig'     => $attach_sig
        ];

        $post_id = dibi::insert(POSTS_TABLE, $insert_data)->execute(dibi::IDENTIFIER);

        $insert_data = [
            'post_id'      => $post_id,
            'post_subject' => $post_subject,
            'bbcode_uid'   => $bbcode_uid,
            'post_text'    => $post_message
        ];

        dibi::insert(POSTS_TEXT_TABLE, $insert_data)->execute();
    } else {
        $update_data = [
            'post_username'  => $post_username,
            'enable_bbcode'  => $bbcode_on,
            'enable_html'    => $html_on,
            'enable_smilies' => $smilies_on,
            'enable_sig'     => $attach_sig
        ];

        if ($mode == 'editpost' && !$post_data['last_post'] && $post_data['poster_post']) {
            $update_data['post_edit_time'] = $current_time;
            $update_data['post_edit_count%sql'] = 'post_edit_count + 1';
        }

        dibi::update(POSTS_TABLE, $update_data)
            ->where('post_id = %i', $post_id)
            ->execute();

        $update_data = [
            'post_text'  => $post_message,
            'bbcode_uid' => $bbcode_uid,
            'post_subject' => $post_subject
        ];

        dibi::update(POSTS_TEXT_TABLE, $update_data)
            ->where('post_id = %i', $post_id)
            ->execute();
    }

	add_search_words('single', $post_id, stripslashes($post_message), stripslashes($post_subject));

	//
	// Add poll
	// 
	if (($mode == 'newtopic' || ($mode == 'editpost' && $post_data['edit_poll'])) && !empty($poll_title) && count($poll_options) >= 2) {
		$sql = (!$post_data['has_poll']) ? "INSERT INTO " . VOTE_DESC_TABLE . " (topic_id, vote_text, vote_start, vote_length) VALUES ($topic_id, '$poll_title', $current_time, " . ($poll_length * 86400) . ")" : "UPDATE " . VOTE_DESC_TABLE . " SET vote_text = '$poll_title', vote_length = " . ($poll_length * 86400) . " WHERE topic_id = $topic_id";

		if (!$db->sql_query($sql)) {
			message_die(GENERAL_ERROR, 'Error in posting', '', __LINE__, __FILE__, $sql);
		}

		$delete_option_sql = '';
		$old_poll_result = [];

		if ($mode == 'editpost' && $post_data['has_poll']) {
			$sql = "SELECT vote_option_id, vote_result  
				FROM " . VOTE_RESULTS_TABLE . " 
				WHERE vote_id = $poll_id 
				ORDER BY vote_option_id ASC";

			if (!($result = $db->sql_query($sql))) {
				message_die(GENERAL_ERROR, 'Could not obtain vote data results for this topic', '', __LINE__, __FILE__, $sql);
			}

			while ($row = $db->sql_fetchrow($result)) {
				$old_poll_result[$row['vote_option_id']] = $row['vote_result'];

				if (!isset($poll_options[$row['vote_option_id']])) {
					$delete_option_sql .= ($delete_option_sql != '') ? ', ' . $row['vote_option_id'] : $row['vote_option_id'];
				}
			}
		} else {
			$poll_id = $db->sql_nextid();
		}

		@reset($poll_options);

		$poll_option_id = 1;

		while (list($option_id, $option_text) = each($poll_options)) {
			if (!empty($option_text)) {
				$option_text = str_replace("\'", "''", htmlspecialchars($option_text));
				$poll_result = ($mode == "editpost" && isset($old_poll_result[$option_id])) ? $old_poll_result[$option_id] : 0;

				$sql = ($mode != "editpost" || !isset($old_poll_result[$option_id])) ? "INSERT INTO " . VOTE_RESULTS_TABLE . " (vote_id, vote_option_id, vote_option_text, vote_result) VALUES ($poll_id, $poll_option_id, '$option_text', $poll_result)" : "UPDATE " . VOTE_RESULTS_TABLE . " SET vote_option_text = '$option_text', vote_result = $poll_result WHERE vote_option_id = $option_id AND vote_id = $poll_id";

				if (!$db->sql_query($sql)) {
					message_die(GENERAL_ERROR, 'Error in posting', '', __LINE__, __FILE__, $sql);
				}

				$poll_option_id++;
			}
		}

		if ($delete_option_sql != '') {
			$sql = "DELETE FROM " . VOTE_RESULTS_TABLE . " 
				WHERE vote_option_id IN ($delete_option_sql) 
					AND vote_id = $poll_id";

			if (!$db->sql_query($sql)) {
				message_die(GENERAL_ERROR, 'Error deleting pruned poll options', '', __LINE__, __FILE__, $sql);
			}
		}
	}

	$meta = '<meta http-equiv="refresh" content="3;url=' . append_sid("viewtopic.php?" . POST_POST_URL . "=" . $post_id) . '#' . $post_id . '">';
	$message = $lang['Stored'] . '<br /><br />' . sprintf($lang['Click_view_message'], '<a href="' . append_sid("viewtopic.php?" . POST_POST_URL . "=" . $post_id) . '#' . $post_id . '">', '</a>') . '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id") . '">', '</a>');

	return false;
}

//
// Update post stats and details
//
function update_post_stats(&$mode, &$post_data, &$forum_id, &$topic_id, &$post_id, &$user_id)
{
	global $db;

	$sign = ($mode == 'delete') ? '- 1' : '+ 1';
	$forum_update_sql = "forum_posts = forum_posts $sign";
	$topic_update_sql = '';

	if ($mode == 'delete') {
		if ($post_data['last_post']) {
			if ($post_data['first_post']) {
				$forum_update_sql .= ', forum_topics = forum_topics - 1';
			} else {

				$topic_update_sql .= 'topic_replies = topic_replies - 1';

				$sql = "SELECT MAX(post_id) AS last_post_id
					FROM " . POSTS_TABLE . " 
					WHERE topic_id = $topic_id";

                if (!($result = $db->sql_query($sql))) {
                    message_die(GENERAL_ERROR, 'Error in deleting post', '', __LINE__, __FILE__, $sql);
                }

                if ($row = $db->sql_fetchrow($result)) {
                    $topic_update_sql .= ', topic_last_post_id = ' . $row['last_post_id'];
                }
			}

			if ($post_data['last_topic']) {
				$sql = "SELECT MAX(post_id) AS last_post_id
					FROM " . POSTS_TABLE . " 
					WHERE forum_id = $forum_id";

				if (!($result = $db->sql_query($sql))) {
					message_die(GENERAL_ERROR, 'Error in deleting post', '', __LINE__, __FILE__, $sql);
				}

				if ($row = $db->sql_fetchrow($result)) {
					$forum_update_sql .= $row['last_post_id'] ? ', forum_last_post_id = ' . $row['last_post_id'] : ', forum_last_post_id = 0';
				}
			}
		} elseif ($post_data['first_post']) {
			$sql = "SELECT MIN(post_id) AS first_post_id
				FROM " . POSTS_TABLE . " 
				WHERE topic_id = $topic_id";

			if (!($result = $db->sql_query($sql))) {
				message_die(GENERAL_ERROR, 'Error in deleting post', '', __LINE__, __FILE__, $sql);
			}

			if ($row = $db->sql_fetchrow($result)) {
				$topic_update_sql .= 'topic_replies = topic_replies - 1, topic_first_post_id = ' . $row['first_post_id'];
			}
		} else {
			$topic_update_sql .= 'topic_replies = topic_replies - 1';
		}
	} elseif ($mode != 'poll_delete') {
		$forum_update_sql .= ", forum_last_post_id = $post_id" . (($mode == 'newtopic') ? ", forum_topics = forum_topics $sign" : ""); 
		$topic_update_sql = "topic_last_post_id = $post_id" . (($mode == 'reply') ? ", topic_replies = topic_replies $sign" : ", topic_first_post_id = $post_id");
	} else {
		$topic_update_sql .= 'topic_vote = 0';
	}

	if ($mode != 'poll_delete') {
		$sql = "UPDATE " . FORUMS_TABLE . " SET 
			$forum_update_sql 
			WHERE forum_id = $forum_id";

		if (!$db->sql_query($sql)) {
			message_die(GENERAL_ERROR, 'Error in posting', '', __LINE__, __FILE__, $sql);
		}
	}

	if ($topic_update_sql != '') {
		$sql = "UPDATE " . TOPICS_TABLE . " SET 
			$topic_update_sql 
			WHERE topic_id = $topic_id";

		if (!$db->sql_query($sql)) {
			message_die(GENERAL_ERROR, 'Error in posting', '', __LINE__, __FILE__, $sql);
		}
	}

	if ($mode != 'poll_delete') {
		$sql = "UPDATE " . USERS_TABLE . "
			SET user_posts = user_posts $sign 
			WHERE user_id = $user_id";

		if (!$db->sql_query($sql, END_TRANSACTION)) {
			message_die(GENERAL_ERROR, 'Error in posting', '', __LINE__, __FILE__, $sql);
		}
	}

	return;
}

//
// Delete a post/poll
//
function delete_post($mode, &$post_data, &$message, &$meta, &$forum_id, &$topic_id, &$post_id, &$poll_id)
{
	global $board_config, $lang, $db, $phpbb_root_path;
	global $userdata, $user_ip;

	if ($mode != 'poll_delete') {
		include $phpbb_root_path . 'includes/functions_search.php';

		dibi::delete(POSTS_TABLE)
            ->where('post_id = %i', $post_id)
            ->execute();

		dibi::delete(POSTS_TEXT_TABLE)
            ->where('post_id = %i', $post_id)
            ->execute();

		if ($post_data['last_post']) {
			if ($post_data['first_post']) {
				$forum_update_sql .= ', forum_topics = forum_topics - 1';
				$sql = "DELETE FROM " . TOPICS_TABLE . " 
					WHERE topic_id = $topic_id 
						OR topic_moved_id = $topic_id";

				if (!$db->sql_query($sql)) {
					message_die(GENERAL_ERROR, 'Error in deleting post', '', __LINE__, __FILE__, $sql);
				}

				dibi::delete(TOPICS_WATCH_TABLE)
                    ->where('topic_id = %i', $topic_id)
                    ->execute();
			}
		}

		remove_search_post($post_id);
	}

	if ($mode == 'poll_delete' || ($mode == 'delete' && $post_data['first_post'] && $post_data['last_post']) && $post_data['has_poll'] && $post_data['edit_poll']) {
		dibi::delete(VOTE_DESC_TABLE)
            ->where('topic_id = %i', $topic_id)
            ->execute();

		dibi::delete(VOTE_RESULTS_TABLE)
            ->where('vote_id = %i', $poll_id)
            ->execute();

		dibi::delete(VOTE_USERS_TABLE)
            ->where('vote_id = %i', $poll_id)
            ->execute();
	}

	if ($mode == 'delete' && $post_data['first_post'] && $post_data['last_post']) {
		$meta = '<meta http-equiv="refresh" content="3;url=' . append_sid("viewforum.php?" . POST_FORUM_URL . '=' . $forum_id) . '">';
		$message = $lang['Deleted'];
	} else {
		$meta = '<meta http-equiv="refresh" content="3;url=' . append_sid("viewtopic.php?" . POST_TOPIC_URL . '=' . $topic_id) . '">';
		$message = (($mode == 'poll_delete') ? $lang['Poll_delete'] : $lang['Deleted']) . '<br /><br />' . sprintf($lang['Click_return_topic'], '<a href="' . append_sid("viewtopic.php?" . POST_TOPIC_URL . "=$topic_id") . '">', '</a>');
	}

	$message .=  '<br /><br />' . sprintf($lang['Click_return_forum'], '<a href="' . append_sid("viewforum.php?" . POST_FORUM_URL . "=$forum_id") . '">', '</a>');

	return;
}

//
// Handle user notification on new post
//
function user_notification($mode, &$post_data, &$topic_title, &$forum_id, &$topic_id, &$post_id, &$notify_user)
{
	global $board_config, $lang, $db, $phpbb_root_path;
	global $userdata, $user_ip;

	$current_time = time();

	if ($mode != 'delete') {
		if ($mode == 'reply') {
			$sql = "SELECT ban_userid 
				FROM " . BANLIST_TABLE;

			if (!($result = $db->sql_query($sql))) {
				message_die(GENERAL_ERROR, 'Could not obtain banlist', '', __LINE__, __FILE__, $sql);
			}

			$user_id_sql = '';
			while ($row = $db->sql_fetchrow($result)) {
				if (isset($row['ban_userid']) && !empty($row['ban_userid'])) {
					$user_id_sql .= ', ' . $row['ban_userid'];
				}
			}

			$sql = "SELECT u.user_id, u.user_email, u.user_lang 
				FROM " . TOPICS_WATCH_TABLE . " tw, " . USERS_TABLE . " u 
				WHERE tw.topic_id = $topic_id 
					AND tw.user_id NOT IN (" . $userdata['user_id'] . ", " . ANONYMOUS . $user_id_sql . ") 
					AND tw.notify_status = " . TOPIC_WATCH_UN_NOTIFIED . " 
					AND u.user_id = tw.user_id";

			if (!($result = $db->sql_query($sql))) {
				message_die(GENERAL_ERROR, 'Could not obtain list of topic watchers', '', __LINE__, __FILE__, $sql);
			}

			$update_watched_sql = '';
			$bcc_list_ary = [];
			
			if ($row = $db->sql_fetchrow($result)) {
				// Sixty second limit
				@set_time_limit(60);

				do {
					if ($row['user_email'] != '') {
						$bcc_list_ary[$row['user_lang']][] = $row['user_email'];
					}

					$update_watched_sql .= ($update_watched_sql != '') ? ', ' . $row['user_id'] : $row['user_id'];
				}
				while ($row = $db->sql_fetchrow($result));

				//
				// Let's do some checking to make sure that mass mail functions
				// are working in win32 versions of php.
				//
				if (preg_match('/[c-z]:\\\.*/i', getenv('PATH')) && !$board_config['smtp_delivery']) {
					$ini_val = (@PHP_VERSION >= '4.0.0') ? 'ini_get' : 'get_cfg_var';

					// We are running on windows, force delivery to use our smtp functions
					// since php's are broken by default
					$board_config['smtp_delivery'] = 1;
					$board_config['smtp_host'] = @$ini_val('SMTP');
				}

				if (count($bcc_list_ary)) {
					include $phpbb_root_path . 'includes/emailer.php';
					$emailer = new emailer($board_config['smtp_delivery']);

					$script_name = preg_replace('/^\/?(.*?)\/?$/', '\1', trim($board_config['script_path']));
					$script_name = ($script_name != '') ? $script_name . '/viewtopic.php' : 'viewtopic.php';
					$server_name = trim($board_config['server_name']);
					$server_protocol = $board_config['cookie_secure'] ? 'https://' : 'http://';
					$server_port = ($board_config['server_port'] <> 80) ? ':' . trim($board_config['server_port']) . '/' : '/';

					$orig_word = [];
					$replacement_word = [];
					obtain_word_list($orig_word, $replacement_word);

					$emailer->from($board_config['board_email']);
					$emailer->replyto($board_config['board_email']);

					$topic_title = count($orig_word) ? preg_replace($orig_word, $replacement_word, unprepare_message($topic_title)) : unprepare_message($topic_title);

					@reset($bcc_list_ary);

					while (list($user_lang, $bcc_list) = each($bcc_list_ary)) {
						$emailer->use_template('topic_notify', $user_lang);
		
						for ($i = 0; $i < count($bcc_list); $i++) {
							$emailer->bcc($bcc_list[$i]);
						}

						// The Topic_reply_notification lang string below will be used
						// if for some reason the mail template subject cannot be read 
						// ... note it will not necessarily be in the posters own language!
						$emailer->set_subject($lang['Topic_reply_notification']); 
						
						// This is a nasty kludge to remove the username var ... till (if?)
						// translators update their templates
						$emailer->msg = preg_replace('#[ ]?{USERNAME}#', '', $emailer->msg);

						$emailer->assign_vars(array(
                                'EMAIL_SIG' => !empty($board_config['board_email_sig']) ? str_replace('<br />', "\n", "-- \n" . $board_config['board_email_sig']) : '',
                                'SITENAME' => $board_config['sitename'],
                                'TOPIC_TITLE' => $topic_title,

                                'U_TOPIC' => $server_protocol . $server_name . $server_port . $script_name . '?' . POST_POST_URL . "=$post_id#$post_id",
                                'U_STOP_WATCHING_TOPIC' => $server_protocol . $server_name . $server_port . $script_name . '?' . POST_TOPIC_URL . "=$topic_id&unwatch=topic")
						);

						$emailer->send();
						$emailer->reset();
					}
				}
			}
			$db->sql_freeresult($result);

			if ($update_watched_sql != '') {
				$sql = "UPDATE " . TOPICS_WATCH_TABLE . "
					SET notify_status = " . TOPIC_WATCH_NOTIFIED . "
					WHERE topic_id = $topic_id
						AND user_id IN ($update_watched_sql)";
				$db->sql_query($sql);
			}
		}

		$topic_watch = dibi::select('topic_id')
            ->from(TOPICS_WATCH_TABLE)
            ->where('topic_id = %i', $topic_id)
            ->where('user_id = %i', $userdata['user_id'])
            ->fetchSingle();

		if (!$notify_user && $topic_watch) {
		    dibi::delete(TOPICS_WATCH_TABLE)
                ->where('topic_id = %i', $topic_id)
                ->where('user_id = %i', $userdata['user_id'])
                ->execute();
		} elseif ($notify_user && $topic_watch === false) {
		    $insert_data = [
		        'user_id'  => $userdata['user_id'],
                'topic_id' => $topic_id,
                'notify_status' => 0
            ];

		    dibi::insert(TOPICS_WATCH_TABLE, $insert_data)->execute();
		}
	}
}

//
// Fill smiley templates (or just the variables) with smileys
// Either in a window or inline
//
function generate_smilies($mode, $page_id)
{
	global $db, $board_config, $template, $lang, $images, $theme, $phpbb_root_path;
	global $user_ip, $session_length;
	global $userdata;

	$inline_columns = 4;
	$inline_rows = 5;
	$window_columns = 8;

	if ($mode == 'window') {
		$userdata = session_pagestart($user_ip, $page_id);
		init_userprefs($userdata);

		$gen_simple_header = TRUE;

		$page_title = $lang['Emoticons'];
		include $phpbb_root_path . 'includes/page_header.php';

        $template->set_filenames(['smiliesbody' => 'posting_smilies.tpl']);
    }

    $sql = "SELECT emoticon, code, smile_url   
		FROM " . SMILIES_TABLE . " 
		ORDER BY smilies_id";

	if ($result = $db->sql_query($sql)) {
		$num_smilies = 0;
		$rowset = [];

		while ($row = $db->sql_fetchrow($result)) {
			if (empty($rowset[$row['smile_url']])) {
				$rowset[$row['smile_url']]['code'] = str_replace("'", "\\'", str_replace('\\', '\\\\', $row['code']));
				$rowset[$row['smile_url']]['emoticon'] = $row['emoticon'];
				$num_smilies++;
			}
		}

		if ($num_smilies) {
			$smilies_count = ($mode == 'inline') ? min(19, $num_smilies) : $num_smilies;
			$smilies_split_row = ($mode == 'inline') ? $inline_columns - 1 : $window_columns - 1;

			$s_colspan = 0;
			$row = 0;
			$col = 0;

			while (list($smile_url, $data) = @each($rowset)) {
				if (!$col) {
					$template->assign_block_vars('smilies_row', []);
				}

                $template->assign_block_vars('smilies_row.smilies_col',
                    [
                        'SMILEY_CODE' => $data['code'],
                        'SMILEY_IMG'  => $board_config['smilies_path'] . '/' . $smile_url,
                        'SMILEY_DESC' => $data['emoticon']
                    ]
                );

                $s_colspan = max($s_colspan, $col + 1);

                if ($col == $smilies_split_row) {
                    if ($mode == 'inline' && $row == $inline_rows - 1) {
                        break;
                    }

                    $col = 0;
                    $row++;
                } else {
                    $col++;
                }
			}

			if ($mode == 'inline' && $num_smilies > $inline_rows * $inline_columns) {
				$template->assign_block_vars('switch_smilies_extra', []);

                $template->assign_vars(
                    [
                        'L_MORE_SMILIES' => $lang['More_emoticons'],
                        'U_MORE_SMILIES' => append_sid("posting.php?mode=smilies")
                    ]
                );
            }

            $template->assign_vars(
                [
                    'L_EMOTICONS'       => $lang['Emoticons'],
                    'L_CLOSE_WINDOW'    => $lang['Close_window'],
                    'S_SMILIES_COLSPAN' => $s_colspan
                ]
            );
        }
    }

    if ($mode == 'window') {
		$template->pparse('smiliesbody');

		include $phpbb_root_path . 'includes/page_tail.php';
	}
}

/**
* Called from within prepare_message to clean included HTML tags if HTML is
* turned on for that post
* @param array $tag Matching text from the message to parse
*/
function clean_html($tag)
{
	global $board_config;

	if (empty($tag[0])) {
		return '';
	}

	$allowed_html_tags = preg_split('/, */', strtolower($board_config['allow_html_tags']));
	$disallowed_attributes = '/^(?:style|on)/i';

	// Check if this is an end tag
	preg_match('/<[^\w\/]*\/[\W]*(\w+)/', $tag[0], $matches);
	if (count($matches)) {
        if (in_array(strtolower($matches[1]), $allowed_html_tags)) {
            return '</' . $matches[1] . '>';
        } else {
            return htmlspecialchars('</' . $matches[1] . '>');
        }
	}

	// Check if this is an allowed tag
	if (in_array(strtolower($tag[1]), $allowed_html_tags)) {
		$attributes = '';

		if (!empty($tag[2])) {
			preg_match_all('/[\W]*?(\w+)[\W]*?=[\W]*?(["\'])((?:(?!\2).)*)\2/', $tag[2], $test);

			for ($i = 0; $i < count($test[0]); $i++) {
				if (preg_match($disallowed_attributes, $test[1][$i])) {
					continue;
				}
				$attributes .= ' ' . $test[1][$i] . '=' . $test[2][$i] . str_replace(['[', ']'], ['&#91;', '&#93;'], htmlspecialchars($test[3][$i])) . $test[2][$i];
			}
		}

		if (in_array(strtolower($tag[1]), $allowed_html_tags)) {
			return '<' . $tag[1] . $attributes . '>';
		} else {
			return htmlspecialchars('<' . $tag[1] . $attributes . '>');
		}
	}
	// Finally, this is not an allowed tag so strip all the attibutes and escape it
	else {
		return htmlspecialchars('<' .   $tag[1] . '>');
	}
}
?>