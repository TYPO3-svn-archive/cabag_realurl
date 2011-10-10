
CREATE TABLE tx_realurl_redirects (
  uid int(11) NOT NULL auto_increment,
  pid int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  crdate int(11) DEFAULT '0' NOT NULL,
  cruser_id int(11) DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  deleted tinyint(4) DEFAULT '0' NOT NULL,
  url text NOT NULL,
  url_regular_expression text NOT NULL,
  destination text NOT NULL,
  destination_typolink text NOT NULL,
  last_referer text NOT NULL,
  counter int(11) DEFAULT '0' NOT NULL,
  has_moved int(11) DEFAULT '0' NOT NULL,
  domain int(11) DEFAULT '0' NOT NULL,
  last_time int(11) DEFAULT '0' NOT NULL,
  
  PRIMARY KEY (uid),
  KEY parent (pid)
);

#
# Table structure for table 'tx_realurl_redirects_sys_domain_mm'
# 
#
CREATE TABLE tx_realurl_redirects_sys_domain_mm (
  uid_local int(11) DEFAULT '0' NOT NULL,
  uid_foreign int(11) DEFAULT '0' NOT NULL,
  tablenames varchar(30) DEFAULT '' NOT NULL,
  sorting int(11) DEFAULT '0' NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);
