<?php


if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}
$match_percent = DB::fetch_all("SELECT * FROM %t ",array('3f7s_match_percent'));

if(!submitcheck('submit')){
	showformheader('plugins&operation=config&do='.$pluginid.'&identifier=mfm_match&pmod=match_percent', 'submit');
	showtableheader();
	showsetting('名称(0-基本，1-众筹，2-众筹给，3-减重)','nameno','','text');
	showsetting('序号','no','','number');
	showsetting('条件(填入*100以后的值)','condition','','number');
	showsetting('比率(填入*100以后的值)','percent','','number');
	showsetting('删除（需填写名称和序号）','radio','0','radio');
	showsubmit('submit','提交');
	echo '<tr class="header"><th>名称</th><th>序号</th><th>条件</th><th>比率</th></tr>';
	foreach($match_percent as $percent) {
		if($percent['nameno'] == 0){$nameno = $percent['nameno'].'-基本奖金比率';$condition = $percent['condition'].'元';}
        if($percent['nameno'] == 1){$nameno = $percent['nameno'].'-众筹金额';$condition = '累计'.$percent['condition'].'元';}
		if($percent['nameno'] == 2){$nameno = $percent['nameno'].'-众筹给他人';$condition = $percent['condition'].'元';}
		if($percent['nameno'] == 3){$nameno = $percent['nameno'].'-减重';$condition = $percent['condition']*100;$condition = $condition.'%';}
		$p = $percent['percent']*100;
        echo '<tr><td>'.$nameno.'</td><td>'.$percent['no'].'</td><td>'.$condition.'</td><td>'.$p.'%</td></tr>';

	}
	showtablefooter();
	showformfooter();
}else{
	if($_GET['radio']&&$_GET['nameno']&&$_GET['no']){
		DB::delete('3f7s_match_percent','nameno='.$_GET['nameno'].' AND no='.$_GET['no']);
	}
	else{
		DB::insert('3f7s_match_percent',array(
			'nameno'=>$_GET['nameno'],
			'no'=>$_GET['no'],
			'condition'=>$_GET['condition']/100,
			'percent'=>$_GET['percent']/100
			),false,true);
	}
	cpmsg('setting_update_succeed', '', 'succeed');
	//debug($_GET);
}
?>