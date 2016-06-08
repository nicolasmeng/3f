<?php
/**
 * Created by PhpStorm.
 * User: guanghua
 * Date: 2016/5/5
 * Time: 15:12
 */
class TopicController extends ApiController{
    public function actionlist()
    {
        $where = '';
        if(isset($this->data['search']) && $this->data['search']){
            $where = ' AND `ti_title` LIKE \'%'.$this->data['search'].'%\'';
        }
        $data = $this->db->queryAll('SELECT `ti_id`,`ti_title` FROM %t WHERE `ti_starttime`<%d AND `ti_endtime`>%d %i ORDER BY `ti_id` DESC LIMIT 0,30',array('appbyme_topic_items',time(),time(),$where));
        $this->setData(array('list'=>$data));
    }
}