<?php
/**
 * Created by PhpStorm.
 * User: Super
 * Date: 2019-12-06
 * Time: 15:49
 */

require_once($_SERVER['DOCUMENT_ROOT'].'/classes/configim.php');
use JMessage\IM\Report;
use JMessage\IM\Friend;
use JMessage\JMessage;
use JMessage\IM\Message;
use JMessage\IM\User;
use JMessage\IM\Resource;


class jgim extends IController implements userAuthorization
{

    public $layout='lb_jgim';

    function init()
    {


    }


    /**
     * 注册接口
     */
    function register($username){

        $appKey = 'efbdad17a9be59ad08af28a0';     //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';   //另外一个app密钥

        $jm = new JMessage($appKey, $masterSecret);
        $user=new User($jm);

        $res=$user->register($username,"111111");

        if($res["http_code"]==201){
            return "1";
        }else{
            return "0";
        }
    }


    function voice(){
        $this->redirect('hahahah',false);
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




    function room(){

        $tbuser=new IModel("user");

        //根据登录的 userid
        $adminuser_id=$this->user["user_id"];
        $fusername  = IFilter::act(IReq::get("fusername"));

        $adminuser_userinfo=$tbuser->getObj("id='".$adminuser_id."'","username");

        $musername=$adminuser_userinfo["username"];


        $tbuser=new IModel("user");

        //好友的用户
        $friendusername  = '442572136';

        //用户自己的用户名
        $myselfusername= 'hahcc';

        /**
         * 两个用户加好友
         */
        $appKey = 'efbdad17a9be59ad08af28a0';           //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';     //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);
        $user = new User($jm);
        $report = new Report($jm);
        $rescource = new Resource($jm);
        $friend = new Friend($jm);
        $fsarray=array();
        $fsarray[]=$fusername;
        $response = $friend->add($musername, $fsarray);


        /**
         * 暂时废掉（效率太慢）
         */
        //$mymessages = $report->getUserMessagesV1("hahcc", 0, 300);// 查询自己所聊过的记录(所有的记录)

        $mymessages=$report->getUserMessages("hahcc",500,date("Y-m-d H:i:s",strtotime("-2 day")),date("Y-m-d H:i:s"));


        //die;
        $cc=array();
        /**
         *  先将原来下标有缺失的   遍历重新存一下   变成下标相连的  从零开始
         */

        foreach ($mymessages["body"]["messages"]  as  $value){
            if(!empty($value) && ($value['from_id'] == $friendusername && $value['target_id'] == $myselfusername)  || ($value['target_id'] == $friendusername && $value['from_id'] == $myselfusername)){
                $cc[]=$value;
            }
        }

        /**
         *  然后将图片的真实路径可以访问的路径，存到url中  显示到聊天界面中
         */
        for ($i=0;$i<count($cc);$i++){
            if($cc[$i]["msg_type"]=="image"){
                $response = $rescource->download($cc[$i]["msg_body"]["media_id"]);
                $cc[$i]["msg_body"]["url"]=$response["body"]["url"];
            }
        }

        /**
         * 使用用户名 将用户的昵称查询出来   放进数组   渲染到前台
         */

        //根据用户名  查询  用户的信息明细
        $frieninfo=$tbuser->getObj("username='".$friendusername."'","id,head_ico,nickname");
        $myselfinfo=$tbuser->getObj("username='".$myselfusername."'","id,head_ico,nickname");


        $cc["fusername"]=$frieninfo["nickname"];      //好友的昵称
        $cc["myusername"]= $myselfinfo["nickname"];   //自己的昵称
        $cc["fuheadpoto"]= $frieninfo["head_ico"];    //好友的头像
        $cc["myheadpoto"]= $myselfinfo["head_ico"];       //自己的头像
        $cc["fuid"]= $frieninfo["id"];
        $cc["myid"]= $myselfinfo["id"];


        /**
         * 将所有的聊天数据,按照信息的先后顺序 渲染到前台页面
         */
        $data['messagelist'] = $cc;
        $this->setRenderData($data);

        $this->redirect('room',true);

    }


    /**
     * 发消息方法  重写(分开两个方法,暂时废掉)
     */
    function  message(){


        $appKey = 'efbdad17a9be59ad08af28a0';           //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';     //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);

        $message = new Message($jm);           //api中的方法new出一个然后可以调用message类中的发消息的方法
        $rescource = new Resource($jm);

        $userlist=M("user")->where("user_id=".session('user_id'))->find();
        $myselfusername= $userlist["chatname"];

        /**
         * 获取页面中post中的参数(发送者id,接受者id,发送内容)
         */
        if($_POST['from_id']==$myselfusername){
            $from_id    = $_POST['from_id'];    //前端传来的发送者id
            $target_id  = $_POST['target_id'];
        }else{
            $from_id    = $_POST['target_id'];    //前端传来的发送者id
            $target_id  = $_POST['from_id'];
        }

        $text       = $_POST['text'];
        $image      = $_POST['ccccc'];  //图像

        $imageName = "25220_".date("His",time())."_".rand(1111,9999).'.jpg';
        if (strstr($image,",")){
            $image = explode(',',$image);
            $image = $image[1];
        }

        $imageSrc= __DIR__ . '/../../../Public/imimage/'. $imageName; //图片名字,项目目录的全路径

        $r = file_put_contents($imageSrc, base64_decode($image));//返回的是字节数
        if (!$r) {
            $tmparr1=array('data'=>null,"code"=>1,"msg"=>"图片生成失败");
            echo json_encode($tmparr1);
        }else{
            $tmparr2=array('data'=>1,"code"=>0,"msg"=>"图片生成成功");
            echo json_encode($tmparr2);
        }

        if(!empty($text)){   //如果为空,则执行发送图片

            /**
             * 发送文字消息的参数
             */
            $from = [
                'id'   => $from_id,
                'type' => 'user'
            ];
            $target = [
                'id'   => $target_id,
                'type' => 'single'
            ];
            $msg = [
                'text' => $text
            ];

            /**
             * 发送消息  返回值中 201 则发送成功
             */
            $response = $message->sendText(1, $from, $target, $msg);

            switch ($response["http_code"]){
                case 201:
                    $this->ajaxReturn(1,"发送成功！", 1);
                    break;
            }

        }else{

            $imagec=$imageSrc;


            /**
             * 首先将图片上传到极光,然后将返回值作为 参数加入到发送图片参数,从而以真实图片呈现给用户
             */
            $response = $rescource->upload('image', $imagec);

            $body = $response['body'];
            /**
             * 发送者
             */
            $from = [
                'id'   => $from_id,
                'type' => 'user'
            ];
            /**
             *接收者
             */
            $target = [
                'id'   => $target_id,
                'type' => 'single'
            ];
            /**
             * 发送的图片的信息
             */
            $msg = $body;


            /**
             * 发送图片
             */
            $response = $message->sendImage(1, $from, $target, $msg);

            switch ($response["http_code"]){
                case 201:
                    $this->ajaxReturn(1,"发送成功！", 1);
                    break;
            }

        }

    }


    /**
     * 发送图片消息(暂时废掉  不调用)
     */
    function sendimage(){


        $appKey = 'efbdad17a9be59ad08af28a0';           //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';     //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);

        $message = new Message($jm);           //api中的方法new出一个然后可以调用message类中的发消息的方法
        $rescource = new Resource($jm);

        $imagepath = $_POST['imgpath'];

        /**
         * 通过测试  原来的写的上传因为在linux上不支持  暂时废掉
         */
        if(false){

        $base64_string = $_POST['base64_string'];

        $image=$base64_string;

        $imageName = "25220_".date("His",time())."_".rand(1111,9999).'.jpg';

        $exif = exif_read_data ($image,0,true);

        $path = 'upload/'.date("Y-m-d");

        //先行判断目录存在与否   不存在则创建这个路径（等待文件录入）
        if(is_dir($path))
        {

        }else{
            $dir = iconv("UTF-8", "GBK", $path);//文件夹路径
            mkdir ($dir,0777,true);
        }


        $imageSrc=  'upload/'.date("Y-m-d")."/". $imageName; //图片名字,项目目录的全路径



        if(isset($exif['IFD0']['Orientation'])){
            $source = imagecreatefromjpeg($image);//读取图片流
            //判断角度翻转
            switch ($exif['IFD0']['Orientation']){
                case 8:
                    $image = imagerotate($source, 90, 0);
                    //保存到本地
                    imagejpeg($image,$imageSrc);

                    //释放内存
                    imagedestroy($image);

                    break;
                case 3:
                    $image = imagerotate($source, 180, 0);
                    //保存到本地
                    imagejpeg($image,$imageSrc);

                    //释放内存
                    imagedestroy($image);

                    break;
                case 6:
                    $image = imagerotate($source, -90, 0);
                    //保存到本地
                    imagejpeg($image,$imageSrc);

                    //释放内存
                    imagedestroy($image);

                    break;
                case 1:
                    if (strstr($image,",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    //imagejpeg($image,$imageSrc);
                    file_put_contents($imageSrc, base64_decode($image));//返回的是字节数
                    break;

                default:
                    if (strstr($image,",")) {
                        $image = explode(',', $image);
                        $image = $image[1];
                    }
                    file_put_contents($imageSrc, base64_decode($image));//返回的是字节数
                    break;
            }

        }else{

            if (strstr($image,",")) {
                $image = explode(',', $image);
                $image = $image[1];
            }
            file_put_contents($imageSrc, base64_decode($image));//返回的是字节数

        }


        $imagec=$imageSrc;


        /**
         * 首先将图片上传到极光,然后将返回值作为 参数加入到发送图片参数,从而以真实图片呈现给用户
         */
        $response = $rescource->upload('image', $_SERVER['DOCUMENT_ROOT']."/".$imagec);
        }



        $response = $rescource->upload('image', $_SERVER['DOCUMENT_ROOT']."/".$imagepath);
        $body = $response['body'];
        /**
         * 发送者
         */
        $from = [
            'id'   => "hahcc",
            'type' => 'user'
        ];
        /**
         *接收者
         */
        $target = [
            'id'   => "442572136",
            'type' => 'single'
        ];
        /**
         * 发送的图片的信息
         */
        $msg = $body;


        /**
         * 发送图片
         */
        $response = $message->sendImage(1, $from, $target, $msg);

        switch ($response["http_code"]){
            case 201:
                die('1');
                break;
        }


    }



    function sendtext(){

        $tbuser=new IModel("user");
        $adminuserid=$this->user["user_id"];
        $userinfo=$tbuser->getObj("id='".$adminuserid."'","username");


        $musername = $userinfo["username"]; //自己登录人的用户名
        $fusername = $_POST['fusername'];   //好友的用户名


        $appKey = 'efbdad17a9be59ad08af28a0';           //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';     //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);

        $message = new Message($jm);           //api中的方法new出一个然后可以调用message类中的发消息的方法
        $rescource = new Resource($jm);


        $textcontent = $_POST['textcontent'];




        /**
         * 发送文字消息的参数
         */
        $from = [
            'id'   => "hahcc",
            'type' => 'user'
        ];
        $target = [
            'id'   => "442572136",
            'type' => 'single'
        ];
        $msg = [
            'text' => $textcontent
        ];
        //http_code
        /**
         * 发送消息  返回值中 201 则发送成功
         */
        $response = $message->sendText(1, $from, $target, $msg);

        switch ($response["http_code"]){
            case 201:
                die('1');
                break;
        }

    }


    /**
     * 发送图片消息
     */
    public function upload()
    {
        $tbuser=new IModel("user");
        $adminuserid=$this->user["user_id"];
        $userinfo=$tbuser->getObj("id='".$adminuserid."'","username");

        $fusername = $_POST['fusername']; //好友的用户名
        $musername = $userinfo["username"];//登录用户自己的用户名

        $appKey = 'efbdad17a9be59ad08af28a0';           //app密钥
        $masterSecret = 'a66414bc1ab0274b7a4535a7';     //另外一个app密钥
        $jm = new JMessage($appKey, $masterSecret);

        $message = new Message($jm);           //api中的方法new出一个然后可以调用message类中的发消息的方法
        $rescource = new Resource($jm);



        $result = array(
            'isError' => true,
        );
        $base64_string = $_POST['base64_string'];
        $image = $base64_string;

        $path='upload/'.date("Y-m-d")."/";
        if (!is_dir($path)){
            $dirs = iconv("UTF-8", "GBK", $path);//文件夹路径
            mkdir ($dirs,0777,true);
        }
        $savename = uniqid() . '.jpg';//localResizeIMG压缩后的图片都是jpeg格式
        $dir = "upload";
        $dir .= '/' . date('Y-m-d') . "/";

        $savepath = $dir . $savename;

        if (strstr($image, ",")) {
            $image = explode(',', $image);
            $image = $image[1];
        }
        file_put_contents($savepath, base64_decode($image));//返回的是字节数


        $response = $rescource->upload('image', $_SERVER['DOCUMENT_ROOT']."/".$savepath);
        $body = $response['body'];
        /**
         * 发送者
         */
        $from = [
            'id'   => "hahcc",
            'type' => 'user'
        ];
        /**
         *接收者
         */
        $target = [
            'id'   => "442572136",
            'type' => 'single'
        ];
        /**
         * 发送的图片的信息
         */
        $msg = $body;

        /**
         * 发送图片
         */
        $response = $message->sendImage(1, $from, $target, $msg);

        switch ($response["http_code"]){
            case 201:
                $result = array(
                    'flag'=> 1,
                    'img' =>$savepath
                );
                echo JSON::encode($result);
                break;
        }


    }








}
