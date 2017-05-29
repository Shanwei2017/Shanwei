<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 17-4-25
 * Time: 下午9:28
 */
namespace app\admin\model;

use think\Model;
use think\Db;

class OrderModel extends Model
{

    public $status_waiting_pay = 1;
    public $status_waiting_refund = -200;
    public $status_pay_suc = 2;
    public $status_refund_suc = -300;
    public $status_waiting_checkup_refund = -110;
    public $status_checkup_suc_refund = -120;
    public $status_checkup_fail_refund = -130;

    /**
     * 获取外卖订单列表
     */
    public function getTakeoutlist($startime, $endtime, $shopname = '', $page = 1, $pagesize = 20)
    {
        $where = array(
            'a.f_type' => 1,
            'a.f_addtime' => array('between', [$startime.' 00:00:00', $endtime.' 59:59:59'])
        );
        if(!empty($shopname)){
            $where['b.f_shopname'] = $shopname;
        }
        $allnum = Db::table('t_orders')->alias('a')->join('t_dineshop b','a.f_shopid = b.f_sid','left')->where($where)->count();
        $orderlist = Db::table('t_orders')
            ->alias('a')
            ->field('a.f_oid orderid,a.f_shopid shopid,b.f_shopname shopname,a.f_userid userid,a.f_type ordertype,a.f_status status,a.f_orderdetail orderdetail,a.f_ordermoney ordermoney,a.f_deliverymoney deliverymoney,a.f_allmoney allmoney,a.f_paymoney paymoney,a.f_paytype paytype,d.f_name recipientname,d.f_mobile recipientmobile,c.f_username deliveryname,c.f_mobile deliverymobie,a.f_deliverytime deliverytime,CONCAT(d.f_province,d.f_city,d.f_address) deliveryaddress,a.f_addtime addtime')
            ->join('t_dineshop b','a.f_shopid = b.f_sid','left')
            ->join('t_dineshop_distripersion c','a.f_deliveryid = c.f_id','left')
            ->join('t_user_address_info d','a.f_addressid = d.f_id','left')
            ->where($where)
            ->order('a.f_addtime desc')
            ->page($page, $pagesize)
            ->select();
        return array(
            "allnum" => $allnum,
            "orderlist" => $orderlist
        );
    }
    /**
     * 获取食堂订单列表
     */
    public function getEatinlist($startime, $endtime, $shopname = '', $page = 1, $pagesize = 20)
    {
        $where = array(
            'a.f_type' => 2,
            'a.f_addtime' => array('between', [$startime.' 00:00:00', $endtime.' 59:59:59'])
        );
        if(!empty($shopname)){
            $where['b.f_shopname'] = $shopname;
        }
        $allnum = Db::table('t_orders')->alias('a')->join('t_dineshop b','a.f_shopid = b.f_sid','left')->where($where)->count();
        $orderlist = Db::table('t_orders')
            ->alias('a')
            ->field('a.f_oid orderid,a.f_shopid shopid,b.f_shopname shopname,a.f_userid userid,a.f_type ordertype,a.f_status status,a.f_orderdetail orderdetail,a.f_ordermoney ordermoney,a.f_deliverymoney deliverymoney,a.f_allmoney allmoney,a.f_paymoney paymoney,a.f_paytype paytype,a.f_mealsnum mealsnum,a.f_startime startime,a.f_endtime endtime,a.f_addtime addtime')
            ->join('t_dineshop b','a.f_shopid = b.f_sid','left')
            ->where($where)
            ->order('a.f_addtime desc')
            ->page($page, $pagesize)
            ->select();
        return array(
            "allnum" => $allnum,
            "orderlist" => $orderlist
        );
    }  
    /**
     * 处理订单
     */
    public function processOrder($orderid, $data)
    {
        $res = array();
        $update = array();
        if(isset($data['status'])) $update['f_status'] = $data['status'];
        if(isset($data['distripid'])) $update['f_deliveryid'] = $data['distripid'];
        if(count($update) > 0){
            $res = Db::table('t_orders')->where('f_oid', $orderid)->update($update);
        }
        return $res;
    }
    /**
     * 获取订单详情
     */
    public function deliveryOrder($orderid, $distripid)
    {
        $res = Db::table('t_orders')->where('f_oid', $orderid)->update(array('f_status' => 3, 'f_deliveryid' => $distripid));
        return $res;
    }

    /**
     * 获取订单详情
     */
    public function getOrderinfo($userid, $orderid)
    {
        $where = array(
            'a.f_userid' => $userid,
            'a.f_oid' => $orderid
        );
        $orderinfo = Db::table('t_orders')
            ->alias('a')
            ->field('a.f_oid orderid,a.f_shopid shopid,b.f_shopname shopname,a.f_userid userid,a.f_type ordertype,a.f_status status,a.f_orderdetail orderdetail,a.f_ordermoney ordermoney,a.f_deliverymoney deliverymoney,a.f_allmoney allmoney,a.f_paymoney paymoney,a.f_paytype paytype,a.f_mealsnum mealsnum,a.f_servicemoney servicemoney,a.f_startime startime,a.f_endtime endtime,c.f_name recipientname,c.f_mobile recipientmobile,d.f_username deliveryname,d.f_mobile deliveryphone,a.f_deliverytime deliverytime,CONCAT(c.f_province,c.f_city,c.f_address) deliveryaddress,a.f_addtime addtime')
            ->join('t_dineshop b','a.f_shopid = b.f_sid','left')
            ->join('t_user_address_info c', 'a.f_addressid = c.f_id','left')
            ->join('t_dineshop_distripersion d', 'a.f_deliveryid = d.f_id','left')
            ->where($where)
            ->find();
        return $orderinfo?$orderinfo:false;
    }

    /**
     * 更新交易订单信息
     * @param $uid
     * @param $orderid
     * @param $status
     * @param $paymoney
     * @return bool
     */
    public function updateTradeOrderInfo($uid, $orderid, $status, $paymoney=0){
        $sql = "update t_orders set f_status = :status, f_paymoney = f_paymoney + :paymoney where f_userid = :uid and f_oid = :orderid";
        $args = array(
            "uid" => $uid,
            "orderid" => $orderid,
            "status" => $status,
            "paymoney" => $paymoney
        );
        $ret = Db::execute($sql,$args);
        if($ret !== false){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 获取交易订单信息(订单支付退款用)
     * @param $uid
     * @param $orderid
     * @return array|false|\PDOStatement|string|Model
     */
    public function getTradeOrderInfo($uid, $orderid){
        $table_name = 'orders';
        $orderinfo = Db::name($table_name)
            ->where('f_userid',$uid)
            ->where('f_oid',$orderid)
            ->field('f_status as status')
            ->field('f_allmoney as allmoney')
            ->field('f_paymoney as paymoney')
            ->find();
        return $orderinfo;
    }
}