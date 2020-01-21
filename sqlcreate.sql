-- MySQL dump 10.11
-- Database: tnuc_wtlite
-- Server version	5.0.85-log

--
-- Table structure for table `chars`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `chars` (
  `uid` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(35) collate utf8_unicode_ci NOT NULL,
  `level` int(11) NOT NULL default '0',
  `voc` char(2) collate utf8_unicode_ci NOT NULL,
  `updated` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=334096 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;


--
-- Table structure for table `cp`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `cp` (
  `pid` int(10) unsigned NOT NULL,
  `cid` int(10) unsigned NOT NULL,
  `issec` tinyint(1) NOT NULL default '0',
  `comment` tinytext collate utf8_unicode_ci,
  PRIMARY KEY  (`pid`,`cid`),
  KEY `ind_cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;


--
-- Table structure for table `projects`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `projects` (
  `uid` int(11) unsigned NOT NULL auto_increment,
  `title` varchar(60) collate utf8_unicode_ci NOT NULL,
  `server` varchar(15) collate utf8_unicode_ci NOT NULL,
  `updated` int(11) unsigned NOT NULL default '0',
  `upw` char(32) collate utf8_unicode_ci default NULL,
  `apw` char(32) collate utf8_unicode_ci NOT NULL,
  `motd` tinytext collate utf8_unicode_ci NOT NULL,
  `str_m` varchar(25) collate utf8_unicode_ci NOT NULL default 'Main Chars',
  `str_s` varchar(25) collate utf8_unicode_ci NOT NULL default 'Second Chars',
  `capacity` smallint(6) unsigned NOT NULL default '1500',
  `hideoffline` tinyint(1) NOT NULL default '0',
  `pw_char` varchar(25) collate utf8_unicode_ci NOT NULL default '',
  `pw_challenge` char(20) collate utf8_unicode_ci NOT NULL default '',
  PRIMARY KEY  (`uid`),
  UNIQUE KEY `title` (`title`)
) ENGINE=MyISAM AUTO_INCREMENT=19983 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

