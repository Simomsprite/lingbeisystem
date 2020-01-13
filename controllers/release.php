<?php
/**
 * @brief 商品发布模块
 * @class Release
 * @note  前台
 */
class Release extends IController implements userAuthorization
{
    public $layout='site';

	public function init()
	{
        //如果已经登录，就跳到ucenter页面
//        if($this->user)
//        {
//            var_dump('111');die;
//        }
//        else
//        {
//            $this->redirect('/simple/lb_login');
//        }
	}
    /**
     * 直接发布页
     */
    public function index()
    {
        $release_id   = IFilter::act(IReq::get("release_id"));
        $goods_id   = IFilter::act(IReq::get("goods_id"));
        //如果发布ID存在
        if($release_id){
            //查询发布表内容
            $good_model = new IModel('release_information');
            $goodsWhere = " id = {$release_id} ";
            $data['goods_info'] = $good_model->getObj($goodsWhere);//一维
            //查询申请表内容
            $apply_model = new IModel('goods_apply');
            $applyWhere = " release_id = {$data['goods_info']['id']}";
            $data['apply_info'] = $apply_model->getObj($applyWhere);//一维
            //查询图片
            $img = $data['apply_info']['img'];
            $data['img_path'] = explode(',',$img);
        }
        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $this->setRenderData($data);
        $this->redirect('index');
    }

    /**
     *  发布求购
     */
    function seek()
    {
        $release_id   = IFilter::act(IReq::get("release_id"));
        //有商品ID
        $goods_id   = IFilter::act(IReq::get("goods_id"));
        //如果发布ID存在
        if($release_id){
            //查询发布表内容
            $good_model = new IModel('release_information');
            $goodsWhere = " id = {$release_id} ";
            $data['goods_info'] = $good_model->getObj($goodsWhere);//一维
            //查询申请表内容
            $apply_model = new IModel('goods_apply');
            $applyWhere = " release_id = {$data['goods_info']['id']}";
            $data['apply_info'] = $apply_model->getObj($applyWhere);//一维
            //查询图片
            $img = $data['apply_info']['img'];
            $data['img_path'] = explode(',',$img);
        }
        if($goods_id){

        }
        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $this->setRenderData($data);
        $this->redirect('seek');
    }
	/**
     *  AJAX城市联动
     */
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

    /**
     *  图片上传
     */
    //上传图片
    public function upload_pic(){
        $base64_string = $_POST['base64_string'];
        var_dump($base64_string);
        $image = $base64_string;

        $exif = @exif_read_data ($image,0,true);
        if ($exif === false) {
            $aaa = '222';
            return $aaa;
        }
        var_dump($exif);

        $name = rand(1,9999);
        $savename = uniqid() . $name . '.jpeg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y/m/d') . "/";
        $savepath = $dir . $savename;

        var_dump($savepath);
        IFile::mkdir($dir);


        if(isset($exif['IFD0']['Orientation'])){
            $source = imagecreatefromjpeg($image);//读取图片流
            //判断角度翻转
            switch ($exif['IFD0']['Orientation']){
                case 8:
                    $image = imagerotate($source, 90, 0);
                    //保存到本地
                    $images = imagejpeg($image,$savepath);
                    break;
                case 3:
                    $image = imagerotate($source, 180, 0);
                    //保存到本地
                    $images = imagejpeg($image,$savepath);
                    break;
                case 6:
                    $image = imagerotate($source, -90, 0);
                    //保存到本地
                    $images = imagejpeg($image,$savepath);
                    break;
                case 1:
                    if (strstr($image,",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    $images = file_put_contents($savepath, base64_decode($image));//返回的是字节数
                    break;

                default:
                    if (strstr($image,",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    $images = file_put_contents($savepath, base64_decode($image));//返回的是字节数
                    break;
            }
        }else{
            if (strstr($image,",")) {
                $image = explode(',', $image);
                $image = $image[1];
            }
            $images = file_put_contents($savepath, base64_decode($image));//返回的是字节数
        }
        var_dump($images);
        $fileMD5  = md5($savepath);
        $insertData = array(
            'id'  => $fileMD5,
            'img' => $savepath
        );
        $modelObj = new IModel('goods_photo');
        $modelObj->setData($insertData);
        $number = $modelObj->add();
        var_dump($number);die;
        //释放内存
        imagedestroy($image);

        if ($images) {
            echo '{"status":1,"content":"上传成功","url":"' . $savepath . '"}';
        } else {
            echo '{"status":0,"content":"上传失败"}';
        }

    }

    /**
     * 货源修改页
     */
    function edit()
    {
        $id = IFilter::act(IReq::get("release_id"));
        $model_release = new IModel('release_information');
        $goodsWhere = " id = {$id} ";
        $apply_info = $model_release->getObj($goodsWhere);//一维
        //如果该信息未通过审核，商品信息存在于goods_apply表
        if($apply_info['status'] == '0' || $apply_info['status'] == '1'){
            $model_apply = new IModel('goods_apply');
            $goods_info = $model_apply->getObj("goods_no = {$apply_info['goods_no']}");
            $data['goods_info'] = $goods_info;
            //查询图片
            $img = $data['goods_info']['img'];
            $data['img_path'] = explode(',',$img);
        }elseif ($apply_info['status'] == '2' || $apply_info['status'] == '3'){
            $model_goods = new IModel('goods');
            $goods_info = $model_goods->getObj("goods_no = {$apply_info['goods_no']}");
            $data['goods_info'] = $goods_info;
            //查询图片
            $img = $data['goods_info']['img'];
            $data['img_path'] = explode(',',$img);
        }

        //查询起购信息
        $model_section = new IModel('release_section');
        $data['section_info']  = $model_section->query("release_id=".$id);//二维

        //查询地址信息
        $model_area = new IModel('goods_area');
        $data['area_info']  = $model_area->getObj("release_id=".$id);//一维

        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $data['apply_info'] = $apply_info;
        $this->setRenderData($data);
        $this->redirect('edit');
    }

    /**
     * 图片
     */
    function pic()
    {
        //调用文件上传类
        $photoObj = new PhotoUpload();
        $photo    = current($photoObj->run());

        //判断上传是否成功，如果float=1则成功
        if($photo['flag'] == 1)
        {
            $result = array(
                'flag'=> 1,
                'img' => $photo['img']
            );
        }
        else
        {
            $result = array('flag'=> $photo['flag']);
        }
        echo JSON::encode($result);
    }

    /**
     * 货源修改页
     */
    function seek_edit()
    {
        $id = IFilter::act(IReq::get("release_id"));
        $model_release = new IModel('release_information');
        $goodsWhere = " id = {$id} ";
        $apply_info = $model_release->getObj($goodsWhere);//一维
        //如果该信息未通过审核，商品信息存在于goods_apply表
        if($apply_info['status'] == '0' || $apply_info['status'] == '1'){
            $model_apply = new IModel('goods_apply');
            $goods_info = $model_apply->getObj("goods_no = {$apply_info['goods_no']}");
            $data['goods_info'] = $goods_info;
            //查询图片
            $img = $data['goods_info']['img'];
            $data['img_path'] = explode(',',$img);
        }elseif ($apply_info['status'] == '2' || $apply_info['status'] == '3'){
            $model_goods = new IModel('goods');
            $goods_info = $model_goods->getObj("goods_no = {$apply_info['goods_no']}");
            $data['goods_info'] = $goods_info;
            //查询图片
            $img = $data['goods_info']['img'];
            $data['img_path'] = explode(',',$img);
        }

        //查询起购信息
        $model_section = new IModel('release_section');
        $data['section_info']  = $model_section->query("release_id=".$id);//二维

        //查询地址信息
        $model_area = new IModel('goods_area');
        $data['area_info']  = $model_area->getObj("release_id=".$id);//一维

        //获取国家信息
        $modelObj = new IModel('areas');
        $nation = $modelObj->query('parent_id = 0','','sort desc');
        $data['nation'] = $nation;
        $data['apply_info'] = $apply_info;
        $this->setRenderData($data);
        $this->redirect('edit');
    }

    static function base64_to_img($base64_string, $output_file)
    {
        $ifp = fopen($output_file, "wb");
        fwrite($ifp, base64_decode($base64_string));
        fclose($ifp);
        return ($output_file);
    }

    /**
     *AJAX校验条形码
     */
    function check_code()
    {
        $goods_bar_code   = IFilter::act(IReq::get("code"));
        $type   = IFilter::act(IReq::get("type"));
        $selease_model = new IModel('release_information');
        $seleaseWhere['goods_no'] = $goods_bar_code;
        $seleaseWhere['user_id'] = $this->user['user_id'];
        $seleaseWhere['type'] = $type;
        $selease_info = $selease_model->getObj($seleaseWhere);
        if(!empty($selease_info)){
            //同一个人发布了同一商品的发布/求购信息
            echo '{"status":1,"id":"' . $selease_info['id'] . '"}';die;
        }else{
            $goods_model = new IModel('goods');
            $goodsWhere = " goods_bar_code = {$goods_bar_code} ";
            $goods_info = $goods_model->getObj($goodsWhere);

            if($goods_info != array()){
                echo '{"status":1,"id":"' . $goods_info['id'] . '"}';
            }else{
                echo '{"status":2}';
            }
            die;
        }

    }

    /**
     *提交发布信息
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
        $min_price = array($price1,$price2,$price3);

        //新增发布表信息
        $release_model = new IModel('release_information');
        $insert_release = array(
            'goods_no'  => $goods_no,
            'number'  => $number,
            'price'  => min($min_price),
            'delivery_cycle'  => $delivery_cycle,
            'type'  => 1,
            'user_id'  => $this->user['user_id'],
            'up_time' => date('Y-m-d H:i:s.n'),
            'update_time' => date('Y-m-d H:i:s.n'),
            'status' => 0,
            'is_exact' => $is_exact,
            'is_delete' => 0,
        );
        $release_model->setData($insert_release);
        $release_id = $release_model->add();
        //新增发布表ID存在时
        if(!$release_id){
            //发布表新增失败
            $release_model->rollback();
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
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
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
        //新增货源地址
        $area_model = new IModel('goods_area');
        $area = explode(",",$area_id);
        $insert_area = array(
            'release_id' => $release_id,
            'nation_id' => $area[0],
            'pro_id' => $area[1],
            'city_id' => $area[2],
        );
        $area_model->setData($insert_area);
        $goods_area_id = $area_model->add();
        if(!$goods_area_id){
            $area_model->rollback();
            //货源表新增失败
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
        if($release_id && $apply_id && $section_id &&$goods_area_id){
            $result = array('status' => 1,'release_id' =>$release_id);
            die(JSON::encode($result));
        }
    }

    /**
     *提交发布信息
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

        //新增发布表信息
        $release_model = new IModel('release_information');
        $insert_release = array(
            'goods_no'  => $goods_no,
            'number'  => $seek_num,
            'price'  => $price,
            'type'  => 0,
            'user_id'  => $this->user['user_id'],
            'up_time' => date('Y-m-d H:i:s.n'),
            'update_time' => date('Y-m-d H:i:s.n'),
            'status' => 0,
            'is_exact' => $is_exact,
            'is_delete' => 0,
        );
        $release_model->setData($insert_release);
        $release_id = $release_model->add();
        //新增发布表ID存在时
        if(!$release_id){
            //发布表新增失败
            $release_model->rollback();
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
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

        //新增货源地址
        $area_model = new IModel('goods_area');
        $area = explode(",",$area_id);
        $insert_area = array(
            'release_id' => $release_id,
            'nation_id' => $area[0],
            'pro_id' => $area[1],
            'city_id' => $area[2],
        );
        $area_model->setData($insert_area);
        $goods_area_id = $area_model->add();
        if(!$goods_area_id){
            $area_model->rollback();
            //货源表新增失败
            $result = array('status' => 2);
            die(JSON::encode($result));
        }
        if($release_id && $apply_id &&$goods_area_id){
            $result = array('status' => 1,'release_id' =>$release_id);
            die(JSON::encode($result));
        }
    }


    /**
     * 修改页执行保存
     */
    function save()
    {

    }

    /**
     * 发布排名
     */
   /* function release_rank(){
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

    }*/
}
