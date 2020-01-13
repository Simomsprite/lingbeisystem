<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/classes/configim.php');
use JMessage\JMessage;
use JMessage\IM\User;

/**
 * Class register
 * 注册
 */
class register
{



    public static function register($username){

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

}