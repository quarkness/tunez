<?php
# admin_sync.php
#
# Performs file maintenance and writes tags back to MP3/Ogg's that need to
# be changed (as shown in the database).

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

$NoRefresh = true;
require("../tunez.inc.php");
require($_CONF['path_system'] . "classes/song.class.php");
if (!($_SESSION['perms']['p_sync'])) {
    header(access_denied());
    die;
}
require($_CONF['path_html'] . "database.inc.php");

$nrOfSongsBeforeUpdate = nrOfSongs();

$title = "Writing ID3 tags back to songs";
require("../header.inc.php");

# update ID3 records...
if (!empty($_GET['song_id'])) {
    $song_id = (int) $_GET['song_id'];
    $kweerie = "SELECT * from songs LEFT JOIN artists on 
        songs.artist_id=artists.artist_id LEFT JOIN albums 
        on songs.album_id=albums.album_id where song_id=$song_id";
}
else {
    $kweerie = "SELECT * from songs LEFT JOIN artists on 
        songs.artist_id=artists.artist_id LEFT JOIN albums on 
        songs.album_id=albums.album_id where update_id3=1 AND
        status!=\"offline\" ORDER BY songs.filename";
}
$result = mysql_query($kweerie) or die(mysql_error());
while ($row = mysql_fetch_object($result))
{
    $filename = $row->filename;
    print("<P>Filename: $filename<br>");
    $song = new Song($row->song_id, NULL);
    $song->read_data_from_db();
    if(!($song->write_data_to_file())) {
        print "<font color=\"red\">Error writing to <i>$filename</i></font><br>";
    }
    else {
        $song->up_to_date_id3(1);
        print "<b>Write sucessfull</b><br>";
    }
}
mysql_free_result($result);

if(empty($_GET['song_id'])) {
# we aren't just syncing a song_id so let's check for songs to
# delete...
    $kweerie = "SELECT song_id,filename from songs where status=\"delete\"";
    $result = tunez_query($kweerie);
    while ($row = mysql_fetch_object($result))
    {
        print("<p>Deleting filename: $row->filename</p>");
        $song = new Song($row->song_id, $row->filename);
        if(file_exists($row->filename)) {
            if(!unlink($row->filename)) {
                print("<font color=red>Error deleting $row->filename</font><br>");
                continue;
            }
            $song->delete_from_db();
        }
        else {
            // file must have been deleted manually
            $song->delete_from_db();        
        }
    }
    mysql_free_result($result);
}

require("../footer.inc.php");
?>
