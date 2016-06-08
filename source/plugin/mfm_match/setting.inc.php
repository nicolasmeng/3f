<?php


if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

$match['robot'] = DB::fetch_first("SELECT * FROM ".DB::table('3f7s_robot'));
if(!submitcheck('submit')){
	showformheader('plugins&operation=config&do='.$pluginid.'&identifier=mfm_match&pmod=setting', 'submit');
	showtableheader();
	showsetting('机器人ID','robot_id',$match['robot']['uid'],'number');
	showsetting('机器人名字','robot_name',$match['robot']['username'],'text',TRUE);
	showtablefooter();
	showsubmit('submit','提交');
	showformfooter();
}else{
	if($_GET['robot_id']!=$match['robot']['uid'] && $_GET['robot_id']!=0){
		$parameter2 = array('common_member',$_GET['robot_id']);
        $match_new['robot'] = DB::fetch_first("SELECT uid,username FROM %t WHERE uid=%d", $parameter2);
		$data1 = array('uid' => $match_new['robot']['uid'],'username'=> $match_new['robot']['username']);
		$data2 = array('uid' => $match['robot']['uid']);
		DB::update('3f7s_robot', $data1, $data2);
	}
	cpmsg('setting_update_succeed', '', 'succeed');
	//debug($_GET);
	//debug($_GET['robot_id']);
}

?>
