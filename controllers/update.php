<?php
/**
 * @brief 升级更新控制器
 */
class Update extends IController
{
	/**
	 * @brief iwebshop16060600 版本升级更新
	 */
	public function index()
	{
		set_time_limit(0);

		$sql = array(
			"ALTER TABLE `{pre}comment` ADD  `order_goods_id` int(11) unsigned default NULL COMMENT '订单商品表中的ID';",
			"ALTER TABLE  `{pre}comment` ADD INDEX (`order_goods_id`)",
			"ALTER TABLE `{pre}comment` ADD foreign key(order_goods_id) references `{pre}order_goods`(id) on delete cascade on update cascade;",
		);

		foreach($sql as $key => $val)
		{
			IDBFactory::getDB()->query( $this->_c($val) );
		}

		die("升级成功!! V4.8版本");
	}

	public function _c($sql)
	{
		return str_replace('{pre}',IWeb::$app->config['DB']['tablePre'],$sql);
	}

	//查询规格标准
	public function searchSpec($specVal,$specValueArray)
	{
		foreach($specValueArray as $tip => $item)
		{
			if($item == $specVal && !is_numeric($tip))
			{
				return $tip;
			}
		}
		return "";
	}
}