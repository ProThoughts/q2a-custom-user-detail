<?php
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../');
        exit;
    }

    require_once QA_INCLUDE_DIR.'db/users.php';
    require_once QA_INCLUDE_DIR.'db/selects.php';
    require_once QA_INCLUDE_DIR.'app/format.php';
    require_once CUD_DIR.'/cud-utils.php';

    $handle = qa_request_part(1);
    $type = qa_request_part(2);
    $start = qa_get_start();
    $pagesize = qa_opt('page_size_users');
    if (!strlen($handle) || !strlen($type)) {
        $handle = qa_get_logged_in_handle();
        qa_redirect(!empty($handle) ? 'user/'.$handle : 'users');
    }
    $userids = qa_db_user_find_by_handle($handle);
    $userid = $userids[0];

    //    Get list of favorites users
    $selectspec = qa_db_top_users_selectspec($start, qa_opt_if_loaded('page_size_users'));
    $query = cud_utils::get_follows_query($type);
    $selectspec['arguments'] = array();
    $selectspec['arguments'][] = $userid;
    $selectspec['arguments'][] = $start;
    $selectspec['arguments'][] = $pagesize;
    $selectspec['columns']['about'] = 'a.about';
    $selectspec['columns']['location'] = 'l.location';
    $selectspec['source'] = $query;

    $users = qa_db_select_with_pending($selectspec);

    $users = array_slice($users, 0, $pagesize);

//    Prepare content for theme
    $qa_content = qa_content_prepare(true);

    $userhtml = qa_html($handle);

    $qa_content['title'] = qa_lang_html_sub('cud_lang/follows_title', $handle);

    $count = cud_utils::get_follows_count($userid);

    $qa_content['users'] = array(
        'items' => array(),
        'rows' => 1,
        'type' => 'users',
        'following_count' => $count['following'],
        'followers_count' => $count['followers']
    );
    if ($type == 'following') {
        qa_set_template('user-following');
        $userscount = $count['following'];
    } elseif ($type === 'followers') {
        qa_set_template('user-followers');
        $userscount = $count['followers'];
    }

    $qa_content['type'] = $type;

    if (count($users)) {
        $qa_content['users']['items'] = cud_utils::get_users_items($users);
    }
    $qa_content['page_links'] = qa_html_page_links(qa_request(), $start, $pagesize, $userscount, qa_opt('pages_prev_next'));

    return $qa_content;