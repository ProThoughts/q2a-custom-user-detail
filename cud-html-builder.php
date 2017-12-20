<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

class cud_html_builder
{
    const ABOUT_MAX_LENGTH = 68;

    public static function create_favorite_button($label, $tags, $is_follow){

      $html = '<a class="mdl-button mdl-js-button mdl-button--fab mdl-js-ripple-effect';
      if($is_follow) {
        $html .= ' mdl-button--colored mdl-color-text--white';
      }
      $html .= '" title="' . $label . '"';
      $html .= $tags;
      $html .= ' data-upgraded=",MaterialButton,MaterialRipple">';
      $html .= ' <i class="material-icons">favorite</i> <span class="mdl-button__ripple-container">';
      $html .= ' <span class="mdl-ripple"></span> </span> </a>';
      return $html;
    }

    public static function create_second_section($content, $handle){
      $template_path = CUD_DIR . '/html/second_section.html';
      $template = file_get_contents($template_path);

      $params = array(
        '^follow_count_label' => qa_lang_html('cud_lang/follow_count'),
        '^follow_list_page_url' => '/user/'.$handle.'/following',
        '^follow_count' => $content['counts']['follows'],
        '^follower_count_label' => qa_lang_html('cud_lang/follower_count'),
        '^follower_list_page_url' => '/user/'.$handle.'/followers',
        '^follower_count' => $content['counts']['followers'],
        '^badge_count_label' => qa_lang_html('cud_lang/badge_count'),
        '^badge_list_page_url' => '/user/'.$handle.'/badge',
        '^badge_count' => $content['counts']['badge'],
      );

      return strtr($template, $params);
    }

    public static function crate_tab_header($active_tab, $postcounts)
    {
        $template_path = CUD_DIR . '/html/main_tab_header.html';
        $template = file_get_contents($template_path);
        $params = array(
          '^questions_active' => $active_tab['questions'],
          '^questions_count' => $postcounts['questions'],
          '^questions_label' => qa_lang_html('cud_lang/questions'),
          '^answers_active' => $active_tab['answers'],
          '^answers_count' => $postcounts['answers'],
          '^answers_label' => qa_lang_html('cud_lang/answers'),
          '^blogs_active' => $active_tab['blogs'],
          '^blogs_count' => $postcounts['blogs'],
          '^blogs_label' => qa_lang_html('cud_lang/blogs'),
          '^favorites_active' => $active_tab['favorites'],
          '^favorites_count' => $postcounts['favorites'],
          '^favorites_label' => qa_lang_html('cud_lang/favorites'),
        );

        return strtr($template, $params);
    }

    public static function create_tab_panel($list_type, $active)
    {
        $html = '';
        $html .= '<div class="mdl-tabs__panel '.$active.'" id="'.$list_type.'">'.PHP_EOL;
        $html .= '  <div class="qa-q-list q-list-'.$list_type.'">'.PHP_EOL;

        return $html;
    }

    public static function create_no_item_list($list_type, $is_my_profile)
    {
      if($is_my_profile) {
        return self::create_no_item_list_mine($list_type);
      } else {
        return self::create_no_item_list_other($list_type);
      }
    }
    
    private static function create_no_item_list_mine($list_type)
    {
      $template_path = CUD_DIR . '/html/no_item_list_mine.html';
      $template = file_get_contents($template_path);

      $action_link = '';
      if($list_type == 'questions'){
        $action_link = qa_opt('site_url') . 'ask';
      } elseif($list_type == 'answers') {
        $action_link = qa_opt('site_url') . 'how-to-use#answer';
      } elseif($list_type == 'blogs') {
        $action_link = qa_opt('site_url') . 'blog/new';
      } elseif($list_type == 'favorites') {
        $action_link = qa_opt('site_url') . 'how-to-use#bookmark';
      }

      $image_dir = qa_opt('site_url') . 'qa-plugin/'. CUD_FOLDER . '/image/';
      $params = array(
        '^title' => qa_lang('cud_lang/no_item_list_mine_title_' . $list_type),
        '^image' => $image_dir. 'no_item_list_mine_' . $list_type . '.svg',
        '^action_btn' => qa_lang('cud_lang/no_item_list_mine_action_btn_' . $list_type),
        '^action_link' => $action_link
      );
      $template = file_get_contents($template_path);
      $html = strtr($template, $params);

      return $html;
    }

    private static function create_no_item_list_other($list_type)
    {
      $path = CUD_DIR . '/html/no_item_list_other.html';
      $template = file_get_contents($path);

      $list_name = qa_lang_html('cud_lang/'.$list_type);
      $params = array(
        '^site_url' => qa_opt('site_url'),
        '^message' => qa_lang_html_sub('cud_lang/no_posts', $list_name),
        '^image' => qa_opt('site_url') . 'qa-plugin/'. CUD_FOLDER . '/image/no_item_icon.svg'
      );
      $html = strtr($template, $params);
      return $html;
    }

    public static function create_follows_header($title)
    {
      $path = CUD_DIR . '/html/follows_main_header.html';
      $template = file_get_contents($path);
      $params = array(
        '^title' => $title
      );
      $html = strtr($template, $params);
      return $html;
    }

    public static function create_follows_footer()
    {
      $path = CUD_DIR . '/html/follows_main_footer.html';
      $template = file_get_contents($path);
      return $template;
    }

    public static function create_follows_tab($content)
    {
      $path = CUD_DIR . '/html/follows_main_tab.html';
      $template = file_get_contents($path);
      $following_active = '';
      $followers_active = '';
      if ($content['type'] === 'following') {
        $following_active = 'is-active qa-sub-nav-selected';
      } else {
        $followers_active = 'is-active qa-sub-nav-selected';
      }
      $params = array(
        '^following_count' => $content['users']['following_count'],
        '^followers_count' => $content['users']['followers_count'],
        '^following_text' => qa_lang_html('cud_lang/following'),
        '^followers_text' => qa_lang_html('cud_lang/followers'),
        '^following_active' => $following_active,
        '^followers_active' => $followers_active,
        '^users_list' => self::create_follows_list($content['users'], $content['type'])
      );
      $html = strtr($template, $params);
      return $html;
    }

    public static function create_follows_list($users, $type)
    {
        if (count($users['items']) <= 0) {
            return self::create_no_follows($type);
        }
        $path = CUD_DIR . '/html/follows_user_item.html';
        $template = file_get_contents($path);
        $html = '';
        foreach ($users['items'] as $user) {
            $params = self::create_follows_params($user);
            $html .= strtr($template, $params);
            $html .= PHP_EOL;
        }
        return $html;
    }

    public static function create_follows_params($user)
    {
        if (mb_strlen($user['about'], 'UTF-8') > self::ABOUT_MAX_LENGTH) {
            $about = mb_substr($user['about'], 0, self::ABOUT_MAX_LENGTH - 1, 'UTF-8');
            $about .= '<a href="'.$user['url'].'">...</a>';
        } else {
            $about = $user['about'];
        }
        return array(
            '^blobid' => $user['raw']['avatarblobid'],
            '^label' => $user['label'],
            '^location' => $user['location'],
            '^points' => $user['score'],
            '^about' =>  $about,
            '^url' => $user['url'],
            '^location_label' => qa_lang_html('cud_lang/location_label'),
        );
    }

    private static function create_no_follows($type)
    {
      $path = CUD_DIR . '/html/no_follows.html';
      $template = file_get_contents($path);

      $params = array(
        '^message' => qa_lang_html('cud_lang/no_'.$type),
        '^image' => qa_opt('site_url') . 'qa-plugin/'. CUD_FOLDER . '/image/no_item_icon.svg'
      );
      $html = strtr($template, $params);
      return $html;
    }
}
