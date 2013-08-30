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
    private $wechatOptions=array('token'=>'rqerwer','account'=>'ligboy@gmail.com','password'=>'wwwwww','session'=>"default");	//
    public $webtoken = '';
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
    const MSGTYPE_GOODS = 'goods';
    const MSGTYPE_CARD = 'card';

    public static $POSITIVE_MSGTYPE = array('text'=>1, 'image'=>2, 'voice'=>3, 'video'=>4, 'news'=>10,'goods'=>11, 'card'=>42);  //主动消息类型代码数组
    /* 私有参数 */
    private $_msg;
    private $_funcflag = false;
    public $_receive;
    private $_logcallback;
    private $_getRevRunOnce = 0;
    private $_cookies;
    private $_wechatcallbackFuns = null;
    private $_curlHttpObject = null;
    private $_referer = "https://mp.weixin.qq.com/";
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
     * @param string $session 登录会话
     * @return Wechat
     */
    function positiveInit($session=null)
    {
        $this->processSession($session);
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
    }


    /**
     * 设置发送消息
     * @param array|string $msg 消息数组
     * @param bool $append 是否在原消息数组追加
     * @return array|null
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
        return null;
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
        return null;
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
    public function login($session=null)
    {
        $this->processSession($session);
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
            $messageList = $this->getMessage(0, 40, 0);
            if ($messageList)
            {
                $count = 0;
                $fakeid = "";
                foreach ($messageList as $value)
                {
                    if ($value['date_time']==$this->getRevCtime())
                    {
                        $count += 1;
                        $fakeid = $value['fakeid'];
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
    public function checkValid($session=null)
    {
        $this->processSession($session);
        $postfields = array();
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getregions?id=1054&t=ajax-getregions&lang=zh_CN&token=".$this->webtoken;
        //判断cookie是否为空，为空的话自动执行登录
        if ($this->_cookies[$session]||($this->_cookies[$session] = $this->getCookies($session)))
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
    public function keepLive($session=null)
    {
        $this->processSession($session);
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
     * @param string $type
     * @param string $imgcode 验证码
     * @param string $session 会话通道
     * @return integer 返回发送结果：成功返回:1,登录问题返回:-1,;需要验证码:-6; 其他原因返回:0
     */
    public function send($fakeid, $content, $type=Wechat::MSGTYPE_TEXT, $imgcode="fuck", $session=null)
    {
        $this->processSession($session);
        return $this->_send($fakeid, $content, $type, $imgcode, $session);
    }

    /**
     * 主动单条发送媒体消息
     * @param $fakeid
     * @param  string $fid 发送的内容
     * @param $type 发送消息类型
     * @param string $imgcode 验证码
     * @param string $session 会话通道
     * @return integer 返回发送结果：成功返回:1,登录问题返回:-1,;需要验证码:-6; 其他原因返回:0
     */
    public function sendMedia($fakeid, $fid, $type, $imgcode="fuck", $session=null)
    {
        $this->processSession($session);
        return $this->_send($fakeid, $fid, $type, $imgcode, $session);
    }

    //TODO Working...... 待解决图文消息添加后获取fid问题。
    /**
     * 通过微信号直接发送图文
     * @param $wechatno 微信号
     * @param $newsArray 消息数组，格式:<p>array(
     * array('title'=>'','digest'=>'','author'=>'','image'=>'','content'=>'','sourceurl'=>''),
     * array('title'=>'','digest'=>'','author'=>'','image'=>'','content'=>'','sourceurl'=>''),
     * )</p>
     * @param string $session 会话通道
     * @return bool
     */
    public function sendPreNews($wechatno, $newsArray, $session=null)
    {
        $this->processSession($session);
        $postfields = array();
        $newsArray = array_values($newsArray);
        if(count($newsArray) < 1)
        {
            return false;
        }
        $i = 0; //完备消息数量
        foreach($newsArray as $value)
        {
            if(preg_match('/^[0-9]{8,9}$/', $value['image']))
            {
                $postfields['fileid'.$i] = $value['image'];
            }
            elseif($fid = $this->mediaUpload($value['image'], Wechat::MSGTYPE_IMAGE,$session))
            {
                $postfields['fileid'.$i] = $fid;
            }
            else
            {
                continue;
            }
            $postfields['title'.$i] = $value['title'];
            $postfields['digest'.$i] = $value['desc']?$value['desc']:"";
            $postfields['author'.$i] = $value['author']?$value['author']:"";
            $postfields['content'.$i] = $value['content'];
            $postfields['sourceurl'.$i] = $value['sourceurl']?$value['sourceurl']:"";
            $i += 1;
        }
        if($i==0)
        {
            return false;
        }
        $postfields['count'] = $i;
        $postfields['error'] = 'false';
        $postfields['AppMsgId'] = "";
        $postfields['token'] = $this->webtoken;
        $postfields['ajax'] = 1;
        $postfields['preusername'] = $wechatno;
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/operate_appmsg?sub=preview&t=ajax-appmsg-preview";
        $this->curlInit("single");
        $result = $this->_curlHttpObject->post($url, $postfields, $this->_referer, $this->getCookies($session));
        $result_json_decode = json_decode($result, true);
        if($result_json_decode && 'OK'==$result_json_decode['appMsgId'])
        {
            return $result_json_decode['appMsgId'];
        }
        else
        {
            return false;
        }
    }
    /**
     * 主动单条发消息
     * @param $fakeid 消息接收人
     * @param  string $content 发送的内容或多媒体内容的fid
     * @param null $type 消息类型 默认：Wechat::MSGTYPE_TEXT
     * @param string $imgcode 验证码
     * @param string $session 会话通道
     * @return integer 返回发送结果：成功返回:1,登录问题返回:-1;需要验证码:-6;其他
     */
    private function _send($fakeid, $content, $type=null, $imgcode="fuck", $session=null)
    {
        $this->processSession($session);
        if($type==null)
        {
            $type = Wechat::MSGTYPE_TEXT;
        }
        //判断cookie是否为空，为空的话自动执行登录
        if ($this->_cookies[$session]||true===$this->login($session))
        {
            $singleMessgae = array();
            $singleMessgae['fakeid'] = $fakeid;
            $singleMessgae['content'] = $content;
            $singleMessgae['imgcode'] = $imgcode;
            $singleMessgae['fid'] = $content;
            $singleMessgae['type'] = $type;
            $postfields = $this->buildPositiveMsgFields($singleMessgae);
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
//            $url = "http://api.fzuer.com/weixin/fzuer/index.php?m=Request&a=index";
            $this->curlInit("single");
            $response = $this->_curlHttpObject->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/cgi-bin/singlemsgpage?", $this->_cookies[$session]);
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
                return $tmp['ret'];
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
     * @param null $type x
     * @param string $session
     * @return mixed  返回一个记录发送结果的数组列表
     * 这里需要注意请求耗时问题，目前采用curl并发性请求.
     */
    public function batSend($fakeidGroup,$content, $type=null, $session=null)
    {
        $this->processSession($session);
        if(NULL==$type)
        {
            $type = Wechat::MSGTYPE_TEXT;
        }
        $queueSendArray = array();
        foreach ($fakeidGroup as $key =>$value)
        {

            $queueSendArray[$key] = array(
                'fakeid' => $value,
                'content' => $content,
                'type'    => $type?$type:Wechat::MSGTYPE_TEXT,
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
            $postfields = $this->buildPositiveMsgFields($value);

            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
            $requestArray[$key] = array('url'=>$url,'method'=>'post','postfields'=>$postfields,'referer'=>$this->protocol."://mp.weixin.qq.com/cgi-bin/singlemsgpage?",'cookies'=>$this->_cookies[($value['session']?$value['session']:(empty($this->wechatOptions['session'])?"default":$this->wechatOptions['session']))]);
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
                return $tmp['ret'];
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
    public function getContactInfo($fakeid, $session=null)
    {
        $this->processSession($session);
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getcontactinfo?t=ajax-getcontactinfo&lang=zh_CN&fakeid=".$fakeid;
        $this->curlInit("single");
        $postfields = array("token"=>$this->webtoken, "ajax"=>1);
        $response = $this->_curlHttpObject->post($url, $postfields, $this->protocol."://mp.weixin.qq.com/", $this->_cookies[$session]);
        $result = json_decode($response, 1);
        if($result['FakeId']){
            unset($result['Groups']);
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
     * 获取下载用户的头像
     * @param  string $fakeid 用户的fakeid
     * @param null $filepath
     * @param string $session
     * @return mixed 如果成功获取返回头像文件保存信息，登录问题返回false
     */
    public function getUserPhoto($fakeid, $filepath=null, $session=null)
    {
        $this->processSession($session);
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/getheadimg?token=".$this->webtoken."&fakeid=$fakeid";
        $ch = curl_init();
        $tmpfile = $filepath?$filepath:sys_get_temp_dir().'WechatPhotoFileTemp'.$fakeid."jpg";
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
                CURLOPT_FILE => $fp, //目标文件保存路径
                CURLOPT_RETURNTRANSFER => 1
            );
            curl_setopt_array($ch, $options);
            $reqCookiesString = "";
            if(is_array($this->_cookies[$session]))
            {
                foreach ($this->_cookies[$session] as $key => $val)
                {
                    $reqCookiesString .=  $key."=".$val."; ";
                }
                curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
            }
            $content = curl_exec($ch);
            $info = (curl_getinfo($ch));
            curl_close($ch);
//            fwrite($fp, $content);
            fclose($fp);
            $result = array();
            if ($content && file_exists($tmpfile) && filesize($tmpfile)>0 && $info["content_type"]=="image/jpeg") {
                $result["filename"] = $tmpfile;
                $result["filesize"] = filesize($tmpfile);
                $result['filetype'] = $info["content_type"];
                return $result;
            }
        }
        return false;
    }

    /**
     * 修改文件媒体消息名称或删除文件媒体消息
     * @param $fid 文件id编号
     * @param string $action  执行动作，default:del 删除, other: rename
     * @param null $filename 修改后的文件名称(删除不需要)
     * @param string $session 会话通道
     * @return bool
     */
    public function mediaModify($fid, $action="del", $filename=null, $session=null)
    {
        $this->processSession($session);
        $postfields = array();
        $postfields['ajax'] = 1;
        $postfields['fileid'] = $fid;
        $postfields['token'] = $this->webtoken;
        if('rename' == $action && $filename!=null)
        {
            $postfields['fileName'] = $filename;
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/modifyfile?oper=rename&lang=zh_CN&t=ajax-response";
        }
        elseif($action=='del')
        {
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/modifyfile?oper=del&lang=zh_CN&t=ajax-response";
        }
        else
        {
            return false;
        }

        $this->curlInit("single");
        $result = $this->_curlHttpObject->post($url, $postfields, $this->_referer, $this->getCookies($session));
        $result_json_decode = json_decode($result, true);

        if('ok'==$result_json_decode['msg'])
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 上传图片到微信的图片CDN上（注意不是图片消息的上传）
     * @param $filepath 媒体文件路径
     * @param string $session 会话通道，默认为: default
     * @return bool|string  成功返回媒体fid，失败返回false
     */
    public function mediaUploadImg($filepath, $session=null)
    {
        if(file_exists($filepath))
        {
            $this->processSession($session);
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/uploadimg2cdn?t=ajax-editor-upload-img&lang=zh_CN&token=$this->webtoken";
    //        $url = "http://api.fzuer.com/weixin/fzuer/index.php?m=Request&a=index";
            $contentTypeList = array('jpg'=>'image/jpeg', 'png'=>'image/png', 'bmp'=>'image/bmp', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif', 'mp3'=>'audio/mpeg3', 'wma'=>'audio/x-ms-wma', 'wav'=>'audio/wav', 'amr'=>'audio/amr', 'rm'=>'application/vnd.rn-realmedia', 'rmvb'=>'application/vnd.rn-realmedia-vbr', 'wmv'=>'video/x-ms-wmv', 'avi'=>'video/avi', 'mpg'=>'video/mpeg', 'mpeg'=>'video/mpeg', 'mp4'=>'video/mpeg4' );
            $fileSuffix = substr($filepath, strrpos($filepath, ".")+1);
            $uploadContentType = $contentTypeList[$fileSuffix];
            $postfields = array("pictitle"=>"", "upfile"=>"@".$filepath.";type=$uploadContentType");
            $ch = curl_init();
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
                CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
                CURLOPT_SSL_VERIFYPEER => false,        //
            );
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_REFERER, $this->_referer);
            $reqCookiesString = "";
            if(is_array($this->getCookies($session)))
            {
                foreach ($this->getCookies($session) as $key => $val)
                {
                    $reqCookiesString .=  $key."=".$val."; ";
                }
                curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
            }
            curl_setopt_array($ch, $options);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            $result = curl_exec($ch);
            $result_json_decode = json_decode($result, true);
    //        var_dump($result);
            if("SUCCESS"==$result_json_decode['state'])
            {
                return $result_json_decode['url'];
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
     *  上传声音、图片、视频媒体消息
     * @param $filepath 媒体文件路径
     * @param $type 上传媒体类型
     * @param string $session 会话通道，默认为: default
     * @return bool|string  成功返回媒体fid，失败返回false
     */
    public function mediaUpload($filepath, $type, $session=null)
    {
        if(file_exists($filepath))
        {
            $this->processSession($session);
            $positiveType = $this->getPositiveMsgType($type);
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/uploadmaterial?cgi=uploadmaterial&type=$positiveType&token=$this->webtoken&t=iframe-uploadfile&lang=zh_CN&formId=file_from_".time();
            //        $url = "http://api.fzuer.com/weixin/fzuer/index.php?m=Request&a=index";
            $contentTypeList = array('jpg'=>'image/jpeg', 'png'=>'image/png', 'bmp'=>'image/bmp', 'jpeg'=>'image/jpeg', 'gif'=>'image/gif', 'mp3'=>'audio/mpeg3', 'wma'=>'audio/x-ms-wma', 'wav'=>'audio/wav', 'amr'=>'audio/amr', 'rm'=>'application/vnd.rn-realmedia', 'rmvb'=>'application/vnd.rn-realmedia-vbr', 'wmv'=>'video/x-ms-wmv', 'avi'=>'video/avi', 'mpg'=>'video/mpeg', 'mpeg'=>'video/mpeg', 'mp4'=>'video/mpeg4' );
            $fileSuffix = substr($filepath, strrpos($filepath, ".")+1);
            $uploadContentType = $contentTypeList[$fileSuffix];
            $postfields = array("uploadfile"=>"@".$filepath.";type=$uploadContentType");
            $ch = curl_init();
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
                CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
                CURLOPT_SSL_VERIFYPEER => false,        //
            );
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_REFERER, $this->_referer);
            $reqCookiesString = "";
            if(is_array($this->getCookies($session)))
            {
                foreach ($this->getCookies($session) as $key => $val)
                {
                    $reqCookiesString .=  $key."=".$val."; ";
                }
                curl_setopt($ch, CURLOPT_COOKIE, $reqCookiesString);
            }
            curl_setopt_array($ch, $options);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            $result = curl_exec($ch);
            //        var_dump($result);
            if(preg_match('%formId,[\s]{0,4}\'([0-9]*?)\'\\)%', $result,$resultMatch))
            {
                return $resultMatch[1];
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
     * 添加图文媒体消息到媒体库
     * @param $newsArray 消息数组，格式:<p>array(
     * array('title'=>'','desc'=>'','author'=>'','image'=>'','content'=>'','sourceurl'=>''),
     * array('title'=>'','desc'=>'','author'=>'','image'=>'','content'=>'','sourceurl'=>''),
     * )</p>
     * @param int $descLength 摘要长度,default:60
     * @param string $session 会话通道
     * @return bool|string 成功添加返回媒体库消息fid
     */
    public function mediaAddNews($newsArray, $descLength=60, $session=null)
    {
        $this->processSession($session);
        $postfields = array();
        $identifyCode = '';
        $newsArray = array_values($newsArray);
        if(count($newsArray) < 1)
        {
            return false;
        }
        $appMsgCount = 0; //完备消息数量
        $identifyCode = substr(md5(microtime(true)),0,5);
        foreach($newsArray as $value)
        {
            if(preg_match('/^[0-9]{8,9}$/', $value['image']))
            {
                $postfields['fileid'.$appMsgCount] = $value['image'];
            }
            elseif($fid = $this->mediaUpload($value['image'], Wechat::MSGTYPE_IMAGE,$session))
            {
                $postfields['fileid'.$appMsgCount] = $fid;
            }
            else
            {
                continue;
            }
            $postfields['title'.$appMsgCount] = $value['title'];
            $postfields['digest'.$appMsgCount] = ($value['desc']?($descLength>0?mb_substr(strip_tags($value['desc']),0,$descLength, 'UTF-8'):strip_tags($value['desc'])):($descLength>0?(mb_substr(strip_tags($value['content']),0,$descLength, 'UTF-8')):strip_tags($value['content'])))."  $identifyCode";
            $postfields['author'.$appMsgCount] = $value['author']?$value['author']:"";
            $postfields['content'.$appMsgCount] = $value['content'];
            $postfields['sourceurl'.$appMsgCount] = $value['sourceurl']?$value['sourceurl']:"";
            $appMsgCount += 1;
        }
        if($appMsgCount==0)
        {
            return false;
        }
        $postfields['count'] = $appMsgCount;
        $postfields['error'] = 'false';
        $postfields['AppMsgId'] = "";
        $postfields['token'] = $this->webtoken;
        $postfields['ajax'] = 1;
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/operate_appmsg?token=$this->webtoken&lang=zh_CN&t=ajax-response&sub=create";
        $this->curlInit("single");
        $result = $this->_curlHttpObject->post($url, $postfields, 'https://mp.weixin.qq.com/cgi-bin/operate_appmsg?', $this->getCookies($session));
        $result_json_decode = json_decode($result, true);
        if($result_json_decode && 'OK'==$result_json_decode['msg'])
        {
            $mediaListArray = $this->mediaList(Wechat::MSGTYPE_NEWS,0,10);
            if($mediaListArray)
            {
                foreach($mediaListArray['List'] as $value)
                {
                    if($postfields['count'] == $value['count'] && $value['time'] == date('Y-m-d') && strpos($value['appmsgList'][0]['desc'], $identifyCode))
                    {
                        return $value['appId'];
                    }
                }
            }
            return -1;
        }
        else
        {
            return false;
        }
    }

    /**
     * 删除图文媒体消息
     * @param $AppMsgId 图文媒体消息id
     * @param null $session 会话通道
     * @return bool 删除结果
     */
    public function mediaDelNews($AppMsgId, $session=null)
    {
        $this->processSession($session);
        $url = "https://mp.weixin.qq.com/cgi-bin/operate_appmsg?sub=del&t=ajax-response";
        $postfields = array('token'=>$this->webtoken, 'ajax'=>1, 'AppMsgId'=>$AppMsgId);
        $this->curlInit("single");
        $result = $this->_curlHttpObject->post($url, $postfields, $this->_referer, $this->getCookies($session));
        $resutl_json_decode = json_decode($result, true);
        if($resutl_json_decode && $resutl_json_decode['ret']==0)
            return true;
        else
            return false;
    }
    /**
     * 获取图片、声音或视频的媒体消息列表
     * @param string $type 媒体类型(必选) Wechat::MSGTYPE_*
     * @param int $pageIndex 媒体库分页页数(可选)
     * @param int $pageSize 分页大小，default:40 (可选)
     * @param string $session 会话通道，default:"default" (可选)
     * @return bool|array
     */
    public function mediaList($type, $pageIndex=0, $pageSize=40, $session=null)
    {
        $this->processSession($session);
        if(in_array($type,array(Wechat::MSGTYPE_IMAGE, Wechat::MSGTYPE_VOICE, Wechat::MSGTYPE_VIDEO, Wechat::MSGTYPE_NEWS)))
        {
            if($type==Wechat::MSGTYPE_NEWS)
            {
                $url = "https://mp.weixin.qq.com/cgi-bin/operate_appmsg?token=$this->webtoken&lang=zh_CN&sub=list&t=ajax-appmsgs-fileselect&type=".$this->getPositiveMsgType($type)."&r=".mt_rand(0,10000)."&pageIdx=$pageIndex&pagesize=$pageSize&formid=file_from_".time()."&subtype=";
            }
            else
            {
                $url = "https://mp.weixin.qq.com/cgi-bin/filemanagepage?token=$this->webtoken&lang=zh_CN&t=ajax-fileselect&type=".$this->getPositiveMsgType($type)."&r=".mt_rand(0,10000)."&pageIdx=$pageIndex&pagesize=$pageSize&formid=file_from_".time();
            }
            $postfields = array();
            $postfields['ajax'] = 1;
            $postfields['token'] = $this->webtoken;
            $this->curlInit("single");
            $result = $this->_curlHttpObject->post($url, $postfields, $this->_referer, $this->getCookies($session));
            $result_json_decode = json_decode($result, true);
            if(!empty($result_json_decode['type']))
            {
                $result_json_decode['List'] = (array)$result_json_decode['List'];
//                if(Wechat::MSGTYPE_NEWS==$type)
//                {
//                    for($i=0;$i<count($result_json_decode['List']);$i++)
//                    {
//                        $result_json_decode['List'][$i]['appmsgList'] = (array)$result_json_decode['List'][$i]['appmsgList'];
//                    }
//                }
                unset($result_json_decode['PageMsg']['passPage']);
                unset($result_json_decode['PageMsg']['nextPage']);
                unset($result_json_decode['PageMsg']['pageJump']);
                return $result_json_decode;
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
     * 获取消息所附文件
     * @param  string $msgid 消息的id
     * @param null $filepath
     * @param string $session
     * @return array 如果成功获取返回下载的文件的基本信息
     */
    public function getDownloadFile($msgid, $filepath = null, $session=null)
    {
        $this->processSession($session);
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
    public function getVerifyCode($session=null) //TODO ^^
    {
        $this->processSession($session);
        $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/verifycode";
        $this->curlInit("single");
        return $this->_curlHttpObject->get($url,$url,$this->getCookies($session));
    }
    /**
     * @name 获取公共消息列表（html）
     * @param int|number $day 0,1,2,3,7
     * @param int|number $count 数量限制
     * @param int|number $offset 分页偏移
     * @param string $session
     * @return array|boolean 成功获取消息返回消息列表
     */
    public function getMessage($day=0, $count=100, $offset=0, $session=null)
    {
        $this->processSession($session);
        if ($this->_cookies[$session]||true===$this->login($session))
        {
            $url = $this->protocol."://mp.weixin.qq.com/cgi-bin/message?t=message/list&&count=".$count."&day=".$day."&offset=".$offset."&token=".$this->webtoken."&lang=zh_CN&r=".mt_rand(0,999999);
            $this->curlInit("single");
            $result = $this->_curlHttpObject->get($url, $this->protocol."://mp.weixin.qq.com/cgi-bin/message?t=message/list", $this->_cookies[$session]);
            if ($match = Wechat::getTextArea($result,'list : (', ').msg_item' )) {
                $tmp = json_decode($match, true);
                if($match && $tmp)
                {
                    return $tmp['msg_item'];
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
        else
        {
            return false;
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
    public function getSingleMessage($fakeid, $lastmsgid=1, $createtime=0, $lastmsgfromfakeid=null, $session=null)
    {
        $this->processSession($session);
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
     * @name 获取公共消息时间线列表(遗留下来兼容)
     * @param int|number $day 获取几日内的消息参数（0:当天;1:昨天;2:前天;3:最近5天.默认0）
     * @param int|number $count 获取消息数量限制.默认100
     * @param int|number $offset 获取消息开始位置,差不多是偏移分页的样子.默认是0
     * @param int|number $msgid 最后消息的id 默认为9999999999(意味着全部消息的意思)
     * @param bool|int $timeline 这个参数决定了上面的$day是否有效，设置成false,直接按时间线排列的全部消息
     * @param string $session
     * @return mixed|boolean
     */
    public function getMessageAjax($day=0, $count=100, $offset=1, $msgid=999999999, $timeline=1, $session=null)
    {
        return $this->getMessage($day, $count, $offset, $session);
    }

    /**
     * @name 得到确定的某条消息(因为微信两个时间戳有时不同, 所以这个接口效果不完美)
     * @param string $datetime
     * @param null $type
     * @param null $openid
     * @param string $session
     * @return bool
     */
    public function getOneMessage($datetime=NULL, $type=NULL, $openid=NULL, $session=null)
    {
        $this->processSession($session);
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
                        if ( $userInfo['fakeid']==$singleMessage[0]['fakeid'] && $datetime == $singleMessage[$i]['date_time'])
                        {
                            return $singleMessage[$i];
                        }

                    }
                    for($i=0;$i<$singleMessageCount;$i++)
                    {
                        if( $userInfo['fakeid']==$singleMessage[$i]['fakeid'] && $singleMessage[$i]['type']==$typeList[$type])
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
        $messageList = $this->getMessage(0, 40, 0);
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
     * @param int|number $groupid 用户组id
     * @param int $pagesize 分页大小
     * @param int $pageindex 页数
     * @param string $session 会话通道
     * @return Ambigous <boolean, array>
     */
    public function getFriendList($groupid=0, $pagesize=100, $pageindex=0, $session=null)
    {
        $this->processSession($session);
        $url = $this->protocol.'://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize='.$pagesize.'&pageidx='.$pageindex.'&type=0&groupid='.$groupid.'&token='.$this->webtoken.'&lang=zh_CN';
        $referer = $this->protocol."://mp.weixin.qq.com/cgi-bin/contactmanage?";
        $this->curlInit("single");
        $response = $this->_curlHttpObject->get($url, $referer, $this->_cookies[$session]);
        if ($match = Wechat::getTextArea($response,'friendsList : (', ').contacts,' ))
        {
            return json_decode($match, true);
        }
        else
        {
            return false;
        }
    }

    /**
     * 将用户放入制定的分组
     * @param array $fakeidsList
     * @param string $groupid
     * @param string $session
     * @return boolean 放入是否成功
     */
    public function putIntoGroup($fakeidsList, $groupid, $session=null)
    {
        $this->processSession($session);
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

    private static  function getTextArea($text,$str_start,$str_end){
        if(empty($text)||empty($str_start))
        {
            return false;
        }
        $start_pos=@strpos($text,$str_start);
        if($start_pos===false){
            return false;
        }
        $end_pos=strpos($text,$str_end, $start_pos);
        if($end_pos>$start_pos && $end_pos!==false)
        {
            $begin_pos=$start_pos+strlen($str_start);
            return substr($text, $begin_pos,$end_pos-$begin_pos);
        }
        else
        {
            return false;
        }
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
    public function setCookies($cookies, $session=null)
    {
        $this->processSession($session);
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
    public function setToken($token, $session=null)
    {
        $this->processSession($session);
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
    private function buildPositiveMsgFields($singleMessageFields)
    {
        if(!isset($singleMessageFields['type']))
        {
            $singleMessageFields['type'] = Wechat::MSGTYPE_TEXT;
        }
        $type = $this->getPositiveMsgType($singleMessageFields['type']);
        if(!$type)
        {
//            $type = $this->getPositiveMsgType(Wechat::MSGTYPE_TEXT);
            $singleMessageFields['type'] = Wechat::MSGTYPE_TEXT;
        }
        $singlepostfields = array();
        $singlepostfields['tofakeid'] = $singleMessageFields['fakeid'];
        $singlepostfields['error'] = 'false';
        $singlepostfields['token'] = $this->webtoken;
        $singlepostfields['ajax'] = 1;
        if(isset($singleMessageFields['imgcode'])&&!empty($singleMessageFields['imgcode']))
        {
            $singlepostfields['imgcode'] = $singleMessageFields['imgcode'];
        }
        switch($singleMessageFields['type'])
        {
            case Wechat::MSGTYPE_TEXT:
                $singlepostfields['type'] = 1;
                $singlepostfields['content'] = $singleMessageFields['content'];
                break;
            case Wechat::MSGTYPE_IMAGE:
                $singlepostfields['type'] = 2;
                $singlepostfields['fid'] = $singleMessageFields['content'];
                $singlepostfields['fileId'] = $singleMessageFields['content'];
                break;
            case Wechat::MSGTYPE_VOICE:
                $singlepostfields['type'] = 3;
                $singlepostfields['fid'] = $singleMessageFields['content'];
                $singlepostfields['fileId'] = $singleMessageFields['content'];
                break;
            case Wechat::MSGTYPE_VIDEO:
                $singlepostfields['type'] = 4;
                $singlepostfields['fid'] = $singleMessageFields['content'];
                $singlepostfields['fileId'] = $singleMessageFields['content'];
                break;
            case Wechat::MSGTYPE_NEWS:
                $singlepostfields['type'] = 10;
                $singlepostfields['appmsgid'] = $singleMessageFields['content'];
                $singlepostfields['fid'] = $singleMessageFields['content'];
                $singlepostfields['fileId'] = $singleMessageFields['content'];
                break;
            //TODO:增加物品和名片支持

        }
        return $singlepostfields;
    }

    /**
     * @name 获取指定消息类型的主动类型编号
     * @param string $msgType 消息类型，如 Wechat::MSGTYPE_TEXT
     * @return bool|int 正确返回值，否则返回false
     */
    private function getPositiveMsgType($msgType)
    {
//        if(array_keys(Wechat::$POSITIVE_MSGTYPE, $msgType))
//        {
            return Wechat::$POSITIVE_MSGTYPE[$msgType];
//        }
//        else
//        {
//            return false;
//        }
    }

    /**
     * @param $session
     */
    private function processSession(&$session)
    {
        if (empty($session)) {
            if (empty($this->wechatOptions['session'])) {
                $session = "default";
                $this->wechatOptions['session'] = "default";
            } else {
                $session = $this->wechatOptions['session'];
            }
        }
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
        CURLOPT_HEADER         => false,        // don't return headers
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
            CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
            CURLOPT_SSL_VERIFYPEER => false,        //
        );
        curl_setopt_array($this->singlequeue, $options);
        curl_setopt($this->singlequeue, CURLOPT_POSTFIELDS, $postfields);   // this are my post vars
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
     * @return $this
     */
    public function setRollLimitCount($limitCount) {
        $this->limitCount = $limitCount;
        return $this;
    }

    /**
     * 设置回调函数
     * @param field_type $_callback
     * @return $this
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
     * @param $header
     * @return $this
     */
    public function setHeader($header) {
        $this->_reqheader = array_merge($this->_reqheader, $header);
        return $this;
    }

    /**
     * @param resource $ch
     * @param array $reqheader
     * @return $this
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
