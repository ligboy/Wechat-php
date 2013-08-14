<?php
// error_reporting(0);
/**
 * 微信公共平台整合库
 * @author Ligboy (ligboy@gmail.com)
 * @license 本库的很多思路来自于网上的其他热心人士的贡献，大家任意使用，我本人放弃所有权利，如果您心情好，给我留个署名也行。
 *
 */

interface WechatSessionToolInter {
	/**
	 * @name 获取token
	 * 
	 */
	function getToken();
	
	/**
	 * @name 设置保存token
	 * @param string $token
	 */
	function setToken($token);

	/**
	 * @name 获取Cookies
     * @param string $session
	 *
	 */
	function getCookies($session);

    /**
     * @name 设置保存Cookies
     * @param string $Cookies
     * @param string $session
     * @return
     */
	function setCookies($Cookies, $session);
}

/**
 * @author Ligboy
 * @name 微信关联接口
 *
 */
interface WechatAscToolInter {

	/**
	 * @name 判断指定Openid是否关联
	 * @param string $Openid 指定Openid
	 * @return boolean 返回逻辑判断结果,已关联则返回用户信息数组
	 */
	function getAscStatusByOpenid($Openid);

	/**
	 * @name 判断指定fakeid是否关联
	 * @param string $fakeid 指定fakeid
	 * @return boolean 返回逻辑判断结果,已关联则返回用户信息数组
	 */
	function getAscStatusByFakeid($fakeid);

	/**
	 * @name 设置fakeid与Openid的关联
	 * @param string $openid Openid
	 * @param string $fakeid fakeid
	 * @param string $detailInfo $detailInfo
	 */
	function setAssociation($openid, $fakeid, $detailInfo);
	
}

interface WechatFollowToolInter {
	/**
	 * @name 用户关注执行动作
	 * @param string $openid Openid
	 */
	function followAddAction($openid);
	
	/**
	 * @name 取消关注执行动作
	 * @param string $openid Openid
	 */
	function followCancelAction($openid);
}

class Wechat {
	/* 配置参数  */
	/**
	 *
	 * @var array
	 * @example array('token'=>'微信接口密钥','account'=>'微信公共平台账号','password'=>'微信公共平台密码','webtoken'=>"微信公共平台网页url的token");
	 */
	private $wechatOptions=array('token'=>'rqerwer','account'=>'ligboy@gmail.com','password'=>'wwwwww');	//
	private $cookiefilepath = ""; //以文件形式保存cookie的保存目录，肯定是可写的
	public $webtoken = '';  
	private $webtokenStoragefile = "";  //微信公共平台的token存储文件，就是公共平后台网页的token
	public $debug =  false;  //调试开关
	public $protocol = "https";  //使用协议类型 http or  https

	/* 静态常量 */
	const MSGTYPE_TEXT = 'text';
	const MSGTYPE_IMAGE = 'image';
	const MSGTYPE_LOCATION = 'location';
	const MSGTYPE_LINK = 'link';
	const MSGTYPE_EVENT = 'event';
	const MSGTYPE_MUSIC = 'music';
	const MSGTYPE_NEWS = 'news';
	const MSGTYPE_VOICE = 'voice';
	const MSGTYPE_VIDEO = 'video';

	/* 私有参数 */
	private $_msg;
	private $_funcflag = false;
	public $_receive;
	private $_logcallback;
	private $_token;
	private $_getRevRunOnce = 0;
	private $_cookies;
	private $_wechatcallbackFuns = null;
	private $_curlHttpObject = null;
	/**
	 * @var boolean 自动附带发送openid开关
	 */
	private $_autosendopenid = false;


	/**
	 * @var boolean 被动响应关联动作开关
	 */
	private $_passiveAssociationSwitch = false;
	/**
	 * 
	 */
	/**
	 * @var boolean 被动响应关联动作开关
	 */
	private $_passiveAscGetDetailSwitch = false;
	/**
	 * 初始化工作
	 * @param array $option  array('token'=>'微信接口密钥','account'=>'微信公共平台账号','password'=>'微信公共平台密码');
	 */
	function __construct($option=array())
	{
		if (!empty($option))
		{
			$this->wechatOptions = array_merge($this->wechatOptions, $option);
		}
	}
	/**
	 * @name 主动动作初始化
	 * @return Wechat
	 */
	function positiveInit($session="default")
	{
		if (!is_object($this->_wechatcallbackFuns)) {
			if ($this->wechatOptions['wechattool']) {
				$this->setWechatToolFun($this->wechatOptions['wechattool']);
			}
			$this->setWechatToolFun($this->wechatOptions['wechattool']);
		}
		$this->_cookies[$session] = $this->getCookies($session);
		$this->webtoken = (string)$this->getToken();
		return $this;
	}
	private function curlInit($type=null, $option=null) {
		if (!isset($this->_curlHttpObject)) {
			$this->_curlHttpObject = new CurlHttp();
		}
		if ("single"==$type) {
			$this->_curlHttpObject->singleInit($option);
		}
		elseif ("roll"==$type){
			$this->_curlHttpObject->rollInit($option);
		}
		return $this->_curlHttpObject;
	}

	/**
	 * 验证请求签名操作
	 * @return boolean
	 */
	private function checkSignature()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];

		$token = $this->wechatOptions['token'];
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );

		if( $tmpStr == $signature )
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * 验证当前请求是否有效
	 * @param bool $return 是否返回
     * @return bool|string
     */
	public function valid($return=false)
	{
		$echoStr = isset($_GET["echostr"]) ? $_GET["echostr"]: '';
		if ($return)
		{
			if ($echoStr)
			{
				if ($this->checkSignature())
				{
					return $echoStr;
				}
				else
				{
					return false;
				}
			} else
				return $this->checkSignature();
		}
		else
		{
			if ($echoStr)
			{
				if ($this->checkSignature())
				{
					die($echoStr);
				}
				else
				{
					die('no access');
				}
			}
			else
			{
				if ($this->checkSignature())
				{
					return true;
				}
				else
				{
					die('no access');
				}
			}
		}
		return false;
	}


    /**
     * 设置发送消息
     * @param array|string $msg 消息数组
     * @param bool $append 是否在原消息数组追加
     * @return array
     */
	public function Message($msg = '',$append = false){
		if (is_null($msg)) {
			$this->_msg =array();
		}elseif (is_array($msg)) {
			if ($append)
				$this->_msg = array_merge($this->_msg,$msg);
			else
				$this->_msg = $msg;
			return $this->_msg;
		} else {
			return $this->_msg;
		}
	}

	public function setFuncFlag($flag) {
		$this->_funcflag = $flag;
		return $this;
	}

	private function log($log){
		if ($this->debug && function_exists($this->_logcallback)) {
			if (is_array($log)) $log = print_r($log,true);
			return call_user_func($this->_logcallback,$log);
		}
	}

	/**
	 * @name 获取微信服务器发来的信息
	 * @return mixed
	 */
	public function getRev()
	{
		$postStr = file_get_contents("php://input");
		$this->log($postStr);
		if (!empty($postStr))
		{
			$this->_receive = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		if ($this->_getRevRunOnce==0) {
			$event = $this->getRevEvent();
			if (Wechat::MSGTYPE_EVENT==$this->getRevType())
			{
				if ($event['event']=="subscribe" && method_exists($this->_wechatcallbackFuns, "followAddAction")) {
					$this->_wechatcallbackFuns->followAddAction($this->getRevFrom());
				}
				elseif ($event['event']=="unsubscribe" && method_exists($this->_wechatcallbackFuns, "followCancelAction")){
					$this->_wechatcallbackFuns->followCancelAction($this->getRevFrom());
				}
			}
			$this->doAssociationAction();

			$this->_getRevRunOnce = 1;
		}
		return $this;
	}

	/**
	 * 获取消息发送者
	 * @return string or boolean
	 */
	public function getRevFrom()
	{
		if ($this->_receive)
		{
			return $this->_receive['FromUserName'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * 获取消息接受者
	 * @return string or boolean
	 */
	public function getRevTo()
	{
		if ($this->_receive)
		{
			return $this->_receive['ToUserName'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * 获取接收消息的类型
	 */
	public function getRevType()
	{
		if (isset($this->_receive['MsgType']))
		{
			return $this->_receive['MsgType'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * 获取消息ID
	 */
	public function getRevID() {
		if (isset($this->_receive['MsgId']))
			return $this->_receive['MsgId'];
		else
			return false;
	}

	/**
	 * 获取消息发送时间
	 */
	public function getRevCtime() {
		if (isset($this->_receive['CreateTime']))
			return $this->_receive['CreateTime'];
		else
			return false;
	}

	/**
	 * 获取接收消息内容正文
	 */
	public function getRevContent(){
		if (isset($this->_receive['Content']))
			return $this->_receive['Content'];
		else
			return false;
	}

	/**
	 * 获取接收消息图片
	 */
	public function getRevPic(){
		if (isset($this->_receive['PicUrl']))
			return $this->_receive['PicUrl'];
		else
			return false;
	}

	/**
	 * 获取接收消息链接
	 */
	public function getRevLink(){
		if (isset($this->_receive['Url'])){
			return array(
					'url'=>$this->_receive['Url'],
					'title'=>$this->_receive['Title'],
					'description'=>$this->_receive['Description']
			);
		} else
			return false;
	}

	/**
	 * 获取接收地理位置
	 * @return array('x'=>'','y'=>'','scale'=>'','label'=>'')
	 */
	public function getRevGeo(){
		if (isset($this->_receive['Location_X'])){
			return array(
					'x'=>$this->_receive['Location_X'],
					'y'=>$this->_receive['Location_Y'],
					'scale'=>$this->_receive['Scale'],
					'label'=>$this->_receive['Label']
			);
		} else
			return false;
	}

	/**
	 * 获取接收事件推送
	 * @return array 成功返回事件数组，失败返回false
	 */
	public function getRevEvent(){
		if (isset($this->_receive['Event'])){
			return array(
					'event'=>$this->_receive['Event'],
					'key'=>$this->_receive['EventKey'],
			);
		} else
			return false;
	}

    /**
     * 获取接收语言推送
     * @return array|bool
     */
	public function getRevVoice(){
		if (isset($this->_receive['MediaId'])){
			return array(
					'mediaid'=>$this->_receive['MediaId'],
					'format'=>$this->_receive['Format'],
			);
		} else
			return false;
	}

	private static function xmlSafeStr($str)
	{
		return '<![CDATA['.preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/",'',$str).']]>';
	}

	/**
	 * 数据XML编码
	 * @param mixed $data 数据
	 * @return string
	 */
	private static function data_to_xml($data) {
		$xml = '';
		foreach ($data as $key => $val) {
			is_numeric($key) && $key = "item id=\"$key\"";
			$xml    .=  "<$key>";
			$xml    .=  ( is_array($val) || is_object($val)) ? self::data_to_xml($val)  : self::xmlSafeStr($val);
			list($key, ) = explode(' ', $key);
			$xml    .=  "</$key>";
		}
		return $xml;
	}

	/**
	 * XML编码
	 * @param mixed $data 数据
	 * @param string $root 根节点名
	 * @param string $item 数字索引的子节点名
	 * @param string $attr 根节点属性
	 * @param string $id   数字索引子节点key转换的属性名
	 * @param string $encoding 数据编码
	 * @return string
	 */
	private function xml_encode($data, $root='xml', $item='item', $attr='', $id='id', $encoding='utf-8') {
		if(is_array($attr)){
			$_attr = array();
			foreach ($attr as $key => $value) {
				$_attr[] = "{$key}=\"{$value}\"";
			}
			$attr = implode(' ', $_attr);
		}
		$attr   = trim($attr);
		$attr   = empty($attr) ? '' : " {$attr}";
        $xml    =null;
		$xml   .= "<{$root}{$attr}>";
		$xml   .= self::data_to_xml($data, $item, $id);
		$xml   .= "</{$root}>";
		return $xml;
	}

	/**
	 * 设置回复消息
	 * Examle: $obj->text('hello')->reply();
	 * @param string $text
     * @return $this
     */
	public function text($text='')
	{
		if ($this->_autosendopenid) {
			if (is_object($this->_wechatcallbackFuns) && method_exists($this->_wechatcallbackFuns, "getAscStatusByOpenid")) {
				if (!$this->_wechatcallbackFuns->getAscStatusByOpenid($this->getRevFrom())) {
					$text .= "<a href=\"##".$this->getRevFrom()."\"> </a>";
				}
			}
			else{
				$text .= "<a href=\"##".$this->getRevFrom()."\"> </a>";
			}
		}
		$FuncFlag = $this->_funcflag ? 1 : 0;
		$msg = array(
				'ToUserName' => $this->getRevFrom(),
				'FromUserName'=>$this->getRevTo(),
				'MsgType'=>self::MSGTYPE_TEXT,
				'Content'=>$text,
				'CreateTime'=>time(),
				'FuncFlag'=>$FuncFlag
		);
		$this->Message($msg);
		return $this;
	}

	/**
	 * 设置回复音乐
	 * @param string $title
	 * @param string $desc
	 * @param string $musicurl
	 * @param string $hgmusicurl
     * @return $this
     */
	public function music($title,$desc,$musicurl,$hgmusicurl='') {
		$FuncFlag = $this->_funcflag ? 1 : 0;
		$msg = array(
				'ToUserName' => $this->getRevFrom(),
				'FromUserName'=>$this->getRevTo(),
				'CreateTime'=>time(),
				'MsgType'=>self::MSGTYPE_MUSIC,
				'Music'=>array(
						'Title'=>$title,
						'Description'=>$desc,
						'MusicUrl'=>$musicurl,
						'HQMusicUrl'=>$hgmusicurl
				),
				'FuncFlag'=>$FuncFlag
		);
		$this->Message($msg);
		return $this;
	}

	/**
	 * 设置回复图文
	 * @param array $newsData
     * @return $this
     * @example 数组结构:
	 *  array(
	 *  	[0]=>array(
	 *  		'Title'=>'msg title',
	 *  		'Description'=>'summary text',
	 *  		'PicUrl'=>'http://www.domain.com/1.jpg',
	 *  		'Url'=>'http://www.domain.com/1.html'
	 *  	),
	 *  	[1]=>....
	 *  )
	 */
	public function news($newsData=array())
	{
		$FuncFlag = $this->_funcflag ? 1 : 0;
		$count = count($newsData);

		$msg = array(
				'ToUserName' => $this->getRevFrom(),
				'FromUserName'=>$this->getRevTo(),
				'MsgType'=>self::MSGTYPE_NEWS,
				'CreateTime'=>time(),
				'ArticleCount'=>$count,
				'Articles'=>$newsData,
				'FuncFlag'=>$FuncFlag
		);
		$this->Message($msg);
		return $this;
	}

    /**
     *
     * 向微信服务器回复消息
     * Example: $this->text('msg tips')->reply();
     * @param array|string $msg 要发送的信息, 默认取$this->_msg
     * @param bool $return 是否返回信息而输出  默认：false
     * @return string
     */
	public function reply($msg=array(),$return = false)
	{
		if (empty($msg))
		{
			$msg = $this->_msg;
		}
		$xmldata=  $this->xml_encode($msg);
		$this->log($xmldata);
		if ($return)
		{
			return $xmldata;
		}
		else
		{
			echo $xmldata;
		}
		//debug 调试记录回复信息
		if ($this->debug){file_put_contents($this->debugpath."./reply.txt","\n---".date('Y-m-d H:i:s')."\n".$xmldata,FILE_APPEND);}
	}


    /**
     * 登录微信公共平台，获取并保存cookie、webtoken到指定文件
     * @param string $session
     * @return mixed 成功则返回true，失败则返回失败代码
     */
	public function login($session='default'){
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/login?lang=zh_CN";
		$postfields["username"] = $this->wechatOptions['account'];
		$postfields["pwd"] = md5($this->wechatOptions['password']);
		$postfields["f"] = "json";
		$postfieldss = "username=".urlencode($this->wechatOptions['account'])."&pwd=".urlencode(md5($this->wechatOptions['password']))."&f=json";

		$this->curlInit("single");
		$response = $this->_curlHttpObject->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/cgi-bin/login", $this->_cookies[$session]);
		$result = json_decode($response, true);
		if ($result['ErrCode']=="65201"||$result['ErrCode']=="65202"||$result['ErrCode']=="0")
		{
			preg_match('/&token=([\d]+)/i', $result['ErrMsg'],$match);
			$this->webtoken = $match[1];
			$this->setToken($this->webtoken);
			$this->setCookies($this->_curlHttpObject->getCookies(),$session);
			return true;
		}
		else
		{
// 			return false;
			return $result['ErrCode'];
		}
	}

	/**
	 * @name 执行关联动作
	 */
	private function doAssociationAction()
	{
		//var_dump($this->_passiveAssociationSwitch && Wechat::MSGTYPE_EVENT!=$this->getRevType() &&	is_object($this->_wechatcallbackFuns) && method_exists($this->_wechatcallbackFuns, "getAscStatusByOpenid") && method_exists($this->_wechatcallbackFuns, "setAssociation") && !$this->_wechatcallbackFuns->getAscStatusByOpenid($this->getRevFrom()));
		if ($this->_passiveAssociationSwitch && Wechat::MSGTYPE_EVENT!=$this->getRevType() &&	is_object($this->_wechatcallbackFuns) && method_exists($this->_wechatcallbackFuns, "getAscStatusByOpenid") && method_exists($this->_wechatcallbackFuns, "setAssociation") && !$this->_wechatcallbackFuns->getAscStatusByOpenid($this->getRevFrom()))
		{
			//$messageList = $this->getMessage();
			$messageList = $this->getMessageAjax(0, 40, 0, 99999999+intval(mt_rand(0, 99999)));
			if ($messageList)
			{
				$count = 0;
				$fakeid = "";
				foreach ($messageList as $value)
				{
					if ($value["dateTime"]==$this->getRevCtime())
					{
						$count += 1;
						$fakeid = $value["fakeId"];
					}
				}
				if (1==$count && $fakeid!="")
				{
					$detailInfo = NULL;
					if ($this->_passiveAscGetDetailSwitch)
					{
						$detailInfo = $this->getContactInfo($fakeid);
					}
					$this->_wechatcallbackFuns->setAssociation((string)$this->getRevFrom(), $fakeid, $detailInfo);
				}
			}
		}
	}

    /**
     * 验证登录是否在线
     * @param string $session
     * @return boolean
     */
	public function checkValid($session='default')
	{
		$postfields = array();
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getregions?id=1054&t=ajax-getregions&lang=zh_CN&token=".$this->webtoken;
		//判断cookie是否为空，为空的话自动执行登录
		if ($this->_cookies[$session]||$this->_cookies[$session] = $this->getCookies($session))
		{
			$this->curlInit("single");
			$response = $this->_curlHttpObject->get($url, $this->protocol."://mp.weixin.qq.com/cgi-bin/", $this->_cookies[$session]);
			$result = json_decode($response,1);
			if(isset($result['num']))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}

    /**
     * keepLive心跳包保持，在线状态，推荐通过cron每15分钟调用一下
     * @param string $session
     * @return boolean
     */
	public function keepLive($session="default")
	{
/*        if($session && strpos($session, ","))
        {
            $sessionList = explode(",", $session);

        }*/
		if (!$this->checkValid($session)) {
			return (true===$this->login($session));
		}
		return 1;
	}

    /**
     * 主动单条发消息
     * @param $fakeid
     * @param  string $content 发送的内容
     * @param string $session
     * @return integer 返回发送结果：成功返回:1,登录问题返回:-1,其他原因返回:0
     */
	public function send($fakeid, $content, $session="default")
	{
		//判断cookie是否为空，为空的话自动执行登录
		if ($this->_cookies[$session]||true===$this->login($session))
		{
			$postfields = array();
			$postfields['tofakeid'] = $fakeid;
			$postfields['type'] = 1;
			$postfields['error']= "false";
			$postfields['token']= $this->webtoken;
			$postfields['content'] = $content;
			$postfields['ajax'] = 1;
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
			$this->curlInit("single");
			$response = $this->_curlHttpObject->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/", $this->_cookies[$session]);
			$tmp = json_decode($response,true);
			//判断发送结果的逻辑部分
			if ('ok'==$tmp["msg"]) {
				return 1;
			}
			elseif ($tmp['ret']=="-2000")
			{
				return -1;
			}
			else
			{
				return 0;
			}
		}
		else  //登录失败返回false
		{
			return 0;
		}
	}

    /**
     * 主动群发相同消息，目前暂支持文本方式
     * @param  array $fakeidGroup     接受微信fakeid集合数组
     * @param  string $content 群发消息内容
     * @param string $session
     * @return mixed  返回一个记录发送结果的数组列表
     * 这里需要注意请求耗时问题，目前采用curl并发性请求.
     */
	public function batSend($fakeidGroup,$content, $session="default")
	{
		$queueSendArray = array();
		foreach ($fakeidGroup as $key =>$value)
		{

			$queueSendArray[] = array(
					'fakeid' => $value,
					'content' => $content,
                    'type'    => Wechat::MSGTYPE_TEXT,
                    'session' => $session
			);
		}
		return $this->doQueueSend($queueSendArray);
	}
	
	/**
	 * 主动发送队列消息，目前暂支持文本方式
	 * @param array 发送队列数组<br />array(array('fakeid'=>'','content'=>"", 'type'=>''text' , 'session'=>'default'))
	 * @param integer $queueCount 并发数量,默认10
	 * @return mixed  返回一个记录发送结果的数组列表
	 * 这里需要注意请求耗时问题，目前采用curl并发性请求.
	 */
	public function queueSend($queueSendArray,$queueCount=10)
	{
		return $this->doQueueSend($queueSendArray,$queueCount);
	}
	
	/**
	 * 执行主动发送队列，默认并发队列数是10
	 * @param array $queueSendArray 发送队列数组  array(array('fakeid'='','content'))
	 * @param integer $queueCount 并发数量,默认10
	 * @return array  返回一个记录发送结果的数组列表
	 **/
	 private function doQueueSend($queueSendArray, $queueCount=10)
	 {
		$requestArray = array();
		foreach ($queueSendArray as $key =>$value)
		{
            $postfields = array();
            switch($value['type'])
            {
                case null:
                case Wechat::MSGTYPE_TEXT:
                    $postfields = $this->buildTextPostFields($value);
                    break;
                default:
                    break;
            }
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
			$requestArray[$key] = array('url'=>$url,'method'=>'post','postfields'=>$postfields,'referer'=>$this->protocol."://mp.weixin.qq.com/",'cookies'=>$this->_cookies[($value['session']?$value['session']:"default")]);
		}
		function callback($result, $key){
			$tmp = json_decode($result,true);
			//判断发送结果的逻辑部分
			if ('ok'==$tmp["msg"]) {
				return 1;
			}
			elseif ($tmp['ret']=="-2000")
			{
				return -1;
			}
			else
			{
				return 0;
			}
		};
	 	$this->curlInit("roll");
		$this->_curlHttpObject->setRollLimitCount($queueCount);
		$response = $this->_curlHttpObject->setCallback("callback")->rollRequest($requestArray);
		return $response;
	}


    /**
     * 获取用户的信息
     * @param  string $fakeid 用户的fakeid
     * @param string $session
     * @return mixed 如果成功获取返回数据数组，登录问题返回false，其他未知问题返回true，
     */
	public function getContactInfo($fakeid, $session='default')
	{
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getcontactinfo?t=ajax-getcontactinfo&lang=zh_CN&fakeid=".$fakeid;
		$this->curlInit("single");
		$postfields = array("token"=>$this->webtoken, "ajax"=>1);
		$response = $this->_curlHttpObject->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/", $this->_cookies[$session]);
		$result = json_decode($response, 1);
		if($result['FakeId']){
			return $result;
		}
		elseif ($result['ret'])
		{
			return false;
		}
		else
		{
			return false;
		}
	}

    /**
     * 获取消息所附文件
     * @param  string $msgid 消息的id
     * @param null $filepath
     * @param string $session
     * @return array 如果成功获取返回下载的文件的基本信息
     */
	public function getDownloadFile($msgid, $filepath = null, $session="default")
	{
		if ($this->_cookies[$session]||true===$this->login($session))
		{
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/downloadfile?token=".$this->webtoken."&msgid=$msgid&source=";
			$ch = curl_init();
			$tmpfile = $filepath?$filepath:tempnam(sys_get_temp_dir(), 'WechatFileTemp');
			$fp = @fopen($tmpfile,"w");
			if ($fp) {
				curl_setopt($ch, CURLOPT_URL, $url);
				$options = array(
						CURLOPT_RETURNTRANSFER => true,         // return web page
						CURLOPT_HEADER         => false,
						CURLOPT_FOLLOWLOCATION => true,         // follow redirects
						CURLOPT_ENCODING       => "",           // handle all encodings
						CURLOPT_USERAGENT      => "",     // who am i
						CURLOPT_AUTOREFERER    => true,         // set referer on redirect
						CURLOPT_CONNECTTIMEOUT => 10,          // timeout on connect
						CURLOPT_TIMEOUT        => 10,          // timeout on response
						CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
						CURLOPT_POST            => false,            // i am sending post data
						CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
						CURLOPT_SSL_VERIFYPEER => false,        //
// 						CURLOPT_FILE => $fp, //目标文件保存路径
// 						CURLOPT_RETURNTRANSFER => 1
				);
				curl_setopt_array($ch, $options);
				$reqCookiesString = "";
				if(is_array($this->_cookies[$session])){
					foreach ($this->_cookies[$session] as $key => $val){
						$reqCookiesString .=  $key."=".$val."; ";
					}
					curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
				}
				$content = curl_exec($ch);
				$info = (curl_getinfo($ch));
				curl_close($ch);
				fwrite($fp, $content);
				fclose($fp);
				$result = array();
				echo filesize($tmpfile);
				if ($content && file_exists($tmpfile) && filesize($tmpfile)>0 && $info["content_type"]!="text/html") {
					
					$result["filename"] = $tmpfile;
					$result["filesize"] = filesize($tmpfile);
					$result['filetype'] = $info["content_type"];
					return $result;
				}
			}
		}
		return false;
	}

    /**
     * @name 获取公共消息列表（html）
     * @param int|number $day
     * @param int|number $count 数量限制
     * @param int|number $page 页数
     * @param string $session
     * @return array|boolean
     */
	public function getMessage($day=0, $count=100, $page=1, $session="default")
	{
		if ($this->_cookies[$session]||true===$this->login($session))
		{
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getmessage?t=wxm-message&token=".$this->webtoken."&lang=zh_CN&count=100";
			$this->curlInit("single");
			$result = $this->_curlHttpObject->get($url, $this->protocol."://mp.weixin.qq.com/cgi-bin/", $this->_cookies[$session]);
			if (preg_match('%<script type="json" id="json-msgList">([\s\S]*?)</script>%', $result, $match)) {
				$tmp = json_decode($match[1], true);
				return $tmp;
			}
			else
			{
				return false;
			}
			
		}
	}

    /**
     * @name 获取与指定用户的对话信息列表
     * @param string $fakeid 要获取指定用户消息的fakeid（必选）
     * @param int|number $lastmsgid 最早消息的id
     * @param int|number $createtime 最早消息的时间戳
     * @param string $lastmsgfromfakeid 消息最后来源
     * @param string $session
     * @return bool|mixed
     */
	public function getSingleMessage($fakeid, $lastmsgid=1, $createtime=0, $lastmsgfromfakeid=null, $session="default")
	{
		if (!empty($this->_cookies[$session]))
		{
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlemsgpage?t=ajax-single-getnewmsg";
			$this->curlInit("single");
			$postfield = array();
			$postfield['createtime']=$createtime;
			$postfield['fromfakeid']=$fakeid;
			$postfield['opcode']=1;
			$postfield['lastmsgfromfakeid']=$lastmsgfromfakeid;
			$postfield['lastmsgid']=$lastmsgid;
			$postfield['token']=$this->webtoken;
			$postfield['ajax']=1;
			$result = $this->_curlHttpObject->post($url, $postfield, $this->protocol."://mp.weixin.qq.com/",$this->_cookies[$session]);
			if ($result)
			{
				return json_decode($result, true);
			}
		}
		return false;
	}

    /**
     * @name 获取公共消息时间线列表
     * @param int|number $day 获取几日内的消息参数（0:当天;1:昨天;2:前天;3:最近5天.默认0）
     * @param int|number $count 获取消息数量限制.默认100
     * @param int|number $offset 获取消息开始位置,差不多是偏移分页的样子.默认是0
     * @param int|number $msgid 最后消息的id 默认为9999999999(意味着全部消息的意思)
     * @param bool|int $timeline 这个参数决定了上面的$day是否有效，设置成false,直接按时间线排列的全部消息
     * @param string $session
     * @return mixed|boolean
     */
	public function getMessageAjax($day=0, $count=100, $offset=1, $msgid=999999999, $timeline=1, $session="default")
	{
		if ($this->_cookies[$session]||true===$this->login($session))
		{
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getmessage?t=ajax-message&lang=zh_CN&count=$count&timeline=".($timeline?"1":"")."&day=$day&star=&frommsgid=$msgid&cgi=getmessage&offset=".intval($offset);
			$this->curlInit("single");
			$postfieldArray = array(
					"token"	=>	$this->webtoken,
					"ajax"	=>	1
			);
			$header = array(
					"X-Requested-With" => "XMLHttpRequest"
			);
			$result = $this->_curlHttpObject->post($url, $postfieldArray, $this->protocol."://mp.weixin.qq.com/cgi-bin/", $this->_cookies[$session], $header);
			if ($result) {
				return json_decode($result, true);
			}
			else
			{
				return false;
			}
			
		}
	}

    /**
     * @name 得到确定的某条消息(因为微信两个时间戳有时不同, 所以这个接口效果不完美)
     * @param string $datetime
     * @param null $type
     * @param null $openid
     * @param string $session
     * @return bool
     */
	public function getOneMessage($datetime=NULL, $type=NULL, $openid=NULL, $session="default")
	{
		if (!$type) {
			$type = $this->getRevType();
		}
		if (!$datetime) {
			$datetime = $this->getRevCtime();
		}
		if (!$openid) {
			$openid = $this->getRevFrom();
		}
		
		$typeList = array(Wechat::MSGTYPE_TEXT=>1, Wechat::MSGTYPE_IMAGE=>2, Wechat::MSGTYPE_VOICE=>3, Wechat::MSGTYPE_VIDEO=>4, Wechat::MSGTYPE_LOCATION=>1);

		if ($openid && method_exists($this->_wechatcallbackFuns, "getAscStatusByOpenid") && is_array($userInfo = $this->_wechatcallbackFuns->getAscStatusByOpenid($openid)))
		{
			if ($userInfo['fakeid'])
			{
				$singleMessage = $this->getSingleMessage($userInfo['fakeid'], 1, (string)(intval($datetime)-10));
				$singleMessageCount = count($singleMessage);
				if ($singleMessageCount==1)
				{
					if( $userInfo['fakeid']==$singleMessage[0]['fakeId'] && (empty($type) || $singleMessage[0]['type']==$typeList[$type]) )
					{
						return $singleMessage[0];
					}
				}
				elseif ($singleMessageCount>1)
				{
					for($i=0;$i<$singleMessageCount;$i++)
					{
						if ( $userInfo['fakeid']==$singleMessage[0]['fakeId'] && $datetime == $singleMessage[$i]['dateTime'])
						{
							return $singleMessage[$i];
						}
						
					}
					for($i=0;$i<$singleMessageCount;$i++)
					{
						if( $userInfo['fakeid']==$singleMessage[$i]['fakeId'] && $singleMessage[$i]['type']==$typeList[$type])
						{
							
							return $singleMessage[$i];
						}
					}
				}
				else
				{
					return FALSE;
				}
			}
		}
		//获取40条最新的公共消息列表
		$messageList = $this->getMessageAjax(0, 40, 0, 99999999+intval(mt_rand(0, 99999)));
		$messageListCount = count($messageList);
		if ($messageListCount>0) {
			$matchMessageList = array();
			for($i=0;$i<$messageListCount;$i++)
			{
				if (($datetime?$datetime:$this->getRevCtime()) == $messageList[$i]['dateTime'] && ($type?($messageList[$i]['type']==$typeList[$type]):true))
				{
					$matchMessageList[] = $messageList[$i];
				}
				
			}
			if (count($matchMessageList)==1) {
				return $matchMessageList[0];
			}
		}
		return FALSE;
		
	}

    /**
     * @name 得到指定分组的用户列表
     * @param int|number $groupid
     * @param int $pagesize
     * @param string $session
     * @return Ambigous <boolean, string, mixed>
     */
	public function getfriendlist($groupid=0, $pagesize=100, $session="default")
	{
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/contactmanagepage?token=$this->webtoken&t=wxm-friend&pagesize=$pagesize&groupid=$groupid";
		$referer = $this->protocol."://mp.weixin.qq.com/";
		$this->curlInit("single");
		$response = $this->_curlHttpObject->get($url, $referer, $this->_cookies[$session]);
		$tmp = "";
		if (preg_match('%<script id="json-friendList" type="json/text">([\s\S]*?)</script>%', $response, $match)) {
			$tmp = json_decode($match[1], true);
		}
		return empty($tmp)?false:$tmp;
	
	}
	
	
	/**
	 * 获取用户的fakeid
	 * @param callback $callback 处理匹配结果的回调函数，剥离出来方便大家自己的实现自己的逻辑，大致就是循环的查找，并写入数据库之类的
	 *
	 * 下面是示例：
	 * 		function callback($result, $key, $request, $otherCallbackArg){
	 * 			$reruen_tmp = false;
	 * 			dump($result);
	 * 			foreach ($otherCallbackArg['data'] as $data_key => $data_value)
	 	* 			{
	 * 				if(false !== strpos($result, substr(md5($data_value['openid']), 0, 16)))
	 	* 				{
	 *     	    		$subscribeusersModel = D("Subscribeusers");
	 *         	    	$condition['openid'] = $data_value['openid'];
	 *             	    $data = $subscribeusersModel->where($condition)->save(array('fakeid'=>$request['postfields']['fromfakeid']));
	 *                 	$otherCallbackArg['wechatObj']->putIntoGroup($request['postfields']['fromfakeid'], 101);
	 *                  $reruen_tmp = $data_value['openid'];
	 *                  break;
	 *               }
	 *          }
	 *          return $reruen_tmp;
	 *     };
	 *     print_r($this->wechatObj->getfakeid("callback"));
	 */
	/* public function getfakeid($callback)
	{
		//接下来是数据库的访问，大家可以按照自己的环境修改，接下来会通过回调函数解决。
		$subscribeusersModel = D("Subscribeusers");
		$data = $subscribeusersModel->where(' 'fakeid' IS NULL and 'unsubscribed'=0')->select();
		//$data 是当前fakeid为空的用户的列表数组
		if (!is_array($data))
		{
			die("none data");
		}
		$unfriendList = $this->getfriendlist(0);
		if (!$unfriendList){
			die("none friendlist");
		}
		$requestArray = array();
		foreach ($unfriendList as $key => $value)
		{
			// 			$requestArray[$key]['postfields']['createtime'] = time()-60000;
			$requestArray[$key]['postfields']['fromfakeid'] = $value['fakeId'];
			$requestArray[$key]['postfields']['opcode'] = 1;
			$requestArray[$key]['postfields']['token'] = $this->webtoken;
			$requestArray[$key]['postfields']['ajax'] = 1;
			$requestArray[$key]['referer'] = $this->protocol."://mp.weixin.qq.com/";
			$requestArray[$key]['cookiefilepath'] = $this->cookiefilepath;
			$requestArray[$key]['method'] = "post";
			$requestArray[$key]['url'] = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlemsgpage?t=ajax-single-getnewmsg";
		}
		$this->curlInit("roll");
		$rollingCurlObj->setOtherCallbackArg(array('data'=>$data, 'wechatObj'=>$this));
		$response = $rollingCurlObj->setCallback($callback)->request($requestArray);
		// 		dump($response);
	} */

    /**
     * 将用户放入制定的分组
     * @param array $fakeidsList
     * @param string $groupid
     * @param string $session
     * @return boolean 放入是否成功
     */
	public function putIntoGroup($fakeidsList, $groupid, $session="default")
	{
		$fakeidsListString = "";
		if(is_array($fakeidsList))
		{
			foreach ($fakeidsList as $value)
			{
				$fakeidsListString .= $value."|";
			}
		}
		else
		{
			$fakeidsListString = $fakeidsList;
		}
		$postfields['contacttype'] = $groupid;
		$postfields['tofakeidlist'] = $fakeidsListString;
		$postfields['token'] = $this->webtoken;
		$postfields['ajax'] = 1;
		$referer = $this->protocol."://mp.weixin.qq.com/";
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/modifycontacts?action=modifycontacts&t=ajax-putinto-group";
		$this->curlInit("roll");
		$response = $this->_wechatcallbackFuns->post($url, $postfields, $referer, $this->_cookies[$session]);
		$tmp = json_decode($response, true);
		$result = $tmp['ret']=="0"&&!empty($tmp)?true:false;
		return $result;
	}
	
	public function setWechatToolFun($class){
		if (is_string($class)) {
			$toolObj = new $class;
			if (is_object($toolObj)) {
				$this->_wechatcallbackFuns = $toolObj;
				return $this;
			}
			else{
				return false;
			}
		}
		elseif (is_object($class)){
			$this->_wechatcallbackFuns = $class;
			return $this;
		}
		else{
			return false;
		}
	}

    /**
     * @param string $session
     * @return $this
     */
	public function getCookies($session = "default") {
        if(is_object($this->_wechatcallbackFuns))
        {
		    return $this->_wechatcallbackFuns->getCookies($session);
        }
        return $this;
	}

	/**
	 * @return the $wechatOptions
	 */
	public function getToken() {
        if(is_object($this->_wechatcallbackFuns))
        {
		    return $this->_wechatcallbackFuns->getToken();
        }
        else
        {
            return false;
        }
	}
	/**
	 * @return the $wechatOptions
	 */
	public function getWechatOptions() {
		return $this->wechatOptions;
	}

    /**
     * 设置微信配置信息
     * @param array $wechatOptions
     * @return $this
     */
	public function setWechatOptions($wechatOptions=array()) {
        if(is_array($wechatOptions))
        {
            $this->wechatOptions = array_merge($this->wechatOptions, $wechatOptions);
        }
		return $this;
	}

    /**
     * 设置cookie保存位置
     * @param string $cookies cookie
     * @param string $session
     * @return $this
     */
	public function setCookies($cookies, $session="default") {
        if(is_object($this->_wechatcallbackFuns))
        {
            $this->_wechatcallbackFuns->setCookies($cookies, $session);
        }
		return $this;
	}

    /**
     * 设置token保存
     * @param string $token token
     * @param string $session
     * @return $this
     */
	public function setToken($token, $session="default") {
        if(is_object($this->_wechatcallbackFuns))
        {
		    $this->_wechatcallbackFuns->setToken($token, $session);
        }
		return $this;
	}

	/**
	 * @param boolean $debug
     * @return $this
     */
	public function setDebug($debug) {
		$this->debug = $debug;
		return $this;
	}

	/**
	 * 设置是否自动附带发送openid开关,default：False
	 * @param boolean $autosendopenid
	 * @return Wechat
	 */
	public function setAutoSendOpenidSwitch($autosendopenid=FALSE) {
	$this->_autosendopenid = $autosendopenid;
	return $this;
}

	/**
	 * @设置被动关联动作开关
	 * @param boolean $switch 开关
	 * @param boolean $detailSwitch 是否获取用户详细信息开关
	 * @return Wechat
	 */
	public function setPassiveAscSwitch($switch, $detailSwitch=false) {
	$this->_passiveAssociationSwitch = $switch;
	$this->_passiveAscGetDetailSwitch = $detailSwitch;
	return $this;
}

    /**
     * 构建文本类型提交表单
     *
     * @param $singleMessageFields 单挑表单数组
     * @return array
     */
    private function buildTextPostFields($singleMessageFields)
    {
        $singlepostfields = array();
        $singlepostfields['tofakeid'] = $singleMessageFields['fakeid'];
        $singlepostfields['type'] = 1;
        $singlepostfields['error'] = "false";
        $singlepostfields['token'] = $this->webtoken;
        $singlepostfields['content'] = $singleMessageFields['content'];
        $singlepostfields['ajax'] = 1;
        return $singlepostfields;
    }


}




/**
 * Rolling Curl Request Class
* @author Ligboy (ligboy@gamil.com)
* @copyright
* @example
*
*
*/
class CurlHttp {


	/* 单线程请求设置项 */

	/* 并发请求设置项 */
	private $limitCount = 10; //并发请求数量
	public $returninfoswitch = false;  //是否返回请求信息，开启后单项请求返回结果为:array('info'=>请求信息, 'result'=>返回内容, 'error'=>错误信息)

	//私有属性
	private $singlequeue = null;
	private $rollqueue = null;
	private $_requstItems = null;
	private $_callback = null;
	private $_result;
	private $_referer = null;
	private $_cookies = array();
	private $_resheader;
	private $_reqheader = array();
	private $_resurl;
	private $_redirect_url;
	private $referer;

	private $_singleoptions = array(
			CURLOPT_RETURNTRANSFER => true,         // return web page
			CURLOPT_HEADER         => true,        // don't return headers
// 			CURLOPT_FOLLOWLOCATION => true,         // follow redirects
			CURLOPT_NOSIGNAL      =>true,
			CURLOPT_ENCODING       => "",           // handle all encodings
			CURLOPT_USERAGENT      => "",           // who am i
			CURLOPT_AUTOREFERER    => true,         // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
			CURLOPT_TIMEOUT        => 120,          // timeout on response
			CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
			CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
			CURLOPT_SSL_VERIFYPEER => false,        //
	);
	private $_rolloptions = array(
			CURLOPT_RETURNTRANSFER => true,         // return web page
			CURLOPT_HEADER         => true,        // don't return headers
// 			CURLOPT_FOLLOWLOCATION => true,         // follow redirects
			CURLOPT_NOSIGNAL      =>true,
			CURLOPT_ENCODING       => "",           // handle all encodings
			CURLOPT_USERAGENT      => "",           // who am i
			CURLOPT_AUTOREFERER    => true,         // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
			CURLOPT_TIMEOUT        => 120,          // timeout on response
			CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
			CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
			CURLOPT_SSL_VERIFYPEER => false,        //
	);
		

	function singleInit($options = array()) {
		if (!$this->singlequeue) {
			$this->singlequeue = curl_init();
		}
		if ($options) {
			$this->_singleoptions = array_merge($this->_singleoptions, $options);
		}
	}
	function rollInit($options = array()) {
		if(!$this->rollqueue){
			$this->rollqueue = curl_multi_init();
		}
		if ($options) {
			$this->_rolloptions = array_merge($this->_rolloptions, $options);
		}
	}

    /**
     * @name 返回Header数组
     * @param resource $ch
     * @param $result
     * @return string
     */
	private function getResRawHeader($ch, $result) {
		$ch_info = curl_getinfo($ch);
		$header_size = $ch_info["header_size"];
		$rawheader = substr($result, 0, $ch_info['header_size']);
		return $rawheader;
	}

    /**
     * @name 返回Header数组
     * @param resource $ch
     * @param $result
     * @return string
     */
	private function getResHeader($ch, $result) {
		$header = array();
		$rawheader = $this->getResRawHeader($ch, $result);
		if(preg_match_all('/([^:\s]+): (.*)/i', $rawheader, $header_match)){
			for($i=0;$i<count($header_match[0]);$i++){
				$header[$header_match[1][$i]] = $header_match[2][$i];
			}
		}
		return $header;
	}

    /**
     * @name 返回网页主体内容
     * @param resource $ch
     * @param $result
     * @return string 网页主体内容
     */
	private function getResBody($ch, $result) {
		$ch_info = curl_getinfo($ch);
		$body = substr($result, -$ch_info['download_content_length']);
		return $body;
	}

    /**
     * @name 返回网页主体内容
     * @param resource $ch
     * @param $result
     * @return array 网页主体内容
     */
	private function getResCookies($ch, $result) {
		$rawheader = $this->getResRawHeader($ch, $result);
		$cookies = array();
		if(preg_match_all('/Set-Cookie:(?:\s*)([^=]*?)=([^\;]*?);/i', $rawheader, $cookie_match)){
			for($i=0;$i<count($cookie_match[0]);$i++){
				$cookies[$cookie_match[1][$i]] = $cookie_match[2][$i];
			}
		}
		return $cookies;
	}

	private function setReqCookies($ch, $reqcookies = array()) {
		$reqCookiesString = "";
		if(!empty($reqcookies)){
			if(is_array($reqcookies)){
				foreach ($reqcookies as $key => $val){
					$reqCookiesString .=  $key."=".$val."; ";
				}
				curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
			}
		}elseif(!empty($this->_cookies)) {
			foreach ($this->_cookies as $key => $val){
				$reqCookiesString .=  $key."=".$val."; ";
			}
			curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
		}
	}
	private function setResCookies($ch) {
		if(!empty($reqcookies)&&is_array($reqcookies)){
			$this->_cookies = array_merge($this->_cookies, $reqcookies);
		}
	}

    /**
     * @param unknown $url
     * @param mixed $postfields
     * @param string $referer
     * @param array $reqcookies
     * @param array $reqheader
     * @return unknown
     */
	function post($url, $postfields=null, $referer=null, $reqcookies=null, $reqheader=array())
	{
		$this->singlequeue = curl_init($url);
		$options = array(
				CURLOPT_RETURNTRANSFER => true,         // return web page
				CURLOPT_HEADER         => true,        // don't return headers
// 				CURLOPT_FOLLOWLOCATION => true,         // follow redirects
				CURLOPT_ENCODING       => "",           // handle all encodings
				CURLOPT_USERAGENT      => "",     // who am i
				CURLOPT_AUTOREFERER    => true,         // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
				CURLOPT_TIMEOUT        => 120,          // timeout on response
				CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
				CURLOPT_POST            => true,            // i am sending post data
				CURLOPT_POSTFIELDS     => $postfields,    // this are my post vars
				CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
				CURLOPT_SSL_VERIFYPEER => false,        //
		);
		curl_setopt_array($this->singlequeue, $options);
		if($referer){
			curl_setopt($this->singlequeue, CURLOPT_REFERER, $referer);
		}
		elseif ($this->referer){
			curl_setopt($this->singlequeue, CURLOPT_REFERER, $this->referer);
		}
		
		$this->setReqheader($this->singlequeue, $reqheader);
		$this->setReqCookies($this->singlequeue, $reqcookies);

		$result = curl_exec($this->singlequeue);
		$resCookies = $this->getResCookies($this->singlequeue, $result);;
		if (is_array($resCookies)&&!empty($resCookies)) {
			$this->_cookies = array_merge($this->_cookies ,$resCookies);
		}
		$resHeader = $this->getResHeader($this->singlequeue, $result);
		if (is_array($resHeader)&&!empty($resHeader)) {
			$this->_resheader = $resHeader;
		}
		$this->_result = $this->getResBody($this->singlequeue, $result);
		curl_close($this->singlequeue);
		$this->singlequeue = null;
		return $this->_result;
	}

    /**
     * @param unknown $url
     * @param unknown $referer
     * @param null $reqcookies
     * @param array $reqheader
     * @return unknown
     */
	function get($url, $referer=null, $reqcookies=null, $reqheader=array())
	{
		$this->singlequeue = curl_init($url);
		$options = array(
				CURLOPT_RETURNTRANSFER => true,         // return web page
				CURLOPT_HEADER         => true,        // don't return headers
// 				CURLOPT_FOLLOWLOCATION => true,         // follow redirects
				CURLOPT_ENCODING       => "",           // handle all encodings
				CURLOPT_USERAGENT      => "",     // who am i
				CURLOPT_AUTOREFERER    => true,         // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
				CURLOPT_TIMEOUT        => 120,          // timeout on response
				CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
				CURLOPT_POST            => false,            // i am sending post data
				CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
				CURLOPT_SSL_VERIFYPEER => false,        //
				CURLOPT_REFERER        =>$referer,
		);
		curl_setopt_array($this->singlequeue, $options);
		if($referer){
			curl_setopt($this->singlequeue, CURLOPT_REFERER, $referer);
		}
		elseif ($this->referer){
			curl_setopt($this->singlequeue, CURLOPT_REFERER, $this->referer);
		}
		$this->setReqheader($this->singlequeue, $reqheader);
		$this->setReqCookies($this->singlequeue, $reqcookies);

		$result = curl_exec($this->singlequeue);
		$resCookies = $this->getResCookies($this->singlequeue, $result);
		if (is_array($resCookies)&&!empty($resCookies)) {
			$this->_cookies = array_merge($this->_cookies ,$resCookies);
		}
		$resHeader = $this->getResHeader($this->singlequeue, $result);
		if (is_array($resHeader)) {
			$this->_resheader = $resHeader;
		}
		$this->_result = $this->getResBody($this->singlequeue, $result);
		curl_close($this->singlequeue);
		$this->singlequeue = null;
		return $this->_result;
	}
	/**
	 * 并发行的curl方法
	 * @param unknown $requestArray
	 * @param string $callback
	 * @return multitype:multitype:
	 */
	function rollRequest($requestArray, $callback="")
	{
		$this->_requstItems = $requestArray;
		$requestArrayKeys = array_keys($requestArray);
		/* 		$requestArray = array(
		 array(
		 		'url' => "",
		 		'method' => "post",
		 		'postfields' => array(),
		 		'cookies' => "",
		 		'referer' => "",
		 ),
				array(
						'url' => "",
						'postfields' => array(),
						'cookies' => "",
						'referer' => "",
				),
		); */
		$this->rollqueue = curl_multi_init();
		$map = array();
		for ($i=0;$i<$this->limitCount && !empty($requestArrayKeys);$i++)
		{
			$keyvalue = array_shift($requestArrayKeys);
			$this->addToRollQueue( $requestArray, $keyvalue, $map );

		}

		$responses = array();
		do {
			while (($code = curl_multi_exec($this->rollqueue, $active)) == CURLM_CALL_MULTI_PERFORM) ;

			if ($code != CURLM_OK) { break; }

			// 找到刚刚完成的任务句柄
			while ($done = curl_multi_info_read($this->rollqueue)) {
				// 处理当前句柄的信息、错误、和返回内容
				$info = curl_getinfo($done['handle']);
				$error = curl_error($done['handle']);
				if ($this->_callback)
				{
					//调用callback函数处理当前句柄的返回内容，callback函数参数有：（返回内容, 队列id）
					$result = call_user_func($this->_callback, curl_multi_getcontent($done['handle']), $map[(string) $done['handle']]);
				}
				else
				{
					//如果callback为空，直接返回内容
					$result = curl_multi_getcontent($done['handle']);
				}
				if ($this->returninfoswitch) {
					$responses[$map[(string) $done['handle']]] = compact('info', 'error', 'result');
				}
				else
				{
					$responses[$map[(string) $done['handle']]] = $result;
				}

				// 从队列里移除上面完成处理的句柄
				curl_multi_remove_handle($this->rollqueue, $done['handle']);
				curl_close($done['handle']);
				if (!empty($requestArrayKeys))
				{
					$addkey = array_shift($requestArrayKeys);
					$this->addToRollQueue ( $requestArray, $addkey, $map );
				}
			}

			// Block for data in / output; error handling is done by curl_multi_exec
			if ($active > 0) {
				curl_multi_select($this->rollqueue, 0.5);
			}

		} while ($active);

		curl_multi_close($this->rollqueue);
		$this->rollqueue = null;
		return $responses;
	}
	/**
	 * @param requestArray
	 * @param map
	 * @param keyvalue
	 */
	private function addToRollQueue($requestArray, $keyvalue, &$map) {
		$ch = curl_init();
		curl_setopt_array($ch, $this->_rolloptions);
		//检查提交方式，并设置对应的设置，为空的话默认采用get方式
		if ("post" === $requestArray[$keyvalue]['method'])
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $requestArray[$keyvalue]['postfields']);
		}
		else
		{
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}

		
		if($requestArray[$keyvalue]['referer']){
			curl_setopt($ch, CURLOPT_REFERER, $requestArray[$keyvalue]['referer']);
		}
		elseif ($this->referer){
			curl_setopt($ch, CURLOPT_REFERER, $this->referer);
		}
		$this->setReqheader($ch, $requestArray[$keyvalue]['header']);
		//cookies设置
		$this->setReqCookies($ch, $requestArray[$keyvalue]['cookies']);

		curl_setopt($ch, CURLOPT_URL, $requestArray[$keyvalue]['url']);
		curl_setopt($ch, CURLOPT_REFERER, $requestArray[$keyvalue]['referer']);
		curl_multi_add_handle($this->rollqueue, $ch);
		$map[(string) $ch] = $keyvalue;
	}

	/**
	 * 返回当前并行数
	 * @return the $limitCount
	 */
	public function getRollLimitCount() {
		return $this->limitCount;
	}

	/**
	 * 设置并发性请求数量
	 * @param number $limitCount
	 */
	public function setRollLimitCount($limitCount) {
		$this->limitCount = $limitCount;
		return $this;
	}

	/**
	 * 设置回调函数
	 * @param field_type $_callback
	 */
	public function setCallback($_callback) {
		$this->_callback = $_callback;
		return $this;
	}

	public function getResult() {
		return $this->_result;
	}

	public function getRawHeader() {
		return $this->_resheader;
	}

	public function getCookies() {
		return $this->_cookies;
	}

	public function setCookies($_cookies) {
		$this->_cookies = $_cookies;
		return $this;
	}

	/**
 * @param unknown_type $reqheader
 */
public function setHeader($header) {
	$this->_reqheader = array_merge($this->_reqheader, $header);
	return $this;
}
	/**
 * @param unknown_type $reqheader
 */
private function setReqheader($ch, $reqheader) {
	$reqheader = array_merge($this->_reqheader, $reqheader);
	if (is_array($reqheader)) {
		$rawReqHeader = array();
		foreach ($reqheader as $key => $value){
			$rawReqHeader[] = "$key: $value";
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $rawReqHeader);
		$this->_reqheader = array();
	}
	return $this;
}
	



}
