<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file notice.php
 * @brief 用户中心api方法
 * @author chendeshan
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APIUcenter
{

	///用户中心-账户余额
	public function getUcenterAccoutLog($userid)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('account_log');
		$query->where="user_id = ".$userid;
		$query->order = 'id desc';
		$query->page  = $page;
		return $query;
	}
	//用户中心-我的建议
	public function getUcenterSuggestion($userid)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('suggestion');
		$query->where="user_id = ".$userid;
		$query->page  = $page;
		$query->order = 'id desc';
		return $query;
	}
	//用户中心-商品讨论
	public function getUcenterConsult($userid)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('refer as r');
		$query->join   = "join goods as go on r.goods_id = go.id ";
		$query->where  = "r.user_id =". $userid;
		$query->fields = "time,name,question,status,answer,admin_id,go.id as gid,reply_time";
		$query->page   = $page;
		$query->order = 'r.id desc';
		return $query;
	}
	//用户中心-商品评价
	public function getUcenterEvaluation($userid,$status = '')
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$where = "go.is_del = 0 and c.user_id = {$userid}";
		$where.= ($status === '') ? "" : " and c.status = {$status}";

		$query = new IQuery('comment as c');
		$query->join   = "left join goods as go on go.id = c.goods_id";
		$query->fields = "c.*,go.sell_price,go.goods_type";
		$query->where  = $where;
		$query->page   = $page;
		$query->order  = 'c.id desc';
		$result        = $query->find();

		foreach($result as $key => $val)
		{
			$goodsRow = comment_class::goodsInfo($val['id']);
			$result[$key] = array_merge($val,$goodsRow);
		}
		return $query->setData($result);
	}

	//用户中心-用户信息
	public function getMemberInfo($userid){
		$tb_member = new IModel('member as m,user as u');
		$info = $tb_member->getObj("m.user_id = u.id and m.user_id=".$userid);
		$info['group_name'] = "";
		if($info['group_id'])
		{
			$userGroup = new IModel('user_group');
			$groupRow  = $userGroup->getObj('id = '.$info['group_id']);
			$info['group_name'] = $groupRow ? $groupRow['group_name'] : "";
		}
		return $info;
	}

    //用户中心-用户信息
    public function getAreaInfo($areaid){
	    $area_id = explode(",",$areaid);
        $area_model = new IModel('areas');

        for ($i=0;$i<count($area_id);$i++){
            $area_info[$i] = $area_model->getObj("area_id = '".$area_id[$i]."'",'area_name');
        }
        $area_name[0] = $area_info[0]['area_name'];
        if(count($area_info) == 2){
            $area_name[1] = $area_info[1]['area_name'];
        }
        if(count($area_info) == 3){
            $area_name[2] = $area_info[2]['area_name'];
        }

        $area_name = implode("|",$area_name);
        return $area_name;
    }
	//用户中心-个人主页统计
	public function getMemberTongJi($userid){
		$result = array();

		$query = new IQuery('order');
		$query->fields = "count(id) as num";
		$query->where  = "user_id = ".$userid." and if_del = 0";
		$info = $query->find();
		$result['num'] = $info[0]['num'];

		$query->fields = "sum(order_amount) as amount";
		$query->where  = "user_id = ".$userid." and status = 5 and if_del = 0";
		$info = $query->find();
		$result['amount'] = $info[0]['amount'];

		return $result;
	}
	//用户中心-代金券统计
	public function getPropTongJi($propIds){
		$query = new IQuery('prop');
		$query->fields = "count(id) as prop_num";
		$query->where  = "id in (".$propIds.") and type = 0";
		$info = $query->find();
		return $info[0];
	}
	//用户中心-积分列表
	public function getUcenterPointLog($userid,$c_datetime)
	{
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('point_log');
		$query->where  = "user_id = ".$userid." and ".$c_datetime;
		$query->page   = $page;
		$query->order= "id desc";
		return $query;
	}

    //用户中心-积分列表
    public function getUcenterExpLog($userid)
    {
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('exp_log');
        $query->where  = "user_id = ".$userid;
        $query->page   = $page;
        $query->order= "id desc";
        return $query;
    }
	//用户中心-信息列表
	public function getUcenterMessageList($msgIds){
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('message');
		$query->where= "id in(".$msgIds.")";
		$query->order= "id desc";
		$query->page = $page;
		return $query;
	}
	//用户中心-订单列表
	public function getOrderList($userid,$is_prescription=1){
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('order');
		$query->where = "user_id =".$userid." and if_del= 0 and is_prescription=".$is_prescription;
		$query->order = "id desc";
		$query->page  = $page;
		return $query;
	}

    //用户中心-订单列表
    public function getOrderListnoPay($userid,$is_prescription=1){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('order');
        $query->where = "user_id =".$userid." and status=1 and pay_status=0 and if_del= 0 and is_prescription=".$is_prescription;
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }

    public function getOrderListPay($userid,$is_prescription=1){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('order');
        $query->where = "user_id =".$userid." and status=2 and pay_status=1 and distribution_status=0 and if_del= 0 and is_prescription=".$is_prescription;
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }

    public function getOrderListShou($userid,$is_prescription=1){
        $page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
        $query = new IQuery('order');
        $query->where = "user_id =".$userid." and status=2 and pay_status=1 and distribution_status=1 and if_del= 0 and is_prescription=".$is_prescription;
        $query->order = "id desc";
        $query->page  = $page;
        return $query;
    }


	//用户中心-我的代金券
	public function getPropList($ids){
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('prop');
		$query->where  = "id in(".$ids.") and is_send = 1";
		$query->page   = $page;
		return $query;
	}
	//用户中心-退款记录
	public function getRefundmentDocList($userid){
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('refundment_doc');
		$query->where = "user_id = ".$userid." and if_del = 0";
		$query->order = "id desc";
		$query->page  = $page;
		return $query;
	}
	//用户中心-提现记录
	public function getWithdrawList($userid){
		$page = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$query = new IQuery('withdraw');
		$query->where = "user_id = ".$userid." and is_del = 0";
		$query->order = "id desc";
		$query->page  = $page;
		return $query;
	}

    //[收藏夹]获取收藏夹数据
	public function getFavorite($userid,$cat = '')
    {
		//获取收藏夹信息
	    $page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
	    $cat_id = IFilter::act($cat,'int');

		$favoriteObj = new IQuery("favorite as f");
		$favoriteObj->join  = "left join goods as go on go.id = f.rid";
		$favoriteObj->fields= " f.*,go.is_prescription,go.name,go.id as goods_id,go.img,go.store_nums,go.sell_price,go.market_price";

		$where = 'user_id = '.$userid;
		$where.= $cat_id ? ' and cat_id = '.$cat_id : "";

		$favoriteObj->where = $where;
		$favoriteObj->page  = $page;
		return $favoriteObj;
    }

    //查询用户昵称
    public function getUserNickname($userid){
        $query = new IQuery('user');
        $query->where = "user_id = ".$userid;
        return $query;
    }
}