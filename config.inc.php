<?php
# config.inc.php
#
# The Tunez php config file

//
// Section 1: You really should configure these variables!
//

// Mysql details
$_CONF['mysql_dbhost'] = 'localhost';
$_CONF['mysql_dbuser'] = 'tunez';
$_CONF['mysql_dbpass'] = 'YOUR-DATABASE-PASSWORD';
$_CONF['mysql_dbname'] = 'tunez';

// This should point to the base Tunez directory (containing the config.inc.php
// file).  Make sure you leave the trailing slash in place!
$_CONF['path'] = '/path/to/directory/';

// This should point to the base URL for your site (trailing slash required)
$_CONF['url'] = "http://www.yourserver.com/site/";

// This is your theme of choice.. for now classic is the only thing on the
// way to being implemented so leave this alone for now! :)
$_CONF['theme'] = "classic";

// This should point to the location of your HTML directory You can
// move it anywhere just make sure to change this variable to point to
// where it is.
$_CONF['path_html'] = "/path/to/your/html/folder/";
#$_CONF['path_html'] = "/var/www";  // for debian (for example)

// These you probably don't have to mess with
$_CONF['path_system'] = $_CONF['path'] . 'system/';
$_CONF['path_layout'] = $_CONF['path_html'] . 'layout/' . $_CONF['theme'] . '/';

// This should remain as it is unless you have to rename the admin directory as
// something else because it is already in use
$_CONF['url_admin'] = $_CONF['url'] . "admin/";
$_CONF['url_images'] = $_CONF['url'] . "images/";

// Choose mode.  Refer to the INSTALL file for directions.
$_CONF['mode'] = "local-perl";  # tunezd.pl
#$_CONF['mode'] = "local-php";  # tunezd.php
#$_CONF['mode'] = "shout-perl"; # tunezd.pl
#$_CONF['mode'] = "shout-php";  # tunezd.php
#$_CONF['mode'] = "ices";       

# The path to the detach binary (which you should compile if
# you want to start/stop the daemon from the web or skip songs)
$_CONF['detach_binary'] = $_CONF['path'] . "detach-1.2/detach";

# The path to the smixer binary (which you should compile if
# you want to change the volume)
$_CONF['smixer_binary'] = $_CONF['path'] . "tmixer/smixer";

# The path to your PERL binary for calling tunezd.pl
# (shout-perl), (local-perl)
$_CONF['perl_binary'] = "/usr/bin/perl";

# The paths to your binaries for playing back in local mode
# (local-perl), (local-php)
$_CONF['mpg123_binary'] = "/usr/bin/mpg123";
$_CONF['ogg123_binary'] = "/usr/bin/ogg123";
#----------------------------------

# for Icecast v1.x and v2.x.  This should be the URL of your icecast stream.
$_CONF['icecast_URL'] = "http://www.yourserver.com:8000/ices";

# for Icecast Stream mode (v1.x)
$_CONF['shoutcast_binary'] = "/usr/bin/shout";
$_CONF['icecast_v1_host'] = "localhost";
$_CONF['icecast_v1_password'] = "hackme";
#-------------------------------

# for ices mode
$_CONF['ices_binary'] = "/usr/local/bin/ices";
#-------------------------------------------------------------------

# for (local-php) and (shout-php) modes
#       Location of the php executable with mysql support compiled in
$_CONF['php_binary'] = "/usr/local/bin/php";
#------------------------------------

# --------------------------
# General settings

# skip_sleeptime
#       Allows the admin to set an appropriate number of seconds to sleep after
#       a song skip occurs.  One second usually suffices for most systems although
#       some admins may be able to set this to 0 and not have any problems
$_CONF['skip_sleeptime'] = 1;

# mixer_devices
#       Allows the displaying and control of the listed mixing devices from the
#       admin_volume.php page
$_CONF['mixer_devices'] = Array(
    "Vol", "Pcm", "Line", "Mic", "CD"
);

# Be careful what you put in here.  If you want to prevent searching
# by uploader, etc. this is where you comment it out.
$_CONF['allowed_search_fields'] = Array(
        artist_name => "artists.artist_name",
        songtitle => "songs.songtitle",
        album_name => "albums.album_name",
        filename => "songs.filename",
        uploader_id => "songs.uploader_id",
        year => "songs.year",
        track => "songs.track",
        type => "songs.type"
        );

# These are field names used pretty much everywhere.  I grouped them
# here to make things easier to update in the future.  Eventually, I might
# partition them off into a language file of some type.
$_CONF['field_descriptions'] = Array(
        artist_name => "Artist",
        songtitle => "Song Title",
        album_name => "Album",
        timesPlayed => "Times Played",
        timestamp => "Played At",
        nrOfVotes => "Votes",
        uploader_id => "Uploader",
        year => "Year",
        track => "Track Number",
        type => "Filetype",
        filename => "Filename"
    );

# number_paths_updateDb [int]
#       The number of paths displayed on the updateDb page which allow users to
#       manually specify paths inside allowed directories to speed up updates.
#       ** Setting this to zero will disallow selective updates **
$_CONF['number_paths_updateDb'] = 10;

# typical_song_select [text]
#       These are the SQL rows which will be displayed on the main page when
#       doing a browse.  Power users may customize this with additional fields
#       if desired (refer to SQL specs)
$_CONF['typical_song_select'] = "song_id, status, update_id3, songtitle, artist_name, album_name";

# TIMEOUT_SECONDS [int]
#   How long a user can remain idle in seconds before they have to reauthenticate
define("TIMEOUT_SECONDS", 60*10);  // 10 minutes

# DEFAULT_SONGS_PER_PAGE [int]
#   This is the default number of songs a user will see per page if they
#   don't set this in their preferences.
define("DEFAULT_SONGS_PER_PAGE", 30);

# ----------------------------------------------------------------------
# Choose voting mode

$_CONF['voting_mode'] = "classic";
#$_CONF['voting_mode'] = "complex";
# complex is still in beta.. you're welcome to try it out if you want but you'll
# have to play around with the voting_rights table on your own (see vote.inc.php
# for guidance)

# ---------------------------------
# Choose government type
#
# Democracy is the Tunez default and is suggested
# If you want to play with socialism or define your own modes, you are welcome to
# If you come up with anything interesting, please post it to our message boards
# on sourceforge!  NOTE: If you use Perl you must override the PQueue.pm module
# with your own governmental query otherwise the webpage will display the queue
# properly but the songs played will be different!

#democracy:
#       METHOD: Everyone gets unlimited votes but can only vote once per song.
#               Votes are sorted by total number per song, then by how new the
#               song is.
$_CONF['government']="SELECT songs.song_id, songtitle, length, artists.artist_name,
    albums.album_name, count(*) AS votes, MIN(timestamp) as timestamp FROM queue
    LEFT JOIN songs ON queue.song_id=songs.song_id
    LEFT JOIN artists on songs.artist_id=artists.artist_id
    LEFT JOIN albums on songs.album_id=albums.album_id
    WHERE songs.status != 'offline' AND songs.status != 'delete'
    GROUP BY filename
    ORDER BY votes DESC, timestamp";

#socialism:
#       METHOD: Songs that are in the queue for 10 minutes get extra
#               Pseudo-Votes.  This is supposed to help make every artist the
#               same.  Ayn Rand proved this wouldn't work in Atlas Shrugged
/*
$_CONF['government'] = "SELECT songs.song_id, songtitle, length, artists.artist_name,
    albums.album_name, count(*) AS votes,
    count(*) + (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(timestamp))/(60*10) AS score FROM queue
    LEFT JOIN songs using(song_id)
    LEFT JOIN artists on songs.artist_id=artists.artist_id
    LEFT JOIN albums on songs.album_id=albums.album_id
    WHERE songs.status != 'offline' AND songs.status != 'delete'
    GROUP BY filename
    ORDER BY score DESC, timestamp";
*/
# ----------------------------------------------------------------------

# The types of file extensions you are looking to be added to the database
# (case doesn't matter).  Feel free to hack on support for whatever filetypes 
# you like/use but you may run into problems when trying to update tags.
$_CONF['valid_extensions'] = Array("mp3","ogg");

# Array for which directories to search through for mp3's (it will recurse
# down from this directory)
$_CONF['dirs'] = Array (
    "/your/audio/files",
    "/even/more/audio/files"
);

# If you want to ignore processing of a file when running admin_updateDb.php
# just add it to this array
$_CONF['ignore_list'] = array(
    );

# default_group_id
#       This is the default group id someone is assigned to when they signup
#       for an account and no email confirmation is required
$_CONF['default_group_id'] = 2;

# trim_songs [boolean]
#       FALSE, 0:
#               Songs are marked as being offline and their records are saved
#               if the file can't be found on the file system.  Later if the
#               file is rediscovered (ala NFS share comes back online or a
#               CDROM is reinserted the file is shown again during searches)
#       TRUE, 1:
#               Songs are deleted from the database when they dissappear off
#               of the file system (old tunez behavior).
$_CONF['trim_songs'] = 0;

# Authenticate the user w/ e-mail + confirmation code
$_CONF['email_authentication'] = 0;

# Options for HTTP uploading
# enable_uploads
#       0 means no uploads are allowed
#       1 means uploads are allowed
$_CONF['enable_uploads'] = 0;
# copying_root_directory is the root path where the mp3's are copied to
# THIS IS IMPORTANT.  You should set this to match an entry in the $mp3dir
# array!!!!
$_CONF['copying_root_dir'] = "/tmp";
# distribute_with_full_paths means that if set, directories will be created to
# house the newly uploaded mp3's in the fashion of distribute_style below.
$_CONF['distribute_with_full_paths'] = 0;
// distribute_style allows you to sort uploaded mp3's into a tree based
// on their id3 tags onto your filesystem.
//
// %a = Artist Name
// %n = Album Name
// %s = Song Title
// %t = Track Number
// %g = Genre
$_CONF['distribute_style'] = "/%a/%n/%s";

# random_query controls how songs are picked when nothing is voted 
# for in the queue
# * Set to unweighted for a completely random selection provided
#   the song is not blocked from random play
# * Set to weighted for a weighted random choice based on the 
#   number of times each song has been played
$_CONF['random_query'] = "unweighted";

?>
