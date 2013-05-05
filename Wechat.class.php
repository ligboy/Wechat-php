<?php
/**
 * 微信公共平台整合库
 * @author Ligboy (ligboy@gmail.com)
 * @license 本库的很多思路来自于网上的其他热心人士的贡献，大家任意使用，我本人放弃所有权利，如果您心情好，给我留个署名也行。
 *
 */
class Wechat {
	/* 配置参数  */
	/**
	 *
	 * @var array
	 * @example array('token'=>'微信接口密钥','account'=>'微信公共平台账号','password'=>'微信公共平台密码','webtoken'=>"微信公共平台网页url的token");
	 */
	private $wechatOptions=array('token'=>'rqerwer','account'=>'ligboy@gmail.com','password'=>'wwwwww');	//
	private $cookiefilepath = ""; //以文件形式保存cookie的保存目录，肯定是可写的
	public $webtoken = '742432903';  
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

	/* 私有参数 */
	private $_msg;
	private $_funcflag = false;
	private $_receive;
	private $_logcallback;
	private $_token;
	private $_cookies;


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
		$this->setCookiefilepath("./app/Runtime", "wechatcookies".md5($this->wechatOptions['account']).".txt");
		if ($this->webtokenStoragefile) {
			$this->webtoken = (string)file_get_contents($this->webtokenStoragefile);
		}
		

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
	 * @param array $msg 消息数组
	 * @param bool $append 是否在原消息数组追加
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
	 * 获取微信服务器发来的信息
	 * @return mixed
	 */
	public function getRev()
	{
		$postStr = file_get_contents("php://input");
		$this->log($postStr);
		if (!empty($postStr)) {
			$this->_receive = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		return $this;
	}

	/**
	 * 获取消息发送者
	 * @return string or boolean
	 */
	public function getRevFrom() {
		if ($this->_receive)
			return $this->_receive['FromUserName'];
		else
			return false;
	}

	/**
	 * 获取消息接受者
	 * @return string or boolean
	 */
	public function getRevTo() {
		if ($this->_receive)
			return $this->_receive['ToUserName'];
		else
			return false;
	}

	/**
	 * 获取接收消息的类型
	 */
	public function getRevType() {
		if (isset($this->_receive['MsgType']))
			return $this->_receive['MsgType'];
		else
			return false;
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
	 * @return 
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

	public static function xmlSafeStr($str)
	{
		return '<![CDATA['.preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/",'',$str).']]>';
	}

	/**
	 * 数据XML编码
	 * @param mixed $data 数据
	 * @return string
	 */
	public static function data_to_xml($data) {
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
	public function xml_encode($data, $root='xml', $item='item', $attr='', $id='id', $encoding='utf-8') {
		if(is_array($attr)){
			$_attr = array();
			foreach ($attr as $key => $value) {
				$_attr[] = "{$key}=\"{$value}\"";
			}
			$attr = implode(' ', $_attr);
		}
		$attr   = trim($attr);
		$attr   = empty($attr) ? '' : " {$attr}";
		$xml   .= "<{$root}{$attr}>";
		$xml   .= self::data_to_xml($data, $item, $id);
		$xml   .= "</{$root}>";
		return $xml;
	}

	/**
	 * 设置回复消息
	 * Examle: $obj->text('hello')->reply();
	 * @param string $text
	 */
	public function text($text='')
	{
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
	 * @param string $msg 要发送的信息, 默认取$this->_msg
	 * @param bool $return 是否返回信息而输出  默认：false
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
	 * @return mixed 成功则返回true，失败则返回失败
	 */
	public function login(){
		$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/login?lang=zh_CN";
		$postfields["username"] = $this->wechatOptions['account'];
		$postfields["pwd"] = md5($this->wechatOptions['password']);
		$postfields["f"] = "json";
		$postfieldss = "username=".urlencode($this->wechatOptions['account'])."&pwd=".urlencode(md5($this->wechatOptions['password']))."&f=json";
		$response = $this->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/cgi-bin/login?lang=zh_CN");
		$result = json_decode($response, true);
		if ($result['ErrCode']=="65201"||$result['ErrCode']=="65202"||$result['ErrCode']=="0")
		{
			preg_match('/&token=([\d]+)/i', $result['ErrMsg'],$match);
			file_put_contents($this->webtokenStoragefile, $match[1]);
			$this->webtoken = $match[1];
			return true;
		}
		else
		{
			unlink($this->cookiefilepath);
			return false;
// 			return $result['ErrCode'];
		}
	}

	/**
	 * 读取缓存的cookies文件
	 * @param  string $filename 文件名
	 * @param  string $content  文件内容
	 * @return [type]           [description]
	 */
	public function readFileCookies(){

		if(file_exists($this->cookiefilepath)){
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getregions?id=1054&t=ajax-getregions&lang=zh_CN&token=".$this->webtoken;
			$response = $this->get($url, $this->protocol."://mp.weixin.qq.com/");
			$result = json_decode($response,true);
			if($result['num'])
			{
				return true;
			}
			else
			{
				return true===$this->login();
			}
		}
		else
		{
			return true===$this->login();
		}
	}

	/**
	 * 验证cookie的有效性
	 * @return boolean
	 */
	public function checkValid()
	{
		$postfields = array();
		$url = $this->protocol.":https://mp.weixin.qq.com/cgi-bin/getregions?id=1054&t=ajax-getregions&lang=zh_CN&token=".$this->webtoken;
		//判断cookie是否为空，为空的话自动执行登录
		if (file_exists($this->cookiefilepath))
		{
			$response = $this->get($url, $this->protocol."://mp.weixin.qq.com/cgi-bin/userinfopage?t=wxm-setting&token=383506232&lang=zh_CN");
			$result = json_decode($response,1);
			if(isset($result['num'])){
				return true;
			}else{
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
	 * @return boolean
	 */
	public function keepLive()
	{
		if (!$this->checkValid()) {
			return (true===$this->login());
		}
		return false;
	}

	/**
	 * 主动单条发消息
	 * @param  string $id      用户的fakeid
	 * @param  string $content 发送的内容
	 * @return integer 返回发送结果：成功返回:1,登录问题返回:-1,其他原因返回:0
	 */
	public function send($fakeid,$content)
	{
		//判断cookie是否为空，为空的话自动执行登录
		if (file_exists($this->cookiefilepath)||true===$this->login())
		{
			$postfields = array();
			$postfields['tofakeid'] = $fakeid;
			$postfields['type'] = 1;
			$postfields['error']= "false";
			$postfields['token']= $this->webtoken;
			$postfields['content'] = $content;
			$postfields['ajax'] = 1;
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
			$response = $this->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/");
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
	 * 主动相同消息群发，目前暂支持文本方式
	 * @param  array $fakeidGroup     接受微信fakeid集合数组
	 * @param  string $content 群发消息内容
	 * @return mixed  如果所有都发送失败，返回false，否则，返回一个数组分别记录成功的列表
	 * 这里需要注意请求耗时问题，目前采用curl并发性请求.
	 */
	public function batSend($fakeidGroup,$content)
	{
		$queueSendArray = array();
		foreach ($fakeidGroup as $key =>$value)
		{
			$queueSendArray[] = array(
					'fakeid' => $value,
					'content' => $content
			);
		}
		return $this->doQueueSend($queueSendArray);

	}
	/**
	 * 执行主动发送队列，默认并发队列数是10
	 * @param array 发送队列数组  array(array('fakeid'='','content'))
	 * @return array
	 **/
	 public function doQueueSend($queueSendArray, $Count)
	 {
		$requestArray = array();
		foreach ($queueSendArray as $key =>$value)
		{
			$postfields = array();
			$postfields['tofakeid'] = $value['fakeid'];
			$postfields['type'] = 1;
			$postfields['error']= "false";
			$postfields['token']= $this->webtoken;
			$postfields['content'] = $value['content'];
			$postfields['ajax'] = 1;
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
			$requestArray[] = array('url'=>$url,'method'=>'post','postfields'=>$postfields,'referer'=>$this->protocol."://mp.weixin.qq.com/",'cookiefilepath'=>$this->cookiefilepath);
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
		$rollingCurlObj = new Rollingcurl();
		$response = $rollingCurlObj->setCallback("callback")->request($requestArray);
		return $response;
	}


	/**
	 * 获取用户的信息
	 * @param  string $fakeid 用户的fakeid
	 * @return mixed 如果成功获取返回数据数组，登录问题返回false，其他未知问题返回true，
	 */
	public function getContactInfo($fakeid)
	{
		if (file_exists($this->cookiefilepath)||true===$this->login())
		{
			$url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getcontactinfo?t=ajax-getcontactinfo&lang=zh_CN&fakeid=".$fakeid;
			$response = $this->get($url, $this->protocol."://mp.weixin.qq.com/");
			$result = json_decode($response,1);
			if($result['FakeId']){
				return $result;
			}
			elseif ($result['ret'])
			{
				return false;
			}
			else
			{
				return true;
			}
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
	 * @return the $cookiestoragemode
	 */
	public final function getCookiestoragemode() {
		return $this->cookiestoragemode;
	}

	/**
	 * @return the $cookiefilepath
	 */
	public final function getCookiefilepath() {
		return $this->cookiefilepath;
	}

	/**
	 * @return the $debug
	 */
	public function getDebug() {
		return $this->debug;
	}

	/**
	 * 设置webtoken的保存文件路径和文件名
	 * @return the $webtokenStoragefile
	 */
	public function getWebtokenStoragefile() {
		return $this->webtokenStoragefile;
	}

	/**
	 *  设置webtoken的保存文件路径和文件名
	 * @param string $webtokenStoragefile
	 */
	public function setWebtokenStoragefile($webtokenStoragefile) {
		$this->webtokenStoragefile = $webtokenStoragefile;
		$this->webtoken = (string)file_get_contents($this->webtokenStoragefile);
	}

	/**
	 * 设置微信配置信息
	 * @param multitype:string  $wechatOptions
	 */
	public function setWechatOptions($wechatOptions) {
		$this->wechatOptions = array_merge($this->wechatOptions, $wechatOptions);
	}

	/**
	 * 设置cookie文件保存位置
	 * @param string $cookiefilepath cookie保存路径
	 * @param string $cookiefilename 默认:wechatcookies".md5($this->wechatOptions['account']).".txt"
	 */
	public function setCookiefilepath($cookiefilepath, $cookiefilename = "") {
		$this->cookiefilepath = $cookiefilepath.(substr($cookiefilepath, -1, 1)=="/"?"":"/").(is_null($cookiefilename))?"wechatcookies".md5($this->wechatOptions['account']).".txt":$cookiefilename;
	}

	/**
	 * @param boolean $debug
	 */
	public function setDebug($debug) {
		$this->debug = $debug;
	}

	private function post($url, $postfields, $refer)
	{
		$ch = curl_init($url);
		$options = array(
			CURLOPT_RETURNTRANSFER => true,         // return web page
			CURLOPT_HEADER         => false,        // don't return headers
			CURLOPT_FOLLOWLOCATION => true,         // follow redirects
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
			CURLOPT_COOKIEFILE     =>$this->cookiefilepath,
			CURLOPT_COOKIEJAR      =>$this->cookiefilepath,
			CURLOPT_REFERER        =>$refer,
		);
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	private function get($url, $refer)
	{
		$info = null;
		$ch = curl_init($url);
		$options = array(
				CURLOPT_RETURNTRANSFER => true,         // return web page
				CURLOPT_HEADER         => false,        // don't return headers
				CURLOPT_FOLLOWLOCATION => true,         // follow redirects
				CURLOPT_ENCODING       => "",           // handle all encodings
				CURLOPT_USERAGENT      => "",     // who am i
				CURLOPT_AUTOREFERER    => true,         // set referer on redirect
				CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
				CURLOPT_TIMEOUT        => 120,          // timeout on response
				CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
				CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
				CURLOPT_SSL_VERIFYPEER => false,        //
				CURLOPT_COOKIEFILE     =>$this->cookiefilepath,
				CURLOPT_COOKIEJAR      =>$this->cookiefilepath,
				CURLOPT_REFERER        =>$refer,
		);
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
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
class Rollingcurl {
	private $limitCount = 10; //并发请求数量
	public $returninfoswitch = false;  //是否返回请求信息，开启后单项请求返回结果为:array('info'=>请求信息, 'result'=>返回内容, 'error'=>错误信息)
	
	//私有属性
	private $queue = null;
	private $_requstItems = null;
	private $_callback = null;
	private $_options = array(
					CURLOPT_RETURNTRANSFER => true,         // return web page
					CURLOPT_HEADER         => false,        // don't return headers
					CURLOPT_FOLLOWLOCATION => true,         // follow redirects
					CURLOPT_NOSIGNAL      =>true,
					CURLOPT_TIMEOUT      =>true,
					CURLOPT_ENCODING       => "",           // handle all encodings
					CURLOPT_USERAGENT      => "",           // who am i
					CURLOPT_AUTOREFERER    => true,         // set referer on redirect
					CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
					CURLOPT_TIMEOUT        => 120,          // timeout on response
					CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
					CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
					CURLOPT_SSL_VERIFYPEER => false,        //
			);
	
	
	/**
	 * 
	 * @param array $options [可选的]公共的Curl请求参数
	 */
	function __construct($options = array()) {
		$this->queue = curl_multi_init();
		if ($options) {
			array_merge($this->_options, $options);
		}
	}
	

	/**
	 * 并发行的curl方法
	 * @param unknown $requestArray
	 * @param string $callback
	 * @return multitype:multitype:
	 */
	function request($requestArray, $callback="")
	{
		$this->_requstItems = $requestArray;
		$requestArrayKeys = array_keys($requestArray);
/* 		$requestArray = array(
				array(
						'url' => "",
						'method' => "post",
						'postfields' => array(),
						'cookiefilepath' => "",
						'cookiefilepath' => "",
						'referer' => "",
				),
				array(
						'url' => "",
						'postfields' => array(),
						'cookiefilepath' => "",
						'cookiefilepath' => "",
						'referer' => "",
				),
		); */
		$this->queue = curl_multi_init();
		$map = array();
		for ($i=0;$i<$this->limitCount && !empty($requestArrayKeys);$i++)
		{
			$keyvalue = array_shift($requestArrayKeys);
			$this->addToQueue ( $requestArray, $keyvalue, $map );

		}
	
		$responses = array();
		do {
			while (($code = curl_multi_exec($this->queue, $active)) == CURLM_CALL_MULTI_PERFORM) ;
	
			if ($code != CURLM_OK) { break; }
	
			// 找到刚刚完成的任务句柄
			while ($done = curl_multi_info_read($this->queue)) {
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
				curl_multi_remove_handle($this->queue, $done['handle']);
				curl_close($done['handle']);
				if (!empty($requestArrayKeys))
				{
					$addkey = array_shift($requestArrayKeys);
					$this->addToQueue ( $requestArray, $addkey, $map );
				}
			}
	
			// Block for data in / output; error handling is done by curl_multi_exec
			if ($active > 0) {
				curl_multi_select($this->queue, 0.5);
			}
	
		} while ($active);
	
		curl_multi_close($this->queue);
		return $responses;
	}
	/**
	 * @param requestArray
	 * @param map
	 * @param keyvalue
	 */private function addToQueue($requestArray, $keyvalue, &$map) {
		$ch = curl_init();
		curl_setopt_array($ch, $this->_options);
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

		//设置cookie保存文件路径
		if (!empty($requestArray[$keyvalue]['cookiefilepath']))
		{
			//如果这个文件存在，则采用采用此文件配置cookie
			if (file_exists($requestArray[$keyvalue]['cookiefilepath']))
			{
				curl_setopt($ch, CURLOPT_COOKIEFILE, $requestArray[$keyvalue]['cookiefilepath']);
			}
			curl_setopt($ch, CURLOPT_COOKIEJAR, $requestArray[$keyvalue]['cookiefilepath']);
		}
		////直接设定cookie。多个cookie用分号分隔，分号后带一个空格(例如， "username=ligboy; password=123456; ")。
		if (!empty($requestArray[$keyvalue]['cookie']))
		{
			curl_setopt($ch, CURLOPT_COOKIE, $requestArray[$keyvalue]['cookie']);
		}
		curl_setopt($ch, CURLOPT_URL, $requestArray[$keyvalue]['url']);
		curl_setopt($ch, CURLOPT_REFERER, $requestArray[$keyvalue]['referer']);
		curl_multi_add_handle($this->queue, $ch);
		$map[(string) $ch] = $keyvalue;
	}

	/**
	 * 返回当前并行数
	 * @return the $limitCount
	 */
	public function getLimitCount() {
		return $this->limitCount;
	}

	/**
	 * 设置并发性请求数量
	 * @param number $limitCount
	 */
	public function setLimitCount($limitCount) {
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

	
}

