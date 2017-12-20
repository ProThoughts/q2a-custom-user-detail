<?php 

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
    header('Location: ../../');
    exit;
}

class cud_utils
{
    public static function get_answer_count_days($userid=null, $days=30)
    {
        if (!isset($userid)) {
            $userid = qa_get_logged_in_userid();
        }
        
        if (empty($userid)) {
            return 0;
        }
        
        $sql = "SELECT count(*)";
        $sql .= " FROM ^posts";
        $sql .= " WHERE (type = 'A' OR type = 'Q')";
        $sql .= " AND userid = $";
        $sql .= " AND created > DATE_SUB(NOW(), INTERVAL # DAY)";
        
        return qa_db_read_one_value(qa_db_query_sub($sql, $userid, $days));
    }

    /*
     * フォロー、ファオロワーの数を取得
     */
    public static function get_follows_count($userid)
    {
        $sql = "SELECT ";
        $sql .= " (SELECT COUNT(*) FROM ^users WHERE userid IN ";
        $sql .= "   (SELECT entityid FROM ^userfavorites WHERE entitytype = 'U' AND userid = $)) AS following, ";
        $sql .= " (SELECT COUNT(*) FROM ^users WHERE userid IN ";
        $sql .= "   (SELECT userid FROM ^userfavorites WHERE entitytype = 'U' AND entityid = $)) AS followers ";
        
        return qa_db_read_one_assoc(qa_db_query_sub($sql, $userid, $userid));
    }

    /*
     * フォローまたはフォロワー取得のクエリを返す
     */
    public static function get_follows_query($type)
    {
        $query = "";
        $query .= " ^users JOIN ^userpoints ON ^users.userid=^userpoints.userid";
        $query .= " LEFT JOIN ( SELECT userid, CASE WHEN title = 'about' THEN content ELSE '' END AS about";
        $query .= " FROM qa_userprofile WHERE title like 'about' ) a ON qa_users.userid = a.userid";
        $query .= " LEFT JOIN ( SELECT userid, CASE WHEN title = 'location' THEN content ELSE '' END AS location";
        $query .= " FROM qa_userprofile WHERE title like 'location' ) l ON qa_users.userid = l.userid";
        if ($type === 'following') {
            $query .= " WHERE ^users.userid IN ( ";
            $query .= " SELECT entityid ";
            $query .= " FROM ^userfavorites";
            $query .= " WHERE entitytype = 'U'";
            $query .= " AND userid = $ )";
        } elseif ($type === 'followers') {
            $query .= " WHERE ^users.userid IN ( ";
            $query .= " SELECT userid ";
            $query .= " FROM ^userfavorites";
            $query .= " WHERE entitytype = 'U'";
            $query .= " AND entityid = $ )";
        }
        $query .= " LIMIT #,#";
        return $query;
    }

    /*
     * ユーザーリスト用のデータの配列を返す
     */
    public static function get_users_items($users)
    {
        $items = array();
        $usershtml = qa_userids_handles_html($users);
        foreach ($users as $userid => $user) {
            if (QA_FINAL_EXTERNAL_USERS)
                $avatarhtml = qa_get_external_avatar_html($user['userid'], qa_opt('avatar_users_size'), true);
            else {
                $avatarhtml = qa_get_user_avatar_html($user['flags'], $user['email'], $user['handle'],
                    $user['avatarblobid'], $user['avatarwidth'], $user['avatarheight'], qa_opt('avatar_users_size'), true);
            }

            // avatar and handle now listed separately for use in themes
            $items[] = array(
                'avatar' => $avatarhtml,
                'label' => $usershtml[$user['userid']],
                'url' => qa_path_html('user/'.$user['handle']),
                'score' => qa_html(number_format($user['points'])),
                'about' => $user['about'],
                'location' => $user['location'],
                'raw' => $user,
            );
        }
        return $items;
    }

    /*
     * ユーザーのバッジ数を返す
     */
    public static function get_badge_count($userid)
    {
        $sql = "SELECT count(*)";
        $sql.= " FROM ^ysb_badges";
        $sql.= " WHERE userid = #";
        
        return qa_db_read_one_value(qa_db_query_sub($sql, $userid));
    }
}
