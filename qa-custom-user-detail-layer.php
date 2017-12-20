<?php

require_once CUD_DIR.'/cud-theme-main.php';
require_once CUD_DIR.'/cud-theme-main-follows.php';

class qa_html_theme_layer extends qa_html_theme_base
{

    function __construct($template, $content, $rooturl, $request)
    {
        qa_html_theme_base::qa_html_theme_base($template, $content, $rooturl, $request);
    }

    function body_footer()
    {
        qa_html_theme_base::body_footer();
        if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME && $this->template === 'user') {
            $action = isset($this->content['raw']['action']) ? $this->content['raw']['action'] : 'questions';
            $cud_lang_json = json_encode (array(
              'read_next' => qa_lang_html('cud_lang/read_next'),
              'read_previous' => qa_lang_html('cud_lang/read_previous'),
            ));
            $this->output(
              '<SCRIPT TYPE="text/javascript">',
              'var action = "'.$action.'";',
              "var cud_lang = '".$cud_lang_json."';",
              '</SCRIPT>'
            );
            $this->output('<SCRIPT TYPE="text/javascript" SRC="'. QA_HTML_THEME_LAYER_URLTOROOT.'js/cud-favorite.js"></SCRIPT>');
        }
    }

    function head_css()
    {
        $allow_templates = array(
            'user',
            'user-following',
            'user-followers'
        );
        qa_html_theme_base::head_css();
        if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME && in_array($this->template, $allow_templates)) {
            $this->output('<LINK REL="stylesheet" TYPE="text/css" HREF="'.QA_HTML_THEME_LAYER_URLTOROOT.'css/cud.css"/>');
        }
    }

    public function body_content()
    {
        if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME && $this->template === 'user') {
            // もともと入っているサブナビゲーションは使用しない
            unset($this->content['navigation']['sub']);
        }
        qa_html_theme_base::body_content();
    }

    public function main()
    {
        $editing = (qa_get_state() === 'edit' && qa_get_logged_in_level() >= QA_USER_LEVEL_ADMIN);
        $valid_theme = (qa_opt('site_theme') === CUD_TARGET_THEME_NAME);
        if ($valid_theme && $this->template === 'user' && !$editing) {
            cud_theme_main::main($this);
        } else if ($valid_theme && $this->template === 'user-following') {
            cud_theme_main_follows::main($this);
        } else if ($valid_theme && $this->template === 'user-followers') {
            cud_theme_main_follows::main($this);
        } else {
            qa_html_theme_base::main();
        }
    }

    public function post_avatar_meta($post, $class, $avatarprefix=null, $metaprefix=null, $metaseparator='<br/>')
    {
        if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME && $this->template === 'user') {
            $this->output('<span class="'.$class.'-avatar-meta">');
            $this->avatar($post, $class, $avatarprefix);
            $this->post_meta($post, $class, $metaprefix, $metaseparator);
            $this->output('</span>');
        } else {
            qa_html_theme_base::post_avatar_meta($post, $class, $avatarprefix, $metaprefix, $metaseparator);
        }
    }

    public function favorite_button($tags, $class)
    {
        if (qa_opt('site_theme') === CUD_TARGET_THEME_NAME && qa_request_part(0)==='user') {
    		if (isset($tags)) {
                $label = $this->get_follow_label($tags);
                $new_tags = $this->replace_tags($tags);
                $is_follow = $this->get_follow_status($tags);

                $html = cud_html_builder::create_favorite_button($label, $new_tags, $is_follow);
                $this->output($html);

            }
        } else {
            qa_html_theme_base::favorite_button($tags, $class);
        }
    }


    private function get_follow_status($tags) {

        $nametag = '';
        $pat = '/name\s*=\s*["\']([^"\']+)["\']/i';
        $matchcount = preg_match($pat, $tags, $match);
        if ($matchcount > 0) {
          $nametag = $match[1];
          if(mb_substr($nametag, -1) == 1) {
            return false;
          } else {
            return true;
          }
        }

        error_log("FATAL: could not get follow status");
        return false;
    }

    private function get_follow_label($tags)
    {
        $label = '';
        $pat = '/title\s*=\s*["\']([^"\']+)["\']/i';
        $matchcount = preg_match($pat, $tags, $match);
        if ($matchcount > 0) {
            $label = $match[1];
        }
        return $label;
    }

    private function replace_tags($tags)
    {
        $pat = '/onclick\s*=\s*["\']([^"\']+)["\']/i';
        $tags2 = preg_replace($pat, '', $tags);
        return $tags2;
    }
}
