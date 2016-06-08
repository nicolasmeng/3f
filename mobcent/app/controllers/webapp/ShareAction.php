<?php

/**
 * WebApp 帖子分享
 *
 * @author NaiXiaoXin<nxx@yytest.cn>
 * @copyright 2012-2016 Appbyme
 */
if (!defined('IN_DISCUZ') || !defined('IN_APPBYME')) {
    exit('Access Denied');
}

//Mobcent::setErrors();

class ShareAction extends MobcentAction {

    public function run() {
        global $_G;
        header("Content-Type: text/html; charset=" . $_G['charset']);
        header("Cache-Control: no-cache, must-revalidate");
        header('Pragma: no-cache');
        require_once libfile('function/forum');
        loadforum();
        require_once libfile('function/forumlist');
        require_once libfile('function/discuzcode');
        require_once libfile('function/post');

//变量映射
        $thread = &$_G['forum_thread'];
        $forum = &$_G['forum'];

//主题或者板块错误则退出
        if (!$_G['forum_thread'] || !$_G['forum']) {
            showmessage('thread_nonexistence');
        }

        $page = max(1, $_G['page']);
        $_GET['stand'] = isset($_GET['stand']) && in_array($_GET['stand'], array('0', '1', '2')) ? $_GET['stand'] : null;

//防采集
        if ($page === 1 && !empty($_G['setting']['antitheft']['allow']) && empty($_G['setting']['antitheft']['disable']['thread']) && empty($_G['forum']['noantitheft'])) {
            helper_antitheft::check($_G['forum_thread']['tid'], 'tid');
        }

//获取当前主题缓存
        if ($_G['setting']['cachethreadlife'] && $_G['forum']['threadcaches'] && !$_G['uid'] && $page == 1 && !$_G['forum']['special'] && empty($_GET['do']) && !defined('IN_ARCHIVER') && !defined('IN_MOBILE')) {
            viewthread_loadcache();
        }

//分表信息
        $threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
        $threadtable_info = !empty($_G['cache']['threadtable_info']) ? $_G['cache']['threadtable_info'] : array();

        $archiveid = $thread['threadtableid'];
        $thread['is_archived'] = $archiveid ? true : false;
        $thread['archiveid'] = $archiveid;
        $forum['threadtableid'] = $archiveid;
        $threadtable = $thread['threadtable'];
        $posttableid = $thread['posttableid'];
        $posttable = $thread['posttable'];

// =====================================================
// gpc 变量处理
// =====================================================

        $_G['action']['fid'] = $_G['fid'];
        $_G['action']['tid'] = $_G['tid'];
//非管理员禁止访问广播帖子页面
        if ($_G['fid'] == $_G['setting']['followforumid'] && $_G['adminid'] != 1) {
            dheader("Location: home.php?mod=follow");
        }

        $st_p = $_G['uid'] . '|' . TIMESTAMP;
        dsetcookie('st_p', $st_p . '|' . md5($st_p . $_G['config']['security']['authkey']));

//关闭左侧
        $close_leftinfo = intval($_G['setting']['close_leftinfo']);
        if ($_G['setting']['close_leftinfo_userctrl']) {
            if ($_G['cookie']['close_leftinfo'] == 1) {
                $close_leftinfo = 1;
            } elseif ($_G['cookie']['close_leftinfo'] == 2) {
                $close_leftinfo = 0;
            }
        }
//是否只看某作者
        $_GET['authorid'] = !empty($_GET['authorid']) ? intval($_GET['authorid']) : 0;
//倒序看帖?
        $_GET['ordertype'] = !empty($_GET['ordertype']) ? intval($_GET['ordertype']) : 0;
//
        $_GET['from'] = in_array($_GET['from'], array('preview', 'portal', 'album')) ? $_GET['from'] : '';
        if ($_GET['from'] == 'portal' && !$_G['setting']['portalstatus']) {
            $_GET['from'] = '';
        } elseif ($_GET['from'] == 'preview' && !$_G['inajax']) {
            $_GET['from'] = '';//禁止非AJAX访问预览模式
        } elseif ($_GET['from'] == 'album' && ($_G['setting']['guestviewthumb']['flag'] && !$_G['uid'] && IN_MOBILE != 2 || !$_G['group']['allowgetimage'])) {
            $_GET['from'] = '';
        }

        $fromuid = $_G['setting']['creditspolicy']['promotion_visit'] && $_G['uid'] ? '&amp;fromuid=' . $_G['uid'] : '';
        $feeduid = $_G['forum_thread']['authorid'] ? $_G['forum_thread']['authorid'] : 0;
        $feedpostnum = $_G['forum_thread']['replies'] > $_G['ppp'] ? $_G['ppp'] : ($_G['forum_thread']['replies'] ? $_G['forum_thread']['replies'] : 1);

//extra
        if (!empty($_GET['extra'])) {
            parse_str($_GET['extra'], $extra);
            $_GET['extra'] = array();
            foreach ($extra as $_k => $_v) {
                if (preg_match('/^\w+$/', $_k)) {
                    if (!is_array($_v)) {
                        $_GET['extra'][] = $_k . '=' . rawurlencode($_v);
                    } else {
                        $_GET['extra'][] = http_build_query(array($_k => $_v));
                    }
                }
            }
            $_GET['extra'] = implode('&', $_GET['extra']);
        }

        $_G['forum_threadindex'] = '';
        $skipaids = $aimgs = $_G['forum_posthtml'] = array();

//note 部分状态处理
        $thread['subjectenc'] = rawurlencode($_G['forum_thread']['subject']);
        $thread['short_subject'] = cutstr($_G['forum_thread']['subject'], 52);

// ==========================================
// note 标题以及导航处理
// ==========================================
        $navigation = '';
        if ($_GET['from'] == 'portal') {
            if ($_G['forum']['status'] == 3) {
                //note 当前板块是群组
                _checkviewgroup();
            }
            //note 门户浏览模式
            $_G['setting']['ratelogon'] = 1;
            $navigation = ' <em>&rsaquo;</em> <a href="portal.php">' . lang('core', 'portal') . '</a>';
            $navsubject = $_G['forum_thread']['subject'];
            $navtitle = $_G['forum_thread']['subject'];

        } elseif ($_GET['from'] == 'preview') {

            //note 主题列表预览模式
            $_G['setting']['ratelogon'] = 1;

        } elseif ($_G['forum']['status'] == 3) {
            //note 当前板块是群组
            _checkviewgroup();
            $nav = get_groupnav($_G['forum']);
            $navigation = ' <em>&rsaquo;</em> <a href="group.php">' . $_G['setting']['navs'][3]['navname'] . '</a> ' . $nav['nav'];
            $upnavlink = 'forum.php?mod=forumdisplay&amp;fid=' . $_G['fid'] . ($_GET['extra'] && !IS_ROBOT ? '&amp;' . $_GET['extra'] : '');
            $_G['grouptypeid'] = $_G['forum']['fup'];

        } else {
            //正常的板块
            $navigation = '';
            $upnavlink = 'forum.php?mod=forumdisplay&amp;fid=' . $_G['fid'] . ($_GET['extra'] && !IS_ROBOT ? '&amp;' . $_GET['extra'] : '');

            //追加子版块所在分区链接
            if ($_G['forum']['type'] == 'sub') {
                $fup = $_G['cache']['forums'][$_G['forum']['fup']]['fup'];
                $t_link = $_G['cache']['forums'][$fup]['type'] == 'group' ? 'forum.php?gid=' . $fup : 'forum.php?mod=forumdisplay&fid=' . $fup;
                $navigation .= ' <em>&rsaquo;</em> <a href="' . $t_link . '">' . ($_G['cache']['forums'][$fup]['name']) . '</a>';
            }

            //追加上级板块链接
            if ($_G['forum']['fup']) {
                $fup = $_G['forum']['fup'];
                $t_link = $_G['cache']['forums'][$fup]['type'] == 'group' ? 'forum.php?gid=' . $fup : 'forum.php?mod=forumdisplay&fid=' . $fup;
                $navigation .= ' <em>&rsaquo;</em> <a href="' . $t_link . '">' . ($_G['cache']['forums'][$fup]['name']) . '</a>';
            }

            //追加当前板块链接
            $t_link = 'forum.php?mod=forumdisplay&amp;fid=' . $_G['fid'] . ($_GET['extra'] && !IS_ROBOT ? '&amp;' . $_GET['extra'] : '');
            $navigation .= ' <em>&rsaquo;</em> <a href="' . $t_link . '">' . ($_G['forum']['name']) . '</a>';

            //追加分表存档连接
            if ($archiveid) {
                if ($threadtable_info[$archiveid]['displayname']) {
                    $t_name = dhtmlspecialchars($threadtable_info[$archiveid]['displayname']);
                } else {
                    $t_name = lang('core', 'archive') . ' ' . $archiveid;
                }
                $navigation .= ' <em>&rsaquo;</em> <a href="forum.php?mod=forumdisplay&fid=' . $_G['fid'] . '&archiveid=' . $archiveid . '">' . $t_name . '</a>';
            }

            //变量释放
            unset($t_link, $t_name);
        }

// ==========================================
// seo 处理
// ==========================================

//此处对 extra 变量再次重新封装
        $_GET['extra'] = $_GET['extra'] ? rawurlencode($_GET['extra']) : '';

        if (@in_array('forum_viewthread', $_G['setting']['rewritestatus'])) {
            $canonical = rewriteoutput('forum_viewthread', 1, '', $_G['tid'], 1, '', '');
        } else {
            $canonical = 'forum.php?mod=viewthread&tid=' . $_G['tid'];
        }
        $_G['setting']['seohead'] .= '<link href="' . $_G['siteurl'] . $canonical . '" rel="canonical" />';

        $_G['forum_tagscript'] = '';

// ==========================================
// 分类信息 处理
// ==========================================
        $threadsort = $thread['sortid'] && isset($_G['forum']['threadsorts']['types'][$thread['sortid']]) ? 1 : 0;
        if ($threadsort) {
            require_once libfile('function/threadsort');
            $threadsortshow = threadsortshow($thread['sortid'], $_G['tid']);
        }

//debug 查看主题的权限判断
        if (empty($_G['forum']['allowview'])) {

            if (!$_G['forum']['viewperm'] && !$_G['group']['readaccess']) {
                showmessage('group_nopermission', NULL, array('grouptitle' => $_G['group']['grouptitle']), array('login' => 1));
            } elseif ($_G['forum']['viewperm'] && !forumperm($_G['forum']['viewperm'])) {
                showmessagenoperm('viewperm', $_G['fid']);
            }

        } elseif ($_G['forum']['allowview'] == -1) {
            showmessage('forum_access_view_disallow');
        }

        if ($_G['forum']['formulaperm']) {
            formulaperm($_G['forum']['formulaperm']);
        }

//debug 隐秘论坛
        if ($_G['forum']['password'] && $_G['forum']['password'] != $_G['cookie']['fidpw' . $_G['fid']]) {
            dheader("Location: $_G[siteurl]forum.php?mod=forumdisplay&fid=$_G[fid]");
        }

        if ($_G['forum']['price'] && !$_G['forum']['ismoderator']) {
            $membercredits = C::t('common_member_forum_buylog')->get_credits($_G['uid'], $_G['fid']);
            $paycredits = $_G['forum']['price'] - $membercredits;
            if ($paycredits > 0) {
                dheader("Location: $_G[siteurl]forum.php?mod=forumdisplay&fid=$_G[fid]");
            }
        }

        if ($_G['forum_thread']['readperm'] && $_G['forum_thread']['readperm'] > $_G['group']['readaccess'] && !$_G['forum']['ismoderator'] && $_G['forum_thread']['authorid'] != $_G['uid']) {
            showmessage('thread_nopermission', NULL, array('readperm' => $_G['forum_thread']['readperm']), array('login' => 1));
        }

        $usemagic = array('user' => array(), 'thread' => array());

//note 是否是接收回复通知
        $replynotice = getstatus($_G['forum_thread']['status'], 6);

//note 是否是隐藏回复的帖子
        $hiddenreplies = getstatus($_G['forum_thread']['status'], 2);

//note 是否是抢楼贴
        $rushreply = getstatus($_G['forum_thread']['status'], 3);

//note 是否单独保存帖子位置信息
        $savepostposition = getstatus($_G['forum_thread']['status'], 1);

//note 是否被收入淘专辑
        $incollection = getstatus($_G['forum_thread']['status'], 9);

//debug 出售帖
        $_G['forum_threadpay'] = FALSE;
        if ($_G['forum_thread']['price'] > 0 && $_G['forum_thread']['special'] == 0) {
            //debug 出售贴的时间限制 按照小时计算 过期价格清零
            if ($_G['setting']['maxchargespan'] && TIMESTAMP - $_G['forum_thread']['dateline'] >= $_G['setting']['maxchargespan'] * 3600) {
                //DB::query("UPDATE ".DB::table($threadtable)." SET price='0' WHERE tid='$_G[tid]'");
                C::t('forum_thread')->update($_G['tid'], array('price' => 0), false, false, $archiveid);
                $_G['forum_thread']['price'] = 0;
            } else {
                $exemptvalue = $_G['forum']['ismoderator'] ? 128 : 16;
                if (!($_G['group']['exempt'] & $exemptvalue) && $_G['forum_thread']['authorid'] != $_G['uid']) {
                    //$query = DB::query("SELECT relatedid FROM ".DB::table('common_credit_log')." WHERE relatedid='$_G[tid]' AND uid='$_G[uid]' AND operation='BTC'");
                    //if(!DB::num_rows($query)) {
                    if (!(C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BTC', $_G['tid']))) {
                        require_once libfile('thread/pay', 'include');
                        $_G['forum_threadpay'] = TRUE;
                    }
                }
            }
        }

//抢楼帖处理
        if ($rushreply) {
            $rewardfloor = '';
            $rushresult = $rewardfloorarr = $rewardfloorarray = array();
            //$rushresult = DB::fetch_first("SELECT * FROM ".DB::table('forum_threadrush')." WHERE tid='$_G[tid]'");
            $rushresult = C::t('forum_threadrush')->fetch($_G['tid']);
            if ($rushresult['creditlimit'] == -996) {
                $rushresult['creditlimit'] = '';
            }
            if ((TIMESTAMP < $rushresult['starttimefrom'] || ($rushresult['starttimeto'] && TIMESTAMP > $rushresult['starttimeto']) || ($rushresult['stopfloor'] && $_G['forum_thread']['replies'] + 1 >= $rushresult['stopfloor'])) && $_G['forum_thread']['closed'] == 0) {
//		DB::query("UPDATE ".DB::table('forum_thread')." SET closed='1' WHERE tid='$_G[tid]'");
                C::t('forum_thread')->update($_G['tid'], array('closed' => 1));
            } elseif (($rushresult['starttimefrom'] && TIMESTAMP > $rushresult['starttimefrom']) && $_G['forum_thread']['closed'] == 1) {
                if (($rushresult['starttimeto'] && TIMESTAMP < $rushresult['starttimeto'] || !$rushresult['starttimeto']) && ($rushresult['stopfloor'] && $_G['forum_thread']['replies'] + 1 < $rushresult['stopfloor'] || !$rushresult['stopfloor'])) {
//				DB::query("UPDATE ".DB::table('forum_thread')." SET closed='0' WHERE tid='$_G[tid]'");
                    C::t('forum_thread')->update($_G['tid'], array('closed' => 0));
                }
                //当到期时间未设置时，但有结束楼层的情况下，new_reply的时候会结束当前帖
            }
            if ($rushresult['starttimefrom'] > TIMESTAMP) {
                $rushresult['timer'] = $rushresult['starttimefrom'] - TIMESTAMP;
                $rushresult['timertype'] = 'start';
            } elseif ($rushresult['starttimeto'] > TIMESTAMP) {
                $rushresult['timer'] = $rushresult['starttimeto'] - TIMESTAMP;
                $rushresult['timertype'] = 'end';
            }
            $rushresult['starttimefrom'] = $rushresult['starttimefrom'] ? dgmdate($rushresult['starttimefrom']) : '';
            $rushresult['starttimeto'] = $rushresult['starttimeto'] ? dgmdate($rushresult['starttimeto']) : '';
            $rushresult['creditlimit_title'] = $_G['setting']['creditstransextra'][11] ? $_G['setting']['extcredits'][$_G['setting']['creditstransextra'][11]]['title'] : lang('forum/misc', 'credit_total');
        }

//noteX 回帖奖励积分
        if ($_G['forum_thread']['replycredit'] > 0) {
//	$_G['forum_thread']['replycredit_rule'] = DB::fetch_first("SELECT * FROM ".DB::table('forum_replycredit')." WHERE tid = '{$thread['tid']}' LIMIT 1");
            $_G['forum_thread']['replycredit_rule'] = C::t('forum_replycredit')->fetch($thread['tid']);
            $_G['forum_thread']['replycredit_rule']['remaining'] = $_G['forum_thread']['replycredit'] / $_G['forum_thread']['replycredit_rule']['extcredits'];
            $_G['forum_thread']['replycredit_rule']['extcreditstype'] = $_G['forum_thread']['replycredit_rule']['extcreditstype'] ? $_G['forum_thread']['replycredit_rule']['extcreditstype'] : $_G['setting']['creditstransextra'][10];
        }
        $_G['group']['raterange'] = $_G['setting']['modratelimit'] && $adminid == 3 && !$_G['forum']['ismoderator'] ? array() : $_G['group']['raterange'];

//debug 论坛本身或者用户有可以下载附件的权限
        $_G['group']['allowgetattach'] = !empty($_G['forum']['allowgetattach']) || ($_G['group']['allowgetattach'] && !$_G['forum']['getattachperm']) || forumperm($_G['forum']['getattachperm']);
        $_G['group']['allowgetimage'] = !empty($_G['forum']['allowgetimage']) || ($_G['group']['allowgetimage'] && !$_G['forum']['getattachperm']) || forumperm($_G['forum']['getattachperm']);
        $_G['getattachcredits'] = '';
        if ($_G['forum_thread']['attachment']) {
            $exemptvalue = $_G['forum']['ismoderator'] ? 32 : 4;
            if (!($_G['group']['exempt'] & $exemptvalue)) {
                $creditlog = updatecreditbyaction('getattach', $_G['uid'], array(), '', 1, 0, $_G['forum_thread']['fid']);
                $p = '';
                if ($creditlog['updatecredit']) for ($i = 1; $i <= 8; $i++) {
                    if ($policy = $creditlog['extcredits' . $i]) {
                        $_G['getattachcredits'] .= $p . $_G['setting']['extcredits'][$i]['title'] . ' ' . $policy . ' ' . $_G['setting']['extcredits'][$i]['unit'];
                        $p = ', ';
                    }
                }
            }
        }

        $exemptvalue = $_G['forum']['ismoderator'] ? 64 : 8;
        $_G['forum_attachmentdown'] = $_G['group']['exempt'] & $exemptvalue;

        $postlist = $_G['forum_attachtags'] = $attachlist = $_G['forum_threadstamp'] = array();
        $aimgcount = 0;
        $_G['forum_attachpids'] = array();

        if (!empty($_GET['action']) && $_GET['action'] == 'printable' && $_G['tid']) {
            require_once libfile('thread/printable', 'include');
            dexit();
        }

        if ($_G['forum_thread']['stamp'] >= 0) {
            $_G['forum_threadstamp'] = $_G['cache']['stamps'][$_G['forum_thread']['stamp']];
        }

//此变量貌似无用
//$thisgid = $_G['forum']['type'] == 'forum' ? $_G['forum']['fup'] : $_G['cache']['forums'][$_G['forum']['fup']]['fup'];
        $lastmod = viewthread_lastmod($_G['forum_thread']);

        $showsettings = str_pad(decbin($_G['setting']['showsettings']), 3, '0', STR_PAD_LEFT);

        $showsignatures = $showsettings{0};
        $showavatars = $showsettings{1};
        $_G['setting']['showimages'] = $showsettings{2};

        $highlightstatus = isset($_GET['highlight']) && str_replace('+', '', $_GET['highlight']) ? 1 : 0;

        $_G['forum']['allowreply'] = isset($_G['forum']['allowreply']) ? $_G['forum']['allowreply'] : '';
        $_G['forum']['allowpost'] = isset($_G['forum']['allowpost']) ? $_G['forum']['allowpost'] : '';

        $allowpostreply = ($_G['forum']['allowreply'] != -1) && (($_G['forum_thread']['isgroup'] || (!$_G['forum_thread']['closed'] && !checkautoclose($_G['forum_thread']))) || $_G['forum']['ismoderator']) && ((!$_G['forum']['replyperm'] && $_G['group']['allowreply']) || ($_G['forum']['replyperm'] && forumperm($_G['forum']['replyperm'])) || $_G['forum']['allowreply']);
        $fastpost = $_G['setting']['fastpost'] && !$_G['forum_thread']['archiveid'] && ($_G['forum']['status'] != 3 || $_G['isgroupuser']);
        $allowfastpost = $_G['setting']['fastpost'] && $allowpostreply;
        if (!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum']) || !$_G['adminid'] && (!cknewuser(1) || $_G['setting']['newbiespan'] && (!getuserprofile('lastpost') || TIMESTAMP - getuserprofile('lastpost') < $_G['setting']['newbiespan'] * 60) && TIMESTAMP - $_G['member']['regdate'] < $_G['setting']['newbiespan'] * 60)) {
            $allowfastpost = false;
        }
        $_G['group']['allowpost'] = $_G['forum']['allowpost'] != -1 && ((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])) || $_G['forum']['allowpost']);

        $_G['forum']['allowpostattach'] = isset($_G['forum']['allowpostattach']) ? $_G['forum']['allowpostattach'] : '';
        $allowpostattach = $allowpostreply && ($_G['forum']['allowpostattach'] != -1 && ($_G['forum']['allowpostattach'] == 1 || (!$_G['forum']['postattachperm'] && $_G['group']['allowpostattach']) || ($_G['forum']['postattachperm'] && forumperm($_G['forum']['postattachperm']))));

//note 特殊类型主题发布按钮
        if ($_G['group']['allowpost']) {
            $_G['group']['allowpostpoll'] = $_G['group']['allowpostpoll'] && ($_G['forum']['allowpostspecial'] & 1);
            $_G['group']['allowposttrade'] = $_G['group']['allowposttrade'] && ($_G['forum']['allowpostspecial'] & 2);
            $_G['group']['allowpostreward'] = $_G['group']['allowpostreward'] && ($_G['forum']['allowpostspecial'] & 4) && isset($_G['setting']['extcredits'][$_G['setting']['creditstrans']]);
            $_G['group']['allowpostactivity'] = $_G['group']['allowpostactivity'] && ($_G['forum']['allowpostspecial'] & 8);
            $_G['group']['allowpostdebate'] = $_G['group']['allowpostdebate'] && ($_G['forum']['allowpostspecial'] & 16);
        } else {
            $_G['group']['allowpostpoll'] = $_G['group']['allowposttrade'] = $_G['group']['allowpostreward'] = $_G['group']['allowpostactivity'] = $_G['group']['allowpostdebate'] = FALSE;
        }

        $_G['forum']['threadplugin'] = $_G['group']['allowpost'] && $_G['setting']['threadplugins'] ? is_array($_G['forum']['threadplugin']) ? $_G['forum']['threadplugin'] : dunserialize($_G['forum']['threadplugin']) : array();

        $_G['setting']['visitedforums'] = $_G['setting']['visitedforums'] && $_G['forum']['status'] != 3 ? visitedforums() : '';

//debug Module: HTML_CACHE 去掉以前的缓存机制。保存$postlist到文件的一段代码

//debug 相关主题功能

        $relatedthreadlist = array();
        $relatedthreadupdate = $tagupdate = FALSE;
        $relatedkeywords = $tradekeywords = $_G['forum_firstpid'] = '';

        if (!isset($_G['cookie']['collapse']) || strpos($_G['cookie']['collapse'], 'modarea_c') === FALSE) {
            $collapseimg['modarea_c'] = 'collapsed_no';
            $collapse['modarea_c'] = '';
        } else {
            $collapseimg['modarea_c'] = 'collapsed_yes';
            $collapse['modarea_c'] = 'display: none';
        }

        $threadtag = array();
////处理未被更新的查看数
//$row = C::t('forum_threadaddviews')->fetch($_G['tid']);
//$thread['addviews'] = intval($row['addviews']);
//$thread['views'] += $thread['addviews'];
        viewthread_updateviews($archiveid);

        $_G['setting']['infosidestatus']['posts'] = $_G['setting']['infosidestatus'][1] && isset($_G['setting']['infosidestatus']['f' . $_G['fid']]['posts']) ? $_G['setting']['infosidestatus']['f' . $_G['fid']]['posts'] : $_G['setting']['infosidestatus']['posts'];

//可能已经废弃
//$infoside = $_G['setting']['infosidestatus'][1] && $_G['forum_thread']['replies'] > $_G['setting']['infosidestatus']['posts'];

        $postfieldsadd = $specialadd1 = $specialadd2 = $specialextra = '';
        $tpids = array();
//note 商品贴处理
        if ($_G['forum_thread']['special'] == 2) {
            if (!empty($_GET['do']) && $_GET['do'] == 'tradeinfo') {
                require_once libfile('thread/trade', 'include');
            }
            //$query = DB::query("SELECT pid FROM ".DB::table('forum_trade')." WHERE tid='$_G[tid]'");
            $query = C::t('forum_trade')->fetch_all_thread_goods($_G['tid']);
            //while($trade = DB::fetch($query)) {
            foreach ($query as $trade) {
                $tpids[] = $trade['pid'];
            }
//note for optimization	$specialadd2 = " AND pid NOT IN (".dimplode($tpids).")";
            $specialadd2 = 1;

//note 辩论贴处理
        } elseif ($_G['forum_thread']['special'] == 5) {
            if (isset($_GET['stand'])) {
                /*note for optimization
                        $specialadd1 .= "LEFT JOIN ".DB::table('forum_debatepost')." dp ON p.pid=dp.pid";
                        if($_GET['stand']) {
                            $specialadd2 .= "AND (dp.stand='$_GET[stand]' OR p.first='1')";
                        } else {
                            $specialadd2 .= "AND (dp.stand='0' OR dp.stand IS NULL OR p.first='1')";
                        }
                 */
                $specialadd2 = 1;
                $specialextra = "&amp;stand=$_GET[stand]";
//note for optimization
//	} else {
//		$specialadd1 = "LEFT JOIN ".DB::table('forum_debatepost')." dp ON p.pid=dp.pid";
            }
//note for optimization	$postfieldsadd .= ", dp.stand, dp.voters";
        }

        $onlyauthoradd = $threadplughtml = '';

        $maxposition = 0;
        if (empty($_GET['viewpid'])) {
            //是否按position字段浏览 悬赏 商品 辩论 除外
            $disablepos = !$rushreply && C::t('forum_threaddisablepos')->fetch($_G['tid']) ? 1 : 0; //note 有记录且不是抢楼时，也不用position方式
            if (!$disablepos && !in_array($_G['forum_thread']['special'], array(2, 3, 5))) {
                if ($_G['forum_thread']['maxposition']) {
                    $maxposition = $_G['forum_thread']['maxposition'];
                } else {
                    $maxposition = C::t('forum_post')->fetch_maxposition_by_tid($posttableid, $_G['tid']);
                    //C::t('forum_thread')->update($_G['tid'], array('maxposition' => $maxposition));
                }
            }

            $ordertype = empty($_GET['ordertype']) && getstatus($_G['forum_thread']['status'], 4) ? 1 : $_GET['ordertype'];
            if ($_GET['from'] == 'album') {
                $ordertype = 1;
            }
            //回帖置顶
            $sticklist = array();
            if ($_G['page'] === 1 && $_G['forum_thread']['stickreply'] && empty($_GET['authorid'])) {
//		$query = DB::query("SELECT p.*, ps.position FROM ".DB::table('forum_poststick')." ps
//			LEFT JOIN ".DB::table($posttable)." p USING(pid)
//			WHERE ps.tid='$_G[tid]' ORDER BY ps.dateline DESC");
                $poststick = C::t('forum_poststick')->fetch_all_by_tid($_G['tid']);
                foreach (C::t('forum_post')->fetch_all($posttableid, array_keys($poststick)) as $post) {
                    $post['position'] = $poststick[$post['pid']]['position'];
                    $post['avatar'] = avatar($post['authorid'], 'small');
                    $post['isstick'] = true;
                    $sticklist[$post['pid']] = $post;
                }
                $stickcount = count($sticklist);
            }
            //抢楼帖获奖楼层列表处理
            if ($rushreply) {
                $rushids = $rushpids = $rushpositionlist = $preg = $arr = array();
                $str = ',,';
                $preg_str = rushreply_rule($rushresult);
                if ($_GET['checkrush']) {
                    $maxposition = 0;
                    for ($i = 1; $i <= $_G['forum_thread']['replies'] + 1; $i++) {
                        $str = $str . $i . ',,';
                    }
                    preg_match_all($preg_str, $str, $arr);
                    $arr = $arr[0];
                    foreach ($arr as $var) {
                        $var = str_replace(',', '', $var);
                        $rushids[$var] = $var;
                    }
                    $temp_reply = $_G['forum_thread']['replies'];
                    $_G['forum_thread']['replies'] = $countrushpost = max(0, count($rushids) - 1);
                    $rushids = array_slice($rushids, ($page - 1) * $_G['ppp'], $_G['ppp']);
                    //$rushquery = DB::query("SELECT pid, position FROM ".DB::table('forum_postposition')." WHERE tid='$_G[tid]' AND position IN (".dimplode($rushids).") ORDER BY position");
                    //while($post = DB::fetch($rushquery)) {
                    foreach (C::t('forum_post')->fetch_all_by_tid_position($posttableid, $_G['tid'], $rushids) as $post) {
                        //$rushpids[] = $post['pid'];
                        $postarr[$post['position']] = $post;
                        //$rushpositionlist[$post['pid']] = $post['position'];
                    }
                } else {
                    //$maxposition = DB::result_first("SELECT position FROM ".DB::table('forum_postposition')." WHERE tid='$_G[tid]' ORDER BY position DESC LIMIT 1");
                    //$maxposition = C::t('forum_postposition')->fetch_maxposition_by_tid($_G['tid']);
                    //$_G['forum_thread']['replies'] = $maxposition;
                    for ($i = ($page - 1) * $_G['ppp'] + 1; $i <= $page * $_G['ppp']; $i++) {
                        $str = $str . $i . ',,';
                    }
                    preg_match_all($preg_str, $str, $arr);
                    $arr = $arr[0];
                    foreach ($arr as $var) {
                        $var = str_replace(',', '', $var);
                        $rushids[$var] = $var;
                    }
                    $_G['forum_thread']['replies'] = $_G['forum_thread']['replies'] - 1;
                }
            }

            if (!empty($_GET['authorid'])) {
                $maxposition = 0;
                //$_G['forum_thread']['replies'] = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0' AND authorid='$_GET[authorid]'");
                $_G['forum_thread']['replies'] = C::t('forum_post')->count_by_tid_invisible_authorid($_G['tid'], $_GET['authorid']);
                $_G['forum_thread']['replies']--;
                if ($_G['forum_thread']['replies'] < 0) {
                    showmessage('undefined_action');
                }
                //note for optimization $onlyauthoradd = "AND p.authorid='$_GET[authorid]'";
                $onlyauthoradd = 1;
            } elseif ($_G['forum_thread']['special'] == 5) {
                if (isset($_GET['stand']) && $_GET['stand'] >= 0 && $_GET['stand'] < 3) {
                    $_G['forum_thread']['replies'] = C::t('forum_debatepost')->count_by_tid_stand($_G['tid'], $_GET['stand']);//note DB::result_first("SELECT COUNT(*) FROM ".DB::table('forum_debatepost')." WHERE tid='$_G[tid]' AND stand='$_GET[stand]'");
                } else {
                    //$_G['forum_thread']['replies'] = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0'");
                    $_G['forum_thread']['replies'] = C::t('forum_post')->count_visiblepost_by_tid($_G['tid']);
                    $_G['forum_thread']['replies'] > 0 && $_G['forum_thread']['replies']--;
                }
            } elseif ($_G['forum_thread']['special'] == 2) {
                //$tradenum = DB::result_first("SELECT count(*) FROM ".DB::table('forum_trade')." WHERE tid='$_G[tid]'");
                $tradenum = C::t('forum_trade')->fetch_counter_thread_goods($_G['tid']);
                $_G['forum_thread']['replies'] -= $tradenum;
            }

            if ($maxposition) {
                $_G['forum_thread']['replies'] = $maxposition - 1;
            }
            //debug Module:HTML_CACHE 只缓存第一页。页面大小为系统默认。
            $_G['ppp'] = $_G['forum']['threadcaches'] && !$_G['uid'] ? $_G['setting']['postperpage'] : $_G['ppp'];
            $totalpage = ceil(($_G['forum_thread']['replies'] + 1) / $_G['ppp']);
            $page > $totalpage && $page = $totalpage;
            //debug 二分分页
            $_G['forum_pagebydesc'] = !$maxposition && $page > 2 && $page > ($totalpage / 2) ? TRUE : FALSE;

            if ($_G['forum_pagebydesc']) {
                $firstpagesize = ($_G['forum_thread']['replies'] + 1) % $_G['ppp'];
                $_G['forum_ppp3'] = $_G['forum_ppp2'] = $page == $totalpage && $firstpagesize ? $firstpagesize : $_G['ppp'];
                $realpage = $totalpage - $page + 1;
                if ($firstpagesize == 0) {
                    $firstpagesize = $_G['ppp'];
                }
                $start_limit = max(0, ($realpage - 2) * $_G['ppp'] + $firstpagesize);
                $_G['forum_numpost'] = ($page - 1) * $_G['ppp'];
                if ($ordertype != 1) {
                    //note for optimization $pageadd =  "ORDER BY p.dateline DESC LIMIT $start_limit, ".$_G['forum_ppp2'];
                } else {
                    $_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'] + ($page > 1 ? 1 : 0);
                    //note for optimization $pageadd = "ORDER BY p.first ASC, p.dateline ASC LIMIT $start_limit, ".$_G['forum_ppp2'];
                }
            } else {
                $start_limit = $_G['forum_numpost'] = max(0, ($page - 1) * $_G['ppp']);
                if ($start_limit > $_G['forum_thread']['replies']) {
                    $start_limit = $_G['forum_numpost'] = 0;
                    $page = 1;
                }
                if ($ordertype != 1) {
                    //note for optimization $pageadd = "ORDER BY p.dateline LIMIT $start_limit, $_G[ppp]";
                } else {
                    $_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'] + ($page > 1 ? 1 : 0);
                    //note for optimization $pageadd = "ORDER BY p.first DESC, p.dateline DESC LIMIT $start_limit, $_G[ppp]";
                }
            }
            $multipage = multi($_G['forum_thread']['replies'] + 1, $_G['ppp'], $page, 'forum.php?mod=viewthread&tid=' . $_G['tid'] .
                ($_G['forum_thread']['is_archived'] ? '&archive=' . $_G['forum_thread']['archiveid'] : '') .
                '&amp;extra=' . $_GET['extra'] .
                ($ordertype && $ordertype != getstatus($_G['forum_thread']['status'], 4) ? '&amp;ordertype=' . $ordertype : '') .
                (isset($_GET['highlight']) ? '&amp;highlight=' . rawurlencode($_GET['highlight']) : '') .
                (!empty($_GET['authorid']) ? '&amp;authorid=' . $_GET['authorid'] : '') .
                (!empty($_GET['from']) ? '&amp;from=' . $_GET['from'] : '') .
                (!empty($_GET['checkrush']) ? '&amp;checkrush=' . $_GET['checkrush'] : '') .
                (!empty($_GET['modthreadkey']) ? '&amp;modthreadkey=' . rawurlencode($_GET['modthreadkey']) : '') .
                $specialextra);
        } else {
            $_GET['viewpid'] = intval($_GET['viewpid']);
            $pageadd = "AND p.pid='$_GET[viewpid]'";
        }

//debug 主题内容及回复
        $_G['forum_newpostanchor'] = $_G['forum_postcount'] = 0;

        $_G['forum_onlineauthors'] = $_G['forum_cachepid'] = $_G['blockedpids'] = array();

//note for optimization $query = "SELECT p.* $postfieldsadd FROM ".DB::table($posttable)." p $specialadd1 ";

        $isdel_post = $cachepids = $postusers = $skipaids = array();

        if ($_G['forum_auditstatuson'] || in_array($_G['forum_thread']['displayorder'], array(-2, -3, -4)) && $_G['forum_thread']['authorid'] == $_G['uid']) {
            $visibleallflag = 1;
        }

//if($savepostposition && empty($onlyauthoradd) && empty($specialadd2) && empty($_GET['viewpid'])) {
        if ($maxposition) {
            $start = ($page - 1) * $_G['ppp'] + 1;
            $end = $start + $_G['ppp'];
            if ($ordertype == 1) {
                $end = $maxposition - ($page - 1) * $_G['ppp'] + ($page > 1 ? 2 : 1);
                $start = $end - $_G['ppp'] + ($page > 1 ? 0 : 1);
                $start = max(array(1, $start));
            }
// 	$q2 = DB::query("SELECT pid, position FROM ".DB::table('forum_postposition')." WHERE tid='$_G[tid]' AND position>='$start' AND position<'$end' ORDER BY position");
            $have_badpost = $realpost = $lastposition = 0;
            //while ($post = DB::fetch($q2)) {
            foreach (C::t('forum_post')->fetch_all_by_tid_range_position($posttableid, $_G['tid'], $start, $end, $maxposition, $ordertype) as $post) {
                if ($post['invisible'] != 0) {
                    $have_badpost = 1;
                }
                $cachepids[$post[position]] = $post['pid'];
                //$positionlist[$post['pid']] = $post['position'];
                $postarr[$post[position]] = $post;
                $lastposition = $post['position'];
            }
            $realpost = count($postarr);
            if ($realpost != $_G['ppp'] || $have_badpost) {
                $k = 0;
                for ($i = $start; $i < $end; $i++) {
                    if (!empty($cachepids[$i])) {
                        $k = $cachepids[$i];
                        //note 因为删除用户时没有把postposition里的数据清理干净，所以...
                        $isdel_post[$i] = array('deleted' => 1, 'pid' => $k, 'message' => '', 'position' => $i);
                    } elseif ($i < $maxposition || ($lastposition && $i < $lastposition)) {
                        $isdel_post[$i] = array('deleted' => 1, 'pid' => $k, 'message' => '', 'position' => $i);
                    }
                    $k++;
                }
            }
            $pagebydesc = false;
        }

//抢楼贴中奖帖子ID
        if ($_GET['checkrush'] && $rushreply) {
            //$cachepids = $rushpids;
            $_G['forum_thread']['replies'] = $temp_reply;
            //$maxposition = $temp_reply;
        }

//note for optimization $query .= $savepostposition && $cachepids ? "WHERE p.pid IN ($cachepids)" : ("WHERE p.tid='$_GET[tid]'".($_G['forum_auditstatuson'] || in_array($_G['forum_thread']['displayorder'], array(-2, -3, -4)) && $_G['forum_thread']['authorid'] == $_G['uid'] ? '' : " AND p.invisible='0'")." $specialadd2 $onlyauthoradd $pageadd");

        /*
        $postarr = array();
        if($savepostposition && $cachepids) {
            $postarr = C::t('forum_post')->fetch_all('tid:'.$_G['tid'], $cachepids);
            $ordertype = 0;
        } else {
         */
        if (!$maxposition && empty($postarr)) {

            if (empty($_GET['viewpid'])) {
                if ($_G['forum_thread']['special'] == 2) {
                    $postarr = C::t('forum_post')->fetch_all_tradepost_viewthread_by_tid($_G['tid'], $visibleallflag, $_GET['authorid'], $tpids, $_G['forum_pagebydesc'], $ordertype, $start_limit, ($_G['forum_pagebydesc'] ? $_G['forum_ppp2'] : $_G['ppp']));
                } elseif ($_G['forum_thread']['special'] == 5) {
                    $postarr = C::t('forum_post')->fetch_all_debatepost_viewthread_by_tid($_G['tid'], $visibleallflag, $_GET['authorid'], $_GET['stand'], $_G['forum_pagebydesc'], $ordertype, $start_limit, ($_G['forum_pagebydesc'] ? $_G['forum_ppp2'] : $_G['ppp']));
                } else {
                    $postarr = C::t('forum_post')->fetch_all_common_viewthread_by_tid($_G['tid'], $visibleallflag, $_GET['authorid'], $_G['forum_pagebydesc'], $ordertype, $_G['forum_thread']['replies'] + 1, $start_limit, ($_G['forum_pagebydesc'] ? $_G['forum_ppp2'] : $_G['ppp']));
                }
            } else {
                $post = array();
                if ($_G['forum_thread']['special'] == 2) {
                    if (!in_array($_GET['viewpid'], $tpids)) {
                        $post = C::t('forum_post')->fetch('tid:' . $_G['tid'], $_GET['viewpid']);
                    }
                } elseif ($_G['forum_thread']['special'] == 5) {
                    $post = C::t('forum_post')->fetch('tid:' . $_G['tid'], $_GET['viewpid']);
                    $debatpost = C::t('forum_debatepost')->fetch($_GET['viewpid']);
                    if (!isset($_GET['stand']) || (isset($_GET['stand']) && ($post['first'] == 1 || $debatpost['stand'] == $_GET['stand']))) {
                        $post = array_merge($post, $debatpost);
                    } else {
                        $post = array();
                    }
                    unset($debatpost);
                } else {
                    $post = C::t('forum_post')->fetch('tid:' . $_G['tid'], $_GET['viewpid']);
                }
                if ($post['tid'] != $_G['tid']) {
                    $post = array();
                }

                if ($post) {
                    if ($visibleallflag || (!$visibleallflag && !$post['invisible'])) {
                        $postarr[0] = $post;
                    }
                }
            }

        }

        if (!empty($isdel_post)) {
            $updatedisablepos = false;
            foreach ($isdel_post as $id => $post) {
                //note -3 是针对草稿帖做特殊处理
                if (isset($postarr[$id]['invisible']) && ($postarr[$id]['invisible'] == 0 || $postarr[$id]['invisible'] == -3 || $visibleallflag)) {
                    continue;
                }
                $postarr[$id] = $post;
                $updatedisablepos = true;
            }
            if ($updatedisablepos && !$rushreply) {
                C::t('forum_threaddisablepos')->insert(array('tid' => $_G['tid']), false, true);
                dheader("Location:forum.php?mod=viewthread&tid=$_G[tid]");
            }
            $ordertype != 1 ? ksort($postarr) : krsort($postarr);
            //$postarr = $isdel_post;
        }
        $summary = '';
//note for optimization $query = DB::query($query);
        if ($page == 1 && $ordertype == 1) {
            $firstpost = C::t('forum_post')->fetch_threadpost_by_tid_invisible($_G['tid']);
            if ($firstpost['invisible'] == 0 || $visibleallflag == 1) {
                $postarr = array_merge(array($firstpost), $postarr);
                unset($firstpost);
            }
        }
        $tagnames = $locationpids = $hotpostarr = $hotpids = $member_blackList = array();

        $remainhots = ($_G['page'] == 1 && !$rushreply && !$hiddenreplies && !$_G['forum_thread']['special'] && !$_G['forum']['noforumrecommend'] && empty($_GET['authorid'])) ? $_G['setting']['threadhotreplies'] : 0;
//热门主题推荐
        if ($remainhots) {
            $hotpids = array_keys(C::t('forum_hotreply_number')->fetch_all_by_tid_total($_G['tid'], 10));
            $remainhots = $remainhots - count($hotpids);
        }

//参与数量高的主题不够显示，且主题数超过一页，开始推荐非水帖
        if ($_G['setting']['nofilteredpost'] && $_G['forum_thread']['replies'] > $_G['setting']['postperpage'] && $remainhots) {
            $hotpids = array_merge($hotpids, array_keys(C::t('forum_filter_post')->fetch_all_by_tid_postlength_limit($_G['tid'], $remainhots)));
        }

//有推荐的热帖
        if ($hotpids) {
            $hotposts = C::t('forum_post')->fetch_all_by_pid($posttableid, $hotpids);//获取招贴
            //按热帖原来顺序排列
            foreach ($hotpids as $hotpid) {
                if ($hotposts[$hotpid]) {
                    $hotpostarr[$hotpid] = $hotposts[$hotpid];
                    $hotpostarr[$hotpid]['hotrecommended'] = true;
                }
            }
            unset($hotposts);
        }

//(有推荐热贴 或 有置顶贴)
        if ($hotpostarr || $sticklist) {
            $_newpostarr_first = array_shift($postarr);
            $postarr = (array)$sticklist + (array)$hotpostarr + $postarr;
            array_unshift($postarr, $_newpostarr_first);
            unset($_newpostarr_first, $sticklist);
        }

        foreach ($postarr as $post) {
            //( ( ( 指定作者 && 非匿名贴) || 所有帖子) && 此PID未处理过 )
            if (($onlyauthoradd && empty($post['anonymous']) || !$onlyauthoradd) && !isset($postlist[$post['pid']])) {

                //热帖是否在当前帖子列表中存在
                if (isset($hotpostarr[$post['pid']])) {
                    $post['existinfirstpage'] = true;
                }

                $postusers[$post['authorid']] = array();
                if ($post['first']) {
                    if ($ordertype == 1 && $page != 1) {
                        continue;
                    }
                    $_G['forum_firstpid'] = $post['pid'];
                    if (!$_G['forum_thread']['price'] && (IS_ROBOT || $_G['adminid'] == 1)) $summary = str_replace(array("\r", "\n"), '', messagecutstr(strip_tags($post['message']), 160));
                    //处理标签
                    $tagarray_all = $posttag_array = array();
                    $tagarray_all = explode("\t", $post['tags']);
                    if ($tagarray_all) {
                        foreach ($tagarray_all as $var) {
                            if ($var) {
                                $tag = explode(',', $var);
                                $posttag_array[] = $tag;
                                $tagnames[] = $tag[1];
                            }
                        }
                    }
                    $post['tags'] = $posttag_array;
                    if ($post['tags']) {
                        $post['relateitem'] = getrelateitem($post['tags'], $post['tid'], $_G['setting']['relatenum'], $_G['setting']['relatetime']);
                    }
                    if (!$_G['forum']['disablecollect']) { //note 判断是否禁止淘帖
                        if ($incollection) {
                            $post['relatecollection'] = getrelatecollection($post['tid'], false, $post['releatcollectionnum'], $post['releatcollectionmore']);
                            if ($_G['group']['allowcommentcollection'] && $_GET['ctid']) {
                                $ctid = dintval($_GET['ctid']);
                                $post['sourcecollection'] = C::t('forum_collection')->fetch($ctid);
                            }
                        } else {
                            $post['releatcollectionnum'] = 0;
                        }
                    }
                }
                $postlist[$post['pid']] = $post;
            }
        }
        unset($hotpostarr);
//note SEO设置
        $seodata = array('forum' => $_G['forum']['name'], 'fup' => $_G['cache']['forums'][$fup]['name'], 'subject' => $_G['forum_thread']['subject'], 'summary' => $summary, 'tags' => @implode(',', $tagnames), 'page' => intval($_GET['page']));
        if ($_G['forum']['status'] != 3) {
            $seotype = 'viewthread';
        } else {
            $seotype = 'viewthread_group';
            $seodata['first'] = $nav['first']['name'];
            $seodata['second'] = $nav['second']['name'];
        }

        list($navtitle, $metadescription, $metakeywords) = get_seosetting($seotype, $seodata);
        if (!$navtitle) {
            $navtitle = helper_seo::get_title_page($_G['forum_thread']['subject'], $_G['page']) . ' - ' . strip_tags($_G['forum']['name']);
            $nobbname = false;
        } else {
            $nobbname = true;
        }
        if (!$metakeywords) {
            $metakeywords = strip_tags($thread['subject']);
        }
        if (!$metadescription) {
            $metadescription = $summary . ' ' . strip_tags($_G['forum_thread']['subject']);
        }

        $_G['allblocked'] = true; //标记是否所有帖子被过滤（所有都是匿名帖除外）
        $postno = &$_G['cache']['custominfo']['postno'];
        $postnostick = str_replace(array('<sup>', '</sup>'), '', $postno[0]);
        if ($postusers) {
            $member_verify = $member_field_forum = $member_status = $member_count = $member_profile = $member_field_home = array();
            $uids = array_keys($postusers);
            $uids = array_filter($uids);
            /*$verifyadd = '';
            $fieldsadd = $_G['cache']['custominfo']['fieldsadd'];
            if($_G['setting']['verify']['enabled']) {
                $verifyadd = "LEFT JOIN ".DB::table('common_member_verify')." mv USING(uid)";
                $fieldsadd .= ', mv.verify1, mv.verify2, mv.verify3, mv.verify4, mv.verify5, mv.verify6, mv.verify7';
            }*/

            /*
            $query = DB::query("SELECT m.uid, m.username, m.groupid, m.adminid, m.regdate, m.credits, m.email, m.status AS memberstatus,
                    ms.lastactivity, ms.lastactivity, ms.invisible AS authorinvisible,
                    mc.*, mp.gender, mp.site, mp.icq, mp.qq, mp.yahoo, mp.msn, mp.taobao, mp.alipay,
                    mf.medals, mf.sightml AS signature, mf.customstatus, mh.privacy $fieldsadd
                    FROM ".DB::table('common_member')." m
                    LEFT JOIN ".DB::table('common_member_field_forum')." mf USING(uid)
                    LEFT JOIN ".DB::table('common_member_status')." ms USING(uid)
                    LEFT JOIN ".DB::table('common_member_count')." mc USING(uid)
                    LEFT JOIN ".DB::table('common_member_profile')." mp USING(uid)
                    LEFT JOIN ".DB::table('common_member_field_home')." mh USING(uid)
                    $verifyadd
                    WHERE m.uid IN (".dimplode(array_keys($postusers)).")");
            while($postuser = DB::fetch($query)) {
                $postuser['privacy'] = unserialize($postuser['privacy']);
                unset($postuser['privacy']['feed'], $postuser['privacy']['view']);
                $postusers[$postuser['uid']] = $postuser;
            }*/
            $selfuids = $uids;
            if ($_G['setting']['threadblacklist'] && $_G['uid'] && !in_array($_G['uid'], $selfuids)) {
                $selfuids[] = $_G['uid'];
            }
            if (!($_G['setting']['threadguestlite'] && !$_G['uid'])) {
                if ($_G['setting']['verify']['enabled']) {
                    $member_verify = C::t('common_member_verify')->fetch_all($uids);
                    foreach ($member_verify as $uid => $data) {
                        foreach ($_G['setting']['verify'] as $vid => $verify) {
                            if ($verify['available'] && $verify['showicon']) {
                                if ($data['verify' . $vid] == 1) {
                                    $member_verify[$uid]['verifyicon'][] = $vid;
                                } elseif (!empty($verify['unverifyicon'])) {
                                    $member_verify[$uid]['unverifyicon'][] = $vid;
                                }
                            }
                        }
                    }
                }
                $member_count = C::t('common_member_count')->fetch_all($selfuids);
                $member_status = C::t('common_member_status')->fetch_all($uids);
                $member_field_forum = C::t('common_member_field_forum')->fetch_all($uids);
                $member_profile = C::t('common_member_profile')->fetch_all($uids);
                $member_field_home = C::t('common_member_field_home')->fetch_all($uids);
            }

            //Black List
            if ($_G['setting']['threadblacklist'] && $_G['uid'] && $member_count[$_G['uid']]['blacklist']) {
                $member_blackList = C::t('home_blacklist')->fetch_all_by_uid_buid($_G['uid'], $uids);
            }

            //$member_followed = C::t('home_follow')->fetch_all_by_uid_followuid($_G['uid'], $uids);
            foreach (C::t('common_member')->fetch_all($uids) as $uid => $postuser) {
                $member_field_home[$uid]['privacy'] = empty($member_field_home[$uid]['privacy']) ? array() : dunserialize($member_field_home[$uid]['privacy']);
                $postuser['memberstatus'] = $postuser['status'];
                $postuser['authorinvisible'] = $member_status[$uid]['invisible'];
                $postuser['signature'] = $member_field_forum[$uid]['sightml'];
                //$postuser['followed'] = !empty($member_followed[$uid]) ? 1 : 0;
                unset($member_field_home[$uid]['privacy']['feed'], $member_field_home[$uid]['privacy']['view'], $postuser['status'], $member_status[$uid]['invisible'], $member_field_forum[$uid]['sightml']);
                $postusers[$uid] = array_merge((array)$member_verify[$uid], (array)$member_field_home[$uid], (array)$member_profile[$uid], (array)$member_count[$uid], (array)$member_status[$uid], (array)$member_field_forum[$uid], $postuser);
                if ($postusers[$uid]['regdate'] + $postusers[$uid]['oltime'] * 3600 > TIMESTAMP) {
                    $postusers[$uid]['oltime'] = 0;
                }
                $postusers[$uid]['office'] = $postusers[$uid]['position'];
                $postusers[$uid]['inblacklist'] = $member_blackList[$uid] ? true : false;
                $postusers[$uid]['groupcolor'] = $_G['cache']['usergroups'][$postuser['groupid']]['color'];
                unset($postusers[$uid]['position']);
            }
            unset($member_field_forum, $member_status, $member_count, $member_profile, $member_field_home, $member_blackList);
            $_G['medal_list'] = array();
            foreach ($postlist as $pid => $post) {
                if (getstatus($post['status'], 6)) {
                    $locationpids[] = $pid;
                }
                $post = array_merge($postlist[$pid], (array)$postusers[$post['authorid']]);
                $postlist[$pid] = viewthread_procpost($post, $_G['member']['lastvisit'], $ordertype, $maxposition);
            }
        }

//防止全被过滤
        if ($_G['allblocked']) {
            $_G['blockedpids'] = array();
        }

        if ($locationpids) {
            $locations = C::t('forum_post_location')->fetch_all($locationpids);
        }

        if ($postlist && $rushids) {
            foreach ($postlist as $pid => $post) {
                $post['number'] = $post['position'];
                $postlist[$pid] = checkrushreply($post);
            }
        }

//回帖投票
        if ($_G['setting']['repliesrank'] && $postlist) {
            if ($postlist) {
                foreach (C::t('forum_hotreply_number')->fetch_all_by_pids(array_keys($postlist)) as $pid => $post) {
                    $postlist[$pid]['postreview']['support'] = dintval($post['support']);
                    $postlist[$pid]['postreview']['against'] = dintval($post['against']);
                }
            }
        }

        /* 新看帖机制楼层已经在post表里了
        //note 从数据库读取回帖位置信息
        if($savepostposition && $positionlist) {
            foreach ($positionlist as $pid => $position) {
                if($postlist[$pid]){
                    $postlist[$pid]['number'] = $position;
                    if($rushreply) {
                        $postlist[$pid] = checkrushreply($postlist[$pid]);
                    }
                }
            }
        }
        //抢楼贴获奖楼层显示楼层
        if($_GET['checkrush'] && $rushreply) {
            foreach ($rushpositionlist as $pid => $position)
            if($postlist[$pid]){
                $postlist[$pid]['number'] = $position;
                $postlist[$pid]['rewardfloor'] = 1;
            }
        }
         *
         */

        if ($_G['forum_thread']['special'] > 0 && (empty($_GET['viewpid']) || $_GET['viewpid'] == $_G['forum_firstpid'])) {
            $_G['forum_thread']['starttime'] = gmdate($_G['forum_thread']['dateline']);
            $_G['forum_thread']['remaintime'] = '';
            switch ($_G['forum_thread']['special']) {
                case 1:
                    require_once libfile('thread/poll', 'include');
                    break;
                case 2:
                    require_once libfile('thread/trade', 'include');
                    break;
                case 3:
                    require_once libfile('thread/reward', 'include');
                    break;
                case 4:
                    require_once libfile('thread/activity', 'include');
                    break;
                case 5:
                    require_once libfile('thread/debate', 'include');
                    break;
                case 127:
                    if ($_G['forum_firstpid']) {
                        $sppos = strpos($postlist[$_G['forum_firstpid']]['message'], chr(0) . chr(0) . chr(0));
                        $specialextra = substr($postlist[$_G['forum_firstpid']]['message'], $sppos + 3);
                        $postlist[$_G['forum_firstpid']]['message'] = substr($postlist[$_G['forum_firstpid']]['message'], 0, $sppos);
                        if ($specialextra) {
                            if (array_key_exists($specialextra, $_G['setting']['threadplugins'])) {
                                @include_once DISCUZ_ROOT . './source/plugin/' . $_G['setting']['threadplugins'][$specialextra]['module'] . '.class.php';
                                $classname = 'threadplugin_' . $specialextra;
                                if (class_exists($classname) && method_exists($threadpluginclass = new $classname, 'viewthread')) {
                                    $threadplughtml = $threadpluginclass->viewthread($_G['tid']);
                                }
                            }
                        }
                    }
                    break;
            }
        }
//note 自动修复主题回复数, 当主题表记录的回复数和实际回复数不符，则自动修复
        if (empty($_GET['authorid']) && empty($postlist)) {
            if ($rushreply) {
                dheader("Location: forum.php?mod=redirect&tid=$_G[tid]&goto=lastpost");
            } else {
                //$replies = DB::result_first("SELECT COUNT(*) FROM ".DB::table($posttable)." WHERE tid='$_G[tid]' AND invisible='0'");
                $replies = C::t('forum_post')->count_visiblepost_by_tid($_G['tid']);
                $replies = intval($replies) - 1;
                if ($_G['forum_thread']['replies'] != $replies && $replies > 0) {
                    //DB::query("UPDATE ".DB::table($threadtable)." SET replies='$replies' WHERE tid='$_G[tid]'");
                    C::t('forum_thread')->update($_G['tid'], array('replies' => $replies), false, false, $archiveid);
                    dheader("Location: forum.php?mod=redirect&tid=$_G[tid]&goto=lastpost");
                }
            }
        }

        if ($_G['forum_pagebydesc'] && (!$savepostposition || $_GET['ordertype'] == 1)) {
            $postlist = array_reverse($postlist, TRUE);
        }

//关闭session时强制使用快速显示（使用lastactiviry的值判断）
        if (!empty($_G['setting']['sessionclose'])) {
            $_G['setting']['vtonlinestatus'] = 1;
        }

        if ($_G['setting']['vtonlinestatus'] == 2 && $_G['forum_onlineauthors']) {
            /*$query = DB::query("SELECT uid FROM ".DB::table('common_session')." WHERE uid IN(".dimplode($_G['forum_onlineauthors']).") AND invisible=0");
            $_G['forum_onlineauthors'] = array();
            while($author = DB::fetch($query)) {
                $_G['forum_onlineauthors'][$author['uid']] = 1;
            }*/
            foreach (C::app()->session->fetch_all_by_uid(array_keys($_G['forum_onlineauthors'])) as $author) {
                if (!$author['invisible']) {
                    $_G['forum_onlineauthors'][$author['uid']] = 1;
                }
            }
        } else {
            $_G['forum_onlineauthors'] = array();
        }
        $ratelogs = $comments = $commentcount = $totalcomment = array();
        if ($_G['forum_cachepid']) {
            foreach (C::t('forum_postcache')->fetch_all($_G['forum_cachepid']) as $postcache) {
                if ($postcache['rate']) {
                    $postcache['rate'] = dunserialize($postcache['rate']);
                    $postlist[$postcache['pid']]['ratelog'] = $postcache['rate']['ratelogs'];
                    $postlist[$postcache['pid']]['ratelogextcredits'] = $postcache['rate']['extcredits'];
                    $postlist[$postcache['pid']]['totalrate'] = $postcache['rate']['totalrate'];
                }
                if ($postcache['comment']) {
                    $postcache['comment'] = dunserialize($postcache['comment']);
                    $commentcount[$postcache['pid']] = $postcache['comment']['count'];
                    $comments[$postcache['pid']] = $postcache['comment']['data'];
                    $totalcomment[$postcache['pid']] = $postcache['comment']['totalcomment'];
                }
                unset($_G['forum_cachepid'][$postcache['pid']]);
            }
            $postcache = $ratelogs = array();
            if ($_G['forum_cachepid']) {
                list($ratelogs, $postlist, $postcache) = C::t('forum_ratelog')->fetch_postrate_by_pid($_G['forum_cachepid'], $postlist, $postcache, $_G['setting']['ratelogrecord']);
            }
            foreach ($postlist as $key => $val) {
                if (!empty($val['ratelogextcredits'])) {
                    ksort($postlist[$key]['ratelogextcredits']);
                }
            }

            if ($_G['forum_cachepid'] && $_G['setting']['commentnumber']) {
                list($comments, $postcache, $commentcount, $totalcomment) = C::t('forum_postcomment')->fetch_postcomment_by_pid($_G['forum_cachepid'], $postcache, $commentcount, $totalcomment, $_G['setting']['commentnumber']);
            }

            foreach ($postcache as $pid => $data) {
                C::t('forum_postcache')->insert(array('pid' => $pid, 'rate' => serialize($data['rate']), 'comment' => serialize($data['comment']), 'dateline' => TIMESTAMP), false, true);
            }
        }

        if ($_G['forum_attachpids'] && !defined('IN_ARCHIVER')) {
            require_once libfile('function/attachment');
            if (is_array($threadsortshow) && !empty($threadsortshow['sortaids'])) {
                $skipaids = $threadsortshow['sortaids'];
            }
            parseattach($_G['forum_attachpids'], $_G['forum_attachtags'], $postlist, $skipaids);
        }

        if (empty($postlist)) {
            showmessage('post_not_found');
        } elseif (!defined('IN_MOBILE_API')) {
            foreach ($postlist as $pid => $post) {
                $postlist[$pid]['message'] = preg_replace("/\[attach\]\d+\[\/attach\]/i", '', $postlist[$pid]['message']);
            }
        }

//note viewthread_parsetags($postlist);
        if (defined('IN_ARCHIVER')) {
            include loadarchiver('forum/viewthread');
            exit();
        }

        $_G['forum_thread']['heatlevel'] = $_G['forum_thread']['recommendlevel'] = 0;
        if ($_G['setting']['heatthread']['iconlevels']) {
            foreach ($_G['setting']['heatthread']['iconlevels'] as $k => $i) {
                if ($_G['forum_thread']['heats'] > $i) {
                    $_G['forum_thread']['heatlevel'] = $k + 1;
                    break;
                }
            }
        }

        if (!empty($_G['setting']['recommendthread']['status']) && $_G['forum_thread']['recommends']) {
            foreach ($_G['setting']['recommendthread']['iconlevels'] as $k => $i) {
                if ($_G['forum_thread']['recommends'] > $i) {
                    $_G['forum_thread']['recommendlevel'] = $k + 1;
                    break;
                }
            }
        }

        $allowblockrecommend = $_G['group']['allowdiy'] || getstatus($_G['member']['allowadmincp'], 4) || getstatus($_G['member']['allowadmincp'], 5) || getstatus($_G['member']['allowadmincp'], 6);
        if ($_G['setting']['portalstatus']) {
            $allowpostarticle = $_G['group']['allowmanagearticle'] || $_G['group']['allowpostarticle'] || getstatus($_G['member']['allowadmincp'], 2) || getstatus($_G['member']['allowadmincp'], 3);
            $allowpusharticle = empty($_G['forum_thread']['special']) && empty($_G['forum_thread']['sortid']) && !$_G['forum_thread']['pushedaid'];
        } else {
            $allowpostarticle = $allowpusharticle = false;
        }
        if ($_G['forum_thread']['displayorder'] != -4) {
            $modmenu = array(
                'thread' => $_G['forum']['ismoderator'] || $allowblockrecommend || $allowpusharticle && $allowpostarticle,
                'post' => $_G['forum']['ismoderator'] && ($_G['group']['allowwarnpost'] || $_G['group']['allowbanpost'] || $_G['group']['allowdelpost'] || $_G['group']['allowstickreply']) || $_G['forum_thread']['pushedaid'] && $allowpostarticle || $_G['forum_thread']['authorid'] == $_G['uid']
            );
        } else {
            $modmenu = array();
        }
        

        if ($_G['forum_thread']['replies'] > $_G['forum_thread']['views']) {
            $_G['forum_thread']['views'] = $_G['forum_thread']['replies'];
        }

        $_G['fourm_thread']['zanList'] = DzForumThread::getZanList($_G['tid']);
        if ($postlist[1]['first'] == '1') {
            $_G['forum_thread']['post'] = $postlist[1];
            unset($postlist[1]);
        }

        header("Content-Type: text/html; charset=utf-8");
        header("Cache-Control: no-cache, must-revalidate");
        header('Pragma: no-cache');
        $downloadInfo = AppbymeConfig::getAppInfoByForumKey($_GET['forumKey']);
        if (empty($downloadInfo)) {
            $downloadInfo = AppbymeConfig::getDownloadOptions();
        }
        $showInfo['appname'] = $downloadInfo['appName'];
        $showInfo['download'] = $downloadInfo['downloadUrl'] ? $downloadInfo['downloadUrl'] : $_G['siteurl'] . '/mobcent/download/down.php';
        $showInfo['icon'] = $downloadInfo['appIcon'];
        $showInfo['describe'] = $downloadInfo['appDescribe'];
        $this->getController()->renderPartial('share', array('postlist' => $postlist, 'downInfo' => $showInfo));
    }

}


/**
 * 更新查看次数，一处用在主题缓存readfile()后，另一处用在forum.php?mod=viewthread。
 */
function viewthread_updateviews($tableid) {
    global $_G;

    //存档帖子直接更新
    if (!$_G['setting']['preventrefresh'] || $_G['cookie']['viewid'] != 'tid_' . $_G['tid']) {
        if (!$tableid && $_G['setting']['optimizeviews']) {
            if ($_G['forum_thread']['addviews']) {
                if ($_G['forum_thread']['addviews'] < 100) {
                    C::t('forum_threadaddviews')->update_by_tid($_G['tid']);
                } else {
                    if (!discuz_process::islocked('update_thread_view')) {
                        $row = C::t('forum_threadaddviews')->fetch($_G['tid']);
                        C::t('forum_threadaddviews')->update($_G['tid'], array('addviews' => 0));
                        C::t('forum_thread')->increase($_G['tid'], array('views' => $row['addviews'] + 1), true);
                        discuz_process::unlock('update_thread_view');
                    }
                }
            } else {
                C::t('forum_threadaddviews')->insert(array('tid' => $_G['tid'], 'addviews' => 1), false, true);
            }
        } else {
            C::t('forum_thread')->increase($_G['tid'], array('views' => 1), true, $tableid);
        }
    }
    //简单的防刷新
    dsetcookie('viewid', 'tid_' . $_G['tid']);
    /*
    //debug 统计数字延迟更新
    if($_G['setting']['delayviewcount'] == 1 || $_G['setting']['delayviewcount'] == 3) {
        $_G['forum_logfile'] = './data/cache/forum_threadviews_'.intval(getglobal('config/server/id')).'.log';
        if(substr(TIMESTAMP, -2) == '00') {
            require_once libfile('function/misc');
            updateviews($threadtable, 'tid', 'views', $_G['forum_logfile']);
        }
        if(@$fp = fopen(DISCUZ_ROOT.$_G['forum_logfile'], 'a')) {
            fwrite($fp, "$_G[tid]\n");
            fclose($fp);
        } elseif($_G['adminid'] == 1) {
            showmessage('view_log_invalid', '', array('logfile' => $_G['forum_logfile']));
        }
    } else {

        //debug 增加访问次数
        //DB::query("UPDATE LOW_PRIORITY ".DB::table($threadtable)." SET views=views+1 WHERE tid='$_G[tid]'", 'UNBUFFERED');
        C::t('forum_thread')->increase($_G['tid'], array('views' => 1), true);

    }
    */
}

/**
 * 帖子处理
 *
 * @param aray $post 帖子信息
 * @return array
 */
function viewthread_procpost($post, $lastvisit, $ordertype, $maxposition = 0) {
    global $_G, $rushreply;

    if (!$_G['forum_newpostanchor'] && $post['dateline'] > $lastvisit) {
        $post['newpostanchor'] = '<a name="newpost"></a>';
        $_G['forum_newpostanchor'] = 1;
    } else {
        $post['newpostanchor'] = '';
    }

    $post['lastpostanchor'] = ($ordertype != 1 && $_G['forum_numpost'] == $_G['forum_thread']['replies']) || ($ordertype == 1 && $_G['forum_numpost'] == $_G['forum_thread']['replies'] + 2) ? '<a name="lastpost"></a>' : '';

    //debug 二分分页法
    if (!$post['hotrecommended']) {
        if ($_G['forum_pagebydesc']) {
            if ($ordertype != 1) {
                $post['number'] = $_G['forum_numpost'] + $_G['forum_ppp2']--;
            } else {
                $post['number'] = $post['first'] == 1 ? 1 : ($_G['forum_numpost'] - 1) - $_G['forum_ppp2']--;
            }
        } else {
            if ($ordertype != 1) {
                $post['number'] = ++$_G['forum_numpost'];
            } else {
                $post['number'] = $post['first'] == 1 ? 1 : --$_G['forum_numpost'];
                $post['number'] = $post['number'] - 1;
            }
        }
    }

    if ($post['existinfirstpage']) {
        if ($_G['forum_pagebydesc']) {
            $_G['forum_ppp2']--;
        } else {
            if ($ordertype != 1) {
                ++$_G['forum_numpost'];
            } else {
                --$_G['forum_numpost'];
            }
        }
    }

    if ($maxposition) {
        $post['number'] = $post['position'];
    }

    if ($post['hotrecommended']) {
        $post['number'] = -1;
    }

    if (!$_G['forum_thread']['special'] && !$rushreply && !$hiddenreplies && $_G['setting']['threadfilternum'] && getstatus($post['status'], 11)) {
        $post['isWater'] = true;
        if ($_G['setting']['hidefilteredpost'] && !$_G['forum']['noforumhidewater']) {
            $post['inblacklist'] = true;
        }
    } else {
        $_G['allblocked'] = false;
    }

    if ($post['inblacklist']) {
        $_G['blockedpids'][] = $post['pid'];
    }

    $_G['forum_postcount']++;

    $post['dbdateline'] = $post['dateline'];
    $post['dateline'] = dgmdate($post['dateline'], 'u', '9999', getglobal('setting/dateformat') . ' H:i:s');
    $post['groupid'] = $_G['cache']['usergroups'][$post['groupid']] ? $post['groupid'] : 7;

    if ($post['username']) {

        $_G['forum_onlineauthors'][$post['authorid']] = 0;
        $post['usernameenc'] = rawurlencode($post['username']);
        //note !$special && $post['groupid'] = getgroupid($post['authorid'], $_G['cache']['usergroups'][$post['groupid']], $post);
        $post['readaccess'] = $_G['cache']['usergroups'][$post['groupid']]['readaccess'];
        if ($_G['cache']['usergroups'][$post['groupid']]['userstatusby'] == 1) {
            $post['authortitle'] = $_G['cache']['usergroups'][$post['groupid']]['grouptitle'];
            $post['stars'] = $_G['cache']['usergroups'][$post['groupid']]['stars'];
        }
        $post['upgradecredit'] = false;
        if ($_G['cache']['usergroups'][$post['groupid']]['type'] == 'member' && $_G['cache']['usergroups'][$post['groupid']]['creditslower'] != 999999999) {
            $post['upgradecredit'] = $_G['cache']['usergroups'][$post['groupid']]['creditslower'] - $post['credits'];
            $post['upgradeprogress'] = 100 - ceil($post['upgradecredit'] / ($_G['cache']['usergroups'][$post['groupid']]['creditslower'] - $_G['cache']['usergroups'][$post['groupid']]['creditshigher']) * 100);
            $post['upgradeprogress'] = min(max($post['upgradeprogress'], 2), 100);
        }

        $post['taobaoas'] = addslashes($post['taobao']);
        $post['regdate'] = dgmdate($post['regdate'], 'd');
        $post['lastdate'] = dgmdate($post['lastvisit'], 'd');

        $post['authoras'] = !$post['anonymous'] ? ' ' . addslashes($post['author']) : '';

        if ($post['medals']) {
            loadcache('medals');
            foreach ($post['medals'] = explode("\t", $post['medals']) as $key => $medalid) {
                list($medalid, $medalexpiration) = explode("|", $medalid);
                if (isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
                    $post['medals'][$key] = $_G['cache']['medals'][$medalid];
                    $post['medals'][$key]['medalid'] = $medalid;
                    //note 勋章showmenu时用的
                    $_G['medal_list'][$medalid] = $_G['cache']['medals'][$medalid];
                } else {
                    unset($post['medals'][$key]);
                }
            }
        }

        //$post['avatar'] = avatar($post['authorid']);
        //七牛云取图法
        $avatar = UserUtils::getUserAvatar($post['authorid']);
        $post['avatar'] = "<img src ='" . $avatar . "' /> ";
        $post['groupicon'] = $post['avatar'] ? g_icon($post['groupid'], 1) : '';
        $post['banned'] = $post['status'] & 1;
        $post['warned'] = ($post['status'] & 2) >> 1;

    } else {
        if (!$post['authorid']) {
            $post['useip'] = substr($post['useip'], 0, strrpos($post['useip'], '.')) . '.x';
        }
    }
    //debug 帖子里面的附件
    $post['attachments'] = array();
    $post['imagelist'] = $post['attachlist'] = '';

    if ($post['attachment']) {
        if ((!empty($_G['setting']['guestviewthumb']['flag']) && !$_G['uid']) || $_G['group']['allowgetattach'] || $_G['group']['allowgetimage']) {
            $_G['forum_attachpids'][] = $post['pid'];
            $post['attachment'] = 0;
            if (preg_match_all("/\[attach\](\d+)\[\/attach\]/i", $post['message'], $matchaids)) {
                $_G['forum_attachtags'][$post['pid']] = $matchaids[1];
            }
        } else {
            //插入圖片
            $tableid = DzForumPost::getTableid($_G['tid']);
            $fj = DzForumPost::getFujian($_G['tid'], $tableid['tableid']);
            $root = $_G['siteurl']."data/attachment/forum/";
            foreach($fj as $k => $v) {
                if($v['aid'] && $v['isimage'] == 1) {
                    $post['message'] = preg_replace("/\[attach\]\d+\[\/attach\]/i", "[img]".$root.$v['attachment']."[/img]", $post['message'], 1);
                }
            }
            $post['message'] = preg_replace("/\[attach\](\d+)\[\/attach\]/i", '', $post['message']);
        }
    }

    if ($_G['setting']['ratelogrecord'] && $post['ratetimes']) {
        $_G['forum_cachepid'][$post['pid']] = $post['pid'];
    }
    if ($_G['setting']['commentnumber'] && ($post['first'] && $_G['setting']['commentfirstpost'] || !$post['first']) && $post['comment']) {
        $_G['forum_cachepid'][$post['pid']] = $post['pid'];
    }
    $post['allowcomment'] = $_G['setting']['commentnumber'] && in_array(1, $_G['setting']['allowpostcomment']) && ($_G['setting']['commentpostself'] || $post['authorid'] != $_G['uid']) &&
        ($post['first'] && $_G['setting']['commentfirstpost'] && in_array($_G['group']['allowcommentpost'], array(1, 3)) ||
            (!$post['first'] && in_array($_G['group']['allowcommentpost'], array(2, 3))));
    //$_G['forum']['allowbbcode'] = $_G['forum']['allowbbcode'] ? -$post['groupid'] : 0;
    $forum_allowbbcode = $_G['forum']['allowbbcode'] ? -$post['groupid'] : 0;
    $post['signature'] = $post['usesig'] ? ($_G['setting']['sigviewcond'] ? (strlen($post['message']) > $_G['setting']['sigviewcond'] ? $post['signature'] : '') : $post['signature']) : '';
    $imgcontent = $post['first'] ? getstatus($_G['forum_thread']['status'], 15) : 0;
    if (!defined('IN_ARCHIVER')) {
        //帖子翻页、目录、密码
        if ($post['first']) {
            if (!defined('IN_MOBILE')) {
                $messageindex = false;
                if (strpos($post['message'], '[/index]') !== FALSE) {
                    $post['message'] = preg_replace("/\s?\[index\](.+?)\[\/index\]\s?/ies", "parseindex('\\1', '$post[pid]')", $post['message']);
                    $messageindex = true;
                    unset($_GET['threadindex']);
                }
                if (strpos($post['message'], '[page]') !== FALSE) {
                    if ($_GET['cp'] != 'all') {
                        $postbg = '';
                        if (strpos($post['message'], '[/postbg]') !== FALSE) {
                            preg_match("/\s?\[postbg\]\s*([^\[\<\r\n;'\"\?\(\)]+?)\s*\[\/postbg\]\s?/is", $post['message'], $r);
                            $postbg = $r[0];
                        }
                        $messagearray = explode('[page]', $post['message']);
                        $cp = max(intval($_GET['cp']), 1);
                        $post['message'] = $messagearray[$cp - 1];
                        if ($postbg && strpos($post['message'], '[/postbg]') === FALSE) {
                            $post['message'] = $postbg . $post['message'];
                        }
                        unset($postbg);
                    } else {
                        $cp = 0;
                        $post['message'] = preg_replace("/\s?\[page\]\s?/is", '', $post['message']);
                    }
                    if ($_GET['cp'] != 'all' && strpos($post['message'], '[/index]') === FALSE && empty($_GET['threadindex']) && !$messageindex) {
                        $_G['forum_posthtml']['footer'][$post['pid']] .= '<div id="threadpage"></div><script type="text/javascript" reload="1">show_threadpage(' . $post['pid'] . ', ' . $cp . ', ' . count($messagearray) . ', ' . ($_GET['from'] == 'preview' ? '1' : '0') . ');</script>';
                    }
                }
            }
        }
        if (!empty($_GET['threadindex'])) {
            $_G['forum_posthtml']['header'][$post['pid']] .= '<div id="threadindex"></div><script type="text/javascript" reload="1">show_threadindex(0, ' . ($_GET['from'] == 'preview' ? '1' : '0') . ');</script>';
        }
        if (!$imgcontent) {
            $post['message'] = discuzcode($post['message'], $post['smileyoff'], $post['bbcodeoff'], $post['htmlon'] & 1, $_G['forum']['allowsmilies'], $forum_allowbbcode, ($_G['forum']['allowimgcode'] && $_G['setting']['showimages'] ? 1 : 0), $_G['forum']['allowhtml'], ($_G['forum']['jammer'] && $post['authorid'] != $_G['uid'] ? 1 : 0), 0, $post['authorid'], $_G['cache']['usergroups'][$post['groupid']]['allowmediacode'] && $_G['forum']['allowmediacode'], $post['pid'], $_G['setting']['lazyload'], $post['dbdateline'], $post['first']);
            if ($post['first']) {
                $_G['relatedlinks'] = '';
                $relatedtype = !$_G['forum_thread']['isgroup'] ? 'forum' : 'group';
                if (!$_G['setting']['relatedlinkstatus']) {
                    $_G['relatedlinks'] = get_related_link($relatedtype);
                } else {
                    $post['message'] = parse_related_link($post['message'], $relatedtype);
                }
                if (strpos($post['message'], '[/begin]') !== FALSE) {
                    $post['message'] = preg_replace("/\[begin(=\s*([^\[\<\r\n]*?)\s*,(\d*),(\d*),(\d*),(\d*))?\]\s*([^\[\<\r\n]+?)\s*\[\/begin\]/ies", $_G['cache']['usergroups'][$post['groupid']]['allowbegincode'] ? "parsebegin('\\2', '\\7', '\\3', '\\4', '\\5', '\\6');" : '', $post['message']);
                }
            }
        }
    }
    //帖子翻页、目录
    if (defined('IN_ARCHIVER') || defined('IN_MOBILE') || !$post['first']) {
        if (strpos($post['message'], '[page]') !== FALSE) {
            $post['message'] = preg_replace("/\s?\[page\]\s?/is", '', $post['message']);
        }
        if (strpos($post['message'], '[/index]') !== FALSE) {
            $post['message'] = preg_replace("/\s?\[index\](.+?)\[\/index\]\s?/is", '', $post['message']);
        }
        if (strpos($post['message'], '[/begin]') !== FALSE) {
            $post['message'] = preg_replace("/\[begin(=\s*([^\[\<\r\n]*?)\s*,(\d*),(\d*),(\d*),(\d*))?\]\s*([^\[\<\r\n]+?)\s*\[\/begin\]/ies", '', $post['message']);
        }
    }
    if ($imgcontent) {
        $post['message'] = '<img id="threadimgcontent" src="./' . stringtopic('', $post['tid']) . '">';
    }
    $_G['forum_firstpid'] = intval($_G['forum_firstpid']);
    $post['numbercard'] = viewthread_numbercard($post);
    $post['mobiletype'] = getstatus($post['status'], 4) ? base_convert(getstatus($post['status'], 10) . getstatus($post['status'], 9) . getstatus($post['status'], 8), 2, 10) : 0;
    return $post;
}

function viewthread_loadcache() {
    global $_G;
    $_G['forum']['livedays'] = ceil((TIMESTAMP - $_G['forum']['dateline']) / 86400);
    $_G['forum']['lastpostdays'] = ceil((TIMESTAMP - $_G['forum']['lastthreadpost']) / 86400);
    //debug 置顶:0-45,  精华:0-30  查看数&帖子存在时间:0-50 回复数&最后回复天数:15天以内的回复
    $threadcachemark = 100 - (
            $_G['forum']['displayorder'] * 15 +
            $_G['thread']['digest'] * 10 +
            min($_G['thread']['views'] / max($_G['forum']['livedays'], 10) * 2, 50) +
            max(-10, (15 - $_G['forum']['lastpostdays'])) +
            min($_G['thread']['replies'] / $_G['setting']['postperpage'] * 1.5, 15));
    //debug 如果满足缓存系数
    if ($threadcachemark < $_G['forum']['threadcaches']) {

        //debug 检查缓存文件，如果不存在相应目录则创建目录。
        $threadcache = getcacheinfo($_G['tid']);

        //debug 如果过期或者不存在，则定义缓存文件。output() 将其缓存到 CACHE_FILE 。
        if (TIMESTAMP - $threadcache['filemtime'] > $_G['setting']['cachethreadlife']) {
            @unlink($threadcache['filename']);
            define('CACHE_FILE', $threadcache['filename']);
        } else {
            readfile($threadcache['filename']);

            //debug 更新点击数
            viewthread_updateviews($_G['forum_thread']['threadtableid']);
            $_G['setting']['debug'] && debuginfo();
            $_G['setting']['debug'] ? die('<script type="text/javascript">document.getElementById("debuginfo").innerHTML = " ' . ($_G['setting']['debug'] ? 'Updated at ' . gmdate("H:i:s", $threadcache['filemtime'] + 3600 * 8) . ', Processed in ' . $debuginfo['time'] . ' second(s), ' . $debuginfo['queries'] . ' Queries' . ($_G['gzipcompress'] ? ', Gzip enabled' : '') : '') . '";</script>') : die();
        }
    }
}

/**
 * 获取主题的被管理信息或被使用道具信息
 * @global <array> $_G
 * @param array $thread 主题数据
 * @return array
 */
function viewthread_lastmod(&$thread) {
    global $_G;
    if (!$thread['moderated']) {
        return array();
    }
    $lastmod = array();
// 	$lastmod = DB::fetch_first("SELECT uid AS moduid, modusername AS modusername, dateline AS moddateline, action AS modaction, magicid, stamp, reason
// 		FROM ".DB::table('forum_threadmod')."
// 		WHERE tid='$thread[tid]' ORDER BY dateline DESC LIMIT 1");
    $lastlog = C::t('forum_threadmod')->fetch_by_tid($thread['tid']);
    if ($lastlog) {
        $lastmod = array(
            'moduid' => $lastlog['uid'],
            'modusername' => $lastlog['username'],
            'moddateline' => $lastlog['dateline'],
            'modaction' => $lastlog['action'],
            'magicid' => $lastlog['magicid'],
            'stamp' => $lastlog['stamp'],
            'reason' => $lastlog['reason']
        );
    }
    if ($lastmod) {
        $modactioncode = lang('forum/modaction');
        $lastmod['modusername'] = $lastmod['modusername'] ? $lastmod['modusername'] : 'System';
        $lastmod['moddateline'] = dgmdate($lastmod['moddateline'], 'u');
        $lastmod['modactiontype'] = $lastmod['modaction'];
        if ($modactioncode[$lastmod['modaction']]) {
            $lastmod['modaction'] = $modactioncode[$lastmod['modaction']] . ($lastmod['modaction'] != 'SPA' ? '' : ' ' . $_G['cache']['stamps'][$lastmod['stamp']]['text']);
        } elseif (substr($lastmod['modaction'], 0, 1) == 'L' && preg_match('/L(\d\d)/', $lastmod['modaction'], $a)) {
            $lastmod['modaction'] = $modactioncode['SLA'] . ' ' . $_G['cache']['stamps'][intval($a[1])]['text'];
        } else {
            $lastmod['modaction'] = '';
        }
        if ($lastmod['magicid']) {
            loadcache('magics');
            $lastmod['magicname'] = $_G['cache']['magics'][$lastmod['magicid']]['name'];
        }
    } else {
        //DB::query("UPDATE ".DB::table($thread['threadtable'])." SET moderated='0' WHERE tid='$thread[tid]'", 'UNBUFFERED');
        C::t('forum_thread')->update($thread['tid'], array('moderated' => 0), false, false, $thread['threadtableid']);
        $thread['moderated'] = 0;
    }
    return $lastmod;
}

function viewthread_baseinfo($post, $extra) {
    global $_G;
    list($key, $type) = $extra;
    $v = '';
    if (substr($key, 0, 10) == 'extcredits') {
        $i = substr($key, 10);
        $extcredit = $_G['setting']['extcredits'][$i];
        if ($extcredit) {
            $v = $type ? ($extcredit['img'] ? $extcredit['img'] . ' ' : '') . $extcredit['title'] : $post['extcredits' . $i] . ' ' . $extcredit['unit'];
        }
    } elseif (substr($key, 0, 6) == 'field_') {
        $field = substr($key, 6);
        if (!empty($post['privacy']['profile'][$field])) {
            return '';//note return lang('space', 'viewthread_userinfo_privacy');
        }
        require_once libfile('function/profile');
        if ($field != 'qq') {
            $v = profile_show($field, $post);
        } elseif (!empty($post['qq'])) {
            $v = '<a href="http://wpa.qq.com/msgrd?V=3&Uin=' . $post['qq'] . '&Site=' . $_G['setting']['bbname'] . '&Menu=yes&from=discuz" target="_blank" title="' . lang('spacecp', 'qq_dialog') . '"><img src="' . STATICURL . '/image/common/qq_big.gif" alt="QQ" style="margin:0px;"/></a>';
        }
        if ($v) {
            if (!isset($_G['cache']['profilesetting'])) {
                loadcache('profilesetting');
            }
            $v = $type ? $_G['cache']['profilesetting'][$field]['title'] : $v;
        }
    } elseif ($key == 'eccredit_seller') {
        $v = $type ? lang('space', 'viewthread_userinfo_sellercredit') : '<a href="home.php?mod=space&uid=' . $post['uid'] . '&do=trade&view=eccredit#buyercredit" target="_blank" class="vm"><img src="' . STATICURL . 'image/traderank/seller/' . countlevel($post['buyercredit']) . '.gif" /></a>';
    } elseif ($key == 'eccredit_buyer') {
        $v = $type ? lang('space', 'viewthread_userinfo_buyercredit') : '<a href="home.php?mod=space&uid=' . $post['uid'] . '&do=trade&view=eccredit#sellercredit" target="_blank" class="vm"><img src="' . STATICURL . 'image/traderank/seller/' . countlevel($post['sellercredit']) . '.gif" /></a>';
    } else {
        $v = getLinkByKey($key, $post);
        if ($v !== '') {
            $v = $type ? lang('space', 'viewthread_userinfo_' . $key) : $v;
        }
    }
    return $v;
}

function viewthread_profile_nodeparse($param) {
    list($name, $s, $e, $extra, $post) = $param;
    if (strpos($name, ':') === false) {
        if (function_exists('profile_node_' . $name)) {
            return call_user_func('profile_node_' . $name, $post, $s, $e, explode(',', $extra));
        } else {
            return '';
        }
    } else {
        list($plugin, $pluginid) = explode(':', $name);
        if ($plugin == 'plugin') {
            global $_G;
            static $pluginclasses;
            if (isset($_G['setting']['plugins']['profile_node'][$pluginid])) {
                @include_once DISCUZ_ROOT . './source/plugin/' . $_G['setting']['plugins']['profile_node'][$pluginid] . '.class.php';
                $classkey = 'plugin_' . $pluginid;
                if (!class_exists($classkey, false)) {
                    return '';
                }
                if (!isset($pluginclasses[$classkey])) {
                    $pluginclasses[$classkey] = new $classkey;
                }
                return call_user_func(array($pluginclasses[$classkey], 'profile_node'), $post, $s, $e, explode(',', $extra));
            }
        }
    }
}

function viewthread_profile_node($type, $post) {
    global $_G;
    $tpid = false;
    if ($post['verifyicon']) {
        $tpid = isset($_G['setting']['profilenode']['groupid'][-$post['verifyicon'][0]]) ? $_G['setting']['profilenode']['groupid'][-$post['verifyicon'][0]] : false;
    }
    if ($tpid === false) {
        $tpid = isset($_G['setting']['profilenode']['groupid'][$post['groupid']]) ? $_G['setting']['profilenode']['groupid'][$post['groupid']] : 0;
    }
    $template = $_G['setting']['profilenode']['template'][$tpid][$type];
    $code = $_G['setting']['profilenode']['code'][$tpid][$type];
    include_once template('forum/viewthread_profile_node');
    foreach ($code as $k => $p) {
        $p[] = $post;
        $template = str_replace($k, call_user_func('viewthread_profile_nodeparse', $p), $template);
    }
    echo $template;
}

function viewthread_numbercard($post) {
    global $_G;
    if (!is_array($_G['setting']['numbercard'])) {
        $_G['setting']['numbercard'] = dunserialize($_G['setting']['numbercard']);
    }

    $numbercard = array();
    foreach ($_G['setting']['numbercard']['row'] as $key) {
        if (substr($key, 0, 10) == 'extcredits') {
            $numbercard[] = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=profile', 'value' => $post[$key], 'lang' => $_G['setting']['extcredits'][substr($key, 10)]['title']);
        } else {
            $getLink = getLinkByKey($key, $post, 1);
            $numbercard[] = array('link' => $getLink['link'], 'value' => $getLink['value'], 'lang' => lang('space', 'viewthread_userinfo_' . $key));
        }
    }
    return $numbercard;
}

function getLinkByKey($key, $post, $returnarray = 0) {
    switch ($key) {
        case 'uid':
            $v = array('link' => '?' . $post['uid'], 'value' => $post['uid']);
            break;
        case 'posts':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=reply&view=me&from=space', 'value' => $post['posts']);
            break;
        case 'threads':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=thread&view=me&from=space', 'value' => $post['threads']);
            break;
        case 'digestposts':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=thread&view=me&from=space', 'value' => $post['digestposts']);
            break;
        case 'feeds':
            $v = array('link' => 'home.php?mod=follow&uid=' . $post['uid'] . '&do=view', 'value' => $post['feeds']);
            break;
        case 'doings':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=doing&view=me&from=space', 'value' => $post['doings']);
            break;
        case 'blogs':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=blog&view=me&from=space', 'value' => $post['blogs']);
            break;
        case 'albums':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=album&view=me&from=space', 'value' => $post['albums']);
            break;
        case 'sharings':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=share&view=me&from=space', 'value' => $post['sharings']);
            break;
        case 'friends':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=friend&view=me&from=space', 'value' => $post['friends']);
            break;
        case 'follower':
            $v = array('link' => 'home.php?mod=follow&do=follower&uid=' . $post['uid'], 'value' => $post['follower']);
            break;
        case 'following':
            $v = array('link' => 'home.php?mod=follow&do=following&uid=' . $post['uid'], 'value' => $post['following']);
            break;
        case 'credits':
            $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=profile', 'value' => $post['credits']);
            break;
        case 'digest':
            $v = array('value' => $post['digestposts']);
            break;
        case 'readperm':
            $v = array('value' => $post['readaccess']);
            break;
        case 'regtime':
            $v = array('value' => $post['regdate']);
            break;
        case 'lastdate':
            $v = array('value' => $post['lastdate']);
            break;
        case 'oltime':
            $v = array('value' => $post['oltime'] . ' ' . lang('space', 'viewthread_userinfo_hour'));
            break;
    }
    if (!$returnarray) {
        if ($v['link']) {
            $v = '<a href="' . $v['link'] . '" target="_blank" class="xi2">' . $v['value'] . '</a>';
        } else {
            $v = $v['value'];
        }
    }
    return $v;
}

function countlevel($usercredit) {
    global $_G;

    $rank = 0;
    if ($usercredit) {
        foreach ($_G['setting']['ec_credit']['rank'] AS $level => $credit) {
            if ($usercredit <= $credit) {
                $rank = $level;
                break;
            }
        }
    }
    return $rank;
}

function remaintime($time) {
    $days = intval($time / 86400);
    $time -= $days * 86400;
    $hours = intval($time / 3600);
    $time -= $hours * 3600;
    $minutes = intval($time / 60);
    $time -= $minutes * 60;
    $seconds = $time;
    return array((int)$days, (int)$hours, (int)$minutes, (int)$seconds);
}

function getrelateitem($tagarray, $tid, $relatenum, $relatetime, $relatecache = '', $type = 'tid') {
    $tagidarray = $relatearray = $relateitem = array();
    $updatecache = 0;
    $limit = $relatenum;
    if (!$limit) {
        return '';
    }
    foreach ($tagarray as $var) {
        $tagidarray[] = $var['0'];
    }
    if (!$tagidarray) {
        return '';
    }
    if (empty($relatecache)) {
        $thread = C::t('forum_thread')->fetch($tid);
        $relatecache = $thread['relatebytag'];
//		$relatecache = DB::result_first("SELECT relatebytag FROM ".DB::table('forum_thread')." WHERE tid='$tid'");
    }
    if ($relatecache) {
        $relatecache = explode("\t", $relatecache);
        if (TIMESTAMP > $relatecache[0] + $relatetime * 60) {
            $updatecache = 1;
        } else {
            if (!empty($relatecache[1])) {
                $relatearray = explode(',', $relatecache[1]);
            }
        }
    } else {
        $updatecache = 1;
    }
    if ($updatecache) {
        //$query = DB::query("SELECT itemid FROM ".DB::table('common_tagitem')." WHERE tagid IN (".dimplode($tagidarray).") AND itemid<>'$tid' AND idtype='$type' LIMIT $limit");
        $query = C::t('common_tagitem')->select($tagidarray, $tid, $type, 'itemid', 'DESC', $limit, 0, '<>');
        //while($result = DB::fetch($query)) {
        foreach ($query as $result) {
            if ($result['itemid']) {
                $relatearray[] = $result['itemid'];
            }
        }
        if ($relatearray) {
            $relatebytag = implode(',', $relatearray);
        }
//		DB::query("UPDATE ".DB::table('forum_thread')." SET relatebytag='".TIMESTAMP."\t".$relatebytag."' WHERE tid='$tid'");
        C::t('forum_thread')->update($tid, array('relatebytag' => TIMESTAMP . "\t" . $relatebytag));
    }


    if (!empty($relatearray)) {
        rsort($relatearray);
//		$query = DB::query("SELECT tid,subject,displayorder FROM ".DB::table('forum_thread')." WHERE tid IN (".dimplode($relatearray).")");
//		while($result = DB::fetch($query)) {
        foreach (C::t('forum_thread')->fetch_all_by_tid($relatearray) as $result) {
            if ($result['displayorder'] >= 0) {
                $relateitem[] = $result;
            }
        }
    }
    return $relateitem;
}

/*
 * 抢楼规则正则制作
 */
function rushreply_rule() {
    global $rushresult;
    if (!empty($rushresult['rewardfloor'])) {
        $rushresult['rewardfloor'] = preg_replace('/\*+/', '*', $rushresult['rewardfloor']);
        $rewardfloorarr = explode(',', $rushresult['rewardfloor']);
        if ($rewardfloorarr) {
            foreach ($rewardfloorarr as $var) {
                $var = trim($var);
                if (strlen($var) > 1) {
                    $var = str_replace('*', '[^,]?[\d]*', $var);
                } else {
                    $var = str_replace('*', '\d+', $var);
                }
                $preg[] = "(,$var,)";
            }
            $preg_str = "/" . implode('|', $preg) . "/";
        }
    }
    return $preg_str;
}

/*
 *
 */
function checkrushreply($post) {
    global $_G, $rushids;
    if ($_GET['authorid']) {
        return $post;
    }
    if (in_array($post['number'], $rushids)) {
        $post['rewardfloor'] = 1;
    }
    return $post;
}

function parseindex($nodes, $pid) {
    global $_G;
    $nodes = dhtmlspecialchars($nodes);
    $nodes = preg_replace('/(\**?)\[#(\d+)\](.+?)[\r\n]/', "<a page=\"\\2\" sub=\"\\1\">\\3</a>", $nodes);
    $nodes = preg_replace('/(\**?)\[#(\d+),(\d+)\](.+?)[\r\n]/', "<a tid=\"\\2\" pid=\"\\3\" sub=\"\\1\">\\4</a>", $nodes);
    $_G['forum_posthtml']['header'][$pid] .= '<div id="threadindex">' . $nodes . '</div><script type="text/javascript" reload="1">show_threadindex(' . $pid . ', ' . ($_GET['from'] == 'preview' ? '1' : '0') . ')</script>';
    return '';
}

function parsebegin($linkaddr, $imgflashurl, $w = 0, $h = 0, $type = 0, $s = 0) {
    static $begincontent;
    if ($begincontent || $_GET['from'] == 'preview') {
        return '';
    }
    preg_match("/((https?){1}:\/\/|www\.)[^\[\"']+/i", $imgflashurl, $matches);
    $imgflashurl = $matches[0];
    $fileext = fileext($imgflashurl);
    $randomid = 'swf_' . random(3);
    $w = ($w >= 400 && $w <= 1024) ? $w : 900;
    $h = ($h >= 300 && $h <= 640) ? $h : 500;
    $s = $s ? $s * 1000 : 5000;
    switch ($fileext) {
        case 'jpg':
        case 'jpeg':
        case 'gif':
        case 'png':
            $content = '<img style="position:absolute;width:' . $w . 'px;height:' . $h . 'px;" src="' . $imgflashurl . '" />';
            break;
        case 'flv':
            $content = '<span id="' . $randomid . '" style="position:absolute;"></span>' .
                '<script type="text/javascript" reload="1">$(\'' . $randomid . '\').innerHTML=' .
                'AC_FL_RunContent(\'width\', \'' . $w . '\', \'height\', \'' . $h . '\', ' .
                '\'allowNetworking\', \'internal\', \'allowScriptAccess\', \'never\', ' .
                '\'src\', \'' . STATICURL . 'image/common/flvplayer.swf\', ' .
                '\'flashvars\', \'file=' . rawurlencode($imgflashurl) . '\', \'quality\', \'high\', ' .
                '\'wmode\', \'transparent\', \'allowfullscreen\', \'true\');</script>';
            break;
        case 'swf':
            $content = '<span id="' . $randomid . '" style="position:absolute;"></span>' .
                '<script type="text/javascript" reload="1">$(\'' . $randomid . '\').innerHTML=' .
                'AC_FL_RunContent(\'width\', \'' . $w . '\', \'height\', \'' . $h . '\', ' .
                '\'allowNetworking\', \'internal\', \'allowScriptAccess\', \'never\', ' .
                '\'src\', encodeURI(\'' . $imgflashurl . '\'), \'quality\', \'high\', \'bgcolor\', \'#ffffff\', ' .
                '\'wmode\', \'transparent\', \'allowfullscreen\', \'true\');</script>';
            break;
        default:
            $content = '';
    }
    if ($content) {
        if ($type == 1) {
            $content = '<div id="threadbeginid" style="display:none;">' .
                '<div class="flb beginidin"><span><div id="begincloseid" class="flbc" title="' . lang('core', 'close') . '">' . lang('core', 'close') . '</div></span></div>' .
                $content . '<div class="beginidimg" style=" width:' . $w . 'px;height:' . $h . 'px;">' .
                '<a href="' . $linkaddr . '" target="_blank" style="display: block; width:' . $w . 'px; height:' . $h . 'px;"></a></div></div>' .
                '<script type="text/javascript">threadbegindisplay(1, ' . $w . ', ' . $h . ', ' . $s . ');</script>';
        } else {
            $content = '<div id="threadbeginid">' .
                '<div class="flb beginidin">
					<span><div id="begincloseid" class="flbc" title="' . lang('core', 'close') . '">' . lang('core', 'close') . '</div></span>
				</div>' .
                $content . '<div class="beginidimg" style=" width:' . $w . 'px; height:' . $h . 'px;">' .
                '<a href="' . $linkaddr . '" target="_blank" style="display: block; width:' . $w . 'px; height:' . $h . 'px;"></a></div>
				</div>' .
                '<script type="text/javascript">threadbegindisplay(' . $type . ', ' . $w . ', ' . $h . ', ' . $s . ');</script>';
        }
    }
    $begincontent = $content;
    return $content;
}

/**
 * 检查群组的访问权限
 * @global array $_G
 */
function _checkviewgroup() {
    global $_G;
    //note 当前板块是群组
    $_G['action']['action'] = 3;
    require_once libfile('function/group');
    $status = groupperm($_G['forum'], $_G['uid']);
    if ($status == 1) {
        showmessage('forum_group_status_off');
    } elseif ($status == 2) {
        showmessage('forum_group_noallowed', 'forum.php?mod=group&fid=' . $_G['fid']);
    } elseif ($status == 3) {
        showmessage('forum_group_moderated', 'forum.php?mod=group&fid=' . $_G['fid']);
    }
}

