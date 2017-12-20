<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

require_once CUD_DIR.'/cud-html-builder.php';
require_once CUD_DIR.'/cud-utils.php';

class cud_theme_main_follows
{
    protected $context = array();

    public static function main($theme_obj)
    {
        $header = cud_html_builder::create_follows_header($theme_obj->content['title']);

        $tabs = cud_html_builder::create_follows_tab($theme_obj->content);

      $page_links =<<<EOF
      <div class="qa-page-links margin--top-16px">
        <nav class="qa-page-links-list">
          <a class="qa-page-selected is-activemdl-js-ripple-effect mdl-button mdl-js-button mdl-button--raised  mdl-button--colored mdl-color-text--white" data-upgraded=",MaterialButton">1</a>
          <a href="./tags?start=30" class="qa-page-link mdl-js-ripple-effect mdl-button mdl-js-button" data-upgraded=",MaterialButton,MaterialRipple">2<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a>
      
          <a href="./tags?start=30" class="qa-page-next mdl-js-ripple-effect mdl-button mdl-js-button" data-upgraded=",MaterialButton,MaterialRipple"><i class="material-icons">chevron_right</i><span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a>
        </nav>
        <div class="qa-page-links-clear">
        </div>
      </div>
      
EOF;
        $footer = cud_html_builder::create_follows_footer();

        $theme_obj->output($header);
        $theme_obj->output($tabs);

        $theme_obj->page_links();

        $theme_obj->output($footer);
    }

}
