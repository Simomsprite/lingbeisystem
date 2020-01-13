<?php
/**
 * Created by PhpStorm.
 * User: Huajie
 * Date: 2019/12/19
 * Time: 13:06
 */

class Wx_api extends IController {

    public $layout='';

    public function loginByEmail(){
        $email=IFilter::act(IReq::get('email','post'));
        $password=IFilter::act(IReq::get('password','post'));
        $csrf=IFilter::act(IReq::get('csrf','post'));

        if (strlen(trim($email))<=0){
            Block::returnAjax("400","邮箱不能为空");
        }

        if (strlen(trim($csrf))<=0){
            Block::returnAjax("403","非法请求");
        }

        if(!preg_match('|\S{6,32}|',$password))
        {
            Block::returnAjax("400","密码格式不正确,请输入6-32个字符");
        }

        if (ISession::get('csrf')!=$csrf){
            Block::returnAjax("403","非法请求");
        }

        $userRow = plugin::trigger("isValidUser",array($email,md5($password)));
        if ($userRow==false){
            Block::returnAjax("401","用户名或密码不正确");
        }

        //用户私密数据
        ISafe::set('user_id',$userRow['id']);
        ISafe::set('username',$userRow['username']);
        ISafe::set('head_ico',isset($userRow['head_ico']) ? $userRow['head_ico'] : '');

        $userM = new IModel('user');
        $userM->setData(['last_login_ip'=>$_SERVER['REMOTE_ADDR']]);
        $userM->update('id='.intval($userRow['id']));

        //更新最后一次登录时间
        $memberObj = new IModel('member');
        $dataArray = array(
            'last_login' => ITime::getDateTime(),
        );
        $memberObj->setData($dataArray);
        $where     = 'user_id = '.$userRow["id"];
        $memberObj->update($where);

        $callback = plugin::trigger('getCallback');

        if($callback){
            $url = IUrl::getHost().IUrl::creatUrl($callback);
        }
        else {
            $url = IUrl::getHost().IUrl::creatUrl('/ucenter/index');
        }
        ISession::set('csrf','');
        Block::returnAjax("200","登录成功",['jumpURL'=>$url]);
    }

    public function regByEmail(){
        $email=IFilter::act(IReq::get('email','post'));
        $password=IFilter::act(IReq::get('password','post'));
        $repassword=IFilter::act(IReq::get('repassword','post'));
        $csrf=IFilter::act(IReq::get('csrf','post'));

        if (strlen(trim($email))<=0){
            Block::returnAjax("400","邮箱不能为空");
        }

        if (!preg_match("!\w+@\w+\.\w+!",$email)){
            Block::returnAjax("400","邮箱格式不合法");
        }

        if (strlen(trim($csrf))<=0){
            Block::returnAjax("403","非法请求");
        }

        if(!preg_match('|\S{6,32}|',$password))
        {
            Block::returnAjax("400","密码格式不正确,请输入6-32个字符");
        }

        if($password!=$repassword)
        {
            Block::returnAjax("400","两次输入的密码不同");
        }

        if (ISession::get('csrf')!=$csrf){
            Block::returnAjax("403","非法请求");
        }


        $userRow=plugin::trigger("isValidUserByWx",[$email,md5($password)]);

        if ($userRow!=false){
            if($userRow['status']==3){
                $res = plugin::trigger("sendCheckMailbyWx",$email);
                if ($res['code']!=200){
                    Block::returnAjax($res['code'],$res['msg']);
                }
                Block::returnAjax("401","该邮箱尚未激活,请前往邮箱激活");
            }else{
                Block::returnAjax("400","该邮箱已注册");
            }
        }

        $siteConfig = new Config('site_config');
        $reg_option = $siteConfig->reg_option;

        /*注册信息校验*/
        if($reg_option == 2)
        {
            Block::returnAjax("401","当前网站禁止新用户注册");
        }

        $userM = new IModel('user');
        $userM->setData([
            'username'=>$email,
            'password'=>md5($password),
            'last_login_ip'=>$_SERVER['REMOTE_ADDR']
        ]);
        $user_id=$userM->add();

        if(!$user_id)
        {
            $userM->rollback();
            Block::returnAjax("401","用户创建失败");
        }

        //更新最后一次登录时间
        $memberObj = new IModel('member');
        $dataArray = array(
            'user_id'=>$user_id,
            'email'=>$email,
            'status'  => $reg_option == 1 ? 3 : 1,
            'last_login' => ITime::getDateTime(),
        );

        $memberObj->setData($dataArray);
        $memberObj->add();
        $res = plugin::trigger("sendCheckMailbyWx",$email);

        if ($res['code']!=200){
            Block::returnAjax($res['code'],$res['msg']);
        }
//        ISession::set('csrf','');
        Block::returnAjax("200","注册成功",['jumpURL'=>"_www/pages/login.html"]);
    }

    private function regByWx($union_id,$open_id,$nick_name,$head_ico){
        $nameConfig = new Config('name_config');
        $firstNameLen=count($nameConfig->firstName);
        $lastNameLen=count($nameConfig->lastName);
        $firstName=$nameConfig->firstName[mt_rand(0,$firstNameLen-1)];
        $lastName=$nameConfig->lastName[mt_rand(0,$lastNameLen-1)];
        $username=$firstName.'-'.$lastName;
        $head_ico=Block::getImage($head_ico);

        $userM=new IModel('user');
        $userM->setData([
            'username'=>$username,
            'password'=>md5('123456'),
            'wx_union_id'=>$union_id,
            'wx_open_id'=>$open_id,
            'head_ico'=>$head_ico,
            'last_login_ip'=>$_SERVER['REMOTE_ADDR'],
        ]);
        $user_id=$userM->add();

        if(!$user_id)
        {
            $userM->rollback();
            Block::returnAjax("401","用户创建失败");
        }

        $memberM=new IModel('member');
        $dataArray = array(
            'user_id'=>$user_id,
            'status'  => 1,
            'nickname'=>$nick_name,
            'last_login' => ITime::getDateTime(),
        );

        $memberM->setData($dataArray);
        $memberM->add();

        return $user_id;

    }
    public function loginByWx(){
        $union_id=IFilter::act(IReq::get('union_id','post'));
        $open_id=IFilter::act(IReq::get('open_id','post'));
        $nick_name=IFilter::act(IReq::get('nick_name','post'));
        $head_ico=IFilter::act(IReq::get('head_ico','post'));
        $csrf=IFilter::act(IReq::get('csrf','post'));


        if (strlen(trim($union_id))<=0){
            Block::returnAjax("400","未获取到微信用户ID");
        }

        if (strlen(trim($csrf))<=0){
            Block::returnAjax("403","非法请求");
        }

        if (ISession::get('csrf')!=$csrf){
            Block::returnAjax("403","非法请求");
        }

        $userM=new IModel('user');
        $user=$userM->getObj("wx_union_id='$union_id'");

        if (empty($user)){
            $user_id = $this->regByWx($union_id,$open_id,$nick_name,$head_ico);
            $user=$userM->getObj("wx_union_id='$union_id'");
        }

        $userM = new IModel('user');
        $userM->setData(['nickname'=>$nick_name,'last_login_ip'=>$_SERVER['REMOTE_ADDR'],'head_ico'=>Block::getImage($head_ico)]);
        $userM->update('id='.intval($user['id']));

        //更新最后一次登录时间
        $memberObj = new IModel('member');
        $dataArray = array(
            'nickname'=>$nick_name,
            'last_login' => ITime::getDateTime(),
        );

        $memberObj->setData($dataArray);
        $where     = 'user_id = '.intval($user["id"]);
        $memberObj->update($where);
        ISafe::set('last_login',$dataArray['last_login']);

        $callback = plugin::trigger('getCallback');

        if($callback){
            $url = IUrl::getHost().IUrl::creatUrl($callback);
        }
        else {
            $url = IUrl::getHost().IUrl::creatUrl('/ucenter/index');
        }
        $url=IUrl::getHost().IUrl::creatUrl('/simple/wxLoginAfter/uid/'.intval($user['id']).'/jumpUrl/'.base64_encode($url));
        ISession::set('csrf','');
        Block::returnAjax("200","登录成功",['jumpURL'=>$url]);

    }

}