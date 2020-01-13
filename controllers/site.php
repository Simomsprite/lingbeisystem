<?php
/**
 * @copyright Copyright(c) 2011 aircheng.com
 * @file site.php
 * @brief
 * @author webning
 * @date 2011-03-22
 * @version 0.6
 * @note
 */

/**
 * @brief Site
 * @class Site
 * @note
 */
class Site extends IController
{
    public $layout = 'site';

    function init()
    {

    }


    /**
     * @author GZY
     * @date 2020-01-02
     * 首页
     *
     */
    function index()
    {


        //*******************声明数据库操作类*********************//
        $tbrelease_information = new IModel("release_information");
        $tbuser = new IModel("user");
        $tbgoods = new IModel("goods");
        $tbkeyword = new IModel("keyword");
        //*******************声明数据库操作类*********************//


        //从发布表中取出排名前八的人用户id  以及排名

        //货源用户排名前八
        $sell_user_id_list_sql = "SELECT b.id,b.head_ico,b.nickname FROM `lb_release_information` a  INNER JOIN  lb_user  b ON  a.user_id=b.id   where a.`status` =2 and a.is_delete=0 and a.type=1 and a.is_top=1 GROUP BY a.user_id ORDER BY a.sort  DESC  LIMIT 8 ;";
        $sell_user_id_list = IDBFactory::getDB('shop')->query($sell_user_id_list_sql);

        //求购用户排名前八
        $buy_user_id_list_sql = "SELECT b.id,b.head_ico,b.nickname FROM `lb_release_information` a  INNER JOIN  lb_user  b ON  a.user_id=b.id   where a.`status` =2 and a.is_delete=0 and a.type=0 and a.is_top=1 GROUP BY a.user_id ORDER BY a.sort  DESC  LIMIT 8 ;";
        $buy_user_id_list = IDBFactory::getDB('shop')->query($buy_user_id_list_sql);

        //货源商品排行前六
        $sell_good_no_list_sql = "SELECT a.id,b.`name`,b.img,a.price FROM `lb_release_information` a  INNER JOIN  lb_goods  b ON  a.goods_no=b.goods_no   where a.`status` =2 and a.is_delete=0 and a.type=1 and a.is_top=1 GROUP BY a.goods_no ORDER BY a.sort  DESC  LIMIT 6";
        $sell_good_no_list = IDBFactory::getDB('shop')->query($sell_good_no_list_sql);

        //货源商品排行前六
        $buy_good_no_list_sql = "SELECT a.id,b.`name`,b.img,a.price FROM `lb_release_information` a  INNER JOIN  lb_goods  b ON  a.goods_no=b.goods_no   where a.`status` =2 and a.is_delete=0 and a.type=0 and a.is_top=1 GROUP BY a.goods_no ORDER BY a.sort  DESC  LIMIT 6";
        $buy_good_no_list = IDBFactory::getDB('shop')->query($buy_good_no_list_sql);

        //热门商品排名前十(按收藏排序)
        $hot_word_list = $tbkeyword->query('hot = 1', 'word', '`order` desc', '10');

        $returnarray = array();
        $returnarray["sell_user_id_list"] = $sell_user_id_list; //货源用户排名
        $returnarray["buy_user_id_list"] = $buy_user_id_list;   //求购用户排名

        $returnarray["sell_good_no_list"] = $sell_good_no_list; //货源商品排名
        $returnarray["buy_good_no_list"] = $buy_good_no_list;   //求购商品排名
        $returnarray["hot_word_list"] = $hot_word_list;   //热门商品排名

        $data['mlist'] = $returnarray;

        //$data['search_word'] = ICookie::get('search_word');
        $data['isIdx']=true;
        $this->setRenderData($data);

        $this->index_slide = Api::run('getBannerList');
        $this->redirect('index');
    }

    //[首页]商品搜索
    function search_list()
    {
        $this->word = IFilter::act(IReq::get('word'), 'text');
        $cat_id = IFilter::act(IReq::get('cat'), 'int');

        if (preg_match("|^[\w\x7f\s*-\xff*]+$|", $this->word)) {
            //搜索关键字
            $tb_sear = new IModel('search');
            $search_info = $tb_sear->getObj('keyword = "' . $this->word . '"', 'id');

            //如果是第一页，相应关键词的被搜索数量才加1
            if ($search_info && intval(IReq::get('page')) < 2) {
                //禁止刷新+1
                $allow_sep = "30";
                $flag = false;
                $time = ICookie::get('step');
                if (isset($time)) {
                    if (time() - $time > $allow_sep) {
                        ICookie::set('step', time());
                        $flag = true;
                    }
                } else {
                    ICookie::set('step', time());
                    $flag = true;
                }
                if ($flag) {
                    $tb_sear->setData(array('num' => 'num + 1'));
                    $tb_sear->update('id=' . $search_info['id'], 'num');
                }
            } elseif (!$search_info) {
                //如果数据库中没有这个词的信息，则新添
                $tb_sear->setData(array('keyword' => $this->word, 'num' => 1));
                $tb_sear->add();
            }
        } else {
            IError::show(403, '请输入正确的查询关键词');
        }
        $this->cat_id = $cat_id;
        $this->redirect('search_list');
    }

    //[site,ucenter头部分]自动完成
    function autoComplete()
    {
        $word = IFilter::act(IReq::get('word'));
        $isError = true;
        $data = array();

        if ($word != '' && $word != '%' && $word != '_') {
            $wordObj = new IModel('keyword');
            $wordList = $wordObj->query('word like "' . $word . '%" and word != "' . $word . '"', 'word, goods_nums', '', 10);

            if (!empty($wordList)) {
                $isError = false;
                $data = $wordList;
            }
        }

        //json数据
        $result = array(
            'isError' => $isError,
            'data' => $data,
        );

        echo JSON::encode($result);
    }

    //[首页]邮箱订阅
    function email_registry()
    {
        $email = IReq::get('email');
        $result = array('isError' => true);

        if (!IValidate::email($email)) {
            $result['message'] = '请填写正确的email地址';
        } else {
            $emailRegObj = new IModel('email_registry');
            $emailRow = $emailRegObj->getObj('email = "' . $email . '"');

            if (!empty($emailRow)) {
                $result['message'] = '此email已经订阅过了';
            } else {
                $dataArray = array(
                    'email' => $email,
                );
                $emailRegObj->setData($dataArray);
                $status = $emailRegObj->add();
                if ($status == true) {
                    $result = array(
                        'isError' => false,
                        'message' => '订阅成功',
                    );
                } else {
                    $result['message'] = '订阅失败';
                }
            }
        }
        echo JSON::encode($result);
    }

    //[列表页]商品
    function pro_list()
    {
        $this->catId = IFilter::act(IReq::get('cat'), 'int');//分类id

        if ($this->catId == 0) {
            IError::show(403, '缺少分类ID');
        }

        //查找分类信息
        $catObj = new IModel('category');
        $this->catRow = $catObj->getObj('id = ' . $this->catId);

        if ($this->catRow == null) {
            IError::show(403, '此分类不存在');
        }

        //获取子分类
        $this->childId = goods_class::catChild($this->catId);
        $this->redirect('pro_list');
    }

    //咨询
    function consult()
    {
        $this->goods_id = IFilter::act(IReq::get('id'), 'int');
        if ($this->goods_id == 0) {
            IError::show(403, '缺少商品ID参数');
        }

        $goodsObj = new IModel('goods');
        $goodsRow = $goodsObj->getObj('id = ' . $this->goods_id);
        if (!$goodsRow) {
            IError::show(403, '商品数据不存在');
        }

        //获取次商品的评论数和平均分
        $goodsRow['apoint'] = $goodsRow['comments'] ? round($goodsRow['grade'] / $goodsRow['comments']) : 0;

        $this->goodsRow = $goodsRow;
        $this->redirect('consult');
    }

    //咨询动作
    function consult_act()
    {
        $goods_id = IFilter::act(IReq::get('goods_id', 'post'), 'int');
        $captcha = IFilter::act(IReq::get('captcha', 'post'));
        $question = IFilter::act(IReq::get('question', 'post'));
        $_captcha = ISafe::get('captcha');
        $message = '';

        if (!$captcha || !$_captcha || $captcha != $_captcha) {
            $message = '验证码输入不正确';
        } else if (!$question) {
            $message = '咨询内容不能为空';
        } else if (!$goods_id) {
            $message = '商品ID不能为空';
        } else {
            $goodsObj = new IModel('goods');
            $goodsRow = $goodsObj->getObj('id = ' . $goods_id);
            if (!$goodsRow) {
                $message = '不存在此商品';
            }
        }

        //有错误情况
        if ($message) {
            IError::show(403, $message);
        } else {
            $dataArray = array(
                'question' => $question,
                'goods_id' => $goods_id,
                'user_id' => isset($this->user['user_id']) ? $this->user['user_id'] : 0,
                'time' => ITime::getDateTime(),
            );
            $referObj = new IModel('refer');
            $referObj->setData($dataArray);
            $referObj->add();
            plugin::trigger('setCallback', '/site/products/id/' . $goods_id);
            $this->redirect('/site/success');
        }
    }

    //公告详情页面
    function notice_detail()
    {
        $this->notice_id = IFilter::act(IReq::get('id'), 'int');
        if ($this->notice_id == '') {
            IError::show(403, '缺少公告ID参数');
        } else {
            $noObj = new IModel('announcement');
            $this->noticeRow = $noObj->getObj('id = ' . $this->notice_id);
            if (empty($this->noticeRow)) {
                IError::show(403, '公告信息不存在');
            }
            $this->redirect('notice_detail');
        }
    }

    //文章列表页面
    function article()
    {
        $catId = IFilter::act(IReq::get('id'), 'int');
        $catRow = Api::run('getArticleCategoryInfo', $catId);
        $queryArticle = $catRow ? Api::run('getArticleListByCatid', $catRow['id']) : Api::run('getArticleList');
        $this->setRenderData(array("catRow" => $catRow, 'queryArticle' => $queryArticle));
        $this->redirect('article');
    }

    //文章详情页面
    function article_detail()
    {
        $this->article_id = IFilter::act(IReq::get('id'), 'int');
        if ($this->article_id == '') {
            IError::show(403, '缺少咨询ID参数');
        } else {
            $articleObj = new IModel('article');
            $this->articleRow = $articleObj->getObj('id = ' . $this->article_id);
            if (empty($this->articleRow)) {
                IError::show(403, '资讯文章不存在');
                exit;
            }

            //关联商品
            $this->relationList = Api::run('getArticleGoods', array("#article_id#", $this->article_id));
            $this->redirect('article_detail');
        }
    }

    //商品展示
    function products()
    {
        $goods_id = IFilter::act(IReq::get('id'), 'int');
        //代理
        $code = IFilter::act(IReq::get('code'));
        ICookie::set('agent_code', $code, 30);
        //代理
        if (!$goods_id) {
            IError::show(403, "传递的参数不正确");
            exit;
        }

        //使用商品id获得商品信息
        $tb_goods = new IModel('goods');
        $goods_info = $tb_goods->getObj('id=' . $goods_id . " AND is_del=0");
        if (!$goods_info) {
            IError::show(403, "这件商品不存在");
            exit;
        }

        //品牌名称
        if ($goods_info['brand_id']) {
            $tb_brand = new IModel('brand');
            $brand_info = $tb_brand->getObj('id=' . $goods_info['brand_id']);
            if ($brand_info) {
                $goods_info['brand'] = $brand_info['name'];
            }
        }

        //获取商品分类
        $categoryObj = new IModel('category_extend as ca,category as c');
        $categoryList = $categoryObj->query('ca.goods_id = ' . $goods_id . ' and ca.category_id = c.id', 'c.id,c.name', 'ca.id desc', 1);
        $categoryRow = null;
        if ($categoryList) {
            $categoryRow = current($categoryList);
        }
        $goods_info['category'] = $categoryRow ? $categoryRow['id'] : 0;

        //商品图片
        $tb_goods_photo = new IQuery('goods_photo_relation as g');
        $tb_goods_photo->fields = 'p.id AS photo_id,p.img ';
        $tb_goods_photo->join = 'left join goods_photo as p on p.id=g.photo_id ';
        $tb_goods_photo->where = ' g.goods_id=' . $goods_id;
        $tb_goods_photo->order = ' g.id asc';
        $goods_info['photo'] = $tb_goods_photo->find();

        //商品是否参加促销活动(团购，抢购)
        $goods_info['promo'] = IReq::get('promo') ? IReq::get('promo') : '';
        $goods_info['active_id'] = IReq::get('active_id') ? IFilter::act(IReq::get('active_id'), 'int') : 0;
        if ($goods_info['promo']) {
            $activeObj = new Active($goods_info['promo'], $goods_info['active_id'], $this->user['user_id'], $goods_id);
            $activeResult = $activeObj->data();
            if (is_string($activeResult)) {
                IError::show(403, $activeResult);
            } else {
                $goods_info[$goods_info['promo']] = $activeResult;
            }
        }


        //商品关联
        $tb_goods_extend = new IQuery('goods_extend as ge');
        $tb_goods_extend->join = "left join goods as g on g.id=ge.extend_id";
        $tb_goods_extend->fields = "ge.*,g.img,g.name,g.sell_price";
        $tb_goods_extend->where = "ge.goods_id=" . $goods_id;
        $goods_extend_list = $tb_goods_extend->find();
        $goods_info['goods_extend_list'] = $goods_extend_list;


        //赠品关联
        $tb_goods_extend = new IQuery('goodsgift_extend as gg');
        $tb_goods_extend->join = "left join goods as g on g.id=gg.extend_id";
        $tb_goods_extend->fields = "gg.*,g.img,g.name,g.sell_price,g.goods_type";
        $tb_goods_extend->where = "gg.goods_id=" . $goods_id;
        $goodsgift_extend_list = $tb_goods_extend->find();
        $goods_info['goodsgift_extend_list'] = $goodsgift_extend_list;


        //获得扩展属性
        $tb_attribute_goods = new IQuery('goods_attribute as g');
        $tb_attribute_goods->join = 'left join attribute as a on a.id=g.attribute_id ';
        $tb_attribute_goods->fields = ' a.name,g.attribute_value ';
        $tb_attribute_goods->where = "goods_id='" . $goods_id . "' and attribute_id!=''";
        $goods_info['attribute'] = $tb_attribute_goods->find();

        //购买记录
        $tb_shop = new IQuery('order_goods as og');
        $tb_shop->join = 'left join order as o on o.id=og.order_id';
        $tb_shop->fields = 'count(*) as totalNum';
        $tb_shop->where = 'og.goods_id=' . $goods_id . ' and o.status = 5';
        $shop_info = $tb_shop->find();
        $goods_info['buy_num'] = 0;
        if ($shop_info) {
            $goods_info['buy_num'] = $shop_info[0]['totalNum'];
        }

        //购买前咨询
        $tb_refer = new IModel('refer');
        $refeer_info = $tb_refer->getObj('goods_id=' . $goods_id, 'count(*) as totalNum');
        $goods_info['refer'] = 0;
        if ($refeer_info) {
            $goods_info['refer'] = $refeer_info['totalNum'];
        }

        //网友讨论
        $tb_discussion = new IModel('discussion');
        $discussion_info = $tb_discussion->getObj('goods_id=' . $goods_id, 'count(*) as totalNum');
        $goods_info['discussion'] = 0;
        if ($discussion_info) {
            $goods_info['discussion'] = $discussion_info['totalNum'];
        }

        //获得商品的价格区间
        $tb_product = new IModel('products');
        $product_info = $tb_product->getObj('goods_id=' . $goods_id, 'max(sell_price) as maxSellPrice ,max(market_price) as maxMarketPrice');
        if (isset($product_info['maxSellPrice']) && $goods_info['sell_price'] != $product_info['maxSellPrice']) {
            $goods_info['sell_price'] .= "-" . $product_info['maxSellPrice'];
        }

        if (isset($product_info['maxMarketPrice']) && $goods_info['market_price'] != $product_info['maxMarketPrice']) {
            $goods_info['market_price'] .= "-" . $product_info['maxMarketPrice'];
        }

        //获得会员价
        $countsumInstance = new countsum();
        $goods_info['group_price'] = $countsumInstance->groupPriceRange($goods_id);

        //获取商家信息
        if ($goods_info['seller_id']) {
            $sellerDB = new IModel('seller');
            $goods_info['seller'] = $sellerDB->getObj('id = ' . $goods_info['seller_id']);
        }

        //如果登录添加浏览记录
        if ($this->user['user_id']) {
            $tb_goods_history = new IModel("goods_history");
            $history_info = $tb_goods_history->getObj("user_id=" . $this->user['user_id'] . " and goods_id=" . $goods_id);
            if (empty($history_info)) {
                $history_data = array();
                $history_data['user_id'] = $this->user['user_id'];
                $history_data['goods_id'] = $goods_id;
                $history_data['goods_name'] = $goods_info['name'];
                $history_data['goods_img'] = $goods_info['img'];
                $history_data['sell_price'] = $goods_info['sell_price'];
                $history_data['market_price'] = $goods_info['market_price'];
                $history_data['is_prescription'] = $goods_info['is_prescription'];
                $history_data['goods_type'] = $goods_info['goods_type'];
                $tb_goods_history->setData($history_data);
                $tb_goods_history->add();
            }
        }
        $this->is_xiangou_ok = 0;
        if ($goods_info['is_xiangou'] == 1) {
            $now_date = ITime::getDateTime();
            if ($goods_info['xiangou_start_date'] < $now_date && $goods_info['xiangou_end_date'] > $now_date) {
                $this->is_xiangou_ok = 1;
            }
        }
//
//        if($goods_info['is_gift'] == 1){
//            IError::show(403,"这件商品不存在");
//        }


        //取优惠券
        $nowtime = ITime::getDateTime();
        $ticket_all = Api::run('getTicketList', array('#nowtime#', $nowtime), 20);
        $ticket_list = array();
        if ($ticket_all) {
            foreach ($ticket_all as $Key => $val) {
                if ($val['goods_id'] > 0) {
                    if ($val['goods_id'] == $goods_id) {
                        $ticket_list[] = $val;
                    }
                } else {
                    if ($val['cat_id'] == 0) {
                        $ticket_list[] = $val;
                    } else {
                        $categoryObj = new IModel('category_extend as ca,category as c');
                        $categoryinfo = $categoryObj->query('ca.goods_id = ' . $goods_id . ' and ca.category_id = c.id and ca.category_id=' . $val['cat_id'], 'c.id,c.name', 'ca.id desc', 1);
                        if ($categoryinfo) {
                            $ticket_list[] = $val;
                        }
                    }
                }
            }
        }

        $this->ticket_list = $ticket_list;
        $favorite_class = "like";
        if ($this->user['user_id']) {
            $favoriteObj = new IModel('favorite');
            $favorite_info = $favoriteObj->getObj('user_id = ' . $this->user['user_id'] . ' and rid = ' . $goods_id);
            if ($favorite_info) {
                $favorite_class = "liked";
            }
        }
        $this->favorite_class = $favorite_class;


        //评论
        $commentDB = new IQuery('comment as c');
        $commentDB->join = 'left join goods as go on c.goods_id = go.id AND go.is_del = 0 left join user as u on u.id = c.user_id';
        $commentDB->fields = 'u.head_ico,u.username,c.*';
        $commentDB->where = 'c.goods_id = ' . $goods_id . ' and c.status = 1';
        $commentDB->order = 'c.id desc';
        $commentDB->page = 1;
        $comment_data = $commentDB->find();
        $new_comment_data = array();
        if ($comment_data) {
            foreach ($comment_data as $key => $val) {
                $val['username'] = substr_replace($val['username'], '****', 3, 4);
                $val['bilie'] = ($val['point'] / 5) * 100;
                $new_comment_data[] = $val;
            }
        }
        $this->new_comment_data = $new_comment_data;

        //增加浏览次数
        $visit = ISafe::get('visit');
        $checkStr = "#" . $goods_id . "#";
        if ($visit && strpos($visit, $checkStr) !== false) {
        } else {
            $tb_goods->setData(array('visit' => 'visit + 1'));
            $tb_goods->update('id = ' . $goods_id, 'visit');
            $visit = $visit === null ? $checkStr : $visit . $checkStr;
            ISafe::set('visit', $visit);
        }

        $this->setRenderData($goods_info);
        $this->redirect('products');
    }

    //商品讨论更新
    function discussUpdate()
    {
        $goods_id = IFilter::act(IReq::get('id'), 'int');
        $content = IFilter::act(IReq::get('content'), 'text');
        $captcha = IReq::get('captcha');
        $_captcha = ISafe::get('captcha');
        $return = array('isError' => true, 'message' => '');

        if (!$this->user['user_id']) {
            $return['message'] = '请先登录系统';
        } else if (!$captcha || !$_captcha || $captcha != $_captcha) {
            $return['message'] = '验证码输入不正确';
        } else if (trim($content) == '') {
            $return['message'] = '内容不能为空';
        } else {
            $return['isError'] = false;

            //插入讨论表
            $tb_discussion = new IModel('discussion');
            $dataArray = array(
                'goods_id' => $goods_id,
                'user_id' => $this->user['user_id'],
                'time' => ITime::getDateTime(),
                'contents' => $content,
            );
            $tb_discussion->setData($dataArray);
            $tb_discussion->add();

            $return['time'] = $dataArray['time'];
            $return['contents'] = $content;
            $return['username'] = $this->user['username'];
        }
        echo JSON::encode($return);
    }

    //获取货品数据
    function getProduct()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $specJSON = IReq::get('specJSON');
        if (!$specJSON || !is_array($specJSON)) {
            echo JSON::encode(array('flag' => 'fail', 'message' => '规格值不符合标准'));
            exit;
        }

        //获取货品数据
        $tb_products = new IModel('products');
        $procducts_info = $tb_products->getObj("goods_id = " . $goods_id . " and spec_array = '" . IFilter::act(htmlspecialchars_decode(JSON::encode($specJSON))) . "'");

        //匹配到货品数据
        if (!$procducts_info) {
            echo JSON::encode(array('flag' => 'fail', 'message' => '没有找到相关货品'));
            exit;
        }

        //获得会员价
        $countsumInstance = new countsum();
        $group_price = $countsumInstance->getGroupPrice($procducts_info['id'], 'product');

        //会员价格
        if ($group_price !== null) {
            $procducts_info['group_price'] = $group_price;
        }

        echo JSON::encode(array('flag' => 'success', 'data' => $procducts_info));
    }

    //顾客评论ajax获取
    function comment_ajax()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $page = IFilter::act(IReq::get('page'), 'int') ? IReq::get('page') : 1;

        $commentDB = new IQuery('comment as c');
        $commentDB->join = 'left join goods as go on c.goods_id = go.id AND go.is_del = 0 left join user as u on u.id = c.user_id';
        $commentDB->fields = 'u.head_ico,u.username,c.*';
        $commentDB->where = 'c.goods_id = ' . $goods_id . ' and c.status = 1';
        $commentDB->order = 'c.id desc';
        $commentDB->page = $page;
        $data = $commentDB->find();
        $pageHtml = $commentDB->getPageBar("javascript:void(0);", 'onclick="comment_ajax([page])"');

        $new_data = array();
        if ($data) {
            foreach ($data as $key => $val) {
                $val['username'] = substr_replace($val['username'], '****', 3, 4);
                $val['bilie'] = ($val['point'] / 5) * 100;
                $new_data[] = $val;
            }
        }

        echo JSON::encode(array('data' => $new_data, 'pageHtml' => $pageHtml));
    }

    //购买记录ajax获取
    function history_ajax()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $page = IFilter::act(IReq::get('page'), 'int') ? IReq::get('page') : 1;

        $orderGoodsDB = new IQuery('order_goods as og');
        $orderGoodsDB->join = 'left join order as o on og.order_id = o.id left join user as u on o.user_id = u.id';
        $orderGoodsDB->fields = 'o.user_id,og.goods_price,og.goods_nums,o.create_time as completion_time,u.username';
        $orderGoodsDB->where = 'og.goods_id = ' . $goods_id . ' and o.status = 5';
        $orderGoodsDB->order = 'o.create_time desc';
        $orderGoodsDB->page = $page;

        $data = $orderGoodsDB->find();
        $pageHtml = $orderGoodsDB->getPageBar("javascript:void(0);", 'onclick="history_ajax([page])"');

        echo JSON::encode(array('data' => $data, 'pageHtml' => $pageHtml));
    }

    //讨论数据ajax获取
    function discuss_ajax()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $page = IFilter::act(IReq::get('page'), 'int') ? IReq::get('page') : 1;

        $discussDB = new IQuery('discussion as d');
        $discussDB->join = 'left join user as u on d.user_id = u.id';
        $discussDB->where = 'd.goods_id = ' . $goods_id;
        $discussDB->order = 'd.id desc';
        $discussDB->fields = 'u.username,d.time,d.contents';
        $discussDB->page = $page;

        $data = $discussDB->find();
        $pageHtml = $discussDB->getPageBar("javascript:void(0);", 'onclick="discuss_ajax([page])"');

        echo JSON::encode(array('data' => $data, 'pageHtml' => $pageHtml));
    }

    //买前咨询数据ajax获取
    function refer_ajax()
    {
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $page = IFilter::act(IReq::get('page'), 'int') ? IReq::get('page') : 1;

        $referDB = new IQuery('refer as r');
        $referDB->join = 'left join user as u on r.user_id = u.id';
        $referDB->where = 'r.goods_id = ' . $goods_id;
        $referDB->order = 'r.id desc';
        $referDB->fields = 'u.username,u.head_ico,r.time,r.question,r.reply_time,r.answer';
        $referDB->page = $page;

        $data = $referDB->find();
        $pageHtml = $referDB->getPageBar("javascript:void(0);", 'onclick="refer_ajax([page])"');

        echo JSON::encode(array('data' => $data, 'pageHtml' => $pageHtml));
    }

    //评论列表页
    function comments_list()
    {
        $id = IFilter::act(IReq::get("id"), 'int');
        $type = IFilter::act(IReq::get("type"));
        $data = array();

        //评分级别
        $type_config = array('bad' => '1', 'middle' => '2,3,4', 'good' => '5');
        $point = isset($type_config[$type]) ? $type_config[$type] : "";

        //查询评价数据
        $this->commentQuery = Api::run('getListByGoods', $id, $point);
        $this->commentCount = Comment_Class::get_comment_info($id);
        $this->goods = Api::run('getGoodsInfo', array("#id#", $id));
        if (!$this->goods) {
            IError::show("商品信息不存在");
        }
        $this->redirect('comments_list');
    }

    //提交评论页
    function comments()
    {
        $id = IFilter::act(IReq::get('id'), 'int');

        if (!$id) {
            IError::show(403, "传递的参数不完整");
        }

        if (!isset($this->user['user_id']) || $this->user['user_id'] == null) {
            IError::show(403, "登录后才允许评论");
        }

        $result = Comment_Class::can_comment($id, $this->user['user_id']);
        if (is_string($result)) {
            IError::show(403, $result);
        }

        $this->comment = $result;
        $this->commentCount = Comment_Class::get_comment_info($result['goods_id']);
        $this->goods = Comment_Class::goodsInfo($id);
        if (!$this->goods) {
            IError::show("商品信息不存在");
        }
        $this->redirect("comments");
    }

    /**
     * @brief 进行商品评论 ajax操作
     */
    public function comment_add()
    {
        $id = IFilter::act(IReq::get('id'), 'int');
        $content = IFilter::act(IReq::get("contents"));
        $img_list = IFilter::act(IReq::get("_imgList"));
        if (!$id || !$content) {
            $array = array(
                'info' => "填写完整的评论内容",
            );
            echo JSON::encode($array);
            exit;
        }

        if (!isset($this->user['user_id']) || !$this->user['user_id']) {
            $array = array(
                'info' => "未登录用户不能评论",
            );
            echo JSON::encode($array);
            exit;
        }

        $data = array(
            'point' => IFilter::act(IReq::get('score'), 'float'),
            'contents' => $content,
            'img_str' => $img_list,
            'status' => 1,
            'comment_time' => ITime::getNow("Y-m-d"),
        );

        if ($data['point'] == 0) {
            $array = array(
                'info' => "请选择分数",
            );
            echo JSON::encode($array);
            exit;
        }

        $result = Comment_Class::can_comment($id, $this->user['user_id']);
        if (is_string($result)) {
            $array = array(
                'info' => $result,
            );
            echo JSON::encode($array);
            exit;
        }

        $tb_comment = new IModel("comment");
        $tb_comment->setData($data);
        $re = $tb_comment->update("id={$id}");

        if ($re) {
            $commentRow = $tb_comment->getObj('id = ' . $id);

            //同步更新goods表,comments,grade
            $goodsDB = new IModel('goods');
            $goodsDB->setData(array(
                'comments' => 'comments + 1',
                'grade' => 'grade + ' . $commentRow['point'],
            ));
            $goodsDB->update('id = ' . $commentRow['goods_id'], array('grade', 'comments'));

            //同步更新seller表,comments,grade
            $sellerDB = new IModel('seller');
            $sellerDB->setData(array(
                'comments' => 'comments + 1',
                'grade' => 'grade + ' . $commentRow['point'],
            ));
            $sellerDB->update('id = ' . $commentRow['seller_id'], array('grade', 'comments'));
            //$this->redirect("/site/comments_list/id/".$commentRow['goods_id']);
            $array = array(
                'info' => "评论成功",
                'url' => IUrl::getHost() . IUrl::creatUrl('/ucenter/index'),
            );
            echo JSON::encode($array);
            exit;
        } else {
            $array = array(
                'info' => "评论失败",
            );
            echo JSON::encode($array);
            exit;
        }
    }

    function pic_show()
    {
        $this->layout = "";

        $id = IFilter::act(IReq::get('id'), 'int');
        $item = Api::run('getGoodsInfo', array('#id#', $id));
        if (!$item) {
            IError::show(403, '商品信息不存在');
        }
        $photo = Api::run('getGoodsPhotoRelationList', array('#id#', $id));
        $this->setRenderData(array("id" => $id, "item" => $item, "photo" => $photo));
        $this->redirect("pic_show");
    }

    function help()
    {
        $id = IFilter::act(IReq::get("id"), 'int');
        $tb_help = new IModel("help");
        $help_row = $tb_help->getObj("id={$id}");
        if (!$help_row) {
            IError::show(404, "您查找的页面已经不存在了");
        }
        $tb_help_cat = new IModel("help_category");
        $this->cat_row = $tb_help_cat->getObj("id={$help_row['cat_id']}");
        $this->help_row = $help_row;
        $this->redirect("help");
    }

    function helps()
    {
        $id = IFilter::act(IReq::get("id"), 'int');
        /*$tb_help  = new IModel("help");
        $help_row = $tb_help->getObj("id={$id}");
        */
        $tb_help_cat = new IModel("help_category");
        $cat_row = $tb_help_cat->getObj("id=$id");
        if (!$cat_row) {
            IError::show(404, "您查找的页面已经不存在了");
        }
        $this->cat_row = $cat_row;
        $this->redirect("helps");
    }

    function help_list()
    {
        $id = IFilter::act(IReq::get("id"), 'int');
        $tb_help_cat = new IModel("help_category");
        $cat_row = $tb_help_cat->getObj("id={$id}");

        //帮助分类数据存在
        if ($cat_row) {
            $this->helpQuery = Api::run('getHelpListByCatId', $id);
            $this->cat_row = $cat_row;
        } else {
            $this->helpQuery = Api::run('getHelpList');
            $this->cat_row = array('id' => 0, 'name' => '站点帮助');
        }
        $this->redirect("help_list");
    }

    //团购页面
    function groupon()
    {
        $id = IFilter::act(IReq::get("id"), 'int');

        //指定某个团购
        if ($id) {
            $this->regiment_list = Api::run('getRegimentRowById', array('#id#', $id));
            $this->regiment_list = $this->regiment_list ? array($this->regiment_list) : array();
        } else {
            $this->regiment_list = Api::run('getRegimentList');
        }

        if (!$this->regiment_list) {
            IError::show('当前没有可以参加的团购活动');
        }

        //往期团购
        $this->ever_list = Api::run('getEverRegimentList');
        $this->redirect("groupon");
    }

    //品牌列表页面
    function brand()
    {
        $id = IFilter::act(IReq::get('id'), 'int');
        $name = IFilter::act(IReq::get('name'));
        $this->setRenderData(array('id' => $id, 'name' => $name));
        $this->redirect('brand');
    }

    //品牌专区页面
    function brand_zone()
    {
        $brandId = IFilter::act(IReq::get('id'), 'int');
        $brandRow = Api::run('getBrandInfo', $brandId);
        if (!$brandRow) {
            IError::show(403, '品牌信息不存在');
        }
        $this->setRenderData(array('brandId' => $brandId, 'brandRow' => $brandRow));
        $this->redirect('brand_zone');
    }

    //商家主页
    function home()
    {
        $seller_id = IFilter::act(IReq::get('id'), 'int');
        $sellerRow = Api::run('getSellerInfo', $seller_id);
        if (!$sellerRow) {
            IError::show(403, '商户信息不存在');
        }
        $this->setRenderData(array('sellerRow' => $sellerRow, 'seller_id' => $seller_id));
        $this->redirect('home');
    }

    //积分兑换
    public function trade_ticket()
    {
        $ticketId = IFilter::act(IReq::get('ticket_id'), 'int');
        if ($ticketId == 0) {
            $array = array(
                'info' => "请选择兑换的优惠券",
            );
            echo JSON::encode($array);
            exit;
        } else {
            $user_id = isset($this->user['user_id']) ? $this->user['user_id'] : 0;
            if ($user_id == 0) {
                $array = array(
                    'info' => "请选登录，再领取优惠券",
                );
                echo JSON::encode($array);
                exit;
            } else {
                $nowTime = ITime::getDateTime();
                $ticketObj = new IModel('ticket');
                $ticketRow = $ticketObj->getObj('id = ' . $ticketId . ' and start_time <= "' . $nowTime . '" and end_time > "' . $nowTime . '"');
                if (empty($ticketRow)) {
                    $array = array(
                        'info' => "对不起，此代金券不能兑换",
                    );
                    echo JSON::encode($array);
                    exit;
                }

                $memberObj = new IModel('member');
                $where = 'user_id = ' . $this->user['user_id'];
                $memberRow = $memberObj->getObj($where, 'point');

                $propobj = new IModel("prop");
                if ($ticketRow['point'] == 0) {
                    $check_prop = $propobj->getObj("`condition`=" . $ticketId . " and is_close=0 and is_userd=0 and user_id=" . $user_id . " and is_send=1");
                    if ($check_prop) {
                        $array = array(
                            'info' => "不能重复领取",
                        );
                        echo JSON::encode($array);
                        exit;
                    }
                }

                if ($ticketRow['point'] > $memberRow['point']) {
                    $array = array(
                        'info' => "对不起，您的积分不足，不能兑换此类代金券",
                    );
                    echo JSON::encode($array);
                    exit;
                }

                $pro_info = $propobj->getObj("`condition`=" . $ticketId . " and is_close=0 and user_id=0 and is_send=1");
                if (empty($pro_info)) {
                    $array = array(
                        'info' => "对不起，此优惠券已领取完",
                    );
                    echo JSON::encode($array);
                    exit;
                }

                $propobj->setData(array('user_id' => $user_id));
                $result = $propobj->update("id=" . $pro_info['id']);

                if ($result) {
                    if ($ticketRow['point'] > 0) {
                        $pointConfig = array(
                            'user_id' => $this->user['user_id'],
                            'point' => '-' . $ticketRow['point'],
                            'log' => '积分兑换代金券，扣除了 -' . $ticketRow['point'] . '积分',
                        );
                        $pointObj = new Point;
                        $pointObj->update($pointConfig);
                        $array = array(
                            'info' => "领取成功",
                        );
                        echo JSON::encode($array);
                        exit;
                    } else {
                        $array = array(
                            'info' => "领取成功",
                        );
                        echo JSON::encode($array);
                        exit;
                    }
                } else {
                    $array = array(
                        'info' => "服务器繁忙，请稍后再试",
                    );
                    echo JSON::encode($array);
                    exit;
                }
            }
        }

    }

    public function ys_zx()
    {
        $mobile = IFilter::act(IReq::get('mobile'));
        $goods_id = IFilter::act(IReq::get('goods_id'), 'int');
        $propobj = new IModel("ys_zx");
        $propobj->setData(array('mobile' => $mobile, 'goods_id' => $goods_id, 'create_time' => ITime::getDateTime()));
        $id = $propobj->add();
        $msg = "提交失败，请稍后再试！";
        $error = true;
        if ($id > 0) {
            $error = false;
            $msg = "已收到您的信息，请等待药师联系";
        }
        $array = array(
            'error' => $error,
            'info' => $msg,
        );
        echo JSON::encode($array);
        exit;
    }


    //商品审核展示
    function products_examine()
    {
        $goods_id = IFilter::act(IReq::get('id'), 'int');

        if (!$goods_id) {
            IError::show(403, "传递的参数不正确");
            exit;
        }

        //使用商品id获得商品信息
        $tb_goods = new IModel('goods');
        $goods_info = $tb_goods->getObj('id=' . $goods_id . " AND is_del=3");
        if (!$goods_info) {
            IError::show(403, "这件商品不存在");
            exit;
        }


        //品牌名称
        if ($goods_info['brand_id']) {
            $tb_brand = new IModel('brand');
            $brand_info = $tb_brand->getObj('id=' . $goods_info['brand_id']);
            if ($brand_info) {
                $goods_info['brand'] = $brand_info['name'];
            }
        }

        //获取商品分类
        $categoryObj = new IModel('category_extend as ca,category as c');
        $categoryList = $categoryObj->query('ca.goods_id = ' . $goods_id . ' and ca.category_id = c.id', 'c.id,c.name', 'ca.id desc', 1);
        $categoryRow = null;
        if ($categoryList) {
            $categoryRow = current($categoryList);
        }
        $goods_info['category'] = $categoryRow ? $categoryRow['id'] : 0;

        //商品图片
        $tb_goods_photo = new IQuery('goods_photo_relation_examine as g');
        $tb_goods_photo->fields = 'p.id AS photo_id,p.img ';
        $tb_goods_photo->join = 'left join goods_photo as p on p.id=g.photo_id ';
        $tb_goods_photo->where = ' g.goods_id=' . $goods_id;
        $tb_goods_photo->order = ' g.id asc';
        $goods_info['photo'] = $tb_goods_photo->find();

        //商品是否参加促销活动(团购，抢购)
        $goods_info['promo'] = IReq::get('promo') ? IReq::get('promo') : '';
        $goods_info['active_id'] = IReq::get('active_id') ? IFilter::act(IReq::get('active_id'), 'int') : 0;
        if ($goods_info['promo']) {
            $activeObj = new Active($goods_info['promo'], $goods_info['active_id'], $this->user['user_id'], $goods_id);
            $activeResult = $activeObj->data();
            if (is_string($activeResult)) {
                IError::show(403, $activeResult);
            } else {
                $goods_info[$goods_info['promo']] = $activeResult;
            }
        }


        //商品关联
        $tb_goods_extend = new IQuery('goods_extend as ge');
        $tb_goods_extend->join = "left join goods as g on g.id=ge.extend_id";
        $tb_goods_extend->fields = "ge.*,g.img,g.name,g.sell_price";
        $tb_goods_extend->where = "ge.goods_id=" . $goods_id;
        $goods_extend_list = $tb_goods_extend->find();
        $goods_info['goods_extend_list'] = $goods_extend_list;


        //赠品关联
        $tb_goods_extend = new IQuery('goodsgift_extend as gg');
        $tb_goods_extend->join = "left join goods as g on g.id=gg.extend_id";
        $tb_goods_extend->fields = "gg.*,g.img,g.name,g.sell_price,g.goods_type";
        $tb_goods_extend->where = "gg.goods_id=" . $goods_id;
        $goodsgift_extend_list = $tb_goods_extend->find();
        $goods_info['goodsgift_extend_list'] = $goodsgift_extend_list;


        //获得扩展属性
        $tb_attribute_goods = new IQuery('goods_attribute as g');
        $tb_attribute_goods->join = 'left join attribute as a on a.id=g.attribute_id ';
        $tb_attribute_goods->fields = ' a.name,g.attribute_value ';
        $tb_attribute_goods->where = "goods_id='" . $goods_id . "' and attribute_id!=''";
        $goods_info['attribute'] = $tb_attribute_goods->find();

        //购买记录
        $tb_shop = new IQuery('order_goods as og');
        $tb_shop->join = 'left join order as o on o.id=og.order_id';
        $tb_shop->fields = 'count(*) as totalNum';
        $tb_shop->where = 'og.goods_id=' . $goods_id . ' and o.status = 5';
        $shop_info = $tb_shop->find();
        $goods_info['buy_num'] = 0;
        if ($shop_info) {
            $goods_info['buy_num'] = $shop_info[0]['totalNum'];
        }

        //购买前咨询
        $tb_refer = new IModel('refer');
        $refeer_info = $tb_refer->getObj('goods_id=' . $goods_id, 'count(*) as totalNum');
        $goods_info['refer'] = 0;
        if ($refeer_info) {
            $goods_info['refer'] = $refeer_info['totalNum'];
        }

        //网友讨论
        $tb_discussion = new IModel('discussion');
        $discussion_info = $tb_discussion->getObj('goods_id=' . $goods_id, 'count(*) as totalNum');
        $goods_info['discussion'] = 0;
        if ($discussion_info) {
            $goods_info['discussion'] = $discussion_info['totalNum'];
        }

        //获得商品的价格区间
        $tb_product = new IModel('products');
        $product_info = $tb_product->getObj('goods_id=' . $goods_id, 'max(sell_price) as maxSellPrice ,max(market_price) as maxMarketPrice');
        if (isset($product_info['maxSellPrice']) && $goods_info['sell_price'] != $product_info['maxSellPrice']) {
            $goods_info['sell_price'] .= "-" . $product_info['maxSellPrice'];
        }

        if (isset($product_info['maxMarketPrice']) && $goods_info['market_price'] != $product_info['maxMarketPrice']) {
            $goods_info['market_price'] .= "-" . $product_info['maxMarketPrice'];
        }

        //获得会员价
        $countsumInstance = new countsum();
        $goods_info['group_price'] = $countsumInstance->groupPriceRange($goods_id);

        //获取商家信息
        if ($goods_info['seller_id']) {
            $sellerDB = new IModel('seller');
            $goods_info['seller'] = $sellerDB->getObj('id = ' . $goods_info['seller_id']);
        }

        //如果登录添加浏览记录
        if ($this->user['user_id']) {
            $tb_goods_history = new IModel("goods_history");
            $history_info = $tb_goods_history->getObj("user_id=" . $this->user['user_id'] . " and goods_id=" . $goods_id);
            if (empty($history_info)) {
                $history_data = array();
                $history_data['user_id'] = $this->user['user_id'];
                $history_data['goods_id'] = $goods_id;
                $history_data['goods_name'] = $goods_info['name'];
                $history_data['goods_img'] = $goods_info['img'];
                $history_data['sell_price'] = $goods_info['sell_price'];
                $history_data['market_price'] = $goods_info['market_price'];
                $history_data['is_prescription'] = $goods_info['is_prescription'];
                $tb_goods_history->setData($history_data);
                $tb_goods_history->add();
            }
        }
        $this->is_xiangou_ok = 0;
        if ($goods_info['is_xiangou'] == 1) {
            $now_date = ITime::getDateTime();
            if ($goods_info['xiangou_start_date'] < $now_date && $goods_info['xiangou_end_date'] > $now_date) {
                $this->is_xiangou_ok = 1;
            }
        }
        //
        //        if($goods_info['is_gift'] == 1){
        //            IError::show(403,"这件商品不存在");
        //        }


        //取优惠券
        $nowtime = ITime::getDateTime();
        $ticket_all = Api::run('getTicketList', array('#nowtime#', $nowtime), 20);
        $ticket_list = array();
        if ($ticket_all) {
            foreach ($ticket_all as $Key => $val) {
                if ($val['goods_id'] > 0) {
                    if ($val['goods_id'] == $goods_id) {
                        $ticket_list[] = $val;
                    }
                } else {
                    if ($val['cat_id'] == 0) {
                        $ticket_list[] = $val;
                    } else {
                        $categoryObj = new IModel('category_extend as ca,category as c');
                        $categoryinfo = $categoryObj->query('ca.goods_id = ' . $goods_id . ' and ca.category_id = c.id and ca.category_id=' . $val['cat_id'], 'c.id,c.name', 'ca.id desc', 1);
                        if ($categoryinfo) {
                            $ticket_list[] = $val;
                        }
                    }
                }
            }
        }

        $this->ticket_list = $ticket_list;
        $favorite_class = "like";
        if ($this->user['user_id']) {
            $favoriteObj = new IModel('favorite');
            $favorite_info = $favoriteObj->getObj('user_id = ' . $this->user['user_id'] . ' and rid = ' . $goods_id);
            if ($favorite_info) {
                $favorite_class = "liked";
            }
        }
        $this->favorite_class = $favorite_class;
        //增加浏览次数
        $visit = ISafe::get('visit');
        $checkStr = "#" . $goods_id . "#";
        if ($visit && strpos($visit, $checkStr) !== false) {
        } else {
            $tb_goods->setData(array('visit' => 'visit + 1'));
            $tb_goods->update('id = ' . $goods_id, 'visit');
            $visit = $visit === null ? $checkStr : $visit . $checkStr;
            ISafe::set('visit', $visit);
        }

        $this->setRenderData($goods_info);
        $this->redirect('products_examine');
    }

    function health_goods()
    {
        //$goodsObj = search_goods::find(array('category_extend' => 'select id from category where parent_id in (select id from category where parent_id =121)'),20);
        //$resultData = $goodsObj->find();
        //print_r($resultData);
        //exit;
        $this->catId = 1;
        $this->redirect('health_goods');
    }

    function test()
    {
        print_r("test_ok");
        exit;
    }

    /**
     * 执行搜索
     */
    function release_search()
    {
        $search_word   = trim(IFilter::act(IReq::get("search_word")));
        //ICookie::set('search_word',$search_word);
        $type   = trim(IFilter::act(IReq::get("type")));
        $tbkeyword=new IModel("keyword");

        //热门商品排名前十(按收藏排序)
        $data['hot_word_list'] = $tbkeyword->query('hot = 1','word','`order` desc','10');
        if($search_word){
            if($type == ''){
                $type = 1;
            }
            $model_goods = new IModel('goods');
            $model_release = new IModel('release_information');
            $goods_no_where = 'goods_no like "%'.$search_word.'%"';
            $name_where = 'name like "%'.$search_word.'%"';
            $brand_where = 'brand like "%'.$search_word.'%"';
            $goods_where = "{$goods_no_where} or {$name_where} or  {$brand_where}";

            $goods_list = $model_goods->query($goods_where);
            $number = 0;
            foreach ($goods_list as $k => $v){
                $no_where = "goods_no = '".$v['goods_no']."' and type = {$type} and status = 2 and is_delete = 0";
                $release_list = $model_release->query($no_where);
                $number += count($release_list);
                $goods_list[$k]['release_list'] = $release_list;
            }
            $data['number'] = $number;
            $data['goods_list'] = $goods_list;
            $data['type'] = $type;
            $data['search_word'] = $search_word;
            $this->setRenderData($data);
            $this->redirect('release_search');
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
        }
        if($this->user['user_id']){
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
        }else{
            $data['attention_msg'] = 2;
            $data['favorite_msg'] = 2;
            $data['is_login'] = 1;
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
     * 发布排名
     */
    function release_rank(){
        $type   = IFilter::act(IReq::get("type"),'string');
        $rank   = IFilter::act(IReq::get("rank"),'string');
        $goodsObj=new IModel('goods as g , release_information as r');
        switch ($type){
            case 'transaction':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.transaction desc";
                    break;

                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.transaction desc";
                    break;
                }
            case 'releases':
                if ($rank=='supply'){
                    $where=" g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.release desc";
                    break;
                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.release desc";
                    break;
                }
            case 'browse':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.visit desc";
                    break;

                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.visit desc";
                    break;
                }
            case 'attention':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.favorite desc";
                    break;
                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.favorite desc";
                    break;
                }
            default:
                $where="g.goods_no=r.goods_no and r.status=2";
                break;
        }

        $goods_info=$goodsObj->query($where,'',$orderBy,'10');
        $this->goods_info=$goods_info;
        $this->transaction="transaction";
        $this->releases="releases";
        $this->browse="browse";
        $this->attention="attention";
        $this->type=$type;
        $this->rank=$rank;

        $this->redirect("release_rank");
    }

    function release_loading(){
        $type   = IFilter::act(IReq::get("type"),'string');
        $rank   = IFilter::act(IReq::get("rank"),'string');
        $page   = IFilter::act(IReq::get("page"),'post');
        $pagesize = 10;

        if(is_null($page)){
            $page=0;
        }else{
            $page=$page*$pagesize;
        }

        switch ($type){
            case 'transaction':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.transaction desc";
                    break;

                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.transaction desc";
                    break;
                }
            case 'releases':
                if ($rank=='supply'){
                    $where=" g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.release desc";
                    break;
                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.release desc";
                    break;
                }
            case 'browse':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.visit desc";
                    break;

                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.visit desc";
                    break;
                }
            case 'attention':
                if ($rank=='supply'){
                    $where="g.goods_no=r.goods_no and r.type=1 and r.status=2";
                    $orderBy="g.favorite desc";
                    break;
                }else{
                    $where="g.goods_no=r.goods_no and r.type=0 and r.status=2";
                    $orderBy="g.favorite desc";
                    break;
                }
            default:
                $where="g.goods_no=r.goods_no and r.status=2";
                $orderBy="g.release desc";
                break;
        }

        $sql="SELECT * FROM lb_goods as g,lb_release_information as r WHERE ".$where." ORDER BY ".$orderBy." limit ".$page.",".$pagesize." ";

        $goods_list=IDBFactory::getDB('shop')->query($sql);
        echo JSON::encode($goods_list);
    }


}
