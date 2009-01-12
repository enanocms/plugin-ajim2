<?php
/**!info**
{
  "Plugin Name"  : "AjIM 2.0",
  "Plugin URI"   : "http://enanocms.org/plugin/ajim2",
  "Description"  : "An AJAX shoutbox and more - re-implemented for Enano 1.2",
  "Author"       : "Dan Fuhry",
  "Version"      : "1.9.1",
  "Author URI"   : "http://enanocms.org/"
}
**!*/

/**!install dbms="mysql"; **
CREATE TABLE {{TABLE_PREFIX}}ajim2 (
  message_id int unsigned NOT NULL auto_increment,
  user_id mediumint NOT NULL DEFAULT 1,
  username varchar(20) NOT NULL DEFAULT 'Guest',
  website varchar(128) NOT NULL DEFAULT '',
  message text NOT NULL DEFAULT '',
  message_time int unsigned NOT NULL DEFAULT 0,
  message_update_time int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY ( message_id )
);
**!*/

/**!install dbms="postgresql"; **
CREATE TABLE {{TABLE_PREFIX}}ajim2 (
  message_id SERIAL,
  user_id int NOT NULL DEFAULT 1,
  username varchar(20) NOT NULL DEFAULT 'Guest',
  website varchar(128) NOT NULL DEFAULT '',
  message text NOT NULL DEFAULT '',
  message_time int NOT NULL DEFAULT 0,
  message_update_time int NOT NULL DEFAULT 0,
  PRIMARY KEY ( message_id )
);
**!*/

/**!uninstall dbms="mysql"; **
DROP TABLE IF EXISTS {{TABLE_PREFIX}}ajim2;
**!*/

/**!uninstall dbms="postgresql"; **
DROP TABLE {{TABLE_PREFIX}}ajim2;
**!*/

/**!language**
!include "plugins/ajim/language.json"
**!*/

require(ENANO_ROOT . '/plugins/ajim/enanosetup.php');
require(ENANO_ROOT . '/plugins/ajim/server.php');
require(ENANO_ROOT . '/plugins/ajim/client.php');

