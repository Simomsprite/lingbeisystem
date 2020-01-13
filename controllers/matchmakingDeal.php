<?php
/**
 * Created by PhpStorm.
 * User: Super
 * Date: 2019-12-06
 * Time: 15:21
 */

class matchmakingDeal extends IController{

    public $layout='ucenter';

    function init()
    {

    }


    /**
     * @param $arr  要降维的数组
     * @param $clos 二级列的名字
     * @return array
     * 数组降维
     */
    function lowerdimensionality($arr,$clos){

        $narr=array();
        for ($i=0;$i<count($arr);$i++){
            $narr[]=$arr[$i][$clos];
        }
        return $narr;
    }


    /**
     * 撮合交易匹配
     */
    function precisematching($informationid){

        //$informationid=IFilter::act(IReq::get('informationid'));


        //判断是求购还是货源
        $tb_release_information = new IModel('release_information');
        $tbmatching = new IModel('matching');
        $tbgoods=new IModel("goods");


        $informationone=$tb_release_information->getObj("id='".$informationid."' and is_delete='0' ");
        $informationonearea= $informationone["area_id"];

        if($informationone["type"]==='0'){
            $matewinarray=array();
            //如果类型为求购(查询符合条件的货源信息,状态有效,没有删除) 货源信息列表
            if(!is_null($informationone["price"])){
                //$this->_siteConfig->pricethreshold  系统参数中的价格浮动阈值(暂时固定为0.1)
                if(is_null($this->_siteConfig->pricethreshold))
                    $this->_siteConfig->pricethreshold=0.1;
                    //筛选结果集
                    $matchinglist=$tb_release_information->query("type = 1 and  goods_no ='".$informationone["goods_no"]."' and  price <=".($informationone["price"]+$informationone["price"]*$this->_siteConfig->pricethreshold)." and status = 2 and is_delete = 0 and user_id!='".$informationone["user_id"]."' and up_time <'".$informationone["up_time"]."'");
            }else{
                Util::showMessage('发布的求购价格不能为空');
            }

            //判断是否是精确匹配
            if($informationone["is_exact"]==='1'){

                /**
                 * 判断两个数组中是否含有相同的元素
                 */
                //地址匹配
                $goods_area = new IModel('goods_area');
                //匹配者的省地址id数组
               // $listarea=$goods_area->query("release_id='".$informationone["id"]."' and  is_service='1'","pro_id");
                //降维方法(重新赋值)
               // $listarea=$this->lowerdimensionality($listarea,"pro_id");



                for ($i=0;$i<count($matchinglist);$i++){

                    //$matchingarea=$goods_area->query("release_id='".$matchinglist[$i]["id"]."' and  is_service='1'","pro_id");
                    //$matchingarea=$this->lowerdimensionality($matchingarea,"pro_id");

                    $matchinglistonearea=$matchinglist[$i]["area_id"];

                    /**
                     * 双向判断是否包含  包含则通过
                     */
                    if(strpos($informationonearea,$matchinglistonearea)!==false || strpos($matchinglistonearea,$informationonearea)!==false){
                        $matewinarray[]=$matchinglist[$i];
                    }else{
                    }
                    /*if(count(array_intersect($listarea,$matchingarea))===0){
                        //两个数组没有相同的元素项
                    }else{
                        $matewinarray[]=$matchinglist[$i];
                    }*/



                }


            }elseif($informationone["is_exact"]==='0') {

                //不是精确匹配的话,就直接返回匹配的结果,跳过地址匹配
                $matewinarray= $matchinglist;
            }else{
                Util::showMessage('精确匹配与否字段值错误');
            }

            /**
             * 更新一哈商品表
             */
            $transactionnum=$tbgoods->getObj("goods_no='".$informationone["goods_no"]."'","transaction");


            $content=array(
                "transaction"=>$transactionnum['transaction']+count($matewinarray),
                "modify_time"=>ITime::getDateTime()
            );


            $tbgoods->setData($content);
            $tbgoods->update("goods_no='".$informationone["goods_no"]."'");


            /**
             * 将匹配结果写入到lb_matching表中
             */

            if(count($matewinarray)>0){
                $both_message=array();
                $both_message["I_price"]= $informationone["price"];
                $both_message["I_number"]= $informationone["number"];
                $both_message["I_up_time"]=$informationone["up_time"];
            foreach ($matewinarray as $v){
                $both_message["M_price"]= $v["price"];
                $both_message["M_number"]= $v["number"];
                $both_message["M_up_time"]=$v["up_time"];

                $content=array(
                    "release_information_id"=>$informationone["id"],
                    "matching_object_id"=>$v["id"],
                    "both_message"=>json_encode($both_message),
                    "create_time"=>ITime::getDateTime()
                );

                $tbmatching->setData($content);
                $matching_result=$tbmatching->add();
                if(is_null($matching_result)){
                    return false;
                }
            }
                $c=array(
                    "is_matching"=>"1"
                );

                $tb_release_information->setData($c);
                $tb_release_information->update("id='".$informationone["id"]."'");
                return count($matewinarray);
            }else{
                $c=array(
                    "is_matching"=>"1"
                );

                $tb_release_information->setData($c);
                $tb_release_information->update("id='".$informationone["id"]."'");
                return 0;
            }

        }else if($informationone["type"]==='1'){

            $matewinarray=array();
            //如果类型为求购(查询符合条件的货源信息,状态有效,没有删除) 货源信息列表  (除去自己发布的)
            if(!is_null($informationone["price"])){
                $matchinglist=$tb_release_information->query("type = 0 and  goods_no ='".$informationone["goods_no"]."' and  price >=".($informationone["price"]-$informationone["price"]*$this->_siteConfig->pricethreshold)." and status = 2 and is_delete = 0 and  user_id!='".$informationone["user_id"]."' and up_time <'".$informationone["up_time"]."'");
            }else{
                Util::showMessage('发布的货源价格不能为空');
                return false;
            }

            //判断是否是精确匹配
            if($informationone["is_exact"]==='1'){
                //地址匹配
                //$goods_area = new IModel('goods_area');


               // $listarea=$goods_area->query("release_id='".$informationone["id"]."' and  is_service='1'","pro_id");
                //$listarea=$this->lowerdimensionality($listarea,"pro_id");
                for ($i=0;$i<count($matchinglist);$i++){

                    $matchinglistonearea=$matchinglist[$i]["area_id"];

                    //$matchingarea=$goods_area->query("release_id='".$matchinglist[$i]["id"]."' and  is_service='1'","pro_id");
                    //$matchingarea=$this->lowerdimensionality($matchingarea,"pro_id");

                    if(strpos($informationonearea,$matchinglistonearea)!==false || strpos($matchinglistonearea,$informationonearea)!==false){
                        $matewinarray[]=$matchinglist[$i];
                    }else{
                    }

/*                    if(count(array_intersect($listarea,$matchingarea))===0){
                        //两个数组没有相同的元素项
                    }else{
                        $matewinarray[]=$matchinglist[$i];
                    }*/
                }

                /**
                 * 发布求购,匹配若干个货源,推送到货源,然后货源显示匹配成功,匹配的状态依据,匹配表中有没有用户的匹配记录
                 */

            }elseif($informationone["is_exact"]==='0') {
                //不是精确匹配的话,就直接返回匹配的结果,跳过地址匹配
                $matewinarray= $matchinglist;
            }else{
                Util::showMessage('精确匹配与否字段值错误');
                return false;
            }
            /**
             * 更新一哈商品表
             */
            $transactionnum=$tbgoods->getObj("goods_no='".$informationone["goods_no"]."'","transaction");

            //修改商品表的商品字段值(匹配成功次数加1)
            $content=array(
                "transaction"=>$transactionnum['transaction']+count($matewinarray),
                "modify_time"=>ITime::getDateTime()
            );

            $tbgoods->setData($content);
            $tbgoods->update("goods_no='".$informationone["goods_no"]."'");


            /**
             * 将匹配结果写入到lb_matching表中
             */
            if(count($matewinarray)>0){  //判断匹配到的人个数 (如果不存在   则不添加)
                $both_message=array();
                $both_message["I_price"]= $informationone["price"];
                $both_message["I_number"]= $informationone["number"];
                $both_message["I_up_time"]=$informationone["up_time"];
            foreach ($matewinarray as $v){
                $both_message["M_price"]= $v["price"];
                $both_message["M_number"]= $v["number"];
                $both_message["M_up_time"]=$v["up_time"];

                $content=array(
                    "release_information_id"=>$informationone["id"],
                    "matching_object_id"=>$v["id"],
                    "both_message"=>json_encode($both_message),
                    "create_time"=>ITime::getDateTime()
                );

                $tbmatching->setData($content);
                $matching_result=$tbmatching->add();

                if(is_null($matching_result)){
                return false;
                }
            }

                $c=array(
                    "is_matching"=>"1"
                );

                $tb_release_information->setData($c);
                $tb_release_information->update("id='".$informationone["id"]."'");
                return count($matewinarray);
            }else {
                $c=array(
                    "is_matching"=>"1"
                );

                $tb_release_information->setData($c);
                $tb_release_information->update("id='".$informationone["id"]."'");
                return 0;
            }

        }else{
            Util::showMessage('卖家买家字段值错误');
            return false;
        }

    }



    /**
     * 货源 求购 匹配情况列表(.......)
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
        $goods_area = new IModel('goods_area');  //地址待定 --》确定一个发布一个求购只有一个地址
        $tbareas=new IModel("areas");
        //*********声明表操作类***********//


        $mlist=$tb_release_information->query("user_id='".$user_id."' and type='".$type."'  order by up_time asc");


        /**
         * 根据商品码 获取到要显示到页面上的 商品的名字 以及 商品的 宣传图片
         */

        for ($i=0;$i<count($mlist);$i++){

            //只取名字和图片两个字段的值，加快运行效率
            $good=$goods->getObj("goods_bar_code='".$mlist[$i]["goods_bar_code"]."'","name,img");
            $mlist[$i]["good"]=$good;           //将商品的名称以及原图存入发布列表中，然后返回到前台。
            $matchingnum=$matching->query("release_information_id='".$mlist[$i]["id"]."'","count(*) as count");
            $mlist[$i]["matchingnum"]=$matchingnum[0]["count"];  //匹配上的人数
            //地址()
            $goodareainfo=$goods_area->getObj("id='".$mlist[$i]["id"]."'","pro_id");
            $areasinfo=$tbareas->getObj("area_id='".$goodareainfo["pro_id"]."'","area_name");
            $mlist[$i]["areaname"]=$areasinfo["area_name"];

        }

        $returnarray=array();
        $returnarray["mlist"]=$mlist;  //求购或者货源的list
        $returnarray["type"]=$type;    //求购还是货源状态

        //将要返回的数据key设置成mlist
        $data['mlist'] = $returnarray;
        $this->setRenderData($data);

        $this->redirect('mypublish',false);

    }


    /**
     * 查看匹配的人数详情(.......)
     */

    function matchingdetailslist(){

        //获取到点击的发布的id
        $infomationid=IFilter::act(IReq::get('infomationid'));


        //*********声明表操作类***********//
        $user=new IModel("user");
        $goods = new IModel('goods');
        $member=new IModel("member");
        $matching = new IModel('matching');
        $tb_release_information = new IModel('release_information');
        //*********声明表操作类***********//

        //根据发布的id  查询匹配的id的集合
        $matchingidlist=$matching->query("release_information_id='".$infomationid."'");
        $matchingobjectarray=array();
        for ($i=0;$i<count($matchingidlist);$i++){
            $information=$tb_release_information->getObj("id='".$matchingidlist[$i]["matching_object_id"]."'");
            $userinfo=$user->getObj("id='".$information["user_id"]."'");
            //$usermember=$member->getObj("user_id='".$information["user_id"]."'","nickname");
            $information["userinfo"]=$userinfo;
            $matchingobjectarray[]=$information;
        }
        $backtrackparameter=array();
        $infomationinfo=$tb_release_information->getObj("id='".$infomationid."'");

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

        $this->redirect('mymatchingresult',false);


    }

    //个人主页(.......)
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
        $userinfo["area"]=$areasinfo["area_name"];

        $returndataarray=array();

        //根据用户id获取发布信息(求购集合)
        $information_buylist=$tbrelease_information->query("user_id='".$userid."' and type='0'","id,up_time,price,goods_no");
        for ($i=0;$i<count($information_buylist);$i++){
            $buygoodsinfo=$tbgoods->getObj("goods_no='".$information_buylist[$i]["goods_no"]."'","name,img");
            //商品的图片以及名字
            $information_buylist[$i]["goodsname"]=$buygoodsinfo["name"];
            $information_buylist[$i]["goodsimg"]=$buygoodsinfo["img"];
            //求购的地址
            $buygoods_areainfo=$tbgoods_area->getObj("release_id='".$information_buylist[$i]["id"]."'","pro_id");
            $areainfo=$tbareas->getObj("area_id='".$buygoods_areainfo["pro_id"]."'","area_name");
            $information_buylist[$i]["areaname"]=$areainfo["area_name"];

            $buyfavoriteinfo=$tbfavorite->query("user_id='".$adminuserid."' and rid='".$information_buylist[$i]["id"]."'");
            if(count($buyfavoriteinfo)==0){
                $information_buylist[$i]["iscollect"]='0';
            }else{
                $information_buylist[$i]["iscollect"]='1';
            }
        }

        //根据用户id获取发布信息(货源集合)
        $information_selllist=$tbrelease_information->query("user_id='".$userid."' and type='1'","id,up_time,price,goods_no");
        for ($j=0;$j<count($information_selllist);$j++){
            $sellgoodsinfo=$tbgoods->getObj("goods_no='".$information_selllist[$j]["goods_no"]."'","name,img");
            //商品的图片以及名字
            $information_selllist[$j]["goodsname"]=$sellgoodsinfo["name"];
            $information_selllist[$j]["goodsimg"]=$sellgoodsinfo["img"];
            //求购的地址
            $selllgoods_areainfo=$tbgoods_area->getObj("release_id='".$information_selllist[$j]["id"]."'","pro_id");
            $areainfo=$tbareas->getObj("area_id='".$selllgoods_areainfo["pro_id"]."'","area_name");
            $information_selllist[$j]["areaname"]=$areainfo["area_name"];

            $selllfavoriteinfo=$tbfavorite->query("user_id='".$adminuserid."' and rid='".$information_selllist[$j]["id"]."'");
            if(count($selllfavoriteinfo)==0){
                $information_selllist[$i]["iscollect"]='0';
            }else{
                $information_selllist[$i]["iscollect"]='1';
            }
        }

        $returndataarray["userinfo"]=$userinfo;
        $returndataarray["buylist"]=$information_buylist;
        $returndataarray["selllist"]=$information_selllist;

        $data['mlist'] = $returndataarray;
        $this->setRenderData($data);

        $this->redirect('userhomepage',false);


    }


    //点击收藏的时候(.......)
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


    //我的收藏(........)
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
            $goodarea=$tbgoodsarea->getObj("release_id='".$informationinfo["id"]."'","pro_id");
            $area=$tbarea->getObj("area_id='".$goodarea["pro_id"]."'","area_name");
            $informationinfo["areaname"]=$area["area_name"];

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


        $this->redirect('myselfcollect',false);

    }

    /**
     * 删除收藏(刷新页面)
     */
    //点击收藏的时候(........)
    function deletecollect(){

        $tbfavorite=new IModel("favorite");


        $informationid = IFilter::act(IReq::get("informationid"));


        //拿到登录人的id  发布id (然后将信息存入收藏表)
        $userid =$this->user["user_id"];


        $type=null;


        $tbfavorite->del("user_id='".$userid."' and rid='".$informationid."'");



        //不刷新页面(将显示与否的type值传入前端)
        $this->redirect("myselfcollect");
    }







}