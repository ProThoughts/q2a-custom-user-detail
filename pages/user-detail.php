<?php
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../');
        exit;
    }

    require_once QA_INCLUDE_DIR.'db/selects.php';
    require_once QA_INCLUDE_DIR.'app/format.php';
    require_once QA_INCLUDE_DIR.'app/limits.php';
    require_once QA_INCLUDE_DIR.'app/updates.php';
    require_once CUD_DIR.'/cud-utils.php';

    $handle = qa_request_part(1);
    $action = qa_get('action');
    $start = qa_get_start();
    $pagesize = qa_opt('page_size_qs');
    if (!strlen($handle)) {
        $handle = qa_get_logged_in_handle();
        qa_redirect(!empty($handle) ? 'user/'.$handle : 'users');
    }
    $loginuserid = qa_get_logged_in_userid();

    $identifier = QA_FINAL_EXTERNAL_USERS ? $userid : $handle;

    list($useraccount, $userprofile, $userfields, $usermessages, $userpoints, $userlevels, $navcategories, $userrank) =
        qa_db_select_with_pending(
            QA_FINAL_EXTERNAL_USERS ? null : qa_db_user_account_selectspec($handle, false),
            QA_FINAL_EXTERNAL_USERS ? null : qa_db_user_profile_selectspec($handle, false),
            QA_FINAL_EXTERNAL_USERS ? null : qa_db_userfields_selectspec(),
            QA_FINAL_EXTERNAL_USERS ? null : qa_db_recent_messages_selectspec(null, null, $handle, false, qa_opt_if_loaded('page_size_wall')),
            qa_db_user_points_selectspec($identifier),
            qa_db_user_levels_selectspec($identifier, QA_FINAL_EXTERNAL_USERS, true),
            qa_db_category_nav_selectspec(null, true),
            qa_db_user_rank_selectspec($identifier)
        );

    if (!QA_FINAL_EXTERNAL_USERS && !is_array($useraccount)) // check the user exists
        return include QA_INCLUDE_DIR.'qa-page-not-found.php';

    if (!QA_FINAL_EXTERNAL_USERS) {
        foreach ($userfields as $index => $userfield) {
            if ( isset($userfield['permit']) && qa_permit_value_error($userfield['permit'], $loginuserid, qa_get_logged_in_level(), qa_get_logged_in_flags()) )
                unset($userfields[$index]); // don't pay attention to user fields we're not allowed to view
        }
    }

    $errors = array();

    $loginlevel = qa_get_logged_in_level();

    if (!QA_FINAL_EXTERNAL_USERS) { // if we're using integrated user management, we can know and show more
        require_once QA_INCLUDE_DIR.'app/messages.php';

        if ((!is_array($userpoints)) && !is_array($useraccount))
            return include QA_INCLUDE_DIR.'qa-page-not-found.php';

        $userid = $useraccount['userid'];
        $fieldseditable = false;
        $maxlevelassign = null;

        $maxuserlevel = $useraccount['level'];
        foreach ($userlevels as $userlevel)
            $maxuserlevel = max($maxuserlevel, $userlevel['level']);

        if (
            isset($loginuserid) &&
            ($loginuserid != $userid) &&
            (($loginlevel >= QA_USER_LEVEL_SUPER) || ($loginlevel > $maxuserlevel)) &&
            (!qa_user_permit_error())
        ) { // can't change self - or someone on your level (or higher, obviously) unless you're a super admin

            if ($loginlevel >= QA_USER_LEVEL_SUPER)
                $maxlevelassign = QA_USER_LEVEL_SUPER;

            elseif ($loginlevel >= QA_USER_LEVEL_ADMIN)
                $maxlevelassign = QA_USER_LEVEL_MODERATOR;

            elseif ($loginlevel >= QA_USER_LEVEL_MODERATOR)
                $maxlevelassign = QA_USER_LEVEL_EXPERT;

            if ($loginlevel >= QA_USER_LEVEL_ADMIN)
                $fieldseditable = true;

            if (isset($maxlevelassign) && ($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED))
                $maxlevelassign = min($maxlevelassign, QA_USER_LEVEL_EDITOR); // if blocked, can't promote too high
        }

        $approvebutton = isset($maxlevelassign)
            && $useraccount['level'] < QA_USER_LEVEL_APPROVED
            && $maxlevelassign >= QA_USER_LEVEL_APPROVED
            && !($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED)
            && qa_opt('moderate_users');
        $usereditbutton = $fieldseditable || isset($maxlevelassign);
        $userediting = $usereditbutton && (qa_get_state() == 'edit');
    }


    if (!QA_FINAL_EXTERNAL_USERS) {
        $reloaduser = false;

        if ($usereditbutton) {
            if (qa_clicked('docancel'))
                qa_redirect(qa_request());

            elseif (qa_clicked('doedit'))
                qa_redirect(qa_request(), array('state' => 'edit'));

            elseif (qa_clicked('dosave')) {
                require_once QA_INCLUDE_DIR.'app/users-edit.php';
                require_once QA_INCLUDE_DIR.'db/users.php';

                $inemail = qa_post_text('email');

                $inprofile = array();
                foreach ($userfields as $userfield)
                    $inprofile[$userfield['fieldid']] = qa_post_text('field_'.$userfield['fieldid']);

                if (!qa_check_form_security_code('user-edit-'.$handle, qa_post_text('code'))) {
                    $errors['page'] = qa_lang_html('misc/form_security_again');
                    $userediting = true;
                }
                else {
                    if (qa_post_text('removeavatar')) {
                        qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, false);
                        qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_GRAVATAR, false);

                        if (isset($useraccount['avatarblobid'])) {
                            require_once QA_INCLUDE_DIR.'app/blobs.php';

                            qa_db_user_set($userid, 'avatarblobid', null);
                            qa_db_user_set($userid, 'avatarwidth', null);
                            qa_db_user_set($userid, 'avatarheight', null);
                            qa_delete_blob($useraccount['avatarblobid']);
                        }
                    }

                    if ($fieldseditable) {
                        $filterhandle = $handle; // we're not filtering the handle...
                        $errors = qa_handle_email_filter($filterhandle, $inemail, $useraccount);
                        unset($errors['handle']); // ...and we don't care about any errors in it

                        if (!isset($errors['email']))
                            if ($inemail != $useraccount['email']) {
                                qa_db_user_set($userid, 'email', $inemail);
                                qa_db_user_set_flag($userid, QA_USER_FLAGS_EMAIL_CONFIRMED, false);
                            }

                        if (count($inprofile)) {
                            $filtermodules = qa_load_modules_with('filter', 'filter_profile');
                            foreach ($filtermodules as $filtermodule)
                                $filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
                        }

                        foreach ($userfields as $userfield)
                            if (!isset($errors[$userfield['fieldid']]))
                                qa_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);

                        if (count($errors))
                            $userediting = true;

                        qa_report_event('u_edit', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
                            'userid' => $userid,
                            'handle' => $useraccount['handle'],
                        ));
                    }

                    if (isset($maxlevelassign)) {
                        $inlevel = min($maxlevelassign, (int)qa_post_text('level')); // constrain based on maximum permitted to prevent simple browser-based attack
                        if ($inlevel != $useraccount['level'])
                            qa_set_user_level($userid, $useraccount['handle'], $inlevel, $useraccount['level']);

                        if (qa_using_categories()) {
                            $inuserlevels = array();

                            for ($index = 1; $index <= 999; $index++) {
                                $inlevel = qa_post_text('uc_'.$index.'_level');
                                if (!isset($inlevel))
                                    break;

                                $categoryid = qa_get_category_field_value('uc_'.$index.'_cat');

                                if (strlen($categoryid) && strlen($inlevel))
                                    $inuserlevels[] = array(
                                        'entitytype' => QA_ENTITY_CATEGORY,
                                        'entityid' => $categoryid,
                                        'level' => min($maxlevelassign, (int)$inlevel),
                                    );
                            }

                            qa_db_user_levels_set($userid, $inuserlevels);
                        }
                    }

                    if (empty($errors))
                        qa_redirect(qa_request());

                    list($useraccount, $userprofile, $userlevels) = qa_db_select_with_pending(
                        qa_db_user_account_selectspec($userid, true),
                        qa_db_user_profile_selectspec($userid, true),
                        qa_db_user_levels_selectspec($userid, true, true)
                    );
                }
            }
        }

        if (qa_clicked('doapprove') || qa_clicked('doblock') || qa_clicked('dounblock') || qa_clicked('dohideall') || qa_clicked('dodelete')) {
            if (!qa_check_form_security_code('user-'.$handle, qa_post_text('code')))
                $errors['page'] = qa_lang_html('misc/form_security_again');

            else {
                if ($approvebutton && qa_clicked('doapprove')) {
                    require_once QA_INCLUDE_DIR.'app/users-edit.php';
                    qa_set_user_level($userid, $useraccount['handle'], QA_USER_LEVEL_APPROVED, $useraccount['level']);
                    qa_redirect(qa_request());
                }

                if (isset($maxlevelassign) && ($maxuserlevel < QA_USER_LEVEL_MODERATOR)) {
                    if (qa_clicked('doblock')) {
                        require_once QA_INCLUDE_DIR.'app/users-edit.php';

                        qa_set_user_blocked($userid, $useraccount['handle'], true);
                        qa_redirect(qa_request());
                    }

                    if (qa_clicked('dounblock')) {
                        require_once QA_INCLUDE_DIR.'app/users-edit.php';

                        qa_set_user_blocked($userid, $useraccount['handle'], false);
                        qa_redirect(qa_request());
                    }

                    if (qa_clicked('dohideall') && !qa_user_permit_error('permit_hide_show')) {
                        require_once QA_INCLUDE_DIR.'db/admin.php';
                        require_once QA_INCLUDE_DIR.'app/posts.php';

                        $postids = qa_db_get_user_visible_postids($userid);

                        foreach ($postids as $postid)
                            qa_post_set_hidden($postid, true, $loginuserid);

                        qa_redirect(qa_request());
                    }

                    if (qa_clicked('dodelete') && ($loginlevel >= QA_USER_LEVEL_ADMIN)) {
                        require_once QA_INCLUDE_DIR.'app/users-edit.php';

                        qa_delete_user($userid);

                        qa_report_event('u_delete', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
                            'userid' => $userid,
                            'handle' => $useraccount['handle'],
                        ));

                        qa_redirect('users');
                    }
                }
            }
        }
    }


    //    Get information on user references
    qa_set_template('user');
    $qa_content = qa_content_prepare(true);

    $userhtml = qa_html($handle);

    $qa_content['title'] = qa_lang_html_sub('profile/user_x', $userhtml);
    $qa_content['error'] = @$errors['page'];

    if (isset($loginuserid) && $loginuserid != $useraccount['userid'] && !QA_FINAL_EXTERNAL_USERS) {
        $favoritemap = qa_get_favorite_non_qs_map();
        $favorite = @$favoritemap['user'][$useraccount['userid']];

        $qa_content['favorite'] = cud_favorite_form(QA_ENTITY_USER, $useraccount['userid'], $favorite,
            qa_lang_sub($favorite ? 'main/remove_x_favorites' : 'users/add_user_x_favorites', $handle));
    }

    if (!QA_FINAL_EXTERNAL_USERS) {
        $membertime = qa_time_to_string(qa_opt('db_time') - $useraccount['created']);
        $joindate = qa_when_to_html($useraccount['created'], 0);

        $qa_content['form_profile'] = array(
            'tags' => 'method="post" action="'.qa_self_html().'"',

            'style' => 'wide',

            'fields' => array(
                'avatar' => array(
                    'type' => 'image',
                    'style' => 'tall',
                    'label' => '',
                    'html' => qa_get_user_avatar_html($useraccount['flags'], $useraccount['email'], $useraccount['handle'],
                        $useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], qa_opt('avatar_profile_size')),
                    'id' => 'avatar',
                ),

                'removeavatar' => null,

                'duration' => array(
                    'type' => 'static',
                    'label' => qa_lang_html('users/member_for'),
                    'value' => qa_html( $membertime . ' (' . qa_lang_sub('main/since_x', $joindate['data']) . ')' ),
                    'id' => 'duration',
                ),

                'level' => array(
                    'type' => 'static',
                    'label' => qa_lang_html('users/member_type'),
                    'tags' => 'name="level"',
                    'value' => qa_html(qa_user_level_string($useraccount['level'])),
                    'note' => (($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED) && isset($maxlevelassign)) ? qa_lang_html('users/user_blocked') : '',
                    'id' => 'level',
                ),
            ),
        );

        if (empty($qa_content['form_profile']['fields']['avatar']['html']))
            unset($qa_content['form_profile']['fields']['avatar']);

    //    Levels editing or viewing (add category-specific levels)

        if ($userediting) {

            if (isset($maxlevelassign)) {
                $qa_content['form_profile']['fields']['level']['type'] = 'select';

                $showlevels = array(QA_USER_LEVEL_BASIC);
                if (qa_opt('moderate_users'))
                    $showlevels[] = QA_USER_LEVEL_APPROVED;

                array_push($showlevels, QA_USER_LEVEL_EXPERT, QA_USER_LEVEL_EDITOR, QA_USER_LEVEL_MODERATOR, QA_USER_LEVEL_ADMIN, QA_USER_LEVEL_SUPER);

                $leveloptions = array();
                $catleveloptions = array('' => qa_lang_html('users/category_level_none'));

                foreach ($showlevels as $showlevel) {
                    if ($showlevel <= $maxlevelassign) {
                        $leveloptions[$showlevel] = qa_html(qa_user_level_string($showlevel));
                        if ($showlevel > QA_USER_LEVEL_BASIC)
                            $catleveloptions[$showlevel] = $leveloptions[$showlevel];
                    }
                }

                $qa_content['form_profile']['fields']['level']['options'] = $leveloptions;


            //    Category-specific levels

                // if (qa_using_categories()) {
                //     $catleveladd = strlen(qa_get('catleveladd')) > 0;
                //
                //     if ((!$catleveladd) && !count($userlevels)) {
                //         $qa_content['form_profile']['fields']['level']['suffix'] = strtr(qa_lang_html('users/category_level_add'), array(
                //             '^1' => '<a href="'.qa_path_html(qa_request(), array('state' => 'edit', 'catleveladd' => 1)).'">',
                //             '^2' => '</a>',
                //         ));
                //     }
                //     else
                //         $qa_content['form_profile']['fields']['level']['suffix'] = qa_lang_html('users/level_in_general');
                //
                //     if ($catleveladd || count($userlevels))
                //         $userlevels[] = array('entitytype' => QA_ENTITY_CATEGORY);
                //
                //     $index = 0;
                    // foreach ($userlevels as $userlevel) {
                    //     if ($userlevel['entitytype'] == QA_ENTITY_CATEGORY) {
                    //         $index++;
                    //         $id = 'ls_'.+$index;
                    //
                    //         $qa_content['form_profile']['fields']['uc_'.$index.'_level'] = array(
                    //             'label' => qa_lang_html('users/category_level_label'),
                    //             'type' => 'select',
                    //             'tags' => 'name="uc_'.$index.'_level" id="'.qa_html($id).'" onchange="this.qa_prev=this.options[this.selectedIndex].value;"',
                    //             'options' => $catleveloptions,
                    //             'value' => isset($userlevel['level']) ? qa_html(qa_user_level_string($userlevel['level'])) : '',
                    //             'suffix' => qa_lang_html('users/category_level_in'),
                    //         );
                    //
                    //         $qa_content['form_profile']['fields']['uc_'.$index.'_cat'] = array();
                    //
                    //         if (isset($userlevel['entityid']))
                    //             $fieldnavcategories = qa_db_select_with_pending(qa_db_category_nav_selectspec($userlevel['entityid'], true));
                    //         else
                    //             $fieldnavcategories = $navcategories;
                    //
                    //         qa_set_up_category_field($qa_content, $qa_content['form_profile']['fields']['uc_'.$index.'_cat'],
                    //             'uc_'.$index.'_cat', $fieldnavcategories, @$userlevel['entityid'], true, true);
                    //
                    //         unset($qa_content['form_profile']['fields']['uc_'.$index.'_cat']['note']);
                    //     }
                    // }

                //     $qa_content['script_lines'][] = array(
                //         "function qa_update_category_levels()",
                //         "{",
                //         "\tglob=document.getElementById('level_select');",
                //         "\tif (!glob)",
                //         "\t\treturn;",
                //         "\tvar opts=glob.options;",
                //         "\tvar lev=parseInt(opts[glob.selectedIndex].value);",
                //         "\tfor (var i=1; i<9999; i++) {",
                //         "\t\tvar sel=document.getElementById('ls_'+i);",
                //         "\t\tif (!sel)",
                //         "\t\t\tbreak;",
                //         "\t\tsel.qa_prev=sel.qa_prev || sel.options[sel.selectedIndex].value;",
                //         "\t\tsel.options.length=1;", // just leaves "no upgrade" element
                //         "\t\tfor (var j=0; j<opts.length; j++)",
                //         "\t\t\tif (parseInt(opts[j].value)>lev)",
                //         "\t\t\t\tsel.options[sel.options.length]=new Option(opts[j].text, opts[j].value, false, (opts[j].value==sel.qa_prev));",
                //         "\t}",
                //         "}",
                //     );
                //
                //     $qa_content['script_onloads'][] = array(
                //         "qa_update_category_levels();",
                //     );
                //
                //     $qa_content['form_profile']['fields']['level']['tags'] .= ' id="level_select" onchange="qa_update_category_levels();"';
                //
                // }
            }

        }
        else {
            foreach ($userlevels as $userlevel) {
                if ( $userlevel['entitytype'] == QA_ENTITY_CATEGORY && $userlevel['level'] > $useraccount['level'] ) {
                    $qa_content['form_profile']['fields']['level']['value'] .= '<br/>'.
                        strtr(qa_lang_html('users/level_for_category'), array(
                            '^1' => qa_html(qa_user_level_string($userlevel['level'])),
                            '^2' => '<a href="'.qa_path_html(implode('/', array_reverse(explode('/', $userlevel['backpath'])))).'">'.qa_html($userlevel['title']).'</a>',
                        ));
                }
            }
        }


    //    Show any extra privileges due to user's level or their points

        $showpermits = array();
        $permitoptions = qa_get_permit_options();

        // foreach ($permitoptions as $permitoption) {
        //     if ( // if not available to approved and email confirmed users with no points, but yes available to the user, it's something special
        //         qa_permit_error($permitoption, $userid, QA_USER_LEVEL_APPROVED, QA_USER_FLAGS_EMAIL_CONFIRMED, 0) &&
        //         !qa_permit_error($permitoption, $userid, $useraccount['level'], $useraccount['flags'], $userpoints['points'])
        //     ) {
        //         if ($permitoption == 'permit_retag_cat')
        //             $showpermits[] = qa_lang(qa_using_categories() ? 'profile/permit_recat' : 'profile/permit_retag');
        //         else
        //             $showpermits[] = qa_lang('profile/'.$permitoption); // then show it as an extra priviliege
        //     }
        // }
        //
        // if (count($showpermits)) {
        //     $qa_content['form_profile']['fields']['permits'] = array(
        //         'type' => 'static',
        //         'label' => qa_lang_html('profile/extra_privileges'),
        //         'value' => qa_html(implode("\n", $showpermits), true),
        //         'rows' => count($showpermits),
        //         'id' => 'permits',
        //     );
        // }


    //    Show email address only if we're an administrator

        if (($loginlevel >= QA_USER_LEVEL_ADMIN) && !qa_user_permit_error()) {
            $doconfirms = qa_opt('confirm_user_emails') && $useraccount['level'] < QA_USER_LEVEL_EXPERT;
            $isconfirmed = ($useraccount['flags'] & QA_USER_FLAGS_EMAIL_CONFIRMED) > 0;
            $htmlemail = qa_html(isset($inemail) ? $inemail : $useraccount['email']);

            $qa_content['form_profile']['fields']['email'] = array(
                'type' => $userediting ? 'text' : 'static',
                'label' => qa_lang_html('users/email_label'),
                'tags' => 'name="email"',
                'value' => $userediting ? $htmlemail : ('<a href="mailto:'.$htmlemail.'">'.$htmlemail.'</a>'),
                'error' => qa_html(@$errors['email']),
                'note' => ($doconfirms ? (qa_lang_html($isconfirmed ? 'users/email_confirmed' : 'users/email_not_confirmed').' ') : '').
                    ($userediting ? '' : qa_lang_html('users/only_shown_admins')),
                'id' => 'email',
            );
        }


    //    Show IP addresses and times for last login or write - only if we're a moderator or higher

        if (($loginlevel >= QA_USER_LEVEL_MODERATOR) && !qa_user_permit_error()) {
            $qa_content['form_profile']['fields']['lastlogin'] = array(
                'type' => 'static',
                'label' => qa_lang_html('users/last_login_label'),
                'value' =>
                    strtr(qa_lang_html('users/x_ago_from_y'), array(
                        '^1' => qa_time_to_string(qa_opt('db_time')-$useraccount['loggedin']),
                        '^2' => qa_ip_anchor_html($useraccount['loginip']),
                    )),
                'note' => $userediting ? null : qa_lang_html('users/only_shown_moderators'),
                'id' => 'lastlogin',
            );

            if (isset($useraccount['written'])) {
                $qa_content['form_profile']['fields']['lastwrite'] = array(
                    'type' => 'static',
                    'label' => qa_lang_html('users/last_write_label'),
                    'value' =>
                        strtr(qa_lang_html('users/x_ago_from_y'), array(
                            '^1' => qa_time_to_string(qa_opt('db_time')-$useraccount['written']),
                            '^2' => qa_ip_anchor_html($useraccount['writeip']),
                        )),
                    'note' => $userediting ? null : qa_lang_html('users/only_shown_moderators'),
                    'id' => 'lastwrite',
                );
            }
            else
                unset($qa_content['form_profile']['fields']['lastwrite']);
        }


    //    Show other profile fields

        $fieldsediting = $fieldseditable && $userediting;

        foreach ($userfields as $userfield) {
            if (($userfield['flags'] & QA_FIELD_FLAGS_LINK_URL) && !$fieldsediting)
                $valuehtml = qa_url_to_html_link(@$userprofile[$userfield['title']], qa_opt('links_in_new_window'));

            else {
                $value = @$inprofile[$userfield['fieldid']];
                if (!isset($value))
                    $value = @$userprofile[$userfield['title']];

                $valuehtml = qa_html($value, (($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) && !$fieldsediting));
            }

            $label = trim(qa_user_userfield_label($userfield), ':');
            if (strlen($label))
                $label .= ':';

            $notehtml = null;
            if (isset($userfield['permit']) && !$userediting) {
                if ($userfield['permit'] <= QA_PERMIT_ADMINS)
                    $notehtml = qa_lang_html('users/only_shown_admins');
                elseif ($userfield['permit'] <= QA_PERMIT_MODERATORS)
                    $notehtml = qa_lang_html('users/only_shown_moderators');
                elseif ($userfield['permit'] <= QA_PERMIT_EDITORS)
                    $notehtml = qa_lang_html('users/only_shown_editors');
                elseif ($userfield['permit'] <= QA_PERMIT_EXPERTS)
                    $notehtml = qa_lang_html('users/only_shown_experts');
            }

            $qa_content['form_profile']['fields'][$userfield['title']] = array(
                'type' => $fieldsediting ? 'text' : 'static',
                'label' => qa_html($label),
                'tags' => 'name="field_'.$userfield['fieldid'].'"',
                'value' => $valuehtml,
                'error' => qa_html(@$errors[$userfield['fieldid']]),
                'note' => $notehtml,
                'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
                'id' => 'userfield-'.$userfield['fieldid'],
            );
        }


    //    Edit form or button, if appropriate

        if ($userediting) {
            if (
                (qa_opt('avatar_allow_gravatar') && ($useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)) ||
                (qa_opt('avatar_allow_upload') && ($useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR) && isset($useraccount['avatarblobid']))
            ) {
                $qa_content['form_profile']['fields']['removeavatar'] = array(
                    'type' => 'checkbox',
                    'label' => qa_lang_html('users/remove_avatar'),
                    'tags' => 'name="removeavatar"',
                );
            }

            $qa_content['form_profile']['buttons'] = array(
                'save' => array(
                    'tags' => 'onclick="qa_show_waiting_after(this, false);"',
                    'label' => qa_lang_html('users/save_user'),
                ),

                'cancel' => array(
                    'tags' => 'name="docancel"',
                    'label' => qa_lang_html('main/cancel_button'),
                ),
            );

            $qa_content['form_profile']['hidden'] = array(
                'dosave' => '1',
                'code' => qa_get_form_security_code('user-edit-'.$handle),
            );

        }


        if (!is_array($qa_content['form_profile']['fields']['removeavatar']))
            unset($qa_content['form_profile']['fields']['removeavatar']);

        $qa_content['raw']['account'] = $useraccount; // for plugin layers to access
        $qa_content['raw']['profile'] = $userprofile;
    }

    if (!$userediting) {
        $qa_content['raw']['account'] = $useraccount; // for plugin layers to access
        $qa_content['raw']['profile'] = $userprofile;
        $qa_content['raw']['userid'] = $useraccount['userid'];
        $qa_content['raw']['points'] = $userpoints;
        $qa_content['raw']['rank'] = $userrank;
        $qa_content['raw']['action'] = $action;

        $questioncount = (int)@$userpoints['qposts'];
        $answercount = (int)@$userpoints['aposts'];

        // $qa_content['q_list']['activities'] = include CUD_DIR.'/pages/user-activities.php';
        // if (isset($activitiescount) && isset($pagesize)) {
        //     $qa_content['page_links_activities'] = qa_html_page_links(qa_request(), $start, $pagesize, $activitiescount, qa_opt('pages_prev_next'), array('action' => 'activities'));
        // } else {
        //     $qa_content['page_links_activities'] = array();
        // }

        $qa_content['q_list']['questions'] = include CUD_DIR.'/pages/user-questions.php';
        if (isset($questioncount) && isset($pagesize)) {
            $qa_content['page_links_questions'] = qa_html_page_links(qa_request(), $questions_start, $pagesize, $questioncount, qa_opt('pages_prev_next'), array('action' => 'questions'));
        } else {
            $qa_content['page_links_questions'] = array();
        }

        $qa_content['q_list']['answers'] = include CUD_DIR.'/pages/user-answers.php';
        if (isset($answercount) && isset($pagesize)) {
            $qa_content['page_links_answers'] = qa_html_page_links(qa_request(), $answers_start, $pagesize, $answercount, qa_opt('pages_prev_next'), array('action' => 'answers'));
        } else {
            $qa_content['page_links_answers'] = array();
        }


        $qa_content['q_list']['blogs'] = include CUD_DIR.'/pages/user-blogs.php';
        if (isset($blogcount) && isset($pagesize)) {
            $qa_content['page_links_blogs'] = qa_html_page_links(qa_request(), $blog_start, $pagesize, $blogcount, qa_opt('pages_prev_next'), array('action' => 'blogs'));
        } else {
            $qa_content['page_links_blogs'] = array();
        }

        $qa_content['q_list']['favorites'] = include CUD_DIR.'/pages/user-favorites.php';
        if (isset($favoritecount) && isset($pagesize)) {
            $qa_content['page_links_favorites'] = qa_html_page_links(qa_request(), $favorites_start, $pagesize, $favoritecount, qa_opt('pages_prev_next'), array('action' => 'favorites'));
        } else {
            $qa_content['page_links_favorites'] = array();
        }
    }

    $qa_content['counts'] = array();
    $qa_content['counts']['questions'] = $questioncount;
    $qa_content['counts']['answers'] = $answercount;
    $qa_content['counts']['blogs'] = $blogcount;
    $qa_content['counts']['favorites'] = $favoritecount;

    $count = cud_utils::get_follows_count($useraccount['userid']);
    $qa_content['counts']['follows'] = $count['following'];
    $qa_content['counts']['followers'] = $count['followers'];
    $qa_content['counts']['badge'] = cud_utils::get_badge_count($useraccount['userid']);

    return $qa_content;

    function cud_favorite_form($entitytype, $entityid, $favorite, $title)
    {
        return array(
            'favorite' => (int)$favorite,
            'code' => 'data-code="'.qa_get_form_security_code('favorite-'.$entitytype.'-'.$entityid).'"',
            'favorite_id' => 'id="favoriting"',
            'favorite_tags' => 'title="'.qa_html($title).'" name="'.qa_html('favorite_'.$entitytype.'_'.$entityid.'_'.(int)!$favorite).'"',
        );
    }
