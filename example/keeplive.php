<?php
/**
 * @name 定时调用保持在线
 * @tutorial 可以使用本机的cron或者百度bae或者新浪sae等cron定时调用，一般时间间隔为15分钟
 */
date_default_timezone_set('Asia/Shanghai');
include '../Wechat.class.php';
$wechatOptions = require('./configure.php');
$wechatObj = new Wechat($wechatOptions);
$wechatObj->positiveInit();
// $wechatObj->setWechatToolFun($wechatToolObj);

// var_dump($wechatObj->login());
var_dump($wechatObj->keepLive());