<?php
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../');
        exit;
    }
    // 回答
    $answers_start = ($action === 'answers') ? $start : 0;
    $answers_sel = qa_db_user_recent_a_qs_selectspec($loginuserid, $identifier, $pagesize, $answers_start);
    $answers_sel['columns']['content'] = '^posts.content ';
    $answers_sel['columns']['format'] = '^posts.format ';
    $answers = qa_db_select_with_pending($answers_sel);
    // $answers = array_slice($answers, $start, $pagesize);
    $usershtml = qa_userids_handles_html($answers, false);
    
    $values = array();
    $htmldefaults = qa_post_html_defaults('Q');
    $htmldefaults['whoview'] = false;
    $htmldefaults['avatarsize'] = 0;
    $htmldefaults['contentview'] = true;

    foreach ($answers as $question) {
        $fields = qa_other_to_q_html_fields($question, $loginuserid, qa_cookie_get(),
            $usershtml, null, array('voteview' => false) + qa_post_html_options($question, $htmldefaults));

        
        if (function_exists('qme_remove_anchor')) {
            $fields['content'] = qme_remove_anchor($fields['content']);
        }
        $values[] = $fields;
    }
    
    return $values;
