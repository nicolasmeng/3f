<?php

/**
 * 初始化 App UI接口
 *
 * @author 谢建平 <jianping_xie@aliyun.com>
 * @author NaiXiaoXin
 * @copyright 2012-2015 Appbyme
 */
if (!defined('IN_DISCUZ') || !defined('IN_APPBYME')) {
    exit('Access Denied');
}

class InitUIAction extends MobcentAction {

    public function run($custom = 0, $configId = 0) {
        $res = $this->initWebApiArray();
        $id = (int)$configId;
        $res['body'] = $this->_getUIconfig($custom, $id);
        $res['head']['errInfo'] = '';
        WebUtils::outputWebApi($res);
    }

    private function _getUIconfig($custom, $id,$version=1) {
        $moduleList = array();
        $uidiyModle = new AppbymeUiDiy($version,$id);
        $uidiyModle->getUiDiyVersion();
        $result = $uidiyModle->getInfo(false);
        $temp = WebUtils::tarr($result['modules']);
        $nav = WebUtils::tarr($result['navInfo']);
        foreach ($temp as $module) {
            if (!$custom && $module['type'] == AppbymeUIDiyModel::MODULE_TYPE_CUSTOM) {
                $module['componentList'] = array();
            }
            $moduleList[] = AppUtils::filterModule($module);
        }

        return array(
            'navigation' => $nav,
            'moduleList' => $moduleList,
        );
    }

}
