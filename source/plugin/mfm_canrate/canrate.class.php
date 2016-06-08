<?php
defined('IN_DISCUZ') || exit('Access Denied');
/*
 *开启插件 
 *半分钟设置
 *简单易用
 *mfm
 */

class plugin_mfm_canrate {
}

class plugin_mfm_canrate_forum extends plugin_mfm_canrate{
	function viewthread_rate(){
		global $_G;
		$vars = $_G['cache']['plugin']['mfm_canrate'];
		if($vars['enable']) {
			$fids = unserialize($vars['select_forum_name']);
			if(!in_array($_G['fid'], $fids)) {
				$_G['group']['raterange'] = 0;
			}
		}
	}
 }

?>