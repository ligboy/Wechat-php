<?php

/**
 * @author Ligboy
 * @name 微信调用接口类
 * @example 里面是Wechat类内所有需要的接口，都是需要按自己情况实现的方法
 */
class WechatTools{

	var $memcache;
	var $db_link = null;
	function __construct(){
		//这里使用memcache存储cookies和token，没有该环境的用户可以自己去实现使用文件或其他方式存取
		$this->memcache = new Memcache();
		$this->memcache->connect("127.0.0.1", 11211);
	}

	/**
	 * @name 获取Cookies
	 * @see WechatSessionToolInter::getCookies()
	 */
	public function getCookies($session="default") {
		return $this->memcache->get("wechat_cookies".$session);  //使用memcache高速缓存存取cookies
	}

	/** 
	 * @name 获取token
	 * @see WechatSessionToolInter::getToken()
	 */
	public function getToken() {
		return $this->memcache->get("wechat_token");  //使用memcache高速缓存存取Token
	}

    /**
     * @name 设置保存Cookies
     * @param string $Cookies
     * @param string $session
     * @see WechatSessionToolInter::setCookies()
     */
	public function setCookies($Cookies, $session='default') {
		$this->memcache->set("wechat_cookies".$session, $Cookies);  //使用memcache高速缓存存取cookies
	}

	/**
	 * @name 设置保存token
	 * @param string $token
	 * @see WechatSessionToolInter::setToken()
	 */
	public function setToken($token) {
		$this->memcache->set("wechat_token", $token);  //使用memcache高速缓存存取Token
	}

	/**
	 * @name 判断指定Openid是否关联
	 * @param string $Openid 指定Openid
	 * @return boolean 返回逻辑判断结果
	 * @see WechatAscToolInter::getAscStatusByOpenid()
	 */
	function getAscStatusByOpenid($Openid)
	{
		$sql = "SELECT * FROM weixin_followusers WHERE weixin_followusers.openid='$Openid'";
		$db_link = mysql_connect("127.0.0.1", "root", "password");
		if (!$db_link) {
			die("Connect Db Error!");
		}
		mysql_select_db("db_name", $db_link);
		mysql_query("set names 'utf8'", $db_link);
		// 	$query = "Select * from 'weixin_fakelist'";
		$result = mysql_query($sql, $db_link);
		$row = mysql_fetch_assoc($result);
		if ($row[2]!="")
		{
			return $row;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @name 判断指定fakeid是否关联
	 * @param string $fakeid 指定fakeid
	 * @return boolean 返回逻辑判断结果
	 * @see WechatAscToolInter::getAscStatusByFakeid()
	 */
	function getAscStatusByFakeid($fakeid)
	{
		$sql = "SELECT * FROM weixin_followusers WHERE weixin_followusers.fakeid='$fakeid'";
		$db_link = mysql_connect("127.0.0.1", "root", "password");
		if (!$db_link) {
			die("Connect Db Error!");
		}
		mysql_select_db("db_name", $db_link);
		mysql_query("set names 'utf8'", $db_link);
		$result = mysql_query($sql, $db_link);
		$row = mysql_fetch_assoc($result);
		if ($row) {
			return $row;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @name 设置fakeid与Openid的关联
	 * @param string $openid Openid
	 * @param string $fakeid fakeid
	 * @param string $detailInfo 用户详细信息(可选)
     * @return resource
     * @see WechatAscToolInter::setAssociation()
	 */
	function setAssociation($openid, $fakeid, $detailInfo)
	{
		$sql = "SELECT * FROM weixin_followusers WHERE weixin_followusers.openid='$openid'";
		$insertsql = "UPDATE weixin_followusers SET fakeid='$fakeid',name='$detailInfo[NickName]',gender='$detailInfo[Sex]'  WHERE weixin_followusers.openid='$openid'";
		$db_link = mysql_connect("127.0.0.1", "root", "password");
		if (!$db_link) {
			die("Connect Db Error!");
		}
		mysql_select_db("db_name", $db_link);
		mysql_query("set names 'utf8'", $db_link);
		$result = mysql_query($insertsql, $db_link);
		return $result;
	}

	/**
	 * @name 用户关注执行动作
	 * @param string $openid Openid
     * @return bool|resource
     * @see WechatFollowToolInter::followAddAction()
	 */
	function followAddAction($openid)
	{
		$sql = "SELECT id,fakeid,subscribed FROM weixin_followusers WHERE weixin_followusers.openid='$openid'";
		$updatesql = "UPDATE weixin_followusers SET weixin_followusers.subscribed=1 WHERE weixin_followusers.openid='$openid'";
		$insertsql = "INSERT INTO weixin_followusers(openid,subscribed) VALUE ('$openid',1)";
		$db_link = mysql_connect("127.0.0.1", "root", "password");
		if (!$db_link) {
			die("Connect Db Error!");
		}
		mysql_select_db("db_name", $db_link);
		mysql_query("set names 'utf8'", $db_link);
		$result = mysql_query($sql, $db_link);
		$row = mysql_fetch_assoc($result);
		var_dump($row);
		if ($row[2]==="0")
		{
				return mysql_query($updatesql, $db_link);
		}
		elseif($row[2]==="1")
		{
			return true;
		}
		else
		{
			return mysql_query($insertsql, $db_link);
		}
	}

	/**
	 * @name 取消关注执行动作
	 * @param string $openid Openid
	 * @see WechatFollowToolInter::followCancelAction()
	 */
	function followCancelAction($openid) {
		$updatesql = "UPDATE weixin_followusers SET weixin_followusers.subscribed=0 WHERE weixin_followusers.openid='$openid'";
		$db_link = mysql_connect("127.0.0.1", "root", "password");
		if (!$db_link) {
			die("Connect Db Error!");
		}
		mysql_select_db("db_name", $db_link);
		mysql_query("set names 'utf8'", $db_link);
		$result = mysql_query($updatesql, $db_link);
	}

}
//上面类的实例化
$wechatToolObj = new WechatTools();

//下面是设置文件
return array(
			'token'=>'mytoken',
			'account'=>'ligboy@gmail.com',
			'password'=>'password',
			"wechattool"=>$wechatToolObj /*这里是上面的接口类实例对象,也可以通过setWechatToolFun()设置*/
);