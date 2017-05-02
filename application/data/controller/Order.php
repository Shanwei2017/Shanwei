<?php
namespace app\data\controller;

use base\Base;
use \app\data\model\UserModel;
use \app\data\model\DineshopModel;
use \app\data\model\DishesModel;
use \app\data\model\OrderModel;

class Order extends Base
{
    /**
     * 新增订单
     * @return \think\response\Json
     */
    //http://shanwei.boss.com/data/order/createOrder?uid=10002&ck=ck_NGE5NJA5NWVMMTIYNWJKZMRLOWZKODFLNMM3YTVKZTU=&shopid=8&orderdetail=1|1@1,2|2@1,23|3@1&ordermoney=216&deliverymoney=9&allmoney=225&paytype=0&ordertype=1&deliverytime=2017-05-02%2012:00:00&addressid=1
    //http://shanwei.boss.com/data/order/createOrder?uid=10002&ck=ck_NGE5NJA5NWVMMTIYNWJKZMRLOWZKODFLNMM3YTVKZTU=&shopid=8&orderdetail=1|1@1,2|2@1,23|3@1&ordermoney=216&deliverymoney=9&allmoney=225&paytype=0&ordertype=2&mealsnum=2&startime=2017-05-02%2012:00:00&endtime=2017-05-02%2012:00:00
    public function createOrder()
    {
        $uid = input('uid'); //用户ID
        $shopid = input('shopid'); //店铺ID
        $orderdetail = input('orderdetail'); //订单明细
        $ordermoney = floatval(input('ordermoney','0')); //订单金额
        $deliverymoney = floatval(input('deliverymoney','0')); //配送费
        $allmoney = floatval(input('allmoney','0')); //订单总金额
        $paytype = input('paytype'); //支付方式
        $ordertype = input('ordertype'); //订单类型（1,外卖订单  2,食堂订单）
        $deliverytime = input('deliverytime'); //外卖 配送时间
        $addressid = input('addressid'); //外卖 配送地址ID
        $mealsnum = input('mealsnum'); //食堂就餐 就餐人数
        $startime = input('startime'); //食堂订餐 开始时间
        $endtime = input('endtime'); //食堂订餐 结束时间
        
        if(!$shopid) return json($this->erres('未指定订单店铺', -30001));
        if(!$orderdetail) return json($this->erres('订单不能为空', -30002));
        if($ordermoney == 0 || $allmoney == 0 || $ordermoney + $deliverymoney != $allmoney){
            return json($this->erres('订单金额错误', -30003)); 
        }
        if($paytype == '') return json($this->erres('请选择支付方式', -30004));
        if(!in_array($ordertype, array('1','2'))) return json($this->erres('订单类型错误', -30005));
        if($ordertype == 1){
            if(!$deliverytime) return json($this->erres('请选择配送时间', -30006));
            if(!check_datetime($deliverytime)) return json($this->erres('配送时间格式不对', -30007));
            if(!$addressid) return json($this->erres('请选择配送地址', -30008));
        }else if($ordertype == 2){
            if(!$mealsnum) return json($this->erres('请选择就餐人数', -30009));
            if(!$startime) return json($this->erres('请选择预计就餐开始时间', -30010));
            if(!check_datetime($startime)) return json($this->erres('就餐时间格式不对', -30011));
            if(!$endtime) return json($this->erres('请选择预计就餐结束时间', -30012));
            if(!check_datetime($endtime)) return json($this->erres('就餐时间格式不对', -30013));
        }
        //判断用户登录
        if($this->checkLogin() === false) return json($this->erres('用户未登录，请先登录', -10001));
        //验证用户
        $UserModel = new UserModel();
        $userinfo = $UserModel->getUserInfoByUid($uid);
        if(empty($userinfo)) return json($this->erres("用户信息不存在", -30014));
        //验证店铺
        $DineshopModel = new DineshopModel();
        $shopinfo = $DineshopModel->getShopInfo($shopid);
        if(empty($shopinfo)) return json($this->erres("店铺信息不存在", -30015));
        //验证订单金额
        if($ordermoney + $deliverymoney != $allmoney) return json($this->erres("订单总金额不正确", -30016));
        $DishesModel = new DishesModel();
        $_orderinfo = array();
        foreach(explode(',', $orderdetail) as $key=>$val){
            preg_match('/(\d+)\|(\d+)\@(\d+)/i', $val, $match);
            $_orderinfo[$match[1]] = $match[3];
        }
        $list = $DishesModel->getDishesList(implode(',', array_keys($_orderinfo)));
        $_ordermoney = 0;
        foreach($list as $val){
            $_ordermoney += floatval($val['price']) * $_orderinfo[$val['id']];
        }
        if($_ordermoney != $ordermoney) return json($this->erres("订单金额不正确", -30017));
        //验证外卖配送地址
        if($ordertype == 1){
            $addressinfo = $UserModel->getAddressInfo($addressid);
            if(empty($addressinfo)) return json($this->erres("地址信息不存在", -30018));
        }
        //创建订单
        $OrderModel = new OrderModel();
        //先验证订单是否已添加
        $orderid = $OrderModel->checkOrder($uid, $shopid, $orderdetail, $ordertype);
        if($orderid){
            return json($this->sucres(array('orderid' => $orderid)));
        }else{
            if($ordertype == 1){
                $orderid = $OrderModel->addTakeoutOrders($uid, $shopid, $orderdetail, $ordermoney, $deliverymoney, $allmoney, $paytype, $deliverytime, $addressid);
            }else{
                $orderid = $OrderModel->addEatinOrders($uid, $shopid, $orderdetail, $ordermoney, $deliverymoney, $allmoney, $paytype, $mealsnum, $startime, $endtime);
            }
            if($orderid){
                if($this->checkMoneyEnough($uid,$allmoney)){
                    return json($this->sucres(array('orderid' => $orderid)));
                }else{
                    $this->res['code'] = -10002; //用户余额不足
                    $this->res['msg'] = '用户余额不足';
                    $this->res['info'] = array('orderid' => $orderid);
                    return json($this->res);
                }
            }else{
                return json($this->erres("创建订单失败", -30019));
            }
        }
    }
    
    /**
     * 完成订单
     */
    //http://shanwei.boss.com/data/order/finishOrder?orderid=12&uid=10005&ck=ck_NGE5NJA5NWVMMTIYNWJKZMRLOWZKODFLNMM3YTVKZTU=
    public function finishOrder(){
        $uid = input('uid'); //用户ID
        $orderid = input('orderid'); //用户ID
        if(empty($orderid)) return json($this->erres('订单信息为空', -30020));
        //判断用户登录
        if($this->checkLogin() === false) return json($this->erres('用户未登录，请先登录', -10001));
        //获取订单信息
        $OrderModel = new OrderModel();
        $orderinfo = $OrderModel->getOrderinfo($orderid);
        if(!$orderinfo)  return json($this->erres('订单信息不存在', -30021));
        $status = $orderinfo['status'];
        $allmoney = floatval($orderinfo['allmoney']);
        $userid = $orderinfo['userid'];
        
        if($status < 2){
            //验证用户余额
            if(!$this->checkMoneyEnough($userid,$allmoney)) return json($this->erres('用户余额不足', -30021));
            //完成订单 事务处理
            $ret = $OrderModel->finishOrder($userid, $orderid, $allmoney);
            if(!$ret){
                return json($this->erres('完成订单失败', -30022));
            }
        }
        return json($this->sucres());
    }
    
    /**
     * 验证用户金额时候充足
     */
    public function checkMoneyEnough($userid, $allmoney){
        $UserModel = new UserModel();
        $userinfo = $UserModel->getUserInfoByUid($userid);
        $usermoney = floatval($userinfo['usermoney']);
        return $usermoney >= $allmoney;
    }
}
