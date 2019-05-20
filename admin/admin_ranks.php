<?php
/***************************************************************************
 *                              admin_ranks.php
 *                            -------------------
 *   begin                : Thursday, Jul 12, 2001
 *   copyright            : (C) 2001 The phpBB Group
 *   email                : support@phpbb.com
 *
 *   $Id: admin_ranks.php 8377 2008-02-10 12:52:05Z acydburn $
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

if (!empty($setmodules) ) {
	$file = basename(__FILE__);
	$module['Users']['Ranks'] = $file;
	return;
}

define('IN_PHPBB', 1);

//
// Let's set the root dir for phpBB
//
$phpbb_root_path = "./../";

$cancel = ( isset($_POST['cancel']) || isset($_POST['cancel']) ) ? true : false;
$no_page_header = $cancel;

require './pagestart.php';

if ($cancel) {
	redirect('admin/' . append_sid("admin_ranks.php", true));
}

if (isset($_GET['mode']) || isset($_POST['mode']) ) {
	$mode = isset($_GET['mode']) ? $_GET['mode'] : $_POST['mode'];
	$mode = htmlspecialchars($mode);
} else {
	//
	// These could be entered via a form button
	//
	if (isset($_POST['add'])) {
		$mode = "add";
	} elseif (isset($_POST['save'])) {
		$mode = "save";
	} else {
		$mode = "";
	}
}

// Restrict mode input to valid options
$mode = in_array($mode, ['add', 'edit', 'save', 'delete']) ? $mode : '';

if ($mode != "" ) {
	if ($mode == "edit" || $mode == "add" ) {
		//
		// They want to add a new rank, show the form.
		//
		$rank_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
		
		$s_hidden_fields = "";
		
		if ($mode == "edit" ) {
			if (empty($rank_id) ) {
				message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
			}

			$rank_info = dibi::select('*')
				->from(RANKS_TABLE)
				->where('rank_id = %i', $rank_id)
				->fetch();

			if (!$rank_info) {
				message_die(GENERAL_ERROR, "Couldn't obtain rank data");
			}

			$s_hidden_fields .= '<input type="hidden" name="id" value="' . $rank_id . '" />';
		} else {
			$rank_info['rank_special'] = 0;
		}

		$s_hidden_fields .= '<input type="hidden" name="mode" value="save" />';

		$rank_is_special = $rank_info->rank_special ? "checked=\"checked\"" : "";
		$rank_is_not_special = ( !$rank_info->rank_special ) ? "checked=\"checked\"" : "";

		$template->set_filenames(["body" => "admin/ranks_edit_body.tpl"]);

		$template->assign_vars(
			[
				"RANK"             => $rank_info->rank_title,
				"SPECIAL_RANK"     => $rank_is_special,
				"NOT_SPECIAL_RANK" => $rank_is_not_special,
				"MINIMUM"          => $rank_is_special ? "" : $rank_info->rank_min,
				"IMAGE"            => ($rank_info->rank_image != "") ? $rank_info->rank_image : "",
				"IMAGE_DISPLAY"    => ($rank_info->rank_image != "") ? '<img src="../' . $rank_info->rank_image . '" />' : "",

				"L_RANKS_TITLE"        => $lang['Ranks_title'],
				"L_RANKS_TEXT"         => $lang['Ranks_explain'],
				"L_RANK_TITLE"         => $lang['Rank_title'],
				"L_RANK_SPECIAL"       => $lang['Rank_special'],
				"L_RANK_MINIMUM"       => $lang['Rank_minimum'],
				"L_RANK_IMAGE"         => $lang['Rank_image'],
				"L_RANK_IMAGE_EXPLAIN" => $lang['Rank_image_explain'],
				"L_SUBMIT"             => $lang['Submit'],
				"L_RESET"              => $lang['Reset'],
				"L_YES"                => $lang['Yes'],
				"L_NO"                 => $lang['No'],

				"S_RANK_ACTION"   => append_sid("admin_ranks.php"),
				"S_HIDDEN_FIELDS" => $s_hidden_fields
			]
		);
	} elseif ($mode == "save" ) {
		//
		// Ok, they sent us our info, let's update it.
		//
		
		$rank_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$rank_title = isset($_POST['title']) ? trim($_POST['title']) : "";
		$special_rank = ( $_POST['special_rank'] == 1 ) ? TRUE : 0;
		$min_posts = isset($_POST['min_posts']) ? (int)$_POST['min_posts'] : -1;
		$rank_image = isset($_POST['rank_image']) ? trim($_POST['rank_image']) : "";

		if ($rank_title == "" ) {
			message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
		}

		if ($special_rank == 1 ) {
			$max_posts = -1;
			$min_posts = -1;
		}

		//
		// The rank image has to be a jpg, gif or png
		//
		if ($rank_image != "") {
			if ( !preg_match("/(\.gif|\.png|\.jpg)$/is", $rank_image)) {
				$rank_image = "";
			}
		}

		if ($rank_id) {
			if (!$special_rank) {
				dibi::update(USERS_TABLE, ['user_rank' => 0])
					->where('user_rank = %i', $rank_id)
					->execute();
			}

			$update_data = [
				'rank_title' => $rank_title,
				'rank_special' => $special_rank,
				'rank_min' => $min_posts,
				'rank_image' => $rank_image
			];

			dibi::update(RANKS_TABLE, $update_data)
				->where('rank_id = %i', $rank_id)
				->execute();

			$message = $lang['Rank_updated'];
		} else {
			$insert_data = [
				'rank_title' => $rank_title,
				'rank_special' => $special_rank,
				'rank_min' => $min_posts,
				'rank_image' => $rank_image
			];

			dibi::insert(RANKS_TABLE, $insert_data)->execute();

			$message = $lang['Rank_added'];
		}

		$message .= "<br /><br />" . sprintf($lang['Click_return_rankadmin'], "<a href=\"" . append_sid("admin_ranks.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

		message_die(GENERAL_MESSAGE, $message);

	} elseif ($mode == "delete" ) {
		//
		// Ok, they want to delete their rank
		//
		
		if (isset($_POST['id']) || isset($_GET['id']) ) {
			$rank_id = isset($_POST['id']) ? (int)$_POST['id'] : (int)$_GET['id'];
		} else {
			$rank_id = 0;
		}

		$confirm = isset($_POST['confirm']);
		
		if ($rank_id && $confirm ) {
			dibi::delete(RANKS_TABLE)
				->where('rank_id = %i', $rank_id)
				->execute();

			$result = dibi::update(USERS_TABLE, ['user_rank' => 0])
				->where('user_rank = %i', $rank_id)
				->execute();

			if (!$result) {
				message_die(GENERAL_ERROR, $lang['No_update_ranks']);
			}

			$message = $lang['Rank_removed'] . "<br /><br />" . sprintf($lang['Click_return_rankadmin'], "<a href=\"" . append_sid("admin_ranks.php") . "\">", "</a>") . "<br /><br />" . sprintf($lang['Click_return_admin_index'], "<a href=\"" . append_sid("index.php?pane=right") . "\">", "</a>");

			message_die(GENERAL_MESSAGE, $message);

		} elseif ($rank_id && !$confirm) {
			// Present the confirmation screen to the user
			$template->set_filenames(['body' => 'admin/confirm_body.tpl']);

			$hidden_fields = '<input type="hidden" name="mode" value="delete" /><input type="hidden" name="id" value="' . $rank_id . '" />';

			$template->assign_vars(
				[
					'MESSAGE_TITLE' => $lang['Confirm'],
					'MESSAGE_TEXT'  => $lang['Confirm_delete_rank'],

					'L_YES' => $lang['Yes'],
					'L_NO'  => $lang['No'],

					'S_CONFIRM_ACTION' => append_sid("admin_ranks.php"),
					'S_HIDDEN_FIELDS'  => $hidden_fields
				]
			);
		} else {
			message_die(GENERAL_MESSAGE, $lang['Must_select_rank']);
		}
	}

	$template->pparse("body");

	include './page_footer_admin.php';
}

//
// Show the default page
//
$template->set_filenames(["body" => "admin/ranks_list_body.tpl"]);

$ranks  = dibi::select('*')
    ->from(RANKS_TABLE)
    ->orderBy('rank_min', dibi::ASC)
    ->orderBy('rank_special', dibi::ASC)
    ->fetchAll();

$rank_count = count($ranks);

$template->assign_vars(
	[
		"L_RANKS_TITLE"  => $lang['Ranks_title'],
		"L_RANKS_TEXT"   => $lang['Ranks_explain'],
		"L_RANK"         => $lang['Rank_title'],
		"L_RANK_MINIMUM" => $lang['Rank_minimum'],
		"L_SPECIAL_RANK" => $lang['Rank_special'],
		"L_EDIT"         => $lang['Edit'],
		"L_DELETE"       => $lang['Delete'],
		"L_ADD_RANK"     => $lang['Add_new_rank'],
		"L_ACTION"       => $lang['Action'],

		"S_RANKS_ACTION" => append_sid("admin_ranks.php")
	]
);

foreach ($ranks as $rank) {
	$special_rank = $rank->rank_special;
	$rank_id = $rank->rank_id;
	$rank_min = $rank->rank_min;
	
	if ($special_rank == 1 ) {
		$rank_min = $rank_max = "-";
	}

	$row_color = ( !($i % 2) ) ? $theme['td_color1'] : $theme['td_color2'];
	$row_class = ( !($i % 2) ) ? $theme['td_class1'] : $theme['td_class2'];

	$rank_is_special = $special_rank ? $lang['Yes'] : $lang['No'];

	$template->assign_block_vars("ranks",
		[
			"ROW_COLOR"    => "#" . $row_color,
			"ROW_CLASS"    => $row_class,
			"RANK"         => $rank->rank_title,
			"SPECIAL_RANK" => $rank_is_special,
			"RANK_MIN"     => $rank_min,

			"U_RANK_EDIT"   => append_sid("admin_ranks.php?mode=edit&amp;id=$rank_id"),
			"U_RANK_DELETE" => append_sid("admin_ranks.php?mode=delete&amp;id=$rank_id")
		]
	);
}

$template->pparse("body");

include './page_footer_admin.php';

?>
