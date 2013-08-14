SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for 'weixin_fakelist'
-- ----------------------------
DROP TABLE IF EXISTS 'weixin_fakelist';
CREATE TABLE 'weixin_fakelist' (
  'id' int(10) unsigned NOT NULL AUTO_INCREMENT,
  'fakeid' char(11) NOT NULL,
  'nickname' varchar(40) DEFAULT NULL,
  'flag' tinyint(4) DEFAULT NULL,
  PRIMARY KEY ('id'),
  UNIQUE KEY 'id' ('id'),
  UNIQUE KEY 'fakeid' ('fakeid')
) ENGINE=MyISAM AUTO_INCREMENT=84 DEFAULT CHARSET=utf8;


-- ----------------------------
-- Table structure for 'weixin_followusers'
-- ----------------------------
DROP TABLE IF EXISTS 'weixin_followusers';
CREATE TABLE 'weixin_followusers' (
  'id' int(10) unsigned NOT NULL AUTO_INCREMENT,
  'openid' char(28) DEFAULT NULL,
  'fakeid' char(11) DEFAULT NULL,
  'name' varchar(50) DEFAULT NULL COMMENT '用户昵称',
  'followtime' timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '关注时间',
  'subscribed' tinyint(1) DEFAULT '1' COMMENT '关注标记',
  'gender' tinyint(4) DEFAULT NULL COMMENT '性别',
  'flag' tinyint(4) DEFAULT NULL,
  PRIMARY KEY ('id')
) ENGINE=MyISAM AUTO_INCREMENT=29 DEFAULT CHARSET=utf8 COMMENT='微信关注用户信息表';

