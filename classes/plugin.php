<?php
/**
 * @copyright Copyright(c) 2016 aircheng.com
 * @file plugin.php
 * @brief 插件核心类
 * @note 观察者模式，注册事件，触发事件
 * @author nswe
 * @date 2016/2/28 10:57:26
 * @version 4.4
 */
class plugin extends IInterceptorBase
{
	//默认开启的插件列表
	private static $defaultList = array("_verification","_goodsCategoryWidget","_authorization","_userInfo","_initData");

	//已经注册监听
	private static $_listen = array();

	//加载插件
	public static function init()
	{
		$pluginDB    = new IModel('plugin');
		$pluginList  = $pluginDB->query("is_open = 1","class_name","sort asc");

		//加载默认插件
		foreach(self::$defaultList as $val)
		{
			$pluginList[]= array('class_name' => $val);
		}

		foreach($pluginList as $key => $val)
		{
			$className = $val['class_name'];
			$classFile = self::path().$className."/".$className.".php";
			if(is_file($classFile))
			{
				include_once($classFile);
				$pluginObj = new $className();
				$pluginObj->reg();
			}
		}
		unset($pluginList);
	}

	/**
	 * @brief 注册事件
	 * @param string $event 事件
	 * @param object ro function $classObj 类实例 或者 匿名函数
	 * @param string $method 方法名字
	 */
	public static function reg($event,$classObj,$method = "")
	{
		if(!isset(self::$_listen[$event]))
		{
			self::$_listen[$event] = array();
		}
		self::$_listen[$event][] = array($classObj,$method);
	}

	/**
	 * @brief 显示已注册事件
	 * @param string $event 事件名称
	 * @return array
	 */
	public static function get($event = '')
	{
		if($event)
		{
			if( isset(self::$_listen[$event]) )
			{
				return self::$_listen[$event];
			}
			return null;
		}
		return self::$_listen;
	}

	/**
	 * @brief 触发事件支持多个参数
	 * @param string $event 事件
	 * @notice 可以调用匿名函数和方法
	 */
	public static function trigger($event)
	{
		$params = func_get_args();
		array_shift($params);

		$result = array();
		if(isset(self::$_listen[$event]))
		{
			foreach(self::$_listen[$event] as $key => $val)
			{
				list($pluginObj,$pluginMethod) = $val;
				$result[$key] = is_callable($pluginObj) ? call_user_func_array($pluginObj,$params):call_user_func_array(array($pluginObj,$pluginMethod),$params);
			}
		}
		return isset($result[1]) ? $result : current($result);
	}

	/**
	 * @brief 插件物理路径
	 * @return string 路径字符串
	 */
	public static function path()
	{
		return IWeb::$app->getBasePath()."plugins/";
	}

	/**
	 * @brief 插件WEB路径
	 * @return string 路径字符串
	 */
	public static function webPath()
	{
		return IUrl::creatUrl('')."plugins/";
	}

	/**
	 * @brief 获取全部插件
	 * @param string $name 插件名字，如果为空则获取全部插件信息
	 * @return array 插件信息 array(
		"name"        => 插件名字,
		"description" => 插件描述,
		"explain"     => 使用说明,
		"class_name"  => 插件ID,
		"is_open"     => 是否开启,
		"is_install"  => 是否安装,
		"config_name" => 默认插件参数结构,
		"config_param"=> 已经保存的插件参数,
		"sort"        => 排序,
	 )
	 */
	public static function getItems($name = '')
	{
		$result = array();
		$dirRes = opendir(self::path());

		//遍历目录读取配置文件
		$pluginDB = new IModel('plugin');
		while( false !== ($dir = readdir($dirRes)) )
		{
			if($dir[0] == "." || $dir[0] == "_")
			{
				continue;
			}

			if($name && $result)
			{
				break;
			}

			if($name && $dir != $name)
			{
				continue;
			}

			$pluginIndex = self::path().$dir."/".$dir.".php";
			if(is_file($pluginIndex))
			{
				include_once($pluginIndex);
				if(get_parent_class($dir) == "pluginBase")
				{
					$class_name   = $dir;
					$pluginRow    = $pluginDB->getObj('class_name = "'.$class_name.'"');
					$is_open      = $pluginRow ? $pluginRow['is_open'] : 0;
					$is_install   = $pluginRow ? 1                     : 0;
					$sort         = $pluginRow ? $pluginRow['sort']    : 99;
					$config_param = array();
					if($pluginRow && $pluginRow['config_param'])
					{
						$config_param = JSON::decode($pluginRow['config_param']);
					}
					$result[$dir] = array(
						"name"        => $class_name::name(),
						"description" => $class_name::description(),
						"explain"     => $class_name::explain(),
						"class_name"  => $class_name,
						"is_open"     => $is_open,
						"is_install"  => $is_install,
						"config_name" => $class_name::configName(),
						"config_param"=> $config_param,
						"sort"        => $sort,
					);
				}
			}
		}

		if(!$name)
		{
			return $result;
		}
		return isset($result[$name]) ? $result[$name] : array();
	}

	/**
	 * @brief 系统内置的所有事件触发
	 */
	public static function onCreateApp(){plugin::init();plugin::trigger("onCreateApp");}
	public static function onFinishApp(){plugin::trigger("onFinishApp");}

	public static function onBeforeCreateController($ctrlId){plugin::trigger("onBeforeCreateController",$ctrlId);plugin::trigger("onBeforeCreateController@".$ctrlId);}
	public static function onCreateController($ctrlObj){plugin::trigger("onCreateController");plugin::trigger("onCreateController@".$ctrlObj->getId());}
	public static function onFinishController($ctrlObj){plugin::trigger("onFinishController");plugin::trigger("onFinishController@".$ctrlObj->getId());}

	public static function onBeforeCreateAction($ctrlObj,$actionId){plugin::trigger("onBeforeCreateAction",$actionId);plugin::trigger("onBeforeCreateAction@".$ctrlObj->getId());plugin::trigger("onBeforeCreateAction@".$ctrlObj->getId()."@".$actionId);}
	public static function onCreateAction($ctrlObj,$actionObj){plugin::trigger("onCreateAction");plugin::trigger("onCreateAction@".$ctrlObj->getId());plugin::trigger("onCreateAction@".$ctrlObj->getId()."@".$actionObj->getId());}
	public static function onFinishAction($ctrlObj,$actionObj){plugin::trigger("onFinishAction");plugin::trigger("onFinishAction@".$ctrlObj->getId());plugin::trigger("onFinishAction@".$ctrlObj->getId()."@".$actionObj->getId());}

	public static function onCreateView($ctrlObj,$actionObj){plugin::trigger("onCreateView");plugin::trigger("onCreateView@".$ctrlObj->getId());plugin::trigger("onCreateView@".$ctrlObj->getId()."@".$actionObj->getId());}
	public static function onFinishView($ctrlObj,$actionObj){plugin::trigger("onFinishView");plugin::trigger("onFinishView@".$ctrlObj->getId());plugin::trigger("onFinishView@".$ctrlObj->getId()."@".$actionObj->getId());}

	public static function onPhpShutDown(){plugin::trigger("onPhpShutDown");}
}

/**
 * @brief 插件基类，所有插件必须继承此类
 * @notice 必须实现3个抽象方法： reg(),name(),description()
 */
abstract class pluginBase extends IInterceptorBase
{
	//插件的WEB目录
	public $webPath = "";

	//插件的物理路径
	public $path = "";

	//错误信息
	protected $error = array();

	//注册事件接口，内部通过调用payment::reg(事件,对象实例,方法);
	public function reg(){}

	/**
	 * @brief 默认插件参数信息，写入到plugin表config_param字段
	 * @return array("字段名" => array(
		 "name"    => "文字显示",
		 "type"    => "数据类型【text,radio,checkbox,select】",
		 "pattern" => "数据校验【int,float,date,datetime,require,正则表达式】",
		 "value"   => "1,数组：枚举数据【radio,checkbox,select】的预设值,array(名字=>数据); 2,字符串：【text】默认数据",
		 "info"    => "解释说明信息"
		))
	 */
	public static function configName()
	{
		return array();
	}

	/**
	 * @brief 插件安装
	 * @return boolean
	 */
	public static function install()
	{
		return true;
	}

	/**
	 * @brief 插件卸载
	 * @return boolean
	 */
	public static function uninstall()
	{
		return true;
	}

	/**
	 * @brief 插件名字
	 * @return string
	 */
	public static function name()
	{
		return "插件名称";
	}

	/**
	 * @brief 插件功能描述
	 * @return string
	 */
	public static function description()
	{
		return "插件描述";
	}

	/**
	 * @brief 插件使用说明
	 * @return string
	 */
	public static function explain()
	{
		return "";
	}

	/**
	 * @brief 获取DB中录入的配置参数
	 * @return array
	 */
	public function config()
	{
		$className= get_class($this);
		$pluginDB = new IModel('plugin');
		$dataRow  = $pluginDB->getObj('class_name = "'.$className.'"');
		if($dataRow && $dataRow['config_param'])
		{
			return JSON::decode($dataRow['config_param']);
		}
		return array();
	}

	/**
	 * @brief 返回错误信息
	 * @return array
	 */
	public function getError()
	{
		return $this->error ? join("\r\n",$this->error) : "";
	}

	/**
	 * @brief 写入错误信息
	 * @return array
	 */
	public function setError($error)
	{
		$this->error[] = $error;
	}

	/**
	 * @brief 插件视图渲染有布局
	 * @param string $view 视图名字
	 * @param array  $data 视图里面的数据
	 */
	public function redirect($view,$data = array())
	{
		$__className         = get_class($this);
		$__pluginViewPath    = plugin::path().$__className."/".$view;
		$__pluginWebViewPath = plugin::webPath().$__className."/".$view;
		$__commonVar         = array("_viewPath" => dirname($__pluginViewPath),"_webPath" => dirname($__pluginWebViewPath));

		//根据主题方案生成runtime目录，防止不同主题的插件视图冲突
		$themeDir         = self::controller()->getThemeDir();
		$__pluginRuntime  = $themeDir ? plugin::path().$themeDir."/".$__className."/".$view : "";

		if($__pluginRuntime)
		{
			$__pluginRuntime = str_replace(IWeb::$app->getBasePath(),IWeb::$app->getRuntimePath(),$__pluginRuntime);
		}
		self::controller()->setRenderData($__commonVar);
		$result = self::controller()->render($__pluginViewPath,$data,false,$__pluginRuntime);
		if($result === false)
		{
			IError::show($__className."/".$view."插件视图不存在");
		}
	}

	/**
	 * @brief 插件视图渲染去掉布局
	 * @param string $view 视图名字
	 * @param array  $data 视图里面的数据
	 */
	public function view($view,$data = array())
	{
		self::controller()->layout = "";
		$this->redirect($view,$data);
	}

	/**
	 * @brief 插件物理目录
	 * @param string 插件路径地址
	 */
	public function path()
	{
		return $this->path ? $this->path : plugin::path().get_class($this)."/";
	}

	/**
	 * @brief 插件WEB目录
	 * @param string 插件路径地址
	 */
	public function webPath()
	{
		return $this->webPath ? $this->webPath : plugin::webPath().get_class($this)."/";
	}
}

/**
 * @brief iWebShop分词接口
 */
interface wordsPart_inter
{
	/**
	 * @brief 运行分词
	 * @param string $content 要分词的内容
	 * @return array 词语
	 */
	public function run($content);

	/**
	 * @brief 处理规范统一的结果集
	 * @param string $result 要处理的返回值
	 * @return array 返回结果 array('result' => 'success 或者 fail','data' => array('分词数据'))
	 */
	public function response($result);
}