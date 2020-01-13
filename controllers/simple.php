<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file Simple.php
 * @brief
 * @author webning
 * @date 2011-03-22
 * @version 0.6
 * @note
 */
/**
 * @brief Simple
 * @class Simple
 * @note
 */

class Simple extends IController
{
    public $layout='lbsite';

	function init()
	{

	}

	/*function login()
	{
		//如果已经登录，就跳到ucenter页面
		if($this->user)
		{
			$this->redirect("/ucenter/index");
		}
		else
		{
			$this->redirect('lb_login');
		}
	}*/

	//lb登录
    function login()
    {
        $csrf=Block::getCSRF();
        ISession::set('csrf',$csrf);
        //如果已经登录，就跳到ucenter页面
        if($this->user)
        {
            $this->redirect("/ucenter/index");
        }
        else
        {
            $this->setRenderData(['csrf'=>$csrf]);
            $this->redirect('login');
        }
    }

    function reg(){
        $csrf=Block::getCSRF();
        ISession::set('csrf',$csrf);
        $url='reg';
        if($this->user){
            $url='/ucenter/index';
        }else{
            $this->setRenderData(['csrf'=>$csrf]);
        }
        $this->redirect($url);
    }

	//退出登录
    function logout()
    {
    	plugin::trigger('clearUser');
    	$this->redirect('login');
    }

    //用户注册
    function reg_act()
    {
        if ($_POST['_csrf']=='' || $_POST['_csrf']!=ISession::get('csrf')){
            $array = array(
                'info' => "非法请求",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/reg'),
            );
            echo JSON::encode($array);
            exit();
        }
    	//调用_userInfo注册插件
    	$result = plugin::trigger("userRegAct",$_POST);
    	if(is_array($result))
    	{
			//自定义跳转页面
			//$this->redirect('/site/success?message='.urlencode("注册成功！"));
            $array = array(
                'info' => "注册成功",
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/index'),
            );
            echo JSON::encode($array);
    	}
    	else
    	{
            $array = array(
                'info' => $result,
                 'url' => IUrl::getHost().IUrl::creatUrl('/simple/login'),
            );
            echo JSON::encode($array);
    	}
    }

    //重新激活邮箱注册
    function reg_act_email()
    {
        //调用_userInfo注册插件
        $result = plugin::trigger("userRegAct_email",$_POST);
        if(is_array($result))
        {
            //自定义跳转页面
            //$this->redirect('/site/success?message='.urlencode("注册成功！"));
            $array = array(
                'info' => "注册成功",
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/index'),
            );
            echo JSON::encode($array);
        }
        else
        {
            $array = array(
                'info' => $result,
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/login'),
            );
            echo JSON::encode($array);
        }
    }

    //用户登录
    function login_act()
    {
        if ($_POST['_csrf']=='' || $_POST['_csrf']!=ISession::get('csrf')){
            $array = array(
                'info' => "非法请求",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/reg'),
            );
            echo JSON::encode($array);
            exit();
        }
    	//调用_userInfo登录插件
		$result = plugin::trigger('userLoginAct',$_POST);
		if(is_array($result))
		{
			//自定义跳转页面
			$callback = '';
			if($callback)
           {
               $url = IUrl::getHost().IUrl::creatUrl($callback);
           }
           else
			{
                $url = IUrl::getHost().IUrl::creatUrl('/ucenter/index');
			}

            $array = array(
                'info' => "登录成功",
                'url' => $url,
            );
            echo JSON::encode($array);
		}
		else
		{
            $array = array(
                'info' => $result,
              //  'url' => IUrl::getHost().IUrl::creatUrl('/simple/login'),
            );
            echo JSON::encode($array);
		}
    }

    //商品加入购物车[ajax]
    function joinCart()
    {
    	$link      = IReq::get('link');
    	$goods_id  = IFilter::act(IReq::get('goods_id'),'int');
    	$goods_num = IFilter::act(IReq::get('goods_num'),'int');
    	$goods_num = $goods_num == 0 ? 1 : $goods_num;
		$type      = IFilter::act(IReq::get('type'));

		//加入购物车
    	$cartObj   = new Cart();

    	$addResult = $cartObj->add($goods_id,$goods_num,$type,$this->user['user_id']);


        if($this->user['user_id'] == null){
            $result = array(
                'isError' => true,
                'message' => "请选登录",
            );
            echo JSON::encode($result);
            exit;
        }

    	if($link != '')
    	{
    		if($addResult === false)
    		{
    			$this->cart(false);
    			Util::showMessage($cartObj->getError());
    		}
    		else
    		{
    			$this->redirect($link);
    		}
    	}
    	else
    	{
	    	if($addResult === false)
	    	{
		    	$result = array(
		    		'isError' => true,
		    		'message' => $cartObj->getError(),
		    	);
	    	}
	    	else
	    	{
		    	$result = array(
		    		'isError' => false,
		    		'message' => '添加成功',
		    	);
	    	}
	    	echo JSON::encode($result);
    	}
    }

    //根据goods_id获取货品
    function getProducts()
    {
    	$id           = IFilter::act(IReq::get('id'),'int');
    	$productObj   = new IModel('products');
    	$productsList = $productObj->query('goods_id = '.$id,'sell_price,id,spec_array,goods_id','store_nums desc',7);
		if($productsList)
		{
			foreach($productsList as $key => $val)
			{
				$productsList[$key]['specData'] = Block::show_spec($val['spec_array']);
			}
		}
		echo JSON::encode($productsList);
    }

    //删除购物车
    function removeCart()
    {
    	$link      = IReq::get('link');
    	$goods_id  = IFilter::act(IReq::get('goods_id'),'int');
    	$type      = IFilter::act(IReq::get('type'));

    	$cartObj   = new Cart();
    	$cartInfo  = $cartObj->getMyCart();
    	$delResult = $cartObj->del($goods_id,$type);

    	if($link != '')
    	{
    		if($delResult === false)
    		{
    			$this->cart(false);
    			Util::showMessage($cartObj->getError());
    		}
    		else
    		{
    			$this->redirect($link);
    		}
    	}
    	else
    	{
	    	if($delResult === false)
	    	{
	    		$result = array(
		    		'isError' => true,
		    		'message' => $cartObj->getError(),
	    		);
	    	}
	    	else
	    	{
		    	$goodsRow = $cartInfo[$type]['data'][$goods_id];
		    	$cartInfo['sum']   -= $goodsRow['sell_price'] * $goodsRow['count'];
		    	$cartInfo['count'] -= $goodsRow['count'];

		    	$result = array(
		    		'isError' => false,
		    		'data'    => $cartInfo,
		    	);
	    	}

	    	echo JSON::encode($result);
    	}
    }

    //清空购物车
    function clearCart()
    {
    	$cartObj = new Cart();
    	$cartObj->clear();
    	$this->redirect('cart');
    }

    //购物车div展示
    function showCart()
    {
    	$cartObj  = new Cart();
    	$cartList = $cartObj->getMyCart();
    	$data['data'] = array_merge($cartList['goods']['data'],$cartList['product']['data']);
    	$data['count']= $cartList['count'];
    	$data['sum']  = $cartList['sum'];
        echo JSON::encode($data);
    }

    //购物车页面及商品价格计算[复杂]
    function cart($redirect = false)
    {
    	//防止页面刷新
    	header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);

		//开始计算购物车中的商品价格
    	$countObj = new CountSum();
    	$result   = $countObj->cart_count();

    	if(is_string($result))
    	{
    		IError::show($result,403);
    	}

        $cart_list_a = array(); //正宗
        $cart_list_b = array();//2处方
        $new_cart_list_a = array(); //正宗
        $new_cart_list_b = array();//2处方

        if($result['goodsList']){
            foreach($result['goodsList'] as $key=>$val){
                if($val['is_prescription'] == 1){
                    $cart_list_a[] = $val;
                }else{
                    $cart_list_b[] = $val;
                }
            }

            if($cart_list_a){
                foreach($cart_list_a as $key=>$val){
                    $new_cart_list_a[$val['seller_id']]['seller_id'] = $val['seller_id'];
                    $new_cart_list_a[$val['seller_id']]['goods_list'][] = $val;
                }
            }
            if($cart_list_b){
                foreach($cart_list_b as $key=>$val){
                    $new_cart_list_b[$val['seller_id']]['seller_id'] = $val['seller_id'];
                    $new_cart_list_b[$val['seller_id']]['goods_list'][] = $val;
                }
            }
        }
//
//        var_dump($new_cart_list_a);
//        exit;

    	//返回值
    	$this->final_sum = $result['final_sum'];
    	$this->promotion = $result['promotion'];
    	$this->proReduce = $result['proReduce'];
    	$this->sum       = $result['sum'];
    	$this->goodsList = $result['goodsList'];
    	$this->count     = $result['count'];
    	$this->reduce    = $result['reduce'];
    	$this->weight    = $result['weight'];
    	$this->new_cart_list_a  = $new_cart_list_a;
    	$this->new_cart_list_b  = $new_cart_list_b;

		//渲染视图
    	$this->redirect('cart',$redirect);
    }

    //计算促销规则[ajax]
    function promotionRuleAjax()
    {
    	$goodsId   = IFilter::act(IReq::get("goodsId"),'int');
    	$productId = IFilter::act(IReq::get("productId"),'int');
    	$num       = IFilter::act(IReq::get("num"),'int');

    	if(!$goodsId || !$num)
    	{
			return;
    	}


        $countObj = new CountSum();
        $result   = $countObj->cart_count('','',1,'','',1);

        echo JSON::encode($result);
    }

    //填写订单信息cart2
    function cart2()
    {
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0",false);
		$id        = IFilter::act(IReq::get('id'),'int');
		$type      = IFilter::act(IReq::get('type'));//goods,product
		$promo     = IFilter::act(IReq::get('promo'));
		$active_id = IFilter::act(IReq::get('active_id'),'int');
		$buy_num   = IReq::get('num') ? IFilter::act(IReq::get('num'),'int') : 1;
		$tourist   = IReq::get('tourist');//游客方式购物

    	//必须为登录用户
    	if($this->user['user_id'] == null)
    	{
    		if($id == 0 || $type == '')
    		{
    			$this->redirect('/simple/login?tourist&callback=/simple/cart2');
    		}
    		else
    		{
    			$url  = '/simple/login?tourist&callback=/simple/cart2/id/'.$id.'/type/'.$type.'/num/'.$buy_num;
    			$url .= $promo     ? '/promo/'.$promo         : '';
    			$url .= $active_id ? '/active_id/'.$active_id : '';
    			$this->redirect($url);
    		}
    	}

		//游客的user_id默认为0
    	$user_id = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];

		//计算商品
		$countSumObj = new CountSum($user_id);
		$result = $countSumObj->cart_count($id,$type,$buy_num,$promo,$active_id,1);

		if($countSumObj->error)
		{
			IError::show(403,$countSumObj->error);
		}


    	//获取收货地址
    	$addressObj  = new IModel('address');
    	$addressList = $addressObj->query('user_id = '.$user_id,"*","is_default desc");

		//更新$addressList数据
    	foreach($addressList as $key => $val)
    	{
    		$temp = area::name($val['province'],$val['city'],$val['area']);
    		if(isset($temp[$val['province']]) && isset($temp[$val['city']]) && isset($temp[$val['area']]))
    		{
	    		$addressList[$key]['province_val'] = $temp[$val['province']];
	    		$addressList[$key]['city_val']     = $temp[$val['city']];
	    		$addressList[$key]['area_val']     = $temp[$val['area']];
    		}
    	}

        //取优惠券


        $goodsPriceList = Block::getGoodPriceListByGoodsList($result['goodsList']);
        $catePriceList = Block::getCateListByGoodsList($result['goodsList']);
        $porpObj = new IModel('prop');
        $pro_list = $porpObj->query('is_userd=0 and is_send =1  and user_id = '.$user_id.'  and NOW() between start_time and end_time and type = 0 and is_close = 0 ');
        $pro_num = 0;
        $new_pro_list = array();
        if($pro_list){
            $tb_goods = new IModel("goods");
            foreach($pro_list as $key=>$val){
                if($val['goods_id'] >0){
                    if(isset($goodsPriceList[$val['goods_id']])){
                        if($goodsPriceList[$val['goods_id']] > $val['max_price']){
                            $val['is_usable'] = 1;
                            $pro_num +=1;
                            $new_pro_list[] = $val;
                        }else{
                            $val['is_usable'] = 0;
                        }
                    }else{
                        $val['is_usable'] = 0;
                    }

                }else{
                    if($val['cat_id'] >0){
                        if(isset($catePriceList[$val['cat_id']])){
                            if($val['max_price'] <= $catePriceList[$val['cat_id']]){
                                $pro_num +=1;
                                $val['is_usable'] = 1;
                                $new_pro_list[] = $val;
                            }else{
                                $val['is_usable'] = 0;
                            }
                        }else{
                            $val['is_usable'] = 0;
                        }
                    }else{
                        if($val['max_price'] <= $result['final_sum']){
                            $pro_num +=1;
                            $val['is_usable'] = 1;
                            $new_pro_list[] = $val;
                        }else{
                            $val['is_usable'] = 0;
                        }
                    }
                }
            }
        }


		//获取习惯方式
		$memberObj = new IModel('member');
		$memberRow = $memberObj->getObj('user_id = '.$user_id,'custom');
		if(isset($memberRow['custom']) && $memberRow['custom'])
		{
			$this->custom = unserialize($memberRow['custom']);
		}
		else
		{
			$this->custom = array(
				'payment'  => '',
				'delivery' => '',
			);
		}


        $this->order_img = ICookie::get($this->user['user_id']."cart2orderimg")?ICookie::get($this->user['user_id']."cart2orderimg"):null;
    	//返回值
		$this->gid       = $id;
		$this->type      = $type;
		$this->num       = $buy_num;
		$this->promo     = $promo;
		$this->active_id = $active_id;
    	$this->final_sum = $result['final_sum'];
    	$this->promotion = $result['promotion'];
    	$this->proReduce = $result['proReduce'];
    	$this->sum       = $result['sum'];
    	$this->goodsList = $result['goodsList'];
    	$this->count       = $result['count'];
    	$this->reduce      = $result['reduce'];
    	$this->weight      = $result['weight'];
    	$this->freeFreight = $result['freeFreight'];
    	$this->seller      = $result['seller'];
    	$this->pro_num      = $pro_num;
    	$this->pro_list      = $new_pro_list;
    	$this->is_prescription  = $result['is_prescription'];

		//收货地址列表
		$this->addressList = $addressList;

		//获取商品税金
		$this->goodsTax    = $result['tax'];


    	//渲染页面
    	$this->redirect('cart2');
    }

	/**
	 * 生成订单
	 */
    function cart3()
    {
		//防止表单重复提交
    	if(IReq::get('timeKey'))
    	{
    		if(ISafe::get('timeKey') == IReq::get('timeKey'))
    		{
	    		IError::show(403,'订单数据不能被重复提交');
    		}
    		ISafe::set('timeKey',IReq::get('timeKey'));
    	}

    	$address_id    = IFilter::act(IReq::get('radio_address'),'int');
    	$delivery_id   = IFilter::act(IReq::get('delivery_id'),'int');
    	$accept_time   = IFilter::act(IReq::get('accept_time'));
    	$payment       = IFilter::act(IReq::get('payment'),'int');
    	$order_message = IFilter::act(IReq::get('message'));
    	$ticket_id     = IFilter::act(IReq::get('ticket_id'),'int');
    	$taxes         = IFilter::act(IReq::get('taxes'),'float');
    	$tax_title     = IFilter::act(IReq::get('tax_title'));
    	$gid           = IFilter::act(IReq::get('direct_gid'),'int');
    	$num           = IFilter::act(IReq::get('direct_num'),'int');
    	$type          = IFilter::act(IReq::get('direct_type'));//商品或者货品
    	$promo         = IFilter::act(IReq::get('direct_promo'));
    	$order_img         = IFilter::act(IReq::get('order_img'));
    	$active_id     = IFilter::act(IReq::get('direct_active_id'),'int');
    	$takeself      = IFilter::act(IReq::get('takeself'),'int');
    	$order_type    = 0;
    	$dataArray     = array();
    	$user_id       = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];




		//获取商品数据信息
    	$countSumObj = new CountSum($user_id);
		$goodsResult = $countSumObj->cart_count($gid,$type,$num,$promo,$active_id,1);

		if($countSumObj->error)
		{
			IError::show(403,$countSumObj->error);
		}

		//处理收件地址
		//1,访客; 2,注册用户
		if($user_id == 0)
		{
			$addressRow = ISafe::get('address');
		}
		else
		{
			$addressDB = new IModel('address');
			$addressRow= $addressDB->getObj('id = '.$address_id.' and user_id = '.$user_id);
		}

		if(!$addressRow)
		{
			IError::show(403,"收货地址信息不存在");
		}
    	$accept_name   = IFilter::act($addressRow['accept_name'],'name');
    	$province      = $addressRow['province'];
    	$city          = $addressRow['city'];
    	$area          = $addressRow['area'];
    	$address       = IFilter::act($addressRow['address']);
    	$mobile        = IFilter::act($addressRow['mobile'],'mobile');
    	$telphone      = IFilter::act($addressRow['telphone'],'phone');
    	$zip           = IFilter::act($addressRow['zip'],'zip');

		//检查订单重复
    	$checkData = array(
    		"accept_name" => $accept_name,
    		"address"     => $address,
    		"mobile"      => $mobile,
    		"distribution"=> $delivery_id,
    	);
    	$result = order_class::checkRepeat($checkData,$goodsResult['goodsList']);

    	if( is_string($result) )
    	{
			//IError::show(403,$result);
    	}

        if($payment == 0){
            $delivery_id = 2;
        }

		//配送方式,判断是否为货到付款
		$deliveryObj = new IModel('delivery');
		$deliveryRow = $deliveryObj->getObj('id = '.$delivery_id);
		if(!$deliveryRow)
		{
			IError::show(403,'配送方式不存在');
		}

		if($deliveryRow['type'] == 0)
		{
			if($payment == 0)
			{
				IError::show(403,'请选择正确的支付方式');
			}
		}
		else if($deliveryRow['type'] == 1)
		{
			$payment = 0;
		}
		else if($deliveryRow['type'] == 2)
		{
			if($takeself == 0)
			{
				IError::show(403,'请选择正确的自提点');
			}
		}
		//如果不是自提方式自动清空自提点
		if($deliveryRow['type'] != 2)
		{
			$takeself = 0;
		}

		if(!$gid)
		{
			//清空购物车
			IInterceptor::reg("cart@onFinishAction");
		}

    	//判断商品是否存在
    	if(is_string($goodsResult) || empty($goodsResult['goodsList']))
    	{
    		IError::show(403,'商品数据错误');
    	}

    	//加入促销活动
    	if($promo && $active_id)
    	{
    		$activeObject = new Active($promo,$active_id,$user_id,$gid,$type,$num);
    		$order_type = $activeObject->getOrderType();
    	}

		$paymentObj = new IModel('payment');
		$paymentRow = $paymentObj->getObj('id = '.$payment,'type,name');
		if(!$paymentRow)
		{
			IError::show(403,'支付方式不存在');
		}
		$paymentName= $paymentRow['name'];
		$paymentType= $paymentRow['type'];
		//最终订单金额计算
		$orderData = $countSumObj->countOrderFee($goodsResult,$province,$delivery_id,$payment,$taxes,0,$promo,$active_id);

        if(is_string($orderData))
		{
			IError::show(403,$orderData);
		}



        //获取红包减免金额
        if($ticket_id)
        {
            foreach($ticket_id as $tk => $tid)
            {
                $propObj   = new IModel('prop');
                $ticketRow = $propObj->getObj('user_id = '.$user_id.' and id = '.$tid.' and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1');
                if(!$ticketRow)
                {
                    IError::show(403,'代金券不可用');
                }

                if($ticketRow['goods_id'] >0){
                    $goodsPriceList = Block::getGoodPriceListByGoodsList($goodsResult['goodsList']);
                    if(isset($goodsPriceList[$ticketRow['goods_id']])){
                        if($goodsPriceList[$ticketRow['goods_id']] < $ticketRow['max_price']){
                            IError::show(403,'指定商品未满'.$ticketRow['max_price']."元，不可用");
                        }
                    }else{
                        IError::show(403,'指定商品优惠券不可用');
                    }

                }else{
                    if($ticketRow['cat_id'] >0){
                        $catePriceList = Block::getCateListByGoodsList($goodsResult['goodsList']);
                        if(isset($catePriceList[$ticketRow['cat_id']])){
                            if($catePriceList[$ticketRow['cat_id']]<$ticketRow['max_price']){
                                IError::show(403,'未满'.$ticketRow['max_price']."元，不可用");
                            }
                        }else{
                            IError::show(403,'代金券不可用');
                        }
                    }else{
                        if($goodsResult['final_sum']<$ticketRow['max_price']){
                            IError::show(403,'未满'.$ticketRow['max_price']."元，不可用");
                        }
                    }
                }



            }
        }



		//根据商品所属商家不同批量生成订单
		$orderIdArray  = array();
		$orderNumArray = array();
		$final_sum     = 0;
		foreach($orderData as $seller_id => $goodsResult)
		{
			//生成的订单数据
			$dataArray = array(
				'order_no'            => Order_Class::createOrderNum(),
				'user_id'             => $user_id,
				'accept_name'         => $accept_name,
				'pay_type'            => $payment,
				'distribution'        => $delivery_id,
				'postcode'            => $zip,
				'telphone'            => $telphone,
				'province'            => $province,
				'city'                => $city,
				'area'                => $area,
				'address'             => $address,
				'mobile'              => $mobile,
				'create_time'         => ITime::getDateTime(),
				'postscript'          => $order_message,
				'accept_time'         => $accept_time,
				'exp'                 => $goodsResult['exp'],
				'point'               => $goodsResult['point'],
				'type'                => $order_type,

				//商品价格
				'payable_amount'      => $goodsResult['sum'],
				'real_amount'         => $goodsResult['final_sum'],

				//运费价格
				'payable_freight'     => $goodsResult['deliveryOrigPrice'],
				'real_freight'        => $goodsResult['deliveryPrice'],

				//手续费
				'pay_fee'             => $goodsResult['paymentPrice'],

				//税金
				'invoice'             => $tax_title ? 1 : 0,
				'invoice_title'       => $tax_title,
				'taxes'               => $goodsResult['taxPrice'],

				//优惠价格
				'promotions'          => $goodsResult['proReduce'] + $goodsResult['reduce'],

				//订单应付总额
				'order_amount'        => $goodsResult['orderAmountPrice'],

				//订单保价
				'insured'             => $goodsResult['insuredPrice'],

				//自提点ID
				'takeself'            => $takeself,

				//促销活动ID
				'active_id'           => $active_id,

				//商家ID
				'seller_id'           => $seller_id,
				//备注信息
				'note'                => '',
				'order_img'           => $order_img,
				'is_prescription'    => $goodsResult['is_prescription'],


			);

			//获取红包减免金额
			if($ticket_id)
			{

                foreach($ticket_id as $tk => $tid)
				{
                    $propObj   = new IModel('prop');
                    $ticketRow = $propObj->getObj('user_id = '.$user_id.' and id = '.$tid.' and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1');
                    if(!$ticketRow)
                    {
                        IError::show(403,'代金券不可用');
                    }

                    if($ticketRow['seller_id'] == 0 || $ticketRow['seller_id'] == $seller_id)
                    {
                        $ticketRow['value']         = $ticketRow['value'] >= $goodsResult['final_sum'] ? $goodsResult['final_sum'] : $ticketRow['value'];
                        $dataArray['prop']          = $tid;
                        $dataArray['promotions']   += $ticketRow['value'];
                        $dataArray['order_amount'] -= $ticketRow['value'];
                        $goodsResult['promotion'][] = array("plan" => "代金券","info" => "使用了￥".$ticketRow['value']."代金券");

                        //锁定红包状态
                        $propObj->setData(array('is_close' => 2));
                        $propObj->update('id = '.$tid);

                        unset($ticket_id[$tk]);
                        break;
                    }
				}
			}

			//促销规则
			if(isset($goodsResult['promotion']) && $goodsResult['promotion'])
			{
				foreach($goodsResult['promotion'] as $key => $val)
				{
					$dataArray['note'] .= join("，",$val)."。";
				}
			}

			$dataArray['order_amount'] = $dataArray['order_amount'] <= 0 ? 0 : $dataArray['order_amount'];

			//生成订单插入order表中
			$orderObj  = new IModel('order');
			$orderObj->setData($dataArray);
			$order_id = $orderObj->add();

			if($order_id == false)
			{
				IError::show(403,'订单生成错误');
			}

			/*将订单中的商品插入到order_goods表*/
	    	$orderInstance = new Order_Class();
	    	$orderInstance->insertOrderGoods($order_id,$goodsResult['goodsResult'],$user_id);

	    	//订单生成后更新推广关系日志表中的有效期-20190228
	    	Agent_Class::update_agent_validitytime($user_id,$sub_code);
	    	//订单生成后更新推广关系日志表中的有效期
	    	
			//订单金额小于等于0直接免单
			if($dataArray['order_amount'] <= 0)
			{
				Order_Class::updateOrderStatus($dataArray['order_no']);
			}
			else
			{
				$orderIdArray[]  = $order_id;
				$orderNumArray[] = $dataArray['order_no'];
				$final_sum      += $dataArray['order_amount'];
			}
		}

		//记录用户默认习惯的数据
		if(!isset($memberRow['custom']))
		{
			$memberObj = new IModel('member');
			$memberRow = $memberObj->getObj('user_id = '.$user_id,'custom');
		}

		$memberData = array(
			'custom' => serialize(
				array(
					'payment'  => $payment,
					'delivery' => $delivery_id,
				)
			),
		);
		$memberObj->setData($memberData);
		$memberObj->update('user_id = '.$user_id);

		//收货地址的处理
		if($user_id)
		{
			$addressDefRow = $addressDB->getObj('user_id = '.$user_id.' and is_default = 1');
			if(!$addressDefRow)
			{
				$addressDB->setData(array('is_default' => 1));
				$addressDB->update('user_id = '.$user_id.' and id = '.$address_id);
			}
		}
		//把优惠券金额拆分到每个商品
		
		//把优惠券金额拆分到每个商品

		//获取备货时间
		$this->stockup_time = $this->_siteConfig->stockup_time ? $this->_siteConfig->stockup_time : 2;

		//数据渲染
		$this->order_id    = join("_",$orderIdArray);
		$this->final_sum   = $final_sum;
		$this->order_num   = join(" ",$orderNumArray);
		$this->payment     = $paymentName;
		$this->paymentType = $paymentType;
		$this->delivery    = $deliveryRow['name'];
		$this->tax_title   = $tax_title;
		$this->deliveryType= $deliveryRow['type'];
		$this->is_prescription= $goodsResult['is_prescription'];

        ICookie::set($this->user['user_id']."cart2orderimg",null);

		plugin::trigger('setCallback','/ucenter/order');
		//订单金额为0时，订单自动完成
		if($this->final_sum <= 0)
		{
			$this->redirect('/site/success/message/'.urlencode("订单确认成功，等待发货"));
		}
		else
		{
			$this->setRenderData($dataArray);
			$this->redirect('cart3');
		}
    }

    //到货通知处理动作
	function arrival_notice()
	{
		$user_id  = $this->user['user_id'];
		$email    = IFilter::act(IReq::get('email'));
		$mobile   = IFilter::act(IReq::get('mobile'));
		$goods_id = IFilter::act(IReq::get('goods_id'),'int');
		$register_time = ITime::getDateTime();

		if(!$goods_id)
		{
			IError::show(403,'商品ID不存在');
		}

		$model = new IModel('notify_registry');
		$obj = $model->getObj("email = '{$email}' and user_id = '{$user_id}' and goods_id = '$goods_id'");
		if(empty($obj))
		{
			$model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time));
			$model->add();
		}
		else
		{
			$model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time,'notify_status'=>0));
			$model->update('id = '.$obj['id']);
		}
		$this->redirect('/site/success',true);
	}

	/**
	 * @brief 邮箱找回密码进行
	 */
    function find_password_email()
	{
        $email = IReq::get('email');
        if($email === null || !IValidate::email($email ))
        {
            IError::show(403,"请输入正确的邮箱地址");
        }
		$username = $email;
		if($username === null || !IValidate::email($username)  )
		{
			IError::show(403,"请输入正确的用户名");
		}
		$tb_user  = new IModel("user as u,member as m");
		$username = IFilter::act($username);
		$email    = IFilter::act($email);
		$user     = $tb_user->getObj(" u.id = m.user_id and u.username='{$username}' AND m.email='{$email}' ");
		if(!$user)
		{
			IError::show(403,"对不起，用户不存在");
		}
		$hash = IHash::md5( microtime(true) .mt_rand());

		//重新找回密码的数据
		$tb_find_password = new IModel("find_password");
		$tb_find_password->setData( array( 'hash' => $hash ,'user_id' => $user['id'] , 'addtime' => time() ) );

		if($tb_find_password->query("`hash` = '{$hash}'") || $tb_find_password->add())
		{
			$url     = IUrl::getHost().IUrl::creatUrl("/simple/restore_password/hash/{$hash}/user_id/".$user['id']);
			$content = mailTemplate::findPassword(array("{url}" => $url));

			$smtp   = new SendMail();
			$result = $smtp->send($user['email'],"拎贝用户密码找回",$content);

			if($result===false)
			{
                $array = array(
                    'info' => "发信失败,请重试！或者联系管理员查看邮件服务是否开启",
                    'url' => IUrl::getHost().IUrl::creatUrl('/simple/lbfind_password'),
                );
                echo JSON::encode($array);
//				IError::show(403,"发信失败,请重试！或者联系管理员查看邮件服务是否开启");
			}
            $array = array(
                'info' => "恭喜您，密码重置邮件已经发送！请到您的邮箱中去激活",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/lb_login'),
            );
            echo JSON::encode($array);
		}
		else
		{
            $array = array(
                'info' => "发信失败,请重试！或者联系管理员查看邮件服务是否开启",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/lbfind_password'),
            );
            echo JSON::encode($array);
//			IError::show(403,"生成HASH重复，请重试");
		}
//		$message = "恭喜您，密码重置邮件已经发送！请到您的邮箱中去激活";
//		$this->redirect("/site/success/message/".urlencode($message));
	}

	//手机短信找回密码
	function find_password_mobile()
	{
		$username = IReq::get('username');
		if($username === null || !IValidate::mobi($username))
		{
            $array = array(
                'info' => "请输入正确的手机事情",
            );
            echo JSON::encode($array);
            exit;
		}


		$mobile_code = IFilter::act(IReq::get('mobile_code'));
		if($mobile_code === null)
		{
            $array = array(
                'info' => "请输入短信校验码",
            );
            echo JSON::encode($array);
            exit;
		}

		$userDB = new IModel('user');
		$userRow = $userDB->getObj('username = "'.$username.'"');
		if($userRow)
		{
			$findPasswordDB = new IModel('find_password');
			$dataRow = $findPasswordDB->getObj('user_id = '.$userRow['id'].' and hash = "'.$mobile_code.'"');
			if($dataRow)
			{
				//短信验证码已经过期
				if(time() - $dataRow['addtime'] > 3600)
				{
					$findPasswordDB->del("user_id = ".$userRow['id']);
					IError::show(403,"您的短信校验码已经过期了，请重新找回密码");
				}
				else
				{
                    $url =  IUrl::getHost().IUrl::creatUrl('/simple/restore_password/hash/'.$mobile_code.'/user_id/'.$userRow['id']);
                    $array = array(
                        'info' => "验证成功",
                        'url' => $url,
                    );
                    echo JSON::encode($array);
                    exit;
					//$this->redirect('/simple/restore_password/hash/'.$mobile_code.'/user_id/'.$userRow['user_id']);
				}
			}
			else
			{
                $array = array(
                    'info' => "手机验证码不对",
                );
                echo JSON::encode($array);
                exit;
			}
		}
		else
		{
            $array = array(
                'info' => "无此用户",
            );
            echo JSON::encode($array);
            exit;
		}
	}

	//找回密码发送手机验证码短信
	function send_message_mobile()
	{
		$username = IFilter::act(IReq::get('username'));
	//	$mobile = IFilter::act(IReq::get('mobile'));

		if($username === null || !IValidate::mobi($username))
		{
			die("请输入正确的手机号");
		}

		$userDB = new IModel('user');
		$userRow = $userDB->getObj('username = "'.$username.'"');

		if($userRow)
		{
			$findPasswordDB = new IModel('find_password');
			$dataRow = $findPasswordDB->query('user_id = '.$userRow['id'],'*','addtime desc');
			$dataRow = current($dataRow);

			//120秒是短信发送的间隔
			if( isset($dataRow['addtime']) && (time() - $dataRow['addtime'] <= 120) )
			{
				die("申请验证码的时间间隔过短，请稍候再试");
			}
			$mobile_code = rand(10000,99999);
			$findPasswordDB->setData(array(
				'user_id' => $userRow['id'],
				'hash'    => $mobile_code,
				'addtime' => time(),
			));
			if($findPasswordDB->add())
			{
				$content = smsTemplate::findPassword(array('{mobile_code}' => $mobile_code));
				$result = Hsms::send($username,$content);
				if($result == 'success')
				{
					die('success');
				}
				die($result);
			}
		}
		else
		{
			die('无此用户');
		}
	}

	/**
	 * @brief 重置密码验证
	 */
	function restore_password()
	{
		$hash = IFilter::act(IReq::get("hash"));
		$user_id = IFilter::act(IReq::get("user_id"),'int');

		if(!$hash)
		{
			IError::show(403,"找不到校验码");
		}
		$tb = new IModel("find_password");
		$addtime = time() - 3600*72;
		$where  = " `hash`='$hash' AND addtime > $addtime ";
		$where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

		$row = $tb->getObj($where);
		if(!$row)
		{
			IError::show(403,"校验码已经超时");
		}

		if($row['user_id'] != $user_id)
		{
			IError::show(403,"验证码不属于此用户");
		}

		$this->formAction = IUrl::creatUrl("/simple/do_restore_password/hash/$hash/user_id/".$user_id);
		$this->redirect("restore_password");
	}

	/**
	 * @brief 执行密码修改重置操作
	 */
	function do_restore_password()
	{
		$hash = IFilter::act(IReq::get("hash"));
		$user_id = IFilter::act(IReq::get("user_id"),'int');

		if(!$hash)
		{
            $array = array(
                'info' => "找不到校验码",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/find_password'),
            );
            echo JSON::encode($array);
            exit;
		}
		$tb = new IModel("find_password");
		$addtime = time() - 3600*24;
		$where  = " `hash`='$hash' AND addtime > $addtime ";
		$where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

		$row = $tb->getObj($where);
		if(!$row)
		{
            $array = array(
                'info' => "重置密码链接已超时",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/lbfind_password'),
            );
            echo JSON::encode($array);
            exit;
		}

		if($row['user_id'] != $user_id)
		{
            $array = array(
                'info' => "此链接不属于此用户",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/lbfind_password'),
            );
            echo JSON::encode($array);
            exit;
		}

		//开始修改密码
		$pwd   = IReq::get("password");
		$repwd = IReq::get("repassword");
		if($pwd == null || strlen($pwd) < 6 || $repwd!=$pwd)
		{
            $array = array(
                'info' => "新密码至少六位，且两次输入的密码应该一致。",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/find_password'),
            );
            echo JSON::encode($array);
            exit;
		}
		$pwd = md5($pwd);
		$tb_user = new IModel("user");
        $userinfo=$tb_user->getObj('id='.$user_id);
        if ($pwd==$userinfo['password']){
            $array = array(
                'info' => '新密码不得与旧密码相同',
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/edited_password'),
            );
            echo JSON::encode($array);
            exit;
        }
		$tb_user->setData(array("password" => $pwd));
		$re = $tb_user->update("id='{$row['user_id']}'");
		if($re !== false)
		{
			$message = "修改密码成功";
			$tb->del("`hash`='{$hash}'");
		//	$this->redirect("/site/success/message/".urlencode($message));
            $array = array(
                'info' => "修改密码成功",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/lb_login'),
            );
            echo JSON::encode($array);
            exit;
		}
        $array = array(
            'info' => "密码修改失败，请重试",
            'url' => IUrl::getHost().IUrl::creatUrl('/simple/find_password'),
        );
        echo JSON::encode($array);
        exit;
	}



	//修改密码
	function edit_password(){
        $user_id = $this->user['user_id'];
        $pwd   = IReq::get("password");
        $repwd = IReq::get("repassword");
        if($pwd == null || strlen($pwd) < 6 || $repwd!=$pwd)
        {
            $array = array(
                'info' => "新密码至少六位，且两次输入的密码应该一致。",
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/edited_password'),
            );
            echo JSON::encode($array);
            exit;
        }
        $pwd = md5($pwd);
        $tb_user = new IModel("user");
        $userinfo=$tb_user->getObj('id='.$user_id);
        if ($pwd==$userinfo['password']){
            $array = array(
                'info' => '新密码不得与旧密码相同',
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/edited_password'),
            );
            echo JSON::encode($array);
            exit;
        }

        $tb_user->setData(array("password" => $pwd));
        $re = $tb_user->update("id='{$user_id}'");
        if ($re!=false){
            $array = array(
                'info' => '修改密码成功',
                'url' => IUrl::getHost().IUrl::creatUrl('/ucenter/info_setup'),
            );
            echo JSON::encode($array);
            exit;
        }else{
            $array = array(
                'info' => '修改密码失败，请重新修改密码',
                'url' => IUrl::getHost().IUrl::creatUrl('/simple/edited_password'),
            );
            echo JSON::encode($array);
            exit;
        }
    }



    //添加收藏夹
    function favorite_add()
    {
    	$goods_id = IFilter::act(IReq::get('goods_id'),'int');
    	$message  = '';

    	if($goods_id == 0)
    	{
    		$message = '商品id值不能为空';
    	}
    	else if(!isset($this->user['user_id']) || !$this->user['user_id'])
    	{
    		$message = '请先登录';
    	}
    	else
    	{
    		$favoriteObj = new IModel('favorite');
    		$goodsRow    = $favoriteObj->getObj('user_id = '.$this->user['user_id'].' and rid = '.$goods_id);
    		if($goodsRow)
    		{
                $favoriteObj->del("id=".$goodsRow['id']);
    			$message = '取消收藏';
    		}
    		else
    		{
    			$catObj = new IModel('category_extend');
    			$catRow = $catObj->getObj('goods_id = '.$goods_id);
    			$cat_id = $catRow ? $catRow['category_id'] : 0;

	    		$dataArray   = array(
	    			'user_id' => $this->user['user_id'],
	    			'rid'     => $goods_id,
	    			'time'    => ITime::getDateTime(),
	    			'cat_id'  => $cat_id,
	    		);
	    		$favoriteObj->setData($dataArray);
	    		$favoriteObj->add();
	    		$message = '收藏成功';

	    		//商品收藏信息更新
	    		$goodsDB = new IModel('goods');
	    		$goodsDB->setData(array("favorite" => "favorite + 1"));
	    		$goodsDB->update("id = ".$goods_id,'favorite');
    		}
    	}
		$result = array(
			'isError' => true,
			'message' => $message,
		);

    	echo JSON::encode($result);
    }

    //获取oauth登录地址
    public function oauth_login()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
    	if($id)
    	{
    		$oauthObj = new OauthCore($id);
			$result   = array(
				'isError' => false,
				'url'     => $oauthObj->getLoginUrl(),
			);
    	}
    	else
    	{
			$result   = array(
				'isError' => true,
				'message' => '请选择要登录的平台',
			);
    	}
    	echo JSON::encode($result);
    }

    //第三方登录回调
    public function oauth_callback()
    {
    	$oauth_name = IFilter::act(IReq::get('oauth_name'));
    	$oauthObj   = new IModel('oauth');
    	$oauthRow   = $oauthObj->getObj('file = "'.$oauth_name.'"');

    	if(!$oauth_name && !$oauthRow)
    	{
    		IError::show(403,"{$oauth_name} 第三方平台信息不存在");
    	}
		$id       = $oauthRow['id'];
    	$oauthObj = new OauthCore($id);
    	$result   = $oauthObj->checkStatus($_GET);

    	if($result === true)
    	{
    		$oauthObj->getAccessToken($_GET);
	    	$userInfo = $oauthObj->getUserInfo();

	    	if(isset($userInfo['id']) && isset($userInfo['name']) && $userInfo['id'] && $userInfo['name'])
	    	{
	    		$this->bindUser($userInfo,$id);
	    		return;
	    	}
    	}
    	else
    	{
    		IError::show("回调URL参数错误");
    	}
    }

    //同步绑定用户数据
    public function bindUser($userInfo,$oauthId)
    {
    	$userObj      = new IModel('user');
    	$oauthUserObj = new IModel('oauth_user');
    	$oauthUserRow = $oauthUserObj->getObj("oauth_user_id = '{$userInfo['id']}' and oauth_id = '{$oauthId}' ",'user_id');
		if($oauthUserRow)
		{
			//清理oauth_user和user表不同步匹配的问题
			$tempRow = $userObj->getObj("id = '{$oauthUserRow['user_id']}'");
			if(!$tempRow)
			{
				$oauthUserObj->del("oauth_user_id = '{$userInfo['id']}' and oauth_id = '{$oauthId}' ");
			}
		}

    	//存在绑定账号oauth_user与user表同步正常！
    	if(isset($tempRow) && $tempRow)
    	{
    		$userRow = plugin::trigger("isValidUser",array($tempRow['username'],$tempRow['password']));
    		plugin::trigger("userLoginCallback",$userRow);
    		$callback = plugin::trigger('getCallback');
    		$callback = $callback ? $callback : "/ucenter/index";
			$this->redirect($callback);
    	}
    	//没有绑定账号
    	else
    	{
	    	$userCount = $userObj->getObj("username = '{$userInfo['name']}'",'count(*) as num');

	    	//没有重复的用户名
	    	if($userCount['num'] == 0)
	    	{
	    		$username = $userInfo['name'];
	    	}
	    	else
	    	{
	    		//随即分配一个用户名
	    		$username = $userInfo['name'].$userCount['num'];
	    	}
			$userInfo['name'] = $username;
	    	ISession::set('oauth_id',$oauthId);
	    	ISession::set('oauth_userInfo',$userInfo);
	    	$this->setRenderData($userInfo);
	    	$this->redirect('bind_user',false);
    	}
    }

	//执行绑定已存在用户
    public function bind_exists_user()
    {
    	$login_info     = IReq::get('login_info');
    	$password       = IReq::get('password');
    	$oauth_id       = IFilter::act(ISession::get('oauth_id'));
    	$oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

    	if(!$oauth_id || !$oauth_userInfo || !isset($oauth_userInfo['id']))
    	{
    		IError::show("缺少oauth信息");
    	}

    	if($userRow = plugin::trigger("isValidUser",array($login_info,md5($password))))
    	{
    		$oauthUserObj = new IModel('oauth_user');

    		//插入关系表
    		$oauthUserData = array(
    			'oauth_user_id' => $oauth_userInfo['id'],
    			'oauth_id'      => $oauth_id,
    			'user_id'       => $userRow['user_id'],
    			'datetime'      => ITime::getDateTime(),
    		);
    		$oauthUserObj->setData($oauthUserData);
    		$oauthUserObj->add();

    		plugin::trigger("userLoginCallback",$userRow);

			//自定义跳转页面
			$this->redirect('/site/success?message='.urlencode("登录成功！"));
    	}
    	else
    	{
    		$this->setError("用户名和密码不匹配");
    		$_GET['bind_type'] = 'exists';
    		$this->redirect('bind_user',false);
    		Util::showMessage("用户名和密码不匹配");
    	}
    }

	//执行绑定注册新用户用户
    public function bind_not_exists_user()
    {
    	$oauth_id       = IFilter::act(ISession::get('oauth_id'));
    	$oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

    	if(!$oauth_id || !$oauth_userInfo || !isset($oauth_userInfo['id']))
    	{
    		IError::show("缺少oauth信息");
    	}

    	//调用_userInfo注册插件
		$result = plugin::trigger('userRegAct',$_POST);
		if(is_array($result))
		{
			//插入关系表
			$oauthUserObj = new IModel('oauth_user');
			$oauthUserData = array(
				'oauth_user_id' => $oauth_userInfo['id'],
				'oauth_id'      => $oauth_id,
				'user_id'       => $result['id'],
				'datetime'      => ITime::getDateTime(),
			);
			$oauthUserObj->setData($oauthUserData);
			$oauthUserObj->add();
			$this->redirect('/site/success?message='.urlencode("注册成功！"));
		}
		else
		{
    		$this->setError($result);
    		$this->redirect('bind_user',false);
    		Util::showMessage($result);
		}
    }

	/**
	 * @brief 商户的增加动作
	 */
	public function seller_reg()
	{
		$seller_name = IValidate::name(IReq::get('seller_name')) ? IReq::get('seller_name') : "";
		$email       = IValidate::email(IReq::get('email'))      ? IReq::get('email')       : "";
		$truename    = IValidate::name(IReq::get('true_name'))   ? IReq::get('true_name')   : "";
		$phone       = IValidate::phone(IReq::get('phone'))      ? IReq::get('phone')       : "";
		$mobile      = IValidate::mobi(IReq::get('mobile'))      ? IReq::get('mobile')      : "";
		$home_url    = IValidate::url(IReq::get('home_url'))     ? IReq::get('home_url')    : "";

		$password    = IFilter::act(IReq::get('password'));
		$repassword  = IFilter::act(IReq::get('repassword'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));

		if($password == '')
		{
			$errorMsg = '请输入密码！';
		}

		if($password != $repassword)
		{
			$errorMsg = '两次输入的密码不一致！';
		}

		if(!$seller_name)
		{
			$errorMsg = '填写正确的登陆用户名';
		}

		if(!$truename)
		{
			$errorMsg = '填写正确的商户真实全称';
		}

		//创建商家操作类
		$sellerDB = new IModel("seller");
		if($seller_name && $sellerDB->getObj("seller_name = '{$seller_name}'"))
		{
			$errorMsg = "登录用户名重复";
		}
		else if($truename && $sellerDB->getObj("true_name = '{$truename}'"))
		{
			$errorMsg = "商户真实全称重复";
		}

		//操作失败表单回填
		if(isset($errorMsg))
		{
			$this->sellerRow = IFilter::act($_POST,'text');
			$this->redirect('seller',false);
			Util::showMessage($errorMsg);
		}

		//待更新的数据
		$sellerRow = array(
			'true_name' => $truename,
			'phone'     => $phone,
			'mobile'    => $mobile,
			'email'     => $email,
			'address'   => $address,
			'province'  => $province,
			'city'      => $city,
			'area'      => $area,
			'home_url'  => $home_url,
			'is_lock'   => 1,
		);

		//商户资质上传
		if(isset($_FILES['paper_img']['name']) && $_FILES['paper_img']['name'])
		{
			$uploadObj = new PhotoUpload();
			$uploadObj->setIterance(false);
			$photoInfo = $uploadObj->run();
			if(isset($photoInfo['paper_img']['img']) && file_exists($photoInfo['paper_img']['img']))
			{
				$sellerRow['paper_img'] = $photoInfo['paper_img']['img'];
			}
		}

		$sellerRow['seller_name'] = $seller_name;
		$sellerRow['password']    = md5($password);
		$sellerRow['create_time'] = ITime::getDateTime();

		$sellerDB->setData($sellerRow);
		$sellerDB->add();

		//短信通知商城平台
		if($this->_siteConfig->mobile)
		{
			$content = smsTemplate::sellerReg(array('{true_name}' => $truename));
			$result = Hsms::send($this->_siteConfig->mobile,$content,0);
		}

		$this->redirect('/site/success?message='.urlencode("申请成功！请耐心等待管理员的审核"));
	}

	//添加地址ajax
	function address_add()
	{

		$id          = IFilter::act(IReq::get('id'),'int');
		$accept_name = IFilter::act(IReq::get('accept_name'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$zip         = IFilter::act(IReq::get('zip'));
		$telphone    = IFilter::act(IReq::get('telphone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
		$is_default  = 1;
        $user_id     = $this->user['user_id'];

		//整合的数据
        $sqlData = array(
        	'user_id'     => $user_id,
        	'accept_name' => $accept_name,
        	'zip'         => $zip,
        	'telphone'    => $telphone,
        	'province'    => $province,
        	'city'        => $city,
        	'area'        => $area,
        	'address'     => $address,
        	'mobile'      => $mobile,
        	'is_default'  => $is_default,
        );

        $checkArray = $sqlData;
        unset($checkArray['telphone'],$checkArray['zip'],$checkArray['user_id']);
        foreach($checkArray as $key => $val)
        {
        	if(!$val)
        	{
        		$result = array('result' => false,'msg' => '请仔细填写收货地址');
				die(JSON::encode($result));
        	}
        }

        if($user_id)
        {

        	$model = new IModel('address');
            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$this->user['user_id']);

            $model->setData($sqlData);
            if($id)
        	{
        		$model->update("id = ".$id." and user_id = ".$user_id);
        	}
        	else
        	{
        		$id = $model->add();
        	}
        	$sqlData['id'] = $id;
        }
        //访客地址保存
        else
        {
        	ISafe::set("address",$sqlData);
        }

        $areaList = area::name($province,$city,$area);
		$sqlData['province_val'] = $areaList[$province];
		$sqlData['city_val']     = $areaList[$city];
		$sqlData['area_val']     = $areaList[$area];
		$result = array('data' => $sqlData);
		die(JSON::encode($result));
	}


    //添加地址ajax
    function address_info()
    {
        $id          = IFilter::act(IReq::get('addressId'),'int');
        $user_id     = $this->user['user_id'];
        if($user_id)
        {
            $model = new IModel("address");
            $info = $model->getObj("id=".$id." and user_id=".$user_id);
            if(empty($info)){
                $result = array('result' => false,'msg' => '地址不存在');
                die(JSON::encode($result));
            }
            $result = array('data' => $info);
            die(JSON::encode($result));
        }
        //访客地址保存
        else
        {
            $result = array('result' => false,'msg' => '请选登录');
            die(JSON::encode($result));
        }


    }

    //添加地址ajax
    function address_add_ajax()
    {

        $id          = IFilter::act(IReq::get('id'),'int');
        $accept_name = IFilter::act(IReq::get('accept_name'));
        $province    = IFilter::act(IReq::get('province'),'int');
        $city        = IFilter::act(IReq::get('city'),'int');
        $area        = IFilter::act(IReq::get('area'),'int');
        $address     = IFilter::act(IReq::get('address'));

        $mobile      = IFilter::act(IReq::get('mobile'));
        $is_default      = 1;
        $user_id     = $this->user['user_id'];

        //整合的数据
        $sqlData = array(
            'user_id'     => $user_id,
            'accept_name' => $accept_name,
            'province'    => $province,
            'city'        => $city,
            'area'        => $area,
            'address'     => $address,
            'mobile'      => $mobile,
            'is_default'  => $is_default,
        );

        $checkArray = $sqlData;
        unset($checkArray['user_id']);
        foreach($checkArray as $key => $val)
        {
            if(!$val)
            {
                $result = array('result' => false,'msg' => '请仔细填写收货地址');
                die(JSON::encode($result));
            }
        }

        if($user_id)
        {


            $model = new IModel('address');

            if($is_default==1)
            {
                $model->setData(array('is_default' => 0));
                $model->update("user_id = ".$this->user['user_id']);
            }

            $model->setData($sqlData);
            if($id)
            {
                $model->update("id = ".$id." and user_id = ".$user_id);
            }
            else
            {
                $id = $model->add();
            }
            $sqlData['id'] = $id;
        }
        //访客地址保存
        else
        {
            ISafe::set("address",$sqlData);
        }

        $areaList = area::name($province,$city,$area);
        $sqlData['province_val'] = $areaList[$province];
        $sqlData['city_val']     = $areaList[$city];
        $sqlData['area_val']     = $areaList[$area];
        $result = array('data' => $sqlData);
        die(JSON::encode($result));
    }


    function addressDefault(){
        $id          = IFilter::act(IReq::get('addressId'),'int');
        $user_id     = $this->user['user_id'];
        if($user_id)
        {
            $model = new IModel("address");

            $model->setData(array('is_default' => 0));
            $model->update("user_id = ".$user_id);

            $model->setData(array('is_default' => 1));
            $model->update("id = ".$id);

            $info = $model->getObj("id=".$id." and user_id=".$user_id);
            if(empty($info)){
                $result = array('result' => false,'msg' => '地址不存在');
                die(JSON::encode($result));
            }
            $result = array('data' => $info);
            die(JSON::encode($result));
        }
        //访客地址保存
        else
        {
            $result = array('result' => false,'msg' => '请选登录');
            die(JSON::encode($result));
        }
    }

    function update_iscart(){
        $car_id  = IFilter::act(IReq::get('car_id'));
        $is_cart  = IFilter::act(IReq::get('is_cart'),'int')?IFilter::act(IReq::get('is_cart'),'int'):0;

        if($this->user['user_id'] == null){
            $result = array(
                'isError' => true,
                'message' => "请先登录",
            );
            echo JSON::encode($result);
            exit;
        }
        if($car_id>0){
            $tb_goods_cars = new IModel("goods_car");
            $update['is_cart'] = $is_cart?1:0;
            $tb_goods_cars->setData($update);
            $tb_goods_cars->update("id in (".$car_id.") and user_id=".$this->user['user_id']);
            $result = array(
                'isError' => false,
                'message' => "成功",
            );
            echo JSON::encode($result);
            exit;

        }else{
            $result = array(
                'isError' => true,
                'message' => "无数据",
            );
            echo JSON::encode($result);
            exit;
        }
    }


    //上传图片
    public function upload_pic(){
        $result = array(
            'isError' => true,
        );

        $base64_string = $_POST['base64_string'];

        $savename = uniqid() . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y/m/d') . "/";
        $savepath = $dir . $savename;

        IFile::mkdir($dir);

        $image = self::base64_to_img($base64_string, $savepath);

        if ($image) {
            echo '{"status":1,"content":"上传成功","url":"' . $image . '"}';
        } else {
            echo '{"status":0,"content":"上传失败"}';
        }
    }

//上传图片
    public function upload_pic_cart2(){

        if($this->user['user_id'] == null){
            echo '{"status":0,"content":"上传失败"}';
            exit;
        }

        $result = array(
            'isError' => true,
        );

        $base64_string = $_POST['base64_string'];

        $savename = uniqid() . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y/m/d') . "/";
        $savepath = $dir . $savename;

        IFile::mkdir($dir);

        $image = self::base64_to_img($base64_string, $savepath);

        if ($image) {
            ICookie::set($this->user['user_id']."cart2orderimg",$image);
            echo '{"status":1,"content":"上传成功","url":"' . $image . '"}';
        } else {
            echo '{"status":0,"content":"上传失败"}';
        }
    }



    static function base64_to_img($base64_string, $output_file)
    {
        $ifp = fopen($output_file, "wb");
        fwrite($ifp, base64_decode($base64_string));
        fclose($ifp);
        return ($output_file);
    }


    public function removeCartById(){
        $car_id  = IFilter::act(IReq::get('car_id'));
        $user_id = $this->user['user_id'];
        $cartObj   = new IModel("goods_car");
        $cartObj->del("id in (".$car_id.") and user_id=".$user_id);
        echo '{"status":0,"content":"删除成功"}';
    }


    //注册发送短信 三石
    function check_mobile_sms(){
        $mobile = IFilter::act(IReq::get('mobile'));
   //     $captcha = IFilter::act(IReq::get('captcha'));
//        if($captcha != ISafe::get('captcha'))
//        {
//            $result = array(
//                'isError' => true,
//                'message' => '验证码输入不正确',
//            );
//            echo JSON::encode($result);
//            exit;
//        }

        $tb_user = new IModel("user");
        $info = $tb_user->getObj("username='".$mobile."'");
        if($info){
            $result = array(
                'isError' => true,
                'message' => '此手机号已注册！',
            );
            echo JSON::encode($result);
            exit;
        }

        $code = rand(10000,99999);
        $content = smsTemplate::checkCode(array('{mobile_code}' => $code));
        $result = Hsms::send($mobile,$content);
        if($result == 'success')
        {
            ISafe::set('code'.$mobile,$code);
            $result = array(
                'isError' => false,
                'message' => '发送成功',
            );
            echo JSON::encode($result);
            exit;
        }else{
            $result = array(
                'isError' => true,
                'message' => $result,
            );
            echo JSON::encode($result);
            exit;
        }
    }

    function wxLoginAfter(){
        $uid = IFilter::act(IReq::get('uid'));
        $jumpUrl = IFilter::act(IReq::get('jumpUrl'));
        $jumpUrl = base64_decode($jumpUrl);
        $userM= new IModel('user');
        $user=$userM->getObj("id='$uid'");
        //用户私密数据
        ISafe::set('user_id',$user['id']);
        ISafe::set('username',$user['username']);
        ISafe::set('head_ico',isset($user['head_ico']) ? $user['head_ico'] : '');
        ISafe::set('user_pwd',$user['password']);
        //print_r(ISafe::get('username').'==='.ISafe::get('user_pwd'));exit();
        $this->user['username']=$user['username'];
        $this->user['user_pwd']=$user['password'];
        $this->user['user_id']=$user['id'];
        $this->user['head_ico']=$user['head_ico'];
        $this->redirect($jumpUrl);
    }
}
