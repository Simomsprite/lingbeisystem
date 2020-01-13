//购物车数量改动计算
function cartCount(obj)
{
	var countInput = $('#count_'+obj.goods_id+'_'+obj.product_id);
	var countInputVal = parseInt(countInput.val());
	var oldNum = countInput.data('oldNum') ? countInput.data('oldNum') : obj.count;

	//商品数量大于1件
	if(isNaN(countInputVal) || (countInputVal <= 0))
	{
        layer.open({
            content:"购买的数量必须大于1件"
            ,skin: 'msg'
            ,time: 2 //2秒后自动关闭
        });
		countInput.val(1);
		countInput.change();
	}
	//商品数量小于库存量
	else if(countInputVal > parseInt(obj.store_nums))
	{
        layer.open({
            content:"购买的数量不能大于此商品的库存量"
            ,skin: 'msg'
            ,time: 2 //2秒后自动关闭
        });
		countInput.val(parseInt(obj.store_nums));
		countInput.change();
	}
	else
	{
		var diff = parseInt(countInputVal) - parseInt(oldNum);
		if(diff == 0)
		{
			return;
		}

		var goods_id   = obj.product_id > 0 ? obj.product_id : obj.goods_id;
		var goods_type = obj.product_id > 0 ? "product"      : "goods";
        var index =   layer.open({type: 2}); //0代表加载的风格，支持0-

		//更新购物车中此商品的数量
		$.getJSON(creatUrl("/simple/joinCart"),{"goods_id":goods_id,"type":goods_type,"goods_num":diff,"random":Math.random()},function(content){
			if(content.isError == true)
			{
                layer.open({
                    content:content.message
                    ,skin: 'msg'
                    ,time: 2 //2秒后自动关闭
                });
				countInput.val(1);
				countInput.change();
                layer.close(index);
			}
			else
			{
				var goodsId   = [];
				var productId = [];
				var num       = [];
				$('[id^="count_"]').each(function(i)
				{
					var idValue = $(this).attr('id');
					var dataArray = idValue.split("_");

					goodsId.push(dataArray[1]);
					productId.push(dataArray[2]);
					num.push(this.value);
				});
				countInput.data('oldNum',countInputVal);
				$.getJSON(creatUrl("/simple/promotionRuleAjax"),{"goodsId":goodsId,"productId":productId,"num":num,"random":Math.random()},function(content){
					$('#cart_prompt_box').empty();
					if(content.promotion.length > 0)
					{
						for(var i = 0;i < content.promotion.length; i++)
						{
							$('#cart_prompt_box').append( template.render('promotionTemplate',{"item":content.promotion[i]}) );
						}
						$('#cart_prompt').show();
					}
					else
					{
						$('#cart_prompt').hide();
					}

					/*开始更新数据*/
					$('#weight').html(content.weight);
					$('#origin_price').html(content.sum);
					$('#discount_price').html(content.reduce);
					$('#promotion_price').html(content.proReduce);
					$('#sum_price').html(content.final_sum);
					$('#sum_'+obj.goods_id+'_'+obj.product_id).html(((obj.sell_price - obj.reduce) * countInputVal).toFixed(2));
                    layer.close(index);
                });
			}
		});
	}
}

//增加商品数量
function cart_increase(obj)
{
	//库存超量检查
	var countInput = $('#count_'+obj.goods_id+'_'+obj.product_id);
	if(parseInt(countInput.val()) + 1 > parseInt(obj.store_nums))
	{
        layer.open({
            content:'购买的数量大于此商品的库存量'
            ,skin: 'msg'
            ,time: 2 //2秒后自动关闭
        });
	}
	else
	{
		countInput.val(parseInt(countInput.val()) + 1);
		countInput.change();
	}
}

//减少商品数量
function cart_reduce(obj)
{
	//库存超量检查
	var countInput = $('#count_'+obj.goods_id+'_'+obj.product_id);
	if(parseInt(countInput.val()) - 1 <= 0)
	{
        layer.open({
            content:'购买的数量必须大于1件'
            ,skin: 'msg'
            ,time: 2 //2秒后自动关闭
        });
	}
	else
	{
		countInput.val(parseInt(countInput.val()) - 1);
		countInput.change();
	}
}

//移除购物车
function removeCartByJSON(obj)
{
	var goods_id   = obj.product_id > 0 ? obj.product_id : obj.goods_id;
	var goods_type = obj.product_id > 0 ? "product"      : "goods";
	$.getJSON(creatUrl("/simple/removeCart"),{"goods_id":goods_id,"type":goods_type,"random":Math.random()},function()
	{
		window.location.reload();
	});
}


function removeCartById(id)
{

    var index =   layer.open({type: 2}); //0代表加载的风格，支持0-
    $.getJSON(creatUrl("/simple/removeCartById"),{"car_id":id,"random":Math.random()},function()
    {
        layer.open({
            content:'删除成功'
            ,skin: 'msg'
            ,time: 2 //2秒后自动关闭
        });
        layer.close(index);
        window.location.reload();
    });
}
