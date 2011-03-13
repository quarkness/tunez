<?php
# who_play.php
#
# This tells users who voted for a song that is playing

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

require("tunez.inc.php");
$title = "Who caused that?!";
require("header.inc.php");
require_once($_CONF['path_system'] . "classes/song.class.php");

if(!get_magic_quotes_gpc()) {
    $_GET['play_id'] = addslashes($_GET['play_id']);
}
$play_id = $_GET['play_id'];

$kweerie = "SELECT play.play_id, songs.song_id, artists.artist_name, songs.songtitle,
                   DATE_FORMAT(play.timestamp,'%W, %M %D (%l:%i %p)') as timestamp, timesPlayed FROM play
            LEFT JOIN songs ON play.song_id=songs.song_id
            LEFT JOIN artists ON songs.artist_id=artists.artist_id
            WHERE play.play_id='$play_id'";

$result = tunez_query($kweerie);
$play = mysql_fetch_object($result);

$song = new song($play->song_id, NULL);
$song->read_data_from_db();
$song->print_info();

echo "<p>The following people were responsible for playing <b>$play->artist_name - $play->songtitle</b> at $play->timestamp</p>";

$kweerie  = "select *,user, DATE_FORMAT(h.timestamp,'%m-%e-%y %H:%i') as timestamp ";
$kweerie .= "from caused c LEFT JOIN history h ON c.history_id=h.history_id ";
$kweerie .= "LEFT JOIN songs s ON h.song_id=s.song_id ";
$kweerie .= "LEFT JOIN users u ON u.user_id=h.user_id ";
$kweerie .= " WHERE c.play_id='$play_id'";

$result = tunez_query($kweerie);

if (mysql_num_rows($result) > 0)
{
	echo "<ol>";
	while ($user = mysql_fetch_object($result))
	{
		echo "<li>$user->user ($user->timestamp)";
	}
	echo "</ol>";
	mysql_free_result($result);	
}
else
{
	echo "<p>Tunez itself ;)";
}
require("footer.inc.php");
?>
