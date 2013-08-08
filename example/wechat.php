<?php
/**
 * @name 微信被动接口示意文件
 * @author ligboy(ligboy@gmail.com) 
 * @license 
 * 
 */
date_default_timezone_set('Asia/Shanghai');
include "../Wechat.class.php";
//加载设置文件
$wechatOptions = require('./configure.php');
$wechatObj = new Wechat($wechatOptions);

$wechatObj->valid();//可以在认证后注释掉(只是这样可能不安全)

$wechatObj->positiveInit();  //主动响应组件初始化
$wechatObj->setAutoSendOpenidSwitch(TRUE);  //设置自动附带发送Openid
$wechatObj->setPassiveAscSwitch(TRUE, TRUE);  //设置打开被动关联组件，并获取用户详细信息

$wechatObj->getRev(); 


$revtype = $wechatObj->getRev()->getRevType();
switch($revtype) {
	case Wechat::MSGTYPE_TEXT:
		if(strstr($wechatObj->getRevContent(),"dddddddCSdddddsddddddd")) {
			$wechatObj->text("是英明的小弟。")->reply();
		}
		/***********************************************************************************/
		elseif (strstr($wechatObj->getRevContent(),"ligboy")) {
			$wechatObj->text("是你英明的老大啊。\n\n你快点叫老大吧。")->reply();
		}
		/***********************************************************************************/
		elseif (preg_match('/^[\s]*?帮助[\s]*?$/', $wechatObj->getRevContent())||preg_match('/^[\s]*?help[\s]*?$/', $wechatObj->getRevContent())) {
			$wechatObj->text("福大人帮助-有效的指令\n我的图书馆\n绑定图书馆\n取消绑定图书馆\n借阅信息\n")->reply();
		}
		/***********************************************************************************/
		else {
			$wechatObj->text("福大人帮助-有效的指令\n我的图书馆\n绑定图书馆  卡号  密码\n取消绑定图书馆\n借阅信息  卡号  密码")->reply();
		}
		break;
	case Wechat::MSGTYPE_EVENT:
		$revEvent = array();
		$revEvent = $wechatObj->getRevEvent();
		switch ($revEvent['event']) {
			case "subscribe":
				$wechatObj->text("欢迎您关注福大人，我们会用心为您服务。\n目前您可以使用的功能有：\n我的图书馆：发送: ”我的图书馆“指令查看\n\n如果您闲来无聊，可以试试和福大人小机器人聊天哦。\n    福大人工作室"."")->reply();
				break;
			case "unsubscribe":
				
				break;
		}
		break;
	case Wechat::MSGTYPE_IMAGE:
		$newsData = array(
		0=>array(
		'Title'=>'欢迎您关注福大人',
		'Description'=>"欢迎您关注福大人，我们会用心为您服务。\n\n    福大人工作室",
		'PicUrl'=>'http://com/weixin//static/images/fzu.gif',
		'Url'=>'http://r.com/weixin//info.html'
				),
		);
		$wechatObj->news($newsData)->reply();
		break;
	case Wechat::MSGTYPE_LOCATION:
		$revGeo = $wechatObj->getRevGeo();
		if ($revGeo) {
			$wechatObj->text("您的位置信息是：X=".$revGeo['x'].",Y=".$revGeo['y']."\n".$revGeo['label'])->reply();;
		}
		break;
	case Wechat::MSGTYPE_VOICE:
		//多媒体消息关联获取id，并下载文件到服务器本地示例
		$oneMessage = $wechatObj->getOneMessage($wechatObj->getRevCtime(), $wechatObj->getRevType(),$wechatObj->getRevFrom());
		$mediaFile = array();
		if ($oneMessage) {
			$mediaFile = $wechatObj->getDownloadFile($oneMessage["id"]);
		}
		// 		$wechatObj->text(serialize($mediaFile))->reply();
		$wechatObj->text($oneMessage?"消息id:$oneMessage[id]\n类型:$oneMessage[type]\nLO时间戳:".$wechatObj->getRevCtime()."\nMP时间戳:$oneMessage[dateTime]\n文件路径:$mediaFile[filename]\n文件大小:$mediaFile[filesize]\n文件类型:$mediaFile[filetype]":"获取失败\nLO时间戳:".$wechatObj->getRevCtime().print_r($oneMessage, TRUE))->reply();
		
		break;
	default:
		$wechatObj->text("help info")->reply();
}
?>