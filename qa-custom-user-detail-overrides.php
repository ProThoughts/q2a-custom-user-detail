<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

function qa_page_routing()
{
    $routing = qa_page_routing_base();
    if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME) {
        $part = qa_request_part(2);
        switch ($part) {
            case 'following':
            case 'followers':
                $routing['user/'] = CUD_RELATIVE_PATH . 'pages/user-follows.php';
                break;
            default:
                $routing['user/'] = CUD_RELATIVE_PATH . 'pages/user-detail.php';
        }
    }
    return $routing;
}
