<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

include './Wechat.class.php';
$wechatOptions = require('./configure.php');
$wechatObj = new Wechat($wechatOptions);
$wechatObj->positiveInit();
// $wechatObj->setWechatToolFun($wechatToolObj);

print_r(is_object($wechatToolObj));
var_dump($wechatObj->login());
$wechatObj->send("823058881", "这是一种问候啊！");

$msgid = "59272";
$filename = $wechatObj->getDownloadFile($msgid);
if ($filename) {
	print_r($filename);
}