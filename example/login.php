<?php

/**
 * @name 强制登陆
 * @tutorial 用于强制登陆刷新
 */
date_default_timezone_set('Asia/Shanghai');
include './Wechat.class.php';
$wechatOptions = require('./configure.php');
$wechatObj = new Wechat($wechatOptions);
$wechatObj->positiveInit();
// $wechatObj->setWechatToolFun($wechatToolObj);


var_dump($wechatObj->login());
