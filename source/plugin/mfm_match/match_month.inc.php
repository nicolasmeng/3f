<?php


if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

$match_month = DB::fetch_all("SELECT * FROM %t ORDER BY now DESC",array('3f7s_match_forumtype'));

if(!submitcheck('submit')){
	showformheader('plugins&operation=config&do='.$pluginid.'&identifier=mfm_match&pmod=match_month', 'submit');
	showtableheader();
	showsetting('版块id','forum_id','','number');
	showsetting('now赋值', array('now', array(
			array(0, '已经结束(0)'),
			array(1, '停止报名(1)'),
			array(2, '正在进行(2)'),
     		array(3, '起止体重(3)'),
		    array(4, '日常体重(4)')
		)), '2', 'mradio');
	showsetting('删除','radio','0','radio');
	showsubmit('submit','提交');
	$now_1 = $now_2 = $now_3 = $now_4 = 0;
	echo '<tr class="header"><th>版块名</th><th>版块id</th><th>now</th></tr>';
	foreach($match_month as $month) {
		$month['fname'] = DB::result_first("SELECT name FROM %t where fid=%d",array('forum_forum',$month['fid']));
        echo '<tr><td>'.$month['fname'].'</td><td>'.$month['fid'].'</td><td>'.$month['now'].'</td></tr>';
		if($month['now']==1){$now_1++;};
		if($month['now']==2){$now_2++;};
		if($month['now']==3){$now_3++;};
		if($month['now']==4){$now_4++;};
	}
	if($now_1!=1){echo '<font size="5" color="red">now(1)数据异常!!</font>';}
	if($now_2!=1){echo '<font size="5" color="red">now(2)数据异常!!</font>';}
	if($now_3!=1){echo '<font size="5" color="red">now(3)数据异常!!</font>';}
	if($now_4!=1){echo '<font size="5" color="red">now(4)数据异常!!</font>';}
	showtablefooter();
	showformfooter();
}else{
	if($_GET['radio']&&$_GET['forum_id']){
		DB::delete('3f7s_match_forumtype','fid='.$_GET['forum_id']);
	}
	else{
		DB::insert('3f7s_match_forumtype',array(
			'fid'=>$_GET['forum_id'],
			'now'=>$_GET['now'],
			),false,true);
	}
	cpmsg('setting_update_succeed', '', 'succeed');
	//debug($_GET);
}
?>