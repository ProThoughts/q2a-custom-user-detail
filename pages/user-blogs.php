<?php
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../');
        exit;
    }
    
    // ブログ
    $blog_start = ($action === 'blogs') ? $start : 0;
    $blogs_sel = qas_blog_db_user_recent_posts_selectspec( $loginuserid, $identifier, $pagesize, $blog_start );
    $blogs_sel['columns']['content'] = '^blogs.content ';
    $blogs_sel['columns']['format'] = '^blogs.format ';
    $blogs = qa_db_select_with_pending($blogs_sel);
    $blogids = array_keys($blogs);
    $blogcount = get_total_blog_count($userid);
    
    $usershtml = qa_userids_handles_html($blogs, false);
    
    $values = array();
    $htmldefaults = qa_post_html_defaults('B');
    $htmldefaults['whoview'] = false;
    $htmldefaults['avatarsize'] = 0;
    $htmldefaults['contentview'] = true;
    $blog_comments = get_blog_comments($blogids);

    foreach ($blogs as $post) {
        $fields = qas_blog_post_html_fields( $post, $loginuserid, qa_cookie_get(),
            $usershtml, null, qas_blog_post_html_options( $post, $htmldefaults ) );
        $fields['answers_raw'] = $blog_comments[$post['postid']];
        $fields['answers'] = array(
            'prefix' => '返信',
            'data' => $blog_comments[$post['postid']],
        );
        if (function_exists('qme_remove_anchor')) {
            $fields['content'] = qme_remove_anchor($fields['content']);
        }
        $values[] = $fields;
    }
    
    return $values;

    function get_blog_comments($postids)
    {
        if(count($postids) <= 0) {
            return array();
        }
        $results = qa_db_read_all_assoc(qa_db_query_sub( qas_blog_db_blog_comments_sql($postids), 'C', 'B'));
        
        $comments = array();
        foreach ($results as $result) {
            $comments[$result['postid']] = $result['comments'];
        }
        return $comments;
    }

    function get_total_blog_count($userid)
    {
        $sql = 'SELECT count(*)';
        $sql.= ' FROM ^blogs';
        $sql.= " WHERE type = 'B'";
        $sql.= ' AND userid = #';

        return qa_db_read_one_value(qa_db_query_sub($sql, $userid), true);
    }