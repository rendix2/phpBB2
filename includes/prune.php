<?php
/***************************************************************************
*                                 prune.php
*                            -------------------
*   begin                : Thursday, June 14, 2001
*   copyright            : (C) 2001 The phpBB Group
*   email                : support@phpbb.com
*
*   $Id: prune.php 5508 2006-01-29 17:31:16Z grahamje $
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

if (!defined('IN_PHPBB')) {
   die('Hacking attempt');
}

require $phpbb_root_path . 'includes/functions_search.php';

function prune($forum_id, $prune_date, $prune_all = false)
{
	$topics = dibi::select('topic_id')
        ->from(TOPICS_TABLE)
        ->where('topic_last_post_id = %i', 0)
        ->fetchAll();

	foreach ($topics as $topic) {
	    sync('topic', $topic->topic_id);
    }

	//
	// Those without polls and announcements ... unless told otherwise!
	//
    $topic_query = dibi::select('t.topic_id')
        ->from(POSTS_TABLE)
        ->as('p')
        ->innerJoin(TOPICS_TABLE)
        ->as('t')
        ->on('p.post_id = t.topic_last_post_id')
        ->where('t.forum_id = %i', $forum_id);

    if (!$prune_all) {
        $topic_query->where('t.topic_vote = %i', 0)
            ->where('t.topic_type <> %i', POST_ANNOUNCE);
    }

	if ($prune_date) {
	    $topic_query->where('p.post_time < %i', $prune_date);
    }

	$topic_data = $topic_query->fetchPairs(null, 'topic_id');
		
	if (count($topic_data)) {
	    $post_ids = dibi::select('post_id')
            ->from(POSTS_TABLE)
            ->where('forum_id = %i', $forum_id)
            ->where('topic_id IN %in', $topic_data)
            ->fetchPairs(null, 'post_id');

		if (count($post_ids)) {
		    $user_ids = dibi::select('poster_id')
                ->from(POSTS_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->fetchPairs(null, 'user_id');

		    $user_counts = [];

		    foreach ($user_ids as $user_id) {
		        if (isset($user_counts[$user_id])) {
                    $user_counts[$user_id]++;
                } else {
		            $user_counts[$user_id] = 1;
                }
            }

		    foreach ($user_counts as $user_id => $user_count) {
                dibi::update(USERS_TABLE, ['user_posts%sql' => 'user_posts - ' . $user_count])
                    ->where('user_id = %i', $user_id)
                    ->execute();
            }

		    dibi::delete(TOPICS_WATCH_TABLE)
                ->where('topic_id IN %in', $topic_data)
                ->execute();

            $pruned_topics = dibi::delete(TOPICS_TABLE)
                ->where('topic_id IN %in', $topic_data)
                ->execute(dibi::AFFECTED_ROWS);

            $pruned_posts = dibi::delete(POSTS_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->execute(dibi::AFFECTED_ROWS);

            dibi::delete(POSTS_TEXT_TABLE)
                ->where('post_id IN %in', $post_ids)
                ->execute(dibi::AFFECTED_ROWS);

			remove_search_post($post_ids);

            return ['topics' => $pruned_topics, 'posts' => $pruned_posts];
        }
    }

    return ['topics' => 0, 'posts' => 0];
}

//
// Function auto_prune(), this function will read the configuration data from
// the auto_prune table and call the prune function with the necessary info.
//
function auto_prune($forum_id = 0)
{
	$prune = dibi::select('*')
        ->from(PRUNE_TABLE)
        ->where('forum_id = %i', $forum_id)
        ->fetch();

    if (!$prune) {
        return;
    }

    if ($prune->prune_freq && $prune->prune_days) {
        $user_timezone = isset($userdata['user_timezone']) ? $userdata['user_timezone'] : $board_config['board_timezone'];

        $time_zone = new DateTimeZone($user_timezone);

        $prune_date = new DateTime();
        $prune_date->setTimezone($time_zone);
        $prune_date->sub(new DateInterval('P' . $prune->prune_days . 'D'))
            ->getTimestamp();

        $next_prune = new DateTime();
        $next_prune->setTimezone($time_zone);
        $next_prune->add(new DateInterval('P' . $prune->prune_freq . 'D'))
            ->getTimestamp();

        prune($forum_id, $prune_date);
        sync('forum', $forum_id);

        dibi::update(FORUMS_TABLE, ['prune_next' => $next_prune])
            ->where('forum_id = %i', $forum_id)
            ->execute();
    }
}

?>