<?php
/**
 *
 * @package attachment_mod
 * @version $Id: displaying.php,v 1.5 2006/09/06 14:26:29 acydburn Exp $
 * @copyright (c) 2002 Meik Sievertsen
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

/**
 */

$allowed_extensions = [];
$display_categories = [];
$download_modes = [];
$upload_icons = [];
$attachments = [];

/**
 * Clear the templates compile cache
 *
 * @param string $filename
 * @param        $template_var
 */
function display_compile_cache_clear($filename, $template_var)
{
    global $template;

    if ($template instanceof TemplateFile) {
        $filename = str_replace($template->getRoot(), '', $filename);

        if (mb_substr($filename, 0, 1) === '/') {
            $filename = mb_substr($filename, 1, mb_strlen($filename));
        }

        if (file_exists($template->getCacheDir() . $filename . '.php')) {
            @unlink($template->getCacheDir() . $filename . '.php');
        }
    }
}

/**
 * Create needed arrays for Extension Assignments
 */
function init_complete_extensions_data()
{
    global $allowed_extensions, $display_categories, $download_modes, $upload_icons;

    $extension_informations = get_extension_informations();

    $allowed_extensions = [];

    foreach ($extension_informations as $extensionInformation) {
        $extension = mb_strtolower(trim($extensionInformation->extension));
        $allowed_extensions[] = $extension;
        $display_categories[$extension] = (int)$extensionInformation->cat_id;
        $download_modes[$extension] = (int)$extensionInformation->download_mode;
        $upload_icons[$extension] = trim($extensionInformation->upload_icon);
    }
}

/**
 * Writing Data into plain Template Vars
 *
 * @param        $template_var
 * @param        $replacement
 * @param string $filename
 */
function init_display_template($template_var, $replacement, $filename = 'viewtopic_attach_body.tpl')
{
    global $template;

    // This function is adapted from the old template class
    // I wish i had the functions from the 3.x one. :D (This class rocks, can't await to use it in Mods)

    // Handle Attachment Informations
    if (!isset($template->getUnCompiledCode()[$template_var]) && empty($template->getUnCompiledCode()[$template_var])) {
        // If we don't have a file assigned to this handle, die.
        if (!isset($template->getFiles()[$template_var])) {
            die("Template->loadfile(): No file specified for handle $template_var");
        }

        $filename_2 = $template->getFiles()[$template_var];

        $str = implode('', @file($filename_2));
        if (empty($str)) {
            die("Template->loadfile(): File $filename_2 for handle $template_var is empty");
        }

        $template->addUnCompiledCode($template_var, $str);
    }

    $complete_filename = $filename;

    if (mb_substr($complete_filename, 0, 1) !== '/') {
        $complete_filename = $template->getRoot() . '/' . $complete_filename;
    }

    if (!file_exists($complete_filename)) {
        die("Template->make_filename(): Error - file $complete_filename does not exist");
    }

    $content = implode('', file($complete_filename));
    if (empty($content)) {
        die('Template->loadfile(): File ' . $complete_filename . ' is empty');
    }

    // replace $replacement with uncompiled code in $filename
    $template->addUnCompiledCode($template_var, str_replace($replacement, $content, $template->getUnCompiledCode()[$template_var]));

    // Force Reload on cached version
    display_compile_cache_clear($template->getFiles()[$template_var], $template_var);
}

/**
 * BEGIN ATTACHMENT DISPLAY IN POSTS
 */

/**
 * Returns the image-tag for the topic image icon
 *
 * @param $switch_attachment
 *
 * @return string
 */
function topic_attachment_image($switch_attachment)
{
    global $attach_config, $is_auth;

    if ((int)$switch_attachment === 0 || (!($is_auth['auth_download'] && $is_auth['auth_view'])) || (int)$attach_config['disable_mod'] || $attach_config['topic_icon'] === '') {
        return '';
    }

    return '<img src="' . $attach_config['topic_icon'] . '" alt="" border="0" /> ';
}

/**
 * Display Attachments in Posts
 * @param $post_id
 * @param $switch_attachment
 * @throws \Dibi\Exception
*/
function display_post_attachments($post_id, $switch_attachment)
{
    global $attach_config, $is_auth;

    if ((int)$switch_attachment === 0 || (int)$attach_config['disable_mod']) {
        return;
    }

    if ($is_auth['auth_download'] && $is_auth['auth_view']) {
        display_attachments($post_id);
    } else {
        // Display Notice (attachment there but not having permissions to view it)
        // Not included because this would mean template and language file changes (at this stage this is not a wise step. ;))
    }
}

/*
//
// Generate the Display Assign File Link
//
function display_assign_link($post_id)
{
	global $attach_config, $is_auth, $phpEx;

	$image = 'templates/subSilver/images/icon_mini_message.gif';

	if ( (intval($attach_config['disable_mod'])) || (!( ($is_auth['auth_download']) && ($is_auth['auth_view']))) )
	{
		return ('');
	}

	$temp_url = append_sid("assign_file.$phpEx?p=" . $post_id);
	$link = '<a href="' . $temp_url . '" target="_blank"><img src="' . $image . '" alt="Add File" title="Add File" border="0" /></a>';
	
	return ($link);
}
*/

/**
 * Initializes some templating variables for displaying Attachments in Posts
 * @param $switch_attachment
*/
function init_display_post_attachments($switch_attachment)
{
    global $attach_config, $is_auth, $template, $lang, $posts, $attachments, $forum_row, $forum_topic_data;

    if (empty($forum_topic_data) && !empty($forum_row)) {
        $switch_attachment = $forum_row['topic_attachment'];
    }

    if ((int)$switch_attachment === 0 || (int)$attach_config['disable_mod'] || (!($is_auth['auth_download'] && $is_auth['auth_view']))) {
        return;
    }

    $post_id_array = [];

    foreach ($posts as $post) {
        if ($post->post_attachment === 1) {
            $post_id_array[] = (int)$post->post_id;
        }
    }

    if (count($post_id_array) === 0) {
        return;
    }

    $rows = get_attachments_from_post($post_id_array);
    $num_rows = count($rows);

    if ($num_rows === 0) {
        return;
    }

    @reset($attachments);

    foreach ($rows as $row) {
        $attachments['_' . $row->post_id][] = $row;
    }

    init_display_template('body', '{postrow.ATTACHMENTS}');

    init_complete_extensions_data();

    $template->assignVars(
        [
            'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
            'L_KILOBYTE' => $lang['KB']
        ]
    );
}

/**
 * END ATTACHMENT DISPLAY IN POSTS
 */

/**
 * BEGIN ATTACHMENT DISPLAY IN PM's
 */

/**
 * Returns the image-tag for the PM image icon
 * @param $privmsg_id
 * @return string
*/
function privmsgs_attachment_image($privmsg_id)
{
    global $attach_config, $userdata;

    $auth = $userdata['user_level'] === ADMIN ? 1 : (int)$attach_config['allow_pm_attach'];

    if (!attachment_exists_db($privmsg_id, PAGE_PRIVMSGS) || !$auth || (int)$attach_config['disable_mod'] || $attach_config['topic_icon'] === '') {
        return '';
    }

    return '<img src="' . $attach_config['topic_icon'] . '" alt="" border="0" /> ';
}

/**
 * Display Attachments in PM's
 * @param $privmsgs_id
 * @param $switch_attachment
 * @throws \Dibi\Exception
*/
function display_pm_attachments($privmsgs_id, $switch_attachment)
{
    global $attach_config, $userdata, $template, $lang;

    if ($userdata['user_level'] === ADMIN) {
        $auth_download = 1;
    } else {
        $auth_download = (int)$attach_config['allow_pm_attach'];
    }

    if ((int)$switch_attachment === 0 || (int)$attach_config['disable_mod'] || !$auth_download) {
        return;
    }

    display_attachments($privmsgs_id);

    $template->assignBlockVars('switch_attachments', []);
    $template->assignVars(
        [
            'L_DELETE_ATTACHMENTS' => $lang['Delete_attachments']
        ]
    );
}

/**
 * Initializes some templating variables for displaying Attachments in Private Messages
 * @param $switch_attachment
*/
function init_display_pm_attachments($switch_attachment)
{
    global $attach_config, $template, $userdata, $lang, $attachments, $privmsg;

    if ($userdata['user_level'] === ADMIN) {
        $auth_download = 1;
    } else {
        $auth_download = (int)$attach_config['allow_pm_attach'];
    }

    if ((int)$switch_attachment === 0 || (int)$attach_config['disable_mod'] || !$auth_download) {
        return;
    }

    $privmsgs_id = $privmsg['privmsgs_id'];

    @reset($attachments);
    $attachments['_' . $privmsgs_id] = get_attachments_from_pm($privmsgs_id);

    if (count($attachments['_' . $privmsgs_id]) === 0) {
        return;
    }

    $template->assignBlockVars('postrow', []);

    init_display_template('body', '{ATTACHMENTS}');

    init_complete_extensions_data();

    $template->assignVars(
        [
            'L_POSTED_ATTACHMENTS' => $lang['Posted_attachments'],
            'L_KILOBYTE' => $lang['KB']
        ]
    );

    display_pm_attachments($privmsgs_id, $switch_attachment);
}

/**
 * END ATTACHMENT DISPLAY IN PM's
 */

/**
 * BEGIN ATTACHMENT DISPLAY IN TOPIC REVIEW WINDOW
 */

/**
 * Display Attachments in Review Window
 * @param $post_id
 * @param $switch_attachment
 * @param $is_auth
 * @throws \Dibi\Exception
*/
function display_review_attachments($post_id, $switch_attachment, $is_auth)
{
    global $attach_config, $attachments;

    if ((int)$switch_attachment === 0 || (int)$attach_config['disable_mod'] || (!($is_auth['auth_download'] && $is_auth['auth_view'])) || (int)$attach_config['attachment_topic_review'] === 0) {
        return;
    }

    @reset($attachments);
    $attachments['_' . $post_id] = get_attachments_from_post($post_id);

    if (count($attachments['_' . $post_id]) === 0) {
        return;
    }

    display_attachments($post_id);
}

/**
 * Initializes some templating variables for displaying Attachments in Review Topic Window
 * @param $is_auth
*/
function init_display_review_attachments($is_auth)
{
    global $attach_config;

    if ((int)$attach_config['disable_mod'] || (!($is_auth['auth_download'] && $is_auth['auth_view'])) || (int)$attach_config['attachment_topic_review'] === 0) {
        return;
    }

    init_display_template('reviewbody', '{postrow.ATTACHMENTS}');

    init_complete_extensions_data();
}

/**
 * END ATTACHMENT DISPLAY IN TOPIC REVIEW WINDOW
 */

/**
 * BEGIN DISPLAY ATTACHMENTS -> PREVIEW
 * @param $attachment_list
 * @param $attachment_filesize_list
 * @param $attachment_filename_list
 * @param $attachment_comment_list
 * @param $attachment_extension_list
 * @param $attachment_thumbnail_list
*/
function display_attachments_preview($attachment_list, $attachment_filesize_list, $attachment_filename_list, $attachment_comment_list, $attachment_extension_list, $attachment_thumbnail_list)
{
    global $attach_config, $is_auth, $allowed_extensions, $lang, $userdata, $display_categories, $upload_dir, $upload_icons, $template, $theme;

    if (count($attachment_list) !== 0) {
        init_display_template('preview', '{ATTACHMENTS}');

        init_complete_extensions_data();

        $template->assignBlockVars('postrow', []);
        $template->assignBlockVars('postrow.attach', []);

        $template->assignVars(
            [
                'T_BODY_TEXT' => '#' . $theme['body_text'],
                'T_TR_COLOR3' => '#' . $theme['tr_color3']
            ]
        );

        foreach ($attachment_list as $i => $attachmentValue) {
            $filename = $upload_dir . '/' . basename($attachmentValue);
            $thumb_filename = $upload_dir . '/' . THUMB_DIR . '/t_' . basename($attachmentValue);
            $filesize = get_formatted_filesize($attachment_filesize_list[$i]);
            $display_name = $attachment_filename_list[$i];
            $comment = nl2br($attachment_comment_list[$i]);
            $extension = $attachment_extension_list[$i];

            $denied = false;

            // Admin is allowed to view forbidden Attachments, but the error-message is displayed too to inform the Admin
            if (!in_array($extension, $allowed_extensions, true)) {
                $denied = true;

                $template->assignBlockVars('postrow.attach.denyrow',
                    [
                        'L_DENIED' => sprintf($lang['Extension_disabled_after_posting'], $extension)
                    ]
                );
            }

            if (!$denied) {
                // Some basic Template Vars
                $template->assignVars(
                    [
                        'L_DESCRIPTION' => $lang['Description'],
                        'L_DOWNLOAD' => $lang['Download'],
                        'L_FILENAME' => $lang['File_name'],
                        'L_FILESIZE' => $lang['Filesize']
                    ]
                );

                // define category
                $image = false;
                $stream = false;
                $swf = false;
                $thumbnail = false;
                $link = false;

                if ((int)$display_categories[$extension] === STREAM_CAT) {
                    $stream = true;
                } else if ((int)$display_categories[$extension] === SWF_CAT) {
                    $swf = true;
                } else if ((int)$display_categories[$extension] === IMAGE_CAT && (int)$attach_config['img_display_inlined']) {
                    if ((int)$attach_config['img_link_width'] !== 0 || (int)$attach_config['img_link_height'] !== 0) {
                        list($width, $height) = image_getdimension($filename);

                        if ($width === 0 && $height === 0) {
                            $image = true;
                        } else {
                            if ($width <= (int)$attach_config['img_link_width'] && $height <= (int)$attach_config['img_link_height']) {
                                $image = true;
                            }
                        }
                    } else {
                        $image = true;
                    }
                }

                if ((int)$display_categories[$extension] === IMAGE_CAT && (int)$attachment_thumbnail_list[$i] === 1) {
                    $thumbnail = true;
                    $image = false;
                }

                if (!$image && !$stream && !$swf && !$thumbnail) {
                    $link = true;
                }

                if ($image) {
                    // Images
                    $template->assignBlockVars('postrow.attach.cat_images',
                        [
                            'DOWNLOAD_NAME' => $display_name,
                            'IMG_SRC' => $filename,
                            'FILESIZE' => $filesize,
                            'COMMENT' => $comment,
                            'L_DOWNLOADED_VIEWED' => $lang['Viewed']
                        ]
                    );
                }

                if ($thumbnail) {
                    // Images, but display Thumbnail
                    $template->assignBlockVars('postrow.attach.cat_thumb_images',
                        [
                            'DOWNLOAD_NAME' => $display_name,
                            'IMG_SRC' => $filename,
                            'IMG_THUMB_SRC' => $thumb_filename,
                            'FILESIZE' => $filesize,
                            'COMMENT' => $comment,
                            'L_DOWNLOADED_VIEWED' => $lang['Viewed']
                        ]
                    );
                }

                if ($stream) {
                    // Streams
                    $template->assignBlockVars('postrow.attach.cat_stream',
                        [
                            'U_DOWNLOAD_LINK' => $filename,
                            'DOWNLOAD_NAME' => $display_name,
                            'FILESIZE' => $filesize,
                            'COMMENT' => $comment,
                            'L_DOWNLOADED_VIEWED' => $lang['Viewed']
                        ]
                    );
                }

                if ($swf) {
                    // Macromedia Flash Files
                    list($width, $height) = swf_getdimension($filename);

                    $template->assignBlockVars('postrow.attach.cat_swf',
                        [
                            'U_DOWNLOAD_LINK' => $filename,
                            'DOWNLOAD_NAME' => $display_name,
                            'FILESIZE' => $filesize,
                            'COMMENT' => $comment,
                            'L_DOWNLOADED_VIEWED' => $lang['Viewed'],
                            'WIDTH' => $width,
                            'HEIGHT' => $height
                        ]
                    );
                }

                if ($link) {
                    $upload_image = '';

                    if ($attach_config['upload_img'] !== '' && $upload_icons[$extension] === '') {
                        $upload_image = '<img src="' . $attach_config['upload_img'] . '" alt="" border="0" />';
                    } else if (trim($upload_icons[$extension]) !== '') {
                        $upload_image = '<img src="' . $upload_icons[$extension] . '" alt="" border="0" />';
                    }

                    $target_blank = 'target="_blank"';

                    // display attachment
                    $template->assignBlockVars('postrow.attach.attachrow',
                        [
                            'U_DOWNLOAD_LINK' => $filename,
                            'S_UPLOAD_IMAGE' => $upload_image,

                            'DOWNLOAD_NAME' => $display_name,
                            'FILESIZE' => $filesize,
                            'COMMENT' => $comment,
                            'L_DOWNLOADED_VIEWED' => $lang['Downloaded'],
                            'TARGET_BLANK' => $target_blank
                        ]
                    );
                }
            }
        }
    }
}

/**
 * END DISPLAY ATTACHMENTS -> PREVIEW
 */

/**
 * Assign Variables and Definitions based on the fetched Attachments - internal
 * used by all displaying functions, the Data was collected before, it's only dependend on the template used. :)
 * before this function is usable, init_display_attachments have to be called for specific pages (pm, posting, review etc...)
 * @param $post_id
 * @throws \Dibi\Exception
*/
function display_attachments($post_id)
{
    global $template, $upload_dir, $userdata, $allowed_extensions, $display_categories, $download_modes, $lang, $attachments, $upload_icons, $attach_config;
    global $phpbb_root_path;

    $num_attachments = count($attachments['_' . $post_id]);

    if ($num_attachments === 0) {
        return;
    }

    $template->assignBlockVars('postrow.attach', []);

    foreach ($attachments['_' . $post_id] as $i => $attachment) {
        bdump($attachment);
        // Some basic things...
        $filename = $upload_dir . '/' . basename($attachment->physical_filename);
        $thumbnail_filename = $upload_dir . '/' . THUMB_DIR . '/t_' . basename($attachment->physical_filename);

        $upload_image = '';

        if ($attach_config['upload_img'] !== '' && trim($upload_icons[$attachment->extension]) === '') {
            $upload_image = '<img src="' . $attach_config['upload_img'] . '" alt="" border="0" />';
        } else if (trim($upload_icons[$attachment->extension]) !== '') {
            $upload_image = '<img src="' . $upload_icons[$attachment->extension] . '" alt="" border="0" />';
        }

        $filesize = get_formatted_filesize($attachment->filesize);
        $display_name = $attachment->real_filename;
        $comment = nl2br($attachment->comment);

        $denied = false;

        // Admin is allowed to view forbidden Attachments, but the error-message is displayed too to inform the Admin
        if (!in_array($attachment->extension, $allowed_extensions, true)) {
            $denied = true;

            $template->assignBlockVars('postrow.attach.denyrow',
                [
                    'L_DENIED' => sprintf($lang['Extension_disabled_after_posting'], $attachment->extension)
                ]
            );
        }

        if (!$denied || $userdata['user_level'] === ADMIN) {
            // Some basic Template Vars
            $template->assignVars(
                [
                    'L_DESCRIPTION' => $lang['Description'],
                    'L_DOWNLOAD' => $lang['Download'],
                    'L_FILENAME' => $lang['File_name'],
                    'L_FILESIZE' => $lang['Filesize']
                ]
            );

            // define category
            $image = false;
            $stream = false;
            $swf = false;
            $thumbnail = false;
            $link = false;

            if ((int)$display_categories[$attachment->extension] === STREAM_CAT) {
                $stream = true;
            } else if ((int)$display_categories[$attachment->extension] === SWF_CAT) {
                $swf = true;
            } else if ((int)$display_categories[$attachment->extension] === IMAGE_CAT && (int)$attach_config['img_display_inlined']) {
                if ((int)$attach_config['img_link_width'] !== 0 || (int)$attach_config['img_link_height'] !== 0) {
                    list($width, $height) = image_getdimension($filename);

                    if ($width === 0 && $height === 0) {
                        $image = true;
                    } else {
                        if ($width <= (int)$attach_config['img_link_width'] && $height <= (int)$attach_config['img_link_height']) {
                            $image = true;
                        }
                    }
                } else {
                    $image = true;
                }
            }

            if ((int)$display_categories[$attachment->extension] === IMAGE_CAT && $attachment->thumbnail === 1) {
                $thumbnail = true;
                $image = false;
            }

            if (!$image && !$stream && !$swf && !$thumbnail) {
                $link = true;
            }

            if ($image) {
                // Images
                // NOTE: If you want to use the download.php everytime an image is displayed inlined, replace the
                // Section between BEGIN and END with (Without the // of course):
                //	$img_source = append_sid($phpbb_root_path . 'download.' . $phpEx . '?id=' . $attachment->attach_id);
                //	$download_link = true;
                //
                //
                if ((int)$attach_config['allow_ftp_upload'] && trim($attach_config['download_path']) === '') {
                    $img_source = Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id);
                    $download_link = true;
                } else {
                    // Check if we can reach the file or if it is stored outside of the webroot
                    if ($attach_config['upload_dir'][0] === '/' || ($attach_config['upload_dir'][0] !== '/' && $attach_config['upload_dir'][1] === ':')) {
                        $img_source = Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id);
                        $download_link = true;
                    } else {
                        // BEGIN
                        $img_source = $filename;
                        $download_link = false;
                        // END
                    }
                }

                $template->assignBlockVars('postrow.attach.cat_images', [
                        'DOWNLOAD_NAME' => $display_name,
                        'S_UPLOAD_IMAGE' => $upload_image,

                        'IMG_SRC' => $img_source,
                        'FILESIZE' => $filesize,
                        'COMMENT' => $comment,
                        'L_DOWNLOADED_VIEWED' => $lang['Viewed'],
                        'L_DOWNLOAD_COUNT' => sprintf($lang['Download_number'], $attachment->download_count)]
                );

                // Directly Viewed Image ... update the download count
                if (!$download_link) {
                    dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['download_count%sql' => 'download_count + 1'])
                        ->where('[attach_id] = %i', $attachment->attach_id)
                        ->execute();
                }
            }

            if ($thumbnail) {
                // Images, but display Thumbnail
                // NOTE: If you want to use the download.php everytime an thumnmail is displayed inlined, replace the
                // Section between BEGIN and END with (Without the // of course):
                //	$thumb_source = append_sid($phpbb_root_path . 'download.' . $phpEx . '?id=' . $attachment->attach_id . '&thumb=1');
                //
                if ((int)$attach_config['allow_ftp_upload'] && trim($attach_config['download_path']) === '') {
                    $thumb_source = Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id . '&thumb=1');
                } else {
                    // Check if we can reach the file or if it is stored outside of the webroot
                    if ($attach_config['upload_dir'][0] === '/' || ($attach_config['upload_dir'][0] !== '/' && $attach_config['upload_dir'][1] === ':')) {
                        $thumb_source = Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id . '&thumb=1');
                    } else {
                        // BEGIN
                        $thumb_source = $thumbnail_filename;
                        // END
                    }
                }

                $template->assignBlockVars('postrow.attach.cat_thumb_images',
                    [
                        'DOWNLOAD_NAME' => $display_name,
                        'S_UPLOAD_IMAGE' => $upload_image,

                        'IMG_SRC' => Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id),
                        'IMG_THUMB_SRC' => $thumb_source,
                        'FILESIZE' => $filesize,
                        'COMMENT' => $comment,
                        'L_DOWNLOADED_VIEWED' => $lang['Viewed'],
                        'L_DOWNLOAD_COUNT' => sprintf($lang['Download_number'], $attachment->download_count)
                    ]
                );
            }

            if ($stream) {
                // Streams
                $template->assignBlockVars('postrow.attach.cat_stream',
                    [
                        'U_DOWNLOAD_LINK' => $filename,
                        'S_UPLOAD_IMAGE' => $upload_image,

//					'U_DOWNLOAD_LINK' => append_sid($phpbb_root_path . 'download.' . $phpEx . '?id=' . $attachment->attach_id),
                        'DOWNLOAD_NAME' => $display_name,
                        'FILESIZE' => $filesize,
                        'COMMENT' => $comment,
                        'L_DOWNLOADED_VIEWED' => $lang['Viewed'],
                        'L_DOWNLOAD_COUNT' => sprintf($lang['Download_number'], $attachment->download_count)
                    ]
                );

                // Viewed/Heared File ... update the download count (download.php is not called here)
                dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['download_count%sql' => 'download_count + 1'])
                    ->where('[attach_id] = %i',$attachment->attach_id)
                    ->execute();
            }

            if ($swf) {
                // Macromedia Flash Files
                list($width, $height) = swf_getdimension($filename);

                $template->assignBlockVars('postrow.attach.cat_swf',
                    [
                        'U_DOWNLOAD_LINK' => $filename,
                        'S_UPLOAD_IMAGE' => $upload_image,

                        'DOWNLOAD_NAME' => $display_name,
                        'FILESIZE' => $filesize,
                        'COMMENT' => $comment,
                        'L_DOWNLOADED_VIEWED' => $lang['Viewed'],
                        'L_DOWNLOAD_COUNT' => sprintf($lang['Download_number'], $attachment->download_count),
                        'WIDTH' => $width,
                        'HEIGHT' => $height
                    ]
                );

                // Viewed/Heared File ... update the download count (download.php is not called here)

                dibi::update(Tables::ATTACH_ATTACHMENTS_DESC_TABLE, ['download_count%sql' => 'download_count + 1'])
                    ->where('[attach_id] = %i', (int)$attachment->attach_id)
                    ->execute();
            }

            if ($link) {
                $target_blank = 'target="_blank"'; //( (intval($display_categories[$attachment->extension]) === IMAGE_CAT) ) ? 'target="_blank"' : '';

                // display attachment
                $template->assignBlockVars('postrow.attach.attachrow',
                    [
                        'U_DOWNLOAD_LINK' => Session::appendSid($phpbb_root_path . 'download.php?id=' . $attachment->attach_id),
                        'S_UPLOAD_IMAGE' => $upload_image,

                        'DOWNLOAD_NAME' => $display_name,
                        'FILESIZE' => $filesize,
                        'COMMENT' => $comment,
                        'TARGET_BLANK' => $target_blank,

                        'L_DOWNLOADED_VIEWED' => $lang['Downloaded'],
                        'L_DOWNLOAD_COUNT' => sprintf($lang['Download_number'], $attachment->download_count)
                    ]
                );
            }
        }
    }
}

?>