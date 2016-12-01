<?php
    if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
        header('Location: ../');
        exit;
    }
    
    // 質問
    $questions_sel = qa_db_user_recent_qs_selectspec($loginuserid, $identifier, $pagesize, $start);
    $questions_sel['columns']['content'] = '^posts.content ';
    $questions_sel['columns']['format'] = '^posts.format ';
    $questions = qa_db_select_with_pending($questions_sel);
    // $questions = array_slice($questions, $start, $pagesize);
    $usershtml = qa_userids_handles_html($questions, false);
    
    $values = array();
    $htmldefaults = qa_post_html_defaults('Q');
    $htmldefaults['whoview'] = false;
    $htmldefaults['avatarsize'] = 0;
    $htmldefaults['contentview'] = true;

    foreach ($questions as $question) {
		$values[] = qa_post_html_fields($question, $loginuserid, qa_cookie_get(),
			$usershtml, null, qa_post_html_options($question, $htmldefaults));
    }
    
    return $values;
