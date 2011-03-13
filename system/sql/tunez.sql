#
# Table structure for table `access`
#

CREATE TABLE access (
  user_id smallint(6) NOT NULL default '0',
  group_id smallint(6) NOT NULL default '0',
  KEY user_id (user_id),
  KEY group_id (group_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `albums`
#

CREATE TABLE albums (
  album_id mediumint(8) unsigned NOT NULL auto_increment,
  album_name varchar(254) NOT NULL default '',
  small_album_cover varchar(255) default NULL,
  large_album_cover varchar(255) default NULL,
  amazon_url varchar(255) default NULL,
  PRIMARY KEY  (album_id),
  UNIQUE KEY album_id (album_id),
  KEY id (album_id),
  KEY album_name (album_name)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `artists`
#

CREATE TABLE artists (
  artist_id mediumint(9) unsigned NOT NULL auto_increment,
  artist_name varchar(254) NOT NULL default '',
  PRIMARY KEY  (artist_id),
  UNIQUE KEY artist_id (artist_id),
  KEY id (artist_id),
  KEY artist_name (artist_name)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `banked`
#

CREATE TABLE banked (
  vr_id int(11) NOT NULL default '0',
  user int(11) NOT NULL default '0'
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `caused`
#

CREATE TABLE caused (
  history_id int(11) NOT NULL default '0',
  play_id int(11) NOT NULL default '0',
  KEY history_id (history_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `genre`
#

CREATE TABLE genre (
  genre_id tinyint(11) unsigned NOT NULL default '0',
  genre_name tinytext NOT NULL,
  KEY genre_id (genre_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `groups`
#

CREATE TABLE groups (
  group_id smallint(11) NOT NULL auto_increment,
  group_name varchar(255) NOT NULL default '',
  PRIMARY KEY  (group_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `history`
#

CREATE TABLE history (
  history_id mediumint(6) NOT NULL auto_increment,
  song_id int(11) unsigned NOT NULL default '0',
  user_id smallint(4) unsigned NOT NULL default '0',
  timestamp timestamp(14) NOT NULL,
  PRIMARY KEY  (history_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `lyrics`
#

CREATE TABLE lyrics (
  song_id int(11) unsigned NOT NULL default '0',
  lyrics text NOT NULL,
  PRIMARY KEY  (song_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `np`
#

CREATE TABLE np (
  song_id int(11) NOT NULL default '0',
  play_id int(11) NOT NULL default '0',
  started varchar(16) NOT NULL default '',
  wasrandom mediumint(9) NOT NULL default '0'
) TYPE=MyISAM PACK_KEYS=1;
# --------------------------------------------------------

#
# Table structure for table `pendingusers`
#

CREATE TABLE pendingusers (
  pending_user_id smallint(5) unsigned NOT NULL default '0',
  confirmation_code varchar(20) NOT NULL default '',
  timestamp timestamp(14) NOT NULL
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `permissions`
#

CREATE TABLE permissions (
  ug_id smallint(5) unsigned NOT NULL default '0',
  who enum('user','group','everyone') NOT NULL default 'user',
  p_upload tinyint(1) NOT NULL default '0',
  p_modify_after_upload tinyint(1) NOT NULL default '0',
  p_unvote tinyint(1) NOT NULL default '0',
  p_see_hidden tinyint(1) NOT NULL default '0',
  p_random_skip tinyint(1) NOT NULL default '0',
  p_skip tinyint(1) NOT NULL default '0',
  p_select_edit tinyint(4) NOT NULL default '0',
  p_change_perms tinyint(1) NOT NULL default '0',
  p_updateDb tinyint(1) NOT NULL default '0',
  p_sync tinyint(1) NOT NULL default '0',
  p_vote tinyint(1) NOT NULL default '0',
  p_daemon tinyint(1) NOT NULL default '0',
  p_volume tinyint(1) NOT NULL default '0',
  p_download tinyint(1) NOT NULL default '0',
  p_select_vote tinyint(1) NOT NULL default '0',
  KEY ug_id (ug_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `play`
#

CREATE TABLE play (
  play_id int(11) NOT NULL auto_increment,
  song_id int(11) NOT NULL default '0',
  timestamp timestamp(14) NOT NULL,
  PRIMARY KEY  (play_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `preferences`
#

CREATE TABLE preferences (
  user_id smallint(4) NOT NULL default '0',
  songsperpage smallint(6) NOT NULL default '50',
  PRIMARY KEY  (user_id)
) TYPE=MyISAM PACK_KEYS=1;
# --------------------------------------------------------

#
# Table structure for table `priority_queue`
#

CREATE TABLE priority_queue (
  priority smallint(6) NOT NULL default '0',
  song_id int(11) NOT NULL default '0',
  KEY priority (priority)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `queue`
#

CREATE TABLE queue (
  queue_id int(11) NOT NULL auto_increment,
  history_id int(11) NOT NULL default '0',
  song_id int(11) NOT NULL default '0',
  user_id int(11) NOT NULL default '0',
  timestamp timestamp(14) NOT NULL,
  PRIMARY KEY  (queue_id)
) TYPE=MyISAM PACK_KEYS=1;
# --------------------------------------------------------

#
# Table structure for table `sessions`
#

CREATE TABLE sessions (
  unique_session_num int(11) NOT NULL auto_increment,
  user_id smallint(6) NOT NULL default '0',
  session_id varchar(255) NOT NULL default '',
  ip varchar(255) NOT NULL default '',
  login_time datetime NOT NULL default '0000-00-00 00:00:00',
  last_refresh_time datetime default NULL,
  status enum('logged_in','logged_out','timed_out') NOT NULL default 'logged_in',
  logout_time datetime default NULL,
  saved tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (unique_session_num)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `songs`
#

CREATE TABLE songs (
  song_id int(11) unsigned NOT NULL auto_increment,
  artist_id mediumint(8) unsigned NOT NULL default '0',
  songtitle varchar(255) NOT NULL default '',
  filename text NOT NULL,
  timesPlayed int(11) NOT NULL default '0',
  playInRandomMode tinyint(1) NOT NULL default '1',
  album_id mediumint(8) unsigned NOT NULL default '0',
  year year(4) default NULL,
  genre_id tinyint(11) unsigned NOT NULL default '255',
  length time default NULL,
  track tinyint(4) unsigned default NULL,
  uploader_id varchar(255) default NULL,
  type enum('mp3','ogg') NOT NULL default 'mp3',
  bitrate float default NULL,
  bitrate_mode enum('cbr','vbr') default 'cbr',
  mode enum('stereo','joint stereo','dual channel','mono') default NULL,
  frequency enum('0','8000','11025','12000','16000','22050','24000','32000','44100','48000') default NULL,
  layer enum('0','1','2','3') default NULL,
  status enum('normal','delete','offline','hide') NOT NULL default 'normal',
  update_id3 tinyint(1) NOT NULL default '0',
  filesize int(11) NOT NULL default '0',
  PRIMARY KEY  (song_id),
  KEY songtitle (songtitle),
  KEY artist_id (artist_id),
  KEY album_id (album_id)
) TYPE=MyISAM PACK_KEYS=1;
# --------------------------------------------------------

#
# Table structure for table `string_groups`
#

CREATE TABLE string_groups (
  group_id tinyint(4) NOT NULL default '0',
  group_description varchar(255) NOT NULL default ''
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `string_map`
#

CREATE TABLE string_map (
  string_id tinyint(4) NOT NULL default '0',
  group_id tinyint(4) NOT NULL default '0'
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `strings`
#

CREATE TABLE strings (
  string_id tinyint(4) NOT NULL auto_increment,
  song_text text NOT NULL,
  KEY string_id (string_id)
) TYPE=MyISAM;
# --------------------------------------------------------

#
# Table structure for table `users`
#

CREATE TABLE users (
  user_id smallint(4) unsigned NOT NULL auto_increment,
  user varchar(32) NOT NULL default '',
  pw varchar(255) NOT NULL default '',
  email varchar(100) default NULL,
  PRIMARY KEY  (user_id),
  KEY user_id (user_id)
) TYPE=MyISAM PACK_KEYS=1;
# --------------------------------------------------------

#
# Table structure for table `voting_rights`
#

CREATE TABLE voting_rights (
  vr_id int(11) NOT NULL auto_increment,
  active tinyint(4) NOT NULL default '0',
  who enum('user','group','everyone') NOT NULL default 'user',
  shared tinyint(4) NOT NULL default '0',
  ug_id int(11) NOT NULL default '0',
  vote_type enum('seconds','votes') NOT NULL default 'seconds',
  MODE enum('bank','window') NOT NULL default 'bank',
  period time default NULL,
  votes int(11) default '0',
  votes_banked int(11) default NULL,
  votes_max int(11) default NULL,
  time_added datetime default NULL,
  PRIMARY KEY  (vr_id),
  KEY ug_id (ug_id)
) TYPE=MyISAM;

