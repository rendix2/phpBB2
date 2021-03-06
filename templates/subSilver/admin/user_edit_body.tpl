
<h1>{L_USER_TITLE}</h1>

<p>{L_USER_EXPLAIN}</p>

{ERROR_BOX}

<form action="{S_PROFILE_ACTION}" {S_FORM_ENCTYPE} method="post">
	<table width="98%" cellspacing="1" cellpadding="4" border="0" align="center" class="forumline">
		<tr>
			<th class="thHead" colspan="2">{L_REGISTRATION_INFO}</th>
		</tr>
		<tr>
			<td class="row2" colspan="2">
				<span class="gensmall">{L_ITEMS_REQUIRED}</span>
			</td>
		</tr>
		<tr>
			<td class="row1" width="38%">
				<label for="username">
					<span class="gen">{L_USERNAME}: *</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="username" id="username" size="35" maxlength="40"
					   value="{USERNAME}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="email">
					<span class="gen">{L_EMAIL_ADDRESS}: *</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="email" id="email" size="35" maxlength="255" value="{EMAIL}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="password">
					<span class="gen">{L_NEW_PASSWORD}: *</span>
				</label>
				<br/>
				<span class="gensmall">{L_PASSWORD_IF_CHANGED}</span></td>
			<td class="row2">
				<input class="post" type="password" name="password" id="password" size="35" maxlength="32" value="" autocomplete="off"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="password_confirm">
					<span class="gen">{L_CONFIRM_PASSWORD}: * </span>
				</label>
				<br/>
				<span class="gensmall">{L_PASSWORD_CONFIRM_IF_CHANGED}</span></td>
			<td class="row2">
				<input class="post" type="password" name="password_confirm" id="password_confirm" size="35" maxlength="32" value="" autocomplete="off"/>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<label for="acp_password">
					<span class="gen">{L_ACP_PASSWORD}:</span>
				</label>
				<br />
				<span class="gensmall">{L_ACP_PASSWORD_EXPLAIN}{L_ACP_PASSWORD_COMPLEX}</span>
			</td>
			<td class="row2">
				<input class="post" type="password" name="acp_password" id="acp_password" size="35" maxlength="32" value="" />
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="acp_password_confirm">
					<span class="gen">{L_ACP_PASSWORD_CONFIRM}:</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="password" name="acp_password_confirm" id="acp_password_confirm" size="35" maxlength="32" value="" />
			</td>
		</tr>

		<tr>
			<td class="catsides" colspan="2">&nbsp;</td>
		</tr>
		<tr>
			<th class="thSides" colspan="2">{L_PROFILE_INFO}</th>
		</tr>
		<tr>
			<td class="row2" colspan="2"><span class="gensmall">{L_PROFILE_INFO_NOTICE}</span></td>
		</tr>
		<tr>
			<td class="row1">
				<label for="website">
					<span class="gen">{L_WEBSITE}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="website" id="website" size="35" maxlength="255" value="{WEBSITE}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="location">
					<span class="gen">{L_LOCATION}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="location" id="location" size="35" maxlength="100" value="{LOCATION}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="occupation">
					<span class="gen">{L_OCCUPATION}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="occupation" id="occupation" size="35" maxlength="100" value="{OCCUPATION}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="interests">
					<span class="gen">{L_INTERESTS}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="interests" id="interests" size="35" maxlength="150" value="{INTERESTS}"/>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="signature">
					<span class="gen">{L_SIGNATURE}</span>
				</label>
				<br/>
				<span class="gensmall">{L_SIGNATURE_EXPLAIN}
					<br/><br/>
					{HTML_STATUS}<br/>
					{BBCODE_STATUS}<br/>
					{SMILIES_STATUS}</span>
			</td>
			<td class="row2">
				<textarea class="post" name="signature" id="signature" rows="6" cols="45">{SIGNATURE}</textarea>
			</td>
		</tr>
		<tr>
			<td class="catsides" colspan="2"><span class="cattitle">&nbsp;</span></td>
		</tr>
		<tr>
			<th class="thSides" colspan="2">{L_PREFERENCES}</th>
		</tr>

		<tr>
			<td class="row1">
				<span class="gen">{L_HIDE_USER}</span>
			</td>
			<td class="row2">
				<input type="radio" name="hideonline" id="hideonline_1" value="1" {HIDE_USER_YES} />
				<label for="hideonline_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="hideonline" id="hideonline_0" value="0" {HIDE_USER_NO} />
				<label for="hideonline_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>

		<tr>
			<td class="row1">
				<span class="gen">{L_NOTIFY_ON_REPLY}</span>
			</td>
			<td class="row2">
				<input type="radio" name="notifyreply" id="notifyreply_1" value="1" {NOTIFY_REPLY_YES} />
				<label for="notifyreply_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="notifyreply" id="notifyreply_0" value="0" {NOTIFY_REPLY_NO} />
				<label for="notifyreply_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_NOTIFY_ON_PRIVMSG}</span>
			</td>
			<td class="row2">
				<input type="radio" name="notifypm" id="notifypm_1" value="1" {NOTIFY_PM_YES} />
				<label for="notifypm_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="notifypm" id="notifypm_0" value="0" {NOTIFY_PM_NO} />
				<label for="notifypm_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_POPUP_ON_PRIVMSG}</span>
			</td>
			<td class="row2">
				<input type="radio" name="popup_pm" id="popup_pm_1" value="1" {POPUP_PM_YES} />
				<label for="popup_pm_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="popup_pm" id="popup_pm_0" value="0" {POPUP_PM_NO} />
				<label for="popup_pm_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_ALWAYS_ADD_SIGNATURE}</span>
			</td>
			<td class="row2">
				<input type="radio" name="attachsig" id="attachsig_1" value="1" {ALWAYS_ADD_SIGNATURE_YES} />
				<label for="attachsig_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="attachsig" id="attachsig_0" value="0" {ALWAYS_ADD_SIGNATURE_NO} />
				<label for="attachsig_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1"><span class="gen">{L_ALWAYS_ALLOW_BBCODE}</span></td>
			<td class="row2">
				<input type="radio" name="allowbbcode" id="allowbbcode_1" value="1" {ALWAYS_ALLOW_BBCODE_YES} />
				<label for="allowbbcode_1">
					<span class="gen">{L_YES}</span>
				</label>
				<input type="radio" name="allowbbcode" id="allowbbcode_0" value="0" {ALWAYS_ALLOW_BBCODE_NO} />
				<label for="allowbbcode_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_ALWAYS_ALLOW_HTML}</span>
			</td>
			<td class="row2">
				<input type="radio" name="allowhtml" id="allowhtml_1" value="1" {ALWAYS_ALLOW_HTML_YES} />
				<label for="allowhtml_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="allowhtml" id="allowhtml_0" value="0" {ALWAYS_ALLOW_HTML_NO} />
				<label for="allowhtml_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_ALWAYS_ALLOW_SMILIES}</span>
			</td>
			<td class="row2">
				<input type="radio" name="allowsmilies" id="allowsmilies_1" value="1" {ALWAYS_ALLOW_SMILIES_YES} />
				<label for="allowsmilies_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="allowsmilies" id="allowsmilies_0" value="0" {ALWAYS_ALLOW_SMILIES_NO} />
				<label for="allowsmilies_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="language">
					<span class="gen">{L_BOARD_LANGUAGE}</span>
				</label>
			</td>
			<td class="row2">{LANGUAGE_SELECT}</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="style">
					<span class="gen">{L_BOARD_STYLE}</span>
				</label>
			</td>
			<td class="row2">{STYLE_SELECT}</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="timezone">
					<span class="gen">{L_TIMEZONE}</span>
				</label>
			</td>
			<td class="row2">{TIMEZONE_SELECT}</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="dateformat">
					<span class="gen">{L_DATE_FORMAT}</span>
				</label>
				<br/>
				<span class="gensmall">{L_DATE_FORMAT_EXPLAIN}</span>
			</td>
			<td class="row2">
				<input class="post" type="text" name="dateformat" id="dateformat" value="{DATE_FORMAT}" maxlength="16"/>
			</td>
		</tr>
		<tr>
			<td class="catSides" colspan="2"><span class="cattitle">&nbsp;</span></td>
		</tr>
		<tr>
			<th class="thSides" colspan="2" height="12" valign="middle">{L_AVATAR_PANEL}</th>
		</tr>
		<tr align="center">
			<td class="row1" colspan="2">
				<table width="70%" cellspacing="2" cellpadding="0" border="0">
					<tr>
						<td width="65%">
							<span class="gensmall">{L_AVATAR_EXPLAIN}</span>
						</td>
						<td align="center">
							<span class="gensmall">{L_CURRENT_IMAGE}</span>
							<br/>
							{AVATAR}<br/>
							<input type="checkbox" name="avatardel" id="avatardel"/>
							<label for="avatardel">
								<span class="gensmall">{L_DELETE_AVATAR}</span>
							</label>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<!-- BEGIN avatar_local_upload -->
		<tr>
			<td class="row1">
				<label for="avatar">
					<span class="gen">{L_UPLOAD_AVATAR_FILE}</span>
				</label>
			</td>
			<td class="row2">
				<input type="hidden" name="MAX_FILE_SIZE" value="{AVATAR_SIZE}"/>
				<input type="file" name="avatar" id="avatar" class="post" style="width: 200px"/>
			</td>
		</tr>
		<!-- END avatar_local_upload -->
		<!-- BEGIN avatar_remote_upload -->
		<tr>
			<td class="row1">
				<label for="avatarurl">
					<span class="gen">{L_UPLOAD_AVATAR_URL}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="avatarurl" id="avatarurl" size="40" style="width: 200px"/>
			</td>
		</tr>
		<!-- END avatar_remote_upload -->
		<!-- BEGIN avatar_remote_link -->
		<tr>
			<td class="row1">
				<label for="avatarremoteurl">
					<span class="gen">{L_LINK_REMOTE_AVATAR}</span>
				</label>
			</td>
			<td class="row2">
				<input class="post" type="text" name="avatarremoteurl" id="avatarremoteurl" size="40" style="width: 200px"/>
			</td>
		</tr>
		<!-- END avatar_remote_link -->
		<!-- BEGIN avatar_local_gallery -->
		<tr>
			<td class="row1">
				<span class="gen">{L_AVATAR_GALLERY}</span>
			</td>
			<td class="row2">
				<input type="submit" name="avatargallery" value="{L_SHOW_GALLERY}" class="liteoption"/>
			</td>
		</tr>
		<!-- END avatar_local_gallery -->

		<tr>
			<td class="catSides" colspan="2">&nbsp;</td>
		</tr>
		<tr>
			<th class="thSides" colspan="2">{L_SPECIAL}</th>
		</tr>
		<tr>
			<td class="row1" colspan="2">
				<span class="gensmall">{L_SPECIAL_EXPLAIN}</span>
			</td>
		</tr>

		<tr>
			<td class="row1"><span class="gen">{L_UPLOAD_QUOTA}</span></td>
			<td class="row2">{S_SELECT_UPLOAD_QUOTA}</td>
		</tr>
		<tr>
			<td class="row1"><span class="gen">{L_PM_QUOTA}</span></td>
			<td class="row2">{S_SELECT_PM_QUOTA}</td>
		</tr>

		<tr>
			<td class="row1">
				<span class="gen">{L_USER_ACTIVE}</span>
			</td>
			<td class="row2">
				<input type="radio" name="user_status" id="user_status_1" value="1" {USER_ACTIVE_YES} />
				<label for="user_status_1">
					<span class="gen">{L_YES}</span>&nbsp;&nbsp;
				</label>

				<input type="radio" name="user_status" id="user_status_0" value="0" {USER_ACTIVE_NO} />
				<label for="user_status_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_ALLOW_PM}</span>
			</td>
			<td class="row2">
				<input type="radio" name="user_allowpm" id="user_allowpm_1" value="1" {ALLOW_PM_YES} />
				<label for="user_allowpm_1">
					<span class="gen">{L_YES}</span>&nbsp;&nbsp;
				</label>

				<input type="radio" name="user_allowpm" id="user_allowpm_0" value="0" {ALLOW_PM_NO} />
				<label for="user_allowpm_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<span class="gen">{L_ALLOW_AVATAR}</span>
			</td>
			<td class="row2">
				<input type="radio" name="user_allow_avatar" id="user_allow_avatar_1" value="1" {ALLOW_AVATAR_YES} />
				<label for="user_allow_avatar_1">
					<span class="gen">{L_YES}</span>
				</label>

				<input type="radio" name="user_allow_avatar" id="user_allow_avatar_0" value="0" {ALLOW_AVATAR_NO} />
				<label for="user_allow_avatar_0">
					<span class="gen">{L_NO}</span>
				</label>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="user_rank">
					<span class="gen">{L_SELECT_RANK}</span>
				</label>
			</td>
			<td class="row2">
				<select name="user_rank" id="user_rank">{RANK_SELECT_BOX}</select>
			</td>
		</tr>
		<tr>
			<td class="row1">
				<label for="deleteuser">
					<span class="gen">{L_DELETE_USER}?</span>
				</label>
			</td>
			<td class="row2">
				<input type="checkbox" name="deleteuser" id="deleteuser">{L_DELETE_USER_EXPLAIN}
			</td>
		</tr>
		<tr>
			<td class="catBottom" colspan="2" align="center">{S_HIDDEN_FIELDS}
				<input type="submit" name="submit" value="{L_SUBMIT}" class="mainoption"/>
				&nbsp;&nbsp;
				<input type="reset" value="{L_RESET}" class="liteoption"/>
			</td>
		</tr>
	</table>
</form>
