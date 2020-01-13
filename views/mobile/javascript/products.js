/**
 * @brief 商品类库
 * @param int goods_id 商品ID参数
 * @param int user_id 用户ID参数
 * @param string promo 活动类型参数
 * @param int active_id 活动ID参数
 */
function productClass(goods_id,user_id,promo,active_id)
{
	_self         = this;
	this.goods_id = goods_id; //商品ID
	this.user_id  = user_id;  //用户ID
	this.province_id;         //收货地址省份ID
	this.province_name;       //收货地址省份名字

	this.promo    = promo;    //活动类型
	this.active_id= active_id;//活动ID

	/**
	 * 获取评论数据
	 * @page 分页数
	 */
	this.comment_ajax = function(page)
	{
		if(!page && $.trim($('#commentBox').text()))
		{
			return;
		}

		page = page ? page : 1;
		$.getJSON(creatUrl("site/comment_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空评论数据
			$('#commentBox').empty();

			for(var item in json.data)
			{
				var commentHtml = template.render('commentRowTemplate',json.data[item]);
				$('#commentBox').append(commentHtml);
			}
			$('#commentBox').append(json.pageHtml);
		});
	}

	/**
	 * 获取购买记录数据
	 * @page 分页数
	 */
	this.history_ajax = function(page)
	{
		if(!page && $.trim($('#historyBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/history_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空购买历史记录
			$('#historyBox').empty();
			$('#historyBox').parent().parent().find('.pages_bar').remove();

			for(var item in json.data)
			{
				var historyHtml = template.render('historyRowTemplate',json.data[item]);
				$('#historyBox').append(historyHtml);
			}
			$('#historyBox').parent().after(json.pageHtml);
		});
	}

	/**
	 * 获取咨询记录数据
	 * @page 分页数
	 */
	this.refer_ajax = function(page)
	{
		if(!page && $.trim($('#referBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/refer_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空评论数据
			$('#referBox').empty();

			for(var item in json.data)
			{
				var commentHtml = template.render('referRowTemplate',json.data[item]);
				$('#referBox').append(commentHtml);
			}
			$('#referBox').append(json.pageHtml);
		});
	}

	/**
	 * 获取购买记录数据
	 * @page 分页数
	 */
	this.discuss_ajax = function(page)
	{
		if(!page && $.trim($('#discussBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/discuss_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空购买历史记录
			$('#discussBox').empty();
			$('#discussBox').parent().parent().find('.pages_bar').remove();

			for(var item in json.data)
			{
				var historyHtml = template.render('discussRowTemplate',json.data[item]);
				$('#discussBox').append(historyHtml);
			}
			$('#discussBox').parent().after(json.pageHtml);
		});
	}

	/**
	 * @brief 计算各种物流的运费
	 * @param int provinceId 省份ID
	 * @param string provinceName 省份名称
	 */
	this.delivery = function(provinceId,provinceName)
	{
		$('[name="localArea"]').text(provinceName);

		var buyNums   = $('#buyNums').val();
		var productId = $('#product_id').val();
		var goodsId   = _self.goods_id;

		//物流显示模板
		var deliveryTemplate = '<%if(if_delivery == 0){%><%=name%>：<b style="color:#fe6c00">￥<%=price%></b>（<%=description%>）&nbsp;&nbsp;';
			deliveryTemplate+= '<%}else{%>';
			deliveryTemplate+= '<%=name%>：<b style="color:red">该地区无法送达</b>&nbsp;&nbsp;<%}%>';

		//通过省份id查询出配送方式，并且传送总重量计算出运费,然后显示配送方式
		$.getJSON(creatUrl("block/order_delivery"),{'province':provinceId,'goodsId':goodsId,'productId':productId,'num':buyNums,'random':Math.random},function(json)
		{
			//清空配送信息
			$('#deliveInfo').empty();

			for(var item in json)
			{
				var deliveRowHtml = template.compile(deliveryTemplate)(json[item]);
				$('#deliveInfo').append(deliveRowHtml);
			}
		});

		//保存所选择的数据
		_self.province_id   = provinceId;
		_self.province_name = provinceName;
	}

	/**
	 * @brief 根据新浪地区接口获取当前所在地的运费
	 */
	this.initLocalArea = function()
	{
		//根据IP查询所在地
		$.getScript('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js',function(){
			var ipAddress = remote_ip_info['province'];

			//根据接口返回的数据查找与iWebShop系统匹配的地区
			$.getJSON(creatUrl("block/searchProvince"),{'province':ipAddress,'random':Math.random},function(json)
			{
				if(json.flag == 'success')
				{
					//计算各个配送方式的运费
					_self.delivery(json.area_id,ipAddress);
				}
			});
		});

		//绑定地区选择按钮事件
		$('[name="areaSelectButton"]').bind("click",function(){
			var provinceId   = $(this).attr('value');
			var provinceName = $(this).text();
			_self.delivery(provinceId,provinceName);
		});
	}

	//发表讨论
	this.sendDiscuss = function()
	{
		var userId = _self.user_id;
		if(userId)
		{
			$('#discussTable').show('normal');
			$('#discussContent').focus();
		}
		else
		{
            layer.open({
                content:'请登陆后再发表讨论'
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
		}
	}

	//发布讨论数据
	this.sendDiscussData = function()
	{
		var content = $('#discussContent').val();
		var captcha = $('[name="captcha"]').val();

		if($.trim(content)=='')
		{
            layer.open({
                content:'请输入讨论内容'
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			$('#discussContent').focus();
			return false;
		}
		if($.trim(captcha)=='')
		{
            layer.open({
                content:'请输入验证码'
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			$('[name="captcha"]').focus();
			return false;
		}

		$.getJSON(creatUrl("site/discussUpdate"),{'content':content,'captcha':captcha,'random':Math.random,'id':_self.goods_id},function(json)
		{
			if(json.isError == false)
			{
				var discussHtml = template.render('discussRowTemplate',json);
				$('#discussBox').prepend(discussHtml);

				//清除数据
				$('#discussContent').val('');
				$('[name="captcha"]').val('');
				$('#discussTable').hide('normal');
				changeCaptcha();
			}
			else
			{
                layer.open({
                    content:json.message
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
			}
		});
	}

	//检查选择规格是否完全
	this.checkSpecSelected = function()
	{
		if(_self.specCount === $('[specId].current').length)
		{
			return true;
		}
		return false;
	}

	//初始化规格数据
	this.initSpec = function()
	{
		//根据specId查询规格种类数量
		_self.specCount = 0;
		var tmpSpecId   = "";
		$('[specId]').each(function()
		{
			if(tmpSpecId != $(this).attr('specId'))
			{
				_self.specCount++;
				tmpSpecId = $(this).attr('specId');
			}
		});

		//绑定商品规格函数
		$('[specId]').bind('click',function()
		{
			//设置选中状态
			$("[specId='"+$(this).attr('specId')+"']").removeClass('current');
			$(this).addClass('current');

			//检查是否选择完成
			if(_self.checkSpecSelected() == true)
			{
				//拼接选中的规格数据
				var specJSON = [];
				$('[specId].current').each(function()
				{
					var specData = $(this).data('specData');
					if(!specData)
					{
                        layer.open({
                            content:'规格数据没有绑定'
                            ,skin: 'msg'
                            ,time: 2 //2秒后自动关闭
                        });
						return;
					}

					specJSON.push({
						"id":specData.id,
						"type":specData.type,
						"value":encodeURIComponent(specData.value),
						"name":encodeURIComponent(specData.name),
						"tip":encodeURIComponent(specData.tip),
					});
				});

				//获取货品数据并进行渲染
				$.getJSON(creatUrl("site/getProduct"),{"goods_id":_self.goods_id,"specJSON":specJSON,"random":Math.random},function(json){
					if(json.flag == 'success')
					{
						//货品数据渲染
						$('#data_goodsNo').text(json.data.products_no);
						$('#data_storeNums').text(json.data.store_nums);$('#data_storeNums').trigger('change');
						$('#data_groupPrice').text(json.data.group_price);
						$('#data_sellPrice').text(json.data.sell_price);
						$('#data_marketPrice').text(json.data.market_price);
						$('#data_weight').text(json.data.weight);
						$('#product_id').val(json.data.id);
					}
					else
					{
                        layer.open({
                            content:json.message
                            ,skin: 'msg'
                            ,time: 2 //2秒后自动关闭
                        });
					}
				});
			}
		});
	}

	//检查购买数量是否合法
	this.checkBuyNums = function()
	{
		var minNums   = parseInt($('#buyNums').attr('minNums'));
		    minNums   = minNums ? minNums : 1;
		var maxNums   = parseInt($('#buyNums').attr('maxNums'));
			maxNums   = maxNums ? maxNums : parseInt($.trim($('#data_storeNums').text()));

		var buyNums   = parseInt($.trim($('#buyNums').val()));

		//购买数量小于0
		if(isNaN(buyNums) || buyNums < minNums)
		{
			$('#buyNums').val(minNums);
            layer.open({
                content:"此商品购买数量不得少于"+minNums
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
		}

		//购买数量大于库存
		if(buyNums > maxNums)
		{
			$('#buyNums').val(maxNums);
            layer.open({
                content:"此商品购买数量不得超过"+maxNums
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
		}
	}

    this.closehlDIV = function()
    {
        $(".shop-pop").animate({'bottom':'-500px'});
        $(".shop-pop").hide(300);
        $(".popup-bg").hide();
        var url = creatUrl("site/products/id/"+this.goods_id);
        window.location.href = url;
    }


	/**
	 * 购物车数量的加减
	 * @param code 增加或者减少购买的商品数量
	 */
	this.modified = function(code)
	{
		var buyNums = parseInt($.trim($('#buyNums').val()));
		switch(code)
		{
			case 1:
			{
				buyNums++;
			}
			break;

			case -1:
			{
				buyNums--;
			}
			break;
		}
		$('#buyNums').val(buyNums);
		$('#buyNums').trigger('change');
	}

	//商品加入购物车
	this.joinCart = function()
	{
		if(_self.checkSpecSelected() == false)
		{
            layer.open({
                content:"请先选择商品的规格"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return;
		}

		var buyNums   = parseInt($.trim($('#buyNums').val()));
		var price     = parseFloat($.trim($('#real_price').text()));
		var productId = $('#product_id').val();
		var type      = productId ? 'product' : 'goods';
		var goods_id  = (type == 'product') ? productId : _self.goods_id;

		$.getJSON(creatUrl("simple/joinCart"),{"goods_id":goods_id,"type":type,"goods_num":buyNums,"random":Math.random},function(content)
		{
			if(content.isError == false)
			{
				//获取购物车信息
				$.getJSON(creatUrl("simple/showCart"),{"random":Math.random},function(json)
				{
					$('[name="mycart_count"]').text(json.count);
					$('[name="mycart_sum"]').text(json.sum);

                    layer.open({
                        content:"添加成功"
                        ,skin: 'msg'
                        ,time: 2 //2秒后自动关闭
                    });
				//	tips("目前选购商品共"+json.count+"件，合计：￥"+json.sum);
				});
			}
			else
			{
                layer.open({
                    content:content.message
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
			}
            $(".shop-pop").animate({'bottom':'-500px'});
            $(".shop-pop").hide(300);
            $(".popup-bg").hide();
		});
	}

	//立即购买按钮
	this.buyNow = function()
	{
		//对规格的检查
		if(_self.checkSpecSelected() == false)
		{
            layer.open({
                content:"请选择商品的规格"
                ,skin: 'msg'
                ,time: 2 //2秒后自动关闭
            });
			return;
		}

		//设置必要参数
		var buyNums = parseInt($.trim($('#buyNums').val()));
		var id      = _self.goods_id;
		var type    = 'goods';




		if($('#product_id').val())
		{
			id   = $('#product_id').val();
			type = 'product';
		}

		//普通购买
		var url = "/simple/cart2/id/"+id+"/num/"+buyNums+"/type/"+type;

		//有促销活动（团购，抢购）
		if(_self.promo && _self.active_id)
		{
			url += "/promo/"+_self.promo+"/active_id/"+_self.active_id;
		}

		//页面跳转
		window.location.href = creatUrl(url);
	}

	//构造函数
	!(function()
	{
		//根据IP地址获取所在地区的物流运费
		_self.initLocalArea();

		//商品规格数据初始化
		_self.initSpec();

		//插入货品ID隐藏域
		$("<input type='hidden' id='product_id' alt='货品ID' value='' />").appendTo("body");

		//绑定商品图片
		$('[thumbimg]').bind('click',function()
		{
			$('#picShow').prop('src',$(this).attr('thumbimg'));
			$('#picShow').attr('rel',$(this).attr('sourceimg'));
			$(this).addClass('current');
		});

		//绑定讨论圈按钮
		$('[name="discussButton"]').bind("click",function(){_self.sendDiscuss();});
		$('[name="sendDiscussButton"]').bind("click",function(){_self.sendDiscussData();});

		//绑定商品数量调控按钮
		$('#buyAddButton').bind("click",function(){_self.modified(1);});
		$('#buyReduceButton').bind("click",function(){_self.modified(-1);});
		$('#close_foot_buy').bind("click",function(){_self.closehlDIV();});
		$('#buyNums').bind("change",function()
		{
			//检查购买数量是否合法
			_self.checkBuyNums();

			//运费查询
			_self.delivery(_self.province_id,_self.province_name);
		});

		//立即购买按钮
		$('#buyNowButton').bind('click',function(){_self.buyNow();});

		//加入购物车按钮
		$('#joinCarButton').bind('click',function(){_self.joinCart();});

		//库存域绑定事件,如果库存不足无法购买和加入购物车
		$('#data_storeNums').bind('change',function()
		{
			var storeNum = parseInt($(this).text());
			if(storeNum <= 0)
			{
                layer.open({
                    content:"当前货品库存不足无法购买"
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
				//按钮锁定
				$('#buyNowButton,#joinCarButton').prop('disabled',true);
				$('#buyNowButton,#joinCarButton').addClass('disabled');
			}
			else
			{
				//按钮解锁
				$('#buyNowButton,#joinCarButton').prop('disabled',false);
				$('#buyNowButton,#joinCarButton').removeClass('disabled');
			}
		});

		//促销活动隐藏购物车按钮
		if(_self.promo && _self.active_id)
		{
			$('#joinCarButton').hide();
		}
	}())
}