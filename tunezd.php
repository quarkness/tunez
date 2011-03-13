<?php
# tunezd.php
#
# This is the Tunez PHP daemon

/*
 * tunez
 *
 * Copyright (C) 2003, Ivo van Doesburg <idoesburg@outdare.nl>
 *  
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */

error_reporting(E_ALL ^ E_NOTICE);
$justdb = TRUE;

/* YOU MUST SET THIS TO YOUR tunez.inc.php path!!! */
require("/path/to/your/tunez.inc.php");
/***************************************************/


require_once($_CONF['path_system'] . "classes/song.class.php");

if ($_CONF['mode'] != "shout-php" && $_CONF['mode'] != "local-php") {
    die("You should not be using this php script unless you are in shout-php or local-php mode");
}

$queue = new PQueue($_CONF['random_query'], $_CONF['valid_extensions']);
$queue->generate_from_votes();

while(1) { 
    $queue->read();
    $songid = $queue->dequeue();
    $song = new song($songid, NULL);
    $song->read_data_from_db("filename,type");

    if ($_CONF['mode'] == "local-php") {
        if($song->type == "mp3" || $song->type == "id3")
            $cmd = $_CONF['mpg123_binary'] . " -b 1024 \"$song->filename\" > /dev/null 2> /dev/null";
        elseif($song->type == "ogg")
            $cmd = $_CONF['ogg123_binary'] . " -b 1024 \"$song->filename\" > /dev/null 2> /dev/null";
        else //assuming it's an MP3
            $cmd = $_CONF['mpg123_binary']  . " -b 1024 \"$song->filename\" > /dev/null 2> /dev/null";
    }
    elseif ($_CONF['mode']=="shout-php")
        $cmd = $_CONF['shoutcast_binary'] . " -x -3 -t -P " . $_CONF['icecast_v1_password'] . " " 
            . $_CONF['icecast_v1_host'] . " \"$song->filename\" > /dev/null 2> /dev/null";
    else
        die("invalid mode");
    system($cmd);
}

mysql_close();
?>

