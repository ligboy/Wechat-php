<?php
/**
 * @name 定时调用获取fakelist并存入数据库（用于后面会实现的另一种关联方式）
 * @tutorial 可以使用本机的cron或者百度bae或者新浪sae等cron定时调用，一般时间间隔为10分钟
 */
date_default_timezone_set('Asia/Shanghai');

include './Wechat.class.php';
$wechatOptions = require('./configure.php');
$wechatObj = new Wechat($wechatOptions);
$wechatObj->positiveInit();
// $wechatObj->setWechatToolFun($wechatToolObj);

// var_dump($wechatObj->login());
//一次得到200个好友，10分钟内应该没有多少公共账号的增长速度超过这个吧
$friendlist = $wechatObj->getfriendlist(0, 200);
if($friendlist && is_array($friendlist))
{
	//批量插入数据库,并忽略重复
	$query = "insert ignore into weixin_fakelist(fakeid,nickname) VALUE ";
	$query .= "('".$friendlist[0]['fakeId']."','".$friendlist[0]['nickName']."')";
	for($i=1;$i<count($friendlist);$i++){
		$query .= ",('".$friendlist[$i]['fakeId']."','".$friendlist[$i]['nickName']."')";
	}
	$query .= ";";
	$db_link = mysql_connect("127.0.0.1", "root", "password");
	if (!$db_link) {
		die("Connect Db Error!");
	}
	mysql_select_db("db_name", $db_link);
	mysql_query("set names 'utf8'", $db_link);
	$result = mysql_query($query, $db_link);
	var_dump($result);
	
}
