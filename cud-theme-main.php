<?php 

class cud_theme_main
{
    public static function main($theme_obj)
    {
        $content = $theme_obj->content;
        $theme_obj->output('<main class="mdl-layout__content">');
        $theme_obj->output('<section>');
        $theme_obj->output('<div class="qa-main'.(@$theme_obj->content['hidden'] ? ' qa-main-hidden' : '').'">');
        //横幅を640pxに収める
        if($theme_obj->template !== 'question'&&$theme_obj->template !== 'login')
        $theme_obj->output('<div class="centering__width-640">');

        $theme_obj->widgets('main', 'top');

        self::output_user($theme_obj);

        $theme_obj->widgets('main', 'high');

        if($theme_obj->template !== 'question'&&$theme_obj->template !== 'login')
        $theme_obj->output('</div><!-- END centering__width-640 -->');

        $theme_obj->widgets('main', 'low');
        $theme_obj->page_links();
        $theme_obj->suggest_next();
        $theme_obj->widgets('main', 'bottom');
        
        $theme_obj->output('</div> <!-- END qa-main -->', '');
        $theme_obj->output('</section>');
        $theme_obj->output('</div> <!-- END mdl-layout__content -->', '');
    }
    
    private static function output_user($theme_obj) {
        $raw = $theme_obj->content['raw'];
        print_r($theme_obj->content['q_list']);
        $path = CUSTOM_USER_DETAIL_DIR . '/html/main_high_user.html';
        $html = file_get_contents($path);
        $buttons = '';
        $params = self::create_params($theme_obj->content);
        $theme_obj->output( strtr($html, $params) );
    }
    
    private static function create_params($content)
    {
        $raw = $content['raw'];
        $points = $raw['points']['points'];
        $points = $points ? number_format($points) : 0;
        $buttons = self::create_buttons($raw['account']['userid']);
        $activities = self::create_activities_list($content['activities']);
        return array(
            '^site_url' => qa_opt('site_url'),
            '^blobid' => $raw['account']['avatarblobid'],
            '^handle' => $raw['account']['handle'],
            '^location' => $raw['profile']['location'],
            '^groups' => $raw['profile']['飼-育-群-数'],
            '^years' => $raw['profile']['ニホンミツバチ-飼-育-歴'],
            '^hivetype' => $raw['profile']['使-用-している-巣-箱'],
            '^about' => $raw['profile']['about'],
            '^points' => $points,
            '^ranking' => $raw['rank'],
            '^buttons' => $buttons,
            '^activities' => $activities,
            '^asks' => $activities,
            '^answers' => $activities,
            '^blogs' => $activities,
        );
    }
    
    private static function create_buttons($userid)
    {
        if($userid === qa_get_logged_in_userid()) {
            $buttons = '<a class="mdl-button mdl-button__block mdl-js-button mdl-button--raised mdl-js-ripple-effect" href="/account">プロフィール編集</a>';
        } else {
            $buttons = '<a class="mdl-button mdl-button__block mdl-js-button mdl-button--raised mdl-button--primary mdl-color-text--white mdl-js-ripple-effect">フォローする</a><a class="mdl-button mdl-button__block mdl-js-button mdl-button--raised mdl-button--primary mdl-color-text--white mdl-js-ripple-effect">メッセージ送信</a>';
        }
        return $buttons;
    }
    
    static function q_list_item($q_item)
    {
        $html = '<section>'.PHP_EOL;
        $html .= '<div class="qa-q-list-item'.rtrim(' '.@$q_item['classes']).'" '.@$q_item['tags'].'>'.PHP_EOL;
        $html .= '<div class="mdl-card mdl-shadow--2dp mdl-cell mdl-cell--12-col">'.PHP_EOL;
        if(self::get_thumbnail($q_item['raw']['postid'])) {
            $html .= self::q_item_thumbnail($q_item);
        }
        $html .= '<div class="mdl-card__supporting-text">'.PHP_EOL;
        $html .= self::q_item_main($q_item);
        $html .= self::q_item_clear();

        $html .= '</div> <!-- END mdl-grid -->'.PHP_EOL;
        $html .= '</div> <!-- END mdl-card -->'.PHP_EOL;
        $html .= '</div> <!-- END qa-q-list-item -->'.PHP_EOL;
        $html .= '</section>'.PHP_EOL;
    }
    
    private static function q_item_main($q_item)
    {
        $html = '<div class="qa-q-item-main">'.PHP_EOL;
        $html .= self::q_item_stats($q_item);
        $html .= self::q_item_title($q_item);
        $html .= '<div class="qa-q-item-tags-view">'.PHP_EOL;
        $html .= self::post_tags($q_item, 'qa-q-item');
        $html .= '</div><!-- END qa-q-item-tags-view -->'.PHP_EOL;
        $html .= self::post_avatar_meta($q_item, 'qa-q-item');
        $html .= self::q_item_buttons($q_item);
        $html .= '</div>'.PHP_EOL;
        
        return $html;
    }
    
    private static function q_item_clear()
    {
        $html = '<div class="qa-q-item-clear">'.PHP_EOL;
        $html .= '</div>'.PHP_EOL;
        
        return $html;
    }
    
    static function q_item_stats($q_item)
    {
        $selected_class = @$q_item['answer_selected'] ? 'qa-a-count-selected' : (@$q_item['answers_raw'] ? null : 'qa-a-count-zero');
        $html = '<div class="qa-q-item-stats '.$selected_class.'">'.PHP_EOL;
        $html .= self::a_count($q_item);
        $html .= '</div>'.PHP_EOL;
        
        return $html;
    }
    
    static function q_item_title($theme_obj, $q_item)
    {
        $search = '/.*>(.*)<.*/';
        $replace = '$1';
        $q_item_title = preg_replace($search, $replace, $q_item['title']);
        $html = self::get_thumbnail($q_item['raw']['postid']) ? '<div class="mdl-card__title">' : '<div class="mdl-card__title no-thumbnail">';
        $html .= '<h1 class="mdl-card__title-text qa-q-item-title">'.PHP_EOL;
        $html .= '<a href="'.$q_item['url'].'">'.$q_item_title.'</a>'.PHP_EOL;
        // add closed note in title
        $html .= empty($q_item['closed']['state']) ? '' : ' ['.$q_item['closed']['state'].']';
        $html .= '</h1>'.PHP_EOL;
        $html .= '</div>'.PHP_EOL;

        $search = '/.*="(.*)".*/';
        $replace = '$1';
        $q_item_content = preg_replace($search, $replace, $q_item['title']);
        $q_item_content = mb_strimwidth($q_item_content, 0, 170, "...", "utf-8");
        $html .= '<div class="qa-item-content">'.PHP_EOL;
        $html .= $q_item_content.PHP_EOL;
        $html .= '</div>'.PHP_EOL;
        
        return $html;
    }
    
    private static function q_item_thumbnail($q_item)
    {
        $thumbnail = self::get_thumbnail($q_item['raw']['postid']);
        $html = '';
        if (!empty($thumbnail)) {
            $thumbnail = preg_replace('/alt="image"/', '', $thumbnail);
            $thumbnail = preg_replace('/src="(.*)".*/', '$1', $thumbnail);
            // $search = '/.*>(.*)<.*/';
            // $replace = '$1';
            // $q_item_title = preg_replace($search, $replace, $q_item['title']);
            $html = '<a href="'.$q_item['url'].'" >'.PHP_EOL,
            $html .= '<div style="background:url('.$thumbnail.') center / cover;" class="mdl-card__title qa-q-item-title thumbnail">'.PHP_EOL;
            $html .= '</div>'.PHP_EOL;
            $html .= '</a>'.PHP_EOL;
        }
        return $html;
    }

    private static function get_thumbnail($postid)
    {
        $post = qa_db_single_select(qa_db_full_post_selectspec(null, $postid));
        $ret = preg_match("/<img(.+?)>/", $post['content'], $matches);
        if ($ret === 1) {
            return $matches[1];
        } else {
            return '';
        }
    }
    
    private static function sample_item_list()
    {
        
        return '<section>
      <div class="qa-q-list-item" id="q10329">
        <div class="mdl-card mdl-shadow--2dp mdl-cell mdl-cell--12-col"><a href="../../10329/%E3%83%9A%E3%83%83%E3%83%88%E3%83%9C%E3%83%88%E3%83%AB%E3%81%AE%E3%83%88%E3%83%A9%E3%83%83%E3%83%97%E3%81%AB%E5%85%A5%E3%82%8A%E3%81%BE%E3%81%97%E3%81%9F-%E3%81%B3%E3%81%A3%E3%81%8F%E3%82%8A%E3%81%A7%E3%81%99">
            <div class="mdl-card__title qa-q-item-title thumbnail" style="background:url( http://38qa.net/?qa=blob&amp;qa_blobid=6523570796681187253) center / cover"></div></a>
          <div class="mdl-card__supporting-text">
            <div class="qa-q-item-main">
              <div class="qa-q-item-stats">
                <div class="qa-a-count">
                  <div class="qa-a-count-pad">回答 </div>
                  <div class="qa-a-count-data">2</div>
                </div>
              </div>
              <div class="mdl-card__title">
                <h1 class="mdl-card__title-text qa-q-item-title"><a href="../../10329/%E3%83%9A%E3%83%83%E3%83%88%E3%83%9C%E3%83%88%E3%83%AB%E3%81%AE%E3%83%88%E3%83%A9%E3%83%83%E3%83%97%E3%81%AB%E5%85%A5%E3%82%8A%E3%81%BE%E3%81%97%E3%81%9F-%E3%81%B3%E3%81%A3%E3%81%8F%E3%82%8A%E3%81%A7%E3%81%99">ペットボトルのトラップに入りました。びっくりです</a></h1>
              </div>
              <div class="qa-item-content">今日、８月１０日、 右側の黒い蜂、初めて見る蜂です尻から鋭い針も出てます、何という名前の蜂でしょうか？</div>
              <div class="qa-q-item-tags-view">
                <div class="qa-q-item-tags">
                  <div class="qa-q-item-tag-list"><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E3%82%B9%E3%82%BA%E3%83%A1%E3%83%90%E3%83%81" data-upgraded=",MaterialButton,MaterialRipple">スズメバチ<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span></div>
                </div>
              </div>
              <!-- END qa-q-item-tags-view--><span class="qa-q-item-avatar-meta"><span class="qa-q-item-meta"><span class="qa-q-item-when-what"><span class="qa-q-item-when"><span class="qa-q-item-when-data">8/11</span></span><span class="qa-q-item-what">に質問</span></span></span></span>
            </div>
            <div class="qa-q-item-clear"></div>
          </div>
          <!-- END mdl-grid-->
        </div>
        <!-- END mdl-card-->
      </div>
      <!-- END qa-q-list-item-->
    </section>
    <section>
      <div class="qa-q-list-item" id="q8177">
        <div class="mdl-card mdl-shadow--2dp mdl-cell mdl-cell--12-col">
          <div class="mdl-card__supporting-text">
            <div class="qa-q-item-main">
              <div class="qa-q-item-stats">
                <div class="qa-a-count">
                  <div class="qa-a-count-pad">回答 </div>
                  <div class="qa-a-count-data">2</div>
                </div>
              </div>
              <div class="mdl-card__title no-thumbnail">
                <h1 class="mdl-card__title-text qa-q-item-title"><a href="../../8177/%E6%9D%A5%E5%B9%B4%E3%81%AE%E5%88%86%E8%9C%82%E5%BE%8C%E3%81%AE%E5%AF%BE%E7%AD%96%E3%82%92%E5%BF%83%E9%85%8D%E3%81%97%E3%81%A6%E3%81%84%E3%81%BE%E3%81%99-%E3%81%94%E6%95%99%E6%8E%88%E3%81%8A%E9%A1%98%E3%81%84%E3%81%97%E3%81%BE%E3%81%99">来年の分蜂後の対策を心配しています、ご教授お願いします。</a></h1>
              </div>
              <div class="qa-item-content">昨年末のアカリンダニ対策にて無事に１郡越冬し３回の分蜂、自然入居１郡で持ち箱３個埋まってしまいました、分蜂の１郡は友人に譲り現在４群飼育中です。 自分なりの判断で蜂の行...</div>
              <div class="qa-q-item-tags-view">
                <div class="qa-q-item-tags">
                  <div class="qa-q-item-tag-list"><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E5%88%86%E8%9C%82" data-upgraded=",MaterialButton,MaterialRipple">分蜂<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E6%8D%95%E7%8D%B2" data-upgraded=",MaterialButton,MaterialRipple">捕獲<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E8%B6%8A%E5%86%AC" data-upgraded=",MaterialButton,MaterialRipple">越冬<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E5%86%AC%E3%81%AE%E9%A3%BC%E8%82%B2%E6%96%B9%E6%B3%95" data-upgraded=",MaterialButton,MaterialRipple">冬の飼育方法<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span><span class="qa-q-item-tag-item"><a class="qa-tag-link mdl-button mdl-js-ripple-effect mdl-js-button mdl-button--raised" href="../../tag/%E3%82%A2%E3%82%AB%E3%83%AA%E3%83%B3%E3%83%80%E3%83%8B" data-upgraded=",MaterialButton,MaterialRipple">アカリンダニ<span class="mdl-button__ripple-container"><span class="mdl-ripple"></span></span></a></span></div>
                </div>
              </div>
              <!-- END qa-q-item-tags-view--><span class="qa-q-item-avatar-meta"><span class="qa-q-item-meta"><span class="qa-q-item-when-what"><span class="qa-q-item-when"><span class="qa-q-item-when-data">6/2</span></span><span class="qa-q-item-what">に質問</span></span></span></span>
            </div>
            <div class="qa-q-item-clear"></div>
          </div>
          <!-- END mdl-grid-->
        </div>
        <!-- END mdl-card-->
      </div>
      <!-- END qa-q-list-item-->
    </section>';
    }
}
