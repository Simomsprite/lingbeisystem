<?php
/**
 * @brief 用户中心模块
 * @class Ucenter
 * @note  前台
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/classes/configim.php');
use JMessage\IM\Friend;
use JMessage\JMessage;
use JMessage\IM\Resource;

class Ucenter extends IController implements userAuthorization
{
	public $layout = 'ucenter';
    public $pagesize = 10;

	public function init()
	{

	}
    /*public function index()
    {

    	//获取用户基本信息
		$user = Api::run('getMemberInfo',$this->user['user_id']);

		//获取用户各项统计数据
		$statistics = Api::run('getMemberTongJi',$this->user['user_id']);

		//获取用户站内信条数
		$msgObj = new Mess($this->user['user_id']);
		$msgNum = $msgObj->needReadNum();

		//获取用户代金券
		$propIds = trim($user['prop'],',');
		$propIds = $propIds ? $propIds : 0;
		$propData= Api::run('getPropTongJi',$propIds);

		$this->setRenderData(array(
			"user"       => $user,
			"statistics" => $statistics,
			"msgNum"     => $msgNum,
			"propData"   => $propData,
		));

        if($user['true_name'] && $user['birthday'] && $user['contact_addr']  && $user['area']){
            $this->info_bi = "100%";
        }elseif($user['true_name'] && $user['birthday'] && $user['contact_addr']){
            $this->info_bi = "75%";
        }elseif($user['true_name'] && $user['birthday']){
            $this->info_bi = "50%";
        }elseif($user['true_name']){
            $this->info_bi = "25%";
        }else{
            $this->info_bi = "0%";;
        }


        $this->initPayment();
        $this->redirect('index');
    }*/

    //lb个人首页
    public function index()
    {

        //获取用户基本信息
        $user = Api::run('getMemberInfo',$this->user['user_id']);

        $this->setRenderData(array(
            "user"       => $user,
        ));
        $this->redirect('index');
    }

	//[用户头像]上传
	function user_ico_upload()
	{
		$result = array(
			'isError' => true,
		);

		if(isset($_FILES['attach']['name']) && $_FILES['attach']['name'] != '')
		{
			$photoObj = new PhotoUpload();
			$photo    = $photoObj->run();

			if(isset($photo['attach']['img']) && $photo['attach']['img'])
			{
				$user_id   = $this->user['user_id'];
				$user_obj  = new IModel('user');
				$dataArray = array(
					'head_ico' => $photo['attach']['img'],
				);
				$user_obj->setData($dataArray);
				$where  = 'id = '.$user_id;
				$isSuss = $user_obj->update($where);

				if($isSuss !== false)
				{
					$result['isError'] = false;
					$result['data'] = IUrl::creatUrl().$photo['attach']['img'];
					ISafe::set('head_ico',$dataArray['head_ico']);
				}
				else
				{
					$result['message'] = '上传失败';
				}
			}
			else
			{
				$result['message'] = '上传失败';
			}
		}
		else
		{
			$result['message'] = '请选择图片';
		}
		echo '<script type="text/javascript">parent.callback_user_ico('.JSON::encode($result).');</script>';
	}

    /**
     * @brief 我的订单列表
     */
    public function order()
    {
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0",false);
        $this->initPayment();
        $this->redirect('order');

    }
    /**
     * @brief 初始化支付方式
     */
    private function initPayment()
    {
        $payment = new IQuery('payment');
        $payment->fields = 'id,name,type';
        $payments = $payment->find();
        $items = array();
        foreach($payments as $pay)
        {
            $items[$pay['id']]['name'] = $pay['name'];
            $items[$pay['id']]['type'] = $pay['type'];
        }
        $this->payments = $items;
    }
    /**
     * @brief 订单详情
     * @return String
     */
    public function order_detail()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        $orderObj = new order_class();
        $this->order_info = $orderObj->getOrderShow($id,$this->user['user_id']);

        if(!$this->order_info)
        {
        	IError::show(403,'订单信息不存在');
        }
        $orderStatus = Order_Class::getOrderStatus($this->order_info);
        $this->setRenderData(array('orderStatus' => $orderStatus));
        $this->redirect('order_detail',false);
    }

    //需求详情  三石
    public function chuorder_detail()
    {
        $id = IFilter::act(IReq::get('id'),'int');

        $orderObj = new order_class();
        $this->order_info = $orderObj->getOrderShow($id,$this->user['user_id']);

        if(!$this->order_info)
        {
            IError::show(403,'订单信息不存在');
        }
        $orderStatus = Order_Class::getOrderStatus($this->order_info);
        $this->setRenderData(array('orderStatus' => $orderStatus));
        $this->redirect('chuorder_detail',false);
    }


    //操作订单状态
	public function order_status()
	{
		$op    = IFilter::act(IReq::get('op'));
		$id    = IFilter::act( IReq::get('order_id'),'int' );
		$model = new IModel('order');
        $order_info = $model->getObj("id=".$id);
		switch($op)
		{
			case "cancel":
			{
				$model->setData(array('status' => 3));
				if($model->update("id = ".$id." and distribution_status = 0 and status = 1 and user_id = ".$this->user['user_id']))
				{
					order_class::resetOrderProp($id);
				}
			}
			break;

			case "confirm":
			{
				$model->setData(array('status' => 5,'completion_time' => ITime::getDateTime()));
				if($model->update("id = ".$id." and distribution_status = 1 and user_id = ".$this->user['user_id']))
				{
					$orderRow = $model->getObj('id = '.$id);

					//确认收货后进行支付
					Order_Class::updateOrderStatus($orderRow['order_no']);

		    		//增加用户评论商品机会
		    		Order_Class::addGoodsCommentChange($id);

		    		//确认收货以后直接跳转到评论页面
		    		$this->redirect('evaluation');
				}
			}
			break;
		}

        if($order_info['is_prescription'] == 1){
            $this->redirect("order_detail/id/$id");
        }else{
            $this->redirect("chuorder_detail/id/$id");
        }
	}
    /**
     * @brief 我的地址
     */
    public function address()
    {
		//取得自己的地址
		$query = new IQuery('address');
        $query->where = 'user_id = '.$this->user['user_id'];
        $query->order = "is_default desc";
		$address = $query->find();
		$areas   = array();

		if($address)
		{
			foreach($address as $ad)
			{
				$temp = area::name($ad['province'],$ad['city'],$ad['area']);
				if(isset($temp[$ad['province']]) && isset($temp[$ad['city']]) && isset($temp[$ad['area']]))
				{
					$areas[$ad['province']] = $temp[$ad['province']];
					$areas[$ad['city']]     = $temp[$ad['city']];
					$areas[$ad['area']]     = $temp[$ad['area']];
				}
			}
		}

		$this->areas = $areas;
		$this->address = $address;
        $this->redirect('address');
    }
    /**
     * @brief 收货地址管理
     */
	public function address_edit()
	{
		$id          = IFilter::act(IReq::get('id'),'int');
		$accept_name = IFilter::act(IReq::get('accept_name'),'name');
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$zip         = IFilter::act(IReq::get('zip'),'zip');
		$telphone    = IFilter::act(IReq::get('telphone'),'phone');
		$mobile      = IFilter::act(IReq::get('mobile'),'mobile');
		$default     = IReq::get('is_default')!= 1 ? 0 : 1;
        $user_id     = $this->user['user_id'];

		$model = new IModel('address');
		$data  = array('user_id'=>$user_id,'accept_name'=>$accept_name,'province'=>$province,'city'=>$city,'area'=>$area,'address'=>$address,'zip'=>$zip,'telphone'=>$telphone,'mobile'=>$mobile,'is_default'=>$default);

        //如果设置为首选地址则把其余的都取消首选
        if($default==1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);
        }

		$model->setData($data);

		if($id == '')
		{
			$model->add();
		}
		else
		{
			$model->update('id = '.$id);
		}
		$this->redirect('address');
	}
    /**
     * @brief 收货地址删除处理
     */
	public function address_del()
	{
		$id = IFilter::act( IReq::get('id'),'int' );
		$model = new IModel('address');
		$model->del('id = '.$id.' and user_id = '.$this->user['user_id']);
		$this->redirect('address');
	}
    /**
     * @brief 设置默认的收货地址
     */
    public function address_default()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $default = IFilter::act(IReq::get('is_default'));
        $model = new IModel('address');
        if($default == 1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);
        }
        $model->setData(array('is_default' => $default));
        $model->update("id = ".$id." and user_id = ".$this->user['user_id']);
        $this->redirect('address');
    }
    /**
     * @brief 退款申请页面
     */
    public function refunds_update()
    {
        $order_goods_id = IFilter::act( IReq::get('order_goods_id'),'int' );
        $order_id       = IFilter::act( IReq::get('order_id'),'int' );
        $user_id        = $this->user['user_id'];
        $content        = IFilter::act(IReq::get('content'),'text');
        $imgList        = IFilter::act(IReq::get('imgList'));
        $refund_msg        = IFilter::act(IReq::get('refund_msg'));
        $message        = '';

        if(!$order_goods_id)
        {
        	$message = "选择要退款的商品";
	        $this->redirect('refunds',false);
	        Util::showMessage($message);
        }

        $orderDB      = new IModel('order');
        $orderRow     = $orderDB->getObj("id = ".$order_id." and user_id = ".$user_id);
        $refundResult = Order_Class::isRefundmentApply($orderRow,$order_goods_id);


        //判断退款申请是否有效
        if($refundResult === true)
        {
			//退款单数据
    		$updateData = array(
				'order_no'       => $orderRow['order_no'],
				'order_id'       => $order_id,
				'user_id'        => $user_id,
				'img'        => $imgList,
				'refund_msg'        => $refund_msg,
				'time'           => ITime::getDateTime(),
				'content'        => $content,
				'seller_id'      => $orderRow['seller_id'],
				 'order_goods_id' => join(",",$order_goods_id),
			);

    		//写入数据库
    		$refundsDB = new IModel('refundment_doc');
    		$refundsDB->setData($updateData);
    		$refund_id= $refundsDB->add();
            $this->redirect("/ucenter/refunds_detail/id/".$refund_id);
        }
        else
        {
        	$message = $refundResult;
	        $this->redirect('refunds',false);
	        Util::showMessage($message);
        }
    }
    /**
     * @brief 退款申请删除
     */
    public function refunds_del()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $model = new IModel("refundment_doc");
        $result= $model->del("id = ".$id." and pay_status = 0 and user_id = ".$this->user['user_id']);
        $this->redirect('refunds');
    }
    /**
     * @brief 查看退款申请详情
     */
    public function refunds_detail()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $refundDB = new IModel("refundment_doc");
        $refundRow = $refundDB->getObj("id = ".$id." and user_id = ".$this->user['user_id']);
        if($refundRow)
        {
        	//获取商品信息
        	$orderGoodsDB   = new IModel('order_goods');
        	$orderGoodsList = $orderGoodsDB->query("id in (".$refundRow['order_goods_id'].")");

        	if($orderGoodsList)
        	{
        		$refundRow['goods'] = $orderGoodsList;
        		$this->data = $refundRow;
        	}
        	else
        	{
	        	$this->redirect('refunds',false);
	        	Util::showMessage("没有找到要退款的商品");
        	}
        	$this->redirect('refunds_detail');
        }
        else
        {
        	$this->redirect('refunds',false);
        	Util::showMessage("退款信息不存在");
        }
    }
    /**
     * @brief 查看退款申请详情
     */

    public function refunds_edit()
    {
        $order_id = IFilter::act(IReq::get('order_id'),'int');
        if($order_id)
        {
            $orderDB  = new IModel('order');
            $orderRow = $orderDB->getObj('id = '.$order_id.' and user_id = '.$this->user['user_id']);

            $refundDB  = new IModel('refundment_doc');
            $refundRow = $refundDB->getObj('order_id = '.$order_id.' and if_del = 0');
            if($refundRow)
            {
                $this->redirect("/ucenter/refunds_detail/id/".$refundRow['id']);
                exit;
            }

            if($orderRow)
            {
                $this->orderRow = $orderRow;
                $this->redirect('refunds_edit');
                return;
            }
        }
        $this->redirect('refunds');
    }

    /**
     * @brief 建议中心
     */
    public function complain_edit()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $title = IFilter::act(IReq::get('title'),'string');
        $content = IFilter::act(IReq::get('content'),'string' );
        $user_id = $this->user['user_id'];
        $model = new IModel('suggestion');
        $model->setData(array('user_id'=>$user_id,'title'=>$title,'content'=>$content,'time'=>ITime::getDateTime()));
        if($id =='')
        {
            $model->add();
        }
        else
        {
            $model->update('id = '.$id.' and user_id = '.$this->user['user_id']);
        }
        $this->redirect('complain');
    }
    //站内消息
    public function message()
    {
    	$msgObj = new Mess($this->user['user_id']);
    	$msgIds = $msgObj->getAllMsgIds();
    	$msgIds = $msgIds ? $msgIds : 0;
		$this->setRenderData(array('msgIds' => $msgIds,'msgObj' => $msgObj));
    	$this->redirect('message');
    }
    /**
     * @brief 删除消息
     * @param int $id 消息ID
     */
    public function message_del()
    {
        $id = IFilter::act( IReq::get('id') ,'int' );
        $msg = new Mess($this->user['user_id']);
        $msg->delMessage($id);
        $this->redirect('message');
    }
    public function message_read()
    {
        $id = IFilter::act( IReq::get('id'),'int' );
        $msg = new Mess($this->user['user_id']);
        echo $msg->writeMessage($id,1);
    }

    //[修改密码]修改动作
    function password_edit()
    {
    	$user_id    = $this->user['user_id'];

    	$fpassword  = IReq::get('fpassword');
    	$password   = IReq::get('password');
    	$repassword = IReq::get('repassword');

    	$userObj    = new IModel('user');
    	$where      = 'id = '.$user_id;
    	$userRow    = $userObj->getObj($where);

		if(!preg_match('|\w{6,32}|',$password))
		{
			$message = '密码格式不正确，请重新输入';
		}
    	else if($password != $repassword)
    	{
    		$message  = '二次密码输入的不一致，请重新输入';
    	}
    	else if(md5($fpassword) != $userRow['password'])
    	{
    		$message  = '原始密码输入错误';
    	}
    	else
    	{
    		$passwordMd5 = md5($password);
	    	$dataArray = array(
	    		'password' => $passwordMd5,
	    	);

	    	$userObj->setData($dataArray);
	    	$result  = $userObj->update($where);
	    	if($result)
	    	{
	    		ISafe::set('user_pwd',$passwordMd5);
	    		$message = '密码修改成功';
	    	}
	    	else
	    	{
	    		$message = '密码修改失败';
	    	}
		}

        $array = array(
            'info' => $message,
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/info'),
        );
        echo JSON::encode($array);
    }

    //[个人资料]展示 单页
    function info()
    {
    	$user_id = $this->user['user_id'];

    	$userObj       = new IModel('user');
    	$where         = 'id = '.$user_id;
    	$this->userRow = $userObj->getObj($where);
        $memberObj       = new IModel('member');
        $where           = 'user_id = '.$user_id;
        $this->memberRow = $memberObj->getObj($where);

    	if($this->memberRow['true_name'] && $this->memberRow['birthday'] && $this->memberRow['contact_addr']  && $this->memberRow['area']){
    	    $this->info_bi = "100%";
        }elseif($this->memberRow['true_name'] && $this->memberRow['birthday'] && $this->memberRow['contact_addr']){
            $this->info_bi = "75%";
        }elseif($this->memberRow['true_name'] && $this->memberRow['birthday']){
            $this->info_bi = "50%";
        }elseif($this->memberRow['true_name']){
            $this->info_bi = "25%";
        }else{
            $this->info_bi = "0%";;
        }


    	$this->redirect('info');
    }

    //lb[个人资料]展示 单页
    function lb_info()
    {
        $user_id = $this->user['user_id'];

        $userObj       = new IModel('user');
        $where         = 'id = '.$user_id;
        $this->userRow = $userObj->getObj($where);
        $memberObj       = new IModel('member');
        $where           = 'user_id = '.$user_id;
        $this->memberRow = $memberObj->getObj($where);
        $addressObj      =new IModel('address');
        $wheres           ='user_id = '.$user_id;
        $this->addressRow=$addressObj->getObj($wheres);

        if($this->memberRow['true_name'] && $this->memberRow['birthday'] && $this->memberRow['contact_addr']  && $this->memberRow['area']){
            $this->info_bi = "100%";
        }elseif($this->memberRow['true_name'] && $this->memberRow['birthday'] && $this->memberRow['contact_addr']){
            $this->info_bi = "75%";
        }elseif($this->memberRow['true_name'] && $this->memberRow['birthday']){
            $this->info_bi = "50%";
        }elseif($this->memberRow['true_name']){
            $this->info_bi = "25%";
        }else{
            $this->info_bi = "0%";;
        }

        $this->redirect('lb_info');
    }

    //[个人资料] 修改 [动作]
    function info_edit_act()
    {
		$email     = IFilter::act( IReq::get('email'),'string');
		$mobile    = IFilter::act( IReq::get('mobile'),'string');

    	$user_id   = $this->user['user_id'];
    	$memberObj = new IModel('member');
    	$where     = 'user_id = '.$user_id;

		if($email)
		{
			$memberRow = $memberObj->getObj('user_id != '.$user_id.' and email = "'.$email.'"');
			if($memberRow)
			{
				IError::show('邮箱已经被注册');
			}
		}

		if($mobile)
		{
			$memberRow = $memberObj->getObj('user_id != '.$user_id.' and mobile = "'.$mobile.'"');
			if($memberRow)
			{
				IError::show('手机已经被注册');
			}
		}

    	//地区
    	$province = IFilter::act( IReq::get('province','post') ,'string');
    	$city     = IFilter::act( IReq::get('city','post') ,'string' );
    	$area     = IFilter::act( IReq::get('area','post') ,'string' );
    	$areaArr  = array_filter(array($province,$city,$area));

    	$dataArray       = array(
    		'email'        => $email,
    		'true_name'    => IFilter::act( IReq::get('true_name') ,'string'),
    		'sex'          => IFilter::act( IReq::get('sex'),'int' ),
    		'birthday'     => IFilter::act( IReq::get('birthday') ),
    		'zip'          => IFilter::act( IReq::get('zip') ,'string' ),
    		'qq'           => IFilter::act( IReq::get('qq') , 'string' ),
    		'contact_addr' => IFilter::act( IReq::get('contact_addr'), 'string'),
    		'mobile'       => $mobile,
    		'telephone'    => IFilter::act( IReq::get('telephone'),'string'),
    		'area'         => $areaArr ? ",".join(",",$areaArr)."," : "",
    	);

    	$memberObj->setData($dataArray);
    	$memberObj->update($where);
    	$this->info();
    }

    //[账户余额] 展示[单页]
    function withdraw()
    {
    	$user_id   = $this->user['user_id'];

    	$memberObj = new IModel('member','balance');
    	$where     = 'user_id = '.$user_id;
    	$this->memberRow = $memberObj->getObj($where);
    	$this->redirect('withdraw');
    }

	//[账户余额] 提现动作
    function withdraw_act()
    {
    	$user_id = $this->user['user_id'];
    	$amount  = IFilter::act( IReq::get('amount','post') ,'float' );
    	$message = '';

    	$dataArray = array(
    		'name'   => IFilter::act( IReq::get('name','post') ,'string'),
    		'note'   => IFilter::act( IReq::get('note','post'), 'string'),
			'amount' => $amount,
			'user_id'=> $user_id,
			'time'   => ITime::getDateTime(),
    	);

		$mixAmount = 0;
		$memberObj = new IModel('member');
		$where     = 'user_id = '.$user_id;
		$memberRow = $memberObj->getObj($where,'balance');

		//提现金额范围
		if($amount <= $mixAmount)
		{
			$message = '提现的金额必须大于'.$mixAmount.'元';
		}
		else if($amount > $memberRow['balance'])
		{
			$message = '提现的金额不能大于您的帐户余额';
		}
		else
		{
	    	$obj = new IModel('withdraw');
	    	$obj->setData($dataArray);
	    	$obj->add();
	    	$this->redirect('withdraw');
		}

		if($message != '')
		{
			$this->memberRow = array('balance' => $memberRow['balance']);
			$this->withdrawRow = $dataArray;
			$this->redirect('withdraw',false);
			Util::showMessage($message);
		}
    }

    //[账户余额] 提现详情
    function withdraw_detail()
    {
    	$user_id = $this->user['user_id'];

    	$id  = IFilter::act( IReq::get('id'),'int' );
    	$obj = new IModel('withdraw');
    	$where = 'id = '.$id.' and user_id = '.$user_id;
    	$this->withdrawRow = $obj->getObj($where);
    	$this->redirect('withdraw_detail');
    }

    //[提现申请] 取消
    function withdraw_del()
    {
    	$id = IFilter::act( IReq::get('id'),'int');
    	if($id)
    	{
    		$dataArray   = array('is_del' => 1);
    		$withdrawObj = new IModel('withdraw');
    		$where = 'id = '.$id.' and user_id = '.$this->user['user_id'];
    		$withdrawObj->setData($dataArray);
    		$withdrawObj->update($where);
    	}
    	$this->redirect('withdraw');
    }

    //[余额交易记录]
    function account_log()
    {
    	$user_id   = $this->user['user_id'];

    	$memberObj = new IModel('member');
    	$where     = 'user_id = '.$user_id;
    	$this->memberRow = $memberObj->getObj($where);
    	$this->redirect('account_log');
    }

    //[收藏夹]备注信息
    function edit_summary()
    {
    	$user_id = $this->user['user_id'];

    	$id      = IFilter::act( IReq::get('id'),'int' );
    	$summary = IFilter::act( IReq::get('summary'),'string' );

    	//ajax返回结果
    	$result  = array(
    		'isError' => true,
    	);

    	if(!$id)
    	{
    		$result['message'] = '收藏夹ID值丢失';
    	}
    	else if(!$summary)
    	{
    		$result['message'] = '请填写正确的备注信息';
    	}
    	else
    	{
	    	$favoriteObj = new IModel('favorite');
	    	$where       = 'id = '.$id.' and user_id = '.$user_id;

	    	$dataArray   = array(
	    		'summary' => $summary,
	    	);

	    	$favoriteObj->setData($dataArray);
	    	$is_success = $favoriteObj->update($where);

	    	if($is_success === false)
	    	{
	    		$result['message'] = '更新信息错误';
	    	}
	    	else
	    	{
	    		$result['isError'] = false;
	    	}
    	}
    	echo JSON::encode($result);
    }

    //[收藏夹]删除
    function favorite_del()
    {
    	$user_id = $this->user['user_id'];
    	$id      = IReq::get('id');

		if(!empty($id))
		{
			$id = IFilter::act($id,'int');

			$favoriteObj = new IModel('favorite');

			if(is_array($id))
			{
				$idStr = join(',',$id);
				$where = 'user_id = '.$user_id.' and id in ('.$idStr.')';
			}
			else
			{
				$where = 'user_id = '.$user_id.' and id = '.$id;
			}

			$favoriteObj->del($where);
			$this->redirect('favorite');
		}
		else
		{
			$this->redirect('favorite',false);
			Util::showMessage('请选择要删除的数据');
		}
    }

    //[我的积分] 单页展示
    function integral()
    {

        /*获取积分增减的记录日期时间段*/
    	$this->historyTime = IFilter::string( IReq::get('history_time','post') );
    	$defaultMonth = 3;//默认查找最近3个月内的记录

		$lastStamp    = ITime::getTime(ITime::getNow('Y-m-d')) - (3600*24*30*$defaultMonth);
		$lastTime     = ITime::getDateTime('Y-m-d',$lastStamp);

		if($this->historyTime != null && $this->historyTime != 'default')
		{
			$historyStamp = ITime::getDateTime('Y-m-d',($lastStamp - (3600*24*30*$this->historyTime)));
			$this->c_datetime = 'datetime >= "'.$historyStamp.'" and datetime < "'.$lastTime.'"';
		}
		else
		{
			$this->c_datetime = 'datetime >= "'.$lastTime.'"';
		}

    	$memberObj         = new IModel('member');
    	$where             = 'user_id = '.$this->user['user_id'];
    	$this->memberRow   = $memberObj->getObj($where,'point');
    	$this->redirect('integral',false);
    }

    //[我的积分]积分兑换代金券 动作
    function trade_ticket()
    {
        IError::show('非法访问');

        $ticketId = IFilter::act( IReq::get('ticket_id','post'),'int' );
    	$message  = '';
    	if(intval($ticketId) == 0)
    	{
    		$message = '请选择要兑换的代金券';
    	}
    	else
    	{
    		$nowTime   = ITime::getDateTime();
    		$ticketObj = new IModel('ticket');
    		$ticketRow = $ticketObj->getObj('id = '.$ticketId.' and point > 0 and start_time <= "'.$nowTime.'" and end_time > "'.$nowTime.'"');
    		if(empty($ticketRow))
    		{
    			$message = '对不起，此代金券不能兑换';
    		}
    		else
    		{
	    		$memberObj = new IModel('member');
	    		$where     = 'user_id = '.$this->user['user_id'];
	    		$memberRow = $memberObj->getObj($where,'point');

	    		if($ticketRow['point'] > $memberRow['point'])
	    		{
	    			$message = '对不起，您的积分不足，不能兑换此类代金券';
	    		}
	    		else
	    		{
	    			//生成红包
					$dataArray = array(
						'condition' => $ticketRow['id'],
						'name'      => $ticketRow['name'],
						'card_name' => 'T'.IHash::random(8),
						'card_pwd'  => IHash::random(8),
						'value'     => $ticketRow['value'],
						'start_time'=> $ticketRow['start_time'],
						'end_time'  => $ticketRow['end_time'],
						'is_send'   => 1,
					);
					$propObj = new IModel('prop');
					$propObj->setData($dataArray);
					$insert_id = $propObj->add();

					//更新用户prop字段
					$memberArray = array('prop' => "CONCAT(IFNULL(prop,''),'{$insert_id},')");
					$memberObj->setData($memberArray);
					$result = $memberObj->update('user_id = '.$this->user["user_id"],'prop');

					//代金券成功
					if($result)
					{
						$pointConfig = array(
							'user_id' => $this->user['user_id'],
							'point'   => '-'.$ticketRow['point'],
							'log'     => '积分兑换代金券，扣除了 -'.$ticketRow['point'].'积分',
						);
						$pointObj = new Point;
						$pointObj->update($pointConfig);
					}
	    		}
    		}
    	}

    	//展示
    	if($message != '')
    	{
    		$this->integral();
    		Util::showMessage($message);
    	}
    	else
    	{
    		$this->redirect('redpacket');
    	}
    }

    /**
     * 余额付款
     * T:支付失败;
     * F:支付成功;
     */
    function payment_balance()
    {
    	$urlStr  = '';
    	$user_id = intval($this->user['user_id']);

    	$return['attach']     = IReq::get('attach');
    	$return['total_fee']  = IReq::get('total_fee');
    	$return['order_no']   = IReq::get('order_no');
    	$return['sign']       = IReq::get('sign');

		$paymentDB  = new IModel('payment');
		$paymentRow = $paymentDB->getObj('class_name = "balance" ');
		if(!$paymentRow)
		{
			IError::show(403,'余额支付方式不存在');
		}

		$paymentInstance = Payment::createPaymentInstance($paymentRow['id']);
		$payResult       = $paymentInstance->callback($return,$paymentRow['id'],$money,$message,$orderNo);
		if($payResult == false)
		{
			IError::show(403,$message);
		}

    	$memberObj = new IModel('member');
    	$memberRow = $memberObj->getObj('user_id = '.$user_id);

    	if(empty($memberRow))
    	{
    		IError::show(403,'用户信息不存在');
    	}

    	if($memberRow['balance'] < $return['total_fee'])
    	{
    		IError::show(403,'账户余额不足');
    	}

		//检查订单状态
		$orderObj = new IModel('order');
		$orderRow = $orderObj->getObj('order_no  = "'.$return['order_no'].'" and pay_status = 0 and status = 1 and user_id = '.$user_id);
		if(!$orderRow)
		{
			IError::show(403,'订单号【'.$return['order_no'].'】已经被处理过，请查看订单状态');
		}

		//扣除余额并且记录日志
		$logObj = new AccountLog();
		$config = array(
			'user_id'  => $user_id,
			'event'    => 'pay',
			'num'      => $return['total_fee'],
			'order_no' => str_replace("_",",",$return['attach']),
		);
		$is_success = $logObj->write($config);
		if(!$is_success)
		{
			$orderObj->rollback();
			IError::show(403,$logObj->error ? $logObj->error : '用户余额更新失败');
		}

		//订单批量结算缓存机制
		$moreOrder = Order_Class::getBatch($orderNo);
		if($money >= array_sum($moreOrder))
		{
			foreach($moreOrder as $key => $item)
			{
				$order_id = Order_Class::updateOrderStatus($key);
				if(!$order_id)
				{
					$orderObj->rollback();
					IError::show(403,'订单修改失败');
				}
			}
		}
		else
		{
			$orderObj->rollback();
			IError::show(403,'付款金额与订单金额不符合');
		}

		//支付成功结果
		$this->redirect('/site/success/message/'.urlencode("支付成功").'/?callback=/ucenter/order');
    }

    //我的代金券
    function redpacket()
    {
		$member_info = Api::run('getMemberInfo',$this->user['user_id']);
		$propIds     = trim($member_info['prop'],',');
		$propIds     = $propIds ? $propIds : 0;
		$this->setRenderData(array('propId' => $propIds));
		$this->redirect('redpacket');
    }


    //上传头像
    function re(){
     phpinfo();
    }

    //上传图片
    public function upload_tou()
    {
        $result = array(
            'isError' => true,
        );

        $base64_string = $_POST['base64_string'];
        $image = $base64_string;
       /* $exif = exif_read_data($image, 0, true);*/

        $path='upload/'.date("Y-m-d")."/";
        if (!is_dir($path)){
            $dirs = iconv("UTF-8", "GBK", $path);//文件夹路径
            mkdir ($dirs,0777,true);
        }
        $savename = uniqid() . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y-m-d') . "/";

        $savepath = $dir . $savename;

        /*if (isset($exif['IFD0']['Orientation'])) {
            $source = imagecreatefromjpeg($image);//读取图片流
            //判断角度翻转
            switch ($exif['IFD0']['Orientation']) {
                case 8:
                    $image = imagerotate($source, 90, 0);
                    imagejpeg($image, $savepath);
                    imagedestroy($image);
                    $tb_user = new IModel("user");
                    $update['head_ico'] = $savepath;
                    $tb_user->setData($update);
                    $tb_user->update("id=" . $this->user['user_id']);
                    ISafe::set('head_ico', $savepath);
                    break;
                case 3:
                    $image = imagerotate($source, 180, 0);
                    imagejpeg($image, $savepath);
                    imagedestroy($image);
                    $tb_user = new IModel("user");
                    $update['head_ico'] = $savepath;
                    $tb_user->setData($update);
                    $tb_user->update("id=" . $this->user['user_id']);
                    ISafe::set('head_ico', $savepath);
                    break;
                case 6:
                    $image = imagerotate($source, -90, 0);
                    imagejpeg($image, $savepath);
                    imagedestroy($image);
                    $tb_user = new IModel("user");
                    $update['head_ico'] = $savepath;
                    $tb_user->setData($update);
                    $tb_user->update("id=" . $this->user['user_id']);
                    ISafe::set('head_ico', $savepath);
                    break;
                case 1:
                    if (strstr($image, ",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    //imagejpeg($image,$imageSrc);
                    file_put_contents($savepath, base64_decode($image));//返回的是字节数
                    $tb_user = new IModel("user");
                    $update['head_ico'] = $savepath;
                    $tb_user->setData($update);
                    $tb_user->update("id=" . $this->user['user_id']);
                    ISafe::set('head_ico', $savepath);
                    break;

                default:
                    if (strstr($image, ",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    file_put_contents($savepath, base64_decode($image));//返回的是字节数
                    $tb_user = new IModel("user");
                    $update['head_ico'] = $savepath;
                    $tb_user->setData($update);
                    $tb_user->update("id=" . $this->user['user_id']);
                    ISafe::set('head_ico', $savepath);
                    break;
            }
            echo '{"status":0,"content":"上传成功",}';
        } else {*/

            if (strstr($image, ",")) {
                $image = explode(',', $image);
                $image = $image[1];
            }
            file_put_contents($savepath, base64_decode($image));//返回的是字节数
            $tb_user = new IModel("user");
            $update['head_ico'] = $savepath;
            $tb_user->setData($update);
            $tb_user->update("id=" . $this->user['user_id']);
            ISafe::set('head_ico', $savepath);
//        $savename = uniqid() . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
//        $dir = "upload";
//        $dir .= '/' . date('Y/m/d') . "/";
//        $savepath = $dir . $savename;
//
//        IFile::mkdir($dir);
//
//        $images = self::base64_to_img($image, $savepath);
//
//        $tb_user = new IModel("user");
//        $update['head_ico'] = $images;
//        $tb_user->setData($update);
//        $tb_user->update("id=".$this->user['user_id']);
//        ISafe::set('head_ico',$images);
//        if ($images) {
//            echo '{"status":1,"content":"上传成功","url":"' . $images . '"}';
//        } else {
//            echo '{"status":0,"content":"上传失败"}';
//        }
       /* }*/
        echo '{"status":0,"content":"上传成功",}';
    }

    static function base64_to_img($base64_string, $output_file)
    {
        $base64_string= explode(',', $base64_string);
        $ifp = fopen($output_file, "wb");
        fwrite($ifp, base64_decode($base64_string[1]));
        fclose($ifp);
        return ($output_file);
    }

    //修改姓名
    function info_true_name_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'true_name'    => IFilter::act( IReq::get('true_name') ,'post'),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),
        );
        echo JSON::encode($array);
    }
    //修改微信号/QQ号
    function info_wecaht_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'qq'    => IFilter::act( IReq::get('qq') ,'post'),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),
        );
        echo JSON::encode($array);
    }
    //修改手机号码
    function info_mobile_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'mobile'    => IFilter::act( IReq::get('mobile') ,'post'),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),
        );
        echo JSON::encode($array);
    }
    //修改用户类型
    function info_usertype_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'usertype'    => IFilter::act( IReq::get('usertype') ,'int'),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),
        );
        echo JSON::encode($array);
    }
    //修改公司名
    function info_companyname_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'companyname'    => IFilter::act( IReq::get('companyname') ,'post'),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),
        );
        echo JSON::encode($array);
    }

    //修改昵称
    function info_nickname_act()
    {
        $user_id = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where = 'user_id = ' . $user_id;
        $nickname = IFilter::act(IReq::get('nickname'), 'post');
        $muser=$memberObj->getObj("nickname="."'$nickname'");
        if ($muser) {
            $array = array(
                'info' => "已有相同昵称，请修改",
                'url' => IUrl::getHost() . IUrl::creatUrl('/ucenter/lbinfo_nickname'),
            );
            echo JSON::encode($array);
        } else {
            $dataArray = array(
                'nickname' => $nickname,
            );
            $memberObj->setData($dataArray);
            $memberObj->update($where);
            $array = array(
                'info' => "修改成功",
                'url' => IUrl::getHost() . IUrl::creatUrl('/ucenter/lb_info'),
            );
            echo JSON::encode($array);
        }
    }
    function info_sex_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'sex'    => IFilter::act( IReq::get('sex')),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/info'),
        );
        echo JSON::encode($array);
    }

    function info_birthday_act(){
        $user_id   = $this->user['user_id'];
        $memberObj = new IModel('member');
        $where     = 'user_id = '.$user_id;
        $dataArray       = array(
            'birthday'    => IFilter::act( IReq::get('birthday')),
        );
        $memberObj->setData($dataArray);
        $memberObj->update($where);
        $array = array(
            'info' => "修改成功",
            'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/info'),
        );
        echo JSON::encode($array);
    }

    //优惠券兑换
    public function coupon_act(){
        $user_id   = $this->user['user_id'];
        $code = IFilter::act( IReq::get('code','post'))?IFilter::act( IReq::get('code','post')):"";
        $message  = '';
        if($code == "")
        {
            $array = array(
                'info' => "请输入兑换码",
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/coupon'),
            );
            echo JSON::encode($array);
            exit;
        }
        else
        {

            $nowTime   = ITime::getDateTime();
            $propObj = new IModel('prop');
            $proRow = $propObj->getObj('card_name = "'.$code.'" and start_time <= "'.$nowTime.'" and end_time > "'.$nowTime.'"');
            if(empty($proRow))
            {
                $array = array(
                    'info' => "兑换码输入错误",
                    'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/coupon'),
                );
                echo JSON::encode($array);
                exit;
            }
            else
            {
                if($proRow['is_send'] == 1 && $proRow['is_close'] == 0 && $proRow['is_userd'] == 0 && $proRow['user_id'] == 0){
                    $update['user_id'] = $user_id;
                    $propObj->setData($update);
                    $propObj->update("id=".$proRow['id']);
                    $array = array(
                        'info' => "兑换成功",
                        'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/coupon'),
                    );
                    echo JSON::encode($array);
                    exit;
                }else{
                    $array = array(
                        'info' => "兑换码已过期",
                        'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/coupon'),
                    );
                    echo JSON::encode($array);
                    exit;
                }

            }

          }
    }


    public function history_del(){
        $user_id = $this->user['user_id'];
        $id      = IReq::get('id');

        if(!empty($id))
        {
            $id = IFilter::act($id,'int');

            $goods_historyObj = new IModel('goods_history');

            if(is_array($id))
            {
                $idStr = join(',',$id);
                $where = 'user_id = '.$user_id.' and id in ('.$idStr.')';
            }
            else
            {
                $where = 'user_id = '.$user_id.' and id = '.$id;
            }

            $goods_historyObj->del($where);
            $this->redirect('history');
        }
        else
        {
            $this->redirect('history',false);
            Util::showMessage('请选择要删除的数据');
        }
    }


    //三石 订单物流
    function orderfreight(){
        $id = IFilter::act(IReq::get('id'),'int');

        $orderObj = new order_class();
        $this->order_info = $orderObj->getOrderShow($id,$this->user['user_id']);

        if(!$this->order_info)
        {
            IError::show(403,'订单信息不存在');
        }
        $orderStatus = Order_Class::getOrderStatus($this->order_info);
        $this->setRenderData(array('orderStatus' => $orderStatus));

        $freight = $this->order_info['freight'];

        $result = freight_facade::line($freight['freight_type'],$freight['delivery_code']);
        if($result['result'] == 'success')
        {
            $this->line = $result['data'];
        }else{
            $this->line = array();
        }
        $this->redirect('orderfreight');
    }



    //地址修改 三石
    public function edit_address(){
        $data = array();
        $id   = IFilter::act(IReq::get('id'),'int');
        if($id)
        {
            //获取文章信息
            $addressObj       = new IModel('address');
            $this->Row = $addressObj->getObj("user_id=".$this->user['user_id']);
            if(!$this->Row)
            {
                IError::show(403,"地址不存在");
            }
        }
        $this->redirect('edit_address');
    }

    //拎贝常用地址修改
    public function edit_addr(){
        $contact_addr = IFilter::act(IReq::get('addr'),'post');
        $data=array('contact_addr'=>$contact_addr);
        $memberModel=new IModel('member');
        $memberModel->setData($data);
        $memberRes=$memberModel->update('user_id='.$this->user['user_id']);
        if ($memberRes){
            $array=array('info'=>'添加地址成功',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }else{
            $array=array('info'=>'添加地址失败',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_ship_address'),);
            echo JSON::encode($array);
        }
    }

    //常用收发货地址 拎贝
    public function lb_ship_address(){
        $user_id=$this->user['user_id'];
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $addressObj=new IModel('address');
        $userInfo=$addressObj->getObj('user_id='."'$user_id'");
        $this->userInfo=$userInfo;
        $this->setRenderData($data);
        $this->redirect('lb_ship_address');
    }

    //地址修改 拎贝
    public function lb_address(){
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $this->setRenderData($data);
        $this->redirect('lb_address');
    }

    //拎贝所在地修改
    public function location_save()
    {
        $address_info= IFilter::act(IReq::get('address'),'post');
        $address=str_replace(',',' ',$address_info);
        $array=array('area'=>$address);
        $memberModel=new IModel('member');
        $memberModel->setData($array);
        $memberModel->update("user_id = ".$this->user['user_id']);
        if ($memberModel){
            $array=array(
                'info'=>'添加地址成功',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }else{
            $array=array(
                'info'=>'添加地址失败',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }
    }

    public function ship_address_add(){
        $accept_name=IFilter::act(IReq::get('accept_name'),'name');
        $mobile=IFilter::act(IReq::get('mobile'),'post');
        $country_info=IFilter::act(IReq::get('address'),'post');
        $country=str_replace(',',' ',$country_info);
        $zip=IFilter::act(IReq::get('zip'),'int');
        $contact_addr=IFilter::act(IReq::get('contact_addr'),'post');
        $user_id=$this->user['user_id'];

        $addressModel=new IModel('address');
        $userObj=$addressModel->getObj('user_id='."'$user_id'");
        if ($userObj){
            $data = array('accept_name' => $accept_name, 'mobile' => $mobile,
                'country' => $country, 'zip' => $zip, 'address' => $contact_addr,);
            $addressModel->setData($data);
            $addRow = $addressModel->update('user_id='."'$user_id'");
        }else {
            $data = array('accept_name' => $accept_name, 'mobile' => $mobile,
                'country' => $country, 'zip' => $zip, 'address' => $contact_addr, 'user_id' => $user_id,);
            $addressModel->setData($data);
            $addRow = $addressModel->add();
        }
        if ($addRow){
            $array=array(
                'info'=>'添加常用地址成功',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }else{
            $array=array(
                'info'=>'添加常用地址失败',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }
    }

    //修改
    public function ship_address_edit(){
        $accept_name=IFilter::act(IReq::get('accept_name'),'name');
        $mobile=IFilter::act(IReq::get('mobile'),'post');
        $country=IFilter::act(IReq::get('address'),'post');
        $zip=IFilter::act(IReq::get('zip'),'int');
        $contact_addr=IFilter::act(IReq::get('contact_addr'),'post');
        $user_id=$this->user['user_id'];

        $addressModel=new IModel('address');
        $data=array('accept_name'=>$accept_name,'mobile'=>$mobile,
            'country'=>$country,'zip'=>$zip,'address'=>$contact_addr,'user_id'=>$user_id,);
        $addressModel->setData($data);
        $addRow=$addressModel->add();
        if ($addRow){
            $array=array(
                'info'=>'添加常用地址成功',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }else{
            $array=array(
                'info'=>'添加常用地址失败',
                'url'=> IUrl::getHost().IUrl::creatUrl('/ucenter/lb_info'),);
            echo JSON::encode($array);
        }

    }


    public function address_save()
    {
        $id          = IFilter::act(IReq::get('id'),'int');
        $accept_name = IFilter::act(IReq::get('accept_name'),'name');
        $province    = IFilter::act(IReq::get('province'),'int');
        $city        = IFilter::act(IReq::get('city'),'int');
        $area        = IFilter::act(IReq::get('area'),'int');
        $address     = IFilter::act(IReq::get('address'));
        $zip         = IFilter::act(IReq::get('zip'),'zip');
        $telphone    = IFilter::act(IReq::get('telphone'),'phone');
        $mobile      = IFilter::act(IReq::get('mobile'),'mobile');
        $default     = IReq::get('is_default')!= 1 ? 0 : 1;
        $user_id     = $this->user['user_id'];

        $model = new IModel('address');
        $data  = array('user_id'=>$user_id,'accept_name'=>$accept_name,'province'=>$province,
            'city'=>$city,'area'=>$area,'address'=>$address,'zip'=>$zip,'telphone'=>$telphone,'mobile'=>$mobile,'is_default'=>$default);

        $areasModel=new IModel('areas');
        $memberModel=new IModel('member');
        $areaProvince=$areasModel->getObj('area_id="'.$province.'"','area_name');
        $areaCity=$areasModel->getObj('area_id="'.$city.'"','area_name');
        $areaA=$areasModel->getObj('area_id="'.$area.'"','area_name');

        $datas=array('area'=>$areaProvince['area_name']."-".$areaCity['area_name']."-".$areaA['area_name']);
        $memberModel->setData($datas);
        $memberModel->update("user_id = ".$this->user['user_id']);

        $datas=array('area'=>$areaA."-".$areaCity."-".$areaProvince);


        //如果设置为首选地址则把其余的都取消首选
        if($default==1)
        {
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);
        }

        $model->setData($data);

        if($id == '')
        {
            $model->add();
        }
        else
        {
            $model->update('id = '.$id);
        }
        $result = array('result' => false,'msg' => '操作成历');
        die(JSON::encode($result));
    }

    //拎贝地址三级联动
    public function search_city()
    {
        $area_id   = IFilter::act(IReq::get("area_id"));
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = '.$area_id,'','sort desc');
        $area_name = $modelObj->query('area_id = '.$area_id);
        if($nation){
            $result = array('status' => 1,'content' =>$nation,'name'=>$area_name[0]['area_name']);
        }else{
            $result = array('status' => 2,'content' =>$nation,'name'=>$area_name[0]['area_name']);
        }
        die(JSON::encode($result));
    }


    //上传图片
    public function upload_pic_order(){
        $result = array(
            'isError' => true,
        );

        $base64_string = $_POST['base64_string'];
        $order_id      = $_POST['order_id'];

        $savename = uniqid() . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y/m/d') . "/";
        $savepath = $dir . $savename;

        IFile::mkdir($dir);

        $image = self::base64_to_img($base64_string, $savepath);

        if ($image) {
            $tb_order = new IModel("order");
            $update['order_img'] = $image;
            $tb_order->setData($update);
            $tb_order->update("id=".$order_id." and user_id=".$this->user['user_id']);
            echo '{"status":1,"content":"上传成功","url":"' . $image . '"}';
        } else {
            echo '{"status":0,"content":"上传失败"}';
        }
    }

    //积分 我的积分三石
    function exp_log()
    {
        $memberObj         = new IModel('member');
        $where             = 'user_id = '.$this->user['user_id'];
        $this->memberRow   = $memberObj->getObj($where);

        $this->redirect('exp_log');
    }
    
    function user_apply(){
    	$user_id     = $this->user['user_id'];
    	$uaObj       = new IModel('user_apply');
    	$where       = 'user_id = '.$user_id;
    	$apply_info  = $uaObj->getObj($where);
    	if(empty($apply_info) || $apply_info['status'] == -1){
    		$this->apply_info = $apply_info;
    		$this->redirect('user_apply');
    	}elseif($apply_info['status'] == 0 || $apply_info['status'] == 1 ){
    		$this->redirect('user_apply_examine');
    	}elseif($apply_info['status'] == 2 ){
    		$this->redirect('agent_Index');
    	}
    }
    function user_apply_save(){    	
    	$user_id     = $this->user['user_id'];
    	$name = IFilter::act(IReq::get('name'));
    	$company = IFilter::act(IReq::get('company'));
    	$department = IFilter::act(IReq::get('department'));
    	$mobile = IFilter::act(IReq::get('mobile'));	
    	
    	$data=array();
    	$status=true;
    	$data['status']='success';
    	$error_log="";
    	if($status&&strlen($name)<4){
    		$status=false;
    		$error_log="请完整输入姓名";
    	}    	
    	if($status&&strlen($company)<4){
    		$status=false;
    		$error_log="请完整输入公司";
    	}
    	if($status&&strlen($department)<4){
    		$status=false;
    		$error_log="请完整输入部门";
    	}    	
    	if($status&&strlen($mobile)!=11){
    		$status=false;
    		$error_log="请完整输入手机号";
    	}    	
    	
    	if($status){
    		$userObj = new IModel('user_apply');
    		$u_info = $userObj->getObj('user_id = "'.$user_id.'"','id');
    		if($u_info['status']>0){
    			$data['status']='error';
    			$data['error']="该手机号已提交请求！";
    			echo JSON::encode($data);
    			exit;
    		}
    	}
    	if($status){
    		$entry_data=array(
    				'user_id'=>$user_id,
    				'name'=>$name,
    				'company'=>$company,
    				'department'=>$department,    				
    				'mobile'=>$mobile    				
    		);
    		$tb     = new IModel('user_apply');    		
    		if(empty($u_info)){
    			$entry_data['createtime'] = date("Y-m-d H:i:s",time());
    			$tb->setData($entry_data);
    			$entry_id=$tb->add();
    		}else{
    			$entry_data['status'] = 0;
    			$tb->setData($entry_data);
    			$entry_id=$tb->update("id = ".$u_info['id']);
    		}    		
    	
    		if($entry_id){
    			
    		}else{
    			$status=false;
    			$error_log="提交申请失败";
    		}
    	}
    	if(!$status){
    		$data['status']='error';
    		$data['error']=$error_log;
    	}
    	echo JSON::encode($data);
    }

    function user_apply_examine(){
    	$user_id     = $this->user['user_id'];
    	$uaObj       = new IModel('user_apply');
    	$where       = 'user_id = '.$user_id;
    	$apply_info  = $uaObj->getObj($where);
    	if(empty($apply_info)){
    		$this->redirect('user_apply');
    	}else{
    		$this->apply_info = $apply_info;
    		$this->redirect('user_apply_examine');
    	}    	
    }
    
    function apply_return(){
    	$data=array();
    	$status=true;
    	$data['status']='success';
    	$error_log="";
    	
    	$user_id     = $this->user['user_id'];
    	$uaObj       = new IModel('user_apply');
    	$where       = 'user_id = '.$user_id;
    	$u_info  = $uaObj->getObj($where);
    	if(empty($u_info)){
    		$status=false;
    		$data['status']='error';
    		$data['error']="申请信息不存在！";
    		echo JSON::encode($data);
    		exit;
    	}
    	if($u_info['status']==2){
    		$status=false;
    		$data['status']='error';
    		$data['error']="申请已通过！";
    		echo JSON::encode($data);
    		exit;
    	}
    	if($status){
    		$tb     = new IModel('user_apply');
    		$entry_data['status'] = -1;
    		$tb->setData($entry_data);
    		$entry_id=$tb->update("id = ".$u_info['id']);
    	}
    	if(!$status){
    		$data['status']='error';
    		$data['error']=$error_log;
    	}
    	echo JSON::encode($data);
    	
    }
    
    function user_Extension(){
    	$this->redirect('user_Extension');
    }
    function agent_index(){
    	$this->redirect('agent_index');
    }
    function agent_order(){
    	$user_id     = $this->user['user_id'];    	
    	$this->start_date=$start_date = IFilter::act(IReq::get('start_date'));
    	$this->end_date=$end_date = IFilter::act(IReq::get('end_date'));
    	$this->goods_id=$goods_id = IFilter::act(IReq::get('goods_id'),'int');
    	$this->status=$status = IFilter::act(IReq::get('status'),'int');
    	$this->goods_txt = IFilter::act(IReq::get('goods_txt'));

    	
    	$where ="";
    	$time_field = "o.create_time";
    	if($status==0){
    		$where.=" and (o.status = 2 or o.status = 5) ";//已支付+已完成
    		$time_field = "o.create_time";
    	}else{
    		$where.=" and o.status = $status ";//已支付或已完成
    	}
    	if(strlen($start_date)>0){
    		$where.=" and DATE_FORMAT(".$time_field.",'%Y-%m-%d')<='".$start_date."'";
    	}
    	if(strlen($end_date)>0){
    		$where.=" and DATE_FORMAT(".$time_field.",'%Y-%m-%d')<='".$end_date."'";
    	}
    	if($goods_id>0){
    		$where.=" and og.goods_id =".$goods_id;
    	}else{
    		$this->goods_id = 0;
    		$this->goods_txt = '全部';
    	}
    	if($user_id){
    		$mObj       = new IModel('member');
    		$m_info  = $mObj->getObj(' user_id = '.$user_id);
    		if(!empty($m_info) && !empty($m_info['tg_code'])){    			 
    			$orderM = new IQuery('order_goods og');
    			$orderM->join = ' left join order as o on o.id = og.order_id left join goods as g on g.id = og.goods_id left join user as u on u.id = o.user_id';
    			$orderM->fields =" DATE_FORMAT(o.create_time,'%Y-%m-%d') creatime, o.status,g.name,og.goods_nums,u.username";
    			$orderM->where = " og.agent_code = '".$m_info['tg_code']."' ".$where;
    			$orderM->order = " og.id desc";
    			$list = $orderM->find();
    			$this->list = $list;
    			    			
    			$ogM = new IQuery('order_goods og');
    			$ogM->join = ' left join order as o on o.id = og.order_id left join goods as g on g.id = og.goods_id ';
    			$ogM->fields ="g.id,g.name";
    			$ogM->where = " og.agent_code = '".$m_info['tg_code']."' ";//.$where
    			$ogM->order = " g.id desc";
    			$goodslist = $ogM->find();
    			$this->goodslist = $goodslist;
    			
    		}
    	}    	 
    	$this->redirect('agent_order');
    }
    function agent_customer(){
    	$user_id     = $this->user['user_id'];
    	if($user_id){
    		$mObj       = new IModel('member');
    		$m_info  = $mObj->getObj(' user_id = '.$user_id);
    		if(!empty($m_info) && !empty($m_info['tg_code'])){
    			$orderM = new IQuery('order_goods og');
    			$orderM->join = ' left join order as o on o.id = og.order_id left join goods as g on g.id = og.goods_id left join user as u on u.id = o.user_id';
    			$orderM->fields =" DATE_FORMAT(o.create_time,'%Y-%m-%d') creatime, o.status,g.name,og.goods_nums,u.username";
    			$orderM->where = " og.agent_code = '".$m_info['tg_code']."' ";
    			$orderM->order = " og.id desc";
    			$list = $orderM->find();
    		}
    	
    	}
    	$this->list = $list;
    	$this->redirect('agent_customer');
    }
    function agent_poster(){
    	$aptM = new IQuery('agent_poster_template');
    	$aptM->where = " status = 1";
    	$aptM->order = " sort desc";
    	$list = $aptM->find();
    	$this->list = $list;
    	$this->default_id = $list[0]['id'];
    	$this->default_img = "/".$list[0]['show_img_url'];
    	$this->redirect('agent_poster');
    }
    
    
    
    function get_agent_poster(){    	
    	$user_id     = $this->user['user_id'];
    	$template_id = IFilter::act(IReq::get('tid'));    	
    	$pObj       = new IModel('agent_user_poster');    	
    	$p_info  = $pObj->getObj('user_id = '.$user_id.' and template_id= '.$template_id );
    	if(!empty($p_info)&& !empty($p_info['img_url'])){
    		$this->poster_img = $p_info['img_url'];    		
    	}else{
    		$re = $this->create_agent_poster($template_id);
    		if($re==1){
    			$p_info  = $pObj->getObj('user_id = '.$user_id.' and template_id= '.$template_id );
    			$this->poster_img = $p_info['img_url'];
    		}else{
    			//失败
    		}
    	}
    	$this->redirect('get_agent_poster');
    }
    function create_agent_poster($template_id){
    	$user_id     = $this->user['user_id'];
    	$head_img     = $this->user['head_ico'];//头像
    	if($user_id>0){
    		$uaObj       = new IModel('user_apply');
    		$ua_info  = $uaObj->getObj('user_id = '.$user_id." and status =2 ");
    		if(!empty($ua_info)){
    			$user_name = $ua_info['name'];//显示名称    		
    			$mObj       = new IModel('member');
    			$m_info  = $mObj->getObj('user_id = '.$user_id);
    			if(!empty($m_info)&& !empty($m_info['ticket_img'])){
    				$ewm_img_url=$m_info['ticket_img'];//二维码图片
    		
    				$pObj       = new IModel('agent_poster_template');
    				$p_info  = $pObj->getObj('id = '.$template_id);
    				if(!empty($p_info) && !empty($p_info['img_url'])){
    					$t_img_url=$p_info['img_url'];//海报底
    					$img_url = $this->compose_poster($p_info,$user_id,$user_name,$head_img,$ewm_img_url);
    					if(strlen($img_url)>0){
    						$aup_data=array(
    								'template_id'=>$template_id,
    								'user_id'=>$user_id,
    								'name'=>$p_info['name'],
    								'img_url'=>$img_url,
    								'createtime'=>date("Y-m-d H:i:s",time())
    						);
    						$auptb     = new IModel('agent_user_poster');
    						$auptb->setData($aup_data);
    						//$aup_id=$auptb->add();
    						$aup_id= 1;
    						if($aup_id>0){
    							return "1";
    						}else{
    							return "保存失败";
    						}
    					}else{
    						return "海报生成失败";
    					}
    				}else{
    					return "所选海报模板丢失";
    				}
    			}else{
    				return "专属二维码丢失";
    			}
    		
    		}else{
    			return "您还未申请或申请未通过审核";
    		}
    	}else{
    		return "登录信息丢失";
    	}    	
    	
    }
    function compose_poster($pObj,$user_id,$user_name,$head_img,$ewm_img_url){
    	$imageDir=IWeb::$app->getBasePath().PhotoUpload::hashDir();//
    	file_exists($imageDir) ? '' : IFile::mkdir($imageDir);//检测目录
    	$filename="agent_".$this->user['user_id']."_".date('YmdHis');
    	$file_str=$imageDir."/".$filename;
    	//获取该用户的带参的二维码
    	$head_img=Thumb::get("/".$head_img,80,80);//生成头像缩略图
    	$qr_path=Thumb::get("/".$ewm_img_url,300,300); //生成二维码缩略图
    	// 获取内容信息
    	
    	//$str_path='/lyjk_hl';//本地
    	$str_path='';    	// 服务器
    	
    	//$font_content_img=$_SERVER['DOCUMENT_ROOT'].$str_path.'/'.$head_img;//头像
    	// 合并图片
    	//$dst_path = 'dst.jpg';
    	$dst_path = $_SERVER['DOCUMENT_ROOT'].$str_path.'/'.$pObj['img_url'];//底图片
    	//创建图片的实例
    	$dst = imagecreatefromstring(file_get_contents($dst_path));
    	//打上文字
    	$font = $_SERVER['DOCUMENT_ROOT'].$str_path.'/upload/agent/template/SIMSUN.TTC';//字体    	
    	$black = imagecolorallocate($dst, $pObj['font_r'],$pObj['font_g'],$pObj['font_b']);//字体颜色    	
    	$head = imagecreatefromstring(file_get_contents($head_img));    	
    	$qr = imagecreatefromstring(file_get_contents($qr_path));
    	imagefttext($dst, 18, 0, $pObj['x1'], $pObj['y1'], $black, $font, $user_name);// 打上称呼文字
    	//获取水印图片的宽高
    	list($font_w, $font_h) = getimagesize($head_img);
    	//如果水印图片本身带透明色，则使用imagecopy方法
    	imagecopy($dst, $head, $pObj['x2'], $pObj['y2'], 0, 0, $font_w, $font_h); // 打上头像图片
    	list($qr_w, $qr_h) = getimagesize($qr_path);
    	imagecopymerge($dst, $qr, $pObj['x3'], $pObj['y3'], 0, 0, $qr_w, $qr_h, 100);//打上二维码
    	//输出图片
    	list($dst_w, $dst_h, $dst_type) = getimagesize($dst_path);
    	switch ($dst_type) {
    		case 1://GIF
    			imagegif($dst,$file_str.".gif");
    			break;
    		case 2://JPG
    			imagejpeg($dst,$file_str.".jpg");
    			break;
    		case 3://PNG
    			imagepng($dst,$file_str.".png");
    			break;
    		default:
    			break;
    	}
    	imagedestroy($dst);
    	imagedestroy($head_img);
    	imagedestroy($qr);
    	$agent_hb=PhotoUpload::hashDir()."/".$filename.".jpg";
    	return $agent_hb;
    }




    /**
     * GZY *********************************************************************************************************
     */

    /**
     * 货源 求购 匹配情况列表
     */


    function matchinglist(){

        //用户的id
        $user_id=$this->user["user_id"];
        //求购还是货源类型
        $type=IFilter::act(IReq::get('type'));
        //求购进度的状态
        //$status=2;

        //*********声明表操作类***********//
        $tb_release_information = new IModel('release_information');
        $goods = new IModel('goods');//临时存储在这个备用表中,稍后再移入到正式的数据库
        $matching = new IModel('matching');
        $tbgoodsapply = new IModel('goods_apply');
        $goods_area = new IModel('goods_area');  //地址待定 --》确定一个发布一个求购只有一个地址
        $tbareas=new IModel("areas");
        //*********声明表操作类***********//

        $mlist=$tb_release_information->query("user_id='".$user_id."' and type='".$type."' and is_delete = 0 ","","up_time desc",$this->pagesize);


        /**
         * 根据商品码 获取到要显示到页面上的 商品的名字 以及 商品的 宣传图片
         */

            for ($i=0;$i<count($mlist);$i++){

            if($mlist[$i]["status"]>=2){
               //读取商品表中的数据
               $good=$goods->getObj("goods_no='".$mlist[$i]["goods_no"]."'","name,img,goods_no,brand");
            }else{
                //读取审核商品表中的数据(图片为逗号分隔的字符串)
                $good=$tbgoodsapply->getObj("id='".$mlist[$i]["apply_id"]."'","name,img,goods_no,brand");

                $pos = strpos($good["img"], ",");
                if($pos){
                    $img=explode(',',$good["img"]);
                    $good["img"]=$img[0];
                }else{

                }
            }

            $mlist[$i]["good"]=$good;           //将商品的名称以及原图存入发布列表中，然后返回到前台。
            $matchingnum=$matching->query("release_information_id='".$mlist[$i]["id"]."'","count(*) as count");
            $mlist[$i]["matchingnum"]=$matchingnum[0]["count"];  //匹配上的人数
            //地址()

            $area_id_array=explode(",",$mlist[$i]["area_id"]);
                $areaname="";
                for ($o=0;$o<count($area_id_array);$o++){
                    $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
                    $areaname .=$areasinfo["area_name"]." ";
                }
                $areaname=substr($areaname, 0, -1);
                $mlist[$i]["areaname"]=$areaname;

            /*$goodareainfo=$goods_area->getObj("release_id='".$mlist[$i]["id"]."'","pro_id");
            $areasinfo=$tbareas->getObj("area_id='".$goodareainfo["pro_id"]."'","area_name");
            $mlist[$i]["areaname"]=$areasinfo["area_name"];*/

        }

        $returnarray=array();
        $returnarray["mlist"]=$mlist;  //求购或者货源的list
        $returnarray["type"]=$type;    //求购还是货源状态

        //将要返回的数据key设置成mlist
        $data['mlist'] = $returnarray;
        $this->setRenderData($data);


        $this->redirect('lb_mypublish',false);

    }

    /**
     * @throws IException
     * 加载更多
     */
    function looding(){
        $page = $_POST['page'];
        $type = $_POST['type'];
        if(is_null($page)){
            $page=0;
        }else{
            $page=$page*$this->pagesize;
        }
        //用户的id
        $user_id=$this->user["user_id"];
        //求购还是货源类型
//        $type=IFilter::act(IReq::get('type'));
        //求购进度的状态
        //$status=2;

        //*********声明表操作类***********//
        $tb_release_information = new IModel('release_information');
        $goods = new IModel('goods');//临时存储在这个备用表中,稍后再移入到正式的数据库
        $matching = new IModel('matching');
        $tbgoodsapply = new IModel('goods_apply');
        $goods_area = new IModel('goods_area');  //地址待定 --》确定一个发布一个求购只有一个地址
        $tbareas=new IModel("areas");
        //*********声明表操作类***********//

        $sql="SELECT * FROM `lb_release_information` WHERE  user_id ='".$user_id."'  and type='".$type."' and is_delete = 0 ORDER by update_time desc LIMIT ".$page.",".($this->pagesize)." ";

        $mlist=IDBFactory::getDB('shop')->query($sql);


//        $mlist=$tb_release_information->query("user_id='".$user_id."' and type='".$type."' and is_delete = 0","","up_time desc","");

        /**
         * 根据商品码 获取到要显示到页面上的 商品的名字 以及 商品的 宣传图片
         */

        for ($i=0;$i<count($mlist);$i++){

            if($mlist[$i]["status"]>=2){
                //读取商品表中的数据
                $good=$goods->getObj("goods_no='".$mlist[$i]["goods_no"]."'","name,img,goods_no,brand");
            }else{
                //读取审核商品表中的数据(图片为逗号分隔的字符串)
                $good=$tbgoodsapply->getObj("id='".$mlist[$i]["apply_id"]."'","name,img,goods_no,brand");

                $pos = strpos($good["img"], ",");
                if($pos){
                    $img=explode(',',$good["img"]);
                    $good["img"]=$img[0];
                }else{

                }
            }

            $mlist[$i]["good"]=$good;           //将商品的名称以及原图存入发布列表中，然后返回到前台。
            $matchingnum=$matching->query("release_information_id='".$mlist[$i]["id"]."'","count(*) as count");
            $mlist[$i]["matchingnum"]=$matchingnum[0]["count"];  //匹配上的人数

            //地址()
            $area_id_array=explode(",",$mlist[$i]["area_id"]);
            $areaname="";
            for ($o=0;$o<count($area_id_array);$o++){
                $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
                $areaname .=$areasinfo["area_name"]." ";
            }
            $areaname=substr($areaname, 0, -1);
            $mlist[$i]["areaname"]=$areaname;


            /*$goodareainfo=$goods_area->getObj("release_id='".$mlist[$i]["id"]."'","pro_id");
            $areasinfo=$tbareas->getObj("area_id='".$goodareainfo["pro_id"]."'","area_name");
            $mlist[$i]["areaname"]=$areasinfo["area_name"];*/

        }

        $returnarray=array();
        $returnarray["mlist"]=$mlist;  //求购或者货源的list
        $returnarray["type"]=$type;    //求购还是货源状态

        //将要返回的数据key设置成mlist
        $data['mlist'] = $returnarray;
        //$this->setRenderData($data);
        //$this->redirect('lb_mypublish',false);

        echo JSON::encode($returnarray);
    }



    /**
     * 查看匹配的人数详情
     */

    function matchingdetailslist(){

        //获取到点击的发布的id
        $infomationid=IFilter::act(IReq::get('infomationid'));


        //*********声明表操作类***********//
        $user=new IModel("user");
        $tbareas=new IModel("areas");
        $goods = new IModel('goods');
        $member=new IModel("member");
        $matching = new IModel('matching');
        $goods_area = new IModel('goods_area');  //地址待定 --》确定一个发布一个求购只有一个地址

        $tb_release_information = new IModel('release_information');
        //*********声明表操作类***********//

        //根据发布的id  查询匹配的id的集合
        $matchingidlist=$matching->query("release_information_id='".$infomationid."'","","create_time"); //查询匹配到的用户 时间倒序排列
        $matchingobjectarray=array();
        for ($i=0;$i<count($matchingidlist);$i++){
            $information=$tb_release_information->getObj("id='".$matchingidlist[$i]["matching_object_id"]."'");
            $userinfo=$user->getObj("id='".$information["user_id"]."'");
            //$usermember=$member->getObj("user_id='".$information["user_id"]."'","nickname");

            //地址
            $area_id_array=explode(",",$information["area_id"]);
            $areaname="";
            for ($o=0;$o<count($area_id_array);$o++){
                $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
                $areaname .=$areasinfo["area_name"]." ";
            }
            $areaname=substr($areaname, 0, -1);
            $userinfo["areaname"]=$areaname;


            /*$goodareainfo=$goods_area->getObj("release_id='".$information["id"]."'","pro_id");
            $uareasinfo=$tbareas->getObj("area_id='".$goodareainfo["pro_id"]."'","area_name");
            $userinfo["areaname"]=$uareasinfo["area_name"];*/

            $information["userinfo"]=$userinfo;
            $information["up_time"]=$matchingidlist[$i]["create_time"];
            $matchingobjectarray[]=$information;
        }
        $backtrackparameter=array();
        $infomationinfo=$tb_release_information->getObj("id='".$infomationid."'");


        $area_id_array=explode(",",$infomationinfo["area_id"]);
        $areanameone="";
        for ($o=0;$o<count($area_id_array);$o++){
            $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
            $areanameone .=$areasinfo["area_name"]." ";
        }
        $areanameone=substr($areanameone, 0, -1);
        $infomationinfo["areaname"]=$areanameone;

        /*$goodareainfo=$goods_area->getObj("release_id='".$infomationinfo["id"]."'","pro_id");
        $areasinfo=$tbareas->getObj("area_id='".$goodareainfo["pro_id"]."'","area_name");
        $infomationinfo["areaname"]=$areasinfo["area_name"];*/

        $goodinfo=$goods->getObj("goods_no='".$infomationinfo["goods_no"]."'","name,img");
        $infomationinfo["good_name"]=$goodinfo["name"];
        $infomationinfo["good_img"]=$goodinfo["img"];

        /**
         *  接下来就是将数据推送到前台然后显示到页面上 欧克
         */

        //$matchingobjectarray 是匹配的人数的详情数组
        $backtrackparameter["infomationinfo"]=$infomationinfo;
        $backtrackparameter["matchingobjectarray"]=$matchingobjectarray;


        $data['mlist'] = $backtrackparameter;
        $this->setRenderData($data);

        $this->redirect('lb_mymatchingresult',false);


    }




    /**
     * 我的收藏
     */

    function myselfcollect(){


        $tbarea=new IModel("areas");
        $tbgoods=new IModel("goods");
        $tbfavorite=new IModel("favorite");
        $tbgoodsarea=new IModel("goods_area");
        $tbrelease_information=new IModel("release_information");


        //获取到登录者本人的id
        $admin_id=$this->user['user_id'];

        //根据id获取收藏表中的所有收藏过的发布信息
        $myselfcollectlist=$tbfavorite->query("user_id='".$admin_id."'","rid");

        //声明两个数组(求购,货源.数组)
        $buylist=array();
        $selllist=array();


        //遍历获取发布的信息  以及  发布涉及的  商品
        for ($i=0;$i<count($myselfcollectlist);$i++){
            $informationinfo=$tbrelease_information->getObj("id='".$myselfcollectlist[$i]["rid"]."'","id,up_time,price,goods_no,type");
            $goodsinfo=$tbgoods->getObj("goods_no='".$informationinfo["goods_no"]."'","name,img");
            $informationinfo["goodname"]=$goodsinfo["name"];
            $informationinfo["goodimg"]=$goodsinfo["img"];

            $area_id_array=explode(",",$informationinfo["area_id"]);
            $areanameone="";
            for ($o=0;$o<count($area_id_array);$o++){
                $areasinfo=$tbarea->getObj("area_id='".$area_id_array[$o]."'","area_name");
                $areanameone .=$areasinfo["area_name"]." ";
            }
            $areanameone=substr($areanameone, 0, -1);
            $informationinfo["areaname"]=$areanameone;

            /*$goodarea=$tbgoodsarea->getObj("release_id='".$informationinfo["id"]."'","pro_id");
            $area=$tbarea->getObj("area_id='".$goodarea["pro_id"]."'","area_name");
            $informationinfo["areaname"]=$area["area_name"];*/


            $myselfcollectlist[$i]["informationinfo"]=$informationinfo;

            //如果标识为求购
            if($informationinfo["type"]==='0'){
                $buylist[] =$myselfcollectlist[$i];
            }else if($informationinfo["type"]==='1'){
                $selllist[] =$myselfcollectlist[$i];
            }else{

            }
        }

        //返回数组
        $returnarray=array();
        $returnarray["buylist"]=$buylist;
        $returnarray["selllist"]=$selllist;


        $data['mlist'] = $returnarray;
        $this->setRenderData($data);

        $this->redirect('lb_myselfcollect',false);

    }


    /**
     *  我的收藏中 点击收藏的时候 删除收藏
     */

    function deletecollect(){

        $tbfavorite=new IModel("favorite");

        $informationid = IFilter::act(IReq::get("informationid"));

        //拿到登录人的id  发布id (然后将信息存入收藏表)
        $userid =$this->user["user_id"];

        $del=$tbfavorite->del("user_id='".$userid."' and rid='".$informationid."'");


        if($del===true){
            die("1");
        }else{
            die("0");
        }

    }





    /**
     *  个人主页
     */

    function userhomepage(){

        $adminuserid=$this->user["user_id"];

        //用户的id  点击的用户的id
        $userid=IFilter::act(IReq::get('userid'));

        //*********声明表操作类***********//
        $tbuser=new IModel("user");
        $tbgoods=new IModel("goods");
        $tbareas=new IModel("areas");
        $tbusermember=new IModel("member");
        $tbfavorite=new IModel("favorite");
        $tbgoods_area=new IModel("goods_area");
        $tbrelease_information=new IModel("release_information");
        //*********声明表操作类***********//

        $userinfo=$tbuser->getObj("id='".$userid."'");//用户信息
        $usermemberinfo=$tbusermember->getObj("user_id='".$userid."'","companyname,area");//用户详情信息
        $areasinfo=$tbareas->getObj("area_id='".$usermemberinfo["area"]."'","area_name");
        $userinfo["companyname"]=$usermemberinfo["companyname"];
        $userinfo["area"]=$usermemberinfo["area"];

        $returndataarray=array();

        //根据用户id获取发布信息(求购集合)
        $information_buylist=$tbrelease_information->query("user_id='".$userid."' and type='0' and status='2'","id,up_time,price,goods_no,area_id");
        for ($i=0;$i<count($information_buylist);$i++){
            $buygoodsinfo=$tbgoods->getObj("goods_no='".$information_buylist[$i]["goods_no"]."'","name,img");
            //商品的图片以及名字
            $information_buylist[$i]["goodsname"]=$buygoodsinfo["name"];
            $information_buylist[$i]["goodsimg"]=$buygoodsinfo["img"];
            //求购的地址
            $area_id_array=explode(",",$information_buylist[$i]["area_id"]);
            $areanamebuy="";
            for ($o=0;$o<count($area_id_array);$o++){
                $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
                $areanamebuy .=$areasinfo["area_name"]." ";
            }
            $areanamebuy=substr($areanamebuy, 0, -1); //去掉末尾的逗号
            $information_buylist[$i]["areaname"]=$areanamebuy;

            /*$buygoods_areainfo=$tbgoods_area->getObj("release_id='".$information_buylist[$i]["id"]."'","pro_id");
            $areainfo=$tbareas->getObj("area_id='".$buygoods_areainfo["pro_id"]."'","area_name");
            $information_buylist[$i]["areaname"]=$areainfo["area_name"];*/

            $buyfavoriteinfo=$tbfavorite->query("user_id='".$adminuserid."' and rid='".$information_buylist[$i]["id"]."'");
            if(count($buyfavoriteinfo)==0){
                $information_buylist[$i]["iscollect"]='0';
            }else{
                $information_buylist[$i]["iscollect"]='1';
            }
        }

        //根据用户id获取发布信息(货源集合)
        $information_selllist=$tbrelease_information->query("user_id='".$userid."' and type='1' and status='2'","id,up_time,price,goods_no,area_id");
        for ($j=0;$j<count($information_selllist);$j++){
            $sellgoodsinfo=$tbgoods->getObj("goods_no='".$information_selllist[$j]["goods_no"]."'","name,img");
            //商品的图片以及名字
            $information_selllist[$j]["goodsname"]=$sellgoodsinfo["name"];
            $information_selllist[$j]["goodsimg"]=$sellgoodsinfo["img"];
            //求购的地址

            $area_id_array=explode(",",$information_selllist[$j]["area_id"]);
            $areanamesell="";
            for ($o=0;$o<count($area_id_array);$o++){
                $areasinfo=$tbareas->getObj("area_id='".$area_id_array[$o]."'","area_name");
                $areanamesell .=$areasinfo["area_name"]." ";
            }
            $areanamesell=substr($areanamesell, 0, -1);
            $information_selllist[$j]["areaname"]=$areanamesell;

            /*$selllgoods_areainfo=$tbgoods_area->getObj("release_id='".$information_selllist[$j]["id"]."'","pro_id");
            $areainfo=$tbareas->getObj("area_id='".$selllgoods_areainfo["pro_id"]."'","area_name");
            $information_selllist[$j]["areaname"]=$areainfo["area_name"];*/

            $selllfavoriteinfo=$tbfavorite->query("user_id='".$adminuserid."' and rid='".$information_selllist[$j]["id"]."'");
            if(count($selllfavoriteinfo)==0){
                $information_selllist[$j]["iscollect"]='0';
            }else{
                $information_selllist[$j]["iscollect"]='1';
            }
        }

        $returndataarray["userinfo"]=$userinfo;
        $returndataarray["buylist"]=$information_buylist;
        $returndataarray["selllist"]=$information_selllist;

        $data['mlist'] = $returndataarray;
        $this->setRenderData($data);

        $this->redirect('lb_userhomepage',false);


    }



    /**
     * 个人主页中 点击 收藏的 时候(点击一次收藏,再点击一次取消收藏)
     */

    function collect(){

        $tbfavorite=new IModel("favorite");

        //拿到登录人的id  发布id (然后将信息存入收藏表)
        $userid =$this->user["user_id"];

        $informationid=IFilter::act(IReq::get('informationid'));

        $type=null;

        $selllfavoriteinfo=$tbfavorite->query("user_id='".$userid."' and rid='".$informationid."'");

        if(count($selllfavoriteinfo)==0){
            //不存在(新增收藏)

            $content=array(
                "user_id"=>$userid,
                "rid"=>$informationid,
                "time"=>ITime::getDateTime()
            );

            $tbfavorite->setData($content);
            $tbfavorite->add();

            $type='1';

        }else{
            //存在删除收藏
            $tbfavorite->del("user_id='".$userid."' and rid='".$informationid."'");
            $type='0';
        }

        //不刷新页面(将显示与否的type值传入前端)
        die($type);
    }


    /**
     * 获取登录用户的所有的好友
     */
    function friendlist(){


        $tbplatformmessage=new IModel("platform_message");
        $adminuser_id=$this->user["user_id"];

        $appKey = 'efbdad17a9be59ad08af28a0';     //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';   //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);

        $rescource = new Resource($jm);
        $friend = new Friend($jm);
        $user="hahcc";
        $response = $friend->listAll($user);     //获取user的所有的好友
        $tbuser=new IModel("user");

        for ($i=0;$i<count($response["body"]);$i++){
            $userheadinfo=$tbuser->getObj("username='".$response["body"][$i]["username"]."'","id,head_ico");
            $response["body"][$i]["user_id"]=$userheadinfo["id"];
            $response["body"][$i]["url"]=$userheadinfo["head_ico"];
        }

        //获取平台未读消息个数
        $unreadplatformmessagenum=$tbplatformmessage->query("user_id='".$adminuser_id."' and  isNull(modify_time) ");
        $unreadplatformmessagenum=count($unreadplatformmessagenum);


        $returnarray=array();
        $returnarray["cssfile"]= $response["body"];
        $returnarray["type"]= '1';
        $returnarray["unreadplatformmessagenum"]= $unreadplatformmessagenum; //平台未读消息
        $data["mlist"]=$returnarray;

        $this->setRenderData($data);

        $this->redirect('platformmessage',false);

    }


    /**
     * 将平台消息未读的 制成 已读
     */
    function readplatformmessage(){

        $tbplatformmessage=new IModel("platform_message");


        $messageid   = IFilter::act(IReq::get("messageid"));
        //点击平台消息的时候，将消息已读（也就是将修改时间填成当前时间）


        $content=array(
            "modify_time"=>ITime::getDateTime()
        );


        $tbplatformmessage->setData($content);
        $tbplatformmessage->update("id='".$messageid."'");
        $this->redirect('platformmessage');

    }


    /**
     * 获取平台的消息
     */
    function platformmessage(){

        //根据登录人的id查询所有的平台推送的消息
        $adminuser_id=$this->user["user_id"];
        $tbplatformmessage=new IModel("platform_message");
        $platformmessagelist=$tbplatformmessage->query("user_id='".$adminuser_id."'");


        //获取平台未读消息
        $unreadplatformmessagenum=$tbplatformmessage->query("user_id='".$adminuser_id."' and isNull(modify_time) ");
        $unreadplatformmessagenum=count($unreadplatformmessagenum);


        $returnarray=array();
        $returnarray["type"]= '2';
        $returnarray["platformmessagelist"]=$platformmessagelist;
        $returnarray["unreadplatformmessagenum"]= $unreadplatformmessagenum; //平台未读消息
        $data["mlist"]=$returnarray;

        $this->setRenderData($data);

        $this->redirect('platformmessage',false);

    }




    /**
     * GZY *********************************************************************************************************
     */







































































    /**
     * SXY *********************************************************************************************************
     */

    /**
     * 发布货源
     */
    public function release_index()
    {
        $goods_id   = IFilter::act(IReq::get("goods_id"));
        //如果商品ID存在
        if($goods_id){
            $goods_model = new IModel('goods');
            $goodsWhere = " id = '".$goods_id."' ";
            $data['apply_info'] = $goods_model->getObj($goodsWhere);
            $data['goods_id'] = $goods_id;
            //查询图片
            $goods_class = new goods_class();
            $goods_info = $goods_class->edit($goods_id);
            $data['img_path'] = $goods_info['goods_photo'];
        }
        $data['type'] = 1;//可编辑
        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        ///upload/2020-01-03/5e0eb0959b0be.jpeg
        $this->setRenderData($data);
        $this->redirect('release_index');
    }

    /**
     *  发布求购
     */
    function release_seek()
    {
        $goods_id   = IFilter::act(IReq::get("goods_id"));
        if($goods_id){
            $goods_model = new IModel('goods');
            $goodsWhere = " id = '".$goods_id."' ";
            $data['apply_info'] = $goods_model->getObj($goodsWhere);
            $data['goods_id'] = $goods_id;
            //查询图片
            $goods_class = new goods_class();
            $goods_info = $goods_class->edit($goods_id);
            $data['img_path'] = $goods_info['goods_photo'];
        }
        $data['type'] = 1;//可编辑
        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $this->setRenderData($data);
        $this->redirect('release_seek');
    }

    /**
     *  AJAX城市联动
     */
    public function release_search_city()
    {
        $area_id   = IFilter::act(IReq::get("area_id"));
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = '.$area_id,'','sort desc');
        $area_name = $modelObj->query('area_id = '.$area_id);
        if($nation){
            $result = array('status' => 1,'content' =>$nation,'name'=>$area_name[0]['area_name']);
        }else{
            $result = array('status' => 2,'content' =>$nation,'name'=>$area_name[0]['area_name']);
        }
        die(JSON::encode($result));
    }

    /**
     *  图片上传
     */
    //上传图片
    public function upload_pic(){
        $base64_string = $_POST['base64_string'];

        $path='upload/'.date("Y-m-d")."/";
        if (!is_dir($path)){
            $dirs = iconv("UTF-8", "GBK", $path);//文件夹路径
            mkdir ($dirs,0777,true);
        }
        $savename = uniqid() . '.jpg';//localResizeIMG压缩后的图片都是jpg格式

        $savepath = $path . $savename;

        $image = self::base64_to_img($base64_string, $savepath);
        if($image){
            echo '{"flag":1,"img":"' . $image . '"}';die;
        }else{
            echo '{"flag":2,"img":"' . $image . '"}';die;
        }

    }

    /**
     * 货源修改页
     */
    function release_edit()
    {
        $id = IFilter::act(IReq::get("release_id"));
        //查询发布信息
        $model_release = new IModel('release_information');
        $goodsWhere = " id = {$id} ";
        $apply_info = $model_release->getObj($goodsWhere);//一维
        $goods_class = new goods_class();
        //判断该信息是否通过审核，未通过商品信息存在于goods_apply表
        if($apply_info['status'] == '0' || $apply_info['status'] == '1'){
            //查询商品信息
            $model_apply = new IModel('goods_apply');
            $goods_info = $model_apply->getObj("goods_no = '".$apply_info['goods_no']."'");

        }else{
            $data['is_goods'] = 1;
            $model_goods = new IModel('goods');
            $goods_info = $model_goods->getObj("goods_no = '".$apply_info['goods_no']."'");
            $data['goods_info_id'] = $goods_info['id'];
        }
        //查询起购信息
        $model_section = new IModel('release_section');
        $data['section_info']  = $model_section->query("release_id=".$id,'','id');//二维
        $data['section_num'] = (int)count($data['section_info']);

        //查询图片
        $img = $goods_info['img'];
        $data['img_path'] = explode(',',$img);

        $data['goods_info'] = $goods_info;
        //查询地址信息(空格隔开的字符串)
        $data['area_info'] = $goods_class->area_name($apply_info['area_id']);

        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $data['apply_info'] = $apply_info;
        $this->setRenderData($data);
        $this->redirect('release_edit');
    }

    /**
     * 求购修改页
     */
    function release_seek_edit()
    {
        $id = IFilter::act(IReq::get("release_id"));
        //查询发布信息
        $model_release = new IModel('release_information');
        $goodsWhere = " id = {$id} ";
        $apply_info = $model_release->getObj($goodsWhere);//一维
        $goods_class = new goods_class();
        //判断该信息是否通过审核，未通过商品信息存在于goods_apply表
        if($apply_info['status'] == '0' || $apply_info['status'] == '1'){
            //查询商品信息
            $model_apply = new IModel('goods_apply');
            $goods_info = $model_apply->getObj("goods_no = '".$apply_info['goods_no']."'");

            //查询起购信息
            $model_section = new IModel('release_section');
            $data['section_info']  = $model_section->query("release_id=".$id);//二维
            $data['section_num'] = (int)count($data['section_info']);

            //查询图片
            $img = $goods_info['img'];
            $data['img_path'] = explode(',',$img);
        }else{
            $data['is_goods'] = 1;
            $model_goods = new IModel('goods');
            $goods_info = $model_goods->getObj("goods_no = '".$apply_info['goods_no']."'");
            $data['goods_info_id'] = $goods_info['id'];

            //查询图片
            $img = $goods_info['img'];
            $data['img_path'] = explode(',',$img);
        }
        $data['goods_info'] = $goods_info;
        //查询地址信息(空格隔开的字符串)
        $data['area_info'] = $goods_class->area_name($apply_info['area_id']);

        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $data['apply_info'] = $apply_info;
        $this->setRenderData($data);
        $this->redirect('release_seek_edit');
    }

    /**
     *AJAX校验条形码
     */
    function check_code()
    {
        $goods_bar_code   = IFilter::act(IReq::get("code"));//商品条形码

        $goods_model = new IModel('goods');
        $goodsWhere = " goods_no = '".$goods_bar_code."' ";
        $goods_info = $goods_model->getObj($goodsWhere);
        if($goods_info) {
            //商品表中存在该商品
            echo '{"status":2,"id":"' . $goods_info['id'] . '"}';
            die;
        }
    }

    /**
     * ajax校验发布是否存在
     */
    function check_goods()
    {
        $goods_no   = IFilter::act(IReq::get("goods_no"));//商品条形码
        $area_id   = IFilter::act(IReq::get("area_id"));//地址ID
        $type   = IFilter::act(IReq::get("type"));//类型
        if($goods_no == ''){
            die;
        }
        //根据商品编号与登录者ID搜索发布信息
        $release_model = new IModel('release_information');
        $release_where = "goods_no = '".$goods_no."' and type= '".$type."' and user_id = '".$this->user['user_id']."' and area_id = '".$area_id."'";
        $release_info = $release_model->query($release_where);
        if($type == 1){
            $msg = '已发布过此商品同货源地信息，是否继续发布';
        }else{
            $msg = '已发布过此商品同求购地信息，是否继续发布';
        }
        if($release_info){
            $result = array('status' => 1,'msg' => $msg);
            die(JSON::encode($result));
        }
    }

    /**
     *提交货源信息
     */
    function commit()
    {
        //获取页面传递信息
        $name   = IFilter::act(IReq::get("name"));
        $brand   = IFilter::act(IReq::get("brand"));
        $goods_no   = IFilter::act(IReq::get("goods_no"));
        $content   = IFilter::act(IReq::get("content"));
        $img   = IFilter::act(IReq::get("img"));
        $area_id   = IFilter::act(IReq::get("area_id"));
        $num_min1   = (int)IFilter::act(IReq::get("num_min1"));
        $num_max1   = (int)IFilter::act(IReq::get("num_max1"));
        $price1   = (float)IFilter::act(IReq::get("price1"));
        $num_min2   = (int)IFilter::act(IReq::get("num_min2"));
        $num_max2   = (int)IFilter::act(IReq::get("num_max2"));
        $price2   = (float)IFilter::act(IReq::get("price2"));
        $num_min3   = (int)IFilter::act(IReq::get("num_min3"));
        $num_max3   = (int)IFilter::act(IReq::get("num_max3"));
        $price3   = (float)IFilter::act(IReq::get("price3"));
        $delivery_cycle   = IFilter::act(IReq::get("delivery_cycle"));
        $number   = IFilter::act(IReq::get("number"));
        $is_exact   = IFilter::act(IReq::get("is_exact"));
        $goods_id   = IFilter::act(IReq::get("goods_id"));

        //获取最小发布数量
        $min_num = array($num_min1);
        if($num_min2 != '' && $num_min2 != 0){
            $min_num = array($num_min1,$num_min2);
        }
        if($num_min3 != '' && $num_min3 != 0){
            $min_num = array($num_min1,$num_min2,$num_min3);
        }
        $max_num = array($num_max1);
        if($num_max2 != '' && $num_max2 != 0){
            $max_num = array($num_max1,$num_max2);
        }
        if($num_max3 != '' && $num_max3 != 0){
            $max_num = array($num_max1,$num_max2,$num_max3);
        }

        //获取最小发布价格
        $min_price[0] = $price1;
        if(!empty($price2)){
            $min_price[1] = $price2;
        }
        if(!empty($price3)){
            $min_price[2] = $price3;
        }
        //新增发布表信息
        $insert_release = array(
            'goods_no'  => $goods_no,
            'number'  => $number,
            'price'  => min($min_price),
            'area_id'  => $area_id,
            'delivery_cycle'  => $delivery_cycle,
            'type'  => 1,
            'user_id'  => $this->user['user_id'],
            'up_time' => date('Y-m-d H:i:s.n'),
            'update_time' => date('Y-m-d H:i:s.n'),
            'status' => 0,
            'is_exact' => $is_exact,
            'is_delete' => 0,
        );
        $release_model = new IModel('release_information');

        //如果goods_id存在,不需审核
        if($goods_id){
            $insert_release['status'] = 2;
        }else{
            $insert_release['status'] = 0;
        }

        $release_model->setData($insert_release);
        $release_id = $release_model->add();
        //新增发布表ID存在时
        if(!$release_id){
            //发布表新增失败
            $release_model->rollback();
            $result = array('status' => 2,'msg' => '新增发布失败');
            die(JSON::encode($result));
        }
        if(!$goods_id){
            //新增申请表信息
            $apply_model = new IModel('goods_apply');
            $imgs = implode(",",$img);
            $insert_apply = array(
                'name' => $name,
                'goods_no' => $goods_no,
                'release_id' => $release_id,
                'brand' => $brand,
                'img' => $imgs,
                'status' => 2,
                'content' => $content,
                'user_id' => $this->user['user_id'],
                'create_time' => date('Y-m-d H:i:s.n'),
            );
            $apply_model->setData($insert_apply);
            $apply_id = $apply_model->add();
            if(!$apply_id){
                $apply_model->rollback();
                //申请表新增失败
                $result = array('status' => 2,'msg' => '新增申请失败');
                die(JSON::encode($result));
            }
            $need_update = array(
                'apple_id' => $apply_id
            );
            $release_model->setData(array("apply_id" => $apply_id));
            $release_model->update("id = ".$release_id);
        }

        //新增起购表信息
        $section_model = new IModel('release_section');
        $length = count($min_num);
        for ($i=0;$i<$length;$i++){
            $insert_section = array(
                'release_id'  => $release_id,
                'goods_no'  => $goods_no,
                'num_min'  => $min_num[$i],
                'num_max'  => $max_num[$i],
                'price'  => $min_price[$i],
            );
            $section_model->setData($insert_section);
            $section_id = $section_model->add();
        }
        if(!$section_id){
            $section_model->rollback();
            //起购表新增失败
            $result = array('status' => 2,'msg' => '新增起购失败');
            die(JSON::encode($result));
        }
        //新增货源地址
        $area_model = new IModel('goods_area');
        $area = explode(",",$area_id);
        $insert_area = array(
            'release_id' => $release_id,
            'nation_id' => $area[0],
        );
        if($area[1]){
            $insert_area['pro_id'] = $area[1];
        }
        if($area[2]){
            $insert_area['city_id'] = $area[2];
        }
        $area_model->setData($insert_area);
        $goods_area_id = $area_model->add();
        if(!$goods_area_id){
            $area_model->rollback();
            //货源表新增失败
            $result = array('status' => 2,'msg' => '新增货源失败');
            die(JSON::encode($result));
        }
        $msg = '提交成功';

        if($goods_id){
            $matchmakingDeal = new matchmakingDeal;
            $is_num = $matchmakingDeal->precisematching($release_id);
            if($is_num === false){
                $result = array('status' => 2,'msg' => '撮合交易失败');
                die(JSON::encode($result));
            }elseif ($is_num>0){
                $msg = "提交成功，已为您匹配.$is_num.条求购信息，可以前往个人中心-我的货源进行查看";
            }
        }

        $log_content = "地址:$area_id;数量1:$num_min1-$num_max1;价格1:$price1;";
        if($num_min2){
            $log_content = $log_content."数量2:$num_min2-$num_max2;价格2:$price2;";
        }
        if($num_min3){
            $log_content = $log_content."数量3:$num_min3-$num_max3;价格3:$price3;";
        }

        if($is_exact == 1){
            $log_content .= "精确;";
        }else{
            $log_content .= "模糊;";
        }

        $log_id = $this->release_log($release_id,'发布货源',$log_content,$insert_release['status']);
        if(empty($log_id)){
            $result = array('status' => 2,'msg' => '创建日志失败');
            die(JSON::encode($result));
        }
        $result = array('status' => 1,'release_id' =>$release_id,'msg'=>$msg);
        die(JSON::encode($result));

    }

    /**
     *提交求购信息
     */
    function seek_save()
    {
        //获取页面传递信息
        $name   = IFilter::act(IReq::get("name"));
        $brand   = IFilter::act(IReq::get("brand"));
        $goods_no   = IFilter::act(IReq::get("goods_no"));
        $content   = IFilter::act(IReq::get("content"));
        $img   = IFilter::act(IReq::get("img"));
        $area_id   = IFilter::act(IReq::get("area_id"));
        $seek_num   = (int)IFilter::act(IReq::get("seek_num"));
        $price   = (float)IFilter::act(IReq::get("price"));
        $is_exact   = IFilter::act(IReq::get("is_exact"));
        $goods_id   = IFilter::act(IReq::get("goods_id"));


        //新增发布表信息
        $release_model = new IModel('release_information');
        $insert_release = array(
            'goods_no'  => $goods_no,
            'number'  => $seek_num,
            'price'  => $price,
            'area_id'  => $area_id,
            'type'  => 0,
            'user_id'  => $this->user['user_id'],
            'up_time' => date('Y-m-d H:i:s.n'),
            'update_time' => date('Y-m-d H:i:s.n'),
            'is_exact' => $is_exact,
            'is_delete' => 0,
        );
        if($goods_id){
            $insert_release['status'] = 2;
            $msg = '发布成功';
        }else{
            $insert_release['status'] = 0;
        }
        $release_model->setData($insert_release);
        $release_id = $release_model->add();
        //新增发布表ID存在时
        if(!$release_id){
            //发布表新增失败
            $release_model->rollback();
            $result = array('status' => 2,'msg' => '发布失败');
            die(JSON::encode($result));
        }

        if(!$goods_id){
            //新增申请表信息
            $apply_model = new IModel('goods_apply');
            $imgs = implode(",",$img);
            $insert_apply = array(
                'name' => $name,
                'goods_no' => $goods_no,
                'release_id' => $release_id,
                'brand' => $brand,
                'img' => $imgs,
                'status' => 2,
                'content' => $content,
                'user_id' => $this->user['user_id'],
                'create_time' => date('Y-m-d H:i:s.n'),
            );
            $apply_model->setData($insert_apply);
            $apply_id = $apply_model->add();
            if(!$apply_id){
                $apply_model->rollback();
                //申请表新增失败
                $result = array('status' => 2);
                die(JSON::encode($result));
            }
            $release_model->setData(array("apply_id" => $apply_id));
            $release_model->update("id = ".$release_id);
        }
        //新增货源地址
        $area_model = new IModel('goods_area');
        $area = explode(",",$area_id);
        $insert_area = array(
            'release_id' => $release_id,
            'nation_id' => $area[0],
        );
        if($area[1]){
            $insert_area['pro_id'] = $area[1];
        }
        if($area[2]){
            $insert_area['city_id'] = $area[2];
        }
        $area_model->setData($insert_area);
        $goods_area_id = $area_model->add();
        if(!$goods_area_id){
            $area_model->rollback();
            //货源表新增失败
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
        $msg = '发布成功';
        if($goods_id){
            $matchmakingDeal = new matchmakingDeal;
            $is_num = $matchmakingDeal->precisematching($release_id);
            if($is_num == false){
                $result = array('status' => 2,'msg' => '撮合交易失败');
                die(JSON::encode($result));
            }else{
                $msg = "提交成功，已为您匹配.$is_num.条货源信息，可以前往个人中心-我的求购进行查看";
            }
        }
        $log_content = "地址:$area_id;价格:$price;数量:$seek_num;";
        if($is_exact == 1){
            $log_content .= "精确;";
        }else{
            $log_content .= "模糊;";
        }

        $log_id = $this->release_log($release_id,'发布求购',$log_content,$insert_release['status']);
        if(empty($log_id)){
            $result = array('status' => 2,'msg' => '创建日志失败');
            die(JSON::encode($result));
        }
        $result = array('status' => 1,'release_id' =>$release_id,'msg' => $msg);
        die(JSON::encode($result));

    }

    /**
     * 修改页执行保存
     */
    function save()
    {
        $type   = IFilter::act(IReq::get("type"));
        //修改货源
        if($type == 1){
            //获取页面传递信息
            $release_id   = IFilter::act(IReq::get("release_id"));
            $name   = IFilter::act(IReq::get("name"));
            $brand   = IFilter::act(IReq::get("brand"));
            $goods_no   = IFilter::act(IReq::get("goods_no"));
            $content   = IFilter::act(IReq::get("content"));
            $img   = IFilter::act(IReq::get("img"));
            $area_id   = IFilter::act(IReq::get("area_id"));
            $num_min1   = (int)IFilter::act(IReq::get("num_min1"));
            $num_max1   = (int)IFilter::act(IReq::get("num_max1"));
            $price1   = (float)IFilter::act(IReq::get("price1"));
            $num_min2   = (int)IFilter::act(IReq::get("num_min2"));
            $num_max2   = (int)IFilter::act(IReq::get("num_max2"));
            $price2   = (float)IFilter::act(IReq::get("price2"));
            $num_min3   = (int)IFilter::act(IReq::get("num_min3"));
            $num_max3   = (int)IFilter::act(IReq::get("num_max3"));
            $price3   = (float)IFilter::act(IReq::get("price3"));
            $delivery_cycle   = IFilter::act(IReq::get("delivery_cycle"));
            $number   = IFilter::act(IReq::get("number"));
            $is_exact   = IFilter::act(IReq::get("is_exact"));
            $goods_id   = IFilter::act(IReq::get("goods_id"));

            //获取最小发布数量
            $min_num = array($num_min1);
            if($num_min2 != '' && $num_min2 != 0){
                $min_num = array($num_min1,$num_min2);
            }
            if($num_min3 != '' && $num_min3 != 0){
                $min_num = array($num_min1,$num_min2,$num_min3);
            }
            $max_num = array($num_max1);
            if($num_max2 != '' && $num_max2 != 0){
                $max_num = array($num_max1,$num_max2);
            }
            if($num_max3 != '' && $num_max3 != 0){
                $max_num = array($num_max1,$num_max2,$num_max3);
            }

            //获取最小发布价格
            $min_price[0] = $price1;
            if(!empty($price2)){
                $min_price[1] = $price2;
            }
            if(!empty($price3)){
                $min_price[2] = $price3;
            }

            $release_model = new IModel('release_information');
            $area_model = new IModel('goods_area');
            $section_model = new IModel('release_section');
            $release_info = $release_model->getObj("id='".$release_id."'");
            $filedData = array(
                'goods_no'  => $goods_no,
                "number" 	=> $number,
                "price" 	=> min($min_price),
                "area_id" 	=> $area_id,
                "type" 	=> $type,
                "delivery_cycle"    => $delivery_cycle,
                "is_exact" 	=> $is_exact,
                'user_id'  => $this->user['user_id'],
                'update_time' => date('Y-m-d H:i:s.n'),
                'is_delete' => 0,
            );
            //如果商品信息已通过审核
            if($goods_id){
                $filedData['status'] = 2;
            }else{
                $filedData['status'] = 0;
            }

            $release_model->setData($filedData);
            $is_success = $release_model->update("id = ".$release_id);
            if(!$is_success){
                //发布表新增失败
                $release_model->rollback();
                $result = array('status' => 2,'msg' =>'发布修改失败');
                die(JSON::encode($result));
            }

            //若发布信息未通过审核，则修改申请表信息
            if(empty($goods_id)){
                $apply_model = new IModel('goods_apply');
                $imgs = implode(",",$img);
                $applt_data = array(
                    'goods_no'  => $goods_no,
                    'name'  => $name,
                    'brand'  => $brand,
                    'content'  => $content,
                    'img'  => $imgs,
                    'status'  => 2,
                    'create_time' => date('Y-m-d H:i:s.n'),
                );
                $apply_model->setData($applt_data);
                $ap_where = "id = ".$release_info['apply_id'];
                $success = $apply_model->update($ap_where);
                if(!$success){
                    //发布表修改失败
                    $release_model->rollback();
                    $result = array('status' => 2,'msg' =>'发布修改失败');
                    die(JSON::encode($result));
                }
            }

            //如果地址信息修改
            if($release_info['area_id'] != $area_id){
                $area_model->del('release_id = "'.$release_id.'"');

                $area = explode(",",$area_id);
                $insert_area = array(
                    'release_id' => $release_id,
                    'nation_id' => $area[0],
                );
                if($area[1]){
                    $insert_area['pro_id'] = $area[1];
                }
                if($area[2]){
                    $insert_area['city_id'] = $area[2];
                }
                $area_model->setData($insert_area);
                $goods_area_id = $area_model->add();
                if(!$goods_area_id){
                    //地址修改失败
                    $result = array('status' => 2,'msg' =>'地址修改失败');
                    die(JSON::encode($result));
                }
            }
            //新增起购表信息
            $section_model->del('release_id = "'.$release_id.'"');

            for ($i=0;$i<count($min_num);$i++){
                $insert_section = array(
                    'release_id'  => $release_id,
                    'goods_no'  => $goods_no,
                    'num_min'  => $min_num[$i],
                    'num_max'  => $max_num[$i],
                    'price'  => $min_price[$i],
                );
                $section_model->setData($insert_section);
                $section_id = $section_model->add();
            }
            if(!$section_id){
                //起购修改失败
                $result = array('status' => 2,'msg' =>'起购修改失败');
                die(JSON::encode($result));
            }
            $msg = '修改成功';
            if($goods_id){
                $matchmakingDeal = new matchmakingDeal;
                $is_num = $matchmakingDeal->precisematching($release_id);
                if($is_num === false){
                    $result = array('status' => 2,'msg' => '撮合交易失败');
                    die(JSON::encode($result));
                }elseif ($is_num>0){
                    $msg = "提交成功，已为您匹配.$is_num.条求购信息，可以前往个人中心-我的货源进行查看";
                }
            }

            $log_content = "地址:$area_id;数量1:$num_min1-$num_max1;价格1:$price1;";
            if($num_min2){
                $log_content = $log_content."数量2:$num_min2-$num_max2;价格2:$price2;";
            }
            if($num_min3){
                $log_content = $log_content."数量3:$num_min3-$num_max3;价格3:$price3;";
            }

            if($is_exact == 1){
                $log_content .= "精确;";
            }else{
                $log_content .= "模糊;";
            }

            $log_id = $this->release_log($release_id,'编辑货源',$log_content,$release_info['status']);
            if(empty($log_id)){
                $result = array('status' => 2,'msg' => '创建日志失败');
                die(JSON::encode($result));
            }

            $result = array('status' => 1,'release_id' =>$release_id,'msg'=>$msg);
            die(JSON::encode($result));

            //修改求购
        }elseif ($type == 0){
            //获取页面传递信息
            $release_id   = IFilter::act(IReq::get("release_id"));
            $name   = IFilter::act(IReq::get("name"));
            $brand   = IFilter::act(IReq::get("brand"));
            $goods_no   = IFilter::act(IReq::get("goods_no"));
            $content   = IFilter::act(IReq::get("content"));
            $img   = IFilter::act(IReq::get("img"));
            $area_id   = IFilter::act(IReq::get("area_id"));
            $seek_num   = (int)IFilter::act(IReq::get("seek_num"));
            $price   = (float)IFilter::act(IReq::get("price"));
            $is_exact   = IFilter::act(IReq::get("is_exact"));
            $goods_id   = IFilter::act(IReq::get("goods_id"));

            //新增发布表信息
            $release_model = new IModel('release_information');
            $insert_release = array(
                'goods_no'  => $goods_no,
                'number'  => $seek_num,
                'price'  => $price,
                'area_id'  => $area_id,
                'type'  => 0,
                'user_id'  => $this->user['user_id'],
                'update_time' => date('Y-m-d H:i:s.n'),
                'is_exact' => $is_exact,
                'is_delete' => 0,
            );
            //如果商品信息已通过审核
            if($goods_id){
                $insert_release['status'] = 2;
            }else{
                $insert_release['status'] = 0;
            }
            $release_model->setData($insert_release);
            $release_change_id = $release_model->update("id = ".$release_id);
            //发布表ID存在时
            if(!$release_change_id){
                //发布修改失败
                $release_model->rollback();
                $result = array('status' => 2,'msg'=>'发布修改失败');
                die(JSON::encode($result));
            }

            //若发布信息未通过审核，则修改申请表信息
            if(empty($goods_id)){
                $apply_model = new IModel('goods_apply');
                $imgs = implode(",",$img);

                $applt_data = array(
                    'goods_no'  => $goods_no,
                    'name'  => $name,
                    'brand'  => $brand,
                    'content'  => $content,
                    'img'  => $imgs,
                    'status'  => 2
                );
                $apply_model->setData($applt_data);
                $success = $apply_model->update("release_id = ".$release_id);
                if(!$success){
                    //申请修改失败
                    $apply_model->rollback();
                    $result = array('status' => 2,'msg' =>'申请修改失败');
                    die(JSON::encode($result));
                }
            }

            //新增货源地址
            $area_model = new IModel('goods_area');
            $area_model->del('release_id = "'.$release_id.'"');

            $area = explode(",",$area_id);
            $insert_area = array(
                'release_id' => $release_id,
                'nation_id' => $area[0],
            );
            if($area[1]){
                $insert_area['pro_id'] = $area[1];
            }
            if($area[2]){
                $insert_area['city_id'] = $area[2];
            }
            $area_model->setData($insert_area);
            $goods_area_id = $area_model->add();
            if(!$goods_area_id){
                $area_model->rollback();
                //货源修改失败
                $result = array('status' => 2,'msg'=>'货源修改失败');
                die(JSON::encode($result));
            }
            $msg = '修改成功';
            if($goods_id){
                $matchmakingDeal = new matchmakingDeal;
                $is_num = $matchmakingDeal->precisematching($release_id);
                if($is_num === false){
                    $result = array('status' => 2,'msg' => '撮合交易失败');
                    die(JSON::encode($result));
                }elseif ($is_num>0){
                    $msg = "提交成功，已为您匹配.$is_num.条货源信息，可以前往个人中心-我的求购进行查看";
                }
            }

            $log_content = "地址:$area_id;数量:$seek_num;价格:$price;";

            if($is_exact == 1){
                $log_content .= "精确;";
            }else{
                $log_content .= "模糊;";
            }

            $log_id = $this->release_log($release_id,'编辑求购',$log_content,$insert_release['status']);
            if(empty($log_id)){
                $result = array('status' => 2,'msg' => '创建日志失败');
                die(JSON::encode($result));
            }
            $result = array('status' => 1,'release_id' =>$release_id,'msg' =>$msg);
            die(JSON::encode($result));
        }
    }

    /**
     * 发布详情页
     */
    function release_view()
    {
        $id   = IFilter::act(IReq::get("release_id"));
        //查询发布表信息
        $model_release = new IModel('release_information');
        $info_where = "id = {$id}";
        $info = $model_release->getObj($info_where);
        if($info['user_id'] == $this->user['user_id']){
            $data['is_man'] = 1;
        } else if($info['status'] < 2 && $info['user_id'] != $this->user['user_id']){
            Util::showMessage('该信息暂时无法查看');
            die;
        }
        //是否关注过此人
        $attention_model = new IModel('attention');
        $attention_info = $attention_model->getObj("user_id='".$this->user['user_id']."' and passive_user_id='".$info['user_id']."' ");
        if(!empty($attention_info)){
            $data['attention_msg'] = 1;
        }else{
            $data['attention_msg'] = 2;
        }
        //是否收藏过此发布信息
        $favorite_model = new IModel('favorite');
        $favorite_info = $favorite_model->getObj("user_id='".$this->user['user_id']."' and rid='".$id."' ");
        if(!empty($favorite_info)){
            $data['favorite_msg'] = 1;
        }else{
            $data['favorite_msg'] = 2;
        }

        //地址信息
        $model_areas = new IModel('areas');
        $area_id = explode(",",$info['area_id']);
        for ($i=0;$i<count($area_id);$i++){
            $area_where = "area_id = {$area_id[$i]}";
            $area_name[$i] = $model_areas->getObj($area_where,'area_name');
        }
        $data['area_name'] = $area_name;
        //查询商品信息 区分：状态 >= 2 goods表 否则 goods_apply表
        $goods_class = new goods_class();
        $model_goods = new IModel('goods');
        if($info['status'] < 2){
            $goodsWhere = " release_id = {$id} ";
            //获取商品
            $obj_goods = new IModel('goods_apply');
            $goods_info['form'] = $obj_goods->getObj($goodsWhere);
            $data['img_info_list'] = explode(',', $goods_info['form']['img']);

        }else{
            $apply_where = "goods_no = '".$info['goods_no']."'";
            $goods_data = $model_goods->getObj($apply_where);
            $goods_info = $goods_class->edit($goods_data['id']);
        }
        //查询已发布货源数量
        $data['release_num'] = $model_release->getObj('status > 1 and type = 1 and user_id='.$info['user_id'],'count(*) as release_num');

        //查询已发布求购数量
        $data['seek_num'] = $model_release->getObj('status > 1 and type = 0 and user_id='.$info['user_id'],'count(*) as seek_num');

        //查询起订信息
        if($info['type'] == 1){
            $model_section = new IModel('release_section');
            $section_where = "release_id = {$info['id']}";
            $section_info = $model_section->query($section_where,'','id');
            foreach ($section_info as $k => $v){
                $data['section_min'][$k] = $v['num_min'];
                $data['section_max'][$k] = $v['num_max'];
                $data['section_price'][$k] = $v['price'];
            }
            $data['section_num'] = count($data['section_price']);
        }

        //下方商品推荐
        $tuijian_where = "goods_no = '".$info['goods_no']."' and status = 2 and type = '".$info['type']."' and is_delete = 0";
        $data['tuijian_info'] = $model_release->query($tuijian_where,'','update_time desc');
        foreach ($data['tuijian_info'] as $k => $v){
            $goods_img_info = $model_goods->getObj("goods_no = '".$v['goods_no']."'");
            $data['tuijian_info'][$k]['goods_info'] = $goods_img_info;
        }

        //查询用户地址
        $model_member = new IModel('member');
        $member_where = "user_id = {$info['user_id']}";
        $data['member_info'] = $model_member->getObj($member_where,'area');

        //查询发布者信息
        $model_user = new IModel('user');
        $user_where = "id = {$info['user_id']}";
        $data['user_info'] = $model_user->getObj($user_where);
        $data['goods_info'] = $goods_info;
        $data['release_info'] = $info;
        $this->setRenderData($data);
        $this->redirect('release_view');
    }

    /**
     *关注
     */
    function add_attention()
    {
        //接收被关注人ID
        $passive_user_id   = IFilter::act(IReq::get("passive_user_id"));
        $attention_model = new IModel('attention');
        $member_model = new IModel('member');
        $user_area_info = $member_model->getObj('user_id="'.$passive_user_id.'"');

        $is_attention = $attention_model->getObj("user_id='".$this->user['user_id']."' and passive_user_id='".$passive_user_id."' ");
        if(!empty($is_attention)){
            $is_delete = $attention_model->del("user_id='".$this->user['user_id']."' and passive_user_id='".$passive_user_id."' ");
            if(!empty($is_delete)){
                $result = array('status' => 3,'msg' => '取消关注成功');
            }else{
                $result = array('status' => 4,'msg' => '取消关注失败');
            }
        }else{
            $attention_data = array(
                'user_id' => $this->user['user_id'],
                'passive_user_id' => $passive_user_id,
                'create_time'  => date('Y-m-d H:i:s.n'),
                'summary'  => $user_area_info['area']
            );
            $attention_model->setData($attention_data);
            $attention_info = $attention_model->add();
            if(!empty($attention_info)){
                $result = array('status' => 1,'msg' => '关注成功');
            }else{
                $result = array('status' => 2,'msg' => '关注失败');
            }
        }
        die(JSON::encode($result));
    }

    /**
     *收藏
     */
    function add_favorite()
    {
        //接收被关注人ID
        $rid   = IFilter::act(IReq::get("rid"));
        $favorite_model = new IModel('favorite');

        $is_favorite = $favorite_model->getObj("user_id='".$this->user['user_id']."' and rid='".$rid."' ");
        if(!empty($is_favorite)){
            $is_delete = $favorite_model->del("user_id='".$this->user['user_id']."' and rid='".$rid."' ");
            if(!empty($is_delete)){
                $result = array('status' => 3,'msg' => '取消收藏成功');
            }else{
                $result = array('status' => 4,'msg' => '取消收藏失败');
            }
        }else{
            $favorite_data = array(
                'user_id' => $this->user['user_id'],
                'rid' => $rid,
                'time'  => date('Y-m-d H:i:s.n'),
            );
            $favorite_model->setData($favorite_data);
            $favorite_info = $favorite_model->add();
            if(!empty($favorite_info)){
                $result = array('status' => 1,'msg' => '收藏成功');
            }else{
                $result = array('status' => 2,'msg' => '收藏失败');
            }
        }
        die(JSON::encode($result));
    }

    /**
     *删除发布
     */
    function delete_info()
    {
        $id   = IFilter::act(IReq::get("release_id"));
        $release_model = new IModel('release_information');
        $release_data = array(
            'is_delete' => 1
        );
        $release_model->setData($release_data);
        $num = $release_model->update('id="'.$id.'"');
        if(!empty($num)){
            $result = array('status' => 1,'msg' =>'删除成功');
            die(JSON::encode($result));
        }else{
            $result = array('status' => 2,'msg' =>'删除失败');
            die(JSON::encode($result));
        }
    }

    /**
     *创建发布日志
     * @param  int $id 发布表ID
     * @param  string  $action 当前动作
     * @param  string  $log_content 日志内容
     * @param  int  $status 发布状态
     * @return int or bool int:影响的条数; bool:false错误
     */
    function release_log($id,$action,$log_content,$status)
    {
        $log_content = "动作:$action;".$log_content;
        if($status == 0){
            $log_content .= "状态:未审核;";
        }elseif ($status == 1){
            $log_content .= "状态:拒绝;";
        }elseif ($status == 2){
            $log_content .= "状态:通过;";
        }elseif ($status == 3){
            $log_content .= "状态:下架;";
        }
        $release_log_info_model = new IModel('release_log');

        $log_data = array(
            'user_id' => $this->user['user_id'],
            'release_id' => $id,
            'content' => $log_content,
            'create_time' => date('Y-m-d H:i:s.n'),
        );
        $release_log_info_model->setData($log_data);
        $log_num = $release_log_info_model->add();
        if(!empty($log_num)){
            return true;
        }else{
            return false;
        }
    }

}