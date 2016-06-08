<?php

/**
 * 微信绑定model类
 *
 * @author HanPengyu
 * @copyright 2012-2015 Appbyme
 */

if (!defined('IN_DISCUZ') || !defined('IN_APPBYME')) {
    exit('Access Denied');
}

class AppbymeSendsms extends DiscuzAR {

    public static function model($className = __CLASS__) {
        return parent::model($className);
    }

    public function tableName() {
        return '{{appbyme_sendsms}}';
    }

    public function rules() {
        return array();
    }

    public static function getTableName() {
        if (WebUtils::getDzPluginAppbymeAppConfig('mobcent_sms_table')) {
            $table = 'common_member_profile';
        } else {
            $table = 'appbyme_sendsms';
        }
        return $table;
    }

    // 根据手机号获取uid
    public static function getMobileUid($mobile) {
        return DbUtils::getDzDbUtils(true)->queryRow('
            SELECT *
            FROM %t
            WHERE mobile=%s
            AND uid > 0
            ',
            array(self::getTableName(), $mobile)
        );

    }

    public static function checkMobile($mobile) {
        return (int)DbUtils::getDzDbUtils(true)->queryRow('
            SELECT *
            FROM %t
            WHERE mobile=%s
            AND uid > 0
            ',
            array(self::getTableName(), $mobile)
        );

    }

    // 插入手机号和验证码时候进行验证
    public static function getMobileUidInfo($mobile) {
        return DbUtils::getDzDbUtils(true)->queryRow('
            SELECT *
            FROM %t
            WHERE mobile=%s
            AND uid=0
            ',
            array('appbyme_sendsms', $mobile)
        );
    }

    public static function getBindByMobileCode($mobile, $code) {
        return DbUtils::getDzDbUtils(true)->queryRow('
            SELECT *
            FROM %t
            WHERE mobile=%s
            AND code=%s
            ',
            array('appbyme_sendsms', $mobile, $code)
        );
    }

    public static function getBindInfoByUid($uid) {
        return DbUtils::getDzDbUtils(true)->queryRow('
            SELECT *
            FROM %t
            WHERE uid=%d
            ',
            array(self::getTableName(), $uid)
        );
    }

    public static function getPhoneByUid($uid) {
        return DbUtils::getDzDbUtils(true)->queryRow('
            SELECT mobile
            FROM %t
            WHERE uid=%d
            ',
            array(self::getTableName(), $uid)
        );
    }

    public static function insertMobile($data) {
        return DbUtils::getDzDbUtils(true)->insert('appbyme_sendsms', $data);
    }

    public static function updateMobile($mobile, $data) {
        $result = DbUtils::getDzDbUtils(true)->update('appbyme_sendsms', $data, array('mobile' => $mobile));
        if ($data['uid']) {
            $result = DbUtils::getDzDbUtils(true)->update('common_member_profile', array('mobile' => $mobile), $data);
        }
        return $result;
    }
}

?>