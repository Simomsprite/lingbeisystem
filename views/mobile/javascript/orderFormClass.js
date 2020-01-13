/**
 * 订单对象
 * address:收货地址; delivery:配送方式; payment:支付方式;
 */
function orderFormClass()
{
	_self = this;

	//商家信息
	this.seller = null;

	//默认数据
	this.deliveryId   = 0;

	//免运费的商家ID
	this.freeFreight  = [];

	//订单各项数据
	this.orderAmount  = 0;//订单金额
	this.goodsSum     = 0;//商品金额
	this.deliveryPrice= 0;//运费金额
	this.paymentPrice = 0;//支付金额
	this.taxPrice     = 0;//税金
	this.protectPrice = 0;//保价
	this.ticketPrice  = 0;//代金券

	/**
	 * 算账
	 */
	this.doAccount = function()
	{
		//税金
		this.taxPrice = $('input:checkbox[name="taxes"]:checked').length > 0 ? $('input:checkbox[name="taxes"]:checked').val() : 0;
		//最终金额
		this.orderAmount = parseFloat(this.goodsSum) - parseFloat(this.ticketPrice) + parseFloat(this.deliveryPrice) + parseFloat(this.paymentPrice) + parseFloat(this.taxPrice) + parseFloat(this.protectPrice);

		this.orderAmount = this.orderAmount <=0 ? 0 : this.orderAmount.toFixed(2);

		//刷新DOM数据
		$('#final_sum').html(this.orderAmount);
		$('[name="ticket_value"]').html(this.ticketPrice);
		$('#delivery_fee_show').html(this.deliveryPrice);
		$('#protect_price_value').html(this.protectPrice);
		$('#payment_value').html(this.paymentPrice);
		$('#tax_fee').html(this.taxPrice);
	}

	//地址修改
	this.addressEdit = function(addressId)
	{
        //获取配送信息和运费
        $.getJSON(creatUrl("simple/address_info"),{"addressId":addressId,"random":Math.random()},function(json){
            if(json.result==false){
                layer.open({
                    content: json.msg
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
            }else{
                var formObj = new Form('edit_address');
                formObj.init(json.data);
                var areaInstance = new areaSelect('province');
                areaInstance.init({"province":json.data.province,"city":json.data.city,"area":json.data.area});
                $(".address-pop2").animate({left:'0'},200);
            }
        });
	}

    this.addressDefault = function(addressId){
        var index =   layer.open({type: 2}); //0代表加载的风格，支持0-

        //获取配送信息和运费
        $.getJSON(creatUrl("simple/addressDefault"),{"addressId":addressId,"random":Math.random()},function(json){
            if(json.result==false){
                layer.open({
                    content: json.msg
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
            }else{

                location.reload();
                // var addressLiHtml = template.render('addressLiTemplate',{"item":json.data});
                // $('#sele_address').html(addressLiHtml);
                // $('input:radio[name="radio_address"]:first').trigger('click');
                // $(".order-pop").animate({left:'-100%'},200);
                // layer.close(index);

            }
        });
    }

	//地址删除
	this.addressDel  = function(addressId)
	{
		$('#addressItem'+addressId).remove();
		$.get(creatUrl("ucenter/address_del"),{"id":addressId});
	}

	//地址增加
	this.addressAdd  = function()
	{
        var areaInstance = new areaSelect('province');
        areaInstance.init();
        $(".address-pop2").animate({left:'0'},200);
	}

    this.addressSave  = function()
    {
        $("#edit_address").Validform({
            tiptype:function(msg){
                layer.open({
                    content: msg
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });

            },
            tipSweep:true,
            ajaxPost:true,
            callback:function(data){
                if(data.result==false){
                    layer.open({
                        content: data.msg
                        ,skin: 'msg'
                        ,time: 2 //2秒后自动关闭
                    });
                }else{
                    layer.open({
                        content: "操作成功"
                        ,skin: 'msg'
                        ,time: 2 //2秒后自动关闭
                    });
                    location.reload();
                    //var addressLiHtml = template.render('addressLiTemplate',{"item":data.data});
                    //$('#sele_address').html(addressLiHtml);
                    //$('input:radio[name="radio_address"]:first').trigger('click');
                    // $(".order-pop").animate({left:'-100%'},200);


                }

            }
        });
    }


	//根据省份地区ajax获取配送方式和运费
	this.getDelivery = function(province,is_prescription)
	{
		//整合当前的商品信息
		var goodsId   = [];
		var productId = [];
		var num       = [];
		$('[id^="deliveryFeeBox_"]').each(function(i)
		{
			var idValue = $(this).attr('id');
			var dataArray = idValue.split("_");

			goodsId.push(dataArray[1]);
			productId.push(dataArray[2]);
			num.push(dataArray[3]);
		});

		//获取配送信息和运费
		if(is_prescription == 2){
            $.getJSON(creatUrl("block/order_delivery"),{"province":province,"goodsId":goodsId,"productId":productId,"num":num,"random":Math.random(),"type":1},function(json){
                for(indexVal in json)
                {
                    var content = json[indexVal];
                    //正常可以送达
                    if(content.if_delivery == 0)
                    {
                        for(var tIndex in _self.freeFreight)
                        {
                            var sellerId  = _self.freeFreight[tIndex];
                            content.price = parseFloat(content.price) - parseFloat(content.seller_price[sellerId]);
                        }
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("protectPrice",parseFloat(content.protect_price));
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("deliveryPrice",parseFloat(content.price));

                        var deliveryHtml = template.render("deliveryTemplate",{"item":content});
                        $("#deliveryShow").html(deliveryHtml);
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",false);

                    }
                    //配送方式不能配送
                    else
                    {
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",true);
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("checked",false);
                        $("#deliveryShow"+content.id).html("<span class='red'>您选择地区部分商品无法送达</span>");
                    }
                }
                var checkVal = $('input[type="radio"][name="delivery_id"]:checked');
                if(checkVal.length > 0)
                {
                    _self.deliverySelected(checkVal.val());
                }
                else if(_self.deliveryId > 0 && $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').prop('disabled') != "disabled")
                {
                    $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').trigger('click');
                }
            });
		}else{
            $.getJSON(creatUrl("block/order_delivery"),{"province":province,"goodsId":goodsId,"productId":productId,"num":num,"random":Math.random(),"type":0},function(json){
                for(indexVal in json)
                {
                    var content = json[indexVal];
                    //正常可以送达
                    if(content.if_delivery == 0)
                    {
                        for(var tIndex in _self.freeFreight)
                        {
                            var sellerId  = _self.freeFreight[tIndex];
                            content.price = parseFloat(content.price) - parseFloat(content.seller_price[sellerId]);
                        }
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("protectPrice",parseFloat(content.protect_price));
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("deliveryPrice",parseFloat(content.price));

                        var deliveryHtml = template.render("deliveryTemplate",{"item":content});
                        $("#deliveryShow").html(deliveryHtml);
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",false);

                    }
                    //配送方式不能配送
                    else
                    {
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",true);
                        $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("checked",false);
                        $("#deliveryShow"+content.id).html("<span class='red'>您选择地区部分商品无法送达</span>");
                    }
                }
                var checkVal = $('input[type="radio"][name="delivery_id"]:checked');
                if(checkVal.length > 0)
                {
                    _self.deliverySelected(checkVal.val());
                }
                else if(_self.deliveryId > 0 && $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').prop('disabled') != "disabled")
                {
                    $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').trigger('click');
                }
            });
		}

	}

	/**
	 * address初始化
	 */
	this.addressInit = function()
	{
		var addressList = $('input:radio[name="radio_address"]');
		if(addressList.length > 0)
		{
			addressList.first().trigger('click');
		}
	}

	/**
	 * delivery初始化
	 */
	this.deliveryInit = function(defaultDeliveryId)
	{
		this.deliveryId = defaultDeliveryId;
	}

	/**
	 * delivery选中
	 * @param int deliveryId 配送方式ID
	 */
	this.deliverySelected = function(deliveryId)
	{
		var deliveryObj = $('input[type="radio"][name="delivery_id"][value="'+deliveryId+'"]');
		this.protectPrice  = deliveryObj.data("protectPrice") > 0 ? deliveryObj.data("protectPrice") : 0;
		this.deliveryPrice = deliveryObj.data("deliveryPrice")> 0 ? deliveryObj.data("deliveryPrice"): 0;
		//先发货后付款
		if(deliveryObj.attr('paytype') == '1')
		{
			$('input[type="radio"][name="payment"]').prop('checked',false);
			$('input[type="radio"][name="payment"]').prop('disabled',true);
			$('#paymentBox').hide("slow");

			//支付手续费清空
			this.paymentPrice = 0;
		}
		else
		{
			$('input[type="radio"][name="payment"]').prop('disabled',false);
			$('#paymentBox').show("slow");
		}
		_self.doAccount();
	}

	/**
	 * payment初始化
	 */
	this.paymentInit = function(defaultPaymentId)
	{
        if(defaultPaymentId > 0)
		{
			$('input:radio[name="payment"][value="'+defaultPaymentId+'"]').trigger('click');
            $('input:radio[name="payment"][value="'+defaultPaymentId+'"]').parent().addClass("active");
		}else{
            $('input:radio[name="payment"]').first().trigger('click');
            $('input:radio[name="payment"]').first().parent().addClass("active");
        }
        ps_button();
        this.doAccount();
    }

    this.paymentInit1 = function()
    {
        $('input:radio[name="payment"]').first().trigger('click');
        $('input:radio[name="payment"]').first().parent().addClass("active");
        ps_button();
        this.doAccount();
    }
    this.paymentInit2 = function()
    {
        $('input:radio[name="payment"][value="0"]').trigger('click');
        $('input:radio[name="payment"][value="0"]').parent().addClass("active");
        ps_button();
        this.doAccount();
    }

	/**
	 * payment选择
	 */
	this.paymentSelected = function(paymentId)
	{
        var paymentObj = $('input[type="radio"][name="payment"][value="'+paymentId+'"]');
        this.paymentPrice = paymentObj.attr("alt");
		this.doAccount();
	}

	/**
	 * 检查表单是否可以提交
	 */
	this.isSubmit = function()
	{
		var addressObj  = $('input[type="radio"][name="radio_address"]:checked');
		var deliveryObj = $('input[type="radio"][name="delivery_id"]:checked');
		var paymentObj  = $('input[type="radio"][name="payment"]:checked');


		if(addressObj.length == 0)
		{
            layer.open({
                content: "请选择收件人地址"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return false;
		}

		if(deliveryObj.length == 0)
		{
            layer.open({
                content: "请选择配送方式"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return false;
		}

		if(deliveryObj.attr('paytype') == 2 && $('input[name="takeself"]').length == 0)
		{
            layer.open({
                content: "请选择配送方式中的自提点"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return false;
		}

		if(paymentObj.length == 0 && deliveryObj.attr('paytype') != "1")
		{
            layer.open({
                content: "请选择支付方式"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return false;
		}
		return true;
	}


    /**
     * 检查表单是否可以提交
     */
    this.isSubmit2 = function()
    {
        var addressObj  = $('input[type="radio"][name="radio_address"]:checked');
        var deliveryObj = $('input[type="radio"][name="delivery_id"]:checked');
        var paymentObj  = $('input[type="radio"][name="payment"]:checked');
        var order_img = $("#order_img").val();


        if(order_img == ""){
            layer.open({
                content: "请上传处方药处方"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
            return false;
        }
        if(addressObj.length == 0)
        {
            layer.open({
                content: "请选择收件人地址"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
            return false;
        }

        if(deliveryObj.length == 0)
        {
            layer.open({
                content: "请选择配送方式"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
            return false;
        }

        if(deliveryObj.attr('paytype') == 2 && $('input[name="takeself"]').length == 0)
        {
            layer.open({
                content: "请选择配送方式中的自提点"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
            return false;
        }

        if(paymentObj.length == 0 && deliveryObj.attr('paytype') != "1")
        {
            layer.open({
                content: "请选择支付方式"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
            return false;
        }
        return true;
    }
	/**
	 * 点击选择自提点
	 */
	this.selectTakeself = function(deliveryId)
	{
		art.dialog.open(creatUrl("block/takeself"),{
			title:'选择自提点',
			okVal:'选择',
			ok:function(iframeWin, topWin)
			{
				var takeselfJson = $(iframeWin.document).find('[name="takeselfItem"]:checked').val();
				if(!takeselfJson)
				{
					alert('请选择自提点');
					return false;
				}
				var json = $.parseJSON(takeselfJson);
				$('#takeself'+deliveryId).empty();
				$('#takeself'+deliveryId).html(template.render('takeselfTemplate',{"item":json}));

				//动态生成节点
				_self.getForm().find("input[name='takeself']").remove();

				//动态插入节点
				_self.getForm().prepend("<input type='hidden' name='takeself' value='"+json.id+"' />");

				return true;
			}
		});
	}

	/**
	 * 代金券显示
	 */
	this.ticketShow = function(ticket,ticketprice)
	{
        //动态插入节点
        _self.getForm().prepend("<input type='hidden' name='ticket_id[]' value='"+ticket+"' />");
        _self.ticketPrice = ticketprice;
        _self.doAccount()
        $(".order-pop").animate({left:'-100%'},200);
        $("#ticketShow_html").html("已选<em class='vi-org2 abe-space'>1</em>张优惠券");
	}

	//获取form表单
	this.getForm = function()
	{
		return $('form[name="order_form"]').length == 1 ? $('form[name="order_form"]') : $('form:first');
	}
}